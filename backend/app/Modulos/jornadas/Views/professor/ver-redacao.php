<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Ver Redação - <?= htmlspecialchars($redacao['aluno_nome']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($jornada['titulo']) ?> • 
                <?= htmlspecialchars($redacao['tema_sugerido'] ?? 'Tema não definido') ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/exercicios-alunos" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/redacao/<?= $redacao['redacao_id'] ?>/corrigir" 
               class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Corrigir
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Redação do Aluno -->
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Redação do Aluno</h3>
        
        <div class="mb-4 p-3 bg-gray-50 rounded-lg">
            <p class="text-sm text-gray-600"><strong>Aluno:</strong> <?= htmlspecialchars($redacao['aluno_nome']) ?></p>
            <?php if ($redacao['aluno_ra']): ?>
                <p class="text-sm text-gray-600"><strong>RA:</strong> <?= htmlspecialchars($redacao['aluno_ra']) ?></p>
            <?php endif; ?>
            <p class="text-sm text-gray-600"><strong>Versão:</strong> <?= $redacao['versao'] ?? 1 ?></p>
            <p class="text-sm text-gray-600"><strong>Entregue em:</strong> <?= date('d/m/Y H:i', strtotime($redacao['created_at'])) ?></p>
            <?php if ($redacao['tempo_escrita']): ?>
                <p class="text-sm text-gray-600"><strong>Tempo de escrita:</strong> <?= gmdate('H:i:s', $redacao['tempo_escrita']) ?></p>
            <?php endif; ?>
        </div>
        
        <style>
            .redacao-pautada { background-color: #fefefe; background-image: repeating-linear-gradient( transparent 0px, transparent 27px, rgba(203, 213, 225, 0.6) 27px, rgba(203, 213, 225, 0.6) 28px ); line-height: 28px; }
        </style>
        <div class="prose max-w-none">
            <div class="redacao-pautada whitespace-pre-wrap text-gray-800 border border-gray-200 rounded-lg px-4 py-4 min-h-[400px]">
                <?= htmlspecialchars($redacao['conteudo'] ?? $redacao['texto'] ?? '') ?>
            </div>
        </div>
    </div>
    
    <!-- Correção -->
    <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Correção</h3>
        
        <!-- Notas Finais -->
        <?php 
        // Calcular nota da IA se existir
        $notaIAFinal = 0;
        if ($feedback_ia) {
            for ($i = 1; $i <= 5; $i++) {
                if (isset($feedback_ia["competencia_{$i}"]['nota'])) {
                    $notaIAFinal += (int)$feedback_ia["competencia_{$i}"]['nota'];
                }
            }
        }
        
        $notaProfessorFinal = $redacao['nota_final_professor'] ?? 0;
        $usarMedia = $redacao['usar_media_notas'] ?? 0;
        $notaMedia = $redacao['nota_media'] ?? null;
        ?>
        
        <div class="mb-6 grid grid-cols-1 md:grid-cols-3 gap-4">
            <!-- Nota Final (Média) - aparece apenas se usar média -->
            <?php if ($usarMedia && $notaMedia !== null): ?>
                <div class="bg-yellow-50 border-2 border-yellow-400 rounded-lg p-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-yellow-700 mb-1">
                            <?= (int)$notaMedia ?>/1000
                        </div>
                        <div class="text-sm text-yellow-800 font-semibold">Nota Final (Média)</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Nota do Professor -->
            <?php if ($notaProfessorFinal > 0): ?>
                <div class="bg-green-50 border-2 border-green-300 rounded-lg p-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600 mb-1">
                            <?= (int)$notaProfessorFinal ?>/1000
                        </div>
                        <div class="text-sm text-green-800 font-semibold">Nota do Professor</div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Nota da Tudinha (IA) -->
            <?php if ($notaIAFinal > 0): ?>
                <div class="bg-blue-50 border-2 border-blue-300 rounded-lg p-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600 mb-1">
                            <?= (int)$notaIAFinal ?>/1000
                        </div>
                        <div class="text-sm text-blue-800 font-semibold">Nota da Tudinha (IA)</div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Competências (IA e Professor juntas) -->
        <div class="space-y-4 mb-6">
            <?php 
            $competencias = [
                1 => 'Domínio da norma padrão da Língua Portuguesa',
                2 => 'Compreensão da proposta e desenvolvimento do tema',
                3 => 'Seleção e organização de argumentos',
                4 => 'Coesão e coerência',
                5 => 'Proposta de intervenção'
            ];
            
            foreach ($competencias as $num => $nome): 
                // Extrair dados da IA
                $notaIA = null;
                $explicacaoIA = '';
                if ($feedback_ia && isset($feedback_ia["competencia_{$num}"])) {
                    $notaIA = $feedback_ia["competencia_{$num}"]['nota'] ?? null;
                    $explicacaoIA = $feedback_ia["competencia_{$num}"]['explicacao'] ?? '';
                    if (is_array($explicacaoIA)) {
                        $explicacaoIA = implode("\n", $explicacaoIA);
                    }
                }
                
                // Extrair dados do professor
                $notaProfessor = $redacao["competencia_{$num}"] ?? null;
                $explicacaoProfessor = $redacao["competencia_{$num}_explicacao_professor"] ?? '';
                
                // Mostrar se tiver IA ou professor
                if ($notaIA !== null || $notaProfessor !== null || $explicacaoIA || $explicacaoProfessor):
            ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="mb-3">
                        <label class="text-sm font-semibold text-gray-900">
                            Competência <?= $num ?>
                        </label>
                        <p class="text-xs text-gray-600 mt-1"><?= htmlspecialchars($nome) ?></p>
                    </div>
                    
                    <?php if ($notaIA !== null || $explicacaoIA): ?>
                    <!-- Correção da IA (SEMPRE EM CIMA) -->
                    <div class="mb-4 p-4 bg-blue-50 border-2 border-blue-300 rounded-lg">
                        <div class="mb-3">
                            <span class="text-sm font-bold text-blue-900 uppercase">Correção Tudinha (IA)</span>
                        </div>
                        <?php if ($notaIA !== null): ?>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm font-medium text-blue-800">Nota:</span>
                                <span class="text-xl font-bold text-blue-600"><?= $notaIA ?>/200</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($explicacaoIA): ?>
                            <div class="mt-3 pt-3 border-t border-blue-200">
                                <p class="text-xs font-semibold text-blue-900 mb-2">Explicação:</p>
                                <p class="text-sm text-blue-800 leading-relaxed"><?= nl2br(htmlspecialchars($explicacaoIA)) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <?php if ($notaProfessor !== null || $explicacaoProfessor): ?>
                    <!-- Correção do Professor (SEMPRE EM BAIXO) -->
                    <div class="p-4 bg-gray-50 border-2 border-gray-300 rounded-lg">
                        <div class="mb-3">
                            <span class="text-sm font-bold text-gray-900 uppercase">Sua Correção (Professor)</span>
                        </div>
                        <?php if ($notaProfessor !== null): ?>
                            <div class="flex justify-between items-center mb-3">
                                <span class="text-sm font-medium text-gray-800">Nota:</span>
                                <span class="text-xl font-bold text-green-600"><?= $notaProfessor ?>/200</span>
                            </div>
                        <?php endif; ?>
                        <?php if ($explicacaoProfessor): ?>
                            <div class="mt-3 pt-3 border-t border-gray-300">
                                <p class="text-xs font-semibold text-gray-900 mb-2">Explicação:</p>
                                <p class="text-sm text-gray-800 leading-relaxed"><?= nl2br(htmlspecialchars($explicacaoProfessor)) ?></p>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            <?php 
                endif;
            endforeach; ?>
        </div>
        
        <!-- Comentários Gerais e Sugestões -->
        <div class="space-y-4">
            <?php if ($feedback_ia && isset($feedback_ia['comentarios_gerais'])): ?>
                <div class="mb-4">
                    <h5 class="text-sm font-semibold text-gray-900 mb-2">Comentários Gerais (IA)</h5>
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-sm text-gray-700">
                            <?php 
                            $comentarios = $feedback_ia['comentarios_gerais'];
                            if (is_array($comentarios)) {
                                echo nl2br(htmlspecialchars(implode("\n", $comentarios)));
                            } else {
                                echo nl2br(htmlspecialchars((string)$comentarios));
                            }
                            ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($redacao['comentarios_gerais_professor']): ?>
                <div class="mb-4">
                    <h5 class="text-sm font-semibold text-gray-900 mb-2">Comentários Gerais (Professor)</h5>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                        <p class="text-sm text-gray-700">
                            <?= nl2br(htmlspecialchars($redacao['comentarios_gerais_professor'])) ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if ($redacao['sugestoes_melhoria_professor']): ?>
                <div class="mb-4">
                    <h5 class="text-sm font-semibold text-gray-900 mb-2">Sugestões de Melhoria (Professor)</h5>
                    <div class="bg-green-50 border border-green-200 rounded-lg p-3">
                        <p class="text-sm text-gray-700">
                            <?= nl2br(htmlspecialchars($redacao['sugestoes_melhoria_professor'])) ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!$feedback_ia && !($redacao['nota_final_professor'] ?? $redacao['nota_final'] ?? 0)): ?>
            <div class="text-center py-8 bg-gray-50 rounded-lg border border-gray-200">
                <p class="text-gray-600 mb-4">Esta redação ainda não foi corrigida.</p>
                <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/redacao/<?= $redacao['redacao_id'] ?>/corrigir" 
                   class="inline-block px-6 py-3 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition-colors">
                    Corrigir Agora
                </a>
            </div>
        <?php else: ?>
            <!-- Opção para permitir refazer redação -->
            <div class="mt-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
                <form method="POST" action="<?= URL ?>/professor/jornadas/permitir-refazer-redacao" id="formPermitirRefazer">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="redacao_id" value="<?= $redacao['redacao_id'] ?? $redacao['id'] ?>">
                    <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?>">
                    
                    <label class="flex items-start cursor-pointer">
                        <input 
                            type="checkbox" 
                            name="permitir_refazer" 
                            value="1" 
                            id="permitirRefazer"
                            <?= ($redacao['permitir_refazer'] ?? 0) ? 'checked' : '' ?>
                            onchange="document.getElementById('formPermitirRefazer').submit()"
                            class="mt-1 mr-3 w-5 h-5 text-blue-600 border-gray-300 rounded focus:ring-blue-500"
                        >
                        <div class="flex-1">
                            <div class="text-sm font-semibold text-gray-900 mb-1">
                                Permitir que o aluno refaça esta redação
                            </div>
                            <div class="text-xs text-gray-600">
                                Se marcado, o aluno poderá criar uma nova versão da redação após ver a correção. A redação anterior será mantida no histórico.
                            </div>
                        </div>
                    </label>
                </form>
            </div>
        <?php endif; ?>
    </div>
</div>

