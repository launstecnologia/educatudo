<?php
$totalProvas = $total_provas ?? 0;
$totalFinalizadas = $total_finalizadas ?? 0;
$mediaNotas = $media_notas ?? 0;
$geralTotal = $geral_total ?? 0;
$geralCorretas = $geral_corretas ?? 0;
$geralErros = $geral_erros ?? 0;
$geralPct = $geral_pct ?? 0;
$porMateria = $por_materia ?? [];
$porProva = $por_prova ?? [];
$notasPorMateria = $notas_por_materia ?? [];
?>

<div class="mb-6">
    <a href="<?= URL ?>/dashboard" class="inline-flex items-center gap-1 text-sm text-gray-500 hover:text-rose-600 transition-colors">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
        Voltar ao Dashboard
    </a>
    <h1 class="text-2xl sm:text-3xl font-bold text-gray-900 mt-2 flex items-center gap-2">
        <span class="w-10 h-10 bg-rose-100 rounded-xl flex items-center justify-center flex-shrink-0">
            <svg class="w-5 h-5 text-rose-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>
        </span>
        Desempenho em Provas
    </h1>
    <p class="text-sm text-gray-500 mt-1">Visão geral do seu desempenho em todas as provas realizadas</p>
</div>

<!-- KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 sm:gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-5 border-t-4 border-rose-500">
        <p class="text-xs font-medium text-gray-500 uppercase">Provas Realizadas</p>
        <p class="text-2xl sm:text-3xl font-bold text-gray-900 mt-1"><?= $totalProvas ?></p>
        <p class="text-xs text-gray-500 mt-1"><?= $totalFinalizadas ?> finalizadas</p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-5 border-t-4 border-green-500">
        <p class="text-xs font-medium text-gray-500 uppercase">Taxa de Acertos</p>
        <p class="text-2xl sm:text-3xl font-bold text-green-600 mt-1"><?= $geralPct ?>%</p>
        <p class="text-xs text-gray-500 mt-1"><?= $geralCorretas ?> de <?= $geralTotal ?> questões</p>
    </div>
    <div class="bg-white rounded-xl shadow-md p-4 sm:p-5 border-t-4 border-red-500">
        <p class="text-xs font-medium text-gray-500 uppercase">Erros</p>
        <p class="text-2xl sm:text-3xl font-bold text-red-500 mt-1"><?= $geralErros ?></p>
        <p class="text-xs text-gray-500 mt-1">de <?= $geralTotal ?> questões</p>
    </div>
</div>

<!-- Acertos vs Erros Geral -->
<div class="bg-white rounded-xl shadow-md p-5 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Acertos e Erros - Geral</h2>
    <div class="flex flex-col sm:flex-row items-center gap-6">
        <div class="flex-shrink-0 w-32 h-32 relative">
            <?php $dashCorretas = 2 * M_PI * 56 * ($geralPct / 100); $dashTotal = 2 * M_PI * 56; ?>
            <svg class="w-32 h-32 transform -rotate-90" viewBox="0 0 128 128">
                <circle cx="64" cy="64" r="56" fill="none" stroke="#f3f4f6" stroke-width="12"/>
                <circle cx="64" cy="64" r="56" fill="none" stroke="#f43f5e" stroke-width="12"
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

<!-- Acertos por Matéria -->
<?php if (!empty($porMateria)): ?>
<div class="bg-white rounded-xl shadow-md p-5 sm:p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Acertos por Matéria</h2>
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

<!-- Detalhamento por Prova -->
<?php if (!empty($porProva)): ?>
<div class="bg-white rounded-xl shadow-md p-5 sm:p-6">
    <h2 class="text-lg font-semibold text-gray-800 mb-4">Detalhamento por Prova</h2>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead>
                <tr class="bg-gray-50">
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-600 uppercase">Prova</th>
                    <th class="px-4 py-2.5 text-left text-xs font-medium text-gray-600 uppercase">Matéria</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">Questões</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">Acertos</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">Erros</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">%</th>
                    <th class="px-4 py-2.5 text-center text-xs font-medium text-gray-600 uppercase">Data</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($porProva as $p): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 text-sm font-medium text-gray-800 max-w-xs truncate"><?= htmlspecialchars($p['titulo']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($p['materia_nome']) ?></td>
                    <td class="px-4 py-3 text-sm text-gray-600 text-center"><?= $p['total'] ?></td>
                    <td class="px-4 py-3 text-sm text-green-600 font-medium text-center"><?= $p['corretas'] ?></td>
                    <td class="px-4 py-3 text-sm text-red-500 font-medium text-center"><?= $p['erros'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($p['total'] > 0): ?>
                        <span class="text-sm font-bold <?= $p['percentual'] >= 70 ? 'text-green-600' : ($p['percentual'] >= 50 ? 'text-amber-600' : 'text-red-500') ?>"><?= $p['percentual'] ?>%</span>
                        <?php else: ?>
                        <span class="text-sm text-gray-400">-</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-500 text-center">
                        <?= !empty($p['finalizado_em']) ? date('d/m/Y', strtotime($p['finalizado_em'])) : '-' ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
