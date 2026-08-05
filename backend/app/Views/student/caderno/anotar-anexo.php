<?php
$caderno = $caderno ?? [];
$anexo = $anexo ?? [];
$cadernoId = (int)($caderno['id'] ?? 0);
$anexoId = (int)($anexo['id'] ?? 0);
$urlArquivo = $url_arquivo ?? '';
$ehPdf = !empty($eh_pdf);
$anotacaoCanvas = $anotacao_canvas ?? null;
$podeAnotar = !empty($urlArquivo);
?>
<div class="container mx-auto px-4 py-4 max-w-7xl w-full">
    <div class="mb-4 flex items-center justify-between flex-wrap gap-2">
        <a href="<?= URL ?>/caderno/<?= $cadernoId ?>" class="text-green-600 hover:text-green-700 font-medium flex items-center gap-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Voltar à anotação
        </a>
        <h1 class="text-lg font-semibold text-gray-900 truncate flex-1 text-center"><?= htmlspecialchars($anexo['nome_original'] ?? 'Anotar') ?></h1>
        <div class="w-24"></div>
    </div>

    <?php if (!$podeAnotar): ?>
        <div class="caderno-wrapper p-8 text-center text-gray-600">
            <p>Não foi possível carregar o arquivo para anotação.</p>
        </div>
    <?php else: ?>
        <!-- Barra de ferramentas – layout moderno -->
        <div class="toolbar-anotar rounded-t-2xl border border-gray-200/80 bg-gradient-to-b from-slate-50 to-white shadow-lg p-4 flex flex-wrap items-center gap-3">
            <div class="flex flex-wrap items-center gap-2">
                <button type="button" id="btn-caneta" class="tool-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 bg-emerald-500 text-white shadow-md shadow-emerald-500/30 hover:shadow-lg hover:scale-[1.02]" data-tool="caneta" title="Caneta">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Caneta
                </button>
                <button type="button" id="btn-seta" class="tool-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200" data-tool="seta" title="Seta">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"></path></svg>
                    Seta
                </button>
                <button type="button" id="btn-destaque" class="tool-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200" data-tool="destaque" title="Destaque">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 8h10M7 12h4m1 8l-4-4H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-3l-4 4z"></path></svg>
                    Destaque
                </button>
                <button type="button" id="btn-circulo" class="tool-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200" data-tool="circulo" title="Círculo">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9" stroke-width="2"></circle></svg>
                    Círculo
                </button>
                <button type="button" id="btn-oval" class="tool-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200" data-tool="oval" title="Oval">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><ellipse cx="12" cy="12" rx="10" ry="7" stroke-width="2"></ellipse></svg>
                    Oval
                </button>
                <button type="button" id="btn-texto" class="tool-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200" data-tool="texto" title="Texto">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                    Texto
                </button>
                <button type="button" id="btn-selecionar" class="tool-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200" data-tool="selecionar" title="Selecionar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 15l-2 5L9 9l11 4-5 2zm0 0l5 5M7.188 2.239l.777 2.897M5.136 7.965l-2.898-.777M13.95 4.05l-2.122 2.122m-5.657 5.656l-2.12 2.122"></path></svg>
                    Selecionar
                </button>
                <button type="button" id="btn-borrachas" class="tool-btn flex items-center gap-2 px-4 py-2.5 rounded-full text-sm font-medium transition-all duration-200 bg-slate-100 text-rose-600 hover:bg-rose-50 border border-slate-200" data-tool="borrachas" title="Apagar">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                    Apagar
                </button>
            </div>
            <div class="h-8 w-px bg-slate-200 hidden sm:block"></div>
            <div class="flex items-center gap-3 flex-wrap">
                <label class="flex items-center gap-2 cursor-pointer">
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Cor</span>
                    <input type="color" id="cor-ferramenta" value="#e11d48" class="w-9 h-9 rounded-xl border-2 border-slate-200 cursor-pointer shadow-inner overflow-hidden" title="Cor">
                </label>
                <label class="flex items-center gap-2 cursor-pointer">
                    <span class="text-xs font-medium text-slate-500 uppercase tracking-wide">Espessura</span>
                    <input type="range" id="espessura" min="1" max="20" value="4" class="w-28 h-2 rounded-full accent-emerald-500">
                </label>
            </div>
        </div>

        <!-- Área do canvas -->
        <div class="bg-gray-100 border border-t-0 border-gray-200 rounded-b-xl shadow-sm p-4 min-h-[70vh] flex justify-center items-start overflow-auto">
            <div id="canvas-container" style="position: relative;">
                <canvas id="canvas-anotar"></canvas>
                <div id="loading-img" class="absolute inset-0 flex items-center justify-center bg-white/80 rounded-lg">
                    <span class="text-gray-500">Carregando...</span>
                </div>
            </div>
        </div>

        <div class="mt-4 flex justify-center gap-3">
            <button type="button" id="btn-salvar" class="px-6 py-3 bg-green-600 text-white rounded-xl font-semibold hover:bg-green-700 transition-colors shadow">
                💾 Salvar anotações
            </button>
            <a href="<?= URL ?>/caderno/<?= $cadernoId ?>" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-xl font-medium hover:bg-gray-50 transition-colors">
                Cancelar
            </a>
        </div>
    <?php endif; ?>
</div>

<?php if ($podeAnotar): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<?php if ($ehPdf): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
<?php endif; ?>
<script>
(function() {
    var cadernoId = <?= (int)$cadernoId ?>;
    var anexoId = <?= (int)$anexoId ?>;
    var urlArquivo = <?= json_encode($urlArquivo) ?>;
    var ehPdf = <?= $ehPdf ? 'true' : 'false' ?>;
    var anotacaoSalva = <?= $anotacaoCanvas ? json_encode($anotacaoCanvas) : 'null' ?>;
    var csrfToken = <?= json_encode($csrf_token ?? '') ?>;

    var canvasEl = document.getElementById('canvas-anotar');
    var canvas = new fabric.Canvas('canvas-anotar', { selection: true, preserveObjectStacking: true });
    var ferramentaAtual = 'caneta';
    var corAtual = document.getElementById('cor-ferramenta').value;
    var espessuraAtual = 4;
    var imgFundo = null;

    function estiloAtivo(btn) {
        btn.classList.remove('bg-slate-100', 'text-slate-600', 'text-rose-600', 'hover:bg-slate-200', 'border-slate-200');
        btn.classList.add('bg-emerald-500', 'text-white', 'shadow-md', 'shadow-emerald-500/30', 'border-transparent');
    }
    function estiloInativo(btn) {
        btn.classList.remove('bg-emerald-500', 'text-white', 'shadow-md', 'shadow-emerald-500/30', 'border-transparent');
        btn.classList.add('bg-slate-100', 'border', 'border-slate-200', 'hover:bg-slate-200');
        if (btn.getAttribute('data-tool') === 'borrachas') {
            btn.classList.add('text-rose-600');
        } else {
            btn.classList.add('text-slate-600');
        }
    }
    function atualizarBotoes() {
        document.querySelectorAll('.tool-btn').forEach(function(btn) {
            var tool = btn.getAttribute('data-tool');
            if (tool === ferramentaAtual) {
                estiloAtivo(btn);
            } else {
                estiloInativo(btn);
            }
        });
    }

    function carregarImagemComoFundo(src, callback) {
        fabric.Image.fromURL(src, function(img) {
            if (!img) { callback(new Error('Falha ao carregar imagem')); return; }
            var w = img.getScaledWidth();
            var h = img.getScaledHeight();
            canvas.setDimensions({ width: w, height: h });
            img.set({ selectable: false, evented: false });
            canvas.setBackgroundImage(img, canvas.renderAll.bind(canvas));
            imgFundo = img;
            if (callback) callback();
        }, { crossOrigin: 'anonymous' });
    }

    function carregarPdfComoFundo(url, callback) {
        if (typeof pdfjsLib === 'undefined') {
            callback(new Error('PDF.js não carregado'));
            return;
        }
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.arrayBuffer(); })
            .then(function(data) {
                return pdfjsLib.getDocument(data).promise;
            })
            .then(function(pdf) {
                return pdf.getPage(1);
            })
            .then(function(page) {
                var scale = 1.5;
                var viewport = page.getViewport({ scale: scale });
                var c = document.createElement('canvas');
                c.width = viewport.width;
                c.height = viewport.height;
                var ctx = c.getContext('2d');
                return page.render({ canvasContext: ctx, viewport: viewport }).promise.then(function() {
                    return c.toDataURL('image/png');
                });
            })
            .then(function(dataUrl) {
                carregarImagemComoFundo(dataUrl, callback);
            })
            .catch(function(err) {
                if (callback) callback(err);
            });
    }

    function carregarAnotacoesSalvas() {
        if (!anotacaoSalva) return;
        try {
            var data = typeof anotacaoSalva === 'string' ? JSON.parse(anotacaoSalva) : anotacaoSalva;
            if (data.objects && data.objects.length) {
                fabric.util.enlivenObjects(data.objects, function(objs) {
                    objs.forEach(function(o) {
                        canvas.add(o);
                    });
                    canvas.renderAll();
                });
            }
        } catch (e) {
            console.warn('Erro ao carregar anotações salvas', e);
        }
    }

    function iniciarDesenho() {
        canvas.isDrawingMode = true;
        canvas.freeDrawingBrush.width = espessuraAtual;
        canvas.freeDrawingBrush.color = corAtual;
        if (ferramentaAtual === 'destaque') {
            canvas.freeDrawingBrush.width = 20;
            canvas.freeDrawingBrush.color = 'rgba(255, 235, 59, 0.6)';
        } else {
            canvas.freeDrawingBrush.width = espessuraAtual;
            canvas.freeDrawingBrush.color = corAtual;
        }
    }

    function pararDesenho() {
        canvas.isDrawingMode = false;
    }

    function evitarDesenhoEmCimaDeObjeto() {
        canvas.on('mouse:down', function(o) {
            if (!o.target) return;
            if (ferramentaAtual === 'caneta' || ferramentaAtual === 'destaque') {
                canvas.isDrawingMode = false;
            }
        });
        canvas.on('mouse:up', function() {
            if (ferramentaAtual === 'caneta' || ferramentaAtual === 'destaque') {
                iniciarDesenho();
            }
        });
    }

    function modoSeta() {
        var linha, inicioX, inicioY, fimX, fimY;
        canvas.on('mouse:down', function(o) {
            if (ferramentaAtual !== 'seta') return;
            if (o.target) return;
            var p = canvas.getPointer(o.e);
            inicioX = p.x;
            inicioY = p.y;
            fimX = p.x;
            fimY = p.y;
            linha = new fabric.Line([p.x, p.y, p.x, p.y], {
                stroke: corAtual,
                strokeWidth: Math.max(2, espessuraAtual)
            });
            canvas.add(linha);
        });
        canvas.on('mouse:move', function(o) {
            if (!linha || ferramentaAtual !== 'seta') return;
            var p = canvas.getPointer(o.e);
            fimX = p.x;
            fimY = p.y;
            linha.set({ x2: p.x, y2: p.y });
            canvas.renderAll();
        });
        canvas.on('mouse:up', function() {
            if (ferramentaAtual !== 'seta' || !linha) return;
            var dx = fimX - inicioX, dy = fimY - inicioY;
            var comprimento = Math.sqrt(dx * dx + dy * dy);
            if (comprimento < 2) {
                canvas.remove(linha);
                canvas.renderAll();
                linha = null;
                return;
            }
            var ang = Math.atan2(dy, dx);
            var angDeg = (ang * 180 / Math.PI) + 90;
            var setaSize = 16;
            var distCentroPonta = setaSize / 2;
            var tri = new fabric.Triangle({
                width: setaSize,
                height: setaSize,
                fill: corAtual,
                left: fimX - Math.cos(ang) * distCentroPonta,
                top: fimY - Math.sin(ang) * distCentroPonta,
                originX: 'center',
                originY: 'center',
                angle: angDeg
            });
            canvas.add(tri);
            linha = null;
        });
    }

    function modoCirculo() {
        var inicioX, inicioY, circlePreview;
        canvas.on('mouse:down', function(o) {
            if (ferramentaAtual !== 'circulo') return;
            if (o.target) return;
            var p = canvas.getPointer(o.e);
            inicioX = p.x;
            inicioY = p.y;
            circlePreview = new fabric.Circle({
                left: p.x,
                top: p.y,
                radius: 0,
                stroke: corAtual,
                strokeWidth: Math.max(2, espessuraAtual),
                fill: 'transparent',
                selectable: false,
                evented: false
            });
            canvas.add(circlePreview);
        });
        canvas.on('mouse:move', function(o) {
            if (ferramentaAtual !== 'circulo' || !circlePreview) return;
            var p = canvas.getPointer(o.e);
            var dx = p.x - inicioX;
            var dy = p.y - inicioY;
            var r = Math.min(Math.abs(dx), Math.abs(dy)) / 2;
            if (r < 1) r = 1;
            var cx = inicioX + dx / 2;
            var cy = inicioY + dy / 2;
            circlePreview.set({ radius: r, left: cx - r, top: cy - r });
            canvas.renderAll();
        });
        canvas.on('mouse:up', function() {
            if (ferramentaAtual !== 'circulo' || !circlePreview) return;
            var p = circlePreview;
            var r = p.radius;
            canvas.remove(circlePreview);
            circlePreview = null;
            if (r < 3) {
                canvas.renderAll();
                return;
            }
            var circ = new fabric.Circle({
                left: p.left,
                top: p.top,
                radius: r,
                stroke: corAtual,
                strokeWidth: Math.max(2, espessuraAtual),
                fill: 'transparent'
            });
            canvas.add(circ);
            canvas.renderAll();
        });
    }

    function modoOval() {
        var inicioX, inicioY, ovalPreview;
        canvas.on('mouse:down', function(o) {
            if (ferramentaAtual !== 'oval') return;
            if (o.target) return;
            var p = canvas.getPointer(o.e);
            inicioX = p.x;
            inicioY = p.y;
            ovalPreview = new fabric.Ellipse({
                left: p.x,
                top: p.y,
                rx: 0,
                ry: 0,
                stroke: corAtual,
                strokeWidth: Math.max(2, espessuraAtual),
                fill: 'transparent',
                selectable: false,
                evented: false
            });
            canvas.add(ovalPreview);
        });
        canvas.on('mouse:move', function(o) {
            if (ferramentaAtual !== 'oval' || !ovalPreview) return;
            var p = canvas.getPointer(o.e);
            var dx = p.x - inicioX;
            var dy = p.y - inicioY;
            var rx = Math.abs(dx) / 2;
            var ry = Math.abs(dy) / 2;
            if (rx < 1) rx = 1;
            if (ry < 1) ry = 1;
            var cx = inicioX + dx / 2;
            var cy = inicioY + dy / 2;
            ovalPreview.set({ rx: rx, ry: ry, left: cx - rx, top: cy - ry });
            canvas.renderAll();
        });
        canvas.on('mouse:up', function() {
            if (ferramentaAtual !== 'oval' || !ovalPreview) return;
            var prev = ovalPreview;
            var rx = prev.rx;
            var ry = prev.ry;
            canvas.remove(ovalPreview);
            ovalPreview = null;
            if (rx < 3 && ry < 3) {
                canvas.renderAll();
                return;
            }
            var el = new fabric.Ellipse({
                left: prev.left,
                top: prev.top,
                rx: rx,
                ry: ry,
                stroke: corAtual,
                strokeWidth: Math.max(2, espessuraAtual),
                fill: 'transparent'
            });
            canvas.add(el);
            canvas.renderAll();
        });
    }

    function modoTexto() {
        canvas.on('mouse:down', function(o) {
            if (ferramentaAtual !== 'texto') return;
            if (o.target) return;
            var p = canvas.getPointer(o.e);
            var txt = new fabric.IText('Digite aqui', {
                left: p.x,
                top: p.y,
                fontFamily: 'Arial',
                fontSize: 20,
                fill: corAtual
            });
            canvas.add(txt);
            canvas.setActiveObject(txt);
            txt.enterEditing();
            txt.selectAll();
            canvas.renderAll();
        });
    }

    function aoClicarFerramenta(tool, aoAtivar) {
        if (ferramentaAtual === tool) {
            ferramentaAtual = 'selecionar';
            pararDesenho();
            atualizarBotoes();
            return;
        }
        ferramentaAtual = tool;
        pararDesenho();
        atualizarBotoes();
        if (aoAtivar) aoAtivar();
    }
    document.getElementById('btn-caneta').onclick = function() {
        aoClicarFerramenta('caneta', iniciarDesenho);
    };
    document.getElementById('btn-seta').onclick = function() {
        aoClicarFerramenta('seta');
    };
    document.getElementById('btn-destaque').onclick = function() {
        aoClicarFerramenta('destaque', iniciarDesenho);
    };
    document.getElementById('btn-circulo').onclick = function() {
        aoClicarFerramenta('circulo');
    };
    document.getElementById('btn-oval').onclick = function() {
        aoClicarFerramenta('oval');
    };
    document.getElementById('btn-texto').onclick = function() {
        aoClicarFerramenta('texto');
    };
    document.getElementById('btn-selecionar').onclick = function() {
        aoClicarFerramenta('selecionar');
    };
    document.getElementById('btn-borrachas').onclick = function() {
        var obj = canvas.getActiveObject();
        if (obj) {
            canvas.remove(obj);
            canvas.renderAll();
        } else {
            alert('Selecione um objeto (use "Selecionar") e clique em Apagar para removê-lo.');
        }
    };
    document.getElementById('cor-ferramenta').oninput = function() {
        corAtual = this.value;
        if (canvas.isDrawingMode) {
            canvas.freeDrawingBrush.color = ferramentaAtual === 'destaque' ? 'rgba(255, 235, 59, 0.6)' : corAtual;
        }
    };
    document.getElementById('espessura').oninput = function() {
        espessuraAtual = parseInt(this.value, 10);
        if (canvas.isDrawingMode && ferramentaAtual !== 'destaque') {
            canvas.freeDrawingBrush.width = espessuraAtual;
        }
    };

    function salvar() {
        var objetos = canvas.getObjects();
        var arr = objetos.map(function(o) {
            return o.toObject();
        });
        var json = JSON.stringify({ objects: arr });
        var form = new FormData();
        form.append('_token', csrfToken);
        form.append('caderno_id', cadernoId);
        form.append('anexo_id', anexoId);
        form.append('canvas_json', json);

        document.getElementById('btn-salvar').disabled = true;
        document.getElementById('btn-salvar').textContent = 'Salvando...';
        fetch('<?= URL ?>/caderno/anexo/salvar-anotacao', { method: 'POST', body: form })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success && data.redirect) {
                    window.location.href = data.redirect;
                } else {
                    alert(data.error || 'Erro ao salvar');
                    document.getElementById('btn-salvar').disabled = false;
                    document.getElementById('btn-salvar').textContent = '💾 Salvar anotações';
                }
            })
            .catch(function() {
                alert('Erro de conexão');
                document.getElementById('btn-salvar').disabled = false;
                document.getElementById('btn-salvar').textContent = '💾 Salvar anotações';
            });
    }
    document.getElementById('btn-salvar').onclick = salvar;

    function init() {
        var loading = document.getElementById('loading-img');
        function esconderLoading() {
            if (loading) loading.style.display = 'none';
        }
        function mostrarErro(msg) {
            if (loading) {
                loading.innerHTML = '<span class="text-red-600">' + (msg || 'Erro ao carregar') + '</span>';
            }
        }

        if (ehPdf) {
            carregarPdfComoFundo(urlArquivo, function(err) {
                if (err) {
                    mostrarErro('Não foi possível carregar o PDF.');
                    return;
                }
                esconderLoading();
                carregarAnotacoesSalvas();
                atualizarBotoes();
                iniciarDesenho();
                modoSeta();
                modoCirculo();
                modoOval();
                modoTexto();
                evitarDesenhoEmCimaDeObjeto();
            });
        } else {
            carregarImagemComoFundo(urlArquivo, function(err) {
                if (err) {
                    mostrarErro('Não foi possível carregar a imagem.');
                    return;
                }
                esconderLoading();
                carregarAnotacoesSalvas();
                atualizarBotoes();
                iniciarDesenho();
                modoSeta();
                modoCirculo();
                modoOval();
                modoTexto();
                evitarDesenhoEmCimaDeObjeto();
            });
        }
    }
    init();
})();
</script>
<?php endif; ?>
