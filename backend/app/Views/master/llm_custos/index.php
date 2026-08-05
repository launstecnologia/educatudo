<?php
$relatorio = $relatorio ?? [];
$escolas = $escolas ?? [];
$date_start = $date_start ?? date('Y-m-d', strtotime('-30 days'));
$date_end = $date_end ?? date('Y-m-d');
$filtro_escola = (int) ($filtro_escola ?? 0);
$totais = $relatorio['totais'] ?? [];
$porModelo = $relatorio['por_modelo'] ?? [];
$porEscola = $relatorio['por_escola'] ?? [];
$porTipo = $relatorio['por_tipo'] ?? [];
$porDia = $relatorio['por_dia'] ?? [];
$erros = $relatorio['erros'] ?? [];

$fmtInt = static fn ($n) => number_format((int) $n, 0, ',', '.');
$fmtUsd = static fn ($n) => '$' . number_format((float) $n, 4, '.', ',');
$fmtUsdShort = static fn ($n) => '$' . number_format((float) $n, 2, '.', ',');
$fmtData = static function ($ymd) {
    $ymd = trim((string) $ymd);
    if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return $ymd;
    }
    $ts = strtotime($ymd);
    return $ts ? date('d/m/Y', $ts) : $ymd;
};

$filtrosAtivos = 0;
if ($filtro_escola > 0) {
    $filtrosAtivos++;
}
$padraoIni = date('Y-m-d', strtotime('-30 days'));
$padraoFim = date('Y-m-d');
if ($date_start !== $padraoIni) {
    $filtrosAtivos++;
}
if ($date_end !== $padraoFim) {
    $filtrosAtivos++;
}
$limparUrl = URL . '/master/llm-custos';
?>

<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1">Custo LLM</h2>
            <p class="text-slate-600 text-sm">
                Tokens de input e output e custo em USD, agregados por escola, modelo e período.
            </p>
        </div>
        <button type="button" onclick="openFilterDrawer()"
                class="relative inline-flex items-center px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors flex-shrink-0">
            <i class="fa-solid fa-filter mr-2 text-slate-500"></i>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
            <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>

<?php if (!empty($erros)): ?>
<div class="mb-6 px-4 py-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
    <p class="font-medium mb-1">Avisos ao consultar algumas escolas:</p>
    <ul class="list-disc list-inside text-xs space-y-0.5">
        <?php foreach (array_slice($erros, 0, 8) as $err): ?>
        <li><?= htmlspecialchars($err) ?></li>
        <?php endforeach; ?>
        <?php if (count($erros) > 8): ?>
        <li>… e mais <?= count($erros) - 8 ?></li>
        <?php endif; ?>
    </ul>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-8">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Requisições</p>
        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $fmtInt($totais['requests'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Input tokens</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= $fmtInt($totais['prompt_tokens'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Output tokens</p>
        <p class="text-2xl font-bold text-violet-600 mt-1"><?= $fmtInt($totais['completion_tokens'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Custo USD</p>
        <p class="text-2xl font-bold text-emerald-700 mt-1"><?= $fmtUsdShort($totais['cost_usd'] ?? 0) ?></p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-4">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between">
        <h3 class="text-lg font-semibold text-slate-900">Por modelo</h3>
        <span class="text-xs text-slate-500">Total tokens: <?= $fmtInt($totais['total_tokens'] ?? 0) ?></span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Modelo</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Req.</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Input</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Output</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">USD</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($porModelo as $row): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-mono text-slate-800"><?= htmlspecialchars($row['model'] ?? '') ?></td>
                    <td class="px-4 py-3 text-sm text-right text-slate-700"><?= $fmtInt($row['requests'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-blue-700"><?= $fmtInt($row['prompt_tokens'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-violet-700"><?= $fmtInt($row['completion_tokens'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-sm text-right font-medium text-slate-900"><?= $fmtInt($row['total_tokens'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-emerald-700"><?= $fmtUsd($row['cost_usd'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($porModelo)): ?>
                <tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-500">Nenhum uso de LLM no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-900">Por escola</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Input</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Output</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">USD</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($porEscola as $row): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm">
                            <span class="font-medium text-slate-900"><?= htmlspecialchars($row['nome'] ?? '') ?></span>
                            <span class="block text-xs text-slate-400"><?= htmlspecialchars($row['slug'] ?? '') ?> · <?= $fmtInt($row['requests'] ?? 0) ?> req.</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-blue-700"><?= $fmtInt($row['prompt_tokens'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-violet-700"><?= $fmtInt($row['completion_tokens'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-medium text-emerald-700"><?= $fmtUsd($row['cost_usd'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($porEscola)): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">Sem dados no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200">
            <h3 class="text-lg font-semibold text-slate-900">Por tipo de uso</h3>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Tipo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Req.</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Tokens</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">USD</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($porTipo as $row): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-800"><?= htmlspecialchars($row['usage_type'] ?? '') ?></td>
                        <td class="px-4 py-3 text-sm text-right text-slate-700"><?= $fmtInt($row['requests'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-slate-700"><?= $fmtInt($row['total_tokens'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-emerald-700"><?= $fmtUsd($row['cost_usd'] ?? 0) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($porTipo)): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-sm text-slate-500">Sem dados no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Por dia</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Req.</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Input</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Output</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Total</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">USD</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach (array_reverse($porDia) as $row): ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2.5 text-sm text-slate-800"><?= htmlspecialchars($fmtData($row['date'] ?? '')) ?></td>
                    <td class="px-4 py-2.5 text-sm text-right text-slate-700"><?= $fmtInt($row['requests'] ?? 0) ?></td>
                    <td class="px-4 py-2.5 text-sm text-right text-blue-700"><?= $fmtInt($row['prompt_tokens'] ?? 0) ?></td>
                    <td class="px-4 py-2.5 text-sm text-right text-violet-700"><?= $fmtInt($row['completion_tokens'] ?? 0) ?></td>
                    <td class="px-4 py-2.5 text-sm text-right font-medium"><?= $fmtInt($row['total_tokens'] ?? 0) ?></td>
                    <td class="px-4 py-2.5 text-sm text-right text-emerald-700"><?= $fmtUsd($row['cost_usd'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($porDia)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Sem dados no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Filtrar custo LLM</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/master/llm-custos" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_data_inicio" class="block text-sm font-medium text-slate-700 mb-1.5">Data início</label>
                <input type="date" id="filtro_data_inicio" name="data_inicio" value="<?= htmlspecialchars($date_start) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_data_fim" class="block text-sm font-medium text-slate-700 mb-1.5">Data fim</label>
                <input type="date" id="filtro_data_fim" name="data_fim" value="<?= htmlspecialchars($date_end) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_escola" class="block text-sm font-medium text-slate-700 mb-1.5">Escola</label>
                <select id="filtro_escola" name="escola_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todas as escolas</option>
                    <?php foreach ($escolas as $e): ?>
                    <option value="<?= (int) $e['id'] ?>" <?= $filtro_escola === (int) $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nome'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex gap-3 bg-slate-50">
            <a href="<?= htmlspecialchars($limparUrl) ?>"
               class="flex-1 px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors text-center">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<script>
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFilterDrawer();
});
</script>
