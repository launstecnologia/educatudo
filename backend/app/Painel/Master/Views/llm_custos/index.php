<?php
$relatorio = $relatorio ?? [];
$escolas = $escolas ?? [];
$date_start = $date_start ?? date('Y-m-d', strtotime('-30 days'));
$date_end = $date_end ?? date('Y-m-d');
$filtro_escola = (int) ($filtro_escola ?? 0);
$filtro_cotacao = (float) ($filtro_cotacao ?? ($relatorio['financeiro']['cotacao_usd_brl'] ?? 5.78));
$totais = $relatorio['totais'] ?? [];
$financeiro = $relatorio['financeiro'] ?? [];
$porModelo = $relatorio['por_modelo'] ?? [];
$porEscola = $relatorio['por_escola'] ?? [];
$porTipo = $relatorio['por_tipo'] ?? [];
$porDia = $relatorio['por_dia'] ?? [];
$erros = $relatorio['erros'] ?? [];

$fmtInt = static fn ($n) => number_format((int) $n, 0, ',', '.');
$fmtDec = static fn ($n, $c = 2) => number_format((float) $n, $c, ',', '.');
$fmtUsd = static fn ($n) => '$' . number_format((float) $n, 4, '.', ',');
$fmtUsdShort = static fn ($n) => '$' . number_format((float) $n, 2, '.', ',');
$fmtBrl = static fn ($n) => 'R$ ' . number_format((float) $n, 2, ',', '.');
$fmtTc = static fn ($n) => 'TC$ ' . number_format((float) $n, 2, ',', '.');
$fmtData = static function ($ymd) {
    $ymd = trim((string) $ymd);
    if ($ymd === '' || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $ymd)) {
        return $ymd;
    }
    $ts = strtotime($ymd);
    return $ts ? date('d/m/Y', $ts) : $ymd;
};

$lucroBrl = (float) ($financeiro['lucro_brl'] ?? 0);
$lucroClasse = $lucroBrl >= 0 ? 'text-emerald-700' : 'text-red-600';
$brlPorCredito = (float) ($financeiro['brl_por_credito'] ?? 0.20);
$cotacao = (float) ($financeiro['cotacao_usd_brl'] ?? $filtro_cotacao);

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
$cotacaoPadrao = \App\Services\MasterLlmCustosService::cotacaoUsdBrl(null);
if (abs($cotacao - $cotacaoPadrao) > 0.0001) {
    $filtrosAtivos++;
}
$limparUrl = URL . '/master/llm-custos';
?>

<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1">Custo LLM</h2>
            <p class="text-slate-600 text-sm">
                Tokens, custo USD/R$ e lucro estimado (1 TudiCoin = R$ <?= $fmtDec($brlPorCredito, 2) ?> · dólar R$ <?= $fmtDec($cotacao, 2) ?>).
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

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
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
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Custo LLM</p>
        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $fmtUsdShort($totais['cost_usd'] ?? 0) ?></p>
        <p class="text-sm text-slate-500 mt-0.5"><?= $fmtBrl($financeiro['custo_brl'] ?? 0) ?></p>
    </div>
</div>

<?php
$custoAlvoCredito = (float) ($financeiro['custo_alvo_brl_por_credito'] ?? 0.10);
$lucroAlvoCredito = (float) ($financeiro['lucro_alvo_brl_por_credito'] ?? max(0, $brlPorCredito - $custoAlvoCredito));
$tokensAlvo = $financeiro['tokens_por_credito_alvo'] ?? null;
$tokensReal = $financeiro['tokens_por_credito_real'] ?? null;
$custoRealCredito = $financeiro['custo_real_por_credito_brl'] ?? null;
$dentroMeta = $financeiro['dentro_meta_custo'] ?? null;
$usdMaxCredito = $financeiro['usd_max_por_credito'] ?? null;
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">TudiCoins consumidos</p>
        <p class="text-2xl font-bold text-slate-900 mt-1"><?= $fmtTc($financeiro['creditos_consumidos'] ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-1">× R$ <?= $fmtDec($brlPorCredito, 2) ?> / crédito</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Receita (créditos)</p>
        <p class="text-2xl font-bold text-blue-700 mt-1"><?= $fmtBrl($financeiro['receita_brl'] ?? 0) ?></p>
        <p class="text-xs text-slate-500 mt-1">Valor gerado pelo consumo no período</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Lucro estimado</p>
        <p class="text-2xl font-bold <?= $lucroClasse ?> mt-1"><?= $fmtBrl($lucroBrl) ?></p>
        <p class="text-xs text-slate-500 mt-1">Receita − custo LLM em R$</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Média / crédito</p>
        <?php if (($financeiro['lucro_medio_por_credito_brl'] ?? null) !== null): ?>
        <p class="text-2xl font-bold <?= $lucroClasse ?> mt-1"><?= $fmtBrl($financeiro['lucro_medio_por_credito_brl']) ?></p>
        <p class="text-xs text-slate-500 mt-1">
            Margem <?= $financeiro['margem_pct'] !== null ? $fmtDec($financeiro['margem_pct'], 1) . '%' : '—' ?>
            <?php if ($custoRealCredito !== null): ?>
            · custo <?= $fmtBrl($custoRealCredito) ?>
            <?php endif; ?>
        </p>
        <?php else: ?>
        <p class="text-2xl font-bold text-slate-400 mt-1">—</p>
        <p class="text-xs text-slate-500 mt-1">Sem consumo de créditos no período</p>
        <?php endif; ?>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-8">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
            <h3 class="text-base font-semibold text-slate-900">Meta por crédito</h3>
            <p class="text-sm text-slate-600 mt-1">
                Cobrar <strong>R$ <?= $fmtDec($brlPorCredito, 2) ?></strong>,
                gastar no máximo <strong>R$ <?= $fmtDec($custoAlvoCredito, 2) ?></strong> de LLM
                → lucro alvo <strong>R$ <?= $fmtDec($lucroAlvoCredito, 2) ?></strong>
                <?php if ($usdMaxCredito !== null): ?>
                (≈ <?= $fmtUsd($usdMaxCredito) ?>).
                <?php else: ?>.
                <?php endif; ?>
            </p>
        </div>
        <?php if ($dentroMeta !== null): ?>
        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold <?= $dentroMeta ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-800' ?>">
            <?= $dentroMeta ? 'Dentro da meta no período' : 'Acima do custo alvo no período' ?>
        </span>
        <?php endif; ?>
    </div>
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mt-4">
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">1 crédito ≈ quantos tokens?</p>
            <?php if ($tokensAlvo !== null): ?>
            <p class="text-2xl font-bold text-slate-900 mt-1"><?= $fmtInt($tokensAlvo) ?></p>
            <p class="text-xs text-slate-500 mt-1">Orçamento no mix in/out deste período (custo alvo R$ <?= $fmtDec($custoAlvoCredito, 2) ?>)</p>
            <?php else: ?>
            <p class="text-2xl font-bold text-slate-400 mt-1">—</p>
            <p class="text-xs text-slate-500 mt-1">Sem tokens/custo no período para estimar</p>
            <?php endif; ?>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Cobrança fracionada</p>
            <?php
            $creditosUso = (float) ($financeiro['creditos_equivalentes_uso'] ?? 0);
            $exemploTokens = $tokensAlvo !== null ? (int) max(1, round($tokensAlvo / 2)) : null;
            $exemploCreditos = ($exemploTokens !== null && $tokensAlvo > 0)
                ? \App\Core\CreditosDecimalHelper::creditosPorTokens($exemploTokens, (int) $tokensAlvo)
                : null;
            ?>
            <?php if ($creditosUso > 0): ?>
            <p class="text-2xl font-bold text-slate-900 mt-1"><?= $fmtTc($creditosUso) ?></p>
            <p class="text-xs text-slate-500 mt-1">
                Custo R$ ÷ R$ <?= $fmtDec($custoAlvoCredito, 2) ?>
                <?php if ($exemploCreditos !== null): ?>
                · ex.: <?= $fmtInt($exemploTokens) ?> tok ≈ TC$ <?= $fmtDec($exemploCreditos, 2) ?>
                <?php endif; ?>
            </p>
            <?php else: ?>
            <p class="text-2xl font-bold text-slate-400 mt-1">—</p>
            <p class="text-xs text-slate-500 mt-1">Sem custo LLM no período</p>
            <?php endif; ?>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Receita se cobrar pelo uso</p>
            <?php if ($creditosUso > 0): ?>
            <p class="text-2xl font-bold text-blue-700 mt-1"><?= $fmtBrl($financeiro['receita_ideal_brl'] ?? 0) ?></p>
            <p class="text-xs text-slate-500 mt-1">
                Lucro <?= $fmtBrl($financeiro['lucro_ideal_brl'] ?? 0) ?>
                (meta R$ <?= $fmtDec($lucroAlvoCredito, 2) ?> / crédito cheio)
            </p>
            <?php else: ?>
            <p class="text-2xl font-bold text-slate-400 mt-1">—</p>
            <p class="text-xs text-slate-500 mt-1">Sem base de custo</p>
            <?php endif; ?>
        </div>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <p class="text-[11px] uppercase tracking-wide text-slate-500">Custo real / crédito debitado</p>
            <?php if ($custoRealCredito !== null): ?>
            <p class="text-2xl font-bold <?= ($dentroMeta === false) ? 'text-amber-700' : 'text-slate-900' ?> mt-1"><?= $fmtBrl($custoRealCredito) ?></p>
            <p class="text-xs text-slate-500 mt-1">
                Meta ≤ R$ <?= $fmtDec($custoAlvoCredito, 2) ?>
                <?php if ($tokensReal !== null): ?>
                · <?= $fmtInt($tokensReal) ?> tok/crédito
                <?php endif; ?>
            </p>
            <?php else: ?>
            <p class="text-2xl font-bold text-slate-400 mt-1">—</p>
            <p class="text-xs text-slate-500 mt-1">Sem créditos consumidos no período</p>
            <?php endif; ?>
        </div>
    </div>
    <p class="text-xs text-slate-500 mt-4">
        Regra: usou metade dos tokens do orçamento → cobra ~0,5 crédito (R$ 0,10 de receita, ~R$ 0,05 de custo, ~R$ 0,05 de lucro).
        A Tudinha admin já debita assim, proporcional ao custo LLM da mensagem.
    </p>
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
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">R$</th>
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
                    <td class="px-4 py-3 text-sm text-right text-slate-700"><?= $fmtUsd($row['cost_usd'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-sm text-right text-emerald-700"><?= $fmtBrl($row['cost_brl'] ?? 0) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($porModelo)): ?>
                <tr><td colspan="7" class="px-4 py-10 text-center text-sm text-slate-500">Nenhum uso de LLM no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-2 gap-4 mb-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
            <h3 class="text-lg font-semibold text-slate-900">Por escola</h3>
            <span class="text-xs text-slate-500">Custo LLM · venda pelo uso (R$ <?= $fmtDec($brlPorCredito, 2) ?>/crédito)</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Custo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Venda</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Lucro</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($porEscola as $row): ?>
                    <?php
                    $custoEsc = (float) ($row['custo_brl'] ?? $row['cost_brl'] ?? 0);
                    $vendaEsc = (float) ($row['venda_brl'] ?? 0);
                    if ($vendaEsc <= 0 && $custoEsc > 0) {
                        // Fallback: venda = custo × (0,20 / 0,10) = 2× custo na meta atual.
                        $vendaEsc = round(
                            \App\Core\CreditosDecimalHelper::creditosPorCustoLlmBrl($custoEsc, $custoAlvoCredito)
                            * $brlPorCredito,
                            2
                        );
                    }
                    $lucroEsc = (float) ($row['lucro_brl'] ?? ($vendaEsc - $custoEsc));
                    $tcUso = (float) ($row['creditos_equivalentes_uso'] ?? 0);
                    $tcCobrado = (float) ($row['creditos_consumidos'] ?? 0);
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm">
                            <span class="font-medium text-slate-900"><?= htmlspecialchars($row['nome'] ?? '') ?></span>
                            <span class="block text-xs text-slate-400">
                                <?= htmlspecialchars($row['slug'] ?? '') ?> · <?= $fmtInt($row['requests'] ?? 0) ?> req. · <?= $fmtUsd($row['cost_usd'] ?? 0) ?>
                            </span>
                            <span class="block text-xs text-slate-400 mt-0.5">
                                Uso ≈ <?= $fmtTc($tcUso) ?>
                                <?php if ($tcCobrado > 0): ?>
                                · debitado <?= $fmtTc($tcCobrado) ?>
                                <?php endif; ?>
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-slate-700">
                            <span class="font-medium"><?= $fmtBrl($custoEsc) ?></span>
                            <span class="block text-[11px] text-slate-400">gasto LLM</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-blue-700">
                            <span class="font-medium"><?= $fmtBrl($vendaEsc) ?></span>
                            <span class="block text-[11px] text-slate-400">cobrança pelo uso</span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right font-medium <?= $lucroEsc >= 0 ? 'text-emerald-700' : 'text-red-600' ?>">
                            <?= $fmtBrl($lucroEsc) ?>
                            <span class="block text-[11px] font-normal text-slate-400">venda − custo</span>
                        </td>
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
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Custo</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Venda</th>
                        <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Lucro</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($porTipo as $row): ?>
                    <?php
                    $custoTipo = (float) ($row['custo_brl'] ?? $row['cost_brl'] ?? 0);
                    $vendaTipo = (float) ($row['venda_brl'] ?? 0);
                    if ($vendaTipo <= 0 && $custoTipo > 0) {
                        $vendaTipo = round(
                            \App\Core\CreditosDecimalHelper::creditosPorCustoLlmBrl($custoTipo, $custoAlvoCredito)
                            * $brlPorCredito,
                            2
                        );
                    }
                    $lucroTipo = (float) ($row['lucro_brl'] ?? ($vendaTipo - $custoTipo));
                    ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-sm font-mono text-slate-800"><?= htmlspecialchars($row['usage_type'] ?? '') ?></td>
                        <td class="px-4 py-3 text-sm text-right text-slate-700"><?= $fmtInt($row['requests'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-slate-700"><?= $fmtInt($row['total_tokens'] ?? 0) ?></td>
                        <td class="px-4 py-3 text-sm text-right text-slate-700">
                            <span class="font-medium"><?= $fmtBrl($custoTipo) ?></span>
                            <span class="block text-[11px] text-slate-400"><?= $fmtUsd($row['cost_usd'] ?? 0) ?></span>
                        </td>
                        <td class="px-4 py-3 text-sm text-right text-blue-700 font-medium"><?= $fmtBrl($vendaTipo) ?></td>
                        <td class="px-4 py-3 text-sm text-right font-medium <?= $lucroTipo >= 0 ? 'text-emerald-700' : 'text-red-600' ?>"><?= $fmtBrl($lucroTipo) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($porTipo)): ?>
                    <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Sem dados no período.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-8">
    <div class="px-6 py-4 border-b border-slate-200 flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-slate-900">Por dia</h3>
        <span class="text-xs text-slate-500">Custo · venda · lucro · clique no dia para detalhe</span>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Req.</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Tokens</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Custo</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Venda</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Lucro</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach (array_reverse($porDia) as $row): ?>
                <?php
                $custoDia = (float) ($row['custo_brl'] ?? $row['cost_brl'] ?? 0);
                $vendaDia = (float) ($row['venda_brl'] ?? 0);
                if ($vendaDia <= 0 && $custoDia > 0) {
                    $vendaDia = round(
                        \App\Core\CreditosDecimalHelper::creditosPorCustoLlmBrl($custoDia, $custoAlvoCredito)
                        * $brlPorCredito,
                        2
                    );
                }
                $lucroDia = (float) ($row['lucro_brl'] ?? ($vendaDia - $custoDia));
                ?>
                <tr class="hover:bg-blue-50 cursor-pointer transition-colors"
                    tabindex="0"
                    role="button"
                    data-dia="<?= htmlspecialchars($row['date'] ?? '') ?>"
                    onclick="abrirDetalheDia(this.getAttribute('data-dia'))"
                    onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();abrirDetalheDia(this.getAttribute('data-dia'));}">
                    <td class="px-4 py-2.5 text-sm text-blue-700 font-medium">
                        <?= htmlspecialchars($fmtData($row['date'] ?? '')) ?>
                        <i class="fa-solid fa-chevron-right ml-1 text-[10px] text-slate-400"></i>
                    </td>
                    <td class="px-4 py-2.5 text-sm text-right text-slate-700"><?= $fmtInt($row['requests'] ?? 0) ?></td>
                    <td class="px-4 py-2.5 text-sm text-right text-slate-700">
                        <span class="font-medium"><?= $fmtInt($row['total_tokens'] ?? 0) ?></span>
                        <span class="block text-[11px] text-slate-400">
                            in <?= $fmtInt($row['prompt_tokens'] ?? 0) ?> · out <?= $fmtInt($row['completion_tokens'] ?? 0) ?>
                        </span>
                    </td>
                    <td class="px-4 py-2.5 text-sm text-right text-slate-700">
                        <span class="font-medium"><?= $fmtBrl($custoDia) ?></span>
                        <span class="block text-[11px] text-slate-400"><?= $fmtUsd($row['cost_usd'] ?? 0) ?></span>
                    </td>
                    <td class="px-4 py-2.5 text-sm text-right text-blue-700 font-medium"><?= $fmtBrl($vendaDia) ?></td>
                    <td class="px-4 py-2.5 text-sm text-right font-medium <?= $lucroDia >= 0 ? 'text-emerald-700' : 'text-red-600' ?>"><?= $fmtBrl($lucroDia) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($porDia)): ?>
                <tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Sem dados no período.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="diaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[80] hidden" onclick="fecharDetalheDia()"></div>
<aside id="diaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-2xl bg-white shadow-2xl z-[90] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <div>
            <h3 id="diaDrawerTitle" class="text-lg font-semibold text-slate-900">Detalhe do dia</h3>
            <p id="diaDrawerSub" class="text-xs text-slate-500 mt-0.5"></p>
        </div>
        <button type="button" onclick="fecharDetalheDia()" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div id="diaDrawerBody" class="flex-1 overflow-y-auto px-6 py-5">
        <p class="text-sm text-slate-500">Carregando…</p>
    </div>
</aside>

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
            <div>
                <label for="filtro_cotacao" class="block text-sm font-medium text-slate-700 mb-1.5">Cotação USD → BRL</label>
                <input type="number" step="0.0001" min="0.01" id="filtro_cotacao" name="cotacao"
                       value="<?= htmlspecialchars(number_format($cotacao, 4, '.', '')) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-xs text-slate-500 mt-1.5">
                    Usada para converter custo OpenAI em reais. Lucro = (TudiCoins × R$ <?= $fmtDec($brlPorCredito, 2) ?>) − custo R$.
                </p>
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
var LLM_CUSTOS_ESCOLA = <?= (int) $filtro_escola ?>;
var LLM_CUSTOS_DIA_URL = <?= json_encode(URL . '/master/llm-custos/dia', JSON_UNESCAPED_SLASHES) ?>;

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

function fmtIntJs(n) {
    return Number(n || 0).toLocaleString('pt-BR');
}
function fmtUsdJs(n) {
    return '$' + Number(n || 0).toFixed(4);
}
function fmtBrlJs(n) {
    return 'R$ ' + Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
var LLM_CUSTOS_COTACAO = <?= json_encode((float) $cotacao) ?>;
var LLM_CUSTOS_BRL_CREDITO = <?= json_encode((float) $brlPorCredito) ?>;
var LLM_CUSTOS_CUSTO_ALVO = <?= json_encode((float) $custoAlvoCredito) ?>;
function fmtTcJs(n) {
    return 'TC$ ' + Number(n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 4 });
}
function fmtDataJs(ymd) {
    if (!ymd || !/^\d{4}-\d{2}-\d{2}$/.test(ymd)) return ymd || '';
    var p = ymd.split('-');
    return p[2] + '/' + p[1] + '/' + p[0];
}
function escHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}

function abrirDetalheDia(data) {
    if (!data) return;
    closeFilterDrawer();
    document.getElementById('diaDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('diaDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    document.getElementById('diaDrawerTitle').textContent = 'Detalhe · ' + fmtDataJs(data);
    document.getElementById('diaDrawerSub').textContent = 'Chats Tudinha e chamadas LLM';
    document.getElementById('diaDrawerBody').innerHTML = '<p class="text-sm text-slate-500">Carregando…</p>';

    var url = LLM_CUSTOS_DIA_URL
        + '?data=' + encodeURIComponent(data)
        + '&escola_id=' + encodeURIComponent(LLM_CUSTOS_ESCOLA)
        + '&cotacao=' + encodeURIComponent(LLM_CUSTOS_COTACAO);
    fetch(url, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
        .then(function(r) { return r.json(); })
        .then(function(payload) {
            if (!payload || !payload.success) {
                document.getElementById('diaDrawerBody').innerHTML =
                    '<p class="text-sm text-red-600">' + escHtml((payload && payload.error) || 'Falha ao carregar.') + '</p>';
                return;
            }
            document.getElementById('diaDrawerBody').innerHTML = renderDetalheDia(payload);
        })
        .catch(function() {
            document.getElementById('diaDrawerBody').innerHTML =
                '<p class="text-sm text-red-600">Erro de rede ao carregar o detalhe.</p>';
        });
}

function fecharDetalheDia() {
    document.getElementById('diaDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('diaDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function renderDetalheDia(data) {
    var totais = data.totais || {};
    var conversas = data.conversas || [];
    var chamadas = data.chamadas || [];
    var html = '';

    var custoBrlDia = Number(totais.custo_brl != null ? totais.custo_brl : (Number(totais.cost_usd || 0) * Number(LLM_CUSTOS_COTACAO || 0)));
    var vendaBrlDia = Number(totais.venda_brl || 0);
    var lucroBrlDia = Number(totais.lucro_brl != null ? totais.lucro_brl : (vendaBrlDia - custoBrlDia));
    var tcUsoDia = Number(totais.creditos_equivalentes_uso || 0);
    html += '<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">';
    html += '<div class="rounded-lg border border-slate-200 p-3"><p class="text-[11px] uppercase text-slate-500">Conversas</p><p class="text-lg font-semibold text-slate-900">' + fmtIntJs(totais.conversas) + '</p></div>';
    html += '<div class="rounded-lg border border-slate-200 p-3"><p class="text-[11px] uppercase text-slate-500">Msgs IA</p><p class="text-lg font-semibold text-slate-900">' + fmtIntJs(totais.mensagens) + '</p></div>';
    html += '<div class="rounded-lg border border-slate-200 p-3"><p class="text-[11px] uppercase text-slate-500">Chamadas</p><p class="text-lg font-semibold text-slate-900">' + fmtIntJs(totais.chamadas) + '</p></div>';
    html += '<div class="rounded-lg border border-slate-200 p-3"><p class="text-[11px] uppercase text-slate-500">Custo</p><p class="text-lg font-semibold text-slate-900">' + fmtBrlJs(custoBrlDia) + '</p><p class="text-xs text-slate-500">' + fmtUsdJs(totais.cost_usd) + '</p></div>';
    html += '<div class="rounded-lg border border-slate-200 p-3"><p class="text-[11px] uppercase text-slate-500">Venda</p><p class="text-lg font-semibold text-blue-700">' + fmtBrlJs(vendaBrlDia) + '</p><p class="text-xs text-slate-500">' + fmtTcJs(tcUsoDia) + ' pelo uso</p></div>';
    html += '<div class="rounded-lg border border-slate-200 p-3"><p class="text-[11px] uppercase text-slate-500">Lucro</p><p class="text-lg font-semibold ' + (lucroBrlDia >= 0 ? 'text-emerald-700' : 'text-red-600') + '">' + fmtBrlJs(lucroBrlDia) + '</p><p class="text-xs text-slate-500">venda − custo</p></div>';
    html += '</div>';
    html += '<p class="text-xs text-slate-500 mb-4">Meta: custo ≤ R$ ' + Number(LLM_CUSTOS_CUSTO_ALVO || 0.1).toFixed(2).replace('.', ',') +
        ' · venda R$ ' + Number(LLM_CUSTOS_BRL_CREDITO || 0.2).toFixed(2).replace('.', ',') + ' por crédito cheio (cobrança fracionada pelo uso).</p>';

    html += '<h4 class="text-sm font-semibold text-slate-900 mb-2">Chats Tudinha</h4>';
    if (!conversas.length) {
        html += '<p class="text-sm text-slate-500 mb-6">Nenhum chat com tokens neste dia' +
            (totais.mensagens === 0 ? ' (mensagens futuras passam a gravar tokens após a migration).' : '.') +
            '</p>';
    } else {
        html += '<div class="space-y-3 mb-6">';
        conversas.forEach(function(c) {
            html += '<div class="border border-slate-200 rounded-lg p-3">';
            html += '<div class="flex items-start justify-between gap-3">';
            html += '<div><p class="text-sm font-medium text-slate-900">' + escHtml(c.titulo || 'Conversa') + '</p>';
            html += '<p class="text-xs text-slate-500 mt-0.5">' + escHtml(c.escola_nome || '') +
                (c.usuario_nome ? ' · ' + escHtml(c.usuario_nome) : '') +
                (c.aluno_nome ? ' · aluno: ' + escHtml(c.aluno_nome) : '') +
                '</p></div>';
            html += '<div class="text-right text-xs whitespace-nowrap">' +
                '<span class="text-blue-700">' + fmtIntJs(c.prompt_tokens) + ' in</span> · ' +
                '<span class="text-violet-700">' + fmtIntJs(c.completion_tokens) + ' out</span><br>' +
                '<span class="font-medium text-slate-800">' + fmtIntJs(c.total_tokens) + ' total</span></div>';
            html += '</div>';
            var msgs = c.mensagens || [];
            if (msgs.length) {
                html += '<ul class="mt-2 space-y-1.5 border-t border-slate-100 pt-2">';
                msgs.forEach(function(m) {
                    html += '<li class="text-xs text-slate-600"><span class="text-slate-400">' +
                        escHtml((m.created_at || '').substring(11, 19)) + '</span> ' +
                        escHtml(m.preview || '') +
                        ' <span class="text-slate-400">(' + fmtIntJs(m.total_tokens) + ' tok' +
                        (m.modelo ? ', ' + escHtml(m.modelo) : '') + ')</span></li>';
                });
                html += '</ul>';
            }
            html += '</div>';
        });
        html += '</div>';
    }

    html += '<h4 class="text-sm font-semibold text-slate-900 mb-2">Chamadas LLM (logs)</h4>';
    if (!chamadas.length) {
        html += '<p class="text-sm text-slate-500">Nenhuma chamada registrada neste dia.</p>';
    } else {
        html += '<div class="overflow-x-auto border border-slate-200 rounded-lg">';
        html += '<table class="min-w-full divide-y divide-gray-200 text-xs">';
        html += '<thead class="bg-slate-50"><tr>' +
            '<th class="px-3 py-2 text-left font-medium text-slate-500">Hora</th>' +
            '<th class="px-3 py-2 text-left font-medium text-slate-500">Escola</th>' +
            '<th class="px-3 py-2 text-left font-medium text-slate-500">Tipo</th>' +
            '<th class="px-3 py-2 text-left font-medium text-slate-500">Modelo</th>' +
            '<th class="px-3 py-2 text-right font-medium text-slate-500">In</th>' +
            '<th class="px-3 py-2 text-right font-medium text-slate-500">Out</th>' +
            '<th class="px-3 py-2 text-right font-medium text-slate-500">Custo</th>' +
            '<th class="px-3 py-2 text-right font-medium text-slate-500">Venda</th>' +
            '<th class="px-3 py-2 text-right font-medium text-slate-500">Lucro</th>' +
            '</tr></thead><tbody class="divide-y divide-gray-100">';
        chamadas.forEach(function(ch) {
            var chCusto = Number(ch.custo_brl != null ? ch.custo_brl : (Number(ch.cost_usd || 0) * Number(LLM_CUSTOS_COTACAO || 0)));
            var chVenda = Number(ch.venda_brl || 0);
            if (!chVenda && chCusto > 0) {
                var tc = chCusto / Number(LLM_CUSTOS_CUSTO_ALVO || 0.1);
                chVenda = tc * Number(LLM_CUSTOS_BRL_CREDITO || 0.2);
            }
            var chLucro = Number(ch.lucro_brl != null ? ch.lucro_brl : (chVenda - chCusto));
            html += '<tr class="hover:bg-slate-50">';
            html += '<td class="px-3 py-2 text-slate-700">' + escHtml((ch.created_at || '').substring(11, 19)) + '</td>';
            html += '<td class="px-3 py-2 text-slate-700">' + escHtml(ch.escola_nome || '') + '</td>';
            html += '<td class="px-3 py-2 font-mono text-slate-700">' + escHtml(ch.usage_type || '') + '</td>';
            html += '<td class="px-3 py-2 font-mono text-slate-700">' + escHtml(ch.model || '') + '</td>';
            html += '<td class="px-3 py-2 text-right text-blue-700">' + fmtIntJs(ch.prompt_tokens) + '</td>';
            html += '<td class="px-3 py-2 text-right text-violet-700">' + fmtIntJs(ch.completion_tokens) + '</td>';
            html += '<td class="px-3 py-2 text-right text-slate-700">' + fmtBrlJs(chCusto) + '<div class="text-[10px] text-slate-400">' + fmtUsdJs(ch.cost_usd) + '</div></td>';
            html += '<td class="px-3 py-2 text-right text-blue-700">' + fmtBrlJs(chVenda) + '</td>';
            html += '<td class="px-3 py-2 text-right ' + (chLucro >= 0 ? 'text-emerald-700' : 'text-red-600') + '">' + fmtBrlJs(chLucro) + '</td>';
            html += '</tr>';
        });
        html += '</tbody></table></div>';
    }

    if ((data.erros || []).length) {
        html += '<p class="mt-4 text-xs text-amber-700">Avisos: ' + escHtml((data.erros || []).slice(0, 3).join(' · ')) + '</p>';
    }
    return html;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharDetalheDia();
        closeFilterDrawer();
    }
});
</script>
