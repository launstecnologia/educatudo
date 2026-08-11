<?php
$totalJornadas = $total_jornadas ?? 0;
$totalConcluidas = $total_concluidas ?? 0;
$totalPendentes = $total_pendentes ?? 0;
$pctConcluidas = $pct_concluidas ?? 0;
$geralTotal = $geral_total ?? 0;
$geralCorretas = $geral_corretas ?? 0;
$geralErros = $geral_erros ?? 0;
$geralPct = $geral_pct ?? 0;
$porMateria = $por_materia ?? [];
$porJornada = $por_jornada ?? [];
?>

<div class="mb-6">
    <a href="<?= URL ?>/dashboard" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-cyan-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Voltar ao Dashboard
    </a>
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 flex items-center gap-2">
        <span class="w-10 h-10 bg-cyan-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-cyan-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/></svg>
        </span>
        Desempenho em Jornadas
    </h1>
    <p class="text-sm text-gray-500 mt-1">Visão geral do seu progresso em todas as jornadas</p>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-5 border-t-4 border-cyan-500">
        <p class="text-xs font-medium text-gray-500 uppercase">Total de Jornadas</p>
        <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1"><?= $totalJornadas ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-5 border-t-4 border-green-500">
        <p class="text-xs font-medium text-gray-500 uppercase">Concluídas</p>
        <p class="text-2xl sm:text-3xl font-bold text-green-600 mt-1"><?= $totalConcluidas ?></p>
        <p class="text-xs text-gray-500 mt-1"><?= $pctConcluidas ?>% do total</p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-5 border-t-4 border-amber-500">
        <p class="text-xs font-medium text-gray-500 uppercase">Pendentes</p>
        <p class="text-2xl sm:text-3xl font-bold text-amber-600 mt-1"><?= $totalPendentes ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-5 border-t-4 border-indigo-500">
        <p class="text-xs font-medium text-gray-500 uppercase">Taxa de Acertos</p>
        <p class="text-2xl sm:text-3xl font-bold text-indigo-600 mt-1"><?= $geralPct ?>%</p>
        <p class="text-xs text-gray-500 mt-1"><?= $geralCorretas ?> de <?= $geralTotal ?> questões</p>
    </div>
</div>

<!-- Acertos vs Erros Geral -->
<div class="bg-white rounded-xl shadow-md p-5 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Acertos e Erros - Geral</h2>
    <div class="flex flex-col sm:flex-row items-center gap-6">
        <div class="flex-shrink-0 w-32 h-32 relative">
            <?php $pctErros = $geralTotal > 0 ? 100 - $geralPct : 0; $dashCorretas = 2 * M_PI * 56 * ($geralPct / 100); $dashTotal = 2 * M_PI * 56; ?>
            <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 128 128">
                <circle cx="64" cy="64" r="56" fill="none" stroke="#f3f4f6" stroke-width="12"/>
                <circle cx="64" cy="64" r="56" fill="none" stroke="#06b6d4" stroke-width="12"
                        stroke-dasharray="<?= round($dashCorretas, 2) ?> <?= round($dashTotal, 2) ?>"
                        stroke-linecap="round"/>
            </svg>
            <div class="absolute inset-0 flex items-center justify-center">
                <span class="text-xl font-bold text-gray-900"><?= $geralPct ?>%</span>
            </div>
        </div>
        <div class="flex-1 grid grid-cols-2 gap-4 w-full">
            <div class="bg-green-50 rounded-xl p-4 text-center">
                <p class="text-3xl font-bold text-green-600"><?= $geralCorretas ?></p>
                <p class="text-sm text-green-700 font-medium mt-1">Acertos</p>
            </div>
            <div class="bg-red-50 rounded-xl p-4 text-center">
                <p class="text-3xl font-bold text-red-500"><?= $geralErros ?></p>
                <p class="text-sm text-red-600 font-medium mt-1">Erros</p>
            </div>
        </div>
    </div>
</div>

<!-- Desempenho por Matéria -->
<?php if (!empty($porMateria)): ?>
<div class="bg-white rounded-xl shadow-md p-5 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Desempenho por Matéria</h2>
    <div class="space-y-4">
        <?php foreach ($porMateria as $mat): ?>
        <div class="border border-gray-100 rounded-lg p-4">
            <div class="flex items-center justify-between mb-2">
                <span class="font-semibold text-gray-800"><?= htmlspecialchars($mat['materia_nome']) ?></span>
                <span class="text-sm font-bold <?= $mat['percentual'] >= 70 ? 'text-green-600' : ($mat['percentual'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $mat['percentual'] ?>%</span>
            </div>
            <div class="w-full bg-gray-100 rounded-full h-2.5 mb-2 overflow-hidden">
                <div class="h-full rounded-full transition-all duration-500 <?= $mat['percentual'] >= 70 ? 'bg-green-500' : ($mat['percentual'] >= 50 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width: <?= min($mat['percentual'], 100) ?>%"></div>
            </div>
            <div class="flex gap-4 text-xs text-gray-600">
                <span><?= $mat['total'] ?> questões</span>
                <span class="text-green-600 font-medium"><?= $mat['corretas'] ?> acertos</span>
                <span class="text-red-500 font-medium"><?= $mat['erros'] ?> erros</span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Detalhamento por Jornada -->
<?php if (!empty($porJornada)): ?>
<div class="bg-white rounded-xl shadow-md p-5 sm:p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Detalhamento por Jornada</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-600 uppercase">Jornada</th>
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-600 uppercase">Matéria</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">Status</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">Questões</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">Acertos</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">Erros</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">%</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($porJornada as $j): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 max-w-xs truncate"><?= htmlspecialchars($j['titulo']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($j['materia_nome'] ?? '-') ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($j['concluida']): ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Concluída</span>
                        <?php else: ?>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Em andamento</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-center"><?= $j['total'] ?></td>
                    <td class="px-4 py-3 text-sm text-green-600 font-medium text-center"><?= $j['corretas'] ?></td>
                    <td class="px-4 py-3 text-sm text-red-500 font-medium text-center"><?= $j['erros'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($j['total'] > 0): ?>
                        <span class="text-sm font-bold <?= $j['percentual'] >= 70 ? 'text-green-600' : ($j['percentual'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $j['percentual'] ?>%</span>
                        <?php else: ?>
                        <span class="text-sm text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
