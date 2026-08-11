<!-- Header Section -->
<div class="mb-6 md:mb-8">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="flex-1">
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Gerenciamento de Alunos 👥</h1>
            <p class="text-sm md:text-base text-gray-600 mt-2">Visualizar dados dos alunos e gerenciar senhas (acesso somente leitura)</p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right bg-gradient-to-br from-blue-50 to-blue-100 px-3 md:px-4 py-2 md:py-3 rounded-xl border border-blue-200">
                <p class="text-xs font-medium text-blue-700 uppercase tracking-wide">Total de Alunos</p>
                <p class="text-xl md:text-2xl font-bold text-blue-600 mt-1"><?= count($alunos) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Info Alert -->
<div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
    <div class="flex items-center">
        <svg class="w-5 h-5 text-blue-600 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
            <h3 class="text-sm font-medium text-blue-800">Permissões do Professor</h3>
            <p class="text-sm text-blue-700">Você pode apenas visualizar dados dos alunos. Não é possível criar ou editar informações dos alunos.</p>
        </div>
    </div>
</div>

<!-- Filters and Search -->
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
                    <p class="text-sm text-blue-100">Encontre alunos rapidamente</p>
                </div>
            </div>
            <div class="flex items-center gap-2 text-white">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
                <span id="alunoCount" class="text-2xl font-bold">0</span>
                <span class="text-sm text-blue-100">alunos</span>
            </div>
        </div>
    </div>
    
    <!-- Conteúdo dos Filtros -->
    <div class="p-6">
        <div class="space-y-5">
            <!-- Busca -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                        Buscar Aluno
                    </div>
                </label>
                <div class="relative">
                    <input type="text" id="searchInput" placeholder="Digite nome, RA ou email..." 
                           class="w-full pl-12 pr-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md">
                    <svg class="w-6 h-6 text-gray-400 absolute left-4 top-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            
            <!-- Filtros em Grid -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <!-- Filtro de Turma -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            Turma
                        </div>
                    </label>
                    <select id="turmaFilter" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md appearance-none cursor-pointer">
                        <option value="">Todas as turmas</option>
                        <?php 
                        $turmas = array_unique(array_column($alunos, 'turma_nome'));
                        sort($turmas);
                        foreach ($turmas as $turma): 
                        ?>
                            <option value="<?= htmlspecialchars($turma) ?>"><?= htmlspecialchars($turma) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <!-- Filtro de Ordenação -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        <div class="flex items-center gap-2">
                            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path>
                            </svg>
                            Ordenar por
                        </div>
                    </label>
                    <select id="sortFilter" class="w-full px-4 py-3 border-2 border-gray-300 rounded-xl focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-all bg-white shadow-sm hover:shadow-md appearance-none cursor-pointer">
                        <option value="nome">Nome (A-Z)</option>
                        <option value="turma">Turma</option>
                        <option value="ra">RA</option>
                        <option value="jornadas">Jornadas</option>
                    </select>
                </div>
                
                <!-- Botão Limpar -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2 opacity-0">Ações</label>
                    <button onclick="limparFiltros()" class="w-full px-4 py-3 bg-gradient-to-r from-gray-100 to-gray-200 text-gray-700 rounded-xl hover:from-gray-200 hover:to-gray-300 transition-all font-medium text-sm flex items-center justify-center gap-2 shadow-sm hover:shadow-md border-2 border-gray-300">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        Limpar Filtros
                    </button>
                </div>
            </div>
            
            <!-- Filtros Ativos -->
            <div id="filtrosAtivos" class="hidden pt-4 border-t border-gray-200">
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-sm font-medium text-gray-700">Filtros aplicados:</span>
                </div>
            </div>
        </div>
    </div>
</div>


<!-- Students Grid -->
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6" id="alunosGrid">
    <?php if (empty($alunos)): ?>
        <div class="col-span-full">
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhum aluno encontrado</h3>
                <p class="text-gray-600">Você ainda não tem alunos atribuídos às suas turmas.</p>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($alunos as $aluno): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow aluno-card" 
                 data-nome="<?= strtolower(htmlspecialchars($aluno['nome'] ?? '')) ?>"
                 data-turma="<?= htmlspecialchars($aluno['turma_nome'] ?? '') ?>"
                 data-ra="<?= strtolower(htmlspecialchars($aluno['ra'] ?? '')) ?>"
                 data-email="<?= strtolower(htmlspecialchars($aluno['email'] ?? '')) ?>"
                 data-jornadas="<?= intval($aluno['total_jornadas'] ?? 0) ?>">
                <div class="flex items-center space-x-4 mb-4">
                    <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold text-lg">
                            <?= strtoupper(substr($aluno['nome'] ?? '', 0, 2)) ?>
                        </span>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($aluno['nome'] ?? '') ?></h3>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($aluno['email'] ?? '') ?></p>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">RA:</span>
                        <span class="text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($aluno['ra'] ?? '') ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Turma:</span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <?= htmlspecialchars($aluno['turma_nome'] ?? '') ?>
                        </span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Jornadas:</span>
                        <span class="text-sm font-medium text-gray-900"><?= $aluno['total_jornadas'] ?></span>
                    </div>
                    
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Status:</span>
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                            Ativo
                        </span>
                    </div>
                </div>
                
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <a href="<?= URL ?>/professor/student/<?= $aluno['id'] ?>" class="block w-full bg-blue-500 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors text-center">
                        Ver Detalhes
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- Hidden CSRF Token -->
<input type="hidden" id="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

<script>
// Sistema de filtros melhorado
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const turmaFilter = document.getElementById('turmaFilter');
    const sortFilter = document.getElementById('sortFilter');
    const alunosGrid = document.getElementById('alunosGrid');
    const alunoCount = document.getElementById('alunoCount');
    const filtrosAtivos = document.getElementById('filtrosAtivos');
    const alunoCards = Array.from(document.querySelectorAll('.aluno-card'));
    
    // Armazenar ordem original para reset
    const ordemOriginal = alunoCards.map(card => card.cloneNode(true));
    
    function atualizarFiltrosAtivos() {
        const filtros = [];
        if (searchInput.value.trim()) {
            filtros.push({
                label: `Busca: "${searchInput.value}"`,
                icon: `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>`
            });
        }
        if (turmaFilter.value) {
            filtros.push({
                label: `Turma: ${turmaFilter.value}`,
                icon: `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>`
            });
        }
        if (sortFilter.value !== 'nome') {
            const sortLabels = {
                'turma': 'Turma',
                'ra': 'RA',
                'jornadas': 'Jornadas'
            };
            filtros.push({
                label: `Ordenado por: ${sortLabels[sortFilter.value]}`,
                icon: `<svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"></path></svg>`
            });
        }
        
        if (filtros.length > 0) {
            filtrosAtivos.innerHTML = filtros.map(f => 
                `<span class="inline-flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-700 text-sm font-medium rounded-lg border border-blue-200">
                    ${f.icon}
                    ${f.label}
                </span>`
            ).join('');
            filtrosAtivos.classList.remove('hidden');
        } else {
            filtrosAtivos.classList.add('hidden');
        }
    }
    
    function ordenarAlunos(cards, criterio) {
        return cards.sort((a, b) => {
            switch(criterio) {
                case 'nome':
                    return a.dataset.nome.localeCompare(b.dataset.nome);
                case 'turma':
                    const turmaCompare = a.dataset.turma.localeCompare(b.dataset.turma);
                    return turmaCompare !== 0 ? turmaCompare : a.dataset.nome.localeCompare(b.dataset.nome);
                case 'ra':
                    return a.dataset.ra.localeCompare(b.dataset.ra);
                case 'jornadas':
                    const jornadasA = parseInt(a.dataset.jornadas) || 0;
                    const jornadasB = parseInt(b.dataset.jornadas) || 0;
                    return jornadasB - jornadasA; // Maior primeiro
                default:
                    return 0;
            }
        });
    }
    
    function temFiltrosAtivos() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedTurma = turmaFilter.value;
        const sortCriterio = sortFilter.value;
        
        // Verifica se há algum filtro aplicado
        return searchTerm !== '' || selectedTurma !== '' || sortCriterio !== 'nome';
    }
    
    function filterAlunos() {
        const searchTerm = searchInput.value.toLowerCase().trim();
        const selectedTurma = turmaFilter.value;
        const sortCriterio = sortFilter.value;
        
        // Verificar se há filtros ativos
        const filtrosAtivos = temFiltrosAtivos();
        
        // Se não houver filtros, esconder todos e mostrar mensagem
        if (!filtrosAtivos) {
            alunoCards.forEach(card => {
                card.style.display = 'none';
            });
            
            // Remover mensagem de "nenhum resultado" se existir
            const noResults = document.getElementById('noResultsMessage');
            if (noResults) {
                noResults.remove();
            }
            
            // Mostrar mensagem inicial
            if (!document.getElementById('initialMessage')) {
                const initialMsg = document.createElement('div');
                initialMsg.id = 'initialMessage';
                initialMsg.className = 'col-span-full bg-blue-50 border border-blue-200 rounded-xl p-8 text-center';
                initialMsg.innerHTML = `
                    <svg class="w-16 h-16 text-blue-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <h3 class="text-xl font-semibold text-blue-800 mb-2">Aplique filtros para visualizar os alunos</h3>
                    <p class="text-sm text-blue-700">Use a busca, selecione uma turma ou altere a ordenação para ver os alunos.</p>
                `;
                alunosGrid.appendChild(initialMsg);
            }
            
            alunoCount.textContent = '0 alunos';
            atualizarFiltrosAtivos();
            return;
        }
        
        // Remover mensagem inicial se existir
        const initialMsg = document.getElementById('initialMessage');
        if (initialMsg) {
            initialMsg.remove();
        }
        
        // Filtrar cards
        let cardsVisiveis = alunoCards.filter(card => {
            const nome = card.dataset.nome || '';
            const ra = card.dataset.ra || '';
            const email = card.dataset.email || '';
            const turma = card.dataset.turma || '';
            
            const matchesSearch = !searchTerm || 
                nome.includes(searchTerm) || 
                ra.includes(searchTerm) || 
                email.includes(searchTerm);
            
            const matchesTurma = !selectedTurma || turma === selectedTurma;
            
            return matchesSearch && matchesTurma;
        });
        
        // Ordenar cards visíveis
        if (sortCriterio) {
            cardsVisiveis = ordenarAlunos(cardsVisiveis, sortCriterio);
        }
        
        // Esconder todos os cards
        alunoCards.forEach(card => {
            card.style.display = 'none';
        });
        
        // Mostrar e reordenar cards visíveis
        cardsVisiveis.forEach((card, index) => {
            card.style.display = 'block';
            alunosGrid.appendChild(card);
        });
        
        // Atualizar contador
        const visibleCount = cardsVisiveis.length;
        alunoCount.textContent = `${visibleCount} aluno${visibleCount !== 1 ? 's' : ''}`;
        
        // Atualizar indicador de filtros ativos
        atualizarFiltrosAtivos();
        
        // Mostrar mensagem se não houver resultados
        if (visibleCount === 0) {
            if (!document.getElementById('noResultsMessage')) {
                const noResults = document.createElement('div');
                noResults.id = 'noResultsMessage';
                noResults.className = 'col-span-full bg-yellow-50 border border-yellow-200 rounded-xl p-6 text-center';
                noResults.innerHTML = `
                    <svg class="w-12 h-12 text-yellow-400 mx-auto mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <h3 class="text-lg font-medium text-yellow-800 mb-1">Nenhum aluno encontrado</h3>
                    <p class="text-sm text-yellow-700">Tente ajustar os filtros de busca.</p>
                `;
                alunosGrid.appendChild(noResults);
            }
        } else {
            const noResults = document.getElementById('noResultsMessage');
            if (noResults) {
                noResults.remove();
            }
        }
    }
    
    // Função para limpar filtros
    window.limparFiltros = function() {
        searchInput.value = '';
        turmaFilter.value = '';
        sortFilter.value = 'nome';
        filterAlunos();
        
        // Focar no campo de busca
        searchInput.focus();
    };
    
    // Event listeners
    searchInput.addEventListener('input', filterAlunos);
    turmaFilter.addEventListener('change', filterAlunos);
    sortFilter.addEventListener('change', filterAlunos);
    
    // Busca com Enter
    searchInput.addEventListener('keydown', function(e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            filterAlunos();
        }
    });
    
    // Inicializar - esconder todos os alunos inicialmente
    alunoCards.forEach(card => {
        card.style.display = 'none';
    });
    
    // Mostrar mensagem inicial
    const initialMsg = document.createElement('div');
    initialMsg.id = 'initialMessage';
    initialMsg.className = 'col-span-full bg-blue-50 border border-blue-200 rounded-xl p-8 text-center';
    initialMsg.innerHTML = `
        <svg class="w-16 h-16 text-blue-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
        </svg>
        <h3 class="text-xl font-semibold text-blue-800 mb-2">Aplique filtros para visualizar os alunos</h3>
        <p class="text-sm text-blue-700">Use a busca, selecione uma turma ou altere a ordenação para ver os alunos.</p>
    `;
    alunosGrid.appendChild(initialMsg);
    
    alunoCount.textContent = '0 alunos';
});

console.log('JavaScript carregado com sucesso!');
</script>