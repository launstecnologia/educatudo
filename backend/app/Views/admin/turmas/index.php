<?php
$turmas = $turmas ?? [];
$csrf_token = $csrf_token ?? '';

$statsTurmas = $stats_turmas ?? [];
$totalTurmas = (int)($statsTurmas['total_turmas'] ?? count($turmas));
$turmasAtivas = (int)($statsTurmas['turmas_ativas'] ?? 0);
$totalAlunos = (int)($statsTurmas['total_alunos'] ?? 0);
$mediaAlunos = $statsTurmas['media_alunos'] ?? 0;

$page_header_title = 'Turmas';
$page_header_subtitle = 'Gerencie as turmas da escola';
ob_start();
?>
<a href="<?= URL ?>/admin/turmas/create"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova Turma
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<?php if ($totalTurmas > 0): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm text-gray-600">Total de turmas</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $totalTurmas ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm text-gray-600">Turmas ativas</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $turmasAtivas ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm text-gray-600">Total de alunos</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $totalAlunos ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-5">
        <p class="text-sm text-gray-600">Média por turma</p>
        <p class="text-2xl font-bold text-gray-900 mt-1"><?= $mediaAlunos ?></p>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <?php if (!empty($turmas)): ?>
    <?php
    $bulk_delete_csrf_token = $csrf_token;
    $bulk_delete_bulk_url = URL . '/admin/turmas/bulk-delete';
    $bulk_delete_ids_param = 'turma_ids';
    $bulk_delete_entity_singular = 'turma';
    $bulk_delete_entity_plural = 'turmas';
    $bulk_delete_single_url_template = URL . '/admin/turmas/{id}';
    $bulk_delete_single_method = 'DELETE';
    include __DIR__ . '/../_partials/bulk_delete_modal.php';
    ?>
    <?php endif; ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php if (!empty($turmas)): ?>
                    <th class="px-4 py-3 text-left w-10">
                        <input type="checkbox" id="bulk-select-all" class="rounded border-gray-300 text-red-600" title="Selecionar todos">
                    </th>
                    <?php endif; ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($turmas)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-users-rectangle text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma turma cadastrada</p>
                        <a href="<?= URL ?>/admin/turmas/create"
                           class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i>
                            Nova Turma
                        </a>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($turmas as $turma): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap">
                        <input type="checkbox" class="bulk-row-checkbox rounded border-gray-300 text-red-600" value="<?= (int) $turma['id'] ?>">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($turma['nome']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?= htmlspecialchars($turma['serie']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                            <?= (int) $turma['total_alunos'] ?> alunos
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $turma['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $turma['ativo'] ? 'Ativa' : 'Inativa' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/turmas/<?= (int) $turma['id'] ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-circle-info text-gray-400 w-4 text-center"></i> Detalhes
                        </a>
                        <a href="<?= URL ?>/admin/turmas/<?= (int) $turma['id'] ?>/edit"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </a>
                        <button type="button"
                                onclick="toggleStatus(<?= (int) $turma['id'] ?>, <?= $turma['ativo'] ? 'false' : 'true' ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-power-off text-gray-400 w-4 text-center"></i> <?= $turma['ativo'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="openBulkDeleteSingle(<?= (int) $turma['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-turma-' . (int) $turma['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pag = $pagination ?? [];
    $pagTotal = (int)($pag['total'] ?? 0);
    $pagPerPage = (int)($pag['per_page'] ?? 10);
    $pagPage = (int)($pag['page'] ?? 1);
    $pagTotalPages = (int)($pag['total_pages'] ?? 1);
    $pagQueryParams = $_GET ?? [];
    unset($pagQueryParams['page']);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> turma(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/turmas<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/turmas<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/turmas<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
function toggleStatus(id, newStatus) {
    if (!confirm('Tem certeza que deseja ' + (newStatus ? 'ativar' : 'desativar') + ' esta turma?')) {
        return;
    }
    fetch('<?= URL ?>/admin/turmas/' + id + '/toggle-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: '_token=' + encodeURIComponent(<?= json_encode($csrf_token) ?>)
    })
    .then(function (r) { return r.json(); })
    .then(function (data) {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Falha ao alterar status'));
        }
    })
    .catch(function () { alert('Erro de conexão. Tente novamente.'); });
}
</script>
