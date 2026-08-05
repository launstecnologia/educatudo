<!-- Header Section -->
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div class="flex items-center">
            <a href="<?= URL ?>/professor/chat" class="mr-4 p-2 text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                </svg>
            </a>
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Chat com <?= htmlspecialchars($aluno['nome']) ?></h1>
                <p class="text-sm text-gray-600">
                    <?php if (!empty($aluno['turma_nome'])): ?>
                        Turma: <?= htmlspecialchars($aluno['turma_nome']) ?>
                    <?php endif; ?>
                    <?php if (!empty($aluno['ra'])): ?>
                        • RA: <?= htmlspecialchars($aluno['ra']) ?>
                    <?php endif; ?>
                </p>
            </div>
        </div>
    </div>
</div>

<!-- Chat Container -->
<div class="bg-white rounded-xl shadow-lg overflow-hidden flex flex-col" style="height: calc(100vh - 250px); min-height: 600px;">
    <!-- Área de Mensagens -->
    <div id="chat-messages" class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-4">
        <?php if (empty($mensagens)): ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <p class="text-gray-600">Nenhuma mensagem ainda. Inicie a conversa!</p>
            </div>
        <?php else: ?>
            <?php foreach ($mensagens as $msg): ?>
                <div class="flex <?= $msg['remetente_tipo'] === 'professor' ? 'justify-end' : 'justify-start' ?>" data-mensagem-id="<?= $msg['id'] ?>">
                    <div class="max-w-md <?= $msg['remetente_tipo'] === 'professor' ? 'bg-purple-500 text-white' : 'bg-white border border-gray-200' ?> rounded-lg p-4 shadow-sm">
                        <div class="flex items-center mb-2">
                            <span class="text-xs font-semibold <?= $msg['remetente_tipo'] === 'professor' ? 'text-purple-100' : 'text-gray-600' ?>">
                                <?= $msg['remetente_tipo'] === 'professor' ? 'Você' : htmlspecialchars($msg['aluno_nome']) ?>
                            </span>
                            <span class="text-xs <?= $msg['remetente_tipo'] === 'professor' ? 'text-purple-100' : 'text-gray-500' ?> ml-2">
                                <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                            </span>
                        </div>
                        <p class="<?= $msg['remetente_tipo'] === 'professor' ? 'text-white' : 'text-gray-800' ?>" style="white-space: pre-wrap; word-wrap: break-word;">
                            <?= htmlspecialchars($msg['mensagem']) ?>
                        </p>
                        
                        <!-- Anexos -->
                        <?php if (!empty($msg['anexos'])): ?>
                            <div class="mt-3 space-y-2">
                                <?php foreach ($msg['anexos'] as $anexo): ?>
                                    <?php 
                                    $isImage = in_array($anexo['tipo_arquivo'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
                                    ?>
                                    <div class="border <?= $msg['remetente_tipo'] === 'professor' ? 'border-purple-300' : 'border-gray-300' ?> rounded p-2 bg-white bg-opacity-20">
                                        <?php if ($isImage): ?>
                                            <img src="<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>" 
                                                 alt="<?= htmlspecialchars($anexo['nome_arquivo']) ?>"
                                                 class="max-w-full h-auto rounded cursor-pointer"
                                                 onclick="window.open('<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>', '_blank')">
                                        <?php else: ?>
                                            <a href="<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>" 
                                               target="_blank"
                                               class="flex items-center text-sm <?= $msg['remetente_tipo'] === 'professor' ? 'text-purple-100' : 'text-blue-600' ?> hover:underline">
                                                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                                </svg>
                                                <?= htmlspecialchars($anexo['nome_arquivo']) ?>
                                            </a>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Formulário de Envio -->
    <div class="border-t border-gray-200 p-4 bg-white">
        <form id="formMensagem" class="space-y-3">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="aluno_id" value="<?= $aluno['id'] ?>">
            
            <div class="flex items-center space-x-2">
                <textarea 
                    name="mensagem" 
                    id="mensagem" 
                    rows="2" 
                    placeholder="Digite sua mensagem..."
                    class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-purple-500 resize-none"
                    required></textarea>
                
                <button 
                    type="submit" 
                    class="px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors font-medium flex items-center">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                    </svg>
                    Enviar
                </button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    const formMensagem = document.getElementById('formMensagem');
    const mensagemInput = document.getElementById('mensagem');
    let ultimaMensagemId = <?= !empty($mensagens) ? max(array_column($mensagens, 'id')) : 0 ?>;
    
    // Scroll para o final das mensagens
    function scrollToBottom() {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    scrollToBottom();
    
    // Enviar mensagem
    formMensagem.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const mensagem = mensagemInput.value.trim();
        if (!mensagem) return;
        
        const formData = new FormData(this);
        const submitBtn = this.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = 'Enviando...';
        
        try {
            const response = await fetch('<?= URL ?>/professor/chat/enviar-mensagem', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                mensagemInput.value = '';
                // Adicionar mensagem à interface
                adicionarMensagem(result.mensagem);
                ultimaMensagemId = result.mensagem.id;
                scrollToBottom();
            } else {
                alert('Erro: ' + (result.error || 'Erro ao enviar mensagem'));
            }
        } catch (error) {
            console.error('Erro:', error);
            alert('Erro ao enviar mensagem');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    });
    
    // Adicionar mensagem à interface
    function adicionarMensagem(msg) {
        const mensagemDiv = document.createElement('div');
        mensagemDiv.className = 'flex justify-end';
        mensagemDiv.setAttribute('data-mensagem-id', msg.id);
        
        const dataFormatada = new Date(msg.created_at).toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        mensagemDiv.innerHTML = `
            <div class="max-w-md bg-purple-500 text-white rounded-lg p-4 shadow-sm">
                <div class="flex items-center mb-2">
                    <span class="text-xs font-semibold text-purple-100">Você</span>
                    <span class="text-xs text-purple-100 ml-2">${dataFormatada}</span>
                </div>
                <p class="text-white" style="white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(msg.mensagem)}</p>
            </div>
        `;
        
        chatMessages.appendChild(mensagemDiv);
    }
    
    // Buscar novas mensagens periodicamente
    setInterval(async function() {
        try {
            const response = await fetch(`<?= URL ?>/professor/chat/buscar-mensagens?aluno_id=<?= $aluno['id'] ?>&ultima_mensagem_id=${ultimaMensagemId}`);
            const result = await response.json();
            
            if (result.success && result.mensagens && result.mensagens.length > 0) {
                result.mensagens.forEach(function(msg) {
                    const mensagemDiv = document.createElement('div');
                    mensagemDiv.className = 'flex justify-start';
                    mensagemDiv.setAttribute('data-mensagem-id', msg.id);
                    
                    const dataFormatada = new Date(msg.created_at).toLocaleString('pt-BR', {
                        day: '2-digit',
                        month: '2-digit',
                        year: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    mensagemDiv.innerHTML = `
                        <div class="max-w-md bg-white border border-gray-200 rounded-lg p-4 shadow-sm">
                            <div class="flex items-center mb-2">
                                <span class="text-xs font-semibold text-gray-600">${escapeHtml(msg.aluno_nome)}</span>
                                <span class="text-xs text-gray-500 ml-2">${dataFormatada}</span>
                            </div>
                            <p class="text-gray-800" style="white-space: pre-wrap; word-wrap: break-word;">${escapeHtml(msg.mensagem)}</p>
                        </div>
                    `;
                    
                    chatMessages.appendChild(mensagemDiv);
                    ultimaMensagemId = Math.max(ultimaMensagemId, msg.id);
                });
                
                scrollToBottom();
            }
        } catch (error) {
            console.error('Erro ao buscar mensagens:', error);
        }
    }, 3000); // Buscar a cada 3 segundos
    
    // Função para escapar HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
});
</script>

