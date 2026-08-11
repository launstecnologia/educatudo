<?php
$csrfToken = (string) ($csrf_token ?? '');
$disponivel = !empty($assistente_disponivel);
$historicoDisponivel = !empty($historico_disponivel);
$conversasIniciais = is_array($conversas_iniciais ?? null) ? $conversas_iniciais : [];
$urlBase = URL . '/admin/assistente';
?>
<div class="space-y-3"
     id="assistente-root"
     data-disponivel="<?= $disponivel ? '1' : '0' ?>"
     data-historico="<?= $historicoDisponivel ? '1' : '0' ?>"
     data-url-stream="<?= htmlspecialchars($urlBase . '/mensagem-stream', ENT_QUOTES, 'UTF-8') ?>"
     data-url-listar="<?= htmlspecialchars($urlBase . '/conversas', ENT_QUOTES, 'UTF-8') ?>"
     data-url-obter="<?= htmlspecialchars($urlBase . '/conversa', ENT_QUOTES, 'UTF-8') ?>"
     data-url-criar="<?= htmlspecialchars($urlBase . '/conversas/criar', ENT_QUOTES, 'UTF-8') ?>"
     data-url-renomear="<?= htmlspecialchars($urlBase . '/conversas/renomear', ENT_QUOTES, 'UTF-8') ?>"
     data-url-excluir="<?= htmlspecialchars($urlBase . '/conversas/excluir', ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
     data-conversas="<?= htmlspecialchars(json_encode($conversasIniciais, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Assistente</h1>
            <p class="text-sm text-gray-600 mt-1">
                Consulte aluno (provas/jornadas/boletim/faltas), turma (saúde), bloco de prova ou professor.
            </p>
        </div>
        <a href="<?= URL ?>/admin/doc-sistema/tool"
           class="inline-flex items-center gap-1.5 px-3 py-2 text-sm rounded-xl border border-gray-200 text-gray-700 hover:bg-gray-50 shrink-0">
            <i class="fa-solid fa-book text-xs"></i> Docs das tools
        </a>
    </div>

    <?php if (!$disponivel): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            Ative <strong>TudiCoins</strong> e o item
            <code class="text-xs bg-white/80 px-1 rounded">provas_aluno_assistente_mensagem</code>
            no catálogo Master.
        </div>
    <?php endif; ?>

    <?php if (!$historicoDisponivel): ?>
        <div class="rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
            Para salvar o histórico de chats, rode a migration
            <code class="text-xs bg-white/80 px-1 rounded">2026_07_20_assistente_conversas</code>
            no Master. Enquanto isso o chat funciona na sessão atual.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden flex"
         style="height: min(78vh, 46rem);">
        <!-- Histórico -->
        <aside class="w-64 shrink-0 border-r border-gray-200 bg-slate-50 flex flex-col hidden sm:flex">
            <div class="p-3 border-b border-gray-200 flex items-center gap-2">
                <button type="button" id="assistente-nova-conversa"
                        class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Novo chat
                </button>
            </div>
            <div id="assistente-lista-conversas" class="flex-1 overflow-y-auto p-2 space-y-1"></div>
        </aside>

        <!-- Chat -->
        <section class="flex-1 min-w-0 flex flex-col">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-2 bg-white">
                <div class="min-w-0">
                    <p id="assistente-titulo-ativo" class="text-sm font-semibold text-gray-900 truncate">Nova conversa</p>
                    <p id="assistente-subtitulo-ativo" class="text-xs text-gray-500 truncate"></p>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" id="assistente-btn-mobile-hist"
                            class="sm:hidden px-2 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-600">Chats</button>
                    <button type="button" id="assistente-renomear" class="px-2 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" title="Renomear">Renomear</button>
                    <button type="button" id="assistente-excluir" class="px-2 py-1.5 text-xs rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50" title="Excluir">Excluir</button>
                </div>
            </div>

            <div id="assistente-mensagens" class="flex-1 overflow-y-auto p-4 space-y-3 text-sm bg-slate-50/40">
                <div class="bg-indigo-50 border border-indigo-100 text-indigo-900 rounded-xl px-4 py-3 space-y-1.5" id="assistente-welcome">
                    <p class="font-medium">Como começar</p>
                <ul class="list-disc list-inside text-indigo-800/90 space-y-0.5">
                    <li>“Como andam as provas da Maria Clara no 2º Ano B?”</li>
                    <li>“Como está a saúde da turma 2ªB?”</li>
                    <li>“Resultados do bloco Simulado 1 — quem precisa de atenção?”</li>
                    <li>“Boletim e faltas da Maria Clara.”</li>
                    <li>“Como estão as jornadas do professor Ana?”</li>
                    <li>Use <strong>Novo chat</strong> para separar contextos.</li>
                </ul>
                </div>
            </div>

            <form id="assistente-form" class="border-t border-gray-100 p-3 flex gap-2 bg-white">
                <textarea id="assistente-input" rows="2"
                          class="flex-1 text-sm border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                          placeholder="Pergunte sobre aluno, professor, turma, bimestre…"
                          <?= $disponivel ? '' : 'disabled' ?>></textarea>
                <button type="submit" id="assistente-enviar"
                        class="self-end px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 disabled:opacity-50"
                        <?= $disponivel ? '' : 'disabled' ?>>Enviar</button>
            </form>
        </section>
    </div>
</div>

<!-- Drawer mobile histórico -->
<div id="assistente-drawer" class="fixed inset-0 z-50 hidden sm:hidden">
    <div class="absolute inset-0 bg-black/40" data-close-drawer></div>
    <div class="absolute left-0 top-0 bottom-0 w-72 bg-white shadow-xl flex flex-col">
        <div class="p-3 border-b flex items-center justify-between">
            <span class="font-semibold text-sm">Conversas</span>
            <button type="button" class="text-gray-400" data-close-drawer>&times;</button>
        </div>
        <div class="p-2">
            <button type="button" id="assistente-nova-conversa-mobile"
                    class="w-full px-3 py-2 rounded-xl bg-indigo-600 text-white text-sm font-medium">Novo chat</button>
        </div>
        <div id="assistente-lista-conversas-mobile" class="flex-1 overflow-y-auto p-2 space-y-1"></div>
    </div>
</div>

<style>
@keyframes assistente-bounce {
    0%, 80%, 100% { transform: translateY(0); opacity: 0.4; }
    40% { transform: translateY(-4px); opacity: 1; }
}
.assistente-dot {
    width: 6px; height: 6px; border-radius: 9999px; background: #6366f1;
    display: inline-block; animation: assistente-bounce 1.2s infinite ease-in-out;
}
.assistente-dot:nth-child(2) { animation-delay: 0.15s; }
.assistente-dot:nth-child(3) { animation-delay: 0.3s; }
.pa-chip {
    display: inline-flex; align-items: center;
    padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.75rem;
    background: #eef2ff; color: #3730a3; border: 1px solid #c7d2fe; cursor: pointer;
}
.pa-chip:hover { background: #e0e7ff; }
.assistente-item { border: 1px solid transparent; }
.assistente-item.ativo { background: #fff; border-color: #c7d2fe; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
</style>

<script>
(function () {
    var root = document.getElementById('assistente-root');
    if (!root) return;

    var disponivel = root.getAttribute('data-disponivel') === '1';
    var historicoOn = root.getAttribute('data-historico') === '1';
    var csrf = root.getAttribute('data-csrf');
    var urls = {
        stream: root.getAttribute('data-url-stream'),
        listar: root.getAttribute('data-url-listar'),
        obter: root.getAttribute('data-url-obter'),
        criar: root.getAttribute('data-url-criar'),
        renomear: root.getAttribute('data-url-renomear'),
        excluir: root.getAttribute('data-url-excluir')
    };

    var conversas = [];
    try { conversas = JSON.parse(root.getAttribute('data-conversas') || '[]') || []; } catch (e) { conversas = []; }

    var conversaId = null;
    var enviando = false;
    var loadingEl = null;
    var streamBubble = null;

    var msgs = document.getElementById('assistente-mensagens');
    var form = document.getElementById('assistente-form');
    var input = document.getElementById('assistente-input');
    var btn = document.getElementById('assistente-enviar');
    var tituloEl = document.getElementById('assistente-titulo-ativo');
    var subEl = document.getElementById('assistente-subtitulo-ativo');
    var listaEl = document.getElementById('assistente-lista-conversas');
    var listaMobile = document.getElementById('assistente-lista-conversas-mobile');
    var drawer = document.getElementById('assistente-drawer');

    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function fmtData(iso) {
        if (!iso) return '—';
        var m = String(iso).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? (m[3] + '/' + m[2] + '/' + m[1]) : esc(iso);
    }
    function fmtNota(n) {
        if (n === null || n === undefined || n === '') return '—';
        var x = Number(n);
        return isNaN(x) ? esc(n) : (Math.round(x * 100) / 100).toString().replace('.', ',');
    }
    function simpleMarkdown(text) {
        var t = esc(text || '');
        t = t.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
        t = t.replace(/^### (.+)$/gm, '<p class="font-semibold mt-2">$1</p>');
        t = t.replace(/^- (.+)$/gm, '<li>$1</li>');
        t = t.replace(/(?:<li>.*<\/li>\n?)+/g, function (b) {
            return '<ul class="list-disc pl-4 my-1">' + b.replace(/\n/g, '') + '</ul>';
        });
        t = t.replace(/\n{2,}/g, '</p><p>').replace(/\n/g, '<br>');
        return '<div><p>' + t + '</p></div>';
    }

    function postForm(url, fields) {
        var body = new URLSearchParams();
        body.set('_token', csrf);
        Object.keys(fields || {}).forEach(function (k) {
            if (fields[k] !== undefined && fields[k] !== null) body.set(k, String(fields[k]));
        });
        return fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: body,
            credentials: 'same-origin'
        }).then(function (r) { return r.json(); });
    }

    function renderLista() {
        function paint(container) {
            if (!container) return;
            container.innerHTML = '';
            if (!conversas.length) {
                container.innerHTML = '<p class="text-xs text-gray-500 px-2 py-3">Nenhum chat ainda.</p>';
                return;
            }
            conversas.forEach(function (c) {
                var btnItem = document.createElement('button');
                btnItem.type = 'button';
                btnItem.className = 'assistente-item w-full text-left rounded-xl px-3 py-2 hover:bg-white transition '
                    + (conversaId === c.id ? 'ativo' : '');
                btnItem.innerHTML = '<div class="text-sm font-medium text-gray-900 truncate">' + esc(c.titulo || 'Nova conversa') + '</div>'
                    + (c.aluno_nome ? '<div class="text-[11px] text-indigo-600 truncate">' + esc(c.aluno_nome) + '</div>' : '')
                    + (c.preview ? '<div class="text-[11px] text-gray-500 truncate mt-0.5">' + esc(c.preview) + '</div>' : '');
                btnItem.addEventListener('click', function () { abrirConversa(c.id); closeDrawer(); });
                container.appendChild(btnItem);
            });
        }
        paint(listaEl);
        paint(listaMobile);
    }

    function setCabecalho(c) {
        tituloEl.textContent = (c && c.titulo) ? c.titulo : 'Nova conversa';
        subEl.textContent = (c && c.aluno_nome) ? ('Contexto: ' + c.aluno_nome) : '';
    }

    function limparMensagens(keepWelcome) {
        msgs.innerHTML = '';
        if (keepWelcome) {
            msgs.innerHTML = document.getElementById('assistente-welcome')
                ? '' : '';
            var welcome = document.createElement('div');
            welcome.className = 'bg-indigo-50 border border-indigo-100 text-indigo-900 rounded-xl px-4 py-3 space-y-1.5';
            welcome.innerHTML = '<p class="font-medium">Como começar</p><ul class="list-disc list-inside text-indigo-800/90 space-y-0.5">'
                + '<li>“Como andam as provas da Maria Clara no 2º Ano B?”</li>'
                + '<li>“Quero ver as jornadas desse aluno.”</li>'
                + '<li>Use <strong>Novo chat</strong> para separar contextos (um aluno por conversa).</li></ul>';
            msgs.appendChild(welcome);
        }
    }

    function appendUser(text) {
        var wrap = document.createElement('div');
        wrap.className = 'ml-10 bg-indigo-600 text-white rounded-xl px-3 py-2 whitespace-pre-wrap';
        wrap.textContent = text || '';
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;
    }

    function appendAssistantShell() {
        var wrap = document.createElement('div');
        wrap.className = 'mr-4 bg-white border border-gray-200 text-gray-800 rounded-xl px-3 py-3 shadow-sm space-y-3';
        msgs.appendChild(wrap);
        msgs.scrollTop = msgs.scrollHeight;
        return wrap;
    }

    function renderOpcoes(container, opcoes) {
        if (!opcoes || !opcoes.length) return;
        var row = document.createElement('div');
        row.className = 'flex flex-wrap gap-2 pt-1';
        opcoes.forEach(function (o) {
            var b = document.createElement('button');
            b.type = 'button';
            b.className = 'pa-chip';
            b.textContent = o;
            b.addEventListener('click', function () {
                if (enviando) return;
                input.value = o;
                form.dispatchEvent(new Event('submit', { cancelable: true }));
            });
            row.appendChild(b);
        });
        container.appendChild(row);
    }

    function renderPainel(container, painel, mensagem) {
        if (mensagem) {
            var intro = document.createElement('div');
            intro.innerHTML = simpleMarkdown(mensagem);
            container.appendChild(intro);
        }
        if (!painel || !painel.tipo) return;

        if (painel.tipo === 'candidatos') {
            var aviso = document.createElement('p');
            aviso.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            aviso.textContent = painel.aviso || 'Há mais de um aluno:';
            container.appendChild(aviso);
            var list = document.createElement('div');
            list.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var html = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Nome</th><th class="px-3 py-2 text-left">Turma</th><th class="px-3 py-2"></th></tr></thead><tbody>';
            (painel.candidatos || []).forEach(function (a) {
                var ask = 'Usar aluno_id ' + a.id + ' (' + (a.nome || '') + (a.turma_nome ? ' — ' + a.turma_nome : '') + ')';
                html += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(a.nome)
                    + '</td><td class="px-3 py-2">' + esc(a.turma_nome || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-chip" data-ask="' + esc(ask) + '">Selecionar</button></td></tr>';
            });
            html += '</tbody></table>';
            list.innerHTML = html;
            list.querySelectorAll('[data-ask]').forEach(function (el) {
                el.addEventListener('click', function () {
                    input.value = el.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            });
            container.appendChild(list);
            return;
        }

        if (painel.tipo === 'candidatos_provas') {
            var avisoP = document.createElement('p');
            avisoP.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoP.textContent = painel.aviso || 'Há mais de uma prova:';
            container.appendChild(avisoP);
            var listP = document.createElement('div');
            listP.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var htmlP = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Prova</th><th class="px-3 py-2 text-left">Matéria</th>'
                + '<th class="px-3 py-2 text-right">Erros</th><th class="px-3 py-2"></th></tr></thead><tbody>';
            (painel.candidatos_provas || []).forEach(function (p) {
                var askP = 'Detalhe da prova_id ' + p.prova_id + ' só questões erradas';
                htmlP += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(p.titulo || '—')
                    + '</td><td class="px-3 py-2">' + esc(p.materia || '—')
                    + '</td><td class="px-3 py-2 text-right text-rose-700">' + esc(p.erros != null ? p.erros : '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-chip" data-ask="' + esc(askP) + '">Ver erros</button></td></tr>';
            });
            htmlP += '</tbody></table>';
            listP.innerHTML = htmlP;
            listP.querySelectorAll('[data-ask]').forEach(function (el) {
                el.addEventListener('click', function () {
                    input.value = el.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            });
            container.appendChild(listP);
            return;
        }

        if (painel.aluno) {
            var head = document.createElement('div');
            head.className = 'rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm';
            head.innerHTML = '<span class="font-semibold">' + esc(painel.aluno.nome) + '</span>'
                + (painel.aluno.turma_nome ? ' <span class="text-gray-500">· ' + esc(painel.aluno.turma_nome) + '</span>' : '');
            container.appendChild(head);
        }

        if (painel.tipo === 'resumo') {
            var cardsR = document.createElement('div');
            cardsR.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function cardR(label, val, extraClass) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (extraClass || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            cardsR.innerHTML = cardR('Provas', painel.total_provas || 0)
                + cardR('Acertos', painel.total_acertos != null ? painel.total_acertos : 0, 'text-emerald-700')
                + cardR('Erros', painel.total_erros != null ? painel.total_erros : 0, 'text-rose-700')
                + cardR('% acerto', painel.percentual_acerto != null ? (painel.percentual_acerto + '%') : '—');
            container.appendChild(cardsR);
            function mini(titulo, rows, key, label) {
                if (!rows || !rows.length) return;
                var box = document.createElement('div');
                box.className = 'overflow-x-auto border border-gray-200 rounded-lg';
                var h = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">' + esc(titulo)
                    + '</div><table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                    + '<th class="px-3 py-2">' + esc(label) + '</th><th class="px-3 py-2">Provas</th>'
                    + '<th class="px-3 py-2 text-emerald-700">Acertos</th><th class="px-3 py-2 text-rose-700">Erros</th>'
                    + '</tr></thead><tbody>';
                rows.forEach(function (r) {
                    h += '<tr class="border-t"><td class="px-3 py-2">' + esc(r[key]) + '</td><td class="px-3 py-2">'
                        + esc(r.quantidade)
                        + '</td><td class="px-3 py-2 text-emerald-700 font-medium">' + esc(r.acertos != null ? r.acertos : 0)
                        + '</td><td class="px-3 py-2 text-rose-700 font-medium">' + esc(r.erros != null ? r.erros : 0)
                        + '</td></tr>';
                });
                h += '</tbody></table>';
                box.innerHTML = h;
                container.appendChild(box);
            }
            mini('Por tipo', painel.por_tipo, 'tipo', 'Tipo');
            mini('Por bimestre', painel.por_bimestre, 'bimestre', 'Bimestre');
            mini('Por matéria', painel.por_materia, 'materia', 'Matéria');
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'lista') {
            var provas = painel.provas || [];
            var box = document.createElement('div');
            box.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var h = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Data</th><th class="px-3 py-2 text-left">Prova</th>'
                + '<th class="px-3 py-2 text-left">Matéria</th><th class="px-3 py-2 text-left">Tipo</th>'
                + '<th class="px-3 py-2 text-left">Bim.</th><th class="px-3 py-2 text-right">Nota</th>'
                + '<th class="px-3 py-2 text-right">Acertos</th><th class="px-3 py-2 text-right">Erros</th>'
                + '<th class="px-3 py-2 text-right">%</th><th class="px-3 py-2 text-left">Ação</th></tr></thead><tbody>';
            if (!provas.length) {
                h += '<tr><td colspan="10" class="px-3 py-4 text-center text-gray-500">Nenhuma prova encontrada.</td></tr>';
            }
            provas.forEach(function (p) {
                var r = p.realizacao || {};
                var ev = p.evento || {};
                var data = r.dia_realizacao || ev.data_prova;
                var tot = r.total_questoes;
                var acertoTxt = tot != null ? ((r.acertos != null ? r.acertos : '—') + '/' + tot) : (r.acertos != null ? r.acertos : '—');
                var pid = p.prova_id || 0;
                h += '<tr class="border-t hover:bg-slate-50/80">'
                    + '<td class="px-3 py-2 whitespace-nowrap">' + fmtData(data) + '</td>'
                    + '<td class="px-3 py-2 font-medium">' + esc(p.titulo || '—') + '</td>'
                    + '<td class="px-3 py-2">' + esc((p.materia && p.materia.nome) || '—') + '</td>'
                    + '<td class="px-3 py-2">' + esc((p.tipo_avaliacao && p.tipo_avaliacao.nome) || '—') + '</td>'
                    + '<td class="px-3 py-2">' + esc(ev.bimestre ? (ev.bimestre + 'º') : '—') + '</td>'
                    + '<td class="px-3 py-2 text-right font-semibold">' + fmtNota(r.nota) + '</td>'
                    + '<td class="px-3 py-2 text-right text-emerald-700">' + esc(acertoTxt) + '</td>'
                    + '<td class="px-3 py-2 text-right text-rose-700">' + esc(r.erros != null ? r.erros : '—') + '</td>'
                    + '<td class="px-3 py-2 text-right">' + (r.percentual_acerto != null ? esc(r.percentual_acerto) + '%' : '—') + '</td>'
                    + '<td class="px-3 py-2"><button type="button" class="assistente-chip text-xs" data-prova-id="' + esc(pid) + '">Ver erros</button></td></tr>';
            });
            h += '</tbody></table>';
            box.innerHTML = h;
            box.querySelectorAll('[data-prova-id]').forEach(function (btnDet) {
                btnDet.addEventListener('click', function () {
                    var id = btnDet.getAttribute('data-prova-id');
                    if (id) enviar('Detalhe da prova_id ' + id + ' só questões erradas');
                });
            });
            container.appendChild(box);
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'detalhe_prova') {
            var dp = painel.detalhe || {};
            var rdp = dp.realizacao || {};
            var headP = document.createElement('div');
            headP.className = 'rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm';
            headP.innerHTML = '<span class="font-semibold">' + esc(dp.titulo || 'Prova') + '</span>'
                + ' <span class="text-gray-500">· ' + esc((dp.materia && dp.materia.nome) || '')
                + ' · nota ' + esc(fmtNota(rdp.nota))
                + ' · ' + esc(rdp.acertos != null ? rdp.acertos : '—') + ' acertos / '
                + esc(rdp.erros != null ? rdp.erros : '—') + ' erros'
                + (dp.somente_erros ? ' · só erros' : '')
                + (dp.versao_adaptada ? ' · versão adaptada (EducaInclui)' : '')
                + '</span>';
            container.appendChild(headP);
            var qs = dp.questoes || [];
            var boxQ = document.createElement('div');
            boxQ.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hq = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">#</th>'
                + '<th class="px-3 py-2 text-left">Enunciado</th>'
                + '<th class="px-3 py-2 text-left">Marcou</th>'
                + '<th class="px-3 py-2 text-left">Correta</th>'
                + '<th class="px-3 py-2 text-left">Resultado</th>'
                + '</tr></thead><tbody>';
            if (!qs.length) {
                hq += '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Sem questões neste detalhe.</td></tr>';
            }
            qs.forEach(function (q) {
                var res = 'Sem resposta';
                var resClass = 'text-gray-500';
                if (q.resultado === 'acerto') {
                    res = 'Acerto';
                    resClass = 'text-emerald-700 font-semibold';
                } else if (q.resultado === 'erro') {
                    res = 'Erro';
                    resClass = 'text-rose-700 font-semibold';
                } else if (q.resultado === 'pendente') {
                    res = 'Pendente';
                    resClass = 'text-amber-700 font-semibold';
                } else if (q.respondeu) {
                    res = q.acertou ? 'Acerto' : 'Erro';
                    resClass = q.acertou ? 'text-emerald-700 font-semibold' : 'text-rose-700 font-semibold';
                }
                var marcou = '—';
                if (q.alternativa_marcada) {
                    marcou = esc((q.alternativa_marcada.letra || '') + ') ' + (q.alternativa_marcada.texto || ''));
                } else if (q.resposta_texto) {
                    marcou = esc(q.resposta_texto);
                }
                var correta = '—';
                if (q.alternativa_correta) {
                    correta = esc((q.alternativa_correta.letra || '') + ') ' + (q.alternativa_correta.texto || ''));
                }
                hq += '<tr class="border-t align-top">'
                    + '<td class="px-3 py-2 whitespace-nowrap font-medium">' + esc(q.numero) + '</td>'
                    + '<td class="px-3 py-2 max-w-xs">' + esc(q.enunciado || '—') + '</td>'
                    + '<td class="px-3 py-2 max-w-[12rem]">' + marcou + '</td>'
                    + '<td class="px-3 py-2 max-w-[12rem]">' + correta + '</td>'
                    + '<td class="px-3 py-2 ' + resClass + '">' + esc(res) + '</td></tr>';
            });
            hq += '</tbody></table>';
            boxQ.innerHTML = hq;
            container.appendChild(boxQ);
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'resumo_jornadas') {
            var cards = document.createElement('div');
            cards.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function card(label, val) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold text-gray-900">' + esc(val) + '</div></div>';
            }
            cards.innerHTML = card('Total', painel.total_jornadas || 0)
                + card('Concluídas', painel.concluidas || 0)
                + card('Em andamento', painel.em_andamento || 0)
                + card('% médio', (painel.percentual_medio != null ? painel.percentual_medio : 0) + '%');
            container.appendChild(cards);
            function miniJ(titulo, rows, key, label) {
                if (!rows || !rows.length) return;
                var box = document.createElement('div');
                box.className = 'overflow-x-auto border border-gray-200 rounded-lg';
                var h = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">' + esc(titulo)
                    + '</div><table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                    + '<th class="px-3 py-2">' + esc(label) + '</th><th class="px-3 py-2">Qtd</th>'
                    + '<th class="px-3 py-2">Concl.</th><th class="px-3 py-2">% médio</th></tr></thead><tbody>';
                rows.forEach(function (r) {
                    h += '<tr class="border-t"><td class="px-3 py-2">' + esc(r[key]) + '</td><td class="px-3 py-2">'
                        + esc(r.quantidade) + '</td><td class="px-3 py-2">' + esc(r.concluidas)
                        + '</td><td class="px-3 py-2 font-medium">' + esc(r.media_pct) + '%</td></tr>';
                });
                h += '</tbody></table>';
                box.innerHTML = h;
                container.appendChild(box);
            }
            miniJ('Por matéria', painel.por_materia, 'materia', 'Matéria');
            miniJ('Por bimestre', painel.por_bimestre, 'bimestre', 'Bimestre');
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'lista_jornadas') {
            var js = painel.jornadas || [];
            var boxJ = document.createElement('div');
            boxJ.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hj = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Jornada</th>'
                + '<th class="px-3 py-2 text-left">Matéria</th>'
                + '<th class="px-3 py-2 text-left">Período</th>'
                + '<th class="px-3 py-2 text-left">Bim.</th>'
                + '<th class="px-3 py-2 text-left">Status aluno</th>'
                + '<th class="px-3 py-2 text-right">%</th>'
                + '<th class="px-3 py-2 text-right">Acertos</th>'
                + '<th class="px-3 py-2 text-right">Erros</th>'
                + '</tr></thead><tbody>';
            if (!js.length) {
                hj += '<tr><td colspan="8" class="px-3 py-4 text-center text-gray-500">Nenhuma jornada encontrada.</td></tr>';
            }
            js.forEach(function (j) {
                var a = j.aluno || {};
                var per = j.periodo || {};
                var periodoTxt = (per.data_inicio || per.data_fim)
                    ? (fmtData(per.data_inicio) + ' → ' + fmtData(per.data_fim))
                    : '—';
                hj += '<tr class="border-t hover:bg-slate-50/80">'
                    + '<td class="px-3 py-2 font-medium">' + esc(j.titulo || '—') + '</td>'
                    + '<td class="px-3 py-2">' + esc((j.materia && j.materia.nome) || '—') + '</td>'
                    + '<td class="px-3 py-2 whitespace-nowrap text-xs">' + periodoTxt + '</td>'
                    + '<td class="px-3 py-2">' + esc(j.bimestre ? (j.bimestre + 'º') : '—') + '</td>'
                    + '<td class="px-3 py-2">' + esc(a.status || '—') + '</td>'
                    + '<td class="px-3 py-2 text-right font-semibold">' + esc(a.percentual_conclusao != null ? a.percentual_conclusao : '—') + '%</td>'
                    + '<td class="px-3 py-2 text-right text-emerald-700">' + esc(a.acertos != null ? a.acertos : '—') + '</td>'
                    + '<td class="px-3 py-2 text-right text-rose-700">' + esc(a.erros != null ? a.erros : '—') + '</td></tr>';
            });
            hj += '</tbody></table>';
            boxJ.innerHTML = hj;
            container.appendChild(boxJ);
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'detalhe_jornada') {
            var d = painel.detalhe || {};
            var headJ = document.createElement('div');
            headJ.className = 'rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm';
            headJ.innerHTML = '<span class="font-semibold">' + esc(d.titulo || 'Jornada') + '</span>'
                + (d.aluno && d.aluno.status ? ' <span class="text-gray-500">· ' + esc(d.aluno.status) + ' · ' + esc(d.aluno.percentual_conclusao) + '%</span>' : '')
                + (d.somente_erros ? ' <span class="text-gray-500">· só erros</span>' : '');
            container.appendChild(headJ);
            var exs = d.exercicios || [];
            var boxE = document.createElement('div');
            boxE.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var he = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">#</th>'
                + '<th class="px-3 py-2 text-left">Módulo</th>'
                + '<th class="px-3 py-2 text-left">Exercício / enunciado</th>'
                + '<th class="px-3 py-2 text-left">Marcou</th>'
                + '<th class="px-3 py-2 text-left">Correta</th>'
                + '<th class="px-3 py-2 text-left">Resultado</th>'
                + '</tr></thead><tbody>';
            if (!exs.length) {
                he += '<tr><td colspan="6" class="px-3 py-4 text-center text-gray-500">Sem exercícios neste detalhe.</td></tr>';
            }
            exs.forEach(function (e) {
                var res = !e.respondeu ? 'Não respondeu' : (e.acertou ? 'Acerto' : 'Erro');
                var resClass = !e.respondeu ? 'text-gray-500' : (e.acertou ? 'text-emerald-700 font-semibold' : 'text-rose-700 font-semibold');
                var marcou = '—';
                if (e.alternativa_marcada) {
                    marcou = esc((e.alternativa_marcada.letra || '') + ') ' + (e.alternativa_marcada.texto || ''));
                } else if (e.resposta_aluno) {
                    marcou = esc(e.resposta_aluno);
                }
                var correta = '—';
                if (e.alternativa_correta) {
                    correta = esc((e.alternativa_correta.letra || '') + ') ' + (e.alternativa_correta.texto || ''));
                } else if (e.resposta_correta) {
                    correta = esc(e.resposta_correta);
                }
                var tituloEx = e.titulo || '';
                var enunciado = e.enunciado || '';
                var celEx = esc(tituloEx);
                if (enunciado && enunciado !== tituloEx) {
                    celEx += '<div class="text-xs text-gray-500 mt-0.5">' + esc(enunciado) + '</div>';
                }
                he += '<tr class="border-t align-top">'
                    + '<td class="px-3 py-2 whitespace-nowrap font-medium">' + esc(e.numero != null ? e.numero : '') + '</td>'
                    + '<td class="px-3 py-2">' + esc(e.modulo || '—') + '</td>'
                    + '<td class="px-3 py-2 max-w-xs">' + celEx + '</td>'
                    + '<td class="px-3 py-2 max-w-[12rem]">' + marcou + '</td>'
                    + '<td class="px-3 py-2 max-w-[12rem]">' + correta + '</td>'
                    + '<td class="px-3 py-2 ' + resClass + '">' + esc(res) + '</td></tr>';
            });
            he += '</tbody></table>';
            boxE.innerHTML = he;
            container.appendChild(boxE);
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'candidatos_professores') {
            var avisoPr = document.createElement('p');
            avisoPr.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoPr.textContent = painel.aviso || 'Há mais de um professor:';
            container.appendChild(avisoPr);
            var listPr = document.createElement('div');
            listPr.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var htmlPr = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Nome</th><th class="px-3 py-2 text-left">E-mail</th><th class="px-3 py-2"></th></tr></thead><tbody>';
            (painel.candidatos || []).forEach(function (p) {
                var askPr = 'Usar professor_id ' + p.id + ' (' + (p.nome || '') + ')';
                htmlPr += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(p.nome)
                    + '</td><td class="px-3 py-2">' + esc(p.email || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-chip" data-ask="' + esc(askPr) + '">Selecionar</button></td></tr>';
            });
            htmlPr += '</tbody></table>';
            listPr.innerHTML = htmlPr;
            listPr.querySelectorAll('[data-ask]').forEach(function (el) {
                el.addEventListener('click', function () {
                    input.value = el.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            });
            container.appendChild(listPr);
            return;
        }

        if (painel.professor) {
            var headProf = document.createElement('div');
            headProf.className = 'rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm';
            headProf.innerHTML = '<span class="font-semibold">' + esc(painel.professor.nome || 'Professor') + '</span>'
                + (painel.professor.email ? ' <span class="text-gray-500">· ' + esc(painel.professor.email) + '</span>' : '');
            container.appendChild(headProf);
        }

        if (painel.tipo === 'resumo_professor') {
            var cardsP = document.createElement('div');
            cardsP.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function cardP(label, val, cls) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (cls || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            cardsP.innerHTML = cardP('Provas', painel.total_provas || 0)
                + cardP('Acertos', painel.total_acertos != null ? painel.total_acertos : 0, 'text-emerald-700')
                + cardP('Erros', painel.total_erros != null ? painel.total_erros : 0, 'text-rose-700')
                + cardP('% acerto', painel.percentual_acerto != null ? (painel.percentual_acerto + '%') : '—');
            container.appendChild(cardsP);
            function miniP(titulo, rows, key, label) {
                if (!rows || !rows.length) return;
                var box = document.createElement('div');
                box.className = 'overflow-x-auto border border-gray-200 rounded-lg';
                var h = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">' + esc(titulo)
                    + '</div><table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                    + '<th class="px-3 py-2">' + esc(label) + '</th><th class="px-3 py-2">Provas</th>'
                    + '<th class="px-3 py-2 text-emerald-700">Acertos</th><th class="px-3 py-2 text-rose-700">Erros</th></tr></thead><tbody>';
                rows.forEach(function (r) {
                    h += '<tr class="border-t"><td class="px-3 py-2">' + esc(r[key]) + '</td><td class="px-3 py-2">'
                        + esc(r.provas) + '</td><td class="px-3 py-2 text-emerald-700 font-medium">' + esc(r.acertos)
                        + '</td><td class="px-3 py-2 text-rose-700 font-medium">' + esc(r.erros) + '</td></tr>';
                });
                h += '</tbody></table>';
                box.innerHTML = h;
                container.appendChild(box);
            }
            miniP('Por matéria', painel.por_materia, 'materia', 'Matéria');
            miniP('Por turma', painel.por_turma, 'turma', 'Turma');
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'lista_provas_professor') {
            var pps = painel.provas || [];
            var boxPp = document.createElement('div');
            boxPp.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hpp = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Prova</th><th class="px-3 py-2 text-left">Matéria</th>'
                + '<th class="px-3 py-2 text-left">Turma</th><th class="px-3 py-2 text-right">Alunos</th>'
                + '<th class="px-3 py-2 text-right">Acertos</th><th class="px-3 py-2 text-right">Erros</th>'
                + '<th class="px-3 py-2 text-right">%</th><th class="px-3 py-2 text-left">Ação</th></tr></thead><tbody>';
            if (!pps.length) {
                hpp += '<tr><td colspan="8" class="px-3 py-4 text-center text-gray-500">Nenhuma prova encontrada.</td></tr>';
            }
            pps.forEach(function (p) {
                var pid = p.prova_id || 0;
                hpp += '<tr class="border-t">'
                    + '<td class="px-3 py-2 font-medium">' + esc(p.titulo || '—') + '</td>'
                    + '<td class="px-3 py-2">' + esc((p.materia && p.materia.nome) || '—') + '</td>'
                    + '<td class="px-3 py-2">' + esc((p.turma && p.turma.nome) || '—') + '</td>'
                    + '<td class="px-3 py-2 text-right">' + esc(p.alunos_finalizaram != null ? p.alunos_finalizaram : '—') + '</td>'
                    + '<td class="px-3 py-2 text-right text-emerald-700">' + esc(p.acertos != null ? p.acertos : '—') + '</td>'
                    + '<td class="px-3 py-2 text-right text-rose-700">' + esc(p.erros != null ? p.erros : '—') + '</td>'
                    + '<td class="px-3 py-2 text-right">' + (p.percentual_acerto != null ? esc(p.percentual_acerto) + '%' : '—') + '</td>'
                    + '<td class="px-3 py-2"><button type="button" class="assistente-chip text-xs" data-ask="Detalhe da prova_id '
                    + esc(pid) + ' do professor">Ver alunos</button></td></tr>';
            });
            hpp += '</tbody></table>';
            boxPp.innerHTML = hpp;
            boxPp.querySelectorAll('[data-ask]').forEach(function (el) {
                el.addEventListener('click', function () {
                    input.value = el.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            });
            container.appendChild(boxPp);
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'detalhe_prova_professor') {
            var dpProf = painel.detalhe || {};
            var headDp = document.createElement('div');
            headDp.className = 'rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm';
            headDp.innerHTML = '<span class="font-semibold">' + esc(dpProf.titulo || 'Prova') + '</span>'
                + ' <span class="text-gray-500">· ' + esc(dpProf.materia || '')
                + ' · ' + esc(dpProf.total_acertos != null ? dpProf.total_acertos : 0) + ' acertos / '
                + esc(dpProf.total_erros != null ? dpProf.total_erros : 0) + ' erros</span>';
            container.appendChild(headDp);
            var als = dpProf.alunos || [];
            var boxA = document.createElement('div');
            boxA.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var ha = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Aluno</th><th class="px-3 py-2 text-left">Turma</th>'
                + '<th class="px-3 py-2 text-right">Nota</th><th class="px-3 py-2 text-right">Acertos</th>'
                + '<th class="px-3 py-2 text-right">Erros</th><th class="px-3 py-2 text-right">%</th></tr></thead><tbody>';
            if (!als.length) {
                ha += '<tr><td colspan="6" class="px-3 py-4 text-center text-gray-500">Nenhum aluno realizou.</td></tr>';
            }
            als.forEach(function (a) {
                ha += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(a.nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.turma_nome || '—')
                    + '</td><td class="px-3 py-2 text-right">' + fmtNota(a.nota)
                    + '</td><td class="px-3 py-2 text-right text-emerald-700">' + esc(a.acertos)
                    + '</td><td class="px-3 py-2 text-right text-rose-700">' + esc(a.erros)
                    + '</td><td class="px-3 py-2 text-right">' + (a.percentual_acerto != null ? esc(a.percentual_acerto) + '%' : '—')
                    + '</td></tr>';
            });
            ha += '</tbody></table>';
            boxA.innerHTML = ha;
            container.appendChild(boxA);
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'ranking_erros_professor') {
            var qsR = painel.questoes || [];
            var headR = document.createElement('div');
            headR.className = 'rounded-lg bg-slate-50 border border-slate-200 px-3 py-2 text-sm';
            headR.innerHTML = '<span class="font-semibold">Questões mais erradas</span>'
                + (painel.prova && painel.prova.titulo ? ' <span class="text-gray-500">· ' + esc(painel.prova.titulo) + '</span>' : '');
            container.appendChild(headR);
            var boxR = document.createElement('div');
            boxR.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hr = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">#</th><th class="px-3 py-2 text-left">Enunciado</th>'
                + '<th class="px-3 py-2 text-right">Erros</th><th class="px-3 py-2 text-right">Acertos</th>'
                + '<th class="px-3 py-2 text-right">% erro</th></tr></thead><tbody>';
            if (!qsR.length) {
                hr += '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Sem erros registrados.</td></tr>';
            }
            qsR.forEach(function (q) {
                hr += '<tr class="border-t align-top"><td class="px-3 py-2">' + esc(q.posicao)
                    + '</td><td class="px-3 py-2 max-w-md">' + esc(q.enunciado || '—')
                    + '</td><td class="px-3 py-2 text-right text-rose-700 font-medium">' + esc(q.erros)
                    + '</td><td class="px-3 py-2 text-right text-emerald-700">' + esc(q.acertos)
                    + '</td><td class="px-3 py-2 text-right">' + (q.percentual_erro != null ? esc(q.percentual_erro) + '%' : '—')
                    + '</td></tr>';
            });
            hr += '</tbody></table>';
            boxR.innerHTML = hr;
            container.appendChild(boxR);
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'saude_professor') {
            var k = painel.kpis || {};
            var cardsS = document.createElement('div');
            cardsS.className = 'grid grid-cols-2 sm:grid-cols-5 gap-2';
            function cardS(label, val, cls) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (cls || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            cardsS.innerHTML = cardS('Alunos', painel.total_alunos || k.total || 0)
                + cardS('Crítico', k.critico || 0, 'text-rose-700')
                + cardS('Atenção', k.atencao || 0, 'text-amber-700')
                + cardS('Monitorar', k.monitorar || 0, 'text-sky-700')
                + cardS('Saudável', k.saudavel || 0, 'text-emerald-700');
            container.appendChild(cardsS);
            var at = painel.alunos_atencao || [];
            var boxS = document.createElement('div');
            boxS.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hs = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Alunos em atenção</div>'
                + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                + '<th class="px-3 py-2">Aluno</th><th class="px-3 py-2">Turma</th><th class="px-3 py-2">Nível</th></tr></thead><tbody>';
            if (!at.length) {
                hs += '<tr><td colspan="3" class="px-3 py-4 text-center text-gray-500">Nenhum aluno em crítico/atenção nesta visão.</td></tr>';
            }
            at.forEach(function (a) {
                hs += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(a.nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.turma_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.nivel_rotulo || a.nivel || '—') + '</td></tr>';
            });
            hs += '</tbody></table>';
            boxS.innerHTML = hs;
            container.appendChild(boxS);
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'candidatos_turmas') {
            var avisoT = document.createElement('p');
            avisoT.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoT.textContent = painel.aviso || 'Há mais de uma turma:';
            container.appendChild(avisoT);
            var listT = document.createElement('div');
            listT.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var ht = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Turma</th><th class="px-3 py-2"></th></tr></thead><tbody>';
            (painel.candidatos || []).forEach(function (t) {
                var ask = 'Usar turma_id ' + t.id + ' (' + (t.nome || '') + ')';
                ht += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(t.nome || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-chip" data-ask="' + esc(ask) + '">Selecionar</button></td></tr>';
            });
            ht += '</tbody></table>';
            listT.innerHTML = ht;
            listT.querySelectorAll('[data-ask]').forEach(function (el) {
                el.addEventListener('click', function () {
                    input.value = el.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            });
            container.appendChild(listT);
            return;
        }

        if (painel.tipo === 'candidatos_blocos') {
            var avisoB = document.createElement('p');
            avisoB.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoB.textContent = painel.aviso || 'Há mais de um bloco:';
            container.appendChild(avisoB);
            var listB = document.createElement('div');
            listB.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hb = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Bloco</th><th class="px-3 py-2 text-left">Data</th><th class="px-3 py-2"></th></tr></thead><tbody>';
            (painel.candidatos || []).forEach(function (b) {
                var bid = b.bloco_id || b.id;
                var ask = 'Usar bloco_id ' + bid + ' (' + (b.titulo || '') + ')';
                hb += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(b.titulo || '—')
                    + '</td><td class="px-3 py-2">' + esc(b.data_prova || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-chip" data-ask="' + esc(ask) + '">Selecionar</button></td></tr>';
            });
            hb += '</tbody></table>';
            listB.innerHTML = hb;
            listB.querySelectorAll('[data-ask]').forEach(function (el) {
                el.addEventListener('click', function () {
                    input.value = el.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            });
            container.appendChild(listB);
            return;
        }

        if (painel.tipo === 'saude_turma') {
            var kT = painel.kpis || {};
            var cardsT = document.createElement('div');
            cardsT.className = 'grid grid-cols-2 sm:grid-cols-5 gap-2';
            function cardT(label, val, cls) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (cls || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            var turmaNome = (painel.turma && painel.turma.nome) ? painel.turma.nome : 'Turma';
            cardsT.innerHTML = cardT(turmaNome, painel.total_alunos || kT.total || 0)
                + cardT('Crítico', kT.critico || 0, 'text-rose-700')
                + cardT('Atenção', kT.atencao || 0, 'text-amber-700')
                + cardT('Monitorar', kT.monitorar || 0, 'text-sky-700')
                + cardT('Saudável', kT.saudavel || 0, 'text-emerald-700');
            container.appendChild(cardsT);
            var atT = painel.alunos_atencao || [];
            var boxT = document.createElement('div');
            boxT.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hst = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Alunos em atenção</div>'
                + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                + '<th class="px-3 py-2">Aluno</th><th class="px-3 py-2">Nível</th></tr></thead><tbody>';
            if (!atT.length) {
                hst += '<tr><td colspan="2" class="px-3 py-4 text-center text-gray-500">Nenhum aluno em crítico/atenção.</td></tr>';
            }
            atT.forEach(function (a) {
                hst += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(a.nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.nivel_rotulo || a.nivel || '—') + '</td></tr>';
            });
            hst += '</tbody></table>';
            boxT.innerHTML = hst;
            container.appendChild(boxT);
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'resumo_turma') {
            var cardsRT = document.createElement('div');
            cardsRT.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function cardRT(label, val) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold text-gray-900">' + esc(val) + '</div></div>';
            }
            cardsRT.innerHTML = cardRT('Provas', painel.total_provas || 0)
                + cardRT('Acertos', painel.total_acertos || 0)
                + cardRT('Erros', painel.total_erros || 0)
                + cardRT('% Acerto', painel.percentual_acerto != null ? painel.percentual_acerto + '%' : '—');
            container.appendChild(cardsRT);
            var pm = painel.por_materia || [];
            if (pm.length) {
                var boxPM = document.createElement('div');
                boxPM.className = 'overflow-x-auto border border-gray-200 rounded-lg';
                var hpm = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Por matéria</div>'
                    + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                    + '<th class="px-3 py-2">Matéria</th><th class="px-3 py-2">Acertos</th><th class="px-3 py-2">Erros</th></tr></thead><tbody>';
                pm.forEach(function (r) {
                    hpm += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(r.materia || '—')
                        + '</td><td class="px-3 py-2">' + esc(r.acertos)
                        + '</td><td class="px-3 py-2">' + esc(r.erros) + '</td></tr>';
                });
                hpm += '</tbody></table>';
                boxPM.innerHTML = hpm;
                container.appendChild(boxPM);
            }
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'lista_blocos') {
            var boxLB = document.createElement('div');
            boxLB.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hlb = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Blocos (' + esc(painel.total || 0) + ')</div>'
                + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                + '<th class="px-3 py-2">Título</th><th class="px-3 py-2">Data</th><th class="px-3 py-2"></th></tr></thead><tbody>';
            (painel.blocos || []).forEach(function (b) {
                var ask = 'Mostrar resultados do bloco_id ' + b.bloco_id;
                hlb += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(b.titulo || '—')
                    + '</td><td class="px-3 py-2">' + esc(b.data_prova || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-chip" data-ask="' + esc(ask) + '">Resultados</button></td></tr>';
            });
            hlb += '</tbody></table>';
            boxLB.innerHTML = hlb;
            boxLB.querySelectorAll('[data-ask]').forEach(function (el) {
                el.addEventListener('click', function () {
                    input.value = el.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            });
            container.appendChild(boxLB);
            renderOpcoes(container, painel.opcoes);
            return;
        }

        if (painel.tipo === 'resultados_bloco') {
            var ind = painel.indicadores || {};
            var cardsRB = document.createElement('div');
            cardsRB.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function cardRB(label, val) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold text-gray-900">' + esc(val) + '</div></div>';
            }
            var tituloBloco = (painel.bloco && painel.bloco.titulo) ? painel.bloco.titulo : 'Bloco';
            cardsRB.innerHTML = cardRB(tituloBloco, ind.concluiram != null ? ind.concluiram + ' concluiram' : '—')
                + cardRB('Atenção', ind.precisam_atencao || 0)
                + cardRB('Aprovados', ind.aprovados || 0)
                + cardRB('Média %', ind.media_geral != null ? ind.media_geral : '—');
            container.appendChild(cardsRB);
            var qe = painel.questoes_mais_erradas || [];
            if (qe.length) {
                var boxQE = document.createElement('div');
                boxQE.className = 'overflow-x-auto border border-gray-200 rounded-lg';
                var hqe = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Questões mais erradas</div>'
                    + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                    + '<th class="px-3 py-2">Enunciado</th><th class="px-3 py-2">Erros</th><th class="px-3 py-2">% erro</th></tr></thead><tbody>';
                qe.forEach(function (q) {
                    hqe += '<tr class="border-t"><td class="px-3 py-2">' + esc(q.enunciado || '—')
                        + '</td><td class="px-3 py-2">' + esc(q.erros)
                        + '</td><td class="px-3 py-2">' + esc(q.percentual_erro != null ? q.percentual_erro + '%' : '—') + '</td></tr>';
                });
                hqe += '</tbody></table>';
                boxQE.innerHTML = hqe;
                container.appendChild(boxQE);
            }
            var atB = painel.alunos_atencao || [];
            if (atB.length) {
                var boxAB = document.createElement('div');
                boxAB.className = 'overflow-x-auto border border-gray-200 rounded-lg';
                var hab = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Alunos em atenção</div>'
                    + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                    + '<th class="px-3 py-2">Aluno</th><th class="px-3 py-2">Turma</th><th class="px-3 py-2">%</th></tr></thead><tbody>';
                atB.forEach(function (a) {
                    hab += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(a.nome || '—')
                        + '</td><td class="px-3 py-2">' + esc(a.turma_nome || '—')
                        + '</td><td class="px-3 py-2">' + esc(a.percentual != null ? a.percentual + '%' : '—') + '</td></tr>';
                });
                hab += '</tbody></table>';
                boxAB.innerHTML = hab;
                container.appendChild(boxAB);
            }
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'resumo_jornadas_professor') {
            var totJ = painel.totais || {};
            var cardsJP = document.createElement('div');
            cardsJP.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function cardJP(label, val) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold text-gray-900">' + esc(val) + '</div></div>';
            }
            cardsJP.innerHTML = cardJP('Jornadas', painel.jornadas_no_escopo != null ? painel.jornadas_no_escopo : '—')
                + cardJP('Atribuídas', totJ.atribuidas != null ? totJ.atribuidas : '—')
                + cardJP('Concluídas', totJ.concluidas != null ? totJ.concluidas : '—')
                + cardJP('Taxa %', totJ.taxa_pct != null ? totJ.taxa_pct : '—');
            container.appendChild(cardsJP);
            var atJ = painel.alunos_atencao || [];
            var boxJP = document.createElement('div');
            boxJP.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hjp = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Alunos em atenção (jornadas)</div>'
                + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                + '<th class="px-3 py-2">Aluno</th><th class="px-3 py-2">Turma</th><th class="px-3 py-2">Taxa</th><th class="px-3 py-2">Pend.</th></tr></thead><tbody>';
            if (!atJ.length) {
                hjp += '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Nenhum aluno em atenção nesta visão.</td></tr>';
            }
            atJ.forEach(function (a) {
                hjp += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(a.nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.turma_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.taxa_pct != null ? a.taxa_pct + '%' : '—')
                    + '</td><td class="px-3 py-2">' + esc(a.pendentes != null ? a.pendentes : '—') + '</td></tr>';
            });
            hjp += '</tbody></table>';
            boxJP.innerHTML = hjp;
            container.appendChild(boxJP);
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'boletim_aluno') {
            var evs = painel.eventos || [];
            if (!evs.length) {
                var vazioB = document.createElement('p');
                vazioB.className = 'text-sm text-gray-600';
                vazioB.textContent = 'Nenhum boletim gerado visível para coordenação.';
                container.appendChild(vazioB);
            }
            evs.forEach(function (ev) {
                var boxEv = document.createElement('div');
                boxEv.className = 'overflow-x-auto border border-gray-200 rounded-lg mb-2';
                var tituloEv = (ev.regra_nome || 'Boletim')
                    + (ev.bimestre ? ' · ' + ev.bimestre + 'º bim' : '')
                    + (ev.ano_letivo ? ' · ' + ev.ano_letivo : '');
                var cols = ev.colunas || [];
                var hev = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">' + esc(tituloEv) + '</div>'
                    + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                    + '<th class="px-3 py-2">Matéria</th>';
                cols.forEach(function (c) {
                    hev += '<th class="px-3 py-2">' + esc(c.label || c.nome || c.chave || '—') + '</th>';
                });
                hev += '</tr></thead><tbody>';
                (ev.linhas || []).forEach(function (linha) {
                    hev += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(linha.materia || '—') + '</td>';
                    var notas = linha.notas || {};
                    if (Array.isArray(notas)) {
                        notas.forEach(function (n) {
                            hev += '<td class="px-3 py-2">' + esc(n.valor != null ? n.valor : (n != null ? n : '—')) + '</td>';
                        });
                    } else if (cols.length) {
                        cols.forEach(function (c) {
                            var key = c.chave || c.key || c.id || c.nome;
                            var val = key != null ? notas[key] : null;
                            if (val && typeof val === 'object') val = val.valor != null ? val.valor : '—';
                            hev += '<td class="px-3 py-2">' + esc(val != null ? val : '—') + '</td>';
                        });
                    } else {
                        hev += '<td class="px-3 py-2" colspan="4">' + esc(JSON.stringify(notas)) + '</td>';
                    }
                    hev += '</tr>';
                });
                hev += '</tbody></table>';
                boxEv.innerHTML = hev;
                container.appendChild(boxEv);
            });
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'faltas_aluno') {
            var freq = painel.frequencia || null;
            var cardsF = document.createElement('div');
            cardsF.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function cardF(label, val, cls) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (cls || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            if (freq) {
                cardsF.innerHTML = cardF('Aulas', freq.total_aulas || 0)
                    + cardF('Presenças', freq.presencas || 0)
                    + cardF('Faltas', freq.faltas || 0, 'text-rose-700')
                    + cardF('Frequência', freq.percentual != null ? freq.percentual + '%' : '—');
            } else {
                cardsF.innerHTML = cardF('Frequência', 'Sem dados');
            }
            container.appendChild(cardsF);
            if (painel.turma_percentual != null) {
                var tipF = document.createElement('p');
                tipF.className = 'text-xs text-slate-500';
                tipF.textContent = 'Média da turma no período: ' + painel.turma_percentual + '%';
                container.appendChild(tipF);
            }
            if (painel.aviso) {
                var avF = document.createElement('p');
                avF.className = 'text-sm text-amber-800';
                avF.textContent = painel.aviso;
                container.appendChild(avF);
            }
            renderOpcoes(container, painel.opcoes);
        }
    }

    function mostrarStatus(texto) {
        removerCarregando();
        loadingEl = document.createElement('div');
        loadingEl.className = 'mr-4 text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 inline-flex items-center gap-2';
        loadingEl.innerHTML = '<span class="assistente-dot"></span><span class="assistente-dot"></span><span class="assistente-dot"></span><span></span>';
        loadingEl.querySelector('span:last-child').textContent = texto || 'Pensando…';
        msgs.appendChild(loadingEl);
        msgs.scrollTop = msgs.scrollHeight;
    }
    function removerCarregando() {
        if (loadingEl && loadingEl.parentNode) loadingEl.parentNode.removeChild(loadingEl);
        loadingEl = null;
    }
    function setEnviando(ativo) {
        enviando = !!ativo;
        if (btn) btn.disabled = enviando || !disponivel;
        if (input) input.disabled = enviando || !disponivel;
    }

    function upsertConversaNaLista(id, titulo, alunoNome) {
        var found = null;
        for (var i = 0; i < conversas.length; i++) {
            if (conversas[i].id === id) { found = conversas[i]; break; }
        }
        if (found) {
            if (titulo) found.titulo = titulo;
            if (alunoNome) found.aluno_nome = alunoNome;
            conversas = [found].concat(conversas.filter(function (c) { return c.id !== id; }));
        } else {
            conversas.unshift({ id: id, titulo: titulo || 'Nova conversa', aluno_nome: alunoNome || null, preview: null });
        }
        renderLista();
    }

    async function novaConversa() {
        if (!historicoOn) {
            conversaId = null;
            setCabecalho({ titulo: 'Nova conversa' });
            limparMensagens(true);
            renderLista();
            return;
        }
        var json = await postForm(urls.criar, { titulo: 'Nova conversa' });
        if (!json.success) {
            alert(json.error || 'Falha ao criar chat');
            return;
        }
        conversaId = json.data.id;
        upsertConversaNaLista(conversaId, json.data.titulo);
        setCabecalho(json.data);
        limparMensagens(true);
        renderLista();
    }

    async function abrirConversa(id) {
        if (!historicoOn) return;
        conversaId = id;
        renderLista();
        limparMensagens(false);
        mostrarStatus('Carregando conversa…');
        try {
            var res = await fetch(urls.obter + '?id=' + encodeURIComponent(id), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin'
            });
            var json = await res.json();
            removerCarregando();
            if (!json.success) {
                var e = appendAssistantShell();
                e.textContent = json.error || 'Conversa não encontrada';
                return;
            }
            setCabecalho(json.data);
            upsertConversaNaLista(json.data.id, json.data.titulo, json.data.aluno_nome);
            var mensagens = json.data.mensagens || [];
            if (!mensagens.length) {
                limparMensagens(true);
                return;
            }
            mensagens.forEach(function (m) {
                if (m.role === 'user') appendUser(m.conteudo);
                else {
                    var shell = appendAssistantShell();
                    if (m.painel) renderPainel(shell, m.painel, m.conteudo);
                    else shell.innerHTML = simpleMarkdown(m.conteudo || '');
                }
            });
        } catch (err) {
            removerCarregando();
            var fail = appendAssistantShell();
            fail.textContent = 'Falha ao abrir conversa.';
        }
    }

    async function renomear() {
        if (!conversaId || !historicoOn) return;
        var atual = tituloEl.textContent || 'Nova conversa';
        var novo = prompt('Nome da conversa', atual);
        if (novo == null) return;
        novo = String(novo).trim();
        if (!novo) return;
        var json = await postForm(urls.renomear, { id: conversaId, titulo: novo });
        if (json.success) {
            tituloEl.textContent = novo;
            upsertConversaNaLista(conversaId, novo);
        } else alert(json.error || 'Falha ao renomear');
    }

    async function excluir() {
        if (!conversaId || !historicoOn) {
            await novaConversa();
            return;
        }
        if (!confirm('Excluir esta conversa?')) return;
        var json = await postForm(urls.excluir, { id: conversaId });
        if (!json.success) {
            alert(json.error || 'Falha ao excluir');
            return;
        }
        conversas = conversas.filter(function (c) { return c.id !== conversaId; });
        conversaId = null;
        renderLista();
        if (conversas.length) abrirConversa(conversas[0].id);
        else await novaConversa();
    }

    function closeDrawer() {
        if (drawer) drawer.classList.add('hidden');
    }
    function openDrawer() {
        if (drawer) drawer.classList.remove('hidden');
    }

    async function enviar(mensagem) {
        if (!disponivel || enviando) return;
        mensagem = (mensagem || '').trim();
        if (!mensagem) return;

        // Garante conversa antes da 1ª mensagem
        if (historicoOn && !conversaId) {
            var criada = await postForm(urls.criar, { titulo: 'Nova conversa' });
            if (criada.success) {
                conversaId = criada.data.id;
                upsertConversaNaLista(conversaId, criada.data.titulo);
                setCabecalho(criada.data);
            }
        }

        // Remove welcome
        var welcome = msgs.querySelector('.bg-indigo-50');
        if (welcome) welcome.remove();

        appendUser(mensagem);
        input.value = '';
        setEnviando(true);
        mostrarStatus('Consultando…');
        streamBubble = null;

        var body = new URLSearchParams();
        body.set('_token', csrf);
        body.set('mensagem', mensagem);
        if (conversaId) body.set('conversa_id', String(conversaId));

        try {
            var res = await fetch(urls.stream, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'Accept': 'text/event-stream',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: body,
                credentials: 'same-origin'
            });
            if (!res.ok || !res.body) {
                removerCarregando();
                var errShell = appendAssistantShell();
                errShell.textContent = 'Falha na requisição (' + res.status + ').';
                setEnviando(false);
                return;
            }

            var reader = res.body.getReader();
            var decoder = new TextDecoder('utf-8');
            var buffer = '';
            var mensagemFinal = '';
            var painelFinal = null;
            var erro = null;

            while (true) {
                var chunk = await reader.read();
                if (chunk.done) break;
                buffer += decoder.decode(chunk.value, { stream: true });
                var parts = buffer.split('\n\n');
                buffer = parts.pop() || '';
                for (var i = 0; i < parts.length; i++) {
                    var block = parts[i].trim();
                    if (!block) continue;
                    var eventName = 'message', dataLine = '';
                    var lines = block.split('\n');
                    for (var L = 0; L < lines.length; L++) {
                        if (lines[L].indexOf('event:') === 0) eventName = lines[L].slice(6).trim();
                        if (lines[L].indexOf('data:') === 0) dataLine += lines[L].slice(5).trim();
                    }
                    if (!dataLine) continue;
                    var data;
                    try { data = JSON.parse(dataLine); } catch (e) { continue; }
                    if (eventName === 'status') mostrarStatus(data.text || 'Consultando…');
                    else if (eventName === 'chunk') {
                        removerCarregando();
                        if (!streamBubble) streamBubble = appendAssistantShell();
                        if (!streamBubble._textNode) {
                            streamBubble._textNode = document.createElement('div');
                            streamBubble._textNode.className = 'whitespace-pre-wrap';
                            streamBubble.appendChild(streamBubble._textNode);
                        }
                        streamBubble._textNode.textContent += (data.text || '');
                        mensagemFinal = streamBubble._textNode.textContent;
                        msgs.scrollTop = msgs.scrollHeight;
                    } else if (eventName === 'done') {
                        removerCarregando();
                        mensagemFinal = (data.mensagem || mensagemFinal || '').trim();
                        painelFinal = data.painel || null;
                        if (data.conversa_id) {
                            conversaId = data.conversa_id;
                            upsertConversaNaLista(conversaId, data.conversa_titulo || tituloEl.textContent,
                                painelFinal && painelFinal.aluno ? painelFinal.aluno.nome : null);
                            if (data.conversa_titulo) tituloEl.textContent = data.conversa_titulo;
                            if (painelFinal && painelFinal.aluno && painelFinal.aluno.nome) {
                                subEl.textContent = 'Contexto: ' + painelFinal.aluno.nome;
                            }
                        }
                    } else if (eventName === 'error') {
                        erro = data.error || 'Erro no assistente.';
                    }
                }
            }

            removerCarregando();
            if (erro) {
                var eShell = appendAssistantShell();
                eShell.textContent = erro;
            } else if (painelFinal || mensagemFinal) {
                if (streamBubble && streamBubble.parentNode) streamBubble.parentNode.removeChild(streamBubble);
                streamBubble = null;
                var shell = appendAssistantShell();
                if (painelFinal) renderPainel(shell, painelFinal, mensagemFinal);
                else shell.innerHTML = simpleMarkdown(mensagemFinal);
            }
        } catch (e) {
            removerCarregando();
            var fail = appendAssistantShell();
            fail.textContent = 'Não foi possível falar com o assistente.';
        }
        setEnviando(false);
        if (input) input.focus();
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        enviar(input.value);
    });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }
    });

    document.getElementById('assistente-nova-conversa').addEventListener('click', novaConversa);
    document.getElementById('assistente-nova-conversa-mobile').addEventListener('click', function () {
        novaConversa(); closeDrawer();
    });
    document.getElementById('assistente-renomear').addEventListener('click', renomear);
    document.getElementById('assistente-excluir').addEventListener('click', excluir);
    document.getElementById('assistente-btn-mobile-hist').addEventListener('click', openDrawer);
    drawer.querySelectorAll('[data-close-drawer]').forEach(function (el) {
        el.addEventListener('click', closeDrawer);
    });

    renderLista();
    if (historicoOn && conversas.length) {
        abrirConversa(conversas[0].id);
    } else {
        setCabecalho({ titulo: 'Nova conversa' });
    }
})();
</script>
