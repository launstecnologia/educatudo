<?php
$wizardSteps = [
    ['label' => 'Dados', 'hint' => 'Matéria, turma e datas'],
    ['label' => 'Conteúdo', 'hint' => 'Manual ou Copiloto'],
    ['label' => 'Objetivos', 'hint' => 'Recursos e oficinas'],
    ['label' => 'Revisão', 'hint' => 'Conferir e salvar'],
];
?>

<div class="mb-6">
    <div class="flex flex-col gap-4 md:flex-row md:items-start md:justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Criar Novo Plano de Aula</h2>
            <p class="text-gray-600 mt-1">Preencha por etapas e salve como rascunho quando precisar.</p>
        </div>
        <a href="<?= URL ?>/professor/planos-aula"
           class="inline-flex items-center justify-center gap-2 rounded-lg bg-gray-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-gray-700">
            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>
</div>

<div class="w-full rounded-lg border border-gray-200 bg-white shadow-sm">
    <div class="border-b border-gray-200 px-5 py-4">
        <ol class="grid grid-cols-1 gap-3 md:grid-cols-4" id="wizardSteps">
            <?php foreach ($wizardSteps as $index => $step): ?>
                <li>
                    <button type="button"
                            class="wizard-step-btn flex w-full items-center gap-3 rounded-lg border border-gray-200 bg-white px-3 py-3 text-left transition-colors"
                            data-step-target="<?= $index ?>">
                        <span class="wizard-step-number flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-sm font-bold text-gray-600"><?= $index + 1 ?></span>
                        <span class="min-w-0">
                            <span class="block text-sm font-semibold text-gray-900"><?= htmlspecialchars($step['label']) ?></span>
                            <span class="block truncate text-xs text-gray-500"><?= htmlspecialchars($step['hint']) ?></span>
                        </span>
                    </button>
                </li>
            <?php endforeach; ?>
        </ol>
    </div>

    <form id="planoForm" class="p-5 md:p-6" novalidate>
        <input type="hidden" id="ano_disciplina" name="ano_disciplina">
        <input type="hidden" id="data_aula" name="data_aula">
        <input type="hidden" id="turma_id" name="turma_id">
        <input type="hidden" id="status" name="status" value="rascunho">

        <section class="wizard-panel space-y-6" data-step="0">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Informações Básicas</h3>
                <p class="mt-1 text-sm text-gray-500">Defina onde este plano será usado.</p>
            </div>

            <div class="grid grid-cols-1 gap-5 md:grid-cols-2">
                <div>
                    <label for="materia_id" class="mb-2 block text-sm font-semibold text-gray-700">Matéria <span class="text-red-500">*</span></label>
                    <select id="materia_id" name="materia_id" required
                            class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <option value="">Selecione uma matéria</option>
                        <?php foreach ($materias as $materia): ?>
                            <option value="<?= htmlspecialchars($materia['id']) ?>"><?= htmlspecialchars($materia['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <?php if (!empty($anos_letivos)): ?>
                    <div>
                        <label for="ano_letivo_id" class="mb-2 block text-sm font-semibold text-gray-700">Ano Letivo</label>
                        <select id="ano_letivo_id" name="ano_letivo_id"
                                class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <option value="">Nao especificado</option>
                            <?php foreach ($anos_letivos as $a): ?>
                                <option value="<?= (int) $a['id'] ?>" <?= (int) $a['id'] === ($ano_letivo_ativo_id ?? 0) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars((string) $a['ano']) ?><?= !empty($a['ativo']) ? ' (ativo)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>

                <div class="md:col-span-2">
                    <label class="mb-2 block text-sm font-semibold text-gray-700">Turmas <span class="text-red-500">*</span></label>
                    <div class="max-h-56 overflow-y-auto rounded-lg border border-gray-300 bg-white p-3">
                        <?php if (empty($turmas)): ?>
                            <p class="text-sm text-gray-500">Nenhuma turma disponível</p>
                        <?php else: ?>
                            <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                                <?php foreach ($turmas as $turma): ?>
                                    <label class="flex cursor-pointer items-center gap-2 rounded-md p-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <input type="checkbox" name="turmas_id[]" value="<?= htmlspecialchars($turma['id']) ?>"
                                               class="turma-checkbox rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                        <span><?= htmlspecialchars($turma['nome']) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">Um rascunho será criado para cada turma selecionada.</p>
                </div>

                <div class="md:col-span-2">
                    <label for="data_aula" class="mb-2 block text-sm font-semibold text-gray-700">Datas da Aula <span class="text-red-500">*</span></label>
                    <div id="datasContainer" class="space-y-2">
                        <div class="flex items-center gap-2">
                            <div class="flex flex-1 items-center gap-2">
                                <input type="date" name="datas_aula[]" required
                                       class="data-input w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <span class="dia-semana text-sm font-medium text-gray-600"></span>
                            </div>
                            <button type="button" onclick="adicionarData()"
                                    class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-green-600 text-white hover:bg-green-700"
                                    aria-label="Adicionar data">
                                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v12m6-6H6"></path>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label for="titulo" class="mb-2 block text-sm font-semibold text-gray-700">Título <span class="text-red-500">*</span></label>
                    <input type="text" id="titulo" name="titulo" required
                           class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                           placeholder="Ex: Plano de aula semanal - 1os anos">
                </div>
            </div>
        </section>

        <section class="wizard-panel hidden space-y-6" data-step="1">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Estrutura do Conteúdo</h3>
                <p class="mt-1 text-sm text-gray-500">Escolha como deseja montar o conteúdo do plano.</p>
            </div>

            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-50 p-1">
                <button type="button" class="modo-conteudo-btn rounded-md px-4 py-2 text-sm font-semibold" data-mode="manual">Manual</button>
                <button type="button" class="modo-conteudo-btn rounded-md px-4 py-2 text-sm font-semibold" data-mode="copiloto">Meu Copiloto</button>
            </div>

            <div id="copilotoPanel" class="hidden rounded-lg border border-blue-200 bg-blue-50/60 p-4">
                <div class="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_1.2fr]">
                    <div>
                        <label for="copilotoPrompt" class="mb-2 block text-sm font-semibold text-gray-700">Pedido para o Copiloto</label>
                        <textarea id="copilotoPrompt" rows="7"
                                  class="w-full resize-y rounded-lg border border-blue-200 bg-white px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                  placeholder="Ex: Monte uma aula introdutória sobre frações com exemplos práticos e atividade em grupo."></textarea>
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-semibold text-gray-700">Materiais de apoio</label>
                        <div id="copilotoDropzone"
                             tabindex="0"
                             class="flex min-h-[172px] cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-blue-300 bg-white px-4 py-6 text-center hover:border-blue-500">
                            <svg class="mb-2 h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01.88-7.9A5 5 0 1118.9 10H19a3 3 0 010 6h-3m-4-3v8m0 0l-3-3m3 3l3-3"></path>
                            </svg>
                            <p class="text-sm font-semibold text-gray-800">PDF, imagens ou prints colados</p>
                            <p class="mt-1 text-xs text-gray-500">Clique, arraste ou cole uma imagem nesta área.</p>
                            <input id="copilotoArquivos" name="copiloto_arquivos[]" type="file" multiple accept="application/pdf,image/png,image/jpeg,image/webp" class="hidden">
                        </div>
                        <ul id="copilotoListaArquivos" class="mt-3 space-y-1 text-xs text-gray-600"></ul>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <p id="copilotoStatus" class="text-sm text-blue-800">O resultado preencherá os campos do plano quando terminar.</p>
                    <button type="button" id="gerarCopilotoBtn"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Gerar rascunho
                    </button>
                </div>
            </div>

            <div id="manualPanel" class="space-y-5">
                <div class="grid grid-cols-1 gap-5 md:grid-cols-3">
                    <div>
                        <label for="modulo" class="mb-2 block text-sm font-semibold text-gray-700">Módulo</label>
                        <input type="text" id="modulo" name="modulo"
                               class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    </div>

                    <div>
                        <label for="aula_num" class="mb-2 block text-sm font-semibold text-gray-700">Aula Nº</label>
                        <input type="text" id="aula_num" name="aula_num"
                               class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Ex: 76 a 79">
                    </div>

                    <div>
                        <label for="paginas" class="mb-2 block text-sm font-semibold text-gray-700">Páginas</label>
                        <input type="text" id="paginas" name="paginas"
                               class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                               placeholder="Ex: 5 a 18">
                    </div>
                </div>

                <div>
                    <label for="conteudo" class="mb-2 block text-sm font-semibold text-gray-700">Conteúdo</label>
                    <div id="editor-conteudo" class="quill-editor-wrapper"></div>
                    <textarea id="conteudo" name="conteudo" class="hidden"></textarea>
                </div>
            </div>
        </section>

        <section class="wizard-panel hidden space-y-6" data-step="2">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Objetivos e Recursos</h3>
                <p class="mt-1 text-sm text-gray-500">Complete os elementos pedagógicos do rascunho.</p>
            </div>

            <div>
                <label for="objetivos" class="mb-2 block text-sm font-semibold text-gray-700">O aluno deverá ser capaz de:</label>
                <div id="editor-objetivos" class="quill-editor-wrapper"></div>
                <textarea id="objetivos" name="objetivos" class="hidden"></textarea>
            </div>

            <div>
                <label for="recursos" class="mb-2 block text-sm font-semibold text-gray-700">Ferramentas utilizadas:</label>
                <div id="editor-recursos" class="quill-editor-wrapper"></div>
                <textarea id="recursos" name="recursos" class="hidden"></textarea>
            </div>

            <div>
                <label class="mb-2 block text-sm font-semibold text-gray-700">Recursos</label>
                <div class="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <?php
                    $recursos_opcoes = ['Quadro', 'Projetor', 'Computador', 'Livro', 'Apostila', 'Vídeo', 'Áudio', 'EducaColag'];
                    foreach ($recursos_opcoes as $recurso): ?>
                        <label class="flex items-center gap-2 rounded-md border border-gray-200 px-3 py-2 text-sm text-gray-700">
                            <input type="checkbox" name="recursos_lista[]" value="<?= htmlspecialchars($recurso) ?>"
                                   class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span><?= htmlspecialchars($recurso) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php $aulas_tarde_data = []; include __DIR__ . '/_aulas_tarde_oficinas_fields.php'; ?>

            <div>
                <label for="observacoes" class="mb-2 block text-sm font-semibold text-gray-700">Observações</label>
                <textarea id="observacoes" name="observacoes" rows="4"
                          class="w-full rounded-lg border border-gray-300 px-4 py-3 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
            </div>
        </section>

        <section class="wizard-panel hidden space-y-6" data-step="3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Revisão</h3>
                <p class="mt-1 text-sm text-gray-500">Confira os principais dados antes de salvar o rascunho.</p>
            </div>
            <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Matéria</p>
                    <p id="reviewMateria" class="mt-1 text-sm font-semibold text-gray-900">-</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Turmas</p>
                    <p id="reviewTurmas" class="mt-1 text-sm font-semibold text-gray-900">-</p>
                </div>
                <div class="rounded-lg border border-gray-200 p-4">
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Datas</p>
                    <p id="reviewDatas" class="mt-1 text-sm font-semibold text-gray-900">-</p>
                </div>
            </div>
            <div class="rounded-lg border border-gray-200 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Título</p>
                <p id="reviewTitulo" class="mt-1 text-sm font-semibold text-gray-900">-</p>
            </div>
        </section>

        <div class="mt-8 flex flex-col gap-3 border-t border-gray-200 pt-5 sm:flex-row sm:items-center sm:justify-between">
            <button type="button" id="prevStepBtn"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 px-5 py-2.5 text-sm font-semibold text-gray-700 hover:bg-gray-50">
                Voltar etapa
            </button>
            <div class="flex flex-col gap-3 sm:flex-row sm:items-center">
                <button type="button" id="saveDraftBtn"
                        class="inline-flex items-center justify-center rounded-lg bg-gray-700 px-5 py-2.5 text-sm font-semibold text-white hover:bg-gray-800">
                    Salvar rascunho
                </button>
                <button type="button" id="nextStepBtn"
                        class="inline-flex items-center justify-center rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    Próxima etapa
                </button>
            </div>
        </div>
    </form>
</div>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<?php include __DIR__ . '/../../components/ai-job-poller.php'; ?>

<style>
.wizard-step-btn.is-active {
    border-color: #2563eb;
    background: #eff6ff;
}
.wizard-step-btn.is-active .wizard-step-number {
    background: #2563eb;
    color: #fff;
}
.wizard-step-btn.is-complete .wizard-step-number {
    background: #16a34a;
    color: #fff;
}
.modo-conteudo-btn.is-active {
    background: #fff;
    color: #1d4ed8;
    box-shadow: 0 1px 2px rgba(15, 23, 42, .08);
}
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
var quillConteudo, quillObjetivos, quillRecursos;
var currentStep = 0;
var copilotoFiles = [];

function obterDiaSemana(dataString) {
    if (!dataString) return '';
    const diasSemana = ['Domingo', 'Segunda-Feira', 'Terça-Feira', 'Quarta-Feira', 'Quinta-Feira', 'Sexta-Feira', 'Sábado'];
    return diasSemana[new Date(dataString + 'T00:00:00').getDay()];
}

function atualizarDiaSemana(input) {
    const container = input.closest('.flex.items-center');
    if (!container) return;
    const span = container.querySelector('.dia-semana');
    if (span) span.textContent = input.value ? '(' + obterDiaSemana(input.value) + ')' : '';
}

function atualizarDataAula() {
    const datas = Array.from(document.querySelectorAll('input[name="datas_aula[]"]'))
        .map(input => input.value)
        .filter(Boolean)
        .sort();
    document.getElementById('data_aula').value = JSON.stringify(datas);
}

function adicionarData() {
    const container = document.getElementById('datasContainer');
    const novoCampo = document.createElement('div');
    novoCampo.className = 'flex items-center gap-2';
    novoCampo.innerHTML = `
        <div class="flex flex-1 items-center gap-2">
            <input type="date" name="datas_aula[]" required
                   class="data-input w-48 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
            <span class="dia-semana text-sm font-medium text-gray-600"></span>
        </div>
        <button type="button" onclick="removerData(this)"
                class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-red-600 text-white hover:bg-red-700"
                aria-label="Remover data">
            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    `;
    container.appendChild(novoCampo);
    const input = novoCampo.querySelector('.data-input');
    input.addEventListener('change', function() {
        atualizarDiaSemana(this);
        atualizarDataAula();
    });
}

function removerData(btn) {
    const container = document.getElementById('datasContainer');
    if (container.children.length <= 1) {
        alert('É necessário ter pelo menos uma data');
        return;
    }
    btn.parentElement.remove();
    atualizarDataAula();
}

function atualizarAnoDisciplina() {
    const materiaSelect = document.getElementById('materia_id');
    const turmaCheckboxes = document.querySelectorAll('.turma-checkbox:checked');
    const anoDisciplinaInput = document.getElementById('ano_disciplina');
    const materiaSelecionada = materiaSelect ? materiaSelect.options[materiaSelect.selectedIndex] : null;

    if (materiaSelecionada && materiaSelecionada.value && turmaCheckboxes.length === 1) {
        const materiaNome = materiaSelecionada.text.trim();
        const label = turmaCheckboxes[0].closest('label');
        const turmaNome = label ? label.querySelector('span').textContent.trim() : '';
        anoDisciplinaInput.value = turmaNome + ' / ' + materiaNome;
    } else {
        anoDisciplinaInput.value = '';
    }
}

function atualizarTurmaHidden() {
    const selecionadas = document.querySelectorAll('.turma-checkbox:checked');
    document.getElementById('turma_id').value = Array.from(selecionadas).map(cb => cb.value).join(',');
    atualizarAnoDisciplina();
}

function syncEditors() {
    if (typeof quillConteudo !== 'undefined') document.getElementById('conteudo').value = quillConteudo.root.innerHTML;
    if (typeof quillObjetivos !== 'undefined') document.getElementById('objetivos').value = quillObjetivos.root.innerHTML;
    if (typeof quillRecursos !== 'undefined') document.getElementById('recursos').value = quillRecursos.root.innerHTML;
}

function setStep(step) {
    currentStep = Math.max(0, Math.min(3, step));
    document.querySelectorAll('.wizard-panel').forEach(panel => {
        panel.classList.toggle('hidden', Number(panel.dataset.step) !== currentStep);
    });
    document.querySelectorAll('.wizard-step-btn').forEach(btn => {
        const index = Number(btn.dataset.stepTarget);
        btn.classList.toggle('is-active', index === currentStep);
        btn.classList.toggle('is-complete', index < currentStep);
    });
    document.getElementById('prevStepBtn').disabled = currentStep === 0;
    document.getElementById('prevStepBtn').classList.toggle('opacity-50', currentStep === 0);
    document.getElementById('nextStepBtn').textContent = currentStep === 3 ? 'Salvar rascunho' : 'Próxima etapa';
    if (currentStep === 3) atualizarRevisao();
}

function validarBasico() {
    atualizarDataAula();
    if (!document.getElementById('materia_id').value) {
        alert('Selecione uma matéria.');
        setStep(0);
        return false;
    }
    if (document.querySelectorAll('.turma-checkbox:checked').length === 0) {
        alert('Selecione pelo menos uma turma.');
        setStep(0);
        return false;
    }
    if (!document.getElementById('data_aula').value || document.getElementById('data_aula').value === '[]') {
        alert('Informe pelo menos uma data da aula.');
        setStep(0);
        return false;
    }
    if (!document.getElementById('titulo').value.trim()) {
        alert('Informe o título do plano.');
        setStep(0);
        return false;
    }
    return true;
}

function atualizarRevisao() {
    atualizarDataAula();
    const materiaSelect = document.getElementById('materia_id');
    document.getElementById('reviewMateria').textContent = materiaSelect.value ? materiaSelect.options[materiaSelect.selectedIndex].text.trim() : '-';
    document.getElementById('reviewTurmas').textContent = Array.from(document.querySelectorAll('.turma-checkbox:checked')).map(cb => {
        const label = cb.closest('label');
        return label ? label.querySelector('span').textContent.trim() : '';
    }).filter(Boolean).join(', ') || '-';
    let datas = [];
    try { datas = JSON.parse(document.getElementById('data_aula').value || '[]'); } catch (e) { datas = []; }
    document.getElementById('reviewDatas').textContent = datas.join(', ') || '-';
    document.getElementById('reviewTitulo').textContent = document.getElementById('titulo').value.trim() || '-';
}

function salvarRascunho() {
    if (!validarBasico()) return;
    if (typeof validarAulasTardeOficinas === 'function' && !validarAulasTardeOficinas()) return;

    syncEditors();
    atualizarAnoDisciplina();
    atualizarDataAula();

    const formData = new FormData(document.getElementById('planoForm'));
    const data = Object.fromEntries(formData);
    data.status = 'rascunho';
    data.turmas_id = Array.from(document.querySelectorAll('.turma-checkbox:checked')).map(cb => cb.value);
    data.recursos_lista = Array.from(document.querySelectorAll('input[name="recursos_lista[]"]:checked')).map(cb => cb.value);
    data.aulas_tarde_atividades = typeof coletarAulasTardeAtividades === 'function' ? coletarAulasTardeAtividades() : [];

    const btn = document.getElementById('saveDraftBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    btn.disabled = true;
    nextBtn.disabled = true;
    btn.textContent = 'Salvando...';

    fetch('<?= URL ?>/professor/planos-aula/salvar', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            let mensagem = data.message;
            if (data.planos_criados && data.planos_criados.length > 1) {
                mensagem += '\n\nTurmas: ' + data.planos_criados.map(p => p.turma_nome).join(', ');
            }
            if (data.erros && data.erros.length) {
                mensagem += '\n\nAvisos: ' + data.erros.join('\n');
            }
            alert(mensagem);
            window.location.href = '<?= URL ?>/professor/planos-aula';
            return;
        }
        alert('Erro: ' + (data.error || 'Erro ao salvar plano'));
        console.error('Erros:', data.errors || data);
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao salvar plano de aula');
    })
    .finally(() => {
        btn.disabled = false;
        nextBtn.disabled = false;
        btn.textContent = 'Salvar rascunho';
    });
}

function setModoConteudo(mode) {
    document.querySelectorAll('.modo-conteudo-btn').forEach(btn => {
        btn.classList.toggle('is-active', btn.dataset.mode === mode);
    });
    document.getElementById('copilotoPanel').classList.toggle('hidden', mode !== 'copiloto');
}

function atualizarListaArquivos() {
    const lista = document.getElementById('copilotoListaArquivos');
    lista.innerHTML = '';
    copilotoFiles.forEach((file, index) => {
        const li = document.createElement('li');
        li.className = 'flex items-center justify-between rounded-md bg-white px-2 py-1';
        li.innerHTML = '<span class="truncate">' + file.name + '</span><button type="button" class="ml-2 text-red-600" data-remove-file="' + index + '">Remover</button>';
        lista.appendChild(li);
    });
}

function adicionarArquivos(files) {
    Array.from(files || []).forEach(file => {
        if (!['application/pdf', 'image/png', 'image/jpeg', 'image/webp'].includes(file.type)) return;
        if (file.size > 12 * 1024 * 1024) return;
        if (copilotoFiles.length < 8) copilotoFiles.push(file);
    });
    atualizarListaArquivos();
}

function gerarComCopiloto() {
    const prompt = document.getElementById('copilotoPrompt').value.trim();
    if (!prompt && copilotoFiles.length === 0) {
        alert('Descreva a aula ou envie um material de apoio.');
        return;
    }

    atualizarDataAula();
    const data = new FormData();
    data.append('prompt', prompt);
    data.append('materia_id', document.getElementById('materia_id').value || '');
    data.append('titulo', document.getElementById('titulo').value || '');
    data.append('data_aula', document.getElementById('data_aula').value || '');
    document.querySelectorAll('.turma-checkbox:checked').forEach(cb => data.append('turmas_id[]', cb.value));
    copilotoFiles.forEach(file => data.append('copiloto_arquivos[]', file, file.name));

    const btn = document.getElementById('gerarCopilotoBtn');
    const status = document.getElementById('copilotoStatus');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    status.textContent = 'Preparando materiais e iniciando geração...';

    fetch('<?= URL ?>/professor/planos-aula/copiloto', {
        method: 'POST',
        body: data,
        credentials: 'same-origin',
        headers: {'X-Requested-With': 'XMLHttpRequest'}
    })
    .then(response => response.json())
    .then(data => {
        if (!data.success || !data.job_id) {
            throw new Error(data.error || 'Não foi possível iniciar o Copiloto.');
        }
        btn.textContent = 'Gerando...';
        status.textContent = 'Copiloto trabalhando no rascunho...';
        new AIJobPoller(data.job_id, {
            statusUrl: '<?= URL ?>/ai-job/{id}/status',
            interval: 2000,
            onProgress: function() {
                status.textContent = 'Copiloto trabalhando no rascunho...';
            },
            onDone: function(result) {
                aplicarRascunhoCopiloto(result);
                status.textContent = 'Rascunho preenchido. Revise e salve quando estiver pronto.';
                btn.disabled = false;
                btn.textContent = 'Gerar novamente';
            },
            onFailed: function(message) {
                status.textContent = message || 'Falha ao gerar o rascunho.';
                btn.disabled = false;
                btn.textContent = 'Gerar rascunho';
            }
        });
    })
    .catch(error => {
        status.textContent = error.message || 'Falha ao acionar o Copiloto.';
        btn.disabled = false;
        btn.textContent = 'Gerar rascunho';
    });
}

function aplicarRascunhoCopiloto(result) {
    const setValue = (id, value) => {
        const el = document.getElementById(id);
        if (el && value !== undefined && value !== null && String(value).trim() !== '') el.value = value;
    };
    setValue('titulo', result.titulo);
    setValue('modulo', result.modulo);
    setValue('aula_num', result.aula_num);
    setValue('paginas', result.paginas);
    setValue('observacoes', result.observacoes);

    if (result.conteudo && typeof quillConteudo !== 'undefined') quillConteudo.clipboard.dangerouslyPasteHTML(result.conteudo);
    if (result.objetivos && typeof quillObjetivos !== 'undefined') quillObjetivos.clipboard.dangerouslyPasteHTML(result.objetivos);
    if (result.recursos && typeof quillRecursos !== 'undefined') quillRecursos.clipboard.dangerouslyPasteHTML(result.recursos);
    if (Array.isArray(result.recursos_lista)) {
        document.querySelectorAll('input[name="recursos_lista[]"]').forEach(cb => {
            cb.checked = result.recursos_lista.includes(cb.value);
        });
    }
    syncEditors();
}

document.addEventListener('DOMContentLoaded', function() {
    quillConteudo = new Quill('#editor-conteudo', {theme: 'snow'});
    quillObjetivos = new Quill('#editor-objetivos', {theme: 'snow'});
    quillRecursos = new Quill('#editor-recursos', {theme: 'snow'});

    quillConteudo.on('text-change', syncEditors);
    quillObjetivos.on('text-change', syncEditors);
    quillRecursos.on('text-change', syncEditors);

    document.querySelectorAll('.turma-checkbox').forEach(cb => cb.addEventListener('change', atualizarTurmaHidden));
    document.getElementById('materia_id')?.addEventListener('change', atualizarAnoDisciplina);
    document.querySelectorAll('.data-input').forEach(input => {
        input.addEventListener('change', function() {
            atualizarDiaSemana(this);
            atualizarDataAula();
        });
    });
    document.querySelectorAll('.wizard-step-btn').forEach(btn => btn.addEventListener('click', () => setStep(Number(btn.dataset.stepTarget))));
    document.getElementById('prevStepBtn').addEventListener('click', () => setStep(currentStep - 1));
    document.getElementById('nextStepBtn').addEventListener('click', function() {
        if (currentStep === 3) {
            salvarRascunho();
            return;
        }
        if (currentStep === 0 && !validarBasico()) return;
        setStep(currentStep + 1);
    });
    document.getElementById('saveDraftBtn').addEventListener('click', salvarRascunho);
    document.getElementById('planoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        salvarRascunho();
    });
    document.querySelectorAll('.modo-conteudo-btn').forEach(btn => btn.addEventListener('click', () => setModoConteudo(btn.dataset.mode)));

    const dropzone = document.getElementById('copilotoDropzone');
    const inputArquivos = document.getElementById('copilotoArquivos');
    dropzone.addEventListener('click', () => inputArquivos.click());
    inputArquivos.addEventListener('change', () => adicionarArquivos(inputArquivos.files));
    dropzone.addEventListener('dragover', function(e) {
        e.preventDefault();
        dropzone.classList.add('border-blue-600');
    });
    dropzone.addEventListener('dragleave', () => dropzone.classList.remove('border-blue-600'));
    dropzone.addEventListener('drop', function(e) {
        e.preventDefault();
        dropzone.classList.remove('border-blue-600');
        adicionarArquivos(e.dataTransfer.files);
    });
    dropzone.addEventListener('paste', function(e) {
        adicionarArquivos(e.clipboardData.files);
    });
    document.getElementById('copilotoListaArquivos').addEventListener('click', function(e) {
        const index = e.target.getAttribute('data-remove-file');
        if (index !== null) {
            copilotoFiles.splice(Number(index), 1);
            atualizarListaArquivos();
        }
    });
    document.getElementById('gerarCopilotoBtn').addEventListener('click', gerarComCopiloto);

    atualizarDataAula();
    atualizarTurmaHidden();
    setModoConteudo('manual');
    setStep(0);
});
</script>
