<!-- Header Section -->
<?php
$back_url = $back_url ?? URL . '/professor/planos-aula';
$update_url = $update_url ?? URL . '/professor/planos-aula/atualizar/' . $plano['id'];
$redirect_url = $redirect_url ?? $back_url;
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Editar Plano de Aula 📚
            </h2>
            <p class="text-gray-600">
                Edite os dados do seu plano de aula
            </p>
        </div>
        <a href="<?= $back_url ?>" 
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
                            <option value="<?= htmlspecialchars($materia['id']) ?>" 
                                    <?= ($plano['materia_id'] == $materia['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($materia['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div>
                    <label for="turma_id" class="block text-sm font-semibold text-gray-700 mb-2">
                        Turma <span class="text-red-500">*</span>
                    </label>
                    <select id="turma_id" name="turma_id" required
                            class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecione uma turma</option>
                        <?php foreach ($turmas as $turma): ?>
                            <option value="<?= htmlspecialchars($turma['id']) ?>"
                                    <?= ($plano['turma_id'] == $turma['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($turma['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label for="data_aula" class="block text-sm font-semibold text-gray-700 mb-2">
                        Datas da Aula <span class="text-red-500">*</span>
                    </label>
                    <div id="datasContainer" class="space-y-2">
                        <?php
                        // Tenta decodificar as datas se estiverem em JSON, senão usa a data única
                        $datas = [];
                        if (!empty($plano['data_aula'])) {
                            $datasJson = json_decode($plano['data_aula'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($datasJson)) {
                                $datas = $datasJson;
                            } else {
                                // Se não for JSON, assume que é uma data única
                                $datas = [$plano['data_aula']];
                            }
                        }
                        // Se não houver datas, adiciona uma vazia
                        if (empty($datas)) {
                            $datas = [''];
                        }
                        foreach ($datas as $index => $dataItem): ?>
                            <div class="flex items-center space-x-2">
                                <div class="flex items-center space-x-2 flex-1">
                                    <input type="date" name="datas_aula[]" required
                                           value="<?= htmlspecialchars($dataItem) ?>"
                                           class="w-48 px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 data-input">
                                    <span class="dia-semana text-sm text-gray-600 font-medium">
                                        <?php if (!empty($dataItem)): ?>
                                            <?php
                                            $dataObj = new DateTime($dataItem);
                                            $diasSemana = ['Domingo', 'Segunda-Feira', 'Terça-Feira', 'Quarta-Feira', 'Quinta-Feira', 'Sexta-Feira', 'Sábado'];
                                            $diaSemana = $diasSemana[(int)$dataObj->format('w')];
                                            ?>
                                            (<?= $diaSemana ?>)
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($index === 0): ?>
                                    <button type="button" onclick="adicionarData()" 
                                            class="px-3 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                        </svg>
                                    </button>
                                <?php else: ?>
                                    <button type="button" onclick="removerData(this)" 
                                            class="px-3 py-2 bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Adicione uma ou mais datas para este plano de aula</p>
                    <input type="hidden" id="data_aula" name="data_aula" required>
                </div>

                <div class="md:col-span-2">
                    <label for="titulo" class="block text-sm font-semibold text-gray-700 mb-2">
                        Título <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="titulo" name="titulo" required
                           value="<?= htmlspecialchars($plano['titulo']) ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Título do plano de aula">
                </div>

                <!-- Campo hidden para ano_disciplina (preenchido automaticamente) -->
                <input type="hidden" id="ano_disciplina" name="ano_disciplina" value="<?= htmlspecialchars($plano['ano_disciplina'] ?? '') ?>">
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
                           value="<?= htmlspecialchars($plano['modulo'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div>
                    <label for="aula_num" class="block text-sm font-semibold text-gray-700 mb-2">
                        Aula Nº
                    </label>
                    <input type="text" id="aula_num" name="aula_num"
                           value="<?= htmlspecialchars($plano['aula_num'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: 76 a 79">
                </div>

                <div>
                    <label for="paginas" class="block text-sm font-semibold text-gray-700 mb-2">
                        Páginas
                    </label>
                    <input type="text" id="paginas" name="paginas"
                           value="<?= htmlspecialchars($plano['paginas'] ?? '') ?>"
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: 5 a 18">
                </div>

                <div class="md:col-span-3">
                    <label for="conteudo" class="block text-sm font-semibold text-gray-700 mb-2">
                        Conteúdo
                    </label>
                    <div id="editor-conteudo" class="quill-editor-wrapper"></div>
                    <textarea id="conteudo" name="conteudo" style="display: none;"><?= htmlspecialchars($plano['conteudo'] ?? '') ?></textarea>
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
                    <textarea id="objetivos" name="objetivos" style="display: none;"><?= htmlspecialchars($plano['objetivos'] ?? '') ?></textarea>
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
                    <textarea id="recursos" name="recursos" style="display: none;"><?= htmlspecialchars($plano['recursos'] ?? '') ?></textarea>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-2">
                        Recursos (Checkboxes)
                    </label>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
                        <?php 
                        $recursos_opcoes = ['Quadro', 'Projetor', 'Computador', 'Livro', 'Apostila', 'Vídeo', 'Áudio', 'Internet'];
                        $recursos_selecionados = is_array($plano['recursos_lista']) ? $plano['recursos_lista'] : [];
                        foreach ($recursos_opcoes as $recurso): ?>
                            <label class="flex items-center space-x-2">
                                <input type="checkbox" name="recursos_lista[]" value="<?= htmlspecialchars($recurso) ?>"
                                       <?= in_array($recurso, $recursos_selecionados) ? 'checked' : '' ?>
                                       class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                <span class="text-sm text-gray-700"><?= htmlspecialchars($recurso) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php
        require_once __DIR__ . '/../../../Helpers/LessonPlanAfternoonHelper.php';
        $aulas_tarde_atividades = LessonPlanAfternoonHelper::parseList($plano['aulas_tarde_oficinas'] ?? '');
        include __DIR__ . '/_aulas_tarde_oficinas_fields.php';
        ?>

        <!-- Observações -->
        <div class="pb-6">
            <div class="space-y-4">
                <div>
                    <label for="observacoes" class="block text-sm font-semibold text-gray-700 mb-2">
                        Observações
                    </label>
                    <textarea id="observacoes" name="observacoes" rows="3"
                              class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"><?= htmlspecialchars($plano['observacoes'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Botões -->
        <div class="flex justify-end space-x-4 pt-6 border-t border-gray-200">
            <a href="<?= $back_url ?>" 
               class="px-6 py-3 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                Cancelar
            </a>
            <button type="submit" 
                    class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                Atualizar Plano
            </button>
        </div>
    </form>
</div>

<script>
// Função para atualizar ano_disciplina automaticamente
function atualizarAnoDisciplina() {
    const materiaSelect = document.getElementById('materia_id');
    const turmaSelect = document.getElementById('turma_id');
    const anoDisciplinaInput = document.getElementById('ano_disciplina');
    
    const materiaSelecionada = materiaSelect.options[materiaSelect.selectedIndex];
    const turmaSelecionada = turmaSelect.options[turmaSelect.selectedIndex];
    
    if (materiaSelecionada.value && turmaSelecionada.value) {
        const materiaNome = materiaSelecionada.text.trim();
        const turmaNome = turmaSelecionada.text.trim();
        anoDisciplinaInput.value = turmaNome + ' / ' + materiaNome;
    } else {
        anoDisciplinaInput.value = '';
    }
}

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

// Adicionar listeners para atualizar quando turma ou matéria mudarem
document.addEventListener('DOMContentLoaded', function() {
    const materiaSelect = document.getElementById('materia_id');
    const turmaSelect = document.getElementById('turma_id');
    
    if (materiaSelect) {
        materiaSelect.addEventListener('change', atualizarAnoDisciplina);
    }
    
    if (turmaSelect) {
        turmaSelect.addEventListener('change', atualizarAnoDisciplina);
    }
    
    // Atualizar ao carregar a página se já houver valores selecionados
    atualizarAnoDisciplina();
    
    // Adicionar listeners para campos de data
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
    
    // Garantir que ano_disciplina está atualizado antes de enviar
    atualizarAnoDisciplina();
    
    // Atualizar datas antes de enviar
    atualizarDataAula();
    
    const formData = new FormData(this);
    const data = Object.fromEntries(formData);
    
    // Coletar checkboxes de recursos
    const recursosLista = [];
    document.querySelectorAll('input[name="recursos_lista[]"]:checked').forEach(cb => {
        recursosLista.push(cb.value);
    });
    data.recursos_lista = recursosLista;
    data.aulas_tarde_atividades = coletarAulasTardeAtividades();
    
    fetch('<?= $update_url ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            window.location.href = '<?= $redirect_url ?>';
        } else {
            alert('Erro: ' + (data.error || 'Erro ao atualizar plano'));
            if (data.errors) {
                console.error('Erros:', data.errors);
            }
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao atualizar plano de aula');
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
    
    // Carregar conteúdo existente se houver
    var conteudoTextarea = document.getElementById('conteudo');
    if (conteudoTextarea && conteudoTextarea.value) {
        quillConteudo.root.innerHTML = conteudoTextarea.value;
    }
    
    // Editor de Objetivos
    quillObjetivos = new Quill('#editor-objetivos', {
        theme: 'snow'
    });
    
    // Carregar conteúdo existente se houver
    var objetivosTextarea = document.getElementById('objetivos');
    if (objetivosTextarea && objetivosTextarea.value) {
        quillObjetivos.root.innerHTML = objetivosTextarea.value;
    }
    
    // Editor de Recursos
    quillRecursos = new Quill('#editor-recursos', {
        theme: 'snow'
    });
    
    // Carregar conteúdo existente se houver
    var recursosTextarea = document.getElementById('recursos');
    if (recursosTextarea && recursosTextarea.value) {
        quillRecursos.root.innerHTML = recursosTextarea.value;
    }
    
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

