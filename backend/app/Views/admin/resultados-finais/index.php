<?php
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';
require_once __DIR__ . '/../../../Services/ResultadoHomologacaoService.php';

$paineis = is_array($paineis ?? null) ? $paineis : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);

$page_header_title = 'Resultados finais';
$page_header_subtitle = 'Fechamento por turma, homologação imutável e documentos oficiais (boletim, ficha, ata, relatórios).';
ob_start();
?>
<a href="<?= URL ?>/admin/resultados-finais/layouts" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-palette mr-2"></i> Layouts
</a>
<a href="<?= URL ?>/admin/resultados-finais/relatorios" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:opacity-90">
    <i class="fa-solid fa-chart-column mr-2"></i> Relatórios
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';

$filtros_mostrar_turma = true;
$filtros_action = URL . '/admin/resultados-finais';
include __DIR__ . '/_filtros.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Homologados</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendências</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aprov. / Reprov.</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($paineis === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
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
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($turma['nome'] ?? '')) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($p['periodo']['label'] ?? '')) ?> / <?= $anoLetivo ?></div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) ($resumo['total'] ?? 0) ?></td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= ((int) ($resumo['homologados'] ?? 0) > 0) ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                            <?= (int) ($resumo['homologados'] ?? 0) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <?php $pend = (int) ($resumo['pendencias'] ?? 0); ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $pend > 0 ? 'bg-amber-100 text-amber-800' : 'bg-green-100 text-green-700' ?>">
                            <?= $pend > 0 ? $pend . ' crítica(s)' : 'OK' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?= (int) ($resumo['aprovados'] ?? 0) ?> / <?= (int) ($resumo['reprovados'] ?? 0) ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/resultados-finais/turma/<?= $tid ?>?<?= htmlspecialchars($qs) ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-list-check text-gray-400 w-4 text-center"></i> Fechamento
                        </a>
                        <a href="<?= URL ?>/admin/resultados-finais/turma/<?= $tid ?>/ata?<?= htmlspecialchars($qs) ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-file-lines text-gray-400 w-4 text-center"></i> Ata
                        </a>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-rf-' . $tid;
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
