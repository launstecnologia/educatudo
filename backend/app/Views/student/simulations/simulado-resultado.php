<?php
/**
 * View: Resultado do Simulado ENEM
 */
?>

<div class="container mx-auto px-4 py-6">
    <!-- Header -->
    <div class="mb-8">
        <div class="flex items-center justify-between">
            <div>
                <h1 class="text-3xl font-bold text-gray-900 mb-2">
                    🏆 Resultado do Simulado ENEM <?= $simulado['ano'] ?>
                </h1>
                <p class="text-gray-600">
                    Finalizado em <?= date('d/m/Y H:i', strtotime($simulado['finalizado_em'])) ?>
                </p>
            </div>
            <a href="<?= URL ?>/simulados" 
               class="bg-blue-600 text-white px-6 py-3 rounded-lg hover:bg-blue-700 transition-colors">
                ← Voltar para Simulados
            </a>
        </div>
    </div>

    <!-- Resumo Geral -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 text-center">
            <div class="text-4xl font-bold text-blue-600 mb-2"><?= $simulado['nota_final'] ?></div>
            <div class="text-sm font-medium text-gray-600">Nota Final</div>
            <div class="text-xs text-gray-500 mt-1">(0-1000)</div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 text-center">
            <div class="text-4xl font-bold text-green-600 mb-2"><?= $simulado['total_acertos'] ?></div>
            <div class="text-sm font-medium text-gray-600">Acertos</div>
            <div class="text-xs text-gray-500 mt-1">de <?= $simulado['quantidade_questoes'] ?> questões</div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 text-center">
            <div class="text-4xl font-bold text-red-600 mb-2"><?= $simulado['total_erros'] ?></div>
            <div class="text-sm font-medium text-gray-600">Erros</div>
            <div class="text-xs text-gray-500 mt-1">de <?= $simulado['quantidade_questoes'] ?> questões</div>
        </div>

        <div class="bg-white p-6 rounded-lg shadow-md border border-gray-200 text-center">
            <div class="text-4xl font-bold text-purple-600 mb-2"><?= $simulado['percentual_acerto'] ?>%</div>
            <div class="text-sm font-medium text-gray-600">Taxa de Acerto</div>
            <div class="text-xs text-gray-500 mt-1">Percentual geral</div>
        </div>
    </div>

    <!-- Informações do Simulado -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-8">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">📊 Informações do Simulado</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="font-medium text-gray-600">Ano da Prova:</span>
                <span class="ml-2 text-gray-900"><?= $simulado['ano'] ?></span>
            </div>
            <div>
                <span class="font-medium text-gray-600">Disciplina:</span>
                <span class="ml-2 text-gray-900"><?= $simulado['disciplina'] ?: 'Todas' ?></span>
            </div>
            <div>
                <span class="font-medium text-gray-600">Tempo Total:</span>
                <span class="ml-2 text-gray-900"><?= gmdate('H:i:s', (int)$simulado['tempo_total']) ?></span>
            </div>
            <div>
                <span class="font-medium text-gray-600">Tempo Limite:</span>
                <span class="ml-2 text-gray-900">
                    <?= $simulado['tempo_limite'] > 0 ? gmdate('H:i:s', (int)$simulado['tempo_limite']) : 'Ilimitado' ?>
                </span>
            </div>
        </div>
    </div>

    <!-- Estatísticas por Matéria -->
    <?php if (!empty($estatisticas_materia)): ?>
        <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6 mb-8">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">📚 Desempenho por Matéria</h2>
            <div class="space-y-4">
                <?php foreach ($estatisticas_materia as $materia): ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-3">
                            <h3 class="font-semibold text-gray-900"><?= $materia['materia'] ?></h3>
                            <span class="text-sm text-gray-600">
                                <?= $materia['acertos'] ?> acertos de <?= $materia['total_questoes'] ?> questões
                            </span>
                        </div>
                        
                        <div class="flex items-center space-x-4">
                            <div class="flex-1">
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-300" 
                                         style="width: <?= $materia['percentual_acerto'] ?>%"></div>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-lg font-bold text-blue-600"><?= $materia['percentual_acerto'] ?>%</div>
                                <div class="text-xs text-gray-500">
                                    <?= gmdate('i:s', (int)$materia['tempo_medio']) ?> média
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Questões Detalhadas -->
    <div class="bg-white rounded-lg shadow-md border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-4">📝 Detalhamento das Questões</h2>
        
        <div class="space-y-6">
            <?php foreach ($questoes as $questao): ?>
                <div class="border border-gray-200 rounded-lg p-6 
                    <?= $questao['acertou'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?>">
                    
                    <!-- Cabeçalho da Questão -->
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">
                            Questão <?= $questao['questao_index'] ?>
                        </h3>
                        <div class="flex items-center space-x-3">
                            <span class="px-3 py-1 bg-blue-100 text-blue-800 text-sm font-medium rounded-full">
                                <?= $questao['materia'] ?: 'Geral' ?>
                            </span>
                            <span class="px-3 py-1 text-sm font-medium rounded-full
                                <?= $questao['acertou'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $questao['acertou'] ? '✅ Acertou' : '❌ Errou' ?>
                            </span>
                        </div>
                    </div>

                    <!-- Enunciado -->
                    <div class="mb-4">
                        <div class="prose max-w-none">
                            <?php
                            require_once __DIR__ . '/../../../Helpers/MarkdownHelper.php';
                            $enunciado = MarkdownHelper::processMarkdown($questao['enunciado']);
                            echo $enunciado;
                            ?>
                        </div>
                    </div>

                    <!-- Alternativas -->
                    <div class="space-y-2">
                        <?php 
                        $alternativas = [
                            'A' => $questao['alternativa_a'],
                            'B' => $questao['alternativa_b'],
                            'C' => $questao['alternativa_c'],
                            'D' => $questao['alternativa_d'],
                            'E' => $questao['alternativa_e']
                        ];
                        ?>
                        
                        <?php foreach ($alternativas as $letra => $texto): ?>
                            <?php 
                            // Verificar se existe arquivo para esta alternativa
                            $arquivo_key = 'alternativa_' . strtolower($letra) . '_file';
                            $arquivo_url = $questao[$arquivo_key] ?? null;
                            ?>
                            <div class="flex items-start p-3 border rounded-lg
                                <?php if ($letra === $questao['resposta_certa']): ?>
                                    bg-green-100 border-green-300
                                <?php elseif ($letra === $questao['resposta_aluno'] && !$questao['acertou']): ?>
                                    bg-red-100 border-red-300
                                <?php else: ?>
                                    bg-gray-50 border-gray-200
                                <?php endif; ?>">
                                
                                <span class="font-medium text-gray-900 mr-3 min-w-[20px]">
                                    <?= $letra ?>) 
                                </span>
                                
                                <div class="flex-1">
                                    <?php if ($arquivo_url && empty($texto)): ?>
                                        <!-- Exibir imagem quando text é NULL mas file existe -->
                                        <img src="<?= htmlspecialchars($arquivo_url) ?>" 
                                             alt="Alternativa <?= $letra ?>" 
                                             class="max-w-full h-auto rounded-lg shadow-sm border border-gray-200"
                                             style="max-height: 400px;"
                                             onerror="this.style.display='none';">
                                    <?php elseif ($arquivo_url && !empty($texto)): ?>
                                        <!-- Exibir ambos texto e imagem -->
                                        <span class="text-gray-700">
                                            <?php
                                            $texto_processado = MarkdownHelper::processMarkdown($texto);
                                            echo $texto_processado;
                                            ?>
                                        </span>
                                        <div class="mt-2">
                                            <img src="<?= htmlspecialchars($arquivo_url) ?>" 
                                                 alt="Alternativa <?= $letra ?>" 
                                                 class="max-w-full h-auto rounded-lg shadow-sm border border-gray-200"
                                                 style="max-height: 400px;"
                                                 onerror="this.style.display='none';">
                                        </div>
                                    <?php else: ?>
                                        <!-- Exibir apenas texto -->
                                        <span class="text-gray-700">
                                            <?php
                                            $texto_processado = MarkdownHelper::processMarkdown($texto);
                                            echo $texto_processado;
                                            ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                
                                <?php if ($letra === $questao['resposta_certa']): ?>
                                    <span class="ml-2 text-green-600 font-bold">✓</span>
                                <?php elseif ($letra === $questao['resposta_aluno'] && !$questao['acertou']): ?>
                                    <span class="ml-2 text-red-600 font-bold">✗</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Informações Adicionais -->
                    <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
                        <div>
                            <span class="font-medium">Resposta Correta:</span> 
                            <span class="font-bold text-green-600"><?= $questao['resposta_certa'] ?></span>
                        </div>
                        <div>
                            <span class="font-medium">Sua Resposta:</span> 
                            <span class="font-bold <?= $questao['acertou'] ? 'text-green-600' : 'text-red-600' ?>">
                                <?= $questao['resposta_aluno'] ?: 'Não respondida' ?>
                            </span>
                        </div>
                        <?php if ($questao['tempo_gasto'] > 0): ?>
                            <div>
                                <span class="font-medium">Tempo:</span> 
                                <span><?= gmdate('i:s', (int)$questao['tempo_gasto']) ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Ações -->
    <div class="mt-8 flex justify-center space-x-4">
        <a href="<?= URL ?>/simulados/criar" 
           class="bg-blue-600 text-white px-8 py-3 rounded-lg hover:bg-blue-700 transition-colors font-medium">
            📝 Fazer Novo Simulado
        </a>
        <button onclick="window.print()" 
                class="bg-gray-600 text-white px-8 py-3 rounded-lg hover:bg-gray-700 transition-colors font-medium">
            🖨️ Imprimir Resultado
        </button>
    </div>
</div>

<!-- Estilos para impressão -->
<style>
@media print {
    .container {
        max-width: none;
        margin: 0;
        padding: 0;
    }
    
    .bg-white {
        background: white !important;
        border: 1px solid #000 !important;
    }
    
    .text-blue-600, .text-green-600, .text-red-600, .text-purple-600 {
        color: #000 !important;
    }
    
    .bg-green-50, .bg-red-50, .bg-blue-50 {
        background: #f9f9f9 !important;
    }
    
    .shadow-md {
        box-shadow: none !important;
    }
}
</style>
