<?php
$requests_preview = $requests_preview ?? [];
$local_requests = $local_requests ?? [];
$flash = $flash ?? [];
$csrf_token = $csrf_token ?? '';
$filters = $filters ?? ['escola' => '', 'materia' => '', 'tema' => '', 'estilo' => '', 'status' => 'pending'];
$filter_escolas = $filter_escolas ?? [];
$filter_materias = $filter_materias ?? [];
$filter_temas = $filter_temas ?? [];
$filter_estilos = $filter_estilos ?? [];
$show_escola_col = (bool) ($show_escola_col ?? false);
$pag = $educahits_pedidos_pagination ?? [
    'page' => 1,
    'per_page' => 25,
    'total' => 0,
    'total_pages' => 0,
    'from' => 0,
    'to' => 0,
];
$perPageOpts = $educahits_pedidos_per_page_options ?? [25, 50, 100];
$ehPedidosQuery = static function (array $filters, int $page, int $perPage): string {
    $q = [];
    foreach (['escola', 'materia', 'tema', 'estilo'] as $k) {
        $v = trim((string) ($filters[$k] ?? ''));
        if ($v !== '') {
            $q[$k] = $v;
        }
    }
    $st = trim((string) ($filters['status'] ?? 'pending'));
    if ($st !== '' && $st !== 'pending') {
        $q['status'] = $st;
    }
    if ($perPage !== 25) {
        $q['per_page'] = $perPage;
    }
    if ($page > 1) {
        $q['page'] = $page;
    }

    return $q === [] ? '' : ('?' . http_build_query($q));
};
$eh_status_label = static function (string $s): string {
    return match ($s) {
        'pending' => 'Pendente',
        'in_progress' => 'Em andamento',
        'processing' => 'Em processamento',
        'approved' => 'Aprovado',
        'completed' => 'Concluído',
        'rejected' => 'Recusado',
        'excluded', 'deleted' => 'Excluído',
        'archived', 'arquivado' => 'Arquivado',
        default => $s,
    };
};
$eh_status_styles = static function (string $s): string {
    return match ($s) {
        'pending' => 'bg-amber-100 text-amber-800',
        'in_progress', 'processing' => 'bg-blue-100 text-blue-800',
        'approved', 'completed' => 'bg-green-100 text-green-800',
        'rejected' => 'bg-red-100 text-red-800',
        'excluded', 'deleted' => 'bg-slate-100 text-slate-700',
        'archived', 'arquivado' => 'bg-slate-100 text-slate-800',
        default => 'bg-slate-100 text-slate-700',
    };
};
$eh_format_data = static function (?string $raw): string {
    $raw = trim((string) $raw);
    if ($raw === '') {
        return '—';
    }
    $ts = strtotime($raw);
    if ($ts === false) {
        return $raw;
    }
    return date('d/m/Y H:i', $ts);
};

$ehSt = (string) ($filters['status'] ?? 'pending');
$filtrosAtivos = 0;
foreach (['escola', 'materia', 'tema', 'estilo'] as $fk) {
    if (trim((string) ($filters[$fk] ?? '')) !== '') {
        $filtrosAtivos++;
    }
}
if ($ehSt !== '' && $ehSt !== 'pending') {
    $filtrosAtivos++;
}
if ((int) ($pag['per_page'] ?? 25) !== 25) {
    $filtrosAtivos++;
}
?>
<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/_nav.php'; ?>

<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-lg font-semibold text-slate-900">Pedidos no EducaTudo</h2>
            <p class="text-sm text-slate-500 mt-0.5">Pedidos de todas as escolas ativas com banco configurado.</p>
        </div>
        <button type="button" onclick="openEhFilterDrawer()"
                class="relative inline-flex items-center px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors flex-shrink-0">
            <i class="fa-solid fa-filter mr-2 text-slate-500"></i>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
            <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>

<div class="mb-8 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <?php if (empty($local_requests)): ?>
    <p class="px-6 py-10 text-sm text-slate-500 text-center">
        Nenhum pedido encontrado ou tabela ainda não existe neste ambiente.
        <?php if ($filtrosAtivos > 0): ?>
        <button type="button" onclick="openEhFilterDrawer()" class="block mx-auto mt-2 text-blue-600 hover:underline">Ajustar filtros</button>
        <?php endif; ?>
    </p>
    <?php else: ?>
    <form method="post" action="<?= URL ?>/master/educa-hits/requests-bulk-status" id="eh-pedidos-bulk-form" class="block" onsubmit="return window.ehBulkPedidosSubmit(this);">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="px-6 py-3 border-b border-slate-100 bg-slate-50/80 flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
            <div class="flex flex-wrap items-center gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-slate-700 cursor-pointer">
                    <input type="checkbox" id="eh-select-page" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="font-medium">Selecionar todos (página)</span>
                </label>
                <span class="hidden sm:inline text-slate-300">|</span>
                <label class="text-xs font-semibold text-slate-600 uppercase tracking-wide">Novo status</label>
                <select name="status" class="border border-slate-300 rounded-lg px-3 py-2 text-sm bg-white min-w-[11rem]">
                    <option value="processing">Em processamento</option>
                    <option value="approved">Aprovado</option>
                    <option value="rejected">Recusado</option>
                    <option value="archived">Arquivar</option>
                </select>
                <button type="submit" name="bulk_action" value="apply" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">
                    Aplicar aos selecionados
                </button>
                <button type="submit" name="bulk_action" value="excluded" class="inline-flex items-center justify-center px-4 py-2 rounded-lg bg-rose-600 text-white text-sm font-medium hover:bg-rose-700" onclick="return confirm('Excluir permanentemente os pedidos selecionados? Esta ação remove o registro no banco da escola.');">
                    Excluir selecionados
                </button>
            </div>
            <p class="text-xs text-slate-500">Marque as linhas, escolha o status ou use Excluir.</p>
        </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100 text-sm">
            <thead class="bg-slate-50/90">
                <tr>
                    <th class="px-3 py-3 w-10">
                        <span class="sr-only">Selecionar</span>
                    </th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">ID</th>
                    <?php if ($show_escola_col): ?><th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Escola</th><?php endif; ?>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Matéria</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Tema</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Estilo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-slate-600 uppercase tracking-wide">Criado</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-slate-600 uppercase tracking-wide">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($local_requests as $row): ?>
                <?php
                    $st = (string) ($row['status'] ?? '');
                    $eidBulk = (int) ($row['escola_id'] ?? $row['school_id'] ?? 0);
                    $cbVal = (int) ($row['id'] ?? 0) . ':' . $eidBulk;
                ?>
                <tr class="hover:bg-slate-50/60 transition-colors">
                    <td class="px-3 py-3 align-top">
                        <input type="checkbox" name="selected[]" value="<?= htmlspecialchars($cbVal, ENT_QUOTES, 'UTF-8') ?>" class="eh-row-cb rounded border-slate-300 text-blue-600 focus:ring-blue-500" aria-label="Selecionar pedido #<?= (int) ($row['id'] ?? 0) ?>">
                    </td>
                    <td class="px-4 py-3 text-slate-800 font-mono text-xs whitespace-nowrap align-top"><?= (int) ($row['id'] ?? 0) ?></td>
                    <?php if ($show_escola_col): ?>
                    <td class="px-4 py-3 text-slate-700 align-top">
                        <span class="font-medium text-slate-900"><?= htmlspecialchars((string) ($row['escola_nome'] ?? '')) ?></span>
                        <?php if (!empty($row['escola_slug'])): ?><span class="block text-[11px] text-slate-400 font-mono mt-0.5"><?= htmlspecialchars((string) $row['escola_slug']) ?></span><?php endif; ?>
                    </td>
                    <?php endif; ?>
                    <td class="px-4 py-3 text-slate-700 align-top"><?= htmlspecialchars((string) ($row['aluno_nome'] ?? '—')) ?></td>
                    <td class="px-4 py-3 text-slate-700 align-top"><?= htmlspecialchars((string) ($row['subject'] ?? '')) ?></td>
                    <td class="px-4 py-3 text-slate-800 align-top max-w-[10rem] sm:max-w-xs"><span class="line-clamp-2" title="<?= htmlspecialchars((string) ($row['topic'] ?? '')) ?>"><?= htmlspecialchars((string) ($row['topic'] ?? '')) ?></span></td>
                    <td class="px-4 py-3 text-slate-600 align-top whitespace-nowrap"><?= htmlspecialchars((string) ($row['music_style'] ?? '—')) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap align-top"><span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= htmlspecialchars($eh_status_styles($st)) ?>"><?= htmlspecialchars($eh_status_label($st)) ?></span></td>
                    <td class="px-4 py-3 text-slate-500 whitespace-nowrap text-sm align-top tabular-nums"><?= htmlspecialchars($eh_format_data($row['created_at'] ?? null)) ?></td>
                    <td class="px-4 py-3 whitespace-nowrap align-top text-right" onclick="event.stopPropagation()">
                        <?php
                        ob_start();
                        ?>
                        <button type="button" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 js-open-details"
                            data-row='<?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>'>
                            <i class="fa-solid fa-circle-info text-gray-400 w-4 text-center"></i> Detalhes
                        </button>
                        <button type="button" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 js-open-status"
                            data-request-id="<?= (int) ($row['id'] ?? 0) ?>"
                            data-escola-id="<?= (int) ($row['escola_id'] ?? $row['school_id'] ?? 0) ?>"
                            data-status="<?= htmlspecialchars($st) ?>">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Status
                        </button>
                        <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                        <?php $row_actions_dropdown_id = 'row-actions-pedido-' . $eidBulk . '-' . (int) ($row['id'] ?? 0); ?>
                        <?php include __DIR__ . '/../../admin/_partials/row_actions_dropdown.php'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (!empty($pag['total'])): ?>
    <?php
    $tp = (int) ($pag['total_pages'] ?? 1);
    $cur = (int) ($pag['page'] ?? 1);
    $prevUrl = $cur > 1 ? (URL . '/master/educa-hits/pedidos' . $ehPedidosQuery($filters, $cur - 1, (int) ($pag['per_page'] ?? 25))) : '';
    $nextUrl = $cur < $tp ? (URL . '/master/educa-hits/pedidos' . $ehPedidosQuery($filters, $cur + 1, (int) ($pag['per_page'] ?? 25))) : '';
    ?>
    <div class="px-6 py-4 border-t border-slate-100 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between bg-slate-50/50">
        <p class="text-sm text-slate-600">
            Exibindo <span class="font-medium text-slate-800"><?= (int) ($pag['from'] ?? 0) ?></span>–<span class="font-medium text-slate-800"><?= (int) ($pag['to'] ?? 0) ?></span>
            de <span class="font-medium text-slate-800"><?= (int) ($pag['total'] ?? 0) ?></span> registro(s)
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <?php if ($prevUrl !== ''): ?>
            <a href="<?= htmlspecialchars($prevUrl) ?>" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 bg-white hover:bg-slate-50">Anterior</a>
            <?php else: ?>
            <span class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 text-slate-400 bg-white cursor-not-allowed">Anterior</span>
            <?php endif; ?>
            <span class="text-sm text-slate-600 tabular-nums">Página <?= $cur ?> / <?= max(1, $tp) ?></span>
            <?php if ($nextUrl !== ''): ?>
            <a href="<?= htmlspecialchars($nextUrl) ?>" class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-300 text-slate-700 bg-white hover:bg-slate-50">Próxima</a>
            <?php else: ?>
            <span class="inline-flex items-center px-3 py-1.5 text-sm font-medium rounded-lg border border-slate-200 text-slate-400 bg-white cursor-not-allowed">Próxima</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    </form>
    <?php endif; ?>
</div>

<?php if (!empty($requests_preview)): ?>
<div class="mb-8 bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h2 class="text-lg font-semibold text-slate-900">Pré-visualização da API</h2>
        <p class="text-sm text-slate-600 mt-1">Leitura opcional de <code class="text-xs bg-slate-100 px-1 rounded">EDUCAHITS_MASTER_REQUESTS_API</code>.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-slate-50"><tr><th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Dados</th></tr></thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($requests_preview as $row): ?>
                <tr><td class="px-4 py-2 text-slate-700 font-mono text-xs whitespace-pre-wrap"><?= htmlspecialchars(json_encode($row, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<!-- Offcanvas filtros -->
<div id="ehFilterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="closeEhFilterDrawer()"></div>
<aside id="ehFilterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Filtrar pedidos</h3>
        <button type="button" onclick="closeEhFilterDrawer()" class="text-slate-400 hover:text-slate-700 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-lg"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/master/educa-hits/pedidos" class="flex flex-col flex-1 min-h-0">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Escola</label>
                <select name="escola" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($filter_escolas as $item): ?>
                    <option value="<?= htmlspecialchars($item) ?>" <?= $filters['escola'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Estilo</label>
                <select name="estilo" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($filter_estilos as $item): ?>
                    <option value="<?= htmlspecialchars($item) ?>" <?= $filters['estilo'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tema</label>
                <select name="tema" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($filter_temas as $item): ?>
                    <option value="<?= htmlspecialchars($item) ?>" <?= $filters['tema'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Matéria</label>
                <select name="materia" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($filter_materias as $item): ?>
                    <option value="<?= htmlspecialchars($item) ?>" <?= $filters['materia'] === $item ? 'selected' : '' ?>><?= htmlspecialchars($item) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                <select name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="pending" <?= $ehSt === 'pending' ? 'selected' : '' ?>>Pendente</option>
                    <option value="processing" <?= $ehSt === 'processing' ? 'selected' : '' ?>>Em andamento</option>
                    <option value="approved" <?= $ehSt === 'approved' ? 'selected' : '' ?>>Aprovado / concluído</option>
                    <option value="rejected" <?= $ehSt === 'rejected' ? 'selected' : '' ?>>Recusado</option>
                    <option value="excluded" <?= $ehSt === 'excluded' ? 'selected' : '' ?>>Excluído</option>
                    <option value="archived" <?= $ehSt === 'archived' ? 'selected' : '' ?>>Arquivado</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Por página</label>
                <select name="per_page" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <?php foreach ($perPageOpts as $n): ?>
                    <option value="<?= (int) $n ?>" <?= (int) ($pag['per_page'] ?? 25) === (int) $n ? 'selected' : '' ?>><?= (int) $n ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 bg-slate-50 flex gap-3 shrink-0">
            <a href="<?= URL ?>/master/educa-hits/pedidos"
               class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<div id="educahits-details-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/60 px-4">
    <div class="w-full max-w-3xl rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Detalhes do pedido</h3>
            <button type="button" class="text-slate-400 hover:text-slate-700 js-close-modal">Fechar</button>
        </div>
        <div class="grid gap-4 px-6 py-5 md:grid-cols-2 text-sm">
            <div><span class="font-semibold text-slate-700">Aluno:</span> <span id="detail-aluno"></span></div>
            <div><span class="font-semibold text-slate-700">Escola:</span> <span id="detail-escola"></span></div>
            <div><span class="font-semibold text-slate-700">Matéria:</span> <span id="detail-materia"></span></div>
            <div><span class="font-semibold text-slate-700">Tema:</span> <span id="detail-tema"></span></div>
            <div><span class="font-semibold text-slate-700">Estilo:</span> <span id="detail-estilo"></span></div>
            <div><span class="font-semibold text-slate-700">Status:</span> <span id="detail-status"></span></div>
            <div class="md:col-span-2"><span class="font-semibold text-slate-700">Observações:</span><div id="detail-observacoes" class="mt-2 rounded-lg bg-slate-50 p-3 whitespace-pre-wrap text-slate-700"></div></div>
        </div>
    </div>
</div>

<div id="educahits-status-modal" class="fixed inset-0 z-[80] hidden items-center justify-center bg-slate-900/60 px-4">
    <div class="w-full max-w-md rounded-2xl bg-white shadow-2xl">
        <div class="flex items-center justify-between border-b border-slate-200 px-6 py-4">
            <h3 class="text-lg font-semibold text-slate-900">Alterar status</h3>
            <button type="button" class="text-slate-400 hover:text-slate-700 js-close-modal">Fechar</button>
        </div>
        <form method="post" action="<?= URL ?>/master/educa-hits/request-status" class="px-6 py-5 space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="request_id" id="status-request-id" value="">
            <input type="hidden" name="escola_id" id="status-escola-id" value="">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Novo status</label>
                <select name="status" id="status-select" class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                    <option value="processing">Em processamento</option>
                    <option value="approved">Aprovado</option>
                    <option value="rejected">Recusado</option>
                    <option value="excluded">Excluído</option>
                    <option value="archived">Arquivar</option>
                </select>
            </div>
            <div class="flex justify-end gap-2">
                <button type="button" class="px-4 py-2 rounded-lg border border-slate-300 text-slate-700 hover:bg-slate-50 js-close-modal">Cancelar</button>
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white font-medium hover:bg-blue-700">Salvar status</button>
            </div>
        </form>
    </div>
</div>

<script>
window.ehBulkPedidosSubmit = function (form) {
    var cbs = form.querySelectorAll('.eh-row-cb:checked');
    if (!cbs.length) {
        alert('Selecione ao menos um pedido (caixa à esquerda de cada linha).');
        return false;
    }
    return true;
};
function openEhFilterDrawer() {
    document.getElementById('ehFilterDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('ehFilterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.classList.add('overflow-hidden');
}
function closeEhFilterDrawer() {
    document.getElementById('ehFilterDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('ehFilterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.classList.remove('overflow-hidden');
}
(function () {
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeEhFilterDrawer();
    });
    var selPage = document.getElementById('eh-select-page');
    if (selPage) {
        selPage.addEventListener('change', function () {
            document.querySelectorAll('.eh-row-cb').forEach(function (cb) {
                cb.checked = selPage.checked;
            });
        });
    }
    var detailsModal = document.getElementById('educahits-details-modal');
    var statusModal = document.getElementById('educahits-status-modal');
    function openModal(el) { if (el) { el.classList.remove('hidden'); el.classList.add('flex'); } }
    function closeModal(el) { if (el) { el.classList.add('hidden'); el.classList.remove('flex'); } }
    document.querySelectorAll('.js-close-modal').forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(detailsModal);
            closeModal(statusModal);
        });
    });
    if (detailsModal) {
        detailsModal.addEventListener('click', function (e) { if (e.target === detailsModal) closeModal(detailsModal); });
    }
    if (statusModal) {
        statusModal.addEventListener('click', function (e) { if (e.target === statusModal) closeModal(statusModal); });
    }
    document.querySelectorAll('.js-open-details').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var row = {};
            try { row = JSON.parse(btn.getAttribute('data-row') || '{}'); } catch (e) {}
            document.getElementById('detail-aluno').textContent = row.aluno_nome || '—';
            document.getElementById('detail-escola').textContent = row.escola_nome || row.escola_slug || '—';
            document.getElementById('detail-materia').textContent = row.subject || '—';
            document.getElementById('detail-tema').textContent = row.topic || '—';
            document.getElementById('detail-estilo').textContent = row.music_style || '—';
            document.getElementById('detail-status').textContent = row.status || '—';
            document.getElementById('detail-observacoes').textContent = row.description || 'Sem observações.';
            openModal(detailsModal);
        });
    });
    document.querySelectorAll('.js-open-status').forEach(function (btn) {
        btn.addEventListener('click', function () {
            document.getElementById('status-request-id').value = btn.getAttribute('data-request-id') || '';
            document.getElementById('status-escola-id').value = btn.getAttribute('data-escola-id') || '';
            document.getElementById('status-select').value = btn.getAttribute('data-status') || 'processing';
            openModal(statusModal);
        });
    });
})();
</script>
