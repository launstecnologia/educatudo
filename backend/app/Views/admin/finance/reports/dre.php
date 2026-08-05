<?php
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$meses = ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$margemLiquida = $totReceita > 0 ? ($lucro / $totReceita * 100) : 0;
?>

<div class="mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900">DRE — Demonstração do Resultado</h2>
            <p class="text-sm text-gray-500"><?= $mes ? $meses[$mes] . '/' . $ano : 'Exercício ' . $ano ?></p>
        </div>
    </div>
    <a href="javascript:window.print()" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"><i class="fa-solid fa-print mr-2"></i> Imprimir</a>
</div>

<!-- Filtro período -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Ano</label>
            <select name="ano" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <?php for ($y = date('Y')+1; $y >= date('Y')-3; $y--): ?>
                <option value="<?= $y ?>" <?= $ano==$y?'selected':'' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Mês (opcional)</label>
            <select name="mes" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Exercício completo</option>
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $mes==$m?'selected':'' ?>><?= $meses[$m] ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-filter mr-2 text-gray-400"></i> Gerar</button>
    </form>
</div>

<!-- DRE estruturado -->
<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden print:shadow-none">
    <div class="px-8 py-5 border-b border-gray-100 bg-gray-50">
        <h3 class="text-base font-bold text-gray-900 uppercase tracking-wide">Demonstração do Resultado do Exercício</h3>
        <p class="text-sm text-gray-500 mt-0.5"><?= $mes ? $meses[$mes] . '/' . $ano : 'Exercício ' . $ano ?> · <?= date('d/m/Y', strtotime($ini)) ?> a <?= date('d/m/Y', strtotime($fim)) ?></p>
    </div>

    <div class="divide-y divide-gray-100">

        <!-- RECEITA BRUTA -->
        <div class="px-8 py-4 bg-green-50/40">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-bold text-green-700 uppercase tracking-wider">1. Receita Bruta</p>
                <p class="text-base font-bold text-green-700"><?= $brl($totReceita) ?></p>
            </div>
            <?php foreach ($receitas as $r): ?>
            <div class="flex items-center justify-between py-1 pl-4">
                <p class="text-sm text-gray-600"><?= htmlspecialchars(ucfirst($r['categoria'] ?? 'Outros')) ?></p>
                <p class="text-sm font-medium text-gray-800"><?= $brl($r['total']) ?></p>
            </div>
            <?php endforeach; ?>
            <?php if (empty($receitas)): ?><p class="text-sm text-gray-400 pl-4">Sem receitas no período.</p><?php endif; ?>
        </div>

        <!-- DEDUÇÕES -->
        <div class="px-8 py-3 flex items-center justify-between text-sm text-gray-500">
            <p class="pl-4">(-) Deduções e Impostos sobre Receita</p>
            <p class="text-gray-400">R$ 0,00</p>
        </div>

        <!-- RECEITA LÍQUIDA -->
        <div class="px-8 py-3 flex items-center justify-between bg-gray-50">
            <p class="text-sm font-semibold text-gray-700">= Receita Líquida</p>
            <p class="text-sm font-bold text-gray-900"><?= $brl($totReceita) ?></p>
        </div>

        <!-- DESPESAS -->
        <div class="px-8 py-4 bg-red-50/30">
            <div class="flex items-center justify-between mb-3">
                <p class="text-xs font-bold text-red-700 uppercase tracking-wider">2. Despesas Operacionais</p>
                <p class="text-base font-bold text-red-700">(<?= $brl($totDespesa) ?>)</p>
            </div>
            <?php foreach ($despesas as $d): ?>
            <div class="flex items-center justify-between py-1 pl-4">
                <div>
                    <?php if ($d['grupo']): ?><p class="text-xs text-gray-400"><?= htmlspecialchars($d['grupo']) ?></p><?php endif; ?>
                    <p class="text-sm text-gray-600"><?= htmlspecialchars($d['categoria'] ?? 'Outros') ?></p>
                </div>
                <p class="text-sm font-medium text-gray-800">(<?= $brl($d['total']) ?>)</p>
            </div>
            <?php endforeach; ?>
            <?php if (empty($despesas)): ?><p class="text-sm text-gray-400 pl-4">Sem despesas registradas no período.</p><?php endif; ?>
        </div>

        <!-- RESULTADO OPERACIONAL -->
        <div class="px-8 py-3 flex items-center justify-between bg-gray-50">
            <p class="text-sm font-semibold text-gray-700">= Resultado Operacional</p>
            <p class="text-sm font-bold <?= $lucro >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $lucro >= 0 ? $brl($lucro) : '(' . $brl(abs($lucro)) . ')' ?></p>
        </div>

        <!-- RESULTADO LÍQUIDO -->
        <div class="px-8 py-5 flex items-center justify-between <?= $lucro >= 0 ? 'bg-green-50' : 'bg-red-50' ?> border-t-2 <?= $lucro >= 0 ? 'border-green-200' : 'border-red-200' ?>">
            <div>
                <p class="text-base font-bold <?= $lucro >= 0 ? 'text-green-800' : 'text-red-800' ?>">= <?= $lucro >= 0 ? 'Lucro' : 'Prejuízo' ?> Líquido do Exercício</p>
                <p class="text-xs text-gray-500 mt-0.5">Margem líquida: <?= number_format(abs($margemLiquida), 1, ',', '.') ?>%</p>
            </div>
            <p class="text-2xl font-bold <?= $lucro >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $lucro >= 0 ? $brl($lucro) : '(' . $brl(abs($lucro)) . ')' ?></p>
        </div>

    </div>
</div>

<?php if ($aReceber > 0): ?>
<div class="mt-4 bg-blue-50 rounded-xl border border-blue-200 px-5 py-4 flex items-center justify-between">
    <div>
        <p class="text-sm font-semibold text-blue-700"><i class="fa-solid fa-clock mr-2"></i> Receitas a Receber no Período</p>
        <p class="text-xs text-blue-500 mt-0.5">Parcelas pendentes com vencimento no período (não realizadas ainda)</p>
    </div>
    <p class="text-lg font-bold text-blue-700"><?= $brl($aReceber) ?></p>
</div>
<?php endif; ?>
