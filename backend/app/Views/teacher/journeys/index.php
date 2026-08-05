<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Minhas Jornadas 📚
            </h2>
            <p class="text-gray-600">
                Gerencie suas jornadas de aprendizado
            </p>
        </div>
        <a href="<?= URL ?>/professor/jornadas/criar" 
           class="bg-blue-600 text-white px-6 py-3 rounded-xl hover:bg-blue-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Nova Jornada
        </a>
    </div>
</div>

<!-- Filtros e Busca -->
<div class="bg-gradient-to-br from-white to-gray-50 rounded-xl shadow-lg border border-gray-200 mb-6 overflow-hidden">
    <!-- Header do Filtro -->
    <div class="bg-gradient-to-r from-blue-600 to-blue-700 px-6 py-4">
        <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"></path>
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-white">Filtros e Busca</h3>
                    <p class="text-sm text-blue-100">Encontre jornadas rapidamente</p>
                </div>
            </div>
            <div class="flex items-center gap-4">
            <div class="flex items-center gap-2 text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <span id="jornadaCount" class="text-2xl font-bold"><?= count($jornadas) ?></span>
                <span class="text-sm text-blue-100">jornadas</span>
                </div>
                <button id="toggleFiltros" class="bg-white/20 hover:bg-white/30 text-white px-4 py-2 rounded-lg transition-all duration-200 flex items-center gap-2">
                    <svg id="iconMostrar" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                    <svg id="iconEsconder" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 15l7-7 7 7"></path>
                    </svg>
                    <span id="textoToggle">Esconder Filtros</span>
                </button>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo dos Filtros -->
    <div id="conteudoFiltros" class="p-6">
        <div class="space-y-5">
            <!-- Busca -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Buscar Jornada
                </label>
                <div class="relative">
                    <input type="text" id="filtroBusca" placeholder="Digite título, matéria ou turma..." 
                           class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md">
                </div>
            </div>
            
            <!-- Filtros em Grid -->
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <!-- Filtro de Turma -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Turma
                    </label>
                    <select id="filtroTurma" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md appearance-none cursor-pointer">
                        <option value="">Todas as turmas</option>
                        <?php foreach ($turmas as $turma): ?>
                            <option value="<?= htmlspecialchars($turma['id']) ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro de Status -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Status
                    </label>
                    <select id="filtroStatus" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md appearance-none cursor-pointer">
                        <option value="">Todos os status</option>
                        <option value="ativa">Ativa</option>
                        <option value="finalizada">Finalizada</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Bimestre
                    </label>
                    <select id="filtroBimestre" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md appearance-none cursor-pointer">
                        <option value="">Todos os bimestres</option>
                        <option value="1">1º Bimestre</option>
                        <option value="2">2º Bimestre</option>
                        <option value="3">3º Bimestre</option>
                        <option value="4">4º Bimestre</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Avaliativo
                    </label>
                    <select id="filtroAvaliativo" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md appearance-none cursor-pointer">
                        <option value="">Todos</option>
                        <option value="1">Sim</option>
                        <option value="0">Não</option>
                    </select>
                </div>
                
                <!-- Ordenar por -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Ordenar por
                    </label>
                    <select id="ordenarPor" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md appearance-none cursor-pointer">
                        <option value="data_desc">Mais Recente</option>
                        <option value="data_asc">Mais Antiga</option>
                        <option value="titulo_asc">Título (A-Z)</option>
                        <option value="titulo_desc">Título (Z-A)</option>
                        <option value="status">Status</option>
                    </select>
                </div>
            </div>
            
            <!-- Botão Limpar Filtros -->
            <div class="flex justify-end pt-2">
                <button id="limparFiltros" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-xl hover:bg-gray-200 transition-all shadow-sm hover:shadow-md">
                    Limpar Filtros
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Stats Card - Status das Jornadas -->
<div class="bg-gradient-to-br from-blue-50 to-indigo-50 rounded-xl shadow-lg border border-blue-200 p-6 mb-8">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- Aguardando -->
        <div class="bg-white rounded-lg p-4 border-l-4 border-yellow-500 shadow-sm">
            <p class="text-sm font-medium text-gray-600 mb-1">Aguardando</p>
            <p class="text-3xl font-bold text-yellow-600"><?= $stats['aguardando'] ?? 0 ?></p>
        </div>
        
        <!-- Em Andamento -->
        <div class="bg-white rounded-lg p-4 border-l-4 border-blue-500 shadow-sm">
            <p class="text-sm font-medium text-gray-600 mb-1">Em Andamento</p>
            <p class="text-3xl font-bold text-blue-600"><?= $stats['em_andamento'] ?? 0 ?></p>
        </div>
        
        <!-- Concluído -->
        <div class="bg-white rounded-lg p-4 border-l-4 border-green-500 shadow-sm">
            <p class="text-sm font-medium text-gray-600 mb-1">Concluído</p>
            <p class="text-3xl font-bold text-green-600"><?= $stats['concluidas'] ?? 0 ?></p>
        </div>
    </div>
</div>

<!-- Jornadas List -->
<?php if (empty($jornadas)): ?>
    <div class="bg-white rounded-xl shadow-lg p-12 text-center">
        <div class="w-24 h-24 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-6">
            <svg class="w-12 h-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
        </div>
        <h3 class="text-xl font-semibold text-gray-900 mb-2">Nenhuma jornada criada</h3>
        <p class="text-gray-600 mb-6">Comece criando sua primeira jornada de aprendizado</p>
        <a href="<?= URL ?>/professor/jornadas/criar" 
           class="inline-flex items-center px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
            </svg>
            Criar Primeira Jornada
        </a>
    </div>
<?php else: ?>
    <!-- Controles de exibição -->
    <div class="flex items-center justify-between mb-4">
        <div class="text-sm text-gray-600">
            Exibindo <span id="jornadasVisiveis"><?= count($jornadas) ?></span> de <?= count($jornadas) ?> jornadas
        </div>
        <?php if (count($jornadas) > 9): ?>
            <div class="flex items-center space-x-2">
                <label class="text-sm text-gray-600">Itens por página:</label>
                <select id="itensPorPagina" class="px-3 py-1 border border-gray-300 rounded-lg text-sm">
                    <option value="9">9</option>
                    <option value="18">18</option>
                    <option value="27">27</option>
                    <option value="all">Todas</option>
                </select>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="jornadasGrid">
        <?php foreach ($jornadas as $jornada): ?>
            <div class="bg-white rounded-xl shadow-lg hover:shadow-xl transition-shadow border border-gray-200 jornada-card flex flex-col" data-jornada-id="<?= (int)$jornada['id'] ?>"
                 data-titulo="<?= strtolower(htmlspecialchars($jornada['titulo'] . ' ' . ($jornada['materia_nome'] ?? '') . ' ' . ($jornada['turma_nome'] ?? ''))) ?>"
                 data-turma-id="<?= htmlspecialchars($jornada['turma_id'] ?? '') ?>"
                 data-status="<?= htmlspecialchars($jornada['status'] ?? 'ativa') ?>"
                 data-bimestre="<?= (int)($jornada['bimestre'] ?? 0) ?>"
                 data-avaliativo="<?= (int)($jornada['avaliativo'] ?? 1) ?>">
                <!-- Header -->
                <div class="p-6 border-b border-gray-200">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($jornada['titulo']) ?></h3>
                                <p class="text-sm text-gray-600"><?= htmlspecialchars($jornada['materia_nome'] ?? 'N/A') ?></p>
                            </div>
                        </div>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full
                            <?php 
                            $statusExibir = $jornada['status_jornada'] ?? 'em_andamento';
                            switch($statusExibir) {
                                case 'aguardando': echo 'bg-yellow-100 text-yellow-800'; break;
                                case 'em_andamento': echo 'bg-blue-100 text-blue-800'; break;
                                case 'concluido': echo 'bg-green-100 text-green-800'; break;
                                default: echo 'bg-blue-100 text-blue-800';
                            }
                            ?>">
                            <?php
                            // Traduzir labels
                            $labels = [
                                'aguardando' => 'Aguardando',
                                'em_andamento' => 'Em Andamento',
                                'concluido' => 'Concluído'
                            ];
                            echo $labels[$statusExibir] ?? 'Em Andamento';
                            ?>
                        </span>
                    </div>
                    
                    <?php if (!empty($jornada['descricao'])): ?>
                        <p class="text-sm text-gray-600 line-clamp-2"><?= htmlspecialchars($jornada['descricao']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Content -->
                <div class="p-6 flex-1 flex flex-col">
                    <div class="space-y-3 flex-1">
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                            </svg>
                            <span>
                                <?php
                                $totalTurmas = $jornada['total_turmas_selecionadas'] ?? 1;
                                $totalAlunos = $jornada['total_alunos_selecionados'] ?? $jornada['total_alunos'] ?? 0;
                                $turmasNomes = $jornada['turmas_selecionadas_nomes'] ?? [];
                                
                                // Exibe quantidade e nomes das turmas
                                if (!empty($turmasNomes)) {
                                    echo $totalTurmas . ' ' . ($totalTurmas == 1 ? 'turma' : 'turmas') . ' (' . implode(', ', array_map('htmlspecialchars', $turmasNomes)) . ')';
                                } else {
                                    echo $totalTurmas . ' ' . ($totalTurmas == 1 ? 'turma' : 'turmas');
                                }
                                echo ' • ';
                                echo $totalAlunos . ' ' . ($totalAlunos == 1 ? 'aluno' : 'alunos');
                                ?>
                            </span>
                        </div>
                        
                        <!-- Lista de Alunos com Status -->
                        <div class="text-sm">
                            <?php if (!empty($jornada['alunos_status'])): ?>
                                <div class="space-y-1">
                                    <?php 
                                    $alunosStatus = [
                                        'concluida' => [],
                                        'visualizado' => [],
                                        'nao_visualizado' => []
                                    ];
                                    foreach ($jornada['alunos_status'] as $alunoStatus) {
                                        $status = $alunoStatus['status_jornada'] ?? 'nao_visualizado';
                                        if ($status === 'pendente') {
                                            $status = 'visualizado';
                                        }
                                        $alunosStatus[$status][] = $alunoStatus;
                                    }
                                    $realizouCount = count($alunosStatus['concluida']) + count($alunosStatus['visualizado']);
                                    $naoRealizouCount = count($alunosStatus['nao_visualizado']);
                                    ?>
                                    <?php if ($realizouCount > 0): ?>
                                        <div class="flex items-center text-xs">
                                            <span class="w-2 h-2 bg-green-500 rounded-full mr-2"></span>
                                            <span class="text-gray-600">Realizou Jornada: <?= $realizouCount ?> aluno(s)</span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($naoRealizouCount > 0): ?>
                                        <div class="flex items-center text-xs">
                                            <span class="w-2 h-2 bg-red-500 rounded-full mr-2"></span>
                                            <span class="text-gray-600">Não realizou Jornada: <?= $naoRealizouCount ?> aluno(s)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-gray-500 text-xs">Nenhum aluno na turma</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex items-center text-sm text-gray-600">
                            <svg class="w-4 h-4 mr-2 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                            </svg>
                            <span>
                                <?php if ($jornada['data_inicio'] && $jornada['data_fim']): ?>
                                    <?= date('d/m/Y', strtotime($jornada['data_inicio'])) ?>
                                    <?php if (!empty($jornada['hora_inicio'])): ?>
                                        às <?= htmlspecialchars($jornada['hora_inicio']) ?>
                                    <?php endif; ?>
                                    - 
                                    <?= date('d/m/Y', strtotime($jornada['data_fim'])) ?>
                                    <?php if (!empty($jornada['hora_fim'])): ?>
                                        às <?= htmlspecialchars($jornada['hora_fim']) ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    Período não definido
                                <?php endif; ?>
                            </span>
                        </div>
                        <?php if (!empty($jornada['created_at'])): ?>
                        <div class="flex items-center text-xs text-gray-500">
                            <svg class="w-3.5 h-3.5 mr-1.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span>Criado em <?= date('d/m/Y', strtotime($jornada['created_at'])) ?> às <?= date('H:i', strtotime($jornada['created_at'])) ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Actions (colado no bottom do card, botões compactos) -->
                    <div class="mt-4 pt-4 border-t border-gray-100 flex gap-2 justify-end flex-wrap">
                        <button type="button" onclick="duplicarJornada(<?= (int)$jornada['id'] ?>, this)"
                                class="bg-emerald-600 text-white px-3 py-1.5 rounded-md text-sm hover:bg-emerald-700 transition-all duration-200 flex items-center gap-1.5"
                                title="Criar uma cópia desta jornada">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            Duplicar
                        </button>
                        <button type="button" onclick="confirmarExclusao(this)"
                                class="bg-gray-500 text-white px-3 py-1.5 rounded-md text-sm hover:bg-gray-600 transition-all duration-200 flex items-center gap-1.5 jornada-deletar"
                                data-jornada-id="<?= (int)$jornada['id'] ?>"
                                data-jornada-titulo="<?= htmlspecialchars($jornada['titulo'] ?? 'Jornada', ENT_QUOTES, 'UTF-8') ?>"
                                title="Inativar jornada">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Deletar
                        </button>
                        <button onclick="window.location.href='<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>'" 
                                class="bg-blue-500 text-white px-3 py-1.5 rounded-md text-sm hover:bg-blue-600 transition-all duration-200 flex items-center gap-1.5"
                                title="Visualizar">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            Ver Detalhes
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script>
    // Debug: Verificar se os links estão funcionando
    console.log('Script carregado - Jornadas Index');
    
    // Filtros e busca
    document.addEventListener('DOMContentLoaded', function() {
        const filtroBusca = document.getElementById('filtroBusca');
        const filtroTurma = document.getElementById('filtroTurma');
        const filtroStatus = document.getElementById('filtroStatus');
        const filtroBimestre = document.getElementById('filtroBimestre');
        const filtroAvaliativo = document.getElementById('filtroAvaliativo');
        const ordenarPor = document.getElementById('ordenarPor');
        const limparFiltros = document.getElementById('limparFiltros');
        const jornadaCount = document.getElementById('jornadaCount');
        const jornadasVisiveis = document.getElementById('jornadasVisiveis');
        const itensPorPagina = document.getElementById('itensPorPagina');
        const jornadasGrid = document.getElementById('jornadasGrid');
        const toggleFiltros = document.getElementById('toggleFiltros');
        const conteudoFiltros = document.getElementById('conteudoFiltros');
        const iconMostrar = document.getElementById('iconMostrar');
        const iconEsconder = document.getElementById('iconEsconder');
        const textoToggle = document.getElementById('textoToggle');
        
        // Toggle dos filtros
        if (toggleFiltros && conteudoFiltros) {
            toggleFiltros.addEventListener('click', function() {
                const estaVisivel = conteudoFiltros.style.display !== 'none';
                
                if (estaVisivel) {
                    conteudoFiltros.style.display = 'none';
                    iconMostrar.classList.remove('hidden');
                    iconEsconder.classList.add('hidden');
                    textoToggle.textContent = 'Mostrar Filtros';
                } else {
                    conteudoFiltros.style.display = 'block';
                    iconMostrar.classList.add('hidden');
                    iconEsconder.classList.remove('hidden');
                    textoToggle.textContent = 'Esconder Filtros';
                }
            });
        }
        
        let itensPorPaginaValue = 9;
        let paginaAtual = 1;
        
        function filtrarJornadas() {
            const busca = filtroBusca?.value.toLowerCase() || '';
            const turma = filtroTurma?.value || '';
            const status = filtroStatus?.value || '';
            const bimestre = filtroBimestre?.value || '';
            const avaliativo = filtroAvaliativo?.value || '';
            const ordenar = ordenarPor?.value || 'data_desc';
            
            let visiveis = 0;
            const cards = Array.from(document.querySelectorAll('.jornada-card'));
            
            // Filtra os cards
            cards.forEach(card => {
                const titulo = card.getAttribute('data-titulo') || '';
                const cardTurmaId = card.getAttribute('data-turma-id') || '';
                const cardStatus = card.getAttribute('data-status') || '';
                const cardBimestre = card.getAttribute('data-bimestre') || '';
                const cardAvaliativo = card.getAttribute('data-avaliativo') || '';
                
                const matchBusca = !busca || titulo.includes(busca);
                const matchTurma = !turma || cardTurmaId === turma;
                const matchStatus = !status || cardStatus === status;
                const matchBimestre = !bimestre || cardBimestre === bimestre;
                const matchAvaliativo = !avaliativo || cardAvaliativo === avaliativo;
                
                if (matchBusca && matchTurma && matchStatus && matchBimestre && matchAvaliativo) {
                    card.style.display = 'block';
                    visiveis++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // Ordena os cards visíveis
            const cardsVisiveis = cards.filter(c => c.style.display !== 'none');
            
            cardsVisiveis.sort((a, b) => {
                const tituloA = a.querySelector('h3')?.textContent || '';
                const tituloB = b.querySelector('h3')?.textContent || '';
                const statusA = a.getAttribute('data-status') || '';
                const statusB = b.getAttribute('data-status') || '';
                
                switch(ordenar) {
                    case 'titulo_asc':
                        return tituloA.localeCompare(tituloB);
                    case 'titulo_desc':
                        return tituloB.localeCompare(tituloA);
                    case 'status':
                        return statusA.localeCompare(statusB);
                    case 'data_asc':
                    case 'data_desc':
                    default:
                        return 0; // Mantém ordem original
                }
            });
            
            // Reordena no DOM
            if (jornadasGrid) {
                cardsVisiveis.forEach(card => {
                    jornadasGrid.appendChild(card);
                });
            }
            
            if (jornadasVisiveis) {
                jornadasVisiveis.textContent = visiveis;
            }
            
            if (jornadaCount) {
                jornadaCount.textContent = visiveis;
            }
        }
        
        // Event listeners
        if (filtroBusca) filtroBusca.addEventListener('input', filtrarJornadas);
        if (filtroTurma) filtroTurma.addEventListener('change', filtrarJornadas);
        if (filtroStatus) filtroStatus.addEventListener('change', filtrarJornadas);
        if (filtroBimestre) filtroBimestre.addEventListener('change', filtrarJornadas);
        if (filtroAvaliativo) filtroAvaliativo.addEventListener('change', filtrarJornadas);
        if (ordenarPor) ordenarPor.addEventListener('change', filtrarJornadas);
        
        if (limparFiltros) {
            limparFiltros.addEventListener('click', function() {
                if (filtroBusca) filtroBusca.value = '';
                if (filtroTurma) filtroTurma.value = '';
                if (filtroStatus) filtroStatus.value = '';
                if (filtroBimestre) filtroBimestre.value = '';
                if (filtroAvaliativo) filtroAvaliativo.value = '';
                if (ordenarPor) ordenarPor.value = 'data_desc';
                filtrarJornadas();
            });
        }
        
        // Inicializa filtros
        filtrarJornadas();
    });

    function toggleStatus(jornadaId, currentStatus) {
        console.log('toggleStatus chamado:', jornadaId, currentStatus);
        
        const newStatus = currentStatus === 'ativa' ? 'pausada' : 'ativa';
        const action = newStatus === 'ativa' ? 'ativar' : 'pausar';
        
        if (confirm(`Tem certeza que deseja ${action} esta jornada?`)) {
            console.log('Enviando requisição para toggle status...');
            
            fetch('<?= URL ?>/professor/jornadas/toggle-status', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    jornada_id: jornadaId,
                    status: newStatus,
                    _token: '<?= htmlspecialchars($csrf_token) ?>'
                })
            })
            .then(response => {
                console.log('Resposta recebida:', response.status);
                return response.json();
            })
            .then(result => {
                console.log('Resultado:', result);
                if (result.success) {
                    if (result.warnings && result.warnings.length > 0) {
                        alert('⚠️ Atenção:\n\n' + result.warnings.join('\n\n'));
                    }
                    location.reload();
                } else {
                    alert('Erro ao alterar status: ' + result.error);
                }
            })
            .catch(error => {
                console.error('Erro na requisição:', error);
                alert('Erro de conexão');
            });
        }
    }
    
    
    function duplicarJornada(jornadaId, btn) {
        if (btn) {
            btn.disabled = true;
        }
        const formData = new FormData();
        formData.append('_token', '<?= htmlspecialchars($csrf_token) ?>');
        formData.append('jornada_id', jornadaId);
        fetch('<?= URL ?>/professor/jornadas/duplicar', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                window.location.href = '<?= URL ?>/professor/jornadas';
            } else {
                alert(data.error || 'Erro ao duplicar.');
                if (btn) btn.disabled = false;
            }
        })
        .catch(() => {
            alert('Erro de conexão.');
            if (btn) btn.disabled = false;
        });
    }
    
    function confirmarExclusao(btn) {
        const jornadaId = parseInt(btn.getAttribute('data-jornada-id'), 10);
        const titulo = btn.getAttribute('data-jornada-titulo') || 'Jornada';
        if (!jornadaId) return;

        function esc(s) {
            return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }
        const tituloEsc = esc(titulo);

        // Remove modal anterior se existir
        const existente = document.getElementById('modalExclusao');
        if (existente) existente.remove();

        const modal = document.createElement('div');
        modal.id = 'modalExclusao';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
        modal.innerHTML = `
            <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
                <div class="p-6">
                    <div class="flex items-center justify-center w-12 h-12 mx-auto bg-red-100 rounded-full mb-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                        </svg>
                    </div>
                    <h3 class="text-lg font-semibold text-gray-900 text-center mb-2">Confirmar inativação</h3>
                    <p class="text-sm text-gray-600 text-center mb-4">
                        A jornada <strong>"${tituloEsc}"</strong> será inativada e sairá da sua lista, do admin e do aluno.<br>
                        Ela permanece registrada no banco (apenas oculta). Digite sua senha para confirmar.
                    </p>
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Digite sua senha para confirmar:
                        </label>
                        <input 
                            type="password" 
                            id="senhaConfirmacao" 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-red-500"
                            placeholder="Sua senha"
                            autocomplete="current-password"
                        >
                        <p id="erroSenha" class="text-red-600 text-xs mt-1 hidden"></p>
                    </div>
                    <div class="flex space-x-3">
                        <button 
                            type="button"
                            onclick="fecharModalExclusao()" 
                            class="flex-1 px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors font-medium"
                        >
                            Cancelar
                        </button>
                        <button 
                            type="button"
                            id="btnConfirmarInativar"
                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors font-medium flex items-center justify-center gap-2"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                            </svg>
                            Inativar
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);

        const btnInativar = document.getElementById('btnConfirmarInativar');
        if (btnInativar) {
            btnInativar.addEventListener('click', function() {
                excluirJornada(jornadaId);
            });
        }

        const inputSenha = document.getElementById('senhaConfirmacao');
        if (inputSenha) {
            setTimeout(function() {
                inputSenha.focus();
            }, 100);
            inputSenha.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    fecharModalExclusao();
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    excluirJornada(jornadaId);
                }
            });
        }
    }
    
    function fecharModalExclusao() {
        const modal = document.getElementById('modalExclusao');
        if (modal) {
            modal.remove();
        }
    }
    
    function excluirJornada(jornadaId) {
        const senha = document.getElementById('senhaConfirmacao').value;
        const erroSenha = document.getElementById('erroSenha');
        
        if (!senha) {
            erroSenha.textContent = 'Por favor, digite sua senha';
            erroSenha.classList.remove('hidden');
            return;
        }
        
        erroSenha.classList.add('hidden');
        
        const btnExcluir = document.getElementById('btnConfirmarInativar');
        if (btnExcluir) {
            btnExcluir.disabled = true;
            btnExcluir.textContent = 'Inativando...';
        }
        
        const formData = new FormData();
        formData.append('_token', '<?= htmlspecialchars($csrf_token) ?>');
        formData.append('jornada_id', jornadaId);
        formData.append('senha', senha);
        
        fetch('<?= URL ?>/professor/jornadas/inativar', {
            method: 'POST',
            body: formData
        })
        .then(r => r.json())
        .then(result => {
            if (result.success) {
                fecharModalExclusao();
                const card = document.querySelector('.jornada-card[data-jornada-id="' + jornadaId + '"]');
                if (card) card.remove();
                const visiveis = document.querySelectorAll('.jornada-card').length;
                const el = document.getElementById('jornadasVisiveis');
                if (el) el.textContent = visiveis;
            } else {
                erroSenha.textContent = result.error || 'Erro ao inativar jornada';
                erroSenha.classList.remove('hidden');
                if (btnExcluir) {
                    btnExcluir.disabled = false;
                    btnExcluir.textContent = 'Inativar';
                }
            }
        })
        .catch(() => {
            erroSenha.textContent = 'Erro de conexão. Tente novamente.';
            erroSenha.classList.remove('hidden');
            if (btnExcluir) {
                btnExcluir.disabled = false;
                btnExcluir.textContent = 'Inativar';
            }
        });
    }
</script>
