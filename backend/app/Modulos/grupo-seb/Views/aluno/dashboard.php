<?php
/**
 * Dashboard do aluno — layout Grupo Seb.
 * Variáveis: $aluno, $aulas_do_dia, $calendario_*, $mural_recados, $primary_color, $csrf_token, $turmas_cursos_select
 */
$primaryHex = $primary_color ?? '#3b82f6';
$aulasDoDia = is_array($aulas_do_dia ?? null) ? $aulas_do_dia : [];
$muralRecados = is_array($mural_recados ?? null) ? $mural_recados : [];
$eventos = is_array($calendario_eventos ?? null) ? $calendario_eventos : [];
$ano = (int) ($calendario_ano ?? date('Y'));
$mes = (int) ($calendario_mes ?? date('n'));
$tipoLabels = is_array($calendario_tipo_labels ?? null) ? $calendario_tipo_labels : [];
$tipoBg = is_array($calendario_tipo_bg ?? null) ? $calendario_tipo_bg : [];
$tipoText = is_array($calendario_tipo_text ?? null) ? $calendario_tipo_text : [];

$mesesNomes = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
$hoje = (new DateTimeImmutable('now', new DateTimeZone('America/Sao_Paulo')))->format('Y-m-d');
$jsonFlags = JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;

$eventMap = [];
$tiposUsados = [];
foreach ($eventos as $ev) {
    $start = strtotime((string) ($ev['data_inicio'] ?? ''));
    $end = strtotime((string) ($ev['data_fim'] ?? ($ev['data_inicio'] ?? '')));
    if ($start === false || $end === false) {
        continue;
    }
    $tipo = (string) ($ev['tipo'] ?? '');
    if ($tipo !== '') {
        $tiposUsados[$tipo] = true;
    }
    $tituloEv = trim((string) ($ev['descricao'] ?? $ev['titulo'] ?? ''));
    if ($tituloEv === '') {
        $tituloEv = (string) ($tipoLabels[$tipo] ?? 'Evento');
    }
    $item = [
        'tipo' => $tipo,
        'titulo' => $tituloEv,
        'descricao' => trim((string) ($ev['descricao'] ?? '')),
        'local' => trim((string) ($ev['local_evento'] ?? '')),
        'link' => trim((string) ($ev['link_reuniao'] ?? '')),
        'inicio' => date('Y-m-d', $start),
        'fim' => date('Y-m-d', $end),
    ];
    for ($d = $start; $d <= $end; $d += 86400) {
        $key = date('Y-m-d', $d);
        $eventMap[$key][] = $item;
    }
}

$tipoLabelsLegenda = $tiposUsados === []
    ? $tipoLabels
    : array_intersect_key($tipoLabels, $tiposUsados);

$eventosMes = [];
foreach ($eventos as $ev) {
    $ini = (string) ($ev['data_inicio'] ?? '');
    $fim = (string) ($ev['data_fim'] ?? $ini);
    if ($ini === '') {
        continue;
    }
    $mesIni = (int) date('n', strtotime($ini));
    $anoIni = (int) date('Y', strtotime($ini));
    $mesFim = (int) date('n', strtotime($fim));
    $anoFim = (int) date('Y', strtotime($fim));
    $cruzaMes = ($anoIni < $ano || ($anoIni === $ano && $mesIni <= $mes))
        && ($anoFim > $ano || ($anoFim === $ano && $mesFim >= $mes));
    if (!$cruzaMes) {
        continue;
    }
    $tipo = (string) ($ev['tipo'] ?? '');
    $tituloEv = trim((string) ($ev['descricao'] ?? $ev['titulo'] ?? ''));
    if ($tituloEv === '') {
        $tituloEv = (string) ($tipoLabels[$tipo] ?? 'Evento');
    }
    $eventosMes[] = [
        'tipo' => $tipo,
        'titulo' => $tituloEv,
        'inicio' => $ini,
        'fim' => $fim,
        'local' => trim((string) ($ev['local_evento'] ?? '')),
    ];
}
usort($eventosMes, static fn($a, $b) => strcmp((string) $a['inicio'], (string) $b['inicio']));

$primeiroDia = mktime(0, 0, 0, $mes, 1, $ano);
$diasNoMes = (int) date('t', $primeiroDia);
$offset = (int) date('w', $primeiroDia);
$diasSemanaLabel = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
?>

<div class="mb-6 md:mb-8">
    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div class="min-w-0">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-900">Dashboard</h1>
            <p class="text-sm sm:text-base text-gray-600 mt-1">Calendário escolar, aulas de hoje e mural de recados.</p>
        </div>
        <?php if (!empty($turmas_cursos_select) && is_array($turmas_cursos_select)): ?>
        <div class="flex flex-col items-stretch sm:items-end gap-1 w-full sm:w-auto max-w-full">
            <label for="dashboard-turma-curso" class="text-xs font-medium text-gray-500 uppercase tracking-wide">Curso / turma</label>
            <form method="post" action="<?= htmlspecialchars(URL . '/dashboard/trocar-turma') ?>" class="w-full sm:w-auto min-w-0">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <select id="dashboard-turma-curso" name="turma_id"
                        class="w-full sm:min-w-[220px] sm:max-w-md text-sm sm:text-base font-semibold text-green-700 bg-white border border-green-200 rounded-lg px-3 py-2 shadow-sm focus:ring-2 focus:ring-green-500 focus:border-green-500"
                        onchange="this.form.submit()"
                        title="Selecione o curso ou turma em que está matriculado">
                    <?php
                    $tidAtual = (int) ($aluno['turma_id'] ?? 0);
                    foreach ($turmas_cursos_select as $opt):
                        $tid = (int) ($opt['turma_id'] ?? 0);
                        $lab = (string) ($opt['label'] ?? '');
                    ?>
                        <option value="<?= $tid ?>" <?= $tid === $tidAtual ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php elseif (!empty($aluno['turma_nome'])): ?>
        <div class="flex items-center gap-2 sm:text-right">
            <span class="text-base sm:text-lg font-semibold text-green-600"><?= htmlspecialchars((string) $aluno['turma_nome']) ?></span>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 xl:grid-cols-3 gap-4 md:gap-6">
    <!-- Calendário escolar -->
    <section id="grupo-seb-calendario"
             class="xl:col-span-2 bg-white rounded-2xl shadow-md overflow-hidden border border-gray-100"
             data-ano="<?= (int) $ano ?>"
             data-mes="<?= (int) $mes ?>"
             data-hoje="<?= htmlspecialchars($hoje) ?>"
             data-primary="<?= htmlspecialchars($primaryHex) ?>">
        <div class="px-4 sm:px-5 py-3.5 border-b border-gray-100 flex flex-wrap items-center justify-between gap-2">
            <div class="min-w-0 flex items-center gap-2.5">
                <span class="w-1 h-8 rounded-full flex-shrink-0" style="background:<?= htmlspecialchars($primaryHex) ?>"></span>
                <div>
                    <h2 class="text-base sm:text-lg font-semibold text-gray-900">Calendário escolar</h2>
                    <p class="text-xs text-gray-500 truncate">
                        <?php if (!empty($calendario_nome)): ?>
                            <?= htmlspecialchars((string) $calendario_nome) ?>
                        <?php else: ?>
                            Feriados, recessos e eventos do ano letivo
                        <?php endif; ?>
                    </p>
                </div>
            </div>
            <a href="<?= URL ?>/calendario-letivo" class="text-sm font-medium hover:underline whitespace-nowrap" style="color:<?= htmlspecialchars($primaryHex) ?>">Ver completo →</a>
        </div>

        <div class="px-4 sm:px-5 py-3 flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-1">
                <button type="button" id="gs-cal-prev" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors" aria-label="Mês anterior">
                    <svg class="w-4 h-4" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"></path></svg>
                </button>
                <span id="gs-cal-mes-label" class="text-sm font-semibold text-gray-800 min-w-[140px] text-center"><?= htmlspecialchars(($mesesNomes[$mes - 1] ?? '') . ' de ' . $ano) ?></span>
                <button type="button" id="gs-cal-next" class="inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-50 hover:bg-gray-100 transition-colors" aria-label="Próximo mês">
                    <svg class="w-4 h-4" fill="none" stroke="#6b7280" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"></path></svg>
                </button>
            </div>
            <button type="button" id="gs-cal-hoje" class="px-2.5 py-1 text-xs font-medium rounded-md border border-gray-200 text-gray-600 bg-white hover:bg-gray-50 transition-colors">Hoje</button>
        </div>

        <?php if ($tipoLabelsLegenda !== []): ?>
        <div class="px-4 sm:px-5 pb-2 flex flex-wrap gap-1.5">
            <?php foreach ($tipoLabelsLegenda as $k => $v): ?>
            <span class="inline-flex items-center gap-1 text-[10px] font-medium px-2 py-0.5 rounded-full" style="background:<?= htmlspecialchars($tipoBg[$k] ?? '#f3f4f6') ?>;color:<?= htmlspecialchars($tipoText[$k] ?? '#374151') ?>">
                <span class="w-1.5 h-1.5 rounded-full" style="background:<?= htmlspecialchars($tipoText[$k] ?? '#6b7280') ?>"></span>
                <?= htmlspecialchars((string) $v) ?>
            </span>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <div class="px-3 sm:px-4 pb-3">
            <div class="grid grid-cols-7 border-b border-gray-100">
                <?php foreach ($diasSemanaLabel as $d): ?>
                <div class="py-1.5 text-center text-[10px] font-semibold text-gray-400 uppercase"><?= $d ?></div>
                <?php endforeach; ?>
            </div>
            <div id="gs-cal-grade" class="grid grid-cols-7 gap-0.5 pt-0.5">
                <?php for ($i = 0; $i < $offset; $i++): ?>
                <div class="h-9 sm:h-10"></div>
                <?php endfor; ?>
                <?php for ($dia = 1; $dia <= $diasNoMes; $dia++):
                    $dataStr = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                    $ehHoje = $dataStr === $hoje;
                    $evsDia = $eventMap[$dataStr] ?? [];
                    $dow = (int) date('N', strtotime($dataStr));
                    $isWeekend = $dow >= 6;
                    $tiposDia = array_values(array_unique(array_column($evsDia, 'tipo')));
                    $primTipo = $tiposDia[0] ?? null;
                    if ($primTipo) {
                        $cellBg = $tipoBg[$primTipo] ?? '#eef2ff';
                        $cellText = $tipoText[$primTipo] ?? '#1e3a8a';
                    } elseif ($isWeekend) {
                        $cellBg = 'transparent';
                        $cellText = '#9ca3af';
                    } else {
                        $cellBg = 'transparent';
                        $cellText = '#4b5563';
                    }
                ?>
                <button type="button"
                        class="gs-cal-dia relative h-9 sm:h-10 rounded-lg flex flex-col items-center justify-center gap-0.5 transition-colors hover:bg-gray-50 focus:outline-none <?= $evsDia !== [] ? 'cursor-pointer' : 'cursor-default' ?>"
                        style="background:<?= htmlspecialchars($cellBg) ?>;<?= $ehHoje ? 'box-shadow:inset 0 0 0 1.5px ' . htmlspecialchars($primaryHex) . ';' : '' ?>"
                        data-data="<?= htmlspecialchars($dataStr) ?>"
                        aria-label="Dia <?= $dia ?>">
                    <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold rounded-full <?= $ehHoje ? 'text-white' : '' ?>"
                          style="color:<?= $ehHoje ? '#ffffff' : htmlspecialchars($cellText) ?>;<?= $ehHoje ? 'background-color:' . htmlspecialchars($primaryHex) : '' ?>"><?= $dia ?></span>
                    <?php if ($evsDia !== []): ?>
                    <span class="flex gap-0.5 leading-none">
                        <?php foreach (array_slice($tiposDia, 0, 3) as $tipoDot): ?>
                        <span class="w-1 h-1 rounded-full" style="background:<?= htmlspecialchars($tipoText[$tipoDot] ?? '#6b7280') ?>"></span>
                        <?php endforeach; ?>
                    </span>
                    <?php endif; ?>
                </button>
                <?php endfor; ?>
            </div>
        </div>

        <div class="px-4 sm:px-6 pb-5 border-t border-gray-100 bg-gray-50/60">
            <div class="pt-4 flex items-center justify-between gap-2 mb-3">
                <h3 class="text-sm font-semibold text-gray-800" id="gs-cal-dia-titulo">
                    Eventos de <?= date('d/m/Y', strtotime($hoje)) ?>
                </h3>
                <span class="text-[10px] sm:text-xs text-gray-400" id="gs-cal-dia-count"></span>
            </div>
            <div id="gs-cal-dia-lista" class="space-y-2 min-h-[3rem]">
                <?php
                $eventosHoje = $eventMap[$hoje] ?? [];
                if ($eventosHoje === []):
                ?>
                <p class="text-sm text-gray-500">Nenhum evento escolar neste dia.</p>
                <?php else: ?>
                    <?php foreach ($eventosHoje as $ev):
                        $tipoEv = (string) ($ev['tipo'] ?? '');
                        $bgEv = $tipoBg[$tipoEv] ?? '#f3f4f6';
                        $txEv = $tipoText[$tipoEv] ?? '#374151';
                    ?>
                    <div class="flex items-start gap-3 p-3 bg-white rounded-xl border border-gray-100 shadow-sm">
                        <span class="w-2.5 h-2.5 rounded-full mt-1.5 flex-shrink-0" style="background:<?= htmlspecialchars($txEv) ?>"></span>
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full" style="background:<?= htmlspecialchars($bgEv) ?>;color:<?= htmlspecialchars($txEv) ?>">
                                    <?= htmlspecialchars($tipoLabels[$tipoEv] ?? $tipoEv) ?>
                                </span>
                                <?php if (!empty($ev['local'])): ?>
                                <span class="text-[10px] text-gray-400 truncate"><i class="fa-solid fa-location-dot mr-0.5"></i><?= htmlspecialchars((string) $ev['local']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="text-sm font-semibold text-gray-900 mt-1"><?= htmlspecialchars((string) $ev['titulo']) ?></p>
                        </div>
                        <?php if (!empty($ev['link'])): ?>
                        <a href="<?= htmlspecialchars((string) $ev['link']) ?>" target="_blank" rel="noopener" class="text-xs font-medium flex-shrink-0 hover:underline" style="color:<?= htmlspecialchars($primaryHex) ?>">Link</a>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div id="gs-cal-mes-bloco" class="mt-5 pt-4 border-t border-gray-200 <?= ($eventosMes === [] && $eventos === []) ? '' : '' ?>">
                <h3 class="text-sm font-semibold text-gray-800 mb-3">Neste mês</h3>
                <ul id="gs-cal-mes-lista" class="space-y-2 max-h-48 overflow-y-auto pr-1">
                    <?php if ($eventosMes === []): ?>
                    <li class="text-sm text-gray-500"><?= $eventos === [] ? 'Nenhum evento cadastrado no calendário letivo ainda.' : 'Nenhum evento neste mês.' ?></li>
                    <?php else: ?>
                        <?php foreach (array_slice($eventosMes, 0, 8) as $evMes):
                            $tipoMes = (string) ($evMes['tipo'] ?? '');
                            $txMes = $tipoText[$tipoMes] ?? '#6b7280';
                            $iniBr = date('d/m', strtotime((string) $evMes['inicio']));
                            $fimBr = date('d/m', strtotime((string) $evMes['fim']));
                            $periodo = $iniBr === $fimBr ? $iniBr : $iniBr . ' – ' . $fimBr;
                        ?>
                        <li class="flex items-center gap-3 text-sm">
                            <span class="w-2 h-2 rounded-full flex-shrink-0" style="background:<?= htmlspecialchars($txMes) ?>"></span>
                            <span class="text-xs font-medium text-gray-500 w-20 flex-shrink-0"><?= htmlspecialchars($periodo) ?></span>
                            <span class="text-gray-900 truncate font-medium"><?= htmlspecialchars((string) $evMes['titulo']) ?></span>
                        </li>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </section>

    <!-- Aula do dia + Mural -->
    <div class="space-y-4 md:space-y-6">
        <section class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Aula do dia</h2>
                    <p class="text-xs text-gray-500 mt-0.5">Grade horária de hoje</p>
                </div>
                <a href="<?= URL ?>/grade-horaria" class="text-sm font-semibold hover:underline" style="color: <?= htmlspecialchars($primaryHex) ?>">Grade →</a>
            </div>
            <div class="p-4 sm:p-5">
                <?php if ($aulasDoDia === []): ?>
                <p class="text-sm text-gray-500">Nenhuma aula prevista para hoje.</p>
                <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach ($aulasDoDia as $aula):
                        $de = substr((string) ($aula['horario_de'] ?? ''), 0, 5);
                        $ate = substr((string) ($aula['horario_ate'] ?? ''), 0, 5);
                        $materia = (string) ($aula['materia_nome'] ?? 'Aula');
                        $prof = (string) ($aula['professor_nome'] ?? '');
                        $sala = trim((string) ($aula['sala_nome'] ?? ''));
                    ?>
                    <li class="flex gap-3 rounded-xl border border-gray-100 p-3">
                        <div class="flex-shrink-0 w-16 text-center">
                            <div class="text-xs font-bold text-gray-900"><?= htmlspecialchars($de) ?></div>
                            <div class="text-[10px] text-gray-400"><?= htmlspecialchars($ate) ?></div>
                        </div>
                        <div class="min-w-0 flex-1 border-l-4 pl-3" style="border-left-color: <?= htmlspecialchars($primaryHex) ?>">
                            <p class="text-sm font-semibold text-gray-900 truncate"><?= htmlspecialchars($materia) ?></p>
                            <?php if ($prof !== ''): ?>
                            <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($prof) ?></p>
                            <?php endif; ?>
                            <?php if ($sala !== ''): ?>
                            <p class="text-xs text-gray-400 truncate"><?= htmlspecialchars($sala) ?></p>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </section>

        <section class="bg-white rounded-2xl shadow-md border border-gray-100 overflow-hidden">
            <div class="p-4 sm:p-5 border-b border-gray-100 flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900">Mural de recados</h2>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <?php
                        $naoLidos = (int) ($mural_recados_nao_lidos_count ?? 0);
                        echo $naoLidos > 0
                            ? ($naoLidos === 1 ? '1 não lido' : $naoLidos . ' não lidos')
                            : 'Sem pendências';
                        ?>
                    </p>
                </div>
                <a href="<?= URL ?>/mural-recados" class="text-sm font-semibold hover:underline" style="color: <?= htmlspecialchars($primaryHex) ?>">Abrir →</a>
            </div>
            <div class="p-4 sm:p-5">
                <?php if ($muralRecados === []): ?>
                <p class="text-sm text-gray-500">Nenhum recado no mural no momento.</p>
                <?php else: ?>
                <ul class="space-y-3">
                    <?php foreach (array_slice($muralRecados, 0, 5) as $recado):
                        $titulo = (string) ($recado['titulo'] ?? 'Recado');
                        $dataPub = (string) ($recado['data_publicacao'] ?? '');
                        $dataFmt = $dataPub !== '' ? date('d/m/Y', strtotime($dataPub)) : '';
                        $visto = !empty($recado['ja_visto']);
                    ?>
                    <li>
                        <a href="<?= URL ?>/mural-recados" class="block rounded-xl border border-gray-100 p-3 hover:bg-gray-50 transition-colors">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-semibold text-gray-900">
                                    <?php if (!$visto): ?><span class="inline-block w-2 h-2 rounded-full bg-amber-500 mr-1.5 align-middle"></span><?php endif; ?>
                                    <?= htmlspecialchars($titulo) ?>
                                </p>
                                <?php if ($dataFmt !== ''): ?>
                                <span class="text-[10px] text-gray-400 whitespace-nowrap"><?= htmlspecialchars($dataFmt) ?></span>
                                <?php endif; ?>
                            </div>
                            <?php if (!empty($recado['conteudo'])): ?>
                            <p class="text-xs text-gray-500 mt-1 line-clamp-2"><?= htmlspecialchars(mb_substr(strip_tags((string) $recado['conteudo']), 0, 120)) ?></p>
                            <?php endif; ?>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </section>
    </div>
</div>

<script>
(function () {
    var root = document.getElementById('grupo-seb-calendario');
    if (!root) return;

    var eventMap = <?= json_encode($eventMap, $jsonFlags) ?>;
    var eventosAno = <?= json_encode(array_values(array_map(static function ($ev) use ($tipoLabels) {
        $tipo = (string) ($ev['tipo'] ?? '');
        $titulo = trim((string) ($ev['descricao'] ?? $ev['titulo'] ?? ''));
        if ($titulo === '') {
            $titulo = (string) ($tipoLabels[$tipo] ?? 'Evento');
        }
        return [
            'tipo' => $tipo,
            'titulo' => $titulo,
            'inicio' => (string) ($ev['data_inicio'] ?? ''),
            'fim' => (string) ($ev['data_fim'] ?? ($ev['data_inicio'] ?? '')),
        ];
    }, $eventos)), $jsonFlags) ?>;
    var tipoLabels = <?= json_encode($tipoLabels, $jsonFlags) ?>;
    var tipoBg = <?= json_encode($tipoBg, $jsonFlags) ?>;
    var tipoText = <?= json_encode($tipoText, $jsonFlags) ?>;
    var mesesNomes = <?= json_encode($mesesNomes, $jsonFlags) ?>;
    var primaryHex = root.dataset.primary || '#3b82f6';
    var hoje = root.dataset.hoje;
    var ano = parseInt(root.dataset.ano, 10);
    var mes = parseInt(root.dataset.mes, 10);
    var diaSelecionado = hoje;

    var elGrade = document.getElementById('gs-cal-grade');
    var elLabel = document.getElementById('gs-cal-mes-label');
    var elTitulo = document.getElementById('gs-cal-dia-titulo');
    var elLista = document.getElementById('gs-cal-dia-lista');
    var elCount = document.getElementById('gs-cal-dia-count');
    var elMesLista = document.getElementById('gs-cal-mes-lista');

    function pad(n) { return n < 10 ? '0' + n : String(n); }
    function iso(y, m, d) { return y + '-' + pad(m) + '-' + pad(d); }
    function br(isoDate) {
        var p = String(isoDate).split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }
    function esc(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function renderLista(dataIso) {
        diaSelecionado = dataIso;
        elTitulo.textContent = 'Eventos de ' + br(dataIso);
        var evs = eventMap[dataIso] || [];
        elCount.textContent = evs.length ? (evs.length === 1 ? '1 evento' : evs.length + ' eventos') : '';
        if (!evs.length) {
            elLista.innerHTML = '<p class="text-sm text-gray-500">Nenhum evento escolar neste dia.</p>';
            return;
        }
        elLista.innerHTML = evs.map(function (ev) {
            var tipo = ev.tipo || '';
            var bg = tipoBg[tipo] || '#f3f4f6';
            var tx = tipoText[tipo] || '#374151';
            var label = tipoLabels[tipo] || tipo || 'Evento';
            var local = ev.local
                ? '<span class="text-[10px] text-gray-400 truncate"><i class="fa-solid fa-location-dot mr-0.5"></i>' + esc(ev.local) + '</span>'
                : '';
            var link = ev.link
                ? '<a href="' + esc(ev.link) + '" target="_blank" rel="noopener" class="text-xs font-medium flex-shrink-0 hover:underline" style="color:' + primaryHex + '">Link</a>'
                : '';
            return '<div class="flex items-start gap-3 p-3 bg-white rounded-xl border border-gray-100 shadow-sm">' +
                '<span class="w-2.5 h-2.5 rounded-full mt-1.5 flex-shrink-0" style="background:' + esc(tx) + '"></span>' +
                '<div class="min-w-0 flex-1"><div class="flex flex-wrap items-center gap-2">' +
                '<span class="text-[10px] font-semibold uppercase tracking-wide px-2 py-0.5 rounded-full" style="background:' + esc(bg) + ';color:' + esc(tx) + '">' + esc(label) + '</span>' +
                local + '</div>' +
                '<p class="text-sm font-semibold text-gray-900 mt-1">' + esc(ev.titulo || label) + '</p></div>' + link + '</div>';
        }).join('');
    }

    function renderMesLista() {
        if (!elMesLista) return;
        var lista = eventosAno.filter(function (ev) {
            if (!ev.inicio) return false;
            var ini = ev.inicio.split('-');
            var fim = (ev.fim || ev.inicio).split('-');
            var yi = parseInt(ini[0], 10), mi = parseInt(ini[1], 10);
            var yf = parseInt(fim[0], 10), mf = parseInt(fim[1], 10);
            return (yi < ano || (yi === ano && mi <= mes)) && (yf > ano || (yf === ano && mf >= mes));
        }).slice(0, 8);
        if (!lista.length) {
            elMesLista.innerHTML = '<li class="text-sm text-gray-500">' +
                (eventosAno.length ? 'Nenhum evento neste mês.' : 'Nenhum evento cadastrado no calendário letivo ainda.') +
                '</li>';
            return;
        }
        elMesLista.innerHTML = lista.map(function (ev) {
            var tx = tipoText[ev.tipo] || '#6b7280';
            var iniP = String(ev.inicio).split('-');
            var fimP = String(ev.fim || ev.inicio).split('-');
            var iniBr = iniP[2] + '/' + iniP[1];
            var fimBr = fimP[2] + '/' + fimP[1];
            var periodo = iniBr === fimBr ? iniBr : (iniBr + ' – ' + fimBr);
            return '<li class="flex items-center gap-3 text-sm">' +
                '<span class="w-2 h-2 rounded-full flex-shrink-0" style="background:' + esc(tx) + '"></span>' +
                '<span class="text-xs font-medium text-gray-500 w-20 flex-shrink-0">' + esc(periodo) + '</span>' +
                '<span class="text-gray-900 truncate font-medium">' + esc(ev.titulo) + '</span></li>';
        }).join('');
    }

    function marcarSelecao() {
        elGrade.querySelectorAll('.gs-cal-dia').forEach(function (b) {
            var sel = b.dataset.data === diaSelecionado || b.dataset.data === hoje;
            b.style.boxShadow = sel ? ('inset 0 0 0 1.5px ' + primaryHex) : '';
        });
    }

    function bindDias() {
        elGrade.querySelectorAll('.gs-cal-dia').forEach(function (btn) {
            btn.addEventListener('click', function () {
                renderLista(btn.dataset.data);
                marcarSelecao();
            });
        });
    }

    function renderGrade() {
        var primeiro = new Date(ano, mes - 1, 1);
        var diasNoMes = new Date(ano, mes, 0).getDate();
        var offset = primeiro.getDay();
        elLabel.textContent = mesesNomes[mes - 1] + ' de ' + ano;
        renderMesLista();
        var html = '';
        var i, dia, dataStr, evs, tipos, prim, cellBg, cellText, dow, isWeekend, ehHoje, dots;
        for (i = 0; i < offset; i++) {
            html += '<div class="h-9 sm:h-10"></div>';
        }
        for (dia = 1; dia <= diasNoMes; dia++) {
            dataStr = iso(ano, mes, dia);
            evs = eventMap[dataStr] || [];
            tipos = [];
            evs.forEach(function (e) {
                if (e.tipo && tipos.indexOf(e.tipo) === -1) tipos.push(e.tipo);
            });
            prim = tipos[0] || null;
            dow = new Date(ano, mes - 1, dia).getDay();
            isWeekend = dow === 0 || dow === 6;
            ehHoje = dataStr === hoje;
            if (prim) {
                cellBg = tipoBg[prim] || '#eef2ff';
                cellText = tipoText[prim] || '#1e3a8a';
            } else if (isWeekend) {
                cellBg = 'transparent';
                cellText = '#9ca3af';
            } else {
                cellBg = 'transparent';
                cellText = '#4b5563';
            }
            dots = tipos.slice(0, 3).map(function (t) {
                return '<span class="w-1 h-1 rounded-full" style="background:' + esc(tipoText[t] || '#6b7280') + '"></span>';
            }).join('');
            html += '<button type="button" class="gs-cal-dia relative h-9 sm:h-10 rounded-lg flex flex-col items-center justify-center gap-0.5 transition-colors hover:bg-gray-50 focus:outline-none' +
                (evs.length ? ' cursor-pointer' : ' cursor-default') +
                '" style="background:' + esc(cellBg) + ';' +
                ((ehHoje || dataStr === diaSelecionado) ? 'box-shadow:inset 0 0 0 1.5px ' + primaryHex + ';' : '') +
                '" data-data="' + dataStr + '" aria-label="Dia ' + dia + '">' +
                '<span class="inline-flex items-center justify-center w-6 h-6 text-xs font-semibold rounded-full' +
                (ehHoje ? ' text-white' : '') + '" style="color:' + (ehHoje ? '#ffffff' : esc(cellText)) + ';' +
                (ehHoje ? 'background-color:' + primaryHex + ';' : '') + '">' + dia + '</span>' +
                (dots ? '<span class="flex gap-0.5 leading-none">' + dots + '</span>' : '') +
                '</button>';
        }
        elGrade.innerHTML = html;
        bindDias();
    }

    document.getElementById('gs-cal-prev').addEventListener('click', function () {
        mes -= 1;
        if (mes < 1) { mes = 12; ano -= 1; }
        renderGrade();
    });
    document.getElementById('gs-cal-next').addEventListener('click', function () {
        mes += 1;
        if (mes > 12) { mes = 1; ano += 1; }
        renderGrade();
    });
    document.getElementById('gs-cal-hoje').addEventListener('click', function () {
        var p = hoje.split('-');
        ano = parseInt(p[0], 10);
        mes = parseInt(p[1], 10);
        renderGrade();
        renderLista(hoje);
    });

    bindDias();

    var evsHoje = eventMap[hoje] || [];
    if (elCount) {
        elCount.textContent = evsHoje.length ? (evsHoje.length === 1 ? '1 evento' : evsHoje.length + ' eventos') : '';
    }
})();
</script>
