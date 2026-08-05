<?php
$stats = $stats_questoes ?? ['mais_acertadas' => [], 'mais_erradas' => []];
$acima40 = $alunos_acima_40 ?? [];
$abaixo40 = $alunos_abaixo_40 ?? [];
$todos = $todos_alunos ?? [];
?>

<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Resultados: <?= htmlspecialchars($prova['titulo'] ?? 'Prova') ?>
            </h2>
            <p class="text-gray-600">
                Visão geral do desempenho dos alunos na prova
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/provas"
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Voltar
            </a>
            <a href="<?= URL ?>/professor/provas/visualizar/<?= (int)$prova['id'] ?>"
               class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                Ver prova / gabarito
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border border-green-200">
        <p class="text-sm text-gray-600">Alunos com >= 40% acerto</p>
        <p class="text-2xl font-bold text-green-600"><?= count($acima40) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-red-200">
        <p class="text-sm text-gray-600">Alunos que precisam de atenção (&lt; 40%)</p>
        <p class="text-2xl font-bold text-red-600"><?= count($abaixo40) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
        <p class="text-sm text-gray-600">Total de alunos com prova finalizada</p>
        <p class="text-2xl font-bold text-blue-600"><?= count($todos) ?></p>
    </div>
</div>

<div class="mb-8 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg border border-blue-200 p-6">
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Questões mais acertadas</h4>
            <?php if (!empty($stats['mais_acertadas'])): ?>
                <div class="space-y-3 max-h-[400px] overflow-y-auto">
                    <?php foreach ($stats['mais_acertadas'] as $q):
                        if (($q['taxa_acerto'] ?? 0) <= 0) continue;
                        $enunciado = $q['enunciado'] ?? '';
                        $enunciado = strlen($enunciado) > 120 ? substr(strip_tags($enunciado), 0, 120) . '...' : strip_tags($enunciado);
                    ?>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="flex items-start justify-between mb-2">
                                <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($enunciado) ?></p>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">
                                    <?= round((float)($q['taxa_acerto'] ?? 0), 1) ?>%
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-600 mt-2">
                                <span><?= (int)($q['total_acertos'] ?? 0) ?> acertos</span>
                                <span><?= (int)($q['total_erros'] ?? 0) ?> erros</span>
                                <span><?= (int)($q['total_respostas'] ?? 0) ?> respostas</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Nenhuma questão com respostas ainda.</p>
            <?php endif; ?>
        </div>

        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
            <h4 class="text-lg font-semibold text-gray-900 mb-4">Questões mais erradas</h4>
            <?php if (!empty($stats['mais_erradas'])): ?>
                <div class="space-y-3 max-h-[400px] overflow-y-auto">
                    <?php foreach ($stats['mais_erradas'] as $q):
                        if (($q['taxa_erro'] ?? 0) <= 0) continue;
                        $enunciado = $q['enunciado'] ?? '';
                        $enunciado = strlen($enunciado) > 120 ? substr(strip_tags($enunciado), 0, 120) . '...' : strip_tags($enunciado);
                    ?>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="flex items-start justify-between mb-2">
                                <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($enunciado) ?></p>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">
                                    <?= round((float)($q['taxa_erro'] ?? 0), 1) ?>%
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-600 mt-2">
                                <span><?= (int)($q['total_acertos'] ?? 0) ?> acertos</span>
                                <span><?= (int)($q['total_erros'] ?? 0) ?> erros</span>
                                <span><?= (int)($q['total_respostas'] ?? 0) ?> respostas</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Nenhuma questão com respostas ainda.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($abaixo40)): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-xl p-6 shadow-md mb-6">
        <h4 class="text-lg font-semibold text-yellow-900 mb-2">Alunos que precisam de atenção</h4>
        <p class="text-sm text-yellow-800 mb-4">Alunos com menos de 40% de acerto na prova.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($abaixo40 as $aluno): ?>
                <div class="bg-white rounded-lg p-4 border border-yellow-200">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($aluno['nome'] ?? '') ?></p>
                            <p class="text-xs text-gray-500">RA: <?= htmlspecialchars($aluno['ra'] ?? '') ?></p>
                        </div>
                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">
                            <?= round($aluno['percentual_acerto'] ?? 0, 1) ?>% acerto
                        </span>
                    </div>
                    <div class="mt-3 pt-3 border-t border-yellow-200 text-xs text-gray-600">
                        <span><?= $aluno['total_acertos'] ?? 0 ?> acertos</span>
                        <span class="mx-2"><?= ($aluno['total_respostas'] ?? 0) - ($aluno['total_acertos'] ?? 0) ?> erros</span>
                        <span><?= $aluno['total_respostas'] ?? 0 ?> questões</span>
                    </div>
                    <div class="mt-3">
                        <a href="<?= URL ?>/professor/provas/resultado-aluno/<?= (int)$prova['id'] ?>/<?= (int)$aluno['aluno_id'] ?>"
                           class="inline-block w-full text-center bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 text-sm">
                            Ver resultado
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-md p-6 border border-gray-200">
        <h4 class="text-lg font-semibold text-gray-900 mb-4">Ver resposta de todos os alunos</h4>
        <?php if (!empty($todos)): ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">RA</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">% Acerto</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Acertos / Total</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($todos as $aluno): ?>
                    <tr>
                        <td class="px-4 py-3 text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($aluno['nome'] ?? '') ?>
                            <?php if (!empty($aluno['adaptada'])): ?>
                                <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-purple-100 text-purple-700" title="Prova adaptada (EducaInclui) — nota na mesma escala da prova original">adaptada</span>
                            <?php endif; ?>
                            <?php if (!empty($aluno['no_spelling_penalty'])): ?>
                                <span class="ml-1 inline-flex px-2 py-0.5 rounded-full text-[10px] font-semibold bg-amber-100 text-amber-800" title="EducaInclui — não descontar nota por ortografia">sem penalizar ortografia</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($aluno['ra'] ?? '') ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-semibold <?= ($aluno['percentual_acerto'] ?? 0) >= 40 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= round($aluno['percentual_acerto'] ?? 0, 1) ?>%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= !empty($aluno['adaptada']) ? '<span class="text-gray-400">—</span>' : ((int)($aluno['total_acertos'] ?? 0) . ' / ' . (int)($aluno['total_respostas'] ?? 0)) ?></td>
                        <td class="px-4 py-3">
                            <a href="<?= URL ?>/professor/provas/resultado-aluno/<?= (int)$prova['id'] ?>/<?= (int)$aluno['aluno_id'] ?>"
                               class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                Ver resultado
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-gray-500 text-sm">Nenhum aluno finalizou esta prova ainda.</p>
        <?php endif; ?>
    </div>
</div>
