<!-- Header Section -->
<div class="mb-6">
    <div class="flex items-center bg-white rounded-2xl shadow-sm border border-gray-100 p-4">
        <a href="<?= URL ?>/chat-professor" class="mr-3 inline-flex items-center justify-center w-10 h-10 rounded-full text-gray-500 hover:bg-gray-100 hover:text-gray-700 transition-colors shrink-0">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div class="w-12 h-12 rounded-full bg-indigo-100 text-indigo-700 flex items-center justify-center font-bold text-lg shrink-0 mr-3">
            <?= htmlspecialchars(mb_strtoupper(mb_substr($professor['nome'] ?? '?', 0, 1))) ?>
        </div>
        <div class="min-w-0">
            <h1 class="text-lg font-bold text-gray-900 truncate">Chat com <?= htmlspecialchars($professor['nome']) ?></h1>
            <p class="text-xs text-gray-500">Código: <?= htmlspecialchars($professor['codigo_prof'] ?? 'N/A') ?></p>
        </div>
    </div>
</div>

<!-- Chat Container -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden flex flex-col" style="height: calc(100vh - 320px); min-height: 360px; max-height: 700px;">
    <!-- Área de Mensagens -->
    <div id="chat-messages" class="flex-1 overflow-y-auto p-6 bg-gray-50 space-y-3">
        <?php if (empty($mensagens)): ?>
            <div class="h-full flex flex-col items-center justify-center text-center py-12">
                <div class="w-16 h-16 rounded-full bg-indigo-50 flex items-center justify-center mb-4">
                    <svg class="w-8 h-8 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                </div>
                <p class="text-gray-600 font-medium">Nenhuma mensagem ainda</p>
                <p class="text-sm text-gray-400 mt-1">Escreva algo abaixo pra iniciar a conversa</p>
            </div>
        <?php else: ?>
            <?php foreach ($mensagens as $msg): ?>
                <?php $isAlunoMsg = $msg['remetente_tipo'] === 'aluno'; ?>
                <div class="flex <?= $isAlunoMsg ? 'justify-end' : 'justify-start' ?>" data-mensagem-id="<?= $msg['id'] ?>">
                    <div class="max-w-md <?= $isAlunoMsg ? 'bg-indigo-600 text-white rounded-2xl rounded-br-sm' : 'bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-bl-sm' ?> px-4 py-3 shadow-sm">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-semibold <?= $isAlunoMsg ? 'text-indigo-100' : 'text-gray-600' ?>">
                                <?= $isAlunoMsg ? 'Você' : htmlspecialchars($msg['professor_nome']) ?>
                            </span>
                            <span class="text-xs <?= $isAlunoMsg ? 'text-indigo-200' : 'text-gray-400' ?>">
                                <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                            </span>
                        </div>
                        <p style="white-space: pre-wrap; word-wrap: break-word;">
                            <?= htmlspecialchars($msg['mensagem']) ?>
                        </p>

                        <!-- Anexos -->
                        <?php if (!empty($msg['anexos'])): ?>
                            <div class="mt-3 space-y-2">
                                <?php foreach ($msg['anexos'] as $anexo): ?>
                                    <?php
                                    $isImage = in_array($anexo['tipo_arquivo'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
                                    ?>
                                    <div class="border <?= $isAlunoMsg ? 'border-indigo-300' : 'border-gray-200' ?> rounded-lg p-2 bg-white bg-opacity-20">
                                        <?php if ($isImage): ?>
                                            <img src="<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>"
                                                 alt="<?= htmlspecialchars($anexo['nome_arquivo']) ?>"
                                                 class="max-w-full h-auto rounded cursor-pointer"
                                                 onclick="window.open('<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>', '_blank')">
                                        <?php else: ?>
                                            <a href="<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>"
                                               target="_blank"
                                               class="flex items-center text-sm <?= $isAlunoMsg ? 'text-indigo-100' : 'text-indigo-600' ?> hover:underline">
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
    <div class="border-t border-gray-100 p-4 bg-white">
        <form id="formMensagem" class="flex items-end gap-2">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="professor_id" value="<?= $professor['id'] ?>">

            <textarea
                name="mensagem"
                id="mensagem"
                rows="1"
                placeholder="Digite sua mensagem..."
                class="flex-1 px-4 py-3 border border-gray-200 bg-gray-50 rounded-2xl focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 focus:bg-white resize-none transition-colors"
                required></textarea>

            <button
                type="submit"
                class="inline-flex items-center justify-center w-12 h-12 rounded-full bg-indigo-600 hover:bg-indigo-700 text-white transition-colors shrink-0"
                aria-label="Enviar mensagem">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                </svg>
            </button>
        </form>
    </div>
</div>

<script>
let ultimaMensagemId = <?= !empty($mensagens) && count($mensagens) > 0 ? max(array_column($mensagens, 'id')) : 0 ?>;

document.addEventListener('DOMContentLoaded', function() {
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
    
    // Enviar mensagem
    const formMensagem = document.getElementById('formMensagem');
    if (formMensagem) {
        formMensagem.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const mensagemInput = document.getElementById('mensagem');
            const mensagem = mensagemInput.value.trim();
            
            if (!mensagem) {
                alert('Digite uma mensagem');
                return;
            }
            
            const formData = new FormData(this);
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="animate-spin w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Enviando...';
            
            fetch('<?= URL ?>/chat-professor/enviar-mensagem', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    mensagemInput.value = '';
                    
                    // Remove mensagem de "nenhuma mensagem"
                    const emptyMessage = chatMessages.querySelector('.text-center');
                    if (emptyMessage && emptyMessage.textContent.includes('Nenhuma mensagem')) {
                        emptyMessage.remove();
                    }
                    
                    // Adiciona a mensagem ao chat
                    if (data.mensagem) {
                        adicionarMensagemAoChat(data.mensagem, chatMessages);
                        if (data.mensagem.id > ultimaMensagemId) {
                            ultimaMensagemId = data.mensagem.id;
                        }
                        chatMessages.scrollTop = chatMessages.scrollHeight;
                    }
                    
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                } else {
                    alert('Erro ao enviar mensagem: ' + (data.error || 'Erro desconhecido'));
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalText;
                }
            })
            .catch(error => {
                console.error('Erro:', error);
                alert('Erro ao enviar mensagem');
                submitBtn.disabled = false;
                submitBtn.innerHTML = originalText;
            });
        });
    }
    
    // Atualizar mensagens a cada 5 segundos
    setInterval(() => {
        buscarNovasMensagens();
    }, 5000);
    
    function buscarNovasMensagens() {
        fetch(`<?= URL ?>/chat-professor/buscar-mensagens?professor_id=<?= $professor['id'] ?>&ultima_mensagem_id=${ultimaMensagemId}`)
            .then(response => response.json())
            .then(data => {
                if (data.success && data.mensagens && data.mensagens.length > 0) {
                    // Remove mensagem de "nenhuma mensagem"
                    const emptyMessage = chatMessages.querySelector('.text-center');
                    if (emptyMessage && emptyMessage.textContent.includes('Nenhuma mensagem')) {
                        emptyMessage.remove();
                    }
                    
                    data.mensagens.forEach(msg => {
                        // Verifica se a mensagem já não existe
                        const existingMsg = chatMessages.querySelector(`[data-mensagem-id="${msg.id}"]`);
                        if (!existingMsg) {
                            adicionarMensagemAoChat(msg, chatMessages);
                            if (msg.id > ultimaMensagemId) {
                                ultimaMensagemId = msg.id;
                            }
                        }
                    });
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }
            })
            .catch(error => console.error('Erro ao buscar mensagens:', error));
    }
    
    function adicionarMensagemAoChat(msg, container) {
        const isAluno = msg.remetente_tipo === 'aluno';
        const div = document.createElement('div');
        div.className = `flex ${isAluno ? 'justify-end' : 'justify-start'}`;
        div.setAttribute('data-mensagem-id', msg.id);
        
        let anexosHtml = '';
        if (msg.anexos && msg.anexos.length > 0) {
            anexosHtml = '<div class="mt-3 space-y-2">';
            msg.anexos.forEach(anexo => {
                const isImage = anexo.tipo_arquivo && anexo.tipo_arquivo.startsWith('image/');
                if (isImage) {
                    anexosHtml += `<div class="border ${isAluno ? 'border-indigo-300' : 'border-gray-200'} rounded-lg p-2 bg-white bg-opacity-20">
                        <img src="<?= URL ?>${anexo.caminho_arquivo}" alt="${anexo.nome_arquivo}" class="max-w-full h-auto rounded cursor-pointer" onclick="window.open('<?= URL ?>${anexo.caminho_arquivo}', '_blank')">
                    </div>`;
                } else {
                    anexosHtml += `<div class="border ${isAluno ? 'border-indigo-300' : 'border-gray-200'} rounded-lg p-2 bg-white bg-opacity-20">
                        <a href="<?= URL ?>${anexo.caminho_arquivo}" target="_blank" class="flex items-center text-sm ${isAluno ? 'text-indigo-100' : 'text-indigo-600'} hover:underline">
                            <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                            </svg>
                            ${anexo.nome_arquivo}
                        </a>
                    </div>`;
                }
            });
            anexosHtml += '</div>';
        }
        
        div.innerHTML = `
            <div class="max-w-md ${isAluno ? 'bg-indigo-600 text-white rounded-2xl rounded-br-sm' : 'bg-white border border-gray-200 text-gray-800 rounded-2xl rounded-bl-sm'} px-4 py-3 shadow-sm">
                <div class="flex items-center gap-2 mb-1">
                    <span class="text-xs font-semibold ${isAluno ? 'text-indigo-100' : 'text-gray-600'}">
                        ${isAluno ? 'Você' : msg.professor_nome || 'Professor'}
                    </span>
                    <span class="text-xs ${isAluno ? 'text-indigo-200' : 'text-gray-400'}">
                        ${new Date(msg.created_at).toLocaleString('pt-BR')}
                    </span>
                </div>
                <p style="white-space: pre-wrap; word-wrap: break-word;">${msg.mensagem}</p>
                ${anexosHtml}
            </div>
        `;
        container.appendChild(div);
    }
});
</script>

