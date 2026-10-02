<?php
/**
 * Gerenciar Provas de um Bloco
 * Acesso: Coordenação
 */
$modoLancamentoNota = !empty($modo_lancamento_nota);
$lancamentoPorCoordenacao = $modoLancamentoNota && (($bloco['configuracao_nota'] ?? '') === 'coordenacao_calcula');
$notaUnicaTodasMaterias = $lancamentoPorCoordenacao && !empty($bloco['nota_unica_todas_materias']);
$statusBlocoRaw = (string) ($bloco['status'] ?? '');
$statusBlocoLabel = ucfirst(str_replace('_', ' ', $statusBlocoRaw));
$statusBadgeClasses = match ($statusBlocoRaw) {
    'aprovado', 'liberado' => 'bg-emerald-50 text-emerald-700',
    'aguardando' => 'bg-slate-100 text-slate-700',
    'concluido' => 'bg-gray-800 text-white',
    default => 'bg-gray-100 text-gray-700',
};
$btnOutlineHeader = 'inline-flex items-center gap-2 px-3.5 py-2 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50';

$iniciaisNome = static function (string $nome): string {
    $nome = trim(preg_replace('/\s+/', ' ', $nome) ?? '');
    if ($nome === '') {
        return '?';
    }
    $partes = explode(' ', $nome);
    $primeira = mb_substr($partes[0], 0, 1);
    $ultima = count($partes) > 1 ? mb_substr($partes[count($partes) - 1], 0, 1) : '';
    return mb_strtoupper($primeira . $ultima);
};

$estiloMateria = static function (string $nome): array {
    $n = mb_strtolower(trim($nome));
    $map = [
        'biologia' => ['bg-emerald-50 text-emerald-700', 'fa-leaf'],
        'física' => ['bg-sky-50 text-sky-700', 'fa-atom'],
        'fisica' => ['bg-sky-50 text-sky-700', 'fa-atom'],
        'gramática' => ['bg-amber-50 text-amber-700', 'fa-book'],
        'gramatica' => ['bg-amber-50 text-amber-700', 'fa-book'],
        'inglês' => ['bg-violet-50 text-violet-700', 'fa-comments'],
        'ingles' => ['bg-violet-50 text-violet-700', 'fa-comments'],
        'leitura e interpretação' => ['bg-rose-50 text-rose-700', 'fa-book-open'],
        'leitura e interpretacao' => ['bg-rose-50 text-rose-700', 'fa-book-open'],
        'literatura' => ['bg-indigo-50 text-indigo-700', 'fa-book'],
        'matemática' => ['bg-blue-50 text-blue-700', 'fa-calculator'],
        'matematica' => ['bg-blue-50 text-blue-700', 'fa-calculator'],
        'química' => ['bg-teal-50 text-teal-700', 'fa-flask'],
        'quimica' => ['bg-teal-50 text-teal-700', 'fa-flask'],
        'história' => ['bg-orange-50 text-orange-700', 'fa-landmark'],
        'historia' => ['bg-orange-50 text-orange-700', 'fa-landmark'],
        'geografia' => ['bg-cyan-50 text-cyan-700', 'fa-globe'],
        'português' => ['bg-fuchsia-50 text-fuchsia-700', 'fa-language'],
        'portugues' => ['bg-fuchsia-50 text-fuchsia-700', 'fa-language'],
        'educação física' => ['bg-lime-50 text-lime-700', 'fa-person-running'],
        'educacao fisica' => ['bg-lime-50 text-lime-700', 'fa-person-running'],
        'artes' => ['bg-pink-50 text-pink-700', 'fa-palette'],
        'filosofia' => ['bg-slate-100 text-slate-700', 'fa-brain'],
        'sociologia' => ['bg-stone-100 text-stone-700', 'fa-users'],
    ];
    foreach ($map as $chave => $estilo) {
        if ($n === $chave || str_contains($n, $chave)) {
            return $estilo;
        }
    }
    $paleta = [
        ['bg-slate-100 text-slate-700', 'fa-book'],
        ['bg-blue-50 text-blue-700', 'fa-book'],
        ['bg-indigo-50 text-indigo-700', 'fa-book'],
        ['bg-teal-50 text-teal-700', 'fa-book'],
        ['bg-amber-50 text-amber-700', 'fa-book'],
    ];
    return $paleta[abs(crc32($n)) % count($paleta)];
};
?>

<!-- Header Section -->
<div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 mb-8">
    <div class="flex flex-col xl:flex-row xl:items-center xl:justify-between gap-4">
        <div class="flex items-start gap-3 min-w-0">
            <div class="shrink-0 w-11 h-11 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center">
                <i class="fa-solid <?= $modoLancamentoNota ? 'fa-pen-to-square' : 'fa-file-lines' ?> text-lg" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <h2 class="text-xl sm:text-2xl font-bold text-gray-900 leading-tight">
                    <?= $modoLancamentoNota ? 'Lançamento de notas' : 'Gerenciar Provas' ?>
                </h2>
                <p class="text-base text-gray-800 mt-0.5 truncate" title="<?= htmlspecialchars((string) ($bloco['titulo'] ?? '')) ?>">
                    <?= htmlspecialchars((string) ($bloco['titulo'] ?? '')) ?>
                </p>
                <?php if (!$modoLancamentoNota): ?>
                <div class="flex flex-wrap items-center gap-x-3 gap-y-1.5 mt-2.5">
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $statusBadgeClasses ?>">
                        <?php if (in_array($statusBlocoRaw, ['aprovado', 'liberado'], true)): ?>
                            <i class="fa-solid fa-circle-check text-[10px]" aria-hidden="true"></i>
                        <?php endif; ?>
                        <?= htmlspecialchars($statusBlocoLabel) ?>
                    </span>
                    <?php if (!empty($bloco['prazo_entrega_professor'])): ?>
                    <span class="inline-flex items-center gap-1.5 text-sm text-gray-500">
                        <i class="fa-regular fa-clock" aria-hidden="true"></i>
                        Prazo de envio: <?= date('d/m/Y H:i', strtotime((string) $bloco['prazo_entrega_professor'])) ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 xl:justify-end">
            <a href="<?= URL ?>/admin/provas" class="<?= $btnOutlineHeader ?>">
                <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
                Voltar
            </a>

            <?php if ($modoLancamentoNota): ?>
                <button type="button" onclick="openDetalhesEventoDrawer()" class="<?= $btnOutlineHeader ?>">
                    <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    Detalhes
                </button>
                <a href="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/exportar-notas-excel" class="<?= $btnOutlineHeader ?>">
                    <i class="fa-solid fa-file-excel" aria-hidden="true"></i>
                    Exportar Excel
                </a>
                <a href="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/resultados" class="<?= $btnOutlineHeader ?>">
                    <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                    Relatório de notas
                </a>
                <?php if ($lancamentoPorCoordenacao): ?>
                <a href="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/lancar-notas-coordenacao"
                   class="btn-primary-custom inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                    <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                    Lançar notas (coordenação)
                </a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/canceladas"
                   class="<?= $btnOutlineHeader ?><?= !empty($total_canceladas) ? ' ring-2 ring-gray-300' : '' ?>">
                    <i class="fa-solid fa-ban" aria-hidden="true"></i>
                    Cancelados<?= !empty($total_canceladas) ? ' (' . (int) $total_canceladas . ')' : '' ?>
                </a>
                <?php if (!empty($bloco['gabarito_liberado'])): ?>
                <span class="inline-flex items-center gap-2 px-3.5 py-2 rounded-lg border border-gray-200 bg-gray-50 text-gray-700 text-sm font-medium">
                    Gabarito liberado
                </span>
                <?php else: ?>
                <form method="post" action="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/liberar-gabarito" class="inline"
                      onsubmit="return confirm('Liberar o gabarito deste bloco para todos os alunos?');">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? '')) ?>">
                    <input type="hidden" name="origem" value="gerenciar">
                    <button type="submit" class="<?= $btnOutlineHeader ?>">
                        Liberar gabarito
                    </button>
                </form>
                <?php endif; ?>
                <a href="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/visualizar-completo" class="<?= $btnOutlineHeader ?>">
                    <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
                    Prova Completa
                </a>
                <?php if (!empty($mostrarBotaoAprovacaoFinal)): ?>
                <button type="button" onclick="aprovarBlocoFinal(<?= (int) $bloco['id'] ?>)"
                        class="btn-primary-custom inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Aprovação Final
                </button>
                <?php endif; ?>
                <a href="<?= URL ?>/admin/provas/blocos/<?= (int) $bloco['id'] ?>/resultados-novos"
                   class="btn-primary-custom inline-flex items-center gap-2 px-3.5 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                    <i class="fa-solid fa-chart-column" aria-hidden="true"></i>
                    Resultados
                </a>
            <?php endif; ?>
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
<?php
$totalLn = max(1, (int) ($contagem['ln_nao_iniciado'] ?? 0) + (int) ($contagem['ln_em_andamento'] ?? 0) + (int) ($contagem['ln_concluido'] ?? 0));
$cardsLn = [
    ['label' => 'Sem notas lançadas', 'valor' => (int) ($contagem['ln_nao_iniciado'] ?? 0), 'icon' => 'fa-file', 'tone' => 'bg-slate-100 text-slate-600'],
    ['label' => 'Em andamento', 'valor' => (int) ($contagem['ln_em_andamento'] ?? 0), 'icon' => 'fa-hourglass-half', 'tone' => 'bg-amber-50 text-amber-600'],
    ['label' => 'Concluído', 'valor' => (int) ($contagem['ln_concluido'] ?? 0), 'icon' => 'fa-circle-check', 'tone' => 'bg-emerald-50 text-emerald-600'],
    ['label' => 'Notas abaixo de 6', 'valor' => (int) ($contagem['ln_abaixo_seis'] ?? 0), 'icon' => 'fa-triangle-exclamation', 'tone' => 'bg-rose-50 text-rose-600'],
];
?>
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <?php foreach ($cardsLn as $card): ?>
    <div class="bg-white rounded-xl border border-gray-100 shadow-sm px-4 py-3.5">
        <div class="flex items-start gap-3">
            <div class="w-9 h-9 rounded-lg <?= $card['tone'] ?> flex items-center justify-center shrink-0">
                <i class="fa-solid <?= $card['icon'] ?>" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($card['label']) ?></p>
                <p class="text-2xl font-bold text-gray-900 leading-tight mt-0.5"><?= $card['valor'] ?></p>
                <p class="text-[11px] text-gray-400 mt-0.5"><?= (int) round(($card['valor'] / $totalLn) * 100) ?>% do total</p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
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
    'nao_iniciado' => ['bg-gray-100 text-gray-700', 'fa-circle', 'Não iniciou'],
    'em_andamento' => ['bg-amber-50 text-amber-700', 'fa-hourglass-half', 'Em andamento'],
    'concluido' => ['bg-emerald-50 text-emerald-700', 'fa-circle-check', 'Concluído'],
    'sem_alunos' => ['bg-gray-50 text-gray-500', 'fa-user-slash', 'Sem alunos nas turmas'],
];
$materiasFiltroLn = [];
foreach ($linhasLancamento as $rowMat) {
    $mn = trim((string) ($rowMat['materia_nome'] ?? ''));
    if ($mn !== '') {
        $materiasFiltroLn[$mn] = true;
    }
}
ksort($materiasFiltroLn, SORT_NATURAL | SORT_FLAG_CASE);
?>
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex items-start gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-table-list" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-gray-900">Lançamento por professor e matéria</h3>
                <p class="text-sm text-gray-500 mt-0.5">
                    <?= $lancamentoPorCoordenacao
                        ? 'Coordenação lança as notas deste evento.'
                        : 'Acompanhe o progresso dos professores e acesse o lançamento de cada matéria.' ?>
                </p>
            </div>
        </div>
        <?php if ($linhasLancamento !== []): ?>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="buscaLancamentoTabela" placeholder="Buscar por professor, matéria..."
                       class="pl-9 pr-3 py-2 w-56 sm:w-64 rounded-lg border border-gray-300 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
            </div>
            <div class="relative">
                <i class="fa-solid fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true"></i>
                <select id="filtroMateriaLancamento"
                        class="appearance-none pl-9 pr-8 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                    <option value="">Todas as matérias</option>
                    <?php foreach (array_keys($materiasFiltroLn) as $matOpt): ?>
                    <option value="<?= htmlspecialchars($matOpt) ?>"><?= htmlspecialchars($matOpt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($linhasLancamento === []): ?>
    <div class="px-6 py-8 text-gray-600 bg-gray-50">
        Nenhum professor/matéria vinculado a este evento. Edite o bloco e adicione professores com turmas.
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full" id="tabelaLancamento">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Professor</th>
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Matéria</th>
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Progresso</th>
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Situação</th>
                    <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($linhasLancamento as $row): ?>
                <?php
                $st = (string) ($row['status'] ?? '');
                $pair = $statusMapLancamento[$st] ?? ['bg-gray-100 text-gray-800', 'fa-circle', $st];
                $profNome = (string) ($row['professor_nome'] ?? '');
                $matNome = (string) ($row['materia_nome'] ?? '');
                [$matCls, $matIcon] = $estiloMateria($matNome);
                if ($lancamentoPorCoordenacao) {
                    $urlLancar = URL . '/admin/provas/blocos/' . (int) $bloco['id']
                        . '/lancar-notas-coordenacao?materia_id=' . (int) ($row['materia_id'] ?? 0);
                } else {
                    $urlLancar = URL . '/admin/provas/blocos/' . (int) $bloco['id']
                        . '/notas-lancadas?professor_id=' . (int) ($row['professor_id'] ?? 0)
                        . '&materia_id=' . (int) ($row['materia_id'] ?? 0);
                }
                ?>
                <tr class="hover:bg-slate-50/80 linha-tabela-filtravel"
                    data-professor="<?= htmlspecialchars(mb_strtolower($profNome)) ?>"
                    data-materia="<?= htmlspecialchars(mb_strtolower($matNome)) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold flex items-center justify-center shrink-0"><?= htmlspecialchars($iniciaisNome($profNome)) ?></span>
                            <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($profNome) ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $matCls ?>">
                            <i class="fa-solid <?= $matIcon ?> text-[10px]" aria-hidden="true"></i>
                            <?= htmlspecialchars($matNome) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-sm text-gray-700 whitespace-nowrap">
                        <?= (int) ($row['com_nota'] ?? 0) ?> / <?= (int) ($row['total_esperado'] ?? 0) ?>
                        <?php if ((int) ($row['total_esperado'] ?? 0) > 0): ?>
                            <span class="text-gray-400">(<?= htmlspecialchars((string) ($row['perc'] ?? '')) ?>%)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $pair[0] ?>">
                            <i class="fa-solid <?= $pair[1] ?> text-[10px]" aria-hidden="true"></i>
                            <?= htmlspecialchars($pair[2]) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5 text-right whitespace-nowrap">
                        <a href="<?= htmlspecialchars($urlLancar) ?>"
                           class="btn-primary-custom inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold hover:opacity-90">
                            <i class="fa-solid fa-pen-to-square" aria-hidden="true"></i>
                            Lançar
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
        <span id="contadorLancamentoTabela">Mostrando <?= count($linhasLancamento) ?> de <?= count($linhasLancamento) ?> registros</span>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php else: ?>

<!-- Cards de indicador -->
<?php if (isset($contagem)): ?>
<?php
$cardsOnline = [
    ['key' => 'em_andamento', 'label' => 'Em andamento', 'valor' => (int) ($contagem['em_andamento'] ?? 0), 'icon' => 'fa-hourglass-half', 'tone' => 'bg-amber-50 text-amber-600', 'bar' => 'bg-amber-400'],
    ['key' => 'enviada', 'label' => 'Enviadas', 'valor' => (int) ($contagem['enviada'] ?? 0), 'icon' => 'fa-paper-plane', 'tone' => 'bg-sky-50 text-sky-600', 'bar' => 'bg-sky-400'],
    ['key' => 'nao_enviada', 'label' => 'Não enviadas', 'valor' => (int) ($contagem['nao_enviada'] ?? 0), 'icon' => 'fa-file', 'tone' => 'bg-slate-100 text-slate-600', 'bar' => 'bg-slate-400'],
    ['key' => 'aprovada', 'label' => 'Aprovadas', 'valor' => (int) ($contagem['aprovada'] ?? 0), 'icon' => 'fa-circle-check', 'tone' => 'bg-emerald-50 text-emerald-600', 'bar' => 'bg-emerald-500'],
    ['key' => 'retornada', 'label' => 'Retornado professor', 'valor' => (int) ($contagem['retornada'] ?? 0), 'icon' => 'fa-rotate-left', 'tone' => 'bg-rose-50 text-rose-600', 'bar' => 'bg-rose-400'],
    ['key' => 'reprovada', 'label' => 'Provas excluídas', 'valor' => (int) ($contagem['reprovada'] ?? 0), 'icon' => 'fa-ban', 'tone' => 'bg-violet-50 text-violet-600', 'bar' => 'bg-violet-400'],
];
$totalOnlineCards = max(1, array_sum(array_column($cardsOnline, 'valor')));
?>
<div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-6" id="cardsFiltroStatus">
    <?php foreach ($cardsOnline as $card): ?>
    <button type="button"
            class="card-filtro-status group text-left bg-white rounded-xl border border-gray-100 shadow-sm px-3.5 py-3 relative overflow-hidden hover:border-gray-300 transition-colors"
            data-status="<?= htmlspecialchars($card['key']) ?>"
            aria-pressed="false">
        <div class="flex items-start gap-2.5">
            <div class="w-8 h-8 rounded-lg <?= $card['tone'] ?> flex items-center justify-center shrink-0">
                <i class="fa-solid <?= $card['icon'] ?> text-sm" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <p class="text-[11px] text-gray-500 truncate"><?= htmlspecialchars($card['label']) ?></p>
                <p class="text-xl font-bold text-gray-900 leading-tight"><?= $card['valor'] ?></p>
                <p class="text-[10px] text-gray-400"><?= (int) round(($card['valor'] / $totalOnlineCards) * 100) ?>% do total</p>
            </div>
        </div>
        <span class="card-filtro-bar absolute bottom-0 left-0 right-0 h-1 <?= $card['bar'] ?> opacity-0 group-[.is-active]:opacity-100"></span>
    </button>
    <?php endforeach; ?>
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
$statusMetaOnline = [
    'nao_avaliada' => ['bg-sky-50 text-sky-700', 'fa-paper-plane', 'Enviada', 'enviada'],
    'aprovado' => ['bg-emerald-50 text-emerald-700', 'fa-circle-check', 'Aprovada', 'aprovada'],
    'em_andamento' => ['bg-amber-50 text-amber-700', 'fa-hourglass-half', 'Em Andamento', 'em_andamento'],
    'concluido' => ['bg-slate-100 text-slate-700', 'fa-flag-checkered', 'Concluída', 'em_andamento'],
    'reprovada' => ['bg-violet-50 text-violet-700', 'fa-ban', 'Prova excluída', 'reprovada'],
    'nao_enviada' => ['bg-slate-100 text-slate-600', 'fa-file', 'Não Enviada', 'nao_enviada'],
    'retornada' => ['bg-rose-50 text-rose-700', 'fa-rotate-left', 'Retornada ao professor', 'retornada'],
    'pendente' => ['bg-gray-100 text-gray-600', 'fa-clock', 'Aguardando envio', 'nao_enviada'],
];
$materiasFiltroOnline = [];
foreach ($linhasProvasOnline as $provaMat) {
    $mn = trim((string) ($provaMat['materia_nome'] ?? ''));
    if ($mn !== '') {
        $materiasFiltroOnline[$mn] = true;
    }
}
ksort($materiasFiltroOnline, SORT_NATURAL | SORT_FLAG_CASE);
$btnSecundario = 'inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50';
$totalLinhasOnline = count($linhasProvasOnline);
?>
<div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-4 border-b border-gray-100 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <div class="flex items-start gap-3 min-w-0">
            <div class="w-10 h-10 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center shrink-0">
                <i class="fa-solid fa-file-lines" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <h3 class="text-lg font-semibold text-gray-900">Provas por professor e matéria</h3>
                <p class="text-sm text-gray-500 mt-0.5">Acompanhe o status de cada prova e gerencie vínculos, trocas e aprovações.</p>
            </div>
        </div>
        <?php if ($linhasProvasOnline !== []): ?>
        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true"></i>
                <input type="search" id="buscaProvasTabela" placeholder="Buscar por professor, matéria..."
                       class="pl-9 pr-3 py-2 w-56 sm:w-64 rounded-lg border border-gray-300 text-sm text-gray-700 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
            </div>
            <div class="relative">
                <i class="fa-solid fa-filter absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" aria-hidden="true"></i>
                <select id="filtroMateriaProvas"
                        class="appearance-none pl-9 pr-8 py-2 rounded-lg border border-gray-300 text-sm text-gray-700 bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400">
                    <option value="">Todas as matérias</option>
                    <?php foreach (array_keys($materiasFiltroOnline) as $matOpt): ?>
                    <option value="<?= htmlspecialchars($matOpt) ?>"><?= htmlspecialchars($matOpt) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php if ($linhasProvasOnline === []): ?>
    <div class="px-6 py-8 text-center text-gray-500">Nenhuma prova encontrada para este filtro.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full" id="tabelaProvasOnline">
            <thead>
                <tr class="border-b border-gray-100">
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Professor</th>
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Matéria</th>
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Situação</th>
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Data envio</th>
                    <th class="px-5 py-3 text-left text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Questões</th>
                    <th class="px-5 py-3 text-right text-[11px] font-semibold text-gray-400 uppercase tracking-wide">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-50">
                <?php foreach ($linhasProvasOnline as $idx => $prova): ?>
                <?php
                $statusExibicao = (string) ($prova['status'] ?? 'nao_enviada');
                $meta = $statusMetaOnline[$statusExibicao] ?? ['bg-gray-100 text-gray-800', 'fa-circle', $statusExibicao, 'nao_enviada'];
                $statusOriginalAcoes = $prova['status_original'] ?? '';
                $podeAprovarReprovar = in_array($statusOriginalAcoes, ['enviada', 'aguardando_aprovacao', 'pendente', 'agendada'], true)
                    && !empty($prova['prova_id']);
                $profNome = (string) ($prova['professor_nome'] ?? '');
                $matNome = (string) ($prova['materia_nome'] ?? '');
                [$matCls, $matIcon] = $estiloMateria($matNome);
                $menuId = 'menu-acoes-prova-' . $idx;
                ?>
                <tr class="hover:bg-slate-50/80 linha-tabela-filtravel"
                    data-professor="<?= htmlspecialchars(mb_strtolower($profNome)) ?>"
                    data-materia="<?= htmlspecialchars(mb_strtolower($matNome)) ?>"
                    data-status="<?= htmlspecialchars($meta[3]) ?>">
                    <td class="px-5 py-3.5">
                        <div class="flex items-center gap-2.5">
                            <span class="w-8 h-8 rounded-full bg-blue-50 text-blue-700 text-xs font-semibold flex items-center justify-center shrink-0"><?= htmlspecialchars($iniciaisNome($profNome)) ?></span>
                            <span class="text-sm font-medium text-gray-900"><?= htmlspecialchars($profNome) ?></span>
                        </div>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $matCls ?>">
                            <i class="fa-solid <?= $matIcon ?> text-[10px]" aria-hidden="true"></i>
                            <?= htmlspecialchars($matNome) ?>
                        </span>
                    </td>
                    <td class="px-5 py-3.5">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold <?= $meta[0] ?>">
                            <i class="fa-solid <?= $meta[1] ?> text-[10px]" aria-hidden="true"></i>
                            <?= htmlspecialchars($meta[2]) ?>
                        </span>
                        <?php if ($statusExibicao === 'retornada' && !empty($prova['observacao_coordenacao'])): ?>
                        <div class="text-xs text-gray-500 mt-1 max-w-xs" title="<?= htmlspecialchars((string) $prova['observacao_coordenacao']) ?>">
                            <?= htmlspecialchars(mb_substr((string) $prova['observacao_coordenacao'], 0, 60)) ?><?= mb_strlen((string) $prova['observacao_coordenacao']) > 60 ? '...' : '' ?>
                        </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-gray-600">
                        <?php if (!empty($prova['data_envio'])): ?>
                        <span class="inline-flex items-center gap-1.5">
                            <i class="fa-regular fa-calendar text-gray-400" aria-hidden="true"></i>
                            <?= date('d/m/Y H:i', strtotime((string) $prova['data_envio'])) ?>
                        </span>
                        <?php else: ?>
                        —
                        <?php endif; ?>
                    </td>
                    <td class="px-5 py-3.5 whitespace-nowrap text-sm text-gray-700 font-medium">
                        <?= (int) ($prova['numero_questoes'] ?? 0) ?>
                    </td>
                    <td class="px-5 py-3.5 text-right">
                        <div class="inline-flex items-center justify-end gap-2">
                            <?php if (!empty($prova['prova_id'])): ?>
                                <a href="<?= URL ?>/admin/provas/visualizar/<?= (int) $prova['prova_id'] ?>"
                                   class="btn-primary-custom inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold hover:opacity-90">
                                    <i class="fa-solid fa-eye" aria-hidden="true"></i>
                                    Ver prova
                                </a>
                                <?php if (!empty($prova['professor_id']) && !empty($prova['materia_id'])): ?>
                                <button type="button"
                                        onclick="abrirModalTrocar(<?= (int) $bloco['id'] ?>, <?= (int) $prova['professor_id'] ?>, <?= (int) $prova['materia_id'] ?>, '<?= htmlspecialchars(addslashes($profNome), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($matNome), ENT_QUOTES) ?>', <?= (int) $prova['prova_id'] ?>)"
                                        class="<?= $btnSecundario ?>">
                                    <i class="fa-solid fa-right-left" aria-hidden="true"></i>
                                    Trocar
                                </button>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php if ($podeAprovarReprovar): ?>
                                <div class="relative">
                                    <button type="button"
                                            class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-300 text-gray-500 hover:bg-gray-50"
                                            onclick="toggleMenuAcoesProva('<?= $menuId ?>')"
                                            aria-label="Mais ações">
                                        <i class="fa-solid fa-ellipsis-vertical" aria-hidden="true"></i>
                                    </button>
                                    <div id="<?= $menuId ?>" class="hidden absolute right-0 mt-1 w-40 bg-white border border-gray-200 rounded-lg shadow-lg z-20 py-1">
                                        <button type="button" onclick="aprovarProva(<?= (int) $prova['prova_id'] ?>)"
                                                class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 inline-flex items-center gap-2">
                                            <i class="fa-solid fa-check text-emerald-600" aria-hidden="true"></i>
                                            Aprovar
                                        </button>
                                        <button type="button" onclick="reprovarProva(<?= (int) $prova['prova_id'] ?>)"
                                                class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50 inline-flex items-center gap-2">
                                            <i class="fa-solid fa-xmark text-rose-600" aria-hidden="true"></i>
                                            Reprovar
                                        </button>
                                    </div>
                                </div>
                            <?php elseif (($prova['status'] ?? '') === 'nao_enviada'): ?>
                                <?php if (!empty($prova['professor_id']) && !empty($prova['materia_id'])): ?>
                                <button type="button"
                                        onclick="abrirModalVincular(<?= (int) $bloco['id'] ?>, <?= (int) $prova['professor_id'] ?>, <?= (int) $prova['materia_id'] ?>, '<?= htmlspecialchars(addslashes($profNome), ENT_QUOTES) ?>', '<?= htmlspecialchars(addslashes($matNome), ENT_QUOTES) ?>')"
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
    <div class="px-5 py-3 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
        <span id="contadorProvasTabela">Mostrando <?= $totalLinhasOnline ?> de <?= $totalLinhasOnline ?> registros</span>
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

function toggleMenuAcoesProva(menuId) {
    document.querySelectorAll('[id^="menu-acoes-prova-"]').forEach(function (el) {
        if (el.id !== menuId) el.classList.add('hidden');
    });
    var menu = document.getElementById(menuId);
    if (menu) menu.classList.toggle('hidden');
}

document.addEventListener('click', function (e) {
    if (!e.target.closest('[id^="menu-acoes-prova-"]') && !e.target.closest('[aria-label="Mais ações"]')) {
        document.querySelectorAll('[id^="menu-acoes-prova-"]').forEach(function (el) {
            el.classList.add('hidden');
        });
    }
});
</script>
<?php endif; ?>

<script>
(function () {
    function setupTableFilter(opts) {
        var rows = Array.prototype.slice.call(document.querySelectorAll(opts.rowSelector));
        if (!rows.length) return;
        var busca = document.getElementById(opts.buscaId);
        var materia = document.getElementById(opts.materiaId);
        var contador = document.getElementById(opts.contadorId);
        var statusAtivo = '';
        var total = rows.length;

        function aplicar() {
            var q = ((busca && busca.value) || '').toLowerCase().trim();
            var mat = ((materia && materia.value) || '').toLowerCase().trim();
            var visiveis = 0;
            rows.forEach(function (row) {
                var prof = row.getAttribute('data-professor') || '';
                var matRow = row.getAttribute('data-materia') || '';
                var st = row.getAttribute('data-status') || '';
                var okBusca = !q || prof.indexOf(q) !== -1 || matRow.indexOf(q) !== -1;
                var okMat = !mat || matRow === mat;
                var okStatus = !statusAtivo || st === statusAtivo;
                var show = okBusca && okMat && okStatus;
                row.style.display = show ? '' : 'none';
                if (show) visiveis++;
            });
            if (contador) {
                contador.textContent = 'Mostrando ' + visiveis + ' de ' + total + ' registros';
            }
        }

        if (busca) busca.addEventListener('input', aplicar);
        if (materia) materia.addEventListener('change', aplicar);

        document.querySelectorAll(opts.cardSelector || '').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var key = btn.getAttribute('data-status') || '';
                var same = statusAtivo === key;
                statusAtivo = same ? '' : key;
                document.querySelectorAll(opts.cardSelector).forEach(function (b) {
                    var active = !same && b === btn;
                    b.classList.toggle('is-active', active);
                    b.setAttribute('aria-pressed', active ? 'true' : 'false');
                });
                aplicar();
            });
        });

        aplicar();
    }

    setupTableFilter({
        rowSelector: '#tabelaProvasOnline .linha-tabela-filtravel',
        buscaId: 'buscaProvasTabela',
        materiaId: 'filtroMateriaProvas',
        contadorId: 'contadorProvasTabela',
        cardSelector: '.card-filtro-status'
    });

    setupTableFilter({
        rowSelector: '#tabelaLancamento .linha-tabela-filtravel',
        buscaId: 'buscaLancamentoTabela',
        materiaId: 'filtroMateriaLancamento',
        contadorId: 'contadorLancamentoTabela',
        cardSelector: ''
    });
})();
</script>

<style>
.card-filtro-status.is-active {
    border-color: #cbd5e1;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
}
.card-filtro-status.is-active .card-filtro-bar {
    opacity: 1;
}
</style>
