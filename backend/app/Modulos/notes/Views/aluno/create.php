<?php
$materias = $materias ?? [];
$pastas = $pastas ?? [];
?>
<div class="container mx-auto px-4 py-6 max-w-7xl w-full">
    <div class="mb-6">
        <a href="<?= URL ?>/caderno" class="text-green-600 hover:text-green-700 font-medium flex items-center gap-1 mb-4">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Voltar ao caderno
        </a>
        <h1 class="text-2xl font-bold text-gray-900">Nova anotação</h1>
        <p class="text-gray-600 mt-1">Preencha o título, a pasta (opcional), a matéria. No campo abaixo você pode colar imagens, escrever e desenhar por cima.</p>
    </div>

    <?php if (!empty($_SESSION['error_message'])): ?>
        <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg border border-red-300"><?= htmlspecialchars($_SESSION['error_message']) ?></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="caderno-wrapper">
        <form method="POST" action="<?= URL ?>/caderno" enctype="multipart/form-data" class="space-y-5" id="form-caderno">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <textarea id="observacao" name="observacao" style="display: none;"></textarea>

            <div>
                <label for="titulo" class="block text-sm font-semibold text-gray-700 mb-2">Título *</label>
                <input type="text" id="titulo" name="titulo" required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white/80"
                       placeholder="Ex: Resumo da aula de Biologia">
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="pasta_id" class="block text-sm font-semibold text-gray-700 mb-2">Pasta de estudo</label>
                    <select id="pasta_id" name="pasta_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white/80">
                        <option value="">Nenhuma</option>
                        <?php foreach ($pastas as $p): ?>
                            <option value="<?= (int)$p['id'] ?>"><?= htmlspecialchars($p['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="materia_id" class="block text-sm font-semibold text-gray-700 mb-2">Matéria</label>
                    <select id="materia_id" name="materia_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white/80">
                        <option value="">Nenhuma</option>
                        <?php foreach ($materias as $m): ?>
                            <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Quadro de anotações (Fabric.js) -->
            <div class="caderno-editor-card rounded-2xl overflow-hidden bg-white border border-gray-200 shadow-lg">
                <div class="caderno-editor-header px-5 py-4 bg-gradient-to-r from-emerald-50 to-white border-b border-gray-200">
                    <h3 class="text-base font-semibold text-gray-800">Quadro de anotações</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Desenhe, escreva e cole imagens (Ctrl+V). Escolha uma ferramenta abaixo e use no quadro.</p>
                </div>
                <div class="toolbar-unico px-4 py-4 space-y-4 border-b border-gray-100">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wide mr-1 hidden sm:inline">Imagens</span>
                        <button type="button" id="btn-colar-img" class="tool-btn-u flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors" title="Colar imagem (Ctrl+V)">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg>
                            Colar
                        </button>
                        <input type="file" id="input-imagem" accept="image/*" class="hidden">
                        <button type="button" id="btn-adicionar-img" class="tool-btn-u flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors" title="Adicionar imagem">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                            Adicionar imagem
                        </button>
                        <span class="w-px h-6 bg-slate-200 mx-1 hidden sm:block"></span>
                        <span class="text-xs font-medium text-slate-400 uppercase tracking-wide mr-1 hidden sm:inline">Ferramentas</span>
                        <button type="button" id="btn-selecionar-u" class="tool-btn-u flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors" data-tool="selecionar" title="Selecionar">Selecionar</button>
                        <button type="button" id="btn-caneta-u" class="tool-btn-u flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-emerald-500 text-white shadow-sm border-transparent transition-colors" data-tool="caneta" title="Caneta">Caneta</button>
                        <button type="button" id="btn-destaque-u" class="tool-btn-u flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors" data-tool="destaque" title="Destaque">Destaque</button>
                        <button type="button" id="btn-seta-u" class="tool-btn-u flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors" data-tool="seta" title="Seta">Seta</button>
                        <button type="button" id="btn-texto-u" class="tool-btn-u flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors" data-tool="texto" title="Texto">Texto</button>
                        <button type="button" id="btn-circulo-u" class="tool-btn-u flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors" data-tool="circulo" title="Círculo">Círculo</button>
                        <button type="button" id="btn-oval-u" class="tool-btn-u flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-slate-600 hover:bg-slate-200 border border-slate-200 transition-colors" data-tool="oval" title="Oval">Oval</button>
                        <button type="button" id="btn-apagar-u" class="tool-btn-u flex items-center gap-1.5 px-3 py-2 rounded-lg text-sm font-medium bg-slate-100 text-rose-600 hover:bg-rose-50 border border-slate-200 transition-colors" data-tool="apagar" title="Apagar">Apagar</button>
                    </div>
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Cor</span>
                            <div class="flex items-center gap-1.5">
                                <button type="button" class="cor-preset w-7 h-7 rounded-lg border-2 border-slate-300 hover:scale-110 transition-transform shadow-sm" data-color="#1f2937" title="Preto" style="background:#1f2937"></button>
                                <button type="button" class="cor-preset w-7 h-7 rounded-lg border-2 border-slate-300 hover:scale-110 transition-transform shadow-sm" data-color="#dc2626" title="Vermelho" style="background:#dc2626"></button>
                                <button type="button" class="cor-preset w-7 h-7 rounded-lg border-2 border-slate-300 hover:scale-110 transition-transform shadow-sm" data-color="#2563eb" title="Azul" style="background:#2563eb"></button>
                                <button type="button" class="cor-preset w-7 h-7 rounded-lg border-2 border-slate-300 hover:scale-110 transition-transform shadow-sm" data-color="#059669" title="Verde" style="background:#059669"></button>
                                <button type="button" class="cor-preset w-7 h-7 rounded-lg border-2 border-slate-300 hover:scale-110 transition-transform shadow-sm" data-color="#e11d48" title="Rosa" style="background:#e11d48"></button>
                                <button type="button" class="cor-preset w-7 h-7 rounded-lg border-2 border-slate-300 hover:scale-110 transition-transform shadow-sm" data-color="#7c3aed" title="Roxo" style="background:#7c3aed"></button>
                            </div>
                            <input type="color" id="cor-u" value="#e11d48" class="w-9 h-9 rounded-lg border-2 border-slate-200 cursor-pointer ml-1" title="Cor personalizada">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Espessura</span>
                            <input type="range" id="espessura-u" min="1" max="20" value="4" class="w-28 h-2 rounded-full accent-emerald-500">
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-medium text-slate-400 uppercase tracking-wide">Altura</span>
                            <select id="altura-quadro" class="rounded-lg border border-slate-200 text-sm py-1.5 px-2 bg-white focus:ring-2 focus:ring-emerald-500 focus:border-emerald-500">
                                <option value="400">400 px</option>
                                <option value="500" selected>500 px</option>
                                <option value="600">600 px</option>
                                <option value="800">800 px</option>
                                <option value="1000">1000 px</option>
                                <option value="1200">1200 px</option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="p-5 bg-slate-100/80 flex justify-center overflow-auto min-h-[480px]">
                    <div id="canvas-unico-container" class="caderno-canvas-wrap rounded-xl shadow-inner bg-white p-1">
                        <canvas id="canvas-unico"></canvas>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-gray-700 mb-2">Anexos (imagens ou documentos)</label>
                <input type="file" name="anexos[]" multiple accept="image/*,.pdf,.doc,.docx,.txt"
                       class="w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded file:border-0 file:bg-green-50 file:text-green-700 file:font-medium">
                <p class="text-xs text-gray-500 mt-1">Máx. 10 MB por arquivo. Imagens (JPG, PNG, GIF, WEBP) ou documentos (PDF, DOC, TXT).</p>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-semibold transition-colors">
                    Salvar anotação
                </button>
                <a href="<?= URL ?>/caderno" class="px-6 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 font-medium transition-colors">
                    Cancelar
                </a>
            </div>
        </form>
    </div>
</div>

<style>
.caderno-canvas-wrap { background: #fff; }
.cor-preset.selected { box-shadow: 0 0 0 2px white, 0 0 0 4px #059669; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var container = document.getElementById('canvas-unico-container');
    if (!container || typeof fabric === 'undefined') return;

    var LARGURA = Math.min(900, (container.parentElement && container.parentElement.offsetWidth) ? container.parentElement.offsetWidth - 32 : 900);
    var ALTURA = parseInt(document.getElementById('altura-quadro').value, 10) || 500;
    var canvasU = new fabric.Canvas('canvas-unico', { selection: true, preserveObjectStacking: true });
    canvasU.setDimensions({ width: LARGURA, height: ALTURA });
    canvasU.setBackgroundColor('white', canvasU.renderAll.bind(canvasU));

    document.getElementById('altura-quadro').addEventListener('change', function() {
        ALTURA = parseInt(this.value, 10) || 500;
        canvasU.setDimensions({ width: LARGURA, height: ALTURA });
        canvasU.renderAll();
    });

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Delete') return;
        var obj = canvasU.getActiveObject();
        if (obj) {
            e.preventDefault();
            canvasU.remove(obj);
            canvasU.renderAll();
        }
    });

    var ferramentaU = 'caneta';
    var corU = document.getElementById('cor-u').value;
    var espessuraU = 4;

    function estiloAtivoU(btn) {
        if (!btn) return;
        btn.classList.remove('bg-slate-100', 'text-slate-600', 'text-rose-600', 'hover:bg-slate-200', 'border-slate-200');
        btn.classList.add('bg-emerald-500', 'text-white', 'shadow-md', 'border-transparent');
    }
    function estiloInativoU(btn) {
        if (!btn) return;
        btn.classList.remove('bg-emerald-500', 'text-white', 'shadow-md', 'border-transparent');
        btn.classList.add('bg-slate-100', 'border', 'border-slate-200', 'hover:bg-slate-200');
        btn.classList.add(btn.getAttribute('data-tool') === 'apagar' ? 'text-rose-600' : 'text-slate-600');
    }
    function atualizarBotoesU() {
        document.querySelectorAll('.tool-btn-u').forEach(function(btn) {
            if (btn.getAttribute('data-tool') === ferramentaU) estiloAtivoU(btn);
            else estiloInativoU(btn);
        });
    }
    function iniciarDesenhoU() {
        canvasU.isDrawingMode = true;
        canvasU.freeDrawingBrush.width = ferramentaU === 'destaque' ? 20 : espessuraU;
        canvasU.freeDrawingBrush.color = ferramentaU === 'destaque' ? 'rgba(255, 235, 59, 0.6)' : corU;
    }
    function pararDesenhoU() {
        canvasU.isDrawingMode = false;
    }

    function adicionarImagemAoCanvas(fileOrDataUrl) {
        var src = typeof fileOrDataUrl === 'string' ? fileOrDataUrl : (fileOrDataUrl && fileOrDataUrl.type && fileOrDataUrl.type.indexOf('image') === 0 ? URL.createObjectURL(fileOrDataUrl) : null);
        if (!src) return;
        fabric.Image.fromURL(src, function(img) {
            if (!img) return;
            var scale = Math.min((LARGURA - 40) / img.getScaledWidth(), (ALTURA - 40) / img.getScaledHeight(), 1);
            img.scale(scale);
            img.set({ left: 20, top: 20, selectable: true, evented: true });
            canvasU.add(img);
            canvasU.renderAll();
            if (typeof fileOrDataUrl !== 'string') URL.revokeObjectURL(src);
        }, { crossOrigin: 'anonymous' });
    }

    document.addEventListener('paste', function(e) {
        var items = e.clipboardData && e.clipboardData.items;
        if (!items) return;
        for (var i = 0; i < items.length; i++) {
            if (items[i].type.indexOf('image') !== -1) {
                e.preventDefault();
                adicionarImagemAoCanvas(items[i].getAsFile());
                return;
            }
        }
    });
    document.getElementById('btn-colar-img').onclick = function() {
        if (navigator.clipboard && navigator.clipboard.read) {
            navigator.clipboard.read().then(function(data) {
                for (var i = 0; i < data.length; i++) {
                    for (var j = 0; j < data[i].types.length; j++) {
                        if (data[i].types[j].indexOf('image') !== -1) {
                            data[i].getType(data[i].types[j]).then(function(blob) {
                                adicionarImagemAoCanvas(blob);
                            });
                            return;
                        }
                    }
                }
                alert('Nenhuma imagem na área de transferência. Copie uma imagem e tente novamente.');
            }).catch(function() {
                alert('Cole com Ctrl+V quando tiver uma imagem copiada.');
            });
        } else {
            alert('Use Ctrl+V para colar uma imagem copiada.');
        }
    };
    document.getElementById('btn-adicionar-img').onclick = function() {
        document.getElementById('input-imagem').click();
    };
    document.getElementById('input-imagem').onchange = function() {
        var f = this.files && this.files[0];
        if (f && f.type.indexOf('image') !== -1) adicionarImagemAoCanvas(f);
        this.value = '';
    };

    document.querySelectorAll('.tool-btn-u[data-tool]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var tool = this.getAttribute('data-tool');
            if (ferramentaU === tool) {
                ferramentaU = 'selecionar';
                pararDesenhoU();
            } else {
                ferramentaU = tool;
                pararDesenhoU();
                if (tool === 'caneta' || tool === 'destaque') iniciarDesenhoU();
                if (tool === 'apagar') {
                    var obj = canvasU.getActiveObject();
                    if (obj) { canvasU.remove(obj); canvasU.renderAll(); }
                }
            }
            atualizarBotoesU();
        });
    });
    document.getElementById('cor-u').oninput = function() {
        corU = this.value;
        if (canvasU.isDrawingMode && ferramentaU !== 'destaque') canvasU.freeDrawingBrush.color = corU;
        document.querySelectorAll('.cor-preset').forEach(function(b) { b.classList.remove('selected'); });
    };
    document.querySelectorAll('.cor-preset').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var c = this.getAttribute('data-color');
            if (!c) return;
            document.getElementById('cor-u').value = c;
            corU = c;
            if (canvasU.isDrawingMode && ferramentaU !== 'destaque') canvasU.freeDrawingBrush.color = c;
            document.querySelectorAll('.cor-preset').forEach(function(b) { b.classList.remove('selected'); });
            this.classList.add('selected');
        });
    });
    document.getElementById('espessura-u').oninput = function() {
        espessuraU = parseInt(this.value, 10);
        if (canvasU.isDrawingMode && ferramentaU !== 'destaque') canvasU.freeDrawingBrush.width = espessuraU;
    };

    var linhaU, inicioX, inicioY, fimX, fimY;
    canvasU.on('mouse:down', function(o) {
        if (ferramentaU !== 'seta' || o.target) return;
        var p = canvasU.getPointer(o.e);
        inicioX = p.x; inicioY = p.y; fimX = p.x; fimY = p.y;
        linhaU = new fabric.Line([p.x, p.y, p.x, p.y], { stroke: corU, strokeWidth: Math.max(2, espessuraU) });
        canvasU.add(linhaU);
    });
    canvasU.on('mouse:move', function(o) {
        if (!linhaU || ferramentaU !== 'seta') return;
        var p = canvasU.getPointer(o.e);
        fimX = p.x; fimY = p.y;
        linhaU.set({ x2: p.x, y2: p.y });
        canvasU.renderAll();
    });
    canvasU.on('mouse:up', function() {
        if (ferramentaU !== 'seta' || !linhaU) return;
        var dx = fimX - inicioX, dy = fimY - inicioY;
        var comprimento = Math.sqrt(dx * dx + dy * dy);
        if (comprimento < 2) {
            canvasU.remove(linhaU);
            linhaU = null;
        } else {
            var ang = Math.atan2(dy, dx), angDeg = (ang * 180 / Math.PI) + 90;
            var tri = new fabric.Triangle({
                width: 16, height: 16, fill: corU,
                left: fimX - Math.cos(ang) * 8, top: fimY - Math.sin(ang) * 8,
                originX: 'center', originY: 'center', angle: angDeg
            });
            canvasU.add(tri);
            linhaU = null;
        }
        canvasU.renderAll();
    });

    var circInicioX, circInicioY, circlePreviewU;
    canvasU.on('mouse:down', function(o) {
        if (ferramentaU !== 'circulo' || o.target) return;
        var p = canvasU.getPointer(o.e);
        circInicioX = p.x; circInicioY = p.y;
        circlePreviewU = new fabric.Circle({
            left: p.x, top: p.y, radius: 0, stroke: corU, strokeWidth: Math.max(2, espessuraU),
            fill: 'transparent', selectable: false, evented: false
        });
        canvasU.add(circlePreviewU);
    });
    canvasU.on('mouse:move', function(o) {
        if (ferramentaU !== 'circulo' || !circlePreviewU) return;
        var p = canvasU.getPointer(o.e);
        var dx = p.x - circInicioX, dy = p.y - circInicioY;
        var r = Math.min(Math.abs(dx), Math.abs(dy)) / 2;
        if (r < 1) r = 1;
        circlePreviewU.set({ radius: r, left: circInicioX + dx/2 - r, top: circInicioY + dy/2 - r });
        canvasU.renderAll();
    });
    canvasU.on('mouse:up', function() {
        if (ferramentaU !== 'circulo' || !circlePreviewU) return;
        var prev = circlePreviewU;
        var r = prev.radius;
        canvasU.remove(prev);
        circlePreviewU = null;
        if (r >= 3) {
            canvasU.add(new fabric.Circle({
                left: prev.left, top: prev.top, radius: r,
                stroke: corU, strokeWidth: Math.max(2, espessuraU), fill: 'transparent'
            }));
        }
        canvasU.renderAll();
    });

    var ovalInicioX, ovalInicioY, ovalPreviewU;
    canvasU.on('mouse:down', function(o) {
        if (ferramentaU !== 'oval' || o.target) return;
        var p = canvasU.getPointer(o.e);
        ovalInicioX = p.x; ovalInicioY = p.y;
        ovalPreviewU = new fabric.Ellipse({
            left: p.x, top: p.y, rx: 0, ry: 0, stroke: corU, strokeWidth: Math.max(2, espessuraU),
            fill: 'transparent', selectable: false, evented: false
        });
        canvasU.add(ovalPreviewU);
    });
    canvasU.on('mouse:move', function(o) {
        if (ferramentaU !== 'oval' || !ovalPreviewU) return;
        var p = canvasU.getPointer(o.e);
        var dx = p.x - ovalInicioX, dy = p.y - ovalInicioY;
        var rx = Math.abs(dx)/2, ry = Math.abs(dy)/2;
        if (rx < 1) rx = 1; if (ry < 1) ry = 1;
        ovalPreviewU.set({ rx: rx, ry: ry, left: ovalInicioX + dx/2 - rx, top: ovalInicioY + dy/2 - ry });
        canvasU.renderAll();
    });
    canvasU.on('mouse:up', function() {
        if (ferramentaU !== 'oval' || !ovalPreviewU) return;
        var prev = ovalPreviewU;
        var rx = prev.rx, ry = prev.ry;
        canvasU.remove(prev);
        ovalPreviewU = null;
        if (rx >= 3 || ry >= 3) {
            canvasU.add(new fabric.Ellipse({
                left: prev.left, top: prev.top, rx: rx, ry: ry,
                stroke: corU, strokeWidth: Math.max(2, espessuraU), fill: 'transparent'
            }));
        }
        canvasU.renderAll();
    });

    canvasU.on('mouse:down', function(o) {
        if (ferramentaU !== 'texto' || o.target) return;
        var p = canvasU.getPointer(o.e);
        var txt = new fabric.IText('Digite seu texto aqui no quadro', {
            left: p.x, top: p.y, fontFamily: 'Arial', fontSize: 18, fill: corU
        });
        canvasU.add(txt);
        canvasU.setActiveObject(txt);
        txt.enterEditing();
        txt.selectAll();
        canvasU.renderAll();
    });

    iniciarDesenhoU();
    atualizarBotoesU();
    var corInicial = document.getElementById('cor-u').value;
    var presetInicial = document.querySelector('.cor-preset[data-color="' + corInicial + '"]');
    if (presetInicial) presetInicial.classList.add('selected');

    document.getElementById('form-caderno').addEventListener('submit', function() {
        var payload = {
            canvas: canvasU.toObject(['data']),
            texto: '',
            canvasHeight: ALTURA
        };
        document.getElementById('observacao').value = JSON.stringify(payload);
    });
});
</script>
