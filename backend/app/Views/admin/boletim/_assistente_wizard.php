<?php
/**
 * Assistente guiado (wizard) — Configuração de Boletins.
 * Variáveis: $csrfToken, $boletimAssistenteDisponivel, $selected_regra_id
 */
$boletimAssistenteDisponivel = !empty($boletimAssistenteDisponivel);
$assistenteRegraId = (int) ($selected_regra_id ?? $selectedRegraId ?? 0);
$boletimAssistentePageMode = !empty($boletimAssistentePageMode);
$boletimAssistenteReturnUrl = URL . '/admin/boletim-configuracao' . ($assistenteRegraId > 0 ? ('?regra_id=' . $assistenteRegraId) : '?novo=1');
$boletimAssistenteDadosIniciais = [
    'estado' => is_array($boletim_assistente_estado_inicial ?? null) ? $boletim_assistente_estado_inicial : null,
    'catalogo' => is_array($boletim_assistente_catalogo_inicial ?? null) ? $boletim_assistente_catalogo_inicial : null,
    'rascunho' => is_array($boletim_assistente_rascunho_inicial ?? null) ? $boletim_assistente_rascunho_inicial : null,
    'resumo' => isset($boletim_assistente_resumo_inicial) ? (string) $boletim_assistente_resumo_inicial : null,
    'erros' => is_array($boletim_assistente_erros_iniciais ?? null) ? $boletim_assistente_erros_iniciais : [],
    'formulas_disponiveis' => is_array($boletim_assistente_formulas_iniciais ?? null) ? $boletim_assistente_formulas_iniciais : [],
    'preview' => $boletim_assistente_preview_inicial ?? null,
];
$ui = __DIR__ . '/../_partials/ui';
$boletimWizardSteps = [
    ['label' => 'Começar', 'sub' => 'Origem'],
    ['label' => 'Identidade', 'sub' => 'Evento'],
    ['label' => 'Peças', 'sub' => 'Blocos'],
    ['label' => 'Exibir', 'sub' => 'Colunas'],
    ['label' => 'Matérias', 'sub' => 'Escopo'],
    ['label' => 'Revisar', 'sub' => 'Confirmar'],
];
?>
<div id="boletim-assistente-root" class="<?= $boletimAssistentePageMode ? 'w-full' : 'fixed bottom-4 right-4 z-[10000] flex flex-col items-end gap-2' ?>"
     data-disponivel="<?= $boletimAssistenteDisponivel ? '1' : '0' ?>"
     data-page-mode="<?= $boletimAssistentePageMode ? '1' : '0' ?>"
     data-return-url="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>"
     data-url-mensagem="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/mensagem-stream', ENT_QUOTES, 'UTF-8') ?>"
     data-url-ferramenta="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/ferramenta', ENT_QUOTES, 'UTF-8') ?>"
     data-url-wizard-inicio="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/wizard/inicio', ENT_QUOTES, 'UTF-8') ?>"
     data-url-wizard-montar="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/wizard/montar', ENT_QUOTES, 'UTF-8') ?>"
     data-url-salvar="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/salvar', ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>"
     data-regra-id="<?= $assistenteRegraId ?>">

    <!-- Overlay wizard -->
    <div id="boletim-wizard-overlay" class="<?= $boletimAssistentePageMode ? 'block' : 'hidden fixed inset-0 z-[10000] bg-slate-900/50 backdrop-blur-[1px] p-2 sm:p-5' ?>">
        <div class="<?= $boletimAssistentePageMode ? 'w-full space-y-6' : 'mx-auto w-full max-w-[min(1180px,calc(100vw-1rem))] h-[min(94vh,900px)] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden' ?>">
            <?php if ($boletimAssistentePageMode): ?>
            <div class="p-4 rounded-lg bg-blue-50 border border-blue-200 text-blue-800 text-sm">
                <i class="fa-solid fa-circle-info mr-2"></i>
                Use este assistente para montar a regra do boletim por etapas. As provas, eventos e jornadas serão buscados pelo bimestre selecionado na peça.
            </div>
            <?php
            $ui_wizard_steps = $boletimWizardSteps;
            $ui_wizard_current = 1;
            $ui_wizard_nav_id = 'boletim-wizard-steps';
            include $ui . '/wizard_steps.php';
            ?>
            <div class="bg-white rounded-xl shadow-lg p-6 w-full min-h-[calc(100vh-20rem)] flex flex-col">
                <div class="space-y-2 mb-6">
                    <p class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Resumo</p>
                    <pre id="boletim-wizard-resumo" class="text-sm text-gray-600 whitespace-pre-wrap font-sans leading-relaxed">Monte as escolhas abaixo para ver o resumo.</pre>
                    <div id="boletim-wizard-erros" class="text-xs text-amber-800 hidden"></div>
                </div>
                <div id="boletim-wizard-body" class="flex-1 min-h-[24rem] space-y-4"></div>
                <div class="flex items-center justify-between pt-6 mt-6 border-t border-gray-100">
                    <button type="button" id="boletim-wizard-voltar" class="wizard-step-back px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-medium disabled:opacity-40 disabled:cursor-not-allowed">
                        <i class="fa-solid fa-arrow-left mr-2"></i>Voltar
                    </button>
                    <div class="flex gap-2">
                        <button type="button" id="boletim-wizard-aplicar" class="px-4 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 text-sm font-semibold transition-colors">Aplicar na configuração</button>
                        <button type="button" id="boletim-wizard-avancar" class="wizard-step-next btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                            Continuar <i class="fa-solid fa-arrow-right ml-2"></i>
                        </button>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <div class="px-4 sm:px-5 py-3 border-b border-gray-100 bg-slate-50 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">Assistente guiado do boletim</h2>
                    <p id="boletim-wizard-subtitulo" class="text-xs text-gray-500 truncate">Adicione blocos de cálculo, dê um nome, arraste a ordem e salve</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="boletim-wizard-copiar-receita" class="text-xs font-medium text-indigo-700 hover:bg-indigo-50 px-2 py-1.5 rounded-md">Copiar receita</button>
                    <a href="<?= htmlspecialchars($boletimAssistenteReturnUrl, ENT_QUOTES, 'UTF-8') ?>" id="boletim-wizard-fechar" class="text-gray-400 hover:text-gray-700 p-1.5" aria-label="Fechar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </a>
                </div>
            </div>

            <div class="flex-1 min-h-0 flex flex-col">
                <div class="flex flex-col min-h-0 flex-1">
                    <nav id="boletim-wizard-steps" class="px-4 pt-3 pb-2 flex flex-wrap gap-1.5 border-b border-gray-50 bg-white"></nav>
                    <div class="px-4 sm:px-5 py-2 border-b border-gray-100 bg-slate-50/70">
                        <p class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Resumo</p>
                        <pre id="boletim-wizard-resumo" class="mt-1 text-xs text-gray-600 whitespace-pre-wrap font-sans leading-relaxed max-h-24 overflow-y-auto">Monte as escolhas abaixo para ver o resumo.</pre>
                        <div id="boletim-wizard-erros" class="mt-2 text-xs text-amber-800 hidden"></div>
                    </div>
                    <div id="boletim-wizard-body" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4"></div>
                    <div class="px-4 py-3 border-t border-gray-100 bg-white flex flex-wrap items-center justify-between gap-2">
                        <button type="button" id="boletim-wizard-voltar" class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-40">Voltar</button>
                        <div class="flex gap-2">
                            <button type="button" id="boletim-wizard-aplicar" class="px-3 py-2 text-sm rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50"><?= $boletimAssistentePageMode ? 'Aplicar na configuração' : 'Aplicar no formulário' ?></button>
                            <button type="button" id="boletim-wizard-avancar" class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Continuar</button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!$boletimAssistentePageMode): ?>
    <button type="button" id="boletim-assistente-toggle"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full shadow-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Assistente guiado
    </button>
    <?php endif; ?>
</div>

<script type="application/json" id="boletim-assistente-dados-iniciais"><?= json_encode($boletimAssistenteDadosIniciais, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<style>
@keyframes boletim-assistente-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40% { transform: translateY(-4px); opacity: 1; }
}
.boletim-assistente-dot {
    width: 6px; height: 6px; border-radius: 9999px; background: #6366f1; display: inline-block;
    animation: boletim-assistente-bounce 1.2s infinite ease-in-out;
}
.boletim-assistente-dot:nth-child(2) { animation-delay: 0.15s; }
.boletim-assistente-dot:nth-child(3) { animation-delay: 0.3s; }
.bw-step-btn { font-size: 11px; padding: 4px 10px; border-radius: 9999px; border: 1px solid #e5e7eb; color: #6b7280; background: #fff; }
.bw-step-btn.active { border-color: #6366f1; background: #eef2ff; color: #4338ca; font-weight: 600; }
.bw-step-btn.done { border-color: #a7f3d0; background: #ecfdf5; color: #047857; }
.bw-card { border: 1px solid #e5e7eb; border-radius: 0.75rem; padding: 0.875rem; cursor: pointer; background: #fff; transition: border-color .15s, box-shadow .15s; }
.bw-card:hover { border-color: #a5b4fc; }
.bw-card.selected { border-color: #6366f1; box-shadow: 0 0 0 2px rgba(99,102,241,.15); background: #f8fafc; }
.bw-chip { display: inline-flex; align-items: center; gap: 4px; padding: 6px 10px; border-radius: 9999px; border: 1px solid; font-size: 13px; line-height: 1.2; user-select: none; cursor: grab; }
.bw-chip:active { cursor: grabbing; }
.bw-chip-peca { background: #eef2ff; border-color: #c7d2fe; color: #3730a3; font-weight: 600; }
.bw-chip-op { background: #f8fafc; border-color: #cbd5e1; color: #0f172a; font-weight: 700; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; min-width: 2rem; justify-content: center; }
.bw-chip-fn { background: #fef3c7; border-color: #fcd34d; color: #92400e; font-weight: 600; }
.bw-chip-num { background: #ecfdf5; border-color: #a7f3d0; color: #047857; font-weight: 600; }
.bw-palette .bw-chip { cursor: pointer; }
.bw-canvas { min-height: 96px; border: 2px dashed #c7d2fe; border-radius: 0.75rem; padding: 10px; display: flex; flex-wrap: wrap; align-content: flex-start; gap: 6px; background: #f8fafc; }
.bw-canvas.drag-over { border-color: #6366f1; background: #eef2ff; }
.bw-chip-x { margin-left: 2px; color: #64748b; font-size: 14px; line-height: 1; padding: 0 2px; }
.bw-chip-x:hover { color: #b91c1c; }
.bw-canvas-ph { width: 100%; font-size: 12px; color: #94a3b8; padding: 18px 8px; text-align: center; }
.bw-preview-table { border-collapse: collapse; width: 100%; font-size: 11px; text-align: center; }
.bw-preview-table th { background: #1f2937; color: #fff; border: 1px solid #111827; padding: 4px 6px; font-weight: 600; }
.bw-preview-table th.sub { background: #374151; }
.bw-preview-table td { border: 1px solid #d1d5db; padding: 3px 5px; }
.bw-preview-table td.mat { text-align: left; font-weight: 600; color: #111827; white-space: nowrap; }
.bw-preview-table tr:nth-child(even) td { background: #f3f4f6; }
.bw-preview-table.boletim th { background: #334155; color: #fff; border-color: #1e293b; text-transform: uppercase; letter-spacing: .02em; }
.bw-preview-table.boletim th.sub { background: #fde68a; color: #334155; text-transform: none; font-weight: 700; }
.bw-preview-table.boletim th.mat { text-transform: none; letter-spacing: 0; }
.bw-preview-table.boletim tr:nth-child(even) td { background: #f8fafc; }
.bw-preview-table.boletim td.ok { color: #047857; font-weight: 700; }
.bw-preview-table.boletim td.nok { color: #b91c1c; font-weight: 700; }
.bw-col-chip { display: inline-flex; align-items: center; gap: 4px; padding: 6px 10px; border-radius: 0.5rem; border: 1px solid #c7d2fe; background: #eef2ff; color: #3730a3; font-size: 12px; font-weight: 600; cursor: grab; user-select: none; }
.bw-col-chip.travada { background: #f1f5f9; border-color: #cbd5e1; color: #475569; cursor: default; }
.bw-col-chip.calc { background: #fef3c7; border-color: #fcd34d; color: #92400e; }
.bw-col-chip.aberta { box-shadow: 0 0 0 2px #f59e0b; }
.bw-col-chip.dragging { opacity: 0.45; }
.bw-col-chip.drop-alvo { box-shadow: 0 0 0 2px #6366f1; }
.bw-col-grip { cursor: grab; opacity: 0.45; letter-spacing: -1px; font-size: 13px; line-height: 1; padding-right: 2px; }
.bw-col-chip:active .bw-col-grip { cursor: grabbing; }
.bw-col-x { margin-left: 2px; color: #92400e; font-size: 14px; line-height: 1; padding: 0 2px; opacity: 0.6; cursor: pointer; }
.bw-col-x:hover { opacity: 1; color: #b91c1c; }
#bw-formula-editor.bw-editor-excecao { border-color: #4f46e5; background: #eef2ff; box-shadow: 0 0 0 3px rgba(79,70,229,.18); }
#bw-formula-editor.bw-editor-excecao .bw-canvas { border-color: #4f46e5; border-style: solid; background: #fff; }
#bw-formula-editor.bw-editor-excecao #bw-formula-canvas-label { color: #312e81; }
.bw-excecao-pill { display: inline-flex; align-items: center; padding: 2px 10px; border-radius: 9999px; background: #fff; color: #3730a3; font-weight: 800; font-size: 15px; line-height: 1.4; }
</style>

<script>
(function () {
    var root = document.getElementById('boletim-assistente-root');
    if (!root) return;

    var disponivelIa = root.getAttribute('data-disponivel') === '1';
    var pageMode = root.getAttribute('data-page-mode') === '1';
    var returnUrl = root.getAttribute('data-return-url') || (URL_BASE + '/admin/boletim-configuracao?novo=1');
    var urlMensagem = root.getAttribute('data-url-mensagem');
    var urlFerramenta = root.getAttribute('data-url-ferramenta');
    var urlWizardInicio = root.getAttribute('data-url-wizard-inicio');
    var urlWizardMontar = root.getAttribute('data-url-wizard-montar');
    var urlSalvar = root.getAttribute('data-url-salvar');
    var csrf = root.getAttribute('data-csrf');
    var regraId = parseInt(root.getAttribute('data-regra-id') || '0', 10) || 0;

    var overlay = document.getElementById('boletim-wizard-overlay');
    var toggle = document.getElementById('boletim-assistente-toggle');
    var btnFechar = document.getElementById('boletim-wizard-fechar');
    var btnVoltar = document.getElementById('boletim-wizard-voltar');
    var btnAvancar = document.getElementById('boletim-wizard-avancar');
    var btnAplicar = document.getElementById('boletim-wizard-aplicar');
    var btnCopiar = document.getElementById('boletim-wizard-copiar-receita');
    var stepsEl = document.getElementById('boletim-wizard-steps');
    var bodyEl = document.getElementById('boletim-wizard-body');
    var resumoEl = document.getElementById('boletim-wizard-resumo');
    var errosEl = document.getElementById('boletim-wizard-erros');
    var msgsEl = null;
    var chatForm = null;
    var chatInput = null;
    var chatEnviar = null;
    var chatStatusEl = null;

    var PASSOS = [
        { id: 'inicio', label: '1. Começar', sub: 'Origem' },
        { id: 'identidade', label: '2. Identidade', sub: 'Evento' },
        { id: 'pecas', label: '3. Peças', sub: 'Blocos' },
        { id: 'formula', label: '4. Exibir', sub: 'Colunas' },
        { id: 'publico', label: '5. Matérias', sub: 'Escopo' },
        { id: 'revisar', label: '6. Revisar', sub: 'Confirmar' }
    ];

    var dadosIniciaisEl = document.getElementById('boletim-assistente-dados-iniciais');
    var dadosIniciais = {};
    if (dadosIniciaisEl && dadosIniciaisEl.textContent) {
        try { dadosIniciais = JSON.parse(dadosIniciaisEl.textContent) || {}; } catch (e) { dadosIniciais = {}; }
    }
    var catalogo = dadosIniciais.catalogo || { modelos: [], formulas: [], regras: [], series: [], pecas: [], papeis: [] };
    var estado = dadosIniciais.estado || null;
    var rascunhoAtual = dadosIniciais.rascunho || ((estado && estado.rascunho_preservado) ? estado.rascunho_preservado : null);
    var formulasDisp = dadosIniciais.formulas_disponiveis || [];
    var historico = [];
    var enviando = false;
    var iniciado = false;
    var montarTimer = null;
    var filaChat = [];
    var statusFilaEl = null;
    var formulaDrag = null;
    var previewAtual = dadosIniciais.preview || null;
    var colunasDrag = null;
    var colunasDragou = false;

    function coletarEstadoFormulario() {
        if (!document.getElementById('form-regra-boletim')) {
            return null;
        }
        var estadoForm = {
            nome: (document.getElementById('regra-nome') || {}).value || '',
            codigo: (document.getElementById('regra-codigo') || {}).value || '',
            descricao_curta: (document.getElementById('regra-descricao-curta') || {}).value || '',
            formula_final: '',
            exibir_em: '',
            ano_letivo: '',
            bimestre: '',
            round_mode: '',
            nota_minima_aprovacao: '',
            turmas_ids: [],
            materias_ids: [],
            series_ids: [],
            componentes: []
        };
        var formulaEl = document.querySelector('[name="formula_final"]');
        if (formulaEl) estadoForm.formula_final = formulaEl.value || '';
        var exibirEl = document.querySelector('[name="exibir_em"]');
        if (exibirEl) estadoForm.exibir_em = exibirEl.value || '';
        var anoEl = document.querySelector('[name="ano_letivo"]') || document.getElementById('regra-ano-letivo');
        if (anoEl) estadoForm.ano_letivo = anoEl.value || '';
        var bimEl = document.querySelector('[name="bimestre"]') || document.getElementById('regra-bimestre');
        if (bimEl) estadoForm.bimestre = bimEl.value || '';
        var roundEl = document.querySelector('[name="round_mode"]');
        if (roundEl) estadoForm.round_mode = roundEl.value || '';
        var notaEl = document.querySelector('[name="nota_minima_aprovacao"]');
        if (notaEl) estadoForm.nota_minima_aprovacao = notaEl.value || '';
        document.querySelectorAll('input[name="turmas_ids[]"]:checked').forEach(function (el) {
            estadoForm.turmas_ids.push(parseInt(el.value, 10));
        });
        document.querySelectorAll('input[name="materias_ids[]"]:checked').forEach(function (el) {
            estadoForm.materias_ids.push(parseInt(el.value, 10));
        });
        document.querySelectorAll('input[name="series_ids[]"]:checked').forEach(function (el) {
            estadoForm.series_ids.push(parseInt(el.value, 10));
        });
        var jsonEl = document.getElementById('componentes-json');
        if (jsonEl && jsonEl.value) {
            try { estadoForm.componentes = JSON.parse(jsonEl.value); } catch (e) {}
        }
        return estadoForm;
    }

    function estadoFormularioParaPost() {
        var form = coletarEstadoFormulario();
        return form ? JSON.stringify(enxugarRascunhoParaChat(form)) : '';
    }

    function enxugarRascunhoParaChat(r) {
        if (!r || typeof r !== 'object') return r;
        var out = {};
        Object.keys(r).forEach(function (k) { out[k] = r[k]; });
        if (Array.isArray(out.componentes)) {
            out.componentes = out.componentes.map(function (c) {
                if (!c || typeof c !== 'object') return c;
                var cc = {};
                Object.keys(c).forEach(function (k) { cc[k] = c[k]; });
                cc.blocos_ids = [];
                return cc;
            });
        }
        return out;
    }

    function wizardEstadoParaPost() {
        if (!estado) return '';
        var copy = {};
        Object.keys(estado).forEach(function (k) {
            if (k === 'rascunho_preservado') return;
            copy[k] = estado[k];
        });
        return JSON.stringify(copy);
    }

    function mostrarStatusFila(texto) {
        if (!msgsEl) return;
        if (statusFilaEl && statusFilaEl.parentNode) {
            statusFilaEl.textContent = texto;
            return;
        }
        statusFilaEl = document.createElement('div');
        statusFilaEl.className = 'mr-2 text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2';
        statusFilaEl.textContent = texto;
        msgsEl.appendChild(statusFilaEl);
        msgsEl.scrollTop = msgsEl.scrollHeight;
    }

    function removerStatusFila() {
        if (statusFilaEl && statusFilaEl.parentNode) {
            statusFilaEl.parentNode.removeChild(statusFilaEl);
        }
        statusFilaEl = null;
    }

    function setChatStatus(texto) {
        if (!chatStatusEl) return;
        if (!texto) {
            chatStatusEl.classList.add('hidden');
            chatStatusEl.textContent = '';
            return;
        }
        chatStatusEl.classList.remove('hidden');
        chatStatusEl.textContent = texto;
    }

    function grupoLinhaPadrao() {
        return { ativo: false, nome: '', modo: 'media', materias_ids: [] };
    }

    function turmasVisiveis() {
        var seriesSel = (estado.series_ids || []).map(Number);
        var ano = Number(estado.ano_letivo || 0);
        return (catalogo.turmas || []).filter(function (t) {
            if (seriesSel.length && t.serie_id && seriesSel.indexOf(Number(t.serie_id)) < 0) return false;
            if (ano && t.ano_letivo && Number(t.ano_letivo) !== ano) return false;
            return true;
        });
    }

    function htmlSeriesTurmas() {
        var html = '';
        html += '<div class="mt-5 pt-4 border-t border-gray-100">';
        html += '<p class="text-sm font-medium text-gray-800">Para quem é este evento?</p>';
        html += '<p class="text-xs text-gray-500 mt-0.5 mb-3">Escolha série e turma agora. Vazio = todas.</p>';
        html += '<p class="text-sm text-gray-700">Séries (opcional)</p>';
        html += '<div class="grid sm:grid-cols-2 gap-2 mt-2 max-h-36 overflow-y-auto border rounded-lg p-3 bg-white">';
        (catalogo.series || []).forEach(function (s) {
            var on = (estado.series_ids || []).some(function (id) { return Number(id) === Number(s.id); });
            var label = s.nome + (s.curso_nome ? ' · ' + s.curso_nome : '');
            html += '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="bw-serie rounded border-gray-300 text-indigo-600" value="' + s.id + '"' + (on ? ' checked' : '') + '> ' + esc(label) + '</label>';
        });
        if (!(catalogo.series || []).length) {
            html += '<p class="text-xs text-gray-500">Nenhuma série cadastrada.</p>';
        }
        html += '</div>';
        html += '<p class="text-sm text-gray-700 mt-4">Turmas (opcional)</p>';
        html += '<p class="text-xs text-gray-500 mb-2">Se marcar turmas, elas têm prioridade sobre séries. Vazio = todas (ou as das séries).</p>';
        html += '<div class="grid sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto border rounded-lg p-3 bg-white">';
        var visiveis = turmasVisiveis();
        visiveis.forEach(function (t) {
            var on = (estado.turmas_ids || []).some(function (id) { return Number(id) === Number(t.id); });
            var lab = t.nome + (t.serie_nome ? ' · ' + t.serie_nome : '');
            html += '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="bw-turma rounded border-gray-300 text-indigo-600" value="' + t.id + '"' + (on ? ' checked' : '') + '> ' + esc(lab) + '</label>';
        });
        if (!visiveis.length) {
            html += '<p class="text-xs text-gray-500">Nenhuma turma para o ano/série escolhidos.</p>';
        }
        html += '</div></div>';
        return html;
    }

    function aplicarMateriaUnicaNasPecas() {
        if (!estado) return;
        if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
        var flag = estado.materia_unica ? 1 : 0;
        (estado.pecas || []).forEach(function (k) {
            if (!estado.pecas_opcoes[k]) {
                estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
            } else {
                estado.pecas_opcoes[k].materia_unica = flag;
                if (!estado.pecas_opcoes[k].papel) estado.pecas_opcoes[k].papel = papelPadrao(k);
            }
        });
    }

    function pecaMeta(key) {
        var found = null;
        (catalogo.pecas || []).forEach(function (p) { if (p.key === key) found = p; });
        return found;
    }

    function papelPadrao(key) {
        return key === 'recuperacao' ? 'substitui' : 'media';
    }

    function calcTypePadrao(key) {
        if (key === 'bimestral' || key === 'enac' || key === 'trabalho' || key === 'participacao' || key === 'recuperacao') {
            return 'ultima';
        }
        return 'media';
    }

    function usarPercentualPadrao(key) {
        return key === 'semanal' ? 1 : 0;
    }

    function pecaMostraNotaEvento(key) {
        return key !== 'semanal' && key !== 'jornada';
    }

    function pecaPermiteAcertos(key) {
        return key === 'semanal' || key === 'bimestral' || key === 'enac' || String(key).indexOf('tipo_') === 0;
    }

    function pecasOpcoesPadraoJs(key) {
        var meta = pecaMeta(key);
        return {
            calc_type: calcTypePadrao(key),
            materia_unica: estado && estado.materia_unica ? 1 : 0,
            usar_percentual: usarPercentualPadrao(key),
            tipo_avaliacao_id: meta && meta.tipo_avaliacao_id ? Number(meta.tipo_avaliacao_id) : 0,
            papel: papelPadrao(key),
            bimestres: bimestresPadraoPeca()
        };
    }

    function bimestresPadraoPeca() {
        var b = Number((estado && estado.bimestre) || 0);
        return b >= 1 && b <= 4 ? [b] : [];
    }

    function bimestresUiPeca() {
        return [1, 2, 3, 4];
    }

    function htmlBimestresPeca(key, opts) {
        var bimsPeca = normalizarBimestresPeca(opts && opts.bimestres);
        var html = '<div><span class="text-xs font-medium text-gray-600">Bimestre dos eventos</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">Marque o(s) bimestre(s). Nenhum = usa o bimestre escolhido na Identidade.</p>';
        html += '<div class="grid grid-cols-3 gap-2 mt-2">';
        bimestresUiPeca().forEach(function (b) {
            var n = eventosDaPecaNoBimestre(key, b).length;
            var on = bimsPeca.some(function (x) { return Number(x) === b; });
            html += '<label class="inline-flex items-center gap-2 text-sm border rounded-lg px-3 py-2 bg-slate-50 cursor-pointer">';
            html += '<input type="checkbox" class="bw-peca-bim rounded border-gray-300 text-indigo-600" data-peca="' + key + '" value="' + b + '"' + (on ? ' checked' : '') + '>';
            html += '<span>' + b + 'º bim. <span class="text-xs text-gray-500">(' + n + ')</span></span></label>';
        });
        html += '</div></div>';
        return html;
    }

    function htmlJornadaPeca(opts) {
        var bims = normalizarBimestresPeca(opts && opts.bimestres);
        if (!bims.length && estado.bimestre >= 1 && estado.bimestre <= 4) bims = [Number(estado.bimestre)];
        estado.jornada_modo = 'bimestre';
        estado.jornada_bimestres = bims.slice();
        var total = 0;
        bims.forEach(function (b) { total += jornadasDoBimestre(b).length; });
        var rotulo = bims.length ? bims.map(function (b) { return b + 'º'; }).join(', ') : 'bimestre da regra';
        var html = '<div class="rounded-lg border border-slate-200 bg-slate-50 p-3 space-y-3">';
        html += '<div><span class="text-xs font-medium text-gray-600">Jornadas vinculadas</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">Serão usadas as jornadas do ' + esc(rotulo) + ' bimestre, conforme o bimestre da peça. Encontradas: ' + total + '.</p></div>';
        html += '<div><span class="text-xs font-medium text-gray-600">Pontuação por conclusão</span>';
        html += '<p class="text-xs text-gray-500 mt-0.5">A nota depende de quantas jornadas do bimestre o aluno concluiu.</p>';
        html += '<div class="mt-2 flex flex-wrap gap-3 text-sm">';
        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-jornada-nota" class="bw-jornada-nota-modo" value="linear"' + ((estado.jornada_nota_modo || 'linear') === 'linear' ? ' checked' : '') + '> Linear (100% = 10)</label>';
        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-jornada-nota" class="bw-jornada-nota-modo" value="faixas"' + (estado.jornada_nota_modo === 'faixas' ? ' checked' : '') + '> Tabela por faixas</label>';
        html += '</div></div>';
        if (estado.jornada_nota_modo === 'faixas') {
            if (!estado.jornada_faixas || !estado.jornada_faixas.length) estado.jornada_faixas = faixasJornadaPadrao();
            html += '<div class="grid grid-cols-1 sm:grid-cols-2 gap-2">';
            estado.jornada_faixas.forEach(function (f, idx) {
                html += '<label class="flex items-center gap-2 text-sm bg-white border rounded-lg px-2 py-1.5">';
                html += '<span class="text-xs font-medium text-amber-900 min-w-[3.5rem]">' + f.percentual_min + '%</span>';
                html += '<input type="number" min="0" step="0.01" class="bw-jornada-faixa-nota flex-1 h-9 border rounded-md px-2 text-sm" data-idx="' + idx + '" value="' + (f.nota != null ? f.nota : '') + '">';
                html += '</label>';
            });
            html += '</div>';
        }
        html += '</div>';
        return html;
    }

    function tipoNomeFixoPeca(key) {
        var meta = pecaMeta(key);
        var opts = (estado.pecas_opcoes && estado.pecas_opcoes[key]) || {};
        var tipoId = Number(opts.tipo_avaliacao_id || (meta && meta.tipo_avaliacao_id) || 0);
        var nome = '';
        (catalogo.tipos_avaliacao || []).forEach(function (t) {
            if (Number(t.id) === tipoId) nome = t.nome || '';
        });
        if (nome) return nome;
        return (meta && meta.label) ? meta.label : String(key || '');
    }

    function normalizarBimestresPeca(raw) {
        var out = [];
        (Array.isArray(raw) ? raw : []).forEach(function (x) {
            var n = parseInt(x, 10);
            if (n >= 1 && n <= 4 && out.indexOf(n) < 0) out.push(n);
        });
        return out;
    }

    function eventosDaPecaNoBimestre(key, bim) {
        var opts = (estado.pecas_opcoes && estado.pecas_opcoes[key]) || {};
        var tipoId = Number(opts.tipo_avaliacao_id || 0);
        var meta = pecaMeta(key);
        if (!tipoId && meta) tipoId = Number(meta.tipo_avaliacao_id || 0);
        var chave = meta && meta.chave_quadro ? String(meta.chave_quadro).toLowerCase() : String(key || '');
        var ano = Number((estado && estado.ano_letivo) || 0);
        return (catalogo.eventos_prova || []).filter(function (ev) {
            if (Number(ev.bimestre) !== Number(bim)) return false;
            var ea = Number(ev.ano_letivo || 0);
            if (ano > 0 && ea > 0 && ea !== ano) return false;
            if (tipoId > 0) return Number(ev.tipo_avaliacao_id) === tipoId;
            var ch = String(ev.chave_quadro || '').toLowerCase();
            if (ch) {
                if (ch === chave) return true;
                if (chave === 'bimestral' && (ch === 'prova_bim' || ch === 'bimestral')) return true;
            }
            var nome = String(ev.tipo_avaliacao_nome || ev.titulo || '').toLowerCase();
            return chave !== '' && nome.indexOf(chave) >= 0;
        });
    }

    function papeisCatalogo() {
        if (catalogo.papeis && catalogo.papeis.length) return catalogo.papeis;
        return [
            { key: 'media', label: 'Entra na média', hint: 'Compõe a nota base com as outras peças neste papel.' },
            { key: 'depois', label: 'Entra depois (média final)', hint: 'Entra numa segunda média, depois da média parcial.' },
            { key: 'so_melhora', label: 'Só melhora', hint: 'Se for menor que a média, a média permanece.' },
            { key: 'substitui', label: 'Substitui se for maior', hint: 'Usa esta nota quando ela for maior que a média (recuperação).' },
            { key: 'exibe', label: 'Só mostra', hint: 'Aparece no boletim, mas não entra no cálculo da média.' }
        ];
    }

    function chavePecaDeComponenteJs(c) {
        if (!c || c.source_type === 'calculado') return null;
        var src = c.source_type || '';
        var cod = String(c.codigo || '').toLowerCase();
        var nome = String(c.nome || '').toLowerCase();
        var blob = cod + ' ' + nome + ' ' + String(c.filtro_titulo || '').toLowerCase();
        var cfg = c.config && typeof c.config === 'object' ? c.config : {};
        var layout = String(cfg.layout_type || '').toLowerCase();
        if (src === 'jornadas' || blob.indexOf('jornada') >= 0) return 'jornada';
        var chaveQuadro = String(cfg.chave_quadro || c.chave_quadro || '').toLowerCase().trim();
        if (chaveQuadro === 'semanal') return 'semanal';
        if (chaveQuadro === 'prova_bim' || chaveQuadro === 'bimestral') return 'bimestral';
        if (chaveQuadro === 'enac') return 'enac';
        if (chaveQuadro === 'trabalho' || chaveQuadro === 'trab') return 'trabalho';
        if (chaveQuadro === 'participacao' || chaveQuadro === 'participa' || chaveQuadro === 'part') return 'participacao';
        if (chaveQuadro === 'recuperacao' || chaveQuadro === 'recupera' || chaveQuadro === 'rec') return 'recuperacao';
        if (cod === 'enac' || blob.indexOf('enac') >= 0) return 'enac';
        if (/^s[1-8]$/.test(cod) || blob.indexOf('semanal') >= 0 || layout === 'semana_nq') return 'semanal';
        if (cod === 'prova_bim' || blob.indexOf('bimestral') >= 0 || blob.indexOf('bimestr') >= 0) return 'bimestral';
        if (cod === 'trab' || blob.indexOf('trabalho') >= 0) return 'trabalho';
        if (cod === 'part' || blob.indexOf('participa') >= 0) return 'participacao';
        if (cod === 'rec' || blob.indexOf('recupera') >= 0) return 'recuperacao';
        var tipoId = Number(cfg.tipo_avaliacao_id || c.tipo_avaliacao_id || 0);
        if (tipoId > 0) return 'tipo_' + tipoId;
        return null;
    }

    function inferirPecasDoRascunho(r) {
        var pecas = [];
        var opcoes = {};
        (r && Array.isArray(r.componentes) ? r.componentes : []).forEach(function (c) {
            var key = chavePecaDeComponenteJs(c);
            if (!key) return;
            if (pecas.indexOf(key) < 0) pecas.push(key);
            if (!opcoes[key]) opcoes[key] = pecasOpcoesPadraoJs(key);
            opcoes[key].calc_type = c.calc_type || opcoes[key].calc_type;
            opcoes[key].materia_unica = c.materia_unica ? 1 : 0;
            opcoes[key].usar_percentual = c.usar_percentual ? 1 : 0;
            var cfg = c.config && typeof c.config === 'object' ? c.config : {};
            if (cfg.tipo_avaliacao_id) opcoes[key].tipo_avaliacao_id = Number(cfg.tipo_avaliacao_id);
            if (cfg.papel_wizard) opcoes[key].papel = cfg.papel_wizard;
            if (cfg.prova_bimestres) opcoes[key].bimestres = normalizarBimestresPeca(cfg.prova_bimestres);
        });
        return { pecas: pecas, pecas_opcoes: opcoes };
    }

    function aplicarPresetNosPapeis(preset) {
        if (!estado || !estado.pecas_opcoes) return;
        var pecas = estado.pecas || [];
        var alvoDepois = pecas.filter(function (k) {
            var p = (estado.pecas_opcoes[k] || {}).papel;
            return p === 'depois' || p === 'so_melhora';
        });
        if (!alvoDepois.length && pecas.indexOf('enac') >= 0) alvoDepois = ['enac'];
        pecas.forEach(function (k) {
            if (!estado.pecas_opcoes[k]) estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
            var atual = estado.pecas_opcoes[k].papel;
            if (atual === 'substitui' || atual === 'exibe') return;
            if (preset === 'media_simples') {
                if (atual === 'depois' || atual === 'so_melhora') estado.pecas_opcoes[k].papel = 'media';
            } else if (preset === 'parcial_depois_final') {
                estado.pecas_opcoes[k].papel = alvoDepois.indexOf(k) >= 0 ? 'depois' : 'media';
            } else if (preset === 'enac_so_melhora') {
                estado.pecas_opcoes[k].papel = alvoDepois.indexOf(k) >= 0 ? 'so_melhora' : 'media';
            }
        });
    }

    function syncPresetDosPapeis() {
        if (!estado) return;
        var papeis = (estado.pecas || []).map(function (k) {
            return ((estado.pecas_opcoes && estado.pecas_opcoes[k]) || {}).papel || 'media';
        });
        var temDepois = papeis.indexOf('depois') >= 0;
        var temMelhora = papeis.indexOf('so_melhora') >= 0;
        if (temMelhora && !temDepois) estado.formula_preset = 'enac_so_melhora';
        else if (temDepois) estado.formula_preset = 'parcial_depois_final';
        else estado.formula_preset = 'media_simples';
    }

    function marcarEdicaoManual() {
        if (!estado) return;
        if (estado.origem === 'formulario' || estado.origem === 'chat' || estado.origem === 'modelo' || estado.origem === 'clonar') {
            estado.origem = 'zero';
            estado.rascunho_preservado = null;
        }
    }

    function materiaUnicaDoRascunho(r) {
        return (r && Array.isArray(r.componentes) ? r.componentes : []).some(function (c) {
            return c && c.source_type !== 'calculado' && !!c.materia_unica;
        }) ? 1 : 0;
    }

    function faixasJornadaPadrao() {
        return [
            { percentual_min: 90, nota: 10 },
            { percentual_min: 80, nota: 9 },
            { percentual_min: 70, nota: 8 },
            { percentual_min: 60, nota: 7 },
            { percentual_min: 50, nota: 6 },
            { percentual_min: 40, nota: 5 },
            { percentual_min: 30, nota: 3.75 },
            { percentual_min: 20, nota: 2.5 },
            { percentual_min: 10, nota: 1.25 }
        ];
    }

    function jornadasDoBimestre(bim) {
        var ano = Number((estado && estado.ano_letivo) || 0);
        return (catalogo.jornadas || []).filter(function (j) {
            if (Number(j.bimestre) !== Number(bim)) return false;
            var ja = Number(j.ano_letivo || 0);
            if (ano > 0 && ja > 0 && ja !== ano) return false;
            return true;
        });
    }

    function estadoVazio() {
        var ano = (new Date()).getFullYear();
        return {
            passo: 'inicio',
            origem: 'zero',
            modelo_key: '',
            clonar_regra_id: null,
            modo: 'criar',
            regra_id: regraId > 0 ? regraId : null,
            nome: '',
            codigo: '',
            ano_letivo: ano,
            bimestre: 1,
            exibir_em: 'notas',
            nota_minima_aprovacao: 7,
            round_mode: 'half',
            pecas: [],
            pecas_opcoes: {},
            materia_unica: 0,
            materia_unica_tocada: false,
            formula_preset: 'media_simples',
            formula_custom: '',
            formula_tokens: [],
            formulas_blocos: {},
            formulas_materias_blocos: {},
            nomes_blocos: {},
            bloco_calc: '',
            materia_calc: 0,
            blocos_calc: [],
            colunas_ordem: [],
            fontes_bimestres: { 1: 0, 2: 0, 3: 0, 4: 0 },
            fontes_faltas: { 1: 0, 2: 0, 3: 0, 4: 0 },
            data_inicio: '',
            data_fim: '',
            jornada_modo: 'bimestre',
            jornada_ids: [],
            jornada_bimestres: [],
            jornada_nota_modo: 'linear',
            jornada_faixas: faixasJornadaPadrao(),
            series_ids: [],
            turmas_ids: [],
            materias_ids: [],
            aluno_preview_id: 0,
            grupo_linha: grupoLinhaPadrao(),
            rascunho_preservado: null
        };
    }

    function garantirEstado() {
        if (!estado) estado = estadoVazio();
        if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
        if (estado.materia_unica == null) estado.materia_unica = 0;
        if (estado.materia_unica_tocada == null) estado.materia_unica_tocada = false;
        if (!estado.jornada_nota_modo) estado.jornada_nota_modo = 'linear';
        if ((estado.pecas || []).indexOf('jornada') >= 0 && estado.jornada_modo === 'todas') estado.jornada_modo = 'bimestre';
        if (!Array.isArray(estado.formula_tokens)) estado.formula_tokens = [];
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        if (!estado.formulas_materias_blocos || typeof estado.formulas_materias_blocos !== 'object' || Array.isArray(estado.formulas_materias_blocos)) {
            estado.formulas_materias_blocos = {};
        } else {
            Object.keys(estado.formulas_materias_blocos).forEach(function (cod) {
                mapaExcecoesBloco(cod, false);
            });
        }
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
        if (estado.bloco_calc == null) estado.bloco_calc = '';
        if (estado.materia_calc == null) estado.materia_calc = 0;
        estado.aluno_preview_id = Number(estado.aluno_preview_id || 0) || 0;
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        if (!estado.fontes_bimestres || typeof estado.fontes_bimestres !== 'object') {
            estado.fontes_bimestres = { 1: 0, 2: 0, 3: 0, 4: 0 };
        } else {
            [1, 2, 3, 4].forEach(function (b) {
                estado.fontes_bimestres[b] = Number(estado.fontes_bimestres[b] || estado.fontes_bimestres[String(b)] || 0) || 0;
            });
        }
        if (!estado.fontes_faltas || typeof estado.fontes_faltas !== 'object') {
            estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
        } else {
            [1, 2, 3, 4].forEach(function (b) {
                estado.fontes_faltas[b] = Number(estado.fontes_faltas[b] || estado.fontes_faltas[String(b)] || 0) || 0;
            });
        }
        if (estado.formula_custom == null) estado.formula_custom = '';
        if (!estado.pecas_opcoes || typeof estado.pecas_opcoes !== 'object') estado.pecas_opcoes = {};
        (estado.pecas || []).forEach(function (k) {
            if (!estado.pecas_opcoes[k]) estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
            if (!Array.isArray(estado.pecas_opcoes[k].bimestres)) {
                estado.pecas_opcoes[k].bimestres = [];
            } else {
                estado.pecas_opcoes[k].bimestres = normalizarBimestresPeca(estado.pecas_opcoes[k].bimestres);
            }
        });
        return estado;
    }

    function pecaLabel(key) {
        var nomes = {
            media_sem: 'Média Sem',
            media_bim: 'Média Bim',
            media_final: 'Média Bim Final',
            prova_bim: 'Prova Bim',
            trab: 'Trab',
            part: 'Part',
            rec: 'Rec'
        };
        if (nomes[key]) return nomes[key];
        var m = pecaMeta(key);
        return (m && m.label) ? m.label : String(key || '');
    }

    function labelToken(tok) {
        if (!tok) return '';
        if (tok.type === 'fn') return tok.value === 'min' ? 'menor (' : 'maior (';
        if (tok.type === 'op' && tok.value === '*') return '×';
        if (tok.type === 'op' && tok.value === '/') return '÷';
        if (tok.type === 'op' && tok.value === '-') return '−';
        if (tok.type === 'peca') return tok.label || pecaLabel(tok.value);
        return tok.label || tok.value;
    }

    function operadoresFormula() {
        return [
            { type: 'op', value: '+', label: '+' },
            { type: 'op', value: '-', label: '−' },
            { type: 'op', value: '*', label: '×' },
            { type: 'op', value: '/', label: '÷' },
            { type: 'op', value: '(', label: '(' },
            { type: 'op', value: ')', label: ')' },
            { type: 'op', value: ',', label: ',' },
            { type: 'fn', value: 'max', label: 'maior (' },
            { type: 'fn', value: 'min', label: 'menor (' }
        ];
    }

    function filtrarTokensOrfaos() {
        if (!estado) return;
        var pecas = estado.pecas || [];
        var livres = ['media_sem', 'media_bim', 'media_final', 'prova_bim'].concat(estado.blocos_calc || []);
        Object.keys(estado.formulas_blocos || {}).forEach(function (k) { livres.push(k); });
        Object.keys(estado.nomes_blocos || {}).forEach(function (k) { livres.push(k); });
        function pecaTokenOk(v) {
            return pecas.indexOf(v) >= 0 || livres.indexOf(v) >= 0;
        }
        function filtrarLista(toks) {
            var out = (toks || []).filter(function (t) {
                if (!t || t.type !== 'peca') return true;
                return pecaTokenOk(t.value);
            });
            var temPeca = out.some(function (t) { return t && t.type === 'peca'; });
            return temPeca ? out : [];
        }
        if (Array.isArray(estado.formula_tokens)) {
            estado.formula_tokens = filtrarLista(estado.formula_tokens);
        }
        if (estado.formulas_blocos && typeof estado.formulas_blocos === 'object') {
            Object.keys(estado.formulas_blocos).forEach(function (cod) {
                estado.formulas_blocos[cod] = filtrarLista(estado.formulas_blocos[cod]);
            });
        }
        if (estado.formulas_materias_blocos && typeof estado.formulas_materias_blocos === 'object') {
            Object.keys(estado.formulas_materias_blocos).forEach(function (cod) {
                var por = estado.formulas_materias_blocos[cod];
                if (!por || typeof por !== 'object') return;
                Object.keys(por).forEach(function (mid) {
                    por[mid] = filtrarLista(por[mid]);
                });
            });
        }
        estado.formula_custom = compilarTokensPreview(estado.formula_tokens || []);
    }

    function compilarTokensPreview(tokens) {
        var parts = [];
        (tokens || []).forEach(function (t) {
            if (!t) return;
            if (t.type === 'fn') parts.push((t.value === 'min' ? 'min' : 'max') + '(');
            else if (t.type === 'peca') parts.push(t.value);
            else parts.push(t.value);
        });
        var exp = parts.join(' ').replace(/\( /g, '(').replace(/ \)/g, ')').replace(/ ,/g, ',');
        return exp.replace(/\b(max|min)\(\s+/g, '$1(').trim();
    }

    function tokensMediaDasPecas() {
        var pecas = (estado && estado.pecas) || [];
        if (!pecas.length) return [];
        var toks = [{ type: 'op', value: '(', label: '(' }];
        pecas.forEach(function (k, i) {
            if (i > 0) toks.push({ type: 'op', value: '+', label: '+' });
            toks.push({ type: 'peca', value: k, label: pecaLabel(k) });
        });
        toks.push({ type: 'op', value: ')', label: ')' });
        if (pecas.length > 1) {
            toks.push({ type: 'op', value: '/', label: '÷' });
            toks.push({ type: 'num', value: String(pecas.length), label: String(pecas.length) });
        }
        return toks;
    }

    function tokensMaiorDuas() {
        var pecas = (estado && estado.pecas) || [];
        if (pecas.length < 2) return tokensMediaDasPecas();
        return [
            { type: 'fn', value: 'max', label: 'maior (' },
            { type: 'peca', value: pecas[0], label: pecaLabel(pecas[0]) },
            { type: 'op', value: ',', label: ',' },
            { type: 'peca', value: pecas[1], label: pecaLabel(pecas[1]) },
            { type: 'op', value: ')', label: ')' }
        ];
    }

    function chipHtml(tok, idx, paleta) {
        var kind = tok.type === 'fn' ? 'fn' : tok.type;
        var html = '<span class="bw-chip bw-chip-' + kind + (paleta ? ' bw-pal-chip' : '') + '" draggable="true"'
            + ' data-type="' + esc(tok.type) + '" data-value="' + esc(tok.value) + '" data-label="' + esc(labelToken(tok)) + '"';
        if (!paleta) html += ' data-idx="' + idx + '"';
        html += '><span>' + esc(labelToken(tok)) + '</span>';
        if (!paleta) html += '<button type="button" class="bw-chip-x" draggable="false" data-idx="' + idx + '" aria-label="Remover">&times;</button>';
        html += '</span>';
        return html;
    }

    function htmlCanvasTokens() {
        var toks = (estado && estado.formula_tokens) || [];
        if (!toks.length) {
            var midPh = Number((estado && estado.materia_calc) || 0);
            if (midPh > 0) {
                return '<p class="bw-canvas-ph">Fórmula só de ' + esc(materiaNome(midPh)) + '. Clique ou arraste as peças que esta matéria tem.</p>';
            }
            return '<p class="bw-canvas-ph">Clique ou arraste os blocos para cá. Ex.: bimestral + semanal ÷ 2 · maior ( bimestral , ENAC )</p>';
        }
        var html = '';
        toks.forEach(function (t, i) { html += chipHtml(t, i, false); });
        return html;
    }

    function renderFormulaCanvas() {
        var canvas = document.getElementById('bw-formula-canvas');
        if (canvas) canvas.innerHTML = htmlCanvasTokens();
        atualizarFormulaPreview();
    }

    function atualizarFormulaPreview() {
        if (!estado) return;
        estado.formula_custom = compilarTokensPreview(estado.formula_tokens || []);
        var el = document.getElementById('bw-formula-preview');
        if (!el) return;
        el.textContent = '';
        var midPrev = Number(estado.materia_calc || 0);
        var expr = estado.formula_custom || 'Monte os blocos acima.';
        if (midPrev > 0) {
            var prefix = document.createElement('span');
            prefix.className = 'font-sans font-semibold text-indigo-800 mr-1';
            prefix.textContent = materiaNome(midPrev) + ':';
            el.appendChild(prefix);
        }
        el.appendChild(document.createTextNode(expr));
    }

    function tokenFromEl(el) {
        if (!el) return null;
        var type = el.getAttribute('data-type');
        var value = el.getAttribute('data-value');
        if (!type || value == null || value === '') return null;
        return { type: type, value: value, label: el.getAttribute('data-label') || value };
    }

    function pushToken(tok, at) {
        if (!estado || !tok) return;
        if (!Array.isArray(estado.formula_tokens)) estado.formula_tokens = [];
        if (estado.formula_tokens.length >= 80) return;
        if (typeof at === 'number' && at >= 0 && at < estado.formula_tokens.length) {
            estado.formula_tokens.splice(at, 0, tok);
        } else {
            estado.formula_tokens.push(tok);
        }
        afterFormulaChange();
    }

    function removeToken(idx) {
        if (!estado || !Array.isArray(estado.formula_tokens)) return;
        if (idx < 0 || idx >= estado.formula_tokens.length) return;
        estado.formula_tokens.splice(idx, 1);
        afterFormulaChange();
    }

    function moveToken(from, to) {
        if (!estado || !Array.isArray(estado.formula_tokens)) return;
        var n = estado.formula_tokens.length;
        if (from < 0 || from >= n) return;
        if (to < 0) to = 0;
        if (to > n) to = n;
        if (from === to || from + 1 === to) return;
        var item = estado.formula_tokens.splice(from, 1)[0];
        if (to > from) to -= 1;
        estado.formula_tokens.splice(to, 0, item);
        afterFormulaChange();
    }

    function afterFormulaChange() {
        marcarEdicaoManual();
        if (estado.bloco_calc) {
            gravarTokensBlocoAberto();
        }
        renderFormulaCanvas();
        renderExcecoesMateria();
        agendarMontar();
    }

    function gravarTokensBlocoAberto() {
        if (!estado || !estado.bloco_calc) return;
        var toks = (estado.formula_tokens || []).slice();
        var mid = Number(estado.materia_calc || 0);
        if (mid > 0) {
            mapaExcecoesBloco(estado.bloco_calc, true)[mid] = toks;
            return;
        }
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        estado.formulas_blocos[estado.bloco_calc] = toks;
    }

    function materiaNome(id) {
        var n = Number(id || 0);
        var lista = (catalogo && catalogo.materias) || [];
        for (var i = 0; i < lista.length; i++) {
            if (Number(lista[i].id) === n) return lista[i].nome || ('Matéria #' + n);
        }
        return 'Matéria #' + n;
    }

    function nomeBlocoCalc(codigo) {
        if (estado && estado.nomes_blocos && estado.nomes_blocos[codigo]) return estado.nomes_blocos[codigo];
        if (codigo === 'media_bim') return 'Média Bim';
        if (codigo === 'media_final') return 'Média Bim Final';
        if (codigo === 'media_sem') return 'Média Sem';
        return pecaLabel(codigo);
    }

    function codigoBlocoReservado(cod) {
        var reserved = { _semanal: 1, media_sem: 1, prova_bim: 1, enac: 1, trab: 1, part: 1, rec: 1, jornada: 1 };
        (estado.pecas || []).forEach(function (k) { reserved[k] = 1; reserved[pecaCodigoQuadro(k)] = 1; });
        return !!reserved[cod];
    }

    function novoCodigoBloco() {
        if ((estado.blocos_calc || []).indexOf('media_bim') < 0 && !(estado.formulas_blocos || {}).media_bim) {
            return 'media_bim';
        }
        if ((estado.blocos_calc || []).indexOf('media_final') < 0 && !(estado.formulas_blocos || {}).media_final) {
            return 'media_final';
        }
        var n = 3;
        var c = 'media_' + n;
        while (codigoBlocoReservado(c) || (estado.formulas_blocos || {})[c] || (estado.blocos_calc || []).indexOf(c) >= 0) {
            n += 1;
            c = 'media_' + n;
        }
        return c;
    }

    function mapaExcecoesBloco(codigo, criar) {
        if (!estado.formulas_materias_blocos || typeof estado.formulas_materias_blocos !== 'object' || Array.isArray(estado.formulas_materias_blocos)) {
            estado.formulas_materias_blocos = {};
        }
        var por = estado.formulas_materias_blocos[codigo];
        if (Array.isArray(por)) {
            var obj = {};
            Object.keys(por).forEach(function (k) {
                var id = Number(k);
                if (id > 0) obj[id] = por[k];
            });
            por = obj;
            estado.formulas_materias_blocos[codigo] = por;
            return por;
        }
        if (por && typeof por === 'object') return por;
        if (!criar) return {};
        por = {};
        estado.formulas_materias_blocos[codigo] = por;
        return por;
    }

    function abrirFormulaBloco(codigo) {
        if (!codigo || codigo === 'media_sem' || codigo === '_semanal') return;
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        var mesmoBloco = estado.bloco_calc === codigo;
        estado.bloco_calc = codigo;
        if (!mesmoBloco) estado.materia_calc = 0;
        var mid = Number(estado.materia_calc || 0);
        var porEx = mapaExcecoesBloco(codigo, false);
        if (mid > 0 && Object.prototype.hasOwnProperty.call(porEx, String(mid))) {
            estado.formula_tokens = Array.isArray(porEx[mid]) ? porEx[mid].slice() : [];
        } else {
            estado.materia_calc = 0;
            estado.formula_tokens = Array.isArray(estado.formulas_blocos[codigo])
                ? estado.formulas_blocos[codigo].slice()
                : [];
        }
        var ed = document.getElementById('bw-formula-editor');
        if (ed) ed.classList.remove('hidden');
        var t = document.getElementById('bw-formula-bloco-nome');
        if (t) t.textContent = nomeBlocoCalc(codigo);
        var inp = document.getElementById('bw-formula-bloco-nome-input');
        if (inp) {
            inp.value = nomeBlocoCalc(codigo);
            if (Number(estado.materia_calc || 0) <= 0) {
                setTimeout(function () { inp.focus(); inp.select(); }, 30);
            }
        }
        renderFormulaCanvas();
        var lista = document.getElementById('bw-colunas-lista');
        if (lista) {
            lista.querySelectorAll('.bw-col-chip').forEach(function (el) {
                if (el.getAttribute('data-codigo') === codigo) el.classList.add('aberta');
                else el.classList.remove('aberta');
            });
        }
        var pal = document.getElementById('bw-formula-pecas-pal');
        if (pal) pal.innerHTML = htmlPalettePecasExibir();
        atualizarBannerExcecao();
        renderExcecoesMateria();
    }

    function atualizarBannerExcecao() {
        var banner = document.getElementById('bw-excecao-banner');
        var ed = document.getElementById('bw-formula-editor');
        var titulo = document.getElementById('bw-formula-bloco-titulo');
        var ajuda = document.getElementById('bw-formula-bloco-ajuda');
        var label = document.getElementById('bw-formula-canvas-label');
        var mid = Number((estado && estado.materia_calc) || 0);
        var nomeMat = mid > 0 ? materiaNome(mid) : '';
        var nomeBloco = nomeBlocoCalc((estado && estado.bloco_calc) || 'média');
        if (ed) {
            if (mid > 0) ed.classList.add('bw-editor-excecao');
            else ed.classList.remove('bw-editor-excecao');
        }
        if (titulo) {
            titulo.className = mid > 0
                ? 'text-base font-bold text-indigo-950'
                : 'text-sm font-semibold text-amber-950';
            titulo.innerHTML = mid > 0
                ? 'Cálculo de ' + esc(nomeBloco) + ' <span class="text-indigo-800">só em ' + esc(nomeMat) + '</span>'
                : 'Cálculo de <span id="bw-formula-bloco-nome">' + esc(nomeBloco) + '</span>';
        }
        if (ajuda) {
            ajuda.textContent = mid > 0
                ? 'Esta fórmula vale somente para ' + nomeMat + '. As outras matérias seguem a fórmula geral da coluna.'
                : 'Peças e sinais viram a fórmula desta coluna. Ex.: maior ( Média Bim , ENAC ).';
        }
        if (label) {
            label.textContent = mid > 0 ? 'Fórmula só de ' + nomeMat : 'Fórmula desta coluna';
        }
        if (!banner) return;
        if (mid > 0) {
            banner.classList.remove('hidden');
            banner.innerHTML = '<div class="flex flex-wrap items-center justify-between gap-2">'
                + '<div class="min-w-0">'
                + '<p class="text-[11px] font-semibold uppercase tracking-wide text-indigo-100">Você está montando a fórmula de uma matéria</p>'
                + '<p class="mt-1 text-lg font-bold leading-tight">Só <span class="bw-excecao-pill">' + esc(nomeMat) + '</span></p>'
                + '<p class="text-xs text-indigo-100 mt-1">As outras matérias continuam com a fórmula geral de ' + esc(nomeBloco) + '.</p>'
                + '</div>'
                + '<button type="button" id="bw-excecao-voltar" class="h-9 px-3 text-xs font-semibold rounded-lg bg-white text-indigo-800 hover:bg-indigo-50">Voltar à fórmula geral</button>'
                + '</div>';
        } else {
            banner.classList.add('hidden');
            banner.innerHTML = '';
        }
        atualizarFormulaPreview();
    }

    function renderExcecoesMateria() {
        var wrap = document.getElementById('bw-excecoes-lista');
        if (!wrap || !estado || !estado.bloco_calc) return;
        var por = mapaExcecoesBloco(estado.bloco_calc, false);
        var mids = Object.keys(por).map(Number).filter(function (id) { return id > 0 && (por[id] || []).length; });
        var html = '';
        if (mids.length) {
            mids.forEach(function (mid) {
                var aberta = Number(estado.materia_calc || 0) === mid;
                html += '<div class="flex flex-wrap items-center justify-between gap-2 text-xs bg-white border border-indigo-100 rounded-lg px-2 py-1.5' + (aberta ? ' ring-2 ring-indigo-500 bg-indigo-50' : '') + '">';
                html += '<span class="font-medium text-indigo-950">' + esc(materiaNome(mid)) + (aberta ? ' <span class="text-[10px] uppercase tracking-wide text-indigo-600">editando agora</span>' : '') + '</span>';
                html += '<span class="font-mono text-slate-600 truncate max-w-[14rem]">' + esc(compilarTokensPreview(por[mid]) || '—') + '</span>';
                html += '<span class="flex gap-1"><button type="button" class="bw-excecao-editar px-2 py-0.5 rounded border border-indigo-200 text-indigo-700" data-mid="' + mid + '">Editar</button>';
                html += '<button type="button" class="bw-excecao-remover px-2 py-0.5 rounded border border-gray-200 text-gray-600" data-mid="' + mid + '">Remover</button></span></div>';
            });
        } else {
            html += '<p class="text-xs text-gray-500">Nenhuma exceção. Use quando uma matéria não tem um tipo de prova (ex.: sem semanal → só bimestral + ENAC).</p>';
        }
        wrap.innerHTML = html;
        var sel = document.getElementById('bw-excecao-materia');
        if (sel) {
            var ja = {};
            mids.forEach(function (id) { ja[id] = true; });
            var opts = '<option value="0">Escolher matéria…</option>';
            ((catalogo && catalogo.materias) || []).forEach(function (m) {
                var id = Number(m.id);
                if (!id || ja[id]) return;
                opts += '<option value="' + id + '">' + esc(m.nome) + '</option>';
            });
            sel.innerHTML = opts;
        }
    }

    function abrirExcecaoMateria(mid) {
        mid = Number(mid || 0);
        if (!estado || !estado.bloco_calc || mid <= 0) return;
        gravarTokensBlocoAberto();
        estado.materia_calc = mid;
        var por = mapaExcecoesBloco(estado.bloco_calc, true);
        var atuais = por[mid];
        if (!Array.isArray(atuais) || !atuais.length) {
            atuais = Array.isArray(estado.formulas_blocos[estado.bloco_calc])
                ? estado.formulas_blocos[estado.bloco_calc].slice()
                : [];
            por[mid] = atuais;
        }
        estado.formula_tokens = atuais.slice();
        renderFormulaCanvas();
        atualizarBannerExcecao();
        renderExcecoesMateria();
        var pal = document.getElementById('bw-formula-pecas-pal');
        if (pal) pal.innerHTML = htmlPalettePecasExibir();
        marcarEdicaoManual();
        agendarMontar();
    }

    function voltarFormulaGeral() {
        if (!estado || !estado.bloco_calc) return;
        gravarTokensBlocoAberto();
        estado.materia_calc = 0;
        estado.formula_tokens = Array.isArray(estado.formulas_blocos[estado.bloco_calc])
            ? estado.formulas_blocos[estado.bloco_calc].slice()
            : [];
        renderFormulaCanvas();
        atualizarBannerExcecao();
        renderExcecoesMateria();
    }

    function removerExcecaoMateria(mid) {
        mid = Number(mid || 0);
        if (!estado || !estado.bloco_calc || mid <= 0) return;
        var por = mapaExcecoesBloco(estado.bloco_calc, true);
        delete por[mid];
        if (Number(estado.materia_calc || 0) === mid) voltarFormulaGeral();
        else renderExcecoesMateria();
        marcarEdicaoManual();
        agendarMontar();
    }

    function htmlPalettePecasExibir() {
        var html = '';
        var visto = {};
        if (estado.bloco_calc) visto[estado.bloco_calc] = true;
        (estado.pecas || []).forEach(function (k) {
            visto[k] = true;
            visto[pecaCodigoQuadro(k)] = true;
            html += chipHtml({ type: 'peca', value: k, label: pecaLabel(k) }, -1, true);
        });
        var pv = previewEfetivo();
        if (pv && pv.modo === 'quadro' && !visto.media_sem && (!estado.bloco_calc || estado.bloco_calc !== 'media_sem')) {
            visto.media_sem = true;
            html += chipHtml({ type: 'peca', value: 'media_sem', label: 'Média Sem' }, -1, true);
        }
        (pv.colunas || []).forEach(function (c) {
            if (!c || !c.codigo || visto[c.codigo] || c.codigo === '_semanal' || c.travada) return;
            if (c.tipo !== 'calculado' && c.codigo !== 'media_sem') return;
            visto[c.codigo] = true;
            html += chipHtml({ type: 'peca', value: c.codigo, label: c.nome || nomeBlocoCalc(c.codigo) }, -1, true);
        });
        if (!html) html = '<p class="text-xs text-gray-500">Volte em Peças e marque ao menos uma.</p>';
        return html;
    }

    function adicionarBlocoMedia() {
        if (estado.bloco_calc) gravarTokensBlocoAberto();
        if (!estado.formulas_blocos || typeof estado.formulas_blocos !== 'object') estado.formulas_blocos = {};
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (!Array.isArray(estado.blocos_calc)) estado.blocos_calc = [];
        var codigo = novoCodigoBloco();
        if (estado.blocos_calc.indexOf(codigo) < 0) estado.blocos_calc.push(codigo);
        if (!Array.isArray(estado.colunas_ordem)) estado.colunas_ordem = [];
        if (estado.colunas_ordem.indexOf(codigo) < 0) estado.colunas_ordem.push(codigo);
        if (!estado.formulas_blocos[codigo]) estado.formulas_blocos[codigo] = [];
        if (!estado.nomes_blocos[codigo]) {
            estado.nomes_blocos[codigo] = codigo === 'media_bim' ? 'Média Bim'
                : (codigo === 'media_final' ? 'Média Bim Final' : 'Nova média');
        }
        marcarEdicaoManual();
        previewAtual = null;
        renderRevisarDinamico();
        abrirFormulaBloco(codigo);
        agendarMontar();
    }

    function salvarBlocoCalc() {
        if (!estado || !estado.bloco_calc) {
            var edVazio = document.getElementById('bw-formula-editor');
            if (edVazio) edVazio.classList.add('hidden');
            return;
        }
        var inp = document.getElementById('bw-formula-bloco-nome-input');
        var nome = String((inp && inp.value) || '').trim().slice(0, 60);
        if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
        if (nome) estado.nomes_blocos[estado.bloco_calc] = nome;
        gravarTokensBlocoAberto();
        estado.bloco_calc = '';
        estado.materia_calc = 0;
        estado.formula_tokens = [];
        var ed = document.getElementById('bw-formula-editor');
        if (ed) ed.classList.add('hidden');
        marcarEdicaoManual();
        previewAtual = null;
        renderRevisarDinamico();
        agendarMontar();
    }

    function removerBlocoCalc(codigo) {
        if (!codigo || codigo === 'media_sem' || codigo === '_semanal') return;
        estado.blocos_calc = (estado.blocos_calc || []).filter(function (c) { return c !== codigo; });
        if (estado.formulas_blocos) delete estado.formulas_blocos[codigo];
        if (estado.formulas_materias_blocos) delete estado.formulas_materias_blocos[codigo];
        if (estado.nomes_blocos) delete estado.nomes_blocos[codigo];
        estado.colunas_ordem = (estado.colunas_ordem || []).filter(function (c) { return c !== codigo; });
        if (estado.bloco_calc === codigo) {
            estado.bloco_calc = '';
            estado.materia_calc = 0;
            estado.formula_tokens = [];
            var ed = document.getElementById('bw-formula-editor');
            if (ed) ed.classList.add('hidden');
        }
        marcarEdicaoManual();
        previewAtual = null;
        renderRevisarDinamico();
        agendarMontar();
    }

    function dropIndexFormula(e, canvas) {
        var chips = canvas.querySelectorAll('.bw-chip[data-idx]');
        var at = chips.length;
        for (var i = 0; i < chips.length; i++) {
            var rect = chips[i].getBoundingClientRect();
            if (e.clientX < rect.left + rect.width / 2) {
                at = i;
                break;
            }
        }
        return at;
    }

    function bindFormulaBuilderOnce() {
        if (bodyEl._bwFormulaBound) return;
        bodyEl._bwFormulaBound = true;
        var formulaArrastando = false;
        bodyEl.addEventListener('click', function (e) {
            if (e.target.closest('#bw-formula-salvar')) {
                e.preventDefault();
                salvarBlocoCalc();
                return;
            }
            if (e.target.closest('#bw-excecao-add')) {
                var selEx = document.getElementById('bw-excecao-materia');
                var midAdd = selEx ? Number(selEx.value || 0) : 0;
                if (midAdd > 0) abrirExcecaoMateria(midAdd);
                return;
            }
            if (e.target.closest('#bw-excecao-voltar')) {
                voltarFormulaGeral();
                return;
            }
            var edEx = e.target.closest('.bw-excecao-editar');
            if (edEx) {
                abrirExcecaoMateria(edEx.getAttribute('data-mid'));
                return;
            }
            var rmEx = e.target.closest('.bw-excecao-remover');
            if (rmEx) {
                removerExcecaoMateria(rmEx.getAttribute('data-mid'));
                return;
            }
            if (e.target.closest('#bw-formula-media')) {
                estado.formula_tokens = tokensMediaDasPecas();
                afterFormulaChange();
                return;
            }
            if (e.target.closest('#bw-formula-max')) {
                estado.formula_tokens = tokensMaiorDuas();
                afterFormulaChange();
                return;
            }
            if (e.target.closest('#bw-formula-limpar')) {
                estado.formula_tokens = [];
                afterFormulaChange();
                return;
            }
            if (e.target.closest('#bw-formula-num-add')) {
                var inp = document.getElementById('bw-formula-num');
                var v = String((inp && inp.value) || '').trim().replace(',', '.');
                if (!/^\d+(\.\d+)?$/.test(v)) return;
                pushToken({ type: 'num', value: v, label: v });
                if (inp) inp.value = '';
                return;
            }
            var x = e.target.closest('.bw-chip-x');
            if (x && bodyEl.contains(x)) {
                e.preventDefault();
                removeToken(parseInt(x.getAttribute('data-idx'), 10) || 0);
                return;
            }
            var pal = e.target.closest('.bw-pal-chip');
            if (pal && bodyEl.contains(pal)) {
                if (formulaArrastando) return;
                pushToken(tokenFromEl(pal));
            }
        });
        bodyEl.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && e.target && e.target.id === 'bw-formula-bloco-nome-input') {
                e.preventDefault();
                salvarBlocoCalc();
                return;
            }
            if (e.key !== 'Enter' || !e.target || e.target.id !== 'bw-formula-num') return;
            e.preventDefault();
            var btn = document.getElementById('bw-formula-num-add');
            if (btn) btn.click();
        });
        bodyEl.addEventListener('input', function (e) {
            if (!e.target || e.target.id !== 'bw-formula-bloco-nome-input' || !estado || !estado.bloco_calc) return;
            if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
            estado.nomes_blocos[estado.bloco_calc] = String(e.target.value || '').slice(0, 60);
            previewAtual = null;
            var chipNome = document.querySelector('#bw-colunas-lista .bw-col-chip[data-codigo="' + estado.bloco_calc + '"] .bw-col-nome');
            if (chipNome) chipNome.textContent = estado.nomes_blocos[estado.bloco_calc] || nomeBlocoCalc(estado.bloco_calc);
            renderRevisarDinamico();
        });
        bodyEl.addEventListener('dragstart', function (e) {
            var pal = e.target.closest('.bw-pal-chip');
            if (pal) {
                formulaArrastando = true;
                formulaDrag = { kind: 'palette', token: tokenFromEl(pal) };
                e.dataTransfer.setData('text/plain', 'palette');
                e.dataTransfer.effectAllowed = 'copy';
                return;
            }
            var chip = e.target.closest('#bw-formula-canvas .bw-chip');
            if (chip) {
                formulaArrastando = true;
                formulaDrag = { kind: 'canvas', idx: parseInt(chip.getAttribute('data-idx'), 10) };
                e.dataTransfer.setData('text/plain', 'canvas');
                e.dataTransfer.effectAllowed = 'move';
                chip.classList.add('opacity-50');
            }
        });
        bodyEl.addEventListener('dragend', function (e) {
            var chip = e.target.closest('.bw-chip');
            if (chip) chip.classList.remove('opacity-50');
            setTimeout(function () { formulaArrastando = false; }, 0);
        });
        bodyEl.addEventListener('dragover', function (e) {
            var canvas = e.target.closest('#bw-formula-canvas');
            if (!canvas) return;
            e.preventDefault();
            canvas.classList.add('drag-over');
        });
        bodyEl.addEventListener('dragleave', function (e) {
            var canvas = document.getElementById('bw-formula-canvas');
            if (!canvas) return;
            if (e.relatedTarget && canvas.contains(e.relatedTarget)) return;
            canvas.classList.remove('drag-over');
        });
        bodyEl.addEventListener('drop', function (e) {
            var canvas = e.target.closest('#bw-formula-canvas');
            if (!canvas) return;
            e.preventDefault();
            canvas.classList.remove('drag-over');
            var at = dropIndexFormula(e, canvas);
            if (formulaDrag && formulaDrag.kind === 'palette' && formulaDrag.token) {
                pushToken(formulaDrag.token, at);
            } else if (formulaDrag && formulaDrag.kind === 'canvas') {
                moveToken(formulaDrag.idx, at);
            }
            formulaDrag = null;
        });
    }

    function fmtPreviewNota(v) {
        if (v == null || v === '' || v === '—') return '—';
        if (typeof v === 'string' && !/^-?\d/.test(v)) return esc(v);
        var n = Number(v);
        if (!isFinite(n)) return '—';
        return String(Math.round(n * 100) / 100).replace('.', ',');
    }

    function pecaCodigoQuadro(key) {
        var m = {
            semanal: 'media_sem',
            bimestral: 'prova_bim',
            enac: 'enac',
            trabalho: 'trab',
            participacao: 'part',
            recuperacao: 'rec',
            jornada: 'jornada'
        };
        return m[key] || key;
    }

    function pecaNomeQuadro(key) {
        var meta = pecaMeta(key);
        if (meta && meta.label) return meta.label;
        var m = { bimestral: 'Prova Bim', enac: 'ENAC', trabalho: 'Trab', participacao: 'Part', recuperacao: 'Rec', jornada: 'Jornada', semanal: 'Média Sem' };
        return m[key] || key;
    }

    function hashNome(nome) {
        var h = 0;
        String(nome || '').split('').forEach(function (ch) { h = ((h * 31) + ch.charCodeAt(0)) | 0; });
        return Math.abs(h);
    }

    function fmtPreviewCelula(v, col) {
        var lt = (col && col.layout_type) || '';
        if (lt === 'resultado' && typeof v === 'string') return esc(v);
        if (lt === 'faltas') {
            if (v == null || v === '' || v === '—') return '—';
            var nf = Number(v);
            if (!isFinite(nf)) return '—';
            return String(Math.round(nf));
        }
        return fmtPreviewNota(v);
    }

    function classePreviewCelula(v, col) {
        if ((col && col.layout_type) === 'resultado') {
            if (v === 'Aprovado') return ' ok';
            if (v === 'Reprovado') return ' nok';
        }
        return '';
    }

    function eventosFaltasDoAno() {
        var ano = Number((estado && estado.ano_letivo) || 0);
        return ((catalogo && catalogo.faltas_eventos) || []).filter(function (ev) {
            var ja = Number(ev.ano_letivo || 0);
            if (ano > 0 && ja > 0 && ja !== ano) return false;
            return true;
        });
    }

    function materiasPreviewLocal(fallback) {
        var selecionadas = {};
        (estado && Array.isArray(estado.materias_ids) ? estado.materias_ids : []).forEach(function (id) {
            id = Number(id || 0);
            if (id > 0) selecionadas[id] = true;
        });
        var filtrar = Object.keys(selecionadas).length > 0;
        var nomes = [];
        (catalogo.materias || []).forEach(function (m) {
            var id = Number(m && m.id || 0);
            if (id <= 0) return;
            if (filtrar && !selecionadas[id]) return;
            var nome = String((m && m.nome) || '').trim();
            if (nome && nomes.indexOf(nome) < 0) nomes.push(nome);
        });
        return nomes.length ? nomes : fallback;
    }

    function alunoPreviewSelecionado() {
        var id = Number((estado && estado.aluno_preview_id) || 0);
        if (id <= 0) return null;
        var alunos = (catalogo && catalogo.alunos) || [];
        for (var i = 0; i < alunos.length; i++) {
            if (Number(alunos[i].id || 0) === id) return alunos[i];
        }
        return null;
    }

    function hashPreviewAluno() {
        var aluno = alunoPreviewSelecionado();
        return aluno ? hashNome((aluno.nome || '') + '#' + aluno.id) : 0;
    }

    function htmlAlunoPreviewSelect() {
        var alunos = (catalogo && catalogo.alunos) || [];
        if (!alunos.length) return '';
        var atual = Number((estado && estado.aluno_preview_id) || 0);
        var html = '<div class="mb-3 flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 py-2">';
        html += '<label class="text-xs font-medium text-slate-700 flex-1 min-w-[16rem]">Simular notas com aluno';
        html += '<select id="bw-preview-aluno" class="mt-1 block w-full h-9 rounded-lg border border-gray-300 bg-white px-2 text-sm">';
        html += '<option value="0">Exemplo visual sem aluno</option>';
        alunos.forEach(function (a) {
            var label = String(a.nome || ('Aluno #' + a.id));
            if (a.turma_nome) label += ' · ' + a.turma_nome;
            html += '<option value="' + Number(a.id || 0) + '"' + (atual === Number(a.id || 0) ? ' selected' : '') + '>' + esc(label) + '</option>';
        });
        html += '</select></label>';
        html += '<p class="text-xs text-slate-500 max-w-md">Atualiza a prévia na hora. Após salvar, use a simulação real da configuração para conferir com dados gravados.</p>';
        html += '</div>';
        return html;
    }

    function previewLocalBoletim() {
        var fontes = (estado && estado.fontes_bimestres) || {};
        var faltas = (estado && estado.fontes_faltas) || {};
        var bims = [1, 2, 3, 4].filter(function (b) {
            return Number(fontes[b] || 0) > 0 || Number(faltas[b] || 0) > 0;
        });
        if (!bims.length) bims = [1, 2, 3, 4];
        var labels = { 1: '1º BIMESTRE', 2: '2º BIMESTRE', 3: '3º BIMESTRE', 4: '4º BIMESTRE' };
        var grupos = bims.map(function (b) {
            return {
                key: 'b' + b,
                label: labels[b],
                cols: [
                    { codigo: 'b' + b + '_media', nome: 'Média', layout_type: 'media', source_type: 'evento_boletim' },
                    { codigo: 'b' + b + '_faltas', nome: 'Faltas', layout_type: 'faltas', source_type: 'faltas_evento' }
                ]
            };
        });
        grupos.push({
            key: 'final',
            label: 'FINAL',
            cols: [
                { codigo: 'media_final', nome: 'Média', layout_type: 'media', source_type: 'calculado' },
                { codigo: 'rec_final', nome: 'Rec.', layout_type: 'rec', source_type: 'nenhuma' },
                { codigo: 'faltas_final', nome: 'Faltas', layout_type: 'faltas', source_type: 'calculado' },
                { codigo: 'resultado', nome: 'Resultado', layout_type: 'resultado', source_type: 'nenhuma' }
            ]
        });
        var notaMin = Number((estado && estado.nota_minima_aprovacao) != null ? estado.nota_minima_aprovacao : 7);
        function notasLinha(nome) {
            var h = hashNome(nome) + hashPreviewAluno();
            var notas = {};
            var acc = 0;
            var nMed = 0;
            var accF = 0;
            bims.forEach(function (b) {
                var media = Math.round(10 * (5 + Math.abs(h + b * 7) % 51) / 10) / 10;
                var falt = Math.abs(h + b * 5) % 9;
                notas['b' + b + '_media'] = media;
                notas['b' + b + '_faltas'] = falt;
                acc += media;
                nMed += 1;
                accF += falt;
            });
            notas.media_final = nMed ? Math.round(100 * acc / nMed) / 100 : 0;
            notas.rec_final = (h % 3) === 0 ? Math.round(10 * (5 + Math.abs(h + 11) % 40) / 10) / 10 : '—';
            notas.faltas_final = accF;
            notas.resultado = notas.media_final >= notaMin ? 'Aprovado' : 'Reprovado';
            return notas;
        }
        var mats = materiasPreviewLocal(['Língua Portuguesa', 'Matemática', 'História', 'Geografia', 'Ciências']);
        return {
            modo: 'boletim',
            aviso: 'Exemplo com dados fictícios — não são notas reais.',
            grupos: grupos,
            colunas: [],
            pecas_disponiveis: [],
            tabelas: [{
                key: 'u',
                titulo: 'Matérias',
                subtitulo: '',
                semanas: [],
                outras: [],
                grupos: grupos,
                linhas: mats.map(function (n) { return { materia_nome: n, notas: notasLinha(n) }; })
            }]
        };
    }

    function previewLocal() {
        if (ehBoletimComposto()) return previewLocalBoletim();
        var pecas = (estado && estado.pecas) ? estado.pecas.slice() : [];
        var semanasA = [1, 3, 5, 7].map(function (s) { return { codigo: 's' + s, nome: 'S' + s }; });
        var semanasB = [2, 4, 6, 8].map(function (s) { return { codigo: 's' + s, nome: 'S' + s }; });
        var outras = [{ codigo: 'media_sem', nome: 'Média Sem', layout_type: 'media_sem', source_type: 'calculado' }];
        var colunas = [{ codigo: '_semanal', nome: 'Prova semanal (S1–S8 · N e Q)', tipo: 'semana_grupo', travada: true }];
        var partesMedia = ['media_sem'];
        pecas.forEach(function (k) {
            if (k === 'semanal') return;
            var cod = pecaCodigoQuadro(k);
            var nome = k === 'bimestral' ? 'Prova Bim' : pecaNomeQuadro(k);
            outras.push({ codigo: cod, nome: nome, layout_type: k === 'recuperacao' ? 'rec' : 'media', source_type: 'provas_sistema' });
            colunas.push({ codigo: cod, nome: nome, tipo: 'peca', travada: false });
            if (k !== 'recuperacao') partesMedia.push(cod);
        });
        if (pecas.indexOf('bimestral') < 0 && pecas.length === 0) {
            outras.push({ codigo: 'prova_bim', nome: 'Prova Bim', layout_type: 'media', source_type: 'provas_sistema' });
            colunas.push({ codigo: 'prova_bim', nome: 'Prova Bim', tipo: 'peca', travada: false });
            partesMedia.push('prova_bim');
        }
        outras.push({ codigo: 'media_bim', nome: nomeBlocoCalc('media_bim'), layout_type: 'media', source_type: 'calculado' });
        colunas.push({ codigo: 'media_bim', nome: nomeBlocoCalc('media_bim'), tipo: 'calculado', travada: false });
        var vistosCalc = { media_bim: true, media_sem: true };
        (estado.blocos_calc || []).concat(Object.keys(estado.formulas_blocos || {})).forEach(function (cod) {
            if (!cod || vistosCalc[cod] || cod === 'media_sem') return;
            vistosCalc[cod] = true;
            var nm = nomeBlocoCalc(cod);
            outras.push({ codigo: cod, nome: nm, layout_type: cod === 'media_final' ? 'resultado' : 'media', source_type: 'calculado' });
            colunas.push({ codigo: cod, nome: nm, tipo: 'calculado', travada: false });
        });
        var temRec = pecas.indexOf('recuperacao') >= 0;
        var temMelhora = pecas.some(function (k) {
            return ((estado.pecas_opcoes && estado.pecas_opcoes[k]) || {}).papel === 'so_melhora';
        });
        var querFinal = temRec || temMelhora
            || (estado.blocos_calc || []).indexOf('media_final') >= 0
            || (estado.formulas_blocos && estado.formulas_blocos.media_final)
            || estado.bloco_calc === 'media_final';
        if (querFinal && !vistosCalc.media_final) {
            outras.push({ codigo: 'media_final', nome: nomeBlocoCalc('media_final'), layout_type: 'resultado', source_type: 'calculado' });
            colunas.push({ codigo: 'media_final', nome: nomeBlocoCalc('media_final'), tipo: 'calculado', travada: false });
        }
        colunas = ordenarColunasLista(colunas);
        outras = ordenarColunasLista(outras);
        function notasLinha(nome, semanas) {
            var h = hashNome(nome) + hashPreviewAluno();
            var notas = {};
            var sumN = 0;
            var sumQ = 0;
            semanas.forEach(function (s) {
                var n = 5 + Math.abs(h + parseInt(String(s.codigo).slice(1), 10) * 3) % 6;
                notas[s.codigo + '__n'] = n;
                notas[s.codigo + '__q'] = 10;
                sumN += n;
                sumQ += 10;
            });
            notas.media_sem = sumQ ? Math.round(1000 * sumN / sumQ) / 100 : 0;
            outras.forEach(function (o) {
                if (o.codigo === 'media_sem') return;
                if (o.source_type === 'calculado' && o.codigo === 'media_bim') {
                    var acc = notas.media_sem;
                    var nPart = 1;
                    partesMedia.forEach(function (c) {
                        if (c === 'media_sem') return;
                        if (typeof notas[c] === 'number') { acc += notas[c]; nPart += 1; }
                    });
                    notas.media_bim = Math.round(100 * acc / nPart) / 100;
                    return;
                }
                if (o.source_type === 'calculado' && o.codigo === 'media_final') {
                    var base = typeof notas.media_bim === 'number' ? notas.media_bim : notas.media_sem;
                    var extra = typeof notas.enac === 'number' ? notas.enac : (typeof notas.rec === 'number' ? notas.rec : base);
                    notas.media_final = Math.max(base, extra);
                    return;
                }
                if (o.codigo === 'rec' && (h % 3) === 0) {
                    notas[o.codigo] = '—';
                    return;
                }
                notas[o.codigo] = Math.round(10 * (5 + Math.abs(h + o.codigo.length * 7) % 51) / 10) / 10;
            });
            return notas;
        }
        var mats = materiasPreviewLocal(['Física', 'Matemática', 'Geografia', 'Biologia', 'Educação Física']);
        var metade = Math.ceil(mats.length / 2);
        var matsA = mats.slice(0, metade);
        var matsB = mats.slice(metade);
        if (!matsB.length) matsB = matsA.slice();
        var sel = pecas;
        var disp = (catalogo.pecas || []).filter(function (p) { return sel.indexOf(p.key) < 0; }).map(function (p) {
            return { key: p.key, label: p.label };
        });
        return {
            modo: 'quadro',
            aviso: 'Exemplo com dados fictícios — não são notas reais.',
            colunas: colunas,
            pecas_disponiveis: disp,
            tabelas: [
                { key: 'a', titulo: 'Matérias Bloco A', subtitulo: 'Prova semanal', semanas: semanasA, outras: outras, linhas: matsA.map(function (n) { return { materia_nome: n, notas: notasLinha(n, semanasA) }; }) },
                { key: 'b', titulo: 'Matérias Bloco B', subtitulo: 'Prova semanal', semanas: semanasB, outras: outras, linhas: matsB.map(function (n) { return { materia_nome: n, notas: notasLinha(n, semanasB) }; }) }
            ]
        };
    }

    function previewEfetivo() {
        if (ehBoletimComposto()) {
            if (previewAtual && previewAtual.modo === 'boletim' && (previewAtual.tabelas || []).length) {
                return previewAtual;
            }
            return previewLocalBoletim();
        }
        if (previewAtual && (previewAtual.tabelas || []).length) return previewAtual;
        return previewLocal();
    }

    function htmlTabelaPreviewBoletim(tab) {
        var grupos = tab.grupos || [];
        var html = '<div class="overflow-x-auto border border-gray-300 rounded-lg bg-white mb-4">';
        html += '<table class="bw-preview-table boletim"><thead><tr>';
        html += '<th rowspan="2" class="mat text-left">' + esc(tab.titulo || 'Matéria') + '</th>';
        grupos.forEach(function (g) {
            html += '<th colspan="' + (g.cols || []).length + '">' + esc(g.label || g.key) + '</th>';
        });
        html += '</tr><tr>';
        grupos.forEach(function (g) {
            (g.cols || []).forEach(function (c) {
                html += '<th class="sub">' + esc(c.nome || c.codigo) + '</th>';
            });
        });
        html += '</tr></thead><tbody>';
        (tab.linhas || []).forEach(function (lin) {
            var notas = lin.notas || {};
            html += '<tr><td class="mat">' + esc(lin.materia_nome) + '</td>';
            grupos.forEach(function (g) {
                (g.cols || []).forEach(function (c) {
                    var v = notas[c.codigo];
                    html += '<td class="' + classePreviewCelula(v, c) + '">' + fmtPreviewCelula(v, c) + '</td>';
                });
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function htmlTabelaPreview(tab) {
        if (tab && (tab.grupos || []).length) return htmlTabelaPreviewBoletim(tab);
        var semanas = tab.semanas || [];
        var outras = tab.outras || [];
        var html = '<div class="overflow-x-auto border border-gray-300 rounded-lg bg-white mb-4">';
        if (tab.subtitulo) {
            html += '<div class="px-3 py-1.5 text-sm font-semibold text-gray-800 bg-gray-50 border-b">' + esc(tab.subtitulo) + '</div>';
        }
        html += '<table class="bw-preview-table"><thead><tr>';
        html += '<th rowspan="2" class="text-left">' + esc(tab.titulo || 'Matérias') + '</th>';
        semanas.forEach(function (s) {
            html += '<th colspan="2">' + esc(s.nome || s.codigo) + '</th>';
        });
        if (semanas.length) html += '<th colspan="2">Total</th>';
        outras.forEach(function (o) {
            var extra = (o.layout_type === 'faltas' || o.layout_type === 'rec') ? '' : '<div class="text-[9px] font-normal opacity-80">Valor 10</div>';
            html += '<th rowspan="2">' + esc(o.nome || o.codigo) + extra + '</th>';
        });
        html += '</tr><tr>';
        semanas.forEach(function () { html += '<th class="sub">N</th><th class="sub">Q</th>'; });
        if (semanas.length) html += '<th class="sub">N</th><th class="sub">Q</th>';
        html += '</tr></thead><tbody>';
        (tab.linhas || []).forEach(function (lin) {
            var notas = lin.notas || {};
            var totN = 0;
            var totQ = 0;
            html += '<tr><td class="mat">' + esc(lin.materia_nome) + '</td>';
            semanas.forEach(function (s) {
                var n = notas[(s.codigo || '') + '__n'];
                var q = notas[(s.codigo || '') + '__q'];
                if (n != null) totN += Number(n) || 0;
                if (q != null) totQ += Number(q) || 0;
                html += '<td>' + (n != null ? n : 0) + '</td><td>' + (q != null ? q : 0) + '</td>';
            });
            if (semanas.length) html += '<td><strong>' + totN + '</strong></td><td><strong>' + totQ + '</strong></td>';
            outras.forEach(function (o) {
                html += '<td>' + fmtPreviewNota(notas[o.codigo]) + '</td>';
            });
            html += '</tr>';
        });
        html += '</tbody></table></div>';
        return html;
    }

    function htmlPreview(pv) {
        if (!pv || !(pv.tabelas || []).length) {
            return '<p class="text-xs text-gray-500">' + esc((pv && pv.aviso) || 'Monte as peças para ver o exemplo.') + '</p>';
        }
        var html = htmlAlunoPreviewSelect();
        html += '<p class="text-[11px] font-semibold text-amber-800 bg-amber-50 border border-amber-100 rounded-md px-2 py-1 mb-2">' + esc(pv.aviso || 'Exemplo com dados fictícios.') + '</p>';
        (pv.tabelas || []).forEach(function (t) {
            if (pv.modo === 'boletim' && !(t.grupos || []).length && (pv.grupos || []).length) {
                t = Object.assign({}, t, { grupos: pv.grupos });
            }
            html += htmlTabelaPreview(t);
        });
        return html;
    }

    function colunaSegueBlocoSemanal(c) {
        return !!(c && (c.codigo === 'media_sem' || c.layout_type === 'media_sem'));
    }

    function ordenarColunasLista(cols) {
        var ordem = (estado && estado.colunas_ordem) || [];
        if (!cols || !cols.length) return cols || [];
        var map = {};
        var travadas = [];
        var pinadas = [];
        var moveis = [];
        cols.forEach(function (c) {
            if (!c) return;
            if (c.travada || c.codigo === '_semanal') travadas.push(c);
            else if (colunaSegueBlocoSemanal(c)) pinadas.push(c);
            else {
                map[c.codigo] = c;
                moveis.push(c);
            }
        });
        var base = travadas.concat(pinadas);
        if (!ordem.length) return base.concat(moveis);
        var out = base.slice();
        var visto = {};
        ordem.forEach(function (cod) {
            if (map[cod] && !visto[cod]) {
                out.push(map[cod]);
                visto[cod] = true;
            }
        });
        moveis.forEach(function (c) {
            if (!visto[c.codigo]) out.push(c);
        });
        return out;
    }

    function htmlColunasOrdem(pv) {
        var cols = ordenarColunasLista((pv && pv.colunas) || []);
        var html = '<div class="flex flex-wrap items-center justify-between gap-2 mb-2">';
        html += '<p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide">Ordem das colunas</p>';
        html += '<button type="button" id="bw-add-media" class="px-3 py-1.5 text-xs font-medium rounded-lg border border-amber-300 text-amber-950 bg-amber-50 hover:bg-amber-100">Adicionar bloco de cálculo</button>';
        html += '</div>';
        html += '<p class="text-xs text-gray-500 mb-2">Arraste pelo <strong>⋮⋮</strong> para mudar a ordem (Prova Bim, ENAC, Média…). Clique no nome amarelo para o cálculo.</p>';
        html += '<div id="bw-colunas-lista" class="flex flex-wrap gap-2 min-h-[2.5rem] p-2 rounded-lg border border-dashed border-indigo-200 bg-slate-50">';
        if (!cols.length) html += '<span class="text-xs text-gray-400">Nenhuma coluna ainda.</span>';
        cols.forEach(function (c, i) {
            if (colunaSegueBlocoSemanal(c)) return;
            var cls = 'bw-col-chip' + (c.travada ? ' travada' : '') + (c.tipo === 'calculado' ? ' calc' : '');
            if (estado && estado.bloco_calc && c.codigo === estado.bloco_calc) cls += ' aberta';
            html += '<span class="' + cls + '" draggable="' + (c.travada ? 'false' : 'true') + '" data-codigo="' + esc(c.codigo) + '" data-tipo="' + esc(c.tipo || '') + '" data-idx="' + i + '">';
            if (!c.travada) html += '<span class="bw-col-grip" title="Arrastar" aria-hidden="true">⋮⋮</span>';
            html += '<span class="bw-col-nome">' + esc(c.nome) + '</span>';
            if (c.tipo === 'calculado' && c.codigo !== 'media_sem') {
                html += '<button type="button" class="bw-col-x" data-codigo="' + esc(c.codigo) + '" aria-label="Remover">×</button>';
            }
            html += '</span>';
        });
        html += '</div>';
        return html;
    }

    function renderRevisarDinamico() {
        var col = document.getElementById('bw-colunas-wrap');
        var pvEl = document.getElementById('bw-preview-wrap');
        var pv = previewEfetivo();
        if (col) col.innerHTML = htmlColunasOrdem(pv);
        if (pvEl) pvEl.innerHTML = htmlPreview(pv);
        bindRevisarOnce();
    }

    function bindRevisarOnce() {
        if (bodyEl._bwRevisarBound) return;
        bodyEl._bwRevisarBound = true;
        bodyEl.addEventListener('click', function (e) {
            if (e.target.closest('#bw-add-media')) {
                adicionarBlocoMedia();
                return;
            }
            var xcol = e.target.closest('#bw-colunas-lista .bw-col-x');
            if (xcol) {
                e.preventDefault();
                removerBlocoCalc(xcol.getAttribute('data-codigo'));
                return;
            }
            var chipCalc = e.target.closest('#bw-colunas-lista .bw-col-chip.calc');
            if (chipCalc) {
                if (colunasDragou || e.target.closest('.bw-col-grip')) {
                    colunasDragou = false;
                    return;
                }
                var codCalc = chipCalc.getAttribute('data-codigo');
                if (codCalc && codCalc !== 'media_sem') {
                    if (estado.passo !== 'formula') {
                        estado.passo = 'formula';
                        estado.bloco_calc = codCalc;
                        renderAll();
                        agendarMontar();
                        return;
                    }
                    abrirFormulaBloco(codCalc);
                }
            }
        });
        bodyEl.addEventListener('change', function (e) {
            if (!e.target || e.target.id !== 'bw-preview-aluno' || !estado) return;
            estado.aluno_preview_id = Number(e.target.value || 0) || 0;
            previewAtual = null;
            renderRevisarDinamico();
            agendarMontar();
        });
        bodyEl.addEventListener('dragstart', function (e) {
            var chip = e.target.closest('#bw-colunas-lista .bw-col-chip');
            if (!chip || chip.classList.contains('travada') || e.target.closest('.bw-col-x')) return;
            colunasDrag = chip.getAttribute('data-codigo');
            colunasDragou = true;
            chip.classList.add('dragging');
            e.dataTransfer.setData('text/plain', colunasDrag || '');
            e.dataTransfer.effectAllowed = 'move';
        });
        bodyEl.addEventListener('dragend', function (e) {
            var chip = e.target.closest('#bw-colunas-lista .bw-col-chip');
            if (chip) chip.classList.remove('dragging');
            document.querySelectorAll('#bw-colunas-lista .drop-alvo').forEach(function (el) {
                el.classList.remove('drop-alvo');
            });
            setTimeout(function () { colunasDragou = false; }, 0);
        });
        bodyEl.addEventListener('dragover', function (e) {
            if (!e.target.closest('#bw-colunas-lista')) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            var alvo = e.target.closest('.bw-col-chip');
            document.querySelectorAll('#bw-colunas-lista .drop-alvo').forEach(function (el) {
                if (el !== alvo) el.classList.remove('drop-alvo');
            });
            if (alvo && !alvo.classList.contains('travada')) alvo.classList.add('drop-alvo');
        });
        bodyEl.addEventListener('drop', function (e) {
            var lista = e.target.closest('#bw-colunas-lista');
            if (!lista || !colunasDrag) return;
            e.preventDefault();
            var alvo = e.target.closest('.bw-col-chip');
            var ordem = [];
            lista.querySelectorAll('.bw-col-chip').forEach(function (el) {
                var c = el.getAttribute('data-codigo');
                if (c && c !== '_semanal' && c !== 'media_sem' && c !== colunasDrag) ordem.push(c);
            });
            var at = ordem.length;
            if (alvo) {
                var ac = alvo.getAttribute('data-codigo');
                if (ac && ac !== '_semanal' && ac !== 'media_sem') {
                    var ix = ordem.indexOf(ac);
                    if (ix >= 0) at = ix;
                }
            }
            ordem.splice(at, 0, colunasDrag);
            estado.colunas_ordem = ordem;
            colunasDrag = null;
            marcarEdicaoManual();
            previewAtual = null;
            renderRevisarDinamico();
            agendarMontar();
        });
    }

    function aplicarRascunhoNoWizard(r) {
        if (!r || typeof r !== 'object') return;
        garantirEstado();
        estado.origem = 'chat';
        estado.rascunho_preservado = r;
        estado.passo = 'revisar';
        estado.materia_unica = materiaUnicaDoRascunho(r);
        estado.materia_unica_tocada = false;
        aplicarMateriaUnicaNasPecas();
        if (r.nome) estado.nome = r.nome;
        if (r.bimestre != null) estado.bimestre = r.bimestre;
        if (r.ano_letivo) estado.ano_letivo = r.ano_letivo;
        if (Array.isArray(r.series_ids)) {
            estado.series_ids = r.series_ids.map(Number).filter(function (id) { return id > 0; });
        }
        if (Array.isArray(r.turmas_ids)) {
            estado.turmas_ids = r.turmas_ids.map(Number).filter(function (id) { return id > 0; });
        }
        if (Array.isArray(r.materias_ids)) {
            estado.materias_ids = r.materias_ids.map(Number).filter(function (id) { return id > 0; });
        }
        if (r.default_data_inicio) estado.data_inicio = String(r.default_data_inicio).slice(0, 10);
        if (r.default_data_fim) estado.data_fim = String(r.default_data_fim).slice(0, 10);
        var inf = inferirPecasDoRascunho(r);
        if (inf.pecas.length) {
            estado.pecas = inf.pecas;
            estado.pecas_opcoes = inf.pecas_opcoes;
        }
        rascunhoAtual = r;
        aplicarRascunho(r);
        renderAll();
        renderResumo(
            'Ajuste do chat aplicado no formulário.\n\nRevise e salve o evento quando estiver ok.',
            []
        );
    }

    function setCheckboxes(name, ids) {
        var set = {};
        (ids || []).forEach(function (id) { set[String(id)] = true; });
        document.querySelectorAll('input[name="' + name + '"]').forEach(function (el) {
            el.checked = !!set[el.value];
        });
    }

    function normalizarComponentesRascunho(compsNovos) {
        compsNovos = Array.isArray(compsNovos) ? compsNovos : [];
        return compsNovos.map(function (c, idx) {
            var blocos = Array.isArray(c.blocos_ids) ? c.blocos_ids : [];
            var cfg = (c.config && typeof c.config === 'object') ? Object.assign({}, c.config) : {};
            if (c.expressao && !cfg.expressao) cfg.expressao = c.expressao;
            if ((c.source_type || '') === 'calculado' && !cfg.formula_mode) {
                var fm = cfg.formula_materias;
                cfg.formula_mode = (fm && typeof fm === 'object' && Object.keys(fm).length) ? 'per_materia' : 'single';
            }
            return {
                codigo: c.codigo || ('comp' + (idx + 1)),
                nome: c.nome || c.codigo || ('Componente ' + (idx + 1)),
                source_type: c.source_type || 'provas_sistema',
                calc_type: c.calc_type || 'media',
                peso: c.peso != null ? c.peso : 1,
                filtro_titulo: c.filtro_titulo || '',
                bloco_id: blocos[0] || c.bloco_id || null,
                blocos_ids: blocos,
                materias_ids: Array.isArray(c.materias_ids) ? c.materias_ids : [],
                materia_unica: c.materia_unica ? 1 : 0,
                usar_percentual: c.usar_percentual ? 1 : 0,
                escala_max: c.escala_max != null ? c.escala_max : 10,
                obrigatorio: c.obrigatorio ? 1 : 0,
                config: cfg
            };
        });
    }

    function aplicarRascunho(r) {
        if (!r || typeof r !== 'object') return;
        var nomeEl = document.getElementById('regra-nome');
        if (nomeEl && r.nome) nomeEl.value = r.nome;
        var codEl = document.getElementById('regra-codigo');
        if (codEl && r.codigo) {
            codEl.readOnly = false;
            codEl.value = r.codigo;
        }
        var descEl = document.getElementById('regra-descricao-curta');
        if (descEl && r.descricao_curta != null) descEl.value = r.descricao_curta;
        var formulaEl = document.querySelector('[name="formula_final"]');
        if (formulaEl && r.formula_final != null) formulaEl.value = r.formula_final;
        var roundEl = document.querySelector('[name="round_mode"]');
        if (roundEl && r.round_mode) roundEl.value = r.round_mode;
        var exibirEl = document.querySelector('[name="exibir_em"]');
        if (exibirEl && r.exibir_em) {
            if (exibirEl.tagName === 'SELECT' || exibirEl.type === 'text' || exibirEl.type === 'hidden') {
                exibirEl.value = r.exibir_em;
            } else {
                document.querySelectorAll('input[name="exibir_em"]').forEach(function (el) {
                    el.checked = el.value === r.exibir_em;
                });
            }
        }
        var anoEl = document.querySelector('[name="ano_letivo"]') || document.getElementById('regra-ano-letivo');
        if (anoEl && r.ano_letivo) anoEl.value = r.ano_letivo;
        var bimEl = document.querySelector('[name="bimestre"]') || document.getElementById('regra-bimestre');
        if (bimEl) {
            if (r.bimestre != null && r.bimestre !== '') bimEl.value = r.bimestre;
            else if (r.exibir_em === 'boletim') bimEl.value = '';
        }
        var diEl = document.querySelector('[name="default_data_inicio"]');
        if (diEl && r.default_data_inicio) diEl.value = r.default_data_inicio;
        var dfEl = document.querySelector('[name="default_data_fim"]');
        if (dfEl && r.default_data_fim) dfEl.value = r.default_data_fim;
        var notaEl = document.querySelector('[name="nota_minima_aprovacao"]');
        if (notaEl && r.nota_minima_aprovacao != null) notaEl.value = r.nota_minima_aprovacao;
        var usarRes = document.querySelector('[name="usar_resultado_aprovacao"]');
        if (usarRes && r.usar_resultado_aprovacao != null) usarRes.checked = !!Number(r.usar_resultado_aprovacao);
        if (Array.isArray(r.turmas_ids)) setCheckboxes('turmas_ids[]', r.turmas_ids);
        if (Array.isArray(r.materias_ids)) setCheckboxes('materias_ids[]', r.materias_ids);
        if (Array.isArray(r.series_ids)) setCheckboxes('series_ids[]', r.series_ids);

        var regraIdInput = document.querySelector('#form-regra-boletim input[name="regra_id"]');
        if (regraIdInput) {
            if (r.modo === 'editar' && r.regra_id) regraIdInput.value = String(r.regra_id);
            else if (r.modo === 'criar') regraIdInput.value = '0';
        }

        var normalized = normalizarComponentesRascunho(r.componentes);
        var jsonEl = document.getElementById('componentes-json');
        if (jsonEl) jsonEl.value = JSON.stringify(normalized);
        if (typeof window.boletimRenderComponentes === 'function') {
            window.boletimRenderComponentes(normalized);
        } else if (typeof window.renderComponentesLista === 'function') {
            window.renderComponentesLista(normalized);
        } else {
            document.dispatchEvent(new CustomEvent('boletim:assistente-rascunho', { detail: { componentes: normalized, rascunho: r } }));
        }
    }
    window.boletimAplicarRascunhoAssistente = aplicarRascunho;

    function copiarTexto(texto) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(texto);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = texto;
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); resolve(); } catch (e) { reject(e); }
            document.body.removeChild(ta);
        });
    }

    function appendBubble(role, text) {
        if (!msgsEl) {
            if (role === 'assistant') {
                renderResumo(text || 'Pronto.', []);
            }
            return null;
        }
        var wrap = document.createElement('div');
        wrap.className = role === 'user'
            ? 'ml-6 bg-indigo-600 text-white rounded-lg px-3 py-2 whitespace-pre-wrap'
            : 'mr-2 bg-white border border-gray-200 text-gray-800 rounded-lg px-3 py-2 whitespace-pre-wrap';
        wrap.textContent = text || '';
        msgsEl.appendChild(wrap);
        msgsEl.scrollTop = msgsEl.scrollHeight;
        return wrap;
    }

    function ehBoletimComposto() {
        return !!(estado && estado.exibir_em === 'boletim' && estado.origem !== 'clonar');
    }

    function passosVisiveis() {
        var vis = ehBoletimComposto()
            ? PASSOS.filter(function (p) {
                return ['inicio', 'identidade', 'publico', 'revisar'].indexOf(p.id) >= 0;
            })
            : PASSOS.slice();
        if (estado && estado.modo === 'editar' && estado.origem === 'formulario') {
            vis = vis.filter(function (p) {
                return p.id !== 'inicio';
            });
        }
        return vis;
    }

    function regrasNotasDoAno() {
        var ano = Number((estado && estado.ano_letivo) || 0);
        var selfId = Number((estado && estado.regra_id) || 0);
        return ((catalogo && catalogo.regras) || []).filter(function (r) {
            if ((r.exibir_em || 'boletim') !== 'notas') return false;
            if (selfId > 0 && Number(r.id) === selfId) return false;
            var ja = Number(r.ano_letivo || 0);
            if (ano > 0 && ja > 0 && ja !== ano) return false;
            return true;
        });
    }

    function sugerirFontesBimestres() {
        if (!estado.fontes_bimestres || typeof estado.fontes_bimestres !== 'object') {
            estado.fontes_bimestres = { 1: 0, 2: 0, 3: 0, 4: 0 };
        }
        regrasNotasDoAno().forEach(function (r) {
            var b = Number(r.bimestre || 0);
            if (b < 1 || b > 4) return;
            if (!Number(estado.fontes_bimestres[b] || 0)) {
                estado.fontes_bimestres[b] = Number(r.id);
            }
        });
        if (!estado.fontes_faltas || typeof estado.fontes_faltas !== 'object') {
            estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
        }
        eventosFaltasDoAno().forEach(function (ev) {
            var b = Number(ev.bimestre || 0);
            if (b < 1 || b > 4) return;
            if (!Number(estado.fontes_faltas[b] || 0)) {
                estado.fontes_faltas[b] = Number(ev.id);
            }
        });
    }

    function aplicarExibirEm(valor, reRender) {
        estado.exibir_em = valor === 'boletim' ? 'boletim' : 'notas';
        if (estado.exibir_em === 'boletim') {
            estado.bimestre = 0;
            if (!estado.nome || /^Notas Bimestrais/i.test(estado.nome)) {
                estado.nome = 'Boletim ' + (estado.ano_letivo || '');
            }
            sugerirFontesBimestres();
            if (['pecas', 'formula'].indexOf(estado.passo) >= 0) estado.passo = 'identidade';
        } else if (!estado.bimestre) {
            estado.bimestre = 1;
            if (!estado.nome || /^Boletim /i.test(estado.nome)) {
                estado.nome = 'Notas Bimestrais — 1º bimestre ' + (estado.ano_letivo || '');
            }
        }
        if (reRender) renderAll();
    }

    function htmlFontesBimestres() {
        var regras = regrasNotasDoAno();
        var faltas = eventosFaltasDoAno();
        var html = '<div class="mt-4 rounded-xl border border-indigo-200 bg-indigo-50/70 p-3 space-y-3">';
        html += '<p class="text-sm font-semibold text-indigo-950">Médias e faltas de cada bimestre</p>';
        html += '<p class="text-xs text-indigo-900/80">O boletim <strong>não monta o cálculo de novo</strong>. Ele puxa a média final do evento de Notas e as faltas do evento de Faltas de cada bimestre (quadro 1º–4º + Final com Média, Rec., Faltas e Resultado).</p>';
        [1, 2, 3, 4].forEach(function (b) {
            var sel = Number((estado.fontes_bimestres && estado.fontes_bimestres[b]) || 0);
            var selF = Number((estado.fontes_faltas && estado.fontes_faltas[b]) || 0);
            var doBim = regras.filter(function (r) { return Number(r.bimestre || 0) === b; });
            var outras = regras.filter(function (r) { return Number(r.bimestre || 0) !== b; });
            var faltasBim = faltas.filter(function (ev) { return Number(ev.bimestre || 0) === b; });
            var faltasOut = faltas.filter(function (ev) { return Number(ev.bimestre || 0) !== b; });
            html += '<div class="rounded-lg border border-indigo-100 bg-white/80 p-2.5 space-y-2">';
            html += '<p class="text-xs font-semibold text-gray-800">' + b + 'º bimestre</p>';
            html += '<div class="grid sm:grid-cols-2 gap-2">';
            html += '<label class="block text-xs font-medium text-gray-700">Média (evento de Notas)';
            html += '<select class="bw-fonte-bim mt-1 w-full h-10 border border-indigo-200 rounded-lg px-3 text-sm bg-white" data-bim="' + b + '">';
            html += '<option value="0">Não usar este bimestre</option>';
            doBim.concat(outras).forEach(function (r) {
                var lab = r.nome || ('Evento #' + r.id);
                if (r.bimestre) lab += ' · ' + r.bimestre + 'º bim';
                if (r.formula_final) lab += ' · col. ' + r.formula_final;
                html += '<option value="' + Number(r.id) + '"' + (sel === Number(r.id) ? ' selected' : '') + '>' + esc(lab) + '</option>';
            });
            html += '</select></label>';
            html += '<label class="block text-xs font-medium text-gray-700">Faltas (evento de Faltas)';
            html += '<select class="bw-fonte-faltas mt-1 w-full h-10 border border-indigo-200 rounded-lg px-3 text-sm bg-white" data-bim="' + b + '">';
            html += '<option value="0">Sem faltas neste bimestre</option>';
            faltasBim.concat(faltasOut).forEach(function (ev) {
                var labF = ev.nome || ('Faltas #' + ev.id);
                if (ev.bimestre_rotulo) labF += ' · ' + ev.bimestre_rotulo;
                else if (ev.bimestre) labF += ' · ' + ev.bimestre + 'º bim';
                html += '<option value="' + Number(ev.id) + '"' + (selF === Number(ev.id) ? ' selected' : '') + '>' + esc(labF) + '</option>';
            });
            html += '</select></label>';
            html += '</div></div>';
        });
        if (!regras.length) {
            html += '<p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5">Nenhum evento de <strong>Notas</strong> neste ano. Crie primeiro o quadro de notas de cada bimestre (Exibir em: Notas) e volte aqui.</p>';
        }
        if (!faltas.length) {
            html += '<p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5">Nenhum evento de <strong>Faltas</strong> neste ano. Cadastre em Faltas (bimestre + turmas) para aparecer a coluna no boletim.</p>';
        }
        html += '</div>';
        return html;
    }

    function idxPasso(id) {
        for (var i = 0; i < PASSOS.length; i++) if (PASSOS[i].id === id) return i;
        return 0;
    }

    var WIZARD_CLASS_MAP = {
        ativo: ['border-accent', 'bg-primary', 'text-primary', 'shadow-md'],
        completo: ['border-green-500', 'bg-green-50', 'text-green-700'],
        erro: ['border-red-400', 'bg-red-50', 'text-red-700'],
        pendente: ['border-gray-200', 'bg-white', 'text-gray-600', 'hover:border-gray-300', 'hover:bg-gray-50']
    };
    var WIZARD_ALL_STATE_CLASSES = Object.keys(WIZARD_CLASS_MAP).reduce(function (acc, k) {
        return acc.concat(WIZARD_CLASS_MAP[k]);
    }, []);

    function renderWizardBadge(btn, estado) {
        var circle = btn.querySelector('.wizard-step-circle');
        if (!circle) return;
        var existing = circle.querySelector('.wizard-step-corner');
        if (existing) existing.remove();
        if (estado !== 'completo') return;
        var span = document.createElement('span');
        span.className = 'wizard-step-corner absolute -top-1 -right-1 flex h-4 w-4 items-center justify-center rounded-full text-white text-[9px] bg-green-500';
        var icon = document.createElement('i');
        icon.className = 'fa-solid fa-check';
        span.appendChild(icon);
        circle.appendChild(span);
    }

    function atualizarStepsPageMode(vis, curIdx) {
        if (!stepsEl) return;
        stepsEl.querySelectorAll('.step-nav-btn').forEach(function (btn) {
            var n = parseInt(btn.getAttribute('data-step-target') || '0', 10);
            var idx = n - 1;
            var p = vis[idx];
            var estadoStep = idx === curIdx ? 'ativo' : (idx < curIdx ? 'completo' : 'pendente');
            btn.setAttribute('data-active', estadoStep === 'ativo' ? 'true' : 'false');
            btn.setAttribute('data-step-state', estadoStep);
            WIZARD_ALL_STATE_CLASSES.forEach(function (c) { btn.classList.remove(c); });
            WIZARD_CLASS_MAP[estadoStep].forEach(function (c) { btn.classList.add(c); });
            renderWizardBadge(btn, estadoStep);
            if (!btn.dataset.boletimBound) {
                btn.dataset.boletimBound = '1';
                btn.addEventListener('click', function () {
                    var alvo = parseInt(btn.getAttribute('data-step-target') || '0', 10) - 1;
                    var passo = passosVisiveis()[alvo];
                    if (!estado || !passo) return;
                    estado.passo = passo.id;
                    renderAll();
                });
            }
            if (p) {
                var labelEl = btn.querySelector('.block.font-semibold');
                if (labelEl) labelEl.textContent = String(p.label || '').replace(/^\d+\.\s*/, '');
                var subEl = btn.querySelector('.block.text-xs');
                if (subEl) subEl.textContent = p.sub || '';
            }
            btn.classList.toggle('hidden', !p);
        });
        stepsEl.querySelectorAll('[data-connector-after]').forEach(function (el) {
            var n = parseInt(el.getAttribute('data-connector-after') || '0', 10);
            var done = n <= curIdx;
            el.style.display = n >= vis.length ? 'none' : '';
            el.classList.toggle('bg-green-400', done);
            el.classList.toggle('bg-gray-200', !done);
        });
    }

    function renderSteps() {
        var vis = passosVisiveis();
        var cur = estado ? estado.passo : 'inicio';
        if (!vis.some(function (p) { return p.id === cur; })) {
            cur = vis.length ? vis[vis.length - 1].id : 'inicio';
            if (estado) estado.passo = cur;
        }
        var curIdx = 0;
        vis.forEach(function (p, i) { if (p.id === cur) curIdx = i; });
        if (pageMode) {
            atualizarStepsPageMode(vis, curIdx);
        } else if (stepsEl) {
            stepsEl.innerHTML = '';
            vis.forEach(function (p, i) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'bw-step-btn' + (p.id === cur ? ' active' : (i < curIdx ? ' done' : ''));
                b.textContent = (i + 1) + '. ' + String(p.label || '').replace(/^\d+\.\s*/, '');
                b.addEventListener('click', function () {
                    if (!estado) return;
                    estado.passo = p.id;
                    renderAll();
                });
                stepsEl.appendChild(b);
            });
        }
        btnVoltar.disabled = curIdx <= 0;
        var label = cur === 'revisar' ? 'Concluir e aplicar' : (cur === 'formula' ? 'Salvar e continuar' : 'Continuar');
        if (pageMode) {
            btnAvancar.innerHTML = esc(label) + (cur === 'revisar' ? ' <i class="fa-solid fa-check ml-2"></i>' : ' <i class="fa-solid fa-arrow-right ml-2"></i>');
        } else {
            btnAvancar.textContent = label;
        }
        var sub = document.getElementById('boletim-wizard-subtitulo');
        if (sub) {
            sub.textContent = ehBoletimComposto()
                ? 'Escolha as médias e as faltas de cada bimestre (eventos já criados)'
                : 'Adicione blocos de cálculo, dê um nome, arraste a ordem e salve';
        }
    }

    function cardHtml(selected, title, desc) {
        return '<div class="bw-card' + (selected ? ' selected' : '') + '"><div class="text-sm font-semibold text-gray-900">' + title + '</div>'
            + (desc ? '<p class="text-xs text-gray-500 mt-1">' + desc + '</p>' : '') + '</div>';
    }

    function renderBody() {
        if (!estado) {
            bodyEl.innerHTML = '<p class="text-sm text-gray-500">Carregando…</p>';
            return;
        }
        var html = '';
        var passo = estado.passo;

        if (passo === 'inicio') {
            html += '<p class="text-sm text-gray-700">Como deseja começar?</p><div class="grid gap-3 mt-2">';
            html += '<div data-origem="zero">' + cardHtml(estado.origem === 'zero', 'Do zero', 'Você escolhe as peças e como cada uma entra na média.') + '</div>';
            html += '<div data-origem="modelo">' + cardHtml(estado.origem === 'modelo', 'Modelo pronto', 'Começa com uma combinação comum da escola.') + '</div>';
            html += '<div data-origem="clonar">' + cardHtml(estado.origem === 'clonar', 'Clonar outro evento', 'Copia a regra de um bimestre/evento já existente e só ajusta o período.') + '</div>';
            html += '</div>';

            if (estado.origem === 'modelo') {
                html += '<p class="text-sm font-medium text-gray-800 mt-4">Escolha o modelo</p><div class="grid gap-2 mt-2">';
                (catalogo.modelos || []).forEach(function (m) {
                    html += '<div data-modelo="' + m.key + '">' + cardHtml(estado.modelo_key === m.key, m.titulo, m.descricao) + '</div>';
                });
                html += '</div>';
            }
            if (estado.origem === 'clonar') {
                html += '<p class="text-sm font-medium text-gray-800 mt-4">Evento de origem</p><select id="bw-clonar-regra" class="mt-2 w-full h-10 border border-gray-300 rounded-lg px-3 text-sm">';
                html += '<option value="">Selecione…</option>';
                (catalogo.regras || []).forEach(function (r) {
                    var label = r.nome || ('Evento #' + r.id);
                    if (r.bimestre) label += ' · ' + r.bimestre + 'º bim';
                    if (r.ano_letivo) label += ' · ' + r.ano_letivo;
                    html += '<option value="' + Number(r.id) + '"' + (String(estado.clonar_regra_id || '') === String(r.id) ? ' selected' : '') + '>' + esc(label) + '</option>';
                });
                html += '</select>';
                html += '<div class="grid grid-cols-2 gap-3 mt-3"><div><label class="text-xs text-gray-600">Novo bimestre</label>'
                    + '<input id="bw-clonar-bim" type="number" min="1" max="4" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + (estado.bimestre || 1) + '"></div>'
                    + '<div><label class="text-xs text-gray-600">Ano letivo</label>'
                    + '<input id="bw-clonar-ano" type="number" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + (estado.ano_letivo || '') + '"></div></div>';
            }
        }

        if (passo === 'identidade') {
            html += '<div class="grid gap-3">';
            html += '<div><label class="text-sm font-medium text-gray-700">Nome do evento</label>'
                + '<input id="bw-nome" type="text" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + esc(estado.nome) + '"></div>';
            html += '<div class="grid grid-cols-2 gap-3"><div><label class="text-sm font-medium text-gray-700">Ano letivo</label>'
                + '<input id="bw-ano" type="number" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + (estado.ano_letivo || '') + '"></div>';
            if (ehBoletimComposto()) {
                html += '<div><label class="text-sm font-medium text-gray-700">Bimestre</label>'
                    + '<p class="mt-1 h-10 flex items-center text-sm text-gray-600">Ano todo (1º ao 4º)</p></div></div>';
            } else {
                html += '<div><label class="text-sm font-medium text-gray-700">Bimestre</label>'
                    + '<select id="bw-bim" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm">'
                    + [1,2,3,4].map(function (b) { return '<option value="' + b + '"' + (Number(estado.bimestre) === b ? ' selected' : '') + '>' + b + 'º</option>'; }).join('')
                    + '</select></div></div>';
            }
            html += '<div class="grid grid-cols-2 gap-3"><div><label class="text-sm font-medium text-gray-700">Exibir em</label>'
                + '<select id="bw-exibir" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm">'
                + '<option value="notas"' + (estado.exibir_em === 'notas' ? ' selected' : '') + '>Notas</option>'
                + '<option value="boletim"' + (estado.exibir_em === 'boletim' ? ' selected' : '') + '>Boletim</option></select></div>'
                + '<div><label class="text-sm font-medium text-gray-700">Nota mínima</label>'
                + '<input id="bw-nota-min" type="number" step="0.1" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + (estado.nota_minima_aprovacao != null ? estado.nota_minima_aprovacao : 7) + '"></div></div>';
            html += '<div><label class="text-sm font-medium text-gray-700">Arredondamento</label>'
                + '<select id="bw-round" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm">'
                + '<option value="half"' + (estado.round_mode === 'half' ? ' selected' : '') + '>Faixa .00 / .50 / próximo inteiro</option>'
                + '<option value="none"' + (estado.round_mode === 'none' ? ' selected' : '') + '>Sem arredondamento especial</option></select></div>';
            html += '</div>';
            if (ehBoletimComposto()) html += htmlFontesBimestres();
            html += htmlSeriesTurmas();
        }

        if (passo === 'pecas') {
            html += '<p class="text-sm text-gray-700">Quais notas entram neste boletim?</p>';
            html += '<p class="text-xs text-gray-500 mt-1">Marque o que entra e o bimestre de cada peça. Ordem das colunas e fórmulas ficam no passo <strong>Exibir</strong>.</p>';
            html += '<div class="grid gap-3 mt-3">';
            (catalogo.pecas || []).forEach(function (p) {
                var on = (estado.pecas || []).indexOf(p.key) >= 0;
                var opts = (estado.pecas_opcoes && estado.pecas_opcoes[p.key]) ? estado.pecas_opcoes[p.key] : pecasOpcoesPadraoJs(p.key);
                html += '<div class="bw-card' + (on ? ' selected' : '') + '">';
                html += '<label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" class="mt-1 bw-peca" value="' + p.key + '"' + (on ? ' checked' : '') + '>'
                    + '<span><span class="text-sm font-semibold text-gray-900">' + p.label + '</span><span class="block text-xs text-gray-500">' + (p.hint || '') + '</span></span></label>';
                if (on) {
                    html += '<div class="mt-3 pl-7 space-y-3 border-t border-gray-100 pt-3">';
                    html += htmlBimestresPeca(p.key, opts);
                    if (p.key === 'jornada') html += htmlJornadaPeca(opts);
                    var calc = opts.calc_type || calcTypePadrao(p.key);
                    if (!pecaMostraNotaEvento(p.key) && calc === 'ultima') calc = 'media';
                    html += '<div><span class="text-xs font-medium text-gray-600">Como calcular as notas desta peça</span>'
                        + '<div class="mt-1 flex flex-wrap gap-3 text-sm">';
                    if (pecaMostraNotaEvento(p.key)) {
                        html += '<label class="inline-flex items-center gap-1.5"><input type="radio" class="bw-peca-calc" name="bw-calc-' + p.key + '" data-peca="' + p.key + '" value="ultima"' + (calc === 'ultima' ? ' checked' : '') + '> Nota do evento</label>';
                    }
                    html += '<label class="inline-flex items-center gap-1.5"><input type="radio" class="bw-peca-calc" name="bw-calc-' + p.key + '" data-peca="' + p.key + '" value="media"' + (calc === 'media' ? ' checked' : '') + '> Média</label>'
                        + '<label class="inline-flex items-center gap-1.5"><input type="radio" class="bw-peca-calc" name="bw-calc-' + p.key + '" data-peca="' + p.key + '" value="soma"' + (calc === 'soma' ? ' checked' : '') + '> Somatória</label>'
                        + '</div></div>';
                    if (pecaPermiteAcertos(p.key)) {
                        html += '<label class="inline-flex items-start gap-2 text-sm text-gray-700">'
                            + '<input type="checkbox" class="mt-0.5 bw-peca-perc" data-peca="' + p.key + '"' + (opts.usar_percentual ? ' checked' : '') + '>'
                            + '<span><strong>Calcular por acertos/questões</strong><span class="block text-xs text-gray-500">Converte desempenho da prova em nota 0–10.</span></span></label>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            });
            html += '</div>';
        }

        if (passo === 'formula') {
            if (estado.origem === 'clonar') {
                html += '<p class="text-sm text-gray-600">No modo clonar, a ordem e as fórmulas do evento de origem são mantidas. Avance para revisar.</p>';
            } else {
                html += '<p class="text-sm text-gray-700">Como o boletim <strong>aparece</strong></p>';
                html += '<p class="text-xs text-gray-500 mt-1">Em cima: adicione um bloco de cálculo, dê o nome e monte a fórmula. Arraste as colunas. Ao terminar, clique em <strong>Salvar bloco</strong> e depois em <strong>Salvar e continuar</strong>.</p>';
                html += '<div id="bw-colunas-wrap" class="mt-3"></div>';
                html += '<div id="bw-formula-editor" class="' + (estado.bloco_calc ? '' : 'hidden') + ' mt-4 rounded-xl border border-amber-200 bg-amber-50/40 p-3">';
                html += '<div id="bw-excecao-banner" class="hidden mb-3 rounded-xl border-2 border-indigo-400 bg-indigo-600 text-white px-3 py-2.5"></div>';
                html += '<div class="flex flex-wrap items-end justify-between gap-2 mb-2">';
                html += '<label class="flex-1 min-w-[12rem]"><span class="text-xs font-medium text-amber-950">Nome do bloco</span>';
                html += '<input id="bw-formula-bloco-nome-input" type="text" maxlength="60" class="mt-1 w-full h-10 border border-amber-200 rounded-lg px-3 text-sm bg-white" value="' + esc(nomeBlocoCalc(estado.bloco_calc || '')) + '" placeholder="Ex.: Média Bim">';
                html += '</label>';
                html += '<button type="button" id="bw-formula-salvar" class="h-10 px-4 text-sm font-medium rounded-lg bg-amber-600 text-white hover:bg-amber-700">Salvar bloco</button>';
                html += '</div>';
                html += '<p id="bw-formula-bloco-titulo" class="text-sm font-semibold text-amber-950">Cálculo de <span id="bw-formula-bloco-nome">' + esc(nomeBlocoCalc(estado.bloco_calc || 'média')) + '</span></p>';
                html += '<p id="bw-formula-bloco-ajuda" class="text-xs text-amber-900/80 mt-0.5 mb-2">Peças e sinais viram a fórmula desta coluna. Ex.: maior ( Média Bim , ENAC ).</p>';
                html += '<p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide mb-1.5">Usar no cálculo</p>';
                html += '<div id="bw-formula-pecas-pal" class="bw-palette flex flex-wrap gap-2"></div>';
                html += '<p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide mt-3 mb-1.5">Sinais</p>';
                html += '<div class="bw-palette flex flex-wrap gap-2 items-center">';
                operadoresFormula().forEach(function (op) { html += chipHtml(op, -1, true); });
                html += '<label class="inline-flex items-center gap-1.5 text-xs text-gray-600 ml-1">Número';
                html += '<input id="bw-formula-num" type="text" inputmode="decimal" class="h-8 w-16 border rounded-md px-2 text-sm" placeholder="2">';
                html += '<button type="button" id="bw-formula-num-add" class="h-8 px-2 text-xs rounded-md border border-gray-300 text-gray-700 hover:bg-gray-50">Inserir</button></label></div>';
                html += '<div class="mt-2 flex flex-wrap gap-2">';
                html += '<button type="button" id="bw-formula-media" class="px-2.5 py-1.5 text-xs rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50">Média das peças</button>';
                html += '<button type="button" id="bw-formula-max" class="px-2.5 py-1.5 text-xs rounded-lg border border-amber-200 text-amber-800 hover:bg-amber-50">Maior entre as duas primeiras</button>';
                html += '<button type="button" id="bw-formula-limpar" class="px-2.5 py-1.5 text-xs rounded-lg border border-gray-300 text-gray-600 hover:bg-gray-50">Limpar</button></div>';
                html += '<p id="bw-formula-canvas-label" class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide mt-3 mb-1.5">Fórmula desta coluna</p>';
                html += '<div id="bw-formula-canvas" class="bw-canvas">' + htmlCanvasTokens() + '</div>';
                html += '<p id="bw-formula-preview" class="mt-2 text-sm font-mono text-slate-800 break-all">' + esc(compilarTokensPreview(estado.formula_tokens || []) || 'Clique nos blocos acima.') + '</p>';
                html += '<div class="mt-4 pt-3 border-t border-amber-200/80">';
                html += '<p class="text-[11px] font-semibold text-slate-600 uppercase tracking-wide mb-1">Exceção só nesta matéria</p>';
                html += '<p class="text-xs text-gray-500 mb-2">Se uma matéria não tem um tipo de prova (ex.: sem semanal), some só o que ela tem — a fórmula geral continua nas outras.</p>';
                html += '<div class="flex flex-wrap items-end gap-2 mb-2">';
                html += '<label class="text-xs text-gray-600">Matéria<select id="bw-excecao-materia" class="mt-1 block h-9 border rounded-md px-2 text-sm min-w-[12rem]"><option value="0">Escolher matéria…</option></select></label>';
                html += '<button type="button" id="bw-excecao-add" class="h-9 px-3 text-xs font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Usar outra fórmula nesta matéria</button>';
                html += '</div>';
                html += '<div id="bw-excecoes-lista" class="space-y-1.5"></div>';
                html += '</div>';
                html += '</div>';
                html += '<div id="bw-preview-wrap" class="mt-4"></div>';
            }
        }

        if (passo === 'publico') {
            html += '<p class="text-sm text-gray-700">Matérias que entram no boletim</p>';
            html += '<p class="text-xs text-gray-500 mb-2">Série e turma já foram no passo Identidade. Aqui: quais matérias entram e se junta linha. Vazio = todas as matérias.</p>';
            html += '<div class="grid sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto border rounded-lg p-3 mb-4">';
            (catalogo.materias || []).forEach(function (m) {
                var on = (estado.materias_ids || []).some(function (id) { return Number(id) === Number(m.id); });
                html += '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="bw-materia rounded border-gray-300 text-indigo-600" value="' + m.id + '"' + (on ? ' checked' : '') + '> ' + esc(m.nome) + '</label>';
            });
            html += '</div>';

            var gl = estado.grupo_linha || grupoLinhaPadrao();
            html += '<div class="mt-4 border rounded-lg p-3 bg-indigo-50/60 space-y-3">';
            html += '<label class="inline-flex items-start gap-2 text-sm text-gray-800">';
            html += '<input type="checkbox" id="bw-materia-unica" class="mt-0.5 rounded border-gray-300 text-indigo-600"' + (estado.materia_unica ? ' checked' : '') + '>';
            html += '<span><strong>Juntar matérias iguais</strong><span class="block text-xs text-gray-600 font-normal">Mesma matéria, professores diferentes (ex.: dois Português). Vale para todas as peças. Não junta Português com Literatura.</span></span></label>';
            html += '<label class="inline-flex items-start gap-2 text-sm text-gray-800">';
            html += '<input type="checkbox" id="bw-grupo-ativo" class="mt-0.5 rounded border-gray-300 text-indigo-600"' + (gl.ativo ? ' checked' : '') + '>';
            html += '<span><strong>Linha única no boletim</strong><span class="block text-xs text-gray-600 font-normal">Junta matérias diferentes numa linha só. Ex.: Português + Literatura + Gramática → “Linguagem Português”.</span></span></label>';
            if (gl.ativo) {
                html += '<div><label class="text-xs text-gray-600">Nome da linha</label>';
                html += '<input id="bw-grupo-nome" type="text" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" placeholder="Linguagem Português" value="' + esc(gl.nome || '') + '"></div>';
                html += '<div><span class="text-xs font-medium text-gray-600">Como juntar as notas dessas matérias</span>';
                html += '<div class="mt-1 flex flex-wrap gap-3 text-sm">';
                html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-grupo-modo" class="bw-grupo-modo" value="media"' + (gl.modo !== 'soma' ? ' checked' : '') + '> Média</label>';
                html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-grupo-modo" class="bw-grupo-modo" value="soma"' + (gl.modo === 'soma' ? ' checked' : '') + '> Soma</label>';
                html += '</div></div>';
                html += '<p class="text-xs text-gray-500">Marque as matérias que entram nessa linha (pelo menos duas).</p>';
                html += '<div class="grid sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto border rounded-lg p-3 bg-white">';
                (catalogo.materias || []).forEach(function (m) {
                    var on = (gl.materias_ids || []).some(function (id) { return Number(id) === Number(m.id); });
                    html += '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="bw-grupo-materia rounded border-gray-300 text-indigo-600" value="' + m.id + '"' + (on ? ' checked' : '') + '> ' + esc(m.nome) + '</label>';
                });
                if (!(catalogo.materias || []).length) {
                    html += '<p class="text-xs text-gray-500">Nenhuma matéria cadastrada.</p>';
                }
                html += '</div>';
            }
            html += '</div>';
        }

        if (passo === 'revisar') {
            html += '<p class="text-sm text-gray-700">Confira o <strong>exemplo</strong> do boletim</p>';
            html += '<p class="text-xs text-gray-500 mt-1">' + (ehBoletimComposto()
                ? 'Layout oficial: 1º–4º bimestre (Média e Faltas) e FINAL (Média, Rec., Faltas, Resultado). Dados fictícios.'
                : 'Dados fictícios. Para mudar ordem ou fórmula, volte em <strong>Exibir</strong>.') + '</p>';
            html += '<div id="bw-preview-wrap" class="mt-4"></div>';
            html += '<p class="text-xs text-indigo-700 mt-4">Se estiver certo, clique em <strong>Concluir e aplicar</strong> e depois em <strong>Salvar evento</strong>.</p>';
        }

        bodyEl.innerHTML = html;
        bindBodyEvents();
        if (passo === 'revisar' || passo === 'formula') renderRevisarDinamico();
        if (passo === 'formula' && estado.bloco_calc) abrirFormulaBloco(estado.bloco_calc);
    }

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function bindBodyEvents() {
        bodyEl.querySelectorAll('[data-origem]').forEach(function (el) {
            el.addEventListener('click', function () {
                estado.origem = el.getAttribute('data-origem');
                if (estado.origem !== 'modelo') estado.modelo_key = '';
                if (estado.origem !== 'clonar') estado.clonar_regra_id = null;
                if (estado.origem === 'zero' || estado.origem === 'modelo') {
                    estado.rascunho_preservado = null;
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('[data-modelo]').forEach(function (el) {
            el.addEventListener('click', function () {
                estado.modelo_key = el.getAttribute('data-modelo');
                var m = (catalogo.modelos || []).find(function (x) { return x.key === estado.modelo_key; });
                if (m) {
                    estado.pecas = (m.pecas || []).slice();
                    estado.formula_preset = m.formula_preset || 'media_simples';
                    estado.pecas_opcoes = {};
                    estado.materia_unica = estado.pecas.indexOf('semanal') >= 0 ? 1 : 0;
                    estado.pecas.forEach(function (k) {
                        estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
                        if (m.papeis && m.papeis[k]) estado.pecas_opcoes[k].papel = m.papeis[k];
                        else if (m.formula_preset === 'enac_so_melhora' && k === 'enac') estado.pecas_opcoes[k].papel = 'so_melhora';
                        else if (m.formula_preset === 'parcial_depois_final' && k === 'enac') estado.pecas_opcoes[k].papel = 'depois';
                    });
                    estado.rascunho_preservado = null;
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca').forEach(function (el) {
            el.addEventListener('change', function () {
                var tinhaSemanal = (estado.pecas || []).indexOf('semanal') >= 0;
                estado.pecas = [];
                bodyEl.querySelectorAll('.bw-peca:checked').forEach(function (c) { estado.pecas.push(c.value); });
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!tinhaSemanal && estado.pecas.indexOf('semanal') >= 0) {
                    estado.materia_unica = 1;
                }
                estado.pecas.forEach(function (k) {
                    if (!estado.pecas_opcoes[k]) {
                        estado.pecas_opcoes[k] = pecasOpcoesPadraoJs(k);
                    }
                    if (!pecaPermiteAcertos(k)) estado.pecas_opcoes[k].usar_percentual = 0;
                });
                aplicarMateriaUnicaNasPecas();
                filtrarTokensOrfaos();
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-calc').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].calc_type = el.value;
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-perc').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].usar_percentual = el.checked ? 1 : 0;
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-papel').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].papel = el.value;
                syncPresetDosPapeis();
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('[data-formula]').forEach(function (el) {
            el.addEventListener('click', function () {
                estado.formula_preset = el.getAttribute('data-formula');
                aplicarPresetNosPapeis(estado.formula_preset);
                marcarEdicaoManual();
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-depois').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                if (el.checked) {
                    estado.pecas_opcoes[peca].papel = estado.formula_preset === 'enac_so_melhora' ? 'so_melhora' : 'depois';
                } else {
                    estado.pecas_opcoes[peca].papel = 'media';
                }
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        var clonarSel = document.getElementById('bw-clonar-regra');
        if (clonarSel) {
            clonarSel.addEventListener('change', function () {
                estado.clonar_regra_id = parseInt(clonarSel.value, 10) || null;
                estado.materia_unica_tocada = false;
                agendarMontar();
            });
        }
        ['bw-clonar-bim', 'bw-clonar-ano', 'bw-nome', 'bw-ano', 'bw-bim', 'bw-exibir', 'bw-nota-min', 'bw-round'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', function () {
                var anoAntes = estado.ano_letivo;
                var exibirAntes = estado.exibir_em;
                syncIdentidadeFromDom();
                if (id === 'bw-exibir' && estado.exibir_em !== exibirAntes) {
                    aplicarExibirEm(estado.exibir_em, true);
                    agendarMontar();
                    return;
                }
                if (id === 'bw-ano' && Number(anoAntes) !== Number(estado.ano_letivo)) {
                    var idsOk = {};
                    turmasVisiveis().forEach(function (t) { idsOk[Number(t.id)] = true; });
                    estado.turmas_ids = (estado.turmas_ids || []).filter(function (idTurma) { return idsOk[Number(idTurma)]; });
                    if (ehBoletimComposto()) {
                        estado.fontes_bimestres = { 1: 0, 2: 0, 3: 0, 4: 0 };
                        estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
                        sugerirFontesBimestres();
                    }
                    renderAll();
                }
                agendarMontar();
            });
            el.addEventListener('input', function () {
                syncIdentidadeFromDom();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-bim').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = pecasOpcoesPadraoJs(peca);
                estado.pecas_opcoes[peca].bimestres = [];
                bodyEl.querySelectorAll('.bw-peca-bim[data-peca="' + peca + '"]:checked').forEach(function (c) {
                    estado.pecas_opcoes[peca].bimestres.push(parseInt(c.value, 10));
                });
                if (peca === 'jornada') {
                    estado.jornada_modo = 'bimestre';
                    estado.jornada_bimestres = estado.pecas_opcoes[peca].bimestres.slice();
                    if (!estado.jornada_bimestres.length && estado.bimestre >= 1 && estado.bimestre <= 4) {
                        estado.jornada_bimestres = [Number(estado.bimestre)];
                    }
                    renderAll();
                }
                marcarEdicaoManual();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-jornada-nota-modo').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.jornada_nota_modo = el.value;
                if (estado.jornada_nota_modo === 'faixas' && !(estado.jornada_faixas || []).length) {
                    estado.jornada_faixas = faixasJornadaPadrao();
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-jornada-faixa-nota').forEach(function (el) {
            el.addEventListener('input', function () {
                var idx = parseInt(el.getAttribute('data-idx'), 10);
                if (!estado.jornada_faixas || !estado.jornada_faixas[idx]) return;
                var n = parseFloat(String(el.value).replace(',', '.'));
                estado.jornada_faixas[idx].nota = Number.isFinite(n) ? n : el.value;
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-jornada-id').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.jornada_ids = [];
                bodyEl.querySelectorAll('.bw-jornada-id:checked').forEach(function (c) {
                    estado.jornada_ids.push(parseInt(c.value, 10));
                });
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-materia').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.materias_ids = [];
                bodyEl.querySelectorAll('.bw-materia:checked').forEach(function (c) {
                    estado.materias_ids.push(parseInt(c.value, 10));
                });
                agendarMontar();
            });
        });
        var materiaUnicaEl = document.getElementById('bw-materia-unica');
        if (materiaUnicaEl) {
            materiaUnicaEl.addEventListener('change', function () {
                estado.materia_unica = materiaUnicaEl.checked ? 1 : 0;
                estado.materia_unica_tocada = true;
                aplicarMateriaUnicaNasPecas();
                agendarMontar();
            });
        }
        var grupoAtivoEl = document.getElementById('bw-grupo-ativo');
        if (grupoAtivoEl) {
            grupoAtivoEl.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.ativo = !!grupoAtivoEl.checked;
                renderAll();
                agendarMontar();
            });
        }
        var grupoNomeEl = document.getElementById('bw-grupo-nome');
        if (grupoNomeEl) {
            grupoNomeEl.addEventListener('input', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.nome = grupoNomeEl.value;
                agendarMontar();
            });
        }
        bodyEl.querySelectorAll('.bw-grupo-modo').forEach(function (el) {
            el.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.modo = el.value === 'soma' ? 'soma' : 'media';
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-grupo-materia').forEach(function (el) {
            el.addEventListener('change', function () {
                if (!estado.grupo_linha) estado.grupo_linha = grupoLinhaPadrao();
                estado.grupo_linha.materias_ids = [];
                bodyEl.querySelectorAll('.bw-grupo-materia:checked').forEach(function (c) {
                    estado.grupo_linha.materias_ids.push(parseInt(c.value, 10));
                });
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-serie').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.series_ids = [];
                bodyEl.querySelectorAll('.bw-serie:checked').forEach(function (c) {
                    estado.series_ids.push(parseInt(c.value, 10));
                });
                var idsOk = {};
                turmasVisiveis().forEach(function (t) { idsOk[Number(t.id)] = true; });
                estado.turmas_ids = (estado.turmas_ids || []).filter(function (id) { return idsOk[Number(id)]; });
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-turma').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.turmas_ids = [];
                bodyEl.querySelectorAll('.bw-turma:checked').forEach(function (c) {
                    estado.turmas_ids.push(parseInt(c.value, 10));
                });
                agendarMontar();
            });
        });
        bindFormulaBuilderOnce();
        bodyEl.querySelectorAll('.bw-fonte-bim').forEach(function (el) {
            el.addEventListener('change', function () {
                var b = Number(el.getAttribute('data-bim') || 0);
                if (b < 1 || b > 4) return;
                if (!estado.fontes_bimestres || typeof estado.fontes_bimestres !== 'object') {
                    estado.fontes_bimestres = { 1: 0, 2: 0, 3: 0, 4: 0 };
                }
                estado.fontes_bimestres[b] = parseInt(el.value, 10) || 0;
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-fonte-faltas').forEach(function (el) {
            el.addEventListener('change', function () {
                var b = Number(el.getAttribute('data-bim') || 0);
                if (b < 1 || b > 4) return;
                if (!estado.fontes_faltas || typeof estado.fontes_faltas !== 'object') {
                    estado.fontes_faltas = { 1: 0, 2: 0, 3: 0, 4: 0 };
                }
                estado.fontes_faltas[b] = parseInt(el.value, 10) || 0;
                agendarMontar();
            });
        });
    }

    function syncIdentidadeFromDom() {
        var map = {
            'bw-clonar-bim': 'bimestre',
            'bw-clonar-ano': 'ano_letivo',
            'bw-nome': 'nome',
            'bw-ano': 'ano_letivo',
            'bw-bim': 'bimestre',
            'bw-exibir': 'exibir_em',
            'bw-nota-min': 'nota_minima_aprovacao',
            'bw-round': 'round_mode',
        };
        Object.keys(map).forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            var key = map[id];
            if (key === 'bimestre' || key === 'ano_letivo') estado[key] = parseInt(el.value, 10) || 0;
            else if (key === 'nota_minima_aprovacao') estado[key] = parseFloat(el.value);
            else estado[key] = el.value;
        });
    }

    function renderResumo(resumo, erros) {
        resumoEl.textContent = resumo || 'Monte as escolhas à esquerda para ver o resumo.';
        if (erros && erros.length) {
            errosEl.classList.remove('hidden');
            errosEl.textContent = erros.join(' ');
        } else {
            errosEl.classList.add('hidden');
            errosEl.textContent = '';
        }
    }

    function renderAll() {
        garantirEstado();
        if (ehBoletimComposto() && ['pecas', 'formula'].indexOf(estado.passo) >= 0) {
            estado.passo = 'identidade';
        }
        if (estado.modo === 'editar' && estado.origem === 'formulario' && estado.passo === 'inicio') {
            estado.passo = 'revisar';
        }
        renderSteps();
        renderBody();
    }

    function agendarMontar() {
        if (montarTimer) clearTimeout(montarTimer);
        montarTimer = setTimeout(montarAgora, 280);
    }

    function montarAgora() {
        garantirEstado();
        aplicarMateriaUnicaNasPecas();
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('wizard_estado', JSON.stringify(estado));
        return fetch(urlWizardMontar, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json();         }).then(function (j) {
            if (!j || !j.success) {
                renderResumo((j && j.resumo) || 'Não deu para montar o rascunho.', (j && j.erros) || [(j && j.error) || 'Falha ao montar.']);
                if (estado && (estado.passo === 'formula' || estado.passo === 'revisar')) renderRevisarDinamico();
                return j || null;
            }
            if (j.estado) {
                estado = j.estado;
                garantirEstado();
            }
            rascunhoAtual = j.rascunho || null;
            previewAtual = j.preview || previewAtual;
            formulasDisp = j.formulas_disponiveis || formulasDisp;
            renderResumo(j.resumo, j.erros || []);
            var materiaUnicaEl = document.getElementById('bw-materia-unica');
            if (materiaUnicaEl) materiaUnicaEl.checked = !!estado.materia_unica;
            if (estado && (estado.passo === 'formula' || estado.passo === 'revisar')) {
                renderRevisarDinamico();
            }
            if (estado && estado.passo === 'formula' && estado.bloco_calc) {
                renderFormulaCanvas();
                atualizarBannerExcecao();
                renderExcecoesMateria();
                var pal = document.getElementById('bw-formula-pecas-pal');
                if (pal) pal.innerHTML = htmlPalettePecasExibir();
            }
            return j;
        }).catch(function () {
            renderResumo('Não deu para falar com o servidor.', ['Recarregue a página (Cmd+Shift+R) e tente de novo.']);
            if (estado && (estado.passo === 'formula' || estado.passo === 'revisar')) renderRevisarDinamico();
            return null;
        });
    }

    function abrirWizard() {
        if (!pageMode) {
            overlay.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
        garantirEstado();
        renderAll();
        if (iniciado) return;
        iniciado = true;
        if (dadosIniciais.estado) {
            renderResumo(dadosIniciais.resumo, dadosIniciais.erros || []);
            return;
        }
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('regra_id', String(regraId));
        var estadoFormularioPost = estadoFormularioParaPost();
        if (estadoFormularioPost) fd.append('estado_formulario', estadoFormularioPost);
        fetch(urlWizardInicio, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) {
            return r.text().then(function (t) {
                var j = null;
                try { j = JSON.parse(t); } catch (e) { j = null; }
                if (!j) {
                    return { success: false, error: 'Catálogo indisponível (HTTP ' + r.status + ').' };
                }
                return j;
            });
        }).then(function (j) {
            if (!j || !j.success) {
                renderResumo((j && j.error) || 'Catálogo parcial. Recarregue a página se algo não aparecer.', []);
                return;
            }
            catalogo = j.catalogo || catalogo;
            var rascunhoChat = (estado && estado.origem === 'chat') ? rascunhoAtual : null;
            estado = j.estado || garantirEstado();
            if (rascunhoChat) {
                estado.origem = 'chat';
                estado.rascunho_preservado = rascunhoChat;
                estado.passo = 'revisar';
                rascunhoAtual = rascunhoChat;
            } else if (!rascunhoAtual) {
                rascunhoAtual = j.rascunho;
            }
            formulasDisp = j.formulas_disponiveis || formulasDisp;
            if (j.preview) previewAtual = j.preview;
            if (j.disponivel_ia === false) disponivelIa = false;
            renderAll();
            if (!rascunhoChat) {
                renderResumo(j.resumo, j.erros || []);
            }
        }).catch(function () {
            renderResumo('Catálogo não carregou.', ['Recarregue a página e tente de novo.']);
        });
    }

    function fecharWizard() {
        if (pageMode) {
            window.location.href = returnUrl;
            return;
        }
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function aplicarRascunhoNaConfiguracao(r) {
        if (!r || typeof r !== 'object') return false;
        if (document.getElementById('form-regra-boletim')) {
            aplicarRascunho(r);
            return true;
        }
        if (pageMode && urlSalvar) {
            var form = document.createElement('form');
            form.method = 'POST';
            form.action = urlSalvar;
            form.style.display = 'none';
            function add(name, value) {
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value == null ? '' : String(value);
                form.appendChild(input);
            }
            function addArray(name, values) {
                (Array.isArray(values) ? values : []).forEach(function (value) {
                    if (Number(value) > 0) add(name, value);
                });
            }
            add('_token', csrf);
            add('regra_id', r.modo === 'editar' && r.regra_id ? r.regra_id : (regraId > 0 ? regraId : 0));
            add('regra_nome', r.nome || 'Evento padrão da escola');
            add('regra_codigo', r.codigo || '');
            add('regra_descricao_curta', r.descricao_curta || '');
            add('formula_final', r.formula_final || '');
            add('formula_materias_json', r.formula_materias_json || '');
            add('exibir_em', r.exibir_em || 'boletim');
            add('ano_letivo', r.ano_letivo || (new Date()).getFullYear());
            add('bimestre', r.exibir_em === 'notas' ? (r.bimestre || '') : '');
            add('default_data_inicio', r.default_data_inicio || '');
            add('default_data_fim', r.default_data_fim || '');
            add('round_mode', r.round_mode || 'none');
            add('decimal_places', r.decimal_places || 2);
            add('nota_minima_aprovacao', r.nota_minima_aprovacao != null ? r.nota_minima_aprovacao : 6);
            if (r.usar_resultado_aprovacao == null || Number(r.usar_resultado_aprovacao) === 1) {
                add('usar_resultado_aprovacao', '1');
            }
            if (r.vis_aluno == null || Number(r.vis_aluno) === 1) add('vis_aluno', '1');
            if (r.vis_pais == null || Number(r.vis_pais) === 1) add('vis_pais', '1');
            if (r.vis_coordenacao == null || Number(r.vis_coordenacao) === 1) add('vis_coordenacao', '1');
            addArray('materias_ids[]', r.materias_ids);
            addArray('series_ids[]', r.series_ids);
            addArray('turmas_ids[]', r.turmas_ids);
            add('componentes_json', JSON.stringify(normalizarComponentesRascunho(r.componentes)));
            document.body.appendChild(form);
            form.submit();
            return true;
        }
        try {
            sessionStorage.setItem('boletim_assistente_rascunho', JSON.stringify(r));
            window.location.href = returnUrl;
            return true;
        } catch (e) {
            renderResumo('Não consegui levar o rascunho para a configuração.', ['Copie a receita e tente de novo.']);
            return false;
        }
    }

    function aplicarRascunhoSeOk(j, msgOk) {
        if (!j || !j.rascunho) {
            renderResumo((j && (j.error || (j.erros && j.erros[0]))) || 'Não consegui montar o evento.', ['Volte em Peças, marque bimestral/semanal e tente de novo.']);
            return false;
        }
        if (j.ok !== true) {
            var errs = (j.erros || []).join(' ');
            renderResumo(j.resumo, j.erros || []);
            return false;
        }
        aplicarRascunhoNaConfiguracao(j.rascunho);
        renderResumo(msgOk, []);
        return true;
    }

    function avancar() {
        garantirEstado();
        syncIdentidadeFromDom();
        var vis = passosVisiveis();
        var i = 0;
        vis.forEach(function (p, idx) { if (p.id === estado.passo) i = idx; });
        if (estado.passo === 'revisar') {
            montarAgora().then(function (j) {
                if (!aplicarRascunhoSeOk(j, pageMode ? 'Aplicado. Abrindo a configuração para salvar o evento.' : 'Aplicado no formulário. Revise os blocos e clique em Salvar evento.')) return;
                fecharWizard();
            });
            return;
        }
        if (estado.passo === 'formula' && estado.bloco_calc) {
            var inpNome = document.getElementById('bw-formula-bloco-nome-input');
            var nomeAv = String((inpNome && inpNome.value) || '').trim().slice(0, 60);
            if (!estado.nomes_blocos || typeof estado.nomes_blocos !== 'object') estado.nomes_blocos = {};
            if (nomeAv) estado.nomes_blocos[estado.bloco_calc] = nomeAv;
            gravarTokensBlocoAberto();
        }
        var next = vis[Math.min(i + 1, vis.length - 1)].id;
        if (estado.origem === 'clonar') {
            if (estado.passo === 'inicio') next = 'identidade';
            else if (estado.passo === 'identidade') next = 'publico';
            else if (estado.passo === 'publico') next = 'revisar';
        }
        estado.passo = next;
        renderAll();
        agendarMontar();
    }

    function voltar() {
        if (!garantirEstado()) return;
        var vis = passosVisiveis();
        var i = 0;
        vis.forEach(function (p, idx) { if (p.id === estado.passo) i = idx; });
        if (i <= 0) return;
        var prev = vis[i - 1].id;
        if (estado.origem === 'clonar') {
            if (estado.passo === 'revisar') prev = 'publico';
            else if (estado.passo === 'publico') prev = 'identidade';
            else if (estado.passo === 'identidade') prev = 'inicio';
        }
        estado.passo = prev;
        renderAll();
    }

    function enviarChat() {
        if (!chatInput) return;
        var texto = (chatInput.value || '').trim();
        if (!texto) return;

        if (!disponivelIa) {
            var pareceReceita = /#\s*RECEITA_BOLETIM\b/i.test(texto) || /\[\[componente\]\]/i.test(texto);
            if (!pareceReceita) {
                appendBubble('assistant', 'Sem TudiCoins, o chat só aplica receita colada. Use os passos à esquerda ou ative TudiCoins no Master.');
                return;
            }
        }

        chatInput.value = '';
        appendBubble('user', texto);

        if (enviando) {
            filaChat.push(texto);
            mostrarStatusFila('Ainda estou fechando o quadro. Já anotei o ajuste e aplico em seguida.');
            return;
        }
        historico.push({ role: 'user', content: texto });
        setChatStatus('Enviando… a IA pode levar até 1 minuto no quadro completo.');
        dispararChat(texto);
    }

    function dispararChat(texto) {
        enviando = true;
        removerStatusFila();
        setChatStatus('Montando o quadro… pode levar um minuto.');

        var loading = document.createElement('div');
        loading.className = 'mr-2 bg-white border border-gray-200 rounded-lg px-3 py-3 inline-flex gap-1.5';
        loading.innerHTML = '<span class="boletim-assistente-dot"></span><span class="boletim-assistente-dot"></span><span class="boletim-assistente-dot"></span>';
        msgsEl.appendChild(loading);
        msgsEl.scrollTop = msgsEl.scrollHeight;

        var fd = new FormData();
        try {
            fd.append('_token', csrf);
            fd.append('mensagem', texto);
            fd.append('regra_id', String(regraId));
            fd.append('historico', JSON.stringify(historico.slice(0, -1)));
            var baseFormulario = rascunhoAtual ? JSON.stringify(enxugarRascunhoParaChat(rascunhoAtual)) : estadoFormularioParaPost();
            if (baseFormulario) fd.append('estado_formulario', baseFormulario);
            var wizardPost = wizardEstadoParaPost();
            if (wizardPost) fd.append('wizard_estado', wizardPost);
        } catch (e) {
            if (loading.parentNode) loading.parentNode.removeChild(loading);
            enviando = false;
            setChatStatus('');
            appendBubble('assistant', 'Não consegui enviar o estado do formulário. Recarregue a página e tente de novo.');
            return;
        }

        var bubble = null;
        var textoStream = '';
        var ac = typeof AbortController !== 'undefined' ? new AbortController() : null;
        var timeoutId = setTimeout(function () {
            if (ac) ac.abort();
        }, 150000);

        function processarEvento(ev) {
            var payload = {};
            try { payload = JSON.parse(ev.data || '{}'); } catch (e) {}
            if (ev.event === 'chunk') {
                if (loading.parentNode) loading.parentNode.removeChild(loading);
                if (!bubble) bubble = appendBubble('assistant', '');
                textoStream += (payload.text || '');
                bubble.textContent = textoStream;
                msgsEl.scrollTop = msgsEl.scrollHeight;
            }
            if (ev.event === 'fase' && payload.fase === 'texto_pronto') {
                setChatStatus('Ainda gerando as colunas S1–S8. Espere o passo 7 (Revisar) à esquerda.');
            }
            if (ev.event === 'fase' && payload.fase === 'consultando') {
                setChatStatus('Consultando o catálogo da escola…');
            }
            if (ev.event === 'error') {
                if (loading.parentNode) loading.parentNode.removeChild(loading);
                setChatStatus('');
                appendBubble('assistant', payload.error || 'Não foi possível processar.');
            }
            if (ev.event === 'done') {
                if (loading.parentNode) loading.parentNode.removeChild(loading);
                removerStatusFila();
                setChatStatus('');
                var msg = (payload.mensagem || textoStream || 'Pronto.').trim();
                if (!bubble) bubble = appendBubble('assistant', msg);
                else bubble.textContent = msg;
                historico.push({ role: 'assistant', content: msg });
                if (payload.rascunho && payload.acao === 'rascunho') {
                    aplicarRascunhoNoWizard(payload.rascunho);
                    renderResumo(
                        'Ajuste do chat aplicado no formulário.\n\nRevise e salve o evento quando estiver ok.',
                        payload.erros || []
                    );
                }
            }
        }

        function parsearSse(buffer) {
            var eventos = [];
            var parts = buffer.split('\n\n');
            var restante = parts.pop() || '';
            parts.forEach(function (block) {
                var lines = block.split('\n');
                var eventName = 'message';
                var dataLines = [];
                lines.forEach(function (line) {
                    if (line.indexOf('event:') === 0) eventName = line.slice(6).trim();
                    else if (line.indexOf('data:') === 0) dataLines.push(line.slice(5).trim());
                });
                if (dataLines.length) eventos.push({ event: eventName, data: dataLines.join('\n') });
            });
            return { eventos: eventos, restante: restante };
        }

        var fetchOpts = {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/event-stream' },
            credentials: 'same-origin'
        };
        if (ac) fetchOpts.signal = ac.signal;

        fetch(urlMensagem, fetchOpts).then(function (res) {
            if (!res.ok || !res.body) {
                return res.text().then(function (t) {
                    var j = {};
                    try { j = JSON.parse(t); } catch (e) {}
                    if (loading.parentNode) loading.parentNode.removeChild(loading);
                    setChatStatus('');
                    appendBubble('assistant', (j && j.error) || ('Não consegui falar com a IA (HTTP ' + res.status + '). Tente de novo.'));
                });
            }
            var reader = res.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';
            var recebeuAlgo = false;
            function ler() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        if (buffer.trim()) parsearSse(buffer + '\n\n').eventos.forEach(processarEvento);
                        if (!bubble && !textoStream) {
                            if (loading.parentNode) loading.parentNode.removeChild(loading);
                            setChatStatus('');
                            appendBubble('assistant', recebeuAlgo
                                ? 'A resposta veio incompleta. Envie de novo em uma frase curta (ex.: monte o quadro semanal S1 a S8).'
                                : 'A IA não retornou resposta. Envie de novo — se a mensagem for muito longa, resuma o pedido.');
                        }
                        return;
                    }
                    recebeuAlgo = true;
                    buffer += decoder.decode(result.value, { stream: true });
                    var parsed = parsearSse(buffer);
                    buffer = parsed.restante;
                    parsed.eventos.forEach(processarEvento);
                    return ler();
                });
            }
            return ler();
        }).catch(function (err) {
            if (loading.parentNode) loading.parentNode.removeChild(loading);
            setChatStatus('');
            var abortou = err && (err.name === 'AbortError' || err.message === 'The user aborted a request.');
            appendBubble('assistant', abortou
                ? 'A IA demorou demais para fechar o quadro. Envie de novo o ajuste (ex.: tirar trabalho e participação).'
                : 'Falha de conexão. Recarregue a página e tente de novo.');
        }).finally(function () {
            clearTimeout(timeoutId);
            enviando = false;
            if (chatEnviar) chatEnviar.disabled = false;
            if (filaChat.length) {
                var proximo = filaChat.shift();
                historico.push({ role: 'user', content: proximo });
                if (filaChat.length) {
                    mostrarStatusFila('Aplicando o próximo ajuste…');
                } else {
                    removerStatusFila();
                }
                dispararChat(proximo);
                return;
            }
            removerStatusFila();
            if (chatInput) chatInput.focus();
        });
    }

    if (toggle) toggle.addEventListener('click', abrirWizard);
    if (btnFechar) btnFechar.addEventListener('click', fecharWizard);
    if (btnVoltar) btnVoltar.addEventListener('click', voltar);
    if (btnAvancar) btnAvancar.addEventListener('click', avancar);
    if (btnAplicar) {
        btnAplicar.addEventListener('click', function () {
            garantirEstado();
            renderResumo('Montando o quadro...', []);
            montarAgora().then(function (j) {
                aplicarRascunhoSeOk(j, pageMode ? 'Aplicado. Abrindo a configuração para salvar o evento.' : 'Aplicado no formulário (ainda não salvo). Salve o evento para valer na simulação.');
            });
        });
    }
    if (btnCopiar) {
        btnCopiar.addEventListener('click', function () {
            var base = rascunhoAtual || coletarEstadoFormulario();
            var fd = new FormData();
            fd.append('_token', csrf);
            fd.append('tool', 'formatar_receita');
            fd.append('estado_formulario', JSON.stringify(base));
            fetch(urlFerramenta, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (j) {
                    var rec = j && j.data && j.data.receita;
                    if (!rec) {
                        renderResumo((j && j.error) || 'Não foi possível gerar a receita.', []);
                        return;
                    }
                    return copiarTexto(rec).then(function () {
                        renderResumo('Receita copiada.', []);
                    });
                });
        });
    }
    overlay.addEventListener('click', function (ev) {
        if (pageMode) return;
        if (ev.target === overlay) fecharWizard();
    });
    if (pageMode) abrirWizard();
})();
</script>
