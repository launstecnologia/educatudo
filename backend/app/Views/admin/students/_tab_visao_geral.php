<?php
$naoInformado = 'Não informado';
$matriculaAtual = is_array($matriculaAtual ?? null) ? $matriculaAtual : null;
$pendenciasAluno = is_array($pendenciasAluno ?? null) ? $pendenciasAluno : [];
$pendenciasCount = (int) ($pendenciasCount ?? count($pendenciasAluno));
$alunoIdVg = (int) ($student['id'] ?? 0);
$turnoLabelsVg = ['manha' => 'Manhã', 'tarde' => 'Tarde', 'noite' => 'Noite', 'integral' => 'Integral'];
$turnoAtual = $turnoLabelsVg[$student['turma_turno'] ?? ''] ?? '';
$vigenciaInicio = is_array($matriculaAtual) ? ($matriculaAtual['data_entrada'] ?? null) : null;
$vigenciaFim = is_array($matriculaAtual) ? ($matriculaAtual['data_saida'] ?? null) : null;
$vigenciaTxt = is_array($matriculaAtual)
    ? (format_data_br($vigenciaInicio) . ' — ' . ($vigenciaFim ? format_data_br($vigenciaFim) : 'em andamento'))
    : $naoInformado;
$vinculoAtual = is_array($matriculaAtual) ? trim((string) ($matriculaAtual['vinculo_rotulo'] ?? '')) : '';
$anoMatriculaAtual = (is_array($matriculaAtual) && !empty($matriculaAtual['ano_letivo_ano']))
    ? (string) (int) $matriculaAtual['ano_letivo_ano']
    : $naoInformado;
$turmaAnoLabel = $turmaAnoLabel ?? trim((string) ((is_array($matriculaAtual) ? ($matriculaAtual['turma_nome'] ?? $turmaDisplay) : $turmaDisplay) . ($anoMatriculaAtual !== $naoInformado ? ' • ' . $anoMatriculaAtual : '')));
$statusMatriculaVisual = $statusMatriculaVisual ?? $statusMatriculaLabel;
$qtdOcorrencias = count($ocorrencias);
$kpisVe = is_array($vida_escolar_kpis ?? null) ? $vida_escolar_kpis : [];
$kpiMedia = isset($kpisVe['media']) && $kpisVe['media'] !== null ? (float) $kpisVe['media'] : null;
$kpiFrequencia = isset($kpisVe['frequencia']) && $kpisVe['frequencia'] !== null ? (float) $kpisVe['frequencia'] : null;
$moduloVidaEscolar = !class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('vida_escolar');
$podeVidaEscolar = !empty($admin_permissions['vida_escolar']['visualizar']);
?>
<div class="space-y-5">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        <div class="xl:col-span-8 space-y-5">
            <section class="student-card">
                <div class="student-card-header">
                    <div class="flex items-center gap-2">
                        <span class="aluno-card-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                        <h3 class="text-base font-semibold text-slate-900">Situação acadêmica</h3>
                    </div>
                </div>
                <div class="student-card-body">
                    <div class="flex flex-col lg:flex-row gap-5">
                        <div class="flex-1 min-w-0">
                            <p class="text-lg font-bold text-slate-900">
                                <?= $matriculaAtual || (!$matriculaPendente && !$matriculaEncerrada)
                                    ? safe_htmlspecialchars($turmaAnoLabel)
                                    : safe_htmlspecialchars($naoInformado) ?>
                            </p>
                            <div class="flex flex-wrap items-center gap-2 mt-2">
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $matriculaBadgeClass ?>"><?= safe_htmlspecialchars($statusMatriculaVisual) ?></span>
                                <?php if ($vinculoAtual !== ''): ?>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full bg-slate-100 text-slate-700"><?= safe_htmlspecialchars($vinculoAtual) ?></span>
                                <?php endif; ?>
                                <?php if ($turnoAtual !== ''): ?>
                                <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full bg-slate-100 text-slate-700"><?= safe_htmlspecialchars($turnoAtual) ?></span>
                                <?php endif; ?>
                            </div>
                            <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <dt class="student-field-label">Período</dt>
                                    <dd class="text-sm text-slate-700"><?= $vigenciaTxt ?></dd>
                                </div>
                                <div>
                                    <dt class="student-field-label">Tipo de vínculo</dt>
                                    <dd class="text-sm text-slate-700"><?= safe_htmlspecialchars($vinculoAtual !== '' ? $vinculoAtual : null, $naoInformado) ?></dd>
                                </div>
                            </dl>
                            <div class="flex flex-wrap gap-2 mt-4">
                                <button type="button" onclick="selecionarAbaAluno('matriculas')" class="aluno-btn-outline" data-perm-key="matriculas_aluno" data-perm-action="visualizar">Ver matrícula</button>
                                <?php if ($moduloVidaEscolar && $podeVidaEscolar): ?>
                                <button type="button" onclick="selecionarAbaAluno('vida-escolar')" class="aluno-btn-outline" data-perm-key="vida_escolar" data-perm-action="visualizar">Vida escolar</button>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="grid grid-cols-3 gap-2 lg:w-[22rem] shrink-0">
                            <button type="button" class="aluno-kpi text-left" <?php if ($podeVidaEscolar): ?>onclick="selecionarAbaAluno('vida-escolar')"<?php endif; ?>>
                                <p class="text-xs text-slate-500 mb-1">Frequência</p>
                                <?php if ($kpiFrequencia !== null): ?>
                                    <p class="text-2xl font-bold text-slate-800 leading-none"><?= number_format($kpiFrequencia, 0, ',', '.') ?>%</p>
                                    <p class="text-[11px] text-slate-500 mt-1">Do boletim oficial</p>
                                <?php else: ?>
                                    <p class="text-2xl font-bold text-slate-400 leading-none">—</p>
                                    <p class="text-[11px] text-slate-400 mt-1"><?= safe_htmlspecialchars($naoInformado) ?></p>
                                <?php endif; ?>
                            </button>
                            <button type="button" class="aluno-kpi text-left" <?php if ($podeVidaEscolar): ?>onclick="selecionarAbaAluno('vida-escolar')"<?php endif; ?>>
                                <p class="text-xs text-slate-500 mb-1">Média geral</p>
                                <?php if ($kpiMedia !== null): ?>
                                    <p class="text-2xl font-bold text-slate-800 leading-none"><?= number_format($kpiMedia, 1, ',', '.') ?></p>
                                    <p class="text-[11px] text-slate-500 mt-1">Média das finais</p>
                                <?php else: ?>
                                    <p class="text-2xl font-bold text-slate-400 leading-none">—</p>
                                    <p class="text-[11px] text-slate-400 mt-1"><?= safe_htmlspecialchars($naoInformado) ?></p>
                                <?php endif; ?>
                            </button>
                            <button type="button" class="aluno-kpi text-left" onclick="abrirAbaAlunoComSub('pedagogico', 'ocorrencias')">
                                <p class="text-xs text-slate-500 mb-1">Ocorrências</p>
                                <p class="text-2xl font-bold <?= $qtdOcorrencias > 0 ? 'text-amber-600' : 'text-slate-800' ?> leading-none"><?= $qtdOcorrencias ?></p>
                                <p class="text-[11px] <?= $qtdOcorrencias > 0 ? 'text-amber-600' : 'text-slate-500' ?> mt-1"><?= $qtdOcorrencias > 0 ? 'Registradas' : 'Sem ocorrências' ?></p>
                            </button>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="xl:col-span-4 space-y-5">
            <section class="student-card">
                <div class="student-card-header flex items-center justify-between gap-2">
                    <div class="flex items-center gap-2">
                        <span class="w-8 h-8 rounded-lg bg-amber-50 text-amber-600 flex items-center justify-center"><i class="fa-solid fa-triangle-exclamation"></i></span>
                        <h3 class="text-base font-semibold text-slate-900">Pendências</h3>
                    </div>
                    <span class="inline-flex min-w-[1.5rem] h-6 px-2 items-center justify-center rounded-full text-xs font-semibold <?= $pendenciasCount > 0 ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' ?>">
                        <?= $pendenciasCount ?>
                    </span>
                </div>
                <div class="student-card-body">
                    <?php if (empty($pendenciasAluno)): ?>
                        <p class="text-sm text-slate-500">Nenhuma pendência no momento.</p>
                    <?php else: ?>
                        <ul class="space-y-2.5">
                            <?php foreach ($pendenciasAluno as $pend): ?>
                            <li class="text-sm text-slate-700 flex items-start gap-2">
                                <span class="mt-1.5 w-1.5 h-1.5 rounded-full bg-amber-500 shrink-0"></span>
                                <span><?= safe_htmlspecialchars($pend) ?></span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    <button type="button" onclick="selecionarAbaAluno('documentos')" data-perm-key="documentos_aluno" data-perm-action="visualizar" class="aluno-btn-outline w-full mt-4">
                        Ver documentos
                    </button>
                </div>
            </section>

            <section class="student-card">
                <div class="student-card-header">
                    <div class="flex items-center gap-2">
                        <span class="aluno-card-icon"><i class="fa-solid fa-bolt"></i></span>
                        <h3 class="text-base font-semibold text-slate-900">Ações rápidas</h3>
                    </div>
                </div>
                <div class="student-card-body pt-1">
                    <div class="divide-y divide-slate-100">
                        <a href="<?= URL ?>/admin/students/<?= $alunoIdVg ?>/acessar-como"
                           data-perm-key="acao_rapida_acessar_aluno" data-perm-action="visualizar"
                           class="w-full flex items-center gap-3 px-1 py-2.5 hover:bg-slate-50 rounded-lg">
                            <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-right-to-bracket"></i></span>
                            <span class="text-sm font-medium text-slate-800 flex-1 text-left">Acessar como aluno</span>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        </a>
                        <?php if ($matriculas_schema_ready): ?>
                        <button type="button" onclick="abrirModalMatricula()"
                                data-perm-key="matriculas_aluno" data-perm-action="cadastrar"
                                class="w-full flex items-center gap-3 px-1 py-2.5 hover:bg-slate-50 rounded-lg text-left">
                            <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-graduation-cap"></i></span>
                            <span class="text-sm font-medium text-slate-800 flex-1">Nova matrícula</span>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        </button>
                        <?php endif; ?>
                        <button type="button" onclick="abrirModalCadastrarPai(<?= $alunoIdVg ?>)"
                                data-perm-key="acao_rapida_cadastrar_responsavel" data-perm-action="cadastrar"
                                class="w-full flex items-center gap-3 px-1 py-2.5 hover:bg-slate-50 rounded-lg text-left">
                            <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-user-plus"></i></span>
                            <span class="text-sm font-medium text-slate-800 flex-1">Cadastrar responsável</span>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        </button>
                        <button type="button" onclick="abrirModalDoc('Declaracoes')"
                                data-perm-key="declaracoes_aluno" data-perm-action="visualizar"
                                class="w-full flex items-center gap-3 px-1 py-2.5 hover:bg-slate-50 rounded-lg text-left">
                            <span class="w-8 h-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center"><i class="fa-solid fa-file-lines"></i></span>
                            <span class="text-sm font-medium text-slate-800 flex-1">Gerar declaração</span>
                            <i class="fa-solid fa-chevron-right text-[10px] text-slate-400"></i>
                        </button>
                    </div>
                    <button type="button" onclick="abrirOffcanvasAcoesAluno(this)" class="aluno-link mt-3">
                        Ver todas as ações <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
            </section>
        </div>
    </div>
</div>
