<?php
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$dt  = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$meses = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
?>

<!-- Header -->
<div class="mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Fluxo de Caixa</h2>
            <p class="text-sm text-gray-500">Entradas e saídas por período</p>
        </div>
    </div>
    <a href="javascript:window.print()" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-print mr-2"></i> Imprimir
    </a>
</div>

<!-- Filtros -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Modo</label>
            <select name="modo" onchange="this.form.submit()" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="mensal" <?= $modo==='mensal'?'selected':'' ?>>Mensal</option>
                <option value="anual"  <?= $modo==='anual' ?'selected':'' ?>>Anual</option>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Ano</label>
            <select name="ano" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <?php for ($y = date('Y')+1; $y >= date('Y')-3; $y--): ?>
                <option value="<?= $y ?>" <?= $ano==$y?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <?php if ($modo === 'mensal'): ?>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Mês</label>
            <select name="mes" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <?php for ($m = 1; $m <= 12; $m++): ?>
                <option value="<?= $m ?>" <?= $mes==$m?'selected':'' ?>><?= $meses[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <?php endif; ?>
        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-filter mr-2 text-gray-400"></i> Filtrar
        </button>
    </form>
</div>

<!-- KPIs -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-green-50 rounded-xl border border-green-200 shadow-sm p-5">
        <p class="text-xs font-semibold text-green-600 uppercase mb-1">Total Entradas</p>
        <p class="text-2xl font-bold text-green-700"><?= $brl($totEntrada) ?></p>
        <p class="text-xs text-green-500 mt-1">Pagamentos recebidos</p>
    </div>
    <div class="bg-red-50 rounded-xl border border-red-200 shadow-sm p-5">
        <p class="text-xs font-semibold text-red-600 uppercase mb-1">Total Saídas</p>
        <p class="text-2xl font-bold text-red-700"><?= $brl($totSaida) ?></p>
        <p class="text-xs text-red-500 mt-1">Contas a pagar pagas</p>
    </div>
    <div class="<?= $totLiquido >= 0 ? 'bg-blue-50 border-blue-200' : 'bg-amber-50 border-amber-200' ?> rounded-xl border shadow-sm p-5">
        <p class="text-xs font-semibold <?= $totLiquido >= 0 ? 'text-blue-600' : 'text-amber-600' ?> uppercase mb-1">Saldo Líquido</p>
        <p class="text-2xl font-bold <?= $totLiquido >= 0 ? 'text-blue-700' : 'text-amber-700' ?>"><?= $brl(abs($totLiquido)) ?></p>
        <p class="text-xs <?= $totLiquido >= 0 ? 'text-blue-500' : 'text-amber-500' ?> mt-1"><?= $totLiquido >= 0 ? 'Superávit' : 'Déficit' ?></p>
    </div>
</div>

<?php if ($modo === 'anual' && !empty($porMes)): ?>
<!-- Visão mensal anual -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700">Resumo por Mês — <?= $ano ?></h3>
    </div>
    <table class="min-w-full divide-y divide-gray-100">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Mês</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Entradas</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Saídas</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Líquido</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($porMes as $ym => $row): ?>
            <?php $parts = explode('-', $ym); ?>
            <tr class="hover:bg-gray-50">
                <td class="px-5 py-3 text-sm font-medium text-gray-800"><?= $meses[(int)$parts[1]] ?> <?= $parts[0] ?></td>
                <td class="px-5 py-3 text-sm text-right text-green-600 font-semibold"><?= $brl($row['entrada']) ?></td>
                <td class="px-5 py-3 text-sm text-right text-red-600 font-semibold"><?= $brl($row['saida']) ?></td>
                <td class="px-5 py-3 text-sm text-right font-bold <?= $row['liquido'] >= 0 ? 'text-blue-700' : 'text-amber-700' ?>"><?= $brl($row['liquido']) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- Detalhamento diário -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-3 border-b border-gray-100">
        <h3 class="text-sm font-semibold text-gray-700">Movimentações — <?= $dt($dataInicio) ?> a <?= $dt($dataFim) ?></h3>
    </div>
    <?php if (empty($dias)): ?>
    <div class="px-6 py-12 text-center text-gray-400">
        <i class="fa-solid fa-chart-line text-4xl mb-4 block text-gray-200"></i>
        <p>Nenhuma movimentação no período.</p>
        <p class="text-sm mt-1">Registre pagamentos recebidos ou contas a pagar pagas.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Data</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-green-600 uppercase">Entradas</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-red-600 uppercase">Saídas</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Líquido Dia</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Saldo Acumulado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($dias as $d): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-5 py-3 text-sm text-gray-700 font-medium"><?= $dt($d['data']) ?></td>
                    <td class="px-5 py-3 text-sm text-right <?= $d['entrada'] > 0 ? 'text-green-600 font-semibold' : 'text-gray-300' ?>"><?= $d['entrada'] > 0 ? $brl($d['entrada']) : '—' ?></td>
                    <td class="px-5 py-3 text-sm text-right <?= $d['saida'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-300' ?>"><?= $d['saida'] > 0 ? $brl($d['saida']) : '—' ?></td>
                    <td class="px-5 py-3 text-sm text-right font-medium <?= $d['liquido'] >= 0 ? 'text-blue-600' : 'text-amber-600' ?>"><?= $brl($d['liquido']) ?></td>
                    <td class="px-5 py-3 text-sm text-right font-bold <?= $d['saldo'] >= 0 ? 'text-gray-800' : 'text-red-700' ?>"><?= $brl($d['saldo']) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
