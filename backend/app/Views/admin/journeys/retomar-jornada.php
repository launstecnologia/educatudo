<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Retomar Jornada do Aluno 🎯
            </h2>
            <p class="text-gray-600">
                Selecione o professor e o aluno para retomar a jornada
            </p>
        </div>
        <a href="<?= URL ?>/admin/jornadas" 
           class="bg-gray-500 text-white px-6 py-3 rounded-xl hover:bg-gray-600 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>
</div>

<!-- Selection Form -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6">
        <form id="retomarJornadaForm" method="POST" action="<?= URL ?>/admin/jornadas/retomar-aluno">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            
            <!-- Step 1: Selecionar Professor -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        Selecione o Professor
                    </span>
                </label>
                <select id="professorSelect" name="professor_id" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500"
                        required>
                    <option value="">-- Selecione um professor --</option>
                    <?php foreach ($professores as $professor): ?>
                        <option value="<?= $professor['id'] ?>">
                            <?= htmlspecialchars($professor['nome']) ?> 
                            (<?= $professor['total_jornadas'] ?> jornada<?= $professor['total_jornadas'] != 1 ? 's' : '' ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Step 2: Selecionar Jornada (aparece após selecionar professor) -->
            <div id="jornadaSection" class="mb-6 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"></path>
                        </svg>
                        Selecione a Jornada
                    </span>
                </label>
                <select id="jornadaSelect" name="jornada_id" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                        required>
                    <option value="">-- Selecione uma jornada --</option>
                </select>
            </div>
            
            <!-- Step 3: Selecionar Aluno (aparece após selecionar jornada) -->
            <div id="alunoSection" class="mb-6 hidden">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    <span class="flex items-center">
                        <svg class="w-5 h-5 mr-2 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                        </svg>
                        Selecione o Aluno
                    </span>
                </label>
                <select id="alunoSelect" name="aluno_id" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        required>
                    <option value="">-- Selecione um aluno --</option>
                </select>
            </div>
            
            <!-- Loading Indicator -->
            <div id="loadingIndicator" class="hidden mb-4">
                <div class="flex items-center justify-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-purple-600"></div>
                    <span class="ml-3 text-gray-600">Carregando...</span>
                </div>
            </div>
            
            <!-- Submit Button -->
            <div class="flex justify-end">
                <button type="submit" id="submitButton" 
                        class="bg-gradient-to-r from-green-600 to-emerald-600 text-white px-8 py-3 rounded-xl hover:from-green-700 hover:to-emerald-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl disabled:opacity-50 disabled:cursor-not-allowed"
                        disabled>
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Retomar Jornada
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const professorSelect = document.getElementById('professorSelect');
    const jornadaSection = document.getElementById('jornadaSection');
    const jornadaSelect = document.getElementById('jornadaSelect');
    const alunoSection = document.getElementById('alunoSection');
    const alunoSelect = document.getElementById('alunoSelect');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const submitButton = document.getElementById('submitButton');
    
    let alunosData = [];
    
    // Quando selecionar professor
    professorSelect.addEventListener('change', function() {
        const professorId = this.value;
        
        if (!professorId) {
            jornadaSection.classList.add('hidden');
            alunoSection.classList.add('hidden');
            submitButton.disabled = true;
            return;
        }
        
        // Carregar jornadas e alunos do professor
        loadingIndicator.classList.remove('hidden');
        jornadaSection.classList.add('hidden');
        alunoSection.classList.add('hidden');
        submitButton.disabled = true;
        
        fetch(`<?= URL ?>/admin/jornadas/buscar-alunos-professor?professor_id=${professorId}`)
            .then(response => response.json())
            .then(data => {
                loadingIndicator.classList.add('hidden');
                
                if (data.success) {
                    // Preencher jornadas
                    jornadaSelect.innerHTML = '<option value="">-- Selecione uma jornada --</option>';
                    data.jornadas.forEach(jornada => {
                        const option = document.createElement('option');
                        option.value = jornada.id;
                        option.textContent = `${jornada.titulo} (${jornada.turma_nome})`;
                        jornadaSelect.appendChild(option);
                    });
                    
                    // Guardar alunos para usar depois
                    alunosData = data.alunos || [];
                    
                    jornadaSection.classList.remove('hidden');
                } else {
                    alert('Erro ao carregar dados: ' + (data.error || 'Erro desconhecido'));
                }
            })
            .catch(error => {
                loadingIndicator.classList.add('hidden');
                console.error('Erro:', error);
                alert('Erro ao carregar dados do professor');
            });
    });
    
    // Quando selecionar jornada
    jornadaSelect.addEventListener('change', function() {
        const jornadaId = this.value;
        
        if (!jornadaId) {
            alunoSection.classList.add('hidden');
            submitButton.disabled = true;
            return;
        }
        
        // Filtrar alunos da turma da jornada selecionada
        const jornada = jornadaSelect.options[jornadaSelect.selectedIndex];
        const turmaNome = jornada.textContent.match(/\(([^)]+)\)/)?.[1] || '';
        
        alunoSelect.innerHTML = '<option value="">-- Selecione um aluno --</option>';
        
        // Filtrar alunos da mesma turma
        const alunosTurma = alunosData.filter(aluno => aluno.turma_nome === turmaNome);
        
        alunosTurma.forEach(aluno => {
            const option = document.createElement('option');
            option.value = aluno.id;
            option.textContent = `${aluno.nome} (${aluno.turma_nome})`;
            alunoSelect.appendChild(option);
        });
        
        if (alunosTurma.length > 0) {
            alunoSection.classList.remove('hidden');
        } else {
            alert('Nenhum aluno encontrado para esta turma');
        }
    });
    
    // Quando selecionar aluno
    alunoSelect.addEventListener('change', function() {
        submitButton.disabled = !this.value;
    });
});
</script>

