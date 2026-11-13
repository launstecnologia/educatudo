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

$eventMap = [];
foreach ($eventos as $ev) {
    $start = strtotime((string) ($ev['data_inicio'] ?? ''));
    $end = strtotime((string) ($ev['data_fim'] ?? ($ev['data_inicio'] ?? '')));
    if ($start === false || $end === false) {
        continue;
    }
    for ($d = $start; $d <= $end; $d += 86400) {
        $key = date('Y-m-d', $d);
        $eventMap[$key][] = $ev;
    }
}

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
    <section class="xl:col-span-2 bg-white rounded-2xl shadow-md overflow-hidden border border-gray-100">
        <div class="p-4 sm:p-6 border-b border-gray-100 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg sm:text-xl font-semibold text-gray-900">Calendário escolar</h2>
                <p class="text-xs sm:text-sm text-gray-500 mt-0.5">
                    <?= htmlspecialchars($mesesNomes[$mes - 1] ?? '') ?> de <?= (int) $ano ?>
                    <?php if (!empty($calendario_nome)): ?>
                        · <?= htmlspecialchars((string) $calendario_nome) ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="<?= URL ?>/calendario-letivo" class="text-sm font-semibold hover:underline" style="color: <?= htmlspecialchars($primaryHex) ?>">Ver completo →</a>
        </div>
        <div class="grid grid-cols-7 border-b border-gray-100 bg-gray-50">
            <?php foreach ($diasSemanaLabel as $d): ?>
            <div class="py-2 text-center text-[10px] sm:text-xs font-semibold text-gray-500 uppercase"><?= $d ?></div>
            <?php endforeach; ?>
        </div>
        <div class="grid grid-cols-7">
            <?php for ($i = 0; $i < $offset; $i++): ?>
            <div class="min-h-[64px] sm:min-h-[80px] border-b border-r border-gray-50 bg-gray-50/40"></div>
            <?php endfor; ?>
            <?php for ($dia = 1; $dia <= $diasNoMes; $dia++):
                $dataStr = sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
                $ehHoje = $dataStr === $hoje;
                $evsDia = $eventMap[$dataStr] ?? [];
            ?>
            <div class="min-h-[64px] sm:min-h-[80px] border-b border-r border-gray-50 p-1 sm:p-2 <?= $ehHoje ? 'bg-blue-50/60' : '' ?>">
                <div class="text-xs sm:text-sm font-semibold <?= $ehHoje ? 'text-blue-700' : 'text-gray-700' ?>"><?= $dia ?></div>
                <div class="mt-1 space-y-0.5">
                    <?php foreach (array_slice($evsDia, 0, 2) as $ev):
                        $tipo = (string) ($ev['tipo'] ?? '');
                        $bg = $tipoBg[$tipo] ?? '#e5e7eb';
                        $tx = $tipoText[$tipo] ?? '#374151';
                        $titulo = (string) ($ev['titulo'] ?? $tipoLabels[$tipo] ?? 'Evento');
                    ?>
                    <div class="truncate rounded px-1 py-0.5 text-[10px] sm:text-xs font-medium" style="background: <?= htmlspecialchars($bg) ?>; color: <?= htmlspecialchars($tx) ?>" title="<?= htmlspecialchars($titulo) ?>">
                        <?= htmlspecialchars($titulo) ?>
                    </div>
                    <?php endforeach; ?>
                    <?php if (count($evsDia) > 2): ?>
                    <div class="text-[10px] text-gray-400 pl-1">+<?= count($evsDia) - 2 ?></div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endfor; ?>
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
                                <p class="text-sm font-semibold text-gray-900 <?= $visto ? '' : '' ?>">
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
