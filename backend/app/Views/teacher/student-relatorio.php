<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Relatório do Aluno 📊</h1>
            <p class="text-gray-600 mt-2">Histórico completo de exercícios, provas e interações de <?= htmlspecialchars($aluno['nome'] ?? '') ?></p>
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

<!-- Estatísticas Gerais -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-xl p-6 border border-blue-200">
        <div class="flex items-center justify-between mb-2">
            <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
            </svg>
        </div>
        <p class="text-sm text-blue-700 font-medium mb-1">Total de Provas</p>
        <p class="text-3xl font-bold text-blue-900"><?= $estatisticas['total_provas'] ?></p>
        <p class="text-xs text-blue-600 mt-1">Média: <?= number_format($estatisticas['media_provas'], 1) ?></p>
    </div>
    
    <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-xl p-6 border border-green-200">
        <div class="flex items-center justify-between mb-2">
            <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <p class="text-sm text-green-700 font-medium mb-1">Exercícios Realizados</p>
        <p class="text-3xl font-bold text-green-900"><?= $estatisticas['total_exercicios'] ?></p>
        <p class="text-xs text-green-600 mt-1">Acertos: <?= $estatisticas['acertos_exercicios'] ?></p>
    </div>
    
    <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-xl p-6 border border-purple-200">
        <div class="flex items-center justify-between mb-2">
            <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
            </svg>
        </div>
        <p class="text-sm text-purple-700 font-medium mb-1">Interações</p>
        <p class="text-3xl font-bold text-purple-900"><?= $estatisticas['total_interacoes'] ?></p>
        <p class="text-xs text-purple-600 mt-1">Mensagens trocadas</p>
    </div>
    
    <div class="bg-gradient-to-br from-orange-50 to-orange-100 rounded-xl p-6 border border-orange-200">
        <div class="flex items-center justify-between mb-2">
            <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
        </div>
        <p class="text-sm text-orange-700 font-medium mb-1">Última Atividade</p>
        <p class="text-lg font-bold text-orange-900">
            <?= $estatisticas['ultima_atividade'] ? date('d/m/Y', strtotime($estatisticas['ultima_atividade'])) : '-' ?>
        </p>
        <p class="text-xs text-orange-600 mt-1">
            <?= $estatisticas['ultima_atividade'] ? date('H:i', strtotime($estatisticas['ultima_atividade'])) : '' ?>
        </p>
    </div>
</div>

<!-- Evolução ao Longo do Tempo -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Evolução e Progresso (Últimos 6 Meses)</h2>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Gráfico de Provas -->
        <div>
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Provas Realizadas</h3>
            <div class="space-y-3">
                <?php foreach ($meses as $mes): ?>
                    <?php 
                    $data = $evolucao[$mes];
                    $mes_nome = date('M/Y', strtotime($mes . '-01'));
                    ?>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600"><?= $mes_nome ?></span>
                            <span class="text-sm font-semibold text-gray-900"><?= $data['provas'] ?> prova(s)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-blue-600 h-2 rounded-full" style="width: <?= min(100, ($data['provas'] / max(1, max(array_column($evolucao, 'provas')))) * 100) ?>%"></div>
                        </div>
                        <?php if ($data['provas'] > 0): ?>
                            <p class="text-xs text-gray-500 mt-1">Média: <?= number_format($data['nota_media'], 1) ?></p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Gráfico de Exercícios -->
        <div>
            <h3 class="text-lg font-semibold text-gray-700 mb-4">Exercícios Realizados</h3>
            <div class="space-y-3">
                <?php foreach ($meses as $mes): ?>
                    <?php 
                    $data = $evolucao[$mes];
                    $mes_nome = date('M/Y', strtotime($mes . '-01'));
                    ?>
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="text-sm text-gray-600"><?= $mes_nome ?></span>
                            <span class="text-sm font-semibold text-gray-900"><?= $data['exercicios'] ?> exercício(s)</span>
                        </div>
                        <div class="w-full bg-gray-200 rounded-full h-2">
                            <div class="bg-green-600 h-2 rounded-full" style="width: <?= min(100, ($data['exercicios'] / max(1, max(array_column($evolucao, 'exercicios')))) * 100) ?>%"></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- Histórico de Provas -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Histórico de Provas</h2>
    <?php if (empty($provas)): ?>
        <p class="text-gray-600 text-center py-8">Nenhuma prova finalizada ainda.</p>
    <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prova</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($provas as $prova): ?>
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($prova['titulo']) ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-600"><?= htmlspecialchars($prova['materia_nome'] ?? '-') ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="text-sm text-gray-600">
                                    <?= $prova['finalizado_em'] ? date('d/m/Y H:i', strtotime($prova['finalizado_em'])) : '-' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if ($prova['realizacao_nota'] !== null): ?>
                                    <?php 
                                    $percentual = (floatval($prova['realizacao_nota']) / floatval($prova['valor_total'])) * 100;
                                    $cor = $percentual >= 70 ? 'green' : ($percentual >= 50 ? 'yellow' : 'red');
                                    ?>
                                    <div class="text-sm font-semibold text-gray-900">
                                        <?= number_format($prova['realizacao_nota'], 2, ',', '.') ?> / <?= number_format($prova['valor_total'], 2, ',', '.') ?>
                                    </div>
                                    <span class="text-xs px-2 py-1 rounded-full bg-<?= $cor ?>-100 text-<?= $cor ?>-800">
                                        <?= number_format($percentual, 1) ?>%
                                    </span>
                                <?php else: ?>
                                    <span class="text-sm text-gray-500">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <a href="<?= URL ?>/professor/provas/resultado-aluno/<?= $prova['id'] ?>/<?= $aluno['id'] ?>" 
                                   class="text-blue-600 hover:text-blue-900">
                                    Ver Detalhes
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Histórico de Exercícios -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Histórico de Exercícios</h2>
    <?php if (empty($exercicios)): ?>
        <p class="text-gray-600 text-center py-8">Nenhum exercício realizado ainda.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach (array_slice($exercicios, 0, 10) as $exercicio): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900">
                                <?= htmlspecialchars($exercicio['jornada_titulo'] ?? 'Jornada') ?>
                            </h3>
                            <?php if ($exercicio['modulo_titulo']): ?>
                                <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($exercicio['modulo_titulo']) ?></p>
                            <?php endif; ?>
                            <?php if ($exercicio['exercicio_titulo']): ?>
                                <p class="text-sm font-medium text-gray-800 mt-2"><?= htmlspecialchars($exercicio['exercicio_titulo']) ?></p>
                            <?php endif; ?>
                            <?php if ($exercicio['exercicio_enunciado']): ?>
                                <p class="text-sm text-gray-700 mt-1"><?= htmlspecialchars($exercicio['exercicio_enunciado']) ?></p>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-2">
                                <?= $exercicio['data_conclusao'] ? date('d/m/Y H:i', strtotime($exercicio['data_conclusao'])) : '-' ?>
                            </p>
                        </div>
                        <div class="ml-4 text-right">
                            <?php
                            $statusEx = JornadaExercicioAvaliacao::classificar(
                                $exercicio['exercicio_tipo'] ?? '',
                                $exercicio['pontuacao'] ?? null,
                                $exercicio['resposta'] ?? '',
                                true
                            );
                            $pendenteEx = $statusEx === JornadaExercicioAvaliacao::STATUS_PENDENTE;
                            $corretoEx = $statusEx === JornadaExercicioAvaliacao::STATUS_ACERTO;
                            ?>
                            <?php if ($pendenteEx): ?>
                                <span class="inline-block px-3 py-1 bg-amber-100 text-amber-800 text-sm font-semibold rounded-full">
                                    Aguardando correção
                                </span>
                            <?php elseif ($corretoEx): ?>
                                <span class="inline-block px-3 py-1 bg-green-100 text-green-800 text-sm font-semibold rounded-full">
                                    ✓ Correto
                                </span>
                            <?php else: ?>
                                <span class="inline-block px-3 py-1 bg-red-100 text-red-800 text-sm font-semibold rounded-full">
                                    ✗ Errado
                                </span>
                            <?php endif; ?>
                            <p class="text-xs text-gray-500 mt-1">
                                <?= $pendenteEx ? 'Pontuação: —' : ('Pontuação: ' . number_format($exercicio['pontuacao'] ?? 0, 1)) ?>
                            </p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($exercicios) > 10): ?>
                <p class="text-center text-gray-600 text-sm">Mostrando 10 de <?= count($exercicios) ?> exercícios</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- Histórico de Interações -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <h2 class="text-xl font-bold text-gray-900 mb-6">Interações e Mensagens</h2>
    <?php if (empty($interacoes)): ?>
        <p class="text-gray-600 text-center py-8">Nenhuma interação registrada ainda.</p>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach (array_slice($interacoes, 0, 10) as $interacao): ?>
                <div class="border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition-colors">
                    <div class="flex items-start justify-between">
                        <div class="flex-1">
                            <h3 class="font-semibold text-gray-900">
                                <?= htmlspecialchars($interacao['jornada_titulo'] ?? 'Jornada') ?>
                            </h3>
                            <p class="text-sm text-gray-700 mt-2"><?= htmlspecialchars($interacao['mensagem']) ?></p>
                            <p class="text-xs text-gray-500 mt-2">
                                <?= date('d/m/Y H:i', strtotime($interacao['created_at'])) ?>
                            </p>
                        </div>
                        <div class="ml-4">
                            <?php if ($interacao['remetente'] === 'aluno'): ?>
                                <span class="inline-block px-3 py-1 bg-blue-100 text-blue-800 text-xs font-semibold rounded-full">
                                    Aluno
                                </span>
                            <?php else: ?>
                                <span class="inline-block px-3 py-1 bg-purple-100 text-purple-800 text-xs font-semibold rounded-full">
                                    Professor
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php if (count($interacoes) > 10): ?>
                <p class="text-center text-gray-600 text-sm">Mostrando 10 de <?= count($interacoes) ?> interações</p>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

