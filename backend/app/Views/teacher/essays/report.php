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

<?php
$ui = __DIR__ . '/../../admin/_partials/ui';
$filtrosAtivos = 0;
foreach (['proposal_title', 'board_name', 'turma_name', 'student_name', 'status', 'submitted_from', 'submitted_to', 'corrected_from', 'corrected_to', 'score_range'] as $chaveFiltro) {
    if (trim((string) ($filters[$chaveFiltro] ?? '')) !== '') {
        $filtrosAtivos++;
    }
}
if ((string) ($filters['date_order'] ?? 'desc') !== 'desc') {
    $filtrosAtivos++;
}
if ((int) ($filters['per_page'] ?? 10) !== 10) {
    $filtrosAtivos++;
}

ob_start();
$ui_btn_variant = 'complementar';
$ui_btn_label = 'Voltar para propostas';
$ui_btn_icon = 'fa-solid fa-arrow-left';
$ui_btn_href = URL . '/professor/redacao-configuravel';
$ui_btn_type = 'button';
$ui_btn_onclick = '';
$ui_btn_id = '';
$ui_btn_class = '';
$ui_btn_attrs = '';
$ui_btn_filter_count = 0;
include $ui . '/btn.php';

$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFiltroDrawer()';
$ui_btn_href = '';
$ui_btn_filter_count = $filtrosAtivos;
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();
$page_header_title = 'Relatório de Redações';
$page_header_subtitle = 'Filtre envios por proposta, banca, aluno, status, notas e datas.';
include __DIR__ . '/../../admin/_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/70 flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-gray-900">Resultados <span class="text-sm font-normal text-gray-500">(<?= (int) ($total ?? 0) ?>)</span></h3>
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
                                    <a href="<?= htmlspecialchars($baseDetailUrl . '#redacao') ?>" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-primary/20 text-primary bg-primary/10 hover:bg-primary/15 transition-colors">
                                        Visualizar redação
                                    </a>
                                    <a href="<?= htmlspecialchars($baseDetailUrl . '#correcao') ?>" class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-200 text-gray-700 bg-white hover:bg-gray-50 transition-colors">
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
               class="min-w-[42px] px-3 py-2 rounded-lg text-sm border text-center <?= $item === $currentPage ? 'bg-primary text-primary border-primary shadow-sm' : 'bg-white text-gray-700 border-gray-200 hover:bg-gray-50' ?>">
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

<?php
$opcoesBanca = ['' => 'Todas'];
foreach ($boardOptions as $boardName) {
    $opcoesBanca[(string) $boardName] = (string) $boardName;
}
$opcoesTurma = ['' => 'Todas'];
foreach ($turmaOptions as $turmaName) {
    $opcoesTurma[(string) $turmaName] = (string) $turmaName;
}
$opcoesStatus = [
    '' => 'Todos',
    'nao_enviado' => 'Não enviado',
    'visualizado' => 'Visualizado',
    'enviado' => 'Enviado',
    'corrigido' => 'Corrigido',
];
$opcoesNota = [
    '' => 'Todas',
    'lt500' => 'Menor de 500',
    '501_600' => '501 a 600',
    '601_700' => '601 a 700',
    '701_800' => '701 a 800',
    '801_900' => '801 a 900',
    '901_1000' => '901 a 1000',
];
$opcoesPorPagina = [];
foreach ([10, 25, 50, 100] as $perPageOption) {
    $opcoesPorPagina[(string) $perPageOption] = $perPageOption . ' por página';
}
$opcoesOrdem = [
    'desc' => 'Mais recente primeiro',
    'asc' => 'Mais antiga primeiro',
];
ob_start();
?>
<form method="get" action="<?= URL ?>/professor/redacao-configuravel/relatorio" class="flex flex-col flex-1 overflow-hidden">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6">
        <?php
        $ui_form_campo_label = 'Título da proposta';
        $ui_form_campo_name = 'proposal_title';
        $ui_form_campo_tipo = 'text';
        $ui_form_campo_value = (string) ($filters['proposal_title'] ?? '');
        $ui_form_campo_placeholder = 'Buscar por título';
        $ui_form_campo_span = 'full';
        $ui_form_campo_mb = 'mb-4';
        $ui_form_campo_obrigatorio = false;
        $ui_form_campo_opcoes = [];
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Tipo de banca';
        $ui_form_campo_name = 'board_name';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesBanca;
        $ui_form_campo_value = (string) ($filters['board_name'] ?? '');
        $ui_form_campo_placeholder = '';
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Aluno';
        $ui_form_campo_name = 'student_name';
        $ui_form_campo_tipo = 'text';
        $ui_form_campo_value = (string) ($filters['student_name'] ?? '');
        $ui_form_campo_placeholder = 'Nome do aluno';
        $ui_form_campo_opcoes = [];
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Turma';
        $ui_form_campo_name = 'turma_name';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesTurma;
        $ui_form_campo_value = (string) ($filters['turma_name'] ?? '');
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Status';
        $ui_form_campo_name = 'status';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesStatus;
        $ui_form_campo_value = (string) ($filters['status'] ?? '');
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Data envio de';
        $ui_form_campo_name = 'submitted_from';
        $ui_form_campo_tipo = 'date';
        $ui_form_campo_value = (string) ($filters['submitted_from'] ?? '');
        $ui_form_campo_opcoes = [];
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Data envio até';
        $ui_form_campo_name = 'submitted_to';
        $ui_form_campo_tipo = 'date';
        $ui_form_campo_value = (string) ($filters['submitted_to'] ?? '');
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Data correção de';
        $ui_form_campo_name = 'corrected_from';
        $ui_form_campo_tipo = 'date';
        $ui_form_campo_value = (string) ($filters['corrected_from'] ?? '');
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Data correção até';
        $ui_form_campo_name = 'corrected_to';
        $ui_form_campo_tipo = 'date';
        $ui_form_campo_value = (string) ($filters['corrected_to'] ?? '');
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Faixa de nota';
        $ui_form_campo_name = 'score_range';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesNota;
        $ui_form_campo_value = (string) ($filters['score_range'] ?? '');
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Exibir';
        $ui_form_campo_name = 'per_page';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesPorPagina;
        $ui_form_campo_value = (string) (int) ($filters['per_page'] ?? 10);
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Ordenar por data de envio';
        $ui_form_campo_name = 'date_order';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = $opcoesOrdem;
        $ui_form_campo_value = (string) ($filters['date_order'] ?? 'desc');
        include $ui . '/form_campo.php';
        ?>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 flex gap-3">
        <?php
        $ui_btn_variant = 'complementar';
        $ui_btn_label = 'Limpar';
        $ui_btn_href = URL . '/professor/redacao-configuravel/relatorio';
        $ui_btn_class = 'flex-1 justify-center';
        $ui_btn_type = 'button';
        $ui_btn_onclick = '';
        $ui_btn_icon = '';
        $ui_btn_filter_count = 0;
        $ui_btn_attrs = '';
        include $ui . '/btn.php';

        $ui_btn_variant = 'confirm';
        $ui_btn_label = 'Aplicar filtros';
        $ui_btn_type = 'submit';
        $ui_btn_href = '';
        $ui_btn_class = 'flex-1 justify-center';
        include $ui . '/btn.php';
        ?>
    </div>
</form>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'filtro';
$ui_offcanvas_titulo = 'Filtros';
$ui_offcanvas_max_w = 'max-w-md';
include $ui . '/offcanvas.php';
?>
