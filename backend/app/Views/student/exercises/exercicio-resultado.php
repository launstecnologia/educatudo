<?php
/**
 * View: Resultado de Exercícios para Alunos
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    📊 Resultado - <?= htmlspecialchars($execucao['titulo']) ?>
                </h1>
                <p class="text-gray-600">
                    <?= htmlspecialchars($execucao['materia']) ?> • 
                    <?= htmlspecialchars($execucao['nivel_dificuldade']) ?>
                </p>
            </div>
            
            <div class="text-right">
                <div class="text-sm text-gray-600 mb-1">Finalizado em:</div>
                <div class="text-lg font-semibold text-gray-900">
                    <?= $execucao['data_fim'] ? date('d/m/Y H:i', strtotime($execucao['data_fim'])) : 'Não finalizado' ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Mensagens de Sucesso/Erro -->
    <?php if (isset($_GET['success'])): ?>
    <div class="mb-4 bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded">
        ✅ <?= htmlspecialchars($_GET['success']) ?>
    </div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
    <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded">
        ❌ <?= htmlspecialchars($_GET['error']) ?>
    </div>
    <?php endif; ?>

    <!-- Resumo Geral -->
    <div class="mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">📈 Resumo Geral</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
                <!-- Nota -->
                <div class="text-center">
                    <div class="text-4xl font-bold 
                        <?php 
                        $percentual = $execucao['percentual_acerto'] ?? 0;
                        if ($percentual >= 80) echo 'text-green-600';
                        elseif ($percentual >= 60) echo 'text-yellow-600';
                        else echo 'text-red-600';
                        ?>">
                        <?= number_format($percentual, 1) ?>%
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Nota Final</div>
                </div>
                
                <!-- Questões Corretas -->
                <div class="text-center">
                    <div class="text-4xl font-bold text-blue-600">
                        <?= ($execucao['questoes_corretas'] ?? 0) ?>/<?= ($execucao['questoes_total'] ?? 0) ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Questões Corretas</div>
                </div>
                
                <!-- Tempo Total -->
                <div class="text-center">
                    <div class="text-4xl font-bold text-purple-600">
                        <?= gmdate('H:i:s', $execucao['tempo_total'] ?? 0) ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Tempo Total</div>
                </div>
                
                <!-- Tempo Médio -->
                <div class="text-center">
                    <div class="text-4xl font-bold text-indigo-600">
                        <?php 
                        $tempo_total = $execucao['tempo_total'] ?? 0;
                        $questoes_total = $execucao['questoes_total'] ?? 0;
                        echo $questoes_total > 0 ? gmdate('i:s', (int)($tempo_total / $questoes_total)) : '00:00';
                        ?>
                    </div>
                    <div class="text-sm text-gray-600 mt-1">Tempo por Questão</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Questões Detalhadas -->
    <div class="mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">📝 Detalhamento das Questões</h2>
        
        <div class="space-y-4">
            <?php foreach ($questoes as $index => $questao): ?>
            <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
                <!-- Cabeçalho da Questão -->
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">
                        Questão <?= $questao['ordem'] ?>
                    </h3>
                    <div class="flex items-center space-x-2">
                        <?php if ($questao['acertou']): ?>
                        <span class="px-3 py-1 bg-green-100 text-green-800 text-sm font-medium rounded-full">
                            ✅ Correta
                        </span>
                        <?php else: ?>
                        <span class="px-3 py-1 bg-red-100 text-red-800 text-sm font-medium rounded-full">
                            ❌ Incorreta
                        </span>
                        <?php endif; ?>
                        
                        <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                            <?= gmdate('i:s', $questao['tempo_gasto']) ?>
                        </span>
                    </div>
                </div>

                <!-- Enunciado -->
                <div class="mb-4">
                    <p class="text-gray-800 text-lg leading-relaxed">
                        <?= nl2br(htmlspecialchars($questao['pergunta'])) ?>
                    </p>
                </div>

                <!-- Alternativas -->
                <div class="space-y-2 mb-4">
                    <?php 
                    $alternativas = [
                        'A' => $questao['alternativa_a'],
                        'B' => $questao['alternativa_b'],
                        'C' => $questao['alternativa_c'],
                        'D' => $questao['alternativa_d']
                    ];
                    
                    if (!empty($questao['alternativa_e'])) {
                        $alternativas['E'] = $questao['alternativa_e'];
                    }
                    ?>
                    
                    <?php foreach ($alternativas as $letra => $texto): ?>
                        <div class="flex items-start p-3 rounded-lg
                            <?php 
                            if ($letra === $questao['resposta_correta']) {
                                echo 'bg-green-50 border border-green-200';
                            } elseif ($letra === $questao['resposta_escolhida'] && !$questao['acertou']) {
                                echo 'bg-red-50 border border-red-200';
                            } else {
                                echo 'bg-gray-50 border border-gray-200';
                            }
                            ?>">
                            
                            <div class="flex items-center mr-3">
                                <?php if ($letra === $questao['resposta_correta']): ?>
                                    <span class="w-6 h-6 bg-green-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                        ✓
                                    </span>
                                <?php elseif ($letra === $questao['resposta_escolhida'] && !$questao['acertou']): ?>
                                    <span class="w-6 h-6 bg-red-500 text-white rounded-full flex items-center justify-center text-sm font-bold">
                                        ✗
                                    </span>
                                <?php else: ?>
                                    <span class="w-6 h-6 bg-gray-300 text-gray-600 rounded-full flex items-center justify-center text-sm font-bold">
                                        <?= $letra ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex-1">
                                <span class="font-medium text-gray-900 mr-2"><?= $letra ?>)</span>
                                <span class="text-gray-700"><?= htmlspecialchars($texto) ?></span>
                                
                                <?php if ($letra === $questao['resposta_correta']): ?>
                                    <span class="ml-2 text-green-600 font-medium">(Resposta Correta)</span>
                                <?php endif; ?>
                                
                                <?php if ($letra === $questao['resposta_escolhida'] && !$questao['acertou']): ?>
                                    <span class="ml-2 text-red-600 font-medium">(Sua Resposta)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Explicação -->
                <?php if (!empty($questao['explicacao'])): ?>
                <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                    <h4 class="font-semibold text-blue-900 mb-2">💡 Explicação:</h4>
                    <p class="text-blue-800"><?= nl2br(htmlspecialchars($questao['explicacao'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Botões de Ação -->
    <div class="flex justify-center space-x-4">
        <a href="<?= URL ?>/exercicios" 
           class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            📚 Voltar aos Exercícios
        </a>
        
        <a href="<?= URL ?>/exercicios/iniciar?id=<?= $execucao['lista_id'] ?>" 
           class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
            🔄 Refazer Exercício
        </a>
    </div>
</div>
