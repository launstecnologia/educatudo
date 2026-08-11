<?php $brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.'); ?>

<div class="mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors"><i class="fa-solid fa-arrow-left"></i></a>
        <div><h2 class="text-2xl font-bold text-gray-900">DLPA — Lucros ou Prejuízos Acumulados</h2><p class="text-sm text-gray-500">Exercício <?= $ano ?></p></div>
    </div>
    <a href="javascript:window.print()" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"><i class="fa-solid fa-print mr-2"></i> Imprimir</a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div><label class="block text-xs font-medium text-gray-500 mb-1">Ano</label><select name="ano" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"><?php for($y=date('Y')+1;$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$ano==$y?'selected':''?>><?=$y?></option><?php endfor; ?></select></div>
        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-filter mr-2 text-gray-400"></i> Gerar</button>
    </form>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden max-w-2xl">
    <div class="px-8 py-5 bg-gray-50 border-b border-gray-100">
        <h3 class="text-base font-bold text-gray-900 uppercase tracking-wide">Demonstração de Lucros e Prejuízos Acumulados</h3>
        <p class="text-sm text-gray-500 mt-0.5">Exercício findo em 31/12/<?= $ano ?></p>
    </div>
    <div class="divide-y divide-gray-100 px-8">

        <div class="flex justify-between py-4">
            <p class="text-sm text-gray-700">Lucros/Prejuízos Acumulados — Início do Exercício (<?= $ano - 1 ?>)</p>
            <p class="text-sm font-semibold <?= $acumuladoAnterior >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $brl($acumuladoAnterior) ?></p>
        </div>

        <div class="py-4">
            <p class="text-sm font-semibold text-gray-700 mb-3">Resultado do Exercício <?= $ano ?></p>
            <div class="flex justify-between py-1.5 pl-4"><p class="text-sm text-gray-600">Receitas realizadas</p><p class="text-sm font-medium text-green-700">+ <?= $brl($receita) ?></p></div>
            <div class="flex justify-between py-1.5 pl-4"><p class="text-sm text-gray-600">Despesas realizadas</p><p class="text-sm font-medium text-red-700">- <?= $brl($despesa) ?></p></div>
            <div class="flex justify-between py-2.5 pl-4 mt-1 border-t border-gray-100">
                <p class="text-sm font-semibold text-gray-700"><?= $resultadoExercicio >= 0 ? 'Lucro' : 'Prejuízo' ?> do Exercício</p>
                <p class="text-sm font-bold <?= $resultadoExercicio >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $resultadoExercicio >= 0 ? '+ ' . $brl($resultadoExercicio) : '(' . $brl(abs($resultadoExercicio)) . ')' ?></p>
            </div>
        </div>

        <div class="py-3 flex justify-between text-gray-500 text-sm pl-4">
            <p>(-) Distribuição de dividendos/pro-labore</p>
            <p>R$ 0,00</p>
        </div>

        <div class="py-5 flex items-center justify-between <?= $acumuladoTotal >= 0 ? 'bg-green-50 -mx-8 px-8' : 'bg-red-50 -mx-8 px-8' ?>">
            <div>
                <p class="text-base font-bold <?= $acumuladoTotal >= 0 ? 'text-green-800' : 'text-red-800' ?>">= Lucros/Prejuízos Acumulados — Fim do Exercício</p>
                <p class="text-xs text-gray-500 mt-0.5">Saldo em 31/12/<?= $ano ?></p>
            </div>
            <p class="text-2xl font-bold <?= $acumuladoTotal >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $acumuladoTotal >= 0 ? $brl($acumuladoTotal) : '(' . $brl(abs($acumuladoTotal)) . ')' ?></p>
        </div>

    </div>
</div>
<p class="text-xs text-gray-400 mt-4 text-center">* Baseado nos dados registrados no sistema. Consulte seu contador para demonstrações formais.</p>
