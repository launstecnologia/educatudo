<?php
$reports_filter_tab = 'exercicios-ia';
$reports_filter_jornadas_extended = false;
require __DIR__ . '/_reports_filters_form.php';
?>
<!-- Lista de Exercícios IA -->
<?php if (!empty($exercicios_ia)): ?>
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
            <?php foreach ($exercicios_ia as $ex): ?>
            <?php 
                $total_respostas = $ex['total_respostas'] ?? 0;
                $acertos = $ex['acertos'] ?? 0;
                $percentual = $total_respostas > 0 ? ($acertos / $total_respostas) * 100 : 0;
            ?>
            <tr>
                <td class="px-6 py-4 whitespace-nowrap">
                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($ex['aluno_nome']) ?></div>
                    <div class="text-sm text-gray-500">RA: <?= htmlspecialchars($ex['ra']) ?></div>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= htmlspecialchars($ex['turma_nome'] ?? 'Sem turma') ?>
                </td>
                <td class="px-6 py-4 text-sm text-gray-900">
                    <?= htmlspecialchars($ex['lista_titulo'] ?? 'Sem título') ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= htmlspecialchars($ex['materia'] ?? 'N/A') ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= number_format($acertos) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= number_format($total_respostas) ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium <?= $percentual >= 70 ? 'text-green-600' : ($percentual >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                    <?= number_format($percentual, 1) ?>%
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                    <?= date('d/m/Y H:i', strtotime($ex['started_at'])) ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="text-center py-12">
    <p class="text-gray-500">Nenhum exercício gerado por IA encontrado para os filtros selecionados.</p>
</div>
<?php endif; ?>

