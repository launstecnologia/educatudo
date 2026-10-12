<?php
$summary = $summary ?? [];
$byRoute = $by_route ?? [];
$topQueries = $top_queries ?? [];
$nPlusOne = $n_plus_one ?? [];
$filters = $filters ?? [];
$tenants = $tenants ?? [];
$profilerEnabled = $profiler_enabled ?? false;
$appDebugOn = $app_debug_on ?? false;
$profilerPaused = $profiler_paused ?? false;
$csrfToken = $csrf_token ?? '';

$fmt = static fn ($n) => number_format((float) $n, 1, ',', '.');
$maxRouteTime = 0.0;
foreach ($byRoute as $r) {
    $maxRouteTime = max($maxRouteTime, (float) $r['avg_time_ms']);
}
$maxQueryTime = 0.0;
foreach ($topQueries as $q) {
    $maxQueryTime = max($maxQueryTime, (float) $q['avg_ms']);
}
$qs = static function (array $overrides) use ($filters) {
    $merged = array_merge($filters, $overrides);
    return '?' . http_build_query($merged);
};
?>

<?php if (!$appDebugOn): ?>
<div class="mb-6 bg-amber-50 border border-amber-300 text-amber-800 rounded-xl p-4 text-sm">
    <strong>Profiler desligado no servidor.</strong> Este painel só coleta dado novo quando
    <code class="bg-amber-100 px-1 rounded">APP_DEBUG=true</code> no <code>.env</code> do servidor que
    está sendo observado (precisa editar o arquivo e reiniciar o processo — é a trava de segurança
    pra nunca vazar isso em produção sem querer). Os números abaixo são do que já foi coletado antes
    (se houver).
</div>
<?php else: ?>
<div class="mb-6 flex items-center justify-between gap-4 rounded-xl border p-4 text-sm
            <?= $profilerPaused ? 'bg-slate-50 border-slate-300 text-slate-700' : 'bg-green-50 border-green-300 text-green-800' ?>">
    <div class="flex items-center gap-2">
        <span class="w-2.5 h-2.5 rounded-full <?= $profilerPaused ? 'bg-slate-400' : 'bg-green-500 animate-pulse' ?>"></span>
        <strong><?= $profilerPaused ? 'Coleta pausada' : 'Coleta ativa' ?></strong>
        <span class="text-xs opacity-75">
            <?= $profilerPaused
                ? 'nenhuma página nova está sendo registrada agora.'
                : 'toda página acessada agora está sendo registrada automaticamente.' ?>
        </span>
    </div>
    <form method="post" action="<?= URL ?>/master/performance/toggle">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
        <input type="hidden" name="action" value="<?= $profilerPaused ? 'resume' : 'pause' ?>">
        <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium
                    <?= $profilerPaused ? 'bg-green-600 text-white hover:bg-green-700' : 'bg-slate-700 text-white hover:bg-slate-800' ?>">
            <?= $profilerPaused ? '▶ Retomar coleta' : '⏸ Pausar coleta' ?>
        </button>
    </form>
</div>
<?php endif; ?>

<!-- Filtros -->
<form method="get" action="<?= URL ?>/master/performance" class="bg-white rounded-xl shadow-sm p-5 border border-slate-200 mb-6">
    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-8 gap-3">
        <div class="col-span-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">De</label>
            <input type="date" name="date_from" value="<?= htmlspecialchars($filters['date_from'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div class="col-span-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">Até</label>
            <input type="date" name="date_to" value="<?= htmlspecialchars($filters['date_to'] ?? '') ?>" class="w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div class="col-span-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">Escola</label>
            <select name="tenant" class="w-full rounded-lg border-slate-300 text-sm">
                <option value="">Todas</option>
                <?php foreach ($tenants as $t): ?>
                    <option value="<?= htmlspecialchars($t) ?>" <?= ($filters['tenant'] ?? '') === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-span-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">Rota contém</label>
            <input type="text" name="route" value="<?= htmlspecialchars($filters['route'] ?? '') ?>" placeholder="/dashboard" class="w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div class="col-span-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">Controller contém</label>
            <input type="text" name="controller" value="<?= htmlspecialchars($filters['controller'] ?? '') ?>" placeholder="StudentController" class="w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div class="col-span-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">Tempo mín. (ms)</label>
            <input type="number" name="min_ms" value="<?= htmlspecialchars((string) ($filters['min_ms'] ?? '')) ?>" class="w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div class="col-span-1">
            <label class="block text-xs font-medium text-slate-500 mb-1">Queries mín.</label>
            <input type="number" name="min_queries" value="<?= htmlspecialchars((string) ($filters['min_queries'] ?? '')) ?>" class="w-full rounded-lg border-slate-300 text-sm">
        </div>
        <div class="col-span-1 flex items-end gap-2">
            <label class="inline-flex items-center gap-1 text-xs text-slate-600">
                <input type="checkbox" name="only_alerts" value="1" <?= !empty($filters['only_alerts']) ? 'checked' : '' ?>>
                só com alerta
            </label>
        </div>
    </div>
    <div class="mt-4 flex items-center gap-3">
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-lg text-sm font-medium hover:bg-blue-700">Filtrar</button>
        <a href="<?= URL ?>/master/performance" class="text-sm text-slate-500 hover:underline">Limpar</a>
        <span class="text-xs text-slate-400 ml-auto">Exportar:</span>
        <a href="<?= URL ?>/master/performance/export<?= $qs(['dataset' => 'routes', 'format' => 'csv']) ?>" class="text-xs text-blue-600 hover:underline">Páginas (CSV)</a>
        <a href="<?= URL ?>/master/performance/export<?= $qs(['dataset' => 'queries', 'format' => 'csv']) ?>" class="text-xs text-blue-600 hover:underline">Queries (CSV)</a>
        <a href="<?= URL ?>/master/performance/export<?= $qs(['format' => 'pdf']) ?>" class="text-xs text-blue-600 hover:underline">PDF</a>
        <a href="<?= URL ?>/master/performance/export<?= $qs(['dataset' => 'routes', 'format' => 'json']) ?>" class="text-xs text-blue-600 hover:underline">JSON</a>
    </div>
</form>

<!-- Cards de resumo -->
<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4 mb-8">
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
        <p class="text-xs font-medium text-slate-500">Requests</p>
        <p class="text-2xl font-bold text-slate-800"><?= $summary['total_requests'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
        <p class="text-xs font-medium text-slate-500">Queries totais</p>
        <p class="text-2xl font-bold text-slate-800"><?= $summary['total_queries'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
        <p class="text-xs font-medium text-slate-500">Média queries/req</p>
        <p class="text-2xl font-bold text-blue-600"><?= $fmt($summary['avg_queries'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border border-slate-200">
        <p class="text-xs font-medium text-slate-500">Tempo médio (ms)</p>
        <p class="text-2xl font-bold text-blue-600"><?= $fmt($summary['avg_time_ms'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border <?= ($summary['requests_with_alerts'] ?? 0) > 0 ? 'border-amber-300' : 'border-slate-200' ?>">
        <p class="text-xs font-medium text-slate-500">Requests com alerta</p>
        <p class="text-2xl font-bold <?= ($summary['requests_with_alerts'] ?? 0) > 0 ? 'text-amber-600' : 'text-slate-800' ?>"><?= $summary['requests_with_alerts'] ?? 0 ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-4 border <?= ($summary['requests_with_n1'] ?? 0) > 0 ? 'border-red-300' : 'border-slate-200' ?>">
        <p class="text-xs font-medium text-slate-500">Requests com N+1</p>
        <p class="text-2xl font-bold <?= ($summary['requests_with_n1'] ?? 0) > 0 ? 'text-red-600' : 'text-slate-800' ?>"><?= $summary['requests_with_n1'] ?? 0 ?></p>
    </div>
</div>

<?php
$byDay = $by_day ?? [];
$trendDays = $trend_days ?? 14;
$maxDayTime = 0.0;
foreach ($byDay as $d) {
    $maxDayTime = max($maxDayTime, (float) $d['avg_time_ms']);
}
// "Está piorando?": compara a média da 2ª metade da janela com a 1ª metade.
$trendPct = null;
if (count($byDay) >= 4) {
    $half = (int) floor(count($byDay) / 2);
    $firstHalf = array_slice($byDay, 0, $half);
    $secondHalf = array_slice($byDay, -$half);
    $avgFirst = array_sum(array_column($firstHalf, 'avg_time_ms')) / max(1, count($firstHalf));
    $avgSecond = array_sum(array_column($secondHalf, 'avg_time_ms')) / max(1, count($secondHalf));
    if ($avgFirst > 0) {
        $trendPct = round((($avgSecond - $avgFirst) / $avgFirst) * 100, 0);
    }
}
?>
<!-- Tendência diária: responde "está piorando com o tempo?" -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-8">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-3">
            <h2 class="font-semibold text-slate-800">Tendência — tempo médio por dia</h2>
            <?php if ($trendPct !== null): ?>
                <span class="text-xs font-semibold px-2 py-1 rounded-full
                    <?= $trendPct > 15 ? 'bg-red-100 text-red-700' : ($trendPct < -15 ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600') ?>">
                    <?= $trendPct > 0 ? '▲' : ($trendPct < 0 ? '▼' : '—') ?>
                    <?= abs($trendPct) ?>% <?= $trendPct > 0 ? 'mais lento' : ($trendPct < 0 ? 'mais rápido' : 'estável') ?>
                    vs. início da janela
                </span>
            <?php endif; ?>
        </div>
        <div class="flex gap-1 text-xs">
            <?php foreach ([7, 14, 30, 90] as $opt): ?>
                <a href="<?= $qs(['trend_days' => $opt]) ?>" class="px-2 py-1 rounded <?= $trendDays === $opt ? 'bg-blue-100 text-blue-700 font-semibold' : 'text-slate-500 hover:bg-slate-50' ?>"><?= $opt ?>d</a>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="p-5">
        <?php if ($byDay === []): ?>
            <p class="text-center text-slate-400 text-sm py-6">Sem dados coletados nos últimos <?= $trendDays ?> dias
                (lembre: os arquivos são apagados automaticamente após <code>PERF_RETENTION_DAYS</code> dias).</p>
        <?php else: ?>
            <div class="flex items-end gap-1.5 h-40 overflow-x-auto">
                <?php foreach ($byDay as $d): ?>
                    <?php
                    $heightPct = $maxDayTime > 0 ? max(3, round(($d['avg_time_ms'] / $maxDayTime) * 100)) : 3;
                    $barColor = $d['avg_time_ms'] > 1000 ? 'bg-red-500' : ($d['avg_time_ms'] > 300 ? 'bg-amber-500' : 'bg-blue-500');
                    ?>
                    <div class="flex flex-col items-center justify-end h-full flex-shrink-0" style="width: <?= max(24, floor(100 / max(1, count($byDay)))) ?>px" title="<?= htmlspecialchars($d['date']) ?>: <?= $fmt($d['avg_time_ms']) ?>ms médio, <?= $d['hits'] ?> request(s), pico <?= $fmt($d['max_time_ms']) ?>ms">
                        <span class="text-[10px] text-slate-500 mb-1"><?= $fmt($d['avg_time_ms']) ?></span>
                        <div class="w-full rounded-t <?= $barColor ?>" style="height: <?= $heightPct ?>%"></div>
                        <span class="text-[10px] text-slate-400 mt-1 whitespace-nowrap"><?= substr($d['date'], 5) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-slate-400 mt-3">Barra = tempo médio de resposta do dia (ms). Vermelho &gt;1000ms, laranja &gt;300ms.
                Passe o mouse numa barra pra ver detalhes. Filtro de escola/rota/controller acima também vale aqui; tempo mín./queries mín./só-alerta não (queremos a média de tudo, não só o pior).</p>
        <?php endif; ?>
    </div>
</div>

<!-- Páginas mais lentas -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-8">
    <div class="px-5 py-4 border-b border-slate-200">
        <h2 class="font-semibold text-slate-800">Páginas mais lentas (por controller@ação)</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-4 py-2">Controller@Ação</th>
                    <th class="text-left px-4 py-2">Rota</th>
                    <th class="text-right px-4 py-2">Hits</th>
                    <th class="text-right px-4 py-2">Queries méd.</th>
                    <th class="text-left px-4 py-2 w-64">Tempo médio</th>
                    <th class="text-right px-4 py-2">Pico (ms)</th>
                    <th class="text-right px-4 py-2">Mem. média (MB)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($byRoute === []): ?>
                <tr><td colspan="7" class="px-4 py-6 text-center text-slate-400">Sem dados no período selecionado.</td></tr>
                <?php endif; ?>
                <?php foreach (array_slice($byRoute, 0, 40) as $r): ?>
                <tr>
                    <td class="px-4 py-2 font-mono text-xs"><?= htmlspecialchars($r['controller_action']) ?></td>
                    <td class="px-4 py-2 text-slate-500"><?= htmlspecialchars($r['route']) ?></td>
                    <td class="px-4 py-2 text-right"><?= $r['hits'] ?></td>
                    <td class="px-4 py-2 text-right"><?= $fmt($r['avg_queries']) ?></td>
                    <td class="px-4 py-2">
                        <div class="flex items-center gap-2">
                            <div class="h-2 bg-blue-500 rounded" style="width: <?= $maxRouteTime > 0 ? max(2, round(($r['avg_time_ms'] / $maxRouteTime) * 100)) : 0 ?>%"></div>
                            <span class="text-xs text-slate-500"><?= $fmt($r['avg_time_ms']) ?>ms</span>
                        </div>
                    </td>
                    <td class="px-4 py-2 text-right <?= $r['max_time_ms'] > 2000 ? 'text-red-600 font-semibold' : '' ?>"><?= $fmt($r['max_time_ms']) ?></td>
                    <td class="px-4 py-2 text-right"><?= $fmt($r['avg_memory_mb']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Top queries -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-8">
    <div class="px-5 py-4 border-b border-slate-200 flex items-center justify-between">
        <h2 class="font-semibold text-slate-800">Top consultas SQL (queries acima do limiar de EXPLAIN)</h2>
        <div class="flex gap-2 text-xs">
            <a href="<?= $qs(['order_by' => 'avg']) ?>" class="px-2 py-1 rounded <?= ($_GET['order_by'] ?? 'avg') === 'avg' ? 'bg-blue-100 text-blue-700' : 'text-slate-500' ?>">Média</a>
            <a href="<?= $qs(['order_by' => 'total']) ?>" class="px-2 py-1 rounded <?= ($_GET['order_by'] ?? '') === 'total' ? 'bg-blue-100 text-blue-700' : 'text-slate-500' ?>">Total</a>
            <a href="<?= $qs(['order_by' => 'count']) ?>" class="px-2 py-1 rounded <?= ($_GET['order_by'] ?? '') === 'count' ? 'bg-blue-100 text-blue-700' : 'text-slate-500' ?>">Qtd.</a>
            <a href="<?= $qs(['order_by' => 'max']) ?>" class="px-2 py-1 rounded <?= ($_GET['order_by'] ?? '') === 'max' ? 'bg-blue-100 text-blue-700' : 'text-slate-500' ?>">Máx.</a>
        </div>
    </div>
    <div class="divide-y divide-slate-100">
        <?php if ($topQueries === []): ?>
        <p class="px-5 py-6 text-center text-slate-400 text-sm">Nenhuma query acima do limiar de EXPLAIN neste período (bom sinal).</p>
        <?php endif; ?>
        <?php foreach ($topQueries as $i => $q): ?>
        <details class="px-5 py-3">
            <summary class="cursor-pointer flex items-center gap-3 text-sm">
                <span class="text-slate-400 w-5 text-right">#<?= $i + 1 ?></span>
                <span class="flex-1 font-mono text-xs text-slate-700 truncate"><?= htmlspecialchars(substr($q['sample_sql'], 0, 140)) ?></span>
                <span class="text-xs text-slate-500 whitespace-nowrap"><?= $q['count'] ?>x</span>
                <span class="text-xs font-semibold text-blue-600 whitespace-nowrap w-20 text-right"><?= $fmt($q['avg_ms']) ?>ms</span>
                <?php foreach ($q['flags'] as $flag): ?>
                    <span class="text-[10px] uppercase bg-red-100 text-red-700 px-2 py-0.5 rounded-full whitespace-nowrap"><?= htmlspecialchars($flag) ?></span>
                <?php endforeach; ?>
            </summary>
            <div class="mt-3 ml-8 space-y-2">
                <pre class="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs overflow-x-auto"><?= htmlspecialchars($q['sample_sql']) ?></pre>
                <p class="text-xs text-slate-500">Rota de exemplo: <span class="font-mono"><?= htmlspecialchars($q['route']) ?></span> · total acumulado: <?= $fmt($q['total_ms']) ?>ms · máximo observado: <?= $fmt($q['max_ms']) ?>ms</p>
                <?php if (!empty($q['index_advice'])): ?>
                <div class="bg-amber-50 border border-amber-200 rounded-lg p-3 text-xs">
                    <strong class="text-amber-800">Sugestão de índice (heurística — valide antes de aplicar):</strong>
                    <pre class="mt-1 whitespace-pre-wrap font-mono text-amber-900"><?= htmlspecialchars($q['index_advice']) ?></pre>
                </div>
                <?php endif; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
</div>

<!-- N+1 -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 mb-8">
    <div class="px-5 py-4 border-b border-slate-200">
        <h2 class="font-semibold text-slate-800">Padrões N+1 detectados</h2>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-slate-500 text-xs uppercase">
                <tr>
                    <th class="text-left px-4 py-2">SQL</th>
                    <th class="text-left px-4 py-2">Rota</th>
                    <th class="text-right px-4 py-2">Máx. repetições/req</th>
                    <th class="text-right px-4 py-2">Tempo desperdiçado (ms)</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if ($nPlusOne === []): ?>
                <tr><td colspan="4" class="px-4 py-6 text-center text-slate-400">Nenhum N+1 detectado no período (bom sinal).</td></tr>
                <?php endif; ?>
                <?php foreach ($nPlusOne as $n): ?>
                <tr>
                    <td class="px-4 py-2">
                        <span class="font-mono text-xs text-slate-700"><?= htmlspecialchars(substr($n['sample_sql'], 0, 100)) ?></span>
                        <p class="text-xs text-red-600 mt-1"><?= htmlspecialchars($n['suggestion']) ?></p>
                    </td>
                    <td class="px-4 py-2 font-mono text-xs text-slate-500"><?= htmlspecialchars($n['route']) ?></td>
                    <td class="px-4 py-2 text-right font-semibold text-red-600"><?= $n['max_repeats_in_one_request'] ?>x</td>
                    <td class="px-4 py-2 text-right"><?= $fmt($n['total_wasted_ms']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<p class="text-xs text-slate-400 mb-8">
    Este painel lê os arquivos <code>storage/logs/performance/requests_*.jsonl</code> — nenhum dado é
    lido do banco de dados de nenhuma escola. Coleta é 100% opt-in via <code>APP_DEBUG=true</code> e
    nunca altera dados nem cria índices sozinha.
</p>
