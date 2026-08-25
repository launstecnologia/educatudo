<?php
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';

$linhas = is_array($linhas ?? null) ? $linhas : [];
$tipo = (string) ($tipo ?? 'relatorio_fechamento');
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$turmaId = (int) ($turma_id ?? 0);
$qs = http_build_query([
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
    'turma_id' => $turmaId,
    'tipo' => $tipo,
]);

$page_header_title = 'Relatórios acadêmicos';
$page_header_subtitle = 'Recortes do resultado homologado (ou prévia, se o fechamento ainda não ocorreu).';
ob_start();
?>
<a href="<?= URL ?>/admin/resultados-finais" class="text-gray-600 hover:text-gray-900 text-sm">← Fechamento</a>
<?php if ($turmaId > 0): ?>
<a href="<?= URL ?>/admin/resultados-finais/relatorios/pdf?<?= htmlspecialchars($qs) ?>" target="_blank" rel="noopener"
   class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">
    <i class="fa-solid fa-file-pdf mr-2"></i> PDF
</a>
<a href="<?= URL ?>/admin/resultados-finais/relatorios/csv?<?= htmlspecialchars($qs) ?>"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold shadow-sm hover:opacity-90">
    <i class="fa-solid fa-file-csv mr-2"></i> CSV
</a>
<?php endif;
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<form method="GET" action="<?= URL ?>/admin/resultados-finais/relatorios" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-6 gap-4 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Relatório</label>
        <select name="tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <?php foreach (ResultadoAcademico::DOCUMENTO_TIPOS as $cod => $lab):
                if (!str_starts_with($cod, 'relatorio_')) {
                    continue;
                }
            ?>
                <option value="<?= htmlspecialchars($cod) ?>" <?= $tipo === $cod ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</label>
        <select name="ano_letivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <?php foreach (($anos ?? []) as $ano): ?>
                <option value="<?= (int) $ano ?>" <?= $anoLetivo === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Período</label>
        <select name="periodo_tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <?php foreach (ResultadoAcademico::PERIODO_TIPOS as $cod => $lab): ?>
                <option value="<?= htmlspecialchars($cod) ?>" <?= $periodoTipo === $cod ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Nº</label>
        <select name="periodo_numero" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <option value="0">—</option>
            <?php for ($n = 1; $n <= 4; $n++): ?>
                <option value="<?= $n ?>" <?= $periodoNumero === $n ? 'selected' : '' ?>><?= $n ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Turma</label>
        <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <option value="0">Selecione</option>
            <?php foreach (($turmas ?? []) as $turma): ?>
                <option value="<?= (int) $turma['id'] ?>" <?= $turmaId === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90 w-full">Gerar</button>
    </div>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Média</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Frequência</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($turmaId <= 0): ?>
                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Selecione uma turma para gerar o relatório.</td></tr>
                <?php elseif ($linhas === []): ?>
                <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">Nenhum aluno neste recorte.</td></tr>
                <?php else: foreach ($linhas as $linha):
                    $media = $linha['avaliado']['media_final'] ?? null;
                    $freq = $linha['frequencia']['percentual'] ?? null;
                ?>
                <tr>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($linha['aluno']['nome'] ?? '')) ?></td>
                    <td class="px-4 py-2"><?= is_numeric($media) ? number_format((float) $media, 1, ',', '.') : '—' ?></td>
                    <td class="px-4 py-2"><?= is_numeric($freq) ? number_format((float) $freq, 1, ',', '.') . '%' : '—' ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($linha['rotulo'] ?? '—')) ?></td>
                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($linha['status'] ?? '')) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
