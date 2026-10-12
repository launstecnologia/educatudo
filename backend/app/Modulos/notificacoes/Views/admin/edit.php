<?php
/**
 * View: Admin - Editar Notificação
 */
$title = $title ?? 'Editar Notificação';
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
            <a href="<?= URL ?>/admin/notifications/<?= $notificacao['id'] ?>" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                </svg>
                Visualizar
            </a>
        </div>
    </div>

    <?php if (isset($flash_message) && $flash_message): ?>
        <div class="mb-6 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <?php if ($notificacao): ?>
        <form action="<?= URL ?>/admin/notifications/<?= $notificacao['id'] ?>/update" method="POST" enctype="multipart/form-data" class="space-y-6">
            <!-- Informações Básicas -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Informações Básicas</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título *</label>
                        <input type="text" id="titulo" name="titulo" value="<?= htmlspecialchars($notificacao['titulo']) ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required>
                    </div>
                    
                    <div>
                        <label for="prioridade" class="block text-sm font-medium text-gray-700 mb-2">Prioridade</label>
                        <select id="prioridade" name="prioridade" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                            <option value="baixa" <?= $notificacao['prioridade'] === 'baixa' ? 'selected' : '' ?>>Baixa</option>
                            <option value="normal" <?= $notificacao['prioridade'] === 'normal' ? 'selected' : '' ?>>Normal</option>
                            <option value="alta" <?= $notificacao['prioridade'] === 'alta' ? 'selected' : '' ?>>Alta</option>
                            <option value="urgente" <?= $notificacao['prioridade'] === 'urgente' ? 'selected' : '' ?>>Urgente</option>
                        </select>
                    </div>
                </div>

                <div class="mt-6">
                    <label for="data_expiracao" class="block text-sm font-medium text-gray-700 mb-2">Data de Expiração</label>
                    <input type="datetime-local" id="data_expiracao" name="data_expiracao" 
                           value="<?= $notificacao['data_expiracao'] ? date('Y-m-d\TH:i', strtotime($notificacao['data_expiracao'])) : '' ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <div class="mt-6">
                    <label class="flex items-center">
                        <input type="checkbox" name="ativo" value="1" <?= $notificacao['ativo'] ? 'checked' : '' ?>
                               class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300 rounded">
                        <span class="ml-2 text-sm text-gray-700">Notificação ativa</span>
                    </label>
                </div>
            </div>

            <!-- Conteúdo -->
            <div class="bg-white rounded-lg shadow-lg p-8">
                <h2 class="text-xl font-semibold text-gray-900 mb-6">Conteúdo</h2>
                
                <div>
                    <label for="conteudo" class="block text-sm font-medium text-gray-700 mb-2">Conteúdo *</label>
                    <textarea id="conteudo" name="conteudo" rows="8" 
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500" required><?= htmlspecialchars($notificacao['conteudo']) ?></textarea>
                </div>

                <?php if ($notificacao['arquivo_url']): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Imagem Atual</label>
                    <img src="<?= URL ?><?= htmlspecialchars($notificacao['arquivo_url']) ?>" 
                         alt="Imagem atual" class="max-w-xs h-auto rounded-lg shadow-md">
                </div>
                <?php endif; ?>

                <div class="mt-4">
                    <label for="arquivo_imagem" class="block text-sm font-medium text-gray-700 mb-2">Nova Imagem</label>
                    <input type="file" id="arquivo_imagem" name="arquivo_imagem" accept="image/*"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>

                <?php if ($notificacao['video_url']): ?>
                <div class="mt-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">URL do Vídeo Atual</label>
                    <p class="text-blue-600"><?= htmlspecialchars($notificacao['video_url']) ?></p>
                </div>
                <?php endif; ?>

                <div class="mt-4">
                    <label for="video_url" class="block text-sm font-medium text-gray-700 mb-2">Nova URL do Vídeo</label>
                    <input type="url" id="video_url" name="video_url" value="<?= htmlspecialchars($notificacao['video_url'] ?? '') ?>"
                           placeholder="https://www.youtube.com/watch?v=..."
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>

            <!-- Botões -->
            <div class="flex justify-end gap-4">
                <a href="<?= URL ?>/admin/notifications/<?= $notificacao['id'] ?>" 
                   class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                    Cancelar
                </a>
                <button type="submit" 
                        class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90">
                    Salvar Alterações
                </button>
            </div>
        </form>
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
