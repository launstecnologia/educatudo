<!-- Header -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Mural de Recados</h2>
            <p class="text-gray-600">Recados para turmas ou todos os alunos.</p>
        </div>
        <a href="<?= URL ?>/professor/mural-recados/criar" class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition-all flex items-center shadow-lg">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path></svg>
            Novo Recado
        </a>
    </div>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
<?php
$msg = $_SESSION['flash_message'];
$typ = $_SESSION['flash_type'] ?? 'success';
unset($_SESSION['flash_message'], $_SESSION['flash_type']);
?>
<div class="mb-4 p-4 rounded-lg <?= $typ === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
    <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<!-- Filtros: Matéria, Entre datas -->
<div class="bg-white rounded-xl shadow-lg p-4 mb-6">
    <form method="get" action="<?= URL ?>/professor/mural-recados" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
            <select name="materia_id" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todas</option>
                <?php foreach ($materias_opcoes ?? [] as $mat): ?>
                <option value="<?= (int)$mat['id'] ?>" <?= (int)($filtro_materia ?? 0) === (int)$mat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mat['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Data de</label>
            <input type="date" name="data_de" value="<?= htmlspecialchars($filtro_data_de ?? '') ?>" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Data até</label>
            <input type="date" name="data_ate" value="<?= htmlspecialchars($filtro_data_ate ?? '') ?>" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                <i class="fas fa-filter"></i> Filtrar
            </button>
            <a href="<?= URL ?>/professor/mural-recados" class="inline-flex items-center gap-2 px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                <i class="fas fa-eraser"></i> Limpar
            </a>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Destinatários</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($recados)): ?>
                <tr>
                    <td colspan="3" class="px-6 py-8 text-center text-gray-500">Nenhum recado ainda. Clique em "Novo Recado" para publicar.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($recados as $r): ?>
                <tr class="hover:bg-gray-50" data-recado-titulo="<?= htmlspecialchars($r['titulo'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <td class="px-6 py-4">
                        <span class="font-medium text-gray-900"><?= htmlspecialchars($r['titulo']) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?= !empty($r['enviar_para_todos']) ? 'Todos' : (htmlspecialchars($r['turmas_nomes'] ?? '-')) ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <div class="flex items-center justify-end gap-3 flex-wrap">
                            <button type="button" class="btn-ver-recado inline-flex items-center gap-1.5 text-gray-700 hover:text-blue-600 text-sm" data-recado-id="<?= (int)$r['id'] ?>" title="Ver texto do recado">
                                <i class="fas fa-eye"></i> Ver
                            </button>
                            <a href="<?= URL ?>/professor/mural-recados/editar?id=<?= (int)$r['id'] ?>" class="inline-flex items-center gap-1.5 text-blue-600 hover:text-blue-800 text-sm">
                                <i class="fas fa-edit"></i> Editar
                            </a>
                            <form action="<?= URL ?>/professor/mural-recados/excluir" method="post" class="inline" onsubmit="return confirm('Excluir este recado?');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                <button type="submit" class="inline-flex items-center gap-1.5 text-red-600 hover:text-red-800 text-sm">
                                    <i class="fas fa-trash-alt"></i> Excluir
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Conteúdos dos recados (oculto, para o modal Ver) -->
<?php if (!empty($recados)): ?>
<div id="recados-conteudos-holder" class="hidden" aria-hidden="true">
    <?php foreach ($recados as $r): ?>
    <div id="recado-conteudo-<?= (int)$r['id'] ?>" class="recado-conteudo-item"><?= rich_text_render($r['conteudo'] ?? '') ?></div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Modal Ver recado -->
<div id="modalVerRecado" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modalVerRecadoTitle" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" id="modalVerRecadoBackdrop"></div>
        <div class="relative inline-block w-full max-w-2xl p-0 my-8 overflow-hidden text-left align-middle transition-all transform bg-white rounded-xl shadow-xl">
            <div class="px-6 py-4 border-b border-gray-200 flex justify-between items-center">
                <h3 id="modalVerRecadoTitle" class="text-lg font-semibold text-gray-900">Recado</h3>
                <button type="button" id="modalVerRecadoFechar" class="text-gray-400 hover:text-gray-600 p-1 rounded" aria-label="Fechar">
                    <i class="fas fa-times text-xl"></i>
                </button>
            </div>
            <div id="modalVerRecadoBody" class="px-6 py-4 max-h-[70vh] overflow-y-auto prose prose-sm max-w-none text-gray-700">
                <!-- conteúdo preenchido via JS -->
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('modalVerRecado');
    var modalTitle = document.getElementById('modalVerRecadoTitle');
    var modalBody = document.getElementById('modalVerRecadoBody');
    var modalBackdrop = document.getElementById('modalVerRecadoBackdrop');
    var btnFechar = document.getElementById('modalVerRecadoFechar');
    if (!modal || !modalBody) return;
    function abrirModal(titulo, conteudoHtml) {
        modalTitle.textContent = titulo || 'Recado';
        modalBody.innerHTML = conteudoHtml || '<p class="text-gray-500">Sem conteúdo.</p>';
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    function fecharModal() {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
    document.querySelectorAll('.btn-ver-recado').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-recado-id');
            var row = this.closest('tr');
            var titulo = row ? row.getAttribute('data-recado-titulo') : '';
            var elConteudo = document.getElementById('recado-conteudo-' + id);
            var conteudoHtml = elConteudo ? elConteudo.innerHTML : '';
            abrirModal(titulo, conteudoHtml);
        });
    });
    if (modalBackdrop) modalBackdrop.addEventListener('click', fecharModal);
    if (btnFechar) btnFechar.addEventListener('click', fecharModal);
    modal.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') fecharModal();
    });
})();
</script>
