<!-- Header Section -->
<div class="mb-6 md:mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex-1">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Painel do Professor 👨‍🏫</h1>
            <p class="text-sm md:text-base text-gray-600 mt-2">Bem-vindo, <?= htmlspecialchars($professor['nome']) ?>! Gerencie suas turmas e acompanhe o progresso dos alunos.</p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right">
                <p class="text-xs md:text-sm text-gray-500">Código do Professor</p>
                <p class="text-base md:text-lg font-semibold text-blue-600"><?= htmlspecialchars($professor['codigo_prof']) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-6 md:mb-8">
    <!-- Card Jornadas -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Total de Jornadas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['total_jornadas'] ?></p>
            </div>
            <div class="w-12 h-12 bg-blue-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
            </div>
        </div>
        <div class="space-y-2 pt-4 border-t border-gray-200">
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Aguardando:</span>
                <span class="text-sm font-semibold text-yellow-600"><?= $stats['jornadas_aguardando'] ?? 0 ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Em Andamento:</span>
                <span class="text-sm font-semibold text-blue-600"><?= $stats['jornadas_em_andamento'] ?? 0 ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Concluído:</span>
                <span class="text-sm font-semibold text-green-600"><?= $stats['jornadas_concluidas'] ?? 0 ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Realizou Jornada:</span>
                <span class="text-sm font-semibold text-green-600"><?= $stats['total_realizou_jornada'] ?? 0 ?></span>
            </div>
            <div class="flex justify-between items-center">
                <span class="text-xs text-gray-600">Não realizou Jornada:</span>
                <span class="text-sm font-semibold text-red-600"><?= $stats['total_nao_realizou_jornada'] ?? 0 ?></span>
            </div>
        </div>
    </div>

    <!-- Card Mensagens -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm font-medium text-gray-600">Mensagens de Alunos</p>
                <p class="text-3xl font-bold text-gray-900"><?= $total_mensagens_nao_lidas ?></p>
                <p class="text-xs text-gray-500 mt-1"><?= $total_mensagens_nao_lidas > 0 ? 'não lida(s)' : 'nenhuma pendente' ?></p>
            </div>
            <div class="w-12 h-12 bg-green-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
            </div>
        </div>
        <?php if ($total_mensagens_nao_lidas > 0): ?>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <a href="<?= URL ?>/professor/chat" class="text-sm text-blue-600 hover:text-blue-700 font-medium">
                    Ver conversas →
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Card Turmas -->
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <div class="flex items-center justify-between mb-4">
            <div>
                <p class="text-sm font-medium text-gray-600">Turmas Ativas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['total_turmas'] ?></p>
            </div>
            <div class="w-12 h-12 bg-purple-100 rounded-lg flex items-center justify-center">
                <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
        <?php if (!empty($stats['pontos_atencao'])): ?>
            <div class="pt-4 border-t border-gray-200">
                <div class="flex items-center mb-2">
                    <svg class="w-5 h-5 text-yellow-500 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                    </svg>
                    <p class="text-xs font-semibold text-gray-700">Pontos de Atenção:</p>
                </div>
                <?php foreach ($stats['pontos_atencao'] as $ponto): ?>
                    <?php if ($ponto['tipo'] === 'alunos_pendentes'): ?>
                        <div class="space-y-1">
                            <?php foreach (array_slice($ponto['dados'], 0, 3) as $aluno): ?>
                                <div class="flex justify-between items-center text-xs">
                                    <span class="text-gray-600 truncate"><?= htmlspecialchars($aluno['nome']) ?></span>
                                    <span class="text-red-600 font-semibold ml-2"><?= $aluno['total_pendentes'] ?> pend.</span>
                                </div>
                            <?php endforeach; ?>
                            <?php if (count($ponto['dados']) > 3): ?>
                                <p class="text-xs text-gray-500 mt-1">+<?= count($ponto['dados']) - 3 ?> mais...</p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="pt-4 border-t border-gray-200">
                <p class="text-xs text-gray-500">Nenhum ponto de atenção</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Main Content Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    <!-- Jornadas Recentes -->
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Jornadas Recentes</h2>
                <a href="<?= URL ?>/professor/jornadas" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                    Ver todas
                </a>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($jornadas_recentes)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                    </svg>
                    <p class="text-gray-500">Nenhuma jornada criada ainda</p>
                    <p class="text-sm text-gray-400">Crie sua primeira jornada para começar</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($jornadas_recentes as $jornada): ?>
                        <?php 
                        // Determina status da jornada (prioriza status_jornada calculado, senão usa status)
                        if ($jornada['status'] === 'pausada') {
                            $statusExibir = 'pausada';
                        } else {
                            $statusExibir = $jornada['status_jornada'] ?? $jornada['status'] ?? 'ativa';
                        }
                        
                        // Define cores e labels do status
                        $statusLabels = [
                            'aguardando' => 'Aguardando',
                            'em_andamento' => 'Em Andamento',
                            'concluido' => 'Concluído',
                            'ativa' => 'Ativa',
                            'pausada' => 'Pausada',
                            'finalizada' => 'Finalizada'
                        ];
                        
                        $statusColors = [
                            'aguardando' => 'bg-yellow-100 text-yellow-800',
                            'em_andamento' => 'bg-blue-100 text-blue-800',
                            'concluido' => 'bg-green-100 text-green-800',
                            'ativa' => 'bg-green-100 text-green-800',
                            'pausada' => 'bg-amber-700 text-white',
                            'finalizada' => 'bg-gray-100 text-gray-800'
                        ];
                        
                        $status_label = $statusLabels[$statusExibir] ?? ucfirst($statusExibir);
                        $status_color = $statusColors[$statusExibir] ?? 'bg-blue-100 text-blue-800';
                        
                        // Mesmo formato do detalhe da jornada: X turmas (1°A, ...) • Y alunos
                        $turmasNomes = $jornada['turmas_selecionadas_nomes'] ?? [$jornada['turma_nome'] ?? ''];
                        $totalTurmas = count($turmasNomes);
                                        $totalAlunos = (int)($jornada['visualizado'] ?? 0) + (int)($jornada['nao_visualizado'] ?? 0) + (int)($jornada['concluido'] ?? 0);
                        $turmasTexto = $totalTurmas > 0 ? $totalTurmas . ' ' . ($totalTurmas == 1 ? 'turma' : 'turmas') . ' (' . implode(', ', array_map('htmlspecialchars', $turmasNomes)) . ')' : 'Sem turmas';
                        $realizouJornada = (int)($jornada['visualizado'] ?? 0) + (int)($jornada['concluido'] ?? 0);
                        $naoRealizouJornada = (int)($jornada['nao_visualizado'] ?? 0);
                        ?>
                        <div class="block p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors border border-transparent hover:border-gray-300">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($jornada['titulo']) ?></h3>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <?= htmlspecialchars($jornada['materia_nome'] ?? 'N/A') ?> • <?= $turmasTexto ?> • <?= $totalAlunos ?> <?= $totalAlunos == 1 ? 'aluno' : 'alunos' ?>
                                    </p>
                                    <?php if ($jornada['data_inicio'] && $jornada['data_fim']): ?>
                                        <p class="text-xs text-gray-500">
                                            <?= date('d/m/Y', strtotime($jornada['data_inicio'])) ?><?= !empty($jornada['hora_inicio']) ? ' às ' . htmlspecialchars($jornada['hora_inicio']) : '' ?> - 
                                            <?= date('d/m/Y', strtotime($jornada['data_fim'])) ?><?= !empty($jornada['hora_fim']) ? ' às ' . htmlspecialchars($jornada['hora_fim']) : '' ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <span class="px-2 py-1 <?= $status_color ?> text-xs rounded-full font-medium ml-2">
                                    <?= $status_label ?>
                                </span>
                            </div>
                            <div class="flex items-center space-x-4 text-xs text-gray-600 mt-2">
                                <span class="flex items-center">
                                    <span class="w-2 h-2 bg-green-500 rounded-full mr-1"></span>
                                    Realizou Jornada: <?= $realizouJornada ?> aluno(s)
                                </span>
                                <span class="flex items-center">
                                    <span class="w-2 h-2 bg-red-500 rounded-full mr-1"></span>
                                    Não realizou Jornada: <?= $naoRealizouJornada ?> aluno(s)
                                </span>
                            </div>
                            <div class="mt-3 pt-3 border-t border-gray-200">
                                <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
                                   class="inline-flex items-center gap-2 text-sm font-medium text-blue-600 hover:text-blue-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                    </svg>
                                    Ver Detalhes
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Prova Online -->
    <div class="bg-white rounded-xl shadow-lg">
        <div class="p-6 border-b border-gray-200">
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold text-gray-900">Prova Online</h2>
                <a href="<?= URL ?>/professor/provas" class="text-blue-600 hover:text-blue-700 text-sm font-medium">
                    Ver todas
                </a>
            </div>
        </div>
        <div class="p-6">
            <?php if (empty($eventos_prova_recentes)): ?>
                <div class="text-center py-8">
                    <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                    </svg>
                    <p class="text-gray-500">Nenhum evento de prova ainda</p>
                    <p class="text-sm text-gray-400">Aguardando eventos criados pela coordenação</p>
                </div>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($eventos_prova_recentes as $evento):
                        $isLancamentoNota = (($evento['formato_evento'] ?? 'online_questoes') === 'lancamento_nota');
                        if ($isLancamentoNota) {
                            $urlProva = URL . '/professor/provas/evento-lancar-notas/' . (int)$evento['id'] . '?materia_id=' . (int)$evento['materia_id'];
                        } else {
                            $urlProva = !empty($evento['prova_existente_id'])
                                ? URL . '/professor/provas/editar/' . (int)$evento['prova_existente_id']
                                : URL . '/professor/provas/criar/evento/' . (int)$evento['id'] . '?materia_id=' . (int)$evento['materia_id'];
                        }
                    ?>
                        <a href="<?= $urlProva ?>" 
                           class="block p-4 bg-gray-50 rounded-lg hover:bg-gray-100 transition-colors cursor-pointer">
                            <div class="flex items-start justify-between mb-2">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-900 mb-1"><?= htmlspecialchars($evento['titulo']) ?></h3>
                                    <p class="text-sm text-gray-600 mb-1">
                                        <span class="font-semibold">Matéria:</span> <?= htmlspecialchars($evento['materia_nome'] ?? 'N/A') ?>
                                    </p>
                                    <?php if ($evento['data_prova']): ?>
                                        <p class="text-xs text-gray-500">
                                            <?= date('d/m/Y', strtotime($evento['data_prova'])) ?> - 
                                            <?= date('H:i', strtotime($evento['hora_inicio'])) ?> às <?= date('H:i', strtotime($evento['hora_fim'])) ?>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-4 text-right">
                                    <?php if ($isLancamentoNota): ?>
                                        <span class="px-2 py-1 bg-violet-100 text-violet-800 text-xs rounded-full font-medium">
                                            Lançamento de notas
                                        </span>
                                    <?php elseif (($evento['provas_criadas_professor'] ?? 0) > 0): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full font-medium">
                                            Prova criada
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 text-xs rounded-full font-medium">
                                            Pendente
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php if (!$isLancamentoNota): ?>
                            <div class="flex items-center mt-2">
                                <span class="text-xs text-gray-600">
                                    Provas criadas: <strong><?= $evento['provas_criadas_professor'] ?? 0 ?></strong>
                                </span>
                            </div>
                            <?php endif; ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
