<?php
/**
 * View: Admin - Detalhes da Notificação Push
 */
$title = $title ?? 'Detalhes da Notificação Push';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= $title ?></h1>
        <a href="<?= URL ?>/admin/notificacoes-push" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center">
            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>

    <?php if ($notificacao): ?>
        <div class="bg-white rounded-lg shadow-lg overflow-hidden mb-6">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-xl font-semibold text-gray-900"><?= htmlspecialchars($notificacao['titulo']) ?></h2>
                <p class="text-gray-600 mt-2"><?= nl2br(htmlspecialchars($notificacao['mensagem'])) ?></p>
                <div class="mt-4 flex flex-wrap gap-4 text-sm text-gray-500">
                    <span>Tipo: <strong><?= htmlspecialchars($notificacao['tipo_destino']) ?></strong></span>
                    <span>Data: <?= date('d/m/Y H:i', strtotime($notificacao['created_at'])) ?></span>
                    <?php if (!empty($notificacao['url'])): ?>
                        <span>URL: <a href="<?= htmlspecialchars($notificacao['url']) ?>" class="text-blue-600 hover:underline" target="_blank" rel="noopener"><?= htmlspecialchars($notificacao['url']) ?></a></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 grid grid-cols-2 md:grid-cols-4 gap-4">
                <div>
                    <div class="text-xs font-medium text-gray-500 uppercase">Enviados</div>
                    <div class="text-2xl font-bold text-gray-900"><?= (int)($notificacao['total_envios'] ?? 0) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 uppercase">Entregues</div>
                    <div class="text-2xl font-bold text-gray-900"><?= (int)($notificacao['total_entregues'] ?? 0) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 uppercase">Visualizados</div>
                    <div class="text-2xl font-bold text-green-600"><?= (int)($notificacao['total_visualizados'] ?? 0) ?></div>
                </div>
                <div>
                    <div class="text-xs font-medium text-gray-500 uppercase">Clicados</div>
                    <div class="text-2xl font-bold text-blue-600"><?= (int)($notificacao['total_clicados'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow overflow-hidden">
            <h3 class="px-6 py-3 bg-gray-50 border-b border-gray-200 font-medium text-gray-900">Destinatários</h3>
            <?php if (empty($envios)): ?>
                <div class="p-6 text-center text-gray-500">Nenhum destinatário registrado.</div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Usuário</th>
                                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Perfil</th>
                                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Entregue</th>
                                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Visualizado</th>
                                <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Clicado</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($envios as $e): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-3 text-sm text-gray-900"><?= htmlspecialchars($e['user_nome'] ?? 'ID ' . $e['user_id']) ?></td>
                                    <td class="px-6 py-3 text-sm text-gray-600"><?= htmlspecialchars($e['role']) ?></td>
                                    <td class="px-6 py-3"><?= !empty($e['entregue']) ? 'Sim' : '-' ?></td>
                                    <td class="px-6 py-3"><?= !empty($e['visualizado']) ? 'Sim' : '-' ?></td>
                                    <td class="px-6 py-3"><?= !empty($e['clicado']) ? 'Sim' : '-' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
