/**
 * EssayImageAnnotator — desenho sobre imagem com Pointer Events (mouse, touch, stylus).
 */
(function (global) {
    'use strict';

    var COLORS = ['#ef4444', '#2563eb', '#16a34a', '#ca8a04', '#9333ea', '#111827'];
    var DEFAULT_COLOR = COLORS[0];
    var DEFAULT_WIDTH = 3;
    function EssayImageAnnotator(container, options) {
        this.container = container;
        this.options = options || {};
        this.readonly = !!this.options.readonly;
        this.imageUrl = this.options.imageUrl || '';
        this.onChange = typeof this.options.onChange === 'function' ? this.options.onChange : null;

        this.strokes = [];
        this.texts = [];
        this.history = [];
        this.currentStroke = null;
        this.tool = 'pen';
        this.color = DEFAULT_COLOR;
        this.lineWidth = DEFAULT_WIDTH;
        this.imageNaturalWidth = 0;
        this.imageNaturalHeight = 0;
        this.imageReady = false;
        this.drawScale = 1;
        this.textEditorEl = null;

        this._build();
        if (this.imageUrl) {
            this._loadImage(this.imageUrl);
        }
        if (this.options.initialData) {
            this.loadAnnotations(this.options.initialData);
        }
    }

    EssayImageAnnotator.prototype._bindToolbar = function () {
        if (!this.toolbar || this.readonly) return;
        var self = this;
        this.toolbar.addEventListener('click', function (e) {
            var toolBtn = e.target.closest('[data-tool]');
            if (toolBtn) {
                self.tool = toolBtn.getAttribute('data-tool');
                self.toolbar.querySelectorAll('[data-tool]').forEach(function (b) {
                    b.classList.toggle('is-active', b === toolBtn);
                });
                self._updateCanvasCursor();
                return;
            }
            var colorBtn = e.target.closest('[data-color]');
            if (colorBtn) {
                self.color = colorBtn.getAttribute('data-color');
                self.toolbar.querySelectorAll('[data-color]').forEach(function (b) {
                    b.classList.toggle('is-active', b === colorBtn);
                });
                return;
            }
            var action = e.target.closest('[data-action]');
            if (!action) return;
            e.preventDefault();
            e.stopPropagation();
            var actionName = action.getAttribute('data-action');
            if (actionName === 'undo') {
                self.undo();
                return;
            }
            if (actionName === 'clear' && confirm('Limpar todos os rabiscos?')) self.clear();
        });
        this._updateUndoButton();
    };

    EssayImageAnnotator.prototype._build = function () {
        this.container.innerHTML = '';

        var toolbarId = this.options.toolbarId || this.container.getAttribute('data-toolbar-id');
        this.toolbar = toolbarId ? document.getElementById(toolbarId) : null;
        this._bindToolbar();

        this.stage = document.createElement('div');
        this.stage.className = 'essay-annotator-stage';
        this.inner = document.createElement('div');
        this.inner.className = 'essay-annotator-inner';
        this.img = document.createElement('img');
        this.img.className = 'essay-annotator-image';
        this.img.alt = 'Redação';
        this.img.draggable = false;
        this.canvas = document.createElement('canvas');
        this.canvas.className = 'essay-annotator-canvas';
        this.canvas.style.touchAction = 'none';
        this.inner.appendChild(this.img);
        this.inner.appendChild(this.canvas);
        this.stage.appendChild(this.inner);
        this.container.appendChild(this.stage);

        if (this.readonly) {
            this.canvas.style.pointerEvents = 'none';
        } else {
            this._bindPointerEvents();
        }

        this._resizeObserver = typeof ResizeObserver !== 'undefined'
            ? new ResizeObserver(this._redraw.bind(this))
            : null;
        if (this._resizeObserver) {
            this._resizeObserver.observe(this.img);
        }
        this.stage.addEventListener('scroll', this._redraw.bind(this));
        window.addEventListener('resize', this._redraw.bind(this));
        this._updateCanvasCursor();
    };

    EssayImageAnnotator.prototype._updateCanvasCursor = function () {
        if (!this.canvas || this.readonly) return;
        this.canvas.classList.toggle('tool-text', this.tool === 'text');
    };

    EssayImageAnnotator.prototype._loadImage = function (url) {
        var self = this;
        var onReady = function () {
            self.imageNaturalWidth = self.img.naturalWidth || self.img.width;
            self.imageNaturalHeight = self.img.naturalHeight || self.img.height;
            self.imageReady = true;
            requestAnimationFrame(function () {
                self._syncCanvasSize();
                self._redraw();
            });
        };
        this.img.onload = onReady;
        this.img.src = url;
        if (this.img.complete && this.img.naturalWidth > 0) {
            onReady();
        }
    };

    EssayImageAnnotator.prototype._getImageRenderBox = function () {
        var rect = this.img.getBoundingClientRect();
        var nw = this.imageNaturalWidth || this.img.naturalWidth || rect.width || 1;
        var nh = this.imageNaturalHeight || this.img.naturalHeight || rect.height || 1;
        var scale = Math.min(rect.width / nw, rect.height / nh);
        if (!isFinite(scale) || scale <= 0) {
            scale = 1;
        }
        var width = nw * scale;
        var height = nh * scale;
        return {
            left: rect.left + (rect.width - width) / 2,
            top: rect.top + (rect.height - height) / 2,
            width: Math.max(1, width),
            height: Math.max(1, height)
        };
    };

    EssayImageAnnotator.prototype._getImageLayoutSize = function () {
        var rect = this.img.getBoundingClientRect();
        return {
            width: Math.max(1, Math.round(rect.width || this.img.offsetWidth || 1)),
            height: Math.max(1, Math.round(rect.height || this.img.offsetHeight || 1))
        };
    };

    EssayImageAnnotator.prototype._syncCanvasSize = function () {
        if (!this.imageReady) return;

        var layout = this._getImageLayoutSize();
        var w = layout.width;
        var h = layout.height;
        var dpr = window.devicePixelRatio || 1;

        this.canvas.width = Math.max(1, Math.round(w * dpr));
        this.canvas.height = Math.max(1, Math.round(h * dpr));
        this.canvas.style.width = w + 'px';
        this.canvas.style.height = h + 'px';
        this.displayWidth = w;
        this.displayHeight = h;
        this.drawScale = dpr;

        var ctx = this.canvas.getContext('2d');
        ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    };

    EssayImageAnnotator.prototype._bindPointerEvents = function () {
        var self = this;
        this.canvas.addEventListener('pointerdown', function (e) {
            if (self.readonly) return;
            e.preventDefault();
            self.canvas.setPointerCapture(e.pointerId);
            var pt = self._pointerToNormalized(e);
            if (self.tool === 'text') {
                self._openTextEditorAt(pt.x, pt.y);
                return;
            }
            self._syncCanvasSize();
            self.currentStroke = {
                id: 'stroke-' + Date.now(),
                tool: self.tool,
                color: self.tool === 'eraser' ? '#ffffff' : self.color,
                width: self.tool === 'eraser' ? 14 : self.lineWidth,
                displayWidth: self.displayWidth,
                points: [[pt.x, pt.y]],
                pressure: e.pressure || 0.5
            };
            self._updateUndoButton();
        });
        this.canvas.addEventListener('pointermove', function (e) {
            if (!self.currentStroke) return;
            e.preventDefault();
            var pt = self._pointerToNormalized(e);
            self.currentStroke.points.push([pt.x, pt.y]);
            self._redraw(self.currentStroke);
        });
        var endStroke = function (e) {
            if (!self.currentStroke) return;
            try { self.canvas.releasePointerCapture(e.pointerId); } catch (err) {}
            if (self.currentStroke.points.length > 1) {
                self.strokes.push(self.currentStroke);
                self._recordAction('stroke', self.currentStroke.id);
                self._notifyChange();
            }
            self.currentStroke = null;
            self._redraw();
            self._updateUndoButton();
        };
        this.canvas.addEventListener('pointerup', endStroke);
        this.canvas.addEventListener('pointercancel', endStroke);
    };

    EssayImageAnnotator.prototype._pointerToNormalized = function (e) {
        var box = this._getImageRenderBox();
        if (!box.width || !box.height) {
            return { x: 0, y: 0 };
        }
        var x = (e.clientX - box.left) / box.width;
        var y = (e.clientY - box.top) / box.height;
        return {
            x: Math.max(0, Math.min(1, x)),
            y: Math.max(0, Math.min(1, y))
        };
    };

    EssayImageAnnotator.prototype._closeTextEditor = function (save) {
        if (!this.textEditorEl) return;
        var wrap = this.textEditorEl;
        var textarea = wrap.querySelector('textarea');
        var text = textarea ? textarea.value.trim() : '';
        var meta = wrap._annotatorMeta || null;
        wrap.remove();
        this.textEditorEl = null;
        if (save && text && meta) {
            var textId = 'text-' + Date.now();
            this.texts.push({
                id: textId,
                x: meta.x,
                y: meta.y,
                text: text,
                color: meta.color,
                fontSizeRatio: meta.fontSizeRatio
            });
            this._recordAction('text', textId);
            this._redraw();
            this._notifyChange();
            this._updateUndoButton();
        }
    };

    EssayImageAnnotator.prototype._openTextEditorAt = function (x, y) {
        var self = this;
        this._syncCanvasSize();
        this._closeTextEditor(false);

        var wrap = document.createElement('div');
        wrap.className = 'essay-annotator-text-editor';
        wrap.setAttribute('role', 'dialog');
        wrap.setAttribute('aria-label', 'Comentário na imagem');

        var left = Math.max(4, Math.min(x * this.displayWidth, this.displayWidth - 170));
        var top = Math.max(4, Math.min(y * this.displayHeight, this.displayHeight - 120));
        wrap.style.left = left + 'px';
        wrap.style.top = top + 'px';
        wrap.style.borderColor = this.color;

        var textarea = document.createElement('textarea');
        textarea.placeholder = 'Digite o comentário...';
        textarea.style.color = this.color;
        textarea.style.caretColor = this.color;

        var hint = document.createElement('div');
        hint.className = 'essay-annotator-text-editor-hint';
        hint.textContent = 'Enter para confirmar · Shift+Enter nova linha · Esc cancelar';

        var actions = document.createElement('div');
        actions.className = 'essay-annotator-text-editor-actions';

        var btnCancel = document.createElement('button');
        btnCancel.type = 'button';
        btnCancel.className = 'essay-annotator-text-editor-cancel';
        btnCancel.textContent = 'Cancelar';

        var btnOk = document.createElement('button');
        btnOk.type = 'button';
        btnOk.className = 'essay-annotator-text-editor-ok';
        btnOk.textContent = 'Inserir';
        btnOk.style.background = this.color;

        actions.appendChild(btnCancel);
        actions.appendChild(btnOk);
        wrap.appendChild(textarea);
        wrap.appendChild(hint);
        wrap.appendChild(actions);

        wrap._annotatorMeta = {
            x: x,
            y: y,
            color: this.color,
            fontSizeRatio: Math.max(0.018, Math.min(0.04, 16 / Math.max(this.displayWidth, 1)))
        };

        btnCancel.addEventListener('click', function () { self._closeTextEditor(false); });
        btnOk.addEventListener('click', function () { self._closeTextEditor(true); });
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                e.preventDefault();
                self._closeTextEditor(false);
            } else if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                self._closeTextEditor(true);
            }
        });

        this.inner.appendChild(wrap);
        this.textEditorEl = wrap;
        setTimeout(function () { textarea.focus(); }, 0);
    };

    EssayImageAnnotator.prototype._notifyChange = function () {
        if (this.onChange) this.onChange(this.getAnnotations());
    };

    EssayImageAnnotator.prototype._redraw = function (previewStroke) {
        if (!this.imageReady) return;
        this._syncCanvasSize();
        var ctx = this.canvas.getContext('2d');
        ctx.setTransform(this.drawScale || 1, 0, 0, this.drawScale || 1, 0, 0);
        ctx.clearRect(0, 0, this.displayWidth, this.displayHeight);

        var strokes = this.strokes.slice();
        if (previewStroke) strokes.push(previewStroke);

        var w = this.displayWidth;
        var h = this.displayHeight;
        strokes.forEach(function (stroke) {
            EssayImageAnnotator._drawStroke(ctx, stroke, w, h);
        });
        this.texts.forEach(function (item) {
            EssayImageAnnotator._drawText(ctx, item, w, h);
        });
    };

    EssayImageAnnotator._strokeLineWidth = function (stroke, w) {
        var base = stroke.width || DEFAULT_WIDTH;
        if (stroke.tool === 'eraser') {
            base = stroke.width || 14;
        }
        var ref = stroke.displayWidth || w;
        if (ref > 0 && w > 0 && ref !== w) {
            return Math.max(1, base * (w / ref));
        }
        return base;
    };

    EssayImageAnnotator._drawStroke = function (ctx, stroke, w, h) {
        var points = stroke.points || [];
        if (points.length < 2) return;
        ctx.save();
        ctx.lineCap = 'round';
        ctx.lineJoin = 'round';
        ctx.strokeStyle = stroke.color || DEFAULT_COLOR;
        ctx.lineWidth = EssayImageAnnotator._strokeLineWidth(stroke, w);
        if (stroke.tool === 'eraser') {
            ctx.globalCompositeOperation = 'destination-out';
        }
        ctx.beginPath();
        ctx.moveTo(points[0][0] * w, points[0][1] * h);
        for (var i = 1; i < points.length; i++) {
            ctx.lineTo(points[i][0] * w, points[i][1] * h);
        }
        ctx.stroke();
        ctx.restore();
    };

    EssayImageAnnotator._measureTextBlock = function (ctx, lines, fontSize) {
        var maxW = 0;
        lines.forEach(function (line) {
            maxW = Math.max(maxW, ctx.measureText(line || ' ').width);
        });
        var lineHeight = fontSize * 1.35;
        return { width: maxW, height: lines.length * lineHeight, lineHeight: lineHeight };
    };

    EssayImageAnnotator._drawText = function (ctx, item, w, h) {
        var text = (item.text || '').trim();
        if (!text) return;

        var fontSize = item.fontSizeRatio
            ? Math.max(12, Math.round(w * item.fontSizeRatio))
            : Math.max(12, Math.round((item.fontSize || 14) * (w / 800)));
        var lines = text.split('\n');
        var x = item.x * w;
        var y = item.y * h;
        var color = item.color || DEFAULT_COLOR;
        var padding = Math.max(6, Math.round(fontSize * 0.35));

        ctx.save();
        ctx.font = '600 ' + fontSize + 'px system-ui, -apple-system, sans-serif';
        var block = EssayImageAnnotator._measureTextBlock(ctx, lines, fontSize);
        var boxX = x - padding;
        var boxY = y - fontSize - padding + 4;
        var boxW = block.width + padding * 2;
        var boxH = block.height + padding * 2;

        ctx.fillStyle = 'rgba(255, 255, 255, 0.94)';
        ctx.strokeStyle = color;
        ctx.lineWidth = Math.max(2, Math.round(fontSize * 0.12));
        if (typeof ctx.roundRect === 'function') {
            ctx.beginPath();
            ctx.roundRect(boxX, boxY, boxW, boxH, 6);
            ctx.fill();
            ctx.stroke();
        } else {
            ctx.fillRect(boxX, boxY, boxW, boxH);
            ctx.strokeRect(boxX, boxY, boxW, boxH);
        }

        ctx.fillStyle = color;
        lines.forEach(function (line, index) {
            ctx.fillText(line, x, y + index * block.lineHeight);
        });
        ctx.restore();
    };

    EssayImageAnnotator._actionTimestamp = function (id) {
        var match = String(id || '').match(/-(\d+)$/);
        return match ? parseInt(match[1], 10) : 0;
    };

    EssayImageAnnotator.prototype._ensureAnnotationIds = function () {
        var base = Date.now();
        this.strokes.forEach(function (stroke, index) {
            if (!stroke.id) stroke.id = 'stroke-' + (base + index);
        });
        this.texts.forEach(function (text, index) {
            if (!text.id) text.id = 'text-' + (base + 1000 + index);
        });
    };

    EssayImageAnnotator.prototype._rebuildHistory = function (data) {
        data = data || {};
        if (Array.isArray(data.history) && data.history.length) {
            this.history = data.history.map(function (entry) {
                return { type: entry.type, id: entry.id };
            });
            return;
        }

        var entries = [];
        this.strokes.forEach(function (stroke, index) {
            entries.push({
                type: 'stroke',
                id: stroke.id,
                order: EssayImageAnnotator._actionTimestamp(stroke.id) || (index + 1)
            });
        });
        this.texts.forEach(function (text, index) {
            entries.push({
                type: 'text',
                id: text.id,
                order: EssayImageAnnotator._actionTimestamp(text.id) || (100000 + index + 1)
            });
        });
        entries.sort(function (a, b) { return a.order - b.order; });
        this.history = entries.map(function (entry) {
            return { type: entry.type, id: entry.id };
        });
    };

    EssayImageAnnotator.prototype._recordAction = function (type, id) {
        this.history.push({ type: type, id: id });
    };

    EssayImageAnnotator.prototype._removeAction = function (type, id) {
        if (type === 'stroke') {
            this.strokes = this.strokes.filter(function (stroke) { return stroke.id !== id; });
            return;
        }
        this.texts = this.texts.filter(function (text) { return text.id !== id; });
    };

    EssayImageAnnotator.prototype._updateUndoButton = function () {
        if (!this.toolbar || this.readonly) return;
        var btn = this.toolbar.querySelector('[data-action="undo"]');
        if (!btn) return;
        var canUndo = !!this.currentStroke || this.history.length > 0;
        btn.disabled = !canUndo;
        btn.classList.toggle('is-disabled', !canUndo);
        btn.setAttribute('aria-disabled', canUndo ? 'false' : 'true');
    };

    EssayImageAnnotator.prototype.loadAnnotations = function (data) {
        data = data || {};
        this.strokes = Array.isArray(data.strokes) ? data.strokes.slice() : [];
        this.texts = Array.isArray(data.texts) ? data.texts.slice() : [];
        this.imageNaturalWidth = data.imageWidth || this.imageNaturalWidth;
        this.imageNaturalHeight = data.imageHeight || this.imageNaturalHeight;
        this._ensureAnnotationIds();
        this._rebuildHistory(data);
        this._redraw();
        this._updateUndoButton();
    };

    EssayImageAnnotator.prototype.getAnnotations = function () {
        return {
            version: 1,
            imageWidth: this.imageNaturalWidth,
            imageHeight: this.imageNaturalHeight,
            strokes: this.strokes.slice(),
            texts: this.texts.slice(),
            history: this.history.slice()
        };
    };

    EssayImageAnnotator.prototype.undo = function () {
        this._closeTextEditor(false);
        if (this.currentStroke) {
            this.currentStroke = null;
            this._redraw();
            this._updateUndoButton();
            return;
        }
        if (!this.history.length) return;

        var last = this.history.pop();
        this._removeAction(last.type, last.id);
        this._redraw();
        this._notifyChange();
        this._updateUndoButton();
    };

    EssayImageAnnotator.prototype.clear = function () {
        this._closeTextEditor(false);
        this.currentStroke = null;
        this.strokes = [];
        this.texts = [];
        this.history = [];
        this._redraw();
        this._notifyChange();
        this._updateUndoButton();
    };

    EssayImageAnnotator.prototype.exportFlattenedBase64 = function () {
        var self = this;
        return new Promise(function (resolve, reject) {
            if (!self.imageNaturalWidth || !self.imageNaturalHeight) {
                reject(new Error('Imagem não carregada'));
                return;
            }
            var canvas = document.createElement('canvas');
            canvas.width = self.imageNaturalWidth;
            canvas.height = self.imageNaturalHeight;
            var ctx = canvas.getContext('2d');
            var img = new Image();
            img.crossOrigin = 'anonymous';
            img.onload = function () {
                ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
                var w = canvas.width;
                var h = canvas.height;
                self.strokes.forEach(function (s) { EssayImageAnnotator._drawStroke(ctx, s, w, h); });
                self.texts.forEach(function (t) { EssayImageAnnotator._drawText(ctx, t, w, h); });
                var dataUrl = canvas.toDataURL('image/png');
                resolve(dataUrl.replace(/^data:image\/png;base64,/, ''));
            };
            img.onerror = function () { reject(new Error('Falha ao carregar imagem')); };
            img.src = self.img.src;
        });
    };

    global.EssayImageAnnotator = EssayImageAnnotator;
})(typeof window !== 'undefined' ? window : this);
