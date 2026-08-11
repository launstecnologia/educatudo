<?php
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10, 'total' => 0, 'total_pages' => 1];
$rows = $rows ?? [];
$boardOptions = $board_options ?? [];
$teacherOptions = $teacher_options ?? [];

$queryParams = $filters;
unset($queryParams['page']);

$currentPage = (int) ($pagination['page'] ?? 1);
$totalPages = (int) ($pagination['total_pages'] ?? 1);
$paginationItems = [];
if ($totalPages > 0) {
    $pagesToShow = [1, $totalPages];
    for ($i = $currentPage - 2; $i <= $currentPage + 2; $i++) {
        if ($i >= 1 && $i <= $totalPages) {
            $pagesToShow[] = $i;
        }
    }
    $pagesToShow = array_values(array_unique($pagesToShow));
    sort($pagesToShow);

    $previousPage = null;
    foreach ($pagesToShow as $pageNumber) {
        if ($previousPage !== null && $pageNumber - $previousPage > 1) {
            $paginationItems[] = 'ellipsis';
        }
        $paginationItems[] = $pageNumber;
        $previousPage = $pageNumber;
    }
}
?>

<?php
$filtrosAtivosCount = 0;
foreach (['proposal_title', 'board_name', 'teacher_name', 'student_name', 'status', 'submitted_from', 'submitted_to', 'corrected_from', 'corrected_to'] as $fk) {
    if (!empty($filters[$fk])) {
        $filtrosAtivosCount++;
    }
}
?>
<div class="mb-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Relatório de Redações</h2>
            <p class="text-gray-600">Filtre envios por proposta, banca, professor, aluno, status e datas.</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
            <a href="<?= URL ?>/admin/redacao-professor/analytics"
               class="inline-flex items-center px-4 py-2.5 border border-indigo-300 rounded-lg text-sm font-medium text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors">
                <i class="fa-solid fa-chart-line mr-2"></i>
                Analytics
            </a>
            <a href="<?= URL ?>/admin/redacao-professor"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-arrow-left mr-2 text-gray-500"></i>
                Voltar para propostas
            </a>
        </div>
    </div>
</div>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar relatório</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/redacao-professor/relatorio" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="f_proposal_title" class="block text-sm font-medium text-gray-700 mb-1.5">Título da proposta</label>
                <input type="text" id="f_proposal_title" name="proposal_title" value="<?= htmlspecialchars((string) ($filters['proposal_title'] ?? '')) ?>"
                       placeholder="Buscar por título"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="f_board_name" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de banca</label>
                <select id="f_board_name" name="board_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($boardOptions as $boardName): ?>
                    <option value="<?= htmlspecialchars($boardName) ?>" <?= (($filters['board_name'] ?? '') === $boardName) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($boardName) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f_teacher_name" class="block text-sm font-medium text-gray-700 mb-1.5">Professor</label>
                <select id="f_teacher_name" name="teacher_name" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($teacherOptions as $teacherName): ?>
                    <option value="<?= htmlspecialchars($teacherName) ?>" <?= (($filters['teacher_name'] ?? '') === $teacherName) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($teacherName) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="f_student_name" class="block text-sm font-medium text-gray-700 mb-1.5">Aluno</label>
                <input type="text" id="f_student_name" name="student_name" value="<?= htmlspecialchars((string) ($filters['student_name'] ?? '')) ?>"
                       placeholder="Nome do aluno"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="f_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="f_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="nao_enviado" <?= (($filters['status'] ?? '') === 'nao_enviado') ? 'selected' : '' ?>>Não enviado</option>
                    <option value="visualizado" <?= (($filters['status'] ?? '') === 'visualizado') ? 'selected' : '' ?>>Visualizado</option>
                    <option value="enviado" <?= (($filters['status'] ?? '') === 'enviado') ? 'selected' : '' ?>>Enviado</option>
                    <option value="corrigido" <?= (($filters['status'] ?? '') === 'corrigido') ? 'selected' : '' ?>>Corrigido</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label for="f_submitted_from" class="block text-sm font-medium text-gray-700 mb-1.5">Envio de</label>
                    <input type="date" id="f_submitted_from" name="submitted_from" value="<?= htmlspecialchars((string) ($filters['submitted_from'] ?? '')) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="f_submitted_to" class="block text-sm font-medium text-gray-700 mb-1.5">Envio até</label>
                    <input type="date" id="f_submitted_to" name="submitted_to" value="<?= htmlspecialchars((string) ($filters['submitted_to'] ?? '')) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="f_corrected_from" class="block text-sm font-medium text-gray-700 mb-1.5">Correção de</label>
                    <input type="date" id="f_corrected_from" name="corrected_from" value="<?= htmlspecialchars((string) ($filters['corrected_from'] ?? '')) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="f_corrected_to" class="block text-sm font-medium text-gray-700 mb-1.5">Correção até</label>
                    <input type="date" id="f_corrected_to" name="corrected_to" value="<?= htmlspecialchars((string) ($filters['corrected_to'] ?? '')) ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label for="f_per_page" class="block text-sm font-medium text-gray-700 mb-1.5">Itens por página</label>
                <select id="f_per_page" name="per_page" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ([10, 25, 50, 100] as $perPageOption): ?>
                    <option value="<?= $perPageOption ?>" <?= ((int) ($filters['per_page'] ?? 10) === $perPageOption) ? 'selected' : '' ?>>
                        <?= $perPageOption ?> por página
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <button type="button" onclick="clearFilters()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-gray-900">Resultados</h3>
        <p class="text-sm text-gray-600">
            <?= (int) ($pagination['total'] ?? 0) ?> resultado(s) encontrado(s)
        </p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título da proposta</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de banca</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data envio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data correção</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">Nenhum registro encontrado com os filtros informados.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <?php
                    $statusClass = ($row['status'] ?? '') === 'corrigido'
                        ? 'bg-blue-100 text-blue-800'
                        : (($row['status'] ?? '') === 'enviado'
                            ? 'bg-green-100 text-green-800'
                            : (($row['status'] ?? '') === 'visualizado'
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-gray-100 text-gray-800'));
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($row['proposal_title'] ?? '')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars((string) ($row['board_name'] ?? '')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-800"><?= htmlspecialchars((string) ($row['teacher_name'] ?? '')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-800"><?= htmlspecialchars((string) ($row['student_name'] ?? '')) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>">
                                <?= htmlspecialchars((string) ($row['status_label'] ?? '')) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= !empty($row['submitted_at']) ? date('d/m/Y H:i', strtotime((string) $row['submitted_at'])) : '—' ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= !empty($row['corrected_at']) ? date('d/m/Y H:i', strtotime((string) $row['corrected_at'])) : '—' ?></td>
                        <td class="px-6 py-4 text-sm">
                            <?php if (!empty($row['submission_id'])): ?>
                                <?php
                                $detailUrl = URL . '/admin/redacao-professor/propostas/' . (int) ($row['proposal_id'] ?? 0) . '/envios/' . (int) $row['submission_id'];
                                ob_start();
                                ?>
                                <a href="<?= htmlspecialchars($detailUrl . '#redacao') ?>"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-file-lines text-gray-400 w-4 text-center"></i> Visualizar redação
                                </a>
                                <a href="<?= htmlspecialchars($detailUrl . '#correcao') ?>"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-circle-check text-gray-400 w-4 text-center"></i> Ver correção
                                </a>
                                <?php
                                $row_actions_dropdown_items = ob_get_clean();
                                $row_actions_dropdown_id = 'row-actions-submission-' . (int) $row['submission_id'];
                                include __DIR__ . '/../_partials/row_actions_dropdown.php';
                                ?>
                            <?php else: ?>
                                <span class="text-gray-400">Sem envio</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pagTotal = (int) ($pagination['total'] ?? 0);
    $pagPerPage = (int) ($pagination['per_page'] ?? 10);
    $pagBaseQuery2 = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
    $pagSep2 = $pagBaseQuery2 === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= count($rows) ?> de <?= $pagTotal ?> registro(s)
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($currentPage > 1): ?>
                <a href="<?= URL ?>/admin/redacao-professor/relatorio<?= $pagBaseQuery2 . $pagSep2 ?>page=<?= $currentPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/redacao-professor/relatorio<?= $pagBaseQuery2 . $pagSep2 ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $currentPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($currentPage < $totalPages): ?>
                <a href="<?= URL ?>/admin/redacao-professor/relatorio<?= $pagBaseQuery2 . $pagSep2 ?>page=<?= $currentPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    function openFilterDrawer() {
        document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
        const drawer = document.getElementById('filterDrawer');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeFilterDrawer() {
        document.getElementById('filterDrawerBackdrop').classList.add('hidden');
        const drawer = document.getElementById('filterDrawer');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function clearFilters() {
        window.location.href = <?= json_encode(URL . '/admin/redacao-professor/relatorio') ?>;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilterDrawer();
        }
    });
</script>
