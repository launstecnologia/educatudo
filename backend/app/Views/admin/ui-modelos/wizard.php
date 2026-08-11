<?php
$ui = __DIR__ . '/../_partials/ui';

$page_header_back_url = URL . '/admin/configuracao/ui-modelos';
$page_header_title = 'Wizard (fluxo em etapas)';
$page_header_subtitle = 'Página própria com várias etapas — partials wizard_steps + wizard_actions';
include __DIR__ . '/../_partials/page_header_form.php';

$flash_status = ($flash_message ?? '') !== ''
    ? (($flash_type ?? '') === 'error' ? 'error' : 'success')
    : '';
include __DIR__ . '/../_partials/flash_message.php';

$steps = [
    ['label' => 'Dados', 'sub' => 'Identificação'],
    ['label' => 'Detalhes', 'sub' => 'Configuração'],
    ['label' => 'Preferências', 'sub' => 'Opcional'],
    ['label' => 'Revisão', 'sub' => 'Confirmar'],
];
?>

<div class="mb-6 p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
    <i class="fa-solid fa-circle-info mr-2"></i>
    Use wizard só quando o cadastro tem <strong>várias etapas ou conteúdo rico</strong>
    (nunca CRUD simples de entidade única — nesse caso ver a skill <code>educatudo-admin-ui</code>
    de offcanvas). Referência real completa: <code>admin/inclusao/manage.php</code>.
</div>

<div id="uiWizardDemo" class="space-y-6">
    <?php
    $ui_wizard_steps = $steps;
    $ui_wizard_current = 1;
    include $ui . '/wizard_steps.php';
    ?>

    <form method="POST" action="<?= URL ?>/admin/configuracao/ui-modelos/wizard" id="uiWizardForm">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

        <!-- Etapa 1 -->
        <div class="step-panel" data-step-panel="1">
            <?php
            ob_start();
            ?>
            <div class="space-y-6">
                <?php $ui_form_secao_titulo = 'Identificação'; include $ui . '/form_secao.php'; ?>
                <div id="wizardStep1Erro" class="hidden rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3">
                    <i class="fa-solid fa-triangle-exclamation mr-2"></i>
                    Preencha os campos obrigatórios (Nome e Tipo) antes de avançar.
                </div>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    $ui_form_campo_label = 'Nome';
                    $ui_form_campo_name = 'wizard_nome';
                    $ui_form_campo_tipo = 'text';
                    $ui_form_campo_obrigatorio = true;
                    $ui_form_campo_placeholder = 'Nome do registro';
                    $ui_form_campo_span = 'full';
                    include $ui . '/form_campo.php';

                    $ui_form_campo_label = 'Tipo';
                    $ui_form_campo_name = 'wizard_tipo';
                    $ui_form_campo_tipo = 'select';
                    $ui_form_campo_opcoes = ['' => 'Selecione…', 'padrao' => 'Padrão', 'avancado' => 'Avançado'];
                    $ui_form_campo_obrigatorio = true;
                    $ui_form_campo_span = 'half';
                    include $ui . '/form_campo.php';

                    $ui_form_campo_label = 'Início da vigência';
                    $ui_form_campo_name = 'wizard_inicio';
                    $ui_form_campo_tipo = 'date';
                    $ui_form_campo_obrigatorio = false;
                    $ui_form_campo_span = 'half';
                    include $ui . '/form_campo.php';
                    ?>
                </div>
                <?php
                $ui_wizard_actions_back_step = null;
                $ui_wizard_actions_next_label = 'Próximo';
                $ui_wizard_actions_next_id = 'wizardBtnStep1Next';
                include $ui . '/wizard_actions.php';
                ?>
            </div>
            <?php
            $ui_card_body = ob_get_clean();
            $ui_card_variant = 'form';
            include $ui . '/card.php';
            ?>
        </div>

        <!-- Etapa 2 -->
        <div class="step-panel hidden" data-step-panel="2">
            <?php
            ob_start();
            ?>
            <div class="space-y-6">
                <?php $ui_form_secao_titulo = 'Detalhes'; include $ui . '/form_secao.php'; ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php
                    $ui_form_campo_label = 'Responsável';
                    $ui_form_campo_name = 'wizard_responsavel';
                    $ui_form_campo_tipo = 'text';
                    $ui_form_campo_obrigatorio = false;
                    $ui_form_campo_placeholder = 'Nome do responsável';
                    $ui_form_campo_span = 'half';
                    include $ui . '/form_campo.php';

                    $ui_form_campo_label = 'Limite';
                    $ui_form_campo_name = 'wizard_limite';
                    $ui_form_campo_tipo = 'number';
                    $ui_form_campo_obrigatorio = false;
                    $ui_form_campo_span = 'half';
                    include $ui . '/form_campo.php';

                    $ui_form_campo_label = 'Observações';
                    $ui_form_campo_name = 'wizard_observacoes';
                    $ui_form_campo_tipo = 'textarea';
                    $ui_form_campo_rows = 3;
                    $ui_form_campo_obrigatorio = false;
                    $ui_form_campo_span = 'full';
                    include $ui . '/form_campo.php';
                    ?>
                </div>
                <?php
                $ui_wizard_actions_back_step = 1;
                $ui_wizard_actions_next_label = 'Próximo';
                $ui_wizard_actions_next_id = 'wizardBtnStep2Next';
                include $ui . '/wizard_actions.php';
                ?>
            </div>
            <?php
            $ui_card_body = ob_get_clean();
            $ui_card_variant = 'form';
            include $ui . '/card.php';
            ?>
        </div>

        <!-- Etapa 3 -->
        <div class="step-panel hidden" data-step-panel="3">
            <?php
            ob_start();
            ?>
            <div class="space-y-6">
                <?php $ui_form_secao_titulo = 'Preferências'; include $ui . '/form_secao.php'; ?>
                <label class="flex items-start gap-3 cursor-pointer border border-gray-200 bg-gray-50/50 rounded-lg p-4">
                    <input type="checkbox" name="wizard_notificar" value="1" class="mt-1 h-4 w-4 text-primary border-gray-300 rounded focus:ring-2">
                    <span>
                        <span class="block text-sm font-medium text-gray-800">Notificar por e-mail</span>
                        <span class="block text-xs text-gray-500">Envia um resumo assim que o registro for concluído.</span>
                    </span>
                </label>
                <?php
                $ui_wizard_actions_back_step = 2;
                $ui_wizard_actions_next_label = 'Revisar';
                $ui_wizard_actions_next_id = 'wizardBtnStep3Next';
                include $ui . '/wizard_actions.php';
                ?>
            </div>
            <?php
            $ui_card_body = ob_get_clean();
            $ui_card_variant = 'form';
            include $ui . '/card.php';
            ?>
        </div>

        <!-- Etapa 4 — Revisão -->
        <div class="step-panel hidden" data-step-panel="4">
            <?php
            ob_start();
            ?>
            <div class="space-y-6">
                <?php $ui_form_secao_titulo = 'Revisão'; include $ui . '/form_secao.php'; ?>
                <p class="text-sm text-gray-500 -mt-2">Confira os dados antes de concluir. Nada é persistido nesta demo.</p>
                <div id="wizardResumo" class="rounded-lg border border-gray-200 bg-gray-50/50 p-5"></div>
                <div class="flex items-center justify-between pt-2">
                    <button type="button" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium" data-go-step="3">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
                    </button>
                    <button type="submit" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                        <i class="fa-solid fa-check mr-2"></i> Concluir exemplo
                    </button>
                </div>
            </div>
            <?php
            $ui_card_body = ob_get_clean();
            $ui_card_variant = 'form';
            include $ui . '/card.php';
            ?>
        </div>
    </form>
</div>

<script>
(function () {
    var wizard = document.getElementById('uiWizardDemo');
    var current = 1;
    var completedSteps = {};
    var errorSteps = {};

    var CLASS_MAP = {
        ativo: ['border-accent', 'bg-primary', 'text-primary', 'shadow-md'],
        completo: ['border-green-500', 'bg-green-50', 'text-green-700'],
        erro: ['border-red-400', 'bg-red-50', 'text-red-700'],
        pendente: ['border-gray-200', 'bg-white', 'text-gray-600', 'hover:border-gray-300', 'hover:bg-gray-50']
    };
    var ALL_STATE_CLASSES = Object.keys(CLASS_MAP).reduce(function (acc, k) { return acc.concat(CLASS_MAP[k]); }, []);
    var BADGE_MAP = {
        completo: ['bg-green-500', 'fa-solid fa-check'],
        erro: ['bg-red-500', 'fa-solid fa-exclamation']
    };

    // Precedência de estado por etapa: erro > ativo > completo > pendente (mesma regra do partial).
    function stepState(n) {
        if (errorSteps[n]) return 'erro';
        if (n === current) return 'ativo';
        if (completedSteps[n]) return 'completo';
        return 'pendente';
    }

    function renderStepBadge(btn, estado) {
        var circle = btn.querySelector('.wizard-step-circle');
        if (!circle) return;
        var existing = circle.querySelector('.wizard-step-corner');
        if (existing) existing.remove();
        var badge = BADGE_MAP[estado];
        if (!badge) return;
        var span = document.createElement('span');
        span.className = 'wizard-step-corner absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-white text-[9px] ' + badge[0];
        var icon = document.createElement('i');
        icon.className = badge[1];
        span.appendChild(icon);
        circle.appendChild(span);
    }

    function setActiveNav() {
        document.querySelectorAll('#wizardStepsNav .step-nav-btn').forEach(function (btn) {
            var n = parseInt(btn.getAttribute('data-step-target'), 10);
            var estado = stepState(n);
            btn.setAttribute('data-active', estado === 'ativo' ? 'true' : 'false');
            btn.setAttribute('data-step-state', estado);
            ALL_STATE_CLASSES.forEach(function (c) { btn.classList.remove(c); });
            CLASS_MAP[estado].forEach(function (c) { btn.classList.add(c); });
            renderStepBadge(btn, estado);
        });
        document.querySelectorAll('#wizardStepsNav [data-connector-after]').forEach(function (el) {
            var n = parseInt(el.getAttribute('data-connector-after'), 10);
            var done = !!completedSteps[n] && !errorSteps[n];
            el.classList.toggle('bg-green-400', done);
            el.classList.toggle('bg-gray-200', !done);
        });
    }

    window.setWizardStep = function (n) {
        current = n;
        document.querySelectorAll('[data-step-panel]').forEach(function (el) {
            var sn = parseInt(el.getAttribute('data-step-panel'), 10);
            el.classList.toggle('hidden', sn !== n);
        });
        setActiveNav();
        if (n === 4) buildWizardResumo();
        window.scrollTo({ top: wizard.offsetTop - 16, behavior: 'smooth' });
    };

    document.querySelectorAll('#wizardStepsNav .step-nav-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setWizardStep(parseInt(btn.getAttribute('data-step-target'), 10));
        });
    });
    document.querySelectorAll('.wizard-step-back').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setWizardStep(parseInt(btn.getAttribute('data-go-step'), 10));
        });
    });

    // Etapa 1 é validada antes de avançar (Nome + Tipo obrigatórios) — demonstra
    // os estados "erro" (campo faltando) e "completo" (validado) do nav.
    var btnStep1 = document.getElementById('wizardBtnStep1Next');
    if (btnStep1) {
        btnStep1.addEventListener('click', function () {
            var nomeEl = document.querySelector('[name="wizard_nome"]');
            var tipoEl = document.querySelector('[name="wizard_tipo"]');
            var valido = !!(nomeEl && nomeEl.value.trim() !== '' && tipoEl && tipoEl.value !== '');
            var erroBox = document.getElementById('wizardStep1Erro');
            if (!valido) {
                errorSteps[1] = true;
                delete completedSteps[1];
                if (erroBox) erroBox.classList.remove('hidden');
                setActiveNav();
                return;
            }
            delete errorSteps[1];
            completedSteps[1] = true;
            if (erroBox) erroBox.classList.add('hidden');
            setWizardStep(2);
        });
    }

    var btnStep2 = document.getElementById('wizardBtnStep2Next');
    if (btnStep2) {
        btnStep2.addEventListener('click', function () {
            completedSteps[2] = true;
            setWizardStep(3);
        });
    }
    var btnStep3 = document.getElementById('wizardBtnStep3Next');
    if (btnStep3) {
        btnStep3.addEventListener('click', function () {
            completedSteps[3] = true;
            setWizardStep(4);
        });
    }

    function addRow(dl, label, value) {
        var dt = document.createElement('dt');
        dt.className = 'text-xs font-semibold text-gray-500 uppercase tracking-wide';
        dt.textContent = label;
        var dd = document.createElement('dd');
        dd.className = 'text-sm text-gray-800 mb-3';
        dd.textContent = value;
        dl.appendChild(dt);
        dl.appendChild(dd);
    }

    function buildWizardResumo() {
        var out = document.getElementById('wizardResumo');
        if (!out) return;
        out.innerHTML = '';

        var nomeEl = document.querySelector('[name="wizard_nome"]');
        var tipoEl = document.querySelector('[name="wizard_tipo"]');
        var responsavelEl = document.querySelector('[name="wizard_responsavel"]');
        var notificarEl = document.querySelector('[name="wizard_notificar"]');

        var tipoLabel = '(não informado)';
        if (tipoEl && tipoEl.selectedOptions[0] && tipoEl.value !== '') {
            tipoLabel = tipoEl.selectedOptions[0].textContent;
        }

        var dl = document.createElement('dl');
        dl.className = 'grid grid-cols-1 md:grid-cols-2 gap-x-6';
        addRow(dl, 'Nome', (nomeEl && nomeEl.value) || '(não informado)');
        addRow(dl, 'Tipo', tipoLabel);
        addRow(dl, 'Responsável', (responsavelEl && responsavelEl.value) || '(não informado)');
        addRow(dl, 'Notificar por e-mail', notificarEl && notificarEl.checked ? 'Sim' : 'Não');
        out.appendChild(dl);
    }

    setWizardStep(1);
})();
</script>
