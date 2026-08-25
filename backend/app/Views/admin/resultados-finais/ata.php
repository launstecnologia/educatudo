<?php
$preview = is_array($preview ?? null) ? $preview : [];
$turma = is_array($preview['turma'] ?? null) ? $preview['turma'] : [];
$periodo = is_array($preview['periodo'] ?? null) ? $preview['periodo'] : [];
$linhas = is_array($preview['linhas'] ?? null) ? $preview['linhas'] : [];
$resumo = is_array($preview['resumo'] ?? null) ? $preview['resumo'] : [];
$anoLetivo = (int) ($ano_letivo ?? ($periodo['ano_letivo'] ?? date('Y')));
$periodoTipo = (string) ($periodo_tipo ?? ($periodo['tipo'] ?? 'ano'));
$periodoNumero = (int) ($periodo_numero ?? ($periodo['numero'] ?? 0));
$turmaId = (int) ($turma['id'] ?? 0);
$qs = http_build_query(['ano_letivo' => $anoLetivo, 'periodo_tipo' => $periodoTipo, 'periodo_numero' => $periodoNumero]);

$page_header_title = 'Ata de Resultados Finais';
$page_header_subtitle = (string) ($turma['nome'] ?? 'Turma') . ' · ' . ($periodo['label'] ?? '') . ' / ' . $anoLetivo;
ob_start();
?>
<a href="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>?<?= htmlspecialchars($qs) ?>" class="text-gray-600 hover:text-gray-900 text-sm">← Voltar</a>
<a href="<?= URL ?>/admin/resultados-finais/turma/<?= $turmaId ?>/ata/pdf?<?= htmlspecialchars($qs) ?>"
   target="_blank" rel="noopener"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:opacity-90">
    <i class="fa-solid fa-file-pdf mr-2"></i> Emitir PDF
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 p-4"><div class="text-xs text-gray-500">Alunos</div><div class="text-2xl font-semibold"><?= (int) ($resumo['total'] ?? 0) ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-4"><div class="text-xs text-gray-500">Homologados</div><div class="text-2xl font-semibold"><?= (int) ($resumo['homologados'] ?? 0) ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-4"><div class="text-xs text-gray-500">Aprovados</div><div class="text-2xl font-semibold"><?= (int) ($resumo['aprovados'] ?? 0) ?></div></div>
    <div class="bg-white rounded-xl border border-gray-200 p-4"><div class="text-xs text-gray-500">Pendências</div><div class="text-2xl font-semibold"><?= (int) ($resumo['pendencias'] ?? 0) ?></div></div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frequência</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Resultado</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php foreach ($linhas as $linha):
                    $freq = $linha['frequencia']['percentual'] ?? null;
                ?>
                <tr>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($linha['aluno']['nome'] ?? '')) ?></td>
                    <td class="px-4 py-2"><?= is_numeric($freq) ? number_format((float) $freq, 1, ',', '.') . '%' : '—' ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($linha['rotulo'] ?? '—')) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($linha['status'] ?? '')) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
