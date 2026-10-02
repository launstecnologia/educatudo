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
            <button type="button" onclick="openDetalhesEventoDrawer()"
                    class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                Detalhes
            </button>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/exportar-notas-excel"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                Exportar Excel
            </a>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/resultados"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Relatório de notas
            </a>
            <?php else: ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/resultados-novos"
               class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2 rounded-lg hover:opacity-90">
                <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                Resultados
            </a>
            <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/canceladas"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50 <?= !empty($total_canceladas) ? 'ring-2 ring-gray-300' : '' ?>">
                <i class="fa-solid fa-ban" aria-hidden="true"></i>
                Cancelados<?= !empty($total_canceladas) ? ' (' . (int)$total_canceladas . ')' : '' ?>
            </a>
            <?php if (!empty($bloco['gabarito_liberado'])): ?>
            <span class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 text-sm font-medium">
                Gabarito liberado
            </span>
            <?php else: ?>
            <form method="post" action="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/liberar-gabarito" class="inline"
                  onsubmit="return confirm('Liberar o gabarito deste bloco para todos os alunos?');">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string)($csrf_token ?? '')) ?>">
                <input type="hidden" name="origem" value="gerenciar">
                <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                    Liberar gabarito
                </button>
            </form>
            <?php endif; ?>
            <?php endif; ?>
            <?php if (!$modoLancamentoNota): ?>
            <a href="<?= URL ?>/admin/provas/blocos/<?= $bloco['id'] ?>/visualizar-completo"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                Prova Completa
            </a>
            <?php endif; ?>
            <?php if (!$modoLancamentoNota && isset($mostrarBotaoAprovacaoFinal) && $mostrarBotaoAprovacaoFinal): ?>
            <button onclick="aprovarBlocoFinal(<?= $bloco['id'] ?>)"
                    class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2 rounded-lg hover:opacity-90">
                <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                Aprovação Final
            </button>
            <?php endif; ?>
            <a href="<?= URL ?>/admin/provas"
               class="inline-flex items-center gap-2 px-4 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50">
                ← Voltar
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

<?php if (isset($contagem)): ?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-500">Sem notas lançadas</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int)($contagem['ln_nao_iniciado'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-500">Em andamento</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int)($contagem['ln_em_andamento'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-500">Concluído</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int)($contagem['ln_concluido'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 px-4 py-3">
        <p class="text-xs text-gray-500">Notas abaixo de 6</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int)($contagem['ln_abaixo_seis'] ?? 0) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Offcanvas detalhes do evento -->
<div id="detalhesEventoBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeDetalhesEventoDrawer()"></div>
<aside id="detalhesEventoDrawer"
       class="fixed inset-y-0 right-0 z-50 w-full max-w-lg bg-white shadow-2xl transform translate-x-full transition-transform duration-200 ease-out flex flex-col"
       aria-hidden="true">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Detalhes do evento</h3>
            <p class="text-xs text-gray-500 mt-0.5">Período, tipo de nota, destino no quadro e turmas</p>
        </div>
        <button type="button" onclick="closeDetalhesEventoDrawer()" class="text-gray-400 hover:text-gray-600" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto px-6 py-5">
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Ano letivo</dt>
                <dd class="font-medium text-gray-900 text-right"><?= $anoTxt > 0 ? (int) $anoTxt : '—' ?></dd>
            </div>
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Período / bimestre</dt>
                <dd class="font-medium text-gray-900 text-right"><?= $periodoTxt !== '' ? htmlspecialchars($periodoTxt) : '—' ?></dd>
            </div>
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Tipo de nota</dt>
                <dd class="font-medium text-gray-900 text-right">
                    <?= $tipoTxt !== '' ? htmlspecialchars($tipoTxt) : '—' ?>
                    <?php if (!empty($desc['tipo_chave'])): ?>
                        <span class="text-xs font-normal text-gray-500">(<?= htmlspecialchars((string) $desc['tipo_chave']) ?>)</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="py-2 border-b border-gray-100">
                <dt class="text-gray-500 mb-1.5">Destino no quadro</dt>
                <dd class="flex flex-wrap gap-1.5">
                    <?php if ($destinosQuadro === []): ?>
                        <span class="text-gray-600">Sem vínculo de quadro</span>
                    <?php else: ?>
                        <?php foreach ($destinosQuadro as $destino): ?>
                            <?php if (trim((string) ($destino['bloco'] ?? '')) !== ''): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-800"><?= htmlspecialchars((string) $destino['bloco']) ?></span>
                            <?php endif; ?>
                            <?php if (trim((string) ($destino['semana'] ?? '')) !== ''): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-slate-100 text-slate-800"><?= htmlspecialchars((string) $destino['semana']) ?></span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Formato</dt>
                <dd class="font-medium text-gray-900 text-right"><?= htmlspecialchars((string) ($desc['formato'] ?? 'Lançamento de notas')) ?></dd>
            </div>
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Quem lança</dt>
                <dd class="font-medium text-gray-900 text-right"><?= htmlspecialchars((string) ($desc['quem_lanca'] ?? 'Professor')) ?> <span class="font-normal text-gray-500">(0 a 10)</span></dd>
            </div>
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Nota única (todas matérias)</dt>
                <dd class="font-medium text-gray-900 text-right"><?= !empty($desc['nota_unica']) ? 'Sim' : 'Não' ?></dd>
            </div>
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Status</dt>
                <dd class="font-medium text-gray-900 text-right"><?= htmlspecialchars($statusTexto) ?></dd>
            </div>
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Data / horário</dt>
                <dd class="font-medium text-gray-900 text-right">
                    <?= $dataTxt !== '' ? htmlspecialchars($dataTxt) : '—' ?>
                    <?php if ($horarioTxt !== ''): ?>
                        <span class="font-normal text-gray-600"> · <?= htmlspecialchars($horarioTxt) ?></span>
                    <?php endif; ?>
                </dd>
            </div>
            <div class="flex justify-between gap-4 py-2 border-b border-gray-100">
                <dt class="text-gray-500 shrink-0">Prazo do professor</dt>
                <dd class="font-medium text-gray-900 text-right"><?= $prazoTxt !== '' ? htmlspecialchars($prazoTxt) : '—' ?></dd>
            </div>
            <div class="py-2 border-b border-gray-100">
                <dt class="text-gray-500 mb-1">Turmas</dt>
                <dd class="font-medium text-gray-900"><?= htmlspecialchars($turmasTxt) ?></dd>
            </div>
            <div class="py-2 border-b border-gray-100">
                <dt class="text-gray-500 mb-1">Matérias</dt>
                <dd class="font-medium text-gray-900"><?= htmlspecialchars($materiasTxt) ?></dd>
            </div>
            <?php if (!empty($desc['criado_por'])): ?>
            <div class="flex justify-between gap-4 py-2">
                <dt class="text-gray-500 shrink-0">Criado por</dt>
                <dd class="font-medium text-gray-900 text-right"><?= htmlspecialchars((string) $desc['criado_por']) ?></dd>
            </div>
            <?php endif; ?>
        </dl>
    </div>
</aside>

<script>
window.openDetalhesEventoDrawer = function () {
    document.getElementById('detalhesEventoBackdrop').classList.remove('hidden');
    var d = document.getElementById('detalhesEventoDrawer');
    d.classList.remove('translate-x-full');
    d.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
};
window.closeDetalhesEventoDrawer = function () {
    document.getElementById('detalhesEventoBackdrop').classList.add('hidden');
    var d = document.getElementById('detalhesEventoDrawer');
    d.classList.add('translate-x-full');
    d.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
};
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeDetalhesEventoDrawer();
    }
});
</script>

<?php if ($notaUnicaTodasMaterias): ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6 border border-gray-200">
    <h3 class="text-lg font-semibold text-gray-900">Nota única para todas as matérias</h3>
    <p class="text-sm text-gray-600 mt-2">Neste evento, a coordenação lança uma única nota por aluno e o sistema replica automaticamente para todas as matérias.</p>
    <div class="mt-4">
        <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/lancar-notas-coordenacao"
           class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2 rounded-lg hover:opacity-90">
            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
            Lançar
        </a>
    </div>
</div>
<?php else: ?>
<?php
$linhasLancamento = [];
foreach ($lancamentoPorMateria ?? [] as $materiaNome => $linhasMat) {
    foreach ($linhasMat as $row) {
        $linhasLancamento[] = $row;
    }
}
usort($linhasLancamento, static function (array $a, array $b): int {
    $cmp = strcasecmp((string) ($a['materia_nome'] ?? ''), (string) ($b['materia_nome'] ?? ''));
    if ($cmp !== 0) {
        return $cmp;
    }
    return strcasecmp((string) ($a['professor_nome'] ?? ''), (string) ($b['professor_nome'] ?? ''));
});
$statusMapLancamento = [
    'nao_iniciado' => ['bg-gray-100 text-gray-700', 'Não iniciou'],
    'em_andamento' => ['bg-slate-100 text-slate-800', 'Em andamento'],
    'concluido' => ['bg-gray-800 text-white', 'Concluído'],
    'sem_alunos' => ['bg-gray-50 text-gray-500', 'Sem alunos nas turmas'],
];
?>
<div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Lançamento por professor e matéria</h3>
        <p class="text-sm text-gray-600 mt-0.5">
            <?= $lancamentoPorCoordenacao
                ? 'Coordenação lança as notas deste evento.'
                : 'Acompanhe o progresso dos professores e acesse o lançamento de cada matéria.' ?>
        </p>
    </div>
    <?php if ($linhasLancamento === []): ?>
    <div class="px-6 py-8 text-gray-600 bg-gray-50 border-t border-gray-100">
        Nenhum professor/matéria vinculado a este evento. Edite o bloco e adicione professores com turmas.
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Progresso</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($linhasLancamento as $row): ?>
                <?php
                $st = (string) ($row['status'] ?? '');
                $pair = $statusMapLancamento[$st] ?? ['bg-gray-100 text-gray-800', $st];
                if ($lancamentoPorCoordenacao) {
                    $urlLancar = URL . '/admin/provas/blocos/' . (int) $bloco['id']
                        . '/lancar-notas-coordenacao?materia_id=' . (int) ($row['materia_id'] ?? 0);
                } else {
                    $urlLancar = URL . '/admin/provas/blocos/' . (int) $bloco['id']
                        . '/notas-lancadas?professor_id=' . (int) ($row['professor_id'] ?? 0)
                        . '&materia_id=' . (int) ($row['materia_id'] ?? 0);
                }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        <?= htmlspecialchars((string) ($row['professor_nome'] ?? '')) ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-800">
                        <?= htmlspecialchars((string) ($row['materia_nome'] ?? '')) ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700 whitespace-nowrap">
                        <?= (int) ($row['com_nota'] ?? 0) ?> / <?= (int) ($row['total_esperado'] ?? 0) ?>
                        <?php if ((int) ($row['total_esperado'] ?? 0) > 0): ?>
                            <span class="text-gray-500">(<?= htmlspecialchars((string) ($row['perc'] ?? '')) ?>%)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $pair[0] ?>"><?= htmlspecialchars($pair[1]) ?></span>
                    </td>
                    <td class="px-6 py-4 text-right whitespace-nowrap">
                        <a href="<?= htmlspecialchars($urlLancar) ?>"
                           class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            Lançar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>

<!-- Cards de indicador compactos -->
<?php if (isset($contagem)): ?>
<div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5">
        <p class="text-xs text-gray-500">Em andamento</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int) ($contagem['em_andamento'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5">
        <p class="text-xs text-gray-500">Enviadas</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int) ($contagem['enviada'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5">
        <p class="text-xs text-gray-500">Não enviadas</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int) ($contagem['nao_enviada'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5">
        <p class="text-xs text-gray-500">Aprovadas</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int) ($contagem['aprovada'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5">
        <p class="text-xs text-gray-500">Retornado professor</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int) ($contagem['retornada'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5">
        <p class="text-xs text-gray-500">Provas excluídas</p>
        <p class="text-xl font-bold text-gray-900 mt-0.5"><?= (int) ($contagem['reprovada'] ?? 0) ?></p>
    </div>
</div>
<?php endif; ?>

<!-- Provas: tabela unificada (Professor × Matéria) -->
<?php
$linhasProvasOnline = [];
foreach ($provasPorMateria ?? [] as $materiaNome => $provasMateria) {
    foreach ($provasMateria as $prova) {
        if (empty($prova['materia_nome'])) {
            $prova['materia_nome'] = $materiaNome;
        }
        $linhasProvasOnline[] = $prova;
    }
}
usort($linhasProvasOnline, static function (array $a, array $b): int {
    $cmp = strcasecmp((string) ($a['materia_nome'] ?? ''), (string) ($b['materia_nome'] ?? ''));
    if ($cmp !== 0) {
        return $cmp;
    }
    return strcasecmp((string) ($a['professor_nome'] ?? ''), (string) ($b['professor_nome'] ?? ''));
});
$statusClassesOnline = [
    'nao_avaliada' => 'bg-slate-100 text-slate-700',
    'aprovado' => 'bg-gray-800 text-white',
    'em_andamento' => 'bg-slate-100 text-slate-800',
    'concluido' => 'bg-slate-100 text-slate-800',
    'reprovada' => 'bg-gray-100 text-gray-600',
    'nao_enviada' => 'bg-gray-100 text-gray-600',
    'retornada' => 'bg-slate-100 text-slate-700',
    'pendente' => 'bg-gray-100 text-gray-600',
];
$statusLabelsOnline = [
    'nao_avaliada' => 'Enviada',
    'aprovado' => 'Aprovada',
    'em_andamento' => 'Em Andamento',
    'concluido' => 'Concluída',
    'reprovada' => 'Prova excluída',
    'nao_enviada' => 'Não Enviada',
    'retornada' => 'Retornada ao professor',
    'pendente' => 'Aguardando envio',
];
$btnSecundario = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50';
?>
<div class="bg-white rounded-xl shadow-lg overflow-hidden mb-6">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Provas por professor e matéria</h3>
        <p class="text-sm text-gray-600 mt-0.5">Acompanhe o status de cada prova e gerencie vínculos, trocas e aprovações.</p>
    </div>
    <?php if ($linhasProvasOnline === []): ?>
    <div class="px-6 py-8 text-center text-gray-500">Nenhuma prova encontrada para este filtro.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Matéria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Data envio</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Questões</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($linhasProvasOnline as $prova): ?>
                <?php
                $statusExibicao = $prova['status'] ?? 'nao_enviada';
                $statusClass = $statusClassesOnline[$statusExibicao] ?? 'bg-gray-100 text-gray-800';
                $statusLabel = $statusLabelsOnline[$statusExibicao] ?? $statusExibicao;
                $statusOriginalAcoes = $prova['status_original'] ?? '';
                $podeAprovarReprovar = in_array($statusOriginalAcoes, ['enviada', 'aguardando_aprovacao', 'pendente', 'agendada'], true)
                    && !empty($prova['prova_id']);
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-3 text-sm font-medium text-gray-900">
                        <?= htmlspecialchars((string) ($prova['professor_nome'] ?? '')) ?>
                    </td>
                    <td class="px-6 py-3 text-sm text-gray-800">
                        <?= htmlspecialchars((string) ($prova['materia_nome'] ?? '')) ?>
                    </td>
                    <td class="px-6 py-3">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>">
                                <?= htmlspecialchars($statusLabel) ?>
                            </span>
                        </div>
                        <?php if (($prova['status'] ?? '') === 'retornada' && !empty($prova['observacao_coordenacao'])): ?>
                        <div class="text-xs text-gray-600 mt-1 max-w-md" title="<?= htmlspecialchars((string) $prova['observacao_coordenacao']) ?>">
                            <?= htmlspecialchars(mb_substr((string) $prova['observacao_coordenacao'], 0, 60)) ?><?= mb_strlen((string) $prova['observacao_coordenacao']) > 60 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600">
                        <?= !empty($prova['data_envio']) ? date('d/m/Y H:i', strtotime((string) $prova['data_envio'])) : '—' ?>
                    </td>
                    <td class="px-6 py-3 whitespace-nowrap text-sm text-gray-600">
                        <?= (int) ($prova['numero_questoes'] ?? 0) ?>
                    </td>
                    <td class="px-6 py-3 text-right">
                        <div class="inline-flex flex-wrap justify-end gap-2">
                            <?php if (!empty($prova['prova_id'])): ?>
                                <a href="<?= URL ?>/admin/provas/visualizar/<?= (int) $prova['prova_id'] ?>"
                                   class="btn-primary-custom inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold hover:opacity-90">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                    Ver prova
                                </a>
                                <?php if (!empty($prova['professor_id']) && !empty($prova['materia_id'])): ?>
                                <button type="button"
                                        onclick="abrirModalTrocar(<?= (int) $bloco['id'] ?>, <?= (int) $prova['professor_id'] ?>, <?= (int) $prova['materia_id'] ?>, '<?= htmlspecialchars(addslashes((string) ($prova['professor_nome'] ?? '')), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes((string) ($prova['materia_nome'] ?? '')), ENT_QUOTES) ?>', <?= (int) $prova['prova_id'] ?>)"
                                        class="<?= $btnSecundario ?>">
                                    <i class="fa-solid fa-right-left" aria-hidden="true"></i>
                                    Trocar
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($podeAprovarReprovar): ?>
                                <button type="button" onclick="aprovarProva(<?= (int) $prova['prova_id'] ?>)"
                                        class="<?= $btnSecundario ?>">
                                    <i class="fa-solid fa-check" aria-hidden="true"></i>
                                    Aprovar
                                </button>
                                <button type="button" onclick="reprovarProva(<?= (int) $prova['prova_id'] ?>)"
                                        class="<?= $btnSecundario ?>">
                                    <i class="fa-solid fa-xmark" aria-hidden="true"></i>
                                    Reprovar
                                </button>
                            <?php elseif (($prova['status'] ?? '') === 'nao_enviada'): ?>
                                <?php if (!empty($prova['professor_id']) && !empty($prova['materia_id'])): ?>
                                <button type="button"
                                        onclick="abrirModalVincular(<?= (int) $bloco['id'] ?>, <?= (int) $prova['professor_id'] ?>, <?= (int) $prova['materia_id'] ?>, '<?= htmlspecialchars(addslashes((string) ($prova['professor_nome'] ?? '')), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes((string) ($prova['materia_nome'] ?? '')), ENT_QUOTES) ?>')"
                                        class="btn-primary-custom inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold hover:opacity-90">
                                    <i class="fa-solid fa-link" aria-hidden="true"></i>
                                    Vincular
                                </button>
                                <?php else: ?>
                                <span class="text-sm text-gray-400 italic">Aguardando criação</span>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

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
                <p class="text-gray-600">Nenhuma prova disponível para vincular (provas do professor nesta matéria que ainda não estão em outro bloco).</p>
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
                html += '<button type="button" onclick="' + btnOnclick + '" class="btn-primary-custom px-3 py-1 text-sm rounded hover:opacity-90">' + btnLabel + '</button>';
                html += '</div></li>';
            });
            html += '</ul>';
            lista.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('modalVincularLista').innerHTML = '<p class="text-gray-600">Erro ao carregar provas.</p>';
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
