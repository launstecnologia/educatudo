<?php
require_once __DIR__ . '/../../Services/FechamentoMaquinaEstados.php';

$paineis = is_array($paineis ?? null) ? $paineis : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$schemaPronto = !empty($schema_pronto);

$page_header_title = 'Fechamento';
$page_header_subtitle = 'Painel por turma e período: pendências, recuperação, homologação e retificação.';
$turmaIdFiltro = (int) ($turma_id ?? 0);
$filtrosAtivosCount = 0;
if ($anoLetivo !== (int) date('Y')) {
    $filtrosAtivosCount++;
}
if ($periodoTipo !== 'ano') {
    $filtrosAtivosCount++;
}
if ($periodoNumero > 0) {
    $filtrosAtivosCount++;
}
if ($turmaIdFiltro > 0) {
    $filtrosAtivosCount++;
}
$impressaoLote = is_array($impressao_lote ?? null) ? $impressao_lote : [];
$lotePronto = !empty($impressaoLote['pode']);
$loteTotal = (int) ($impressaoLote['total_turmas'] ?? 0);
$loteHomologadas = (int) ($impressaoLote['homologadas'] ?? 0);
$loteQs = http_build_query([
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
    'turma_id' => $turmaIdFiltro,
]);
$loteTitulo = $lotePronto
    ? 'Imprimir ficha, histórico e consolidado final das turmas homologadas'
    : ('Homologue todas as turmas deste filtro para imprimir em lote (' . $loteHomologadas . ' de ' . $loteTotal . ').');
ob_start();
?>
<a href="<?= URL ?>/admin/resultados-finais/layouts" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Layouts</a>
<a href="<?= URL ?>/admin/resultados-finais/relatorios" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Relatórios</a>
<a href="<?= URL ?>/admin/fechamento/apoio-digitacao?<?= htmlspecialchars($loteQs) ?>" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Apoio à digitação</a>
<?php
if ($lotePronto):
    $ui_btn_variant = 'complementar';
    $ui_btn_label = 'Imprimir em lote';
    $ui_btn_icon = 'fa-solid fa-print';
    $ui_btn_href = URL . '/admin/fechamento/impressao-lote?' . $loteQs;
    $ui_btn_attrs = 'title="' . htmlspecialchars($loteTitulo, ENT_QUOTES, 'UTF-8') . '"';
    include __DIR__ . '/../../../../Views/admin/_partials/ui/btn.php';
else:
?>
<span class="inline-flex items-center px-4 py-2.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed"
      title="<?= htmlspecialchars($loteTitulo, ENT_QUOTES, 'UTF-8') ?>">
    <i class="fa-solid fa-print mr-2"></i> Imprimir em lote
</span>
<?php
endif;
$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFilterDrawer()';
$ui_btn_filter_count = $filtrosAtivosCount;
include __DIR__ . '/../../../../Views/admin/_partials/ui/btn.php';
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

if (!$schemaPronto):
?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_09_09_fechamento_periodo.sql</code> no painel Master. O painel já lista turmas; o estado canônico só persiste depois da migration.
</div>
<?php endif;

$filtros_mostrar_turma = true;
$filtros_action = URL . '/admin/fechamento';
$filtros_offcanvas = true;
include __DIR__ . '/../../../../Views/admin/resultados-finais/_filtros.php';

$statusBadge = static function (string $status): string {
    return match (FechamentoMaquinaEstados::normalizar($status)) {
        FechamentoMaquinaEstados::HOMOLOGADO => 'bg-green-100 text-green-700',
        FechamentoMaquinaEstados::RETIFICADO => 'bg-purple-100 text-purple-700',
        FechamentoMaquinaEstados::EM_RECUPERACAO => 'bg-amber-100 text-amber-800',
        FechamentoMaquinaEstados::EM_FECHAMENTO => 'bg-sky-100 text-sky-800',
        default => 'bg-gray-100 text-gray-600',
    };
};
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estado</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Frequência</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Recuperação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendências</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($paineis === []): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-clipboard-check text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma turma encontrada para este filtro.</p>
                    </td>
                </tr>
                <?php else: foreach ($paineis as $p):
                    $turma = $p['turma'] ?? [];
                    $resumo = $p['resumo'] ?? [];
                    $tid = (int) ($turma['id'] ?? 0);
                    $qs = http_build_query([
                        'ano_letivo' => $anoLetivo,
                        'periodo_tipo' => $periodoTipo,
                        'periodo_numero' => $periodoNumero,
                    ]);
                    $status = (string) ($p['status'] ?? 'ABERTO');
                    $pend = (int) ($p['pendencias'] ?? 0);
                    $totalAlunos = (int) ($resumo['total'] ?? 0);
                    $chamadasPendentes = (int) ($resumo['chamadas_pendentes'] ?? 0);
                    $urlTurma = URL . '/admin/fechamento/turma/' . $tid . '?' . htmlspecialchars($qs);
                    $periodoEncerrado = $status === FechamentoMaquinaEstados::HOMOLOGADO;
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($turma['nome'] ?? '')) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($p['periodo']['label'] ?? '')) ?> / <?= $anoLetivo ?></div>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $statusBadge($status) ?>">
                            <?= htmlspecialchars((string) ($p['status_rotulo'] ?? $status)) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <a href="<?= $urlTurma ?>#alunos" class="text-indigo-700 hover:underline font-medium">
                            <?= $totalAlunos ?>
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <a href="<?= $urlTurma ?>#frequencia" class="text-indigo-700 hover:underline font-medium">
                            <?php if ($periodoEncerrado): ?>
                                Encerrado
                            <?php elseif ($chamadasPendentes > 0): ?>
                                <?= $chamadasPendentes ?> chamada<?= $chamadasPendentes === 1 ? '' : 's' ?> pendente<?= $chamadasPendentes === 1 ? '' : 's' ?>
                            <?php else: ?>
                                Em dia
                            <?php endif; ?>
                        </a>
                    </td>
                    <td class="px-6 py-4 text-sm">
                        <a href="<?= $urlTurma ?>#recuperacao" class="text-indigo-700 hover:underline font-medium">
                            <?= (int) ($resumo['recuperacao'] ?? 0) ?>
                        </a>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $pend > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-700' ?>">
                            <?= $pend > 0 ? $pend . ' crítica(s)' : 'OK' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php ob_start(); ?>
                        <a href="<?= $urlTurma ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-user-group text-gray-400 w-4 text-center"></i> Alunos
                        </a>
                        <?php if ($periodoEncerrado): ?>
                        <a href="<?= URL ?>/admin/fechamento/impressao-lote?<?= htmlspecialchars($qs) ?>&amp;turma_id=<?= $tid ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-folder-open text-gray-400 w-4 text-center"></i> Documentação
                        </a>
                        <?php endif; ?>
                        <button type="button"
                                data-fechamento-consulta="homologacoes"
                                data-turma-id="<?= $tid ?>"
                                data-turma-nome="<?= htmlspecialchars((string) ($turma['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-book text-gray-400 w-4 text-center"></i> Registro de homologações
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-fech-' . $tid;
                        $row_actions_dropdown_menu_class = 'w-80';
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php include __DIR__ . '/_drawer_consulta.php'; ?>
