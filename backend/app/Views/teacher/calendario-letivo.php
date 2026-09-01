<?php
$eventos = $eventos ?? [];
$ano     = (int) ($ano ?? date('Y'));

$tipoLabels = $tipoLabels ?? [];
$tipoBg     = $tipoBg ?? [];
$tipoText   = $tipoText ?? [];
if ($tipoLabels === []) {
    $tipoLabels = [
        'feriado' => 'Feriado', 'recesso' => 'Recesso', 'reposicao' => 'Reposição',
        'evento' => 'Evento', 'suspensao' => 'Suspensão', 'avaliacao' => 'Avaliação',
    ];
    $tipoBg = [
        'feriado' => '#fee2e2', 'recesso' => '#fef3c7', 'reposicao' => '#dcfce7',
        'evento' => '#dbeafe', 'suspensao' => '#f3f4f6', 'avaliacao' => '#ede9fe',
    ];
    $tipoText = [
        'feriado' => '#991b1b', 'recesso' => '#92400e', 'reposicao' => '#166534',
        'evento' => '#1e40af', 'suspensao' => '#374151', 'avaliacao' => '#5b21b6',
    ];
}

$jsonJs = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
$eventMap = [];
foreach ($eventos as $ev) {
    $start = strtotime((string) $ev['data_inicio']);
    $end   = strtotime((string) $ev['data_fim']);
    for ($d = $start; $d <= $end; $d += 86400) {
        $key = date('Y-m-d', $d);
        $eventMap[$key][] = $ev;
    }
}

$mesesNomes = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$mesAtual   = (int) date('m');
$tiposUsados = [];
foreach ($eventos as $ev) {
    $t = (string) ($ev['tipo'] ?? '');
    if ($t !== '') {
        $tiposUsados[$t] = true;
    }
}
$tipoLabelsLegenda = $tiposUsados === [] ? $tipoLabels : array_intersect_key($tipoLabels, $tiposUsados);
?>

<div class="mb-6">
    <div class="flex flex-wrap items-center gap-2">
        <h1 class="text-2xl font-bold text-gray-900 flex-1">Calendário Letivo <?= $ano ?></h1>
        <a href="?ano=<?= $ano - 1 ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-left"></i></a>
        <a href="?ano=<?= $ano + 1 ?>" class="px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-right"></i></a>
        <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden bg-white">
            <button id="btnViewYear" onclick="setView('year')" class="px-3 py-2 text-sm font-medium"><i class="fa-solid fa-calendar-days mr-1.5"></i>Ano</button>
            <button id="btnViewMonth" onclick="setView('month')" class="px-3 py-2 text-sm font-medium border-l border-gray-300"><i class="fa-solid fa-calendar mr-1.5"></i>Mês</button>
        </div>
        <div id="navMes" class="hidden items-center gap-1">
            <button onclick="navMes(-1)" class="px-2 py-2 border border-gray-300 rounded-lg text-sm bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-left"></i></button>
            <span id="labelMes" class="text-sm font-semibold text-gray-700 w-28 text-center"></span>
            <button onclick="navMes(+1)" class="px-2 py-2 border border-gray-300 rounded-lg text-sm bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
    </div>
    <p class="text-sm text-gray-500 mt-1">Feriados, recessos, avaliações e eventos do ano letivo.</p>
</div>

<!-- Legenda -->
<div class="flex flex-wrap gap-3 mb-5">
    <?php foreach ($tipoLabelsLegenda as $k => $v): ?>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full" style="background:<?= htmlspecialchars($tipoBg[$k] ?? '#f3f4f6') ?>;color:<?= htmlspecialchars($tipoText[$k] ?? '#374151') ?>">
        <span class="w-2 h-2 rounded-full" style="background:<?= htmlspecialchars($tipoText[$k] ?? '#6b7280') ?>"></span><?= htmlspecialchars((string) $v) ?>
    </span>
    <?php endforeach; ?>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">
        <span class="w-2 h-2 rounded-full bg-gray-400"></span>Fim de semana
    </span>
</div>

<!-- View mensal -->
<div id="viewMes" class="hidden mb-8">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div id="mesCabecalho" class="px-6 py-4 border-b border-gray-100 flex items-center justify-between" style="background-color: var(--primary-color);">
            <span id="mesTitulo" class="text-base font-bold" style="color: var(--primary-text-color);"></span>
            <span class="text-sm opacity-75" style="color: var(--primary-text-color);"><?= $ano ?></span>
        </div>
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50">
            <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $d): ?>
            <div class="py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= $d ?></div>
            <?php endforeach; ?>
        </div>
        <div id="mesGrid" class="grid grid-cols-7 divide-x divide-y divide-gray-100"></div>
    </div>
    <div id="mesEventosList" class="mt-4 space-y-2"></div>
</div>

<!-- Grade dos 12 meses -->
<div id="viewAno" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
<?php for ($mes = 1; $mes <= 12; $mes++):
    $primeiroDia  = mktime(0, 0, 0, $mes, 1, $ano);
    $diasNoMes    = (int) date('t', $primeiroDia);
    $inicioSemana = (int) date('N', $primeiroDia);
    $offset = $inicioSemana % 7;
?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between" style="background-color: var(--primary-color);">
        <span class="text-sm font-semibold" style="color: var(--primary-text-color);"><?= $mesesNomes[$mes-1] ?></span>
        <span class="text-xs opacity-75" style="color: var(--primary-text-color);"><?= $ano ?></span>
    </div>
    <div class="p-3">
        <div class="grid grid-cols-7 mb-1">
            <?php foreach (['D','S','T','Q','Q','S','S'] as $dow): ?>
            <div class="text-center text-xs font-medium text-gray-400 py-1"><?= $dow ?></div>
            <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-7 gap-0.5">
            <?php
            for ($i = 0; $i < $offset; $i++): ?>
            <div></div>
            <?php endfor;
            for ($dia = 1; $dia <= $diasNoMes; $dia++):
                $dateKey   = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                $timestamp = mktime(0,0,0,$mes,$dia,$ano);
                $dow       = (int) date('N', $timestamp);
                $isWeekend = $dow >= 6;
                $evsDia    = $eventMap[$dateKey] ?? [];
                $tiposDia  = array_unique(array_column($evsDia, 'tipo'));
                $primTipo  = $tiposDia[0] ?? null;
                $isToday   = ($dateKey === date('Y-m-d'));

                if ($primTipo) {
                    $cellBg   = $tipoBg[$primTipo] ?? '#f9fafb';
                    $cellText = $tipoText[$primTipo] ?? '#111827';
                } elseif ($isWeekend) {
                    $cellBg   = '#f9fafb';
                    $cellText = '#9ca3af';
                } else {
                    $cellBg   = 'transparent';
                    $cellText = '#374151';
                }
                $ring = $isToday ? 'ring-2 ring-offset-1 ring-blue-500' : '';

                $evPayload = '';
                if (!empty($evsDia)) {
                    $evPayload = htmlspecialchars(json_encode([
                        'date'   => date('d/m/Y', $timestamp),
                        'events' => array_map(fn($e) => [
                            'tipo'         => $e['tipo'],
                            'label'        => $tipoLabels[$e['tipo']] ?? $e['tipo'],
                            'descricao'    => $e['descricao'],
                            'inicio'       => date('d/m/Y', strtotime($e['data_inicio'])),
                            'fim'          => date('d/m/Y', strtotime($e['data_fim'])),
                            'link_reuniao' => $e['link_reuniao'] ?? '',
                            'local_evento' => $e['local_evento'] ?? '',
                        ], array_values($evsDia)),
                    ], $jsonJs), ENT_QUOTES);
                }
            ?>
            <div
                class="relative flex items-center justify-center rounded text-xs font-medium h-7 select-none <?= $ring ?> <?= !empty($evsDia) ? 'cursor-pointer hover:opacity-80 hover:shadow-sm transition-all' : 'cursor-default' ?>"
                style="background-color:<?= $cellBg ?>; color:<?= $cellText ?>;"
                <?= $evPayload ? "onclick=\"openDayModal('" . $evPayload . "')\"" : '' ?>
            ><?= $dia ?><?php if (count($tiposDia) > 1): ?><span class="absolute top-0.5 right-0.5 w-1.5 h-1.5 rounded-full bg-blue-500"></span><?php endif; ?></div>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endfor; ?>
</div>

<!-- Lista de eventos -->
<?php if (!empty($eventos)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Eventos do ano</h3>
    </div>
    <div class="divide-y divide-gray-100">
    <?php foreach ($eventos as $ev):
        $t      = (string) $ev['tipo'];
        $dtI    = date('d/m/Y', strtotime((string) $ev['data_inicio']));
        $dtF    = date('d/m/Y', strtotime((string) $ev['data_fim']));
        $periodo = ($ev['data_inicio'] === $ev['data_fim']) ? $dtI : "$dtI – $dtF";
    ?>
        <div class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= $tipoText[$t] ?? '#6b7280' ?>"></span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) $ev['descricao']) ?></p>
                <p class="text-xs text-gray-500"><?= $periodo ?><?= !empty($ev['local_evento']) ? ' · <i class="fa-solid fa-location-dot"></i> ' . htmlspecialchars($ev['local_evento']) : '' ?></p>
            </div>
            <span class="text-xs font-semibold px-2 py-1 rounded-full" style="background:<?= htmlspecialchars($tipoBg[$t] ?? '#f3f4f6') ?>;color:<?= htmlspecialchars($tipoText[$t] ?? '#374151') ?>"><?= htmlspecialchars($tipoLabels[$t] ?? $t) ?></span>
            <?php if (!empty($ev['link_reuniao'])): ?>
            <a href="<?= htmlspecialchars($ev['link_reuniao']) ?>" target="_blank" rel="noopener" class="text-blue-600 hover:text-blue-800 text-xs"><i class="fa-solid fa-link"></i></a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-10 text-center text-gray-500 mb-6">
    <i class="fa-regular fa-calendar-days text-3xl text-gray-300 mb-3 block"></i>
    Nenhum evento disponível para <?= $ano ?>.
</div>
<?php endif; ?>

<!-- Modal: Detalhe do dia -->
<div id="modalDia" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div id="modalDiaHeader" class="flex items-center justify-between px-6 py-4 rounded-t-2xl">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider opacity-75" id="modalDiaTipoLabel"></p>
                <h3 class="text-lg font-bold" id="modalDiaData"></h3>
            </div>
            <button onclick="closeDayModal()" class="opacity-75 hover:opacity-100"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div id="modalDiaBody" class="px-6 pb-6 space-y-3 max-h-80 overflow-y-auto"></div>
    </div>
</div>

<script>
var MESES_NOMES = <?= json_encode($mesesNomes, $jsonJs) ?>;
var currentView = 'year';
var currentMes  = <?= $mesAtual ?>;
var anoAtual    = <?= $ano ?>;
var tipoBg      = <?= json_encode($tipoBg, $jsonJs) ?>;
var tipoText    = <?= json_encode($tipoText, $jsonJs) ?>;
var eventData   = <?php
    $jsMap = [];
    foreach ($eventMap as $k => $evs) {
        $jsMap[$k] = array_map(fn($e) => [
            'tipo'         => $e['tipo'],
            'label'        => $tipoLabels[$e['tipo']] ?? $e['tipo'],
            'descricao'    => $e['descricao'],
            'inicio'       => date('d/m/Y', strtotime($e['data_inicio'])),
            'fim'          => date('d/m/Y', strtotime($e['data_fim'])),
            'link_reuniao' => $e['link_reuniao'] ?? '',
            'local_evento' => $e['local_evento'] ?? '',
        ], array_values($evs));
    }
    echo json_encode($jsMap, $jsonJs);
?>;

function setView(v) {
    currentView = v;
    var btnY = document.getElementById('btnViewYear');
    var btnM = document.getElementById('btnViewMonth');
    var navM = document.getElementById('navMes');
    if (v === 'year') {
        btnY.classList.add('bg-gray-100','font-semibold');
        btnM.classList.remove('bg-gray-100','font-semibold');
        navM.classList.add('hidden'); navM.classList.remove('flex');
        document.getElementById('viewAno').classList.remove('hidden');
        document.getElementById('viewMes').classList.add('hidden');
    } else {
        btnM.classList.add('bg-gray-100','font-semibold');
        btnY.classList.remove('bg-gray-100','font-semibold');
        navM.classList.remove('hidden'); navM.classList.add('flex');
        document.getElementById('viewAno').classList.add('hidden');
        document.getElementById('viewMes').classList.remove('hidden');
        renderMes(currentMes);
    }
}

function navMes(delta) {
    currentMes += delta;
    if (currentMes < 1)  currentMes = 12;
    if (currentMes > 12) currentMes = 1;
    renderMes(currentMes);
}

function renderMes(mes) {
    document.getElementById('labelMes').textContent = MESES_NOMES[mes - 1];
    document.getElementById('mesTitulo').textContent = MESES_NOMES[mes - 1];
    var ano   = anoAtual;
    var grid  = document.getElementById('mesGrid');
    grid.innerHTML = '';
    var firstDay    = new Date(ano, mes - 1, 1);
    var daysInMonth = new Date(ano, mes, 0).getDate();
    var offset      = firstDay.getDay();
    var today       = new Date(); today.setHours(0,0,0,0);

    for (var i = 0; i < offset; i++) {
        var empty = document.createElement('div');
        empty.className = 'min-h-[80px] bg-gray-50/50';
        grid.appendChild(empty);
    }
    for (var d = 1; d <= daysInMonth; d++) {
        var dt        = new Date(ano, mes - 1, d);
        var dow       = dt.getDay();
        var isWeekend = (dow === 0 || dow === 6);
        var key       = ano + '-' + String(mes).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        var evs       = eventData[key] || [];
        var isToday   = (dt.getTime() === today.getTime());

        var cell = document.createElement('div');
        cell.className = 'min-h-[80px] p-1.5 flex flex-col ' + (isWeekend ? 'bg-gray-50' : 'bg-white');
        if (isToday) cell.classList.add('ring-2','ring-inset','ring-blue-400');

        var numDiv  = document.createElement('div');
        numDiv.className = 'text-right mb-1';
        var numSpan = document.createElement('span');
        numSpan.className = 'text-xs font-semibold inline-flex items-center justify-center w-6 h-6 rounded-full ' +
            (isToday ? 'bg-blue-500 text-white' : (isWeekend ? 'text-gray-400' : 'text-gray-700'));
        numSpan.textContent = d;
        numDiv.appendChild(numSpan);
        cell.appendChild(numDiv);

        evs.forEach(function(ev) {
            var chip = document.createElement('div');
            chip.className = 'text-xs rounded px-1 py-0.5 mb-0.5 truncate cursor-pointer font-medium';
            chip.style.backgroundColor = tipoBg[ev.tipo] || '#f3f4f6';
            chip.style.color = tipoText[ev.tipo] || '#111';
            chip.textContent = ev.descricao;
            chip.onclick = function(e) {
                e.stopPropagation();
                openDayModal(JSON.stringify({
                    date: String(d).padStart(2,'0') + '/' + String(mes).padStart(2,'0') + '/' + ano,
                    events: evs
                }));
            };
            cell.appendChild(chip);
        });

        if (evs.length > 0) {
            cell.style.cursor = 'pointer';
            cell.addEventListener('click', (function(evsCopy, dCopy) {
                return function() {
                    openDayModal(JSON.stringify({
                        date: String(dCopy).padStart(2,'0') + '/' + String(mes).padStart(2,'0') + '/' + ano,
                        events: evsCopy
                    }));
                };
            })(evs, d));
        }
        grid.appendChild(cell);
    }

    var list   = document.getElementById('mesEventosList');
    list.innerHTML = '';
    var mesKey = String(mes).padStart(2,'0');
    var mesEvs = [];
    Object.keys(eventData).forEach(function(k) {
        if (k.startsWith(ano + '-' + mesKey + '-')) {
            eventData[k].forEach(function(ev) {
                if (!mesEvs.find(function(e){ return e.tipo === ev.tipo && e.descricao === ev.descricao && e.inicio === ev.inicio; })) mesEvs.push(ev);
            });
        }
    });
    if (!mesEvs.length) return;

    var header = document.createElement('h4');
    header.className = 'text-sm font-semibold text-gray-700 mb-2';
    header.textContent = 'Eventos de ' + MESES_NOMES[mes-1];
    list.appendChild(header);

    mesEvs.forEach(function(ev) {
        var div = document.createElement('div');
        div.className = 'flex items-center gap-3 px-4 py-2.5 rounded-xl border text-sm';
        div.style.backgroundColor = tipoBg[ev.tipo] || '#f9fafb';
        div.style.borderColor = (tipoText[ev.tipo]||'#6b7280') + '33';
        var dtRange = ev.inicio === ev.fim ? ev.inicio : ev.inicio + ' – ' + ev.fim;
        div.innerHTML =
            '<span class="w-2 h-2 rounded-full flex-shrink-0" style="background:' + (tipoText[ev.tipo]||'#6b7280') + '"></span>' +
            '<div class="flex-1"><p class="font-medium" style="color:' + (tipoText[ev.tipo]||'#111') + '">' + escHtml(ev.descricao) + '</p>' +
            '<p class="text-xs opacity-75">' + escHtml(ev.label) + ' · ' + dtRange + '</p></div>';
        list.appendChild(div);
    });
}

function openDayModal(payloadStr) {
    var data   = JSON.parse(payloadStr);
    var events = data.events;
    var primTipo = events.length ? events[0].tipo : 'evento';

    var header = document.getElementById('modalDiaHeader');
    header.style.backgroundColor = tipoBg[primTipo] || '#f3f4f6';
    header.style.color = tipoText[primTipo] || '#111827';
    document.getElementById('modalDiaData').textContent = data.date;

    var tipos = [...new Set(events.map(function(e){ return e.label; }))];
    document.getElementById('modalDiaTipoLabel').textContent = tipos.join(' · ');

    var body = document.getElementById('modalDiaBody');
    body.innerHTML = '';
    events.forEach(function(ev) {
        var periodo = ev.inicio === ev.fim ? ev.inicio : ev.inicio + ' – ' + ev.fim;
        var div = document.createElement('div');
        div.className = 'p-3 rounded-xl border';
        div.style.backgroundColor = tipoBg[ev.tipo] || '#f9fafb';
        div.style.borderColor = tipoText[ev.tipo] + '33' || '#e5e7eb';

        var extras = '';
        if (ev.local_evento) extras += '<span class="inline-flex items-center gap-1 mr-2"><i class="fa-solid fa-location-dot text-xs"></i>' + escHtml(ev.local_evento) + '</span>';
        if (ev.link_reuniao) extras += '<a href="' + escHtml(ev.link_reuniao) + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1 underline"><i class="fa-solid fa-link text-xs"></i>Acessar link</a>';

        div.innerHTML =
            '<p class="text-sm font-semibold" style="color:' + (tipoText[ev.tipo]||'#111') + '">' + escHtml(ev.descricao) + '</p>' +
            '<p class="text-xs mt-0.5 opacity-75" style="color:' + (tipoText[ev.tipo]||'#666') + '">' + escHtml(ev.label) + ' · ' + escHtml(periodo) + '</p>' +
            (extras ? '<p class="text-xs mt-1" style="color:' + (tipoText[ev.tipo]||'#666') + '">' + extras + '</p>' : '');
        body.appendChild(div);
    });

    document.getElementById('modalDia').classList.remove('hidden');
}

function closeDayModal() {
    document.getElementById('modalDia').classList.add('hidden');
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeDayModal();
});
document.getElementById('modalDia').addEventListener('click', function(e) {
    if (e.target === this) closeDayModal();
});

setView('year');
</script>
