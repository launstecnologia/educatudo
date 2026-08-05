<!-- Header Section -->
<style>
#formBloco select {
    -webkit-appearance: none;
    appearance: none;
    background-color: #fff;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M6 8l4 4 4-4' stroke='%236b7280' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 1rem 1rem;
    padding-right: 2.5rem;
    min-height: 42px;
}

#formBloco select::-ms-expand {
    display: none;
}

#formBloco select:focus {
    outline: none;
}
</style>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Criar Evento Prova Online 📚
            </h2>
            <p class="text-gray-600">
                Crie um evento de prova onde o professor poderá criar a prova dentro dele
            </p>
        </div>

        <a href="<?= URL ?>/admin/provas" 
           class="text-gray-600 hover:text-gray-900">
            ← Voltar
        </a>
    </div>
</div>

<!-- Form -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="formBloco" onsubmit="salvarBloco(event)">
        <!-- Título -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Título do Evento <span class="text-red-500">*</span>
            </label>
            <input type="text" 
                   id="titulo" 
                   name="titulo" 
                   required
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                   placeholder="Ex: Prova Bimestral 1º Bimestre">
        </div>

        <!-- Descrição -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Descrição
            </label>
            <textarea id="descricao" 
                      name="descricao" 
                      rows="3"
                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                      placeholder="Descrição opcional do evento"></textarea>
        </div>

        <!-- Ano Letivo e Bimestre -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Ano Letivo <span class="text-red-500">*</span>
                </label>
                <input type="number"
                       id="ano_letivo"
                       name="ano_letivo"
                       min="2000"
                       max="2100"
                       required
                       value="<?= date('Y') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                       placeholder="Ex: <?= date('Y') ?>">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Bimestre <span class="text-red-500">*</span>
                </label>
                <select id="bimestre"
                        name="bimestre"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione</option>
                    <option value="1">1º Bimestre</option>
                    <option value="2">2º Bimestre</option>
                    <option value="3">3º Bimestre</option>
                    <option value="4">4º Bimestre</option>
                </select>
            </div>
        </div>

        <div class="mb-6">
            <div class="flex items-center justify-between mb-2">
                <label class="block text-sm font-medium text-gray-700">
                    Tipo de Avaliação <span class="text-red-500">*</span>
                </label>
                <a href="<?= URL ?>/admin/provas/tipos-avaliacao" target="_blank" class="text-xs text-purple-700 hover:text-purple-900">Gerenciar tipos</a>
            </div>
            <select id="tipo_avaliacao_id"
                    name="tipo_avaliacao_id"
                    required
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="">Selecione</option>
                <?php foreach (($tiposAvaliacao ?? []) as $tipo): ?>
                    <option value="<?= (int)$tipo['id'] ?>"><?= htmlspecialchars($tipo['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Bloco Professores (opcional) -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Bloco Professores (Opcional)
            </label>
            <select id="bloco_modelo_id" 
                    name="bloco_modelo_id"
                    onchange="carregarModelo(this.value)"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="">-- Selecione um bloco de professores (opcional) --</option>
                <?php if (!empty($blocosModelo)): ?>
                    <?php foreach ($blocosModelo as $modelo): ?>
                        <option value="<?= $modelo['id'] ?>">
                            <?= htmlspecialchars($modelo['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">
                Selecione um bloco de professores para preencher automaticamente os professores, matérias e número de questões
            </p>
        </div>

        <!-- Turmas do Bloco -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Turmas <span class="text-red-500">*</span>
            </label>
            <p class="text-sm text-gray-500 mb-3">Selecione as turmas que participarão deste bloco de provas:</p>
            <div class="border border-gray-200 rounded-lg p-4 max-h-64 overflow-y-auto bg-white">
                <?php if (!empty($turmas)): ?>
                    <?php foreach ($turmas as $turma): ?>
                        <label class="flex items-center p-2 hover:bg-gray-50 rounded cursor-pointer">
                            <input type="checkbox" 
                                   name="turmas[]" 
                                   value="<?= $turma['id'] ?>"
                                   class="w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                            <div class="ml-2">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($turma['nome']) ?></div>
                                <?php if (!empty($turma['serie'])): ?>
                                    <div class="text-xs text-gray-500">Série: <?= htmlspecialchars($turma['serie']) ?></div>
                                <?php endif; ?>
                            </div>
                        </label>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p class="text-sm text-gray-500">Nenhuma turma disponível</p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Professores e Matérias (Múltiplos) -->
        <div class="mb-6">
            <div class="flex items-center justify-between mb-4">
                <label class="block text-sm font-medium text-gray-700">
                    Professores e Matérias <span class="text-red-500">*</span>
                </label>
                <button type="button" 
                        onclick="adicionarProfessor()"
                        class="btn-primary-custom px-4 py-2 text-sm font-semibold rounded-lg transition-colors hover:opacity-90">
                    + Adicionar Professor
                </button>
            </div>
            <p class="text-sm text-gray-500 mb-4">Adicione um ou mais professores com suas matérias:</p>
            
            <div id="professoresContainer" class="space-y-4">
                <!-- Primeiro professor será adicionado aqui via JavaScript -->
            </div>
        </div>

        <!-- Visível no portal do aluno -->
        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
            <label class="flex items-start cursor-pointer">
                <input type="checkbox"
                       id="visivel_no_portal_aluno"
                       name="visivel_no_portal_aluno"
                       value="1"
                       class="mt-1 w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <span class="ml-3">
                    <span class="block text-sm font-medium text-gray-900">Mostrar este evento no portal do aluno</span>
                    <span class="block text-xs text-gray-600 mt-1">Desmarcado: o aluno não vê em &quot;Minhas provas&quot; nem acessa por link (útil para provas bimestrais internas). A coordenação pode ativar depois na edição do evento.</span>
                </span>
            </label>
        </div>

        <!-- Tipo de Prova -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Prova <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 mb-2">Pedagógico: original ou substitutiva (independe de como a nota será lançada).</p>
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center">
                    <input type="radio" 
                           name="tipo_prova" 
                           value="original" 
                           checked
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Original</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" 
                           name="tipo_prova" 
                           value="substitutiva"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Substitutiva</span>
                </label>
            </div>
        </div>

        <!-- Formato do evento (online vs nota manual) -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Formato do evento <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 mb-2">Em <strong>lançamento de notas</strong>, não há prova com questões: as notas são lançadas diretamente por professor ou coordenação.</p>
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center">
                    <input type="radio" name="formato_evento" value="online_questoes"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Prova online (professor cria questões)</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="formato_evento" value="lancamento_nota"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Lançamento de notas (sem questões)</span>
                </label>
            </div>
        </div>

        <!-- Configuração de Nota -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Configuração de Nota / Responsável <span class="text-red-500">*</span>
            </label>
            <div id="cfgOnlineOptions" class="flex flex-wrap gap-6">
                <label class="flex items-center">
                    <input type="radio" 
                           name="configuracao_nota" 
                           value="professor_por_questao" 
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Professor coloca por questão</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" 
                           name="configuracao_nota" 
                           value="coordenacao_calcula"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Coordenação faz o cálculo</span>
                </label>
            </div>
            <div id="cfgLancamentoOptions" class="flex flex-wrap gap-6 hidden">
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="coordenacao_calcula"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Coordenação lança as notas dos alunos</span>
                </label>
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="professor_por_questao"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Professor lança as notas dos alunos</span>
                </label>
            </div>
        </div>

        <div id="notaUnicaTodasMateriasBox" class="mb-6 p-4 bg-violet-50 border border-violet-200 rounded-lg hidden">
            <label class="flex items-start cursor-pointer">
                <input type="checkbox"
                       id="nota_unica_todas_materias"
                       name="nota_unica_todas_materias"
                       value="1"
                       class="mt-1 w-4 h-4 text-violet-600 border-gray-300 rounded focus:ring-violet-500">
                <span class="ml-3">
                    <span class="block text-sm font-medium text-violet-900">Mesma nota em todas as matérias do evento</span>
                    <span class="block text-xs text-violet-800 mt-1">Use para ENAC: ao lançar a nota do aluno em uma matéria, o sistema replica a mesma nota para as demais matérias do evento.</span>
                </span>
            </label>
            <p id="msgCoordenacaoLanca" class="text-xs text-violet-900 mt-2 hidden">
                Coordenação lança notas: você pode usar nota única para aplicar a mesma nota do aluno em todas as matérias.
            </p>
        </div>

        <!-- Agenda de Prova (somente para prova online) -->
        <div id="agendamentoDataHoraContainer" class="hidden">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Data da Prova <span class="text-red-500">*</span>
                    </label>
                    <input type="date"
                           id="data_prova"
                           name="data_prova"
                           required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Horário de Início <span class="text-red-500">*</span>
                    </label>
                    <input type="time"
                           id="hora_inicio"
                           name="hora_inicio"
                           required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Horário de Término <span class="text-red-500">*</span>
                    </label>
                    <input type="time"
                           id="hora_fim"
                           name="hora_fim"
                           required
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                </div>
            </div>
        </div>

        <!-- Prazo de Entrega do Professor (prova online e lançamento por professor) -->
        <div id="prazoProfessorContainer" class="hidden">
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Prazo para Professores Enviarem Provas <span class="text-red-500">*</span>
                </label>
                <input type="datetime-local"
                       id="prazo_entrega_professor"
                       name="prazo_entrega_professor"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <p class="text-xs text-gray-500 mt-1">Após este prazo, provas não enviadas serão automaticamente marcadas como "Não Enviadas" e travadas</p>
            </div>
        </div>

        <!-- Botões -->
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/provas" 
               class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Cancelar
            </a>
            <button type="submit" 
                    class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90">
                Criar Evento
            </button>
        </div>
    </form>
</div>

<script>
const professores = <?= json_encode($professores ?? []) ?>;
const materias = <?= json_encode($materias ?? []) ?>;
const turmas = <?= json_encode($turmas ?? []) ?>;
let professorCounter = 0;

function adicionarProfessor() {
    professorCounter++;
    const container = document.getElementById('professoresContainer');
    
    const professorDiv = document.createElement('div');
    professorDiv.className = 'border border-gray-300 rounded-lg p-4 bg-gray-50';
    professorDiv.id = `professor_${professorCounter}`;
    
    professorDiv.innerHTML = `
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-semibold text-gray-700">Professor ${professorCounter}</h4>
            <button type="button" 
                    onclick="removerProfessor(${professorCounter})"
                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                ✕ Remover
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Professor <span class="text-red-500">*</span>
                </label>
                <select name="professores[${professorCounter}][professor_id]" 
                        required
                        onchange="carregarMateriasProfessor(${professorCounter})"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione o professor</option>
                    ${professores.map(p => `
                        <option value="${p.id}" 
                                data-materias='${JSON.stringify(p.materias || [])}'>
                            ${p.nome}
                        </option>
                    `).join('')}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Matéria <span class="text-red-500">*</span>
                </label>
                <select name="professores[${professorCounter}][materia_id]" 
                        id="materia_${professorCounter}"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione primeiro o professor</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Número de Questões <span class="text-red-500">*</span>
                </label>
                <input type="number" 
                       name="professores[${professorCounter}][numero_questoes]" 
                       min="1" 
                       value="5"
                       required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>
    `;
    
    container.appendChild(professorDiv);
}

function removerProfessor(id) {
    const professorDiv = document.getElementById(`professor_${id}`);
    if (professorDiv) {
        professorDiv.remove();
    }
}

function carregarMateriasProfessor(professorIndex) {
    const professorSelect = document.querySelector(`select[name="professores[${professorIndex}][professor_id]"]`);
    const materiaSelect = document.getElementById(`materia_${professorIndex}`);
    
    if (!professorSelect || !materiaSelect) {
        console.warn(`Elementos não encontrados para professor ${professorIndex}`);
        return;
    }
    
    const selectedOption = professorSelect.options[professorSelect.selectedIndex];
    materiaSelect.innerHTML = '<option value="">Selecione a matéria</option>';
    
    if (!selectedOption || !selectedOption.value) {
        console.warn(`Nenhum professor selecionado para índice ${professorIndex}`);
        return;
    }
    
    const materiasJson = selectedOption.getAttribute('data-materias');
    if (!materiasJson) {
        console.warn(`Nenhuma matéria encontrada no atributo data-materias para professor ${professorIndex}`);
        return;
    }
    
    try {
        const materiasProfessor = JSON.parse(materiasJson);
        console.log(`Matérias do professor ${professorIndex}:`, materiasProfessor);
        console.log(`Todas as matérias disponíveis:`, materias);
        
        // Filtra matérias do professor
        const materiasFiltradas = materias.filter(m => 
            materiasProfessor.includes(m.nome)
        );
        
        console.log(`Matérias filtradas para professor ${professorIndex}:`, materiasFiltradas);
        
        if (materiasFiltradas.length === 0) {
            console.warn(`Nenhuma matéria encontrada para professor ${professorIndex}. Matérias do professor:`, materiasProfessor);
        }
        
        materiasFiltradas.forEach(materia => {
            const option = document.createElement('option');
            option.value = materia.id;
            option.textContent = materia.nome;
            materiaSelect.appendChild(option);
        });
    } catch (e) {
        console.error('Erro ao carregar matérias:', e, 'JSON:', materiasJson);
    }
}

function carregarModelo(modeloId) {
    if (!modeloId) {
        // Limpa os professores se não houver modelo selecionado
        document.getElementById('professoresContainer').innerHTML = '';
        professorCounter = 0;
        return;
    }
    
    // Busca dados do modelo via AJAX
    fetch(`<?= URL ?>/admin/blocos-modelo/${modeloId}/dados`)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.modelo && data.modelo.professores) {
                // Limpa professores existentes
                document.getElementById('professoresContainer').innerHTML = '';
                professorCounter = 0;
                
                // Adiciona cada professor do modelo
                data.modelo.professores.forEach(profModelo => {
                    adicionarProfessorDoModelo(profModelo);
                });
            } else {
                alert('Erro ao carregar modelo: ' + (data.error || 'Modelo não encontrado'));
            }
        })
        .catch(error => {
            console.error('Erro ao carregar modelo:', error);
            alert('Erro ao carregar modelo. Tente novamente.');
        });
}

function adicionarProfessorDoModelo(profModelo) {
    professorCounter++;
    const container = document.getElementById('professoresContainer');
    
    // Encontra o professor no array de professores
    const professor = professores.find(p => p.id == profModelo.professor_id);
    if (!professor) {
        console.error('Professor não encontrado:', profModelo.professor_id);
        return;
    }
    
    // Encontra a matéria
    const materia = materias.find(m => m.id == profModelo.materia_id);
    if (!materia) {
        console.error('Matéria não encontrada:', profModelo.materia_id);
        return;
    }
    
    const professorDiv = document.createElement('div');
    professorDiv.className = 'border border-gray-300 rounded-lg p-4 bg-gray-50';
    professorDiv.id = `professor_${professorCounter}`;
    
    professorDiv.innerHTML = `
        <div class="flex items-center justify-between mb-4">
            <h4 class="text-sm font-semibold text-gray-700">Professor ${professorCounter}</h4>
            <button type="button" 
                    onclick="removerProfessor(${professorCounter})"
                    class="text-red-600 hover:text-red-800 text-sm font-medium">
                ✕ Remover
            </button>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Professor <span class="text-red-500">*</span>
                </label>
                <select name="professores[${professorCounter}][professor_id]" 
                        required
                        onchange="carregarMateriasProfessor(${professorCounter})"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione o professor</option>
                    ${professores.map(p => `
                        <option value="${p.id}" 
                                data-materias='${JSON.stringify(p.materias || [])}'
                                ${p.id == profModelo.professor_id ? 'selected' : ''}>
                            ${p.nome}
                        </option>
                    `).join('')}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Matéria <span class="text-red-500">*</span>
                </label>
                <select name="professores[${professorCounter}][materia_id]" 
                        id="materia_${professorCounter}"
                        required
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="">Selecione a matéria</option>
                    ${materias.filter(m => {
                        const materiasProfessor = professor.materias || [];
                        return materiasProfessor.includes(m.nome);
                    }).map(m => `
                        <option value="${m.id}" ${m.id == profModelo.materia_id ? 'selected' : ''}>
                            ${m.nome}
                        </option>
                    `).join('')}
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Número de Questões <span class="text-red-500">*</span>
                </label>
                <input type="number" 
                       name="professores[${professorCounter}][numero_questoes]" 
                       min="1" 
                       value="${profModelo.numero_questoes || 5}"
                       required
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            </div>
        </div>
    `;
    
    container.appendChild(professorDiv);
}

// Adiciona o primeiro professor automaticamente ao carregar
document.addEventListener('DOMContentLoaded', function() {
    adicionarProfessor();
    manterAgendaNoFinalDoFormulario();
    esconderAgendaAteEscolhaInicial();
    document.querySelectorAll('input[name="formato_evento"]').forEach(el => {
        el.addEventListener('change', ajustarOpcoesConfiguracaoNotaPorFormato);
    });
    document.querySelectorAll('input[name="configuracao_nota"]').forEach(el => {
        el.addEventListener('change', ajustarOpcoesConfiguracaoNotaPorFormato);
    });
});

function manterAgendaNoFinalDoFormulario() {
    const form = document.getElementById('formBloco');
    if (!form) return;
    const botoes = form.querySelector('.flex.justify-end.space-x-4');
    const dataHoraBox = document.getElementById('agendamentoDataHoraContainer');
    const prazoBox = document.getElementById('prazoProfessorContainer');
    if (!botoes || !dataHoraBox || !prazoBox) return;
    form.insertBefore(dataHoraBox, botoes);
    form.insertBefore(prazoBox, botoes);
}

function esconderAgendaAteEscolhaInicial() {
    const dataHoraBox = document.getElementById('agendamentoDataHoraContainer');
    const prazoBox = document.getElementById('prazoProfessorContainer');
    const dataProvaInput = document.getElementById('data_prova');
    const horaInicioInput = document.getElementById('hora_inicio');
    const horaFimInput = document.getElementById('hora_fim');
    const prazoInput = document.getElementById('prazo_entrega_professor');
    if (dataHoraBox) dataHoraBox.classList.add('hidden');
    if (prazoBox) prazoBox.classList.add('hidden');
    if (dataProvaInput) dataProvaInput.required = false;
    if (horaInicioInput) horaInicioInput.required = false;
    if (horaFimInput) horaFimInput.required = false;
    if (prazoInput) prazoInput.required = false;
}

function inputConfiguracaoNotaNoFormatoAtual() {
    const formatoSel = document.querySelector('input[name="formato_evento"]:checked')?.value || '';
    const onlineBox = document.getElementById('cfgOnlineOptions');
    const lancBox = document.getElementById('cfgLancamentoOptions');
    if (!formatoSel) {
        return onlineBox?.querySelector('input[name="configuracao_nota"]:checked')
            || document.querySelector('input[name="configuracao_nota"]:checked');
    }
    const scope = formatoSel === 'lancamento_nota' ? lancBox : onlineBox;
    const inScope = scope?.querySelector('input[name="configuracao_nota"]:checked');
    if (inScope) {
        return inScope;
    }
    return document.querySelector('input[name="configuracao_nota"]:checked');
}

function ajustarOpcoesConfiguracaoNotaPorFormato() {
    const formatoSel = document.querySelector('input[name="formato_evento"]:checked')?.value || '';
    const onlineBox = document.getElementById('cfgOnlineOptions');
    const lancBox = document.getElementById('cfgLancamentoOptions');
    const notaUnicaBox = document.getElementById('notaUnicaTodasMateriasBox');
    const msgCoord = document.getElementById('msgCoordenacaoLanca');
    const dataHoraBox = document.getElementById('agendamentoDataHoraContainer');
    const prazoBox = document.getElementById('prazoProfessorContainer');
    const dataProvaInput = document.getElementById('data_prova');
    const horaInicioInput = document.getElementById('hora_inicio');
    const horaFimInput = document.getElementById('hora_fim');
    const prazoInput = document.getElementById('prazo_entrega_professor');

    if (!formatoSel) {
        onlineBox.classList.remove('hidden');
        lancBox.classList.add('hidden');
        if (notaUnicaBox) notaUnicaBox.classList.add('hidden');
        if (msgCoord) msgCoord.classList.add('hidden');
        if (dataHoraBox) dataHoraBox.classList.add('hidden');
        if (prazoBox) prazoBox.classList.add('hidden');
        if (dataProvaInput) dataProvaInput.required = false;
        if (horaInicioInput) horaInicioInput.required = false;
        if (horaFimInput) horaFimInput.required = false;
        if (prazoInput) prazoInput.required = false;
        return;
    } else if (formatoSel === 'lancamento_nota') {
        onlineBox.classList.add('hidden');
        lancBox.classList.remove('hidden');
        let cfgChecked = lancBox.querySelector('input[name="configuracao_nota"]:checked')?.value || '';
        if (!['coordenacao_calcula', 'professor_por_questao'].includes(cfgChecked)) {
            const def = lancBox.querySelector('input[name="configuracao_nota"][value="coordenacao_calcula"]');
            if (def) def.checked = true;
        }
    } else {
        onlineBox.classList.remove('hidden');
        lancBox.classList.add('hidden');
        notaUnicaBox.classList.add('hidden');
        if (msgCoord) msgCoord.classList.add('hidden');
        const notaUnicaInput = document.getElementById('nota_unica_todas_materias');
        if (notaUnicaInput) notaUnicaInput.checked = false;
        let cfgChecked = onlineBox.querySelector('input[name="configuracao_nota"]:checked')?.value || '';
        if (!['professor_por_questao', 'coordenacao_calcula'].includes(cfgChecked)) {
            const def = onlineBox.querySelector('input[name="configuracao_nota"][value="professor_por_questao"]');
            if (def) def.checked = true;
        }
    }

    const cfgFinal = inputConfiguracaoNotaNoFormatoAtual()?.value || '';
    const showDataHora = (formatoSel === 'online_questoes');
    const showPrazo = (formatoSel === 'online_questoes') || (formatoSel === 'lancamento_nota' && cfgFinal === 'professor_por_questao');
    const showNotaUnica = (formatoSel === 'lancamento_nota' && cfgFinal === 'coordenacao_calcula');
    if (notaUnicaBox) notaUnicaBox.classList.toggle('hidden', !showNotaUnica);
    const notaUnicaInput = document.getElementById('nota_unica_todas_materias');
    if (notaUnicaInput && !showNotaUnica) notaUnicaInput.checked = false;
    if (dataHoraBox) dataHoraBox.classList.toggle('hidden', !showDataHora);
    if (prazoBox) prazoBox.classList.toggle('hidden', !showPrazo);
    if (dataProvaInput) dataProvaInput.required = showDataHora;
    if (horaInicioInput) horaInicioInput.required = showDataHora;
    if (horaFimInput) horaFimInput.required = showDataHora;
    if (prazoInput) prazoInput.required = showPrazo;
    if (msgCoord) msgCoord.classList.toggle('hidden', !showNotaUnica);
}

function salvarBloco(event) {
    event.preventDefault();
    
    const form = event.target;
    const formData = new FormData(form);
    
    // Coleta professores com suas matérias e turmas
    const professores = [];
    const professorDivs = document.querySelectorAll('[id^="professor_"]');
    
    if (professorDivs.length === 0) {
        alert('Adicione pelo menos um professor');
        return;
    }
    
    professorDivs.forEach(div => {
        const professorId = div.querySelector('select[name*="[professor_id]"]')?.value;
        const materiaId = div.querySelector('select[name*="[materia_id]"]')?.value;
        const numeroQuestoes = div.querySelector('input[name*="[numero_questoes]"]')?.value;
        
        if (!professorId || !materiaId) {
            alert('Preencha professor e matéria para todos os professores adicionados');
            return;
        }
        
        if (!numeroQuestoes || parseInt(numeroQuestoes) < 1) {
            alert('Defina o número de questões para todos os professores');
            return;
        }
        
        professores.push({
            professor_id: parseInt(professorId),
            materia_id: parseInt(materiaId),
            numero_questoes: parseInt(numeroQuestoes)
        });
    });
    
    // Coleta turmas do bloco (não por professor)
    const turmasCheckboxes = form.querySelectorAll('input[name="turmas[]"]:checked');
    if (turmasCheckboxes.length === 0) {
        alert('Selecione pelo menos uma turma para o bloco');
        return;
    }
    const turmasIds = Array.from(turmasCheckboxes).map(cb => parseInt(cb.value));
    
    const blocoModeloId = formData.get('bloco_modelo_id');
    const formatoEvInput = form.querySelector('input[name="formato_evento"]:checked');
    const tipoProvaInput = form.querySelector('input[name="tipo_prova"]:checked');
    const configNotaInput = inputConfiguracaoNotaNoFormatoAtual();
    const bimestreEl = document.getElementById('bimestre');
    const bimestreVal = (bimestreEl && bimestreEl.value) ? parseInt(bimestreEl.value, 10) : (formData.get('bimestre') ? parseInt(formData.get('bimestre'), 10) : null);

    const data = {
        titulo: formData.get('titulo'),
        descricao: formData.get('descricao') || null,
        ano_letivo: formData.get('ano_letivo') ? parseInt(formData.get('ano_letivo'), 10) : null,
        bimestre: bimestreVal,
        tipo_avaliacao_id: formData.get('tipo_avaliacao_id') ? parseInt(formData.get('tipo_avaliacao_id'), 10) : null,
        professores: professores,
        turmas: turmasIds, // Turmas do bloco (não por professor)
        data_prova: formData.get('data_prova') || null,
        hora_inicio: formData.get('hora_inicio') || null,
        hora_fim: formData.get('hora_fim') || null,
        prazo_entrega_professor: formData.get('prazo_entrega_professor') || null,
        status: 'rascunho',
        tipo_prova: tipoProvaInput ? tipoProvaInput.value : (formData.get('tipo_prova') || 'original'),
        formato_evento: formatoEvInput ? formatoEvInput.value : (formData.get('formato_evento') || 'online_questoes'),
        configuracao_nota: configNotaInput ? configNotaInput.value : formData.get('configuracao_nota'),
        liberar_gabarito: 'imediatamente',
        liberado: 0,
        bloco_modelo_id: blocoModeloId ? parseInt(blocoModeloId, 10) : null,
        visivel_no_portal_aluno: document.getElementById('visivel_no_portal_aluno')?.checked ? 1 : 0,
        nota_unica_todas_materias: document.getElementById('nota_unica_todas_materias')?.checked ? 1 : 0
    };
    
    fetch('<?= URL ?>/admin/provas/blocos', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            window.location.href = '<?= URL ?>/admin/provas';
        } else {
            let msg = data.error || 'Erro desconhecido';
            if (data.missing_columns && data.missing_columns.length) {
                msg += '\n\nColunas faltando no banco: ' + data.missing_columns.join(', ');
            }
            alert(msg);
            if (data.errors) {
                console.error('Erros:', data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao criar evento');
    });
}
</script>
