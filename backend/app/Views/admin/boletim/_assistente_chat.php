<?php
/**
 * Painel do Assistente de Boletim (NL → rascunho no formulário).
 * Variáveis: $csrfToken, $boletimAssistenteDisponivel, $selected_regra_id (opcional)
 */
$boletimAssistenteDisponivel = !empty($boletimAssistenteDisponivel);
$assistenteRegraId = (int) ($selected_regra_id ?? $selectedRegraId ?? 0);
?>
<div id="boletim-assistente-root" class="fixed bottom-4 right-4 z-40 flex flex-col items-end gap-2"
     data-disponivel="<?= $boletimAssistenteDisponivel ? '1' : '0' ?>"
     data-url-mensagem="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/mensagem-stream', ENT_QUOTES, 'UTF-8') ?>"
     data-url-ferramenta="<?= htmlspecialchars(URL . '/admin/boletim-configuracao/assistente/ferramenta', ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars((string) $csrfToken, ENT_QUOTES, 'UTF-8') ?>"
     data-regra-id="<?= $assistenteRegraId ?>">
    <div id="boletim-assistente-panel" class="hidden w-[min(100vw-2rem,26rem)] h-[min(75vh,36rem)] bg-white border border-gray-200 shadow-xl rounded-2xl flex flex-col overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100 bg-slate-50 flex items-start justify-between gap-2">
            <div class="min-w-0">
                <h3 class="text-sm font-semibold text-gray-900">Assistente de boletim</h3>
                <p class="text-xs text-gray-500">Descreva a regra ou cole uma receita</p>
            </div>
            <div class="flex items-center gap-1 shrink-0">
                <button type="button" id="boletim-assistente-copiar-receita"
                        class="text-xs font-medium text-indigo-700 hover:text-indigo-900 px-2 py-1 rounded-md hover:bg-indigo-50"
                        title="Copia a config atual para colar em outro chat">
                    Copiar receita
                </button>
                <button type="button" id="boletim-assistente-fechar" class="text-gray-400 hover:text-gray-700 p-1" aria-label="Fechar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
        </div>
        <div id="boletim-assistente-mensagens" class="flex-1 overflow-y-auto p-3 space-y-2 text-sm bg-white">
            <div class="text-gray-600 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 space-y-1.5">
                <p>Ex.: “Prova semanal de 01/01 a 31/05 (média). Depois média com a bimestral ÷ 2.”</p>
                <p class="text-xs text-indigo-800/80">Para reutilizar em outro chat: <strong>Copiar receita</strong> aqui → cole lá (monta igual). Edite o que quiser antes de colar. Blocos calculados precisam de <code class="text-[11px] bg-white/70 px-1 rounded">expressao</code> (ex.: <code class="text-[11px] bg-white/70 px-1 rounded">(c1 + c2) / 2</code>).</p>
            </div>
        </div>
        <?php if (!$boletimAssistenteDisponivel): ?>
            <div class="px-3 py-2 text-xs text-amber-800 bg-amber-50 border-t border-amber-100 space-y-2">
                <p>
                    Ative <strong>TudiCoins</strong> para esta escola no Master para usar o chat com IA.
                    O checkbox <strong>Cobra</strong> no catálogo só define se debita ou não (desmarcado = grátis).
                </p>
                <p class="text-amber-900/80">
                    Mesmo sem IA: use <strong>Copiar receita</strong> e, no campo abaixo, cole uma receita para montar o formulário (sem gastar créditos).
                </p>
                <form id="boletim-assistente-form-receita" class="flex gap-2">
                    <textarea id="boletim-assistente-input" rows="2" class="flex-1 text-sm border border-amber-200 rounded-lg px-2 py-1.5 bg-white focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="Cole aqui uma # RECEITA_BOLETIM v1…"></textarea>
                    <button type="submit" id="boletim-assistente-enviar" class="self-end px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">Aplicar</button>
                </form>
            </div>
        <?php else: ?>
            <form id="boletim-assistente-form" class="border-t border-gray-100 p-2 flex gap-2 bg-gray-50">
                <textarea id="boletim-assistente-input" rows="2" class="flex-1 text-sm border border-gray-300 rounded-lg px-2 py-1.5 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none" placeholder="Descreva a média ou cole uma receita… (Enter envia · Shift+Enter quebra linha)"></textarea>
                <button type="submit" id="boletim-assistente-enviar" class="self-end px-3 py-2 bg-indigo-600 text-white text-sm font-medium rounded-lg hover:bg-indigo-700 disabled:opacity-50">Enviar</button>
            </form>
        <?php endif; ?>
    </div>
    <button type="button" id="boletim-assistente-toggle"
            class="inline-flex items-center gap-2 px-4 py-2.5 rounded-full shadow-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
        Assistente IA
    </button>
</div>

<style>
@keyframes boletim-assistente-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40% { transform: translateY(-4px); opacity: 1; }
}
.boletim-assistente-dot {
    width: 6px;
    height: 6px;
    border-radius: 9999px;
    background: #6366f1;
    display: inline-block;
    animation: boletim-assistente-bounce 1.2s infinite ease-in-out;
}
.boletim-assistente-dot:nth-child(2) { animation-delay: 0.15s; }
.boletim-assistente-dot:nth-child(3) { animation-delay: 0.3s; }
</style>

<script>
(function () {
    var root = document.getElementById('boletim-assistente-root');
    if (!root) return;
    var disponivel = root.getAttribute('data-disponivel') === '1';
    var urlMensagem = root.getAttribute('data-url-mensagem');
    var urlFerramenta = root.getAttribute('data-url-ferramenta');
    var csrf = root.getAttribute('data-csrf');
    var regraId = parseInt(root.getAttribute('data-regra-id') || '0', 10) || 0;
    var panel = document.getElementById('boletim-assistente-panel');
    var toggle = document.getElementById('boletim-assistente-toggle');
    var fechar = document.getElementById('boletim-assistente-fechar');
    var btnCopiarReceita = document.getElementById('boletim-assistente-copiar-receita');
    var form = document.getElementById('boletim-assistente-form')
        || document.getElementById('boletim-assistente-form-receita');
    var input = document.getElementById('boletim-assistente-input');
    var msgs = document.getElementById('boletim-assistente-mensagens');
    var historico = [];
    var enviando = false;
    var loadingEl = null;

    function appendBubble(role, text, extraHtml) {
        var wrap = document.createElement('div');
        wrap.className = role === 'user'
            ? 'ml-8 bg-indigo-600 text-white rounded-lg px-3 py-2 whitespace-pre-wrap'
            : 'mr-4 bg-gray-100 text-gray-800 rounded-lg px-3 py-2 whitespace-pre-wrap';
        wrap.textContent = text || '';
        if (extraHtml) {
            var extra = document.createElement('div');
            extra.className = 'mt-2';
            extra.innerHTML = extraHtml;
            wrap.appendChild(extra);
        }
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;
        return wrap;
    }

    function mostrarCarregando() {
        removerCarregando();
        loadingEl = document.createElement('div');
        loadingEl.id = 'boletim-assistente-loading';
        loadingEl.className = 'mr-4 bg-gray-100 text-gray-800 rounded-lg px-3 py-3 inline-flex items-center gap-1.5';
        loadingEl.setAttribute('aria-label', 'Assistente digitando');
        loadingEl.innerHTML = '<span class="boletim-assistente-dot"></span><span class="boletim-assistente-dot"></span><span class="boletim-assistente-dot"></span>';
        msgs.appendChild(loadingEl);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function removerCarregando() {
        if (loadingEl && loadingEl.parentNode) {
            loadingEl.parentNode.removeChild(loadingEl);
        }
        loadingEl = null;
        var legacy = document.getElementById('boletim-assistente-loading');
        if (legacy && legacy.parentNode) legacy.parentNode.removeChild(legacy);
    }

    function setEnviando(ativo) {
        enviando = !!ativo;
        var btn = document.getElementById('boletim-assistente-enviar');
        if (btn) btn.disabled = enviando;
        if (input) input.disabled = enviando;
    }

    function coletarEstadoFormulario() {
        var estado = {
            nome: (document.getElementById('regra-nome') || {}).value || '',
            codigo: (document.getElementById('regra-codigo') || {}).value || '',
            descricao_curta: (document.getElementById('regra-descricao-curta') || {}).value || '',
            formula_final: '',
            exibir_em: '',
            ano_letivo: '',
            bimestre: '',
            round_mode: '',
            nota_minima_aprovacao: '',
            default_data_inicio: '',
            default_data_fim: '',
            turmas_ids: [],
            materias_ids: [],
            series_ids: [],
            componentes: []
        };
        var formulaEl = document.querySelector('[name="formula_final"]');
        if (formulaEl) estado.formula_final = formulaEl.value || '';
        var exibirEl = document.querySelector('[name="exibir_em"]');
        if (exibirEl) estado.exibir_em = exibirEl.value || '';
        var anoEl = document.querySelector('[name="ano_letivo"]');
        if (anoEl) estado.ano_letivo = anoEl.value || '';
        var bimEl = document.querySelector('[name="bimestre"]');
        if (bimEl) estado.bimestre = bimEl.value || '';
        var roundEl = document.querySelector('[name="round_mode"]');
        if (roundEl) estado.round_mode = roundEl.value || '';
        var notaEl = document.querySelector('[name="nota_minima_aprovacao"]');
        if (notaEl) estado.nota_minima_aprovacao = notaEl.value || '';
        var diEl = document.querySelector('[name="default_data_inicio"]');
        if (diEl) estado.default_data_inicio = diEl.value || '';
        var dfEl = document.querySelector('[name="default_data_fim"]');
        if (dfEl) estado.default_data_fim = dfEl.value || '';
        document.querySelectorAll('input[name="turmas_ids[]"]:checked').forEach(function (el) {
            estado.turmas_ids.push(parseInt(el.value, 10));
        });
        document.querySelectorAll('input[name="materias_ids[]"]:checked').forEach(function (el) {
            estado.materias_ids.push(parseInt(el.value, 10));
        });
        document.querySelectorAll('input[name="series_ids[]"]:checked').forEach(function (el) {
            estado.series_ids.push(parseInt(el.value, 10));
        });
        var jsonEl = document.getElementById('componentes-json');
        if (jsonEl && jsonEl.value) {
            try { estado.componentes = JSON.parse(jsonEl.value); } catch (e) {}
        }
        return estado;
    }

    function copiarTexto(texto) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            return navigator.clipboard.writeText(texto);
        }
        return new Promise(function (resolve, reject) {
            var ta = document.createElement('textarea');
            ta.value = texto;
            ta.style.position = 'fixed';
            ta.style.left = '-9999px';
            document.body.appendChild(ta);
            ta.select();
            try {
                document.execCommand('copy');
                resolve();
            } catch (e) {
                reject(e);
            }
            document.body.removeChild(ta);
        });
    }

    function copiarReceitaAtual() {
        if (!urlFerramenta) {
            appendBubble('assistant', 'Não foi possível copiar a receita agora.');
            return;
        }
        var estado = coletarEstadoFormulario();
        if (!estado.nome && !(estado.componentes && estado.componentes.length)) {
            appendBubble('assistant', 'Monte ou carregue a regra no formulário antes de copiar a receita.');
            if (panel) panel.classList.remove('hidden');
            return;
        }
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('tool', 'formatar_receita');
        fd.append('estado_formulario', JSON.stringify(estado));
        fetch(urlFerramenta, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin'
        }).then(function (res) { return res.json(); }).then(function (j) {
            var receita = j && j.data && j.data.receita ? j.data.receita : '';
            if (!j || !j.success || !receita) {
                appendBubble('assistant', (j && j.error) || 'Não foi possível gerar a receita.');
                if (panel) panel.classList.remove('hidden');
                return;
            }
            return copiarTexto(receita).then(function () {
                if (panel) panel.classList.remove('hidden');
                appendBubble('assistant', 'Receita copiada. Cole em outro Assistente de Boletim para montar igual (pode editar antes).');
            });
        }).catch(function () {
            appendBubble('assistant', 'Falha ao copiar a receita. Tente de novo.');
            if (panel) panel.classList.remove('hidden');
        });
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
        if (exibirEl && r.exibir_em) exibirEl.value = r.exibir_em;

        var anoEl = document.querySelector('[name="ano_letivo"]');
        if (anoEl && r.ano_letivo) anoEl.value = r.ano_letivo;

        var bimEl = document.querySelector('[name="bimestre"]');
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
            if (r.modo === 'editar' && r.regra_id) {
                regraIdInput.value = String(r.regra_id);
            } else if (r.modo === 'criar') {
                regraIdInput.value = '0';
            }
        }

        var compsNovos = Array.isArray(r.componentes) ? r.componentes : [];
        var atuais = [];
        var jsonEl = document.getElementById('componentes-json');
        if (jsonEl && jsonEl.value) {
            try { atuais = JSON.parse(jsonEl.value) || []; } catch (e) { atuais = []; }
        }
        if (!Array.isArray(atuais)) atuais = [];

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
                usar_percentual: c.usar_percentual ? 1 : 0,
                escala_max: c.escala_max != null ? c.escala_max : 10,
                obrigatorio: c.obrigatorio ? 1 : 0,
                config: cfg
            };
        }

        var novosNorm = compsNovos.map(normalizarComp);
        var atuaisCodigos = {};
        atuais.forEach(function (c) {
            if (c && c.codigo) atuaisCodigos[String(c.codigo)] = true;
        });
        var novosCobremAtuais = Object.keys(atuaisCodigos).length === 0 || Object.keys(atuaisCodigos).every(function (cod) {
            return novosNorm.some(function (n) { return String(n.codigo) === cod; });
        });

        var normalized;
        if (novosNorm.length === 0) {
            normalized = atuais;
        } else if (novosCobremAtuais || novosNorm.length >= atuais.length) {
            normalized = novosNorm;
        } else {
            var porCodigo = {};
            var ordem = [];
            atuais.forEach(function (c, idx) {
                var n = normalizarComp(c, idx);
                porCodigo[String(n.codigo)] = n;
                ordem.push(String(n.codigo));
            });
            novosNorm.forEach(function (n) {
                var cod = String(n.codigo);
                if (!porCodigo[cod]) ordem.push(cod);
                porCodigo[cod] = n;
            });
            normalized = ordem.map(function (cod) { return porCodigo[cod]; });
        }

        if (jsonEl) {
            jsonEl.value = JSON.stringify(normalized);
        }
        if (typeof window.boletimRenderComponentes === 'function') {
            window.boletimRenderComponentes(normalized);
        } else if (typeof window.renderComponentesLista === 'function') {
            window.renderComponentesLista(normalized);
        } else {
            document.dispatchEvent(new CustomEvent('boletim:assistente-rascunho', { detail: { componentes: normalized, rascunho: r } }));
        }
    }

    function enviarMensagem() {
        if (enviando || !input) return;
        var texto = (input.value || '').trim();
        if (!texto) return;
        // Sem TudiCoins: só aceita export/import de receita (atalho sem IA).
        if (!disponivel) {
            var pareceReceita = /#\s*RECEITA_BOLETIM\b/i.test(texto)
                || /\[\[componente\]\]/i.test(texto)
                || (/^\s*-\s*Nome\s*:/im.test(texto) && /Componentes\s*:/i.test(texto));
            var pareceExport = /copiar receita|exportar receita|exportar config|copiar config|gerar receita|mostrar receita|me d[aá] a receita|config completa/i.test(texto);
            if (!pareceReceita && !pareceExport) {
                appendBubble('assistant', 'Sem TudiCoins só dá para colar uma receita (# RECEITA_BOLETIM) ou usar Copiar receita. Ative TudiCoins no Master para o chat com IA.');
                return;
            }
        }

        appendBubble('user', texto);
        historico.push({ role: 'user', content: texto });
        input.value = '';
        setEnviando(true);
        mostrarCarregando();

        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('mensagem', texto);
        fd.append('regra_id', String(regraId));
        fd.append('historico', JSON.stringify(historico.slice(0, -1)));
        fd.append('estado_formulario', JSON.stringify(coletarEstadoFormulario()));

        var assistantBubble = null;
        var textoStream = '';

        function garantirBubbleAssistente() {
            if (assistantBubble) return;
            removerCarregando();
            assistantBubble = appendBubble('assistant', '');
        }

        function parsearEventosSse(buffer) {
            var eventos = [];
            var parts = buffer.split('\n\n');
            var restante = parts.pop() || '';
            parts.forEach(function (block) {
                var lines = block.split('\n');
                var eventName = 'message';
                var dataLines = [];
                lines.forEach(function (line) {
                    if (line.indexOf('event:') === 0) {
                        eventName = line.slice(6).trim();
                    } else if (line.indexOf('data:') === 0) {
                        dataLines.push(line.slice(5).trim());
                    }
                });
                if (dataLines.length) {
                    eventos.push({ event: eventName, data: dataLines.join('\n') });
                }
            });
            return { eventos: eventos, restante: restante };
        }

        function processarEvento(ev) {
            var payload = {};
            try { payload = JSON.parse(ev.data || '{}'); } catch (e) { payload = {}; }
            if (ev.event === 'chunk') {
                garantirBubbleAssistente();
                textoStream += (payload.text || '');
                assistantBubble.textContent = textoStream;
                msgs.scrollTop = msgs.scrollHeight;
                return;
            }
            if (ev.event === 'error') {
                removerCarregando();
                if (assistantBubble) {
                    assistantBubble.textContent = payload.error || 'Não foi possível processar.';
                } else {
                    appendBubble('assistant', payload.error || 'Não foi possível processar.');
                }
                return;
            }
            if (ev.event === 'done') {
                removerCarregando();
                var mensagemFinal = (payload.mensagem || textoStream || 'Pronto.').trim();
                if (!assistantBubble) {
                    assistantBubble = appendBubble('assistant', mensagemFinal);
                } else {
                    assistantBubble.textContent = mensagemFinal;
                }
                historico.push({ role: 'assistant', content: mensagemFinal });
                if (payload.rascunho && payload.acao === 'rascunho') {
                    aplicarRascunho(payload.rascunho);
                    var extra = document.createElement('div');
                    extra.className = 'mt-2';
                    extra.innerHTML = '<button type="button" class="mt-1 text-xs font-medium text-indigo-700 underline" data-reaplicar="1">Reaplicar rascunho no formulário</button>';
                    assistantBubble.appendChild(extra);
                    var btn = extra.querySelector('[data-reaplicar="1"]');
                    if (btn) {
                        btn.addEventListener('click', function () { aplicarRascunho(payload.rascunho); });
                    }
                }
                if (payload.acao === 'receita' && payload.receita) {
                    var extraRec = document.createElement('div');
                    extraRec.className = 'mt-2';
                    extraRec.innerHTML = '<button type="button" class="mt-1 text-xs font-medium text-indigo-700 underline" data-copiar-receita="1">Copiar só a receita</button>';
                    assistantBubble.appendChild(extraRec);
                    var btnRec = extraRec.querySelector('[data-copiar-receita="1"]');
                    if (btnRec) {
                        btnRec.addEventListener('click', function () {
                            copiarTexto(payload.receita).then(function () {
                                btnRec.textContent = 'Copiado!';
                            });
                        });
                    }
                }
            }
        }

        fetch(urlMensagem, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'text/event-stream' },
            credentials: 'same-origin'
        }).then(function (res) {
            if (!res.ok || !res.body) {
                return res.json().catch(function () { return {}; }).then(function (j) {
                    removerCarregando();
                    appendBubble('assistant', (j && j.error) || ('Erro ' + res.status + ' ao consultar a IA.'));
                });
            }
            var reader = res.body.getReader();
            var decoder = new TextDecoder();
            var buffer = '';
            function ler() {
                return reader.read().then(function (result) {
                    if (result.done) {
                        if (buffer.trim()) {
                            parsearEventosSse(buffer + '\n\n').eventos.forEach(processarEvento);
                        }
                        if (!assistantBubble && !textoStream) {
                            removerCarregando();
                            appendBubble('assistant', 'A IA não retornou resposta. Tente novamente.');
                        }
                        return;
                    }
                    buffer += decoder.decode(result.value, { stream: true });
                    var parsed = parsearEventosSse(buffer);
                    buffer = parsed.restante;
                    parsed.eventos.forEach(processarEvento);
                    return ler();
                });
            }
            return ler();
        }).catch(function () {
            removerCarregando();
            appendBubble('assistant', 'Falha de conexão. Tente novamente.');
        }).finally(function () {
            setEnviando(false);
            if (input) input.focus();
        });
    }

    if (toggle && panel) {
        toggle.addEventListener('click', function () {
            panel.classList.toggle('hidden');
            if (!panel.classList.contains('hidden') && input) {
                setTimeout(function () { input.focus(); }, 50);
            }
        });
    }
    if (fechar && panel) {
        fechar.addEventListener('click', function () {
            panel.classList.add('hidden');
        });
    }
    if (btnCopiarReceita) {
        btnCopiarReceita.addEventListener('click', function () {
            copiarReceitaAtual();
        });
    }

    if (form) {
        form.addEventListener('submit', function (ev) {
            ev.preventDefault();
            enviarMensagem();
        });
    }
    if (input) {
        input.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' && !ev.shiftKey) {
                ev.preventDefault();
                enviarMensagem();
            }
        });
    }
})();
</script>
