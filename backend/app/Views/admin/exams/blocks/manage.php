<?php
/**
 * Gerenciar Provas de um Bloco
 * Acesso: Coordenação
 */
$modoLancamentoNota = !empty($modo_lancamento_nota);
$lancamentoPorCoordenacao = $modoLancamentoNota && (($bloco['configuracao_nota'] ?? '') === 'coordenacao_calcula');
$notaUnicaTodasMaterias = $lancamentoPorCoordenacao && !empty($bloco['nota_unica_todas_materias']);
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                <?php if ($modoLancamentoNota): ?>
                    Lançamento de notas: <?= htmlspecialchars($bloco['titulo']) ?>
                <?php else: ?>
                    Gerenciar Provas: <?= htmlspecialchars($bloco['titulo']) ?>
                <?php endif; ?>
            </h2>
            <?php if (!$modoLancamentoNota): ?>
            <p class="text-gray-600">
                Status: <span class="font-semibold"><?= ucfirst(str_replace('_', ' ', $bloco['status'])) ?></span>
                <?php if ($bloco['prazo_entrega_professor']): ?>
                    | Prazo: <?= date('d/m/Y H:i', strtotime($bloco['prazo_entrega_professor'])) ?>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
        <div class="flex flex-wrap gap-2 justify-end">
            <?php if ($modoLancamentoNota && $lancamentoPorCoordenacao): ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/lancar-notas-coordenacao"
               class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2 rounded-lg hover:opacity-90">
                <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                Lançar notas (coordenação)
            </a>
            <?php endif; ?>
            <?php if ($modoLancamentoNota): ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/resultados"
               class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Relatório de notas
            </a>
            <?php else: ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/resultados-novos"
               class="inline-flex items-center gap-2 bg-indigo-600 text-white px-4 py-2 rounded-lg hover:bg-indigo-700">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Resultados
            </a>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/canceladas"
               class="inline-flex items-center gap-2 bg-amber-600 text-white px-4 py-2 rounded-lg hover:bg-amber-700 <?= !empty($total_canceladas) ? 'ring-2 ring-amber-300' : '' ?>">
                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                Cancelados<?= !empty($total_canceladas) ? ' (' . (int)$total_canceladas . ')' : '' ?>
            </a>
            <?php if (!empty($bloco['gabarito_liberado'])): ?>
            <span class="inline-flex items-center gap-2 bg-emerald-100 text-emerald-800 px-4 py-2 rounded-lg font-medium">
                <i class="fa-solid fa-unlock-keyhole" aria-hidden="true"></i>
                Gabarito liberado
            </span>
            <?php else: ?>
            <form method="post" action="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/liberar-gabarito" class="inline"
                  onsubmit="return confirm('Liberar o gabarito deste bloco para todos os alunos?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($csrf_token ?? '')) ?>">
                <input type="hidden" name="origem" value="gerenciar">
                <button type="submit" class="inline-flex items-center gap-2 bg-emerald-600 text-white px-4 py-2 rounded-lg hover:bg-emerald-700">
                    <i class="fa-solid fa-unlock-keyhole" aria-hidden="true"></i>
                    Liberar gabarito
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            <?php if (!$modoLancamentoNota): ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/visualizar-completo"
               class="inline-flex items-center gap-2 bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                Prova Completa
            </a>
            <?php endif; ?>
            <?php if (!$modoLancamentoNota && isset($mostrarBotaoAprovacaoFinal) && $mostrarBotaoAprovacaoFinal): ?>
            <button onclick="aprovarBlocoFinal(<?= $bloco['id'] ?>)" 
                    class="inline-flex items-center gap-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                Aprovação Final
            </button>
            <?php endif; ?>
            <a href="<?= URL ?>/admin/provas" 
               class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">
                Voltar
            </a>
        </div>
    </div>
</div>

<?php if ($modoLancamentoNota && !empty($flash_importacao_notas['message'])): ?>
<?php
$flashTipo = (string) ($flash_importacao_notas['type'] ?? 'info');
$flashClasses = [
    'success' => 'bg-green-50 border-green-200 text-green-800',
    'error' => 'bg-red-50 border-red-200 text-red-800',
    'warning' => 'bg-amber-50 border-amber-200 text-amber-900',
    'info' => 'bg-blue-50 border-blue-200 text-blue-800',
];
?>
<div class="mb-6 rounded-lg border px-4 py-3 text-sm <?= $flashClasses[$flashTipo] ?? $flashClasses['info'] ?>" role="status">
    <?= htmlspecialchars((string) $flash_importacao_notas['message']) ?>
</div>
<?php endif; ?>

<?php if ($modoLancamentoNota): ?>
<!-- Painel lançamento de notas -->
<?php
$desc = is_array($evento_descricao ?? null) ? $evento_descricao : [];
$destinosQuadro = is_array($desc['destinos_quadro'] ?? null) ? $desc['destinos_quadro'] : [];
$statusMap = [
    'aguardando' => 'Aguardando',
    'aprovado' => 'Aprovado',
    'liberado' => 'Liberado',
    'concluido' => 'Concluído',
];
$statusTexto = $statusMap[(string) ($desc['status'] ?? '')] ?? ucfirst(str_replace('_', ' ', (string) ($desc['status'] ?? $bloco['status'] ?? '')));
$dataTxt = !empty($desc['data_prova']) ? date('d/m/Y', strtotime((string) $desc['data_prova'])) : '';
$horaIni = !empty($desc['hora_inicio']) ? substr((string) $desc['hora_inicio'], 0, 5) : '';
$horaFim = !empty($desc['hora_fim']) ? substr((string) $desc['hora_fim'], 0, 5) : '';
$horarioTxt = ($horaIni !== '' || $horaFim !== '') ? trim($horaIni . ($horaFim !== '' ? ' – ' . $horaFim : '')) : '';
$prazoTxt = !empty($desc['prazo_professor']) ? date('d/m/Y H:i', strtotime((string) $desc['prazo_professor'])) : '';
$turmasTxt = !empty($desc['turmas']) ? implode(', ', $desc['turmas']) : '—';
$materiasTxt = !empty($desc['materias']) ? implode(', ', $desc['materias']) : '—';
$tipoTxt = trim((string) ($desc['tipo_nota'] ?? ''));
$periodoTxt = trim((string) ($desc['periodo_texto'] ?? ''));
$anoTxt = (int) ($desc['ano_letivo'] ?? 0);
?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <div class="flex items-start gap-3 mb-4">
        <span class="inline-flex items-center justify-center w-10 h-10 rounded-full bg-indigo-100 text-indigo-700 shrink-0">
            <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
        </span>
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Detalhes do evento</h3>
            <p class="text-sm text-gray-600 mt-0.5">Resumo completo do lançamento: período, tipo de nota, destino no quadro e turmas.</p>
        </div>
    </div>
    <dl class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 text-sm">
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Ano letivo</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= $anoTxt > 0 ? (int) $anoTxt : '—' ?></dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Período / bimestre</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= $periodoTxt !== '' ? htmlspecialchars($periodoTxt) : '—' ?></dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Tipo de nota</dt>
            <dd class="mt-1 font-semibold text-gray-900">
                <?= $tipoTxt !== '' ? htmlspecialchars($tipoTxt) : '—' ?>
                <?php if (!empty($desc['tipo_chave'])): ?>
                    <span class="ml-1 text-xs font-normal text-gray-500">(<?= htmlspecialchars((string) $desc['tipo_chave']) ?>)</span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3 sm:col-span-2 lg:col-span-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Destino no quadro (bloco / semana)</dt>
            <dd class="mt-2 flex flex-wrap gap-1.5">
                <?php if ($destinosQuadro === []): ?>
                    <span class="text-gray-500">Sem vínculo de quadro (S1, Bloco A/B etc.).</span>
                <?php else: ?>
                    <?php foreach ($destinosQuadro as $destino): ?>
                        <?php if (trim((string) ($destino['bloco'] ?? '')) !== ''): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-800"><?= htmlspecialchars((string) $destino['bloco']) ?></span>
                        <?php endif; ?>
                        <?php if (trim((string) ($destino['semana'] ?? '')) !== ''): ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-sky-100 text-sky-800"><?= htmlspecialchars((string) $destino['semana']) ?></span>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Formato</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars((string) ($desc['formato'] ?? 'Lançamento de notas')) ?></dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Quem lança a nota</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars((string) ($desc['quem_lanca'] ?? 'Professor')) ?> <span class="font-normal text-gray-600">(0 a 10)</span></dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Nota única para todas as matérias</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= !empty($desc['nota_unica']) ? 'Sim' : 'Não' ?></dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Status</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($statusTexto) ?></dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Data / horário</dt>
            <dd class="mt-1 font-semibold text-gray-900">
                <?= $dataTxt !== '' ? htmlspecialchars($dataTxt) : '—' ?>
                <?php if ($horarioTxt !== ''): ?>
                    <span class="font-normal text-gray-600"> · <?= htmlspecialchars($horarioTxt) ?></span>
                <?php endif; ?>
            </dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Prazo do professor</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= $prazoTxt !== '' ? htmlspecialchars($prazoTxt) : '—' ?></dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3 sm:col-span-2">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Turmas</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($turmasTxt) ?></dd>
        </div>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3 sm:col-span-2 lg:col-span-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Matérias deste evento</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars($materiasTxt) ?></dd>
        </div>
        <?php if (!empty($desc['criado_por'])): ?>
        <div class="rounded-lg bg-slate-50 border border-slate-100 p-3">
            <dt class="text-xs font-medium text-gray-500 uppercase tracking-wide">Criado por</dt>
            <dd class="mt-1 font-semibold text-gray-900"><?= htmlspecialchars((string) $desc['criado_por']) ?></dd>
        </div>
        <?php endif; ?>
    </dl>
</div>

<?php if (isset($contagem)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-gray-400">
        <p class="text-sm text-gray-600">Professor sem notas lançadas</p>
        <p class="text-3xl font-bold text-gray-900"><?= (int)($contagem['ln_nao_iniciado'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-amber-500">
        <p class="text-sm text-gray-600">Em andamento (parcial)</p>
        <p class="text-3xl font-bold text-gray-900"><?= (int)($contagem['ln_em_andamento'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <p class="text-sm text-gray-600">Concluído (todos os alunos)</p>
        <p class="text-3xl font-bold text-gray-900"><?= (int)($contagem['ln_concluido'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <p class="text-sm text-gray-600">Notas abaixo de 6 (linhas)</p>
        <p class="text-3xl font-bold text-gray-900"><?= (int)($contagem['ln_abaixo_seis'] ?? 0) ?></p>
    </div>
</div>
<?php endif; ?>

<?php if ($notaUnicaTodasMaterias): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border-l-4 border-violet-500">
    <h3 class="text-lg font-semibold text-gray-900">Nota única para todas as matérias</h3>
    <p class="text-sm text-gray-600 mt-2">Neste evento, a coordenação lança uma única nota por aluno e o sistema replica automaticamente para todas as matérias.</p>
    <div class="mt-4">
        <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/lancar-notas-coordenacao"
           class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg hover:opacity-90">
            Lançar/editar nota única dos alunos
        </a>
    </div>
</div>
<?php elseif (empty($lancamentoPorMateria)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-900 mb-6">
    Nenhum professor/matéria vinculado a este evento. Edite o bloco e adicione professores com turmas.
</div>
<?php else: ?>
<?php foreach ($lancamentoPorMateria as $materiaNome => $linhas): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4"><?= htmlspecialchars($materiaNome) ?></h3>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progresso</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($linhas as $row): ?>
                <tr>
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['professor_nome'] ?? '') ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?= (int)($row['com_nota'] ?? 0) ?> / <?= (int)($row['total_esperado'] ?? 0) ?>
                        <?php if (($row['total_esperado'] ?? 0) > 0): ?>
                            <span class="text-gray-500">(<?= htmlspecialchars((string)($row['perc'] ?? '')) ?>%)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $st = $row['status'] ?? '';
                        $map = [
                            'nao_iniciado' => ['bg-red-100 text-red-800', 'Não iniciou'],
                            'em_andamento' => ['bg-amber-100 text-amber-800', 'Em andamento'],
                            'concluido' => ['bg-green-100 text-green-800', 'Concluído'],
                            'sem_alunos' => ['bg-gray-100 text-gray-700', 'Sem alunos nas turmas'],
                        ];
                        $pair = $map[$st] ?? ['bg-gray-100 text-gray-800', $st];
                        ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $pair[0] ?>"><?= htmlspecialchars($pair[1]) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <?php if ($lancamentoPorCoordenacao): ?>
                            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/lancar-notas-coordenacao?materia_id=<?= (int)($row['materia_id'] ?? 0) ?>"
                               class="text-violet-700 hover:text-violet-900 font-medium">Lançar/editar notas</a>
                        <?php else: ?>
                            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/notas-lancadas?professor_id=<?= (int)($row['professor_id'] ?? 0) ?>&materia_id=<?= (int)($row['materia_id'] ?? 0) ?>"
                               class="text-indigo-600 hover:text-indigo-900 font-medium">Ver notas dos alunos</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<?php else: ?>

<!-- Cards de Indicador (padrão: borda esquerda, label, valor, ícone circular) -->
<?php if (isset($contagem)): ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Em Andamento</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['em_andamento'] ?? 0 ?></p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-yellow-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Enviadas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['enviada'] ?? 0 ?></p>
            </div>
            <div class="bg-yellow-100 rounded-full p-3">
                <svg class="w-8 h-8 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-red-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Não Enviadas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['nao_enviada'] ?? 0 ?></p>
            </div>
            <div class="bg-red-100 rounded-full p-3">
                <svg class="w-8 h-8 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Aprovadas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['aprovada'] ?? 0 ?></p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-amber-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Retornado Professor</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['retornada'] ?? 0 ?></p>
            </div>
            <div class="bg-amber-100 rounded-full p-3">
                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-orange-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Provas Excluídas</p>
                <p class="text-3xl font-bold text-gray-900"><?= $contagem['reprovada'] ?? 0 ?></p>
            </div>
            <div class="bg-orange-100 rounded-full p-3">
                <svg class="w-8 h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                </svg>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Filtros -->
<?php $statusFiltro = $status_filtro ?? $_GET['status'] ?? ''; ?>
<div class="bg-white rounded-xl shadow-lg p-4 mb-6">
    <div class="flex flex-wrap gap-2">
        <a href="?" 
           class="px-4 py-2 <?= $statusFiltro === '' ? 'bg-gray-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?> rounded-lg transition-colors">
            Todas
        </a>
        <a href="?status=enviada" 
           class="px-4 py-2 <?= $statusFiltro === 'enviada' ? 'bg-yellow-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-yellow-600 hover:text-white' ?> rounded-lg transition-colors">
            Enviadas
        </a>
        <a href="?status=nao_enviada" 
           class="px-4 py-2 <?= $statusFiltro === 'nao_enviada' ? 'bg-red-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-red-600 hover:text-white' ?> rounded-lg transition-colors">
            Não Enviadas
        </a>
        <a href="?status=retornada" 
           class="px-4 py-2 <?= $statusFiltro === 'retornada' ? 'bg-amber-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-amber-600 hover:text-white' ?> rounded-lg transition-colors">
            Retornado Professor
        </a>
        <a href="?status=excluido" 
           class="px-4 py-2 <?= $statusFiltro === 'excluido' ? 'bg-orange-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-orange-600 hover:text-white' ?> rounded-lg transition-colors">
            Excluído
        </a>
    </div>
</div>

<!-- Provas Agrupadas por Matéria -->
<?php foreach ($provasPorMateria as $materiaNome => $provasMateria): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-4"><?= htmlspecialchars($materiaNome) ?></h3>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data Envio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Questões</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($provasMateria as $prova): ?>
                <tr>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($prova['professor_nome']) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        // Mesmos rótulos dos cards da tela: Em Andamento, Enviadas, Não Enviadas, Aprovadas, Provas Excluídas
                        $statusClasses = [
                            'nao_avaliada' => 'bg-yellow-100 text-yellow-800',
                            'aprovado' => 'bg-green-100 text-green-800',
                            'em_andamento' => 'bg-blue-100 text-blue-800',
                            'concluido' => 'bg-purple-100 text-purple-800',
                            'reprovada' => 'bg-orange-100 text-orange-800',
                            'nao_enviada' => 'bg-red-100 text-red-800',
                            'retornada' => 'bg-amber-100 text-amber-800',
                            'pendente' => 'bg-amber-100 text-amber-800'
                        ];
                        $statusLabels = [
                            'nao_avaliada' => 'Enviada',
                            'aprovado' => 'Aprovada',
                            'em_andamento' => 'Em Andamento',
                            'concluido' => 'Concluída',
                            'reprovada' => 'Prova excluída',
                            'nao_enviada' => 'Não Enviada',
                            'retornada' => 'Retornada ao professor',
                            'pendente' => 'Aguardando envio'
                        ];
                        $statusExibicao = $prova['status'] ?? 'nao_enviada';
                        $statusClass = $statusClasses[$statusExibicao] ?? 'bg-gray-100 text-gray-800';
                        $statusLabel = $statusLabels[$statusExibicao] ?? $statusExibicao;
                        ?>
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>">
                            <?= $statusLabel ?>
                        </span>
                        <?php if (($prova['status'] ?? '') === 'retornada' && !empty($prova['observacao_coordenacao'])): ?>
                        <div class="text-xs text-amber-700 mt-1 max-w-md" title="<?= htmlspecialchars($prova['observacao_coordenacao']) ?>">
                            <?= htmlspecialchars(mb_substr($prova['observacao_coordenacao'], 0, 60)) ?><?= mb_strlen($prova['observacao_coordenacao']) > 60 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                        <?php if ($prova['travada'] ?? false): ?>
                            <span class="ml-2 text-red-600" title="Travada">🔒</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= $prova['data_envio'] ? date('d/m/Y H:i', strtotime($prova['data_envio'])) : '-' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= $prova['numero_questoes'] ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                        <?php if ($prova['prova_id']): ?>
                            <a href="<?= URL ?>/admin/provas/visualizar/<?= $prova['prova_id'] ?>" 
                               class="text-blue-600 hover:text-blue-900 mr-3">
                                Ver Prova
                            </a>
                            <?php if (!empty($prova['professor_id']) && !empty($prova['materia_id'])): ?>
                                <button type="button" 
                                        onclick="abrirModalTrocar(<?= (int)$bloco['id'] ?>, <?= (int)$prova['professor_id'] ?>, <?= (int)$prova['materia_id'] ?>, '<?= htmlspecialchars(addslashes($prova['professor_nome'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($prova['materia_nome'] ?? '')) ?>', <?= (int)$prova['prova_id'] ?>)"
                                        class="text-amber-600 hover:text-amber-900 font-medium">
                                    Trocar prova
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php 
                        // Mostra botões de aprovar/reprovar para provas enviadas OU ainda pendentes/agendadas (coordenação pode aprovar mesmo sem envio)
                        $statusOriginalAcoes = $prova['status_original'] ?? '';
                        $podeAprovarReprovar = in_array($statusOriginalAcoes, ['enviada', 'aguardando_aprovacao', 'pendente', 'agendada']) && $prova['prova_id'];
                        if ($podeAprovarReprovar): ?>
                            <button onclick="aprovarProva(<?= $prova['prova_id'] ?>)" 
                                    class="text-green-600 hover:text-green-900 mr-3">
                                ✅ Aprovar
                            </button>
                            <button onclick="reprovarProva(<?= $prova['prova_id'] ?>)" 
                                    class="text-red-600 hover:text-red-900">
                                ❌ Reprovar
                            </button>
                        <?php elseif ($prova['status'] === 'nao_enviada'): ?>
                            <?php if (!empty($prova['professor_id']) && !empty($prova['materia_id'])): ?>
                                <button type="button" 
                                        onclick="abrirModalVincular(<?= (int)$bloco['id'] ?>, <?= (int)$prova['professor_id'] ?>, <?= (int)$prova['materia_id'] ?>, '<?= htmlspecialchars(addslashes($prova['professor_nome'] ?? '')) ?>', '<?= htmlspecialchars(addslashes($prova['materia_nome'] ?? '')) ?>')"
                                        class="text-indigo-600 hover:text-indigo-900 font-medium">
                                    🔗 Vincular prova
                                </button>
                            <?php else: ?>
                                <span class="text-gray-400 italic">Aguardando criação da prova</span>
                            <?php endif; ?>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endforeach; ?>

<?php endif; ?>

<?php if (!$modoLancamentoNota): ?>
<!-- Modal Vincular Prova -->
<div id="modalVincularProva" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-labelledby="modal-title" role="dialog" aria-modal="true">
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:p-0">
        <div class="fixed inset-0 bg-gray-500 bg-opacity-75 transition-opacity" aria-hidden="true" onclick="fecharModalVincular()"></div>
        <div class="relative inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle bg-white rounded-xl shadow-xl">
            <h3 class="text-lg font-semibold text-gray-900" id="modalVincularTitulo">Vincular prova</h3>
            <p class="mt-1 text-sm text-gray-500" id="modalVincularSubtitulo"></p>
            <div id="modalVincularLista" class="mt-4 max-h-64 overflow-y-auto">
                <p class="text-gray-500">Carregando...</p>
            </div>
            <div id="modalVincularVazio" class="mt-4 hidden">
                <p class="text-amber-700">Nenhuma prova disponível para vincular (provas do professor nesta matéria que ainda não estão em outro bloco).</p>
            </div>
            <div class="flex justify-end gap-2 mt-4">
                <button type="button" onclick="fecharModalVincular()" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
var modalVincularBlocoId = null;
var modalVincularProvaAtualId = null;

function abrirModalVincular(blocoId, professorId, materiaId, professorNome, materiaNome) {
    modalVincularProvaAtualId = null;
    abrirModalVincularOuTrocar(blocoId, professorId, materiaId, professorNome, materiaNome, null);
}

function abrirModalTrocar(blocoId, professorId, materiaId, professorNome, materiaNome, provaAtualId) {
    modalVincularProvaAtualId = provaAtualId || null;
    abrirModalVincularOuTrocar(blocoId, professorId, materiaId, professorNome, materiaNome, provaAtualId);
}

function abrirModalVincularOuTrocar(blocoId, professorId, materiaId, professorNome, materiaNome, provaAtualId) {
    var isTrocar = !!provaAtualId;
    modalVincularBlocoId = blocoId;
    document.getElementById('modalVincularTitulo').textContent = isTrocar ? 'Trocar prova' : 'Vincular prova';
    document.getElementById('modalVincularSubtitulo').textContent = professorNome + ' – ' + materiaNome;
    document.getElementById('modalVincularLista').innerHTML = '<p class="text-gray-500">Carregando...</p>';
    document.getElementById('modalVincularLista').classList.remove('hidden');
    document.getElementById('modalVincularVazio').classList.add('hidden');
    document.getElementById('modalVincularProva').classList.remove('hidden');
    document.getElementById('modalVincularVazio').querySelector('p').textContent = isTrocar
        ? 'Nenhuma outra prova disponível para colocar no lugar (provas do professor nesta matéria que ainda não estão em outro bloco).'
        : 'Nenhuma prova disponível para vincular (provas do professor nesta matéria que ainda não estão em outro bloco).';
    fetch('<?= URL ?>/admin/provas/blocos/' + blocoId + '/provas-disponiveis?professor_id=' + encodeURIComponent(professorId) + '&materia_id=' + encodeURIComponent(materiaId))
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var lista = document.getElementById('modalVincularLista');
            var vazio = document.getElementById('modalVincularVazio');
            if (!data.provas || data.provas.length === 0) {
                lista.classList.add('hidden');
                vazio.classList.remove('hidden');
                return;
            }
            var html = '<ul class="space-y-2">';
            data.provas.forEach(function(p) {
                var dataEnvio = p.data_envio ? new Date(p.data_envio).toLocaleDateString('pt-BR') : '-';
                var turmaTexto = (p.turma_nome && p.turma_nome.trim()) ? ' · ' + (p.turma_nome.trim()) : '';
                var btnLabel = isTrocar ? 'Trocar' : 'Vincular';
                var btnOnclick = isTrocar
                    ? 'trocarProvaSubmit(' + blocoId + ',' + provaAtualId + ',' + p.id + ')'
                    : 'vincularProvaSubmit(' + blocoId + ',' + p.id + ')';
                var urlVer = '<?= URL ?>/admin/provas/visualizar/' + p.id;
                html += '<li class="flex items-center justify-between p-3 border rounded-lg hover:bg-gray-50">';
                html += '<div class="flex items-center gap-3">';
                html += '<span class="shrink-0 px-2 py-0.5 text-xs font-mono font-semibold rounded bg-gray-200 text-gray-700" title="ID da prova">#' + (p.id || '') + '</span>';
                html += '<div><span class="font-medium">' + (p.titulo || 'Prova #' + p.id) + '</span><span class="text-sm text-gray-500 ml-2">' + (p.numero_questoes || 0) + ' questões · ' + dataEnvio + turmaTexto + '</span></div>';
                html += '</div>';
                html += '<div class="flex items-center gap-2 shrink-0">';
                html += '<a href="' + urlVer + '" target="_blank" rel="noopener" class="px-3 py-1 text-sm text-gray-700 bg-gray-100 rounded hover:bg-gray-200">Visualizar</a>';
                html += '<button type="button" onclick="' + btnOnclick + '" class="px-3 py-1 text-sm bg-indigo-600 text-white rounded hover:bg-indigo-700">' + btnLabel + '</button>';
                html += '</div></li>';
            });
            html += '</ul>';
            lista.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('modalVincularLista').innerHTML = '<p class="text-red-600">Erro ao carregar provas.</p>';
        });
}

function fecharModalVincular() {
    document.getElementById('modalVincularProva').classList.add('hidden');
}

function vincularProvaSubmit(blocoId, provaId) {
    if (!confirm('Vincular esta prova ao bloco? Ela voltará a aparecer como enviada/aprovada neste evento.')) return;
    fetch('<?= URL ?>/admin/provas/blocos/' + blocoId + '/vincular', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({ prova_id: provaId, _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?> })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.message || 'Prova vinculada.');
            fecharModalVincular();
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível vincular'));
        }
    })
    .catch(function(e) {
        alert('Erro de conexão: ' + e.message);
    });
}

function trocarProvaSubmit(blocoId, provaAtualId, novaProvaId) {
    if (!confirm('Trocar a prova vinculada por esta? A prova atual será desvinculada do bloco.')) return;
    fetch('<?= URL ?>/admin/provas/blocos/' + blocoId + '/trocar-prova', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            prova_atual_id: provaAtualId,
            nova_prova_id: novaProvaId,
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            alert(data.message || 'Prova trocada.');
            fecharModalVincular();
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível trocar'));
        }
    })
    .catch(function(e) {
        alert('Erro de conexão: ' + e.message);
    });
}

function aprovarProva(provaId) {
    if (!confirm('Deseja aprovar esta avaliação? O professor não poderá mais editar. A prova só será liberada para os alunos na aprovação final do bloco.')) {
        return;
    }
    
    fetch(`<?= URL ?>/admin/provas/liberar/${provaId}`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(async response => {
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Resposta do servidor não é JSON válido: ' + text.substring(0, 200));
        }
        return response.json();
    })
    .then(data => {
        if (data.success) {
            alert('Prova aprovada com sucesso! O professor não poderá mais editar. Libere para os alunos na aprovação final do bloco.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao aprovar prova'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

function reprovarProva(provaId) {
    if (!confirm('Deseja reprovar esta prova? A prova será marcada como não aprovada.')) {
        return;
    }
    
    // Atualiza status da prova para 'rascunho' e remove liberação
    fetch(`<?= URL ?>/admin/provas/${provaId}/reprovar`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Prova reprovada.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao reprovar prova'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

function aprovarBlocoFinal(blocoId) {
    if (!confirm('Deseja fazer a aprovação final do bloco? Isso irá liberar todas as provas aprovadas para os alunos quando estiver na data e horário corretos.')) {
        return;
    }
    
    fetch(`<?= URL ?>/admin/provas/blocos/${blocoId}/aprovar-final`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Bloco aprovado e liberado com sucesso! As provas estarão disponíveis para os alunos na data e horário programados.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao aprovar bloco'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
        console.error(error);
    });
}

function editarObsCoordenacao(provaId, obsAtual) {
    var novoTexto = prompt('Observação da coordenação para esta prova/matéria:', obsAtual || '');
    if (novoTexto === null) return;
    fetch(`<?= URL ?>/admin/provas/${provaId}/observacao-coordenacao`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        },
        body: JSON.stringify({
            observacao_coordenacao: novoTexto,
            _token: <?= json_encode($_SESSION['csrf_token'] ?? '') ?>
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Observação salva com sucesso.');
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Não foi possível salvar a observação.'));
        }
    })
    .catch(error => {
        alert('Erro de conexão: ' + error.message);
    });
}
</script>
<?php endif; ?>
