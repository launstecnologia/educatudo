<?php
$esc = fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$campanhas = $campanhas ?? [];
$statusLabels = [
    'rascunho' => 'Rascunho',
    'aberta' => 'Aberta',
    'encerrada' => 'Encerrada',
];
$statusColors = [
    'rascunho' => 'bg-gray-100 text-gray-700',
    'aberta' => 'bg-green-100 text-green-800',
    'encerrada' => 'bg-red-100 text-red-800',
];

$page_header_title = 'Campanhas de rematrícula';
$page_header_subtitle = 'Prazo, mapa de preços e geração de processos para o ano seguinte.';
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm">← Matrículas</a>
<a href="<?= URL ?>/admin/enrollment/campanhas/create" class="btn-primary text-sm">
    <i class="fa-solid fa-plus mr-1.5"></i> Nova campanha
</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Campanha</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Anos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Prazo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Processos</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($campanhas === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        Nenhuma campanha cadastrada.
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($campanhas as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= $esc($c['nome'] ?? '') ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?= $esc($c['ano_origem'] ?? '—') ?> → <?= $esc($c['ano_destino'] ?? '—') ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600">
                        <?= !empty($c['inicio']) ? date('d/m/Y', strtotime((string) $c['inicio'])) : '—' ?>
                        —
                        <?= !empty($c['fim']) ? date('d/m/Y', strtotime((string) $c['fim'])) : '—' ?>
                    </td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $statusColors[$c['status'] ?? ''] ?? 'bg-gray-100 text-gray-700' ?>">
                            <?= $esc($statusLabels[$c['status'] ?? ''] ?? ($c['status'] ?? '')) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) ($c['total_processos'] ?? 0) ?></td>
                    <td class="px-6 py-4 text-right">
                        <a href="<?= URL ?>/admin/enrollment/campanhas/<?= (int) $c['id'] ?>" class="text-sm font-medium text-primary hover:underline">Abrir</a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
