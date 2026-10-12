<?php
/** @var list<array<string,mixed>> $jornadas */
if (empty($jornadas)): ?>
    <div class="col-span-full jornadas-empty-state">
        <div class="bg-white rounded-xl shadow-lg p-12 text-center">
            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma jornada encontrada</h3>
            <p class="text-gray-600 mb-4">Seus professores ainda não criaram jornadas para sua turma.</p>
            <p class="text-sm text-gray-500">Entre em contato com seus professores para mais informações.</p>
        </div>
    </div>
<?php else: ?>
    <?php foreach ($jornadas as $jornada):
        $statusExib = $jornada['status_exibicao'] ?? 'em_andamento';
        $dataInicioAttr = !empty($jornada['data_inicio']) ? date('Y-m-d', strtotime($jornada['data_inicio'])) : '';
        $dataFimAttr = !empty($jornada['data_fim']) ? date('Y-m-d', strtotime($jornada['data_fim'])) : '';
        $cardResp = (int) ($jornada['questoes_respondidas'] ?? 0);
        $cardAc = (int) ($jornada['questoes_acertos'] ?? 0);
        $cardPend = (int) ($jornada['questoes_pendentes'] ?? 0);
        $cardQt = (int) ($jornada['questoes_total'] ?? 0);
        $cardEr = (int) ($jornada['questoes_erros'] ?? max(0, $cardResp - $cardAc - $cardPend));
        ?>
        <div class="jornada-card flex h-full min-h-0 flex-col rounded-xl bg-white p-6 shadow-lg transition-shadow hover:shadow-xl"
             data-titulo="<?= strtolower(htmlspecialchars($jornada['titulo'])) ?>"
             data-materia="<?= htmlspecialchars($jornada['materia_nome'] ?? '') ?>"
             data-status="<?= htmlspecialchars($statusExib) ?>"
             data-professor="<?= htmlspecialchars($jornada['professor_nome'] ?? '') ?>"
             data-data-inicio="<?= htmlspecialchars($dataInicioAttr) ?>"
             data-data-fim="<?= htmlspecialchars($dataFimAttr) ?>"
             data-questoes-total="<?= $cardQt ?>"
             data-questoes-acertos="<?= $cardAc ?>"
             data-questoes-erros="<?= $cardEr ?>">

            <div class="mb-4 flex min-h-[7.5rem] shrink-0 items-start justify-between sm:min-h-[8rem]">
                <div class="min-w-0 flex-1 pr-2">
                    <div class="mb-2">
                        <h3 class="text-xl font-semibold leading-snug text-gray-900"><?= htmlspecialchars($jornada['titulo']) ?></h3>
                    </div>
                    <?php if (!empty($jornada['descricao'])): ?>
                        <p class="text-gray-600 text-sm mb-1"><?= htmlspecialchars($jornada['descricao']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500">Prof. <?= htmlspecialchars($jornada['professor_nome']) ?></p>
                </div>
                <div class="flex shrink-0 items-start space-x-2 pt-0.5">
                    <?php
                    $podeIniciar = true;
                    $mensagemBloqueio = '';
                    $statusJornada = 'Em andamento';
                    $corStatus = 'bg-green-100 text-green-800';
                    $agoraTs = time();

                    if (!empty($jornada['jornada_concluida'])) {
                        $statusJornada = 'Concluído';
                        $corStatus = 'bg-blue-100 text-blue-800';
                    } elseif (!empty($jornada['data_inicio']) && !empty($jornada['data_fim'])) {
                        $horaInicio = trim((string) ($jornada['hora_inicio'] ?? '')) ?: '00:00';
                        $horaFim = trim((string) ($jornada['hora_fim'] ?? '')) ?: '23:59:59';
                        $tsInicio = strtotime($jornada['data_inicio'] . ' ' . $horaInicio);
                        $tsFim = strtotime($jornada['data_fim'] . ' ' . $horaFim);
                        if ($tsInicio !== false && $tsFim !== false) {
                            if ($agoraTs > $tsFim) {
                                $podeIniciar = false;
                                $statusJornada = 'Expirado';
                                $corStatus = 'bg-red-100 text-red-700';
                                $mensagemBloqueio = 'Expirado';
                            } elseif ($agoraTs < $tsInicio) {
                                $podeIniciar = false;
                                $statusJornada = 'Aguardando';
                                $corStatus = 'bg-orange-100 text-orange-800';
                                $mensagemBloqueio = 'Disponível em ' . date('d/m/Y H:i', $tsInicio);
                            }
                        }
                    }
                    ?>
                    <span class="px-2 py-1 <?= $corStatus ?> text-xs rounded-full">
                        <?= $statusJornada ?>
                    </span>
                </div>
            </div>

            <div class="space-y-2 mb-4">
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Matéria:</span>
                    <span class="font-medium text-gray-900"><?= htmlspecialchars($jornada['materia_nome'] ?? 'Não especificada') ?></span>
                </div>
                <?php
                $dataInicio = $jornada['data_inicio'] ?? null;
                $horaInicio = $jornada['hora_inicio'] ?? null;
                $dataFim = $jornada['data_fim'] ?? null;
                $horaFim = $jornada['hora_fim'] ?? null;
                if (!$dataInicio && !empty($jornada['estrutura'])) {
                    $estrutura = json_decode($jornada['estrutura'], true);
                    if (is_array($estrutura)) {
                        $dataInicio = $estrutura['data_inicio'] ?? null;
                        $horaInicio = $estrutura['hora_inicio'] ?? null;
                        $dataFim = $estrutura['data_fim'] ?? null;
                        $horaFim = $estrutura['hora_fim'] ?? null;
                    }
                }
                ?>
                <?php if ($dataInicio): ?>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Início:</span>
                    <span class="font-medium text-gray-900">
                        <?= date('d/m/Y', strtotime($dataInicio)) ?>
                        <?php if ($horaInicio): ?>
                            <?php
                            $horaFormatada = strlen($horaInicio) > 5 ? date('H:i', strtotime($horaInicio)) : $horaInicio;
                            ?>
                            às <?= $horaFormatada ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
                <?php if ($dataFim): ?>
                <div class="flex items-center justify-between text-sm">
                    <span class="text-gray-500">Término:</span>
                    <span class="font-medium text-gray-900">
                        <?= date('d/m/Y', strtotime($dataFim)) ?>
                        <?php if ($horaFim): ?>
                            <?php
                            $horaFormatada = strlen($horaFim) > 5 ? date('H:i', strtotime($horaFim)) : $horaFim;
                            ?>
                            às <?= $horaFormatada ?>
                        <?php endif; ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <?php
            $qt = (int) ($jornada['questoes_total'] ?? 0);
            $resp = (int) ($jornada['questoes_respondidas'] ?? 0);
            $ac = (int) ($jornada['questoes_acertos'] ?? 0);
            $pend = (int) ($jornada['questoes_pendentes'] ?? 0);
            $er = (int) ($jornada['questoes_erros'] ?? max(0, $resp - $ac - $pend));
            $nr = (int) ($jornada['questoes_nao_respondidas'] ?? max(0, $qt - $resp));
            $pctAcTot = $jornada['questoes_pct_acertos_total'] ?? null;
            $pctErTot = $jornada['questoes_pct_erros_total'] ?? null;
            $temQuestoes = $qt > 0 || $resp > 0;
            ?>
            <?php if ($temQuestoes): ?>
            <?php
            $pctRespSobreTotal = $qt > 0 ? round(($resp / $qt) * 100, 1) : ($resp > 0 ? 100.0 : 0.0);
            $pctRespSobreTotalFmt = number_format((float) $pctRespSobreTotal, 1, ',', '.');
            ?>
            <div class="border-t border-gray-100 pt-3 mt-3">
                <div class="flex items-center justify-between gap-2 mb-2">
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Questões (exercícios)</p>
                    <span class="text-xs text-gray-400 tabular-nums">Total: <strong class="text-gray-700"><?= $qt ?></strong></span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                    <div class="rounded-lg border border-slate-200 bg-slate-50/90 p-3 text-center">
                        <span class="text-xs font-medium text-slate-600">Respondidas</span>
                        <span class="mt-1 block text-2xl font-bold text-slate-900 tabular-nums"><?= $resp ?></span>
                        <span class="text-sm text-slate-600 tabular-nums"><?= $pctRespSobreTotalFmt ?>%</span>
                    </div>
                    <div class="rounded-lg border border-emerald-200 bg-emerald-50/90 p-3 text-center">
                        <span class="text-xs font-medium text-emerald-800">Acertos</span>
                        <span class="mt-1 block text-2xl font-bold text-emerald-700 tabular-nums"><?= $ac ?></span>
                        <span class="text-sm text-emerald-700 tabular-nums"><?= $pctAcTot !== null ? number_format((float) $pctAcTot, 1, ',', '.') . '%' : '—' ?></span>
                    </div>
                    <div class="rounded-lg border border-rose-200 bg-rose-50/90 p-3 text-center">
                        <span class="text-xs font-medium text-rose-900">Erros</span>
                        <span class="mt-1 block text-2xl font-bold text-rose-700 tabular-nums"><?= $er ?></span>
                        <span class="text-sm text-rose-800 tabular-nums"><?= $pctErTot !== null ? number_format((float) $pctErTot, 1, ',', '.') . '%' : '—' ?></span>
                    </div>
                </div>
                <?php if ($nr > 0): ?>
                <div class="text-gray-500 text-xs mt-2">Ainda não respondidas: <strong><?= $nr ?></strong></div>
                <?php endif; ?>
                <?php if ($pend > 0): ?>
                <div class="text-amber-700 text-xs mt-2">Aguardando correção do professor: <strong><?= $pend ?></strong></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="mt-auto shrink-0 pt-5">
                <?php if ($podeIniciar): ?>
                    <a href="<?= URL ?>/jornadas/<?= (int) $jornada['id'] ?>" class="block w-full bg-blue-500 text-white px-4 py-2.5 rounded-lg text-sm font-medium hover:bg-blue-600 transition-colors text-center">
                        <?= !empty($jornada['jornada_concluida']) ? 'Ver Jornada' : 'Iniciar Jornada' ?>
                    </a>
                <?php else: ?>
                    <button type="button" disabled class="w-full px-4 py-2.5 rounded-lg text-sm font-medium cursor-not-allowed text-center <?= ($statusJornada === 'Expirado') ? 'bg-red-100 text-red-700' : 'bg-gray-300 text-gray-500' ?>" title="<?= htmlspecialchars($mensagemBloqueio) ?>">
                        <?= htmlspecialchars($mensagemBloqueio) ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
