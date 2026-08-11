<div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
    <div class="mb-6">
        <a href="<?= URL ?>/exercicios-personalizados/minhas-listas" class="text-purple-600 hover:text-purple-800 flex items-center mb-4">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
            Voltar
        </a>
        <h1 class="text-3xl font-bold text-gray-900 mb-2">📚 Histórico</h1>
        <p class="text-gray-600"><?= htmlspecialchars($lista['titulo']) ?> • <?= htmlspecialchars($lista['materia']) ?></p>
    </div>

    <?php if (empty($sessoes)): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <svg class="mx-auto h-24 w-24 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <h3 class="mt-4 text-xl font-semibold text-gray-900">Nenhuma sessão finalizada ainda</h3>
            <p class="mt-2 text-gray-600">Finalize uma execução para ver o histórico</p>
        </div>
    <?php else: ?>
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <!-- Lista de Sessões -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-xl shadow-lg p-6">
                    <h2 class="text-lg font-semibold text-gray-900 mb-4">Execuções Realizadas</h2>
                    <div class="space-y-2">
                        <?php 
                        // Calcular estatísticas de cada sessão no controller e passar para a view
                        require_once 'app/Core/Database.php';
                        $db = Database::getInstance();
                        
                        foreach ($sessoes as $sessao_item): 
                            $respostas_sessao = $db->fetchAll(
                                "SELECT is_correct FROM listas_personalizadas_respostas 
                                 WHERE sessao_id = :sessao_id",
                                ['sessao_id' => $sessao_item['id']]
                            );
                            $total_sessao = count($respostas_sessao);
                            $corretas_sessao = count(array_filter($respostas_sessao, fn($r) => $r['is_correct'] == 1));
                            $percentual_sessao = $total_sessao > 0 ? round(($corretas_sessao / $total_sessao) * 100, 0) : 0;
                        ?>
                            <a href="<?= URL ?>/exercicios-personalizados/historico?lista_id=<?= $lista['id'] ?>&sessao_id=<?= $sessao_item['id'] ?>" 
                               class="block p-4 rounded-lg border-2 <?= $sessao_item['id'] == $sessaoId ? 'border-purple-500 bg-purple-50' : 'border-gray-200 hover:border-purple-300 hover:bg-gray-50' ?> transition-colors">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-sm font-medium text-gray-900">
                                        <?= date('d/m/Y', strtotime($sessao_item['started_at'])) ?>
                                    </span>
                                    <span class="text-xs px-2 py-1 rounded-full <?= $percentual_sessao >= 70 ? 'bg-green-100 text-green-800' : ($percentual_sessao >= 50 ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                        <?= $percentual_sessao ?>%
                                    </span>
                                </div>
                                <div class="text-xs text-gray-600">
                                    <?= date('H:i', strtotime($sessao_item['started_at'])) ?> • <?= $corretas_sessao ?>/<?= $total_sessao ?> acertos
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Detalhes da Sessão Selecionada -->
            <div class="lg:col-span-2">
                <?php if ($sessao_selecionada && !empty($questoes)): ?>
                    <?php 
                    $total_q = count($questoes);
                    $corretas_q = count(array_filter($questoes, fn($q) => $q['is_correct'] == 1));
                    $erradas_q = $total_q - $corretas_q;
                    $percentual_q = $total_q > 0 ? round(($corretas_q / $total_q) * 100, 1) : 0;
                    ?>
                    
                    <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
                        <h2 class="text-xl font-semibold text-gray-900 mb-4">📊 Resumo da Sessão</h2>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-3xl font-bold text-blue-600"><?= $total_q ?></div>
                                <div class="text-sm text-gray-600">Total</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-green-600"><?= $corretas_q ?></div>
                                <div class="text-sm text-gray-600">Corretas</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold text-red-600"><?= $erradas_q ?></div>
                                <div class="text-sm text-gray-600">Incorretas</div>
                            </div>
                            <div class="text-center">
                                <div class="text-3xl font-bold <?= $percentual_q >= 70 ? 'text-green-600' : ($percentual_q >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                                    <?= $percentual_q ?>%
                                </div>
                                <div class="text-sm text-gray-600">Acertos</div>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-600">
                            <p>Data: <?= date('d/m/Y H:i', strtotime($sessao_selecionada['started_at'])) ?></p>
                        </div>
                    </div>

                    <div class="space-y-4">
                        <?php foreach ($questoes as $index => $questao): ?>
                            <div class="bg-white rounded-lg shadow p-6 border-l-4 <?= $questao['is_correct'] ? 'border-green-500' : 'border-red-500' ?>">
                                <div class="flex items-center justify-between mb-3">
                                    <h3 class="text-lg font-semibold text-gray-900">Questão <?= $index + 1 ?></h3>
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full <?= $questao['is_correct'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                        <?php if ($questao['is_correct']): ?>
                                            ✅ Correta
                                        <?php else: ?>
                                            ❌ Incorreta
                                        <?php endif; ?>
                                    </span>
                                </div>
                                
                                <p class="text-gray-700 mb-4"><?= nl2br(htmlspecialchars($questao['pergunta'])) ?></p>
                                
                                <div class="bg-gray-50 rounded-lg p-4 mb-4">
                                    <p class="text-sm font-medium text-gray-700 mb-2">Sua resposta:</p>
                                    <p class="text-gray-900"><strong><?= htmlspecialchars($questao['resposta_escolhida']) ?>)</strong> <?= htmlspecialchars($questao['alternativa_' . strtolower($questao['resposta_escolhida'])]) ?></p>
                                </div>
                                
                                <div class="bg-green-50 rounded-lg p-4">
                                    <p class="text-sm font-medium text-green-800 mb-2">Resposta correta:</p>
                                    <p class="text-green-900"><strong><?= htmlspecialchars($questao['resposta_correta']) ?>)</strong> <?= htmlspecialchars($questao['alternativa_' . strtolower($questao['resposta_correta'])]) ?></p>
                                </div>
                                
                                <?php if (!empty($questao['explicacao'])): ?>
                                <div class="mt-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                                    <p class="text-sm text-blue-900"><?= nl2br(htmlspecialchars($questao['explicacao'])) ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                        <p class="text-gray-600">Selecione uma execução ao lado para ver os detalhes</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

