<?php
require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../../Core/LayoutHelper.php';

$copilotoDisponivel = CreditosModuleRegistry::acaoIaDisponivel('planos_aula_copiloto');
$copilotoCusto = LayoutHelper::get('credito_custo_planos_aula_copiloto', '1');
if (!is_numeric($copilotoCusto)) {
    $copilotoCusto = '1';
}

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
        <input type="hidden" id="contexto_llm" name="contexto_llm">

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
                <?php if ($copilotoDisponivel): ?>
                <button type="button" class="modo-conteudo-btn rounded-md px-4 py-2 text-sm font-semibold" data-mode="copiloto">Meu Copiloto</button>
                <?php endif; ?>
            </div>

            <?php if ($copilotoDisponivel): ?>
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
                             aria-label="Área para selecionar, arrastar ou colar materiais do Copiloto"
                             class="flex min-h-[172px] cursor-pointer flex-col items-center justify-center rounded-lg border-2 border-dashed border-blue-300 bg-white px-4 py-6 text-center hover:border-blue-500 focus:border-blue-600 focus:outline-none focus:ring-2 focus:ring-blue-100">
                            <svg class="mb-2 h-8 w-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01.88-7.9A5 5 0 1118.9 10H19a3 3 0 010 6h-3m-4-3v8m0 0l-3-3m3 3l3-3"></path>
                            </svg>
                            <p class="text-sm font-semibold text-gray-800">PDF, imagens ou prints colados</p>
                            <p class="mt-1 text-xs text-gray-500">Clique para selecionar, arraste ou pressione Ctrl/Cmd+V.</p>
                            <input id="copilotoArquivos" name="copiloto_arquivos[]" type="file" multiple accept="application/pdf,image/png,image/jpeg,image/webp" class="hidden">
                        </div>
                        <div id="copilotoListaArquivos" class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3"></div>
                    </div>
                </div>

                <div class="mt-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div id="copilotoStatus" class="flex items-center gap-2 text-sm text-blue-800">
                        <span>O resultado preencherá os campos do plano quando terminar. Custo: <?= htmlspecialchars((string) $copilotoCusto) ?> TudiCoin(s).</span>
                    </div>
                    <button type="button" id="gerarCopilotoBtn"
                            class="inline-flex items-center justify-center gap-2 rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                        </svg>
                        Gerar rascunho
                    </button>
                </div>
            </div>
            <?php endif; ?>

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
                <label for="reviewTituloInput" class="text-xs font-semibold uppercase tracking-wide text-gray-500">Título</label>
                <input type="text" id="reviewTituloInput"
                       class="mt-2 w-full rounded-lg border border-gray-300 px-4 py-3 text-sm font-semibold text-gray-900 focus:outline-none focus:ring-2 focus:ring-blue-500"
                       placeholder="Informe o título do plano">
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
var copilotoPreviewUrls = [];

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
    document.getElementById('nextStepBtn').textContent = currentStep === 3 ? 'Finalizar plano' : 'Próxima etapa';
    if (currentStep === 3) atualizarRevisao();
}

function limparErroCampoWizard(el) {
    if (!el) return;
    if (typeof limparErroCampoObrigatorio === 'function') {
        limparErroCampoObrigatorio(el);
        return;
    }
    el.classList.remove('border-red-500', 'bg-red-50', 'focus:ring-red-500', 'focus:border-red-500');
    el.classList.add('border-gray-300', 'focus:ring-blue-500');
    el.removeAttribute('aria-invalid');
}

function marcarErroCampoWizard(el, mensagem) {
    if (!el) return;
    if (typeof marcarErroCampoObrigatorio === 'function') {
        marcarErroCampoObrigatorio(el, mensagem);
        return;
    }
    el.classList.remove('border-gray-300', 'focus:ring-blue-500');
    el.classList.add('border-red-500', 'bg-red-50', 'focus:ring-red-500', 'focus:border-red-500');
    el.setAttribute('aria-invalid', 'true');
}

function marcarErroGrupoWizard(el, mensagem) {
    if (!el) return;
    el.classList.remove('border-gray-300');
    el.classList.add('border-red-500', 'bg-red-50');
    el.setAttribute('aria-invalid', 'true');
    const wrapper = el.parentElement;
    if (wrapper && !wrapper.querySelector('[data-field-error]')) {
        const msg = document.createElement('p');
        msg.setAttribute('data-field-error', '1');
        msg.className = 'mt-1 text-xs font-medium text-red-600';
        msg.textContent = mensagem || 'Campo obrigatório.';
        wrapper.appendChild(msg);
    }
}

function limparErroGrupoWizard(el) {
    if (!el) return;
    el.classList.remove('border-red-500', 'bg-red-50');
    el.classList.add('border-gray-300');
    el.removeAttribute('aria-invalid');
    const wrapper = el.parentElement;
    const msg = wrapper?.querySelector('[data-field-error]');
    if (msg) msg.remove();
}

function validarBasico() {
    atualizarDataAula();
    const materia = document.getElementById('materia_id');
    const turmasBox = document.querySelector('.turma-checkbox')?.closest('.border');
    const dataInputs = Array.from(document.querySelectorAll('input[name="datas_aula[]"]'));
    const titulo = document.getElementById('titulo');
    let primeiroErro = null;

    [materia, titulo, ...dataInputs].forEach(limparErroCampoWizard);
    limparErroGrupoWizard(turmasBox);

    function marcar(el, mensagem, isGroup) {
        if (!primeiroErro) primeiroErro = el;
        if (isGroup) {
            marcarErroGrupoWizard(el, mensagem);
        } else {
            marcarErroCampoWizard(el, mensagem);
        }
    }

    if (!materia.value) {
        marcar(materia, 'Selecione uma matéria.');
    }
    if (!document.getElementById('data_aula').value || document.getElementById('data_aula').value === '[]') {
        marcar(dataInputs[0], 'Informe pelo menos uma data da aula.');
    }
    if (document.querySelectorAll('.turma-checkbox:checked').length === 0) {
        marcar(turmasBox, 'Selecione pelo menos uma turma.', true);
    }
    if (!titulo.value.trim()) {
        marcar(titulo, 'Informe o título do plano.');
    }

    if (primeiroErro) {
        setStep(0);
        setTimeout(function () {
            primeiroErro.scrollIntoView({ behavior: 'smooth', block: 'center' });
            if (typeof primeiroErro.focus === 'function') {
                primeiroErro.focus({ preventScroll: true });
            }
        }, 100);
        return false;
    }

    return true;
}

function validarEtapaAtual() {
    if (currentStep === 0) {
        return validarBasico();
    }
    if (currentStep === 2 && typeof validarAulasTardeOficinas === 'function') {
        return validarAulasTardeOficinas();
    }
    return true;
}

function podeIrParaEtapa(targetStep) {
    if (targetStep <= currentStep) return true;
    while (currentStep < targetStep) {
        if (!validarEtapaAtual()) return false;
        setStep(currentStep + 1);
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
    document.getElementById('reviewDatas').textContent = datas.map(formatarDataPtBr).filter(Boolean).join(', ') || '-';
    const reviewTituloInput = document.getElementById('reviewTituloInput');
    if (reviewTituloInput && document.activeElement !== reviewTituloInput) {
        reviewTituloInput.value = document.getElementById('titulo').value.trim();
    }
}

function formatarDataPtBr(value) {
    const raw = String(value || '').trim();
    const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
    if (!match) return raw;
    return `${match[3]}/${match[2]}/${match[1]}`;
}

function salvarPlano(statusPlano) {
    statusPlano = statusPlano || 'rascunho';
    if (!validarBasico()) return;
    if (typeof validarAulasTardeOficinas === 'function' && !validarAulasTardeOficinas()) return;

    syncEditors();
    atualizarAnoDisciplina();
    atualizarDataAula();

    const formData = new FormData(document.getElementById('planoForm'));
    const data = Object.fromEntries(formData);
    data.status = statusPlano;
    data.turmas_id = Array.from(document.querySelectorAll('.turma-checkbox:checked')).map(cb => cb.value);
    data.recursos_lista = Array.from(document.querySelectorAll('input[name="recursos_lista[]"]:checked')).map(cb => cb.value);
    data.aulas_tarde_atividades = typeof coletarAulasTardeAtividades === 'function' ? coletarAulasTardeAtividades() : [];

    const btn = document.getElementById('saveDraftBtn');
    const nextBtn = document.getElementById('nextStepBtn');
    btn.disabled = true;
    nextBtn.disabled = true;
    btn.textContent = statusPlano === 'rascunho' ? 'Salvando...' : 'Finalizando...';

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
            if (statusPlano === 'aprovado') {
                mensagem += '\n\nStatus: Plano finalizado.';
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

function salvarRascunho() {
    salvarPlano('rascunho');
}

function finalizarPlano() {
    salvarPlano('aprovado');
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

    copilotoPreviewUrls.forEach(url => URL.revokeObjectURL(url));
    copilotoPreviewUrls = [];

    copilotoFiles.forEach((file, index) => {
        const item = document.createElement('div');
        item.className = 'overflow-hidden rounded-lg border border-blue-100 bg-white shadow-sm';

        let preview = '';
        if (file.type && file.type.indexOf('image/') === 0) {
            const url = URL.createObjectURL(file);
            copilotoPreviewUrls.push(url);
            preview = '<img src="' + url + '" alt="" class="h-20 w-full object-cover bg-gray-50">';
        } else {
            preview = '<div class="flex h-20 w-full items-center justify-center bg-red-50 text-red-600"><i class="fa-solid fa-file-pdf text-2xl"></i></div>';
        }

        item.innerHTML = `
            ${preview}
            <div class="flex items-center justify-between gap-2 px-2 py-1.5">
                <span class="truncate text-xs text-gray-700" title="${escapeHtml(file.name)}">${escapeHtml(file.name)}</span>
                <button type="button" class="shrink-0 text-xs font-medium text-red-600 hover:text-red-700" data-remove-file="${index}">Remover</button>
            </div>
        `;
        lista.appendChild(item);
    });
}

function escapeHtml(value) {
    return String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function decodeHtmlEntities(value) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = String(value || '');
    return textarea.value;
}

function containsHtml(value) {
    return /<\/?[a-z][\s\S]*>/i.test(String(value || ''));
}

function sanitizeCopilotoHtml(value) {
    let html = decodeHtmlEntities(value)
        .replace(/^\s*[•\-*]\s*(?=<(?:ul|ol|li|p|div|strong|b|em|i|br)\b)/i, '')
        .replace(/<script[\s\S]*?<\/script>/gi, '')
        .replace(/<style[\s\S]*?<\/style>/gi, '')
        .replace(/\son\w+="[^"]*"/gi, '')
        .replace(/\son\w+='[^']*'/gi, '')
        .replace(/\shref=(["'])\s*javascript:[\s\S]*?\1/gi, '');

    const allowed = ['UL', 'OL', 'LI', 'P', 'BR', 'STRONG', 'B', 'EM', 'I'];
    const template = document.createElement('template');
    template.innerHTML = html;
    template.content.querySelectorAll('*').forEach(node => {
        if (!allowed.includes(node.tagName)) {
            node.replaceWith(...Array.from(node.childNodes));
            return;
        }
        Array.from(node.attributes).forEach(attr => node.removeAttribute(attr.name));
    });

    return template.innerHTML.trim();
}

function htmlToPlainText(value) {
    const div = document.createElement('div');
    div.innerHTML = sanitizeCopilotoHtml(value);
    return div.textContent.trim();
}

function normalizarHtmlListaCopiloto(value) {
    const html = sanitizeCopilotoHtml(value);
    if (!html) return '';

    const template = document.createElement('template');
    template.innerHTML = html;
    const leafItems = Array.from(template.content.querySelectorAll('li'))
        .filter(li => !li.querySelector('li'))
        .map(li => li.innerHTML.trim())
        .filter(Boolean);

    if (leafItems.length > 0) {
        return '<ul>' + leafItems.map(item => '<li>' + item + '</li>').join('') + '</ul>';
    }

    if (containsHtml(html)) return html;

    const lines = html
        .split(/\r?\n+/)
        .map(line => line.replace(/^\s*[•\-*]\s*/, '').trim())
        .filter(Boolean);

    return lines.length > 1
        ? '<ul>' + lines.map(line => '<li>' + escapeHtml(line) + '</li>').join('') + '</ul>'
        : (lines[0] ? '<p>' + escapeHtml(lines[0]) + '</p>' : '');
}

function extrairArquivosClipboard(clipboardData) {
    const arquivos = [];
    if (!clipboardData) return arquivos;

    Array.from(clipboardData.files || []).forEach(file => arquivos.push(file));
    Array.from(clipboardData.items || []).forEach(item => {
        if (!item || item.kind !== 'file') return;
        const file = item.getAsFile();
        if (file) arquivos.push(file);
    });

    const vistos = new Set();
    return arquivos.filter(file => {
        const key = [file.name, file.type, file.size, file.lastModified].join(':');
        if (vistos.has(key)) return false;
        vistos.add(key);
        return true;
    });
}

function adicionarArquivos(files) {
    let adicionados = 0;
    Array.from(files || []).forEach(file => {
        if (!['application/pdf', 'image/png', 'image/jpeg', 'image/webp'].includes(file.type)) return;
        if (file.size > 12 * 1024 * 1024) return;
        if (copilotoFiles.length < 8) {
            copilotoFiles.push(file);
            adicionados++;
        }
    });
    atualizarListaArquivos();
    return adicionados;
}

function setCopilotoStatus(message, loading) {
    const status = document.getElementById('copilotoStatus');
    if (!status) return;

    const spinner = loading
        ? '<span class="h-4 w-4 animate-spin rounded-full border-2 border-blue-200 border-t-blue-700" aria-hidden="true"></span>'
        : '';
    status.innerHTML = spinner + '<span>' + escapeHtml(message) + '</span>';
}

function setCopilotoButtonLoading(loading, label) {
    const btn = document.getElementById('gerarCopilotoBtn');
    if (!btn) return;

    btn.disabled = loading;
    btn.innerHTML = loading
        ? '<span class="h-4 w-4 animate-spin rounded-full border-2 border-white/40 border-t-white" aria-hidden="true"></span><span>' + escapeHtml(label || 'Gerando...') + '</span>'
        : '<svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg><span>' + escapeHtml(label || 'Gerar rascunho') + '</span>';
}

function receberPasteCopiloto(e) {
    const panel = document.getElementById('copilotoPanel');
    if (!panel || panel.classList.contains('hidden')) return;

    const arquivos = extrairArquivosClipboard(e.clipboardData);
    if (!arquivos.length) return;

    e.preventDefault();
    const adicionados = adicionarArquivos(arquivos);
    setCopilotoStatus(
        adicionados > 0 ? adicionados + ' arquivo(s) colado(s).' : 'O item colado não é PDF ou imagem aceita.',
        false
    );
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
    data.append('modulo', document.getElementById('modulo').value || '');
    data.append('aula_num', document.getElementById('aula_num').value || '');
    data.append('paginas', document.getElementById('paginas').value || '');
    data.append('data_aula', document.getElementById('data_aula').value || '');
    document.querySelectorAll('.turma-checkbox:checked').forEach(cb => data.append('turmas_id[]', cb.value));
    copilotoFiles.forEach(file => data.append('copiloto_arquivos[]', file, file.name));

    setCopilotoButtonLoading(true, 'Enviando...');
    setCopilotoStatus('Preparando materiais e iniciando geração...', true);

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
        setCopilotoButtonLoading(true, 'Gerando...');
        setCopilotoStatus('Copiloto trabalhando no rascunho...', true);
        new AIJobPoller(data.job_id, {
            statusUrl: '<?= URL ?>/ai-job/{id}/status',
            interval: 2000,
            onProgress: function() {
                setCopilotoStatus('Copiloto trabalhando no rascunho...', true);
            },
            onDone: function(result) {
                aplicarRascunhoCopiloto(result);
                setCopilotoStatus('Rascunho preenchido. Revise e salve quando estiver pronto.', false);
                setCopilotoButtonLoading(false, 'Gerar novamente');
            },
            onFailed: function(message) {
                setCopilotoStatus(message || 'Falha ao gerar o rascunho.', false);
                setCopilotoButtonLoading(false, 'Gerar rascunho');
            }
        });
    })
    .catch(error => {
        setCopilotoStatus(error.message || 'Falha ao acionar o Copiloto.', false);
        setCopilotoButtonLoading(false, 'Gerar rascunho');
    });
}

function aplicarRascunhoCopiloto(result) {
    const normalizarValorCopiloto = (value, asHtmlList) => {
        if (value === undefined || value === null) return '';
        if (Array.isArray(value)) {
            const items = value
                .map(item => normalizarValorCopiloto(item, asHtmlList))
                .filter(item => item.trim() !== '');
            if (!asHtmlList) return items.join('\n');

            const listItems = [];
            items.forEach(item => {
                const template = document.createElement('template');
                template.innerHTML = item;
                const lis = Array.from(template.content.querySelectorAll('li'))
                    .filter(li => !li.querySelector('li'))
                    .map(li => li.innerHTML.trim())
                    .filter(Boolean);

                if (lis.length > 0) {
                    listItems.push(...lis);
                    return;
                }

                const text = htmlToPlainText(item) || String(item).trim();
                if (text) listItems.push(escapeHtml(text));
            });

            return listItems.length > 0
                ? '<ul>' + listItems.map(item => '<li>' + item + '</li>').join('') + '</ul>'
                : '';
        }
        if (typeof value === 'object') {
            return Object.entries(value)
                .map(([key, item]) => {
                    const text = normalizarValorCopiloto(item, false);
                    return text.trim() === '' ? '' : key + ': ' + text;
                })
                .filter(Boolean)
                .join('\n');
        }
        const normalized = String(value);
        if (!asHtmlList) {
            return containsHtml(normalized) ? htmlToPlainText(normalized) : decodeHtmlEntities(normalized).trim();
        }
        return normalizarHtmlListaCopiloto(normalized);
    };

    const setValue = (id, value) => {
        const el = document.getElementById(id);
        const normalized = normalizarValorCopiloto(value, false);
        if (el && normalized.trim() !== '') el.value = normalized;
    };
    setValue('titulo', result.titulo);
    setValue('modulo', result.modulo);
    setValue('aula_num', result.aula_num);
    setValue('paginas', result.paginas);
    setValue('observacoes', result.observacoes);
    setValue('contexto_llm', result.contexto_llm);

    const conteudo = normalizarValorCopiloto(result.conteudo, true);
    const objetivos = normalizarValorCopiloto(result.objetivos, true);
    const recursos = normalizarValorCopiloto(result.recursos, true);

    if (conteudo && typeof quillConteudo !== 'undefined') quillConteudo.clipboard.dangerouslyPasteHTML(conteudo);
    if (objetivos && typeof quillObjetivos !== 'undefined') quillObjetivos.clipboard.dangerouslyPasteHTML(objetivos);
    if (recursos && typeof quillRecursos !== 'undefined') quillRecursos.clipboard.dangerouslyPasteHTML(recursos);
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
    document.querySelectorAll('.wizard-step-btn').forEach(btn => btn.addEventListener('click', () => {
        const targetStep = Number(btn.dataset.stepTarget);
        if (targetStep <= currentStep) {
            setStep(targetStep);
            return;
        }
        podeIrParaEtapa(targetStep);
    }));
    document.getElementById('prevStepBtn').addEventListener('click', () => setStep(currentStep - 1));
    document.getElementById('nextStepBtn').addEventListener('click', function() {
        if (currentStep === 3) {
            finalizarPlano();
            return;
        }
        if (!validarEtapaAtual()) return;
        setStep(currentStep + 1);
    });
    document.getElementById('saveDraftBtn').addEventListener('click', salvarRascunho);
    document.getElementById('planoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        salvarRascunho();
    });
    document.getElementById('reviewTituloInput')?.addEventListener('input', function() {
        const titulo = document.getElementById('titulo');
        titulo.value = this.value;
        limparErroCampoWizard(titulo);
    });
    document.querySelectorAll('.modo-conteudo-btn').forEach(btn => btn.addEventListener('click', () => setModoConteudo(btn.dataset.mode)));
    document.getElementById('planoForm').addEventListener('input', function(e) {
        if (e.target.matches('input, select, textarea')) {
            limparErroCampoWizard(e.target);
        }
    });
    document.getElementById('planoForm').addEventListener('change', function(e) {
        if (e.target.matches('input, select, textarea')) {
            limparErroCampoWizard(e.target);
        }
        if (e.target.matches('.turma-checkbox')) {
            limparErroGrupoWizard(document.querySelector('.turma-checkbox')?.closest('.border'));
        }
    });

    const dropzone = document.getElementById('copilotoDropzone');
    const inputArquivos = document.getElementById('copilotoArquivos');
    if (dropzone && inputArquivos) {
        dropzone.addEventListener('click', function() {
            dropzone.focus();
            inputArquivos.click();
        });
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
        dropzone.addEventListener('paste', receberPasteCopiloto);
        document.addEventListener('paste', receberPasteCopiloto);
    }
    document.getElementById('copilotoListaArquivos')?.addEventListener('click', function(e) {
        const index = e.target.getAttribute('data-remove-file');
        if (index !== null) {
            copilotoFiles.splice(Number(index), 1);
            atualizarListaArquivos();
        }
    });
    document.getElementById('gerarCopilotoBtn')?.addEventListener('click', gerarComCopiloto);

    atualizarDataAula();
    atualizarTurmaHidden();
    setModoConteudo('manual');
    setStep(0);
});
</script>
