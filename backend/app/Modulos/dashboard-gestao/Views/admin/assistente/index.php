<?php
$csrfToken = (string) ($csrf_token ?? '');
$disponivel = !empty($assistente_disponivel);
$historicoDisponivel = !empty($historico_disponivel);
$conversasIniciais = is_array($conversas_iniciais ?? null) ? $conversas_iniciais : [];
$urlBase = URL . '/admin/assistente';
$assistenteAvatar = htmlspecialchars(
    (string) ($assistente_avatar_url ?? (URL . '/static/images/avatar-assistente-documentos.webp')),
    ENT_QUOTES,
    'UTF-8'
);
$assistenteNome = htmlspecialchars((string) ($assistente_nome ?? 'Tudinha'), ENT_QUOTES, 'UTF-8');
?>
<div class="space-y-3"
     id="assistente-root"
     data-disponivel="<?= $disponivel ? '1' : '0' ?>"
     data-historico="<?= $historicoDisponivel ? '1' : '0' ?>"
     data-avatar="<?= $assistenteAvatar ?>"
     data-nome="<?= $assistenteNome ?>"
     data-url-stream="<?= htmlspecialchars($urlBase . '/mensagem-stream', ENT_QUOTES, 'UTF-8') ?>"
     data-url-listar="<?= htmlspecialchars($urlBase . '/conversas', ENT_QUOTES, 'UTF-8') ?>"
     data-url-obter="<?= htmlspecialchars($urlBase . '/conversa', ENT_QUOTES, 'UTF-8') ?>"
     data-url-criar="<?= htmlspecialchars($urlBase . '/conversas/criar', ENT_QUOTES, 'UTF-8') ?>"
     data-url-renomear="<?= htmlspecialchars($urlBase . '/conversas/renomear', ENT_QUOTES, 'UTF-8') ?>"
     data-url-excluir="<?= htmlspecialchars($urlBase . '/conversas/excluir', ENT_QUOTES, 'UTF-8') ?>"
     data-url-confirmar="<?= htmlspecialchars($urlBase . '/confirmar-proposta', ENT_QUOTES, 'UTF-8') ?>"
     data-csrf="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
     data-conversas="<?= htmlspecialchars(json_encode($conversasIniciais, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>">

        <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2">
        <div class="flex items-center gap-3 min-w-0">
            <img src="<?= $assistenteAvatar ?>" width="56" height="56" alt="<?= $assistenteNome ?>"
                 class="w-14 h-14 rounded-full shadow-md object-cover shrink-0 bg-white">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold text-gray-900"><?= $assistenteNome ?></h1>
                <p class="text-sm text-gray-600 mt-1">
                    Consulte aluno/turma/professor e proponha ocorrência, entrada/saída ou chamada (com confirmação).
                </p>
            </div>
        </div>
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
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center justify-between gap-2 bg-slate-50">
                <div class="flex items-center gap-2.5 min-w-0">
                    <img src="<?= $assistenteAvatar ?>" width="40" height="40" alt=""
                         class="w-10 h-10 rounded-full object-cover shrink-0 bg-white shadow-sm">
                    <div class="min-w-0">
                        <p id="assistente-titulo-ativo" class="text-sm font-semibold text-gray-900 truncate">Nova conversa</p>
                        <p id="assistente-subtitulo-ativo" class="text-xs text-gray-500 truncate"></p>
                    </div>
                </div>
                <div class="flex items-center gap-1 shrink-0">
                    <button type="button" id="assistente-btn-mobile-hist"
                            class="sm:hidden px-2 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-600">Chats</button>
                    <button type="button" id="assistente-renomear" class="px-2 py-1.5 text-xs rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50" title="Renomear">Renomear</button>
                    <button type="button" id="assistente-excluir" class="px-2 py-1.5 text-xs rounded-lg border border-rose-200 text-rose-600 hover:bg-rose-50" title="Excluir">Excluir</button>
                </div>
            </div>

            <div id="assistente-mensagens" class="flex-1 overflow-y-auto p-4 space-y-3 text-sm bg-slate-50/40">
                <div class="flex items-start gap-2.5" id="assistente-welcome">
                    <img src="<?= $assistenteAvatar ?>" width="36" height="36" alt="<?= $assistenteNome ?>"
                         class="w-9 h-9 rounded-full object-cover shrink-0 bg-white shadow-sm mt-0.5">
                    <div class="bg-indigo-50 border border-indigo-100 text-indigo-900 rounded-xl px-4 py-3 space-y-1.5 min-w-0">
                        <p class="font-medium">Como começar</p>
                        <ul class="list-disc list-inside text-indigo-800/90 space-y-0.5">
                            <li>“Como andam as provas da Maria Clara no 2º Ano B?”</li>
                            <li>“Como está a saúde da turma 2ªB?”</li>
                            <li>“Quem precisa de atenção na escola em 2024?”</li>
                            <li>“Me dá um 360 da Ana Luiza.”</li>
                            <li>“Quais diários estão em atraso?”</li>
                            <li>“Qual aula a turma 1A tem hoje?”</li>
                            <li>“Resultados do bloco Simulado 1 — quem precisa de atenção?”</li>
                            <li>“Boletim do RA 24254 em 2024.”</li>
                            <li>“Notas do 1º bimestre / MU do 2º bimestre do RA 24254.”</li>
                            <li>“Tem ocorrência do aluno RA 24254?”</li>
                            <li>“Como estão as jornadas do professor Ana?”</li>
                            <li>Use <strong>Novo chat</strong> para separar contextos.</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="border-t border-gray-100 bg-white px-3 pt-3 pb-2 space-y-2">
                <form id="assistente-form" class="flex gap-2">
                    <textarea id="assistente-input" rows="2"
                              class="flex-1 text-sm border border-gray-300 rounded-xl px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 resize-none"
                              placeholder="Pergunte sobre aluno, professor, turma, bimestre…"
                              <?= $disponivel ? '' : 'disabled' ?>></textarea>
                    <button type="submit" id="assistente-enviar"
                            class="self-end px-4 py-2.5 bg-indigo-600 text-white text-sm font-medium rounded-xl hover:bg-indigo-700 disabled:opacity-50"
                            <?= $disponivel ? '' : 'disabled' ?>>Enviar</button>
                </form>
                <p class="text-[11px] leading-snug text-gray-500 px-0.5">
                    A IA pode errar ou não entender o contexto. Em qualquer situação, comunique a equipe técnica.
                </p>
            </div>
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
.pa-btn-confirmar {
    display: inline-flex; align-items: center; justify-content: center;
    padding: 0.5rem 1rem; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 600;
    background: #4f46e5; color: #fff; border: none; cursor: pointer;
}
.pa-btn-confirmar:hover { background: #4338ca; }
.pa-btn-confirmar:disabled { opacity: 0.6; cursor: not-allowed; }
.assistente-item { border: 1px solid transparent; }
.assistente-item.ativo { background: #fff; border-color: #c7d2fe; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
</style>

<script>
(function () {
    var root = document.getElementById('assistente-root');
    if (!root) return;

    var disponivel = root.getAttribute('data-disponivel') === '1';
    var historicoOn = root.getAttribute('data-historico') === '1';
    var avatarUrl = root.getAttribute('data-avatar') || '';
    var assistenteNome = root.getAttribute('data-nome') || 'Tudinha';
    var csrf = root.getAttribute('data-csrf');

    function avatarHtml(sizeClass) {
        if (!avatarUrl) return '';
        return '<img src="' + esc(avatarUrl) + '" width="36" height="36" alt="' + esc(assistenteNome)
            + '" class="' + (sizeClass || 'w-9 h-9') + ' rounded-full object-cover shrink-0 bg-white shadow-sm mt-0.5">';
    }
    var urls = {
        stream: root.getAttribute('data-url-stream'),
        listar: root.getAttribute('data-url-listar'),
        obter: root.getAttribute('data-url-obter'),
        criar: root.getAttribute('data-url-criar'),
        renomear: root.getAttribute('data-url-renomear'),
        excluir: root.getAttribute('data-url-excluir'),
        confirmar: root.getAttribute('data-url-confirmar')
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
    function limparTextoAssistente(text) {
        var s = String(text || '');
        s = s.replace(/\[contexto_estruturado\][\s\S]*$/i, '');
        s = s.replace(/^\s*aluno_id\s*=\s*\d+.*$/gmi, '');
        return s.trim();
    }
    function simpleMarkdown(text) {
        var t = esc(limparTextoAssistente(text || ''));
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
            var welcome = document.createElement('div');
            welcome.className = 'flex items-start gap-2.5';
            welcome.id = 'assistente-welcome';
            welcome.innerHTML = avatarHtml()
                + '<div class="bg-indigo-50 border border-indigo-100 text-indigo-900 rounded-xl px-4 py-3 space-y-1.5 min-w-0">'
                + '<p class="font-medium">Como começar</p><ul class="list-disc list-inside text-indigo-800/90 space-y-0.5">'
                + '<li>“Como andam as provas da Maria Clara no 2º Ano B?”</li>'
                + '<li>“Quero ver as jornadas desse aluno.”</li>'
                + '<li>Use <strong>Novo chat</strong> para separar contextos (um aluno por conversa).</li></ul></div>';
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
        var row = document.createElement('div');
        row.className = 'flex items-start gap-2.5 mr-4';
        if (avatarUrl) {
            var img = document.createElement('img');
            img.src = avatarUrl;
            img.width = 36;
            img.height = 36;
            img.alt = assistenteNome;
            img.className = 'w-9 h-9 rounded-full object-cover shrink-0 bg-white shadow-sm mt-0.5';
            row.appendChild(img);
        }
        var wrap = document.createElement('div');
        wrap.className = 'bg-white border border-gray-200 text-gray-800 rounded-xl px-3 py-3 shadow-sm space-y-3 min-w-0 flex-1';
        row.appendChild(wrap);
        msgs.appendChild(row);
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
                var ask = 'Aluna ' + (a.nome || '')
                    + (a.turma_nome ? ' da turma ' + a.turma_nome : '')
                    + (a.ra ? ' RA ' + a.ra : '')
                    + (a.id ? ' aluno_id=' + a.id : '');
                html += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(a.nome)
                    + '</td><td class="px-3 py-2">' + esc(a.turma_nome || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-btn-confirmar" data-ask="' + esc(ask) + '">Confirmar</button></td></tr>';
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

        if (painel.tipo === 'candidatos_aulas') {
            var avisoA = document.createElement('p');
            avisoA.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoA.textContent = painel.aviso || 'Há mais de uma aula:';
            container.appendChild(avisoA);
            var listA = document.createElement('div');
            listA.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var htmlA = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                + '<th class="px-3 py-2 text-left">Aula</th><th class="px-3 py-2 text-left">Matéria</th>'
                + '<th class="px-3 py-2 text-left">Horário</th><th class="px-3 py-2"></th></tr></thead><tbody>';
            (painel.candidatos_aulas || []).forEach(function (a) {
                var askA = 'Aula de ' + (a.materia_nome || 'matéria')
                    + (a.turma_nome ? ' na turma ' + a.turma_nome : '')
                    + (a.data_aula ? ' em ' + a.data_aula : '')
                    + (a.horario ? ' às ' + a.horario : '')
                    + (a.diario_aula_id ? ' diario_aula_id=' + a.diario_aula_id : '');
                htmlA += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(a.turma_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.materia_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.horario || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-btn-confirmar" data-ask="' + esc(askA) + '">Confirmar</button></td></tr>';
            });
            htmlA += '</tbody></table>';
            listA.innerHTML = htmlA;
            listA.querySelectorAll('[data-ask]').forEach(function (el) {
                el.addEventListener('click', function () {
                    input.value = el.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
            });
            container.appendChild(listA);
            return;
        }

        if (painel.tipo === 'candidatos_bimestres') {
            var avisoBim = document.createElement('p');
            avisoBim.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoBim.textContent = painel.aviso || 'Há mais de um bimestre:';
            container.appendChild(avisoBim);
            var listBim = document.createElement('div');
            listBim.className = 'flex flex-wrap gap-2';
            var colB = painel.coluna || 'PP';
            var matB = painel.materia || '';
            var alunoB = (painel.aluno && painel.aluno.nome) ? painel.aluno.nome : '';
            var raB = (painel.aluno && painel.aluno.ra) ? String(painel.aluno.ra) : '';
            var idB = (painel.aluno && painel.aluno.id) ? String(painel.aluno.id) : '';
            var anoB = painel.ano_letivo || '';
            (painel.candidatos_bimestres || []).forEach(function (b) {
                var askBim = 'Nota da ' + colB
                    + (matB ? ' de ' + matB : '')
                    + (alunoB ? ' da aluna ' + alunoB : '')
                    + (raB ? ' RA ' + raB : (idB ? ' aluno_id=' + idB : ''))
                    + ' no ' + (b.bimestre || '') + 'º bimestre'
                    + (anoB ? ' de ' + anoB : '');
                var chip = document.createElement('button');
                chip.type = 'button';
                chip.className = 'pa-btn-confirmar';
                chip.textContent = 'Confirmar — ' + (b.rotulo || ((b.bimestre || '') + 'º bimestre'));
                chip.setAttribute('data-ask', askBim);
                chip.addEventListener('click', function () {
                    input.value = chip.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
                listBim.appendChild(chip);
            });
            container.appendChild(listBim);
            return;
        }

        if (painel.tipo === 'candidatos_materias') {
            var avisoMat = document.createElement('p');
            avisoMat.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoMat.textContent = painel.aviso || 'Há mais de uma matéria:';
            container.appendChild(avisoMat);
            var listMat = document.createElement('div');
            listMat.className = 'flex flex-wrap gap-2';
            var colM = painel.coluna || 'PP';
            var alunoM = (painel.aluno && painel.aluno.nome) ? painel.aluno.nome : '';
            var raM = (painel.aluno && painel.aluno.ra) ? String(painel.aluno.ra) : '';
            var idM = (painel.aluno && painel.aluno.id) ? String(painel.aluno.id) : '';
            var anoM = painel.ano_letivo || '';
            var bimM = painel.bimestre || '';
            (painel.candidatos_materias || []).forEach(function (m) {
                var nomeMat = m.materia || '';
                var askMat = 'Nota da ' + colM + ' de ' + nomeMat
                    + (alunoM ? ' da aluna ' + alunoM : '')
                    + (raM ? ' RA ' + raM : (idM ? ' aluno_id=' + idM : ''))
                    + (bimM ? ' no ' + bimM + 'º bimestre' : '')
                    + (anoM ? ' de ' + anoM : '');
                var chipM = document.createElement('button');
                chipM.type = 'button';
                chipM.className = 'pa-btn-confirmar';
                chipM.textContent = 'Confirmar — ' + (nomeMat || 'Matéria');
                chipM.setAttribute('data-ask', askMat);
                chipM.addEventListener('click', function () {
                    input.value = chipM.getAttribute('data-ask');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
                listMat.appendChild(chipM);
            });
            container.appendChild(listMat);
            return;
        }

        function enviarPerguntaAssistente(texto) {
            input.value = texto;
            form.dispatchEvent(new Event('submit', { cancelable: true }));
        }

        function montarChipsCategoria(lista, avisoTexto) {
            if (avisoTexto) {
                var avisoCat = document.createElement('p');
                avisoCat.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avisoCat.textContent = avisoTexto;
                container.appendChild(avisoCat);
            }
            var listCat = document.createElement('div');
            listCat.className = 'flex flex-wrap gap-2';
            (lista || []).forEach(function (c) {
                var nomeCat = c.nome || '';
                var chipC = document.createElement('button');
                chipC.type = 'button';
                chipC.className = 'pa-btn-confirmar';
                chipC.textContent = nomeCat || 'Categoria';
                chipC.setAttribute('data-ask', 'categoria ' + nomeCat);
                chipC.addEventListener('click', function () {
                    enviarPerguntaAssistente(chipC.getAttribute('data-ask'));
                });
                listCat.appendChild(chipC);
            });
            container.appendChild(listCat);
        }

        if (painel.tipo === 'coleta_ocorrencia') {
            var avisoColeta = document.createElement('p');
            avisoColeta.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoColeta.textContent = painel.aviso || 'Complete os dados da ocorrência:';
            container.appendChild(avisoColeta);
            if (painel.aguardando_campo === 'categoria' && (painel.candidatos_categorias || []).length) {
                montarChipsCategoria(painel.candidatos_categorias, '');
            }
            if (painel.aguardando_campo === 'gravidade' && (painel.opcoes_gravidade || []).length) {
                var listG = document.createElement('div');
                listG.className = 'flex flex-wrap gap-2';
                (painel.opcoes_gravidade || []).forEach(function (o) {
                    var chipG = document.createElement('button');
                    chipG.type = 'button';
                    chipG.className = 'pa-btn-confirmar';
                    chipG.textContent = o.rotulo || o.valor || '';
                    chipG.addEventListener('click', function () {
                        enviarPerguntaAssistente(o.valor || o.rotulo || '');
                    });
                    listG.appendChild(chipG);
                });
                container.appendChild(listG);
            }
            if ((painel.aguardando_campo === 'notificar_pais' || painel.aguardando_campo === 'mais_envolvidos')
                && (painel.opcoes_sim_nao || []).length) {
                var listSN = document.createElement('div');
                listSN.className = 'flex flex-wrap gap-2';
                (painel.opcoes_sim_nao || []).forEach(function (o) {
                    var chipSN = document.createElement('button');
                    chipSN.type = 'button';
                    chipSN.className = 'pa-btn-confirmar';
                    chipSN.textContent = o.rotulo || o.valor || '';
                    chipSN.addEventListener('click', function () {
                        enviarPerguntaAssistente(o.valor === 'sim' ? 'sim' : 'não');
                    });
                    listSN.appendChild(chipSN);
                });
                container.appendChild(listSN);
            }
            return;
        }

        if (painel.tipo === 'candidatos_categorias') {
            montarChipsCategoria(painel.candidatos_categorias || [], painel.aviso || 'Digite a categoria ou escolha uma sugestão:');
            return;
        }

        if (painel.tipo === 'proposta') {
            var d = painel.detalhes || {};
            var payload = painel.payload || {};
            var tituloCard = d.titulo || payload.titulo || 'Proposta pendente';
            var descricaoCard = d.descricao || payload.detalhe || payload.observacao || '';
            var dataHoraCard = d.data_hora_label || '';
            if (!dataHoraCard) {
                var rawDh = d.data_hora || payload.data_ocorrencia || payload.ocorrido_em || '';
                dataHoraCard = rawDh ? String(rawDh) : '—';
            }
            var envolvidosCard = Array.isArray(d.envolvidos) ? d.envolvidos.slice() : [];
            if (!envolvidosCard.length && Array.isArray(payload.alunos)) {
                envolvidosCard = payload.alunos.map(function (a) {
                    if (!a || !a.nome) return '';
                    var t = String(a.nome);
                    if (a.turma_nome) t += ' (' + a.turma_nome + ')';
                    if (a.ra) t += ' · RA ' + a.ra;
                    return t;
                }).filter(Boolean);
            }

            var box = document.createElement('div');
            box.className = 'rounded-xl border border-indigo-200 bg-white shadow-sm overflow-hidden';

            var head = document.createElement('div');
            head.className = 'px-4 py-3 bg-indigo-50 border-b border-indigo-100';
            head.innerHTML = '<p class="text-xs font-semibold uppercase tracking-wide text-indigo-600">'
                + esc(d.tipo_label || 'Proposta pendente')
                + '</p><p class="text-base font-semibold text-indigo-950 mt-0.5">'
                + esc(tituloCard) + '</p>';
            box.appendChild(head);

            var body = document.createElement('div');
            body.className = 'px-4 py-3 space-y-3 text-sm text-slate-800';

            var blocoDesc = document.createElement('div');
            blocoDesc.innerHTML = '<p class="text-xs font-semibold uppercase text-slate-500 mb-1">Descrição detalhada</p>'
                + '<p class="whitespace-pre-wrap text-slate-800">'
                + esc(descricaoCard || '—') + '</p>';
            body.appendChild(blocoDesc);

            var blocoDh = document.createElement('div');
            blocoDh.innerHTML = '<p class="text-xs font-semibold uppercase text-slate-500 mb-1">Dia e horário</p>'
                + '<p class="font-medium text-slate-900">' + esc(dataHoraCard) + '</p>';
            body.appendChild(blocoDh);

            var blocoEnv = document.createElement('div');
            var listaEnv = envolvidosCard.length
                ? '<ul class="list-disc pl-5 space-y-0.5">' + envolvidosCard.map(function (n) {
                    return '<li>' + esc(n) + '</li>';
                }).join('') + '</ul>'
                : '<p class="text-slate-500">—</p>';
            blocoEnv.innerHTML = '<p class="text-xs font-semibold uppercase text-slate-500 mb-1">Envolvidos</p>' + listaEnv;
            body.appendChild(blocoEnv);

            if (Array.isArray(d.extras) && d.extras.length) {
                var extrasWrap = document.createElement('div');
                extrasWrap.className = 'grid grid-cols-1 sm:grid-cols-2 gap-2 pt-1 border-t border-slate-100';
                d.extras.forEach(function (ex) {
                    if (!ex || !ex.label) return;
                    var item = document.createElement('div');
                    item.innerHTML = '<p class="text-[11px] uppercase text-slate-500">' + esc(ex.label)
                        + '</p><p class="font-medium text-slate-900">' + esc(ex.valor || '—') + '</p>';
                    extrasWrap.appendChild(item);
                });
                body.appendChild(extrasWrap);
            }

            var avisoR = document.createElement('p');
            avisoR.className = 'text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
            avisoR.textContent = painel.situacao || 'RASCUNHO — nada foi gravado ainda.';
            body.appendChild(avisoR);
            box.appendChild(body);

            var foot = document.createElement('div');
            foot.className = 'px-4 py-3 bg-slate-50 border-t border-slate-100 flex flex-wrap items-center gap-2';
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'pa-btn-confirmar';
            btn.textContent = 'Confirmar';
            var hint = document.createElement('span');
            hint.className = 'text-xs text-slate-500';
            hint.textContent = 'Ou digite “sim” no chat.';
            btn.addEventListener('click', function () {
                if (!window.confirm('Confirmar e gravar no sistema?\n\n'
                    + tituloCard + '\n'
                    + (descricaoCard ? descricaoCard + '\n' : '')
                    + 'Quando: ' + dataHoraCard + '\n'
                    + 'Envolvidos: ' + (envolvidosCard.join(', ') || '—'))) {
                    return;
                }
                btn.disabled = true;
                btn.textContent = 'Confirmando…';
                var bodyReq = new URLSearchParams();
                bodyReq.set('_token', csrf);
                bodyReq.set('proposta_id', String(painel.proposta_id || 0));
                bodyReq.set('confirmar', '1');
                fetch(urls.confirmar, {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    body: bodyReq,
                    credentials: 'same-origin'
                }).then(function (r) { return r.json().then(function (j) { return { ok: r.ok, j: j }; }); })
                  .then(function (res) {
                      var msg = document.createElement('p');
                      msg.className = 'text-sm w-full ' + (res.ok && res.j.success ? 'text-emerald-700' : 'text-rose-700');
                      msg.textContent = (res.j && (res.j.mensagem || res.j.error)) || (res.ok ? 'Aplicado.' : 'Falha ao confirmar.');
                      foot.appendChild(msg);
                      if (res.ok && res.j.success) {
                          btn.textContent = 'Confirmada';
                          hint.textContent = 'Gravado no sistema.';
                      } else {
                          btn.disabled = false;
                          btn.textContent = 'Confirmar';
                      }
                  }).catch(function () {
                      btn.disabled = false;
                      btn.textContent = 'Confirmar';
                      var err = document.createElement('p');
                      err.className = 'text-sm w-full text-rose-700';
                      err.textContent = 'Erro de rede ao confirmar.';
                      foot.appendChild(err);
                  });
            });
            foot.appendChild(btn);
            foot.appendChild(hint);
            box.appendChild(foot);
            container.appendChild(box);
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
            var rankingCtx = !!(painel.aviso && /ranking/i.test(String(painel.aviso)))
                || !!(painel.professor && painel.professor.id);
            (painel.candidatos_provas || []).forEach(function (p) {
                var askP = rankingCtx
                    ? ('Ranking de erros da prova_id ' + p.prova_id
                        + (painel.professor && painel.professor.id ? (' professor_id=' + painel.professor.id) : ''))
                    : ('Detalhe da prova_id ' + p.prova_id + ' só questões erradas');
                htmlP += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(p.titulo || '—')
                    + '</td><td class="px-3 py-2">' + esc(p.materia || '—')
                    + '</td><td class="px-3 py-2 text-right text-rose-700">' + esc(p.erros != null ? p.erros : '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-btn-confirmar" data-ask="'
                    + esc(askP) + '">Confirmar</button></td></tr>';
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
                var res = !e.respondeu ? 'Não respondeu' : (e.aguardando_correcao ? 'Aguardando correção' : (e.acertou ? 'Acerto' : 'Erro'));
                var resClass = !e.respondeu ? 'text-gray-500' : (e.aguardando_correcao ? 'text-amber-700 font-semibold' : (e.acertou ? 'text-emerald-700 font-semibold' : 'text-rose-700 font-semibold'));
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
                var askPr = 'Professor ' + (p.nome || '')
                    + (p.id ? ' professor_id=' + p.id : '');
                htmlPr += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(p.nome)
                    + '</td><td class="px-3 py-2">' + esc(p.email || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-btn-confirmar" data-ask="' + esc(askPr) + '">Confirmar</button></td></tr>';
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
                var ask = 'Turma ' + (t.nome || '')
                    + (t.id ? ' turma_id=' + t.id : '');
                ht += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(t.nome || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-btn-confirmar" data-ask="' + esc(ask) + '">Confirmar</button></td></tr>';
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
                var ask = 'Bloco ' + (b.titulo || '')
                    + (bid ? ' bloco_id=' + bid : '');
                hb += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(b.titulo || '—')
                    + '</td><td class="px-3 py-2">' + esc(b.data_prova || '—')
                    + '</td><td class="px-3 py-2 text-right"><button type="button" class="pa-btn-confirmar" data-ask="' + esc(ask) + '">Confirmar</button></td></tr>';
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

        if (painel.tipo === 'saude_academica') {
            var kA = painel.kpis || {};
            var cardsSA = document.createElement('div');
            cardsSA.className = 'grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2';
            function cardSA(label, val, cls) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (cls || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            cardsSA.innerHTML = cardSA('Total alertas', painel.total_alertas || kA.total || 0, 'text-rose-700')
                + cardSA('Sem matrícula', kA.sem_matricula_ano || 0)
                + cardSA('Divergente', kA.matricula_divergente || 0)
                + cardSA('Sem chamada', kA.sem_chamada || 0)
                + cardSA('Pendente sem turma', kA.pending_sem_turma || 0)
                + cardSA('Curso extra', kA.extra_apenas_matricula || 0);
            container.appendChild(cardsSA);
            if (painel.aviso) {
                var avSa = document.createElement('p');
                avSa.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avSa.textContent = painel.aviso;
                container.appendChild(avSa);
            }
            var alertasSA = painel.alertas || [];
            var boxSA = document.createElement('div');
            boxSA.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hsa = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Alertas cadastrais</div>'
                + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                + '<th class="px-3 py-2">Aluno</th><th class="px-3 py-2">Turma</th><th class="px-3 py-2">Alerta</th><th class="px-3 py-2">Descrição</th></tr></thead><tbody>';
            if (!alertasSA.length) {
                hsa += '<tr><td colspan="4" class="px-3 py-4 text-center text-gray-500">Nenhum alerta encontrado.</td></tr>';
            }
            alertasSA.forEach(function (a) {
                hsa += '<tr class="border-t align-top"><td class="px-3 py-2 font-medium">' + esc(a.nome || '—')
                    + (a.ra ? '<div class="text-xs text-gray-500">RA ' + esc(a.ra) + '</div>' : '')
                    + '</td><td class="px-3 py-2">' + esc(a.turma_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.tipo_rotulo || a.tipo || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.descricao || '—') + '</td></tr>';
            });
            hsa += '</tbody></table>';
            boxSA.innerHTML = hsa;
            container.appendChild(boxSA);
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'perfil_aluno_360') {
            var ind360 = painel.indicadores || {};
            var atv360 = painel.atividade || {};
            var pen360 = painel.pendencias || {};
            var cards360 = document.createElement('div');
            cards360.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function card360(label, val, cls) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (cls || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            cards360.innerHTML = card360('Provas %', ind360.provas_pct != null ? ind360.provas_pct + '%' : '—')
                + card360('Frequência', ind360.frequencia_pct != null ? ind360.frequencia_pct + '%' : '—')
                + card360('Jornadas %', ind360.jornadas_pct != null ? ind360.jornadas_pct + '%' : '—')
                + card360('Ocorrências abertas', pen360.ocorrencias_abertas != null ? pen360.ocorrencias_abertas : 0, 'text-rose-700');
            container.appendChild(cards360);

            var meta360 = document.createElement('div');
            meta360.className = 'grid grid-cols-1 sm:grid-cols-2 gap-2';
            meta360.innerHTML = '<div class="rounded-lg border border-gray-200 bg-white px-3 py-2"><div class="text-xs uppercase text-slate-500">RA</div><div class="font-medium text-gray-900">' + esc((painel.aluno && painel.aluno.ra) || '—') + '</div></div>'
                + '<div class="rounded-lg border border-gray-200 bg-white px-3 py-2"><div class="text-xs uppercase text-slate-500">Atividade</div><div class="font-medium text-gray-900">'
                + esc((atv360.total_provas || 0) + ' provas · ' + (atv360.jornadas_concluidas || 0) + '/' + (atv360.total_jornadas || 0) + ' jornadas') + '</div></div>';
            container.appendChild(meta360);

            var destaques360 = painel.destaques || [];
            if (destaques360.length) {
                var box360 = document.createElement('div');
                box360.className = 'rounded-lg border border-gray-200 bg-white px-4 py-3';
                var html360 = '<div class="text-xs font-semibold uppercase text-slate-600 mb-2">Destaques</div><ul class="list-disc pl-5 space-y-1 text-sm text-gray-700">';
                destaques360.forEach(function (d) {
                    html360 += '<li>' + esc(d) + '</li>';
                });
                html360 += '</ul>';
                box360.innerHTML = html360;
                container.appendChild(box360);
            }
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'diario_pendencias') {
            var resD = painel.resumo || {};
            var cardsD = document.createElement('div');
            cardsD.className = 'grid grid-cols-2 sm:grid-cols-5 gap-2';
            function cardD(label, val, cls) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (cls || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            cardsD.innerHTML = cardD('Em atraso', resD.atraso || 0, 'text-rose-700')
                + cardD('Atenção', resD.atencao || 0, 'text-amber-700')
                + cardD('Em dia', resD.em_dia || 0, 'text-emerald-700')
                + cardD('Pendências', resD.pendentes_vencidas || 0)
                + cardD('Cobertura', resD.cobertura_media != null ? resD.cobertura_media + '%' : '—');
            container.appendChild(cardsD);
            if (painel.aviso) {
                var avD = document.createElement('p');
                avD.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avD.textContent = painel.aviso;
                container.appendChild(avD);
            }
            var diarios = painel.diarios || [];
            var boxD = document.createElement('div');
            boxD.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hd = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Pendências do diário</div>'
                + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                + '<th class="px-3 py-2">Professor</th><th class="px-3 py-2">Turma</th><th class="px-3 py-2">Matéria</th><th class="px-3 py-2">Pendentes</th><th class="px-3 py-2">Cobertura</th><th class="px-3 py-2">Situação</th></tr></thead><tbody>';
            if (!diarios.length) {
                hd += '<tr><td colspan="6" class="px-3 py-4 text-center text-gray-500">Nenhuma pendência encontrada.</td></tr>';
            }
            diarios.forEach(function (d) {
                hd += '<tr class="border-t"><td class="px-3 py-2 font-medium">' + esc(d.professor_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(d.turma_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(d.materia_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(d.pendentes_vencidas != null ? d.pendentes_vencidas : 0)
                    + '</td><td class="px-3 py-2">' + esc(d.percentual != null ? d.percentual + '%' : '—')
                    + '</td><td class="px-3 py-2">' + esc(d.situacao_label || d.situacao || '—') + '</td></tr>';
            });
            hd += '</tbody></table>';
            boxD.innerHTML = hd;
            container.appendChild(boxD);
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'grade_horaria_consulta') {
            if (painel.aviso) {
                var avG = document.createElement('p');
                avG.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avG.textContent = painel.aviso;
                container.appendChild(avG);
            }
            var boxG = document.createElement('div');
            boxG.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var tituloG = 'Agenda de ' + esc(painel.dia_semana_label || 'hoje')
                + (painel.data ? ' · ' + esc(painel.data) : '');
            var hg = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">' + tituloG + '</div>'
                + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                + '<th class="px-3 py-2">Horário</th><th class="px-3 py-2">Turma</th><th class="px-3 py-2">Professor</th><th class="px-3 py-2">Matéria</th><th class="px-3 py-2">Sala</th></tr></thead><tbody>';
            var aulasG = painel.aulas || [];
            if (!aulasG.length) {
                hg += '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Nenhuma aula encontrada.</td></tr>';
            }
            aulasG.forEach(function (a) {
                hg += '<tr class="border-t"><td class="px-3 py-2 whitespace-nowrap">' + esc((a.horario_de || '—') + ' - ' + (a.horario_ate || '—'))
                    + '</td><td class="px-3 py-2">' + esc(a.turma_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.professor_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.materia_nome || '—')
                    + '</td><td class="px-3 py-2">' + esc(a.sala_nome || '—') + '</td></tr>';
            });
            hg += '</tbody></table>';
            boxG.innerHTML = hg;
            container.appendChild(boxG);
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

        function fmtNota(v) {
            if (v == null || v === '') return '—';
            if (typeof v === 'object') v = v.valor != null ? v.valor : null;
            if (v == null || v === '') return '—';
            if (typeof v === 'number') {
                return (Math.round(v * 10) / 10).toLocaleString('pt-BR', { minimumFractionDigits: 0, maximumFractionDigits: 1 });
            }
            return String(v);
        }

        function renderQuadroNotas(containerEl, quadro, tituloQuadro) {
            if (!quadro || !quadro.colunas || !quadro.colunas.length) {
                var vazioQ = document.createElement('p');
                vazioQ.className = 'text-sm text-gray-600';
                vazioQ.textContent = 'Sem colunas de notas para exibir.';
                containerEl.appendChild(vazioQ);
                return;
            }
            var box = document.createElement('div');
            box.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var cols = quadro.colunas || [];
            var grupos = quadro.grupos || [];
            var html = '';
            if (tituloQuadro) {
                html += '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">' + esc(tituloQuadro) + '</div>';
            }
            html += '<table class="min-w-full text-xs sm:text-sm"><thead>';
            if (grupos.length) {
                html += '<tr class="bg-slate-600 text-white text-[10px] uppercase">';
                html += '<th class="px-2 py-1.5 text-left sticky left-0 bg-slate-600 z-10">Matérias</th>';
                grupos.forEach(function (g) {
                    html += '<th class="px-1 py-1.5 text-center" colspan="' + (g.colspan || 1) + '">' + esc(g.titulo || '') + '</th>';
                });
                html += '</tr>';
            }
            html += '<tr class="bg-slate-100 text-[10px] uppercase text-slate-600"><th class="px-2 py-1.5 text-left sticky left-0 bg-slate-100 z-10">Componente</th>';
            cols.forEach(function (c) {
                html += '<th class="px-1.5 py-1.5 text-center whitespace-nowrap">' + esc(c.nome || c.codigo || '—') + '</th>';
            });
            html += '</tr></thead><tbody>';
            (quadro.linhas || []).forEach(function (linha) {
                html += '<tr class="border-t"><td class="px-2 py-1.5 font-medium text-gray-900 whitespace-nowrap sticky left-0 bg-white z-10">'
                    + esc(linha.materia || '—') + '</td>';
                var notas = linha.notas || {};
                cols.forEach(function (c) {
                    var key = c.codigo || c.chave || c.key;
                    html += '<td class="px-1.5 py-1.5 text-center tabular-nums">' + esc(fmtNota(key != null ? notas[key] : null)) + '</td>';
                });
                html += '</tr>';
            });
            html += '</tbody></table>';
            box.innerHTML = html;
            containerEl.appendChild(box);
        }

        if (painel.tipo === 'boletim_aluno') {
            if (painel.aviso) {
                var avB = document.createElement('p');
                avB.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avB.textContent = painel.aviso;
                container.appendChild(avB);
            }
            var temQuadro = painel.formato === 'quadro_anual'
                && painel.quadro
                && (painel.quadro.colunas || []).length
                && (painel.quadro.linhas || []).length;
            var evsBol = painel.eventos || [];
            var temEventos = evsBol.some(function (ev) {
                return (ev.colunas || []).length && (ev.linhas || []).length;
            });
            if (painel.pdf_url || painel.pdf_oficial_url) {
                var pdfBar = document.createElement('div');
                pdfBar.className = 'flex flex-wrap gap-2 mb-2';
                if (painel.pdf_url) {
                    var aPdf = document.createElement('a');
                    aPdf.href = painel.pdf_url;
                    aPdf.target = '_blank';
                    aPdf.rel = 'noopener';
                    aPdf.className = 'inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700';
                    aPdf.innerHTML = '<i class="fa-solid fa-file-pdf"></i> Baixar boletim (PDF)';
                    pdfBar.appendChild(aPdf);
                }
                if (painel.pdf_oficial_url) {
                    var aOf = document.createElement('a');
                    aOf.href = painel.pdf_oficial_url;
                    aOf.target = '_blank';
                    aOf.rel = 'noopener';
                    aOf.className = 'inline-flex items-center gap-1.5 px-3 py-2 rounded-lg border border-indigo-200 text-indigo-800 text-sm font-medium hover:bg-indigo-50';
                    aOf.innerHTML = '<i class="fa-solid fa-stamp"></i> PDF oficial (fechamento)';
                    pdfBar.appendChild(aOf);
                }
                container.appendChild(pdfBar);
            }
            if (temQuadro) {
                var tituloQ = 'Boletim'
                    + (painel.ano_letivo ? ' · ' + painel.ano_letivo : '')
                    + (painel.regra && painel.regra.nome ? ' · ' + painel.regra.nome : '');
                renderQuadroNotas(container, painel.quadro, tituloQ);
            } else if (temEventos) {
                evsBol.forEach(function (ev) {
                    if (!(ev.colunas || []).length || !(ev.linhas || []).length) return;
                    var tituloEv = (ev.regra_nome || 'Boletim')
                        + (ev.bimestre ? ' · ' + ev.bimestre + 'º bim' : '')
                        + (ev.ano_letivo ? ' · ' + ev.ano_letivo : '');
                    renderQuadroNotas(container, { colunas: ev.colunas || [], linhas: (ev.linhas || []).map(function (l) {
                        return { materia: l.materia, notas: l.notas || {} };
                    }) }, tituloEv);
                });
            } else {
                var vazioB = document.createElement('p');
                vazioB.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                vazioB.textContent = 'Não há quadro de notas para exibir neste boletim.';
                container.appendChild(vazioB);
            }
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'candidatos_eventos') {
            if (painel.aviso) {
                var avE = document.createElement('p');
                avE.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avE.textContent = painel.aviso;
                container.appendChild(avE);
            }
            var listE = document.createElement('div');
            listE.className = 'flex flex-wrap gap-2';
            (painel.candidatos || []).forEach(function (ev) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'pa-btn-confirmar';
                b.textContent = 'Confirmar — ' + (ev.nome || ('Evento #' + ev.id))
                    + (ev.bimestre ? ' · bim ' + ev.bimestre : '');
                b.addEventListener('click', function () {
                    if (enviando) return;
                    input.value = 'Usar evento_id ' + ev.id + (ev.nome ? (' (' + ev.nome + ')') : '');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
                listE.appendChild(b);
            });
            container.appendChild(listE);
        }

        if (painel.tipo === 'candidatos_eventos_sigla' || painel.tipo === 'eventos_avaliativos_disponiveis') {
            if (painel.aviso) {
                var avEs = document.createElement('p');
                avEs.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avEs.textContent = painel.aviso;
                container.appendChild(avEs);
            }
            var listEs = document.createElement('div');
            listEs.className = 'flex flex-wrap gap-2';
            (painel.candidatos || []).forEach(function (ev) {
                var eid = ev.evento_id || ev.id;
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'pa-btn-confirmar';
                b.textContent = 'Confirmar — ' + (ev.sigla ? (ev.sigla + ' · ') : '')
                    + (ev.nome || ('Evento #' + eid))
                    + (ev.ano_letivo ? ' · ' + ev.ano_letivo : '')
                    + (ev.bimestre ? ' · bim ' + ev.bimestre : '');
                b.addEventListener('click', function () {
                    if (enviando) return;
                    input.value = 'Usar evento avaliativo id ' + eid
                        + (ev.sigla ? (' sigla ' + ev.sigla) : '')
                        + (ev.nome ? (' (' + ev.nome + ')') : '');
                    form.dispatchEvent(new Event('submit', { cancelable: true }));
                });
                listEs.appendChild(b);
            });
            container.appendChild(listEs);
        }

        if (painel.tipo === 'nota_evento_sigla') {
            if (painel.aviso) {
                var avNs = document.createElement('p');
                avNs.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avNs.textContent = painel.aviso;
                container.appendChild(avNs);
            }
            if (painel.formato === 'nota_unica') {
                var boxN = document.createElement('div');
                boxN.className = 'rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-950';
                var siglaN = (painel.evento && painel.evento.sigla) ? String(painel.evento.sigla) : '';
                boxN.innerHTML = '<span class="font-semibold">' + esc(siglaN || (painel.evento && painel.evento.nome) || 'Evento') + '</span>'
                    + (painel.materia ? ' · ' + esc(painel.materia) : '')
                    + ' → <strong class="text-lg">' + esc(painel.nota != null ? String(painel.nota) : '—') + '</strong>';
                container.appendChild(boxN);
            } else if ((painel.notas || []).length) {
                var boxL = document.createElement('div');
                boxL.className = 'overflow-x-auto border border-gray-200 rounded-lg';
                var hl = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                    + '<th class="px-3 py-2 text-left">Matéria</th>'
                    + '<th class="px-3 py-2 text-right">Nota</th>'
                    + '<th class="px-3 py-2 text-left">Subevento</th></tr></thead><tbody>';
                (painel.notas || []).forEach(function (n) {
                    hl += '<tr class="border-t"><td class="px-3 py-2">' + esc(n.materia || '—')
                        + '</td><td class="px-3 py-2 text-right font-semibold">' + esc(n.nota != null ? String(n.nota) : '—')
                        + '</td><td class="px-3 py-2 text-gray-600">' + esc(n.bloco_titulo || '—') + '</td></tr>';
                });
                hl += '</tbody></table>';
                boxL.innerHTML = hl;
                container.appendChild(boxL);
            }
        }

        if (painel.tipo === 'notas_evento_aluno') {
            if (painel.aviso) {
                var avNe = document.createElement('p');
                avNe.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avNe.textContent = painel.aviso;
                container.appendChild(avNe);
            }
            var tituloEvn = (painel.evento && painel.evento.nome ? painel.evento.nome : 'Evento')
                + (painel.ano_letivo ? ' · ' + painel.ano_letivo : '')
                + (painel.coluna ? ' · coluna ' + painel.coluna : '');
            renderQuadroNotas(container, painel.quadro || {}, tituloEvn);
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'ocorrencias_aluno') {
            var listaOco = painel.ocorrencias || [];
            if (painel.aviso) {
                var avO = document.createElement('p');
                avO.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avO.textContent = painel.aviso;
                container.appendChild(avO);
            }
            if (!listaOco.length && !painel.aviso) {
                var vazioO = document.createElement('p');
                vazioO.className = 'text-sm text-gray-600';
                vazioO.textContent = 'Nenhuma ocorrência encontrada.';
                container.appendChild(vazioO);
            }
            if (listaOco.length) {
                var boxO = document.createElement('div');
                boxO.className = 'overflow-x-auto border border-gray-200 rounded-lg';
                var ho = '<table class="min-w-full text-sm"><thead class="bg-slate-50 text-xs uppercase text-slate-600"><tr>'
                    + '<th class="px-3 py-2 text-left">Data</th>'
                    + '<th class="px-3 py-2 text-left">Título</th>'
                    + '<th class="px-3 py-2 text-left">Gravidade</th>'
                    + '<th class="px-3 py-2 text-left">Status</th>'
                    + '<th class="px-3 py-2 text-left">Categoria</th></tr></thead><tbody>';
                listaOco.forEach(function (o) {
                    ho += '<tr class="border-t align-top">'
                        + '<td class="px-3 py-2 whitespace-nowrap">' + esc(o.data || '—') + '</td>'
                        + '<td class="px-3 py-2"><div class="font-medium text-gray-900">' + esc(o.titulo || '—') + '</div>'
                        + (o.detalhe ? '<div class="text-xs text-gray-500 mt-0.5">' + esc(o.detalhe) + '</div>' : '')
                        + '</td>'
                        + '<td class="px-3 py-2">' + esc(o.gravidade_label || o.gravidade || '—') + '</td>'
                        + '<td class="px-3 py-2">' + esc(o.status_label || o.status || '—') + '</td>'
                        + '<td class="px-3 py-2">' + esc(o.categoria || '—') + '</td></tr>';
                });
                ho += '</tbody></table>';
                if (painel.total && painel.total > listaOco.length) {
                    ho += '<div class="px-3 py-2 text-xs text-slate-500 border-t bg-slate-50">Mostrando '
                        + listaOco.length + ' de ' + painel.total + '</div>';
                }
                boxO.innerHTML = ho;
                container.appendChild(boxO);
            }
            renderOpcoes(container, painel.opcoes);
        }

        if (painel.tipo === 'ocorrencias_turma') {
            var gravT = painel.por_gravidade || {};
            var cardsOT = document.createElement('div');
            cardsOT.className = 'grid grid-cols-2 sm:grid-cols-4 gap-2';
            function cardOT(label, val, cls) {
                return '<div class="rounded-lg border border-gray-200 bg-slate-50 px-3 py-2">'
                    + '<div class="text-[11px] uppercase tracking-wide text-slate-500">' + esc(label) + '</div>'
                    + '<div class="text-lg font-semibold ' + (cls || 'text-gray-900') + '">' + esc(val) + '</div></div>';
            }
            cardsOT.innerHTML = cardOT('Total', painel.total || 0)
                + cardOT('Leves', gravT.leve || 0)
                + cardOT('Moderadas', gravT.moderado || 0, 'text-amber-700')
                + cardOT('Graves', gravT.grave || 0, 'text-rose-700');
            container.appendChild(cardsOT);
            if (painel.aviso) {
                var avOt = document.createElement('p');
                avOt.className = 'text-sm text-amber-800 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2';
                avOt.textContent = painel.aviso;
                container.appendChild(avOt);
            }
            var boxOT = document.createElement('div');
            boxOT.className = 'overflow-x-auto border border-gray-200 rounded-lg';
            var hot = '<div class="px-3 py-2 text-xs font-semibold uppercase text-slate-600 bg-slate-50 border-b">Ocorrências da turma</div>'
                + '<table class="min-w-full text-sm"><thead><tr class="text-xs text-slate-500 text-left">'
                + '<th class="px-3 py-2">Data</th><th class="px-3 py-2">Aluno(s)</th><th class="px-3 py-2">Título</th><th class="px-3 py-2">Gravidade</th><th class="px-3 py-2">Status</th></tr></thead><tbody>';
            (painel.ocorrencias || []).forEach(function (o) {
                hot += '<tr class="border-t align-top"><td class="px-3 py-2 whitespace-nowrap">' + esc(o.data || '—')
                    + '</td><td class="px-3 py-2">' + esc(o.alunos_nomes || '—')
                    + '</td><td class="px-3 py-2"><div class="font-medium text-gray-900">' + esc(o.titulo || '—') + '</div>'
                    + (o.detalhe ? '<div class="text-xs text-gray-500 mt-0.5">' + esc(o.detalhe) + '</div>' : '')
                    + '</td><td class="px-3 py-2">' + esc(o.gravidade_label || o.gravidade || '—')
                    + '</td><td class="px-3 py-2">' + esc(o.status_label || o.status || '—') + '</td></tr>';
            });
            if (!(painel.ocorrencias || []).length) {
                hot += '<tr><td colspan="5" class="px-3 py-4 text-center text-gray-500">Nenhuma ocorrência encontrada.</td></tr>';
            }
            hot += '</tbody></table>';
            boxOT.innerHTML = hot;
            container.appendChild(boxOT);
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
        loadingEl.className = 'flex items-start gap-2.5 mr-4';
        loadingEl.innerHTML = avatarHtml()
            + '<div class="text-xs text-indigo-700 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 inline-flex items-center gap-2">'
            + '<span class="assistente-dot"></span><span class="assistente-dot"></span><span class="assistente-dot"></span><span></span></div>';
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
        var welcome = msgs.querySelector('#assistente-welcome');
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
                if (streamBubble && streamBubble.parentNode) streamBubble.parentNode.remove();
                streamBubble = null;
                var shell = appendAssistantShell();
                if (painelFinal) renderPainel(shell, painelFinal, mensagemFinal);
                else shell.innerHTML = simpleMarkdown(mensagemFinal);
            } else {
                // Stream cortou sem evento done (proxy/timeout) — não deixa só o “Consultando…”.
                var vazio = appendAssistantShell();
                vazio.textContent = 'A consulta não retornou resposta. Tente de novo em alguns segundos.';
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
