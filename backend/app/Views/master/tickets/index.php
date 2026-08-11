<?php
$tickets = $tickets ?? [];
$escolas = $escolas ?? [];
$resumo = $resumo ?? ['total' => 0, 'aberto' => 0, 'em_andamento' => 0, 'respondido' => 0, 'fechado' => 0];
$filtros = $filtros ?? ['escola_id' => 0, 'status' => '', 'busca' => ''];
$paginacao = $paginacao ?? ['page' => 1, 'total_pages' => 1, 'total' => 0, 'per_page' => 20];
$csrf_token = $csrf_token ?? '';
$statusMap = [
    'aberto' => ['label' => 'Aberto', 'class' => 'bg-amber-100 text-amber-800'],
    'em_andamento' => ['label' => 'Em andamento', 'class' => 'bg-blue-100 text-blue-800'],
    'respondido' => ['label' => 'Respondido', 'class' => 'bg-green-100 text-green-800'],
    'fechado' => ['label' => 'Fechado', 'class' => 'bg-slate-100 text-slate-600'],
];

$queryBase = array_filter([
    'escola_id' => !empty($filtros['escola_id']) ? (int) $filtros['escola_id'] : null,
    'status' => $filtros['status'] !== '' ? $filtros['status'] : null,
    'busca' => $filtros['busca'] !== '' ? $filtros['busca'] : null,
], static fn($v) => $v !== null && $v !== '');

$filtrosAtivos = 0;
if (!empty($filtros['escola_id'])) {
    $filtrosAtivos++;
}
if (($filtros['status'] ?? '') !== '') {
    $filtrosAtivos++;
}
if (($filtros['busca'] ?? '') !== '') {
    $filtrosAtivos++;
}
?>

<?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
<?php if (!empty($flash_msg)): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
    <?= htmlspecialchars($flash_msg) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1">Tickets de Suporte</h2>
            <p class="text-slate-500 text-sm">Chamados dos alunos em todas as escolas.</p>
        </div>
        <button type="button" onclick="openFilterDrawer()"
                class="relative inline-flex items-center px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors flex-shrink-0">
            <i class="fa-solid fa-filter mr-2 text-slate-500"></i>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
            <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-5 gap-4 mb-6">
    <?php
    $cards = [
        ['label' => 'Total', 'value' => (int) $resumo['total'], 'tone' => 'text-slate-900'],
        ['label' => 'Abertos', 'value' => (int) $resumo['aberto'], 'tone' => 'text-amber-600'],
        ['label' => 'Em andamento', 'value' => (int) $resumo['em_andamento'], 'tone' => 'text-blue-600'],
        ['label' => 'Respondidos', 'value' => (int) $resumo['respondido'], 'tone' => 'text-green-600'],
        ['label' => 'Fechados', 'value' => (int) $resumo['fechado'], 'tone' => 'text-slate-500'],
    ];
    foreach ($cards as $c):
    ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs text-slate-500"><?= htmlspecialchars($c['label']) ?></p>
        <p class="text-2xl font-bold mt-1 <?= $c['tone'] ?>"><?= $c['value'] ?></p>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-slate-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Assunto</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                <?php if (empty($tickets)): ?>
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-sm text-slate-500">
                        Nenhum ticket encontrado.
                        <?php if ($filtrosAtivos > 0): ?>
                        <button type="button" onclick="openFilterDrawer()" class="block mx-auto mt-2 text-blue-600 hover:underline">Ajustar filtros</button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($tickets as $t):
                    $st = $statusMap[$t['status'] ?? ''] ?? $statusMap['aberto'];
                    $ticketId = (int) ($t['id'] ?? 0);
                    $escolaId = (int) ($t['escola_id'] ?? 0);
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-medium text-slate-900 max-w-xs truncate"><?= htmlspecialchars($t['assunto'] ?? '') ?></td>
                    <td class="px-4 py-3 text-sm text-slate-700">
                        <span class="block font-medium text-slate-900 truncate"><?= htmlspecialchars($t['aluno_nome'] ?? '') ?></span>
                        <span class="block text-xs text-slate-500 mt-0.5 truncate"><?= htmlspecialchars($t['escola_nome'] ?? '') ?></span>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full <?= $st['class'] ?>"><?= $st['label'] ?></span>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-500 whitespace-nowrap">
                        <?= !empty($t['criado_em']) ? date('d/m/Y H:i', strtotime($t['criado_em'])) : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php ob_start(); ?>
                        <button type="button"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                                data-open-ticket
                                data-escola-id="<?= $escolaId ?>"
                                data-ticket-id="<?= $ticketId ?>">
                            <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Abrir
                        </button>
                        <?php if (($t['status'] ?? '') !== 'em_andamento' && ($t['status'] ?? '') !== 'fechado'): ?>
                        <button type="button"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50"
                                data-mark-progress
                                data-escola-id="<?= $escolaId ?>"
                                data-ticket-id="<?= $ticketId ?>">
                            <i class="fa-solid fa-spinner text-blue-400 w-4 text-center"></i> Marcar em andamento
                        </button>
                        <?php endif; ?>
                        <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                        <?php $row_actions_dropdown_id = 'row-actions-ticket-' . $escolaId . '-' . $ticketId; ?>
                        <?php include __DIR__ . '/../../admin/_partials/row_actions_dropdown.php'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int) $paginacao['total_pages'] > 1): ?>
    <div class="px-4 py-3 border-t border-slate-200 flex items-center justify-between gap-3">
        <p class="text-xs text-slate-500">
            Página <?= (int) $paginacao['page'] ?> de <?= (int) $paginacao['total_pages'] ?>
            (<?= (int) $paginacao['total'] ?> tickets)
        </p>
        <div class="flex gap-2">
            <?php if ((int) $paginacao['page'] > 1):
                $prev = http_build_query(array_merge($queryBase, ['page' => (int) $paginacao['page'] - 1]));
            ?>
            <a href="?<?= htmlspecialchars($prev) ?>" class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Anterior</a>
            <?php endif; ?>
            <?php if ((int) $paginacao['page'] < (int) $paginacao['total_pages']):
                $next = http_build_query(array_merge($queryBase, ['page' => (int) $paginacao['page'] + 1]));
            ?>
            <a href="?<?= htmlspecialchars($next) ?>" class="px-3 py-1.5 text-sm border border-slate-300 rounded-lg hover:bg-slate-50">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Filtro lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Filtrar tickets</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/master/tickets" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_escola" class="block text-sm font-medium text-slate-700 mb-1.5">Escola</label>
                <select id="filtro_escola" name="escola_id"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($escolas as $e): ?>
                    <option value="<?= (int) $e['id'] ?>" <?= (int) $filtros['escola_id'] === (int) $e['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($e['nome'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status"
                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($statusMap as $k => $st): ?>
                    <option value="<?= $k ?>" <?= $filtros['status'] === $k ? 'selected' : '' ?>><?= $st['label'] ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_busca" class="block text-sm font-medium text-slate-700 mb-1.5">Busca</label>
                <input type="text" id="filtro_busca" name="busca" value="<?= htmlspecialchars($filtros['busca']) ?>"
                       placeholder="Assunto ou aluno"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex gap-3 bg-slate-50">
            <a href="<?= URL ?>/master/tickets"
               class="flex-1 px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors text-center">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<!-- Offcanvas detalhe -->
<div id="ticketDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="closeTicketDrawer()"></div>
<aside id="ticketDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-2xl bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="px-5 py-4 border-b border-slate-200 flex items-start justify-between gap-3 shrink-0">
        <div class="min-w-0">
            <p class="text-xs text-slate-500" id="ticketDrawerEscola">—</p>
            <h2 class="text-lg font-bold text-slate-900 truncate" id="ticketDrawerTitle">Ticket</h2>
            <div class="flex flex-wrap items-center gap-2 mt-2 text-xs text-slate-500" id="ticketDrawerMeta"></div>
        </div>
        <button type="button" onclick="closeTicketDrawer()" class="p-2 text-slate-400 hover:text-slate-700 hover:bg-slate-100 rounded-lg" aria-label="Fechar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto p-5 space-y-4" id="ticketDrawerBody">
        <p class="text-sm text-slate-500 text-center py-10">Carregando…</p>
    </div>
    <div class="border-t border-slate-200 p-4 shrink-0 bg-white" id="ticketDrawerFooter"></div>
</aside>

<div id="ticketImageLightbox" class="hidden fixed inset-0 bg-black/80 z-[70] items-center justify-center p-6" onclick="closeTicketImageLightbox()">
    <button type="button" onclick="closeTicketImageLightbox()" class="absolute top-4 right-4 text-white/80 hover:text-white p-2" aria-label="Fechar">
        <i class="fa-solid fa-xmark text-2xl"></i>
    </button>
    <img id="ticketImageLightboxImg" src="" alt="" class="max-w-full max-h-full rounded-lg shadow-2xl" onclick="event.stopPropagation()">
</div>

<style>
.ticket-msg-content img { max-width: 100%; border-radius: 6px; margin: 6px 0; cursor: zoom-in; }
.ticket-msg-content p { margin: 0; }
.ticket-msg-content ul, .ticket-msg-content ol { padding-left: 1.5rem; margin: 4px 0; }
.ticket-msg-content a { text-decoration: underline; }
#ticketImageLightbox.flex { display: flex; }
</style>

<script>
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

(function() {
    var baseUrl = <?= json_encode(URL) ?>;
    var csrf = <?= json_encode($csrf_token) ?>;
    var statusMap = <?= json_encode($statusMap, JSON_UNESCAPED_UNICODE) ?>;
    var currentEscolaId = 0;
    var currentTicketId = 0;

    function openTicketDrawer() {
        closeFilterDrawer();
        document.getElementById('ticketDrawerBackdrop').classList.remove('hidden');
        var d = document.getElementById('ticketDrawer');
        d.classList.remove('translate-x-full');
        d.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }

    window.closeTicketDrawer = function() {
        document.getElementById('ticketDrawerBackdrop').classList.add('hidden');
        var d = document.getElementById('ticketDrawer');
        d.classList.add('translate-x-full');
        d.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        currentEscolaId = 0;
        currentTicketId = 0;
    };

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function badge(status) {
        var st = statusMap[status] || statusMap.aberto;
        return '<span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full ' + st.class + '">' + esc(st.label) + '</span>';
    }

    function renderTicket(data) {
        var t = data.ticket || {};
        var escola = data.escola || {};
        var msgs = data.mensagens || [];
        currentEscolaId = escola.id || 0;
        currentTicketId = t.id || 0;

        document.getElementById('ticketDrawerEscola').textContent = escola.nome || '—';
        document.getElementById('ticketDrawerTitle').textContent = t.assunto || ('Ticket #' + t.id);
        var meta = [];
        if (t.aluno_nome) meta.push('<span>' + esc(t.aluno_nome) + '</span>');
        if (t.aluno_email) meta.push('<span class="text-slate-400">' + esc(t.aluno_email) + '</span>');
        if (t.categoria) meta.push('<span class="bg-blue-50 text-blue-700 px-2 py-0.5 rounded-full">' + esc(t.categoria) + '</span>');
        if (t.modulo) meta.push('<span class="bg-slate-50 text-slate-700 px-2 py-0.5 rounded-full">' + esc(t.modulo) + '</span>');
        meta.push(badge(t.status));
        if (t.criado_em_fmt) meta.push('<span>Aberto em ' + esc(t.criado_em_fmt) + '</span>');
        document.getElementById('ticketDrawerMeta').innerHTML = meta.join('');

        var html = '';
        if (!msgs.length) {
            html = '<p class="text-sm text-slate-500 text-center py-8">Nenhuma mensagem neste ticket.</p>';
        } else {
            html = '<div class="space-y-3" id="ticket-msgs">';
            msgs.forEach(function(m) {
                var isAdmin = m.remetente_tipo === 'admin';
                html += '<div class="flex ' + (isAdmin ? 'justify-end' : 'justify-start') + '">';
                html += '<div class="max-w-[85%] rounded-2xl px-4 py-3 ' + (isAdmin ? 'bg-blue-600 text-white rounded-br-md' : 'bg-slate-100 text-slate-800 rounded-bl-md') + '">';
                html += '<p class="text-xs font-semibold mb-1 ' + (isAdmin ? 'text-blue-200' : 'text-slate-500') + '">' + (isAdmin ? 'Admin Master' : esc(t.aluno_nome || 'Aluno')) + '</p>';
                html += '<div class="text-sm break-words ticket-msg-content">' + (m.mensagem_html || '') + '</div>';
                html += '<p class="text-[10px] mt-1 text-right ' + (isAdmin ? 'text-blue-300' : 'text-slate-400') + '">' + esc(m.criado_em_fmt || '') + '</p>';
                html += '</div></div>';
            });
            html += '</div>';
        }
        document.getElementById('ticketDrawerBody').innerHTML = html;
        var box = document.getElementById('ticket-msgs');
        if (box) box.parentElement.scrollTop = box.parentElement.scrollHeight;

        var footer = document.getElementById('ticketDrawerFooter');
        if (t.status === 'fechado') {
            footer.innerHTML = '<p class="text-sm text-slate-500 text-center">Ticket fechado' + (t.fechado_em_fmt ? ' em ' + esc(t.fechado_em_fmt) : '') + '.</p>';
            return;
        }
        footer.innerHTML =
            '<form id="ticketReplyForm" class="space-y-3">' +
            '<textarea name="mensagem" rows="3" required placeholder="Digite sua resposta..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 resize-none"></textarea>' +
            '<div class="flex flex-wrap justify-between gap-2">' +
            '<div class="flex flex-wrap gap-2">' +
            '<button type="button" id="btnFecharTicket" class="px-4 py-2 bg-slate-600 text-white text-sm font-medium rounded-lg hover:bg-slate-700">Fechar ticket</button>' +
            (t.status !== 'em_andamento' ? '<button type="button" id="btnEmAndamento" class="px-4 py-2 bg-blue-100 text-blue-700 text-sm font-medium rounded-lg hover:bg-blue-200">Marcar em andamento</button>' : '') +
            '</div>' +
            '<button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Enviar resposta</button>' +
            '</div></form>';

        document.getElementById('ticketReplyForm').addEventListener('submit', function(ev) {
            ev.preventDefault();
            var msg = (this.mensagem.value || '').trim();
            if (!msg) return;
            var fd = new FormData();
            fd.append('_token', csrf);
            fd.append('escola_id', String(currentEscolaId));
            fd.append('ticket_id', String(currentTicketId));
            fd.append('mensagem', msg);
            fetch(baseUrl + '/master/tickets/responder', {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (!res.ok) { alert(res.error || 'Erro ao responder'); return; }
                loadTicket(currentEscolaId, currentTicketId);
            }).catch(function() { alert('Erro de rede ao responder'); });
        });

        var btnEmAndamento = document.getElementById('btnEmAndamento');
        if (btnEmAndamento) {
            btnEmAndamento.addEventListener('click', function() {
                var fd = new FormData();
                fd.append('_token', csrf);
                fd.append('escola_id', String(currentEscolaId));
                fd.append('ticket_id', String(currentTicketId));
                fetch(baseUrl + '/master/tickets/em-andamento', {
                    method: 'POST',
                    body: fd,
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(r) { return r.json(); }).then(function(res) {
                    if (!res.ok) { alert(res.error || 'Erro ao atualizar ticket'); return; }
                    loadTicket(currentEscolaId, currentTicketId);
                }).catch(function() { alert('Erro de rede ao atualizar ticket'); });
            });
        }

        document.getElementById('btnFecharTicket').addEventListener('click', function() {
            if (!confirm('Deseja fechar este ticket?')) return;
            var fd = new FormData();
            fd.append('_token', csrf);
            fd.append('escola_id', String(currentEscolaId));
            fd.append('ticket_id', String(currentTicketId));
            fetch(baseUrl + '/master/tickets/fechar', {
                method: 'POST',
                body: fd,
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(r) { return r.json(); }).then(function(res) {
                if (!res.ok) { alert(res.error || 'Erro ao fechar'); return; }
                closeTicketDrawer();
                window.location.reload();
            }).catch(function() { alert('Erro de rede ao fechar'); });
        });
    }

    function loadTicket(escolaId, ticketId) {
        openTicketDrawer();
        document.getElementById('ticketDrawerBody').innerHTML = '<p class="text-sm text-slate-500 text-center py-10">Carregando…</p>';
        document.getElementById('ticketDrawerFooter').innerHTML = '';
        fetch(baseUrl + '/master/tickets/dados?escola_id=' + encodeURIComponent(escolaId) + '&ticket_id=' + encodeURIComponent(ticketId), {
            headers: { 'Accept': 'application/json' }
        }).then(function(r) { return r.json(); }).then(function(data) {
            if (!data.ok) {
                document.getElementById('ticketDrawerBody').innerHTML = '<p class="text-sm text-red-600 text-center py-10">' + esc(data.error || 'Erro') + '</p>';
                return;
            }
            renderTicket(data);
        }).catch(function() {
            document.getElementById('ticketDrawerBody').innerHTML = '<p class="text-sm text-red-600 text-center py-10">Falha ao carregar o ticket.</p>';
        });
    }

    window.closeTicketImageLightbox = function() {
        var lb = document.getElementById('ticketImageLightbox');
        lb.classList.add('hidden');
        lb.classList.remove('flex');
        document.getElementById('ticketImageLightboxImg').src = '';
    };

    document.addEventListener('click', function(ev) {
        var img = ev.target.closest('.ticket-msg-content img');
        if (!img) return;
        ev.preventDefault();
        var lb = document.getElementById('ticketImageLightbox');
        document.getElementById('ticketImageLightboxImg').src = img.src;
        lb.classList.remove('hidden');
        lb.classList.add('flex');
    });

    document.addEventListener('click', function(ev) {
        var el = ev.target.closest('[data-open-ticket]');
        if (!el) return;
        ev.preventDefault();
        ev.stopPropagation();
        loadTicket(el.getAttribute('data-escola-id'), el.getAttribute('data-ticket-id'));
    });

    document.addEventListener('click', function(ev) {
        var el = ev.target.closest('[data-mark-progress]');
        if (!el) return;
        ev.preventDefault();
        ev.stopPropagation();
        marcarEmAndamento(el.getAttribute('data-escola-id'), el.getAttribute('data-ticket-id'));
    });

    function marcarEmAndamento(escolaId, ticketId) {
        var fd = new FormData();
        fd.append('_token', csrf);
        fd.append('escola_id', String(escolaId));
        fd.append('ticket_id', String(ticketId));
        fetch(baseUrl + '/master/tickets/em-andamento', {
            method: 'POST',
            body: fd,
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json(); }).then(function(res) {
            if (!res.ok) { alert(res.error || 'Erro ao atualizar ticket'); return; }
            window.location.reload();
        }).catch(function() { alert('Erro de rede ao atualizar ticket'); });
    }

    document.addEventListener('keydown', function(e) {
        if (e.key !== 'Escape') return;
        var lightboxOpen = !document.getElementById('ticketImageLightbox').classList.contains('hidden');
        if (lightboxOpen) {
            closeTicketImageLightbox();
            return;
        }
        var ticketOpen = !document.getElementById('ticketDrawer').classList.contains('translate-x-full');
        if (ticketOpen) {
            closeTicketDrawer();
            return;
        }
        closeFilterDrawer();
    });

    var params = new URLSearchParams(window.location.search);
    var abrirEscola = params.get('abrir_escola');
    var abrirTicket = params.get('abrir_ticket');
    if (abrirEscola && abrirTicket) {
        loadTicket(abrirEscola, abrirTicket);
    }
})();
</script>
