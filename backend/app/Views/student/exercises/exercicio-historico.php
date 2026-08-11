<?php
/**
 * View: Histórico de Respostas de Exercícios
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-6">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">
                    📚 Histórico de Respostas
                </h1>
                <p class="text-gray-600">
                    <?= htmlspecialchars($lista['titulo']) ?> • 
                    <?= htmlspecialchars($lista['materia']) ?>
                </p>
            </div>
            
            <div class="text-right">
                <div class="text-sm text-gray-600">
                    Executado em: <?= date('d/m/Y H:i', strtotime($sessao['started_at'])) ?>
                </div>
                <?php if ($sessao['finished_at']): ?>
                <div class="text-sm text-gray-600">
                    Finalizado em: <?= date('d/m/Y H:i', strtotime($sessao['finished_at'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Resumo da Execução -->
    <div class="mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">📊 Resumo da Execução</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                <div class="text-center">
                    <div class="text-2xl font-bold text-blue-600"><?= count($questoes) ?></div>
                    <div class="text-sm text-gray-600">Total de Questões</div>
                </div>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-green-600">
                        <?php 
                        $corretas = 0;
                        foreach ($respostas as $resposta) {
                            if ($resposta['is_correct']) $corretas++;
                        }
                        echo $corretas;
                        ?>
                    </div>
                    <div class="text-sm text-gray-600">Respostas Corretas</div>
                </div>
                
                <div class="text-center">
                    <div class="text-2xl font-bold text-red-600">
                        <?= count($questoes) - $corretas ?>
                    </div>
                    <div class="text-sm text-gray-600">Respostas Incorretas</div>
                </div>
                
                <div class="text-center">
                    <div class="text-2xl font-bold <?= ($corretas / count($questoes) * 100) >= 70 ? 'text-green-600' : (($corretas / count($questoes) * 100) >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                        <?= number_format(($corretas / count($questoes)) * 100, 1) ?>%
                    </div>
                    <div class="text-sm text-gray-600">Percentual de Acerto</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Histórico Completo -->
    <?php if (!empty($historico_completo)): ?>
    <div class="mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">📈 Histórico Completo</h2>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Acertos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Percentual</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tempo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($historico_completo as $index => $h): ?>
                        <tr class="<?= $h['sessao_id'] == $sessao['id'] ? 'bg-blue-50' : '' ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= date('d/m/Y H:i', strtotime($h['data_execucao'])) ?>
                                <?php if ($h['sessao_id'] == $sessao['id']): ?>
                                <span class="ml-2 px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">Atual</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $h['questoes_corretas'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $h['questoes_total'] ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $h['percentual_acerto'] >= 70 ? 'bg-green-100 text-green-800' : ($h['percentual_acerto'] >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                    <?= number_format($h['percentual_acerto'], 1) ?>%
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?= gmdate('H:i:s', $h['tempo_total']) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $h['status'] == 'finalizado' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                    <?= ucfirst($h['status']) ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Detalhes das Questões -->
    <div class="mb-8">
        <div class="bg-white rounded-lg shadow-md p-6 border border-gray-200">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">❓ Detalhes das Questões</h2>
            
            <div class="space-y-6">
                <?php foreach ($questoes as $index => $questao): ?>
                <?php $resposta = $respostas[$questao['id']] ?? null; ?>
                <div class="border border-gray-200 rounded-lg p-6 <?= $resposta && $resposta['is_correct'] ? 'bg-green-50 border-green-200' : ($resposta ? 'bg-red-50 border-red-200' : 'bg-gray-50') ?>">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center">
                            <span class="text-lg font-semibold text-gray-700 mr-3"><?= $index + 1 ?>.</span>
                            <?php if ($resposta): ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium <?= $resposta['is_correct'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                    <?= $resposta['is_correct'] ? '✅ Correta' : '❌ Incorreta' ?>
                                </span>
                            <?php else: ?>
                                <span class="px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                    ⚪ Não respondida
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($resposta): ?>
                        <div class="text-sm text-gray-600">
                            Tempo: <?= gmdate('i:s', $resposta['tempo_resposta']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mb-4">
                        <p class="text-gray-800 font-medium"><?= htmlspecialchars($questao['pergunta']) ?></p>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 mb-4">
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
                        
                        foreach ($alternativas as $letra => $texto): 
                        ?>
                        <div class="flex items-center p-3 rounded-lg border <?= $letra == $questao['resposta_correta'] ? 'bg-green-100 border-green-300' : ($resposta && $resposta['resposta'] == $letra ? 'bg-red-100 border-red-300' : 'bg-gray-100 border-gray-300') ?>">
                            <span class="font-semibold mr-3 <?= $letra == $questao['resposta_correta'] ? 'text-green-700' : ($resposta && $resposta['resposta'] == $letra ? 'text-red-700' : 'text-gray-700') ?>">
                                <?= $letra ?>
                            </span>
                            <span class="<?= $letra == $questao['resposta_correta'] ? 'text-green-800' : ($resposta && $resposta['resposta'] == $letra ? 'text-red-800' : 'text-gray-800') ?>">
                                <?= htmlspecialchars($texto) ?>
                            </span>
                            <?php if ($letra == $questao['resposta_correta']): ?>
                                <span class="ml-auto text-green-600">✓</span>
                            <?php elseif ($resposta && $resposta['resposta'] == $letra): ?>
                                <span class="ml-auto text-red-600">✗</span>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <?php if (!empty($questao['explicacao'])): ?>
                    <div class="mt-4 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                        <h4 class="font-semibold text-blue-800 mb-2">💡 Explicação:</h4>
                        <p class="text-blue-700"><?= htmlspecialchars($questao['explicacao']) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Botões de Ação -->
    <div class="flex justify-between items-center pt-6 border-t border-gray-200">
        <a href="<?= URL ?>/exercicios" 
           class="px-6 py-3 bg-gray-500 text-white rounded-lg hover:bg-gray-600 transition-colors">
            ← Voltar para Exercícios
        </a>
        
        <div class="flex gap-3">
            <?php if (count($historico_completo) > 0): ?>
            <a href="<?= URL ?>/exercicios/iniciar?id=<?= $lista['id'] ?>" 
               class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                🔄 Refazer Exercício
            </a>
            <?php endif; ?>
            
            <a href="<?= URL ?>/exercicios/resultado?id=<?= $sessao['id'] ?>" 
               class="px-6 py-3 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                📊 Ver Resultado Completo
            </a>
        </div>
    </div>
</div>
