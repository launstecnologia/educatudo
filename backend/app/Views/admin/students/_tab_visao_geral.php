<?php
$naoInformado = 'Não informado';
$enderecoResumo = $enderecoResumo ?? '';
$matriculaAtual = is_array($matriculaAtual ?? null) ? $matriculaAtual : null;
$pendenciasAluno = is_array($pendenciasAluno ?? null) ? $pendenciasAluno : [];
$pendenciasCount = (int) ($pendenciasCount ?? count($pendenciasAluno));
$atividadeRecente = is_array($atividadeRecente ?? null) ? $atividadeRecente : [];
$auditResumo = is_array($audit_logs ?? null) ? array_slice($audit_logs, 0, 2) : [];
$responsaveisResumo = array_slice($responsaveis_aluno, 0, 2);
$docsResumo = is_array($linhasDocResumo ?? null) ? $linhasDocResumo : [];
$totalEntregues = (int) ($totalEntregues ?? 0);
$totalChecklist = (int) ($totalChecklist ?? 0);
$pctDocs = $totalChecklist > 0 ? (int) round(($totalEntregues / $totalChecklist) * 100) : 0;
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
$alergiaTxt = trim((string) ($fc['alergias'] ?? ''));
$auditLabelsVg = [
    'CREATE_STUDENT' => 'Cadastro do aluno',
    'UPDATE_STUDENT' => 'Edição do cadastro',
    'DELETE_STUDENT' => 'Exclusão do aluno',
    'LINK_GUARDIAN' => 'Responsável vinculado',
    'UPDATE_GUARDIAN' => 'Responsável atualizado',
    'SAVE_STUDENT_DOCUMENT' => 'Documento salvo',
    'DELETE_STUDENT_DOCUMENT' => 'Documento removido',
    'DOWNLOAD_STUDENT_DOCUMENT' => 'Download de documento',
    'GENERATE_DECLARATION' => 'Declaração emitida',
    'VIEW_ADMIN' => 'Visualização do perfil',
];
$tiposTransporte = ['escolar' => 'Van/Ônibus escolar', 'publico' => 'Transporte público', 'proprio' => 'Próprio / familiar', 'a_pe' => 'A pé / bicicleta'];
$tipoTransporte = (string) ($fc['transporte_tipo'] ?? '');
$transporteTxt = !empty($fc['usa_transporte_escolar'])
    ? ('Utiliza' . (isset($tiposTransporte[$tipoTransporte]) ? ' · ' . $tiposTransporte[$tipoTransporte] : ''))
    : 'Não utiliza';
?>
<div class="space-y-5">
    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        <div class="xl:col-span-8 space-y-5">
            <section class="student-card">
                <div class="student-card-header flex items-center justify-between gap-3">
                    <div class="flex items-center gap-2">
                        <span class="aluno-card-icon"><i class="fa-regular fa-user"></i></span>
                        <h3 class="text-base font-semibold text-slate-900">Informações essenciais</h3>
                    </div>
                    <button type="button" onclick="selecionarAbaAluno('dados-pessoais')" class="aluno-link">
                        Ver dados completos <i class="fa-solid fa-chevron-right text-[10px]"></i>
                    </button>
                </div>
                <div class="student-card-body">
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-8 gap-y-4">
                        <div>
                            <dt class="student-field-label">CPF</dt>
                            <dd class="student-field-value font-normal"><?= safe_htmlspecialchars($cpfDisplay ?: null, $naoInformado) ?></dd>
                        </div>
                        <div>
                            <dt class="student-field-label">E-mail</dt>
                            <dd class="student-field-value font-normal break-all"><?= safe_htmlspecialchars($student['email'] ?? null, $naoInformado) ?></dd>
                        </div>
                        <div>
                            <dt class="student-field-label">Data de nascimento</dt>
                            <dd class="student-field-value font-normal"><?= safe_htmlspecialchars($dataNascDisplay ?: null, $naoInformado) ?></dd>
                        </div>
                        <div>
                            <dt class="student-field-label">Endereço</dt>
                            <dd class="student-field-value font-normal"><?= safe_htmlspecialchars($enderecoResumo !== '' ? $enderecoResumo : null, $naoInformado) ?></dd>
                        </div>
                        <div>
                            <dt class="student-field-label">Celular</dt>
                            <dd class="student-field-value font-normal"><?= safe_htmlspecialchars($celularDisplay ?: null, $naoInformado) ?></dd>
                        </div>
                        <div>
                            <dt class="student-field-label">Responsável principal</dt>
                            <dd class="student-field-value font-normal"><?= safe_htmlspecialchars($student['responsavel_nome'] ?? null, 'Sem responsável') ?></dd>
                        </div>
                    </dl>
                </div>
            </section>

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
                            </div>
                            <dl class="mt-4 space-y-2">
                                <div>
                                    <dt class="student-field-label">Período</dt>
                                    <dd class="text-sm text-slate-700"><?= $vigenciaTxt ?></dd>
                                </div>
                                <div>
                                    <dt class="student-field-label">Tipo de vínculo</dt>
                                    <dd class="text-sm text-slate-700"><?= safe_htmlspecialchars($vinculoAtual !== '' ? $vinculoAtual : null, $naoInformado) ?></dd>
                                </div>
                            </dl>
                        </div>
                        <div class="grid grid-cols-3 gap-2 lg:w-[22rem] shrink-0">
                            <div class="aluno-kpi">
                                <p class="text-xs text-slate-500 mb-1">Frequência</p>
                                <p class="text-2xl font-bold text-slate-400 leading-none">—</p>
                                <p class="text-[11px] text-slate-400 mt-1"><?= safe_htmlspecialchars($naoInformado) ?></p>
                            </div>
                            <div class="aluno-kpi">
                                <p class="text-xs text-slate-500 mb-1">Média geral</p>
                                <p class="text-2xl font-bold text-slate-400 leading-none">—</p>
                                <p class="text-[11px] text-slate-400 mt-1"><?= safe_htmlspecialchars($naoInformado) ?></p>
                            </div>
                            <div class="aluno-kpi">
                                <p class="text-xs text-slate-500 mb-1">Ocorrências</p>
                                <p class="text-2xl font-bold <?= $qtdOcorrencias > 0 ? 'text-amber-600' : 'text-slate-800' ?> leading-none"><?= $qtdOcorrencias ?></p>
                                <p class="text-[11px] <?= $qtdOcorrencias > 0 ? 'text-amber-600' : 'text-slate-500' ?> mt-1"><?= $qtdOcorrencias > 0 ? 'Registradas' : 'Sem ocorrências' ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="student-card">
                <div class="student-card-header">
                    <div class="flex items-center gap-2">
                        <span class="aluno-card-icon"><i class="fa-regular fa-clock"></i></span>
                        <h3 class="text-base font-semibold text-slate-900">Atividade recente</h3>
                    </div>
                </div>
                <div class="student-card-body">
                    <?php if (empty($atividadeRecente)): ?>
                        <p class="text-sm text-slate-500">Nenhuma atividade recente registrada.</p>
                    <?php else: ?>
                        <ul class="divide-y divide-slate-100">
                            <?php foreach ($atividadeRecente as $ev): ?>
                            <li class="py-2.5 first:pt-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-xs text-slate-500 whitespace-nowrap"><?= safe_htmlspecialchars($ev['quando'] ?? '') ?></span>
                                    <span class="inline-flex px-2 py-0.5 rounded text-[11px] font-semibold bg-blue-50 text-blue-700"><?= safe_htmlspecialchars($ev['tipo'] ?? '') ?></span>
                                    <span class="text-sm text-slate-800"><?= safe_htmlspecialchars($ev['descricao'] ?? '') ?></span>
                                </div>
                                <?php if (!empty($ev['usuario'])): ?>
                                <p class="text-xs text-slate-400 mt-1">Por <?= safe_htmlspecialchars($ev['usuario']) ?></p>
                                <?php endif; ?>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
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

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-5">
        <section class="student-card xl:col-span-8" data-perm-key="responsaveis_vinculados" data-perm-action="visualizar">
            <div class="student-card-header">
                <div class="flex items-center gap-2">
                    <span class="aluno-card-icon"><i class="fa-solid fa-user-group"></i></span>
                    <h3 class="text-base font-semibold text-slate-900">Responsáveis vinculados</h3>
                </div>
            </div>
            <div class="student-card-body">
                <?php if (empty($responsaveisResumo)): ?>
                    <p class="text-sm text-slate-500">Nenhum responsável vinculado.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($responsaveisResumo as $resp): ?>
                            <?php
                            $respNome = (string) ($resp['nome'] ?? '');
                            $respTelefone = (string) ($resp['telefone'] ?? $resp['celular'] ?? '');
                            $respFinanceiro = (int) ($resp['is_financeiro'] ?? 0) === 1;
                            ?>
                            <div class="flex items-start gap-3">
                                <div class="w-10 h-10 rounded-full bg-violet-100 text-violet-700 flex items-center justify-center text-sm font-bold flex-shrink-0">
                                    <?= safe_htmlspecialchars(responsavel_iniciais($respNome)) ?>
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <p class="text-sm font-semibold text-slate-900"><?= safe_htmlspecialchars($respNome, '-') ?></p>
                                        <?php if (!empty($resp['parentesco'])): ?>
                                        <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded-full bg-slate-100 text-slate-700"><?= safe_htmlspecialchars($resp['parentesco']) ?></span>
                                        <?php endif; ?>
                                        <?php if ($respFinanceiro): ?>
                                        <span class="inline-flex px-2 py-0.5 text-[11px] font-semibold rounded-full bg-violet-50 text-violet-700">Financeiro</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($respTelefone !== ''): ?>
                                    <p class="text-xs text-slate-500 mt-1"><?= safe_htmlspecialchars($respTelefone) ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($resp['pode_retirar'])): ?>
                                    <span class="inline-flex mt-1.5 px-2 py-0.5 text-[11px] font-semibold rounded-full bg-emerald-100 text-emerald-800">Pode retirar</span>
                                    <?php endif; ?>
                                </div>
                                <button type="button"
                                        class="aluno-link shrink-0 text-xs"
                                        data-responsavel="<?= htmlspecialchars(json_encode([
                                            'aluno_id' => $alunoIdVg,
                                            'responsavel_id' => (int) ($resp['id'] ?? 0),
                                            'nome' => $respNome,
                                            'email' => (string) ($resp['email'] ?? ''),
                                            'telefone' => (string) ($resp['telefone'] ?? ''),
                                            'cpf' => (string) ($resp['cpf'] ?? ''),
                                            'rg' => (string) ($resp['rg'] ?? ''),
                                            'celular' => (string) ($resp['celular'] ?? ''),
                                            'data_nascimento' => (string) ($resp['data_nascimento'] ?? ''),
                                            'endereco' => (string) ($resp['endereco'] ?? ''),
                                            'numero' => (string) ($resp['numero'] ?? ''),
                                            'complemento' => (string) ($resp['complemento'] ?? ''),
                                            'bairro' => (string) ($resp['bairro'] ?? ''),
                                            'cidade' => (string) ($resp['cidade'] ?? ''),
                                            'uf' => (string) ($resp['uf'] ?? ''),
                                            'cep' => (string) ($resp['cep'] ?? ''),
                                            'observacoes' => (string) ($resp['observacoes'] ?? ''),
                                            'is_financeiro' => $respFinanceiro ? 1 : 0,
                                            'ativo' => (int) ($resp['ativo'] ?? 1),
                                            'parentesco' => (string) ($resp['parentesco'] ?? ''),
                                            'profissao' => (string) ($resp['profissao'] ?? ''),
                                            'empresa' => (string) ($resp['empresa'] ?? ''),
                                            'pode_retirar' => (int) ($resp['pode_retirar'] ?? 0),
                                            'recebe_boletos' => (int) ($resp['recebe_boletos'] ?? 0),
                                            'recebe_boletim' => (int) ($resp['recebe_boletim'] ?? 0),
                                            'recebe_notificacoes' => (int) ($resp['recebe_notificacoes'] ?? 0),
                                            'responsavel_pedagogico' => (int) ($resp['responsavel_pedagogico'] ?? 0),
                                            'guarda_judicial' => (int) ($resp['guarda_judicial'] ?? 0),
                                            'assina_documentos' => (int) ($resp['assina_documentos'] ?? 0),
                                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>"
                                        onclick="abrirModalEditarResponsavel(JSON.parse(this.dataset.responsavel))">
                                    Ver perfil
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                <button type="button" onclick="selecionarAbaAluno('responsaveis')" class="aluno-link mt-4">
                    Ver todos os responsáveis <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>
            </div>
        </section>

        <section class="student-card xl:col-span-4" id="card-matricula-atual" data-perm-key="matriculas_aluno" data-perm-action="visualizar">
            <div class="student-card-header">
                <div class="flex items-center gap-2">
                    <span class="aluno-card-icon"><i class="fa-solid fa-graduation-cap"></i></span>
                    <h3 class="text-base font-semibold text-slate-900">Matrícula atual</h3>
                </div>
            </div>
            <div class="student-card-body">
                <?php if (!$matriculaAtual && $matriculaPendente): ?>
                    <p class="text-sm text-slate-500">Nenhuma matrícula ativa. Use Nova matrícula para vincular o aluno.</p>
                <?php else: ?>
                    <div class="flex flex-wrap items-center gap-2">
                        <p class="text-lg font-bold text-slate-900"><?= safe_htmlspecialchars($turmaAnoLabel) ?></p>
                        <span class="inline-flex px-2.5 py-0.5 text-xs font-semibold rounded-full <?= $matriculaBadgeClass ?>"><?= safe_htmlspecialchars($statusMatriculaVisual) ?></span>
                    </div>
                    <dl class="grid grid-cols-3 gap-3 mt-4">
                        <div>
                            <dt class="student-field-label">Vínculo</dt>
                            <dd class="text-sm text-slate-800"><?= safe_htmlspecialchars($vinculoAtual !== '' ? $vinculoAtual : null, $naoInformado) ?></dd>
                        </div>
                        <div>
                            <dt class="student-field-label">Entrada</dt>
                            <dd class="text-sm text-slate-800"><?= $matriculaAtual ? format_data_br($matriculaAtual['data_entrada'] ?? null) : safe_htmlspecialchars($naoInformado) ?></dd>
                        </div>
                        <div>
                            <dt class="student-field-label">Turno</dt>
                            <dd class="text-sm text-slate-800"><?= safe_htmlspecialchars($turnoAtual !== '' ? $turnoAtual : null, $naoInformado) ?></dd>
                        </div>
                    </dl>
                <?php endif; ?>
                <div class="flex flex-wrap gap-2 mt-4">
                    <button type="button" onclick="selecionarAbaAluno('matriculas')" class="aluno-btn-outline">Ver matrícula</button>
                    <button type="button" onclick="selecionarAbaAluno('matriculas'); setTimeout(function(){ if (typeof toggleHistoricoTurmas === 'function') { var b = document.getElementById('bloco-historico-turmas'); if (b && b.classList.contains('hidden')) toggleHistoricoTurmas(); } }, 50);" class="aluno-btn-outline">Histórico de turmas</button>
                </div>
            </div>
        </section>
    </div>

    <section class="student-card" data-perm-key="documentos_aluno" data-perm-action="visualizar">
        <div class="student-card-header flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0 flex-1">
                <div class="flex items-center gap-2">
                    <span class="aluno-card-icon"><i class="fa-regular fa-folder-open"></i></span>
                    <h3 class="text-base font-semibold text-slate-900"><?= $totalEntregues ?> de <?= $totalChecklist ?> documentos entregues</h3>
                </div>
                <div class="h-2 rounded-full bg-slate-100 overflow-hidden mt-3 max-w-md">
                    <div class="h-full bg-primary rounded-full" style="width: <?= max(0, min(100, $pctDocs)) ?>%"></div>
                </div>
            </div>
            <?php if (!empty($docCanEdit)): ?>
            <button type="button" onclick="selecionarAbaAluno('documentos'); setTimeout(function(){ if (typeof abrirModalDocumento === 'function') abrirModalDocumento(); }, 50);" class="aluno-btn-outline shrink-0">
                <i class="fa-solid fa-plus mr-2 text-gray-500"></i> Adicionar documento
            </button>
            <?php endif; ?>
        </div>
        <div class="student-card-body pt-2">
            <?php if (empty($docsResumo)): ?>
                <p class="text-sm text-slate-500">Nenhum documento no checklist.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase tracking-wide text-slate-400 border-b border-slate-100">
                                <th class="py-2 pr-3 font-semibold">Documento</th>
                                <th class="py-2 pr-3 font-semibold">Status</th>
                                <th class="py-2 pr-3 font-semibold">Arquivo</th>
                                <th class="py-2 pr-3 font-semibold">Atualização</th>
                                <th class="py-2 font-semibold text-right">Ação</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php foreach ($docsResumo as $linha): ?>
                                <?php
                                $row = $linha['row'] ?? null;
                                [$badgeLabel, $badgeClass] = $docStatusBadge($row['status'] ?? 'pendente');
                                $temArquivo = !empty($row['arquivo_key']);
                                $docId = (int) ($row['id'] ?? 0);
                                $atualizado = !empty($row['updated_at']) ? date('d/m/Y', strtotime((string) $row['updated_at'])) : '—';
                                $dataAttr = htmlspecialchars(json_encode([
                                    'doc_id' => $docId,
                                    'tipo' => $linha['tipo'] ?? '',
                                    'titulo' => (string) ($row['titulo'] ?? ''),
                                    'status' => (string) ($row['status'] ?? 'pendente'),
                                    'observacao' => (string) ($row['observacao'] ?? ''),
                                    'label' => $linha['label'],
                                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
                                ?>
                                <tr>
                                    <td class="py-2.5 pr-3 font-medium text-slate-800"><?= safe_htmlspecialchars($linha['label']) ?></td>
                                    <td class="py-2.5 pr-3"><span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                                    <td class="py-2.5 pr-3">
                                        <?php if ($temArquivo && $docId > 0): ?>
                                            <a href="<?= URL ?>/admin/students/<?= $alunoIdVg ?>/documentos/<?= $docId ?>/baixar" class="text-blue-600 hover:text-blue-800 text-xs" target="_blank" rel="noopener"><?= safe_htmlspecialchars($row['arquivo_nome'] ?? 'Arquivo') ?></a>
                                        <?php else: ?>
                                            <span class="text-xs text-slate-400">Sem arquivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2.5 pr-3 text-xs text-slate-500"><?= safe_htmlspecialchars($atualizado) ?></td>
                                    <td class="py-2.5 text-right">
                                        <?php if (!empty($docCanEdit)): ?>
                                        <button type="button" class="text-xs font-semibold text-blue-600 hover:text-blue-800" data-documento="<?= $dataAttr ?>" onclick="abrirModalDocumento(JSON.parse(this.dataset.documento))">Gerenciar</button>
                                        <?php elseif ($temArquivo && $docId > 0): ?>
                                        <a href="<?= URL ?>/admin/students/<?= $alunoIdVg ?>/documentos/<?= $docId ?>/baixar" class="text-xs font-semibold text-blue-600 hover:text-blue-800" target="_blank" rel="noopener">Visualizar</a>
                                        <?php else: ?>
                                        <button type="button" class="text-xs font-semibold text-blue-600 hover:text-blue-800" onclick="selecionarAbaAluno('documentos')">Ver</button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
            <button type="button" onclick="selecionarAbaAluno('documentos')" class="aluno-link mt-3">
                Ver todos os documentos <i class="fa-solid fa-chevron-right text-[10px]"></i>
            </button>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
        <section class="student-card">
            <div class="student-card-header">
                <div class="flex items-center gap-2">
                    <span class="aluno-card-icon"><i class="fa-regular fa-clipboard"></i></span>
                    <h3 class="text-base font-semibold text-slate-900">Resumo complementar</h3>
                </div>
            </div>
            <div class="student-card-body">
                <dl class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <dt class="student-field-label">Saúde</dt>
                        <dd class="text-sm text-slate-800">
                            Tipo sanguíneo <?= safe_htmlspecialchars($fcVal('tipo_sanguineo'), $naoInformado) ?>
                            <span class="block text-slate-500 mt-1"><?= $alergiaTxt !== '' ? safe_htmlspecialchars($alergiaTxt) : 'Nenhuma alergia conhecida' ?></span>
                        </dd>
                    </div>
                    <div>
                        <dt class="student-field-label">Contato de emergência</dt>
                        <dd class="text-sm text-slate-800">
                            <?= safe_htmlspecialchars($fcVal('contato_emergencia_nome'), $naoInformado) ?>
                            <?php if ($fcVal('contato_emergencia_telefone')): ?>
                                <span class="block text-slate-500 mt-1"><?= safe_htmlspecialchars($fcVal('contato_emergencia_telefone')) ?></span>
                            <?php endif; ?>
                        </dd>
                    </div>
                    <div>
                        <dt class="student-field-label">Transporte escolar</dt>
                        <dd class="text-sm text-slate-800"><?= safe_htmlspecialchars($transporteTxt) ?></dd>
                    </div>
                </dl>
                <button type="button" onclick="selecionarAbaAluno('saude')" class="aluno-link mt-4">
                    Ver ficha completa <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </button>
            </div>
        </section>

        <section class="student-card">
            <div class="student-card-header">
                <div class="flex items-center gap-2">
                    <span class="aluno-card-icon"><i class="fa-solid fa-shield-halved"></i></span>
                    <h3 class="text-base font-semibold text-slate-900">Histórico de auditoria</h3>
                </div>
            </div>
            <div class="student-card-body">
                <?php if (empty($auditResumo)): ?>
                    <p class="text-sm text-slate-500">Nenhuma alteração sensível recente.</p>
                <?php else: ?>
                    <ul class="aluno-timeline space-y-4">
                        <?php foreach ($auditResumo as $log): ?>
                            <?php
                            $code = (string) ($log['action'] ?? '');
                            $lblAud = $auditLabelsVg[$code] ?? $code;
                            $quando = !empty($log['created_at']) ? date('d/m/Y H:i', strtotime((string) $log['created_at'])) : '';
                            $papel = trim((string) ($log['user_role'] ?? ''));
                            ?>
                            <li class="relative">
                                <span class="aluno-timeline-dot"></span>
                                <p class="text-sm font-medium text-slate-800"><?= safe_htmlspecialchars($lblAud) ?></p>
                                <p class="text-xs text-slate-500 mt-0.5">
                                    <?= safe_htmlspecialchars($quando) ?>
                                    <?php if ($papel !== ''): ?> · Por <?= safe_htmlspecialchars(ucfirst($papel)) ?><?php endif; ?>
                                </p>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
                <a href="<?= URL ?>/admin/students/<?= $alunoIdVg ?>/auditoria" class="aluno-btn-outline w-full mt-4">Ver histórico</a>
            </div>
        </section>
    </div>

    <?php include __DIR__ . '/_relatorio_detalhado.php'; ?>
</div>
