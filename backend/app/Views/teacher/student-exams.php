<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Provas Semanais 📝</h1>
            <p class="text-gray-600 mt-2">Histórico de provas do aluno <?= htmlspecialchars($aluno['nome'] ?? '') ?></p>
        </div>
        <div class="flex items-center space-x-4">
            <a href="<?= URL ?>/professor/student/<?= $aluno['id'] ?>" class="px-4 py-2 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
                ← Voltar ao Aluno
            </a>
        </div>
    </div>
</div>

<!-- Student Info Card -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex items-center space-x-4">
        <div class="w-16 h-16 bg-gradient-to-br from-blue-500 to-blue-600 rounded-full flex items-center justify-center shadow-lg">
            <span class="text-white font-bold text-xl">
                <?= strtoupper(substr($aluno['nome'] ?? '', 0, 2)) ?>
            </span>
        </div>
        <div>
            <h2 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($aluno['nome'] ?? '') ?></h2>
            <p class="text-gray-600"><?= htmlspecialchars($aluno['email'] ?? '') ?></p>
            <span class="inline-block mt-2 px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                <?= htmlspecialchars($aluno['turma_nome'] ?? '') ?>
            </span>
        </div>
    </div>
</div>

<!-- Provas List -->
<?php if (empty($provas)): ?>
    <div class="bg-white rounded-xl shadow-lg p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma prova encontrada</h3>
        <p class="text-gray-600">Este aluno ainda não possui provas atribuídas à sua turma.</p>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($provas as $prova): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow border border-gray-200">
                <!-- Header da Prova -->
                <div class="mb-4">
                    <h3 class="text-lg font-bold text-gray-900 mb-2"><?= htmlspecialchars($prova['titulo']) ?></h3>
                    <?php if ($prova['materia_nome']): ?>
                        <span class="inline-block px-2 py-1 bg-purple-100 text-purple-800 text-xs font-medium rounded-full">
                            <?= htmlspecialchars($prova['materia_nome']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                
                <!-- Informações da Prova -->
                <div class="space-y-2 mb-4">
                    <?php if ($prova['data_inicio']): ?>
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Início: <?= date('d/m/Y', strtotime($prova['data_inicio'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($prova['data_fim']): ?>
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            Fim: <?= date('d/m/Y', strtotime($prova['data_fim'])) ?>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex items-center text-sm text-gray-600">
                        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        Valor: <?= number_format($prova['valor_total'], 2, ',', '.') ?> pontos
                    </div>
                </div>
                
                <!-- Status da Realização -->
                <div class="mb-4 pt-4 border-t border-gray-200">
                    <?php if ($prova['realizacao_id']): ?>
                        <div class="space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-600">Status:</span>
                                <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                    <?= $prova['realizacao_status'] === 'finalizado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= ucfirst($prova['realizacao_status']) ?>
                                </span>
                            </div>
                            
                            <?php if ($prova['realizacao_nota'] !== null): ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Nota:</span>
                                    <span class="text-lg font-bold text-gray-900">
                                        <?= number_format($prova['realizacao_nota'], 2, ',', '.') ?>
                                    </span>
                                </div>
                                
                                <?php 
                                $percentual = ($prova['realizacao_nota'] / $prova['valor_total']) * 100;
                                $cor = $percentual >= 70 ? 'green' : ($percentual >= 50 ? 'yellow' : 'red');
                                ?>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Percentual:</span>
                                    <span class="px-2 py-1 text-xs font-semibold rounded-full bg-<?= $cor ?>-100 text-<?= $cor ?>-800">
                                        <?= number_format($percentual, 1) ?>%
                                    </span>
                                </div>
                                
                                <?php if ($prova['tempo_gasto']): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Tempo:</span>
                                        <span class="text-sm font-medium text-gray-900">
                                            <?= $prova['tempo_gasto'] ?> min
                                        </span>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($prova['finalizado_em']): ?>
                                    <div class="flex items-center justify-between">
                                        <span class="text-sm text-gray-600">Finalizada em:</span>
                                        <span class="text-sm text-gray-900">
                                            <?= date('d/m/Y H:i', strtotime($prova['finalizado_em'])) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-2">
                            <span class="text-sm text-gray-500">Prova não iniciada</span>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Ações -->
                <div class="pt-4 border-t border-gray-200">
                    <?php if ($prova['realizacao_id'] && $prova['realizacao_status'] === 'finalizado'): ?>
                        <a href="<?= URL ?>/professor/provas/resultado-aluno/<?= $prova['id'] ?>/<?= $aluno['id'] ?>" 
                           class="block w-full bg-blue-600 text-white text-center py-2.5 rounded-lg hover:bg-blue-700 transition-colors font-medium">
                            Ver Resultado Completo
                        </a>
                    <?php elseif ($prova['realizacao_id'] && $prova['realizacao_status'] === 'iniciado'): ?>
                        <div class="text-center py-2">
                            <span class="text-sm text-yellow-600 font-medium">Prova em andamento</span>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-2">
                            <span class="text-sm text-gray-500">Aguardando início</span>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

