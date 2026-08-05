<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Meus Alunos 👥</h1>
            <p class="text-gray-600 mt-2">Gerencie e acompanhe o progresso dos seus alunos</p>
        </div>
        <div class="flex items-center space-x-4">
            <div class="text-right">
                <p class="text-sm text-gray-500">Total de Alunos</p>
                <p class="text-lg font-semibold text-blue-600"><?= count($alunos) ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Filters and Search -->
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div class="flex items-center space-x-4">
            <div class="relative">
                <input type="text" id="searchInput" placeholder="Buscar aluno..." 
                       class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
            <select id="turmaFilter" class="px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <option value="">Todas as turmas</option>
                <?php 
                $turmas = array_unique(array_column($alunos, 'turma_nome'));
                foreach ($turmas as $turma): 
                ?>
                    <option value="<?= htmlspecialchars($turma) ?>"><?= htmlspecialchars($turma) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-center space-x-2">
            <span class="text-sm text-gray-500">Mostrando:</span>
            <span id="alunoCount" class="text-sm font-medium text-blue-600"><?= count($alunos) ?> alunos</span>
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
                 data-nome="<?= strtolower(htmlspecialchars($aluno['nome'])) ?>"
                 data-turma="<?= htmlspecialchars($aluno['turma_nome']) ?>">
                <div class="flex items-center space-x-4 mb-4">
                    <div class="w-12 h-12 bg-blue-500 rounded-full flex items-center justify-center">
                        <span class="text-white font-semibold text-lg">
                            <?= strtoupper(substr($aluno['nome'], 0, 2)) ?>
                        </span>
                    </div>
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($aluno['nome']) ?></h3>
                        <p class="text-sm text-gray-600"><?= htmlspecialchars($aluno['email']) ?></p>
                    </div>
                </div>
                
                <div class="space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-500">Turma:</span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">
                            <?= htmlspecialchars($aluno['turma_nome']) ?>
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
                    <div class="flex space-x-2">
                        <button class="flex-1 bg-blue-500 text-white px-3 py-2 rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors">
                            Ver Progresso
                        </button>
                        <button class="flex-1 bg-gray-100 text-gray-700 px-3 py-2 rounded-lg text-sm font-medium hover:bg-gray-200 transition-colors">
                            Detalhes
                        </button>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchInput');
    const turmaFilter = document.getElementById('turmaFilter');
    const alunosGrid = document.getElementById('alunosGrid');
    const alunoCount = document.getElementById('alunoCount');
    const alunoCards = document.querySelectorAll('.aluno-card');
    
    function filterAlunos() {
        const searchTerm = searchInput.value.toLowerCase();
        const selectedTurma = turmaFilter.value;
        let visibleCount = 0;
        
        alunoCards.forEach(card => {
            const nome = card.dataset.nome;
            const turma = card.dataset.turma;
            
            const matchesSearch = nome.includes(searchTerm);
            const matchesTurma = !selectedTurma || turma === selectedTurma;
            
            if (matchesSearch && matchesTurma) {
                card.style.display = 'block';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });
        
        alunoCount.textContent = `${visibleCount} alunos`;
    }
    
    searchInput.addEventListener('input', filterAlunos);
    turmaFilter.addEventListener('change', filterAlunos);
});
</script>
