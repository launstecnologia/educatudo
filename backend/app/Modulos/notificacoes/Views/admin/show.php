<?php
/**
 * View: Admin - Detalhes da Notificação
 */
$title = $title ?? 'Detalhes da Notificação';
$arquivoUrl = $notificacao['arquivo_url'] ?? '';
if (!empty($arquivoUrl) && !preg_match('#^https?://#i', $arquivoUrl)) {
    $arquivoUrl = URL . $arquivoUrl;
}
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= $title ?></h1>
        <div class="flex gap-3">
            <a href="<?= URL ?>/admin/notifications" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
            <a href="<?= URL ?>/admin/notifications/<?= $notificacao['id'] ?>/edit" class="btn-primary-custom px-4 py-2 rounded-lg flex items-center hover:opacity-90">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                Editar
            </a>
        </div>
    </div>

    <?php if (isset($flash_message) && $flash_message): ?>
        <div class="mb-6 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($notificacao): ?>
        <div class="bg-white rounded-lg shadow-lg overflow-hidden">
            <!-- Header da Notificação -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 px-6 py-4">
                <div class="flex items-center justify-between">
                    <div>
                        <h2 class="text-2xl font-bold text-white"><?= htmlspecialchars($notificacao['titulo']) ?></h2>
                        <p class="text-blue-100 mt-1">
                            Enviado por: <span class="font-semibold"><?= htmlspecialchars($notificacao['nome_enviador'] ?? 'Sistema') ?></span>
                        </p>
                    </div>
                    <div class="text-right text-white">
                        <div class="text-sm text-blue-100">Enviado em</div>
                        <div class="font-semibold"><?= date('d/m/Y H:i', strtotime($notificacao['created_at'])) ?></div>
                    </div>
                </div>
            </div>

            <!-- Informações da Notificação -->
            <div class="px-6 py-4 border-b border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="text-sm font-medium text-gray-500">Tipo de Conteúdo</label>
                        <p class="text-gray-900 capitalize"><?= htmlspecialchars($notificacao['tipo_conteudo']) ?></p>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Prioridade</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?= $notificacao['prioridade'] === 'urgente' ? 'bg-red-100 text-red-800' : 
                                ($notificacao['prioridade'] === 'alta' ? 'bg-orange-100 text-orange-800' : 
                                ($notificacao['prioridade'] === 'normal' ? 'bg-blue-100 text-blue-800' : 'bg-gray-100 text-gray-800')) ?>">
                            <?= ucfirst($notificacao['prioridade']) ?>
                        </span>
                    </div>
                    <div>
                        <label class="text-sm font-medium text-gray-500">Status</label>
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium
                            <?= $notificacao['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $notificacao['ativo'] ? 'Ativa' : 'Inativa' ?>
                        </span>
                    </div>
                </div>

                <?php if ($notificacao['tipos_conteudo']): ?>
                <div class="mt-4">
                    <label class="text-sm font-medium text-gray-500">Tipos de Conteúdo</label>
                    <div class="flex flex-wrap gap-2 mt-1">
                        <?php 
                        $tipos = explode(',', $notificacao['tipos_conteudo']);
                        foreach ($tipos as $tipo): 
                        ?>
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                <?= ucfirst(trim($tipo)) ?>
                            </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($notificacao['is_update']): ?>
                <div class="mt-4">
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-yellow-100 text-yellow-800">
                        <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                        </svg>
                        Notificação de Atualização
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Conteúdo da Notificação -->
            <div class="px-6 py-4">
                <label class="text-sm font-medium text-gray-500">Conteúdo</label>
                <div class="mt-2 prose max-w-none">
                    <?= rich_text_render($notificacao['conteudo']) ?>
                </div>
            </div>

            <!-- Mídia -->
            <?php if ($notificacao['arquivo_url'] || $notificacao['video_url']): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <label class="text-sm font-medium text-gray-500">Mídia</label>
                <div class="mt-2">
                    <?php if ($notificacao['arquivo_url']): ?>
                        <div class="mb-4">
                            <img src="<?= htmlspecialchars($arquivoUrl) ?>" 
                                 alt="Imagem da notificação" 
                                 class="max-w-full h-auto rounded-lg shadow-md">
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($notificacao['video_url']): ?>
                        <div class="mb-4">
                            <div class="bg-gray-100 p-4 rounded-lg">
                                <p class="text-sm text-gray-600 mb-2">Vídeo:</p>
                                <a href="<?= htmlspecialchars($notificacao['video_url']) ?>" 
                                   target="_blank" 
                                   class="text-blue-600 hover:text-blue-800 underline">
                                    <?= htmlspecialchars($notificacao['video_url']) ?>
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Destinatários -->
            <?php if (isset($destinatarios) && !empty($destinatarios)): ?>
            <div class="px-6 py-4 border-t border-gray-200">
                <label class="text-sm font-medium text-gray-500">Destinatários</label>
                <div class="mt-2 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-2">
                    <?php foreach ($destinatarios as $destinatario): ?>
                        <div class="flex items-center p-2 bg-gray-50 rounded-lg">
                            <div class="flex-1">
                                <div class="text-sm font-medium text-gray-900">
                                    <?= htmlspecialchars($destinatario['nome_destinatario'] ?? 'Todos') ?>
                                </div>
                                <div class="text-xs text-gray-500 capitalize">
                                    <?= htmlspecialchars($destinatario['tipo_destinatario']) ?>
                                </div>
                            </div>
                            <div class="ml-2">
                                <?php if ($destinatario['lida']): ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        Lida
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center px-2 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                        Não lida
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Estatísticas -->
            <?php if (isset($estatisticas) && $estatisticas): ?>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <label class="text-sm font-medium text-gray-500">Estatísticas de Entrega</label>
                <div class="mt-2 grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div class="text-center">
                        <div class="text-2xl font-bold text-gray-900"><?= $estatisticas['total_destinatarios'] ?></div>
                        <div class="text-sm text-gray-500">Total</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-green-600"><?= $estatisticas['total_lidas'] ?></div>
                        <div class="text-sm text-gray-500">Lidas</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-blue-600"><?= $estatisticas['total_visualizadas'] ?></div>
                        <div class="text-sm text-gray-500">Visualizadas</div>
                    </div>
                    <div class="text-center">
                        <div class="text-2xl font-bold text-purple-600"><?= $estatisticas['percentual_lidas'] ?>%</div>
                        <div class="text-sm text-gray-500">Taxa de Leitura</div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Datas -->
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                    <div>
                        <label class="font-medium text-gray-500">Data de Envio</label>
                        <p class="text-gray-900"><?= date('d/m/Y H:i:s', strtotime($notificacao['created_at'])) ?></p>
                    </div>
                    <?php if ($notificacao['data_expiracao']): ?>
                    <div>
                        <label class="font-medium text-gray-500">Data de Expiração</label>
                        <p class="text-gray-900"><?= date('d/m/Y H:i:s', strtotime($notificacao['data_expiracao'])) ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="text-center py-12">
            <svg class="w-16 h-16 mx-auto text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
            </svg>
            <h3 class="text-lg font-medium text-gray-900 mb-2">Notificação não encontrada</h3>
            <p class="text-gray-500">A notificação solicitada não existe ou foi removida.</p>
            <div class="mt-4">
                <a href="<?= URL ?>/admin/notifications" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg">
                    Voltar para Lista
                </a>
            </div>
        </div>
    <?php endif; ?>
</div>
