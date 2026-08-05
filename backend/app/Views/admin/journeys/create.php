<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Criar Nova Jornada 📚
            </h2>
            <p class="text-gray-600">
                Crie uma nova jornada de aprendizado para os alunos
            </p>
        </div>
        <a href="<?= URL ?>/admin/jornadas" 
           class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>
</div>

<!-- Form Section -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Dados da Jornada</h3>
    </div>
    
    <form id="jornadaForm" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Professor (obrigatório) -->
        <div>
            <label for="professor_id" class="block text-sm font-semibold text-gray-700 mb-2">
                Professor <span class="text-red-500">*</span>
            </label>
            <?php if (empty($professores)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        Nenhum professor cadastrado no sistema.
                    </p>
                </div>
            <?php else: ?>
                <select id="professor_id" name="professor_id" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                    <option value="">Selecione um professor</option>
                    <?php foreach ($professores as $professor): ?>
                        <option value="<?= htmlspecialchars($professor['id']) ?>">
                            <?= htmlspecialchars($professor['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <p class="mt-1 text-sm text-gray-500">Professor responsável pela jornada</p>
        </div>
        
        <!-- Título da Jornada -->
        <div>
            <label for="titulo" class="block text-sm font-semibold text-gray-700 mb-2">
                Título da Jornada <span class="text-red-500">*</span>
            </label>
            <input type="text" id="titulo" name="titulo" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                   placeholder="Ex: Introdução à Matemática">
            <p class="mt-1 text-sm text-gray-500">Nome que identifica esta jornada</p>
        </div>

        <!-- Turmas (carregadas ao selecionar o professor) -->
        <div id="blocoTurmas" class="hidden">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                Turmas <span class="text-red-500">*</span>
            </label>
            <div id="turmasLoading" class="hidden text-sm text-gray-500 p-2">Carregando turmas...</div>
            <div id="turmasEmpty" class="hidden bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                <p class="text-sm text-yellow-800">Este professor não está vinculado a nenhuma turma.</p>
            </div>
            <div id="turmasContainer" class="hidden border border-gray-300 rounded-lg p-4 max-h-48 overflow-y-auto bg-white">
                <div id="turmasLista" class="space-y-2"></div>
            </div>
            <input type="hidden" id="turma_id" name="turma_id" value="">
            <p class="mt-1 text-sm text-gray-500">Selecione uma ou mais turmas que receberão esta jornada</p>
        </div>

        <!-- Seleção de Alunos -->
        <div id="selecaoAlunos" class="hidden">
            <label class="block text-sm font-semibold text-gray-700 mb-2">Alunos</label>
            <div class="mb-3">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="radio" name="tipo_selecao_alunos" value="todos" checked
                           class="text-purple-600 focus:ring-purple-500 tipo-selecao-alunos">
                    <span class="text-sm text-gray-700">Todos os alunos das turmas selecionadas</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer mt-2">
                    <input type="radio" name="tipo_selecao_alunos" value="especificos"
                           class="text-purple-600 focus:ring-purple-500 tipo-selecao-alunos">
                    <span class="text-sm text-gray-700">Selecionar alunos específicos</span>
                </label>
            </div>
            <div id="containerAlunos" class="hidden border border-gray-300 rounded-lg p-4 max-h-64 overflow-y-auto bg-white">
                <div id="listaAlunos" class="space-y-2"></div>
            </div>
            <p class="mt-1 text-sm text-gray-500">Escolha se a jornada será para todos os alunos ou apenas alunos específicos</p>
        </div>

        <!-- Matéria -->
        <div>
            <label for="materia_id" class="block text-sm font-semibold text-gray-700 mb-2">
                Matéria <span class="text-red-500">*</span>
            </label>
            <?php if (empty($materias)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">Nenhuma matéria cadastrada no sistema.</p>
                </div>
            <?php else: ?>
                <select id="materia_id" name="materia_id" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                    <option value="">Selecione uma matéria</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= htmlspecialchars($materia['id']) ?>"
                                data-cor="<?= htmlspecialchars($materia['cor'] ?? '') ?>"
                                data-icone="<?= htmlspecialchars($materia['icone'] ?? '') ?>">
                            <?= htmlspecialchars($materia['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <p class="mt-1 text-sm text-gray-500">Matéria principal da jornada</p>
        </div>

        <?php $anoAtual = (int)date('Y'); ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="ano_letivo" class="block text-sm font-semibold text-gray-700 mb-2">
                    Ano Letivo <span class="text-red-500">*</span>
                </label>
                <input type="number" id="ano_letivo" name="ano_letivo" required min="2000" max="2100"
                       value="<?= $anoAtual ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
            </div>
            <div>
                <label for="bimestre" class="block text-sm font-semibold text-gray-700 mb-2">
                    Bimestre <span class="text-red-500">*</span>
                </label>
                <select id="bimestre" name="bimestre" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                    <option value="">Selecione</option>
                    <option value="2">2º Bimestre</option>
                </select>
            </div>
        </div>
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                Avaliativo
            </label>
            <input type="hidden" name="avaliativo" value="1">
            <div class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-gray-50 text-gray-800">Sim</div>
        </div>

        <!-- Período: data e hora -->
        <?php $dataMinimaHoje = date('Y-m-d'); ?>
        <div class="space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="data_inicio" class="block text-sm font-semibold text-gray-700 mb-2">
                        Data de Início <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="data_inicio" name="data_inicio" required
                           min="<?= htmlspecialchars($dataMinimaHoje) ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                </div>
                <div>
                    <label for="data_fim" class="block text-sm font-semibold text-gray-700 mb-2">
                        Data de Fim <span class="text-red-500">*</span>
                    </label>
                    <input type="date" id="data_fim" name="data_fim" required
                           min="<?= htmlspecialchars($dataMinimaHoje) ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="hora_inicio" class="block text-sm font-semibold text-gray-700 mb-2">Horário de Início</label>
                    <input type="time" id="hora_inicio" name="hora_inicio"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                </div>
                <div>
                    <label for="hora_fim" class="block text-sm font-semibold text-gray-700 mb-2">Horário de Fim</label>
                    <input type="time" id="hora_fim" name="hora_fim"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                </div>
            </div>
        </div>

        <!-- Error Message -->
        <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
        <!-- Success Message -->
        <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"></div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/jornadas"
               class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancelar
            </a>
            <button type="submit"
                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                <span id="submitText">Criar Jornada</span>
                <span id="loadingText" class="hidden">Criando...</span>
            </button>
        </div>
    </form>
</div>

<script>
(function() {
    const URL_BASE = '<?= URL ?>';
    const blocoTurmas = document.getElementById('blocoTurmas');
    const turmasLoading = document.getElementById('turmasLoading');
    const turmasEmpty = document.getElementById('turmasEmpty');
    const turmasContainer = document.getElementById('turmasContainer');
    const turmasLista = document.getElementById('turmasLista');
    const turmaIdHidden = document.getElementById('turma_id');
    const selecaoAlunos = document.getElementById('selecaoAlunos');
    const containerAlunos = document.getElementById('containerAlunos');
    const listaAlunos = document.getElementById('listaAlunos');
    const professorSelect = document.getElementById('professor_id');

    // Data mínima e validação data fim >= data início
    const dataInicioEl = document.getElementById('data_inicio');
    const dataFimEl = document.getElementById('data_fim');
    if (dataInicioEl && dataFimEl) {
        dataFimEl.min = dataInicioEl.min || new Date().toISOString().split('T')[0];
        dataInicioEl.addEventListener('change', function() {
            dataFimEl.min = this.value;
            if (dataFimEl.value && dataFimEl.value < this.value) dataFimEl.value = this.value;
        });
        dataFimEl.addEventListener('change', function() {
            if (dataInicioEl.value && dataInicioEl.value > this.value) dataInicioEl.value = this.value;
        });
    }

    // Carregar turmas do professor
    async function carregarTurmas(professorId) {
        blocoTurmas.classList.remove('hidden');
        turmasLista.innerHTML = '';
        turmasContainer.classList.add('hidden');
        turmasEmpty.classList.add('hidden');
        turmasLoading.classList.remove('hidden');
        selecaoAlunos.classList.add('hidden');
        turmaIdHidden.value = '';

        if (!professorId) {
            turmasLoading.classList.add('hidden');
            blocoTurmas.classList.add('hidden');
            return;
        }
        try {
            const res = await fetch(URL_BASE + '/admin/jornadas/turmas-do-professor?professor_id=' + encodeURIComponent(professorId));
            const data = await res.json();
            turmasLoading.classList.add('hidden');
            if (data.success && data.turmas && data.turmas.length > 0) {
                turmasContainer.classList.remove('hidden');
                data.turmas.forEach(function(t) {
                    const label = document.createElement('label');
                    label.className = 'flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded';
                    label.innerHTML = '<input type="checkbox" name="turmas_id[]" value="' + t.id + '" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 turma-checkbox" data-turma-id="' + t.id + '">' +
                        '<span class="text-sm text-gray-700">' + (t.nome || '') + '</span>';
                    turmasLista.appendChild(label);
                });
                turmasLista.querySelectorAll('.turma-checkbox').forEach(function(cb) {
                    cb.addEventListener('change', onTurmasChange);
                });
            } else {
                turmasEmpty.classList.remove('hidden');
            }
        } catch (e) {
            turmasLoading.classList.add('hidden');
            turmasEmpty.classList.remove('hidden');
            turmasEmpty.querySelector('p').textContent = 'Erro ao carregar turmas. Tente novamente.';
        }
    }

    function getTurmasSelecionadas() {
        return Array.from(document.querySelectorAll('.turma-checkbox:checked')).map(function(c) { return c.value; });
    }

    async function carregarAlunos(professorId, turmasIds) {
        if (turmasIds.length === 0) {
            listaAlunos.innerHTML = '';
            return;
        }
        listaAlunos.innerHTML = '<div class="text-sm text-gray-500 p-2">Carregando alunos...</div>';
        try {
            const url = URL_BASE + '/admin/jornadas/buscar-alunos-criar?professor_id=' + encodeURIComponent(professorId) + '&turmas=' + turmasIds.join(',');
            const res = await fetch(url);
            const data = await res.json();
            listaAlunos.innerHTML = '';
            if (data.success && data.alunos && data.alunos.length > 0) {
                data.alunos.forEach(function(aluno) {
                    const label = document.createElement('label');
                    label.className = 'flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded';
                    label.innerHTML = '<input type="checkbox" name="alunos_id[]" value="' + aluno.id + '" class="rounded border-gray-300 text-purple-600 focus:ring-purple-500 aluno-checkbox">' +
                        '<span class="text-sm text-gray-700">' + (aluno.nome || '') + ' (' + (aluno.ra || '') + ') - ' + (aluno.turma_nome || '') + '</span>';
                    listaAlunos.appendChild(label);
                });
            } else {
                listaAlunos.innerHTML = '<div class="text-sm text-gray-500 p-2">Nenhum aluno encontrado nas turmas selecionadas.</div>';
            }
        } catch (e) {
            listaAlunos.innerHTML = '<div class="text-sm text-red-500 p-2">Erro ao carregar alunos.</div>';
        }
    }

    function onTurmasChange() {
        var ids = getTurmasSelecionadas();
        turmaIdHidden.value = ids.length > 0 ? ids[0] : '';
        if (ids.length > 0) {
            selecaoAlunos.classList.remove('hidden');
            carregarAlunos(professorSelect.value, ids);
        } else {
            selecaoAlunos.classList.add('hidden');
            containerAlunos.classList.add('hidden');
        }
    }

    professorSelect.addEventListener('change', function() {
        carregarTurmas(this.value);
    });

    document.querySelectorAll('.tipo-selecao-alunos').forEach(function(radio) {
        radio.addEventListener('change', function() {
            if (this.value === 'especificos') {
                containerAlunos.classList.remove('hidden');
            } else {
                containerAlunos.classList.add('hidden');
                document.querySelectorAll('.aluno-checkbox').forEach(function(c) { c.checked = false; });
            }
        });
    });

    document.getElementById('jornadaForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        var turmasIds = getTurmasSelecionadas();
        if (turmasIds.length === 0) {
            document.getElementById('errorMessage').textContent = 'Selecione pelo menos uma turma.';
            document.getElementById('errorMessage').classList.remove('hidden');
            return;
        }
        var errorDiv = document.getElementById('errorMessage');
        var successDiv = document.getElementById('successMessage');
        var submitBtn = this.querySelector('button[type="submit"]');
        var submitText = document.getElementById('submitText');
        var loadingText = document.getElementById('loadingText');
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        loadingText.classList.remove('hidden');
        try {
            var formData = new FormData(this);
            var response = await fetch(URL_BASE + '/admin/jornadas', { method: 'POST', body: formData });
            var result = await response.json();
            if (response.ok && result.success) {
                successDiv.textContent = result.message || 'Jornada criada com sucesso!';
                successDiv.classList.remove('hidden');
                setTimeout(function() { window.location.href = URL_BASE + '/admin/jornadas'; }, 2000);
            } else {
                errorDiv.textContent = result.error || 'Erro ao criar jornada';
                errorDiv.classList.remove('hidden');
            }
        } catch (err) {
            errorDiv.textContent = 'Erro de conexão. Tente novamente.';
            errorDiv.classList.remove('hidden');
        } finally {
            submitBtn.disabled = false;
            submitText.classList.remove('hidden');
            loadingText.classList.add('hidden');
        }
    });
})();
</script>
