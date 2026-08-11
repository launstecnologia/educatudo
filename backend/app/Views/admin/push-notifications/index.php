<?php
/**
 * View: Admin - Lista de Notificações Push (OneSignal)
 */
$title = $title ?? 'Notificações Push';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= $title ?></h1>
        <a href="<?= URL ?>/admin/notificacoes-push/criar" class="btn-primary-custom px-4 py-2 rounded-lg flex items-center hover:opacity-90">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Enviar Notificação Push
        </a>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="mb-4 p-4 rounded-lg <?= ($flash_type ?? '') === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <?php if (isset($onesignal_configured) && !$onesignal_configured): ?>
        <div class="mb-4 p-4 rounded-lg bg-amber-100 text-amber-800">
            Configure <strong>ONESIGNAL_APP_ID</strong> e <strong>ONESIGNAL_REST_API_KEY</strong> no .env para enviar notificações.
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (empty($notificacoes)): ?>
            <div class="p-8 text-center">
                <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma notificação push</h3>
                <p class="text-gray-500">Envie sua primeira notificação push pelo botão acima.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enviados</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Visualizados</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Clicados</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($notificacoes as $n): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($n['titulo']) ?></div>
                                    <div class="text-xs text-gray-500"><?= htmlspecialchars(mb_substr($n['mensagem'], 0, 60)) ?>...</div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($n['tipo_destino']) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= (int)($n['total_envios'] ?? 0) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= (int)($n['total_visualizados'] ?? 0) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= (int)($n['total_clicados'] ?? 0) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <a href="<?= URL ?>/admin/notificacoes-push/<?= (int)$n['id'] ?>" class="text-blue-600 hover:text-blue-800 text-sm font-medium">Ver detalhes</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
