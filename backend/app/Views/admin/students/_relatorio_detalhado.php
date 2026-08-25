<?php
/** Extraído de show.php — conteúdo existente, sem alteração de regra de negócio. */
?>
<!-- Relatório Detalhado -->
<div id="section-relatorio-detalhado" class="mt-1">
    <div class="student-card overflow-hidden">
        <div class="student-card-header">
            <div class="flex items-center gap-2">
                <span class="aluno-card-icon"><i class="fa-solid fa-chart-column"></i></span>
                <h3 class="text-base font-semibold text-slate-900">Relatório detalhado</h3>
            </div>
        </div>
        <div class="border-b border-slate-200 px-4 student-tabs-nav-scroll">
            <nav class="student-tabs-nav -mb-px py-1" aria-label="Tabs">
                <button onclick="showTab('relatorio')" id="tab-relatorio" data-tab-perm-key="tab_relatorio" class="tab-button active flex items-center px-4 py-3 text-sm font-medium border-b-2 border-blue-500 text-blue-600 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path></svg>
                    Desempenho
                </button>
                <button onclick="showTab('redacoes')" id="tab-redacoes" data-tab-perm-key="tab_redacao" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path></svg>
                    Jornada de Redação
                </button>
                <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias')): ?>
                <button onclick="showTab('ocorrencias')" id="tab-ocorrencias" data-tab-perm-key="tab_ocorrencias" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Ocorrências
                </button>
                <?php endif; ?>
                <button onclick="showTab('jornadas')" id="tab-jornadas" data-tab-perm-key="tab_jornadas" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path></svg>
                    Jornadas
                </button>
                <button onclick="showTab('provas')" id="tab-provas" data-tab-perm-key="tab_provas" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Provas
                </button>
                <button onclick="showTab('notas-eventos')" id="tab-notas-eventos" data-tab-perm-key="tab_notas" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4V7m-9 8h12a2 2 0 002-2V5a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                    Notas
                </button>
                <button onclick="showTab('boletim-eventos')" id="tab-boletim-eventos" data-tab-perm-key="tab_boletim" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6l4 2m5-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Boletim
                </button>
                <button onclick="showTab('acessos')" id="tab-acessos" data-tab-perm-key="tab_acessos" class="tab-button flex items-center px-4 py-3 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 whitespace-nowrap">
                    <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path></svg>
                    Acessos
                </button>
            </nav>
        </div>

        <!-- Tab Content -->
        <div class="p-6">
            <!-- Tab: Relatório -->
            <div id="content-relatorio" class="tab-content">
                <div class="student-metrics-row">
                    <div class="student-metric-card border-l-red-500">
                        <div class="text-xs font-medium text-slate-500 mb-1 leading-tight">Jornada de Redação</div>
                        <div class="text-2xl font-bold text-slate-900"><?= $stats['redacoes_total'] ?? 0 ?></div>
                        <div class="text-[11px] text-slate-500 mt-1"><?= $stats['redacoes_corrigidas'] ?? 0 ?> corr. | <?= number_format($stats['media_redacoes'] ?? 0, 1) ?></div>
                    </div>
                    <div class="student-metric-card border-l-green-500">
                        <div class="text-xs font-medium text-slate-500 mb-1 leading-tight">Jornadas</div>
                        <div class="text-2xl font-bold text-slate-900"><?= $stats['jornadas_concluidas'] ?? 0 ?></div>
                        <div class="text-[11px] text-slate-500 mt-1">Trilhas completas</div>
                    </div>
                    <div class="student-metric-card border-l-slate-400">
                        <div class="text-xs font-medium text-slate-500 mb-1 leading-tight">Mural de recados</div>
                        <div class="text-2xl font-bold text-slate-900"><?= $stats['mural_recados_vistos'] ?? 0 ?> <span class="text-lg font-semibold text-slate-400">/</span> <?= $stats['mural_recados_total'] ?? 0 ?></div>
                        <div class="text-[11px] text-slate-500 mt-1"><?= (int)($stats['mural_recados_total'] ?? 0) > 0 && (int)($stats['mural_recados_vistos'] ?? 0) > 0 ? 'Está lendo' : 'Não está lendo' ?></div>
                    </div>
                </div>

            </div>

            <!-- Tab: Exercícios Banco de Dados -->
            <div id="content-exercicios-bd" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Exercícios do Banco de Dados</h3>
                    <span class="text-sm text-gray-500"><?= count($exercicios_bd ?? []) ?> exercícios encontrados</span>
                </div>
                
                <?php if (empty($exercicios_bd)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <p class="text-gray-500">Nenhum exercício encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($exercicios_bd as $exercicio): ?>
                            <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="text-lg font-semibold text-gray-900">
                                                <?= safe_htmlspecialchars($exercicio['titulo'] ?? null, 'Exercício') ?>
                                            </h4>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">
                                                Finalizado
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">Matéria:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= safe_htmlspecialchars($exercicio['materia'] ?? null, 'N/A') ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Questões:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= $exercicio['questoes_corretas'] ?? 0 ?>/<?= $exercicio['questoes_total'] ?? 0 ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Acertos:</span>
                                                <span class="font-medium text-green-600 ml-1">
                                                    <?= $exercicio['questoes_corretas'] ?? 0 ?>
                                                    <?php
                                                        $totalQuestoes = $exercicio['questoes_total'] ?? 0;
                                                        $acertos = $exercicio['questoes_corretas'] ?? 0;
                                                        $percentualAcerto = ($totalQuestoes > 0 && $acertos > 0) ? round(($acertos / $totalQuestoes) * 100) : ($exercicio['percentual_acerto'] ?? 0);
                                                    ?>
                                                    <?php if ($totalQuestoes > 0): ?>
                                                        <span class="text-blue-600 font-semibold ml-1">(<?= number_format($percentualAcerto, 1) ?>%)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Realizado em:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= !empty($exercicio['created_at']) ? date('d/m/Y H:i', strtotime($exercicio['created_at'])) : '' ?></span>
                                            </div>
                                        </div>
                                        <?php if (!empty($exercicio['data_fim'])): ?>
                                            <div class="mt-2 text-sm text-gray-600">
                                                <strong>Finalizado em:</strong> <?= !empty($exercicio['data_fim']) ? date('d/m/Y H:i', strtotime($exercicio['data_fim'])) : 'N/A' ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Exercícios IA -->
            <div id="content-exercicios-ia" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Exercícios Gerados por IA</h3>
                    <span class="text-sm text-gray-500"><?= count($exercicios_ia ?? []) ?> sessões encontradas</span>
                </div>

                <!-- Listas do aluno (permite excluir listas em erro/gerando) -->
                <?php $listas_pers = $listas_personalizadas_aluno ?? []; ?>
                <?php if (!empty($listas_pers)): ?>
                <div class="mb-8">
                    <h4 class="text-lg font-semibold text-gray-800 mb-3">Listas de exercícios personalizados do aluno</h4>
                    <div class="overflow-x-auto rounded-lg border border-gray-200">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Lista</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Sessões</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Criada em</th>
                                    <th class="px-4 py-2 text-right text-xs font-medium text-gray-500 uppercase">Ação</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($listas_pers as $lp): ?>
                                <tr class="lista-personalizada-row" data-lista-id="<?= (int)$lp['id'] ?>">
                                    <td class="px-4 py-2 text-sm text-gray-900"><?= safe_htmlspecialchars($lp['titulo'] ?? '', '—') ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= safe_htmlspecialchars($lp['materia'] ?? '', '—') ?></td>
                                    <td class="px-4 py-2">
                                        <?php
                                        $st = $lp['status'] ?? '';
                                        $badge = $st === 'concluido' ? 'bg-green-100 text-green-800' : ($st === 'gerando' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800');
                                        $label = $st === 'concluido' ? 'Pronta' : ($st === 'gerando' ? 'Gerando' : 'Erro');
                                        ?>
                                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $badge ?>"><?= $label ?></span>
                                    </td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= (int)($lp['total_sessoes'] ?? 0) ?></td>
                                    <td class="px-4 py-2 text-sm text-gray-600"><?= !empty($lp['created_at']) ? date('d/m/Y H:i', strtotime($lp['created_at'])) : '—' ?></td>
                                    <td class="px-4 py-2 text-right">
                                        <form method="post" action="<?= URL ?>/admin/students/excluir-lista-exercicio-ia" class="inline" onsubmit="return confirm('Excluir esta lista? Esta ação não pode ser desfeita.');">
                                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                            <input type="hidden" name="lista_id" value="<?= (int)$lp['id'] ?>">
                                            <input type="hidden" name="aluno_id" value="<?= (int)($student['id'] ?? 0) ?>">
                                            <button type="submit" class="text-red-600 hover:text-red-800 text-sm font-medium">Excluir</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <h4 class="text-lg font-semibold text-gray-800 mb-3">Exercícios realizados (sessões)</h4>
                
                <?php if (empty($exercicios_ia)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        <p class="text-gray-500">Nenhum exercício IA encontrado</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($exercicios_ia as $exercicio): ?>
                            <div class="bg-indigo-50 border border-indigo-200 rounded-lg p-4 hover:shadow-md transition-shadow">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <div class="flex items-center space-x-3 mb-2">
                                            <h4 class="text-lg font-semibold text-gray-900">
                                                <?= safe_htmlspecialchars($exercicio['lista_titulo'] ?? null, 'Exercício IA') ?>
                                            </h4>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $exercicio['status'] === 'finalizado' ? 'bg-green-100 text-green-800' : ($exercicio['status'] === 'em_andamento' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                                                <?= ucfirst($exercicio['status'] ?? 'N/A') ?>
                                            </span>
                                        </div>
                                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
                                            <div>
                                                <span class="text-gray-500">Matéria:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= safe_htmlspecialchars($exercicio['materia'] ?? null, 'N/A') ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Questões:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= $exercicio['total_respostas'] ?? 0 ?>/<?= $exercicio['quantidade_exercicios'] ?? 0 ?></span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Acertos:</span>
                                                <span class="font-medium text-green-600 ml-1">
                                                    <?= $exercicio['acertos'] ?? 0 ?>
                                                    <?php
                                                        $totalQuestoes = $exercicio['quantidade_exercicios'] ?? 0;
                                                        $acertos = $exercicio['acertos'] ?? 0;
                                                        $percentualAcerto = ($totalQuestoes > 0 && $acertos > 0) ? round(($acertos / $totalQuestoes) * 100) : 0;
                                                    ?>
                                                    <?php if ($totalQuestoes > 0 && $acertos > 0): ?>
                                                        <span class="text-blue-600 font-semibold ml-1">(<?= $percentualAcerto ?>%)</span>
                                                    <?php endif; ?>
                                                </span>
                                            </div>
                                            <div>
                                                <span class="text-gray-500">Iniciado:</span>
                                                <span class="font-medium text-gray-900 ml-1"><?= !empty($exercicio['started_at']) ? date('d/m/Y H:i', strtotime($exercicio['started_at'])) : '' ?></span>
                                            </div>
                                        </div>
                                        <?php if ($exercicio['status'] === 'finalizado' && $exercicio['finished_at']): ?>
                                            <div class="mt-2 text-sm text-gray-600">
                                                <strong>Finalizado em:</strong> <?= !empty($exercicio['finished_at']) ? date('d/m/Y H:i', strtotime($exercicio['finished_at'])) : 'N/A' ?>
                                                <?php if ($exercicio['tempo_gasto']): ?>
                                                    | <strong>Tempo:</strong> <?= gmdate('H:i:s', $exercicio['tempo_gasto']) ?>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="ml-4">
                                        <a href="<?= URL ?>/admin/students/exercicio-ia/<?= $exercicio['id'] ?>" 
                                           class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 transition-colors">
                                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                            </svg>
                                            Ver Detalhes
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Jornada de Redação -->
            <div id="content-redacoes" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Jornada de Redação</h3>
                    <span class="text-sm text-gray-500"><?= count($redacoes ?? []) ?> redações encontradas</span>
                </div>
                
                <?php if (empty($redacoes)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                        </svg>
                        <p class="text-gray-500">Nenhuma redação encontrada</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($redacoes as $index => $redacao): ?>
                            <div class="bg-white border border-gray-200 rounded-lg hover:shadow-md transition-shadow">
                                <!-- Header Clicável -->
                                <button onclick="toggleRedacaoDetails(<?= $redacao['id'] ?>)" class="w-full text-left p-6 flex items-center justify-between hover:bg-gray-50 transition-colors">
                                    <div class="flex-1">
                                        <div class="flex items-center flex-wrap gap-3 mb-2">
                                            <h4 class="text-lg font-bold text-gray-900">
                                                <?= safe_htmlspecialchars($redacao['tema'] ?? null, 'Sem tema') ?>
                                            </h4>
                                            <?php 
                                                $estaCorrigida = !empty($redacao['corrigida_em']) || 
                                                                !empty($redacao['correcao']) || 
                                                                !empty($redacao['feedback_ia']) || 
                                                                !empty($redacao['nota']) || 
                                                                !empty($redacao['nota_final']);
                                            ?>
                                            <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $estaCorrigida ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                                <?= safe_htmlspecialchars($redacao['status_descricao'] ?? null, 'Pendente') ?>
                                            </span>
                                            <?php 
                                                $notaExibir = $redacao['nota'] ?? $redacao['nota_final'] ?? null;
                                                if ($notaExibir): 
                                            ?>
                                                <span class="px-3 py-1 text-sm font-semibold rounded-full bg-blue-100 text-blue-800">
                                                    Nota: <?= number_format($notaExibir, 1) ?>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex flex-wrap gap-4 text-sm text-gray-600">
                                            <div>
                                                <span class="font-medium text-gray-700">Criada em:</span>
                                                <span class="ml-1"><?= !empty($redacao['created_at']) ? date('d/m/Y H:i', strtotime($redacao['created_at'])) : '' ?></span>
                                            </div>
                                            <?php if (!empty($redacao['texto'])): ?>
                                                <?php 
                                                    $texto_limpo = strip_tags($redacao['texto']);
                                                    $palavras = str_word_count($texto_limpo);
                                                ?>
                                                <div>
                                                    <span class="font-medium text-gray-700">Palavras:</span>
                                                    <span class="ml-1"><?= $palavras ?></span>
                                                </div>
                                            <?php endif; ?>
                                            <?php if ($redacao['updated_at'] && $redacao['updated_at'] != $redacao['created_at']): ?>
                                                <div>
                                                    <span class="font-medium text-gray-700">Atualizada:</span>
                                                    <span class="ml-1"><?= !empty($redacao['updated_at']) ? date('d/m/Y H:i', strtotime($redacao['updated_at'])) : '' ?></span>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <svg id="arrow-<?= $redacao['id'] ?>" class="w-5 h-5 text-gray-400 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>

                                <!-- Conteúdo Expansível -->
                                <div id="redacao-detalhes-<?= $redacao['id'] ?>" class="hidden border-t">
                                    <div class="p-6">
                                        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
                                            <!-- Coluna 8: Redação -->
                                            <div class="lg:col-span-8">
                                                <?php if (!empty($redacao['texto'])): ?>
                                                    <div class="mb-6">
                                                        <h5 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Redação:</h5>
                                                        <div class="bg-gray-50 border border-gray-200 rounded-lg p-5 text-base text-gray-800 leading-relaxed whitespace-pre-wrap">
                                                            <?= nl2br(safe_htmlspecialchars($redacao['texto'] ?? null, '')) ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>

                                            <!-- Coluna 4: Resultado -->
                                            <div class="lg:col-span-4 space-y-4">
                                                <!-- Correção -->
                                                <?php if (!empty($redacao['correcao'])): ?>
                                                    <div>
                                                        <h5 class="text-sm font-semibold text-blue-700 mb-2 uppercase tracking-wide">Correção:</h5>
                                                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 text-sm text-blue-900 leading-relaxed whitespace-pre-wrap max-h-96 overflow-y-auto">
                                                            <?= nl2br(safe_htmlspecialchars($redacao['correcao'] ?? null, '')) ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Feedback IA -->
                                                <?php if (!empty($redacao['feedback_ia'])): ?>
                                                    <div>
                                                        <h5 class="text-sm font-semibold text-purple-700 mb-2 uppercase tracking-wide">Feedback da IA:</h5>
                                                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-4 text-xs text-purple-900 leading-relaxed whitespace-pre-wrap max-h-96 overflow-y-auto">
                                                            <?php 
                                                                // Tentar decodificar JSON se for JSON
                                                                $feedback = $redacao['feedback_ia'];
                                                                $decoded = json_decode($feedback, true);
                                                                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                                                    echo '<div class="space-y-2">';
                                                                    if (isset($decoded['comentarios_gerais'])) {
                                                                        echo '<div class="mb-3"><strong>Comentários Gerais:</strong><br>' . nl2br(safe_htmlspecialchars($decoded['comentarios_gerais'] ?? null, '')) . '</div>';
                                                                    }
                                                                    if (isset($decoded['sugestoes_melhoria'])) {
                                                                        echo '<div class="mb-3"><strong>Sugestões de Melhoria:</strong><br>' . nl2br(safe_htmlspecialchars($decoded['sugestoes_melhoria'] ?? null, '')) . '</div>';
                                                                    }
                                                                    echo '</div>';
                                                                } else {
                                                                    echo nl2br(safe_htmlspecialchars(is_array($feedback) ? '' : (string)($feedback ?? ''), ''));
                                                                }
                                                            ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <!-- Competências -->
                                                <?php 
                                                    // Buscar feedback da IA se existir
                                                    $feedback = null;
                                                    if (!empty($redacao['feedback_ia'])) {
                                                        $feedbackDecoded = json_decode($redacao['feedback_ia'], true);
                                                        if (json_last_error() === JSON_ERROR_NONE) {
                                                            $feedback = $feedbackDecoded;
                                                        } else {
                                                            $feedback = $redacao['feedback_ia'];
                                                        }
                                                    }
                                                    
                                                    $competencias = [
                                                        1 => ['nome' => 'Domínio da norma padrão da Língua Portuguesa', 'nota' => $redacao['competencia_1'] ?? null],
                                                        2 => ['nome' => 'Compreensão da proposta e desenvolvimento do tema', 'nota' => $redacao['competencia_2'] ?? null],
                                                        3 => ['nome' => 'Seleção e organização de argumentos', 'nota' => $redacao['competencia_3'] ?? null],
                                                        4 => ['nome' => 'Coesão e coerência', 'nota' => $redacao['competencia_4'] ?? null],
                                                        5 => ['nome' => 'Proposta de intervenção', 'nota' => $redacao['competencia_5'] ?? null]
                                                    ];
                                                    
                                                    $tem_competencia = false;
                                                    foreach ($competencias as $comp) {
                                                        if ($comp['nota'] !== null && $comp['nota'] !== '') {
                                                            $tem_competencia = true;
                                                            break;
                                                        }
                                                    }
                                                ?>
                                                
                                                <?php if ($tem_competencia): ?>
                                                    <div>
                                                        <h5 class="text-sm font-semibold text-gray-700 mb-3 uppercase tracking-wide">Competências:</h5>
                                                        <div class="space-y-3">
                                                            <?php foreach ($competencias as $num => $comp): ?>
                                                                <?php if ($comp['nota'] !== null && $comp['nota'] !== ''): ?>
                                                                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                                                                        <div class="flex justify-between items-center mb-2">
                                                                            <span class="text-sm font-medium text-gray-900">
                                                                                Competência <?= $num ?>
                                                                            </span>
                                                                            <span class="text-sm font-bold text-blue-600">
                                                                                <?= $comp['nota'] ?>/200
                                                                            </span>
                                                                        </div>
                                                                        <div class="text-xs text-gray-600 mb-2">
                                                                            <?= safe_htmlspecialchars($comp['nome'] ?? null, '') ?>
                                                                        </div>
                                                                        <?php if ($feedback && isset($feedback["competencia_$num"]['explicacao'])): ?>
                                                                            <div class="text-xs text-gray-700 bg-white border border-gray-200 p-2 rounded mt-2">
                                                                                <?= nl2br(safe_htmlspecialchars($feedback["competencia_$num"]['explicacao'] ?? null, '')) ?>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                <?php endif; ?>
                                                            <?php endforeach; ?>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>

                                                <?php if (!empty($redacao['corrigida_em'])): ?>
                                                    <div class="text-xs text-gray-500 text-center pt-2 border-t">
                                                        Corrigida em: <?= !empty($redacao['corrigida_em']) ? date('d/m/Y H:i', strtotime($redacao['corrigida_em'])) : 'N/A' ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Ocorrências -->
            <div id="content-ocorrencias" class="tab-content hidden">
                <div class="flex items-center justify-between mb-4 flex-wrap gap-3">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Ocorrências do aluno</h3>
                        <p class="text-sm text-gray-500"><?= count($ocorrencias ?? []) ?> registros</p>
                    </div>
                    <?php if (!empty($admin_permissions['ocorrencias']['cadastrar']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))): ?>
                    <a href="<?= URL ?>/admin/ocorrencias/nova?aluno_id=<?= (int) ($student['id'] ?? 0) ?>"
                       class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                        <i class="fa-solid fa-plus mr-2"></i> Nova ocorrência
                    </a>
                    <?php endif; ?>
                </div>
                <?php if (!empty($ocorrencias)): ?>
                <div class="flex flex-wrap gap-3 mb-4">
                    <select id="filtroOcStatus" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="filtrarOcorrenciasAluno()">
                        <option value="">Todos os status</option>
                        <option value="aberta">Aberta</option>
                        <option value="em_acompanhamento">Em acompanhamento</option>
                        <option value="encerrada">Encerrada</option>
                    </select>
                    <select id="filtroOcCategoria" class="px-3 py-2 border border-gray-300 rounded-lg text-sm" onchange="filtrarOcorrenciasAluno()">
                        <option value="">Todas as categorias</option>
                        <?php
                        $catsTab = [];
                        foreach ($ocorrencias as $ocCat) {
                            $n = trim((string) ($ocCat['categoria_nome'] ?? ''));
                            if ($n !== '') { $catsTab[$n] = $n; }
                        }
                        foreach ($catsTab as $n): ?>
                            <option value="<?= htmlspecialchars($n) ?>"><?= htmlspecialchars($n) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <?php if (empty($ocorrencias)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <p class="text-gray-500">Nenhuma ocorrência registrada.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4" id="listaOcorrenciasAluno">
                        <?php foreach ($ocorrencias as $oc): ?>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 oc-card"
                                 data-status="<?= htmlspecialchars((string) ($oc['status'] ?? '')) ?>"
                                 data-categoria="<?= htmlspecialchars((string) ($oc['categoria_nome'] ?? '')) ?>">
                                <div class="flex items-center justify-between">
                                    <div>
                                        <h4 class="text-base font-semibold text-gray-900"><?= safe_htmlspecialchars($oc['titulo'] ?? '', '') ?></h4>
                                        <p class="text-xs text-gray-500 mt-1"><?= date('d/m/Y H:i', strtotime($oc['data_ocorrencia'])) ?></p>
                                    </div>
                                    <span class="text-xs font-semibold px-2 py-1 rounded-full bg-gray-100 text-gray-700">
                                        <?= ucfirst($oc['nivel_gravidade'] ?? '') ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-700 mt-3"><?= safe_htmlspecialchars($oc['detalhe'] ?? '', '') ?></p>
                                <div class="text-xs text-gray-500 mt-3 flex flex-wrap gap-4">
                                    <?php if (!empty($oc['categoria_nome'])): ?>
                                    <div>Categoria: <?= safe_htmlspecialchars($oc['categoria_nome'], '') ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($oc['status'])): ?>
                                    <div>Status: <?= safe_htmlspecialchars($oc['status'], '') ?></div>
                                    <?php endif; ?>
                                    <div>Pais: <?= !empty($oc['enviar_pais']) ? 'Sim' : 'Não' ?></div>
                                    <div>Registrado por: <?= safe_htmlspecialchars($oc['criado_por_nome'] ?? 'Admin', '') ?></div>
                                    <?php if (!empty($oc['id'])): ?>
                                    <a href="<?= URL ?>/admin/ocorrencias/<?= (int) $oc['id'] ?>" class="text-purple-700 hover:underline">Abrir registro</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <script>
                    function filtrarOcorrenciasAluno() {
                        var st = (document.getElementById('filtroOcStatus') || {}).value || '';
                        var cat = (document.getElementById('filtroOcCategoria') || {}).value || '';
                        document.querySelectorAll('#listaOcorrenciasAluno .oc-card').forEach(function (el) {
                            var okSt = !st || el.getAttribute('data-status') === st;
                            var okCat = !cat || el.getAttribute('data-categoria') === cat;
                            el.classList.toggle('hidden', !(okSt && okCat));
                        });
                    }
                    </script>
                <?php endif; ?>
            </div>

            <!-- Tab: Jornadas -->
            <div id="content-jornadas" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Jornadas feitas</h3>
                    <span class="text-sm text-gray-500"><?= count($jornadas_feitas ?? []) ?> jornadas concluídas</span>
                </div>
                <?php if (empty($jornadas_feitas)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <p class="text-gray-500">Nenhuma jornada concluída.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Jornada</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data conclusão</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Detalhes</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($jornadas_feitas as $jf): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900"><?= safe_htmlspecialchars($jf['titulo'] ?? '', '—') ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?= !empty($jf['data_conclusao']) ? date('d/m/Y H:i', strtotime($jf['data_conclusao'])) : '—' ?></td>
                                        <td class="px-4 py-2 text-sm">
                                            <?php if (!empty($jf['id']) && !empty($student['id'])): ?>
                                                <a href="<?= URL ?>/admin/jornadas/<?= (int)$jf['id'] ?>/aluno/<?= (int)$student['id'] ?>/exercicios"
                                                   class="inline-flex items-center px-3 py-1.5 rounded-lg bg-blue-600 text-white hover:bg-blue-700 text-xs font-medium transition-colors">
                                                    Ver respostas (acertos/erros)
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Tab: Provas -->
            <div id="content-provas" class="tab-content hidden" data-lazy-tab="provas" data-lazy-loaded="0">
                <div class="text-center py-12 text-gray-400">
                    <i class="fa-solid fa-spinner fa-spin mr-2"></i> Carregando provas...
                </div>
            </div>

            <!-- Tab: Notas -->
            <div id="content-notas-eventos" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Eventos de Notas</h3>
                    <span class="text-sm text-gray-500"><?= count($boletim_eventos_notas) ?> evento(s)</span>
                </div>

                <?php if (empty($boletim_eventos_notas)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-gray-500">Nenhum evento de notas visível para coordenação.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-3">
                        <?php foreach ($boletim_eventos_notas as $ev): ?>
                            <?php
                                $ridNota = (int)($ev['id'] ?? 0);
                                $geradoNota = $ridNota > 0 ? ($boletins_gerados_notas_por_regra[$ridNota] ?? null) : null;
                                $updatedFmt = !empty($ev['updated_at']) ? date('d/m/Y H:i', strtotime((string)$ev['updated_at'])) : '-';
                            ?>
                            <div class="border border-gray-200 rounded-lg p-4 bg-white">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-base font-semibold text-gray-900"><?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento') ?></div>
                                        <div class="text-sm text-gray-600">
                                            <?php $bimestreNota = $ev['bimestre'] ?? null; ?>
                                            <?php $anoNota = $ev['ano_letivo'] ?? null; ?>
                                            Bimestre: <?= $bimestreNota ? ((int) $bimestreNota . 'º') : 'N/A' ?>
                                            | Ano: <?= $anoNota ? (int) $anoNota : 'N/A' ?>
                                            | Atualizado: <?= safe_htmlspecialchars($updatedFmt, '-') ?>
                                        </div>
                                    </div>
                                    <?php if (is_array($geradoNota) && !empty($geradoNota['linhas'])): ?>
                                        <div class="flex items-center gap-2">
                                            <button
                                                type="button"
                                                class="px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700"
                                                data-notas-title="<?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento de Notas') ?>"
                                                onclick="abrirModalNotasEvento('modal-notas-evento-<?= $ridNota ?>', this)">
                                                Abrir notas
                                            </button>
                                            <button
                                                type="button"
                                                class="px-3 py-2 bg-emerald-600 text-white text-sm rounded-lg hover:bg-emerald-700"
                                                data-notas-title="<?= safe_htmlspecialchars($ev['nome'] ?? '', 'Evento de Notas') ?>"
                                                onclick="imprimirNotasEvento('modal-notas-evento-<?= $ridNota ?>', this)">
                                                Imprimir
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-xs text-amber-700">Sem tabela gerada no banco para este evento.</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (is_array($geradoNota) && !empty($geradoNota['linhas'])): ?>
                                <div id="modal-notas-evento-<?= $ridNota ?>" class="hidden">
                                    <?php
                                    $boletinsGeradosBackup = $boletins_gerados;
                                    $boletimPodeExcluirBackup = $boletim_pode_excluir ?? false;
                                    $boletimAlunoIdBackup = $boletim_aluno_id ?? 0;
                                    $boletins_gerados = [$geradoNota];
                                    $boletim_pode_excluir = false;
                                    $boletim_aluno_id = 0;
                                    require __DIR__ . '/../../partials/boletins_gerados.php';
                                    $boletins_gerados = $boletinsGeradosBackup;
                                    $boletim_pode_excluir = $boletimPodeExcluirBackup;
                                    $boletim_aluno_id = $boletimAlunoIdBackup;
                                    ?>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

            </div>

            <!-- Modal genérico de abrir/imprimir conteúdo (usado pelas abas Provas e Notas) -->
            <div id="modal-notas-evento" class="hidden fixed inset-0 z-50 p-4 sm:p-6">
                <div class="absolute inset-0 bg-black/50" onclick="fecharModalNotasEvento()"></div>
                <div class="relative bg-white rounded-xl border border-gray-200 shadow-xl max-w-6xl mx-auto h-[85vh] flex flex-col overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-200 flex items-center justify-between">
                        <h4 id="modal-notas-evento-title" class="text-base font-semibold text-gray-900">Notas do evento</h4>
                        <div class="flex items-center gap-2">
                            <button type="button" class="px-3 py-1.5 text-sm rounded-md bg-emerald-600 hover:bg-emerald-700 text-white" onclick="imprimirNotasModalAtual()">Imprimir</button>
                            <button type="button" class="px-3 py-1.5 text-sm rounded-md bg-gray-200 hover:bg-gray-300 text-gray-700" onclick="fecharModalNotasEvento()">Fechar</button>
                        </div>
                    </div>
                    <div id="modal-notas-evento-body" class="w-full flex-1 overflow-y-auto p-4 bg-gray-50"></div>
                </div>
            </div>

            <!-- Tab: Boletim -->
            <div id="content-boletim-eventos" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h2 class="text-3xl font-bold text-gray-900">Boletim</h2>
                    <?php if (!empty($boletins_gerados)): ?>
                        <a href="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/boletim/pdf"
                           class="inline-flex items-center gap-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg font-medium text-sm shadow-sm">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 20h16" />
                            </svg>
                            Baixar PDF
                        </a>
                    <?php endif; ?>
                </div>

                <?php if (empty($boletins_gerados)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
                        <p class="text-gray-500">Nenhum boletim gerado para este aluno ainda.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $boletim_pode_excluir = (bool) ($boletim_pode_excluir ?? false);
                    $boletim_aluno_id = (int) ($student['id'] ?? 0);
                    $boletim_csrf_token = (string) ($csrf_token ?? '');
                    require __DIR__ . '/../../partials/boletins_gerados.php';
                    ?>

                    <?php
                    $obsConteudo = (string) (($boletim_observacao['conteudo'] ?? '') ?: '');
                    $obsTokenInit = htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8');
                    ?>
                    <div id="boletim-observacao-block"
                         class="mt-6 rounded-xl border border-gray-200 bg-white p-5"
                         data-aluno-id="<?= (int) ($student['id'] ?? 0) ?>"
                         data-csrf-token="<?= $obsTokenInit ?>"
                         data-endpoint="<?= URL ?>/admin/students/<?= (int) ($student['id'] ?? 0) ?>/boletim/observacao">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="text-base font-semibold text-gray-900">Observação</h3>
                            <button type="button"
                                    id="btn-editar-observacao"
                                    class="<?= $obsConteudo === '' ? 'hidden' : '' ?> text-sm text-indigo-600 hover:text-indigo-700 font-medium">
                                Editar
                            </button>
                        </div>

                        <div id="observacao-view" class="<?= $obsConteudo === '' ? 'hidden' : '' ?>">
                            <p id="observacao-texto" class="text-sm text-gray-800 whitespace-pre-wrap break-words"><?= htmlspecialchars($obsConteudo, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>

                        <div id="observacao-edit" class="<?= $obsConteudo === '' ? '' : 'hidden' ?> space-y-3">
                            <textarea id="observacao-textarea"
                                      rows="5"
                                      maxlength="5000"
                                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 text-sm"
                                      placeholder="Escreva uma observação que ficará no boletim e no PDF…"><?= htmlspecialchars($obsConteudo, ENT_QUOTES, 'UTF-8') ?></textarea>
                            <div class="flex items-center gap-2">
                                <button type="button"
                                        id="btn-salvar-observacao"
                                        class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-medium hover:opacity-90">
                                    Salvar
                                </button>
                                <button type="button"
                                        id="btn-cancelar-observacao"
                                        class="<?= $obsConteudo === '' ? 'hidden' : '' ?> px-4 py-2 bg-gray-200 hover:bg-gray-300 text-gray-800 rounded-lg text-sm font-medium">
                                    Cancelar
                                </button>
                                <span id="observacao-status" class="text-xs text-gray-500"></span>
                            </div>
                        </div>
                    </div>

                    <script>
                    (function () {
                        var block = document.getElementById('boletim-observacao-block');
                        if (!block) return;
                        var viewEl = document.getElementById('observacao-view');
                        var editEl = document.getElementById('observacao-edit');
                        var textoEl = document.getElementById('observacao-texto');
                        var taEl = document.getElementById('observacao-textarea');
                        var btnEditar = document.getElementById('btn-editar-observacao');
                        var btnSalvar = document.getElementById('btn-salvar-observacao');
                        var btnCancelar = document.getElementById('btn-cancelar-observacao');
                        var statusEl = document.getElementById('observacao-status');
                        var endpoint = block.getAttribute('data-endpoint') || '';
                        var csrf = block.getAttribute('data-csrf-token') || '';
                        var ultimoSalvo = (textoEl && textoEl.textContent) ? textoEl.textContent : '';

                        function entrarEdicao() {
                            if (viewEl) viewEl.classList.add('hidden');
                            if (editEl) editEl.classList.remove('hidden');
                            if (btnEditar) btnEditar.classList.add('hidden');
                            if (btnCancelar) btnCancelar.classList.toggle('hidden', ultimoSalvo.trim() === '');
                            if (taEl) {
                                taEl.value = ultimoSalvo;
                                taEl.focus();
                            }
                        }

                        function sairEdicao() {
                            if (textoEl) textoEl.textContent = ultimoSalvo;
                            var temConteudo = ultimoSalvo.trim() !== '';
                            if (viewEl) viewEl.classList.toggle('hidden', !temConteudo);
                            if (editEl) editEl.classList.toggle('hidden', temConteudo);
                            if (btnEditar) btnEditar.classList.toggle('hidden', !temConteudo);
                            if (btnCancelar) btnCancelar.classList.toggle('hidden', !temConteudo);
                        }

                        function salvar() {
                            if (!taEl) return;
                            var conteudo = taEl.value || '';
                            statusEl.textContent = 'Salvando…';
                            statusEl.classList.remove('text-red-600');
                            statusEl.classList.add('text-gray-500');
                            var form = new FormData();
                            form.append('_token', csrf);
                            form.append('conteudo', conteudo);
                            fetch(endpoint, {
                                method: 'POST',
                                credentials: 'same-origin',
                                headers: { 'X-CSRF-Token': csrf, 'Accept': 'application/json' },
                                body: form,
                            }).then(function (resp) {
                                return resp.json().then(function (data) { return { ok: resp.ok, data: data }; });
                            }).then(function (res) {
                                if (!res.ok || !res.data || res.data.success !== true) {
                                    var msg = (res.data && res.data.error) ? res.data.error : 'Falha ao salvar.';
                                    statusEl.textContent = msg;
                                    statusEl.classList.remove('text-gray-500');
                                    statusEl.classList.add('text-red-600');
                                    return;
                                }
                                ultimoSalvo = (res.data.conteudo !== undefined ? String(res.data.conteudo) : conteudo);
                                statusEl.textContent = 'Salvo.';
                                sairEdicao();
                                setTimeout(function () { statusEl.textContent = ''; }, 1800);
                            }).catch(function (err) {
                                statusEl.textContent = 'Falha de rede.';
                                statusEl.classList.remove('text-gray-500');
                                statusEl.classList.add('text-red-600');
                                console.error(err);
                            });
                        }

                        if (btnEditar) btnEditar.addEventListener('click', entrarEdicao);
                        if (btnSalvar) btnSalvar.addEventListener('click', salvar);
                        if (btnCancelar) btnCancelar.addEventListener('click', sairEdicao);
                    })();
                    </script>
                <?php endif; ?>
            </div>

            <!-- Tab: Acessos -->
            <div id="content-acessos" class="tab-content hidden">
                <div class="flex items-center justify-between mb-6">
                    <h3 class="text-xl font-bold text-gray-900">Histórico de acesso</h3>
                    <span class="text-sm text-gray-500"><?= count($historico_acesso ?? []) ?> acessos</span>
                </div>
                <?php if (empty($historico_acesso)): ?>
                    <div class="text-center py-12 bg-gray-50 rounded-lg">
                        <p class="text-gray-500">Nenhum registro de acesso (logins com sucesso) encontrado para o RA deste aluno.</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto border border-gray-200 rounded-lg">
                        <table class="min-w-full divide-y divide-gray-200">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data e hora</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">IP</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($historico_acesso as $ha): ?>
                                    <tr>
                                        <td class="px-4 py-2 text-sm text-gray-900"><?= !empty($ha['created_at']) ? date('d/m/Y H:i:s', strtotime($ha['created_at'])) : '—' ?></td>
                                        <td class="px-4 py-2 text-sm text-gray-600"><?= safe_htmlspecialchars($ha['ip_address'] ?? '', '—') ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>
