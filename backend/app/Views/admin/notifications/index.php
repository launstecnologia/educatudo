<?php
/**
 * View: Admin - Lista de Notificações
 */
$title = $title ?? 'Gerenciar Notificações';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= $title ?></h1>
        <a href="<?= URL ?>/admin/notifications/create" class="btn-primary-custom px-4 py-2 rounded-lg flex items-center hover:opacity-90">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
            Nova Notificação
        </a>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="mb-4 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow overflow-hidden">
        <?php if (empty($notificacoes)): ?>
            <div class="p-8 text-center">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Nenhuma notificação encontrada</h3>
                <p class="text-gray-500">Comece criando sua primeira notificação.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Notificação</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Enviado por</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Prioridade</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatários</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($notificacoes as $notificacao): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10">
                                            <?php if ($notificacao['tipo_conteudo'] === 'video'): ?>
                                                <div class="h-10 w-10 rounded-lg bg-red-100 flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                                    </svg>
                                                </div>
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded-lg bg-blue-100 flex items-center justify-center">
                                                    <svg class="w-5 h-5 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                    </svg>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($notificacao['titulo']) ?></div>
                                            <?php
                                            $previewTexto = trim(strip_tags($notificacao['conteudo'] ?? ''));
                                            ?>
                                            <div class="text-sm text-gray-500">
                                                <?= $previewTexto !== '' ? htmlspecialchars(substr($previewTexto, 0, 50)) . '...' : 'Sem conteúdo de texto' ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= htmlspecialchars($notificacao['nome_enviador']) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <?php
                                    $prioridadeClasses = [
                                        'baixa' => 'bg-gray-100 text-gray-800',
                                        'normal' => 'bg-blue-100 text-blue-800',
                                        'alta' => 'bg-yellow-100 text-yellow-800',
                                        'urgente' => 'bg-red-100 text-red-800'
                                    ];
                                    $classe = $prioridadeClasses[$notificacao['prioridade']] ?? 'bg-gray-100 text-gray-800';
                                    ?>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full <?= $classe ?>">
                                        <?= ucfirst($notificacao['prioridade']) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= $notificacao['total_destinatarios'] ?> destinatários
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900">
                                        <?= $notificacao['total_lidas'] ?> / <?= $notificacao['total_destinatarios'] ?> lidas
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <?php $percentual = $notificacao['total_destinatarios'] > 0 ? ($notificacao['total_lidas'] / $notificacao['total_destinatarios']) * 100 : 0; ?>
                                        <div class="bg-blue-600 h-2 rounded-full" style="width: <?= $percentual ?>%"></div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?= date('d/m/Y H:i', strtotime($notificacao['created_at'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <div class="flex space-x-2">
                                        <a href="<?= URL ?>/admin/notifications/<?= $notificacao['id'] ?>" class="text-blue-600 hover:text-blue-900">Ver</a>
                                        <a href="<?= URL ?>/admin/notifications/<?= $notificacao['id'] ?>/delete" 
                                           class="text-red-600 hover:text-red-900"
                                           onclick="return confirm('Tem certeza que deseja excluir esta notificação?')">Excluir</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>
