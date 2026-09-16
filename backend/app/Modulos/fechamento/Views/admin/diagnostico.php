<?php
$matriz = is_array($matriz ?? null) ? $matriz : [];
$page_header_title = 'Diagnóstico do menu';
$page_header_subtitle = 'Item × FeatureGate × permissão × perfil. Em desenvolvimento, itens ocultos também vão para o log.';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Grupo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Item</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rota</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Permissão</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gate</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Visível</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Motivo se oculto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($matriz as $item): ?>
                <tr class="hover:bg-gray-50 <?= empty($item['visivel']) ? 'bg-amber-50/40' : '' ?>">
                    <td class="px-6 py-3 text-sm text-gray-600"><?= htmlspecialchars((string) ($item['grupo'] ?? '')) ?></td>
                    <td class="px-6 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($item['label'] ?? '')) ?></td>
                    <td class="px-6 py-3 text-xs text-gray-500"><?= htmlspecialchars((string) ($item['path'] ?? '')) ?></td>
                    <td class="px-6 py-3 text-xs"><?= htmlspecialchars((string) ($item['permissao'] ?? '')) ?> <?= !empty($item['permissao_ok']) ? '✓' : '✗' ?></td>
                    <td class="px-6 py-3 text-xs"><?= htmlspecialchars((string) ($item['feature'] ?? '—')) ?> <?= !empty($item['feature_on']) ? '✓' : '✗' ?></td>
                    <td class="px-6 py-3">
                        <?php if (!empty($item['visivel'])): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700">Sim</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-amber-100 text-amber-800">Não</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-3 text-xs text-gray-600"><?= htmlspecialchars(implode('; ', $item['motivos'] ?? [])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
