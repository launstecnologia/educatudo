<?php
$filters = $filters ?? [];
$pagination = $pagination ?? ['page' => 1, 'per_page' => 10, 'total' => 0, 'total_pages' => 1];
$rows = $rows ?? [];
$boardOptions = $board_options ?? [];
$turmaOptions = $turma_options ?? [];

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

<style>
    .report-field {
        min-height: 42px;
        -webkit-appearance: none;
        appearance: none;
        line-height: 1.2;
    }

    .report-field[type="date"] {
        min-width: 0;
    }

    .report-field::-webkit-date-and-time-value {
        text-align: left;
    }
</style>

<div class="mb-8">
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Relatório de Redações</h2>
            <p class="text-gray-600">Filtre envios por proposta, banca, aluno, status, notas e datas.</p>
        </div>
        <a href="<?= URL ?>/professor/redacao-configuravel" class="inline-flex items-center justify-center bg-white text-gray-700 px-4 py-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 transition-all duration-200">
            ← Voltar para propostas
        </a>
    </div>
</div>

<div class="bg-white/90 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 p-6 mb-6">
    <form method="GET" action="<?= URL ?>/professor/redacao-configuravel/relatorio" class="space-y-4">
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
            <div class="xl:col-span-2">
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Título da proposta</label>
                <input type="text" name="proposal_title" value="<?= htmlspecialchars((string) ($filters['proposal_title'] ?? '')) ?>"
                       placeholder="Buscar por título"
                       class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Tipo de banca</label>
                <select name="board_name" class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Todas</option>
                    <?php foreach ($boardOptions as $boardName): ?>
                    <option value="<?= htmlspecialchars($boardName) ?>" <?= (($filters['board_name'] ?? '') === $boardName) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($boardName) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Aluno</label>
                <input type="text" name="student_name" value="<?= htmlspecialchars((string) ($filters['student_name'] ?? '')) ?>"
                       placeholder="Nome do aluno"
                       class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Turma</label>
                <select name="turma_name" class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Todas</option>
                    <?php foreach ($turmaOptions as $turmaName): ?>
                    <option value="<?= htmlspecialchars($turmaName) ?>" <?= (($filters['turma_name'] ?? '') === $turmaName) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($turmaName) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-7 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Status</label>
                <select name="status" class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Todos</option>
                    <option value="nao_enviado" <?= (($filters['status'] ?? '') === 'nao_enviado') ? 'selected' : '' ?>>Não enviado</option>
                    <option value="visualizado" <?= (($filters['status'] ?? '') === 'visualizado') ? 'selected' : '' ?>>Visualizado</option>
                    <option value="enviado" <?= (($filters['status'] ?? '') === 'enviado') ? 'selected' : '' ?>>Enviado</option>
                    <option value="corrigido" <?= (($filters['status'] ?? '') === 'corrigido') ? 'selected' : '' ?>>Corrigido</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Data envio de</label>
                <input type="date" name="submitted_from" value="<?= htmlspecialchars((string) ($filters['submitted_from'] ?? '')) ?>"
                       class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Data envio até</label>
                <input type="date" name="submitted_to" value="<?= htmlspecialchars((string) ($filters['submitted_to'] ?? '')) ?>"
                       class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Data correção de</label>
                <input type="date" name="corrected_from" value="<?= htmlspecialchars((string) ($filters['corrected_from'] ?? '')) ?>"
                       class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Data correção até</label>
                <input type="date" name="corrected_to" value="<?= htmlspecialchars((string) ($filters['corrected_to'] ?? '')) ?>"
                       class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Exibir</label>
                <select name="per_page" class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <?php foreach ([10, 25, 50, 100] as $perPageOption): ?>
                    <option value="<?= $perPageOption ?>" <?= ((int) ($filters['per_page'] ?? 10) === $perPageOption) ? 'selected' : '' ?>>
                        <?= $perPageOption ?> por página
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Faixa de nota</label>
                <select name="score_range" class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="">Todas</option>
                    <option value="lt500" <?= (($filters['score_range'] ?? '') === 'lt500') ? 'selected' : '' ?>>Menor de 500</option>
                    <option value="501_600" <?= (($filters['score_range'] ?? '') === '501_600') ? 'selected' : '' ?>>501 a 600</option>
                    <option value="601_700" <?= (($filters['score_range'] ?? '') === '601_700') ? 'selected' : '' ?>>601 a 700</option>
                    <option value="701_800" <?= (($filters['score_range'] ?? '') === '701_800') ? 'selected' : '' ?>>701 a 800</option>
                    <option value="801_900" <?= (($filters['score_range'] ?? '') === '801_900') ? 'selected' : '' ?>>801 a 900</option>
                    <option value="901_1000" <?= (($filters['score_range'] ?? '') === '901_1000') ? 'selected' : '' ?>>901 a 1000</option>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-6 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1">Ordenar por data de envio</label>
                <select name="date_order" class="report-field w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm focus:ring-2 focus:ring-purple-500 focus:border-purple-500">
                    <option value="desc" <?= (($filters['date_order'] ?? 'desc') === 'desc') ? 'selected' : '' ?>>Mais recente primeiro</option>
                    <option value="asc" <?= (($filters['date_order'] ?? '') === 'asc') ? 'selected' : '' ?>>Mais antiga primeiro</option>
                </select>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <button type="submit" class="inline-flex items-center justify-center bg-gradient-to-r from-purple-600 to-indigo-600 text-white px-5 py-2.5 rounded-xl hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 shadow-lg">
                Filtrar
            </button>
            <a href="<?= URL ?>/professor/redacao-configuravel/relatorio" class="inline-flex items-center justify-center bg-white text-gray-700 px-4 py-2.5 rounded-xl border border-gray-200 hover:bg-gray-50 transition-all duration-200">
                Limpar filtros
            </a>
            <span class="text-sm text-gray-500">
                <strong class="text-gray-800"><?= (int) ($total ?? 0) ?></strong> resultado(s) encontrado(s)
            </span>
        </div>
    </form>
</div>

<div class="bg-white/90 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/70 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-gray-900">Resultados</h3>
        <p class="text-sm text-gray-500">
            Página <?= (int) ($pagination['page'] ?? 1) ?> de <?= (int) ($pagination['total_pages'] ?? 1) ?>
        </p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título da proposta</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de banca</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data envio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data correção</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($rows)): ?>
                <tr>
                    <td colspan="9" class="px-6 py-12 text-center text-gray-500">Nenhum registro encontrado com os filtros informados.</td>
                </tr>
                <?php else: ?>
                    <?php foreach ($rows as $row): ?>
                    <?php
                    $isPendingCorrection = !empty($row['submitted_at']) && empty($row['corrected_at']) && (($row['status'] ?? '') !== 'corrigido');
                    $isOverduePending = false;
                    if ($isPendingCorrection && !empty($row['submitted_at'])) {
                        $submittedTs = strtotime((string) $row['submitted_at']);
                        if ($submittedTs !== false) {
                            $isOverduePending = ((time() - $submittedTs) / 86400) > 15;
                        }
                    }
                    $statusClass = ($row['status'] ?? '') === 'corrigido'
                        ? 'bg-blue-100 text-blue-800'
                        : (($row['status'] ?? '') === 'enviado'
                            ? 'bg-green-100 text-green-800'
                            : (($row['status'] ?? '') === 'visualizado'
                                ? 'bg-amber-100 text-amber-800'
                                : 'bg-gray-100 text-gray-800'));
                    ?>
                    <tr class="<?= $isOverduePending ? 'bg-red-50/40 hover:bg-red-50/70' : 'hover:bg-gray-50' ?>">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($row['proposal_title'] ?? '')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars((string) ($row['board_name'] ?? '')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars((string) ($row['turma_name'] ?? '—')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-800"><?= htmlspecialchars((string) ($row['student_name'] ?? '')) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2.5 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>">
                                <?= htmlspecialchars((string) ($row['status_label'] ?? '')) ?>
                            </span>
                            <?php if ($isOverduePending): ?>
                                <span class="inline-flex ml-2 px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-700">
                                    +15 dias sem correção
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= !empty($row['submitted_at']) ? date('d/m/Y H:i', strtotime((string) $row['submitted_at'])) : '—' ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500"><?= !empty($row['corrected_at']) ? date('d/m/Y H:i', strtotime((string) $row['corrected_at'])) : '—' ?></td>
                        <td class="px-6 py-4 text-sm font-semibold text-gray-800"><?= isset($row['nota_final']) && $row['nota_final'] !== null ? number_format((float) $row['nota_final'], 0, ',', '.') : '—' ?></td>
                        <td class="px-6 py-4 text-sm">
                            <?php if (!empty($row['submission_id'])): ?>
                                <?php $baseDetailUrl = URL . '/professor/redacao-configuravel/propostas/' . (int) ($row['proposal_id'] ?? 0) . '/envios/' . (int) $row['submission_id'] . '/corrigir'; ?>
                                <div class="flex flex-wrap items-center gap-2">
                                    <a href="<?= htmlspecialchars($baseDetailUrl . '#redacao') ?>" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-indigo-200 text-indigo-700 bg-indigo-50 hover:bg-indigo-100 transition-colors">
                                        Visualizar redação
                                    </a>
                                    <a href="<?= htmlspecialchars($baseDetailUrl . '#correcao') ?>" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-blue-200 text-blue-700 bg-blue-50 hover:bg-blue-100 transition-colors">
                                        Ver correção
                                    </a>
                                </div>
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
</div>

<?php if ($totalPages > 1): ?>
<div class="mt-6 flex flex-wrap items-center justify-between gap-4">
    <p class="text-sm text-gray-500">
        Exibindo <?= count($rows) ?> de <?= (int) ($pagination['total'] ?? 0) ?> registro(s)
    </p>
    <div class="flex flex-wrap items-center gap-2">
        <?php
        $baseQuery = $queryParams;
        if ($currentPage > 1):
            $prevQuery = $baseQuery;
            $prevQuery['page'] = $currentPage - 1;
            $prevUrl = URL . '/professor/redacao-configuravel/relatorio?' . http_build_query($prevQuery);
        ?>
        <a href="<?= htmlspecialchars($prevUrl) ?>"
           class="px-3.5 py-2 rounded-lg text-sm border bg-white text-gray-700 border-gray-200 hover:bg-gray-50">
            ← Anterior
        </a>
        <?php endif; ?>

        <?php foreach ($paginationItems as $item): ?>
            <?php if ($item === 'ellipsis'): ?>
            <span class="px-2 py-2 text-sm text-gray-400 select-none">...</span>
            <?php else: ?>
                <?php
                $pageQuery = $baseQuery;
                $pageQuery['page'] = $item;
                $pageUrl = URL . '/professor/redacao-configuravel/relatorio?' . http_build_query($pageQuery);
                ?>
            <a href="<?= htmlspecialchars($pageUrl) ?>"
               class="min-w-[42px] px-3 py-2 rounded-lg text-sm border text-center <?= $item === $currentPage ? 'bg-purple-600 text-white border-purple-600 shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' ?>">
                <?= $item ?>
            </a>
            <?php endif; ?>
        <?php endforeach; ?>

        <?php
        if ($currentPage < $totalPages):
            $nextQuery = $baseQuery;
            $nextQuery['page'] = $currentPage + 1;
            $nextUrl = URL . '/professor/redacao-configuravel/relatorio?' . http_build_query($nextQuery);
        ?>
        <a href="<?= htmlspecialchars($nextUrl) ?>"
           class="px-3.5 py-2 rounded-lg text-sm border bg-white text-gray-700 border-gray-200 hover:bg-gray-50">
            Próxima →
        </a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>
