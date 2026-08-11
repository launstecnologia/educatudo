<?php
/**
 * Calendário mensal da agenda do aluno (widget do dashboard).
 * Variáveis: $agenda_eventos (array), $agenda_ano, $agenda_mes, $primary_color
 */
$agendaEventos = $agenda_eventos ?? [];
$agendaAno = (int) ($agenda_ano ?? date('Y'));
$agendaMes = (int) ($agenda_mes ?? date('n'));
$primaryHex = $primary_color ?? '#3b82f6';

$mesesNomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$tipoLabels = [
    'prova' => 'Prova',
    'jornada' => 'Jornada',
    'redacao' => 'Redação',
    'aula_online' => 'Aula online',
    'evento' => 'Evento',
    'escola' => 'Evento da escola',
    'pessoal' => 'Meu item',
    'aviso' => 'Aviso',
    'entrega' => 'Entrega',
];
$tipoDotColors = [
    'prova' => '#ef4444',
    'jornada' => '#3b82f6',
    'redacao' => '#a855f7',
    'aula_online' => '#14b8a6',
    'evento' => '#22c55e',
    'escola' => '#22c55e',
    'pessoal' => '#eab308',
    'aviso' => '#f59e0b',
    'entrega' => '#6366f1',
];

$eventosPorDia = [];
foreach ($agendaEventos as $ev) {
    $d = (string) ($ev['data'] ?? '');
    if ($d !== '') {
        $eventosPorDia[$d][] = $ev;
    }
}

$primeiroDia = mktime(0, 0, 0, $agendaMes, 1, $agendaAno);
$diasNoMes = (int) date('t', $primeiroDia);
$offset = (int) date('w', $primeiroDia);
$hoje = date('Y-m-d');
?>

<div class="bg-white rounded-2xl shadow-md overflow-hidden" id="dashboard-agenda-widget" data-ano="<?= $agendaAno ?>" data-mes="<?= $agendaMes ?>" data-defer-load="<?= !empty($dashboard_agenda_defer) ? '1' : '0' ?>">
    <div class="p-4 sm:p-6 border-b border-gray-100">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Agenda do mês</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">Provas, jornadas, redações e compromissos</p>
            </div>
            <div class="flex items-center gap-2 flex-wrap">
                <button type="button" id="dashboard-agenda-prev" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors" aria-label="Mês anterior">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <span id="dashboard-agenda-mes-label" class="text-sm sm:text-base font-bold text-gray-900 min-w-[140px] text-center"><?= $mesesNomes[$agendaMes - 1] ?> de <?= $agendaAno ?></span>
                <button type="button" id="dashboard-agenda-next" class="p-2 rounded-lg text-gray-600 hover:bg-gray-100 transition-colors" aria-label="Próximo mês">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </button>
                <button type="button" id="dashboard-agenda-hoje" class="px-3 py-1.5 text-xs font-semibold rounded-lg border border-gray-200 text-gray-700 hover:bg-gray-50 transition-colors">Hoje</button>
            </div>
        </div>
    </div>

    <div id="dashboard-agenda-loading" class="hidden px-6 py-4 text-sm text-gray-500 flex items-center gap-2">
        <svg class="animate-spin h-4 w-4 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path></svg>
        Carregando agenda…
    </div>
    <div id="dashboard-agenda-erro" class="hidden px-6 py-3 text-sm text-red-600 bg-red-50 border-b border-red-100"></div>

    <div id="dashboard-agenda-calendario">
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50">
            <?php foreach (['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'] as $d): ?>
            <div class="py-2 sm:py-3 text-center text-[10px] sm:text-xs font-semibold text-gray-500 uppercase"><?= $d ?></div>
            <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-7 divide-x divide-y divide-gray-100" id="dashboard-agenda-grade">
            <?php for ($i = 0; $i < $offset; $i++): ?>
            <div class="min-h-[52px] sm:min-h-[72px] bg-gray-50/60"></div>
            <?php endfor; ?>
            <?php for ($dia = 1; $dia <= $diasNoMes; $dia++):
                $dataIso = sprintf('%04d-%02d-%02d', $agendaAno, $agendaMes, $dia);
                $temEventos = !empty($eventosPorDia[$dataIso]);
                $ehHoje = $dataIso === $hoje;
                $tiposNoDia = [];
                if ($temEventos) {
                    foreach ($eventosPorDia[$dataIso] as $evDia) {
                        $tiposNoDia[$evDia['tipo'] ?? 'evento'] = true;
                    }
                }
            ?>
            <button type="button"
                    class="dashboard-agenda-dia min-h-[52px] sm:min-h-[72px] p-1 sm:p-2 text-left hover:bg-gray-50 transition-colors focus:outline-none focus:ring-2 focus:ring-inset focus:ring-blue-300 <?= $ehHoje ? 'bg-blue-50/80 ring-2 ring-inset' : '' ?>"
                    style="<?= $ehHoje ? 'ring-color:' . htmlspecialchars($primaryHex) . ';' : '' ?>"
                    data-data="<?= htmlspecialchars($dataIso) ?>"
                    aria-label="Dia <?= $dia ?>">
                <span class="inline-flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 text-xs sm:text-sm font-semibold rounded-full <?= $ehHoje ? 'text-white' : 'text-gray-800' ?>"
                      style="<?= $ehHoje ? 'background-color:' . htmlspecialchars($primaryHex) : '' ?>"><?= $dia ?></span>
                <?php if ($temEventos): ?>
                <div class="flex flex-wrap gap-0.5 mt-1 justify-center sm:justify-start">
                    <?php foreach (array_keys($tiposNoDia) as $tipoDia):
                        $corDot = $tipoDotColors[$tipoDia] ?? '#6b7280';
                    ?>
                    <span class="w-1.5 h-1.5 rounded-full" style="background:<?= htmlspecialchars($corDot) ?>" title="<?= htmlspecialchars($tipoLabels[$tipoDia] ?? $tipoDia) ?>"></span>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </button>
            <?php endfor; ?>
        </div>
    </div>

    <div class="p-4 sm:p-6 border-t border-gray-100 bg-gray-50/50">
        <h3 class="text-sm font-semibold text-gray-800 mb-3" id="dashboard-agenda-dia-titulo">
            Eventos de <?= date('d/m/Y', strtotime($hoje)) ?>
        </h3>
        <div id="dashboard-agenda-eventos-lista" class="space-y-2">
            <?php
            $eventosHoje = $eventosPorDia[$hoje] ?? [];
            if (empty($eventosHoje)):
            ?>
            <p class="text-sm text-gray-500" id="dashboard-agenda-vazio">Nenhum compromisso para este dia.</p>
            <?php else: ?>
                <?php foreach ($eventosHoje as $ev): ?>
                <?php
                $tipoEv = (string) ($ev['tipo'] ?? 'evento');
                $corEv = $tipoDotColors[$tipoEv] ?? '#6b7280';
                $linkEv = trim((string) ($ev['link'] ?? ''));
                ?>
                <div class="flex items-start gap-3 p-3 bg-white rounded-xl border border-gray-100 shadow-sm">
                    <span class="w-2 h-2 rounded-full mt-2 flex-shrink-0" style="background:<?= htmlspecialchars($corEv) ?>"></span>
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-medium uppercase tracking-wide text-gray-500"><?= htmlspecialchars($tipoLabels[$tipoEv] ?? $tipoEv) ?><?php if (!empty($ev['hora'])): ?> · <?= htmlspecialchars($ev['hora']) ?><?php endif; ?></p>
                        <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($ev['titulo'] ?? '') ?></p>
                        <?php if (!empty($ev['descricao'])): ?>
                        <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($ev['descricao']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if ($linkEv !== ''): ?>
                    <a href="<?= htmlspecialchars($linkEv) ?>" class="text-xs font-medium flex-shrink-0 hover:underline" style="color:<?= htmlspecialchars($primaryHex) ?>">Abrir</a>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="px-4 sm:px-6 pb-4 flex flex-wrap gap-2 text-[10px] sm:text-xs text-gray-500">
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-red-500"></span> Prova</span>
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-blue-500"></span> Jornada</span>
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-purple-500"></span> Redação</span>
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-teal-500"></span> Aula online</span>
        <span class="inline-flex items-center gap-1"><span class="w-2 h-2 rounded-full bg-green-500"></span> Evento</span>
    </div>
</div>

<script>
(function () {
    const widget = document.getElementById('dashboard-agenda-widget');
    if (!widget) return;

    const agendaUrl = <?= json_encode(URL . '/dashboard/agenda') ?>;
    const tipoLabels = <?= json_encode($tipoLabels, JSON_UNESCAPED_UNICODE) ?>;
    const tipoDotColors = <?= json_encode($tipoDotColors, JSON_UNESCAPED_UNICODE) ?>;
    const primaryHex = <?= json_encode($primaryHex) ?>;

    let ano = parseInt(widget.dataset.ano, 10);
    let mes = parseInt(widget.dataset.mes, 10);
    let eventosPorDia = <?= json_encode($eventosPorDia, JSON_UNESCAPED_UNICODE) ?>;
    let diaSelecionado = <?= json_encode($hoje) ?>;

    const elGrade = document.getElementById('dashboard-agenda-grade');
    const elMesLabel = document.getElementById('dashboard-agenda-mes-label');
    const elLista = document.getElementById('dashboard-agenda-eventos-lista');
    const elDiaTitulo = document.getElementById('dashboard-agenda-dia-titulo');
    const elLoading = document.getElementById('dashboard-agenda-loading');
    const elErro = document.getElementById('dashboard-agenda-erro');
    const mesesNomes = <?= json_encode($mesesNomes, JSON_UNESCAPED_UNICODE) ?>;

    function formatarDataBr(iso) {
        const p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function renderEventosDia(dataIso) {
        diaSelecionado = dataIso;
        elDiaTitulo.textContent = 'Eventos de ' + formatarDataBr(dataIso);
        const eventos = eventosPorDia[dataIso] || [];
        if (!eventos.length) {
            elLista.innerHTML = '<p class="text-sm text-gray-500">Nenhum compromisso para este dia.</p>';
            return;
        }
        elLista.innerHTML = eventos.map(function (ev) {
            const tipo = ev.tipo || 'evento';
            const cor = tipoDotColors[tipo] || '#6b7280';
            const label = tipoLabels[tipo] || tipo;
            const hora = ev.hora ? ' · ' + ev.hora : '';
            const desc = ev.descricao ? '<p class="text-xs text-gray-500 mt-0.5">' + escapeHtml(ev.descricao) + '</p>' : '';
            const link = ev.link ? '<a href="' + escapeHtml(ev.link) + '" class="text-xs font-medium flex-shrink-0 hover:underline" style="color:' + primaryHex + '">Abrir</a>' : '';
            return '<div class="flex items-start gap-3 p-3 bg-white rounded-xl border border-gray-100 shadow-sm">' +
                '<span class="w-2 h-2 rounded-full mt-2 flex-shrink-0" style="background:' + cor + '"></span>' +
                '<div class="flex-1 min-w-0"><p class="text-xs font-medium uppercase tracking-wide text-gray-500">' + escapeHtml(label) + hora + '</p>' +
                '<p class="text-sm font-semibold text-gray-900">' + escapeHtml(ev.titulo || '') + '</p>' + desc + '</div>' + link + '</div>';
        }).join('');
        document.querySelectorAll('.dashboard-agenda-dia').forEach(function (btn) {
            btn.classList.toggle('ring-2', btn.dataset.data === dataIso);
            btn.classList.toggle('ring-inset', btn.dataset.data === dataIso);
        });
    }

    function escapeHtml(s) {
        return String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderGrade() {
        const primeiro = new Date(ano, mes - 1, 1);
        const diasNoMes = new Date(ano, mes, 0).getDate();
        const offset = primeiro.getDay();
        const hojeIso = new Date().toISOString().slice(0, 10);
        let html = '';
        for (let i = 0; i < offset; i++) {
            html += '<div class="min-h-[52px] sm:min-h-[72px] bg-gray-50/60"></div>';
        }
        for (let dia = 1; dia <= diasNoMes; dia++) {
            const m = String(mes).padStart(2, '0');
            const d = String(dia).padStart(2, '0');
            const dataIso = ano + '-' + m + '-' + d;
            const eventos = eventosPorDia[dataIso] || [];
            const tipos = {};
            eventos.forEach(function (ev) { tipos[ev.tipo || 'evento'] = true; });
            const ehHoje = dataIso === hojeIso;
            let dots = '';
            Object.keys(tipos).forEach(function (t) {
                dots += '<span class="w-1.5 h-1.5 rounded-full" style="background:' + (tipoDotColors[t] || '#6b7280') + '"></span>';
            });
            html += '<button type="button" class="dashboard-agenda-dia min-h-[52px] sm:min-h-[72px] p-1 sm:p-2 text-left hover:bg-gray-50 transition-colors focus:outline-none' +
                (ehHoje ? ' bg-blue-50/80 ring-2 ring-inset' : '') + '" data-data="' + dataIso + '">' +
                '<span class="inline-flex items-center justify-center w-6 h-6 sm:w-7 sm:h-7 text-xs sm:text-sm font-semibold rounded-full' +
                (ehHoje ? ' text-white" style="background:' + primaryHex : ' text-gray-800"') + '">' + dia + '</span>' +
                (dots ? '<div class="flex flex-wrap gap-0.5 mt-1 justify-center sm:justify-start">' + dots + '</div>' : '') +
                '</button>';
        }
        elGrade.innerHTML = html;
        elMesLabel.textContent = mesesNomes[mes - 1] + ' de ' + ano;
        widget.dataset.ano = ano;
        widget.dataset.mes = mes;
        bindDias();
        if (!eventosPorDia[diaSelecionado] && diaSelecionado.slice(0, 7) !== (ano + '-' + String(mes).padStart(2, '0'))) {
            diaSelecionado = ano + '-' + String(mes).padStart(2, '0') + '-01';
        }
        renderEventosDia(diaSelecionado);
    }

    function bindDias() {
        document.querySelectorAll('.dashboard-agenda-dia').forEach(function (btn) {
            btn.addEventListener('click', function () {
                renderEventosDia(btn.dataset.data);
            });
        });
    }

    function carregarMes(novoAno, novoMes) {
        elLoading.classList.remove('hidden');
        elErro.classList.add('hidden');
        fetch(agendaUrl + '?ano=' + novoAno + '&mes=' + novoMes, { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                elLoading.classList.add('hidden');
                if (!data.success) {
                    elErro.textContent = data.error || 'Não foi possível carregar a agenda.';
                    elErro.classList.remove('hidden');
                    return;
                }
                ano = data.year;
                mes = data.month;
                eventosPorDia = {};
                (data.events || []).forEach(function (ev) {
                    const d = ev.data;
                    if (!d) return;
                    if (!eventosPorDia[d]) eventosPorDia[d] = [];
                    eventosPorDia[d].push(ev);
                });
                renderGrade();
            })
            .catch(function () {
                elLoading.classList.add('hidden');
                elErro.textContent = 'Erro de rede ao carregar a agenda.';
                elErro.classList.remove('hidden');
            });
    }

    document.getElementById('dashboard-agenda-prev')?.addEventListener('click', function () {
        let m = mes - 1, a = ano;
        if (m < 1) { m = 12; a--; }
        carregarMes(a, m);
    });
    document.getElementById('dashboard-agenda-next')?.addEventListener('click', function () {
        let m = mes + 1, a = ano;
        if (m > 12) { m = 1; a++; }
        carregarMes(a, m);
    });
    document.getElementById('dashboard-agenda-hoje')?.addEventListener('click', function () {
        const h = new Date();
        carregarMes(h.getFullYear(), h.getMonth() + 1);
        diaSelecionado = h.toISOString().slice(0, 10);
    });

    bindDias();

    if (widget.dataset.deferLoad === '1') {
        carregarMes(ano, mes);
    }
})();
</script>
