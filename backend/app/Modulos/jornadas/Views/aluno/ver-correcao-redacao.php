<?php 
// Verificar se há correção do professor (definir antes de usar no header)
$temCorrecaoProfessor = ($redacao['nota_final_professor'] ?? $redacao['nota_final'] ?? 0) > 0 || 
                       ($redacao['competencia_1'] ?? 0) > 0 || 
                       ($redacao['competencia_2'] ?? 0) > 0 ||
                       ($redacao['competencia_3'] ?? 0) > 0 ||
                       ($redacao['competencia_4'] ?? 0) > 0 ||
                       ($redacao['competencia_5'] ?? 0) > 0;
?>
<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($redacao['titulo'] ?? $redacao['tema_sugerido'] ?? 'Redação da Jornada') ?></h1>
                    <p class="text-gray-600 mt-1">Tema: <?= htmlspecialchars($redacao['tema_sugerido'] ?? 'Tema não definido') ?></p>
                </div>
                <div class="flex gap-3">
                    <?php 
                    $versaoAtual = $versao_atual ?? ($redacao['versao'] ?? 1);
                    $temVersaoPosterior = $temVersaoPosterior ?? false;
                    if ($temCorrecaoProfessor && ($redacao['permitir_refazer'] ?? 0) == 1 && $versaoAtual == 1 && !$temVersaoPosterior): 
                    ?>
                        <form method="POST" action="<?= URL ?>/jornadas/refazer-redacao" class="inline">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <input type="hidden" name="jornada_id" value="<?= $jornada_id ?>">
                            <input type="hidden" name="redacao_id" value="<?= $redacao['redacao_id'] ?? $redacao['id'] ?? '' ?>">
                            <button type="submit" 
                                    class="px-4 py-2 bg-orange-500 hover:bg-orange-600 text-white rounded-lg transition-colors font-medium"
                                    onclick="return confirm('Deseja refazer esta redação? Você poderá ver a correção e escrever uma nova versão. A redação anterior será mantida no histórico.')">
                                <i class="fas fa-redo mr-2"></i>Refazer Redação
                            </button>
                        </form>
                    <?php endif; ?>
                    <a href="<?= URL ?>/jornadas/<?= $jornada_id ?>" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <!-- Redação -->
            <div class="bg-white rounded-lg shadow-sm p-6">
                <h2 class="text-lg font-semibold text-gray-900 mb-4">Sua Redação</h2>
                <div class="prose max-w-none">
                    <div class="whitespace-pre-wrap text-gray-800 leading-relaxed">
                        <?= htmlspecialchars($redacao['conteudo'] ?? $redacao['texto'] ?? '') ?>
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
                <?php 
                // Verificar se deve mostrar correção da IA
                $mostrarCorrecaoIA = ($redacao['mostrar_correcao_ia_aluno'] ?? 0) == 1;
                
                // Verificar quais competências do professor devem ser mostradas
                // Mostrar apenas as competências que o professor marcou para mostrar ao aluno
                $competenciasProfessor = [];
                for ($i = 1; $i <= 5; $i++) {
                    $mostrarAoAluno = ($redacao["mostrar_competencia_{$i}_aluno"] ?? 0) == 1;
                    $nota = $redacao["competencia_{$i}"] ?? 0;
                    // Mostrar apenas se o professor marcou para mostrar E tiver nota
                    if ($mostrarAoAluno && $nota > 0) {
                        $competenciasProfessor[] = $i;
                    }
                }
                ?>
                
                <?php if ($temCorrecaoProfessor): ?>
                    <!-- Correção do Professor -->
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Correção do Professor</h2>
                    
                    <?php 
                    // Debug: Log dos valores para verificar
                    error_log("DEBUG - usar_media_notas: " . ($redacao['usar_media_notas'] ?? 'null') . 
                             ", nota_final_utilizada: " . ($redacao['nota_final_utilizada'] ?? 'null') . 
                             ", nota_final_professor: " . ($redacao['nota_final_professor'] ?? 'null') . 
                             ", nota_final: " . ($redacao['nota_final'] ?? 'null'));
                    
                    // Verificar se o professor escolheu usar a média
                    $usarMedia = isset($redacao['usar_media_notas']) && ((int)$redacao['usar_media_notas'] == 1);
                    
                    // Determinar qual nota mostrar
                    if ($usarMedia && isset($redacao['nota_final_utilizada']) && (float)$redacao['nota_final_utilizada'] > 0) {
                        // Se escolheu usar média, mostrar a nota final utilizada (que é a média)
                        $notaFinal = (float)$redacao['nota_final_utilizada'];
                        $labelNota = 'Nota Final (Média)';
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("DEBUG - Usando média: {$notaFinal}");
                        }
                    } elseif (isset($redacao['nota_final_professor']) && (float)$redacao['nota_final_professor'] > 0) {
                        // Se não escolheu usar média, mostrar a nota do professor
                        $notaFinal = (float)$redacao['nota_final_professor'];
                        $labelNota = 'Nota Final';
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("DEBUG - Usando nota do professor: {$notaFinal}");
                        }
                    } else {
                        // Fallback para nota_final caso não tenha nota_final_professor
                        $notaFinal = isset($redacao['nota_final']) ? (float)$redacao['nota_final'] : 0;
                        $labelNota = 'Nota Final';
                        if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true') {
                            error_log("DEBUG - Usando nota_final (fallback): {$notaFinal}");
                        }
                    }
                    
                    if ($notaFinal > 0): 
                    ?>
                        <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-6">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-green-600 mb-2">
                                    <?= number_format($notaFinal, 0) ?>/1000
                                </div>
                                <div class="text-sm text-green-800"><?= htmlspecialchars($labelNota) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Competências do Professor -->
                    <?php if (!empty($competenciasProfessor)): ?>
                        <div class="space-y-4 mb-6">
                            <h3 class="text-md font-semibold text-gray-900">Competências</h3>
                            <?php 
                            $competencias = [
                                1 => 'Domínio da norma padrão da Língua Portuguesa',
                                2 => 'Compreensão da proposta e desenvolvimento do tema',
                                3 => 'Seleção e organização de argumentos',
                                4 => 'Coesão e coerência',
                                5 => 'Proposta de intervenção'
                            ];
                            
                            foreach ($competenciasProfessor as $num): 
                                $nota = $redacao["competencia_{$num}"] ?? 0;
                                $explicacao = $redacao["competencia_{$num}_explicacao_professor"] ?? 
                                           $redacao["explicacao_{$num}"] ?? null;
                                
                                if ($nota > 0): ?>
                                    <div class="border border-gray-200 rounded-lg p-3">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-900">
                                                Competência <?= $num ?>
                                            </span>
                                            <span class="text-sm font-bold text-green-600">
                                                <?= $nota ?>/200
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-600 mb-2">
                                            <?= htmlspecialchars($competencias[$num]) ?>
                                        </div>
                                        <?php if ($explicacao): ?>
                                            <div class="text-xs text-gray-700 bg-gray-50 p-2 rounded mt-2">
                                                <?php 
                                                if (is_array($explicacao)) {
                                                    echo nl2br(htmlspecialchars(implode("\n", $explicacao)));
                                                } else {
                                                    echo nl2br(htmlspecialchars((string)$explicacao));
                                                }
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif;
                            endforeach; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($redacao['comentarios_gerais'] ?? $redacao['comentarios_gerais_professor'] ?? null): ?>
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-900 mb-2">Comentários Gerais</h3>
                            <div class="bg-gray-50 border border-gray-200 rounded-lg p-3">
                                <p class="text-sm text-gray-700">
                                    <?= nl2br(htmlspecialchars($redacao['comentarios_gerais'] ?? $redacao['comentarios_gerais_professor'])) ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($redacao['sugestoes_melhoria'] ?? $redacao['sugestoes_melhoria_professor'] ?? null): ?>
                        <div class="mb-4">
                            <h3 class="text-md font-semibold text-gray-900 mb-2">Sugestões de Melhoria</h3>
                            <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                                <p class="text-sm text-gray-700">
                                    <?= nl2br(htmlspecialchars($redacao['sugestoes_melhoria'] ?? $redacao['sugestoes_melhoria_professor'])) ?>
                                </p>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Correção da IA (se o professor permitiu) -->
                    <?php if ($mostrarCorrecaoIA && $feedback_ia): ?>
                        <div class="mt-8 pt-6 border-t border-gray-300">
                            <h2 class="text-lg font-semibold text-gray-900 mb-4">Correção Tudinha (Referência)</h2>
                            
                            <?php if (isset($feedback_ia['nota_final'])): ?>
                                <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
                                    <div class="text-center">
                                        <div class="text-3xl font-bold text-blue-600 mb-2">
                                            <?= number_format($feedback_ia['nota_final'], 0) ?>/1000
                                        </div>
                                        <div class="text-sm text-blue-800">Nota Final (Correção Tudinha)</div>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <div class="space-y-4">
                                <?php 
                                for ($i = 1; $i <= 5; $i++): 
                                    if (isset($feedback_ia["competencia_{$i}"])):
                                        $compIA = $feedback_ia["competencia_{$i}"];
                                        $notaIA = $compIA['nota'] ?? 0;
                                        $explicacaoIA = $compIA['explicacao'] ?? null;
                                ?>
                                    <div class="border border-blue-200 rounded-lg p-3 bg-blue-50">
                                        <div class="flex justify-between items-center mb-2">
                                            <span class="text-sm font-medium text-gray-900">
                                                Competência <?= $i ?> (Correção Tudinha)
                                            </span>
                                            <span class="text-sm font-bold text-blue-600">
                                                <?= $notaIA ?>/200
                                            </span>
                                        </div>
                                        <div class="text-xs text-gray-600 mb-2">
                                            <?= htmlspecialchars($competencias[$i]) ?>
                                        </div>
                                        <?php if ($explicacaoIA): ?>
                                            <div class="text-xs text-gray-700 bg-white p-2 rounded mt-2">
                                                <?php 
                                                if (is_array($explicacaoIA)) {
                                                    echo nl2br(htmlspecialchars(implode("\n", $explicacaoIA)));
                                                } else {
                                                    echo nl2br(htmlspecialchars((string)$explicacaoIA));
                                                }
                                                ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php 
                                    endif;
                                endfor; 
                                ?>
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

