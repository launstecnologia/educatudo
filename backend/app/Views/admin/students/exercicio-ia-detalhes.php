<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center mb-4">
        <a href="<?= URL ?>/admin/students/<?= $sessao['aluno_id'] ?>" class="text-gray-500 hover:text-gray-700 mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Detalhes do Exercício IA 🤖
            </h2>
            <p class="text-gray-600">
                Questões e respostas do aluno
            </p>
        </div>
    </div>
</div>

<!-- Informações do Exercício -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-indigo-200 mb-6">
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Aluno</label>
                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($sessao['aluno_nome'] ?? 'N/A') ?></p>
                <p class="text-sm text-gray-600">RA: <?= htmlspecialchars($sessao['aluno_ra'] ?? 'N/A') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Título do Exercício</label>
                <p class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($sessao['lista_titulo'] ?? 'Exercício IA') ?></p>
                <p class="text-sm text-gray-600">Matéria: <?= htmlspecialchars($sessao['materia'] ?? 'N/A') ?></p>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-500 mb-1">Status</label>
                <span class="inline-block px-3 py-1 text-sm font-semibold rounded-full <?= $sessao['status'] === 'finalizado' ? 'bg-green-100 text-green-800' : ($sessao['status'] === 'em_andamento' ? 'bg-yellow-100 text-yellow-800' : 'bg-gray-100 text-gray-800') ?>">
                    <?= ucfirst($sessao['status'] ?? 'N/A') ?>
                </span>
                <div class="mt-2 text-sm text-gray-600">
                    <p><strong>Iniciado:</strong> <?= date('d/m/Y H:i', strtotime($sessao['started_at'])) ?></p>
                    <?php if ($sessao['finished_at']): ?>
                        <p><strong>Finalizado:</strong> <?= date('d/m/Y H:i', strtotime($sessao['finished_at'])) ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Estatísticas -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
        <div class="text-sm text-blue-600 font-medium mb-1">Total de Questões</div>
        <div class="text-2xl font-bold text-blue-900"><?= count($questoes) ?></div>
    </div>
    <div class="bg-green-50 border border-green-200 rounded-lg p-4">
        <div class="text-sm text-green-600 font-medium mb-1">Acertos</div>
        <div class="text-2xl font-bold text-green-900">
            <?php
                $acertos = 0;
                foreach ($questoes as $q) {
                    if (isset($q['is_correct']) && $q['is_correct'] == 1) {
                        $acertos++;
                    }
                }
                echo $acertos;
            ?>
        </div>
    </div>
    <div class="bg-red-50 border border-red-200 rounded-lg p-4">
        <div class="text-sm text-red-600 font-medium mb-1">Erros</div>
        <div class="text-2xl font-bold text-red-900">
            <?php
                $erros = 0;
                foreach ($questoes as $q) {
                    if (isset($q['resposta_escolhida']) && (!isset($q['is_correct']) || $q['is_correct'] == 0)) {
                        $erros++;
                    }
                }
                echo $erros;
            ?>
        </div>
    </div>
    <div class="bg-purple-50 border border-purple-200 rounded-lg p-4">
        <div class="text-sm text-purple-600 font-medium mb-1">Percentual</div>
        <div class="text-2xl font-bold text-purple-900">
            <?php
                $total = count($questoes);
                $percentual = $total > 0 ? round(($acertos / $total) * 100) : 0;
                echo $percentual . '%';
            ?>
        </div>
    </div>
</div>

<!-- Lista de Questões -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Questões e Respostas</h3>
    </div>
    <div class="p-6">
        <?php if (empty($questoes)): ?>
            <div class="text-center py-12">
                <p class="text-gray-500">Nenhuma questão encontrada</p>
            </div>
        <?php else: ?>
            <div class="space-y-6">
                <?php foreach ($questoes as $index => $questao): ?>
                    <div class="border border-gray-200 rounded-lg p-6 <?= isset($questao['is_correct']) && $questao['is_correct'] == 1 ? 'bg-green-50 border-green-300' : (isset($questao['resposta_escolhida']) ? 'bg-red-50 border-red-300' : 'bg-gray-50') ?>">
                        <!-- Cabeçalho da Questão -->
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex items-center space-x-3">
                                <span class="flex items-center justify-center w-8 h-8 rounded-full bg-indigo-600 text-white font-bold text-sm">
                                    <?= $index + 1 ?>
                                </span>
                                <h4 class="text-lg font-semibold text-gray-900">Questão <?= $index + 1 ?></h4>
                            </div>
                            <div>
                                <?php if (isset($questao['resposta_escolhida'])): ?>
                                    <?php if (isset($questao['is_correct']) && $questao['is_correct'] == 1): ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-800">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                            </svg>
                                            Correta
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-red-100 text-red-800">
                                            <svg class="w-4 h-4 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                            </svg>
                                            Incorreta
                                        </span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-gray-100 text-gray-800">
                                        Não respondida
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Pergunta -->
                        <div class="mb-4">
                            <p class="text-gray-900 font-medium leading-relaxed"><?= nl2br(htmlspecialchars($questao['pergunta'] ?? 'Pergunta não disponível')) ?></p>
                        </div>
                        
                        <!-- Alternativas -->
                        <div class="space-y-2 mb-4">
                            <?php 
                            $alternativas = [
                                'A' => $questao['alternativa_a'] ?? '',
                                'B' => $questao['alternativa_b'] ?? '',
                                'C' => $questao['alternativa_c'] ?? '',
                                'D' => $questao['alternativa_d'] ?? '',
                                'E' => $questao['alternativa_e'] ?? ''
                            ];
                            
                            foreach ($alternativas as $letra => $texto):
                                if (empty($texto)) continue;
                                
                                $isRespostaCorreta = ($questao['resposta_correta'] ?? '') === $letra;
                                $isRespostaAluno = isset($questao['resposta_escolhida']) && $questao['resposta_escolhida'] === $letra;
                                
                                $bgClass = '';
                                $borderClass = '';
                                $textClass = '';
                                
                                if ($isRespostaCorreta) {
                                    $bgClass = 'bg-green-100';
                                    $borderClass = 'border-green-400';
                                    $textClass = 'text-green-900 font-semibold';
                                } elseif ($isRespostaAluno && !$isRespostaCorreta) {
                                    $bgClass = 'bg-red-100';
                                    $borderClass = 'border-red-400';
                                    $textClass = 'text-red-900 font-semibold';
                                } else {
                                    $bgClass = 'bg-white';
                                    $borderClass = 'border-gray-300';
                                    $textClass = 'text-gray-700';
                                }
                            ?>
                                <div class="flex items-start p-3 rounded-lg border-2 <?= $bgClass ?> <?= $borderClass ?>">
                                    <span class="flex-shrink-0 w-8 h-8 rounded-full <?= $isRespostaCorreta ? 'bg-green-600' : ($isRespostaAluno ? 'bg-red-600' : 'bg-gray-400') ?> text-white flex items-center justify-center font-bold text-sm mr-3">
                                        <?= $letra ?>
                                    </span>
                                    <span class="<?= $textClass ?> flex-1"><?= nl2br(htmlspecialchars($texto)) ?></span>
                                    <?php if ($isRespostaCorreta): ?>
                                        <svg class="w-5 h-5 text-green-600 ml-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                        </svg>
                                    <?php elseif ($isRespostaAluno): ?>
                                        <svg class="w-5 h-5 text-red-600 ml-2 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                                        </svg>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <!-- Resumo das Respostas -->
                        <div class="mt-4 pt-4 border-t border-gray-300">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span class="text-gray-600 font-medium">Resposta Correta:</span>
                                    <span class="ml-2 px-2 py-1 bg-green-100 text-green-800 rounded font-semibold">
                                        <?= htmlspecialchars($questao['resposta_correta'] ?? 'N/A') ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="text-gray-600 font-medium">Resposta do Aluno:</span>
                                    <span class="ml-2 px-2 py-1 <?= isset($questao['is_correct']) && $questao['is_correct'] == 1 ? 'bg-green-100 text-green-800' : (isset($questao['resposta_escolhida']) ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?> rounded font-semibold">
                                        <?= isset($questao['resposta_escolhida']) ? htmlspecialchars($questao['resposta_escolhida']) : 'Não respondida' ?>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- Explicação -->
                            <?php if (!empty($questao['explicacao'])): ?>
                                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                                    <p class="text-sm font-medium text-blue-900 mb-1">📚 Explicação:</p>
                                    <p class="text-sm text-blue-800 leading-relaxed"><?= nl2br(htmlspecialchars($questao['explicacao'])) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Nível de Dificuldade -->
                            <?php if (!empty($questao['nivel_dificuldade'])): ?>
                                <div class="mt-2 text-xs text-gray-500">
                                    <span class="font-medium">Nível:</span> 
                                    <span class="capitalize"><?= htmlspecialchars($questao['nivel_dificuldade']) ?></span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Botão Voltar -->
<div class="mt-6">
    <a href="<?= URL ?>/admin/students/<?= $sessao['aluno_id'] ?>" 
       class="inline-flex items-center px-6 py-3 bg-gray-600 text-white font-medium rounded-lg hover:bg-gray-700 transition-colors">
        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
        </svg>
        Voltar para Detalhes do Aluno
    </a>
</div>

