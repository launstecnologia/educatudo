<?php
/**
 * View: Usuário - Visualizar Notificação Específica
 */
$title = $title ?? 'Notificação';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= $title ?></h1>
        <a href="<?= URL ?>/notifications" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
            </svg>
            Voltar
        </a>
    </div>

    <?php if (!empty($flash_message)): ?>
        <div class="mb-4 p-4 rounded-lg <?= $flash_type === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
            <?= htmlspecialchars($flash_message) ?>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-lg overflow-hidden">
        <div class="p-8">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <?php if ($notificacao['tipo_conteudo'] === 'video'): ?>
                        <div class="w-16 h-16 rounded-lg bg-red-100 flex items-center justify-center">
                            <svg class="w-8 h-8 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                            </svg>
                        </div>
                    <?php else: ?>
                        <div class="w-16 h-16 rounded-lg bg-blue-100 flex items-center justify-center">
                            <svg class="w-8 h-8 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="ml-6 flex-1">
                    <?php
                    $tiposConteudo = json_decode($notificacao['tipos_conteudo'] ?? '[]', true);
                    if (!is_array($tiposConteudo)) {
                        $tiposConteudo = explode(',', $notificacao['tipos_conteudo'] ?? '');
                        $tiposConteudo = array_map('trim', $tiposConteudo);
                        $tiposConteudo = array_filter($tiposConteudo);
                    }

                    $arquivoUrl = $notificacao['arquivo_url'] ?? '';
                    if (!empty($arquivoUrl) && !preg_match('#^https?://#i', $arquivoUrl)) {
                        $arquivoUrl = URL . $arquivoUrl;
                    }
                    ?>
                    <div class="flex justify-between items-start mb-4">
                        <div>
                            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($notificacao['titulo']) ?></h2>
                            <p class="text-sm text-gray-500">Por <?= htmlspecialchars($notificacao['nome_enviador']) ?></p>
                        </div>
                        <div class="flex items-center space-x-2">
                            <?php if (!$notificacao['lida']): ?>
                                <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800">
                                    Nova
                                </span>
                            <?php endif; ?>
                            
                            <?php
                            $prioridadeClasses = [
                                'baixa' => 'bg-gray-100 text-gray-800',
                                'normal' => 'bg-blue-100 text-blue-800',
                                'alta' => 'bg-yellow-100 text-yellow-800',
                                'urgente' => 'bg-red-100 text-red-800'
                            ];
                            $classe = $prioridadeClasses[$notificacao['prioridade']] ?? 'bg-gray-100 text-gray-800';
                            ?>
                            <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium <?= $classe ?>">
                                <?= ucfirst($notificacao['prioridade']) ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="prose max-w-none">
                        <?php if (!empty($notificacao['conteudo'])): ?>
                            <div class="text-gray-700 text-lg leading-relaxed">
                                <?= rich_text_render($notificacao['conteudo']) ?>
                            </div>
                        <?php endif; ?>

                        <?php if (in_array('imagem', $tiposConteudo) && !empty($arquivoUrl)): ?>
                            <div class="mt-6">
                                <img src="<?= htmlspecialchars($arquivoUrl) ?>" alt="Imagem da notificação" class="max-w-full h-auto rounded-lg shadow-lg">
                            </div>
                        <?php endif; ?>

                        <?php if (in_array('video', $tiposConteudo)): ?>
                            <div class="mt-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-3">Vídeo</h3>
                                <?php if (!empty($notificacao['video_url'])): ?>
                                    <a href="<?= htmlspecialchars($notificacao['video_url']) ?>" target="_blank" class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                                        Assistir Vídeo
                                    </a>
                                <?php elseif (!empty($arquivoUrl)): ?>
                                    <video controls class="w-full max-w-2xl rounded-lg shadow-lg">
                                        <source src="<?= htmlspecialchars($arquivoUrl) ?>" type="video/mp4">
                                        Seu navegador não suporta vídeos.
                                    </video>
                                    <p class="text-sm text-gray-500 mt-2">
                                        Arquivo: <?= htmlspecialchars($notificacao['arquivo_nome']) ?>
                                        <?php if ($notificacao['arquivo_tamanho']): ?>
                                            (<?= number_format($notificacao['arquivo_tamanho'] / 1024 / 1024, 2) ?> MB)
                                        <?php endif; ?>
                                    </p>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-8 pt-6 border-t border-gray-200">
                        <div class="flex justify-between items-center">
                            <div class="text-sm text-gray-500">
                                <p>Enviada em: <?= date('d/m/Y H:i', strtotime($notificacao['created_at'])) ?></p>
                                <?php if ($notificacao['lida']): ?>
                                    <p>Lida em: <?= date('d/m/Y H:i', strtotime($notificacao['lida_em'])) ?></p>
                                <?php endif; ?>
                                <?php if ($notificacao['data_expiracao']): ?>
                                    <p>Expira em: <?= date('d/m/Y H:i', strtotime($notificacao['data_expiracao'])) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <div class="flex space-x-3">
                                <?php if (!$notificacao['lida']): ?>
                                    <a href="<?= URL ?>/notifications/<?= $notificacao['id'] ?>/marcar-lida" 
                                       class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg text-sm">
                                        Marcar como lida
                                    </a>
                                <?php endif; ?>
                                <a href="<?= URL ?>/notifications" 
                                   class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-lg text-sm">
                                    Voltar para lista
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
