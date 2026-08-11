<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Criar Nova Jornada
            </h2>
            <p class="text-gray-600">
                Crie uma nova jornada de aprendizado para seus alunos
            </p>
        </div>
        <a href="<?= URL ?>/professor/jornadas" 
           class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>
</div>

<!-- Form Section -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-blue-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Dados da Jornada</h3>
    </div>
    
    <form id="jornadaForm" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        
        <!-- Título da Jornada -->
        <div>
            <label for="titulo" class="block text-sm font-semibold text-gray-700 mb-2">
                Título da Jornada <span class="text-red-500">*</span>
            </label>
            <input type="text" id="titulo" name="titulo" required
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors"
                   placeholder="Ex: Introdução à Matemática">
            <p class="mt-1 text-sm text-gray-500">Nome que identifica esta jornada</p>
        </div>

        <!-- Turmas (Múltipla Seleção) -->
        <div>
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                Turmas <span class="text-red-500">*</span>
            </label>
            <?php if (empty($turmas)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        Você não está vinculado a nenhuma turma. 
                        <a href="<?= URL ?>/admin/teachers" class="text-blue-600 hover:text-blue-700 underline">
                            Contate o administrador
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <div class="border border-gray-300 rounded-lg p-4 max-h-48 overflow-y-auto bg-white">
                    <div class="space-y-2">
                        <?php foreach ($turmas as $turma): ?>
                            <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                                <input type="checkbox" name="turmas_id[]" value="<?= htmlspecialchars($turma['id']) ?>"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 turma-checkbox"
                                       data-turma-id="<?= htmlspecialchars($turma['id']) ?>"
                                       data-turma-nome="<?= htmlspecialchars($turma['nome']) ?>">
                                <span class="text-sm text-gray-700"><?= htmlspecialchars($turma['nome']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" id="turma_id" name="turma_id" value="">
            <?php endif; ?>
            <p class="mt-1 text-sm text-gray-500">Selecione uma ou mais turmas que receberão esta jornada</p>
        </div>

        <!-- Seleção de Alunos (aparece quando turmas são selecionadas) -->
        <div id="selecaoAlunos" class="hidden">
            <label class="block text-sm font-semibold text-gray-700 mb-2">
                Alunos
            </label>
            <div class="mb-3">
                <label class="flex items-center space-x-2 cursor-pointer">
                    <input type="radio" name="tipo_selecao_alunos" value="todos" checked
                           class="text-blue-600 focus:ring-blue-500 tipo-selecao-alunos">
                    <span class="text-sm text-gray-700">Todos os alunos das turmas selecionadas</span>
                </label>
                <label class="flex items-center space-x-2 cursor-pointer mt-2">
                    <input type="radio" name="tipo_selecao_alunos" value="especificos"
                           class="text-blue-600 focus:ring-blue-500 tipo-selecao-alunos">
                    <span class="text-sm text-gray-700">Selecionar alunos específicos</span>
                </label>
            </div>
            <div id="containerAlunos" class="hidden border border-gray-300 rounded-lg p-4 max-h-64 overflow-y-auto bg-white">
                <div id="listaAlunos" class="space-y-2">
                    <!-- Alunos serão carregados dinamicamente via JavaScript -->
                </div>
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
                    <p class="text-sm text-yellow-800">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        Nenhuma matéria cadastrada no sistema. 
                        <a href="<?= URL ?>/admin/jornadas" class="text-blue-600 hover:text-blue-700 underline">
                            Cadastre matérias primeiro
                        </a>
                    </p>
                </div>
            <?php else: ?>
                <select id="materia_id" name="materia_id" required
                        class="w-full px-4 pr-10 py-3 border border-gray-300 rounded-lg bg-white text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors appearance-none"
                        style="-webkit-appearance:none; -moz-appearance:none; appearance:none; background-image:url('data:image/svg+xml;utf8,<svg xmlns=&quot;http://www.w3.org/2000/svg&quot; width=&quot;20&quot; height=&quot;20&quot; viewBox=&quot;0 0 20 20&quot; fill=&quot;none&quot;><path d=&quot;M5 7.5L10 12.5L15 7.5&quot; stroke=&quot;%236B7280&quot; stroke-width=&quot;1.8&quot; stroke-linecap=&quot;round&quot; stroke-linejoin=&quot;round&quot;/></svg>'); background-repeat:no-repeat; background-position:right 0.75rem center; background-size:1rem;">
                    <option value="">Selecione uma matéria</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= htmlspecialchars($materia['id'] ?? '') ?>" 
                                data-cor="<?= htmlspecialchars($materia['cor'] ?? '') ?>"
                                data-icone="<?= htmlspecialchars($materia['icone'] ?? '') ?>">
                            <?= htmlspecialchars($materia['nome'] ?? '') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <p class="mt-1 text-sm text-gray-500">Matéria principal da jornada</p>
        </div>

        <?php $anoAtual = (int)date('Y'); ?>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div>
                <label for="ano_letivo" class="block text-sm font-semibold text-gray-700 mb-2">
                    Ano Letivo <span class="text-red-500">*</span>
                </label>
                <input type="number" id="ano_letivo" name="ano_letivo" required min="2000" max="2100"
                       value="<?= $anoAtual ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-gray-900 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label for="bimestre" class="block text-sm font-semibold text-gray-700 mb-2">
                    Bimestre <span class="text-red-500">*</span>
                </label>
                <select id="bimestre" name="bimestre" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-gray-900 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="">Selecione</option>
                    <option value="2">2º Bimestre</option>
                    <option value="3">3º Bimestre</option>
                </select>
            </div>
            <div>
                <label for="avaliativo" class="flex items-center gap-2 text-sm font-semibold text-gray-700 mb-2">
                    <span>Avaliativo</span>
                    <button type="button" id="btnAvaliativoInfo" class="inline-flex items-center justify-center w-5 h-5 rounded-full border border-blue-400 text-blue-600 text-xs hover:bg-blue-50" aria-label="O que significa avaliativo?">?</button>
                </label>
                <select id="avaliativo" name="avaliativo"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-gray-900 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    <option value="1" selected>Sim</option>
                    <option value="0">Não</option>
                </select>
            </div>
        </div>

        <!-- Período: data mínima = hoje (permite começar a jornada hoje) -->
        <?php $dataMinimaHoje = date('Y-m-d'); ?>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
            <div>
                <label for="data_inicio" class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Início <span class="text-red-500">*</span>
                </label>
                <input type="date" id="data_inicio" name="data_inicio" required
                       min="<?= htmlspecialchars($dataMinimaHoje) ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-gray-900 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label for="hora_inicio" class="block text-sm font-semibold text-gray-700 mb-2">
                    Horário de Início
                </label>
                <input type="time" id="hora_inicio" name="hora_inicio"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-gray-900 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label for="data_fim" class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Fim <span class="text-red-500">*</span>
                </label>
                <input type="date" id="data_fim" name="data_fim" required
                       min="<?= htmlspecialchars($dataMinimaHoje) ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-gray-900 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
            </div>
            <div>
                <label for="hora_fim" class="block text-sm font-semibold text-gray-700 mb-2">
                    Horário de Fim
                </label>
                <input type="time" id="hora_fim" name="hora_fim"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg bg-white text-gray-900 appearance-none focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
            </div>
        </div>

        <!-- Error Message -->
        <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        </div>

        <!-- Success Message -->
        <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/professor/jornadas" 
               class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-3 bg-gradient-to-r from-blue-600 to-indigo-600 text-white rounded-lg hover:from-blue-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                <span id="submitText">Criar Jornada</span>
                <span id="loadingText" class="hidden">Criando...</span>
            </button>
        </div>
    </form>
</div>

<!-- Modal: Explicação Avaliativo -->
<div id="avaliativoInfoModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/50"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full p-6">
            <h4 class="text-lg font-semibold text-gray-900 mb-3">O que significa "Avaliativo"?</h4>
            <p class="text-sm text-gray-700 leading-relaxed">
                Quando a jornada está marcada como <strong>Sim</strong>, as atividades contam para a nota do aluno no boletim.
                Quando está marcada como <strong>Não</strong>, a jornada continua disponível para estudo e prática, mas não entra no cálculo da nota.
            </p>
            <div class="flex justify-end mt-6">
                <button type="button" id="fecharAvaliativoInfoModal" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">Entendi</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal: Loading -->
<div id="savingModal" class="hidden fixed inset-0 z-50">
    <div class="absolute inset-0 bg-black/45"></div>
    <div class="relative z-10 min-h-screen flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-2xl p-6 w-full max-w-sm text-center">
            <div class="mx-auto mb-4 h-10 w-10 rounded-full border-4 border-blue-200 border-t-blue-600 animate-spin"></div>
            <p class="text-base font-semibold text-gray-900">Salvando jornada...</p>
            <p class="text-sm text-gray-600 mt-1">Aguarde, estamos finalizando os dados.</p>
        </div>
    </div>
</div>

<script>
    // Data mínima já definida no HTML (hoje, via PHP). Garantir data_fim >= data_inicio ao mudar data_inicio
    (function() {
        const dataInicioEl = document.getElementById('data_inicio');
        const dataFimEl = document.getElementById('data_fim');
        if (dataInicioEl && dataFimEl && dataInicioEl.min) {
            dataFimEl.min = dataInicioEl.min; // data fim pode ser no mínimo hoje (ou data início)
        }
    })();

    // Validar que data fim seja maior ou igual à data início (permite mesmo dia)
    document.getElementById('data_inicio').addEventListener('change', function() {
        const dataFim = document.getElementById('data_fim');
        dataFim.min = this.value; // Permite mesmo dia (>=)
        if (dataFim.value && dataFim.value < this.value) {
            dataFim.value = this.value; // Ajusta automaticamente se for menor
        }
    });
    
    // Validar que data início seja menor ou igual à data fim (permite mesmo dia)
    document.getElementById('data_fim').addEventListener('change', function() {
        const dataInicio = document.getElementById('data_inicio');
        if (dataInicio.value && dataInicio.value > this.value) {
            dataInicio.value = this.value; // Ajusta automaticamente se for maior
        }
    });

    // Gerenciar seleção de turmas e alunos
    const turmaCheckboxes = document.querySelectorAll('.turma-checkbox');
    const selecaoAlunos = document.getElementById('selecaoAlunos');
    const containerAlunos = document.getElementById('containerAlunos');
    const listaAlunos = document.getElementById('listaAlunos');
    const tipoSelecaoAlunos = document.querySelectorAll('.tipo-selecao-alunos');
    const turmaIdHidden = document.getElementById('turma_id');

    // Função para carregar alunos das turmas selecionadas
    async function carregarAlunos(turmasIds) {
        if (turmasIds.length === 0) {
            listaAlunos.innerHTML = '';
            return;
        }

        try {
            listaAlunos.innerHTML = '<div class="text-sm text-gray-500 p-2">Carregando alunos...</div>';
            
            const response = await fetch('<?= URL ?>/professor/jornadas/buscar-alunos?turmas=' + turmasIds.join(','));
            
            if (!response.ok) {
                throw new Error(`Erro HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.alunos) {
                listaAlunos.innerHTML = '';
                if (data.alunos.length === 0) {
                    listaAlunos.innerHTML = '<div class="text-sm text-gray-500 p-2">Nenhum aluno encontrado nas turmas selecionadas.</div>';
                } else {
                    data.alunos.forEach(aluno => {
                        const label = document.createElement('label');
                        label.className = 'flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded';
                        label.innerHTML = `
                            <input type="checkbox" name="alunos_id[]" value="${aluno.id}"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 aluno-checkbox">
                            <span class="text-sm text-gray-700">${aluno.nome} (${aluno.ra || ''}) - ${aluno.turma_nome || ''}</span>
                        `;
                        listaAlunos.appendChild(label);
                    });
                }
            } else {
                listaAlunos.innerHTML = '<div class="text-sm text-red-500 p-2">Erro ao carregar alunos: ' + (data.error || 'Erro desconhecido') + '</div>';
            }
        } catch (error) {
            console.error('Erro ao carregar alunos:', error);
            listaAlunos.innerHTML = '<div class="text-sm text-red-500 p-2">Erro ao carregar alunos. Tente novamente.</div>';
        }
    }

    // Quando turmas são selecionadas/deselecionadas
    turmaCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const turmasSelecionadas = Array.from(document.querySelectorAll('.turma-checkbox:checked'))
                .map(cb => cb.value);
            
            // Atualiza campo hidden com primeira turma (para compatibilidade)
            if (turmasSelecionadas.length > 0) {
                turmaIdHidden.value = turmasSelecionadas[0];
                selecaoAlunos.classList.remove('hidden');
                carregarAlunos(turmasSelecionadas);
            } else {
                turmaIdHidden.value = '';
                selecaoAlunos.classList.add('hidden');
                containerAlunos.classList.add('hidden');
            }
        });
    });

    // Quando tipo de seleção de alunos muda
    tipoSelecaoAlunos.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'especificos') {
                containerAlunos.classList.remove('hidden');
            } else {
                containerAlunos.classList.add('hidden');
                // Desmarca todos os alunos
                document.querySelectorAll('.aluno-checkbox').forEach(cb => cb.checked = false);
            }
        });
    });

    // Submit do formulário
    document.getElementById('jornadaForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const formData = new FormData(this);
        const errorDiv = document.getElementById('errorMessage');
        const successDiv = document.getElementById('successMessage');
        const submitBtn = this.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submitText');
        const loadingText = document.getElementById('loadingText');
        const savingModal = document.getElementById('savingModal');
        
        // Limpa mensagens anteriores
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
        
        // Mostra loading
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        loadingText.classList.remove('hidden');
        savingModal.classList.remove('hidden');
        
        try {
            const response = await fetch('<?= URL ?>/professor/jornadas', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (response.ok) {
                successDiv.textContent = result.message;
                successDiv.classList.remove('hidden');
                window.location.href = '<?= URL ?>/professor/jornadas';
            } else {
                errorDiv.textContent = result.error || 'Erro ao criar jornada';
                errorDiv.classList.remove('hidden');
                savingModal.classList.add('hidden');
            }
        } catch (error) {
            errorDiv.textContent = 'Erro de conexão. Tente novamente.';
            errorDiv.classList.remove('hidden');
            savingModal.classList.add('hidden');
        } finally {
            // Remove loading
            submitBtn.disabled = false;
            submitText.classList.remove('hidden');
            loadingText.classList.add('hidden');
        }
    });

    // Modal de explicação do campo Avaliativo
    (function() {
        const btnInfo = document.getElementById('btnAvaliativoInfo');
        const modal = document.getElementById('avaliativoInfoModal');
        const btnFechar = document.getElementById('fecharAvaliativoInfoModal');

        if (btnInfo && modal && btnFechar) {
            btnInfo.addEventListener('click', function() {
                modal.classList.remove('hidden');
            });
            btnFechar.addEventListener('click', function() {
                modal.classList.add('hidden');
            });
            modal.addEventListener('click', function(event) {
                if (event.target === modal || event.target.classList.contains('bg-black/50')) {
                    modal.classList.add('hidden');
                }
            });
        }
    })();
</script>
