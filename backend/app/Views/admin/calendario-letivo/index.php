<?php
$config  = $config  ?? null;
$eventos = $eventos ?? [];
$status  = $status  ?? null;
$ano     = (int) ($ano ?? date('Y'));

$tipoLabels = [
    'feriado'   => 'Feriado',
    'recesso'   => 'Recesso',
    'reposicao' => 'Reposição',
    'evento'    => 'Evento',
    'suspensao' => 'Suspensão',
    'avaliacao' => 'Avaliação',
];
$tipoBg = [
    'feriado'   => '#fee2e2',
    'recesso'   => '#fef3c7',
    'reposicao' => '#dcfce7',
    'evento'    => '#dbeafe',
    'suspensao' => '#f3f4f6',
    'avaliacao' => '#ede9fe',
];
$tipoText = [
    'feriado'   => '#991b1b',
    'recesso'   => '#92400e',
    'reposicao' => '#166534',
    'evento'    => '#1e40af',
    'suspensao' => '#374151',
    'avaliacao' => '#5b21b6',
];
$tipoClasses = [
    'feriado'   => 'bg-red-100 text-red-800',
    'recesso'   => 'bg-amber-100 text-amber-800',
    'reposicao' => 'bg-green-100 text-green-800',
    'evento'    => 'bg-blue-100 text-blue-800',
    'suspensao' => 'bg-gray-200 text-gray-700',
    'avaliacao' => 'bg-violet-100 text-violet-800',
];

$pct  = $status['percentual'] ?? null;
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));

// Monta mapa de datas com eventos: ['2026-04-21'] = [['tipo'=>..,'descricao'=>..], ...]
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
$mesAtual   = (int) date('m'); // mês atual para view mensal
$page_header_title    = 'Calendário Letivo ' . $ano;
$page_header_subtitle = 'Visualize feriados, recessos, reposições e eventos do ano letivo.';

ob_start();
?>
<div class="flex flex-wrap items-center gap-2">
    <a href="?ano=<?= $ano - 1 ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-left"></i></a>
    <span class="text-sm font-semibold text-gray-700"><?= $ano ?></span>
    <a href="?ano=<?= $ano + 1 ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-right"></i></a>
    <!-- Toggle de visualização -->
    <div class="flex items-center border border-gray-300 rounded-lg overflow-hidden bg-white">
        <button id="btnViewYear" onclick="setView('year')" class="px-3 py-2 text-sm font-medium transition-colors" title="Ano inteiro"><i class="fa-solid fa-calendar-days mr-1.5"></i>Ano</button>
        <button id="btnViewMonth" onclick="setView('month')" class="px-3 py-2 text-sm font-medium transition-colors border-l border-gray-300" title="Mês atual"><i class="fa-solid fa-calendar mr-1.5"></i>Mês</button>
    </div>
    <!-- Navegação do mês (visível só na view mensal) -->
    <div id="navMes" class="hidden items-center gap-1">
        <button onclick="navMes(-1)" class="px-2 py-2 border border-gray-300 rounded-lg text-sm bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-left"></i></button>
        <span id="labelMes" class="text-sm font-semibold text-gray-700 w-28 text-center"></span>
        <button onclick="navMes(+1)" class="px-2 py-2 border border-gray-300 rounded-lg text-sm bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-right"></i></button>
    </div>
    <button onclick="document.getElementById('modalConfig').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-gear mr-2"></i>Configurar ano</button>
    <?php if ($config): ?>
    <button onclick="document.getElementById('modalEvento').classList.remove('hidden')" class="inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold text-white bg-green-600 hover:bg-green-700"><i class="fa-solid fa-plus mr-2"></i>Adicionar evento</button>
    <?php endif; ?>
</div>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';

if (!($schema_pronto ?? false)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 mb-6">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i> Rode a migration <code>2026_06_25_calendario_letivo.sql</code> no painel Master para habilitar este módulo.
    </div>
<?php endif; ?>

<!-- Cartões de resumo -->
<?php
$counts = ['feriado'=>0,'recesso'=>0,'reposicao'=>0,'evento'=>0,'suspensao'=>0,'avaliacao'=>0];
foreach ($eventos as $ev) {
    $tipo = (string)$ev['tipo'];
    $days = (int)((strtotime($ev['data_fim']) - strtotime($ev['data_inicio'])) / 86400) + 1;
    $counts[$tipo] = ($counts[$tipo] ?? 0) + $days;
}
$diasLetivosMeta = (int)($status['dias_meta'] ?? 200);
?>
<div class="grid grid-cols-2 sm:grid-cols-4 gap-4 mb-6">
    <div class="rounded-xl border p-4 flex items-center gap-3 bg-red-50 border-red-200 text-red-700">
        <i class="fa-solid fa-calendar-xmark text-xl"></i>
        <div>
            <p class="text-xs font-medium opacity-75" id="cardFeriadoLabel">Feriados</p>
            <p class="text-lg font-bold" id="cardFeriadoVal"><?= $counts['feriado'] ?></p>
        </div>
    </div>
    <div class="rounded-xl border p-4 flex items-center gap-3 bg-amber-50 border-amber-200 text-amber-700">
        <i class="fa-solid fa-umbrella-beach text-xl"></i>
        <div>
            <p class="text-xs font-medium opacity-75" id="cardRecessoLabel">Recessos</p>
            <p class="text-lg font-bold" id="cardRecessoVal"><?= $counts['recesso'] ?></p>
        </div>
    </div>
    <div class="rounded-xl border p-4 flex items-center gap-3 bg-green-50 border-green-200 text-green-700">
        <i class="fa-solid fa-rotate-right text-xl"></i>
        <div>
            <p class="text-xs font-medium opacity-75" id="cardReposicaoLabel">Reposições</p>
            <p class="text-lg font-bold" id="cardReposicaoVal"><?= $counts['reposicao'] ?></p>
        </div>
    </div>
    <div class="rounded-xl border p-4 flex items-center gap-3 bg-blue-50 border-blue-200 text-blue-700">
        <i class="fa-solid fa-graduation-cap text-xl"></i>
        <div>
            <p class="text-xs font-medium opacity-75" id="cardDiasLabel">Dias letivos</p>
            <p class="text-lg font-bold" id="cardDiasVal"><?= (int)($status['dias_letivos'] ?? 0) ?>/<?= $diasLetivosMeta ?></p>
        </div>
    </div>
</div>

<!-- Legenda -->
<div class="flex flex-wrap gap-3 mb-5">
    <?php foreach ($tipoLabels as $k => $v): ?>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full <?= $tipoClasses[$k] ?>">
        <span class="w-2 h-2 rounded-full" style="background:<?= $tipoText[$k] ?>"></span>
        <?= $v ?>
    </span>
    <?php endforeach; ?>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">
        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
        Fim de semana
    </span>
</div>

<!-- View mensal (gerada via JS) -->
<div id="viewMes" class="hidden mb-8">
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div id="mesCabecalho" class="px-6 py-4 border-b border-gray-100 flex items-center justify-between" style="background-color: var(--primary-color);">
            <span id="mesTitulo" class="text-base font-bold" style="color: var(--primary-text-color);"></span>
            <span class="text-sm opacity-75" style="color: var(--primary-text-color);"><?= $ano ?></span>
        </div>
        <!-- cabeçalho DOM–SAB -->
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50">
            <?php foreach (['Dom','Seg','Ter','Qua','Qui','Sex','Sáb'] as $d): ?>
            <div class="py-3 text-center text-xs font-semibold text-gray-500 uppercase tracking-wide"><?= $d ?></div>
            <?php endforeach; ?>
        </div>
        <!-- células geradas por JS -->
        <div id="mesGrid" class="grid grid-cols-7 divide-x divide-y divide-gray-100"></div>
    </div>
    <!-- Lista de eventos do mês -->
    <div id="mesEventosList" class="mt-4 space-y-2"></div>
</div>

<!-- Grade dos 12 meses -->
<div id="viewAno" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8">
<?php for ($mes = 1; $mes <= 12; $mes++):
    $primeiroDia  = mktime(0, 0, 0, $mes, 1, $ano);
    $diasNoMes    = (int) date('t', $primeiroDia);
    $inicioSemana = (int) date('N', $primeiroDia); // 1=seg .. 7=dom
    // Ajusta para começar na domingo (0)
    $offset = $inicioSemana % 7; // dom=0,seg=1..sab=6
?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between" style="background-color: var(--primary-color);">
        <span class="text-sm font-semibold" style="color: var(--primary-text-color);"><?= $mesesNomes[$mes-1] ?></span>
        <span class="text-xs opacity-75" style="color: var(--primary-text-color);"><?= $ano ?></span>
    </div>
    <div class="p-3">
        <!-- Cabeçalho dias da semana -->
        <div class="grid grid-cols-7 mb-1">
            <?php foreach (['D','S','T','Q','Q','S','S'] as $dow): ?>
            <div class="text-center text-xs font-medium text-gray-400 py-1"><?= $dow ?></div>
            <?php endforeach; ?>
        </div>
        <!-- Dias -->
        <div class="grid grid-cols-7 gap-0.5">
            <?php
            // Células vazias antes do primeiro dia
            for ($i = 0; $i < $offset; $i++): ?>
            <div></div>
            <?php endfor;
            for ($dia = 1; $dia <= $diasNoMes; $dia++):
                $dateKey  = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                $timestamp = mktime(0,0,0,$mes,$dia,$ano);
                $dow = (int) date('N', $timestamp); // 1=seg..7=dom
                $isWeekend = $dow >= 6;
                $evsDia = $eventMap[$dateKey] ?? [];
                $tiposDia = array_unique(array_column($evsDia, 'tipo'));
                $primTipo = $tiposDia[0] ?? null;
                $isToday = ($dateKey === date('Y-m-d'));

                // Cores do dia
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

                // Data para modal: payload JSON com eventos do dia
                $evPayload = '';
                if (!empty($evsDia)) {
                    $evPayload = htmlspecialchars(json_encode([
                        'date'   => date('d/m/Y', $timestamp),
                        'events' => array_map(fn($e) => [
                            'id'           => (int) $e['id'],
                            'tipo'         => $e['tipo'],
                            'label'        => $tipoLabels[$e['tipo']] ?? $e['tipo'],
                            'descricao'    => $e['descricao'],
                            'inicio'       => date('d/m/Y', strtotime($e['data_inicio'])),
                            'fim'          => date('d/m/Y', strtotime($e['data_fim'])),
                            'link_reuniao' => $e['link_reuniao'] ?? '',
                            'local_evento' => $e['local_evento'] ?? '',
                        ], array_values($evsDia)),
                    ]), ENT_QUOTES);
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
</div><!-- /viewAno -->

<!-- Lista de eventos -->
<?php if ($config && !empty($eventos)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="font-semibold text-gray-900">Eventos cadastrados</h3>
    </div>
    <div class="divide-y divide-gray-100">
    <?php foreach ($eventos as $ev):
        $t = (string) $ev['tipo'];
        $dtI = date('d/m/Y', strtotime((string) $ev['data_inicio']));
        $dtF = date('d/m/Y', strtotime((string) $ev['data_fim']));
        $periodo = ($ev['data_inicio'] === $ev['data_fim']) ? $dtI : "$dtI – $dtF";
    ?>
        <div class="flex items-center gap-4 px-6 py-3 hover:bg-gray-50">
            <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= $tipoText[$t] ?? '#6b7280' ?>"></span>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) $ev['descricao']) ?></p>
                <p class="text-xs text-gray-500"><?= $periodo ?></p>
            </div>
            <span class="text-xs font-semibold px-2 py-1 rounded-full <?= $tipoClasses[$t] ?? 'bg-gray-100 text-gray-600' ?>"><?= htmlspecialchars($tipoLabels[$t] ?? $t) ?></span>
            <form method="post" action="<?= URL ?>/admin/calendario-letivo/excluir-evento" onsubmit="return confirm('Remover este evento?');" class="inline">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="ano" value="<?= $ano ?>">
                <input type="hidden" name="id" value="<?= (int) $ev['id'] ?>">
                <button type="submit" class="text-xs text-red-500 hover:text-red-700 font-medium"><i class="fa-solid fa-trash-can"></i></button>
            </form>
        </div>
    <?php endforeach; ?>
    </div>
</div>
<?php elseif ($config): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-10 text-center text-gray-500 mb-6">
    <i class="fa-regular fa-calendar-days text-3xl text-gray-300 mb-3 block"></i>
    Nenhum evento cadastrado para <?= $ano ?>. Use o botão "Adicionar evento" para incluir feriados, recessos e reposições.
</div>
<?php endif; ?>

<!-- Modal: Configurar ano -->
<div id="modalConfig" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-lg mx-4 p-6">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-lg font-bold text-gray-900">Configurar ano letivo</h3>
            <button onclick="document.getElementById('modalConfig').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="post" action="<?= URL ?>/admin/calendario-letivo/salvar-ano" class="space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Ano</label>
                    <input type="number" name="ano" value="<?= $ano ?>" min="2000" max="2100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Dias letivos (meta)</label>
                    <input type="number" name="dias_meta" value="<?= (int) ($config['dias_meta'] ?? 200) ?>" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Carga horária (meta)</label>
                    <input type="number" name="carga_horaria_meta" value="<?= (int) ($config['carga_horaria_meta'] ?? 800) ?>" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Observação</label>
                <input type="text" name="observacao" value="<?= htmlspecialchars((string) ($config['observacao'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <?php if ($status): ?>
            <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm text-gray-600">Dias letivos previstos</span>
                    <span class="text-sm font-bold <?= $pct >= 100 ? 'text-green-600' : ($pct >= 90 ? 'text-amber-600' : 'text-red-600') ?>"><?= (int) $status['dias_letivos'] ?>/<?= (int) $status['dias_meta'] ?></span>
                </div>
                <div class="w-full h-2 rounded-full bg-gray-200 overflow-hidden">
                    <div class="h-full <?= $pct >= 100 ? 'bg-green-500' : ($pct >= 90 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width:<?= min(100, (float)$pct) ?>%"></div>
                </div>
            </div>
            <?php endif; ?>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalConfig').classList.add('hidden')" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Detalhe do dia -->
<div id="modalDia" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4">
        <div id="modalDiaHeader" class="flex items-center justify-between px-6 py-4 rounded-t-2xl">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wider opacity-75" id="modalDiaTipoLabel"></p>
                <h3 class="text-lg font-bold" id="modalDiaData"></h3>
            </div>
            <button onclick="closeDayModal()" class="opacity-75 hover:opacity-100 transition-opacity"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <div id="modalDiaBody" class="px-6 pb-6 space-y-3 max-h-80 overflow-y-auto"></div>
        <div class="px-6 pb-5">
            <button onclick="closeDayModal(); document.getElementById('modalEvento').classList.remove('hidden');" class="w-full px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors"><i class="fa-solid fa-plus mr-2"></i>Adicionar evento neste dia</button>
        </div>
    </div>
</div>

<!-- Modal: Adicionar evento -->
<div id="modalEvento" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 hidden">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-xl mx-4 max-h-[90vh] overflow-y-auto">
        <div class="sticky top-0 bg-white flex items-center justify-between px-6 py-4 border-b border-gray-100 rounded-t-2xl">
            <h3 class="text-lg font-bold text-gray-900">Adicionar evento</h3>
            <button onclick="document.getElementById('modalEvento').classList.add('hidden')" class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-xmark text-xl"></i></button>
        </div>
        <form method="post" action="<?= URL ?>/admin/calendario-letivo/salvar-evento" class="p-6 space-y-4">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="ano" value="<?= $ano ?>">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Início <span class="text-red-500">*</span></label>
                    <input type="date" name="data_inicio" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Fim</label>
                    <input type="date" name="data_fim" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                <div class="grid grid-cols-3 gap-2">
                    <?php foreach ($tipoLabels as $k => $v): ?>
                    <label class="cursor-pointer">
                        <input type="radio" name="tipo" value="<?= $k ?>" class="sr-only peer" <?= $k === 'feriado' ? 'checked' : '' ?>>
                        <div class="text-center text-xs font-semibold px-2 py-2 rounded-lg border-2 border-transparent peer-checked:border-green-500 <?= $tipoClasses[$k] ?> hover:opacity-90 transition-all"><?= $v ?></div>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Descrição <span class="text-red-500">*</span></label>
                <input type="text" name="descricao" required placeholder="Ex: Feriado Nacional - Tiradentes" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5"><i class="fa-solid fa-location-dot mr-1 text-gray-400"></i>Local</label>
                    <input type="text" name="local_evento" placeholder="Ex: Ginásio, Sala 3..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1.5"><i class="fa-solid fa-link mr-1 text-gray-400"></i>Link (reunião / meet)</label>
                    <input type="url" name="link_reuniao" placeholder="https://meet.google.com/..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 text-sm">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2"><i class="fa-solid fa-eye mr-1 text-gray-400"></i>Visível para</label>
                <div class="flex flex-wrap gap-3">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="visivel_aluno" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Alunos</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="visivel_professor" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Professores</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" name="visivel_pais" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Pais / Responsáveis</span>
                    </label>
                </div>
            </div>
            <div class="flex gap-3 pt-2">
                <button type="button" onclick="document.getElementById('modalEvento').classList.add('hidden')" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="flex-1 px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700"><i class="fa-solid fa-plus mr-2"></i>Adicionar</button>
            </div>
        </form>
    </div>
</div>

<script>
// ── View anual / mensal ───────────────────────────────────────────────────────
var MESES_NOMES = <?= json_encode($mesesNomes) ?>;
var currentView = 'year';
var currentMes  = <?= $mesAtual ?>;  // 1-based

// Contagens anuais para restaurar ao voltar à view de ano
var countsAno = <?php
    echo json_encode([
        'feriado'   => $counts['feriado'],
        'recesso'   => $counts['recesso'],
        'reposicao' => $counts['reposicao'],
        'avaliacao' => $counts['avaliacao'] ?? 0,
        'diasLetivos' => (int)($status['dias_letivos'] ?? 0),
        'diasMeta'    => $diasLetivosMeta,
    ]);
?>;

function updateCards(mes) {
    // mes = null → ano inteiro; mes = 1..12 → filtrar por mês
    var f = 0, r = 0, rep = 0, av = 0;
    Object.keys(eventData).forEach(function(k) {
        // k = 'YYYY-MM-DD'
        var kMes = parseInt(k.substring(5, 7), 10);
        if (mes !== null && kMes !== mes) return;
        eventData[k].forEach(function(ev) {
            if (ev.tipo === 'feriado')   f++;
            if (ev.tipo === 'recesso')   r++;
            if (ev.tipo === 'reposicao') rep++;
            if (ev.tipo === 'avaliacao') av++;
        });
    });
    // Dias contados por data única (cada data conta 1 dia)
    var suffix = mes !== null ? ' — ' + MESES_NOMES[mes - 1] : '';
    document.getElementById('cardFeriadoLabel').textContent  = 'Feriados' + suffix;
    document.getElementById('cardRecessoLabel').textContent  = 'Recessos' + suffix;
    document.getElementById('cardReposicaoLabel').textContent = 'Reposições' + suffix;
    document.getElementById('cardFeriadoVal').textContent   = f;
    document.getElementById('cardRecessoVal').textContent   = r;
    document.getElementById('cardReposicaoVal').textContent = rep;
    if (mes !== null) {
        document.getElementById('cardDiasLabel').textContent = 'Avaliações' + suffix;
        document.getElementById('cardDiasVal').textContent   = av;
    } else {
        document.getElementById('cardDiasLabel').textContent = 'Dias letivos';
        document.getElementById('cardDiasVal').textContent   = countsAno.diasLetivos + '/' + countsAno.diasMeta;
    }
}

function setView(v) {
    currentView = v;
    var btnY = document.getElementById('btnViewYear');
    var btnM = document.getElementById('btnViewMonth');
    var navM = document.getElementById('navMes');
    var vAno = document.getElementById('viewAno');
    var vMes = document.getElementById('viewMes');
    if (v === 'year') {
        btnY.classList.add('bg-gray-100','font-semibold');
        btnM.classList.remove('bg-gray-100','font-semibold');
        navM.classList.add('hidden'); navM.classList.remove('flex');
        vAno.classList.remove('hidden');
        vMes.classList.add('hidden');
        updateCards(null);
    } else {
        btnM.classList.add('bg-gray-100','font-semibold');
        btnY.classList.remove('bg-gray-100','font-semibold');
        navM.classList.remove('hidden'); navM.classList.add('flex');
        vAno.classList.add('hidden');
        vMes.classList.remove('hidden');
        renderMes(currentMes);
        updateCards(currentMes);
    }
}

function navMes(delta) {
    currentMes += delta;
    if (currentMes < 1)  currentMes = 12;
    if (currentMes > 12) currentMes = 1;
    renderMes(currentMes);
    updateCards(currentMes);
}

function renderMes(mes) {
    document.getElementById('labelMes').textContent = MESES_NOMES[mes - 1];
    document.getElementById('mesTitulo').textContent = MESES_NOMES[mes - 1];

    var ano = anoAtual;
    var grid = document.getElementById('mesGrid');
    grid.innerHTML = '';

    var firstDay = new Date(ano, mes - 1, 1);
    var daysInMonth = new Date(ano, mes, 0).getDate();
    var offset = firstDay.getDay(); // 0=dom

    // Células vazias
    for (var i = 0; i < offset; i++) {
        var empty = document.createElement('div');
        empty.className = 'min-h-[80px] bg-gray-50/50';
        grid.appendChild(empty);
    }

    var today = new Date();
    today.setHours(0,0,0,0);

    for (var d = 1; d <= daysInMonth; d++) {
        var dt = new Date(ano, mes - 1, d);
        var dow = dt.getDay();
        var isWeekend = (dow === 0 || dow === 6);
        var key = ano + '-' + String(mes).padStart(2,'0') + '-' + String(d).padStart(2,'0');
        var evs = eventData[key] || [];
        var isToday = (dt.getTime() === today.getTime());

        var cell = document.createElement('div');
        cell.className = 'min-h-[80px] p-1.5 flex flex-col ' + (isWeekend ? 'bg-gray-50' : 'bg-white');
        if (isToday) cell.classList.add('ring-2','ring-inset','ring-blue-400');

        var numDiv = document.createElement('div');
        numDiv.className = 'text-right mb-1';
        var numSpan = document.createElement('span');
        numSpan.className = 'text-xs font-semibold inline-flex items-center justify-center w-6 h-6 rounded-full ' +
            (isToday ? 'bg-blue-500 text-white' : (isWeekend ? 'text-gray-400' : 'text-gray-700'));
        numSpan.textContent = d;
        numDiv.appendChild(numSpan);
        cell.appendChild(numDiv);

        // Chips de eventos
        evs.forEach(function(ev) {
            var chip = document.createElement('div');
            chip.className = 'text-xs rounded px-1 py-0.5 mb-0.5 truncate cursor-pointer font-medium';
            chip.style.backgroundColor = tipoBg[ev.tipo] || '#f3f4f6';
            chip.style.color = tipoText[ev.tipo] || '#111';
            chip.textContent = ev.descricao;
            chip.onclick = function(e) {
                e.stopPropagation();
                var payload = JSON.stringify({
                    date: String(d).padStart(2,'0') + '/' + String(mes).padStart(2,'0') + '/' + ano,
                    events: evs
                });
                openDayModal(payload);
            };
            cell.appendChild(chip);
        });

        if (evs.length > 0) {
            cell.style.cursor = 'pointer';
            cell.addEventListener('click', (function(evsCopy, dCopy) {
                return function() {
                    var payload = JSON.stringify({
                        date: String(dCopy).padStart(2,'0') + '/' + String(mes).padStart(2,'0') + '/' + ano,
                        events: evsCopy
                    });
                    openDayModal(payload);
                };
            })(evs, d));
        }

        grid.appendChild(cell);
    }

    // Lista de eventos do mês
    var list = document.getElementById('mesEventosList');
    list.innerHTML = '';
    var mesKey = String(mes).padStart(2,'0');
    var mesEvs = [];
    Object.keys(eventData).forEach(function(k) {
        if (k.startsWith(ano + '-' + mesKey + '-')) {
            eventData[k].forEach(function(ev) {
                if (!mesEvs.find(function(e){ return e.id === ev.id; })) mesEvs.push(ev);
            });
        }
    });
    if (mesEvs.length === 0) return;

    var header = document.createElement('h4');
    header.className = 'text-sm font-semibold text-gray-700 mb-2';
    header.textContent = 'Eventos de ' + MESES_NOMES[mes-1];
    list.appendChild(header);

    mesEvs.forEach(function(ev) {
        var dtI = ev.inicio, dtF = ev.fim;
        var div = document.createElement('div');
        div.className = 'flex items-center gap-3 px-4 py-2.5 rounded-xl border text-sm';
        div.style.backgroundColor = tipoBg[ev.tipo] || '#f9fafb';
        div.style.borderColor = (tipoText[ev.tipo] || '#6b7280') + '33';
        div.innerHTML =
            '<span class="w-2 h-2 rounded-full flex-shrink-0" style="background:' + (tipoText[ev.tipo]||'#6b7280') + '"></span>' +
            '<div class="flex-1"><p class="font-medium" style="color:' + (tipoText[ev.tipo]||'#111') + '">' + escHtml(ev.descricao) + '</p>' +
            '<p class="text-xs opacity-75" style="color:' + (tipoText[ev.tipo]||'#666') + '">' + escHtml(ev.label) + ' · ' + (dtI === dtF ? dtI : dtI + ' – ' + dtF) + '</p></div>';
        list.appendChild(div);
    });
}

// Pré-computa eventData para JS (mapa dateKey → eventos)
var eventData = <?php
    $jsMap = [];
    foreach ($eventMap as $k => $evs) {
        $jsMap[$k] = array_map(fn($e) => [
            'id'           => (int) $e['id'],
            'tipo'         => $e['tipo'],
            'label'        => $tipoLabels[$e['tipo']] ?? $e['tipo'],
            'descricao'    => $e['descricao'],
            'inicio'       => date('d/m/Y', strtotime($e['data_inicio'])),
            'fim'          => date('d/m/Y', strtotime($e['data_fim'])),
            'link_reuniao' => $e['link_reuniao'] ?? '',
            'local_evento' => $e['local_evento'] ?? '',
        ], array_values($evs));
    }
    echo json_encode($jsMap);
?>;

// Inicializa toggle
setView('year');

var tipoBg   = <?= json_encode($tipoBg) ?>;
var tipoText = <?= json_encode($tipoText) ?>;
var tipoClasses = <?= json_encode($tipoClasses) ?>;
var csrfToken = <?= json_encode($csrf_token ?? '') ?>;
var anoAtual  = <?= $ano ?>;
var baseUrl   = <?= json_encode(defined('URL') ? URL : '') ?>;

function openDayModal(payloadStr) {
    var data = JSON.parse(payloadStr);
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
        div.className = 'flex items-start gap-3 p-3 rounded-xl border';
        div.style.backgroundColor = tipoBg[ev.tipo] || '#f9fafb';
        div.style.borderColor = tipoText[ev.tipo] + '33' || '#e5e7eb';
        var extras = '';
        if (ev.local_evento) extras += '<span class="inline-flex items-center gap-1 mr-2"><i class="fa-solid fa-location-dot text-xs"></i>' + escHtml(ev.local_evento) + '</span>';
        if (ev.link_reuniao) extras += '<a href="' + escHtml(ev.link_reuniao) + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1 underline hover:opacity-80"><i class="fa-solid fa-link text-xs"></i>Acessar link</a>';
        div.innerHTML =
            '<div class="flex-1 min-w-0">' +
                '<p class="text-sm font-semibold" style="color:' + (tipoText[ev.tipo]||'#111') + '">' + escHtml(ev.descricao) + '</p>' +
                '<p class="text-xs mt-0.5" style="color:' + (tipoText[ev.tipo]||'#666') + '; opacity:0.75">' + escHtml(ev.label) + ' · ' + escHtml(periodo) + '</p>' +
                (extras ? '<p class="text-xs mt-1" style="color:' + (tipoText[ev.tipo]||'#666') + '">' + extras + '</p>' : '') +
            '</div>' +
            '<form method="post" action="' + baseUrl + '/admin/calendario-letivo/excluir-evento" onsubmit="return confirm(\'Remover este evento?\')">' +
                '<input type="hidden" name="csrf_token" value="' + escHtml(csrfToken) + '">' +
                '<input type="hidden" name="ano" value="' + anoAtual + '">' +
                '<input type="hidden" name="id" value="' + ev.id + '">' +
                '<button type="submit" class="text-xs font-medium px-2 py-1 rounded-lg hover:opacity-80 transition-opacity" style="background:' + (tipoText[ev.tipo]||'#ef4444') + '; color:#fff;"><i class="fa-solid fa-trash-can mr-1"></i>Remover</button>' +
            '</form>';
        body.appendChild(div);
    });

    // Preenche a data de início do modal de evento com o dia clicado
    var parts = data.date.split('/');
    var isoDate = parts[2] + '-' + parts[1] + '-' + parts[0];
    var inpInicio = document.querySelector('#modalEvento input[name="data_inicio"]');
    var inpFim    = document.querySelector('#modalEvento input[name="data_fim"]');
    if (inpInicio) inpInicio.value = isoDate;
    if (inpFim)    inpFim.value    = isoDate;

    document.getElementById('modalDia').classList.remove('hidden');
}

function closeDayModal() {
    document.getElementById('modalDia').classList.add('hidden');
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// Fechar modais com Esc
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        document.getElementById('modalConfig').classList.add('hidden');
        document.getElementById('modalEvento').classList.add('hidden');
        closeDayModal();
    }
});
// Clique fora do modal fecha
['modalConfig','modalEvento','modalDia'].forEach(function(id) {
    document.getElementById(id).addEventListener('click', function(e) {
        if (e.target === this) this.classList.add('hidden');
    });
});
// Sincroniza data_fim com data_inicio quando data_fim está vazio
document.querySelector('#modalEvento input[name="data_inicio"]').addEventListener('change', function() {
    var fim = document.querySelector('#modalEvento input[name="data_fim"]');
    if (!fim.value) fim.value = this.value;
});
// Abre modal de config se não tem config ainda
<?php if (!$config): ?>
document.getElementById('modalConfig').classList.remove('hidden');
<?php endif; ?>
</script>
