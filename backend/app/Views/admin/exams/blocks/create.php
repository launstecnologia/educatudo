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
<?php
$ui = __DIR__ . '/../../_partials/ui';
$ui_wizard_steps = [
    ['label' => 'Dados', 'sub' => 'Identificação'],
    ['label' => 'Configuração', 'sub' => 'Tipo e prazos'],
    ['label' => 'Turmas', 'sub' => 'Participantes'],
    ['label' => 'Professores', 'sub' => 'Matérias'],
    ['label' => 'Revisão', 'sub' => 'Confirmar'],
];
$ui_wizard_current = 1;
?>
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
<div id="provaEventoWizard" class="space-y-6">
    <?php include $ui . '/wizard_steps.php'; ?>

    <div id="wizardAlert" class="hidden rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3"></div>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 rounded-lg border border-gray-200 bg-white px-4 py-3 text-sm">
        <span id="draftStatus" class="text-gray-500">Rascunho automático ativo.</span>
        <button type="button"
                id="btnLimparRascunho"
                class="self-start sm:self-auto text-xs font-medium text-red-600 hover:text-red-800">
            Limpar rascunho
        </button>
    </div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="formBloco" onsubmit="salvarBloco(event)" novalidate>
        <section class="step-panel" data-step-panel="1">
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
                    <option value="<?= (int)$tipo['id'] ?>" data-chave-quadro="<?= htmlspecialchars((string) ($tipo['chave_quadro'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars($tipo['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="mb-6" id="campo-semana-evento">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Semana no quadro
            </label>
            <select id="semana" name="semana" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="">Não se aplica</option>
                <?php for ($s = 1; $s <= 8; $s++): ?>
                    <option value="<?= $s ?>">S<?= $s ?></option>
                <?php endfor; ?>
            </select>
            <p class="text-xs text-gray-500 mt-1">Para prova semanal, escolha S1 a S8. Bloco A costuma ser S1/S3/S5/S7; Bloco B, S2/S4/S6/S8.</p>
        </div>
        <script>
        (function () {
            var sel = document.getElementById('tipo_avaliacao_id');
            var wrap = document.getElementById('campo-semana-evento');
            var semana = document.getElementById('semana');
            if (!sel || !wrap) return;
            function syncSemana() {
                var opt = sel.options[sel.selectedIndex];
                var chave = (opt && opt.getAttribute('data-chave-quadro')) || '';
                var hide = chave !== '' && chave !== 'semanal';
                wrap.classList.toggle('hidden', hide);
                if (hide && semana) semana.value = '';
            }
            sel.addEventListener('change', syncSemana);
            syncSemana();
        })();
        </script>

        <div class="flex justify-end pt-2">
            <button type="button"
                    class="wizard-step-next btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                Próximo
                <i class="fa-solid fa-arrow-right ml-2"></i>
            </button>
        </div>
        </section>

        <section class="step-panel hidden" data-step-panel="3">
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

        <div class="flex items-center justify-between pt-2">
            <button type="button" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="2">
                <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
            </button>
            <button type="button"
                    class="wizard-step-next btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                Próximo
                <i class="fa-solid fa-arrow-right ml-2"></i>
            </button>
        </div>
        </section>

        <section class="step-panel hidden" data-step-panel="4">
        <div id="blocoProfessoresContainer" class="mb-6 hidden">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Bloco de professores (opcional)
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
                Preenche automaticamente professor e matéria a partir de um bloco pronto.
            </p>
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
            <p id="hintPassoProfessores" class="text-sm text-gray-500 mb-4">Adicione um ou mais professores com suas matérias.</p>
            
            <div id="professoresContainer" class="space-y-4">
                <!-- Primeiro professor será adicionado aqui via JavaScript -->
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <button type="button" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="3">
                <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
            </button>
            <button type="button"
                    class="wizard-step-next btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                Revisar
                <i class="fa-solid fa-arrow-right ml-2"></i>
            </button>
        </div>
        </section>

        <section class="step-panel hidden" data-step-panel="2">
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

        <!-- Tipo de Evento (online vs lançamento) -->
        <div class="mb-6">
            <label class="block text-sm font-medium text-gray-700 mb-2">
                Tipo de Evento <span class="text-red-500">*</span>
            </label>
            <p class="text-xs text-gray-500 mb-2">Em <strong>lançamento de notas</strong> não há prova com questões: a nota cheia é lançada por professor ou coordenação.</p>
            <div class="flex flex-wrap gap-6">
                <label class="flex items-center">
                    <input type="radio" name="formato_evento" value="online_questoes"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Prova online</span>
                </label>
                <label class="flex items-center">
                    <input type="radio" name="formato_evento" value="lancamento_nota"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Lançamento de notas</span>
                </label>
            </div>
        </div>

        <!-- Responsável (depende do tipo de evento) -->
        <div id="responsavelEventoBox" class="mb-6 hidden">
            <label id="labelResponsavelEvento" class="block text-sm font-medium text-gray-700 mb-2">
                Responsável <span class="text-red-500">*</span>
            </label>
            <p id="helpResponsavelEvento" class="text-xs text-gray-500 mb-2"></p>
            <div id="cfgOnlineOptions" class="flex flex-wrap gap-6 hidden">
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="professor_por_questao"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Atribuir professor</span>
                </label>
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="coordenacao_calcula"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Coordenação faz a prova</span>
                </label>
            </div>
            <div id="cfgLancamentoOptions" class="flex flex-wrap gap-6 hidden">
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="professor_por_questao"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Atribuir ao professor</span>
                </label>
                <label class="flex items-center">
                    <input type="radio"
                           name="configuracao_nota"
                           value="coordenacao_calcula"
                           class="w-4 h-4 text-purple-600 border-gray-300 focus:ring-purple-500">
                    <span class="ml-2 text-gray-700">Coordenação lança a nota</span>
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
                <label id="labelPrazoProfessor" class="block text-sm font-medium text-gray-700 mb-2">
                    Prazo para Professores Enviarem Provas <span class="text-red-500">*</span>
                </label>
                <input type="datetime-local"
                       id="prazo_entrega_professor"
                       name="prazo_entrega_professor"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <p class="text-xs text-gray-500 mt-1">Após este prazo, provas não enviadas serão automaticamente marcadas como "Não Enviadas" e travadas</p>
            </div>
        </div>

        <div class="flex items-center justify-between pt-2">
            <button type="button" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="1">
                <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
            </button>
            <button type="button"
                    class="wizard-step-next btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                Próximo
                <i class="fa-solid fa-arrow-right ml-2"></i>
            </button>
        </div>
        </section>

        <section class="step-panel hidden" data-step-panel="5">
        <div class="mb-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Revisão do evento</h3>
            <p class="text-sm text-gray-500">Confira os dados principais antes de criar o evento.</p>
        </div>

        <div id="wizardResumoEvento" class="rounded-lg border border-gray-200 bg-gray-50/50 p-5 text-sm text-gray-700 mb-6"></div>

        <!-- Botões -->
        <div class="flex items-center justify-between pt-2">
            <button type="button" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="4">
                <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
            </button>
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
        </div>
        </section>
    </form>
</div>
</div>

<script>
const professores = <?= json_encode($professores ?? []) ?>;
const materias = <?= json_encode($materias ?? []) ?>;
const turmas = <?= json_encode($turmas ?? []) ?>;
let professorCounter = 0;
const draftStorageKey = `educatudo:prova-evento:create:v2:${window.location.host}:${window.location.pathname}`;
let draftSaveTimer = null;
let restoringDraft = false;
let wizardCurrentStep = 1;
const wizardTotalSteps = 5;
const wizardCompletedSteps = {};
const wizardErrorSteps = {};
const wizardClassMap = {
    ativo: ['border-accent', 'bg-primary', 'text-primary', 'shadow-md'],
    completo: ['border-green-500', 'bg-green-50', 'text-green-700'],
    erro: ['border-red-400', 'bg-red-50', 'text-red-700'],
    pendente: ['border-gray-200', 'bg-white', 'text-gray-600', 'hover:border-gray-300', 'hover:bg-gray-50']
};
const wizardAllStateClasses = Object.keys(wizardClassMap).reduce((acc, key) => acc.concat(wizardClassMap[key]), []);

function wizardStepState(step) {
    if (wizardErrorSteps[step]) return 'erro';
    if (step === wizardCurrentStep) return 'ativo';
    if (wizardCompletedSteps[step]) return 'completo';
    return 'pendente';
}

function renderWizardBadge(btn, state) {
    const circle = btn.querySelector('.wizard-step-circle');
    if (!circle) return;
    const oldBadge = circle.querySelector('.wizard-step-corner');
    if (oldBadge) oldBadge.remove();
    const badge = state === 'completo'
        ? ['bg-green-500', 'fa-solid fa-check']
        : (state === 'erro' ? ['bg-red-500', 'fa-solid fa-exclamation'] : null);
    if (!badge) return;
    const span = document.createElement('span');
    span.className = `wizard-step-corner absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-white text-[9px] ${badge[0]}`;
    span.innerHTML = `<i class="${badge[1]}"></i>`;
    circle.appendChild(span);
}

function renderWizardSteps() {
    document.querySelectorAll('#wizardStepsNav .step-nav-btn').forEach(btn => {
        const step = parseInt(btn.dataset.stepTarget || '0', 10);
        const state = wizardStepState(step);
        btn.classList.remove(...wizardAllStateClasses);
        btn.classList.add(...wizardClassMap[state]);
        btn.dataset.stepState = state;
        btn.dataset.active = state === 'ativo' ? 'true' : 'false';
        renderWizardBadge(btn, state);
    });
    document.querySelectorAll('#wizardStepsNav [data-connector-after]').forEach(el => {
        const step = parseInt(el.dataset.connectorAfter || '0', 10);
        const ok = !!wizardCompletedSteps[step] && !wizardErrorSteps[step];
        el.classList.toggle('bg-green-400', ok);
        el.classList.toggle('bg-gray-200', !ok);
    });
}

function showWizardAlert(message) {
    const box = document.getElementById('wizardAlert');
    if (!box) return;
    box.innerHTML = `<i class="fa-solid fa-triangle-exclamation mr-2"></i>${message}`;
    box.classList.remove('hidden');
}

function clearWizardAlert() {
    const box = document.getElementById('wizardAlert');
    if (box) box.classList.add('hidden');
}

function setWizardStep(step) {
    wizardCurrentStep = Math.max(1, Math.min(wizardTotalSteps, step));
    document.querySelectorAll('.step-panel').forEach(panel => {
        panel.classList.toggle('hidden', parseInt(panel.dataset.stepPanel || '0', 10) !== wizardCurrentStep);
    });
    if (wizardCurrentStep === 5) {
        atualizarResumoWizard();
    }
    clearWizardAlert();
    renderWizardSteps();
    const wizard = document.getElementById('provaEventoWizard');
    if (wizard) {
        window.scrollTo({ top: wizard.offsetTop - 16, behavior: 'smooth' });
    }
}

function validateWizardStep(step) {
    let ok = true;
    let message = '';

    if (step === 1) {
        const required = ['titulo', 'ano_letivo', 'bimestre', 'tipo_avaliacao_id'];
        ok = required.every(id => {
            const el = document.getElementById(id);
            return el && String(el.value || '').trim() !== '';
        });
        message = 'Preencha os dados obrigatórios do evento antes de avançar.';
    } else if (step === 2) {
        const formato = document.querySelector('input[name="formato_evento"]:checked')?.value || '';
        const configuracao = inputConfiguracaoNotaNoFormatoAtual()?.value || '';
        const dataHoraVisible = !document.getElementById('agendamentoDataHoraContainer')?.classList.contains('hidden');
        const prazoVisible = !document.getElementById('prazoProfessorContainer')?.classList.contains('hidden');
        ok = !!formato && !!configuracao;
        if (ok && dataHoraVisible) {
            ok = ['data_prova', 'hora_inicio', 'hora_fim'].every(id => String(document.getElementById(id)?.value || '').trim() !== '');
        }
        if (ok && prazoVisible) {
            ok = String(document.getElementById('prazo_entrega_professor')?.value || '').trim() !== '';
        }
        message = 'Complete o tipo de evento, o responsável e os prazos necessários.';
    } else if (step === 3) {
        ok = getTurmasBlocoSelecionadas().length > 0;
        message = 'Selecione pelo menos uma turma para o evento.';
    } else if (step === 4) {
        const needQtd = exigeNumeroQuestoes();
        const professorDivs = Array.from(document.querySelectorAll('[id^="professor_"]'));
        ok = professorDivs.length > 0 && professorDivs.every(div => {
            const professorId = div.querySelector('select[name*="[professor_id]"]')?.value;
            const materiaId = div.querySelector('select[name*="[materia_id]"]')?.value;
            const turmasProfessor = div.querySelectorAll('.turma-professor-checkbox:checked').length;
            if (!professorId || !materiaId || turmasProfessor === 0) return false;
            if (needQtd) {
                const numeroQuestoes = parseInt(div.querySelector('input[name*="[numero_questoes]"]')?.value || '0', 10);
                return numeroQuestoes > 0;
            }
            return true;
        });
        message = needQtd
            ? 'Informe professor, matéria, quantidade de questões e turmas para cada professor.'
            : 'Informe professor, matéria e turmas para cada item.';
    }

    wizardErrorSteps[step] = !ok;
    if (ok) {
        wizardCompletedSteps[step] = true;
        clearWizardAlert();
    } else {
        showWizardAlert(message);
    }
    renderWizardSteps();
    return ok;
}

function goWizardStep(targetStep) {
    const target = Math.max(1, Math.min(wizardTotalSteps, parseInt(targetStep, 10) || 1));
    if (target <= wizardCurrentStep) {
        setWizardStep(target);
        return;
    }

    while (wizardCurrentStep < target) {
        if (!validateWizardStep(wizardCurrentStep)) {
            return;
        }
        setWizardStep(wizardCurrentStep + 1);
    }
}

function escapeResumo(text) {
    return String(text || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function selectedText(selector) {
    const el = document.querySelector(selector);
    if (!el || el.selectedIndex < 0) return '';
    return el.options[el.selectedIndex]?.textContent?.trim() || '';
}

function atualizarResumoWizard() {
    const out = document.getElementById('wizardResumoEvento');
    if (!out) return;

    const professoresResumo = Array.from(document.querySelectorAll('[id^="professor_"]')).map(div => {
        const professor = div.querySelector('select[name*="[professor_id]"] option:checked')?.textContent?.trim() || 'Professor não selecionado';
        const materia = div.querySelector('select[name*="[materia_id]"] option:checked')?.textContent?.trim() || 'Matéria não selecionada';
        const qtd = div.querySelector('input[name*="[numero_questoes]"]')?.value || '0';
        const turmasQtd = div.querySelectorAll('.turma-professor-checkbox:checked').length;
        const qtdTxt = exigeNumeroQuestoes() ? ` · ${escapeResumo(qtd)} questão(ões)` : '';
        return `<li>${escapeResumo(professor)} · ${escapeResumo(materia)}${qtdTxt} · ${turmasQtd} turma(s)</li>`;
    }).join('');

    const formatoTxt = document.querySelector('input[name="formato_evento"]:checked')?.parentElement?.textContent?.trim() || '';
    const respTxt = inputConfiguracaoNotaNoFormatoAtual()?.parentElement?.textContent?.trim() || '';

    out.innerHTML = `
        <dl class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div><dt class="text-xs font-medium text-gray-500 uppercase">Título</dt><dd class="font-semibold text-gray-900">${escapeResumo(document.getElementById('titulo')?.value)}</dd></div>
            <div><dt class="text-xs font-medium text-gray-500 uppercase">Ano/Bimestre</dt><dd>${escapeResumo(document.getElementById('ano_letivo')?.value)} · ${escapeResumo(selectedText('#bimestre'))}</dd></div>
            <div><dt class="text-xs font-medium text-gray-500 uppercase">Tipo de avaliação</dt><dd>${escapeResumo(selectedText('#tipo_avaliacao_id'))}</dd></div>
            <div><dt class="text-xs font-medium text-gray-500 uppercase">Semana</dt><dd>${escapeResumo(selectedText('#semana') || '—')}</dd></div>
            <div><dt class="text-xs font-medium text-gray-500 uppercase">Tipo de evento</dt><dd>${escapeResumo(formatoTxt)}</dd></div>
            <div><dt class="text-xs font-medium text-gray-500 uppercase">Responsável</dt><dd>${escapeResumo(respTxt)}</dd></div>
            <div><dt class="text-xs font-medium text-gray-500 uppercase">Turmas do evento</dt><dd>${getTurmasBlocoSelecionadas().length} turma(s)</dd></div>
            <div><dt class="text-xs font-medium text-gray-500 uppercase">Portal do aluno</dt><dd>${document.getElementById('visivel_no_portal_aluno')?.checked ? 'Visível' : 'Oculto'}</dd></div>
        </dl>
        <div class="mt-5">
            <p class="text-xs font-medium text-gray-500 uppercase mb-2">Professores</p>
            <ul class="list-disc pl-5 space-y-1">${professoresResumo || '<li>Nenhum professor configurado</li>'}</ul>
        </div>
    `;
}

function getRadioValue(name) {
    return document.querySelector(`input[name="${name}"]:checked`)?.value || '';
}

function setRadioValue(name, value) {
    if (!value) return;
    const all = Array.from(document.querySelectorAll(`input[name="${name}"]`));
    const visible = all.find(input => input.value === String(value) && !input.closest('.hidden'));
    const el = visible || all.find(input => input.value === String(value));
    if (el) el.checked = true;
}

function coletarRascunhoEvento() {
    return {
        saved_at: new Date().toISOString(),
        step: wizardCurrentStep,
        campos: {
            titulo: document.getElementById('titulo')?.value || '',
            descricao: document.getElementById('descricao')?.value || '',
            ano_letivo: document.getElementById('ano_letivo')?.value || '',
            bimestre: document.getElementById('bimestre')?.value || '',
            tipo_avaliacao_id: document.getElementById('tipo_avaliacao_id')?.value || '',
            semana: document.getElementById('semana')?.value || '',
            bloco_modelo_id: document.getElementById('bloco_modelo_id')?.value || '',
            tipo_prova: getRadioValue('tipo_prova'),
            formato_evento: getRadioValue('formato_evento'),
            configuracao_nota: getRadioValue('configuracao_nota'),
            data_prova: document.getElementById('data_prova')?.value || '',
            hora_inicio: document.getElementById('hora_inicio')?.value || '',
            hora_fim: document.getElementById('hora_fim')?.value || '',
            prazo_entrega_professor: document.getElementById('prazo_entrega_professor')?.value || '',
            visivel_no_portal_aluno: !!document.getElementById('visivel_no_portal_aluno')?.checked,
            nota_unica_todas_materias: !!document.getElementById('nota_unica_todas_materias')?.checked
        },
        turmas: getTurmasBlocoSelecionadas(),
        professores: Array.from(document.querySelectorAll('[id^="professor_"]')).map(div => ({
            professor_id: div.querySelector('select[name*="[professor_id]"]')?.value || '',
            materia_id: div.querySelector('select[name*="[materia_id]"]')?.value || '',
            numero_questoes: div.querySelector('input[name*="[numero_questoes]"]')?.value || '',
            turmas: Array.from(div.querySelectorAll('.turma-professor-checkbox:checked')).map(cb => parseInt(cb.value, 10)).filter(id => id > 0)
        }))
    };
}

function atualizarStatusRascunho(savedAt = null) {
    const el = document.getElementById('draftStatus');
    if (!el) return;
    if (!savedAt) {
        el.textContent = 'Rascunho automático ativo.';
        return;
    }
    const dt = new Date(savedAt);
    const hora = Number.isNaN(dt.getTime()) ? '' : dt.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    el.textContent = hora ? `Rascunho salvo às ${hora}.` : 'Rascunho salvo.';
}

function salvarRascunhoEvento() {
    if (restoringDraft) return;
    try {
        const draft = coletarRascunhoEvento();
        localStorage.setItem(draftStorageKey, JSON.stringify(draft));
        atualizarStatusRascunho(draft.saved_at);
    } catch (e) {
        console.warn('Não foi possível salvar o rascunho do evento:', e);
    }
}

function agendarSalvarRascunho() {
    if (restoringDraft) return;
    clearTimeout(draftSaveTimer);
    draftSaveTimer = setTimeout(salvarRascunhoEvento, 350);
}

function limparRascunhoEvento(confirmar = true) {
    if (confirmar && !confirm('Limpar o rascunho salvo deste evento?')) {
        return;
    }
    localStorage.removeItem(draftStorageKey);
    atualizarStatusRascunho(null);
}

function preencherCampo(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

function restaurarRascunhoEvento() {
    let draft = null;
    try {
        draft = JSON.parse(localStorage.getItem(draftStorageKey) || 'null');
    } catch (e) {
        draft = null;
    }
    if (!draft || typeof draft !== 'object') {
        return;
    }

    restoringDraft = true;
    const campos = draft.campos || {};
    preencherCampo('titulo', campos.titulo);
    preencherCampo('descricao', campos.descricao);
    preencherCampo('ano_letivo', campos.ano_letivo);
    preencherCampo('bimestre', campos.bimestre);
    preencherCampo('tipo_avaliacao_id', campos.tipo_avaliacao_id);
    preencherCampo('semana', campos.semana);
    preencherCampo('bloco_modelo_id', campos.bloco_modelo_id);
    preencherCampo('data_prova', campos.data_prova);
    preencherCampo('hora_inicio', campos.hora_inicio);
    preencherCampo('hora_fim', campos.hora_fim);
    preencherCampo('prazo_entrega_professor', campos.prazo_entrega_professor);
    setRadioValue('tipo_prova', campos.tipo_prova);
    setRadioValue('formato_evento', campos.formato_evento);
    ajustarOpcoesConfiguracaoNotaPorFormato();
    setRadioValue('configuracao_nota', campos.configuracao_nota);
    ajustarOpcoesConfiguracaoNotaPorFormato();
    const portal = document.getElementById('visivel_no_portal_aluno');
    if (portal) portal.checked = !!campos.visivel_no_portal_aluno;
    const notaUnica = document.getElementById('nota_unica_todas_materias');
    if (notaUnica) notaUnica.checked = !!campos.nota_unica_todas_materias;

    const turmasDraft = new Set((draft.turmas || []).map(id => parseInt(id, 10)).filter(id => id > 0));
    document.querySelectorAll('input[name="turmas[]"]').forEach(cb => {
        cb.checked = turmasDraft.has(parseInt(cb.value, 10));
    });

    const professoresDraft = Array.isArray(draft.professores) && draft.professores.length > 0
        ? draft.professores
        : [];
    if (professoresDraft.length > 0) {
        document.getElementById('professoresContainer').innerHTML = '';
        professorCounter = 0;
        professoresDraft.forEach(profDraft => {
            adicionarProfessor();
            const div = document.getElementById(`professor_${professorCounter}`);
            if (!div) return;
            const professorSelect = div.querySelector('select[name*="[professor_id]"]');
            if (professorSelect) {
                professorSelect.value = profDraft.professor_id || '';
                carregarMateriasProfessor(professorCounter);
            }
            const materiaSelect = div.querySelector('select[name*="[materia_id]"]');
            if (materiaSelect) materiaSelect.value = profDraft.materia_id || '';
            const qtd = div.querySelector('input[name*="[numero_questoes]"]');
            if (qtd) qtd.value = profDraft.numero_questoes || '5';
            const grid = div.querySelector('.turmas-professor-grid');
            if (grid) grid.innerHTML = turmasProfessorHtml(professorCounter, profDraft.turmas || []);
        });
    } else {
        sincronizarTurmasProfessoresComBloco();
    }

    ajustarOpcoesConfiguracaoNotaPorFormato();
    atualizarStatusRascunho(draft.saved_at);
    restoringDraft = false;
    setWizardStep(Math.min(Math.max(parseInt(draft.step || '1', 10), 1), wizardTotalSteps));
}

function inicializarRascunhoEvento() {
    const form = document.getElementById('formBloco');
    if (!form) return;
    form.addEventListener('input', agendarSalvarRascunho);
    form.addEventListener('change', agendarSalvarRascunho);
    const btnLimpar = document.getElementById('btnLimparRascunho');
    if (btnLimpar) {
        btnLimpar.addEventListener('click', () => limparRascunhoEvento(true));
    }
    restaurarRascunhoEvento();
}

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
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
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
            <div class="campo-numero-questoes">
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

        <div>
            <div class="flex items-center justify-between gap-3 mb-2">
                <label class="block text-sm font-medium text-gray-700">
                    Turmas deste professor <span class="text-red-500">*</span>
                </label>
                <button type="button"
                        onclick="marcarTurmasProfessor(${professorCounter})"
                        class="text-xs font-medium text-purple-700 hover:text-purple-900">
                    Marcar turmas do evento
                </button>
            </div>
            <div class="turmas-professor-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 border border-gray-200 rounded-lg bg-white p-3">
                ${turmasProfessorHtml(professorCounter)}
            </div>
            <p class="text-xs text-gray-500 mt-2">Selecione apenas as turmas em que este professor deve criar prova ou lançar nota.</p>
        </div>
    `;
    
    container.appendChild(professorDiv);
    atualizarCamposPassoProfessores();
}

function removerProfessor(id) {
    const professorDiv = document.getElementById(`professor_${id}`);
    if (professorDiv) {
        professorDiv.remove();
        agendarSalvarRascunho();
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
        agendarSalvarRascunho();
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
                agendarSalvarRascunho();
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
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
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
            <div class="campo-numero-questoes">
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

        <div>
            <div class="flex items-center justify-between gap-3 mb-2">
                <label class="block text-sm font-medium text-gray-700">
                    Turmas deste professor <span class="text-red-500">*</span>
                </label>
                <button type="button"
                        onclick="marcarTurmasProfessor(${professorCounter})"
                        class="text-xs font-medium text-purple-700 hover:text-purple-900">
                    Marcar turmas do evento
                </button>
            </div>
            <div class="turmas-professor-grid grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 border border-gray-200 rounded-lg bg-white p-3">
                ${turmasProfessorHtml(professorCounter)}
            </div>
            <p class="text-xs text-gray-500 mt-2">Selecione apenas as turmas em que este professor deve criar prova ou lançar nota.</p>
        </div>
    `;
    
    container.appendChild(professorDiv);
    atualizarCamposPassoProfessores();
}

// Adiciona o primeiro professor automaticamente ao carregar
document.addEventListener('DOMContentLoaded', function() {
    adicionarProfessor();
    esconderAgendaAteEscolhaInicial();
    inicializarWizardEventoProva();
    document.querySelectorAll('input[name="turmas[]"]').forEach(el => {
        el.addEventListener('change', sincronizarTurmasProfessoresComBloco);
    });
    document.querySelectorAll('input[name="formato_evento"]').forEach(el => {
        el.addEventListener('change', ajustarOpcoesConfiguracaoNotaPorFormato);
    });
    document.querySelectorAll('input[name="configuracao_nota"]').forEach(el => {
        el.addEventListener('change', ajustarOpcoesConfiguracaoNotaPorFormato);
    });
    inicializarRascunhoEvento();
});

function inicializarWizardEventoProva() {
    document.querySelectorAll('#wizardStepsNav .step-nav-btn').forEach(btn => {
        btn.addEventListener('click', () => goWizardStep(btn.dataset.stepTarget));
    });
    document.querySelectorAll('.wizard-step-next').forEach(btn => {
        btn.addEventListener('click', () => goWizardStep(wizardCurrentStep + 1));
    });
    document.querySelectorAll('.wizard-step-back').forEach(btn => {
        btn.addEventListener('click', () => goWizardStep(btn.dataset.goStep || (wizardCurrentStep - 1)));
    });
    renderWizardSteps();
}

function getTurmasBlocoSelecionadas() {
    return Array.from(document.querySelectorAll('input[name="turmas[]"]:checked'))
        .map(cb => parseInt(cb.value, 10))
        .filter(id => id > 0);
}

function turmasProfessorHtml(professorIndex, selecionadas = null) {
    const turmasBloco = getTurmasBlocoSelecionadas();
    const turmasSelecionadas = Array.isArray(selecionadas)
        ? selecionadas.map(id => parseInt(id, 10))
        : turmasBloco;
    const selecionadasSet = new Set(turmasSelecionadas);

    if (!Array.isArray(turmas) || turmas.length === 0) {
        return '<p class="text-sm text-gray-500">Nenhuma turma disponível</p>';
    }

    const turmasDoEvento = turmas.filter(t => turmasBloco.includes(parseInt(t.id, 10)));

    if (turmasDoEvento.length === 0) {
        return '<p class="text-sm text-gray-500 col-span-full">Selecione as turmas do evento para liberar as turmas deste professor.</p>';
    }

    return turmasDoEvento.map(t => {
        const turmaId = parseInt(t.id, 10);
        const checked = selecionadasSet.has(turmaId) ? 'checked' : '';
        const serie = t.serie ? `<span class="block text-xs text-gray-500">Série: ${t.serie}</span>` : '';
        return `
            <label class="flex items-start gap-2 p-2 rounded border border-gray-100 hover:bg-gray-50 cursor-pointer">
                <input type="checkbox"
                       name="professores[${professorIndex}][turmas][]"
                       value="${turmaId}"
                       data-turma-id="${turmaId}"
                       class="turma-professor-checkbox mt-1 w-4 h-4 text-purple-600 border-gray-300 rounded focus:ring-purple-500">
                <span class="min-w-0">
                    <span class="block text-sm font-medium text-gray-800">${t.nome}</span>
                    ${serie}
                </span>
            </label>
        `.replace('value="' + turmaId + '"', 'value="' + turmaId + '" ' + checked);
    }).join('');
}

function marcarTurmasProfessor(professorIndex) {
    const selecionadas = new Set(getTurmasBlocoSelecionadas());
    document.querySelectorAll(`#professor_${professorIndex} .turma-professor-checkbox`).forEach(cb => {
        const turmaId = parseInt(cb.dataset.turmaId || cb.value, 10);
        cb.checked = selecionadas.has(turmaId);
    });
    agendarSalvarRascunho();
}

function sincronizarTurmasProfessoresComBloco() {
    const selecionadas = new Set(getTurmasBlocoSelecionadas());
    document.querySelectorAll('[id^="professor_"]').forEach(div => {
        const professorIndex = parseInt(div.id.replace('professor_', ''), 10);
        const checks = Array.from(div.querySelectorAll('.turma-professor-checkbox'));
        const turmasVisiveisAntes = new Set(checks.map(cb => parseInt(cb.dataset.turmaId || cb.value, 10)));
        const turmasMarcadas = checks
            .filter(cb => cb.checked)
            .map(cb => parseInt(cb.dataset.turmaId || cb.value, 10))
            .filter(id => selecionadas.has(id));

        selecionadas.forEach(id => {
            if (!turmasVisiveisAntes.has(id)) {
                turmasMarcadas.push(id);
            }
        });

        const grid = div.querySelector('.turmas-professor-grid');
        if (grid && professorIndex > 0) {
            grid.innerHTML = turmasProfessorHtml(professorIndex, checks.length > 0 ? turmasMarcadas : null);
        }
    });
    agendarSalvarRascunho();
}

function manterAgendaNoFinalDoFormulario() {
    return;
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

function getFormatoEvento() {
    return document.querySelector('input[name="formato_evento"]:checked')?.value || '';
}

function atribuirAoProfessor() {
    return (inputConfiguracaoNotaNoFormatoAtual()?.value || '') === 'professor_por_questao';
}

function exigeNumeroQuestoes() {
    return getFormatoEvento() === 'online_questoes' && atribuirAoProfessor();
}

function atualizarCamposPassoProfessores() {
    const formato = getFormatoEvento();
    const atribuir = atribuirAoProfessor();
    const blocoBox = document.getElementById('blocoProfessoresContainer');
    if (blocoBox) {
        blocoBox.classList.toggle('hidden', !(formato && atribuir));
    }

    const showQtd = exigeNumeroQuestoes();
    document.querySelectorAll('.campo-numero-questoes').forEach(el => {
        el.classList.toggle('hidden', !showQtd);
        const input = el.querySelector('input');
        if (input) {
            input.required = showQtd;
            if (!showQtd) {
                input.removeAttribute('required');
            }
        }
    });

    const hint = document.getElementById('hintPassoProfessores');
    if (hint) {
        if (!formato) {
            hint.textContent = 'Adicione um ou mais professores com suas matérias.';
        } else if (formato === 'lancamento_nota' && !atribuir) {
            hint.textContent = 'Selecione matéria e professor. A coordenação lança a nota cheia — sem quantidade de questões.';
        } else if (formato === 'lancamento_nota') {
            hint.textContent = 'Selecione o professor e, se quiser, um bloco. A nota será lançada cheia, sem quantidade de questões.';
        } else if (atribuir) {
            hint.textContent = 'Selecione o professor, o bloco (opcional) e a quantidade de questões que ele deve criar.';
        } else {
            hint.textContent = 'Selecione matéria e professor. A coordenação elabora a prova — sem quantidade fixa de questões.';
        }
    }
}

function inputConfiguracaoNotaNoFormatoAtual() {
    const formatoSel = getFormatoEvento();
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
    const formatoSel = getFormatoEvento();
    const respBox = document.getElementById('responsavelEventoBox');
    const labelResp = document.getElementById('labelResponsavelEvento');
    const helpResp = document.getElementById('helpResponsavelEvento');
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
        if (respBox) respBox.classList.add('hidden');
        onlineBox.classList.add('hidden');
        lancBox.classList.add('hidden');
        if (notaUnicaBox) notaUnicaBox.classList.add('hidden');
        if (msgCoord) msgCoord.classList.add('hidden');
        if (dataHoraBox) dataHoraBox.classList.add('hidden');
        if (prazoBox) prazoBox.classList.add('hidden');
        if (dataProvaInput) dataProvaInput.required = false;
        if (horaInicioInput) horaInicioInput.required = false;
        if (horaFimInput) horaFimInput.required = false;
        if (prazoInput) prazoInput.required = false;
        atualizarCamposPassoProfessores();
        return;
    }

    if (respBox) respBox.classList.remove('hidden');

    const cfgAnterior = document.querySelector('input[name="configuracao_nota"]:checked')?.value || '';

    if (formatoSel === 'lancamento_nota') {
        onlineBox.classList.add('hidden');
        lancBox.classList.remove('hidden');
        if (labelResp) labelResp.innerHTML = 'Quem lança a nota <span class="text-red-500">*</span>';
        if (helpResp) helpResp.textContent = 'Atribua ao professor ou deixe a coordenação lançar a nota cheia.';
        let cfgChecked = lancBox.querySelector('input[name="configuracao_nota"]:checked')?.value || '';
        if (!['coordenacao_calcula', 'professor_por_questao'].includes(cfgChecked)) {
            const prefer = ['coordenacao_calcula', 'professor_por_questao'].includes(cfgAnterior)
                ? cfgAnterior
                : 'professor_por_questao';
            const def = lancBox.querySelector(`input[name="configuracao_nota"][value="${prefer}"]`);
            if (def) def.checked = true;
        }
        onlineBox.querySelectorAll('input[name="configuracao_nota"]').forEach(el => { el.checked = false; });
    } else {
        onlineBox.classList.remove('hidden');
        lancBox.classList.add('hidden');
        if (labelResp) labelResp.innerHTML = 'Quem elabora a prova <span class="text-red-500">*</span>';
        if (helpResp) helpResp.textContent = 'Atribua ao professor para ele criar as questões, ou a coordenação elabora a prova.';
        if (notaUnicaBox) notaUnicaBox.classList.add('hidden');
        if (msgCoord) msgCoord.classList.add('hidden');
        const notaUnicaInput = document.getElementById('nota_unica_todas_materias');
        if (notaUnicaInput) notaUnicaInput.checked = false;
        let cfgChecked = onlineBox.querySelector('input[name="configuracao_nota"]:checked')?.value || '';
        if (!['professor_por_questao', 'coordenacao_calcula'].includes(cfgChecked)) {
            const prefer = ['coordenacao_calcula', 'professor_por_questao'].includes(cfgAnterior)
                ? cfgAnterior
                : 'professor_por_questao';
            const def = onlineBox.querySelector(`input[name="configuracao_nota"][value="${prefer}"]`);
            if (def) def.checked = true;
        }
        lancBox.querySelectorAll('input[name="configuracao_nota"]').forEach(el => { el.checked = false; });
    }

    const cfgFinal = inputConfiguracaoNotaNoFormatoAtual()?.value || '';
    const showDataHora = (formatoSel === 'online_questoes');
    const showPrazo = cfgFinal === 'professor_por_questao';
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
    const prazoLabel = document.getElementById('labelPrazoProfessor');
    if (prazoLabel) {
        prazoLabel.innerHTML = formatoSel === 'lancamento_nota'
            ? 'Prazo para o professor lançar as notas <span class="text-red-500">*</span>'
            : 'Prazo para Professores Enviarem Provas <span class="text-red-500">*</span>';
    }
    atualizarCamposPassoProfessores();
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
    
    let professoresInvalidos = false;
    let turmasProfessorInvalidas = false;
    const needQtd = exigeNumeroQuestoes();
    professorDivs.forEach(div => {
        const professorId = div.querySelector('select[name*="[professor_id]"]')?.value;
        const materiaId = div.querySelector('select[name*="[materia_id]"]')?.value;
        const numeroQuestoes = div.querySelector('input[name*="[numero_questoes]"]')?.value;
        
        if (!professorId || !materiaId) {
            professoresInvalidos = true;
            return;
        }
        
        if (needQtd && (!numeroQuestoes || parseInt(numeroQuestoes) < 1)) {
            professoresInvalidos = true;
            return;
        }

        const turmasProfessor = Array.from(div.querySelectorAll('.turma-professor-checkbox:checked'))
            .map(cb => parseInt(cb.value, 10))
            .filter(id => id > 0);
        if (turmasProfessor.length === 0) {
            turmasProfessorInvalidas = true;
            return;
        }
        
        professores.push({
            professor_id: parseInt(professorId),
            materia_id: parseInt(materiaId),
            numero_questoes: needQtd ? parseInt(numeroQuestoes, 10) : 0,
            turmas: turmasProfessor
        });
    });

    // Coleta turmas do bloco (não por professor)
    const turmasCheckboxes = form.querySelectorAll('input[name="turmas[]"]:checked');
    if (turmasCheckboxes.length === 0) {
        alert('Selecione pelo menos uma turma para o bloco');
        return;
    }
    const turmasIds = Array.from(turmasCheckboxes).map(cb => parseInt(cb.value));
    const turmasBlocoSet = new Set(turmasIds);
    if (professoresInvalidos) {
        alert(needQtd
            ? 'Preencha professor, matéria e número de questões para todos os professores adicionados'
            : 'Preencha professor e matéria para todos os professores adicionados');
        return;
    }
    if (turmasProfessorInvalidas) {
        alert('Selecione pelo menos uma turma para cada professor');
        return;
    }
    if (professores.some(prof => prof.turmas.some(turmaId => !turmasBlocoSet.has(turmaId)))) {
        alert('As turmas de cada professor precisam estar dentro das turmas selecionadas para o evento');
        return;
    }
    
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
        semana: formData.get('semana') ? parseInt(formData.get('semana'), 10) : null,
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
            limparRascunhoEvento(false);
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
