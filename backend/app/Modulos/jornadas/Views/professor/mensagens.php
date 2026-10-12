<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                💬 Mensagens - <?= htmlspecialchars($jornada['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($jornada['materia_nome']) ?> • <?= htmlspecialchars($jornada['turma_nome']) ?>
            </p>
        </div>
        <div class="flex space-x-3">
            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Lista de Alunos -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-lg p-4">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Alunos</h3>
            <div class="space-y-2 max-h-[600px] overflow-y-auto">
                <?php if (empty($alunos_com_mensagens)): ?>
                    <p class="text-gray-500 text-sm text-center py-4">Nenhum aluno com mensagens</p>
                <?php else: ?>
                    <?php foreach ($alunos_com_mensagens as $aluno): ?>
                        <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/mensagens?aluno_id=<?= $aluno['id'] ?>" 
                           class="block p-3 rounded-lg border-2 transition-all <?= ($aluno_selecionado && $aluno_selecionado['id'] == $aluno['id']) ? 'border-blue-500 bg-blue-50' : 'border-gray-200 hover:border-blue-300 hover:bg-gray-50' ?>">
                            <div class="flex items-center justify-between">
                                <div class="flex-1">
                                    <p class="font-medium text-gray-900"><?= htmlspecialchars($aluno['nome']) ?></p>
                                    <?php if ($aluno['ra']): ?>
                                        <p class="text-xs text-gray-500">RA: <?= htmlspecialchars($aluno['ra']) ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php if ($aluno['mensagens_nao_lidas'] > 0): ?>
                                    <span class="ml-2 px-2 py-1 bg-red-500 text-white text-xs font-bold rounded-full">
                                        <?= $aluno['mensagens_nao_lidas'] ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Chat -->
    <div class="lg:col-span-2">
        <?php if (!$aluno_selecionado): ?>
            <div class="bg-white rounded-xl shadow-lg p-12 text-center">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <h3 class="text-lg font-medium text-gray-900 mb-2">Selecione um aluno</h3>
                <p class="text-gray-600">Escolha um aluno da lista para ver e responder suas mensagens.</p>
            </div>
        <?php else: ?>
            <div class="bg-white rounded-xl shadow-lg overflow-hidden">
                <!-- Header do Chat -->
                <div class="bg-blue-600 text-white px-6 py-4">
                    <h3 class="text-lg font-semibold"><?= htmlspecialchars($aluno_selecionado['nome']) ?></h3>
                    <?php if ($aluno_selecionado['ra']): ?>
                        <p class="text-sm text-blue-100">RA: <?= htmlspecialchars($aluno_selecionado['ra']) ?></p>
                    <?php endif; ?>
                </div>
                
                <!-- Área de Mensagens -->
                <div id="chat-messages" class="h-96 overflow-y-auto p-6 bg-gray-50 space-y-4">
                    <?php if (empty($mensagens)): ?>
                        <div class="text-center py-8">
                            <p class="text-gray-600">Nenhuma mensagem ainda.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($mensagens as $msg): ?>
                            <div class="flex <?= $msg['remetente_tipo'] === 'professor' ? 'justify-end' : 'justify-start' ?>" data-mensagem-id="<?= $msg['id'] ?>">
                                <div class="max-w-md <?= $msg['remetente_tipo'] === 'professor' ? 'bg-green-500 text-white' : 'bg-white border border-gray-200' ?> rounded-lg p-4 shadow-sm" style="text-align: left !important; display: block !important;">
                                    <div class="flex items-center mb-2" style="justify-content: flex-start !important;">
                                        <span class="text-xs font-semibold <?= $msg['remetente_tipo'] === 'professor' ? 'text-green-100' : 'text-gray-600' ?>">
                                            <?= $msg['remetente_tipo'] === 'professor' ? 'Você' : htmlspecialchars($msg['aluno_nome']) ?>
                                        </span>
                                        <span class="text-xs <?= $msg['remetente_tipo'] === 'professor' ? 'text-green-100' : 'text-gray-500' ?> ml-2">
                                            <?= date('d/m/Y H:i', strtotime($msg['created_at'])) ?>
                                        </span>
                                    </div>
                                    <p class="<?= $msg['remetente_tipo'] === 'professor' ? 'text-white' : 'text-gray-800' ?>" style="text-align: left !important; white-space: pre-wrap; word-wrap: break-word; margin: 0 !important; padding: 0 !important;">
                                        <?php 
                                        $mensagemLimpa = preg_replace('/\s+/u', ' ', $msg['mensagem']);
                                        $mensagemLimpa = trim($mensagemLimpa);
                                        echo htmlspecialchars($mensagemLimpa);
                                        ?>
                                    </p>
                                    
                                    <!-- Anexos -->
                                    <?php if (!empty($msg['anexos'])): ?>
                                        <div class="mt-3 space-y-2">
                                            <?php foreach ($msg['anexos'] as $anexo): ?>
                                                <?php 
                                                $isImage = in_array($anexo['tipo_arquivo'], ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp']);
                                                ?>
                                                <div class="border <?= $msg['remetente_tipo'] === 'professor' ? 'border-green-300' : 'border-gray-300' ?> rounded p-2 bg-white bg-opacity-20">
                                                    <?php if ($isImage): ?>
                                                        <img src="<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>" 
                                                             alt="<?= htmlspecialchars($anexo['nome_arquivo']) ?>"
                                                             class="max-w-full h-auto rounded cursor-pointer"
                                                             onclick="window.open('<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>', '_blank')">
                                                    <?php else: ?>
                                                        <a href="<?= URL . htmlspecialchars($anexo['caminho_arquivo']) ?>" 
                                                           target="_blank"
                                                           class="flex items-center text-sm <?= $msg['remetente_tipo'] === 'professor' ? 'text-green-100' : 'text-blue-600' ?> hover:underline">
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
                
                <!-- Formulário de Resposta -->
                <div class="border-t border-gray-200 p-4 bg-white">
                    <form id="formResposta" enctype="multipart/form-data" class="space-y-3">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="jornada_id" value="<?= $jornada['id'] ?>">
                        <input type="hidden" name="aluno_id" value="<?= $aluno_selecionado['id'] ?>">
                        
                        <!-- Preview de Anexos -->
                        <div id="anexos-preview" class="hidden space-y-2"></div>
                        
                        <!-- Input de Anexos -->
                        <div class="flex items-center space-x-2">
                            <label class="cursor-pointer p-2 text-gray-600 hover:text-green-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"></path>
                                </svg>
                                <input type="file" 
                                       name="anexos[]" 
                                       id="anexos" 
                                       multiple 
                                       accept="image/*,application/pdf,.doc,.docx"
                                       class="hidden"
                                       onchange="previewAnexos(this)">
                            </label>
                            
                            <textarea 
                                name="mensagem" 
                                id="mensagem" 
                                rows="2" 
                                placeholder="Digite sua resposta..."
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 resize-none"
                                required></textarea>
                            
                            <button 
                                type="submit" 
                                class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors font-medium flex items-center">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                                </svg>
                                Enviar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const formResposta = document.getElementById('formResposta');
    if (formResposta) {
        formResposta.addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const mensagemInput = document.getElementById('mensagem');
            const anexosInput = document.getElementById('anexos');
            
            // Limpa espaços extras antes de enviar
            if (mensagemInput.value) {
                mensagemInput.value = mensagemInput.value.replace(/\s+/g, ' ').trim();
            }
            
            if (!mensagemInput.value.trim() && anexosInput.files.length === 0) {
                alert('Digite uma mensagem ou anexe um arquivo');
                return;
            }
            
            const submitBtn = this.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<svg class="animate-spin w-5 h-5 inline mr-2" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>Enviando...';
            
            fetch('<?= URL ?>/professor/jornadas/responder-mensagem', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Limpa o formulário
                    mensagemInput.value = '';
                    anexosInput.value = '';
                    document.getElementById('anexos-preview').classList.add('hidden');
                    document.getElementById('anexos-preview').innerHTML = '';
                    
                    // Adiciona a mensagem imediatamente ao chat
                    if (data.mensagem) {
                        const chatMessages = document.getElementById('chat-messages');
                        
                        // Remove mensagem de "nenhuma mensagem"
                        const emptyMessage = chatMessages.querySelector('.text-center');
                        if (emptyMessage && emptyMessage.textContent.includes('Nenhuma mensagem')) {
                            emptyMessage.remove();
                        }
                        
                        // Verifica se a mensagem já não existe
                        const existingMsg = chatMessages.querySelector(`[data-mensagem-id="${data.mensagem.id}"]`);
                        if (!existingMsg) {
                            adicionarMensagemAoChat(data.mensagem, chatMessages);
                            chatMessages.scrollTop = chatMessages.scrollHeight;
                        }
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
    
    // Auto-scroll do chat
    const chatMessages = document.getElementById('chat-messages');
    if (chatMessages) {
        chatMessages.scrollTop = chatMessages.scrollHeight;
    }
});

function adicionarMensagemAoChat(msg, container) {
    const isProfessor = msg.remetente_tipo === 'professor';
    const div = document.createElement('div');
    div.className = `flex ${isProfessor ? 'justify-end' : 'justify-start'}`;
    div.setAttribute('data-mensagem-id', msg.id);
    
    let anexosHtml = '';
    if (msg.anexos && msg.anexos.length > 0) {
        anexosHtml = '<div class="mt-3 space-y-2">';
        msg.anexos.forEach(anexo => {
            const isImage = anexo.tipo_arquivo && anexo.tipo_arquivo.startsWith('image/');
            if (isImage) {
                anexosHtml += `<div class="border ${isProfessor ? 'border-green-300' : 'border-gray-300'} rounded p-2 bg-white bg-opacity-20">
                    <img src="<?= URL ?>${anexo.caminho_arquivo}" alt="${anexo.nome_arquivo}" class="max-w-full h-auto rounded cursor-pointer" onclick="window.open('<?= URL ?>${anexo.caminho_arquivo}', '_blank')">
                </div>`;
            } else {
                anexosHtml += `<div class="border ${isProfessor ? 'border-green-300' : 'border-gray-300'} rounded p-2 bg-white bg-opacity-20">
                    <a href="<?= URL ?>${anexo.caminho_arquivo}" target="_blank" class="flex items-center text-sm ${isProfessor ? 'text-green-100' : 'text-blue-600'} hover:underline">
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
    
    const dataFormatada = new Date(msg.created_at).toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
    
    div.innerHTML = `
        <div class="max-w-md ${isProfessor ? 'bg-green-500 text-white' : 'bg-white border border-gray-200'} rounded-lg p-4 shadow-sm" style="text-align: left !important;">
            <div class="flex items-center mb-2">
                <span class="text-xs font-semibold ${isProfessor ? 'text-green-100' : 'text-gray-600'}">
                    ${isProfessor ? 'Você' : msg.aluno_nome || 'Aluno'}
                </span>
                <span class="text-xs ${isProfessor ? 'text-green-100' : 'text-gray-500'} ml-2">
                    ${dataFormatada}
                </span>
            </div>
            <p class="${isProfessor ? 'text-white' : 'text-gray-800'}" style="text-align: left !important; white-space: pre-wrap; word-wrap: break-word;">${msg.mensagem.replace(/\s+/g, ' ').trim()}</p>
            ${anexosHtml}
        </div>
    `;
    container.appendChild(div);
}

function previewAnexos(input) {
    const preview = document.getElementById('anexos-preview');
    preview.innerHTML = '';
    
    if (input.files && input.files.length > 0) {
        preview.classList.remove('hidden');
        
        Array.from(input.files).forEach((file, index) => {
            const div = document.createElement('div');
            div.className = 'flex items-center justify-between p-2 bg-gray-100 rounded border border-gray-300';
            
            const isImage = file.type.startsWith('image/');
            let previewContent = '';
            
            if (isImage) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewContent = `<img src="${e.target.result}" class="h-16 w-auto rounded mr-2">`;
                };
                reader.readAsDataURL(file);
            }
            
            div.innerHTML = `
                ${isImage ? `<img src="" class="h-16 w-auto rounded mr-2" id="preview-${index}">` : ''}
                <span class="flex-1 text-sm text-gray-700">${file.name}</span>
                <button type="button" onclick="removerAnexo(${index})" class="ml-2 text-red-600 hover:text-red-800">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            `;
            
            if (isImage) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const img = div.querySelector(`#preview-${index}`);
                    if (img) img.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
            
            preview.appendChild(div);
        });
    } else {
        preview.classList.add('hidden');
    }
}

function removerAnexo(index) {
    const input = document.getElementById('anexos');
    const dt = new DataTransfer();
    Array.from(input.files).forEach((file, i) => {
        if (i !== index) {
            dt.items.add(file);
        }
    });
    input.files = dt.files;
    previewAnexos(input);
}
</script>

