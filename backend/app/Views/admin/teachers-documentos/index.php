<?php
$professores = $professores ?? [];
$totalChecklist = (int) ($total_checklist ?? 0);

$page_header_title = 'Documentos dos Professores';
$page_header_subtitle = 'Checklist de documentação (diploma, licenciatura, contrato) por professor.';
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';

if (!($schema_pronto ?? false)): ?>
    <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700 mb-6">
        <i class="fa-solid fa-triangle-exclamation mr-2"></i> Execute a migration <code>2026_06_25_documentos_institucionais.sql</code> no painel Master.
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Documentos entregues</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($professores)): ?>
                    <tr><td colspan="3" class="px-6 py-12 text-center text-gray-500">Nenhum professor ativo encontrado.</td></tr>
                <?php else: foreach ($professores as $p):
                    $entregues = (int) ($p['entregues'] ?? 0);
                    $pct = $totalChecklist > 0 ? round(($entregues / $totalChecklist) * 100) : 0; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $p['nome']) ?></td>
                        <td class="px-6 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-32 h-2 rounded-full bg-gray-100 overflow-hidden">
                                    <div class="h-full <?= $pct >= 100 ? 'bg-green-500' : ($pct >= 50 ? 'bg-amber-500' : 'bg-red-500') ?>" style="width: <?= $pct ?>%"></div>
                                </div>
                                <span class="text-sm text-gray-600"><?= $entregues ?>/<?= $totalChecklist ?></span>
                            </div>
                        </td>
                        <td class="px-6 py-3 text-right">
                            <a href="<?= URL ?>/admin/teachers-documentos?professor_id=<?= (int) $p['id'] ?>" class="text-sm font-medium text-green-700 hover:text-green-900">Gerenciar</a>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
