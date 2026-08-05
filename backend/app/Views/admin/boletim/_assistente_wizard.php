<?php
/**
 * Assistente guiado (wizard + chat) — Configuração de Boletins.
 * Variáveis: $csrfToken, $boletimAssistenteDisponivel, $selected_regra_id
 */
$boletimAssistenteDisponivel = !empty($boletimAssistenteDisponivel);
$assistenteRegraId = (int) ($selected_regra_id ?? $selectedRegraId ?? 0);
?>
<div id="boletim-assistente-root" class="fixed bottom-4 right-4 z-40 flex flex-col items-end gap-2"
     data-disponivel="<?= $boletimAssistenteDisponivel ? '1' : '0' ?>"
     data-url-mensagem="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/mensagem-stream', ENT_QUOTES, 'UTF-8') ?>"
     data-url-ferramenta="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/ferramenta', ENT_QUOTES, 'UTF-8') ?>"
     data-url-wizard-inicio="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/wizard/inicio', ENT_QUOTES, 'UTF-8') ?>"
     data-url-wizard-montar="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/wizard/montar', ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>"
     data-regra-id="<?= $assistenteRegraId ?>">

    <!-- Overlay wizard -->
    <div id="boletim-wizard-overlay" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-[1px] p-3 sm:p-6">
        <div class="mx-auto max-w-6xl h-[min(92vh,880px)] bg-white rounded-2xl shadow-2xl border border-gray-200 flex flex-col overflow-hidden">
            <div class="px-4 sm:px-5 py-3 border-b border-gray-100 bg-slate-50 flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <h2 class="text-base font-semibold text-gray-900">Assistente guiado do boletim</h2>
                    <p class="text-xs text-gray-500 truncate">Passos à esquerda · chat à direita (média/soma por peça, juntar matérias, ENAC…)</p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button type="button" id="boletim-wizard-copiar-receita" class="text-xs font-medium text-indigo-700 hover:bg-indigo-50 px-2 py-1.5 rounded-md">Copiar receita</button>
                    <button type="button" id="boletim-wizard-fechar" class="text-gray-400 hover:text-gray-700 p-1.5" aria-label="Fechar">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>

            <div class="flex-1 min-h-0 grid grid-cols-1 lg:grid-cols-5">
                <!-- Wizard -->
                <div class="lg:col-span-3 flex flex-col min-h-0 border-b lg:border-b-0 lg:border-r border-gray-100">
                    <nav id="boletim-wizard-steps" class="px-4 pt-3 pb-2 flex flex-wrap gap-1.5 border-b border-gray-50 bg-white"></nav>
                    <div id="boletim-wizard-body" class="flex-1 overflow-y-auto p-4 sm:p-5 space-y-4"></div>
                    <div class="px-4 py-3 border-t border-gray-100 bg-white flex flex-wrap items-center justify-between gap-2">
                        <button type="button" id="boletim-wizard-voltar" class="px-3 py-2 text-sm rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 disabled:opacity-40">Voltar</button>
                        <div class="flex gap-2">
                            <button type="button" id="boletim-wizard-aplicar" class="px-3 py-2 text-sm rounded-lg border border-indigo-200 text-indigo-700 hover:bg-indigo-50">Aplicar no formulário</button>
                            <button type="button" id="boletim-wizard-avancar" class="px-4 py-2 text-sm font-medium rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">Continuar</button>
                        </div>
                    </div>
                </div>

                <!-- Chat + resumo -->
                <div class="lg:col-span-2 flex flex-col min-h-0 bg-slate-50/80">
                    <div class="px-4 py-2 border-b border-gray-100">
                        <p class="text-xs font-semibold text-gray-700 uppercase tracking-wide">Resumo</p>
                        <pre id="boletim-wizard-resumo" class="mt-1 text-xs text-gray-600 whitespace-pre-wrap font-sans leading-relaxed max-h-36 overflow-y-auto">Monte as escolhas à esquerda para ver o resumo.</pre>
                        <div id="boletim-wizard-erros" class="mt-2 text-xs text-amber-800 hidden"></div>
                    </div>
                    <div id="boletim-wizard-mensagens" class="flex-1 overflow-y-auto p-3 space-y-2 text-sm"></div>
                    <form id="boletim-wizard-chat-form" class="border-t border-gray-100 p-2 flex gap-2 bg-white">
                        <textarea id="boletim-wizard-chat-input" rows="2" class="flex-1 text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-indigo-500 resize-none" placeholder="<?= $boletimAssistenteDisponivel ? 'Ex.: bimestral em soma · junta matérias iguais (Enter envia)' : 'Cole uma receita ou use os passos (IA desligada)' ?>"></textarea>
                        <button type="submit" id="boletim-wizard-chat-enviar" class="self-end px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">Enviar</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <button type="button" id="boletim-assistente-toggle"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full shadow-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Assistente guiado
    </button>
</div>

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
</style>

<script>
(function () {
    var root = document.getElementById('boletim-assistente-root');
    if (!root) return;

    var disponivelIa = root.getAttribute('data-disponivel') === '1';
    var urlMensagem = root.getAttribute('data-url-mensagem');
    var urlFerramenta = root.getAttribute('data-url-ferramenta');
    var urlWizardInicio = root.getAttribute('data-url-wizard-inicio');
    var urlWizardMontar = root.getAttribute('data-url-wizard-montar');
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
    var msgsEl = document.getElementById('boletim-wizard-mensagens');
    var chatForm = document.getElementById('boletim-wizard-chat-form');
    var chatInput = document.getElementById('boletim-wizard-chat-input');
    var chatEnviar = document.getElementById('boletim-wizard-chat-enviar');

    var PASSOS = [
        { id: 'inicio', label: '1. Começar' },
        { id: 'identidade', label: '2. Identidade' },
        { id: 'pecas', label: '3. Peças' },
        { id: 'periodo', label: '4. Período' },
        { id: 'formula', label: '5. Fórmula' },
        { id: 'publico', label: '6. Público' },
        { id: 'revisar', label: '7. Revisar' }
    ];

    var catalogo = { modelos: [], formulas: [], regras: [], series: [], pecas: [] };
    var estado = null;
    var rascunhoAtual = null;
    var formulasDisp = [];
    var historico = [];
    var enviando = false;
    var iniciado = false;
    var montarTimer = null;

    function coletarEstadoFormulario() {
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

    function setCheckboxes(name, ids) {
        var set = {};
        (ids || []).forEach(function (id) { set[String(id)] = true; });
        document.querySelectorAll('input[name="' + name + '"]').forEach(function (el) {
            el.checked = !!set[el.value];
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
        if (bimEl && r.bimestre != null) bimEl.value = r.bimestre;
        var diEl = document.querySelector('[name="default_data_inicio"]');
        if (diEl && r.default_data_inicio) diEl.value = r.default_data_inicio;
        var dfEl = document.querySelector('[name="default_data_fim"]');
        if (dfEl && r.default_data_fim) dfEl.value = r.default_data_fim;
        var notaEl = document.querySelector('[name="nota_minima_aprovacao"]');
        if (notaEl && r.nota_minima_aprovacao != null) notaEl.value = r.nota_minima_aprovacao;
        if (Array.isArray(r.turmas_ids)) setCheckboxes('turmas_ids[]', r.turmas_ids);
        if (Array.isArray(r.materias_ids)) setCheckboxes('materias_ids[]', r.materias_ids);
        if (Array.isArray(r.series_ids)) setCheckboxes('series_ids[]', r.series_ids);

        var regraIdInput = document.querySelector('#form-regra-boletim input[name="regra_id"]');
        if (regraIdInput) {
            if (r.modo === 'editar' && r.regra_id) regraIdInput.value = String(r.regra_id);
            else if (r.modo === 'criar') regraIdInput.value = '0';
        }

        var compsNovos = Array.isArray(r.componentes) ? r.componentes : [];
        function normalizarComp(c, idx) {
            var blocos = Array.isArray(c.blocos_ids) ? c.blocos_ids : [];
            var cfg = (c.config && typeof c.config === 'object') ? Object.assign({}, c.config) : {};
            if (c.expressao && !cfg.expressao) cfg.expressao = c.expressao;
            if ((c.source_type || '') === 'calculado' && !cfg.formula_mode) cfg.formula_mode = 'single';
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
        }
        var normalized = compsNovos.map(normalizarComp);
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
        var wrap = document.createElement('div');
        wrap.className = role === 'user'
            ? 'ml-6 bg-indigo-600 text-white rounded-lg px-3 py-2 whitespace-pre-wrap'
            : 'mr-2 bg-white border border-gray-200 text-gray-800 rounded-lg px-3 py-2 whitespace-pre-wrap';
        wrap.textContent = text || '';
        msgsEl.appendChild(wrap);
        msgsEl.scrollTop = msgsEl.scrollHeight;
        return wrap;
    }

    function idxPasso(id) {
        for (var i = 0; i < PASSOS.length; i++) if (PASSOS[i].id === id) return i;
        return 0;
    }

    function renderSteps() {
        var cur = estado ? estado.passo : 'inicio';
        var curIdx = idxPasso(cur);
        stepsEl.innerHTML = '';
        PASSOS.forEach(function (p, i) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'bw-step-btn' + (p.id === cur ? ' active' : (i < curIdx ? ' done' : ''));
            b.textContent = p.label;
            b.addEventListener('click', function () {
                if (!estado) return;
                estado.passo = p.id;
                renderAll();
            });
            stepsEl.appendChild(b);
        });
        btnVoltar.disabled = curIdx <= 0;
        btnAvancar.textContent = cur === 'revisar' ? 'Concluir e aplicar' : 'Continuar';
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
            html += '<div data-origem="zero">' + cardHtml(estado.origem === 'zero', 'Do zero', 'Você escolhe as peças e a fórmula nos próximos passos.') + '</div>';
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
                + '<input id="bw-ano" type="number" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + (estado.ano_letivo || '') + '"></div>'
                + '<div><label class="text-sm font-medium text-gray-700">Bimestre</label>'
                + '<select id="bw-bim" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm">'
                + [1,2,3,4].map(function (b) { return '<option value="' + b + '"' + (Number(estado.bimestre) === b ? ' selected' : '') + '>' + b + 'º</option>'; }).join('')
                + '</select></div></div>';
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
        }

        if (passo === 'pecas') {
            html += '<p class="text-sm text-gray-700">O que entra na média?</p>';
            html += '<p class="text-xs text-gray-500 mt-1">Em cada peça marcada, escolha se as notas internas são <strong>média</strong> ou <strong>somatória</strong>, e se junta a mesma matéria de professores diferentes.</p>';
            html += '<div class="grid gap-3 mt-3">';
            (catalogo.pecas || []).forEach(function (p) {
                var on = (estado.pecas || []).indexOf(p.key) >= 0;
                var opts = (estado.pecas_opcoes && estado.pecas_opcoes[p.key]) ? estado.pecas_opcoes[p.key] : { calc_type: 'media', materia_unica: 0, usar_percentual: 1 };
                html += '<div class="bw-card' + (on ? ' selected' : '') + '">';
                html += '<label class="flex items-start gap-3 cursor-pointer"><input type="checkbox" class="mt-1 bw-peca" value="' + p.key + '"' + (on ? ' checked' : '') + '>'
                    + '<span><span class="text-sm font-semibold text-gray-900">' + p.label + '</span><span class="block text-xs text-gray-500">' + (p.hint || '') + '</span></span></label>';
                if (on) {
                    html += '<div class="mt-3 pl-7 space-y-2 border-t border-gray-100 pt-3">';
                    html += '<div><span class="text-xs font-medium text-gray-600">Como calcular as notas desta peça</span>'
                        + '<div class="mt-1 flex flex-wrap gap-3 text-sm">'
                        + '<label class="inline-flex items-center gap-1.5"><input type="radio" class="bw-peca-calc" name="bw-calc-' + p.key + '" data-peca="' + p.key + '" value="media"' + (opts.calc_type !== 'soma' ? ' checked' : '') + '> Média</label>'
                        + '<label class="inline-flex items-center gap-1.5"><input type="radio" class="bw-peca-calc" name="bw-calc-' + p.key + '" data-peca="' + p.key + '" value="soma"' + (opts.calc_type === 'soma' ? ' checked' : '') + '> Somatória</label>'
                        + '</div></div>';
                    html += '<label class="inline-flex items-start gap-2 text-sm text-gray-700">'
                        + '<input type="checkbox" class="mt-0.5 bw-peca-unica" data-peca="' + p.key + '"' + (opts.materia_unica ? ' checked' : '') + '>'
                        + '<span><strong>Juntar matérias iguais</strong><span class="block text-xs text-gray-500">Soma as notas da mesma matéria quando há mais de um professor (matérias únicas).</span></span></label>';
                    if (p.key !== 'jornada') {
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

        if (passo === 'periodo') {
            html += '<p class="text-sm text-gray-700">Período das provas e jornadas</p>';
            html += '<p class="text-xs text-gray-500">Usamos as datas para achar os eventos de prova do tipo certo. Vazio = não filtra por data.</p>';
            html += '<div class="grid grid-cols-2 gap-3 mt-3">';
            html += '<div><label class="text-xs text-gray-600">Data início</label><input id="bw-data-ini" type="date" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + esc(estado.data_inicio || '') + '"></div>';
            html += '<div><label class="text-xs text-gray-600">Data fim</label><input id="bw-data-fim" type="date" class="mt-1 w-full h-10 border rounded-lg px-3 text-sm" value="' + esc(estado.data_fim || '') + '"></div>';
            html += '</div>';

            var pecasProva = (estado.pecas || []).filter(function (p) { return p === 'semanal' || p === 'bimestral' || p === 'enac'; });
            if (pecasProva.length) {
                html += '<p class="text-sm font-medium text-gray-800 mt-4">Tipo de avaliação por peça</p>';
                html += '<p class="text-xs text-gray-500">Se não escolher, tentamos pelo nome (Semanal, Bimestral, ENAC).</p>';
                html += '<div class="grid gap-3 mt-2">';
                pecasProva.forEach(function (key) {
                    var label = key;
                    (catalogo.pecas || []).forEach(function (p) { if (p.key === key) label = p.label; });
                    var opts = (estado.pecas_opcoes && estado.pecas_opcoes[key]) ? estado.pecas_opcoes[key] : {};
                    var tipoSel = Number(opts.tipo_avaliacao_id || 0);
                    html += '<div class="border rounded-lg p-3 bg-white"><label class="text-xs font-medium text-gray-700">' + esc(label) + '</label>';
                    html += '<select class="bw-tipo-avaliacao mt-1 w-full h-10 border rounded-lg px-3 text-sm" data-peca="' + key + '">';
                    html += '<option value="0">Detectar automaticamente</option>';
                    (catalogo.tipos_avaliacao || []).forEach(function (t) {
                        html += '<option value="' + t.id + '"' + (tipoSel === Number(t.id) ? ' selected' : '') + '>' + esc(t.nome) + '</option>';
                    });
                    html += '</select></div>';
                });
                html += '</div>';
            }

            if ((estado.pecas || []).indexOf('jornada') >= 0) {
                html += '<div class="mt-4 border rounded-lg p-3 bg-slate-50">';
                html += '<p class="text-sm font-medium text-gray-800">Jornadas</p>';
                html += '<div class="mt-2 flex flex-wrap gap-3 text-sm">';
                html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-jornada-modo" class="bw-jornada-modo" value="todas"' + ((estado.jornada_modo || 'todas') !== 'selecionadas' ? ' checked' : '') + '> Todas no período</label>';
                html += '<label class="inline-flex items-center gap-1.5"><input type="radio" name="bw-jornada-modo" class="bw-jornada-modo" value="selecionadas"' + (estado.jornada_modo === 'selecionadas' ? ' checked' : '') + '> Selecionar</label>';
                html += '</div>';
                if (estado.jornada_modo === 'selecionadas') {
                    html += '<div class="mt-2 max-h-40 overflow-y-auto border rounded-lg p-2 bg-white grid gap-1">';
                    (catalogo.jornadas || []).forEach(function (j) {
                        var on = (estado.jornada_ids || []).some(function (id) { return Number(id) === Number(j.id); });
                        var lab = j.nome + (j.materia_nome ? ' · ' + j.materia_nome : '');
                        html += '<label class="inline-flex items-center gap-2 text-xs"><input type="checkbox" class="bw-jornada-id" value="' + j.id + '"' + (on ? ' checked' : '') + '> ' + esc(lab) + '</label>';
                    });
                    if (!(catalogo.jornadas || []).length) {
                        html += '<p class="text-xs text-gray-500">Nenhuma jornada encontrada. Deixe “Todas no período”.</p>';
                    }
                    html += '</div>';
                }
                html += '</div>';
            }
        }

        if (passo === 'formula') {
            if (estado.origem === 'clonar') {
                html += '<p class="text-sm text-gray-600">No modo clonar, a fórmula do evento de origem é mantida. Avance para revisar.</p>';
            } else {
                html += '<p class="text-sm text-gray-700">Como juntar as <strong>peças entre si</strong> na média final?</p>';
                html += '<p class="text-xs text-gray-500">Isso é depois do cálculo de cada peça (média/somatória que você marcou no passo anterior).</p>';
                html += '<div class="grid gap-2 mt-2">';
                (formulasDisp.length ? formulasDisp : (catalogo.formulas || [])).forEach(function (f) {
                    html += '<div data-formula="' + f.key + '">' + cardHtml(estado.formula_preset === f.key, f.titulo, f.descricao) + '</div>';
                });
                html += '</div>';
            }
        }

        if (passo === 'publico') {
            html += '<p class="text-sm text-gray-700">Matérias que entram no boletim</p>';
            html += '<p class="text-xs text-gray-500 mb-2">Vazio = todas as matérias.</p>';
            html += '<div class="grid sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto border rounded-lg p-3 mb-4">';
            (catalogo.materias || []).forEach(function (m) {
                var on = (estado.materias_ids || []).some(function (id) { return Number(id) === Number(m.id); });
                html += '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="bw-materia rounded border-gray-300 text-indigo-600" value="' + m.id + '"' + (on ? ' checked' : '') + '> ' + esc(m.nome) + '</label>';
            });
            html += '</div>';

            html += '<p class="text-sm text-gray-700">Séries (opcional)</p>';
            html += '<div class="grid sm:grid-cols-2 gap-2 mt-2 max-h-36 overflow-y-auto border rounded-lg p-3">';
            (catalogo.series || []).forEach(function (s) {
                var on = (estado.series_ids || []).some(function (id) { return Number(id) === Number(s.id); });
                var label = s.nome + (s.curso_nome ? ' · ' + s.curso_nome : '');
                html += '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="bw-serie rounded border-gray-300 text-indigo-600" value="' + s.id + '"' + (on ? ' checked' : '') + '> ' + esc(label) + '</label>';
            });
            html += '</div>';

            html += '<p class="text-sm text-gray-700 mt-4">Turmas (opcional)</p>';
            html += '<p class="text-xs text-gray-500 mb-2">Se marcar turmas, elas têm prioridade sobre séries. Vazio = todas (ou as das séries).</p>';
            html += '<div class="grid sm:grid-cols-2 gap-2 max-h-40 overflow-y-auto border rounded-lg p-3">';
            var seriesSel = (estado.series_ids || []).map(Number);
            (catalogo.turmas || []).forEach(function (t) {
                if (seriesSel.length && t.serie_id && seriesSel.indexOf(Number(t.serie_id)) < 0) return;
                var on = (estado.turmas_ids || []).some(function (id) { return Number(id) === Number(t.id); });
                var lab = t.nome + (t.serie_nome ? ' · ' + t.serie_nome : '');
                html += '<label class="inline-flex items-center gap-2 text-sm"><input type="checkbox" class="bw-turma rounded border-gray-300 text-indigo-600" value="' + t.id + '"' + (on ? ' checked' : '') + '> ' + esc(lab) + '</label>';
            });
            html += '</div>';
        }

        if (passo === 'revisar') {
            html += '<div class="rounded-xl border border-indigo-100 bg-indigo-50/50 p-4">';
            html += '<p class="text-sm font-semibold text-indigo-900">Confira o resumo à direita</p>';
            html += '<p class="text-sm text-indigo-800/90 mt-2">Se estiver certo, clique em <strong>Aplicar no formulário</strong> e depois em <strong>Salvar evento</strong> na tela principal.</p>';
            html += '<p class="text-xs text-indigo-700 mt-3">Dica: use o chat (“inclui ENAC”, “só 1º ano”) para ajustar sem voltar os passos.</p>';
            html += '</div>';
        }

        bodyEl.innerHTML = html;
        bindBodyEvents();
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
                    estado.pecas.forEach(function (k) {
                        estado.pecas_opcoes[k] = { calc_type: 'media', materia_unica: 0, usar_percentual: 1, tipo_avaliacao_id: 0 };
                    });
                    estado.rascunho_preservado = null;
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.pecas = [];
                bodyEl.querySelectorAll('.bw-peca:checked').forEach(function (c) { estado.pecas.push(c.value); });
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                estado.pecas.forEach(function (k) {
                    if (!estado.pecas_opcoes[k]) {
                        estado.pecas_opcoes[k] = { calc_type: 'media', materia_unica: 0, usar_percentual: 1, tipo_avaliacao_id: 0 };
                    }
                });
                if (estado.origem === 'formulario' || estado.origem === 'chat') {
                    estado.origem = 'zero';
                    estado.rascunho_preservado = null;
                }
                renderAll();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-calc').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = { calc_type: 'media', materia_unica: 0, usar_percentual: 1 };
                estado.pecas_opcoes[peca].calc_type = el.value;
                if (estado.origem === 'formulario' || estado.origem === 'chat') {
                    estado.origem = 'zero';
                    estado.rascunho_preservado = null;
                }
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-unica').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = { calc_type: 'media', materia_unica: 0, usar_percentual: 1 };
                estado.pecas_opcoes[peca].materia_unica = el.checked ? 1 : 0;
                if (estado.origem === 'formulario' || estado.origem === 'chat') {
                    estado.origem = 'zero';
                    estado.rascunho_preservado = null;
                }
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-peca-perc').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = { calc_type: 'media', materia_unica: 0, usar_percentual: 1 };
                estado.pecas_opcoes[peca].usar_percentual = el.checked ? 1 : 0;
                if (estado.origem === 'formulario' || estado.origem === 'chat') {
                    estado.origem = 'zero';
                    estado.rascunho_preservado = null;
                }
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('[data-formula]').forEach(function (el) {
            el.addEventListener('click', function () {
                estado.formula_preset = el.getAttribute('data-formula');
                if (estado.origem === 'formulario' || estado.origem === 'chat') {
                    estado.origem = 'zero';
                    estado.rascunho_preservado = null;
                }
                renderAll();
                agendarMontar();
            });
        });
        var clonarSel = document.getElementById('bw-clonar-regra');
        if (clonarSel) {
            clonarSel.addEventListener('change', function () {
                estado.clonar_regra_id = parseInt(clonarSel.value, 10) || null;
                agendarMontar();
            });
        }
        ['bw-clonar-bim', 'bw-clonar-ano', 'bw-nome', 'bw-ano', 'bw-bim', 'bw-exibir', 'bw-nota-min', 'bw-round', 'bw-data-ini', 'bw-data-fim'].forEach(function (id) {
            var el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('change', syncIdentidadeFromDom);
            el.addEventListener('input', function () {
                syncIdentidadeFromDom();
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-tipo-avaliacao').forEach(function (el) {
            el.addEventListener('change', function () {
                var peca = el.getAttribute('data-peca');
                if (!peca) return;
                if (!estado.pecas_opcoes) estado.pecas_opcoes = {};
                if (!estado.pecas_opcoes[peca]) estado.pecas_opcoes[peca] = { calc_type: 'media', materia_unica: 0, usar_percentual: 1, tipo_avaliacao_id: 0 };
                estado.pecas_opcoes[peca].tipo_avaliacao_id = parseInt(el.value, 10) || 0;
                if (estado.origem === 'formulario' || estado.origem === 'chat') {
                    estado.origem = 'zero';
                    estado.rascunho_preservado = null;
                }
                agendarMontar();
            });
        });
        bodyEl.querySelectorAll('.bw-jornada-modo').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.jornada_modo = el.value;
                renderAll();
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
        bodyEl.querySelectorAll('.bw-serie').forEach(function (el) {
            el.addEventListener('change', function () {
                estado.series_ids = [];
                bodyEl.querySelectorAll('.bw-serie:checked').forEach(function (c) {
                    estado.series_ids.push(parseInt(c.value, 10));
                });
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
            'bw-data-ini': 'data_inicio',
            'bw-data-fim': 'data_fim'
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
        renderSteps();
        renderBody();
    }

    function agendarMontar() {
        if (montarTimer) clearTimeout(montarTimer);
        montarTimer = setTimeout(montarAgora, 280);
    }

    function montarAgora() {
        if (!estado) return;
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('wizard_estado', JSON.stringify(estado));
        fetch(urlWizardMontar, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || !j.success) return;
            if (j.estado) estado = j.estado;
            rascunhoAtual = j.rascunho || null;
            formulasDisp = j.formulas_disponiveis || formulasDisp;
            renderResumo(j.resumo, j.erros || []);
        }).catch(function () {});
    }

    function abrirWizard() {
        overlay.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        if (iniciado) return;
        iniciado = true;
        msgsEl.innerHTML = '';
        appendBubble('assistant', 'Vou te guiar pelos passos: peças, período/provas, matérias e fórmula. No chat: “só Matemática e Português”, “bimestral em soma”, “jornadas todas do período”, “inclui ENAC”.');
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('regra_id', String(regraId));
        fd.append('estado_formulario', JSON.stringify(coletarEstadoFormulario()));
        fetch(urlWizardInicio, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); }).then(function (j) {
            if (!j || !j.success) {
                appendBubble('assistant', (j && j.error) || 'Não foi possível iniciar o assistente.');
                return;
            }
            catalogo = j.catalogo || catalogo;
            estado = j.estado;
            rascunhoAtual = j.rascunho;
            formulasDisp = j.formulas_disponiveis || [];
            if (j.disponivel_ia === false) disponivelIa = false;
            renderAll();
            renderResumo(j.resumo, j.erros || []);
        }).catch(function () {
            appendBubble('assistant', 'Falha ao carregar o assistente. Recarregue a página.');
        });
    }

    function fecharWizard() {
        overlay.classList.add('hidden');
        document.body.style.overflow = '';
    }

    function avancar() {
        if (!estado) return;
        syncIdentidadeFromDom();
        var i = idxPasso(estado.passo);
        if (estado.passo === 'revisar') {
            if (rascunhoAtual) {
                aplicarRascunho(rascunhoAtual);
                appendBubble('assistant', 'Aplicado no formulário. Revise os blocos e clique em Salvar evento.');
                fecharWizard();
            } else {
                montarAgora();
                appendBubble('assistant', 'Aguarde o resumo montar e tente de novo.');
            }
            return;
        }
        // pular peças/fórmula se clonar; período sempre útil para datas
        var next = PASSOS[Math.min(i + 1, PASSOS.length - 1)].id;
        if (estado.origem === 'clonar') {
            if (estado.passo === 'inicio') next = 'identidade';
            else if (estado.passo === 'identidade') next = 'periodo';
            else if (estado.passo === 'periodo') next = 'publico';
            else if (estado.passo === 'publico') next = 'revisar';
        }
        estado.passo = next;
        renderAll();
        agendarMontar();
    }

    function voltar() {
        if (!estado) return;
        var i = idxPasso(estado.passo);
        if (i <= 0) return;
        estado.passo = PASSOS[i - 1].id;
        renderAll();
    }

    function enviarChat() {
        if (enviando || !chatInput) return;
        var texto = (chatInput.value || '').trim();
        if (!texto) return;

        if (!disponivelIa) {
            var pareceReceita = /#\s*RECEITA_BOLETIM\b/i.test(texto) || /\[\[componente\]\]/i.test(texto);
            if (!pareceReceita) {
                appendBubble('assistant', 'Sem TudiCoins, o chat só aplica receita colada. Use os passos à esquerda ou ative TudiCoins no Master.');
                return;
            }
        }

        appendBubble('user', texto);
        historico.push({ role: 'user', content: texto });
        chatInput.value = '';
        enviando = true;
        if (chatEnviar) chatEnviar.disabled = true;

        var loading = document.createElement('div');
        loading.className = 'mr-2 bg-white border border-gray-200 rounded-lg px-3 py-3 inline-flex gap-1.5';
        loading.innerHTML = '<span class="boletim-assistente-dot"></span><span class="boletim-assistente-dot"></span><span class="boletim-assistente-dot"></span>';
        msgsEl.appendChild(loading);

        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('mensagem', texto);
        fd.append('regra_id', String(regraId));
        fd.append('historico', JSON.stringify(historico.slice(0, -1)));
        fd.append('estado_formulario', JSON.stringify(rascunhoAtual || coletarEstadoFormulario()));
        if (estado) fd.append('wizard_estado', JSON.stringify(estado));

        var bubble = null;
        var textoStream = '';

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
            if (ev.event === 'error') {
                if (loading.parentNode) loading.parentNode.removeChild(loading);
                appendBubble('assistant', payload.error || 'Não foi possível processar.');
            }
            if (ev.event === 'done') {
                if (loading.parentNode) loading.parentNode.removeChild(loading);
                var msg = (payload.mensagem || textoStream || 'Pronto.').trim();
                if (!bubble) bubble = appendBubble('assistant', msg);
                else bubble.textContent = msg;
                historico.push({ role: 'assistant', content: msg });
                if (payload.rascunho && payload.acao === 'rascunho') {
                    rascunhoAtual = payload.rascunho;
                    aplicarRascunho(payload.rascunho);
                    if (estado) {
                        estado.origem = 'chat';
                        estado.rascunho_preservado = payload.rascunho;
                        estado.passo = 'revisar';
                        if (payload.rascunho.nome) estado.nome = payload.rascunho.nome;
                        if (payload.rascunho.bimestre != null) estado.bimestre = payload.rascunho.bimestre;
                        if (payload.rascunho.ano_letivo) estado.ano_letivo = payload.rascunho.ano_letivo;
                    }
                    renderAll();
                    renderResumo(
                        'Ajuste do chat aplicado no formulário.\n\nRevise e salve o evento quando estiver ok.',
                        payload.erros || []
                    );
                    // Remonta só metadados/resumo preservando componentes do chat
                    agendarMontar();
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

        fetch(urlMensagem, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/event-stream' },
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok || !res.body) {
                return res.json().catch(function () { return {}; }).then(function (j) {
                    if (loading.parentNode) loading.parentNode.removeChild(loading);
                    appendBubble('assistant', (j && j.error) || ('Erro ' + res.status));
                });
            }
            var reader = res.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';
            function ler() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        if (buffer.trim()) parsearSse(buffer + '\n\n').eventos.forEach(processarEvento);
                        return;
                    }
                    buffer += decoder.decode(result.value, { stream: true });
                    var parsed = parsearSse(buffer);
                    buffer = parsed.restante;
                    parsed.eventos.forEach(processarEvento);
                    return ler();
                });
            }
            return ler();
        }).catch(function () {
            if (loading.parentNode) loading.parentNode.removeChild(loading);
            appendBubble('assistant', 'Falha de conexão.');
        }).finally(function () {
            enviando = false;
            if (chatEnviar) chatEnviar.disabled = false;
            if (chatInput) chatInput.focus();
        });
    }

    if (toggle) toggle.addEventListener('click', abrirWizard);
    if (btnFechar) btnFechar.addEventListener('click', fecharWizard);
    if (btnVoltar) btnVoltar.addEventListener('click', voltar);
    if (btnAvancar) btnAvancar.addEventListener('click', avancar);
    if (btnAplicar) {
        btnAplicar.addEventListener('click', function () {
            if (!rascunhoAtual) {
                montarAgora();
                appendBubble('assistant', 'Aguarde montar o rascunho…');
                return;
            }
            aplicarRascunho(rascunhoAtual);
            appendBubble('assistant', 'Aplicado no formulário (ainda não salvo).');
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
                        appendBubble('assistant', (j && j.error) || 'Não foi possível gerar a receita.');
                        return;
                    }
                    return copiarTexto(rec).then(function () {
                        appendBubble('assistant', 'Receita copiada. Cole em outro assistente para montar igual.');
                    });
                });
        });
    }
    if (chatForm) {
        chatForm.addEventListener('submit', function (ev) {
            ev.preventDefault();
            enviarChat();
        });
    }
    if (chatInput) {
        chatInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && !ev.shiftKey) {
                ev.preventDefault();
                enviarChat();
            }
        });
    }
    overlay.addEventListener('click', function (ev) {
        if (ev.target === overlay) fecharWizard();
    });
})();
</script>
