<?php
/**
 * Resultados do Bloco de Provas (admin) – estilo jornada
 * Questões mais acertadas/erradas, alunos acima/abaixo 40%, ver respostas de todos
 */
$stats = $stats_questoes ?? ['mais_acertadas' => [], 'mais_erradas' => []];
$acima40 = $alunos_acima_40 ?? [];
$abaixo40 = $alunos_abaixo_40 ?? [];
$todos = $todos_alunos ?? [];
$porMateria = $stats_por_materia ?? [];
$total_canceladas = (int)($total_canceladas ?? 0);
?>

<!-- Header -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Resultados: <?= htmlspecialchars($bloco['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                Visão geral do desempenho dos alunos no bloco de provas
            </p>
        </div>
        <div class="flex flex-col items-start gap-2 lg:items-end">
            <div class="flex flex-wrap gap-2 items-center">
                <?php if (!empty($bloco['gabarito_liberado'])): ?>
                    <span class="bg-emerald-100 text-emerald-800 px-4 py-2 rounded-lg font-medium">
                        Gabarito liberado para os alunos
                    </span>
                <?php else: ?>
                    <form method="post" action="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/liberar-gabarito" class="inline"
                          onsubmit="return confirm('Liberar o gabarito deste bloco para todos os alunos?');">
                        <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-4 py-2 rounded-lg">
                            Liberar gabarito
                        </button>
                    </form>
                <?php endif; ?>
                <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/canceladas"
                   class="bg-amber-600 hover:bg-amber-700 text-white px-4 py-2 rounded-lg flex items-center <?= !empty($total_canceladas) ? 'ring-2 ring-amber-300' : '' ?>">
                    Canceladas<?= !empty($total_canceladas) ? ' (' . (int)$total_canceladas . ')' : '' ?>
                </a>
                <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/relatorio-acertos"
                   target="_blank"
                   class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                    Matriz de Desempenho
                </a>
                <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/visualizar-completo"
                   class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                    Ver Prova Completa do Bloco
                </a>
                <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/gerenciar"
                   class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Voltar
                </a>
            </div>
            <p class="text-xs text-gray-500">
                <strong>Canceladas (<?= (int)$total_canceladas ?>)</strong>: tentativas canceladas por saída/interrupção da prova em modo seguro. Clique para liberar nova tentativa.
            </p>
        </div>
    </div>
</div>

<!-- Cards de resumo -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border border-green-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-600">Alunos com ≥ 55% acerto</p>
                <p class="text-2xl font-bold text-green-600"><?= count($acima40) ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-red-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-red-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-600">Alunos que precisam de atenção (&lt; 55%)</p>
                <p class="text-2xl font-bold text-red-600"><?= count($abaixo40) ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-blue-200">
        <div class="flex items-center">
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center mr-4">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
            </div>
            <div>
                <p class="text-sm text-gray-600">Total de alunos com prova finalizada</p>
                <p class="text-2xl font-bold text-blue-600"><?= count($todos) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Cards por matéria: acertos, erros, total questões, percentual -->
<?php if (!empty($porMateria)): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-6">
    <?php foreach ($porMateria as $m): ?>
    <div class="bg-white rounded-xl shadow-md p-5 border border-gray-200">
        <h4 class="font-semibold text-gray-900 truncate" title="<?= htmlspecialchars($m['materia_nome']) ?>"><?= htmlspecialchars($m['materia_nome']) ?></h4>
        <?php $nomeProf = trim((string) ($m['professor_nome'] ?? '')); ?>
        <p class="text-sm text-gray-500 mb-3 truncate" title="<?= htmlspecialchars($nomeProf !== '' ? $nomeProf : 'Professor não informado') ?>">
            Prof. <?= htmlspecialchars($nomeProf !== '' ? $nomeProf : 'Não informado') ?>
        </p>
        <div class="space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-600">Acertos</span>
                <span class="font-medium text-green-600"><?= (int)$m['acertos'] ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Erros</span>
                <span class="font-medium text-red-600"><?= (int)$m['erros'] ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-600">Total questões</span>
                <span class="font-medium text-gray-900"><?= (int)$m['total_questoes'] ?></span>
            </div>
            <div class="flex justify-between pt-2 border-t border-gray-100">
                <span class="text-gray-600">Percentual</span>
                <span class="font-bold text-blue-600"><?= number_format((float)($m['percentual'] ?? 0), 1, ',', '') ?>%</span>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Dashboard (estilo jornada) -->
<div class="mb-8 bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg border border-blue-200 p-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-xl font-bold text-gray-900 flex items-center gap-2">
            <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
            Resultados por questão e por aluno
        </h3>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <!-- Questões mais acertadas -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-green-500">
            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Questões mais acertadas
            </h4>
            <?php if (!empty($stats['mais_acertadas'])): ?>
                <div class="space-y-3 max-h-[400px] overflow-y-auto">
                    <?php foreach ($stats['mais_acertadas'] as $q):
                        if (($q['taxa_acerto'] ?? 0) <= 0) continue;
                        $enunciado = $q['enunciado'] ?? '';
                        $enunciado = strlen($enunciado) > 120 ? substr(strip_tags($enunciado), 0, 120) . '…' : strip_tags($enunciado);
                    ?>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($enunciado) ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($q['materia_nome'] ?? '') ?></p>
                                </div>
                                <span class="px-2 py-1 bg-green-100 text-green-800 rounded text-xs font-semibold">
                                    <?= round((float)($q['taxa_acerto'] ?? 0), 1) ?>%
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-600 mt-2">
                                <span>✓ <?= (int)($q['total_acertos'] ?? 0) ?> acertos</span>
                                <span>✗ <?= (int)($q['total_erros'] ?? 0) ?> erros</span>
                                <span>📊 <?= (int)($q['total_respostas'] ?? 0) ?> respostas</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Nenhuma questão com respostas ainda.</p>
            <?php endif; ?>
        </div>

        <!-- Questões mais erradas -->
        <div class="bg-white rounded-xl shadow-md p-6 border-l-4 border-red-500">
            <h4 class="text-lg font-semibold text-gray-900 mb-4 flex items-center gap-2">
                <svg class="w-5 h-5 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Questões mais erradas
            </h4>
            <?php if (!empty($stats['mais_erradas'])): ?>
                <div class="space-y-3 max-h-[400px] overflow-y-auto">
                    <?php foreach ($stats['mais_erradas'] as $q):
                        if (($q['taxa_erro'] ?? 0) <= 0) continue;
                        $enunciado = $q['enunciado'] ?? '';
                        $enunciado = strlen($enunciado) > 120 ? substr(strip_tags($enunciado), 0, 120) . '…' : strip_tags($enunciado);
                    ?>
                        <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($enunciado) ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($q['materia_nome'] ?? '') ?></p>
                                </div>
                                <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">
                                    <?= round((float)($q['taxa_erro'] ?? 0), 1) ?>%
                                </span>
                            </div>
                            <div class="flex items-center gap-4 text-xs text-gray-600 mt-2">
                                <span>✓ <?= (int)($q['total_acertos'] ?? 0) ?> acertos</span>
                                <span>✗ <?= (int)($q['total_erros'] ?? 0) ?> erros</span>
                                <span>📊 <?= (int)($q['total_respostas'] ?? 0) ?> respostas</span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-sm">Nenhuma questão com respostas ainda.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Alunos que precisam de atenção (< 40%) -->
    <?php if (!empty($abaixo40)): ?>
    <div class="bg-yellow-50 border-l-4 border-yellow-500 rounded-xl p-6 shadow-md mb-6">
        <h4 class="text-lg font-semibold text-yellow-900 mb-2">Alunos que precisam de atenção</h4>
        <p class="text-sm text-yellow-800 mb-4">Alunos com menos de <strong>55% de acerto</strong> no bloco.</p>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            <?php foreach ($abaixo40 as $aluno): ?>
                <div class="bg-white rounded-lg p-4 border border-yellow-200">
                    <div class="flex items-start justify-between mb-2">
                        <div class="flex-1">
                            <p class="font-semibold text-gray-900 text-sm"><?= htmlspecialchars($aluno['nome'] ?? 'Sem nome') ?></p>
                            <p class="text-xs text-gray-500">RA: <?= htmlspecialchars($aluno['ra'] ?? 'N/A') ?></p>
                        </div>
                        <span class="px-2 py-1 bg-red-100 text-red-800 rounded text-xs font-semibold">
                            <?= round($aluno['percentual_acerto'] ?? 0, 1) ?>% acerto
                        </span>
                    </div>
                    <div class="mt-3 pt-3 border-t border-yellow-200 text-xs text-gray-600">
                        <span>✓ <?= $aluno['total_acertos'] ?? 0 ?> acertos</span>
                        <span class="mx-2">✗ <?= ($aluno['total_respostas'] ?? 0) - ($aluno['total_acertos'] ?? 0) ?> erros</span>
                        <span>📊 <?= $aluno['total_respostas'] ?? 0 ?> questões</span>
                    </div>
                    <div class="mt-3">
                        <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/aluno/<?= (int)$aluno['aluno_id'] ?>/resultado"
                           class="inline-block w-full text-center bg-blue-500 text-white px-3 py-2 rounded-lg hover:bg-blue-600 text-sm">
                            Ver resultado
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Ver respostas de todos os alunos -->
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
                        <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($aluno['nome'] ?? '') ?></td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($aluno['ra'] ?? '') ?></td>
                        <td class="px-4 py-3">
                            <span class="px-2 py-1 rounded text-xs font-semibold <?= ($aluno['percentual_acerto'] ?? 0) >= 55 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= round($aluno['percentual_acerto'] ?? 0, 1) ?>%
                            </span>
                        </td>
                        <td class="px-4 py-3 text-sm text-gray-600"><?= (int)($aluno['total_acertos'] ?? 0) ?> / <?= (int)($aluno['total_respostas'] ?? 0) ?></td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap items-center gap-2">
                                <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/aluno/<?= (int)$aluno['aluno_id'] ?>/resultado"
                                   class="text-blue-600 hover:text-blue-800 font-medium text-sm">
                                    Ver resultado
                                </a>
                                <form method="post" action="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/aluno/<?= (int)$aluno['aluno_id'] ?>/reabrir" class="inline"
                                      onsubmit="return confirm('Reabrir a prova para este aluno? Ele poderá continuar de onde parou.');">
                                    <button type="submit" class="text-amber-600 hover:text-amber-800 font-medium text-sm">
                                        Reabrir prova
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <p class="text-gray-500 text-sm">Nenhum aluno finalizou provas deste bloco ainda.</p>
        <?php endif; ?>
    </div>
</div>
