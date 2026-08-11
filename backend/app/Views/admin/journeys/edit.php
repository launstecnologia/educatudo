<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Editar Jornada 📝
            </h2>
            <p class="text-gray-600">
                Edite os dados da jornada de aprendizado
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
        <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?>">
        
        <!-- Professor -->
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
                        <option value="<?= htmlspecialchars($professor['id']) ?>" 
                                <?= $professor['id'] == $jornada['professor_id'] ? 'selected' : '' ?>>
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
                   value="<?= htmlspecialchars($jornada['titulo']) ?>"
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                   placeholder="Ex: Introdução à Matemática">
            <p class="mt-1 text-sm text-gray-500">Nome que identifica esta jornada</p>
        </div>

        <!-- Descrição -->
        <div>
            <label for="descricao" class="block text-sm font-semibold text-gray-700 mb-2">
                Descrição
            </label>
            <textarea id="descricao" name="descricao" rows="3"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                      placeholder="Descreva o objetivo desta jornada..."><?= htmlspecialchars($jornada['descricao'] ?? '') ?></textarea>
            <p class="mt-1 text-sm text-gray-500">Breve descrição dos objetivos da jornada</p>
        </div>

        <!-- Turmas (múltiplas: garante que 8º A, 8º B, 8º C continuem vendo a jornada após edição) -->
        <?php 
        $turmasSelecionadasIds = isset($estrutura['turmas_selecionadas']) && is_array($estrutura['turmas_selecionadas']) 
            ? array_map('intval', $estrutura['turmas_selecionadas']) 
            : [(int)$jornada['turma_id']]; 
        ?>
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
                        Nenhuma turma cadastrada no sistema.
                    </p>
                </div>
            <?php else: ?>
                <div class="border border-gray-300 rounded-lg p-4 max-h-48 overflow-y-auto bg-white">
                    <div class="space-y-2">
                        <?php foreach ($turmas as $turma): ?>
                            <label class="flex items-center space-x-2 cursor-pointer">
                                <input type="checkbox" name="turmas_id[]" value="<?= (int)$turma['id'] ?>"
                                       class="turma-check rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                                    <?= in_array((int)$turma['id'], $turmasSelecionadasIds, true) ? 'checked' : '' ?>>
                                <span class="text-sm text-gray-700"><?= htmlspecialchars($turma['nome']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <input type="hidden" name="turma_id" id="turma_id" value="<?= (int)($turmasSelecionadasIds[0] ?? $jornada['turma_id']) ?>">
            <?php endif; ?>
            <p class="mt-1 text-sm text-gray-500">Selecione uma ou mais turmas que receberão esta jornada (obrigatório ao menos uma)</p>
        </div>

        <!-- Matéria -->
        <div>
            <label for="materia_id" class="block text-sm font-semibold text-gray-700 mb-2">
                Matéria
            </label>
            <?php if (empty($materias)): ?>
                <div class="bg-yellow-50 border border-yellow-200 rounded-lg p-4">
                    <p class="text-sm text-yellow-800">
                        <svg class="w-4 h-4 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.732 16.5c-.77.833.192 2.5 1.732 2.5z"></path>
                        </svg>
                        Nenhuma matéria cadastrada no sistema.
                    </p>
                </div>
            <?php else: ?>
                <select id="materia_id" name="materia_id"
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                    <option value="">Selecione uma matéria (opcional)</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= htmlspecialchars($materia['id']) ?>" 
                                data-cor="<?= htmlspecialchars($materia['cor'] ?? '') ?>"
                                data-icone="<?= htmlspecialchars($materia['icone'] ?? '') ?>"
                                <?= $materia['id'] == $jornada['materia_id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($materia['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
            <p class="mt-1 text-sm text-gray-500">Matéria principal da jornada (opcional)</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="ano_letivo" class="block text-sm font-semibold text-gray-700 mb-2">
                    Ano Letivo <span class="text-red-500">*</span>
                </label>
                <input type="number" id="ano_letivo" name="ano_letivo" required min="2000" max="2100"
                       value="<?= htmlspecialchars((string)($jornada['ano_letivo'] ?? date('Y'))) ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
            </div>
            <div>
                <label for="bimestre" class="block text-sm font-semibold text-gray-700 mb-2">
                    Bimestre <span class="text-red-500">*</span>
                </label>
                <select id="bimestre" name="bimestre" required
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
                    <option value="">Selecione</option>
                    <option value="2" <?= (int)($jornada['bimestre'] ?? 0) === 2 ? 'selected' : '' ?>>2º Bimestre</option>
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

        <!-- Período -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label for="data_inicio" class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Início <span class="text-red-500">*</span>
                </label>
                <input type="date" id="data_inicio" name="data_inicio" required
                       value="<?= isset($estrutura['data_inicio']) ? htmlspecialchars($estrutura['data_inicio']) : '' ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
            </div>
            <div>
                <label for="data_fim" class="block text-sm font-semibold text-gray-700 mb-2">
                    Data de Fim <span class="text-red-500">*</span>
                </label>
                <input type="date" id="data_fim" name="data_fim" required
                       value="<?= isset($estrutura['data_fim']) ? htmlspecialchars($estrutura['data_fim']) : '' ?>"
                       class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors">
            </div>
        </div>

        <!-- Objetivos -->
        <div>
            <label for="objetivos" class="block text-sm font-semibold text-gray-700 mb-2">
                Objetivos de Aprendizagem
            </label>
            <textarea id="objetivos" name="objetivos" rows="4"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                      placeholder="Descreva os objetivos que os alunos devem alcançar..."><?= isset($estrutura['objetivos']) ? htmlspecialchars($estrutura['objetivos']) : '' ?></textarea>
            <p class="mt-1 text-sm text-gray-500">O que os alunos devem aprender nesta jornada</p>
        </div>

        <!-- Critérios de Avaliação -->
        <div>
            <label for="criterios_avaliacao" class="block text-sm font-semibold text-gray-700 mb-2">
                Critérios de Avaliação
            </label>
            <textarea id="criterios_avaliacao" name="criterios_avaliacao" rows="3"
                      class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-purple-500 transition-colors"
                      placeholder="Como os alunos serão avaliados..."><?= isset($estrutura['criterios_avaliacao']) ? htmlspecialchars($estrutura['criterios_avaliacao']) : '' ?></textarea>
            <p class="mt-1 text-sm text-gray-500">Critérios para avaliar o progresso dos alunos</p>
        </div>

        <!-- Error Message -->
        <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
        </div>

        <!-- Success Message -->
        <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg">
        </div>

        <!-- Submit Button -->
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/jornadas" 
               class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition-colors">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700 transition-all duration-300 shadow-lg hover:shadow-xl">
                <span id="submitText">Salvar Alterações</span>
                <span id="loadingText" class="hidden">Salvando...</span>
            </button>
        </div>
    </form>
</div>

<script>
    // Validar que data fim seja maior que data início
    document.getElementById('data_inicio').addEventListener('change', function() {
        const dataFim = document.getElementById('data_fim');
        dataFim.min = this.value;
        if (dataFim.value && dataFim.value < this.value) {
            dataFim.value = this.value;
        }
    });

    // Sincroniza turma_id (principal) com a primeira turma marcada
    document.querySelectorAll('.turma-check').forEach(function(cb) {
        cb.addEventListener('change', function() {
            const checked = document.querySelectorAll('.turma-check:checked');
            const first = checked.length ? checked[0].value : '';
            document.getElementById('turma_id').value = first;
        });
    });

    // Submit do formulário
    document.getElementById('jornadaForm').addEventListener('submit', async function(e) {
        e.preventDefault();
        const checked = document.querySelectorAll('.turma-check:checked');
        if (!checked.length) {
            document.getElementById('errorMessage').textContent = 'Selecione pelo menos uma turma.';
            document.getElementById('errorMessage').classList.remove('hidden');
            return;
        }
        
        const formData = new FormData(this);
        const errorDiv = document.getElementById('errorMessage');
        const successDiv = document.getElementById('successMessage');
        const submitBtn = this.querySelector('button[type="submit"]');
        const submitText = document.getElementById('submitText');
        const loadingText = document.getElementById('loadingText');
        
        // Limpa mensagens anteriores
        errorDiv.classList.add('hidden');
        successDiv.classList.add('hidden');
        
        // Mostra loading
        submitBtn.disabled = true;
        submitText.classList.add('hidden');
        loadingText.classList.remove('hidden');
        
        try {
            const response = await fetch('<?= URL ?>/admin/jornadas/<?= $jornada['id'] ?>/atualizar', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (response.ok && result.success) {
                successDiv.textContent = result.message || 'Jornada atualizada com sucesso!';
                successDiv.classList.remove('hidden');
                
                // Redireciona após 2 segundos
                setTimeout(() => {
                    window.location.href = '<?= URL ?>/admin/jornadas';
                }, 2000);
            } else {
                errorDiv.textContent = result.error || 'Erro ao atualizar jornada';
                errorDiv.classList.remove('hidden');
            }
        } catch (error) {
            errorDiv.textContent = 'Erro de conexão. Tente novamente.';
            errorDiv.classList.remove('hidden');
        } finally {
            // Remove loading
            submitBtn.disabled = false;
            submitText.classList.remove('hidden');
            loadingText.classList.add('hidden');
        }
    });
</script>
