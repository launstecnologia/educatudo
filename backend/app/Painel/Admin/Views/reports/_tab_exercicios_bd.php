<?php
$reports_filter_tab = 'exercicios-bd';
$reports_filter_jornadas_extended = false;
require __DIR__ . '/_reports_filters_form.php';
?>
<!-- Estatísticas de Exercícios Banco de Dados -->
<div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
    <div class="bg-orange-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Exercícios Completados</p>
                <p class="text-3xl font-bold text-orange-600"><?= number_format($exercises_stats['total_execucoes']) ?></p>
            </div>
            <div class="bg-orange-100 rounded-full p-3">
                <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    
    <div class="bg-teal-50 rounded-lg p-4">
        <div class="flex justify-between items-center">
            <div>
                <p class="text-sm text-gray-600">Média de Acertos</p>
                <p class="text-3xl font-bold text-teal-600"><?= number_format($exercises_stats['media_acertos'] ?? 0, 1) ?>%</p>
            </div>
            <div class="bg-teal-100 rounded-full p-3">
                <svg class="w-6 h-6 text-teal-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<!-- Lista de Exercícios Banco de Dados -->
<?php if (!empty($exercicios_bd)): ?>
<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Título</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acertos</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Total</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">% Acerto</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($exercicios_bd as $ex): ?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($ex['aluno_nome']) ?></div>
                    <div class="text-sm text-gray-500">RA: <?= htmlspecialchars($ex['ra']) ?></div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= htmlspecialchars($ex['turma_nome'] ?? 'Sem turma') ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                    <?= htmlspecialchars($ex['titulo']) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= htmlspecialchars($ex['materia']) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= number_format($ex['questoes_corretas']) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= number_format($ex['questoes_total']) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= $ex['percentual_acerto'] >= 70 ? 'text-green-600' : ($ex['percentual_acerto'] >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= number_format($ex['percentual_acerto'], 1) ?>%
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= date('d/m/Y H:i', strtotime($ex['created_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="text-center py-12">
    <p class="text-gray-500">Nenhum exercício do banco de dados encontrado para os filtros selecionados.</p>
</div>
<?php endif; ?>

