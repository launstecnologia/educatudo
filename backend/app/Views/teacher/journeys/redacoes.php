<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Redações - <?= htmlspecialchars($jornada['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?>
                <?php if (!empty($jornada['turma_nome'])): ?>
                    • <?= htmlspecialchars($jornada['turma_nome']) ?>
                <?php endif; ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<!-- Lista de Redações -->
<?php if (empty($redacoes_jornada)): ?>
    <div class="bg-white rounded-xl shadow-lg p-12 text-center">
        <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
        </svg>
        <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma redação encontrada</h3>
        <p class="text-gray-600">Ainda não há temas de redação configurados para esta jornada.</p>
    </div>
<?php else: ?>
    <div class="space-y-6">
        <?php foreach ($redacoes_jornada as $redacaoJornada): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <!-- Tema da Redação -->
                <div class="mb-6">
                    <h3 class="text-xl font-bold text-gray-900 mb-2">
                        <?= htmlspecialchars($redacaoJornada['tema_sugerido']) ?>
                    </h3>
                    <?php if ($redacaoJornada['descricao_tema']): ?>
                        <p class="text-gray-600 mb-2"><?= nl2br(htmlspecialchars($redacaoJornada['descricao_tema'])) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Redações dos Alunos -->
                <?php if (empty($redacaoJornada['redacoes_alunos'])): ?>
                    <div class="bg-gray-50 rounded-lg p-4 text-center">
                        <p class="text-gray-500">Nenhum aluno entregou esta redação ainda.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-900 mb-3">Redações dos Alunos (<?= count($redacaoJornada['redacoes_alunos']) ?>)</h4>
                        <?php foreach ($redacaoJornada['redacoes_alunos'] as $redacaoAluno): ?>
                            <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                                <div class="flex items-start justify-between mb-3">
                                    <div class="flex-1">
                                        <h5 class="font-semibold text-gray-900">
                                            <?= htmlspecialchars($redacaoAluno['aluno_nome']) ?>
                                            <?php if ($redacaoAluno['aluno_ra']): ?>
                                                <span class="text-sm text-gray-500">(RA: <?= htmlspecialchars($redacaoAluno['aluno_ra']) ?>)</span>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="text-sm text-gray-600 mt-1">
                                            Versão <?= $redacaoAluno['versao'] ?> • 
                                            Entregue em: <?= date('d/m/Y H:i', strtotime($redacaoAluno['created_at'])) ?>
                                        </p>
                                    </div>
                                    <div class="ml-4">
                                        <?php
                                        $statusClass = [
                                            'rascunho' => 'bg-gray-100 text-gray-800',
                                            'entregue' => 'bg-yellow-100 text-yellow-800',
                                            'corrigida_ia' => 'bg-blue-100 text-blue-800',
                                            'corrigida_professor' => 'bg-green-100 text-green-800',
                                            'retornada' => 'bg-orange-100 text-orange-800',
                                            'aprovada' => 'bg-green-100 text-green-800'
                                        ];
                                        $statusText = [
                                            'rascunho' => 'Rascunho',
                                            'entregue' => 'Aguardando Correção',
                                            'corrigida_ia' => 'Corrigida pela IA',
                                            'corrigida_professor' => 'Corrigida pelo Professor',
                                            'retornada' => 'Retornada',
                                            'aprovada' => 'Aprovada'
                                        ];
                                        $status = $redacaoAluno['status'] ?? 'rascunho';
                                        ?>
                                        <span class="px-3 py-1 text-sm rounded-full <?= $statusClass[$status] ?? 'bg-gray-100 text-gray-800' ?>">
                                            <?= $statusText[$status] ?? ucfirst($status) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-2 mt-4">
                                    <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/redacao/<?= $redacaoAluno['redacao_id'] ?>/corrigir" 
                                       class="px-4 py-2 bg-blue-500 text-white rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors">
                                        <?= in_array($status, ['entregue', 'corrigida_ia']) ? 'Corrigir' : 'Ver Correção' ?>
                                    </a>
                                    <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/redacao/<?= $redacaoAluno['redacao_id'] ?>/ver" 
                                       class="px-4 py-2 bg-gray-500 text-white rounded-lg text-sm font-medium hover:bg-gray-600 transition-colors">
                                        Ver Redação
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

