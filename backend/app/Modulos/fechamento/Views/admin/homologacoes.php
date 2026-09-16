<?php
require_once __DIR__ . '/../../Services/FechamentoMaquinaEstados.php';

$registros = is_array($registros ?? null) ? $registros : [];
$historicos = is_array($historicos ?? null) ? $historicos : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$schemaPronto = !empty($schema_pronto);

$page_header_title = 'Homologações';
$page_header_subtitle = 'Livro de registros e retificações do fechamento por turma e período.';
ob_start();
?>
<a href="<?= URL ?>/admin/fechamento" class="text-gray-600 hover:text-gray-900 text-sm">← Fechamento</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';

if (!$schemaPronto):
?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code>2026_09_09_fechamento_periodo.sql</code> para gravar o livro de homologações.
</div>
<?php endif; ?>

<form method="GET" action="<?= URL ?>/admin/homologacoes" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Ano letivo</label>
        <select name="ano_letivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <?php foreach (($anos ?? []) as $ano): ?>
                <option value="<?= (int) $ano ?>" <?= $anoLetivo === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Turma</label>
        <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <option value="0">Todas</option>
            <?php foreach (($turmas ?? []) as $t): ?>
                <option value="<?= (int) $t['id'] ?>" <?= (int) ($turma_id ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) ($t['nome'] ?? '')) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Filtrar</button>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Período</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vigente</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Homologação / retificação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Auditoria</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php if ($registros === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhum registro de fechamento neste filtro.</td>
                </tr>
                <?php else: foreach ($registros as $reg):
                    $fid = (int) ($reg['id'] ?? 0);
                    $hist = $historicos[$fid] ?? [];
                    $st = FechamentoMaquinaEstados::normalizar((string) ($reg['status'] ?? ''));
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($reg['turma_nome'] ?? '')) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($reg['periodo_ref'] ?? '')) ?></td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">
                            <?= htmlspecialchars(FechamentoMaquinaEstados::rotulo($st)) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm"><?= !empty($reg['vigente']) ? 'Sim' : 'Histórico' ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?php if (!empty($reg['homologado_em'])): ?>
                            <?= htmlspecialchars((string) $reg['homologado_em']) ?>
                            <?php if (!empty($reg['homologado_por_nome'])): ?> · <?= htmlspecialchars((string) $reg['homologado_por_nome']) ?><?php endif; ?>
                        <?php else: ?>—<?php endif; ?>
                        <?php if (!empty($reg['justificativa'])): ?>
                            <div class="text-xs text-gray-500 mt-1"><?= htmlspecialchars((string) $reg['justificativa']) ?></div>
                        <?php endif; ?>
                        <?php if (!empty($reg['retificado_de_id'])): ?>
                            <div class="text-xs text-purple-700 mt-1">Retifica #<?= (int) $reg['retificado_de_id'] ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-xs text-gray-600">
                        <?php if ($hist === []): ?>
                            —
                        <?php else: foreach ($hist as $h): ?>
                            <div><?= htmlspecialchars((string) ($h['status_anterior'] ?? '—')) ?> → <?= htmlspecialchars((string) ($h['status_novo'] ?? '')) ?></div>
                        <?php endforeach; endif; ?>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
