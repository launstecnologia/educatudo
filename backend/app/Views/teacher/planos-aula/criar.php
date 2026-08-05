<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Criar Novo Plano de Aula 📚
            </h2>
            <p class="text-gray-600">
                Preencha os dados do seu plano de aula
            </p>
        </div>
        <a href="<?= URL ?>/professor/planos-aula" 
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
    <form id="planoForm" class="p-6 space-y-6">
        <!-- Informações Básicas -->
        <div class="border-b border-gray-200 pb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Informações Básicas</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="materia_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        Matéria <span class="text-red-500">*</span>
                    </label>
                    <select id="materia_id" name="materia_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecione uma matéria</option>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?= htmlspecialchars($materia['id']) ?>">
                                <?= htmlspecialchars($materia['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($anos_letivos)): ?>
                <div>
                    <label for="ano_letivo_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        Ano Letivo
                    </label>
                    <select id="ano_letivo_id" name="ano_letivo_id"
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">— Não especificado —</option>
                        <?php foreach ($anos_letivos as $a): ?>
                            <option value="<?= (int) $a['id'] ?>"
                                <?= (int) $a['id'] === ($ano_letivo_ativo_id ?? 0) ? 'selected' : '' ?>>
                                <?= htmlspecialchars((string) $a['ano']) ?><?= !empty($a['ativo']) ? ' (ativo)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="md:col-span-2">
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Turmas <span class="text-red-500">*</span>
                    </label>
                    <div class="border border-gray-300 rounded-lg p-4 max-h-48 overflow-y-auto bg-white">
                        <?php if (empty($turmas)): ?>
                            <p class="text-sm text-gray-500">Nenhuma turma disponível</p>
                        <?php else: ?>
                            <div class="space-y-2">
                                <?php foreach ($turmas as $turma): ?>
                                    <label class="flex items-center space-x-2 cursor-pointer hover:bg-gray-50 p-2 rounded">
                                        <input type="checkbox" name="turmas_id[]" value="<?= htmlspecialchars($turma['id']) ?>"
                                               class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 turma-checkbox">
                                        <span class="text-sm text-gray-700"><?= htmlspecialchars($turma['nome']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Selecione uma ou mais turmas. Um plano será criado para cada turma selecionada.</p>
                    <input type="hidden" id="turma_id" name="turma_id" required>
                </div>

                <div class="md:col-span-2">
                    <label for="data_aula" class="block text-sm font-semibold text-gray-700 mb-2">
                        Datas da Aula <span class="text-red-500">*</span>
                    </label>
                    <div id="datasContainer" class="space-y-2">
                        <div class="flex items-center space-x-2">
                            <div class="flex items-center space-x-2 flex-1">
                                <input type="date" name="datas_aula[]" required
                                       class="w-48 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 data-input">
                                <span class="dia-semana text-sm text-gray-600 font-medium"></span>
                            </div>
                            <button type="button" onclick="adicionarData()" 
                                    class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Adicione uma ou mais datas para este plano de aula</p>
                    <input type="hidden" id="data_aula" name="data_aula" required>
                </div>

                <div class="md:col-span-2">
                    <label for="titulo" class="block text-sm font-semibold text-gray-700 mb-2">
                        Título <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="titulo" name="titulo" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="EX: PLANO DE AULA SEMANAL 1° ANOS_SEMANA 26 a 30/01">
                </div>

                <!-- Campo hidden para ano_disciplina (preenchido automaticamente) -->
                <input type="hidden" id="ano_disciplina" name="ano_disciplina">
            </div>
        </div>

        <!-- Estrutura do Conteúdo -->
        <div class="border-b border-gray-200 pb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Estrutura do Conteúdo</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div>
                    <label for="modulo" class="block text-sm font-semibold text-gray-700 mb-2">
                        Módulo
                    </label>
                    <input type="text" id="modulo" name="modulo"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="aula_num" class="block text-sm font-semibold text-gray-700 mb-2">
                        Aula Nº
                    </label>
                    <input type="text" id="aula_num" name="aula_num"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: 76 a 79">
                </div>

                <div>
                    <label for="paginas" class="block text-sm font-semibold text-gray-700 mb-2">
                        Páginas
                    </label>
                    <input type="text" id="paginas" name="paginas"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: 5 a 18">
                </div>

                <div class="md:col-span-3">
                    <label for="conteudo" class="block text-sm font-semibold text-gray-700 mb-2">
                        Conteúdo
                    </label>
                    <div id="editor-conteudo" class="quill-editor-wrapper"></div>
                    <textarea id="conteudo" name="conteudo" style="display: none;"></textarea>
                </div>
            </div>
        </div>

        <!-- Objetivos -->
        <div class="border-b border-gray-200 pb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Objetivos</h3>
            
            <div class="space-y-4">
                <div>
                    <label for="objetivos" class="block text-sm font-semibold text-gray-700 mb-2">
                        O Aluno deverá ser capaz de:
                    </label>
                    <div id="editor-objetivos" class="quill-editor-wrapper"></div>
                    <textarea id="objetivos" name="objetivos" style="display: none;"></textarea>
                </div>
            </div>
        </div>

        <!-- Recursos -->
        <div class="border-b border-gray-200 pb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Recursos</h3>
            
            <div class="space-y-4">
                <div>
                    <label for="recursos" class="block text-sm font-semibold text-gray-700 mb-2">
                        Ferramentas utilizadas para que os objetivos sejam atingidos:
                    </label>
                    <div id="editor-recursos" class="quill-editor-wrapper"></div>
                    <textarea id="recursos" name="recursos" style="display: none;"></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Recursos (Checkboxes)
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php 
                        $recursos_opcoes = ['Quadro', 'Projetor', 'Computador', 'Livro', 'Apostila', 'Vídeo', 'Áudio', 'EducaColag'];
                        foreach ($recursos_opcoes as $recurso): ?>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="recursos_lista[]" value="<?= htmlspecialchars($recurso) ?>"
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-gray-700"><?= htmlspecialchars($recurso) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php $aulas_tarde_data = []; include __DIR__ . '/_aulas_tarde_oficinas_fields.php'; ?>

        <!-- Observações -->
        <div class="pb-6">
            <div class="space-y-4">
                <div>
                    <label for="observacoes" class="block text-sm font-semibold text-gray-700 mb-2">
                        Observações
                    </label>
                    <textarea id="observacoes" name="observacoes" rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
            <a href="<?= URL ?>/professor/planos-aula" 
               class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Salvar Plano
            </button>
        </div>
    </form>
</div>

<script>
// Função para atualizar ano_disciplina quando matéria ou turmas mudarem
// Nota: O ano_disciplina será gerado automaticamente no backend para cada turma
function atualizarAnoDisciplina() {
    const materiaSelect = document.getElementById('materia_id');
    const turmaCheckboxes = document.querySelectorAll('.turma-checkbox:checked');
    const anoDisciplinaInput = document.getElementById('ano_disciplina');
    
    const materiaSelecionada = materiaSelect ? materiaSelect.options[materiaSelect.selectedIndex] : null;
    
    if (materiaSelecionada && materiaSelecionada.value && turmaCheckboxes.length > 0) {
        const materiaNome = materiaSelecionada.text.trim();
        const turmasNomes = Array.from(turmaCheckboxes).map(cb => {
            const label = cb.closest('label');
            return label ? label.querySelector('span').textContent.trim() : '';
        }).filter(nome => nome !== '');
        
        if (turmasNomes.length === 1) {
            anoDisciplinaInput.value = turmasNomes[0] + ' / ' + materiaNome;
        } else {
            // Deixa vazio para o backend gerar por turma
            anoDisciplinaInput.value = '';
        }
    } else {
        anoDisciplinaInput.value = '';
    }
}

// Adicionar listeners para atualizar quando turma ou matéria mudarem
document.addEventListener('DOMContentLoaded', function() {
    const materiaSelect = document.getElementById('materia_id');
    const turmaCheckboxes = document.querySelectorAll('.turma-checkbox');
    
    if (materiaSelect) {
        materiaSelect.addEventListener('change', atualizarAnoDisciplina);
    }
    
    // Adicionar listener para cada checkbox de turma
    turmaCheckboxes.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            // Atualizar campo hidden para validação
            const turmasSelecionadas = document.querySelectorAll('.turma-checkbox:checked');
            const turmaHidden = document.getElementById('turma_id');
            if (turmasSelecionadas.length > 0) {
                turmaHidden.value = Array.from(turmasSelecionadas).map(cb => cb.value).join(',');
            } else {
                turmaHidden.value = '';
            }
            atualizarAnoDisciplina();
        });
    });
    
    // Atualizar ao carregar a página se já houver valores selecionados
    atualizarAnoDisciplina();
});

// Função para obter o nome do dia da semana em português
function obterDiaSemana(dataString) {
    if (!dataString) return '';
    
    const diasSemana = ['Domingo', 'Segunda-Feira', 'Terça-Feira', 'Quarta-Feira', 'Quinta-Feira', 'Sexta-Feira', 'Sábado'];
    const data = new Date(dataString + 'T00:00:00');
    const diaSemana = data.getDay();
    return diasSemana[diaSemana];
}

// Função para atualizar o dia da semana ao lado do input
function atualizarDiaSemana(input) {
    const container = input.closest('.flex.items-center');
    if (!container) return;
    
    const diaSemanaSpan = container.querySelector('.dia-semana');
    if (diaSemanaSpan) {
        const dataString = input.value;
        if (dataString) {
            const diaSemana = obterDiaSemana(dataString);
            diaSemanaSpan.textContent = `(${diaSemana})`;
        } else {
            diaSemanaSpan.textContent = '';
        }
    }
}

// Função para adicionar novo campo de data
function adicionarData() {
    const container = document.getElementById('datasContainer');
    const novoCampo = document.createElement('div');
    novoCampo.className = 'flex items-center space-x-2';
    novoCampo.innerHTML = `
        <div class="flex items-center space-x-2 flex-1">
            <input type="date" name="datas_aula[]" required
                   class="w-48 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 data-input">
            <span class="dia-semana text-sm text-gray-600 font-medium"></span>
        </div>
        <button type="button" onclick="removerData(this)" 
                class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    container.appendChild(novoCampo);
    
    // Adicionar listener ao novo input
    const novoInput = novoCampo.querySelector('.data-input');
    novoInput.addEventListener('change', function() {
        atualizarDiaSemana(this);
        atualizarDataAula();
    });
}

// Função para remover campo de data
function removerData(btn) {
    const container = document.getElementById('datasContainer');
    if (container.children.length > 1) {
        btn.parentElement.remove();
        atualizarDataAula();
    } else {
        alert('É necessário ter pelo menos uma data');
    }
}

// Função para atualizar o campo hidden com as datas selecionadas
function atualizarDataAula() {
    const dataInputs = document.querySelectorAll('input[name="datas_aula[]"]');
    const datas = Array.from(dataInputs)
        .map(input => input.value)
        .filter(val => val !== '')
        .sort();
    
    // Armazena como JSON no campo hidden
    document.getElementById('data_aula').value = JSON.stringify(datas);
}

// Adicionar listeners para atualizar quando datas mudarem
document.addEventListener('DOMContentLoaded', function() {
    const dataInputs = document.querySelectorAll('.data-input');
    dataInputs.forEach(input => {
        input.addEventListener('change', function() {
            atualizarDiaSemana(this);
            atualizarDataAula();
        });
        
        // Atualizar dia da semana se já houver valor
        if (input.value) {
            atualizarDiaSemana(input);
        }
    });
    
    // Atualizar ao carregar
    atualizarDataAula();
});

document.getElementById('planoForm').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!validarAulasTardeOficinas()) {
        return;
    }
    
    // Atualizar textareas hidden com o conteúdo do Quill antes de enviar
    if (typeof quillConteudo !== 'undefined') {
        document.getElementById('conteudo').value = quillConteudo.root.innerHTML;
    }
    if (typeof quillObjetivos !== 'undefined') {
        document.getElementById('objetivos').value = quillObjetivos.root.innerHTML;
    }
    if (typeof quillRecursos !== 'undefined') {
        document.getElementById('recursos').value = quillRecursos.root.innerHTML;
    }
    
    // Verificar se pelo menos uma turma foi selecionada
    const turmasSelecionadas = document.querySelectorAll('.turma-checkbox:checked');
    if (turmasSelecionadas.length === 0) {
        alert('Por favor, selecione pelo menos uma turma');
        return;
    }
    
    // Garantir que ano_disciplina está atualizado antes de enviar
    atualizarAnoDisciplina();
    
    // Atualizar datas antes de enviar
    atualizarDataAula();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // Coletar turmas selecionadas
    const turmasIds = Array.from(turmasSelecionadas).map(cb => cb.value);
    data.turmas_id = turmasIds;
    
    // Coletar checkboxes de recursos
    const recursosLista = [];
    document.querySelectorAll('input[name="recursos_lista[]"]:checked').forEach(cb => {
        recursosLista.push(cb.value);
    });
    data.recursos_lista = recursosLista;
    data.aulas_tarde_atividades = coletarAulasTardeAtividades();
    
    fetch('<?= URL ?>/professor/planos-aula/salvar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let mensagem = data.message;
            if (data.planos_criados && data.planos_criados.length > 1) {
                const turmasNomes = data.planos_criados.map(p => p.turma_nome).join(', ');
                mensagem += '\n\nTurmas: ' + turmasNomes;
            }
            if (data.erros && data.erros.length > 0) {
                mensagem += '\n\nAvisos: ' + data.erros.join('\n');
            }
            alert(mensagem);
            window.location.href = '<?= URL ?>/professor/planos-aula';
        } else {
            alert('Erro: ' + (data.error || 'Erro ao salvar plano'));
            if (data.errors) {
                console.error('Erros:', data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar plano de aula');
    });
});
</script>

<!-- Quill.js - Editor WYSIWYG -->
<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<style>
.quill-editor-wrapper {
    background: white;
    border: 1px solid #d1d5db;
    border-radius: 0.5rem;
    overflow: visible;
    width: 100%;
}

.quill-editor-wrapper .ql-container {
    min-height: 200px;
    font-size: 14px;
    border: none;
    border-top: 1px solid #d1d5db;
}

.quill-editor-wrapper .ql-editor {
    min-height: 200px;
    padding: 12px 15px;
    color: #1f2937;
}

.quill-editor-wrapper .ql-toolbar {
    border-top: none;
    border-left: none;
    border-right: none;
    border-bottom: 1px solid #d1d5db;
    background: #f9fafb;
    padding: 8px;
}

.quill-editor-wrapper .ql-editor.ql-blank::before {
    color: #9ca3af;
    font-style: normal;
}
</style>
<script>
// Variáveis globais para os editores Quill
var quillConteudo, quillObjetivos, quillRecursos;

// Inicializar Quill.js após o DOM estar pronto
document.addEventListener('DOMContentLoaded', function() {
    // Editor de Conteúdo
    quillConteudo = new Quill('#editor-conteudo', {
        theme: 'snow'
    });
    
    // Editor de Objetivos
    quillObjetivos = new Quill('#editor-objetivos', {
        theme: 'snow'
    });
    
    // Editor de Recursos
    quillRecursos = new Quill('#editor-recursos', {
        theme: 'snow'
    });
    
    // Atualizar textareas hidden quando o conteúdo mudar
    quillConteudo.on('text-change', function() {
        document.getElementById('conteudo').value = quillConteudo.root.innerHTML;
    });
    
    quillObjetivos.on('text-change', function() {
        document.getElementById('objetivos').value = quillObjetivos.root.innerHTML;
    });
    
    quillRecursos.on('text-change', function() {
        document.getElementById('recursos').value = quillRecursos.root.innerHTML;
    });
});
</script>
