<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($redacao['titulo']) ?></h1>
                    <p class="text-gray-600 mt-1">Tema: <?= htmlspecialchars($redacao['tema']) ?></p>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.location.href='<?= URL ?>/redacoes/historico'" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Redação -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Sua Redação</h2>
                <div class="prose max-w-none">
                    <div class="whitespace-pre-wrap text-gray-800 leading-relaxed">
                        <?= htmlspecialchars($redacao['conteudo']) ?>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <div class="text-sm text-gray-500">
                        <strong>Data de criação:</strong> <?= date('d/m/Y H:i', strtotime($redacao['created_at'])) ?>
                    </div>
                    <?php if ($redacao['corrigida_em']): ?>
                        <div class="text-sm text-gray-500 mt-1">
                            <strong>Corrigida em:</strong> <?= date('d/m/Y H:i', strtotime($redacao['corrigida_em'])) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Correção -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <?php if ($redacao['corrigida_em'] && $feedback): ?>
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Correção ENEM</h2>
                    
                    <!-- Nota Final -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-blue-600 mb-2">
                                <?= $redacao['nota_final'] ?>/1000
                            </div>
                            <div class="text-sm text-blue-800">Nota Final</div>
                        </div>
                    </div>

                    <!-- Competências -->
                    <div class="space-y-4 mb-6">
                        <h3 class="text-md font-semibold text-gray-900">Competências</h3>
                        
                        <?php 
                        $competencias = [
                            1 => ['nome' => 'Domínio da norma padrão da Língua Portuguesa', 'nota' => $redacao['competencia_1']],
                            2 => ['nome' => 'Compreensão da proposta e desenvolvimento do tema', 'nota' => $redacao['competencia_2']],
                            3 => ['nome' => 'Seleção e organização de argumentos', 'nota' => $redacao['competencia_3']],
                            4 => ['nome' => 'Coesão e coerência', 'nota' => $redacao['competencia_4']],
                            5 => ['nome' => 'Proposta de intervenção', 'nota' => $redacao['competencia_5']]
                        ];
                        
                        foreach ($competencias as $num => $comp): 
                            if ($comp['nota']): ?>
                                <div class="border border-gray-200 rounded-lg p-3">
                                    <div class="flex justify-between items-center mb-2">
                                        <span class="text-sm font-medium text-gray-900">
                                            Competência <?= $num ?>
                                        </span>
                                        <span class="text-sm font-bold text-blue-600">
                                            <?= $comp['nota'] ?>/200
                                        </span>
                                    </div>
                                    <div class="text-xs text-gray-600 mb-2">
                                        <?= htmlspecialchars($comp['nome']) ?>
                                    </div>
                                    <?php if (isset($feedback["competencia_$num"]['explicacao'])): ?>
                                        <div class="text-xs text-gray-700 bg-gray-50 p-2 rounded">
                                            <?php 
                                            $explicacao = $feedback["competencia_$num"]['explicacao'];
                                            if (is_array($explicacao)) {
                                                echo htmlspecialchars(implode("\n", $explicacao));
                                            } else {
                                                echo htmlspecialchars((string)$explicacao);
                                            }
                                            ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif;
                        endforeach; ?>
                    </div>

                    <!-- Comentários Gerais -->
                    <?php if (isset($feedback['comentarios_gerais'])): ?>
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-900 mb-2">Comentários Gerais</h3>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                                <p class="text-sm text-gray-700">
                                    <?php 
                                    $comentarios = $feedback['comentarios_gerais'];
                                    if (is_array($comentarios)) {
                                        echo htmlspecialchars(implode("\n", $comentarios));
                                    } else {
                                        echo htmlspecialchars((string)$comentarios);
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Sugestões de Melhoria -->
                    <?php if (isset($feedback['sugestoes_melhoria'])): ?>
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-900 mb-2">Sugestões de Melhoria</h3>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <p class="text-sm text-gray-700">
                                    <?php 
                                    $sugestoes = $feedback['sugestoes_melhoria'];
                                    if (is_array($sugestoes)) {
                                        echo htmlspecialchars(implode("\n", $sugestoes));
                                    } else {
                                        echo htmlspecialchars((string)$sugestoes);
                                    }
                                    ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="text-center py-8">
                        <div class="w-16 h-16 bg-yellow-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-clock text-yellow-600 text-2xl"></i>
                        </div>
                        <h3 class="text-lg font-medium text-gray-900 mb-2">Aguardando Correção</h3>
                        <p class="text-gray-600">Sua redação será corrigida em breve.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
