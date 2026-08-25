<?php
require_once dirname(__DIR__, 4) . '/Services/FrequencyService.php';
?>
<div class="space-y-6">
    <div class="bg-white border border-gray-200 rounded-xl p-5">
        <p class="text-xs font-semibold text-gray-500 uppercase">Frequência da turma no período</p>
        <p class="text-3xl font-bold text-gray-900 mt-1">
            <?= $frequencia_turma !== null ? number_format($frequencia_turma, 1) . '%' : '—' ?>
        </p>
        <?php if ($frequencia_turma !== null && $frequencia_turma < FrequencyService::MINIMO_LEGAL): ?>
            <p class="text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-3 py-2 mt-3">
                Abaixo do mínimo legal (<?= FrequencyService::MINIMO_LEGAL ?>% — LDB art. 24, VI).
            </p>
        <?php endif; ?>
        <p class="text-xs text-gray-400 mt-3">Calculada automaticamente a partir das chamadas finalizadas — não é um lançamento separado.</p>
    </div>

    <div class="bg-white border border-gray-200 rounded-xl overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-base font-semibold text-gray-900">Frequência por aluno</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php foreach (['Aluno', 'Aulas', 'Presenças', 'Faltas', 'Justificadas', 'Frequência'] as $header): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($frequencia_alunos)): ?>
                        <tr><td colspan="6" class="px-6 py-10 text-center text-gray-500">Nenhum registro de frequência neste período.</td></tr>
                    <?php else: ?>
                        <?php foreach ($frequencia_alunos as $a): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $a['nome']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $a['total_aulas'] ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $a['presencas'] ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $a['faltas'] ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $a['faltas_justificadas'] ?></td>
                                <td class="px-6 py-4 text-sm font-semibold <?= $a['percentual'] !== null && $a['percentual'] < FrequencyService::MINIMO_LEGAL ? 'text-red-700' : 'text-gray-900' ?>">
                                    <?= $a['percentual'] !== null ? number_format($a['percentual'], 1) . '%' : '—' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
