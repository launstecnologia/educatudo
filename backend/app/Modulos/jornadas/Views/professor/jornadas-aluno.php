<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Jornadas dos Alunos 📚</h1>
            <p class="text-gray-600 mt-2">Gerencie jornadas de aprendizado e acompanhe o progresso dos alunos</p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right">
                <p class="text-sm text-gray-500">Total de Jornadas</p>
                <p class="text-lg font-semibold text-blue-600"><?= count($jornadas) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex items-center space-x-4">
            <div class="relative">
                <input type="text" id="searchInput" placeholder="Buscar jornada..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <select id="turmaFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Todas as turmas</option>
                <?php 
                $turmas = array_unique(array_column($jornadas, 'turma_nome'));
                foreach ($turmas as $turma): 
                ?>
                    <option value="<?= htmlspecialchars($turma) ?>"><?= htmlspecialchars($turma) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">Mostrando:</span>
            <span id="jornadaCount" class="text-sm font-medium text-blue-600"><?= count($jornadas) ?> jornadas</span>
        </div>
    </div>
</div>

<!-- Jornadas Grid -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6" id="jornadasGrid">
    <?php if (empty($jornadas)): ?>
        <div class="col-span-full">
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma jornada encontrada</h3>
                <p class="text-gray-600 mb-4">Você ainda não criou nenhuma jornada de aprendizado.</p>
                <a href="<?= URL ?>/professor/criar-jornada" class="inline-flex items-center px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600 transition-colors">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Criar Primeira Jornada
                </a>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($jornadas as $jornada): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 hover:shadow-xl transition-shadow jornada-card" 
                 data-titulo="<?= strtolower(htmlspecialchars($jornada['titulo'])) ?>"
                 data-turma="<?= htmlspecialchars($jornada['turma_nome']) ?>">
                
                <!-- Header -->
                <div class="flex items-start justify-between mb-4">
                    <div class="flex-1">
                        <h3 class="text-xl font-semibold text-gray-900 mb-2"><?= htmlspecialchars($jornada['titulo']) ?></h3>
                        <p class="text-gray-600 text-sm"><?= htmlspecialchars($jornada['descricao'] ?: 'Sem descrição') ?></p>
                    </div>
                    <div class="flex items-center space-x-2">
                        <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">
                            Ativa
                        </span>
                    </div>
                </div>
                
                <!-- Stats -->
                <div class="grid grid-cols-2 gap-4 mb-4">
                    <div class="bg-blue-50 rounded-lg p-3">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-blue-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm text-gray-600">Alunos</p>
                                <p class="text-lg font-semibold text-blue-600"><?= $jornada['total_alunos'] ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="bg-green-50 rounded-lg p-3">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <div>
                                <p class="text-sm text-gray-600">Exercícios</p>
                                <p class="text-lg font-semibold text-green-600"><?= $jornada['total_exercicios'] ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Info -->
                <div class="space-y-2 mb-4">
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Turma:</span>
                        <span class="font-medium text-gray-900"><?= htmlspecialchars($jornada['turma_nome']) ?></span>
                    </div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-500">Criada em:</span>
                        <span class="font-medium text-gray-900"><?= date('d/m/Y', strtotime($jornada['created_at'])) ?></span>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="flex space-x-2">
                    <button class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors">
                        Ver Alunos
                    </button>
                    <button class="flex-1 bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                        Editar
                    </button>
                    <button class="px-3 py-2 bg-red-100 text-red-700 rounded-lg text-sm font-medium hover:bg-red-200 transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        </svg>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const turmaFilter = document.getElementById('turmaFilter');
    const jornadasGrid = document.getElementById('jornadasGrid');
    const jornadaCount = document.getElementById('jornadaCount');
    const jornadaCards = document.querySelectorAll('.jornada-card');
    
    function filterJornadas() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedTurma = turmaFilter.value;
        let visibleCount = 0;
        
        jornadaCards.forEach(card => {
            const titulo = card.dataset.titulo;
            const turma = card.dataset.turma;
            
            const matchesSearch = titulo.includes(searchTerm);
            const matchesTurma = !selectedTurma || turma === selectedTurma;
            
            if (matchesSearch && matchesTurma) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        jornadaCount.textContent = `${visibleCount} jornadas`;
    }
    
    searchInput.addEventListener('input', filterJornadas);
    turmaFilter.addEventListener('change', filterJornadas);
});
</script>
