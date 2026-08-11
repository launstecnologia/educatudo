<?php
/**
 * View: Usuário - Lista de Notificações
 */
$title = $title ?? 'Minhas Notificações';
?>

<div class="container mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-3xl font-bold text-gray-900"><?= $title ?></h1>
        <a href="<?= URL ?>/notifications/atualizar" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-lg flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
            </svg>
            Atualizar Sistema
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
                <p class="text-gray-500">Você não possui notificações no momento.</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-200">
                <?php foreach ($notificacoes as $notificacao): ?>
                    <div class="p-6 hover:bg-gray-50 transition-colors">
                        <div class="flex items-start">
                            <div class="flex-shrink-0">
                                <?php if ($notificacao['tipo_conteudo'] === 'video'): ?>
                                    <div class="w-12 h-12 rounded-lg bg-red-100 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                                        </svg>
                                    </div>
                                <?php else: ?>
                                    <div class="w-12 h-12 rounded-lg bg-blue-100 flex items-center justify-center">
                                        <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                        </svg>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="ml-4 flex-1">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h3 class="text-lg font-medium text-gray-900"><?= htmlspecialchars($notificacao['titulo']) ?></h3>
                                        <p class="text-sm text-gray-500 mt-1">Por <?= htmlspecialchars($notificacao['nome_enviador']) ?></p>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <?php if (!$notificacao['lida']): ?>
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
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
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $classe ?>">
                                            <?= ucfirst($notificacao['prioridade']) ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="mt-4 flex justify-between items-center">
                                    <div class="text-sm text-gray-500">
                                        <?= date('d/m/Y H:i', strtotime($notificacao['created_at'])) ?>
                                        <?php if ($notificacao['lida']): ?>
                                            <span class="ml-2">• Lida em <?= date('d/m/Y H:i', strtotime($notificacao['lida_em'])) ?></span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="flex space-x-2">
                                        <?php if (!$notificacao['lida']): ?>
                                            <a href="<?= URL ?>/notifications/<?= $notificacao['id'] ?>/marcar-lida" 
                                               class="text-sm text-blue-600 hover:text-blue-800">
                                                Marcar como lida
                                            </a>
                                        <?php endif; ?>
                                        <button onclick="abrirModalNotificacao(<?= $notificacao['id'] ?>)" 
                                                class="text-sm text-purple-600 hover:text-purple-800 font-medium">
                                            Ver detalhes
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal de Notificação -->
<div id="notification-detail-modal" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display: none;">
    <!-- Backdrop com transição suave -->
    <div id="modal-backdrop" class="fixed inset-0 bg-gray-500/75 transition-opacity duration-300 ease-out opacity-0" onclick="fecharModalNotificacao()"></div>
    
    <!-- Container do Modal -->
    <div class="relative transform transition-all duration-300 ease-out opacity-0 scale-95 translate-y-4 sm:translate-y-0 sm:scale-95">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden border border-gray-200">
            <!-- Header do Modal -->
            <div class="bg-gradient-to-r from-purple-600 to-blue-600 text-white p-6">
                <div class="flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="mx-auto flex size-10 shrink-0 items-center justify-center rounded-full bg-white/20 mr-3">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                        <div>
                            <h2 id="modal-title" class="text-xl font-semibold">Notificação</h2>
                            <p id="modal-sender" class="text-purple-100 text-sm">Por Sistema</p>
                        </div>
                    </div>
                    <button onclick="fecharModalNotificacao()" class="text-white hover:text-purple-200 transition-colors p-1 rounded-full hover:bg-white/10">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Conteúdo do Modal -->
            <div class="bg-white px-6 py-4">
                <div id="modal-content" class="space-y-4 max-h-[60vh] overflow-y-auto">
                    <!-- Conteúdo será carregado via JavaScript -->
                </div>
            </div>

            <!-- Footer do Modal -->
            <div class="bg-gray-50 px-6 py-4 flex justify-between items-center border-t border-gray-200">
                <div class="text-sm text-gray-500">
                    <span id="modal-date">Data</span>
                </div>
                <div class="flex space-x-3">
                    <button onclick="fecharModalNotificacao()" class="inline-flex justify-center rounded-md bg-purple-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-purple-500">
                        Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let notificacaoAtual = null;

function abrirModalNotificacao(id) {
    console.log('Tentando abrir modal para notificação ID:', id);
    
    // Buscar dados da notificação
    const notificacao = <?= json_encode($notificacoes) ?>.find(n => n.id == id);
    
    console.log('Notificação encontrada:', notificacao);
    console.log('tipos_conteudo:', notificacao?.tipos_conteudo);
    console.log('conteudo:', notificacao?.conteudo);
    console.log('arquivo_url:', notificacao?.arquivo_url);
    console.log('video_url:', notificacao?.video_url);
    console.log('Todas as propriedades:', Object.keys(notificacao));
    
    if (!notificacao) {
        console.error('Notificação não encontrada:', id);
        return;
    }
    
    notificacaoAtual = notificacao;
    
    // Preencher header
    document.getElementById('modal-title').textContent = notificacao.titulo;
    document.getElementById('modal-sender').textContent = 'Por ' + notificacao.nome_enviador;
    document.getElementById('modal-date').textContent = 'Enviado em ' + new Date(notificacao.created_at).toLocaleString('pt-BR');
    
    // Preencher conteúdo
    const contentDiv = document.getElementById('modal-content');
    contentDiv.innerHTML = '';
    
    // Verificar tipos de conteúdo
    let tiposConteudo = [];
    try {
        if (notificacao.tipos_conteudo) {
            // Se for uma string simples, converter para array
            if (typeof notificacao.tipos_conteudo === 'string') {
                if (notificacao.tipos_conteudo.startsWith('[') || notificacao.tipos_conteudo.startsWith('{')) {
                    // É JSON válido
                    tiposConteudo = JSON.parse(notificacao.tipos_conteudo);
                } else {
                    // É string simples, converter para array
                    tiposConteudo = [notificacao.tipos_conteudo];
                }
            } else {
                tiposConteudo = notificacao.tipos_conteudo;
            }
        }
    } catch (e) {
        console.warn('Erro ao processar tipos_conteudo:', e);
        tiposConteudo = [];
    }
    
    // Texto - sempre exibir se houver conteúdo
    if (notificacao.conteudo && notificacao.conteudo.trim() !== '') {
        const textoDiv = document.createElement('div');
        textoDiv.className = 'prose max-w-none';
        textoDiv.innerHTML = notificacao.conteudo;
        contentDiv.appendChild(textoDiv);
        console.log('Texto adicionado ao modal:', notificacao.conteudo);
    } else {
        console.log('Nenhum conteúdo de texto encontrado');
    }
    
    // Imagem - verificar se há arquivo_url independente de tipos_conteudo
    if (notificacao.arquivo_url && notificacao.arquivo_url.trim() !== '') {
        let arquivoUrl = notificacao.arquivo_url;
        if (!/^https?:\/\//i.test(arquivoUrl)) {
            arquivoUrl = '<?= URL ?>' + arquivoUrl;
        }
        const imagemDiv = document.createElement('div');
        imagemDiv.className = 'mt-4';
        imagemDiv.innerHTML = `
            <img src="${arquivoUrl}" 
                 alt="Imagem da notificação" 
                 class="max-w-full h-auto rounded-lg shadow-lg">
        `;
        contentDiv.appendChild(imagemDiv);
        console.log('Imagem adicionada ao modal:', notificacao.arquivo_url);
    }
    
    // Vídeo - verificar se há video_url independente de tipos_conteudo
    if (notificacao.video_url && notificacao.video_url.trim() !== '') {
        const videoDiv = document.createElement('div');
        videoDiv.className = 'mt-4';
        videoDiv.innerHTML = `
            <div class="bg-gray-100 rounded-lg p-4">
                <h4 class="font-semibold text-gray-900 mb-2">Vídeo</h4>
                <a href="${notificacao.video_url}" 
                   target="_blank" 
                   class="inline-flex items-center px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg">
                    <svg class="w-5 h-5 mr-2" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h6a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6zM14.553 7.106A1 1 0 0014 8v4a1 1 0 00.553.894l2 1A1 1 0 0018 13V7a1 1 0 00-1.447-.894l-2 1z"></path>
                    </svg>
                    Assistir Vídeo
                </a>
            </div>
        `;
        contentDiv.appendChild(videoDiv);
        console.log('Vídeo adicionado ao modal:', notificacao.video_url);
    }
    
    // Se não há conteúdo, mostrar mensagem
    if (contentDiv.children.length === 0) {
        const semConteudoDiv = document.createElement('div');
        semConteudoDiv.className = 'text-center text-gray-500 py-8';
        semConteudoDiv.innerHTML = `
            <svg class="w-12 h-12 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <p>Esta notificação não possui conteúdo adicional.</p>
        `;
        contentDiv.appendChild(semConteudoDiv);
        console.log('Nenhum conteúdo encontrado, exibindo mensagem padrão');
    }
    
    // Mostrar modal com animação
    const modal = document.getElementById('notification-detail-modal');
    const backdrop = document.getElementById('modal-backdrop');
    
    if (!modal) {
        console.error('Modal não encontrado!');
        return;
    }
    
    modal.style.display = 'flex';
    
    // Animar backdrop
    setTimeout(() => {
        if (backdrop) {
            backdrop.style.opacity = '1';
        }
    }, 10);
    
    // Animar modal
    setTimeout(() => {
        const modalPanel = modal.querySelector('.relative.transform');
        if (modalPanel) {
            modalPanel.style.opacity = '1';
            modalPanel.style.transform = 'scale(1) translateY(0)';
        }
    }, 50);
}

function fecharModalNotificacao() {
    const modal = document.getElementById('notification-detail-modal');
    const backdrop = document.getElementById('modal-backdrop');
    
    if (modal && backdrop) {
        // Animar saída
        const modalPanel = modal.querySelector('.relative.transform');
        modalPanel.style.opacity = '0';
        modalPanel.style.transform = 'scale(0.95) translateY(4px)';
        
        backdrop.style.opacity = '0';
        
        setTimeout(() => {
            modal.style.display = 'none';
            // Reset para próxima abertura
            modalPanel.style.opacity = '0';
            modalPanel.style.transform = 'scale(0.95) translateY(4px)';
            backdrop.style.opacity = '0';
        }, 300);
    }
}


// Fechar modal com tecla ESC
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        const modal = document.getElementById('notification-detail-modal');
        if (modal.style.display === 'flex') {
            fecharModalNotificacao();
        }
    }
});
</script>
