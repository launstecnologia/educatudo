<?php
/**
 * Modal genérico "só para visualizar" (sem botão de impressão).
 * Use abrirModalConteudoVisualizar('id-do-conteudo-oculto', 'Título') para abrir.
 * Inclua este partial uma única vez por página.
 */
?>
<?php if (!defined('MODAL_ABRIR_CONTEUDO_VISUALIZAR_INCLUIDO')): ?>
<?php define('MODAL_ABRIR_CONTEUDO_VISUALIZAR_INCLUIDO', true); ?>
<div id="modal-abrir-conteudo-visualizar" class="hidden fixed inset-0 z-50 p-4 sm:p-6">
    <div class="absolute inset-0 bg-black/50" onclick="fecharModalConteudoVisualizar()"></div>
    <div class="relative bg-white rounded-xl border border-gray-200 shadow-xl max-w-6xl mx-auto h-[85vh] flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
            <h4 id="modal-abrir-conteudo-visualizar-title" class="text-base font-semibold text-gray-900">Visualizar</h4>
            <button type="button" class="px-3 py-1.5 text-sm rounded-md bg-gray-200 hover:bg-gray-300 text-gray-700" onclick="fecharModalConteudoVisualizar()">Fechar</button>
        </div>
        <div id="modal-abrir-conteudo-visualizar-body" class="w-full flex-1 overflow-y-auto p-4 bg-gray-50"></div>
    </div>
</div>
<script>
function abrirModalConteudoVisualizar(contentId, title) {
    if (!contentId) return;
    const content = document.getElementById(contentId);
    if (!content) return;
    const modal = document.getElementById('modal-abrir-conteudo-visualizar');
    const body = document.getElementById('modal-abrir-conteudo-visualizar-body');
    const titleEl = document.getElementById('modal-abrir-conteudo-visualizar-title');
    if (!modal || !body || !titleEl) return;
    titleEl.textContent = title || 'Visualizar';
    body.innerHTML = content.innerHTML;
    modal.classList.remove('hidden');
}

function fecharModalConteudoVisualizar() {
    const modal = document.getElementById('modal-abrir-conteudo-visualizar');
    const body = document.getElementById('modal-abrir-conteudo-visualizar-body');
    if (modal) modal.classList.add('hidden');
    if (body) body.innerHTML = '';
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        fecharModalConteudoVisualizar();
    }
});
</script>
<?php endif; ?>
