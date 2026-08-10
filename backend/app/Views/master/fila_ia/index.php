<?php
$aba = $aba ?? 'fila';
$escolas = $escolas ?? [];
$filtros = $filtros ?? [];
$lista_jobs = $lista_jobs ?? [];
$lista_cron = $lista_cron ?? [];
$ultima_cron = $ultima_cron ?? null;
$kpis = $lista_jobs['kpis'] ?? [];
$jobs = $lista_jobs['jobs'] ?? [];
$erros = $lista_jobs['erros'] ?? [];
$totalJobs = (int) ($lista_jobs['total'] ?? 0);
$page = (int) ($lista_jobs['page'] ?? 1);
$perPage = (int) ($lista_jobs['per_page'] ?? 20);
$totalPages = $perPage > 0 ? (int) ceil($totalJobs / $perPage) : 1;

$cronItems = $lista_cron['items'] ?? [];
$cronTotal = (int) ($lista_cron['total'] ?? 0);
$cronPage = (int) ($lista_cron['page'] ?? 1);
$cronPerPage = (int) ($lista_cron['per_page'] ?? 20);
$cronPages = $cronPerPage > 0 ? (int) ceil($cronTotal / $cronPerPage) : 1;
$cronTabelaOk = !empty($lista_cron['tabela_ok']);

$fmtInt = static fn ($n) => number_format((int) $n, 0, ',', '.');
$fmtDt = static function ($dt) {
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y H:i:s', $ts) : $dt;
};

$statusBadge = static function (string $status): string {
    $map = [
        'pending' => 'bg-amber-100 text-amber-800',
        'processing' => 'bg-blue-100 text-blue-800',
        'done' => 'bg-emerald-100 text-emerald-800',
        'failed' => 'bg-red-100 text-red-800',
        'rodando' => 'bg-blue-100 text-blue-800',
        'ok' => 'bg-emerald-100 text-emerald-800',
        'erro' => 'bg-red-100 text-red-800',
        'parcial' => 'bg-amber-100 text-amber-800',
        'pulada' => 'bg-slate-100 text-slate-700',
    ];
    $cls = $map[$status] ?? 'bg-slate-100 text-slate-700';
    return '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ' . $cls . '">'
        . htmlspecialchars($status) . '</span>';
};

$filtrosAtivos = 0;
if ((int) ($filtros['escola_id'] ?? 0) > 0) {
    $filtrosAtivos++;
}
if (($filtros['status'] ?? '') !== '') {
    $filtrosAtivos++;
}
if (($filtros['job_type'] ?? '') !== '') {
    $filtrosAtivos++;
}
if (($filtros['q'] ?? '') !== '') {
    $filtrosAtivos++;
}
if (!empty($filtros['so_travados'])) {
    $filtrosAtivos++;
}

$queryBase = static function (array $extra = []) use ($filtros, $aba): string {
    $params = array_merge([
        'aba' => $aba,
        'escola_id' => $filtros['escola_id'] ?? 0,
        'status' => $filtros['status'] ?? '',
        'job_type' => $filtros['job_type'] ?? '',
        'q' => $filtros['q'] ?? '',
        'so_travados' => !empty($filtros['so_travados']) ? '1' : '',
    ], $extra);
    $params = array_filter($params, static fn ($v) => $v !== '' && $v !== null && $v !== 0 && $v !== '0');
    return URL . '/master/fila-ia?' . http_build_query($params);
};
?>

<div class="mb-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1">Fila IA</h2>
            <p class="text-slate-600 text-sm">
                Jobs assíncronos (`ai_jobs`) por escola e histórico do cron <code class="text-xs bg-slate-100 px-1 rounded">process_ai_jobs</code>.
            </p>
        </div>
        <?php if ($aba === 'fila'): ?>
        <button type="button" onclick="openFilterDrawer()"
                class="relative inline-flex items-center px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors flex-shrink-0">
            <i class="fa-solid fa-filter mr-2 text-slate-500"></i>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
            <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    <?= htmlspecialchars((string) $_SESSION['flash_error']) ?>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

<?php if ($aba === 'fila'): ?>
<div class="grid grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Pending</p>
        <p class="text-2xl font-bold text-amber-600 mt-1"><?= $fmtInt($kpis['pending'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Processing</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= $fmtInt($kpis['processing'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Failed</p>
        <p class="text-2xl font-bold text-red-600 mt-1"><?= $fmtInt($kpis['failed'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Travados</p>
        <p class="text-2xl font-bold text-orange-600 mt-1"><?= $fmtInt($kpis['travados'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Escolas c/ fila</p>
        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $fmtInt($kpis['escolas_com_fila'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Último cron</p>
        <?php if ($ultima_cron): ?>
        <p class="text-sm font-semibold text-slate-900 mt-1"><?= $statusBadge((string) ($ultima_cron['status'] ?? '')) ?></p>
        <p class="text-xs text-slate-500 mt-1"><?= $fmtDt($ultima_cron['iniciado_em'] ?? '') ?></p>
        <?php else: ?>
        <p class="text-sm text-slate-400 mt-2">Sem registro</p>
        <?php endif; ?>
    </div>
</div>
<?php else: ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Último status</p>
        <?php if ($ultima_cron): ?>
        <p class="text-sm font-semibold text-slate-900 mt-1"><?= $statusBadge((string) ($ultima_cron['status'] ?? '')) ?></p>
        <?php else: ?>
        <p class="text-sm text-slate-400 mt-2">Sem registro</p>
        <?php endif; ?>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Última execução</p>
        <p class="text-sm font-semibold text-slate-900 mt-1"><?= $fmtDt($ultima_cron['iniciado_em'] ?? '') ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Jobs na última</p>
        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $fmtInt($ultima_cron['jobs_processados'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Escolas (ok/erro)</p>
        <p class="text-2xl font-bold text-slate-900 mt-1">
            <?= (int) ($ultima_cron['escolas_ok'] ?? 0) ?>/<?= (int) ($ultima_cron['escolas_erro'] ?? 0) ?>
        </p>
    </div>
</div>
<?php endif; ?>

<div class="flex gap-2 mb-4 border-b border-slate-200">
    <a href="<?= htmlspecialchars($queryBase(['aba' => 'fila', 'page' => 1])) ?>"
       class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px <?= $aba === 'fila' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
        Fila de jobs
    </a>
    <a href="<?= htmlspecialchars(URL . '/master/fila-ia?aba=cron') ?>"
       class="px-4 py-2.5 text-sm font-medium border-b-2 -mb-px <?= $aba === 'cron' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-800' ?>">
        Execuções do cron
    </a>
</div>

<?php if (!empty($erros) && $aba === 'fila'): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
    <p class="font-medium mb-1">Avisos ao consultar algumas escolas:</p>
    <ul class="list-disc list-inside text-xs space-y-0.5">
        <?php foreach (array_slice($erros, 0, 8) as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<?php if ($aba === 'fila'): ?>
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-slate-900">Jobs</h3>
        <span class="text-xs text-slate-500"><?= $fmtInt($totalJobs) ?> resultado(s)</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Usuário</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Criado</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($jobs)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                        Nenhum job na fila<?= !empty($filtros['so_travados']) ? ' travado' : ' ativa' ?> com os filtros atuais.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($jobs as $job): ?>
                <tr class="hover:bg-slate-50 <?= !empty($job['travado']) ? 'bg-orange-50/40' : '' ?>">
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-slate-900"><?= htmlspecialchars((string) ($job['escola_nome'] ?? '')) ?></div>
                        <div class="text-xs text-slate-500">#<?= (int) ($job['id'] ?? 0) ?> · tent. <?= (int) ($job['attempts'] ?? 0) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars((string) ($job['job_type'] ?? '')) ?></code>
                        <?php if (!empty($job['payload_resumo'])): ?>
                        <div class="text-xs text-slate-500 mt-1 max-w-[220px] truncate" title="<?= htmlspecialchars((string) $job['payload_resumo']) ?>">
                            <?= htmlspecialchars((string) $job['payload_resumo']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <?= $statusBadge((string) ($job['status'] ?? '')) ?>
                        <?php if (!empty($job['travado'])): ?>
                        <span class="ml-1 inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">travado</span>
                        <?php endif; ?>
                        <?php if (!empty($job['error_message'])): ?>
                        <div class="text-xs text-red-600 mt-1 max-w-[200px] truncate" title="<?= htmlspecialchars((string) $job['error_message']) ?>">
                            <?= htmlspecialchars((string) $job['error_message']) ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <?php
                        $nome = $job['usuario_nome'] ?? $job['aluno_nome'] ?? $job['professor_nome'] ?? null;
                        $role = $job['user_role'] ?? '';
                        ?>
                        <?php if ($nome): ?>
                        <div class="font-medium"><?= htmlspecialchars((string) $nome) ?></div>
                        <div class="text-xs text-slate-500"><?= htmlspecialchars((string) $role) ?><?= !empty($job['user_id']) ? ' #' . (int) $job['user_id'] : '' ?></div>
                        <?php elseif (!empty($job['user_id'])): ?>
                        <div class="text-slate-600"><?= htmlspecialchars((string) $role) ?> #<?= (int) $job['user_id'] ?></div>
                        <?php else: ?>
                        <span class="text-slate-400">—</span>
                        <?php endif; ?>
                        <?php if (!empty($job['aluno_nome']) && ($job['user_role'] ?? '') !== 'aluno'): ?>
                        <div class="text-xs text-slate-500 mt-0.5">Aluno: <?= htmlspecialchars((string) $job['aluno_nome']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600 whitespace-nowrap"><?= $fmtDt($job['created_at'] ?? '') ?></td>
                    <td class="px-4 py-3 text-sm text-right">
                        <a href="<?= URL ?>/master/fila-ia/job?escola_id=<?= (int) ($job['escola_id'] ?? 0) ?>&job_id=<?= (int) ($job['id'] ?? 0) ?>"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($totalPages > 1): ?>
    <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-600">
        <span>Página <?= $page ?> de <?= $totalPages ?></span>
        <div class="flex gap-2">
            <?php if ($page > 1): ?>
            <a class="px-3 py-1.5 border rounded-lg hover:bg-slate-50" href="<?= htmlspecialchars($queryBase(['page' => $page - 1])) ?>">Anterior</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
            <a class="px-3 py-1.5 border rounded-lg hover:bg-slate-50" href="<?= htmlspecialchars($queryBase(['page' => $page + 1])) ?>">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Drawer filtros -->
<div id="filterDrawer" class="fixed inset-0 z-50 hidden" aria-hidden="true">
    <div class="absolute inset-0 bg-slate-900/40" onclick="closeFilterDrawer()"></div>
    <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-xl flex flex-col">
        <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-slate-900">Filtros</h3>
            <button type="button" onclick="closeFilterDrawer()" class="p-2 text-slate-500 hover:bg-slate-100 rounded-lg">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>
        <form method="get" action="<?= URL ?>/master/fila-ia" class="flex flex-col flex-1 overflow-hidden">
            <input type="hidden" name="aba" value="fila">
            <div class="flex-1 overflow-y-auto p-5 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Escola</label>
                    <select name="escola_id" class="w-full border border-slate-300 rounded-lg px-3 py-2 bg-white text-sm">
                        <option value="0">Todas</option>
                        <?php foreach ($escolas as $e): ?>
                        <option value="<?= (int) $e['id'] ?>" <?= (int) ($filtros['escola_id'] ?? 0) === (int) $e['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) $e['nome']) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Status</label>
                    <select name="status" class="w-full border border-slate-300 rounded-lg px-3 py-2 bg-white text-sm">
                        <option value="">Ativos (pending/processing/failed)</option>
                        <?php foreach (['pending', 'processing', 'failed', 'done'] as $st): ?>
                        <option value="<?= $st ?>" <?= ($filtros['status'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Tipo de job</label>
                    <input type="text" name="job_type" value="<?= htmlspecialchars((string) ($filtros['job_type'] ?? '')) ?>"
                           placeholder="ex.: corrigir_redacao"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Busca (nome, id, erro)</label>
                    <input type="text" name="q" value="<?= htmlspecialchars((string) ($filtros['q'] ?? '')) ?>"
                           class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm">
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="so_travados" value="1" class="rounded border-slate-300 text-blue-600"
                           <?= !empty($filtros['so_travados']) ? 'checked' : '' ?>>
                    Somente travados
                </label>
            </div>
            <div class="px-5 py-4 border-t border-slate-200 flex gap-2">
                <a href="<?= URL ?>/master/fila-ia?aba=fila" class="flex-1 text-center px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Limpar</a>
                <button type="submit" class="flex-1 px-4 py-2.5 rounded-lg text-sm font-medium text-white bg-blue-600 hover:bg-blue-700">Aplicar</button>
            </div>
        </form>
    </div>
</div>
<script>
function openFilterDrawer(){var d=document.getElementById('filterDrawer');if(!d)return;d.classList.remove('hidden');d.setAttribute('aria-hidden','false');}
function closeFilterDrawer(){var d=document.getElementById('filterDrawer');if(!d)return;d.classList.add('hidden');d.setAttribute('aria-hidden','true');}
</script>

<?php else: ?>

<?php if (!$cronTabelaOk): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
    Tabela <code class="bg-amber-100 px-1 rounded">cron_execucoes</code> ainda não existe no master.
    Execute a migration <code class="bg-amber-100 px-1 rounded">2026_08_10_cron_execucoes_master.sql</code> em Migrations.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900">Execuções do cron</h3>
        <span class="text-xs text-slate-500"><?= $fmtInt($cronTotal) ?> registro(s) · retenção 30 dias</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Início</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escolas</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Jobs</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Duração</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($cronItems)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">
                        Nenhuma execução registrada ainda. O cron grava automaticamente a cada minuto após a migration.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($cronItems as $ex): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm text-slate-700 whitespace-nowrap">
                        <div><?= $fmtDt($ex['iniciado_em'] ?? '') ?></div>
                        <div class="text-xs text-slate-400">#<?= (int) ($ex['id'] ?? 0) ?><?= !empty($ex['hostname']) ? ' · ' . htmlspecialchars((string) $ex['hostname']) : '' ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm"><?= $statusBadge((string) ($ex['status'] ?? '')) ?></td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        ok <?= (int) ($ex['escolas_ok'] ?? 0) ?>
                        · erro <?= (int) ($ex['escolas_erro'] ?? 0) ?>
                        · pulada <?= (int) ($ex['escolas_puladas'] ?? 0) ?>
                        <div class="text-xs text-slate-400">total <?= (int) ($ex['escolas_total'] ?? 0) ?></div>
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-slate-900"><?= $fmtInt($ex['jobs_processados'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-sm text-slate-600">
                        <?php
                        $ms = isset($ex['duracao_ms']) ? (int) $ex['duracao_ms'] : null;
                        echo $ms === null ? '—' : ($ms < 1000 ? $ms . ' ms' : number_format($ms / 1000, 1, ',', '.') . ' s');
                        ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-right">
                        <a href="<?= URL ?>/master/fila-ia/cron?id=<?= (int) ($ex['id'] ?? 0) ?>"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Ver
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($cronPages > 1): ?>
    <div class="px-6 py-3 border-t border-slate-200 flex items-center justify-between text-sm text-slate-600">
        <span>Página <?= $cronPage ?> de <?= $cronPages ?></span>
        <div class="flex gap-2">
            <?php if ($cronPage > 1): ?>
            <a class="px-3 py-1.5 border rounded-lg hover:bg-slate-50" href="<?= URL ?>/master/fila-ia?aba=cron&page=<?= $cronPage - 1 ?>">Anterior</a>
            <?php endif; ?>
            <?php if ($cronPage < $cronPages): ?>
            <a class="px-3 py-1.5 border rounded-lg hover:bg-slate-50" href="<?= URL ?>/master/fila-ia?aba=cron&page=<?= $cronPage + 1 ?>">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>
