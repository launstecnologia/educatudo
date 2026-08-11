<?php $brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.'); $meses=['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']; ?>

<div class="mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors"><i class="fa-solid fa-arrow-left"></i></a>
        <div><h2 class="text-2xl font-bold text-gray-900">Balanço Patrimonial</h2><p class="text-sm text-gray-500"><?= $mes ? $meses[$mes] . '/' . $ano : 'Exercício ' . $ano ?></p></div>
    </div>
    <a href="javascript:window.print()" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"><i class="fa-solid fa-print mr-2"></i> Imprimir</a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div><label class="block text-xs font-medium text-gray-500 mb-1">Ano</label><select name="ano" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"><?php for($y=date('Y')+1;$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$ano==$y?'selected':''?>><?=$y?></option><?php endfor; ?></select></div>
        <div><label class="block text-xs font-medium text-gray-500 mb-1">Mês</label><select name="mes" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"><option value="">Exercício</option><?php for($m=1;$m<=12;$m++): ?><option value="<?=$m?>" <?=$mes==$m?'selected':''?>><?=$meses[$m]?></option><?php endfor; ?></select></div>
        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-filter mr-2 text-gray-400"></i> Gerar</button>
    </form>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- ATIVO -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-blue-50 border-b border-blue-100">
            <h3 class="text-sm font-bold text-blue-800 uppercase tracking-wide">ATIVO</h3>
        </div>
        <div class="divide-y divide-gray-100">
            <div class="px-6 py-3 bg-blue-50/30">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Ativo Circulante</p>
                <div class="flex justify-between py-1"><p class="text-sm text-gray-700">Caixa e Equivalentes</p><p class="text-sm font-medium text-gray-900"><?= $brl($caixa) ?></p></div>
                <div class="flex justify-between py-1"><p class="text-sm text-gray-700">Contas a Receber (alunos)</p><p class="text-sm font-medium text-gray-900"><?= $brl($aReceber) ?></p></div>
            </div>
            <div class="px-6 py-3 bg-gray-50">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Ativo Não Circulante</p>
                <div class="flex justify-between py-1"><p class="text-sm text-gray-400 italic">Não registrado</p><p class="text-sm text-gray-400">R$ 0,00</p></div>
            </div>
            <div class="px-6 py-4 bg-blue-50 flex justify-between">
                <p class="text-sm font-bold text-blue-800">TOTAL DO ATIVO</p>
                <p class="text-base font-bold text-blue-800"><?= $brl($totalAtivo) ?></p>
            </div>
        </div>
    </div>

    <!-- PASSIVO + PL -->
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-6 py-4 bg-red-50 border-b border-red-100">
            <h3 class="text-sm font-bold text-red-800 uppercase tracking-wide">PASSIVO + PATRIMÔNIO LÍQUIDO</h3>
        </div>
        <div class="divide-y divide-gray-100">
            <div class="px-6 py-3 bg-red-50/30">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Passivo Circulante</p>
                <div class="flex justify-between py-1"><p class="text-sm text-gray-700">Contas a Pagar (fornecedores)</p><p class="text-sm font-medium text-gray-900"><?= $brl($aPagar) ?></p></div>
            </div>
            <div class="px-6 py-3">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Passivo Não Circulante</p>
                <div class="flex justify-between py-1"><p class="text-sm text-gray-400 italic">Não registrado</p><p class="text-sm text-gray-400">R$ 0,00</p></div>
            </div>
            <div class="px-6 py-3 <?= $patrimonioLiquido >= 0 ? 'bg-green-50/40' : 'bg-amber-50/40' ?>">
                <p class="text-xs font-semibold text-gray-500 uppercase mb-2">Patrimônio Líquido</p>
                <div class="flex justify-between py-1"><p class="text-sm text-gray-700">Resultado Acumulado</p><p class="text-sm font-medium <?= $patrimonioLiquido >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $brl($patrimonioLiquido) ?></p></div>
            </div>
            <div class="px-6 py-4 bg-red-50 flex justify-between">
                <p class="text-sm font-bold text-red-800">TOTAL PASSIVO + PL</p>
                <p class="text-base font-bold text-red-800"><?= $brl($aPagar + $patrimonioLiquido) ?></p>
            </div>
        </div>
    </div>
</div>

<p class="text-xs text-gray-400 mt-4 text-center">* Balanço simplificado com base nos dados registrados no sistema. Não substitui demonstrações contábeis formais.</p>
