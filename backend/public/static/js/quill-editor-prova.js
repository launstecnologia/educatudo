/**
 * Editor de enunciado/alternativas com Quill.js + KaTeX (equações).
 * Uso: QuillProvaEditor.initEnunciado('#quill-enunciado', '#enunciado-questao');
 *      QuillProvaEditor.initAlternativa('#quill-alt-0');
 */
(function(global) {
    'use strict';

    if (typeof Quill === 'undefined' || typeof katex === 'undefined') {
        console.error('QuillProvaEditor: carregue Quill e KaTeX antes deste script.');
        return;
    }

    var instances = {};
    var formulaModalQuill = null;

    // Blot customizado para equação: guarda LaTeX e renderiza com MathLive no editor (block para compatibilidade com insertEmbed)
    var BlockEmbed = Quill.import('blots/block/embed');
    var MathEmbedBlot = function() {
        BlockEmbed.apply(this, arguments);
    };
    MathEmbedBlot.blotName = 'mathEmbed';
    MathEmbedBlot.tagName = 'SPAN';
    MathEmbedBlot.className = 'math-embed';
    MathEmbedBlot.prototype = Object.create(BlockEmbed.prototype);
    Object.assign(MathEmbedBlot.prototype, {
        create: function(value) {
            var node = document.createElement(this.statics.tagName);
            node.setAttribute('contenteditable', 'false');
            node.setAttribute('data-latex', value || '');
            node.className = this.statics.className || '';
            var inner = document.createElement('span');
            inner.className = 'math-embed-inner';
            inner.textContent = value ? '\\( ' + value + ' \\)' : '';
            node.appendChild(inner);
            return node;
        },
        attach: function() {
            BlockEmbed.prototype.attach.call(this);
            var self = this;
            var inner = this.domNode.querySelector('.math-embed-inner');
            if (inner && inner.textContent && window.MathLive && typeof MathLive.renderMathInElement === 'function') {
                setTimeout(function() {
                    var el = self.domNode && self.domNode.querySelector('.math-embed-inner');
                    if (el) {
                        try {
                            MathLive.renderMathInElement(el);
                        } catch (e) {
                            console.warn('MathLive render:', e);
                        }
                    }
                }, 0);
            }
        },
        value: function(node) {
            return node.getAttribute('data-latex') || '';
        }
    });
    Quill.register(MathEmbedBlot);

    function openFormulaModal(quill) {
        formulaModalQuill = quill;
        var modal = document.getElementById('quill-formula-modal');
        if (!modal) {
            modal = createFormulaModal();
            document.body.appendChild(modal);
        }
        document.getElementById('quill-formula-latex').value = '';
        document.getElementById('quill-formula-preview').innerHTML = '';
        modal.classList.remove('hidden');
        document.getElementById('quill-formula-latex').focus();
        updateFormulaPreview();
    }

    function createFormulaModal() {
        var modal = document.createElement('div');
        modal.id = 'quill-formula-modal';
        modal.className = 'fixed inset-0 z-[100] flex items-center justify-center hidden';
        modal.innerHTML = '<div class="absolute inset-0 bg-black/50" data-close="formula"></div>' +
            '<div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full mx-4 p-6">' +
            '<h3 class="text-lg font-semibold text-gray-900 mb-2">Inserir equação matemática</h3>' +
            '<label class="block text-sm font-medium text-gray-700 mb-1">Fórmula em LaTeX</label>' +
            '<textarea id="quill-formula-latex" rows="3" class="w-full border border-gray-300 rounded-lg px-3 py-2 font-mono text-sm mb-3" placeholder="Ex: x = \\frac{-b \\pm \\sqrt{b^2-4ac}}{2a}"></textarea>' +
            '<p class="text-sm text-gray-600 mb-1">Pré-visualização:</p>' +
            '<div id="quill-formula-preview" class="min-h-[60px] p-3 border border-gray-200 rounded-lg bg-gray-50 mb-3 overflow-x-auto"></div>' +
            '<p class="text-xs text-gray-500 mb-2">Exemplos:</p>' +
            '<div class="flex flex-wrap gap-2 mb-4">' +
            '<button type="button" data-latex="x = \\frac{-b \\pm \\sqrt{b^2-4ac}}{2a}" class="px-2 py-1 text-xs bg-gray-200 rounded hover:bg-gray-300">Bhaskara</button>' +
            '<button type="button" data-latex="E = mc^2" class="px-2 py-1 text-xs bg-gray-200 rounded hover:bg-gray-300">E=mc²</button>' +
            '<button type="button" data-latex="\\sum_{i=1}^{n} i = \\frac{n(n+1)}{2}" class="px-2 py-1 text-xs bg-gray-200 rounded hover:bg-gray-300">Soma</button>' +
            '<button type="button" data-latex="\\int_0^\\infty e^{-x^2} dx = \\frac{\\sqrt{\\pi}}{2}" class="px-2 py-1 text-xs bg-gray-200 rounded hover:bg-gray-300">Integral</button>' +
            '</div>' +
            '<div class="flex justify-end gap-2">' +
            '<button type="button" id="quill-formula-cancel" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>' +
            '<button type="button" id="quill-formula-insert" class="px-4 py-2 rounded-lg bg-blue-600 text-white hover:bg-blue-700">Inserir</button>' +
            '</div></div>';
        modal.querySelector('[data-close="formula"]').addEventListener('click', closeFormulaModal);
        modal.querySelector('#quill-formula-cancel').addEventListener('click', closeFormulaModal);
        modal.querySelector('#quill-formula-insert').addEventListener('click', insertFormula);
        modal.querySelector('#quill-formula-latex').addEventListener('input', updateFormulaPreview);
        modal.querySelector('#quill-formula-latex').addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeFormulaModal();
        });
        modal.querySelectorAll('[data-latex]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                document.getElementById('quill-formula-latex').value = this.getAttribute('data-latex');
                updateFormulaPreview();
            });
        });
        return modal;
    }

    function updateFormulaPreview() {
        var latex = document.getElementById('quill-formula-latex').value.trim();
        var preview = document.getElementById('quill-formula-preview');
        if (!latex) {
            preview.innerHTML = '';
            return;
        }
        try {
            katex.render(latex, preview, { throwOnError: false, displayMode: true });
        } catch (e) {
            preview.innerHTML = '<span class="text-red-600 text-sm">Erro na fórmula</span>';
        }
    }

    function closeFormulaModal() {
        var modal = document.getElementById('quill-formula-modal');
        if (modal) modal.classList.add('hidden');
        formulaModalQuill = null;
    }

    function buildMathEmbedHtml(latex) {
        var trimmed = String(latex || '').trim();
        if (!trimmed) return '';
        var escAttr = trimmed.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
        var safeInner = trimmed.replace(/</g, '&lt;').replace(/>/g, '&gt;');
        return '<span class="math-embed" contenteditable="false" data-latex="' + escAttr + '"><span class="math-embed-inner">\\( ' + safeInner + ' \\)</span></span>';
    }

    function insertFormula() {
        if (!formulaModalQuill) return;
        var latex = document.getElementById('quill-formula-latex').value.trim();
        if (!latex) return;
        var quill = formulaModalQuill;
        var range = quill.getSelection(true) || { index: quill.getLength() };
        var html = buildMathEmbedHtml(latex);
        if (!html) { closeFormulaModal(); return; }
        try {
            quill.clipboard.dangerouslyPasteHTML(range.index, html);
            quill.setSelection(range.index + 1);
        } catch (e) {
            console.error(e);
        }
        setTimeout(function() {
            renderMathEmbedsInNode(quill.root);
        }, 50);
        closeFormulaModal();
    }

    var icons = Quill.import('ui/icons');
    icons['formula'] = '<span title="Inserir equação">∑</span>';

    function applyImageSizeToNode(img, pct) {
        if (!img || img.tagName !== 'IMG') return;
        img.style.maxWidth = pct + '%';
        img.style.width = pct + '%';
        img.style.height = 'auto';
    }

    function showImageResizePopover(img) {
        var id = 'quill-image-size-popover';
        var existing = document.getElementById(id);
        if (existing) existing.remove();

        var div = document.createElement('div');
        div.id = id;
        div.className = 'quill-image-size-dropdown';
        div.innerHTML = '<span class="quill-image-size-label">Largura:</span>' +
            '<button type="button" data-pct="25">25%</button>' +
            '<button type="button" data-pct="50">50%</button>' +
            '<button type="button" data-pct="75">75%</button>' +
            '<button type="button" data-pct="100">100%</button>';
        document.body.appendChild(div);

        function positionPopover() {
            var rect = img.getBoundingClientRect();
            div.style.position = 'fixed';
            div.style.left = rect.left + 'px';
            div.style.top = (rect.bottom + 6) + 'px';
        }
        positionPopover();

        div.querySelectorAll('button').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                applyImageSizeToNode(img, parseInt(btn.getAttribute('data-pct'), 10));
                div.remove();
                document.removeEventListener('click', closeHandler);
            });
        });

        function closeHandler(e) {
            if (div.parentNode && !div.contains(e.target) && e.target !== img) {
                div.remove();
                document.removeEventListener('click', closeHandler);
            }
        }
        setTimeout(function() {
            document.addEventListener('click', closeHandler);
        }, 10);
    }

    function setupImageResizeOnClick(quill) {
        if (!quill || !quill.root) return;
        quill.root.addEventListener('click', function(e) {
            var img = e.target && e.target.tagName === 'IMG' ? e.target : null;
            if (!img) return;
            e.preventDefault();
            e.stopPropagation();
            showImageResizePopover(img);
        }, true);
    }

    var toolbarOptions = [
        [{ 'font': [] }],
        [{ 'size': ['10px', '12px', '14px', '16px', '18px', '24px', '32px'] }],
        ['bold', 'italic', 'underline', 'strike'],
        [{ 'color': [] }, { 'background': [] }],
        [{ 'align': [] }],
        [{ 'list': 'ordered' }, { 'list': 'bullet' }],
        ['link', 'image'],
        ['formula'],
        ['clean']
    ];

    var toolbarAlternativa = [
        ['bold', 'italic', 'underline'],
        ['formula'],
        ['clean']
    ];

    function setupPasteImage(quill) {
        if (!quill || !quill.root) return;
        quill.root.addEventListener('paste', function(e) {
            var items = e.clipboardData && e.clipboardData.items;
            if (!items) return;
            for (var i = 0; i < items.length; i++) {
                if (items[i].type.indexOf('image') !== -1) {
                    e.preventDefault();
                    var file = items[i].getAsFile();
                    var reader = new FileReader();
                    reader.onload = function(ev) {
                        var range = quill.getSelection(true) || { index: quill.getLength() };
                        quill.insertEmbed(range.index, 'image', ev.target.result);
                        quill.setSelection(range.index + 1);
                    };
                    reader.readAsDataURL(file);
                    return;
                }
            }
        });
    }

    function initEnunciado(containerSelector, hiddenInputId, options) {
        options = options || {};
        var container = typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
        if (!container) return null;
        var placeholder = options.placeholder || 'Digite o enunciado da questão aqui...';
        var initialHtml = options.initialHtml || '';

        var div = document.createElement('div');
        div.className = 'quill-enunciado-container';
        div.style.minHeight = '200px';
        container.innerHTML = '';
        container.appendChild(div);

        var quill = new Quill(div, {
            theme: 'snow',
            placeholder: placeholder,
            modules: {
                toolbar: {
                    container: toolbarOptions,
                    handlers: {
                        formula: function() {
                            if (typeof MathEditor !== 'undefined' && MathEditor.abrirParaQuill) {
                                MathEditor.abrirParaQuill(quill);
                            } else {
                                openFormulaModal(quill);
                            }
                        }
                    }
                }
            }
        });

        if (initialHtml) quill.root.innerHTML = initialHtml;
        setupPasteImage(quill);
        setupImageResizeOnClick(quill);
        quill.root.addEventListener('blur', function() {
            convertAndRenderMathInEditor(quill);
        });
        quill.on('text-change', function() {
            if (quill._skipMathConvert) return;
            var t = quill._mathConvertTimer;
            if (t) clearTimeout(t);
            quill._mathConvertTimer = setTimeout(function() {
                quill._mathConvertTimer = null;
                convertAndRenderMathInEditor(quill);
            }, 600);
        });

        var key = containerSelector + (hiddenInputId || '');
        instances[key] = { quill: quill, hiddenId: hiddenInputId };
        return quill;
    }

    function initAlternativa(containerSelector, options) {
        options = options || {};
        var container = typeof containerSelector === 'string' ? document.querySelector(containerSelector) : containerSelector;
        if (!container) return null;
        var placeholder = options.placeholder || 'Digite a alternativa...';
        var initialHtml = options.initialHtml || '';

        var div = document.createElement('div');
        div.className = 'quill-alt-container';
        div.style.minHeight = '60px';
        container.innerHTML = '';
        container.appendChild(div);

        var quill = new Quill(div, {
            theme: 'snow',
            placeholder: placeholder,
            modules: {
                toolbar: {
                    container: toolbarAlternativa,
                    handlers: {
                        formula: function() {
                            if (typeof MathEditor !== 'undefined' && MathEditor.abrirParaQuill) {
                                MathEditor.abrirParaQuill(quill);
                            } else {
                                openFormulaModal(quill);
                            }
                        }
                    }
                }
            }
        });

        if (initialHtml) quill.root.innerHTML = initialHtml;
        setupImageResizeOnClick(quill);
        quill.root.addEventListener('blur', function() {
            convertAndRenderMathInEditor(quill);
        });
        quill.on('text-change', function() {
            if (quill._skipMathConvert) return;
            var t = quill._mathConvertTimer;
            if (t) clearTimeout(t);
            quill._mathConvertTimer = setTimeout(function() {
                quill._mathConvertTimer = null;
                convertAndRenderMathInEditor(quill);
            }, 600);
        });
        return quill;
    }

    function getHTML(quill) {
        return quill && quill.root ? quill.root.innerHTML : '';
    }

    function escapeHtmlAttr(s) {
        return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
    }

    function convertRawLatexToEmbed(html) {
        if (!html || typeof html !== 'string') return html;
        return html.replace(/\\\(\s*([\s\S]*?)\s*\\\)/g, function(match, latex) {
            var trimmed = latex.trim();
            if (!trimmed) return match;
            var escAttr = escapeHtmlAttr(trimmed);
            var safeInner = trimmed.replace(/</g, '&lt;').replace(/>/g, '&gt;');
            return '<span class="math-embed" contenteditable="false" data-latex="' + escAttr + '"><span class="math-embed-inner">\\( ' + safeInner + ' \\)</span></span>';
        });
    }

    function convertAndRenderMathInEditor(quill, skipConvert) {
        if (!quill || !quill.root) return;
        var html = quill.root.innerHTML;
        var converted = skipConvert ? html : convertRawLatexToEmbed(html);
        if (converted !== html) {
            quill._skipMathConvert = true;
            quill.root.innerHTML = converted;
            quill._skipMathConvert = false;
            renderMathEmbedsInNode(quill.root);
        } else {
            renderMathEmbedsInNode(quill.root);
        }
    }

    function setHTML(quill, html) {
        if (!quill || !quill.root) return;
        var content = html || '';
        content = convertRawLatexToEmbed(content);
        quill.root.innerHTML = content;
        renderMathEmbedsInNode(quill.root);
    }

    function renderMathEmbedsInNode(container) {
        if (!container) return;
        var embeds = container.querySelectorAll('.math-embed[data-latex]');
        embeds.forEach(function(span) {
            var latex = span.getAttribute('data-latex');
            var inner = span.querySelector('.math-embed-inner');
            if (inner && latex && window.MathLive && typeof MathLive.renderMathInElement === 'function') {
                inner.textContent = '\\( ' + latex + ' \\)';
                try {
                    MathLive.renderMathInElement(inner);
                } catch (e) {
                    console.warn('MathLive render:', e);
                }
            }
        });
    }

    function getDelta(quill) {
        return quill ? quill.getContents() : null;
    }

    function setDelta(quill, delta) {
        if (quill && delta) quill.setContents(delta);
    }

    function syncToHidden(quill, hiddenInputId) {
        var el = document.getElementById(hiddenInputId);
        if (el && quill) el.value = getHTML(quill);
    }

    global.QuillProvaEditor = {
        initEnunciado: initEnunciado,
        initAlternativa: initAlternativa,
        getHTML: getHTML,
        setHTML: setHTML,
        getDelta: getDelta,
        setDelta: setDelta,
        syncToHidden: syncToHidden,
        openFormulaModal: openFormulaModal,
        closeFormulaModal: closeFormulaModal,
        instances: instances
    };
})(typeof window !== 'undefined' ? window : this);
