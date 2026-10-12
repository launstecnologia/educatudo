<?php
$caderno = $caderno ?? [];
$anexos = $anexos ?? [];
$cadernoId = (int)($caderno['id'] ?? 0);
$observacaoRaw = $caderno['observacao'] ?? '';
$observacaoPayload = null;
$ehExcalidraw = false;
if (trim($observacaoRaw) !== '' && strpos(trim($observacaoRaw), '{') === 0) {
    $observacaoPayload = @json_decode($observacaoRaw, true);
    if (!is_array($observacaoPayload)) {
        $observacaoPayload = null;
    } else {
        $ehExcalidraw = isset($observacaoPayload['type']) && $observacaoPayload['type'] === 'excalidraw';
    }
}
?>
<div class="container mx-auto px-4 py-6 max-w-7xl w-full">
    <div class="mb-6 flex items-center justify-between flex-wrap gap-4">
        <a href="<?= URL ?>/caderno" class="text-green-600 hover:text-green-700 font-medium flex items-center gap-1">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
            Voltar ao caderno
        </a>
        <div class="flex gap-2">
            <a href="<?= URL ?>/caderno/<?= $cadernoId ?>/editar" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 font-medium transition-colors">
                Editar
            </a>
            <form method="POST" action="<?= URL ?>/caderno/<?= $cadernoId ?>/excluir" class="inline" onsubmit="return confirm('Excluir esta anotação e todos os anexos?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="px-4 py-2 border border-red-300 text-red-600 rounded-lg hover:bg-red-50 font-medium transition-colors">
                    Excluir
                </button>
            </form>
        </div>
    </div>

    <?php if (!empty($_SESSION['success_message'])): ?>
        <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg border border-green-300"><?= htmlspecialchars($_SESSION['success_message']) ?></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <div class="caderno-wrapper">
        <div class="caderno-page-title flex flex-wrap items-center gap-2">
            <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($caderno['titulo']) ?></h1>
            <?php if (!empty($caderno['pasta_nome'])): ?>
                <span class="text-sm font-medium text-amber-800 bg-amber-100 px-2 py-0.5 rounded"><?= htmlspecialchars($caderno['pasta_nome']) ?></span>
            <?php endif; ?>
        </div>
        <?php if (!empty($caderno['materia_nome'])): ?>
            <p class="text-green-700 font-medium text-sm mb-2"><?= htmlspecialchars($caderno['materia_nome']) ?></p>
        <?php endif; ?>
        <p class="text-sm text-gray-500 mb-4"><?= date('d/m/Y H:i', strtotime($caderno['updated_at'] ?? $caderno['created_at'])) ?></p>

        <?php if ($ehExcalidraw): ?>
            <div class="mb-6 border border-gray-200 rounded-xl overflow-hidden bg-white shadow-sm">
                <div class="text-sm font-medium text-gray-500 px-4 py-2 border-b border-gray-200 bg-slate-50">Quadro de anotações</div>
                <iframe
                    src="<?= htmlspecialchars(URL . '/caderno/' . $cadernoId . '/excalidraw-view') ?>"
                    class="w-full border-0"
                    style="height: 60vh; min-height: 400px;"
                    title="Visualização da anotação"
                ></iframe>
            </div>
        <?php elseif ($observacaoPayload): ?>
            <?php if (!empty($observacaoPayload['texto'])): ?>
                <div class="mb-4 p-4 bg-white border border-gray-200 rounded-lg shadow-sm">
                    <div class="text-sm font-medium text-gray-500 mb-1">Texto da anotação</div>
                    <div class="caderno-prose prose prose-sm max-w-none whitespace-pre-wrap text-gray-800"><?= nl2br(htmlspecialchars($observacaoPayload['texto'])) ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($observacaoPayload['canvas']) && isset($observacaoPayload['canvas']['objects'])): ?>
                <div class="mb-6">
                    <div class="text-sm font-medium text-gray-500 mb-1">Quadro (imagens e desenhos)</div>
                    <div id="canvas-show-wrapper" class="inline-block bg-gray-100 p-4 rounded-lg overflow-auto max-w-full">
                        <canvas id="canvas-show"></canvas>
                    </div>
                </div>
                <script>window.cadernoObservacaoCanvasData = <?= json_encode($observacaoPayload['canvas']) ?>;</script>
            <?php endif; ?>
        <?php elseif (trim($observacaoRaw) !== ''): ?>
            <div class="caderno-prose prose prose-sm max-w-none mb-6">
                <?= $observacaoRaw ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($anexos)): ?>
            <div class="pt-4 border-t border-gray-200/60">
                <h2 class="text-sm font-semibold text-gray-700 mb-3">Anexos</h2>
                <div class="space-y-4">
                    <?php foreach ($anexos as $a):
                        $ehImagem = (($a['tipo'] ?? '') === 'imagem');
                        $ehPdf = (($a['mime_type'] ?? '') === 'application/pdf');
                        $podeAnotar = $ehImagem || $ehPdf;
                        $temAnotacoes = !empty($a['anotacao_canvas']);
                    ?>
                        <div class="border border-gray-200 rounded-lg overflow-hidden bg-white/60">
                            <?php if ($ehImagem): ?>
                                <a href="<?= URL ?>/caderno/<?= $cadernoId ?>/anexo/<?= (int)$a['id'] ?>" target="_blank" class="block">
                                    <img src="<?= URL ?>/caderno/<?= $cadernoId ?>/anexo/<?= (int)$a['id'] ?>" alt="<?= htmlspecialchars($a['nome_original']) ?>" class="w-full max-h-80 object-contain bg-white">
                                </a>
                            <?php endif; ?>
                            <div class="p-3 flex items-center justify-between flex-wrap gap-2">
                                <a href="<?= URL ?>/caderno/<?= $cadernoId ?>/anexo/<?= (int)$a['id'] ?>" target="_blank" class="text-green-600 hover:text-green-700 font-medium truncate flex-1">
                                    <?= htmlspecialchars($a['nome_original']) ?>
                                </a>
                                <?php if ($podeAnotar): ?>
                                    <button type="button" class="btn-abrir-anotar inline-flex items-center gap-1.5 px-3 py-1.5 bg-amber-100 text-amber-800 border border-amber-300 rounded-lg text-sm font-medium hover:bg-amber-200 transition-colors" data-url-anotar="<?= htmlspecialchars(URL . '/caderno/' . $cadernoId . '/anexo/' . (int)$a['id'] . '/anotar') ?>">
                                        <?= $temAnotacoes ? '✏️ Ver/Editar anotações' : '✏️ Anotar (escrever por cima)' ?>
                                    </button>
                                    <?php if ($temAnotacoes): ?>
                                        <span class="px-2 py-0.5 bg-green-100 text-green-800 text-xs font-medium rounded">Com anotações</span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($observacaoPayload['canvas']) && isset($observacaoPayload['canvas']['objects'])): ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/fabric.js/5.3.0/fabric.min.js"></script>
<script>
(function() {
    var data = window.cadernoObservacaoCanvasData;
    if (!data || typeof fabric === 'undefined') return;
    var el = document.getElementById('canvas-show');
    if (!el) return;
    var w = (data.width != null) ? data.width : 900;
    var h = (data.height != null) ? data.height : 500;
    var canvas = new fabric.Canvas('canvas-show', { selection: false });
    canvas.setDimensions({ width: w, height: h });
    canvas.loadFromJSON(data, function() {
        canvas.getObjects().forEach(function(obj) { obj.set({ selectable: false, evented: false }); });
        canvas.renderAll();
    });
})();
</script>
<?php endif; ?>

<!-- Modal: Anotar por cima (mesma tela) -->
<div id="modalAnotarAnexo" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="fixed inset-0 bg-black/60" id="modalAnotarBackdrop"></div>
    <div class="fixed inset-4 md:inset-8 bg-white rounded-2xl shadow-2xl flex flex-col z-50 overflow-hidden">
        <div class="flex items-center justify-between px-4 py-3 border-b border-gray-200 bg-gray-50">
            <h3 class="text-lg font-semibold text-gray-900">Escrever por cima do anexo</h3>
            <button type="button" id="modalAnotarFechar" class="p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100" aria-label="Fechar">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <div class="flex-1 min-h-0 relative">
            <iframe id="iframeAnotar" src="" class="absolute inset-0 w-full h-full border-0 rounded-b-2xl" title="Editor de anotações"></iframe>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('modalAnotarAnexo');
    var iframe = document.getElementById('iframeAnotar');
    var backdrop = document.getElementById('modalAnotarBackdrop');
    var btnFechar = document.getElementById('modalAnotarFechar');
    var cadernoPath = '<?= htmlspecialchars(URL . '/caderno/' . $cadernoId, ENT_QUOTES, 'UTF-8') ?>';

    function abrirModal(url) {
        if (!url) return;
        iframe.src = url;
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }

    function fecharModal() {
        iframe.src = 'about:blank';
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function urlEhPaginaCaderno(href) {
        if (!href) return false;
        var u = (href || '').split('?')[0];
        return (u === cadernoPath || u === cadernoPath + '/') && href.indexOf('/anexo/') === -1 && href.indexOf('/anotar') === -1;
    }

    iframe.addEventListener('load', function() {
        try {
            var loc = iframe.contentWindow && iframe.contentWindow.location;
            if (loc && urlEhPaginaCaderno(loc.href)) {
                fecharModal();
                window.location.reload();
            }
        } catch (e) {
            // cross-origin ou iframe ainda carregando
        }
    });

    document.querySelectorAll('.btn-abrir-anotar').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var url = this.getAttribute('data-url-anotar');
            abrirModal(url);
        });
    });

    backdrop.addEventListener('click', fecharModal);
    if (btnFechar) btnFechar.addEventListener('click', fecharModal);
})();
</script>
