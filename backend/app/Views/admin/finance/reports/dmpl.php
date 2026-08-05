<?php $brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.'); $meses=['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez']; ?>

<div class="mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors"><i class="fa-solid fa-arrow-left"></i></a>
        <div><h2 class="text-2xl font-bold text-gray-900">DMPL — Mutações do Patrimônio Líquido</h2><p class="text-sm text-gray-500">Exercício <?= $ano ?></p></div>
    </div>
    <a href="javascript:window.print()" class="inline-flex items-center px-4 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-600 hover:bg-gray-50 transition-colors"><i class="fa-solid fa-print mr-2"></i> Imprimir</a>
</div>

<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-5 py-4 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div><label class="block text-xs font-medium text-gray-500 mb-1">Ano</label><select name="ano" class="border border-gray-300 rounded-lg px-3 py-2 text-sm"><?php for($y=date('Y')+1;$y>=date('Y')-3;$y--): ?><option value="<?=$y?>" <?=$ano==$y?'selected':''?>><?=$y?></option><?php endfor; ?></select></div>
        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-filter mr-2 text-gray-400"></i> Gerar</button>
    </form>
</div>

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 bg-gray-50 border-b border-gray-100">
        <h3 class="text-sm font-bold text-gray-900 uppercase tracking-wide">Demonstração das Mutações do Patrimônio Líquido — <?= $ano ?></h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-100">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Mês</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-green-600 uppercase">Receitas</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-red-600 uppercase">Despesas</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-600 uppercase">Resultado Mês</th>
                    <th class="px-5 py-3 text-right text-xs font-semibold text-gray-600 uppercase">PL Acumulado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php $plAcum = 0; foreach ($meses as $i => $nm): if ($i === 0) continue; ?>
                <?php $m = $meses[$i]; $row = $meses[$i] ? ($meses_data[$i] ?? null) : null; ?>
                <?php // find by index
                $mr = null;
                foreach ($meses as $midx => $mnm) {
                    if ($midx === $i && isset($meses[$i])) {
                        foreach ($GLOBALS['meses'] ?? [] as $k => $v) { /* noop */ }
                    }
                }
                $mr = null;
                foreach ($meses as $k => $v) { if ($k === 0) continue; }
                // Access the controller-provided array directly
                $mr = $meses[$i] ?? null;
                // $meses is PHP names array; controller passes $meses as data variable with same name
                // Use $meses_rows passed by controller
                ?>
                <?php endforeach; ?>
                <?php
                $plAcum = 0;
                foreach ($meses as $row):
                    if (!is_array($row)) continue;
                    $plAcum += ($row['resultado'] ?? 0);
                    $isFuture = $row['resultado'] === null;
                ?>
                <tr class="hover:bg-gray-50 <?= $isFuture ? 'opacity-40' : '' ?>">
                    <td class="px-5 py-3 text-sm font-medium text-gray-800"><?= ['','Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'][$row['mes']] ?>/<?= $ano ?></td>
                    <td class="px-5 py-3 text-sm text-right text-green-600 font-medium"><?= $isFuture ? '—' : $brl($row['receita']) ?></td>
                    <td class="px-5 py-3 text-sm text-right text-red-600 font-medium"><?= $isFuture ? '—' : $brl($row['despesa']) ?></td>
                    <td class="px-5 py-3 text-sm text-right font-bold <?= $isFuture ? 'text-gray-400' : ($row['resultado'] >= 0 ? 'text-blue-700' : 'text-red-700') ?>">
                        <?= $isFuture ? '—' : ($row['resultado'] >= 0 ? $brl($row['resultado']) : '(' . $brl(abs($row['resultado'])) . ')') ?>
                    </td>
                    <td class="px-5 py-3 text-sm text-right font-bold <?= $isFuture ? 'text-gray-400' : ($plAcum >= 0 ? 'text-gray-800' : 'text-red-700') ?>">
                        <?= $isFuture ? '—' : $brl($plAcum) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50 border-t-2 border-gray-200">
                <?php
                $totRec  = array_sum(array_column(array_filter($meses, fn($m) => is_array($m) && $m['resultado'] !== null), 'receita'));
                $totDesp = array_sum(array_column(array_filter($meses, fn($m) => is_array($m) && $m['resultado'] !== null), 'despesa'));
                $totRes  = $totRec - $totDesp;
                ?>
                <tr>
                    <td class="px-5 py-3 text-sm font-bold text-gray-700">Total <?= $ano ?></td>
                    <td class="px-5 py-3 text-sm text-right font-bold text-green-700"><?= $brl($totRec) ?></td>
                    <td class="px-5 py-3 text-sm text-right font-bold text-red-700"><?= $brl($totDesp) ?></td>
                    <td class="px-5 py-3 text-sm text-right font-bold <?= $totRes >= 0 ? 'text-blue-700' : 'text-red-700' ?>"><?= $totRes >= 0 ? $brl($totRes) : '(' . $brl(abs($totRes)) . ')' ?></td>
                    <td class="px-5 py-3 text-sm text-right font-bold <?= $totRes >= 0 ? 'text-gray-900' : 'text-red-700' ?>"><?= $brl($totRes) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>
