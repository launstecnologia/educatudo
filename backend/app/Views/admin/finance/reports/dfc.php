<?php $brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.'); $meses=['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']; ?>

<div class="mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors"><i class="fa-solid fa-arrow-left"></i></a>
        <div><h2 class="text-2xl font-bold text-gray-900">DFC — Demonstração do Fluxo de Caixa</h2><p class="text-sm text-gray-500">Método Direto · <?= $mes ? $meses[$mes] . '/' . $ano : 'Exercício ' . $ano ?></p></div>
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

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-8 py-5 border-b border-gray-100 bg-gray-50">
        <h3 class="text-base font-bold text-gray-900 uppercase tracking-wide">Demonstração do Fluxo de Caixa — Método Direto</h3>
        <p class="text-sm text-gray-500 mt-0.5"><?= $mes ? $meses[$mes] . '/' . $ano : 'Exercício ' . $ano ?></p>
    </div>
    <div class="divide-y divide-gray-100">

        <!-- Atividades Operacionais -->
        <div class="px-8 py-4">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">I — Atividades Operacionais</p>

            <div class="flex justify-between py-2 pl-4 text-green-700">
                <p class="text-sm">Recebimentos de clientes (mensalidades e cobranças)</p>
                <p class="text-sm font-semibold">+ <?= $brl($recebimentos) ?></p>
            </div>

            <?php foreach ($despPorGrupo as $g): ?>
            <div class="flex justify-between py-1.5 pl-4 text-red-600">
                <p class="text-sm">(<?= htmlspecialchars($g['grupo']) ?>)</p>
                <p class="text-sm font-medium">- <?= $brl($g['total']) ?></p>
            </div>
            <?php endforeach; ?>

            <?php if (empty($despPorGrupo)): ?>
            <div class="flex justify-between py-1.5 pl-4 text-red-600">
                <p class="text-sm">(Pagamentos a fornecedores)</p>
                <p class="text-sm font-medium">- <?= $brl($pagamentos) ?></p>
            </div>
            <?php endif; ?>

            <div class="flex justify-between py-3 pl-4 mt-2 border-t border-gray-100 font-semibold <?= $fcOperacional >= 0 ? 'text-blue-700' : 'text-red-700' ?>">
                <p class="text-sm">= Caixa Líquido das Atividades Operacionais</p>
                <p class="text-sm"><?= $fcOperacional >= 0 ? '+ ' . $brl($fcOperacional) : '- ' . $brl(abs($fcOperacional)) ?></p>
            </div>
        </div>

        <!-- Atividades de Investimento -->
        <div class="px-8 py-4 bg-gray-50/50">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">II — Atividades de Investimento</p>
            <div class="flex justify-between py-1.5 pl-4 text-gray-400 text-sm"><p>Sem movimentações registradas</p><p>R$ 0,00</p></div>
            <div class="flex justify-between py-3 pl-4 mt-2 border-t border-gray-100 font-semibold text-gray-600">
                <p class="text-sm">= Caixa Líquido das Atividades de Investimento</p><p class="text-sm">R$ 0,00</p>
            </div>
        </div>

        <!-- Atividades de Financiamento -->
        <div class="px-8 py-4">
            <p class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-3">III — Atividades de Financiamento</p>
            <div class="flex justify-between py-1.5 pl-4 text-gray-400 text-sm"><p>Sem movimentações registradas</p><p>R$ 0,00</p></div>
            <div class="flex justify-between py-3 pl-4 mt-2 border-t border-gray-100 font-semibold text-gray-600">
                <p class="text-sm">= Caixa Líquido das Atividades de Financiamento</p><p class="text-sm">R$ 0,00</p>
            </div>
        </div>

        <!-- Resultado Final -->
        <div class="px-8 py-5 flex items-center justify-between <?= $fcOperacional >= 0 ? 'bg-green-50' : 'bg-red-50' ?> border-t-2 <?= $fcOperacional >= 0 ? 'border-green-200' : 'border-red-200' ?>">
            <p class="text-base font-bold <?= $fcOperacional >= 0 ? 'text-green-800' : 'text-red-800' ?>">= Variação Líquida de Caixa no Período</p>
            <p class="text-2xl font-bold <?= $fcOperacional >= 0 ? 'text-green-700' : 'text-red-700' ?>"><?= $fcOperacional >= 0 ? $brl($fcOperacional) : '(' . $brl(abs($fcOperacional)) . ')' ?></p>
        </div>

    </div>
</div>
<p class="text-xs text-gray-400 mt-4 text-center">* Atividades de investimento e financiamento serão preenchidas conforme os dados forem registrados no sistema.</p>
