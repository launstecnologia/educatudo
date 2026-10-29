<?php
$config  = $config  ?? null;
$eventos = $eventos ?? [];
$status  = $status  ?? null;
$ano     = (int) ($ano ?? date('Y'));

$tipoLabels = $tipoLabels ?? [];
$tipoBg     = $tipoBg ?? [];
$tipoText   = $tipoText ?? [];
$tipos      = $tipos ?? [];
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
$podeCadastrarTipo = !empty($pode_cadastrar_tipo);
$podeExcluirTipo = !empty($pode_excluir_tipo);
$podeAlterarEvento = !empty($pode_alterar_evento);
$dataCalendario = static function ($raw): ?int {
    $ts = strtotime((string) $raw);
    if ($ts === false) {
        return null;
    }
    $ano = (int) date('Y', $ts);
    if ($ano < 2000 || $ano > 2100) {
        return null;
    }
    return $ts;
};
$gradesProvas = is_array($grades_provas ?? null) ? $grades_provas : [];
$eventoParaJs = static function (array $e) use ($tipoLabels, $dataCalendario, $gradesProvas): array {
    $ini = $dataCalendario($e['data_inicio'] ?? '');
    $fim = $dataCalendario($e['data_fim'] ?? '');
    $provas = $gradesProvas[(int) ($e['id'] ?? 0)] ?? [];
    return [
        'id' => (int) $e['id'],
        'tipo' => $e['tipo'],
        'label' => $tipoLabels[$e['tipo']] ?? $e['tipo'],
        'descricao' => $e['descricao'],
        'inicio' => $ini ? date('d/m/Y', $ini) : 'Data inválida',
        'fim' => $fim ? date('d/m/Y', $fim) : 'Data inválida',
        'data_inicio' => $ini ? date('Y-m-d', $ini) : '',
        'data_fim' => $fim ? date('Y-m-d', $fim) : '',
        'link_reuniao' => $e['link_reuniao'] ?? '',
        'local_evento' => $e['local_evento'] ?? '',
        'visivel_aluno' => (int) ($e['visivel_aluno'] ?? 0),
        'visivel_professor' => (int) ($e['visivel_professor'] ?? 0),
        'visivel_pais' => (int) ($e['visivel_pais'] ?? 0),
        'provas' => is_array($provas) ? array_values($provas) : [],
    ];
};
$coresTipoPreset = ['#0d9488', '#db2777', '#ea580c', '#0891b2', '#65a30d', '#e11d48', '#7c3aed', '#ca8a04'];
$jsonJs = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$pct  = $status['percentual'] ?? null;
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$variosPronto = !empty($varios_pronto);
$calendarios = $calendarios ?? [];
$cursosSeries = $cursos_series ?? [];
$calendarioId = (int) ($config['id'] ?? 0);
$cursoIdsSel = array_fill_keys(array_map('intval', (array) ($config['curso_ids'] ?? [])), true);
$serieIdsSel = array_fill_keys(array_map('intval', (array) ($config['serie_ids'] ?? [])), true);
$nomeCal = trim((string) ($config['nome'] ?? ''));
$rotuloCal = trim((string) ($config['rotulo'] ?? ''));
$corCal = strtolower(trim((string) ($config['cor'] ?? '')));
$textoCal = strtolower(trim((string) ($config['cor_texto'] ?? '')));
if (!preg_match('/^#[0-9a-f]{6}$/', $corCal) || !preg_match('/^#[0-9a-f]{6}$/', $textoCal)) {
    $corCal = '';
    $textoCal = '';
}
$tomCal = $corCal !== '' ? '--button-primary-color:' . $corCal . ';--primary-text-color:' . $textoCal . ';' : '';

// Monta mapa de datas com eventos: ['2026-04-21'] = [['tipo'=>..,'descricao'=>..], ...]
$eventMap = [];
foreach ($eventos as $ev) {
    $start = $dataCalendario($ev['data_inicio'] ?? '');
    $end   = $dataCalendario($ev['data_fim'] ?? '');
    if ($start === null || $end === null || $end < $start) {
        continue;
    }
    if (($end - $start) > 370 * 86400) {
        continue;
    }
    for ($d = $start; $d <= $end; $d += 86400) {
        $key = date('Y-m-d', $d);
        $eventMap[$key][] = $ev;
    }
}

$mesesNomes = ['Janeiro','Fevereiro','Março','Abril','Maio','Junho','Julho','Agosto','Setembro','Outubro','Novembro','Dezembro'];
$mesAtual   = (int) date('m'); // mês atual para view mensal
$page_header_title    = 'Calendário Letivo ' . $ano . ($variosPronto && $nomeCal !== '' ? ' · ' . $nomeCal : '');
$page_header_subtitle = $rotuloCal !== ''
    ? $rotuloCal . '. Feriados, recessos, reposições e eventos deste calendário.'
    : 'Visualize feriados, recessos, reposições e eventos do ano letivo.';

ob_start();
?>
<div class="flex flex-wrap items-center gap-2">
    <a href="?ano=<?= $ano - 1 ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-left"></i></a>
    <span class="text-sm font-semibold text-gray-700"><?= $ano ?></span>
    <a href="?ano=<?= $ano + 1 ?>" class="inline-flex items-center px-3 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-chevron-right"></i></a>
    <?php if ($variosPronto): ?>
    <div class="inline-flex shrink-0 items-center rounded-lg border border-gray-300 bg-white"<?= $corCal !== '' ? ' style="border-color:' . htmlspecialchars($corCal, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
        <?php if ($corCal !== ''): ?>
        <span class="ml-3 h-2.5 w-2.5 shrink-0 rounded-full" style="background:<?= htmlspecialchars($corCal, ENT_QUOTES, 'UTF-8') ?>"></span>
        <?php endif; ?>
        <select id="selCalendario" aria-label="Calendário" onchange="if (this.value) window.location = this.value;"
                class="max-w-[16rem] cursor-pointer border-0 bg-transparent py-2 <?= $corCal !== '' ? 'pl-2' : 'pl-3' ?> pr-9 text-sm text-gray-700 focus:outline-none focus:ring-0">
            <?php foreach ($calendarios as $cal): ?>
            <option value="?ano=<?= $ano ?>&id=<?= (int) $cal['id'] ?>" <?= (int) $cal['id'] === $calendarioId ? 'selected' : '' ?>><?= htmlspecialchars((string) ($cal['nome'] ?? 'Geral')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="button" onclick="openNovoCalendario()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
        <i class="fa-solid fa-plus mr-2"></i>Novo calendário
    </button>
    <?php endif; ?>
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
    <button type="button" onclick="openConfigDrawer()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-gear mr-2"></i>Configurar ano</button>
    <?php if ($config): ?>
    <button type="button" onclick="openEventosDrawer()" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-700 bg-white hover:bg-gray-50">
        <i class="fa-solid fa-list-ul mr-2"></i>Eventos
        <span class="ml-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-gray-100 px-1.5 text-xs font-semibold text-gray-600"><?= count($eventos) ?></span>
    </button>
    <button type="button" onclick="openEventoDrawer()" class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90"><i class="fa-solid fa-plus mr-2"></i>Adicionar evento</button>
    <?php endif; ?>
</div>
<?php
$page_header_actions = ob_get_clean();
include \App\Core\ViewPaths::adminPartial('page_header_list.php');

if (!($schema_pronto ?? false)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 mb-6">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i> Rode a migration <code>2026_06_25_calendario_letivo.sql</code> no painel Master para habilitar este módulo.
    </div>
<?php elseif (!$variosPronto): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 mb-6">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i> Para cadastrar um calendário por curso ou série, rode a migration <code>2026_10_01_calendario_letivo_varios.sql</code> no painel Master.
    </div>
<?php endif; ?>
<?php
$flash_status = (string) ($flash_type ?? '');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<!-- Cartões de resumo -->
<?php
$counts = ['feriado'=>0,'recesso'=>0,'reposicao'=>0,'evento'=>0,'suspensao'=>0,'avaliacao'=>0];
foreach ($eventos as $ev) {
    $tipo = (string)$ev['tipo'];
    $iniDias = $dataCalendario($ev['data_inicio'] ?? '');
    $fimDias = $dataCalendario($ev['data_fim'] ?? '');
    $days = 1;
    if ($iniDias !== null && $fimDias !== null && $fimDias >= $iniDias && ($fimDias - $iniDias) <= 370 * 86400) {
        $days = (int) (($fimDias - $iniDias) / 86400) + 1;
    }
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
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full" data-tipo-slug="<?= htmlspecialchars($k) ?>" style="background:<?= htmlspecialchars($tipoBg[$k] ?? '#f3f4f6') ?>;color:<?= htmlspecialchars($tipoText[$k] ?? '#374151') ?>">
        <span class="w-2 h-2 rounded-full" style="background:<?= htmlspecialchars($tipoText[$k] ?? '#6b7280') ?>"></span>
        <?= htmlspecialchars((string) $v) ?>
    </span>
    <?php endforeach; ?>
    <span class="inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">
        <span class="w-2 h-2 rounded-full bg-gray-400"></span>
        Fim de semana
    </span>
</div>

<!-- View mensal (gerada via JS) -->
<div id="viewMes" class="hidden mb-8"<?= $tomCal !== '' ? ' style="' . htmlspecialchars($tomCal, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div id="mesCabecalho" class="px-6 py-4 border-b border-gray-100 flex items-center justify-between" style="background-color: var(--button-primary-color);">
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
<div id="viewAno" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 mb-8"<?= $tomCal !== '' ? ' style="' . htmlspecialchars($tomCal, ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
<?php for ($mes = 1; $mes <= 12; $mes++):
    $primeiroDia  = mktime(0, 0, 0, $mes, 1, $ano);
    $diasNoMes    = (int) date('t', $primeiroDia);
    $inicioSemana = (int) date('N', $primeiroDia); // 1=seg .. 7=dom
    // Ajusta para começar na domingo (0)
    $offset = $inicioSemana % 7; // dom=0,seg=1..sab=6
?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-100 flex items-center justify-between" style="background-color: var(--button-primary-color);">
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
                        'events' => array_map($eventoParaJs, array_values($evsDia)),
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
</div><!-- /viewAno -->

<?php if ($config): ?>
<div id="eventosDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeEventosDrawer()"></div>
<aside id="eventosDrawer"
       class="fixed top-0 right-0 z-50 flex h-full w-full max-w-3xl translate-x-full flex-col bg-white shadow-2xl transition-transform duration-300 ease-in-out"
       aria-hidden="true">
    <div class="flex items-center justify-between border-b border-gray-200 px-6 py-5 sm:px-8">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Eventos cadastrados</h2>
            <p class="mt-0.5 text-sm text-gray-500"><?= count($eventos) ?> neste calendário</p>
        </div>
        <button type="button" onclick="closeEventosDrawer()" class="p-1 text-gray-400 hover:text-gray-600" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto">
        <?php if ($eventos === []): ?>
        <div class="px-6 py-16 text-center text-gray-500">
            <i class="fa-regular fa-calendar-days mb-3 block text-3xl text-gray-300"></i>
            Nenhum evento cadastrado para <?= (int) $ano ?>. Use o botão "Adicionar evento" para incluir feriados, recessos e reposições.
        </div>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Evento</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Período</th>
                        <th class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500">Tipo</th>
                        <th class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500">Ações</th>
                    </tr>
                </thead>
                <tbody id="eventosTabela" class="divide-y divide-gray-200 bg-white">
                <?php foreach ($eventos as $idx => $ev):
                    $t = (string) $ev['tipo'];
                    $tsI = $dataCalendario($ev['data_inicio'] ?? '');
                    $tsF = $dataCalendario($ev['data_fim'] ?? '');
                    $dtI = $tsI ? date('d/m/Y', $tsI) : 'Data inválida';
                    $dtF = $tsF ? date('d/m/Y', $tsF) : 'Data inválida';
                    $periodoTitle = '';
                    if ($tsI === null || $tsF === null || $tsF < $tsI) {
                        $brutoInicio = trim((string) ($ev['data_inicio'] ?? ''));
                        $brutoFim = trim((string) ($ev['data_fim'] ?? ''));
                        $periodo = 'Data inválida';
                        if ($brutoInicio !== '' || $brutoFim !== '') {
                            $periodo .= ' (' . $brutoInicio . ' – ' . $brutoFim . ')';
                        }
                        $periodoTitle = 'O calendário continua disponível; este cadastro antigo não foi alterado.';
                    } else {
                        $periodo = ($ev['data_inicio'] === $ev['data_fim']) ? $dtI : "$dtI – $dtF";
                    }
                    $eventoId = (int) $ev['id'];
                ?>
                    <tr class="hover:bg-gray-50 <?= $idx >= 10 ? 'hidden' : '' ?>" data-evento-linha>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-3">
                                <span class="h-2.5 w-2.5 flex-shrink-0 rounded-full" style="background:<?= htmlspecialchars($tipoText[$t] ?? '#6b7280') ?>"></span>
                                <p class="max-w-xs truncate text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $ev['descricao']) ?></p>
                            </div>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-sm text-gray-500"<?= $periodoTitle !== '' ? ' title="' . htmlspecialchars($periodoTitle) . '"' : '' ?>><?= htmlspecialchars($periodo) ?></td>
                        <td class="whitespace-nowrap px-6 py-4">
                            <span class="rounded-full px-2 py-1 text-xs font-semibold" style="background:<?= htmlspecialchars($tipoBg[$t] ?? '#f3f4f6') ?>;color:<?= htmlspecialchars($tipoText[$t] ?? '#374151') ?>"><?= htmlspecialchars($tipoLabels[$t] ?? $t) ?></span>
                        </td>
                        <td class="whitespace-nowrap px-6 py-4 text-right">
                            <form id="form-excluir-evento-<?= $eventoId ?>" method="post" action="<?= URL ?>/admin/calendario-letivo/excluir-evento" class="hidden">
                                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                                <input type="hidden" name="ano" value="<?= $ano ?>">
                                <input type="hidden" name="calendario_id" value="<?= $calendarioId ?>">
                                <input type="hidden" name="id" value="<?= $eventoId ?>">
                            </form>
                            <?php if ($podeAlterarEvento): ?>
                            <?php ob_start(); ?>
                            <button type="button" onclick="editarEvento(<?= $eventoId ?>)"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen w-4 text-center text-gray-400"></i> Editar
                            </button>
                            <div class="my-1 border-t border-gray-100"></div>
                            <button type="submit" form="form-excluir-evento-<?= $eventoId ?>" onclick="return confirm('Remover este evento?');"
                                    class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can w-4 text-center text-red-400"></i> Excluir
                            </button>
                            <?php
                            $row_actions_dropdown_items = ob_get_clean();
                            $row_actions_dropdown_id = 'evento-acoes-' . $eventoId;
                            include \App\Core\ViewPaths::adminPartial('row_actions_dropdown.php');
                            ?>
                            <?php else: ?>
                            <button type="submit" form="form-excluir-evento-<?= $eventoId ?>" onclick="return confirm('Remover este evento?');"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3 py-1.5 text-sm font-medium text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-xs"></i> Excluir
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($eventos !== []): ?>
    <div id="eventosPaginacao" class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-200 px-6 py-4"></div>
    <?php endif; ?>
</aside>
<?php endif; ?>

<!-- Offcanvas: Configurar ano -->
<div id="configDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeConfigDrawer()"></div>
<aside id="configDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="configDrawerTitle" class="text-xl font-bold text-gray-900">Configurar calendário</h2>
        <button type="button" onclick="closeConfigDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="form-calendario" method="post" action="<?= URL ?>/admin/calendario-letivo/salvar-ano" class="flex flex-col flex-1 overflow-hidden">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="calendario_id" id="calendario_id" value="<?= $calendarioId ?>">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Metas do ano</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <?php if ($variosPronto): ?>
                    <div class="sm:col-span-2">
                        <label for="calendario_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome do calendário *</label>
                        <input type="text" name="nome" id="calendario_nome" required maxlength="120" value="<?= htmlspecialchars($nomeCal !== '' ? $nomeCal : 'Geral') ?>"
                               data-original="<?= htmlspecialchars($nomeCal !== '' ? $nomeCal : 'Geral') ?>"
                               placeholder="Ex.: Fundamental, Ensino Médio, 6º Ano"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="text-xs text-gray-500 mt-1">Use um nome para cada calendário do ano. Marque o curso inteiro ou só as séries que seguem este calendário. Sem marcação, vale para toda a escola.</p>
                    </div>
                    <?php endif; ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Ano</label>
                        <input type="number" name="ano" value="<?= $ano ?>" min="2000" max="2100" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Dias letivos (meta)</label>
                        <input type="number" name="dias_meta" value="<?= (int) ($config['dias_meta'] ?? 200) ?>" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Carga horária (meta)</label>
                        <input type="number" name="carga_horaria_meta" value="<?= (int) ($config['carga_horaria_meta'] ?? 800) ?>" min="0" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Observação</label>
                        <input type="text" name="observacao" value="<?= htmlspecialchars((string) ($config['observacao'] ?? '')) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            </section>
            <?php if ($variosPronto && $cursosSeries !== []): ?>
            <section id="secaoAbrangencia">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Para quem vale</h3>
                <div class="space-y-4">
                    <?php foreach ($cursosSeries as $curso):
                        $cursoMarcado = isset($cursoIdsSel[(int) $curso['id']]);
                    ?>
                    <div class="rounded-lg border border-gray-200 p-3">
                        <label class="flex items-center gap-2 text-sm font-semibold text-gray-900">
                            <input type="checkbox" class="cal-curso rounded border-gray-300" name="curso_ids[]" value="<?= (int) $curso['id'] ?>" data-curso="<?= (int) $curso['id'] ?>" data-original="<?= $cursoMarcado ? '1' : '0' ?>" <?= $cursoMarcado ? 'checked' : '' ?> onchange="sincronizarCursoCalendario(this)">
                            <?= htmlspecialchars((string) $curso['nome']) ?>
                            <span class="font-normal text-gray-500">curso inteiro</span>
                        </label>
                        <?php if (($curso['series'] ?? []) !== []): ?>
                        <div class="mt-2 pl-6 grid grid-cols-1 sm:grid-cols-2 gap-1">
                            <?php foreach ($curso['series'] as $serie):
                                $serieMarcada = $cursoMarcado || isset($serieIdsSel[(int) $serie['id']]);
                            ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" class="cal-serie rounded border-gray-300" name="serie_ids[]" value="<?= (int) $serie['id'] ?>" data-curso="<?= (int) $curso['id'] ?>" data-original="<?= $serieMarcada ? '1' : '0' ?>" data-bloqueada="<?= $cursoMarcado ? '1' : '0' ?>" <?= $serieMarcada ? 'checked' : '' ?> <?= $cursoMarcado ? 'disabled' : '' ?>>
                                <?= htmlspecialchars((string) $serie['nome']) ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </section>
            <?php endif; ?>
            <?php if ($status): ?>
            <section id="secaoSituacaoCalendario">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Situação prevista</h3>
                <div class="rounded-lg bg-gray-50 border border-gray-200 p-4">
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-sm text-gray-600">Dias letivos previstos</span>
                        <span class="text-sm font-bold <?= $pct >= 100 ? 'text-green-600' : ($pct >= 90 ? 'text-amber-600' : 'text-red-600') ?>"><?= (int) $status['dias_letivos'] ?>/<?= (int) $status['dias_meta'] ?></span>
                    </div>
                    <div class="w-full h-2 rounded-full bg-gray-200 overflow-hidden">
                        <div class="h-full <?= $pct >= 100 ? 'bg-green-500' : ($pct >= 90 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width:<?= min(100, (float)$pct) ?>%"></div>
                    </div>
                </div>
            </section>
            <?php endif; ?>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row sm:items-center justify-end gap-3">
            <?php if ($variosPronto && $calendarioId > 0): ?>
            <button type="submit" form="form-excluir-calendario" id="btnExcluirCalendario" onclick="return confirm('Remover este calendário e os eventos dele?');"
                    class="sm:mr-auto px-6 py-2.5 border border-red-200 rounded-lg text-red-700 hover:bg-red-50 transition-colors">Excluir</button>
            <?php endif; ?>
            <button type="button" onclick="closeConfigDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">Salvar</button>
        </div>
    </form>
</aside>
<?php if ($variosPronto && $calendarioId > 0): ?>
<form id="form-excluir-calendario" method="post" action="<?= URL ?>/admin/calendario-letivo/excluir" class="hidden">
    <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
    <input type="hidden" name="ano" value="<?= $ano ?>">
    <input type="hidden" name="calendario_id" value="<?= $calendarioId ?>">
</form>
<?php endif; ?>

<!-- Offcanvas: provas e eventos do dia -->
<div id="diaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeDayModal()"></div>
<aside id="diaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <div>
            <p class="text-xs font-semibold uppercase tracking-wider text-gray-500" id="modalDiaTipoLabel"></p>
            <h2 id="modalDiaData" class="text-xl font-bold text-gray-900"></h2>
        </div>
        <button type="button" onclick="closeDayModal()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div id="modalDiaBody" class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-4"></div>
    <div class="px-6 sm:px-8 py-5 border-t border-gray-200">
        <button type="button" onclick="closeDayModal(); openEventoDrawer({keepDates: true});" class="btn-primary-custom w-full px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-plus mr-2"></i>Adicionar evento neste dia</button>
    </div>
</aside>

<!-- Offcanvas: Adicionar evento -->
<div id="eventoDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeEventoDrawer()"></div>
<aside id="eventoDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="eventoDrawerTitle" class="text-xl font-bold text-gray-900">Adicionar evento</h2>
        <button type="button" onclick="closeEventoDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="evento-form" method="post" action="<?= URL ?>/admin/calendario-letivo/salvar-evento" novalidate class="flex flex-col flex-1 overflow-hidden">
        <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
        <input type="hidden" name="ano" value="<?= $ano ?>">
        <input type="hidden" name="calendario_id" value="<?= $calendarioId ?>">
        <input type="hidden" name="id" id="evento_id" value="">
        <input type="hidden" name="envio_token" id="evento_envio_token" value="">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do evento</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="evento_data_inicio" class="block text-sm font-medium text-gray-700 mb-1">Início <span class="text-red-500">*</span></label>
                        <input type="date" id="evento_data_inicio" name="data_inicio" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p id="erro_evento_data_inicio" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                    </div>
                    <div>
                        <label for="evento_data_fim" class="block text-sm font-medium text-gray-700 mb-1">Fim</label>
                        <input type="date" id="evento_data_fim" name="data_fim" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p id="erro_evento_data_fim" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                        <div id="tipoGrid" class="grid grid-cols-2 sm:grid-cols-3 gap-2">
                            <?php foreach ($tipoLabels as $k => $v):
                                $ehSistema = !isset($tipos[$k]) || (int) ($tipos[$k]['sistema'] ?? 1) === 1;
                            ?>
                            <label class="cursor-pointer relative group" data-tipo-slug="<?= htmlspecialchars($k) ?>">
                                <input type="radio" name="tipo" value="<?= htmlspecialchars($k) ?>" class="sr-only peer" <?= $k === 'feriado' ? 'checked' : '' ?>>
                                <div class="text-center text-xs font-semibold px-2 py-2 rounded-lg border-2 border-transparent peer-checked:border-green-500 hover:opacity-90 transition-all" style="background:<?= htmlspecialchars($tipoBg[$k] ?? '#f3f4f6') ?>;color:<?= htmlspecialchars($tipoText[$k] ?? '#374151') ?>"><?= htmlspecialchars((string) $v) ?></div>
                                <?php if (!$ehSistema && $podeExcluirTipo): ?>
                                <button type="button" class="tipo-excluir absolute -top-1.5 -right-1.5 hidden group-hover:flex w-5 h-5 items-center justify-center rounded-full bg-white border border-gray-300 text-gray-400 hover:text-red-600 hover:border-red-300 shadow-sm" title="Remover tipo" aria-label="Remover tipo <?= htmlspecialchars((string) $v) ?>">
                                    <i class="fa-solid fa-xmark text-[9px]"></i>
                                </button>
                                <?php endif; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($podeCadastrarTipo): ?>
                        <button type="button" id="btnNovoTipo" onclick="toggleNovoTipo(true)" class="mt-2 inline-flex items-center gap-1.5 text-xs font-semibold text-gray-600 hover:text-gray-900 px-2 py-1.5 rounded-lg border border-dashed border-gray-300 hover:border-gray-400 hover:bg-gray-50">
                            <i class="fa-solid fa-plus"></i> Novo tipo
                        </button>
                        <div id="novoTipoPanel" class="hidden mt-3 rounded-xl border border-gray-200 bg-gray-50 p-4 space-y-3">
                            <p class="text-sm font-medium text-gray-800">Cadastrar tipo</p>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label for="novo_tipo_nome" class="block text-xs font-medium text-gray-600 mb-1">Nome</label>
                                    <input type="text" id="novo_tipo_nome" maxlength="80" placeholder="Ex: Férias" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Cor</label>
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <input type="color" id="novo_tipo_cor" value="#0d9488" class="h-9 w-12 p-0.5 border border-gray-300 rounded-lg bg-white cursor-pointer">
                                        <?php foreach ($coresTipoPreset as $hex): ?>
                                        <button type="button" class="w-6 h-6 rounded-full border border-black/10 hover:scale-110 transition-transform" style="background:<?= $hex ?>" data-cor-preset="<?= $hex ?>" title="<?= $hex ?>"></button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <fieldset class="space-y-2">
                                <legend class="text-xs font-medium text-gray-600 mb-1">O que este tipo faz com o dia</legend>
                                <label class="flex items-start gap-2 cursor-pointer select-none">
                                    <input type="radio" name="efeito_novo_tipo" value="nao_letivo" class="mt-0.5" checked>
                                    <span class="text-xs text-gray-600">Descontar dos dias letivos <span class="text-gray-400">(feriado ou recesso, de segunda a sexta)</span></span>
                                </label>
                                <label class="flex items-start gap-2 cursor-pointer select-none">
                                    <input type="radio" name="efeito_novo_tipo" value="reposicao" class="mt-0.5">
                                    <span class="text-xs text-gray-600">Contar sábado e domingo como dia letivo <span class="text-gray-400">(entra na meta de dias)</span></span>
                                </label>
                                <label class="flex items-start gap-2 cursor-pointer select-none">
                                    <input type="radio" name="efeito_novo_tipo" value="neutro" class="mt-0.5">
                                    <span class="text-xs text-gray-600">Só marcar no calendário <span class="text-gray-400">(não altera a conta)</span></span>
                                </label>
                            </fieldset>
                            <p id="novoTipoErro" class="hidden text-xs text-red-600"></p>
                            <div class="flex justify-end gap-2">
                                <button type="button" onclick="toggleNovoTipo(false)" class="px-3 py-1.5 text-xs font-medium border border-gray-300 rounded-lg text-gray-700 bg-white hover:bg-gray-50">Cancelar</button>
                                <button type="button" id="btnSalvarTipo" onclick="salvarNovoTipo()" class="btn-primary-custom px-3 py-1.5 text-xs font-semibold rounded-lg">Salvar tipo</button>
                            </div>
                        </div>
                        <?php endif; ?>
                        <p id="efeitoTipoHint" class="hidden mt-2 text-xs text-gray-600"></p>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="evento_descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição <span class="text-red-500">*</span></label>
                        <input type="text" id="evento_descricao" name="descricao" required maxlength="255" placeholder="Ex: Feriado Nacional - Tiradentes" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p id="erro_evento_descricao" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                    </div>
                    <div>
                        <label for="evento_local" class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-solid fa-location-dot mr-1 text-gray-400"></i>Local</label>
                        <input type="text" id="evento_local" name="local_evento" placeholder="Ex: Ginásio, Sala 3..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                    </div>
                    <div>
                        <label for="evento_link" class="block text-sm font-medium text-gray-700 mb-1"><i class="fa-solid fa-link mr-1 text-gray-400"></i>Link (reunião / meet)</label>
                        <input type="url" id="evento_link" name="link_reuniao" placeholder="https://meet.google.com/..." class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                        <p id="erro_evento_link" class="hidden mt-1 text-xs text-red-600" role="alert"></p>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Visibilidade</h3>
                <p class="text-sm text-gray-600 mb-3">Quem vê este evento no calendário letivo.</p>
                <div class="flex flex-wrap gap-4 mb-5">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="evento_visivel_aluno" name="visivel_aluno" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Alunos</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="evento_visivel_professor" name="visivel_professor" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Professores</span>
                    </label>
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                        <input type="checkbox" id="evento_visivel_pais" name="visivel_pais" value="1" class="w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                        <span class="text-sm text-gray-700">Pais / Responsáveis</span>
                    </label>
                </div>
                <?php if (!empty($pode_publicar_escolar)): ?>
                <label class="flex items-start gap-3 cursor-pointer select-none rounded-lg border border-gray-200 bg-gray-50 p-4 hover:bg-gray-100">
                    <input type="checkbox" id="evento_publicar_escolar" name="publicar_calendario_escolar" value="1" class="mt-0.5 w-4 h-4 text-green-600 rounded border-gray-300 focus:ring-green-500">
                    <span>
                        <span class="block text-sm font-medium text-gray-900">Publicar também no Calendário Escolar</span>
                        <span class="block text-xs text-gray-600 mt-0.5">O evento aparece para os responsáveis no app e gera notificação.</span>
                    </span>
                </label>
                <?php endif; ?>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col gap-3">
            <p id="erro_evento_formulario" tabindex="-1" class="hidden text-sm text-red-600" role="alert"></p>
            <div class="flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeEventoDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" id="eventoSubmitBtn" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>Adicionar
            </button>
            </div>
        </div>
    </form>
</aside>

<script>
// ── View anual / mensal ───────────────────────────────────────────────────────
var MESES_NOMES = <?= json_encode($mesesNomes, $jsonJs) ?>;
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
    ], $jsonJs);
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
        if (podeAlterarEvento) {
            var btnMes = document.createElement('button');
            btnMes.type = 'button';
            btnMes.className = 'text-xs font-medium px-2 py-1 rounded-lg border border-current/20 hover:opacity-80';
            btnMes.style.color = tipoText[ev.tipo] || '#374151';
            btnMes.innerHTML = '<i class="fa-solid fa-pen mr-1"></i>Editar';
            btnMes.addEventListener('click', function () { editarEvento(ev.id); });
            div.appendChild(btnMes);
        }
        list.appendChild(div);
    });
}

// Pré-computa eventData para JS (mapa dateKey → eventos)
var eventData = <?php
    $jsMap = [];
    foreach ($eventMap as $k => $evs) {
        $jsMap[$k] = array_map($eventoParaJs, array_values($evs));
    }
    echo json_encode($jsMap, $jsonJs);
?>;

// Inicializa toggle
setView('year');

var tipoBg   = <?= json_encode($tipoBg, $jsonJs) ?>;
var tipoText = <?= json_encode($tipoText, $jsonJs) ?>;
var tipoLabelsMap = <?= json_encode($tipoLabels, $jsonJs) ?>;
var csrfToken = <?= json_encode($csrf_token ?? '', $jsonJs) ?>;
var anoAtual  = <?= $ano ?>;
var calendarioAtualId = <?= $calendarioId ?>;
var baseUrl   = <?= json_encode(defined('URL') ? URL : '', $jsonJs) ?>;
var podeExcluirTipo = <?= $podeExcluirTipo ? 'true' : 'false' ?>;
var podeAlterarEvento = <?= $podeAlterarEvento ? 'true' : 'false' ?>;
var tipoEfeitos = <?= json_encode(array_map(static fn ($t) => (string) ($t['efeito'] ?? 'neutro'), is_array($tipos) ? $tipos : []), $jsonJs) ?>;
var tipoSistema = <?= json_encode(array_map(static fn ($t) => (int) ($t['sistema'] ?? 1) === 1, is_array($tipos) ? $tipos : []), $jsonJs) ?>;
var eventosPorId = {};
Object.keys(eventData).forEach(function (k) {
    eventData[k].forEach(function (ev) { eventosPorId[ev.id] = ev; });
});

function htmlHorarioProva(prova) {
    var inicio = prova.inicio || '';
    var fim = prova.fim || '';
    if (!inicio && !fim) return 'Horário a definir';
    if (inicio === '00:00' && fim === '23:59') return 'Dia todo';
    if (inicio && fim) return inicio + ' – ' + fim;
    return inicio || fim;
}

function htmlProvasDoDia(ev) {
    var provas = Array.isArray(ev.provas) ? ev.provas : [];
    if (!provas.length) return '';
    return provas.map(function (prova) {
        var materias = Array.isArray(prova.materias) ? prova.materias.filter(Boolean) : [];
        var series = Array.isArray(prova.series) ? prova.series : [];
        var titulo = prova.titulo || '';
        var descricao = ev.descricao || '';
        var rotulo = descricao && titulo && descricao.indexOf(titulo) === -1
            ? descricao + ' — ' + titulo
            : (descricao || titulo || 'Avaliação');
        var seriesHtml = series.map(function (serie) {
            var turmas = Array.isArray(serie.turmas) ? serie.turmas.filter(Boolean) : [];
            return '<div class="py-2">' +
                '<p class="text-sm font-medium text-gray-900">' + escHtml(serie.nome || 'Série') + '</p>' +
                '<p class="text-sm text-gray-600 mt-0.5">' + escHtml(turmas.join(', ') || 'Sem turma') + '</p>' +
            '</div>';
        }).join('');
        return '<article class="rounded-xl border border-gray-200 overflow-hidden">' +
            '<div class="px-4 py-3 bg-gray-50 border-b border-gray-100">' +
                '<p class="text-base font-semibold text-gray-900">' + escHtml(htmlHorarioProva(prova)) + '</p>' +
                '<p class="text-sm text-gray-800 mt-0.5">' + escHtml(rotulo) + '</p>' +
                (materias.length ? '<p class="text-xs text-gray-500 mt-1">' + escHtml(materias.join(' · ')) + '</p>' : '') +
            '</div>' +
            '<div class="px-4 divide-y divide-gray-100">' + seriesHtml + '</div>' +
        '</article>';
    }).join('');
}

function htmlAcoesEvento(ev) {
    var acoes = '';
    if (podeAlterarEvento) {
        acoes += '<button type="button" class="btn-editar-evento text-xs font-medium px-2 py-1 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-pen mr-1"></i>Editar</button>';
    }
    acoes += '<form method="post" action="' + baseUrl + '/admin/calendario-letivo/excluir-evento" onsubmit="return confirm(\'Remover este evento?\')">' +
        '<input type="hidden" name="csrf_token" value="' + escHtml(csrfToken) + '">' +
        '<input type="hidden" name="ano" value="' + anoAtual + '">' +
        '<input type="hidden" name="calendario_id" value="' + calendarioAtualId + '">' +
        '<input type="hidden" name="id" value="' + ev.id + '">' +
        '<button type="submit" class="text-xs font-medium px-2 py-1 rounded-lg bg-red-50 text-red-700 border border-red-200 hover:bg-red-100"><i class="fa-solid fa-trash-can mr-1"></i>Remover</button>' +
    '</form>';
    return '<div class="flex flex-wrap justify-end gap-2">' + acoes + '</div>';
}

function openDayModal(payloadStr) {
    var data = JSON.parse(payloadStr);
    var events = data.events;
    document.getElementById('modalDiaData').textContent = data.date;

    var tipos = [...new Set(events.map(function(e){ return e.label; }))];
    document.getElementById('modalDiaTipoLabel').textContent = tipos.join(' · ');

    var body = document.getElementById('modalDiaBody');
    body.innerHTML = '';
    events.forEach(function(ev) {
        var provasHtml = htmlProvasDoDia(ev);
        var div = document.createElement('section');
        div.className = 'space-y-3';
        if (provasHtml) {
            div.innerHTML = provasHtml + htmlAcoesEvento(ev);
        } else {
            var periodo = ev.inicio === ev.fim ? ev.inicio : ev.inicio + ' – ' + ev.fim;
            var extras = '';
            if (ev.local_evento) extras += '<span class="inline-flex items-center gap-1 mr-2"><i class="fa-solid fa-location-dot text-xs"></i>' + escHtml(ev.local_evento) + '</span>';
            if (ev.link_reuniao) extras += '<a href="' + escHtml(ev.link_reuniao) + '" target="_blank" rel="noopener" class="inline-flex items-center gap-1 underline hover:opacity-80"><i class="fa-solid fa-link text-xs"></i>Acessar link</a>';
            div.innerHTML =
                '<div class="rounded-xl border border-gray-200 p-4 space-y-2">' +
                    '<p class="text-sm font-semibold text-gray-900">' + escHtml(ev.descricao) + '</p>' +
                    '<p class="text-xs text-gray-500">' + escHtml(ev.label) + ' · ' + escHtml(periodo) + '</p>' +
                    (extras ? '<p class="text-xs text-gray-600">' + extras + '</p>' : '') +
                    htmlAcoesEvento(ev) +
                '</div>';
        }
        var btnEditar = div.querySelector('.btn-editar-evento');
        if (btnEditar) {
            btnEditar.addEventListener('click', function () { editarEvento(ev.id); });
        }
        body.appendChild(div);
    });

    // Preenche a data de início do drawer de evento com o dia clicado
    var parts = data.date.split('/');
    var isoDate = parts[2] + '-' + parts[1] + '-' + parts[0];
    var inpInicio = document.getElementById('evento_data_inicio');
    var inpFim    = document.getElementById('evento_data_fim');
    if (inpInicio) inpInicio.value = isoDate;
    if (inpFim)    inpFim.value    = isoDate;

    showDrawer('diaDrawerBackdrop', 'diaDrawer');
}

function closeDayModal() {
    var backdrop = document.getElementById('diaDrawerBackdrop');
    if (!backdrop) return;
    hideDrawer('diaDrawerBackdrop', 'diaDrawer');
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function showDrawer(backdropId, drawerId) {
    document.getElementById(backdropId).classList.remove('hidden');
    var drawer = document.getElementById(drawerId);
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
    document.body.style.overflow = 'hidden';
}
function hideDrawer(backdropId, drawerId) {
    document.getElementById(backdropId).classList.add('hidden');
    var drawer = document.getElementById(drawerId);
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function restaurarFormularioCalendario() {
    var idEl = document.getElementById('calendario_id');
    if (idEl) idEl.value = String(calendarioAtualId || '');
    var nomeEl = document.getElementById('calendario_nome');
    if (nomeEl) nomeEl.value = nomeEl.getAttribute('data-original') || '';
    document.querySelectorAll('.cal-curso').forEach(function (el) {
        el.checked = el.getAttribute('data-original') === '1';
    });
    document.querySelectorAll('.cal-serie').forEach(function (el) {
        el.checked = el.getAttribute('data-original') === '1';
        el.disabled = el.getAttribute('data-bloqueada') === '1';
    });
    var situacao = document.getElementById('secaoSituacaoCalendario');
    if (situacao) situacao.classList.remove('hidden');
    var excluir = document.getElementById('btnExcluirCalendario');
    if (excluir) excluir.classList.remove('hidden');
}

function openConfigDrawer() {
    restaurarFormularioCalendario();
    document.getElementById('configDrawerTitle').textContent = 'Configurar calendário';
    showDrawer('configDrawerBackdrop', 'configDrawer');
}
function closeConfigDrawer() { hideDrawer('configDrawerBackdrop', 'configDrawer'); }

function sincronizarCursoCalendario(cursoEl) {
    var cursoId = cursoEl.getAttribute('data-curso');
    document.querySelectorAll('.cal-serie[data-curso="' + cursoId + '"]').forEach(function (el) {
        el.disabled = cursoEl.checked;
        if (cursoEl.checked) el.checked = true;
    });
}

function openNovoCalendario() {
    var idEl = document.getElementById('calendario_id');
    var nomeEl = document.getElementById('calendario_nome');
    if (idEl) idEl.value = '';
    if (nomeEl) nomeEl.value = '';
    document.querySelectorAll('.cal-curso, .cal-serie').forEach(function (el) {
        el.checked = false;
        el.disabled = false;
    });
    var situacao = document.getElementById('secaoSituacaoCalendario');
    if (situacao) situacao.classList.add('hidden');
    var excluir = document.getElementById('btnExcluirCalendario');
    if (excluir) excluir.classList.add('hidden');
    document.getElementById('configDrawerTitle').textContent = 'Novo calendário';
    showDrawer('configDrawerBackdrop', 'configDrawer');
}

function definirModoEvento(editando) {
    var titulo = document.getElementById('eventoDrawerTitle');
    var btn = document.getElementById('eventoSubmitBtn');
    if (titulo) titulo.textContent = editando ? 'Editar evento' : 'Adicionar evento';
    if (btn) {
        btn.innerHTML = editando
            ? '<i class="fa-solid fa-check mr-2"></i>Salvar'
            : '<i class="fa-solid fa-plus mr-2"></i>Adicionar';
    }
}

function preencherFormEvento(ev) {
    fimSegueAutomatico = false;
    ultimoFimAutomatico = '';
    document.getElementById('evento_id').value = ev.id;
    document.getElementById('evento_data_inicio').value = ev.data_inicio || '';
    document.getElementById('evento_data_fim').value = ev.data_fim || '';
    document.getElementById('evento_descricao').value = ev.descricao || '';
    document.getElementById('evento_local').value = ev.local_evento || '';
    document.getElementById('evento_link').value = ev.link_reuniao || '';
    var radios = document.querySelectorAll('#evento-form input[name="tipo"]');
    var marcado = false;
    radios.forEach(function (radio) {
        radio.checked = radio.value === ev.tipo;
        if (radio.checked) marcado = true;
    });
    if (!marcado && radios.length) radios[0].checked = true;
    document.getElementById('evento_visivel_aluno').checked = Number(ev.visivel_aluno) === 1;
    document.getElementById('evento_visivel_professor').checked = Number(ev.visivel_professor) === 1;
    document.getElementById('evento_visivel_pais').checked = Number(ev.visivel_pais) === 1;
    var pub = document.getElementById('evento_publicar_escolar');
    if (pub) pub.checked = false;
    definirModoEvento(true);
}

function fecharMenusEventos() {
    document.querySelectorAll('#eventosDrawer [data-dropdown-menu]').forEach(function (menu) {
        menu.classList.add('hidden');
    });
}

function editarEvento(id) {
    var ev = eventosPorId[id];
    if (!ev) return;
    closeDayModal();
    fecharMenusEventos();
    var lista = document.getElementById('eventosDrawer');
    if (lista && !lista.classList.contains('translate-x-full')) {
        document.getElementById('eventosDrawerBackdrop').classList.add('hidden');
        lista.classList.add('translate-x-full');
        lista.setAttribute('aria-hidden', 'true');
    }
    openEventoDrawer({ evento: ev });
}

var eventosPaginaAtual = 1;
var EVENTOS_POR_PAGINA = 10;

function openEventosDrawer() {
    renderEventosPagina(eventosPaginaAtual || 1);
    showDrawer('eventosDrawerBackdrop', 'eventosDrawer');
}
function closeEventosDrawer() {
    if (!document.getElementById('eventosDrawer')) return;
    fecharMenusEventos();
    hideDrawer('eventosDrawerBackdrop', 'eventosDrawer');
}

function renderEventosPagina(pagina) {
    var linhas = document.querySelectorAll('#eventosTabela [data-evento-linha]');
    var total = linhas.length;
    var totalPaginas = Math.max(1, Math.ceil(total / EVENTOS_POR_PAGINA));
    pagina = Math.min(Math.max(1, pagina), totalPaginas);
    eventosPaginaAtual = pagina;
    fecharMenusEventos();
    var inicio = (pagina - 1) * EVENTOS_POR_PAGINA;
    var fim = inicio + EVENTOS_POR_PAGINA;
    linhas.forEach(function (linha, i) {
        linha.classList.toggle('hidden', i < inicio || i >= fim);
    });
    var box = document.getElementById('eventosPaginacao');
    if (!box) return;
    if (total === 0) {
        box.classList.add('hidden');
        box.innerHTML = '';
        return;
    }
    box.classList.remove('hidden');
    var de = inicio + 1;
    var ate = Math.min(fim, total);
    var html = '<p class="text-sm text-gray-600">Exibindo ' + de + '–' + ate + ' de ' + total + ' registro(s)</p>';
    if (totalPaginas > 1) {
        html += '<div class="flex items-center gap-1">';
        if (pagina > 1) {
            html += '<button type="button" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200" data-eventos-pagina="' + (pagina - 1) + '">Anterior</button>';
        }
        var i0 = Math.max(1, pagina - 2);
        var i1 = Math.min(totalPaginas, pagina + 2);
        for (var i = i0; i <= i1; i++) {
            var ativo = i === pagina ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200';
            html += '<button type="button" class="px-3 py-1.5 text-sm font-medium rounded-lg ' + ativo + '" data-eventos-pagina="' + i + '">' + i + '</button>';
        }
        if (pagina < totalPaginas) {
            html += '<button type="button" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200" data-eventos-pagina="' + (pagina + 1) + '">Próxima</button>';
        }
        html += '</div>';
    }
    box.innerHTML = html;
}

function openEventoDrawer(opts) {
    opts = opts || {};
    var form = document.getElementById('evento-form');
    var savedInicio = opts.keepDates ? document.getElementById('evento_data_inicio').value : '';
    var savedFim = opts.keepDates ? document.getElementById('evento_data_fim').value : '';
    form.reset();
    toggleNovoTipo(false);
    definirModoEvento(false);
    var feriado = form.querySelector('input[name="tipo"][value="feriado"]');
    if (feriado) feriado.checked = true;
    if (opts.evento) {
        preencherFormEvento(opts.evento);
    } else if (opts.keepDates) {
        document.getElementById('evento_data_inicio').value = savedInicio;
        document.getElementById('evento_data_fim').value = savedFim;
        fimSegueAutomatico = savedInicio !== '' && savedInicio === savedFim;
        ultimoFimAutomatico = fimSegueAutomatico ? savedFim : '';
    } else {
        fimSegueAutomatico = true;
        ultimoFimAutomatico = '';
    }
    var tokenEnvio = document.getElementById('evento_envio_token');
    if (tokenEnvio) tokenEnvio.value = novoTokenEnvio();
    limparErrosEvento();
    showDrawer('eventoDrawerBackdrop', 'eventoDrawer');
}
function closeEventoDrawer() { hideDrawer('eventoDrawerBackdrop', 'eventoDrawer'); }

var chkPublicar = document.getElementById('evento_publicar_escolar');
if (chkPublicar) {
    chkPublicar.addEventListener('change', function () {
        if (this.checked) {
            document.getElementById('evento_visivel_pais').checked = true;
        }
    });
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeConfigDrawer();
        closeEventoDrawer();
        closeEventosDrawer();
        closeDayModal();
    }
});
var fimSegueAutomatico = true;
var ultimoFimAutomatico = '';
var escrevendoFimAuto = false;
var enviandoEvento = false;
var camposErroEvento = {
    data_inicio: 'evento_data_inicio',
    data_fim: 'evento_data_fim',
    descricao: 'evento_descricao',
    link: 'evento_link',
    formulario: 'erro_evento_formulario'
};

function novoTokenEnvio() {
    if (window.crypto && typeof crypto.randomUUID === 'function') return crypto.randomUUID();
    return 'env-' + Date.now().toString(36) + '-' + Math.random().toString(16).slice(2);
}

function classificarDataEvento(valor) {
    if (!valor) return { estado: 'vazia', mensagem: '' };
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(valor);
    if (!m) return { estado: 'invalida', mensagem: 'Informe uma data válida.' };
    var ano = parseInt(m[1], 10);
    var mes = parseInt(m[2], 10);
    var dia = parseInt(m[3], 10);
    var dt = new Date(ano, mes - 1, dia);
    if (dt.getFullYear() !== ano || dt.getMonth() !== mes - 1 || dt.getDate() !== dia) {
        return { estado: 'invalida', mensagem: 'Informe uma data válida.' };
    }
    if (ano < 2000 || ano > 2100) return { estado: 'ano', mensagem: 'O ano deve estar entre 2000 e 2100.' };
    return { estado: 'ok', mensagem: '' };
}

function limparErroCampoEvento(campo) {
    var inputId = camposErroEvento[campo];
    var erro = document.getElementById(campo === 'formulario' ? 'erro_evento_formulario' : 'erro_' + inputId);
    if (erro) {
        erro.textContent = '';
        erro.classList.add('hidden');
    }
    if (campo !== 'formulario' && inputId) {
        var input = document.getElementById(inputId);
        if (input) input.removeAttribute('aria-invalid');
    }
}

function limparErrosEvento() {
    Object.keys(camposErroEvento).forEach(limparErroCampoEvento);
}

function mostrarErroCampoEvento(campo, mensagem) {
    var inputId = camposErroEvento[campo];
    var erro = document.getElementById(campo === 'formulario' ? 'erro_evento_formulario' : 'erro_' + inputId);
    if (erro) {
        erro.textContent = mensagem;
        erro.classList.remove('hidden');
    }
    if (campo !== 'formulario' && inputId) {
        var input = document.getElementById(inputId);
        if (input) input.setAttribute('aria-invalid', 'true');
    }
}

function aplicarErrosEvento(erros) {
    limparErrosEvento();
    var ordem = ['data_inicio', 'data_fim', 'descricao', 'link', 'formulario'];
    var primeiro = '';
    ordem.forEach(function (campo) {
        if (!erros[campo]) return;
        mostrarErroCampoEvento(campo, erros[campo]);
        if (!primeiro) primeiro = campo;
    });
    if (!primeiro) return;
    var alvo = primeiro === 'formulario'
        ? document.getElementById('erro_evento_formulario')
        : document.getElementById(camposErroEvento[primeiro]);
    if (alvo && alvo.focus) alvo.focus();
}

function errosDatasEvento(aoEnviar) {
    var inicioEl = document.getElementById('evento_data_inicio');
    var fimEl = document.getElementById('evento_data_fim');
    var descEl = document.getElementById('evento_descricao');
    var erros = {};
    var ini = classificarDataEvento(inicioEl.value);
    var fim = classificarDataEvento(fimEl.value);
    if (ini.estado === 'vazia') {
        if (aoEnviar) erros.data_inicio = 'Informe uma data válida.';
    } else if (ini.estado !== 'ok') {
        erros.data_inicio = ini.mensagem;
    }
    if (fim.estado !== 'vazia' && fim.estado !== 'ok') {
        erros.data_fim = fim.mensagem;
    } else if (ini.estado === 'ok' && fim.estado === 'ok' && fimEl.value < inicioEl.value) {
        erros.data_fim = 'A data final deve ser igual ou posterior à data inicial.';
    }
    if (aoEnviar && descEl.value.trim() === '') {
        erros.descricao = 'Informe a descrição do evento.';
    }
    if (aoEnviar) {
        var link = document.getElementById('evento_link');
        if (link && link.value.trim() !== '' && link.validity && link.validity.typeMismatch) {
            erros.link = 'Informe um link válido.';
        }
    }
    return erros;
}

function atualizarErrosDatasAoVivo() {
    var erros = errosDatasEvento(false);
    ['data_inicio', 'data_fim'].forEach(function (campo) {
        if (erros[campo]) mostrarErroCampoEvento(campo, erros[campo]);
        else limparErroCampoEvento(campo);
    });
}

function sincronizarFimAutomatico() {
    var inicio = document.getElementById('evento_data_inicio');
    var fim = document.getElementById('evento_data_fim');
    if (!inicio || !fim || escrevendoFimAuto) return;
    if (classificarDataEvento(inicio.value).estado !== 'ok') return;
    var acompanha = fimSegueAutomatico && (!fim.value || fim.value === ultimoFimAutomatico);
    if (!acompanha) return;
    if (fim.value === inicio.value) {
        ultimoFimAutomatico = inicio.value;
        fimSegueAutomatico = true;
        return;
    }
    escrevendoFimAuto = true;
    fim.value = inicio.value;
    ultimoFimAutomatico = inicio.value;
    fimSegueAutomatico = true;
    escrevendoFimAuto = false;
    limparErroCampoEvento('data_fim');
}

function aoAlterarInicioEvento() {
    sincronizarFimAutomatico();
    atualizarErrosDatasAoVivo();
}

function aoAlterarFimEvento() {
    if (escrevendoFimAuto) return;
    var fim = document.getElementById('evento_data_fim');
    if (!fim.value) {
        fimSegueAutomatico = true;
        ultimoFimAutomatico = '';
        sincronizarFimAutomatico();
        atualizarErrosDatasAoVivo();
        return;
    }
    if (fim.value !== ultimoFimAutomatico) fimSegueAutomatico = false;
    atualizarErrosDatasAoVivo();
}

var campoInicioEvento = document.getElementById('evento_data_inicio');
var campoFimEvento = document.getElementById('evento_data_fim');
campoInicioEvento.addEventListener('input', sincronizarFimAutomatico);
campoInicioEvento.addEventListener('change', aoAlterarInicioEvento);
campoFimEvento.addEventListener('input', function () {
    if (escrevendoFimAuto) return;
    var fim = document.getElementById('evento_data_fim');
    if (!fim.value) {
        fimSegueAutomatico = true;
        ultimoFimAutomatico = '';
        sincronizarFimAutomatico();
        return;
    }
    if (fim.value !== ultimoFimAutomatico) fimSegueAutomatico = false;
});
campoFimEvento.addEventListener('change', aoAlterarFimEvento);

document.getElementById('evento-form').addEventListener('submit', function (e) {
    e.preventDefault();
    if (enviandoEvento) return;
    var erros = errosDatasEvento(true);
    if (Object.keys(erros).length) {
        aplicarErrosEvento(erros);
        return;
    }
    limparErrosEvento();
    enviandoEvento = true;
    var form = this;
    var btn = document.getElementById('eventoSubmitBtn');
    var htmlBtn = btn ? btn.innerHTML : '';
    if (btn) btn.disabled = true;
    fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
        credentials: 'same-origin'
    }).then(function (res) {
        return res.text().then(function (texto) {
            var json = null;
            try { json = JSON.parse(texto); } catch (err) { json = null; }
            return json;
        });
    }).then(function (json) {
        if (json && json.ok && json.redirect) {
            window.location.href = json.redirect.indexOf('http') === 0 ? json.redirect : (baseUrl + json.redirect);
            return;
        }
        enviandoEvento = false;
        if (btn) { btn.disabled = false; btn.innerHTML = htmlBtn; }
        if (json && json.erros) {
            aplicarErrosEvento(json.erros);
            return;
        }
        aplicarErrosEvento({ formulario: 'Não foi possível salvar agora. Os dados continuam aqui. Tente novamente.' });
    }).catch(function () {
        enviandoEvento = false;
        if (btn) { btn.disabled = false; btn.innerHTML = htmlBtn; }
        aplicarErrosEvento({ formulario: 'Não foi possível salvar agora. Os dados continuam aqui. Tente novamente.' });
    });
});

function toggleNovoTipo(show) {
    var panel = document.getElementById('novoTipoPanel');
    var btn = document.getElementById('btnNovoTipo');
    if (!panel) return;
    panel.classList.toggle('hidden', !show);
    if (btn) btn.classList.toggle('hidden', !!show);
    if (show) {
        var err = document.getElementById('novoTipoErro');
        if (err) { err.classList.add('hidden'); err.textContent = ''; }
        var nome = document.getElementById('novo_tipo_nome');
        if (nome) { nome.value = ''; nome.focus(); }
        var padrao = document.querySelector('input[name="efeito_novo_tipo"][value="nao_letivo"]');
        if (padrao) padrao.checked = true;
    }
}

function mostrarErroTipo(msg) {
    var err = document.getElementById('novoTipoErro');
    if (!err) return;
    err.textContent = msg || 'Não foi possível salvar.';
    err.classList.remove('hidden');
}

function montarBotaoTipo(tipo, checked) {
    var label = document.createElement('label');
    label.className = 'cursor-pointer relative group';
    label.setAttribute('data-tipo-slug', tipo.slug);
    var radio = document.createElement('input');
    radio.type = 'radio';
    radio.name = 'tipo';
    radio.value = tipo.slug;
    radio.className = 'sr-only peer';
    if (checked) radio.checked = true;
    var chip = document.createElement('div');
    chip.className = 'text-center text-xs font-semibold px-2 py-2 rounded-lg border-2 border-transparent peer-checked:border-green-500 hover:opacity-90 transition-all';
    chip.style.background = tipo.cor_fundo;
    chip.style.color = tipo.cor;
    chip.textContent = tipo.nome;
    label.appendChild(radio);
    label.appendChild(chip);
    if (!tipo.sistema && podeExcluirTipo) {
        var del = document.createElement('button');
        del.type = 'button';
        del.className = 'tipo-excluir absolute -top-1.5 -right-1.5 hidden group-hover:flex w-5 h-5 items-center justify-center rounded-full bg-white border border-gray-300 text-gray-400 hover:text-red-600 hover:border-red-300 shadow-sm';
        del.title = 'Remover tipo';
        del.setAttribute('aria-label', 'Remover tipo ' + tipo.nome);
        del.innerHTML = '<i class="fa-solid fa-xmark text-[9px]"></i>';
        del.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            excluirTipo(tipo.slug, label);
        });
        label.appendChild(del);
    }
    return label;
}

function adicionarTipoNaLegenda(tipo) {
    var legendas = document.querySelectorAll('.flex.flex-wrap.gap-3.mb-5');
    if (!legendas.length) return;
    var span = document.createElement('span');
    span.className = 'inline-flex items-center gap-1.5 text-xs font-medium px-2.5 py-1 rounded-full';
    span.style.background = tipo.cor_fundo;
    span.style.color = tipo.cor;
    span.setAttribute('data-tipo-slug', tipo.slug);
    span.innerHTML = '<span class="w-2 h-2 rounded-full" style="background:' + escHtml(tipo.cor) + '"></span>' + escHtml(tipo.nome);
    var weekend = legendas[0].querySelector('span:last-child');
    if (weekend) legendas[0].insertBefore(span, weekend);
    else legendas[0].appendChild(span);
}

function salvarNovoTipo() {
    var nomeInp = document.getElementById('novo_tipo_nome');
    var corInp = document.getElementById('novo_tipo_cor');
    var efeitoInp = document.querySelector('input[name="efeito_novo_tipo"]:checked');
    var btn = document.getElementById('btnSalvarTipo');
    var nome = nomeInp ? nomeInp.value.trim() : '';
    if (nome.length < 2) {
        mostrarErroTipo('Informe o nome do tipo.');
        return;
    }
    if (btn) { btn.disabled = true; btn.textContent = 'Salvando…'; }
    var body = new FormData();
    body.append('csrf_token', csrfToken);
    body.append('nome', nome);
    body.append('cor', corInp ? corInp.value : '#0d9488');
    body.append('efeito', efeitoInp ? efeitoInp.value : 'nao_letivo');
    fetch(baseUrl + '/admin/calendario-letivo/salvar-tipo', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: body
    }).then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
    .then(function (pack) {
        if (btn) { btn.disabled = false; btn.textContent = 'Salvar tipo'; }
        if (!pack.data || !pack.data.ok || !pack.data.tipo) {
            mostrarErroTipo((pack.data && pack.data.erro) || 'Não foi possível salvar o tipo.');
            return;
        }
        var tipo = pack.data.tipo;
        tipoBg[tipo.slug] = tipo.cor_fundo;
        tipoText[tipo.slug] = tipo.cor;
        tipoLabelsMap[tipo.slug] = tipo.nome;
        tipoEfeitos[tipo.slug] = tipo.efeito || 'neutro';
        tipoSistema[tipo.slug] = false;
        var grid = document.getElementById('tipoGrid');
        if (grid) {
            grid.appendChild(montarBotaoTipo(tipo, true));
        }
        adicionarTipoNaLegenda(tipo);
        toggleNovoTipo(false);
        atualizarHintEfeito();
    }).catch(function () {
        if (btn) { btn.disabled = false; btn.textContent = 'Salvar tipo'; }
        mostrarErroTipo('Falha de rede. Tente de novo.');
    });
}

function excluirTipo(slug, labelEl) {
    if (!confirm('Remover este tipo?')) return;
    var body = new FormData();
    body.append('csrf_token', csrfToken);
    body.append('slug', slug);
    fetch(baseUrl + '/admin/calendario-letivo/excluir-tipo', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: body
    }).then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
    .then(function (pack) {
        if (!pack.data || !pack.data.ok) {
            alert((pack.data && pack.data.erro) || 'Não foi possível remover o tipo.');
            return;
        }
        if (labelEl && labelEl.parentNode) labelEl.parentNode.removeChild(labelEl);
        var leg = document.querySelector('.flex.flex-wrap.gap-3.mb-5 [data-tipo-slug="' + slug + '"]');
        if (leg && leg.parentNode) leg.parentNode.removeChild(leg);
        var feriado = document.querySelector('#tipoGrid input[name="tipo"][value="feriado"]');
        if (feriado) feriado.checked = true;
        atualizarHintEfeito();
    }).catch(function () {
        alert('Falha de rede. Tente de novo.');
    });
}

function atualizarHintEfeito() {
    var hint = document.getElementById('efeitoTipoHint');
    if (!hint) return;
    var radio = document.querySelector('#tipoGrid input[name="tipo"]:checked');
    var slug = radio ? radio.value : '';
    var sistema = !!(tipoSistema[slug]);
    var efeito = tipoEfeitos[slug] || 'neutro';
    hint.replaceChildren();
    if (!slug || sistema) {
        hint.classList.add('hidden');
        return;
    }
    hint.classList.remove('hidden');
    if (efeito === 'reposicao') {
        hint.textContent = 'Sábado e domingo marcados com este tipo entram nos dias letivos.';
        return;
    }
    hint.appendChild(document.createTextNode(
        efeito === 'nao_letivo'
            ? 'Este tipo desconta dia útil. Sábado e domingo não entram na conta. '
            : 'No fim de semana este tipo só aparece no calendário. '
    ));
    if (!podeAlterarEvento) return;
    var btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'font-semibold text-gray-900 underline';
    btn.textContent = 'Contar como dia letivo';
    btn.addEventListener('click', function () { definirEfeitoTipo(slug, 'reposicao'); });
    hint.appendChild(btn);
}

function definirEfeitoTipo(slug, efeito) {
    var body = new FormData();
    body.append('csrf_token', csrfToken);
    body.append('slug', slug);
    body.append('efeito', efeito);
    fetch(baseUrl + '/admin/calendario-letivo/efeito-tipo', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest' },
        body: body
    }).then(function (res) { return res.json().then(function (data) { return { ok: res.ok, data: data }; }); })
    .then(function (pack) {
        if (!pack.data || !pack.data.ok) {
            alert((pack.data && pack.data.erro) || 'Não foi possível atualizar o tipo.');
            return;
        }
        tipoEfeitos[slug] = pack.data.efeito || efeito;
        window.location.reload();
    }).catch(function () {
        alert('Falha de rede. Tente de novo.');
    });
}

document.querySelectorAll('.tipo-excluir').forEach(function (btn) {
    btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var label = btn.closest('[data-tipo-slug]');
        if (!label) return;
        excluirTipo(label.getAttribute('data-tipo-slug'), label);
    });
});
document.querySelectorAll('[data-cor-preset]').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var corInp = document.getElementById('novo_tipo_cor');
        if (corInp) corInp.value = btn.getAttribute('data-cor-preset');
    });
});
var novoTipoPanel = document.getElementById('novoTipoPanel');
if (novoTipoPanel) {
    novoTipoPanel.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            salvarNovoTipo();
        }
    });
}
var tipoGrid = document.getElementById('tipoGrid');
if (tipoGrid) tipoGrid.addEventListener('change', atualizarHintEfeito);
atualizarHintEfeito();
var eventosPaginacao = document.getElementById('eventosPaginacao');
if (eventosPaginacao) {
    renderEventosPagina(1);
    eventosPaginacao.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-eventos-pagina]');
        if (!btn) return;
        renderEventosPagina(parseInt(btn.getAttribute('data-eventos-pagina'), 10));
    });
}
<?php if (!$config): ?>
openConfigDrawer();
<?php endif; ?>
</script>
