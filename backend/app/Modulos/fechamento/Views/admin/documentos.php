<?php
require_once __DIR__ . '/../../../../Models/Education/ResultadoAcademico.php';

$emissoes = is_array($emissoes ?? null) ? $emissoes : [];
$paineis = is_array($paineis ?? null) ? $paineis : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$turmaId = (int) ($turma_id ?? 0);
$tipo = (string) ($tipo ?? '');
$qs = http_build_query([
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
    'turma_id' => $turmaId,
]);

$page_header_title = 'Documentos do período';
$page_header_subtitle = 'Boletins, atas e fichas. Emissão oficial usa o snapshot homologado; rascunho/prévia continua disponível.';
ob_start();
?>
<a href="<?= URL ?>/admin/fechamento" class="text-gray-600 hover:text-gray-900 text-sm">← Fechamento</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

$filtros_mostrar_turma = true;
$filtros_action = URL . '/admin/documentos-periodo';
include __DIR__ . '/../../../../Views/admin/resultados-finais/_filtros.php';
?>

<?php if ($turmaId > 0 && $paineis !== []):
    $preview = $paineis[0];
    $linhas = $preview['linhas'] ?? [];
    $homologados = (int) (($preview['resumo']['homologados'] ?? 0));
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1"><?= htmlspecialchars((string) ($preview['turma']['nome'] ?? 'Turma')) ?></h3>
    <p class="text-sm text-gray-500 mb-4">
        <?= $homologados > 0 ? 'Documentos oficiais usam o snapshot homologado.' : 'Ainda não homologado — PDF sai como rascunho/prévia, sem valor de fechamento oficial.' ?>
    </p>
    <div class="flex flex-wrap gap-2">
        <a href="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/ata?<?= htmlspecialchars($qs) ?>"
           class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Ata (prévia)</a>
        <a href="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/ata/pdf?<?= htmlspecialchars($qs) ?>"
           target="_blank" rel="noopener"
           class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Ata PDF</a>
    </div>
    <div class="mt-4 overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="text-left text-xs text-gray-500 uppercase">
                <tr>
                    <th class="py-2 pr-4">Aluno</th>
                    <th class="py-2 pr-4">Situação</th>
                    <th class="py-2">Documentos</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($linhas as $linha):
                    $aid = (int) ($linha['aluno']['id'] ?? 0);
                    $qsAluno = $qs . '&turma_id=' . $turmaId;
                ?>
                <tr>
                    <td class="py-2 pr-4"><?= htmlspecialchars((string) ($linha['aluno']['nome'] ?? '')) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars((string) ($linha['rotulo'] ?? '')) ?></td>
                    <td class="py-2">
                        <a class="text-accent underline mr-3" href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/ficha?<?= htmlspecialchars($qsAluno) ?>">Ficha</a>
                        <a class="text-accent underline" target="_blank" rel="noopener" href="<?= URL ?>/admin/resultados-finais/aluno/<?= $aid ?>/boletim/pdf?<?= htmlspecialchars($qsAluno) ?>">Boletim PDF</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-100">
        <h3 class="text-lg font-semibold text-gray-900">Histórico de emissões</h3>
        <p class="text-sm text-gray-500">Cada PDF oficial gera uma linha aqui.</p>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Quando</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma / aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nº</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($emissoes === []): ?>
                <tr><td colspan="4" class="px-6 py-12 text-center text-gray-500">Nenhuma emissão neste filtro.</td></tr>
                <?php else: foreach ($emissoes as $em): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($em['emitido_em'] ?? '')) ?></td>
                    <td class="px-6 py-4 text-sm"><?= htmlspecialchars(ResultadoAcademico::DOCUMENTO_TIPOS[$em['tipo'] ?? ''] ?? (string) ($em['tipo'] ?? '')) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?= htmlspecialchars((string) ($em['turma_nome'] ?? '—')) ?>
                        <?php if (!empty($em['aluno_nome'])): ?> · <?= htmlspecialchars((string) $em['aluno_nome']) ?><?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= (int) ($em['numero'] ?? 0) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
