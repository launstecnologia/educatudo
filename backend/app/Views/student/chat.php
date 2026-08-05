<?php
$iaName = LayoutHelper::getIaName();
$iaAvatarUrl = LayoutHelper::getIaAvatarUrl();
$iaAvatarTag = $iaAvatarUrl !== ''
    ? '<img src="' . htmlspecialchars($iaAvatarUrl) . '" alt="' . htmlspecialchars($iaName) . '" class="inline-block w-8 h-8 rounded-full object-cover align-middle -mt-1">'
    : '🤖';
?>
<!-- Container da página do chat: ocupa altura disponível; caixa de escrever sempre visível no fim -->
<div class="flex flex-col flex-1 min-h-0 h-full">
<!-- Header de página removido — a marca (avatar + nome) já aparece no topo
     da sidebar de conversas, esse bloco só ocupava espaço vertical à toa. -->

<!-- Main Chat Interface: flex-1 min-h-0 para caber em qualquer altura de tela -->
<div id="chatPageWrapper" class="flex flex-col flex-1 min-h-0">
<div class="grid grid-cols-1 lg:grid-cols-4 gap-6 flex-1 min-h-0 transition-all duration-200" id="chatGrid">
    <!-- Conversations List (oculta ao selecionar conversa em telas lg; botão Voltar reabre) -->
    <div id="chatConversationsColumn" class="lg:col-span-1 flex flex-col min-h-0 transition-all duration-200">
        <div class="bg-white rounded-xl shadow-lg flex flex-col flex-1 min-h-0 overflow-hidden">
            <div class="p-4 border-b border-gray-200 flex-shrink-0 space-y-3">
                <div class="flex items-center gap-2">
                    <?php if ($iaAvatarUrl !== ''): ?>
                        <img src="<?= htmlspecialchars($iaAvatarUrl) ?>" alt="<?= htmlspecialchars($iaName) ?>" class="w-9 h-9 rounded-full object-cover flex-shrink-0">
                    <?php else: ?>
                        <span class="text-2xl flex-shrink-0">🤖</span>
                    <?php endif; ?>
                    <span class="text-lg font-bold bg-gradient-to-r from-purple-600 to-teal-500 bg-clip-text text-transparent"><?= htmlspecialchars($iaName) ?></span>
                </div>
                <button onclick="openCreateConversationModal()" class="w-full flex items-center justify-center gap-2 px-4 py-2.5 bg-gradient-to-r from-purple-600 to-teal-500 text-white text-sm font-medium rounded-lg hover:opacity-90 transition-opacity">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                    </svg>
                    Nova Conversa
                </button>
                <div class="relative">
                    <svg class="w-4 h-4 text-gray-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                    <input type="text" id="chatConversationSearch" placeholder="Buscar conversas..." oninput="filtrarConversasChat(this.value)"
                           class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>
                <div class="flex items-center gap-1.5 text-xs font-semibold text-gray-500 uppercase tracking-wide pt-1">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    Histórico
                </div>
            </div>
            <div class="flex-1 min-h-0 overflow-y-auto p-4 pt-2 chat-conversations-list" id="chatConversationsListWrap">
                <?php if (empty($conversas)): ?>
                    <div class="text-center py-8">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p class="text-gray-500">Nenhuma conversa ainda</p>
                        <p class="text-sm text-gray-400">Crie sua primeira conversa para começar!</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-1" id="conversationsList">
                        <?php foreach ($conversas as $conversa): ?>
                            <div class="conversation-item p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors" 
                                 data-conversa-id="<?= $conversa['id'] ?>"
                                 onclick="loadConversation(<?= $conversa['id'] ?>)">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h3 class="font-medium text-gray-900 text-sm"><?= htmlspecialchars($conversa['titulo']) ?></h3>
                                        <?php if ($conversa['materia']): ?>
                                            <p class="text-xs text-gray-500"><?= htmlspecialchars($conversa['materia']) ?></p>
                                        <?php endif; ?>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <span class="text-sm font-medium text-orange-500"><?= $conversa['interacoes'] ?? 0 ?></span>
                                        <button onclick="event.stopPropagation(); deleteConversation(<?= $conversa['id'] ?>)" 
                                                class="text-red-400 hover:text-red-600 text-xs p-1 rounded hover:bg-red-50 transition-colors">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Chat Area (ocupa todo o espaço quando lista está oculta) -->
    <div id="chatAreaColumn" class="lg:col-span-3 flex flex-col min-h-0">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 chat-container flex flex-col flex-1 min-h-0 h-full relative">
            <!-- Voltar (mobile) e Modo foco viraram ícones flutuantes sobre a área de
                 mensagens — sem barra de cabeçalho dedicada, pra colar tudo no topo. -->
            <button type="button" id="chatBackToList" onclick="chatShowConversationsList()" class="hidden flex items-center gap-1 px-2 py-1.5 rounded-lg text-gray-600 bg-white/90 backdrop-blur-sm shadow-sm hover:bg-gray-100 transition-colors absolute top-2 left-2 z-10" title="Voltar à lista de conversas">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                <span class="text-sm font-medium">Voltar</span>
            </button>
            <span id="conversationTitle" class="hidden"></span>
            <span id="conversationSubject" class="hidden"></span>
            <div class="absolute top-2 right-2 z-10 flex items-center gap-1.5">
                <button type="button" id="voiceModeBtn" onclick="abrirModoVoz()" class="p-1.5 rounded-lg text-purple-500 bg-white/90 backdrop-blur-sm hover:bg-purple-50 transition-colors" title="Conversar por voz com a Tudinha">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                </button>
                <button type="button" id="chatFocusModeBtn" onclick="chatToggleFocusMode()" class="p-1.5 rounded-lg text-gray-400 bg-white/90 backdrop-blur-sm hover:bg-gray-100 hover:text-gray-700 transition-colors" title="Modo foco (expandir chat)">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path></svg>
                </button>
            </div>

            <!-- Overlay do Modo de Voz (WebRTC direto navegador↔OpenAI) -->
            <div id="voiceModeOverlay" class="hidden absolute inset-0 z-30 bg-white flex flex-col items-center justify-center gap-6 px-6">
                <div id="voiceModeAvatar" class="w-24 h-24 rounded-full bg-purple-100 flex items-center justify-center text-5xl transition-transform">
                    🤖
                </div>
                <p id="voiceModeStatus" class="text-gray-600 text-sm font-medium">Conectando...</p>
                <p id="voiceModeCaption" class="text-gray-900 text-center text-lg max-w-md min-h-[3.5rem]"></p>
                <button type="button" onclick="encerrarModoVoz()" class="px-6 py-3 bg-red-500 text-white rounded-full font-medium hover:bg-red-600 transition-colors flex items-center gap-2">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                    Encerrar
                </button>
                <audio id="voiceModeAudio" autoplay class="hidden"></audio>
            </div>

            <!-- Messages Area: flex-1 min-h-0 overflow-y-auto -->
            <div class="flex-1 py-3 px-2 sm:px-4 overflow-y-auto bg-white min-h-0" id="messagesArea">
                <div class="flex items-center justify-center h-full text-gray-500">
                    <div class="text-center">
                        <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                        <p class="text-lg font-medium">Bem-vindo ao Chat <?= htmlspecialchars($iaName) ?>!</p>
                        <p class="text-sm">Selecione uma conversa ou crie uma nova para começar</p>
                    </div>
                </div>
            </div>

            <!-- Message Input: flex-shrink-0 para nunca sair da tela -->
            <div class="py-4 px-2 sm:px-4 border-t border-gray-200 bg-white hidden flex-shrink-0" id="messageInput">
                <!-- Anexo pendente: preview da imagem colada/escolhida — some só ao enviar
                     ou remover, permitindo escrever um texto pra complementar antes de enviar -->
                <div id="pendingAttachmentPreview" class="hidden relative inline-block mb-2 ml-1">
                    <img id="pendingAttachmentImg" class="h-20 w-20 object-cover rounded-lg border border-gray-300">
                    <button type="button" onclick="removePendingAttachment()"
                            class="absolute -top-2 -right-2 bg-gray-800 text-white rounded-full w-5 h-5 flex items-center justify-center text-xs hover:bg-gray-900"
                            title="Remover imagem">×</button>
                </div>
                <form id="messageForm" class="flex items-end gap-3">
                    <input type="hidden" id="currentConversationId" name="conversa_id">
                    <input type="hidden" name="_token" id="csrfTokenInput" value="<?= htmlspecialchars($csrf_token ?? $this->generateCsrfToken()) ?>">
                    
                    <div class="relative flex-shrink-0">
                        <button type="button" onclick="toggleAttachMenu()" id="attachMenuBtn" class="p-2 text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors" title="Anexar">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </button>
                        <!-- Menu de anexos: cada item abre o seletor nativo do SO direto -->
                        <div id="attachMenu" class="hidden absolute bottom-full left-0 mb-2 w-52 bg-white rounded-xl shadow-lg border border-gray-200 py-1.5 z-20">
                            <button type="button" onclick="attachMenuAction('foto')" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <span class="text-purple-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path><circle cx="12" cy="13" r="3"></circle></svg>
                                </span>
                                <span>Tirar Foto</span>
                            </button>
                            <button type="button" onclick="attachMenuAction('imagem')" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <span class="text-green-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"></rect><circle cx="8.5" cy="8.5" r="1.5"></circle><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 15l-5-5L5 21"></path></svg>
                                </span>
                                <span>Enviar Imagem</span>
                            </button>
                            <button type="button" onclick="attachMenuAction('pdf')" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <span class="text-blue-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2v6h6"></path></svg>
                                </span>
                                <span>Enviar PDF</span>
                            </button>
                            <button type="button" onclick="attachMenuAction('audio')" class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors">
                                <span class="text-orange-500">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path></svg>
                                </span>
                                <span>Gravar Áudio</span>
                            </button>
                        </div>
                        <!-- Inputs de arquivo ocultos: cada um abre o seletor nativo direto, sem modal antes -->
                        <input type="file" id="attachFotoInput" accept="image/*" capture="environment" class="hidden" onchange="onAttachFileSelected(this, 'imagem')">
                        <input type="file" id="attachImagemInput" accept="image/*" class="hidden" onchange="onAttachFileSelected(this, 'imagem')">
                        <input type="file" id="attachPdfInput" accept="application/pdf" class="hidden" onchange="onAttachFileSelected(this, 'pdf')">
                    </div>

                    <div class="flex-1 relative" style="min-width: 0;">
                        <textarea id="messageText" name="mensagem" placeholder="Pergunte alguma coisa..." 
                                  rows="1"
                                  class="chat-textarea-dynamic w-full px-4 py-3 pr-14 border border-gray-300 rounded-2xl focus:ring-2 focus:ring-purple-400 focus:border-transparent"
                                  style="box-sizing: border-box; min-height: 44px;"
                                  onkeydown="handleTextareaKeydown(event)"
                                  oninput="updateInputState()"></textarea>
                        
                        <!-- Botão gravar áudio: 1º clique = gravar, 2º clique = parar e transcrever -->
                        <div id="micButtonContainer">
                            <button type="button" onclick="toggleAudioTranscription()" 
                                    id="micButton"
                                    class="mic-btn-idle px-3 py-2 text-gray-600 hover:text-gray-800 hover:bg-gray-100 rounded-xl transition-colors flex items-center gap-1.5 text-sm font-medium"
                                    title="Clique para começar a gravar; clique de novo para parar e transcrever"
                                    style="display: flex; align-items: center; justify-content: center;">
                                <svg id="micButtonIcon" class="w-5 h-5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>
                                </svg>
                                <span id="micButtonLabel">Gravar</span>
                            </button>
                        </div>
                    </div>
                    
                    <!-- Botão enviar (quando tem texto) -->
                    <button type="submit" id="sendButton" 
                            class="px-4 py-3 bg-green-500 text-white rounded-2xl hover:bg-green-600 transition-colors font-medium disabled:opacity-50 disabled:cursor-not-allowed flex items-center justify-center min-w-[44px] flex-shrink-0 hidden">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                        </svg>
                    </button>
                </form>
                
                <!-- Disclaimer -->
                <div class="mt-3 text-center">
                    <p class="text-xs text-gray-500">A <?= htmlspecialchars($iaName) ?> pode cometer erros. Por isso, lembre-se de conferir informações relevantes.</p>
                </div>
            </div>
        </div>
    </div>
</div>
</div>
</div>

<!-- Create Conversation Modal -->
<div id="createConversationModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Nova Conversa</h3>
                    <button onclick="closeCreateConversationModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="createConversationForm">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? $this->generateCsrfToken()) ?>">
                    
                    <div class="mb-4">
                        <label for="titulo" class="block text-sm font-medium text-gray-700 mb-2">Título da Conversa</label>
                        <input type="text" id="titulo" name="titulo" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                               placeholder="Ex: Dúvidas sobre Matemática">
                    </div>
                    
                    <div class="mb-4">
                        <label for="materia" class="block text-sm font-medium text-gray-700 mb-2">Matéria (opcional)</label>
                        <select id="materia" name="materia" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent">
                            <option value="">Selecione uma matéria</option>
                            <option value="Matemática">Matemática</option>
                            <option value="Português">Português</option>
                            <option value="História">História</option>
                            <option value="Geografia">Geografia</option>
                            <option value="Ciências">Ciências</option>
                            <option value="Física">Física</option>
                            <option value="Química">Química</option>
                            <option value="Biologia">Biologia</option>
                            <option value="Inglês">Inglês</option>
                            <option value="Educação Física">Educação Física</option>
                            <option value="Arte">Arte</option>
                            <option value="Filosofia">Filosofia</option>
                            <option value="Sociologia">Sociologia</option>
                        </select>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="button" onclick="closeCreateConversationModal()" 
                                class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="flex-1 px-4 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition-colors">
                            Criar Conversa
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Image View Modal -->
<div id="imageViewModal" class="fixed inset-0 bg-black bg-opacity-75 hidden z-50 flex items-center justify-center p-4">
    <div class="relative max-w-4xl max-h-full w-full">
        <button onclick="closeImageViewModal()" class="absolute top-4 right-4 z-10 bg-black bg-opacity-50 text-white rounded-full p-2 hover:bg-opacity-75 transition-all">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
        <img id="viewImage" class="max-w-full max-h-full mx-auto rounded-lg shadow-2xl cursor-zoom-out" onclick="closeImageViewModal()">
        <div class="absolute bottom-4 left-4 right-4 bg-black bg-opacity-50 text-white p-3 rounded-lg">
            <p id="imageCaption" class="text-sm text-center"></p>
        </div>
    </div>
</div>

<style>
/* Altura do chat pelo flex (não fixa), para #messagesArea ter scroll próprio */
.chat-container {
    min-height: 0;
}

/* Garantir que a área de mensagens role e mostre scrollbar quando necessário */
#messagesArea {
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
#messagesArea::-webkit-scrollbar {
    width: 8px;
}
#messagesArea::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
#messagesArea::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
#messagesArea::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

/* Lista de conversas: scroll visível quando há muitas conversas */
.chat-conversations-list {
    min-height: 0;
    overflow-y: auto;
    -webkit-overflow-scrolling: touch;
}
.chat-conversations-list::-webkit-scrollbar {
    width: 8px;
}
.chat-conversations-list::-webkit-scrollbar-track {
    background: #f1f5f9;
    border-radius: 4px;
}
.chat-conversations-list::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 4px;
}
.chat-conversations-list::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.conversation-item:hover {
    background-color: #f8fafc;
    transform: translateY(-1px);
    transition: all 0.2s ease;
}

.conversation-item.active {
    background-color: #f0fdf4;
    border-left: 4px solid #10b981;
    box-shadow: 0 2px 8px rgba(16, 185, 129, 0.1);
}

/* Cards de conversa mais compactos */
.conversation-item {
    min-height: 48px;
    max-height: 48px;
}

/* Anexo pendente: preview da imagem colada/selecionada, acima do textarea */
#pendingAttachmentPreview img {
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.15);
}

/* Modal de visualização de imagem */
#imageViewModal {
    backdrop-filter: blur(4px);
    transition: opacity 0.3s ease;
}

#imageViewModal img {
    transition: transform 0.3s ease;
}

#imageViewModal img:hover {
    transform: scale(1.02);
}

#imageViewModal button {
    backdrop-filter: blur(8px);
}

.notification {
    position: fixed;
    top: 20px;
    right: 20px;
    z-index: 1000;
    padding: 12px 20px;
    border-radius: 12px;
    color: white;
    font-weight: 500;
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    transform: translateX(100%);
    transition: transform 0.3s ease;
    backdrop-filter: blur(10px);
}

.notification.show {
    transform: translateX(0);
}

.notification.success {
    background-color: rgba(16, 185, 129, 0.9);
}

.notification.error {
    background-color: rgba(239, 68, 68, 0.9);
}

/* Estilos para formatação rica das mensagens da IA */
.ai-message-content {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    line-height: 1.6;
    color: #1f2937;
}

.ai-message-content h1 {
    color: #8b5cf6;
    font-size: 1.1rem;
    font-weight: 700;
    margin: 0.75rem 0 0.5rem 0;
}

.ai-message-content h2 {
    color: #8b5cf6;
    font-size: 1.05rem;
    font-weight: 600;
    margin: 0.75rem 0 0.4rem 0;
}

.ai-message-content h3 {
    color: #8b5cf6;
    font-size: 1rem;
    font-weight: 600;
    margin: 0.6rem 0 0.35rem 0;
}

.ai-message-content > h1:first-child,
.ai-message-content > h2:first-child,
.ai-message-content > h3:first-child {
    margin-top: 0;
}

.ai-message-content p {
    margin: 0.75rem 0;
    line-height: 1.7;
    color: #374151;
}

/* Imagem gerada pela IA embutida no texto da resposta — menor, clicável pra
   expandir (ver listener de clique em #messagesArea mais abaixo). */
.ai-message-content img {
    max-width: 280px;
    max-height: 280px;
    width: auto;
    height: auto;
    cursor: pointer;
    transition: opacity 0.15s;
}
.ai-message-content img:hover {
    opacity: 0.9;
}

/* Legenda "Imagem gerada por: ..." abaixo da imagem gerada */
.ai-message-content .tudinha-imagem-provedor {
    font-size: 0.75rem;
    color: #9ca3af;
    margin-top: -0.5rem;
}

/* Botão "Abrir Flashcards" na mensagem de flashcard pronto */
.ai-message-content .tudinha-flashcard-abrir {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    background: linear-gradient(to right, #9333ea, #a855f7);
    color: #fff;
    font-weight: 600;
    font-size: 0.875rem;
    padding: 0.5rem 1rem;
    border-radius: 0.75rem;
    text-decoration: none;
    transition: opacity 0.15s;
}
.ai-message-content .tudinha-flashcard-abrir:hover {
    opacity: 0.9;
}

.ai-message-content strong {
    font-weight: 700;
    color: #1f2937;
}

.ai-message-content em {
    font-style: italic;
    color: #6b7280;
}

.ai-message-content ul {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
    list-style-type: disc;
}

.ai-message-content ol {
    margin: 0.75rem 0;
    padding-left: 1.5rem;
    list-style-type: decimal;
}

.ai-message-content li {
    margin: 0.25rem 0;
    line-height: 1.6;
    color: #374151;
}

.ai-message-content code {
    background-color: #f3f4f6;
    color: #1f2937;
    padding: 0.125rem 0.375rem;
    border-radius: 0.25rem;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    border: 1px solid #e5e7eb;
}

.ai-message-content pre {
    background-color: #1f2937;
    color: #f9fafb;
    padding: 1rem;
    border-radius: 0.5rem;
    overflow-x: auto;
    margin: 1rem 0;
    font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
    font-size: 0.875rem;
    line-height: 1.5;
    border: 1px solid #374151;
}

.ai-message-content pre code {
    background: none;
    color: inherit;
    padding: 0;
    border: none;
    font-size: inherit;
}

.ai-message-content blockquote {
    border-left: 4px solid #8b5cf6;
    padding-left: 1rem;
    margin: 1rem 0;
    color: #6b7280;
    font-style: italic;
    background-color: #f8fafc;
    padding: 0.75rem 1rem;
    border-radius: 0.375rem;
}

.ai-message-content a {
    color: #3b82f6;
    text-decoration: underline;
    font-weight: 500;
}

.ai-message-content a:hover {
    color: #1d4ed8;
}

.ai-message-content sub {
    font-size: 0.75em;
    vertical-align: sub;
    color: #6b7280;
}

.ai-message-content sup {
    font-size: 0.75em;
    vertical-align: super;
    color: #6b7280;
}

/* Melhorar espaçamento geral */
.ai-message-content > *:first-child {
    margin-top: 0;
}

.ai-message-content > *:last-child {
    margin-bottom: 0;
}

/* Melhorias no visual das mensagens */
.messages-area {
    scrollbar-width: thin;
    scrollbar-color: #e5e7eb transparent;
}

.messages-area::-webkit-scrollbar {
    width: 6px;
}

.messages-area::-webkit-scrollbar-track {
    background: transparent;
}

.messages-area::-webkit-scrollbar-thumb {
    background-color: #e5e7eb;
    border-radius: 3px;
}

.messages-area::-webkit-scrollbar-thumb:hover {
    background-color: #d1d5db;
}

/* Animações suaves */
.message-bubble {
    animation: fadeInUp 0.3s ease-out;
}

@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* Input melhorado */
.message-input {
    backdrop-filter: blur(10px);
    background-color: rgba(255, 255, 255, 0.95);
}

/* Garantir que o campo de input fique completamente oculto quando não há conversa */
#messageInput.hidden {
    display: none !important;
    visibility: hidden !important;
    height: 0 !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    opacity: 0 !important;
    pointer-events: none !important;
}

#messageInput:not(.hidden) {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    pointer-events: auto !important;
}

/* Garantir que o formulário tenha layout correto */
#messageForm {
    width: 100%;
    display: flex;
    align-items: flex-end;
}

/* Cursor piscando no fim da bolha da IA durante o streaming */
.tudinha-cursor-piscando {
    display: inline-block;
    animation: tudinha-piscar 1s steps(1) infinite;
    color: rgb(147, 51, 234);
}

@keyframes tudinha-piscar {
    0%, 50% { opacity: 1; }
    51%, 100% { opacity: 0; }
}

/* Destaque ao arrastar um arquivo sobre a área de mensagens */
.chat-drop-highlight {
    outline: 2px dashed rgb(147, 51, 234);
    outline-offset: -4px;
    background-color: rgba(147, 51, 234, 0.04);
}

/* Modo de voz: avatar pulsa enquanto a Tudinha está falando */
#voiceModeAvatar.voice-speaking {
    animation: voice-pulse 1.1s ease-in-out infinite;
}

@keyframes voice-pulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.12); }
}

#messageForm > * {
    flex-shrink: 0;
}

#messageForm > .flex-1 {
    flex: 1 1 0%;
    min-width: 0;
}

/* Ajustar posicionamento do botão do microfone */
#micButtonContainer {
    position: absolute;
    right: 8px;
    bottom: 8px;
    z-index: 10;
}

/* Textarea: altura controlada por JS (auto-resize 1-5 linhas) */
#messageText {
    line-height: 1.5;
    box-sizing: border-box;
}

/* Garantir que o formulário mantenha layout estável */
#messageForm {
    align-items: flex-end;
    min-height: 48px;
}

#messageForm .flex-1 {
    display: block;
    position: relative;
}

/* Garantir que o container do input tenha altura mínima e nunca seja escondido pelo scroll */
#messageInput:not(.hidden) {
    min-height: auto;
    flex-shrink: 0;
}
/* Container do chat: altura limitada ao disponível para mensagens + input sempre visível */
.chat-container {
    display: flex;
    flex-direction: column;
    min-height: 0;
}
.chat-container #messagesArea {
    flex: 1 1 0;
    min-height: 0;
    overflow-y: auto;
}

#messageInput:not(.hidden) #messageForm {
    min-height: 48px;
}

/* Botões de interação */
.interaction-button {
    transition: all 0.2s ease;
}

.interaction-button:hover {
    transform: scale(1.1);
    background-color: rgba(0, 0, 0, 0.05);
}

/* Lista de conversas oculta (ao selecionar conversa, estilo WhatsApp Web) */
#chatGrid.chat-list-hidden #chatConversationsColumn {
    display: none !important;
}
#chatGrid.chat-list-hidden #chatAreaColumn {
    grid-column: 1 / -1;
}

/* Modo foco: esconde apenas lista de conversas + header do main + header topo do chat; mantém sidebar (navbar lateral) */
body.chat-focus-mode #chatConversationsColumn {
    display: none !important;
}
body.chat-focus-mode #chatGrid #chatAreaColumn {
    grid-column: 1 / -1;
}
body.chat-focus-mode main {
    max-width: none !important;
}
/* Esconder header do main (título, avatar) para dar mais altura ao chat */
body.chat-focus-mode main > header {
    display: none !important;
}
/* Esconder banner "Chat IA" + Nova Conversa no modo foco */
body.chat-focus-mode #chatTopHeader {
    display: none !important;
}
/* Ajustar padding no modo foco para usar toda a altura */
body.chat-focus-mode .chat-content-wrap {
    padding-top: 0.5rem !important;
}

/* Textarea auto-resize: máx 5 linhas (~120px), 1 linha inicial */
#messageText.chat-textarea-dynamic {
    min-height: 44px !important;
    height: auto !important;
    max-height: 120px !important;
    resize: none;
    overflow-y: auto;
    line-height: 1.5;
    transition: height 0.1s ease-out;
}
</style>

<?php include __DIR__ . '/../components/ai-job-poller.php'; ?>

<script>
const CHAT_BASE_URL = (typeof URL_BASE !== 'undefined' ? URL_BASE : '<?= rtrim(URL, "/") ?>');
let currentConversationId = null;
function chatImageSrc(path) {
    if (!path || !String(path).trim()) return '';
    const p = String(path).trim();
    if (p.indexOf('http') === 0) return p;
    // Sempre usar a rota do app para imagens do chat (evita 404 e log "Rota não encontrada" no servidor)
    if (p.indexOf('storage/chat/') !== -1) {
        const filename = p.split('/').pop();
        if (filename) return CHAT_BASE_URL + '/chat/ver-imagem?f=' + encodeURIComponent(filename);
    }
    return CHAT_BASE_URL + (p.indexOf('/') === 0 ? p : '/' + p);
}
let isRecording = false;
let mediaRecorder = null;
let audioChunks = [];
let currentStream = null;

// Atualiza o token CSRF em todos os campos e na meta tag (quando o servidor devolve token inválido + novo token)
function updateCsrfTokenInPage(newToken) {
    if (!newToken || typeof newToken !== 'string') return;
    document.querySelectorAll('input[name="_token"]').forEach(function(el) { el.value = newToken; });
    var meta = document.querySelector('meta[name="csrf-token"]');
    if (meta) meta.setAttribute('content', newToken);
    var csrfInput = document.getElementById('csrfTokenInput');
    if (csrfInput) csrfInput.value = newToken;
}

// Retry em falhas de rede (principalmente primeira conversa / primeira mensagem)
function fetchWithRetry(url, options, maxRetries) {
    maxRetries = maxRetries || 2;
    function attempt(attemptNumber) {
        return fetch(url, options).catch(function(err) {
            if (attemptNumber < maxRetries) {
                return new Promise(function(resolve) { setTimeout(resolve, 800 * (attemptNumber + 1)); }).then(function() {
                    return attempt(attemptNumber + 1);
                });
            }
            throw err;
        });
    }
    return attempt(0);
}

// Garantir que o campo de input esteja oculto ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    const messageInput = document.getElementById('messageInput');
    if (messageInput && !currentConversationId) {
        messageInput.classList.add('hidden');
        messageInput.style.display = 'none';
        messageInput.style.visibility = 'hidden';
        messageInput.style.opacity = '0';
        messageInput.style.height = '0';
        messageInput.style.padding = '0';
        messageInput.style.margin = '0';
        messageInput.style.overflow = 'hidden';
        messageInput.style.pointerEvents = 'none';
    }
});

// Truncate message text
function truncateMessage(message, maxLength = 50) {
    if (!message) return 'Nenhuma mensagem';
    if (message.length <= maxLength) return message;
    return message.substring(0, maxLength) + '...';
}

// Modal functions
function openCreateConversationModal() {
    document.getElementById('createConversationModal').classList.remove('hidden');
}

function closeCreateConversationModal() {
    document.getElementById('createConversationModal').classList.add('hidden');
    document.getElementById('createConversationForm').reset();
}

// ========== 3 MODOS SIMPLES ==========

// MODO 1: Texto normal -> Resposta texto (já implementado no form submit)

// MODO 2: Áudio -> Transcreve -> Responde texto
function toggleAudioTranscription() {
    if (!isRecording) {
        startAudioRecording();
    } else {
        stopAudioRecording();
    }
}


// Atualizar estado do input (vazio/preenchido) + auto-resize 1-5 linhas
function updateInputState() {
    const messageText = document.getElementById('messageText');
    if (!messageText) return;
    
    var minHeight = 44;
    var lineHeight = 22;
    var maxLines = 5;
    var maxHeight = lineHeight * maxLines;
    messageText.style.height = 'auto';
    var newHeight = Math.min(maxHeight, Math.max(minHeight, messageText.scrollHeight));
    messageText.style.height = newHeight + 'px';
    
    var hasText = messageText.value.trim().length > 0;
    var sendButton = document.getElementById('sendButton');
    
    if (hasText) {
        if (sendButton) sendButton.classList.remove('hidden');
    } else {
        if (sendButton) sendButton.classList.add('hidden');
    }
}

// Inicializar altura do textarea quando o campo aparecer (1 linha)
function initializeMessageInput() {
    var messageInput = document.getElementById('messageInput');
    var messageText = document.getElementById('messageText');
    
    if (messageInput && messageText && !messageInput.classList.contains('hidden')) {
        messageText.style.height = 'auto';
        messageText.style.minHeight = '44px';
        messageText.style.height = '44px';
        void messageText.offsetHeight;
    }
}

// Handle Enter key
function handleTextareaKeydown(e) {
    if (e.key === 'Enter' && !e.shiftKey) {
        e.preventDefault();
        document.getElementById('messageForm').dispatchEvent(new Event('submit'));
    }
}

// Polling para verificar novas mensagens e atualizar contagem
let pollingInterval = null;
let lastMessageCount = {};
const CONVERSA_INFO_POLLING_MS = 10000;

function startPolling() {
    if (pollingInterval) return; // Já está rodando
    if (document.hidden) return; // Não faz polling quando a aba está inativa
    
    pollingInterval = setInterval(() => {
        if (document.hidden) return;
        updateAllConversationCounts();
    }, CONVERSA_INFO_POLLING_MS); // Verificar a cada 10 segundos
}

function stopPolling() {
    if (pollingInterval) {
        clearInterval(pollingInterval);
        pollingInterval = null;
    }
}

function handleChatVisibilityChange() {
    if (document.hidden) {
        stopPolling();
        return;
    }
    startPolling();
    updateAllConversationCounts();
}

function updateAllConversationCounts() {
    const conversationItems = document.querySelectorAll('.conversation-item');
    
    conversationItems.forEach(item => {
        const conversaId = item.getAttribute('data-conversa-id');
        if (!conversaId) return;
        
        // Buscar informações atualizadas da conversa
        fetch(`<?= URL ?>/chat/conversa-info?conversa_id=${conversaId}`)
            .then(response => {
                // Verificar se a resposta é JSON
                const contentType = response.headers.get('content-type');
                if (!contentType || !contentType.includes('application/json')) {
                    return response.text().then(text => {
                        console.error('Resposta não-JSON recebida em conversa-info:', text.substring(0, 200));
                        throw new Error('Resposta inválida do servidor');
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.interacoes !== undefined) {
                    const countElement = item.querySelector('.text-orange-500');
                    if (countElement) {
                        const currentCount = parseInt(countElement.textContent) || 0;
                        const newCount = data.interacoes || 0;
                        
                        if (newCount !== currentCount) {
                            countElement.textContent = newCount;
                            countElement.classList.add('animate-pulse');
                            setTimeout(() => {
                                countElement.classList.remove('animate-pulse');
                            }, 1000);
                        }
                    }
                }
            })
            .catch(error => {
                // Silenciar erros de conversa-info para não poluir o console
                // console.error('Erro ao atualizar contagem:', error);
            });
    });
}

// Update interaction count in real-time (only when complete interaction)
function updateInteractionCount(conversaId, isCompleteInteraction = false) {
    const conversationItem = document.querySelector(`[data-conversa-id="${conversaId}"]`);
    if (!conversationItem) return;
    
    const countElement = conversationItem.querySelector('.text-orange-500');
    if (!countElement) return;
    
    // Só atualizar se for uma interação completa (pergunta + resposta)
    if (isCompleteInteraction) {
        let currentCount = parseInt(countElement.textContent) || 0;
        currentCount++;
        countElement.textContent = currentCount;
        
        // Add animation effect
        countElement.classList.add('animate-pulse');
        setTimeout(() => {
            countElement.classList.remove('animate-pulse');
        }, 1000);
    }
}

// Filtra a lista de conversas (client-side, sem chamada ao backend) pelo
// título exibido em cada .conversation-item, conforme o aluno digita na busca.
function filtrarConversasChat(termo) {
    const alvo = (termo || '').trim().toLowerCase();
    const itens = document.querySelectorAll('#conversationsList .conversation-item');
    itens.forEach(function (item) {
        const titulo = item.querySelector('h3');
        const texto = titulo ? titulo.textContent.toLowerCase() : '';
        item.style.display = (alvo === '' || texto.includes(alvo)) ? '' : 'none';
    });
}

// Add conversation to list
function addConversationToList(conversa) {
    let conversationsList = document.getElementById('conversationsList');
    
    // Se a lista não existe (primeira conversa), criar o container
    if (!conversationsList) {
        // Buscar o container específico que contém "Nenhuma conversa ainda"
        // É o segundo .p-6 dentro de .bg-white.rounded-xl.shadow-lg
        const card = document.querySelector('.bg-white.rounded-xl.shadow-lg');
        if (card) {
            const containers = card.querySelectorAll('.p-6');
            // O segundo container (índice 1) é onde está "Nenhuma conversa ainda"
            if (containers.length > 1) {
                containers[1].innerHTML = '<div class="space-y-1" id="conversationsList"></div>';
                conversationsList = document.getElementById('conversationsList');
            }
        }
    }
    
    // Criar elemento da nova conversa
    const conversationItem = document.createElement('div');
    conversationItem.className = 'conversation-item p-2 rounded-lg hover:bg-gray-50 cursor-pointer transition-colors';
    conversationItem.setAttribute('data-conversa-id', conversa.id);
    conversationItem.onclick = () => loadConversation(conversa.id);
    
    conversationItem.innerHTML = `
        <div class="flex items-center justify-between">
            <div class="flex-1">
                <h3 class="font-medium text-gray-900 text-sm">${escapeHtml(conversa.titulo)}</h3>
                <p class="text-xs text-gray-500">${escapeHtml(conversa.materia_nome || 'Geral')}</p>
            </div>
            <div class="flex items-center space-x-2">
                <span class="text-sm font-medium text-orange-500">0</span>
                <button onclick="event.stopPropagation(); deleteConversation(${conversa.id})" 
                        class="text-red-400 hover:text-red-600 text-xs p-1 rounded hover:bg-red-50 transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                    </svg>
                </button>
            </div>
        </div>
    `;
    
    // Adicionar no início da lista
    conversationsList.insertBefore(conversationItem, conversationsList.firstChild);
}

// Create conversation
document.getElementById('createConversationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetchWithRetry('<?= URL ?>/chat/conversa', {
        method: 'POST',
        body: formData
    }, 2)
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Conversa criada com sucesso!');
            closeCreateConversationModal();
            
            // Adicionar nova conversa à lista
            addConversationToList(data.conversa);
            
            // Abrir automaticamente a nova conversa
            loadConversation(data.conversa.id);
        } else {
            if (data.error === 'Token inválido' && data.csrf_token) {
                updateCsrfTokenInPage(data.csrf_token);
                showNotification('Sessão atualizada. Tente criar a conversa novamente.', 'error');
            } else {
                showNotification(data.error || 'Erro ao criar conversa', 'error');
            }
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
});

// Esconder lista de conversas e mostrar botão Voltar (comportamento WhatsApp Web)
function chatHideConversationsList() {
    var grid = document.getElementById('chatGrid');
    var backBtn = document.getElementById('chatBackToList');
    if (grid) grid.classList.add('chat-list-hidden');
    if (backBtn) backBtn.classList.remove('hidden');
}
function chatShowConversationsList() {
    var grid = document.getElementById('chatGrid');
    var backBtn = document.getElementById('chatBackToList');
    if (grid) grid.classList.remove('chat-list-hidden');
    if (backBtn) backBtn.classList.add('hidden');
    if (chatFocusMode) chatToggleFocusMode();
}
// Modo foco: colapsa lista + sidebar para 100% largura do chat
var chatFocusMode = false;
function chatToggleFocusMode() {
    chatFocusMode = !chatFocusMode;
    document.body.classList.toggle('chat-focus-mode', chatFocusMode);
    var btn = document.getElementById('chatFocusModeBtn');
    if (btn) btn.title = chatFocusMode ? 'Sair do modo foco' : 'Modo foco (expandir chat)';
}

// Load conversation
function loadConversation(conversaId) {
    currentConversationId = conversaId;
    document.getElementById('currentConversationId').value = conversaId;
    // Esconder lista e mostrar botão Voltar (estilo WhatsApp Web, qualquer tela)
    chatHideConversationsList();
    const messageInput = document.getElementById('messageInput');
    if (messageInput) {
        messageInput.classList.remove('hidden');
        messageInput.style.display = 'block';
        messageInput.style.visibility = 'visible';
        messageInput.style.opacity = '1';
        messageInput.style.height = 'auto';
        messageInput.style.padding = '';
        messageInput.style.margin = '';
        messageInput.style.overflow = '';
        messageInput.style.pointerEvents = 'auto';
        
        // Inicializar altura do textarea após aparecer e aplicar prefill (flashcards, jornada, etc.)
        function applyPrefillIfPending() {
            var prefill = window.pendingChatPrefill || window.pendingFlashcardPrefill;
            if (!prefill) return;
            const messageText = document.getElementById('messageText');
            if (messageText) {
                messageText.value = prefill;
                updateInputState();
                messageText.focus();
            }
            window.pendingChatPrefill = null;
            window.pendingFlashcardPrefill = null;
        }
        setTimeout(function() {
            initializeMessageInput();
            applyPrefillIfPending();
        }, 10);
        setTimeout(applyPrefillIfPending, 150);
    }
    
    // Update UI (defensivo: se o item não estiver no DOM — ex.: filtrado pela
    // busca — não deve impedir o carregamento das mensagens abaixo)
    try {
        const conversationItem = document.querySelector(`[data-conversa-id="${conversaId}"]`);
        const title = conversationItem?.querySelector('h3')?.textContent;
        const materia = conversationItem?.querySelector('p')?.textContent;
        if (title) {
            document.getElementById('conversationTitle').textContent = title;
            document.getElementById('conversationSubject').textContent = materia || 'Conversa geral';
        }
    } catch (e) {
        console.error('Erro ao atualizar título da conversa:', e);
    }

    // Load messages
    loadMessages(conversaId);
}

// Load messages
function loadMessages(conversaId, silent) {
    const messagesArea = document.getElementById('messagesArea');

    // Mostrar loading — pulado quando "silent" (ex.: troca da bolha de streaming
    // pela versão final): apagar a tela pra mostrar um spinner e redesenhar tudo
    // de novo em seguida gera um "flash" perceptível, já que o streaming acabou
    // de mostrar o conteúdo certinho um instante antes.
    if (!silent) {
        messagesArea.innerHTML = `
            <div class="flex items-center justify-center h-full">
                <div class="text-center">
                    <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-500 mx-auto mb-2"></div>
                    <p class="text-gray-500">Carregando mensagens...</p>
                </div>
            </div>
        `;
    }

    // Buscar mensagens do servidor (retry na primeira carga para evitar "Erro de conexão" intermitente)
    fetchWithRetry(`<?= URL ?>/chat/mensagens?conversa_id=${conversaId}&t=${Date.now()}`, {}, 2)
        .then(response => {
            // Verificar se a resposta é JSON válida
            const contentType = response.headers.get('content-type') || '';
            if (!contentType.includes('application/json')) {
                return response.text().then(text => {
                    console.error('Resposta não-JSON ao carregar mensagens:', text.substring(0, 500));
                    throw new Error('Resposta inválida do servidor');
                });
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                console.log('Mensagens carregadas:', data.mensagens?.length || 0);
                
                // Verificar se há uma nova interação completa (pergunta + resposta)
                const mensagens = data.mensagens;
                if (mensagens && mensagens.length >= 2) {
                    const ultimaMensagem = mensagens[mensagens.length - 1];
                    const penultimaMensagem = mensagens[mensagens.length - 2];
                    
                    // Verificar se a última é da IA e a penúltima é do usuário
                    if (ultimaMensagem.is_ia && !penultimaMensagem.is_ia && ultimaMensagem.created_at) {
                        // Verificar se é uma mensagem nova (criada nos últimos 5 segundos)
                        const agora = new Date();
                        const dataMensagem = new Date(ultimaMensagem.created_at);
                        const diferencaSegundos = (agora - dataMensagem) / 1000;
                        
                        if (diferencaSegundos < 5) {
                            // É uma nova interação completa (pergunta + resposta), atualizar contagem
                            updateInteractionCount(conversaId, true);
                        }
                    }
                }
                
                // Remover mensagem "pensando" se existir
                removeThinkingMessage();
                
                displayMessages(data.mensagens);
            } else {
                messagesArea.innerHTML = `
                    <div class="flex items-center justify-center h-full text-gray-500">
                        <div class="text-center">
                            <p class="text-lg font-medium">Erro ao carregar mensagens</p>
                            <p class="text-sm">${data.error || 'Tente novamente'}</p>
                        </div>
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            messagesArea.innerHTML = `
                <div class="flex items-center justify-center h-full text-gray-500">
                    <div class="text-center">
                        <p class="text-lg font-medium">Erro de conexão</p>
                        <p class="text-sm">Verifique sua conexão e tente novamente</p>
                    </div>
                </div>
            `;
        });
}

// Converte o bloco <div class="tudinha-sugestoes" data-sugestoes='[...]'>
// (já extraído no backend — StudentController::getMessages() — para o campo
// mensagem.sugestoes; não depende de sobreviver ao sanitizador de HTML) em
// chips clicáveis que reenviam a pergunta como nova mensagem.
function renderizarSugestoesTudinha(container, sugestoes) {
    if (!Array.isArray(sugestoes) || sugestoes.length === 0) {
        return;
    }
    const wrapper = document.createElement('div');
    wrapper.className = 'flex flex-wrap gap-2 mt-3';
    sugestoes.slice(0, 4).forEach(function (pergunta) {
        if (typeof pergunta !== 'string' || !pergunta.trim()) {
            return;
        }
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'text-left px-3 py-2 text-sm bg-purple-50 text-purple-700 border border-purple-200 rounded-full hover:bg-purple-100 hover:border-purple-300 transition-colors inline-flex items-center gap-2';
        chip.innerHTML = '<span class="flex-shrink-0">💡</span><span>' + escapeHtml(pergunta) + '</span>';
        chip.onclick = function () {
            tudinhaEnviarSugestao(pergunta);
        };
        wrapper.appendChild(chip);
    });
    container.appendChild(wrapper);
}

// Preenche o campo de mensagem com a sugestão clicada e envia como se o
// aluno tivesse digitado — mesmo padrão de disparo de submit já usado no
// prefill de dúvidas vindo dos flashcards.
function tudinhaEnviarSugestao(texto) {
    const input = document.getElementById('messageText');
    const form = document.getElementById('messageForm');
    if (!input || !form) {
        return;
    }
    input.value = texto;
    form.dispatchEvent(new Event('submit'));
}

// Copia o texto puro (sem HTML) da resposta da IA pra área de transferência.
function copiarRespostaTudinha(texto, botao) {
    if (!navigator.clipboard) {
        return;
    }
    navigator.clipboard.writeText(texto).then(function () {
        const original = botao.innerHTML;
        botao.innerHTML = '<svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>';
        setTimeout(function () { botao.innerHTML = original; }, 1500);
    }).catch(function () {
        showNotification('Não foi possível copiar. Tente selecionar o texto manualmente.', 'error');
    });
}

// Reenvia a última pergunta do aluno na conversa atual — mesmo padrão de
// disparo de submit já usado pelos chips de sugestão.
function regenerarRespostaTudinha() {
    // Procura, de trás pra frente, a última bolha que NÃO é da IA (mensagem do aluno).
    const todasBolhas = Array.from(document.getElementById('messagesArea').children[0]?.children || []);
    for (let i = todasBolhas.length - 1; i >= 0; i--) {
        const bolha = todasBolhas[i];
        if (bolha.classList.contains('justify-end')) {
            const texto = bolha.querySelector('p')?.textContent || '';
            if (texto.trim()) {
                tudinhaEnviarSugestao(texto.trim());
            }
            return;
        }
    }
}

// Display messages
function displayMessages(mensagens) {
    console.log('displayMessages chamada com:', mensagens);
    const messagesArea = document.getElementById('messagesArea');
    
    if (mensagens.length === 0) {
        messagesArea.innerHTML = `
            <div class="flex items-center justify-center h-full text-gray-500">
                <div class="text-center">
                    <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                    </svg>
                    <p class="text-lg font-medium">Nenhuma mensagem ainda</p>
                    <p class="text-sm">Envie uma mensagem para começar a conversa!</p>
                </div>
            </div>
        `;
        return;
    }
    
    // Limpar área de mensagens
    messagesArea.innerHTML = '';
    const container = document.createElement('div');
    container.className = 'space-y-6';
    
    mensagens.forEach(mensagem => {
        const isIA = mensagem.is_ia == 1;
        const time = new Date(mensagem.created_at).toLocaleTimeString('pt-BR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        
        if (isIA) {
            // Preparar texto para áudio (remover HTML)
            const tempDivAudio = document.createElement('div');
            tempDivAudio.innerHTML = mensagem.mensagem_formatada || mensagem.mensagem || '';
            const textoParaAudio = tempDivAudio.textContent || tempDivAudio.innerText || '';
            const textoEscapado = textoParaAudio.replace(/`/g, '\\`').replace(/\$/g, '\\$').replace(/\\/g, '\\\\');
            
            // Criar elemento da mensagem
            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex items-end gap-2 justify-start';
            if (mensagem.id) {
                messageDiv.setAttribute('data-mensagem-id', mensagem.id);
            }
            messageDiv.appendChild(criarAvatarIaChat());

            const messageContent = document.createElement('div');
            messageContent.className = 'bg-gray-100 text-gray-900 p-4 rounded-2xl rounded-bl-sm max-w-3xl shadow-sm';

            // Criar container do conteúdo
            const contentDiv = document.createElement('div');
            contentDiv.className = 'ai-message-content';
            
            // Se tem imagem, adicionar primeiro
            let temImagem = false;
            if (mensagem.tipo === 'imagem' && mensagem.image_url) {
                temImagem = true;
                const imgDiv = document.createElement('div');
                imgDiv.className = 'mb-3';
                
                const img = document.createElement('img');
                img.src = chatImageSrc(mensagem.image_url);
                img.alt = 'Imagem gerada';
                img.className = 'max-w-full rounded-lg shadow-lg cursor-pointer hover:opacity-90 transition-opacity';
                img.style.maxWidth = '100%';
                img.style.height = 'auto';
                img.onclick = () => openImageViewModal(chatImageSrc(mensagem.image_url), 'Imagem gerada em ' + time);
                
                imgDiv.appendChild(img);
                contentDiv.appendChild(imgDiv);
            }
            
            // Adicionar conteúdo HTML formatado
            // IMPORTANTE: Para mensagens da IA, mensagem_formatada já contém HTML válido
            // Não devemos escapar, apenas inserir diretamente via innerHTML
            let conteudoHtml = '';
            
            if (mensagem.is_ia) {
                // Mensagem da IA: usar mensagem_formatada se disponível (já é HTML)
                // Se não tiver mensagem_formatada, usar mensagem original (pode ser HTML também)
                if (mensagem.mensagem_formatada && typeof mensagem.mensagem_formatada === 'string' && mensagem.mensagem_formatada.trim() !== '') {
                    conteudoHtml = mensagem.mensagem_formatada;
                } else if (mensagem.mensagem && typeof mensagem.mensagem === 'string') {
                    // A mensagem original da IA pode já ser HTML
                    // Se contém tags HTML, usar diretamente; caso contrário, escapar
                    if (mensagem.mensagem.includes('<') && mensagem.mensagem.includes('>')) {
                        conteudoHtml = mensagem.mensagem;
                    } else {
                        conteudoHtml = escapeHtml(mensagem.mensagem);
                    }
                }
            } else {
                // Mensagem do usuário: sempre escapar para segurança
                if (mensagem.mensagem_formatada && mensagem.mensagem_formatada.trim() !== '') {
                    // Se tem formatação, usar (já está escapada pelo backend)
                    conteudoHtml = mensagem.mensagem_formatada;
                } else if (mensagem.mensagem) {
                    conteudoHtml = escapeHtml(mensagem.mensagem);
                }
            }
            
            // Inserir HTML diretamente no contentDiv usando innerHTML
            // Isso renderiza o HTML corretamente em vez de exibir como texto
            contentDiv.innerHTML = conteudoHtml;

            // Respostas educacionais podem vir com sugestões de aprofundamento
            // (extraídas no backend em mensagem.sugestoes) — exibe como chips clicáveis
            if (mensagem.is_ia && Array.isArray(mensagem.sugestoes) && mensagem.sugestoes.length > 0) {
                renderizarSugestoesTudinha(contentDiv, mensagem.sugestoes);
            }

            messageContent.appendChild(contentDiv);
            
            // Verificar se a mensagem menciona que não consegue criar imagens
            const textoMensagem = mensagem.mensagem || mensagem.mensagem_formatada || '';
            const textoMensagemLower = textoMensagem.toLowerCase();
            const mencionaNaoPodeCriar = (textoMensagemLower.includes('não consigo gerar') || 
                                         textoMensagemLower.includes('não posso criar') || 
                                         textoMensagemLower.includes('não consigo criar') ||
                                         textoMensagemLower.includes('infelizmente') && textoMensagemLower.includes('imagem') ||
                                         textoMensagemLower.includes('não tenho') && textoMensagemLower.includes('capacidade')) &&
                                        (textoMensagemLower.includes('imagem') || textoMensagemLower.includes('desenhar') || textoMensagemLower.includes('ilustração'));
            
            // Botões e timestamp
            const footerDiv = document.createElement('div');
            footerDiv.className = 'flex items-center justify-between mt-3';
            
            const buttonsDiv = document.createElement('div');
            buttonsDiv.className = 'flex items-center space-x-2';
            
            // Botão Gerar Imagem (se mencionar que não consegue criar)
            if (mencionaNaoPodeCriar && !temImagem) {
                const generateImageBtn = document.createElement('button');
                generateImageBtn.className = 'px-3 py-1.5 bg-blue-500 text-white text-sm rounded-lg hover:bg-blue-600 transition-colors flex items-center space-x-1';
                generateImageBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span>Gerar Imagem</span>
                `;
                generateImageBtn.onclick = () => gerarImagemDaResposta(mensagem.id, currentConversationId, mensagem.mensagem || mensagem.mensagem_formatada);
                buttonsDiv.appendChild(generateImageBtn);
            }
            
            if (!temImagem) {
                const audioBtn = document.createElement('button');
                audioBtn.className = 'p-1 text-green-500 hover:text-green-600 transition-colors';
                audioBtn.title = 'Ouvir explicação em áudio';
                audioBtn.onclick = () => playAudioResponse(mensagem.id || '', textoEscapado);
                audioBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.536 8.464a5 5 0 010 7.072m2.828-9.9a9 9 0 010 12.728M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"></path>
                    </svg>
                `;
                buttonsDiv.appendChild(audioBtn);

                const copyBtn = document.createElement('button');
                copyBtn.className = 'p-1 text-gray-400 hover:text-gray-600 transition-colors';
                copyBtn.title = 'Copiar resposta';
                copyBtn.onclick = () => copiarRespostaTudinha(textoParaAudio, copyBtn);
                copyBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                    </svg>
                `;
                buttonsDiv.appendChild(copyBtn);

                const regenBtn = document.createElement('button');
                regenBtn.className = 'p-1 text-gray-400 hover:text-purple-600 transition-colors';
                regenBtn.title = 'Regenerar resposta';
                regenBtn.onclick = () => regenerarRespostaTudinha();
                regenBtn.innerHTML = `
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path>
                    </svg>
                `;
                buttonsDiv.appendChild(regenBtn);
            }

            const timeSpan = document.createElement('span');
            timeSpan.className = 'text-xs text-gray-500';
            timeSpan.textContent = time;
            
            footerDiv.appendChild(buttonsDiv);
            footerDiv.appendChild(timeSpan);
            messageContent.appendChild(footerDiv);
            
            messageDiv.appendChild(messageContent);
            container.appendChild(messageDiv);
        } else {
            // Mensagem do usuário
            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex justify-end';
            
            const messageContent = document.createElement('div');
            messageContent.className = 'bg-gradient-to-r from-purple-600 to-purple-500 text-white p-4 rounded-2xl rounded-br-sm max-w-3xl shadow-sm';

            // Exibir imagem enviada pelo aluno (URL completa para funcionar em localhost e no servidor)
            const imageUrlUser = (mensagem.image_url && String(mensagem.image_url).trim()) ? String(mensagem.image_url).trim() : '';
            if (mensagem.tipo === 'imagem' && imageUrlUser) {
                const imgDiv = document.createElement('div');
                imgDiv.className = 'mb-2';
                
                const img = document.createElement('img');
                img.src = chatImageSrc(imageUrlUser);
                img.alt = 'Imagem enviada';
                img.className = 'max-w-xs max-h-48 rounded-lg cursor-pointer hover:opacity-90 transition-opacity shadow-sm';
                img.onerror = function() { this.style.display = 'none'; this.parentNode.appendChild(document.createTextNode('(Imagem não disponível)')); };
                img.onclick = () => openImageViewModal(chatImageSrc(imageUrlUser), 'Imagem enviada em ' + time);
                
                imgDiv.appendChild(img);
                messageContent.appendChild(imgDiv);
            }
            // Não exibir texto extraído (OCR) no balão; para imagem já mostramos só a imagem (backend envia "Imagem enviada")
            const textoExibir = (mensagem.tipo === 'imagem' && imageUrlUser) ? '' : (mensagem.mensagem || '');
            if (textoExibir) {
                const p = document.createElement('p');
                p.className = 'whitespace-pre-wrap leading-relaxed';
                p.textContent = textoExibir;
                messageContent.appendChild(p);
            }
            
            const footerDiv = document.createElement('div');
            footerDiv.className = 'flex items-center justify-end mt-2';
            
            const timeSpan = document.createElement('span');
            timeSpan.className = 'text-xs opacity-75';
            timeSpan.textContent = time;
            
            footerDiv.appendChild(timeSpan);
            messageContent.appendChild(footerDiv);
            
            messageDiv.appendChild(messageContent);
            container.appendChild(messageDiv);
        }
    });
    
    // Adicionar todas as mensagens de uma vez
    messagesArea.appendChild(container);
    
    // Scroll para o final
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Escape HTML
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Display user message immediately
function displayUserMessage(message) {
    const messagesArea = document.getElementById('messagesArea');
    const now = new Date();
    const time = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
    
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex justify-end mb-4';
    messageDiv.innerHTML = `
        <div class="max-w-xs lg:max-w-md">
            <div class="bg-gradient-to-r from-purple-600 to-purple-500 text-white p-3 rounded-lg">
                <p class="text-sm">${escapeHtml(message)}</p>
            </div>
            <p class="text-xs text-gray-500 mt-1 text-right">${time}</p>
        </div>
    `;
    
    messagesArea.appendChild(messageDiv);
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Nome da IA configurável
const IA_NAME = <?= json_encode($iaName) ?>;
const IA_AVATAR_URL = <?= json_encode($iaAvatarUrl) ?>;

// Avatar circular ao lado de cada resposta da IA — mesmo padrão do chat da
// apostila (app/Views/components/apostila_ia_chat_script.php), usando o
// ícone customizado por escola quando existir, senão o emoji padrão.
function criarAvatarIaChat() {
    const avatar = document.createElement('div');
    avatar.className = 'w-8 h-8 rounded-full overflow-hidden flex-shrink-0 bg-purple-100 flex items-center justify-center self-end shadow-sm';
    if (IA_AVATAR_URL) {
        const img = document.createElement('img');
        img.src = IA_AVATAR_URL;
        img.alt = IA_NAME;
        img.className = 'w-full h-full object-cover';
        img.onerror = function () { avatar.innerHTML = '<span class="text-base">🤖</span>'; };
        avatar.appendChild(img);
    } else {
        avatar.innerHTML = '<span class="text-base">🤖</span>';
    }
    return avatar;
}

// Display "IA está pensando..." message
function displayThinkingMessage(textoCustom) {
    const messagesArea = document.getElementById('messagesArea');
    const texto = textoCustom || `${IA_NAME} está pensando...`;

    const thinkingDiv = document.createElement('div');
    thinkingDiv.id = 'thinking-message';
    thinkingDiv.className = 'flex justify-start mb-4';
    thinkingDiv.innerHTML = `
        <div class="max-w-xs lg:max-w-md">
            <div class="bg-gray-200 text-gray-700 p-3 rounded-lg">
                <div class="flex items-center space-x-2">
                    <div class="flex space-x-1">
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.1s"></div>
                        <div class="w-2 h-2 bg-gray-400 rounded-full animate-bounce" style="animation-delay: 0.2s"></div>
                    </div>
                    <span class="text-sm">${escapeHtml(texto)}</span>
                </div>
            </div>
        </div>
    `;
    
    messagesArea.appendChild(thinkingDiv);
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Remove thinking message
function removeThinkingMessage() {
    const thinkingMessage = document.getElementById('thinking-message');
    if (thinkingMessage) {
        thinkingMessage.remove();
    }
}

// Parseia eventos SSE de um buffer acumulado (mesmo padrão usado no chat da
// apostila — app/Views/components/apostila_ia_chat_script.php), adaptado pro
// formato mais simples que StudentController::sendMessageStream() emite
// (sem "event:", só "data:" por bloco).
function parsearEventosSseChat(buffer) {
    const eventos = [];
    const blocos = buffer.split('\n\n');
    const restante = blocos.pop() || '';
    blocos.forEach(function (bloco) {
        bloco.split('\n').forEach(function (linha) {
            if (linha.indexOf('data:') === 0) {
                const dados = linha.slice(5).trim();
                if (dados) {
                    eventos.push(dados);
                }
            }
        });
    });
    return { eventos: eventos, restante: restante };
}

// Cria a bolha de resposta da IA vazia, com avatar, pra ir preenchendo token
// a token durante o streaming. Retorna as referências que o loop de leitura
// do stream precisa (contentDiv pra reatribuir o HTML acumulado a cada chunk).
function criarBubbleIaStreamChat() {
    const messagesArea = document.getElementById('messagesArea');

    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex items-end gap-2 justify-start';
    messageDiv.appendChild(criarAvatarIaChat());

    const messageContent = document.createElement('div');
    messageContent.className = 'bg-gray-100 text-gray-900 p-4 rounded-2xl rounded-bl-sm max-w-3xl shadow-sm';

    const contentDiv = document.createElement('div');
    contentDiv.className = 'ai-message-content';
    contentDiv.innerHTML = '<span class="tudinha-cursor-piscando">▍</span>';

    messageContent.appendChild(contentDiv);
    messageDiv.appendChild(messageContent);
    messagesArea.appendChild(messageDiv);
    messagesArea.scrollTop = messagesArea.scrollHeight;

    return { messageDiv: messageDiv, contentDiv: contentDiv };
}

// Envia a mensagem de texto do aluno via streaming real (SSE), preenchendo a
// bolha da IA token a token em vez de esperar a resposta inteira. Ao final,
// recarrega a conversa (loadMessages) pra pegar a versão canônica já
// sanitizada e com as sugestões extraídas pelo backend.
async function enviarMensagemStream(userMessage, submitButton, originalButtonHtml) {
    displayUserMessage(userMessage);

    const streamState = criarBubbleIaStreamChat();
    let textoAcumulado = '';
    let chunksDesdeUltimoRender = 0;
    let recebeuDone = false;
    let mensagemErro = '';

    const csrfToken = document.getElementById('csrfTokenInput')?.value
        || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
        || '';

    const formData = new FormData();
    formData.append('conversa_id', currentConversationId);
    formData.append('mensagem', userMessage);
    formData.append('_token', csrfToken);

    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 240000);

    try {
        const res = await fetch('<?= URL ?>/chat/mensagem-stream', {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'text/event-stream' },
            signal: controller.signal
        });

        if (!res.ok || !res.body) {
            throw new Error('Erro ' + res.status + ' ao conversar com ' + IA_NAME + '.');
        }

        const reader = res.body.getReader();
        const decoder = new TextDecoder();
        let buffer = '';

        while (true) {
            const parte = await reader.read();
            if (parte.done) {
                break;
            }
            buffer += decoder.decode(parte.value, { stream: true });
            const parsed = parsearEventosSseChat(buffer);
            buffer = parsed.restante;

            for (const dados of parsed.eventos) {
                let payload;
                try {
                    payload = JSON.parse(dados);
                } catch (e) {
                    continue;
                }

                if (payload.error) {
                    mensagemErro = payload.error;
                    continue;
                }

                if (payload.pediu_imagem) {
                    // Backend detectou pedido de geração de imagem — isso é síncrono
                    // (não passa pelo streaming), cai pro POST clássico /chat/mensagem.
                    streamState.messageDiv.remove();
                    clearTimeout(timeoutId);
                    enviarMensagemSincrona(userMessage, submitButton, originalButtonHtml, true, `Gerando sua imagem, aguarde...`);
                    return;
                }

                if (payload.pediu_flashcard) {
                    // Backend detectou pedido de flashcard — enfileira job assíncrono,
                    // cai pro POST clássico /chat/mensagem (que devolve o job_id e
                    // inicia o polling).
                    streamState.messageDiv.remove();
                    clearTimeout(timeoutId);
                    enviarMensagemSincrona(userMessage, submitButton, originalButtonHtml, true, `Criando seus flashcards, aguarde...`);
                    return;
                }

                if (payload.chunk) {
                    textoAcumulado += payload.chunk;
                    chunksDesdeUltimoRender++;
                    streamState.contentDiv.innerHTML = textoAcumulado + '<span class="tudinha-cursor-piscando">▍</span>';
                    if (chunksDesdeUltimoRender >= 3) {
                        chunksDesdeUltimoRender = 0;
                        renderMathJax();
                    }
                    messagesArea_scrollToBottom();
                }

                if (payload.done) {
                    recebeuDone = true;
                }
            }
        }

        if (mensagemErro) {
            streamState.messageDiv.remove();
            removeThinkingMessage();
            if (mensagemErro === 'Token inválido') {
                showNotification('Sessão atualizada. Tente enviar a mensagem novamente.', 'error');
            } else {
                showNotification(mensagemErro, 'error');
            }
            return;
        }

        if (!recebeuDone) {
            streamState.messageDiv.remove();
            showNotification('A conexão foi interrompida antes da resposta terminar. Tente novamente.', 'error');
            return;
        }

        // Recarrega pra pegar a versão canônica (sanitizada + sugestões extraídas) —
        // silencioso pra não apagar a tela com o spinner de "Carregando mensagens..."
        // logo depois do streaming ter acabado de mostrar o conteúdo certinho.
        loadMessages(currentConversationId, true);
    } catch (error) {
        streamState.messageDiv.remove();
        if (error.name === 'AbortError') {
            showNotification('A resposta está demorando muito. Verifique sua conexão ou tente novamente.', 'error');
        } else {
            showNotification('Erro de conexão: ' + (error.message || 'Tente novamente.'), 'error');
        }
        console.error('Erro no streaming da Tudinha:', error);
    } finally {
        clearTimeout(timeoutId);
        submitButton.innerHTML = originalButtonHtml;
        submitButton.disabled = false;
    }
}

function messagesArea_scrollToBottom() {
    const messagesArea = document.getElementById('messagesArea');
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Envia a mensagem pelo POST clássico /chat/mensagem (síncrono) — usado como
// fallback pra navegador sem suporte a streams, e quando o backend detecta
// (via evento pediu_imagem no stream) que a mensagem é um pedido de geração
// de imagem, que é síncrona e tem fluxo de crédito/fallback próprio.
function enviarMensagemSincrona(userMessage, submitButton, originalButtonHtml, jaExibiuMensagemUsuario, textoDigitando) {
    if (!jaExibiuMensagemUsuario) {
        displayUserMessage(userMessage);
    }
    displayThinkingMessage(textoDigitando);
    // O caller pode ter reabilitado o botão (ex.: finally de outro fluxo que já
    // encerrou antes deste fetch começar) — trava de novo pra evitar reenvio duplo.
    if (submitButton) {
        submitButton.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mx-auto"></div>';
        submitButton.disabled = true;
    }

    const formData = new FormData();
    formData.append('conversa_id', currentConversationId);
    formData.append('mensagem', userMessage);
    formData.append('_token', document.getElementById('csrfTokenInput')?.value || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    fetchWithRetry('<?= URL ?>/chat/mensagem', { method: 'POST', body: formData }, 1)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.flashcard_job_id) {
                // Job enfileirado — a bolha "Criando seus flashcards..." fica na tela
                // até o polling terminar e loadMessages() substituir pela versão real.
                new AIJobPoller(data.flashcard_job_id, {
                    onDone: function (result) {
                        const deckId = result && result.deck_id;
                        if (!deckId) {
                            removeThinkingMessage();
                            showNotification('Flashcards prontos, mas não consegui atualizar a conversa.', 'error');
                            return;
                        }
                        const fd = new FormData();
                        fd.append('conversa_id', currentConversationId);
                        fd.append('deck_id', deckId);
                        fd.append('topico', data.topico || '');
                        fd.append('_token', document.getElementById('csrfTokenInput')?.value || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
                        fetch('<?= URL ?>/chat/flashcard-concluido', { method: 'POST', body: fd })
                            .then(r => r.json())
                            .finally(() => loadMessages(currentConversationId, true));
                    },
                    onFailed: function (err) {
                        removeThinkingMessage();
                        showNotification('Não consegui gerar os flashcards agora. Tente novamente.', 'error');
                    }
                });
            } else if (data.success) {
                loadMessages(currentConversationId, true);
            } else {
                removeThinkingMessage();
                showNotification(data.error || 'Erro ao enviar mensagem', 'error');
            }
        })
        .catch(error => {
            removeThinkingMessage();
            showNotification('Erro de conexão: ' + (error.message || 'Tente novamente.'), 'error');
        })
        .finally(() => {
            if (submitButton) {
                submitButton.innerHTML = originalButtonHtml;
                submitButton.disabled = false;
            }
        });
}

// Send message
document.getElementById('messageForm').addEventListener('submit', function(e) {
    e.preventDefault();

    if (!currentConversationId) {
        showNotification('Selecione uma conversa primeiro', 'error');
        return;
    }

    const messageText = document.getElementById('messageText');
    const userMessage = messageText.value.trim();

    // Se tem imagem anexada (colada ou escolhida no menu), envia como mensagem
    // com imagem — o texto digitado (se houver) vira a legenda, junto na mesma mensagem.
    if (pendingImageFile) {
        const fileToSend = pendingImageFile;
        removePendingAttachment();
        sendImageMessage(fileToSend);
        return;
    }

    if (!userMessage) {
        showNotification('Digite uma mensagem', 'error');
        return;
    }

    // Limpar o campo de texto imediatamente
    messageText.value = '';
    updateInputState();

    // Mostrar loading no botão
    const submitButton = this.querySelector('button[type="submit"]');
    const originalText = submitButton.innerHTML;
    submitButton.innerHTML = '<div class="animate-spin rounded-full h-4 w-4 border-b-2 border-white mx-auto"></div>';
    submitButton.disabled = true;

    // Fallback pra navegadores muito antigos sem suporte a streams
    if (!window.fetch || !window.ReadableStream) {
        enviarMensagemSincrona(userMessage, submitButton, originalText, false);
        return;
    }

    enviarMensagemStream(userMessage, submitButton, originalText);
});

// Add message to chat
function addMessageToChat(message, isUser) {
    const messagesArea = document.getElementById('messagesArea');
    const messageDiv = document.createElement('div');
    
    if (isUser) {
        messageDiv.className = 'flex justify-end';
        messageDiv.innerHTML = `
            <div class="bg-gradient-to-r from-purple-600 to-purple-500 text-white p-3 rounded-lg max-w-xs">
                <p>${message}</p>
                <span class="text-xs opacity-75">${new Date().toLocaleTimeString()}</span>
            </div>
        `;
    } else {
        messageDiv.className = 'flex justify-start';
        messageDiv.innerHTML = `
            <div class="bg-gray-100 text-gray-900 p-3 rounded-lg max-w-xs">
                <p>${message}</p>
                <span class="text-xs opacity-75">${new Date().toLocaleTimeString()}</span>
            </div>
        `;
    }
    
    messagesArea.appendChild(messageDiv);
    messagesArea.scrollTop = messagesArea.scrollHeight;
}

// Anexo de imagem pendente: fica preso ao composer (preview acima do textarea),
// não abre modal nem crop — o aluno escreve o texto que quiser e envia junto
// (Enter ou botão enviar), mesmo fluxo de uma mensagem normal.
let pendingImageFile = null;

function setPendingImage(file) {
    if (!file) return;
    pendingImageFile = file;
    const reader = new FileReader();
    reader.onload = function (e) {
        document.getElementById('pendingAttachmentImg').src = e.target.result;
        document.getElementById('pendingAttachmentPreview').classList.remove('hidden');
    };
    reader.readAsDataURL(file);
    document.getElementById('messageText').focus();
}

function removePendingAttachment() {
    pendingImageFile = null;
    document.getElementById('pendingAttachmentImg').src = '';
    document.getElementById('pendingAttachmentPreview').classList.add('hidden');
}

// Funções do modal de visualização de imagem
// Imagem gerada pela IA vem embutida como <img> dentro do texto da resposta
// (não passa pelo campo estruturado mensagem.image_url) — clique delegado
// pra abrir o mesmo modal de visualização usado nas imagens enviadas.
document.getElementById('messagesArea').addEventListener('click', function (e) {
    var img = e.target.closest('.ai-message-content img');
    if (img) {
        openImageViewModal(img.src, 'Imagem gerada pela Tudinha');
    }
});

function openImageViewModal(imageUrl, caption = '') {
    document.getElementById('viewImage').src = imageUrl;
    document.getElementById('imageCaption').textContent = caption;
    document.getElementById('imageViewModal').classList.remove('hidden');
    
    // Prevenir scroll do body
    document.body.style.overflow = 'hidden';
}

function closeImageViewModal() {
    document.getElementById('imageViewModal').classList.add('hidden');
    document.getElementById('viewImage').src = '';
    document.getElementById('imageCaption').textContent = '';
    
    // Restaurar scroll do body
    document.body.style.overflow = 'auto';
}

// Fechar modal com ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeImageViewModal();
    }
});

function sendImageMessage(imageData) {
    const messagesArea = document.getElementById('messagesArea');
    let imageUrl = '';
    // Legenda opcional escrita junto do anexo — some pra fora daqui, mas segue
    // pro fluxo de envio abaixo pra virar a mensagem do aluno (não fica vazia).
    const legendaEl = document.getElementById('messageText');
    const legenda = legendaEl ? legendaEl.value.trim() : '';
    if (legendaEl) {
        legendaEl.value = '';
        updateInputState();
    }

    // Mostrar loading enquanto faz upload
    showNotification('Enviando imagem...');

    // First upload the image to get URL
    const formData = new FormData();
    formData.append('imagem', imageData, 'image.jpg');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    
    // Upload image
    fetch('<?= URL ?>/chat/upload-imagem', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(uploadResult => {
        if (uploadResult.success) {
            imageUrl = uploadResult.image_url;
            const displayImageUrl = chatImageSrc(imageUrl);
            
            // Exibir imagem imediatamente no chat (usar rota ver-imagem para não gerar log "Rota não encontrada")
            const now = new Date();
            const time = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
            
            const messageDiv = document.createElement('div');
            messageDiv.className = 'flex justify-end mb-4';
            messageDiv.innerHTML = `
                <div class="max-w-xs lg:max-w-md">
                    <div class="bg-gradient-to-r from-purple-600 to-purple-500 text-white p-3 rounded-lg">
                        <div class="mb-2">
                            <img src="${displayImageUrl.replace(/"/g, '&quot;')}"
                                 alt="Imagem enviada"
                                 class="max-w-full max-h-48 rounded-lg cursor-pointer hover:opacity-90 transition-opacity shadow-sm"
                                 onclick="openImageViewModal(this.src, 'Imagem enviada em ${time.replace(/'/g, "\\'")}')">
                        </div>
                        ${legenda ? `<p class="text-sm whitespace-pre-wrap">${escapeHtml(legenda)}</p>` : ''}
                    </div>
                    <p class="text-xs text-gray-500 mt-1 text-right">${time}</p>
                </div>
            `;

            messagesArea.appendChild(messageDiv);
            messagesArea.scrollTop = messagesArea.scrollHeight;

            // Mostrar mensagem de pensando...
            displayThinkingMessage();

            // Now send message with image URL
            const messageFormData = new FormData();
            messageFormData.append('conversa_id', currentConversationId);
            messageFormData.append('mensagem', legenda);
            messageFormData.append('tipo', 'imagem');
            messageFormData.append('image_url', uploadResult.image_url);
            messageFormData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
            
            // Send message with image URL
            return fetch('<?= URL ?>/chat/mensagem', {
                method: 'POST',
                body: messageFormData
            });
        } else {
            if (uploadResult.error === 'Token inválido' && uploadResult.csrf_token) {
                updateCsrfTokenInPage(uploadResult.csrf_token);
                showNotification('Sessão atualizada. Tente enviar a imagem novamente.', 'error');
                return Promise.resolve({ _tokenRefreshed: true });
            }
            throw new Error(uploadResult.error || 'Erro no upload da imagem');
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Imagem enviada! Aguardando resposta...');
            
            // Fazer polling para verificar quando a resposta da IA chegar
            let pollCount = 0;
            const maxPolls = 75; // 75 tentativas de 2s = 150 segundos (2.5 minutos) máximo
            
            const checkForResponse = setInterval(() => {
                pollCount++;
                
                // Buscar mensagens atualizadas
                fetch(`<?= URL ?>/chat/mensagens?conversa_id=${currentConversationId}&t=${Date.now()}`)
                    .then(response => response.json())
                    .then(responseData => {
                        if (responseData.success && responseData.mensagens && responseData.mensagens.length > 0) {
                            // Verificar se há uma nova mensagem da IA
                            const lastMsg = responseData.mensagens[responseData.mensagens.length - 1];
                            if (lastMsg.is_ia == 1 || lastMsg.is_ia === true) {
                                // Resposta da IA chegou!
                                clearInterval(checkForResponse);
                                removeThinkingMessage();
                                displayMessages(responseData.mensagens);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao verificar resposta:', error);
                    });
                
                // Se excedeu o limite de tentativas, parar e recarregar
                if (pollCount >= maxPolls) {
                    clearInterval(checkForResponse);
                    removeThinkingMessage();
                    loadMessages(currentConversationId);
                }
            }, 2000); // Verificar a cada 2 segundos
            
            // Limpar intervalo quando sair da página
            window.addEventListener('beforeunload', () => clearInterval(checkForResponse));
        } else {
            removeThinkingMessage();
            if (data._tokenRefreshed) return;
            if (data.error === 'Token inválido' && data.csrf_token) {
                updateCsrfTokenInPage(data.csrf_token);
                showNotification('Sessão atualizada. Tente enviar a imagem novamente.', 'error');
            } else {
                showNotification(data.error || 'Erro ao enviar imagem', 'error');
            }
        }
    })
    .catch(error => {
        removeThinkingMessage();
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Envia um PDF anexado (mesmo padrão de sendImageMessage, sem crop — só
// upload direto + mensagem com tipo='pdf'). Extração de texto acontece no
// backend (PdfTextExtractorService), assíncrona em relação a esta chamada.
function sendPdfMessage(file) {
    const messagesArea = document.getElementById('messagesArea');
    const legendaEl = document.getElementById('messageText');
    const legenda = legendaEl ? legendaEl.value.trim() : '';
    if (legendaEl) {
        legendaEl.value = '';
        updateInputState();
    }

    showNotification('Enviando PDF...');

    const formData = new FormData();
    formData.append('pdf', file, file.name || 'documento.pdf');
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

    fetch('<?= URL ?>/chat/upload-pdf', { method: 'POST', body: formData })
        .then(response => response.json())
        .then(uploadResult => {
            if (uploadResult.success) {
                const now = new Date();
                const time = now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                const nomeArquivo = file.name || 'documento.pdf';
                const tamanhoKb = Math.max(1, Math.round(file.size / 1024));

                const messageDiv = document.createElement('div');
                messageDiv.className = 'flex justify-end mb-4';
                messageDiv.innerHTML = `
                    <div class="max-w-xs lg:max-w-md">
                        <div class="bg-gradient-to-r from-purple-600 to-purple-500 text-white p-3 rounded-lg">
                            <div class="flex items-center gap-2 mb-1">
                                <svg class="w-6 h-6 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 2v6h6"></path></svg>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium truncate">${escapeHtml(nomeArquivo)}</p>
                                    <p class="text-xs opacity-75">${tamanhoKb} KB</p>
                                </div>
                            </div>
                            ${legenda ? `<p class="text-sm whitespace-pre-wrap">${escapeHtml(legenda)}</p>` : ''}
                        </div>
                        <p class="text-xs text-gray-500 mt-1 text-right">${time}</p>
                    </div>
                `;
                messagesArea.appendChild(messageDiv);
                messagesArea.scrollTop = messagesArea.scrollHeight;

                displayThinkingMessage();

                const messageFormData = new FormData();
                messageFormData.append('conversa_id', currentConversationId);
                messageFormData.append('mensagem', legenda);
                messageFormData.append('tipo', 'pdf');
                messageFormData.append('image_url', uploadResult.file_url);
                messageFormData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');

                return fetch('<?= URL ?>/chat/mensagem', { method: 'POST', body: messageFormData });
            }
            if (uploadResult.error === 'Token inválido' && uploadResult.csrf_token) {
                updateCsrfTokenInPage(uploadResult.csrf_token);
                showNotification('Sessão atualizada. Tente enviar o PDF novamente.', 'error');
                return Promise.resolve({ _tokenRefreshed: true });
            }
            throw new Error(uploadResult.error || 'Erro no upload do PDF');
        })
        .then(response => (response && response.json) ? response.json() : response)
        .then(data => {
            if (!data || data._tokenRefreshed) return;
            if (data.success) {
                loadMessages(currentConversationId, true);
            } else {
                removeThinkingMessage();
                showNotification(data.error || 'Erro ao enviar PDF', 'error');
            }
        })
        .catch(error => {
            removeThinkingMessage();
            showNotification('Erro de conexão', 'error');
            console.error('Error:', error);
        });
}

// Menu de anexos ("+"): Tirar Foto / Enviar Imagem / Enviar PDF / Gravar Áudio
function toggleAttachMenu() {
    document.getElementById('attachMenu').classList.toggle('hidden');
}

function closeAttachMenu() {
    document.getElementById('attachMenu').classList.add('hidden');
}

// Fecha o menu ao clicar fora
document.addEventListener('click', function (e) {
    const menu = document.getElementById('attachMenu');
    const btn = document.getElementById('attachMenuBtn');
    if (!menu || menu.classList.contains('hidden')) return;
    if (!menu.contains(e.target) && e.target !== btn && !btn.contains(e.target)) {
        closeAttachMenu();
    }
});

function attachMenuAction(tipo) {
    closeAttachMenu();
    if (tipo === 'foto') {
        document.getElementById('attachFotoInput').click();
    } else if (tipo === 'imagem') {
        document.getElementById('attachImagemInput').click();
    } else if (tipo === 'pdf') {
        document.getElementById('attachPdfInput').click();
    } else if (tipo === 'audio') {
        toggleAudioTranscription();
    }
}

// Arquivo escolhido em um dos inputs ocultos do menu de anexos
function onAttachFileSelected(inputEl, tipo) {
    const file = inputEl.files && inputEl.files[0];
    if (!file) {
        return;
    }
    if (tipo === 'imagem') {
        // Fica como anexo pendente no composer — aluno pode escrever algo
        // pra complementar antes de enviar.
        setPendingImage(file);
    } else if (tipo === 'pdf') {
        sendPdfMessage(file);
    }
    inputEl.value = '';
}

// Modo de voz em tempo real (Realtime API) — WebRTC direto do navegador pra
// OpenAI, usando um client secret efêmero mintado pelo backend (a chave
// mestra nunca sai do servidor). Sem servidor WebSocket próprio: o áudio
// flui navegador↔OpenAI, o PHP só participa do handshake inicial.
let voicePc = null;
let voiceStream = null;
let voiceDc = null;
let voiceTurnos = [];
let voiceTimeoutId = null;
const VOICE_DURACAO_MAXIMA_MS = 10 * 60 * 1000; // 10 minutos

async function abrirModoVoz() {
    if (!currentConversationId) {
        showNotification('Selecione uma conversa primeiro', 'error');
        return;
    }
    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia || !window.RTCPeerConnection) {
        showNotification('Seu navegador não suporta o modo de voz em tempo real', 'error');
        return;
    }

    const overlay = document.getElementById('voiceModeOverlay');
    const statusEl = document.getElementById('voiceModeStatus');
    const captionEl = document.getElementById('voiceModeCaption');
    overlay.classList.remove('hidden');
    statusEl.textContent = 'Conectando...';
    captionEl.textContent = '';
    voiceTurnos = [];

    try {
        const tokenResp = await fetch('<?= URL ?>/chat/voz-realtime/iniciar', {
            method: 'POST',
            body: (() => {
                const fd = new FormData();
                fd.append('conversa_id', currentConversationId);
                fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
                return fd;
            })()
        });
        const tokenData = await tokenResp.json();
        if (!tokenData.success) {
            throw new Error(tokenData.error || 'Não foi possível iniciar o modo de voz');
        }
        console.log('Sessão de voz criada, model:', tokenData.model, 'expires_at:', tokenData.expires_at);

        voiceStream = await navigator.mediaDevices.getUserMedia({ audio: true });

        voicePc = new RTCPeerConnection();
        voiceStream.getTracks().forEach(track => voicePc.addTrack(track, voiceStream));

        const audioEl = document.getElementById('voiceModeAudio');
        voicePc.ontrack = (event) => {
            audioEl.srcObject = event.streams[0];
        };

        voiceDc = voicePc.createDataChannel('oai-events');
        voiceDc.addEventListener('message', onVoiceDataChannelMessage);
        voiceDc.addEventListener('open', () => {
            statusEl.textContent = 'Pode falar!';
        });

        const offer = await voicePc.createOffer();
        await voicePc.setLocalDescription(offer);

        // Endpoint "calls" (par do /v1/realtime/client_secrets usado pra mintar o
        // token) — pode variar conforme a versão da Realtime API; se a OpenAI
        // mudar isso, o console (log acima) mostra o HTTP status/corpo real do erro.
        const sdpResp = await fetch(`https://api.openai.com/v1/realtime/calls?model=${encodeURIComponent(tokenData.model)}`, {
            method: 'POST',
            body: offer.sdp,
            headers: {
                'Authorization': `Bearer ${tokenData.client_secret}`,
                'Content-Type': 'application/sdp'
            }
        });
        if (!sdpResp.ok) {
            const corpoErro = await sdpResp.text().catch(() => '');
            console.error('Falha no handshake WebRTC com a OpenAI Realtime API:', sdpResp.status, corpoErro);
            throw new Error(`Falha ao conectar com a Tudinha (voz) — HTTP ${sdpResp.status}. Veja o console para detalhes.`);
        }
        const answerSdp = await sdpResp.text();
        await voicePc.setRemoteDescription({ type: 'answer', sdp: answerSdp });

        voiceTimeoutId = setTimeout(() => {
            showNotification('A chamada de voz atingiu o tempo máximo e foi encerrada.', 'error');
            encerrarModoVoz();
        }, VOICE_DURACAO_MAXIMA_MS);
    } catch (error) {
        console.error('Erro no modo de voz:', error);
        showNotification(error.message || 'Erro ao iniciar o modo de voz', 'error');
        encerrarModoVoz();
    }
}

function onVoiceDataChannelMessage(event) {
    let payload;
    try {
        payload = JSON.parse(event.data);
    } catch (e) {
        return;
    }

    const avatarEl = document.getElementById('voiceModeAvatar');
    const statusEl = document.getElementById('voiceModeStatus');
    const captionEl = document.getElementById('voiceModeCaption');

    if (payload.type === 'input_audio_buffer.speech_started') {
        statusEl.textContent = 'Ouvindo...';
        avatarEl.classList.remove('voice-speaking');
    } else if (payload.type === 'response.audio_transcript.delta' && payload.delta) {
        statusEl.textContent = 'Tudinha está falando...';
        avatarEl.classList.add('voice-speaking');
        captionEl.textContent = (captionEl.textContent || '') + payload.delta;
    } else if (payload.type === 'response.audio_transcript.done' && payload.transcript) {
        avatarEl.classList.remove('voice-speaking');
        captionEl.textContent = payload.transcript;
        voiceTurnos.push({ role: 'ia', texto: payload.transcript });
    } else if (payload.type === 'conversation.item.input_audio_transcription.completed' && payload.transcript) {
        voiceTurnos.push({ role: 'aluno', texto: payload.transcript });
    } else if (payload.type === 'response.done') {
        statusEl.textContent = 'Pode falar!';
        avatarEl.classList.remove('voice-speaking');
    }
}

function encerrarModoVoz() {
    if (voiceTimeoutId) {
        clearTimeout(voiceTimeoutId);
        voiceTimeoutId = null;
    }
    if (voiceDc) {
        voiceDc.close();
        voiceDc = null;
    }
    if (voicePc) {
        voicePc.close();
        voicePc = null;
    }
    if (voiceStream) {
        voiceStream.getTracks().forEach(track => track.stop());
        voiceStream = null;
    }
    document.getElementById('voiceModeOverlay').classList.add('hidden');
    document.getElementById('voiceModeAvatar').classList.remove('voice-speaking');

    if (voiceTurnos.length > 0 && currentConversationId) {
        const fd = new FormData();
        fd.append('conversa_id', currentConversationId);
        fd.append('turnos', JSON.stringify(voiceTurnos));
        fd.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
        fetch('<?= URL ?>/chat/voz-realtime/salvar-transcricao', { method: 'POST', body: fd })
            .then(() => loadMessages(currentConversationId, true))
            .catch(err => console.error('Erro ao salvar transcrição da voz:', err));
    }
    voiceTurnos = [];
}

// Arrastar e soltar arquivo direto no chat
(function setupDragAndDrop() {
    const area = document.getElementById('messagesArea');
    if (!area) return;
    let dragCounter = 0;

    area.addEventListener('dragover', function (e) {
        e.preventDefault();
    });
    area.addEventListener('dragenter', function (e) {
        e.preventDefault();
        dragCounter++;
        area.classList.add('chat-drop-highlight');
    });
    area.addEventListener('dragleave', function (e) {
        e.preventDefault();
        dragCounter = Math.max(0, dragCounter - 1);
        if (dragCounter === 0) {
            area.classList.remove('chat-drop-highlight');
        }
    });
    area.addEventListener('drop', function (e) {
        e.preventDefault();
        dragCounter = 0;
        area.classList.remove('chat-drop-highlight');
        if (!currentConversationId) {
            showNotification('Selecione uma conversa primeiro', 'error');
            return;
        }
        const file = e.dataTransfer.files && e.dataTransfer.files[0];
        if (!file) return;
        if (file.type === 'application/pdf') {
            sendPdfMessage(file);
        } else if (file.type.startsWith('image/')) {
            setPendingImage(file);
        } else {
            showNotification('Tipo de arquivo não suportado. Envie uma imagem ou PDF.', 'error');
        }
    });
})();

// MODO 2: Implementação completa de gravação de áudio
async function startAudioRecording() {
    try {
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            throw new Error('Seu navegador não suporta gravação de áudio');
        }
        
        const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
        currentStream = stream;
        
        const supportedMimeTypes = ['audio/webm;codecs=opus', 'audio/webm', 'audio/mp4', 'audio/mpeg', 'audio/wav'];
        let selectedMimeType = 'audio/webm';
        for (const mimeType of supportedMimeTypes) {
            if (MediaRecorder.isTypeSupported(mimeType)) {
                selectedMimeType = mimeType;
                break;
            }
        }
        
        mediaRecorder = new MediaRecorder(stream, { mimeType: selectedMimeType, audioBitsPerSecond: 128000 });
        audioChunks = [];
        
        mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) audioChunks.push(event.data);
        };
        
        mediaRecorder.onstop = async () => {
            if (audioChunks.length === 0) {
                stream.getTracks().forEach(track => track.stop());
                isRecording = false;
                updateMicButton(false);
                return;
            }
            
            const mimeType = (mediaRecorder.mimeType || 'audio/webm').split(';')[0];
            const audioBlob = new Blob(audioChunks, { type: mimeType });
            
            // Transcrever e enviar automaticamente
            await sendAudioMessage(audioBlob, mimeType, true);
            
            stream.getTracks().forEach(track => track.stop());
            isRecording = false;
            updateMicButton(false);
        };
        
        mediaRecorder.start(1000);
        isRecording = true;
        updateMicButton(true);
        showNotification('Gravando... Clique em "Parar e transcrever" quando terminar', 'info');
        
    } catch (error) {
        console.error('Erro ao gravar:', error);
        showNotification('Erro: ' + error.message, 'error');
        isRecording = false;
        updateMicButton(false);
    }
}

function stopAudioRecording() {
    if (mediaRecorder && mediaRecorder.state === 'recording') {
        mediaRecorder.stop();
    }
}


// Função para enviar áudio (transcrever e enviar)
async function sendAudioMessage(audioBlob, mimeType, autoSend) {
    const mimeToExt = {
        'audio/webm': 'webm', 'audio/mp3': 'mp3', 'audio/mpeg': 'mp3',
        'audio/wav': 'wav', 'audio/ogg': 'ogg', 'audio/oga': 'oga',
        'audio/flac': 'flac', 'audio/m4a': 'm4a', 'audio/mp4': 'm4a', 'audio/mpga': 'mpga'
    };
    
    const extension = mimeToExt[mimeType] || 'webm';
    const filename = `audio.${extension}`;
    
    const formData = new FormData();
    formData.append('audio', audioBlob, filename);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    
    showNotification('Transcrevendo áudio...');
    
    try {
        const response = await fetch('<?= URL ?>/chat/voz-para-texto', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            const texto = data.texto.trim();
            console.log('Texto transcrito:', texto); // Debug
            
            if (!texto || texto.length < 2) {
                showNotification('Áudio muito curto ou sem fala detectada', 'error');
                return;
            }
            
            const messageText = document.getElementById('messageText');
            messageText.value = texto;
            updateInputState();
            
            if (autoSend && texto) {
                showNotification('Enviando mensagem...');
                setTimeout(() => {
                    // Enviar mensagem via form submit
                    const messageForm = document.getElementById('messageForm');
                    if (messageForm) {
                        messageForm.dispatchEvent(new Event('submit'));
                    }
                }, 500);
            } else {
                showNotification('Áudio transcrito com sucesso!');
            }
        } else {
            if (data.error === 'Token inválido' && data.csrf_token) {
                updateCsrfTokenInPage(data.csrf_token);
                showNotification('Sessão atualizada. Tente gravar o áudio novamente.', 'error');
            } else {
                showNotification(data.error || 'Erro ao transcrever áudio', 'error');
            }
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao transcrever áudio', 'error');
    }
}

// Função auxiliar de UI: estado do botão Gravar / Parar e transcrever
function updateMicButton(recording) {
    const btn = document.getElementById('micButton');
    const label = document.getElementById('micButtonLabel');
    const icon = document.getElementById('micButtonIcon');
    if (!btn) return;
    if (recording) {
        btn.classList.remove('mic-btn-idle');
        btn.classList.add('animate-pulse', 'text-red-600', 'bg-red-50', 'mic-btn-recording');
        btn.title = 'Gravando... Clique para parar e transcrever';
        if (label) label.textContent = 'Parar e transcrever';
        if (icon) icon.innerHTML = '<rect x="8" y="8" width="8" height="8" rx="1" stroke="currentColor" stroke-width="2" fill="currentColor"/>';
    } else {
        btn.classList.add('mic-btn-idle');
        btn.classList.remove('animate-pulse', 'text-red-600', 'bg-red-50', 'mic-btn-recording');
        btn.title = 'Clique para começar a gravar; clique de novo para parar e transcrever';
        if (label) label.textContent = 'Gravar';
        if (icon) icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"></path>';
    }
}

// Delete conversation
function deleteConversation(conversaId) {
    if (!confirm('Tem certeza que deseja excluir esta conversa? Ela será removida da sua lista, mas os dados serão mantidos para estatísticas.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('conversa_id', conversaId);
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    
    fetch('<?= URL ?>/chat/conversa/excluir', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Conversa excluída com sucesso!');
            
            // Remover a conversa da lista
            const conversationItem = document.querySelector(`[data-conversa-id="${conversaId}"]`);
            if (conversationItem) {
                conversationItem.remove();
            }
            
            // Se era a conversa ativa, limpar o chat
            if (currentConversationId == conversaId) {
                currentConversationId = null;
                document.getElementById('conversationTitle').textContent = 'Selecione uma conversa';
                document.getElementById('conversationSubject').textContent = 'Escolha uma conversa da lista ao lado para começar';
                const messageInput = document.getElementById('messageInput');
                if (messageInput) {
                    messageInput.classList.add('hidden');
                    messageInput.style.display = 'none';
                    messageInput.style.visibility = 'hidden';
                    messageInput.style.opacity = '0';
                    messageInput.style.height = '0';
                    messageInput.style.padding = '0';
                    messageInput.style.margin = '0';
                    messageInput.style.overflow = 'hidden';
                    messageInput.style.pointerEvents = 'none';
                }
                
                const messagesArea = document.getElementById('messagesArea');
                messagesArea.innerHTML = `
                    <div class="flex items-center justify-center h-full text-gray-500">
                        <div class="text-center">
                            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p class="text-lg font-medium">Bem-vindo ao Chat <?= htmlspecialchars($iaName) ?>!</p>
                            <p class="text-sm">Selecione uma conversa ou crie uma nova para começar</p>
                        </div>
                    </div>
                `;
            }
            
            // Verificar se não há mais conversas
            const remainingConversations = document.querySelectorAll('.conversation-item');
            if (remainingConversations.length === 0) {
                const conversationsList = document.querySelector('#conversationsList');
                if (conversationsList) {
                    conversationsList.innerHTML = `
                        <div class="text-center py-8">
                            <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <p class="text-gray-500">Nenhuma conversa encontrada</p>
                            <p class="text-sm text-gray-400">Crie sua primeira conversa para começar</p>
                        </div>
                    `;
                }
            }
        } else {
            if (data.error === 'Token inválido' && data.csrf_token) {
                updateCsrfTokenInPage(data.csrf_token);
                showNotification('Sessão atualizada. Tente excluir novamente.', 'error');
            } else {
                showNotification(data.error || 'Erro ao excluir conversa', 'error');
            }
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Mensagem fixa quando o aluno vem dos flashcards ("Explicar com IA")
window.MENSAGEM_DUVIDA_FLASHCARDS = 'Marquei alguns flashcards como "não entendi". Pode me explicar? Tenho dúvidas sobre eles.';

function etdMontarMensagemPrefillJornada(titulo, materia) {
    var t = (titulo || '').trim();
    var m = (materia || '').trim();
    var base = 'Acabei de concluir a jornada';
    if (t) base += ' "' + t + '"';
    if (m) base += ' (' + m + ')';
    base += '. Pode explicar os principais conceitos e reforçar o que mais importa para eu consolidar o aprendizado?';
    return base;
}

function etdTituloConversaJornada(titulo) {
    var convTitulo = 'Jornada: ' + ((titulo || '').trim() || 'Revisão');
    if (convTitulo.length > 120) convTitulo = convTitulo.slice(0, 117) + '...';
    return convTitulo;
}

function etdAplicarMateriaModalJornada(materia) {
    var m = (materia || '').trim();
    if (!m) return;
    var materiaSelect = document.querySelector('#createConversationForm select[name="materia"]');
    if (!materiaSelect) return;
    for (var i = 0; i < materiaSelect.options.length; i++) {
        if (materiaSelect.options[i].value === m) {
            materiaSelect.selectedIndex = i;
            return;
        }
    }
}

// Inicializar estado do textarea e abrir chat para dúvidas (ex.: "Explicar com IA" nos flashcards)
document.addEventListener('DOMContentLoaded', function() {
    const messageText = document.getElementById('messageText');
    if (messageText) {
        updateInputState();
        messageText.addEventListener('input', function() {
            updateInputState();
        });
    }
    
    var fromFlashcards = (window.location.hash === '#flashcards-duvida');
    if (!fromFlashcards) {
        try {
            fromFlashcards = sessionStorage.getItem('chatDuvidaFlashcards') === '1';
            if (fromFlashcards) sessionStorage.removeItem('chatDuvidaFlashcards');
        } catch (e) {}
    }
    if (!fromFlashcards) {
        var paramsFc = new URLSearchParams(window.location.search);
        fromFlashcards = paramsFc.get('from') === 'flashcards';
    }
    if (fromFlashcards) {
        if (window.history && window.history.replaceState) {
            try { window.history.replaceState(null, '', window.location.pathname + window.location.search); } catch (e) {}
        }
        window.pendingFlashcardPrefill = window.MENSAGEM_DUVIDA_FLASHCARDS;
        var firstConversation = document.querySelector('[data-conversa-id]');
        if (firstConversation) {
            var id = firstConversation.getAttribute('data-conversa-id');
            if (id) loadConversation(parseInt(id, 10));
        } else {
            openCreateConversationModal();
            var titleInput = document.querySelector('#createConversationForm input[name="titulo"]');
            if (titleInput) titleInput.value = 'Dúvidas - Flashcards';
        }
        return;
    }

    var paramsJn = new URLSearchParams(window.location.search);
    var fromJornada = paramsJn.get('from') === 'jornada';
    if (fromJornada) {
        if (window.history && window.history.replaceState) {
            try {
                var u = new URL(window.location.href);
                u.searchParams.delete('from');
                window.history.replaceState(null, '', u.pathname + u.search + u.hash);
            } catch (e) {}
        }
        var jTitulo = '';
        var jMateria = '';
        try {
            var rawJn = sessionStorage.getItem('chatPrefillJornada');
            if (rawJn) {
                var jData = JSON.parse(rawJn);
                sessionStorage.removeItem('chatPrefillJornada');
                if (jData && typeof jData === 'object') {
                    jTitulo = String(jData.titulo || '');
                    jMateria = String(jData.materia || '');
                }
            }
        } catch (e) {
            try { sessionStorage.removeItem('chatPrefillJornada'); } catch (e2) {}
        }
        window.pendingChatPrefill = etdMontarMensagemPrefillJornada(jTitulo, jMateria);
        var firstConvJn = document.querySelector('[data-conversa-id]');
        if (firstConvJn) {
            var idJn = firstConvJn.getAttribute('data-conversa-id');
            if (idJn) loadConversation(parseInt(idJn, 10));
        } else {
            openCreateConversationModal();
            var titleInputJn = document.querySelector('#createConversationForm input[name="titulo"]');
            if (titleInputJn) titleInputJn.value = etdTituloConversaJornada(jTitulo);
            etdAplicarMateriaModalJornada(jMateria);
        }
    }
});

// Detectar paste de imagens
document.addEventListener('paste', function(e) {
    // Verificar se está na área de chat e se há uma conversa ativa
    if (!currentConversationId) return;
    
    // Verificar se há imagem no clipboard
    const items = e.clipboardData?.items;
    if (!items) return;
    
    for (let i = 0; i < items.length; i++) {
        const item = items[i];

        // Verificar se é uma imagem
        if (item.type.indexOf('image') !== -1) {
            e.preventDefault();
            const blob = item.getAsFile();
            const file = new File([blob], 'pasted-image.png', { type: blob.type || 'image/png' });
            setPendingImage(file);
            return;
        }
    }
});

// Função para gerar imagem da resposta da IA
async function gerarImagemDaResposta(mensagemId, conversaId, textoResposta) {
    try {
        if (!conversaId) {
            showNotification('Erro: ID da conversa não encontrado', 'error');
            return;
        }
        
        showNotification('Gerando imagem...', 'info');
        
        // Buscar mensagens da conversa para pegar a mensagem original do usuário
        let promptUsuario = '';
        try {
            const responseMsgs = await fetch(`<?= URL ?>/chat/mensagens?conversa_id=${conversaId}&t=${Date.now()}`);
            const dataMsgs = await responseMsgs.json();
            if (dataMsgs.success && dataMsgs.mensagens) {
                // Encontrar a mensagem do usuário que gerou esta resposta da IA
                const mensagemIA = dataMsgs.mensagens.find(m => m.id == mensagemId);
                if (mensagemIA) {
                    // Buscar a mensagem anterior do usuário
                    const indexIA = dataMsgs.mensagens.indexOf(mensagemIA);
                    if (indexIA > 0) {
                        const mensagemUsuario = dataMsgs.mensagens[indexIA - 1];
                        if (mensagemUsuario && !mensagemUsuario.is_ia) {
                            promptUsuario = mensagemUsuario.mensagem || '';
                        }
                    }
                }
            }
        } catch (e) {
            console.error('Erro ao buscar mensagens:', e);
        }
        
        // Se não encontrou a mensagem do usuário, usar o texto da resposta da IA
        if (!promptUsuario) {
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = textoResposta || '';
            promptUsuario = tempDiv.textContent || tempDiv.innerText || '';
        }
        
        // Construir prompt baseado na conversa
        const formData = new FormData();
        formData.append('prompt', promptUsuario);
        formData.append('conversa_id', conversaId);
        
        // Buscar token CSRF de input hidden ou meta tag
        // Priorizar o token do formulário principal do chat
        const csrfTokenInput = document.getElementById('csrfTokenInput') || document.querySelector('input[name="_token"]');
        const csrfToken = csrfTokenInput?.value || 
                         document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        if (!csrfToken) {
            console.error('Token CSRF não encontrado. Elementos disponíveis:', {
                csrfTokenInput: csrfTokenInput,
                allTokens: document.querySelectorAll('input[name="_token"]').length,
                metaToken: document.querySelector('meta[name="csrf-token"]')
            });
            showNotification('Erro: Token de segurança não encontrado. Por favor, recarregue a página.', 'error');
            return;
        }
        
        console.log('Token CSRF encontrado:', csrfToken.substring(0, 10) + '...');
        formData.append('_token', csrfToken);
        
        const response = await fetch('<?= URL ?>/chat/gerar-imagem', {
            method: 'POST',
            body: formData
        });
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não-JSON recebida:', text.substring(0, 500));
            showNotification('Erro: Resposta inválida do servidor', 'error');
            return;
        }
        
        // Verificar status da resposta
        if (!response.ok) {
            console.error('Erro HTTP:', response.status, response.statusText);
        }
        
        const data = await response.json();
        
        if (data.success && data.image_url) {
            // Recarregar mensagens para atualizar (a imagem já foi salva no backend)
            if (conversaId) {
                setTimeout(() => {
                    loadMessages(conversaId);
                }, 500);
            }
            
            showNotification('Imagem gerada com sucesso!', 'success');
        } else {
            console.error('Erro ao gerar imagem:', data);
            // Mostrar mensagem de erro mais amigável
            let errorMsg = data.error || 'Erro ao gerar imagem';
            if (errorMsg.includes('Could not resolve host') || errorMsg.includes('não foi possível conectar')) {
                errorMsg = 'Não foi possível conectar ao servidor de geração de imagens. Verifique sua conexão com a internet.';
            } else if (errorMsg.includes('SSL') || errorMsg.includes('certificado')) {
                errorMsg = 'Erro de segurança na conexão. Tente novamente.';
            } else if (errorMsg.includes('API key') || errorMsg.includes('chave')) {
                errorMsg = 'Chave de API não configurada. Entre em contato com o administrador.';
            }
            showNotification(errorMsg, 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro ao gerar imagem: ' + (error.message || 'Erro desconhecido'), 'error');
    }
}

// Função para ouvir resposta em áudio
async function playAudioResponse(mensagemId, texto) {
    try {
        // Remover tags HTML do texto para enviar apenas texto puro
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = texto;
        const textoLimpo = tempDiv.textContent || tempDiv.innerText || texto;
        
        if (!textoLimpo.trim()) {
            showNotification('Nenhum texto para converter em áudio', 'error');
            return;
        }
        
        showNotification('Gerando áudio...', 'info');
        
        // Buscar token CSRF de input hidden ou meta tag
        const csrfToken = document.querySelector('input[name="_token"]')?.value || 
                         document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        if (!csrfToken) {
            console.error('Token CSRF não encontrado');
            showNotification('Erro: Token de segurança não encontrado', 'error');
            return;
        }
        
        const formData = new FormData();
        formData.append('texto', textoLimpo);
        formData.append('_token', csrfToken);
        
        const response = await fetch('<?= URL ?>/chat/texto-para-voz', {
            method: 'POST',
            body: formData
        });
        
        // Verificar status da resposta
        if (!response.ok) {
            console.error('Erro HTTP:', response.status, response.statusText);
        }
        
        // Verificar se a resposta é JSON
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            const text = await response.text();
            console.error('Resposta não-JSON recebida:', text.substring(0, 500));
            showNotification('Erro: Resposta inválida do servidor', 'error');
            return;
        }
        
        const data = await response.json();
        
        if (data.success && data.audio_url) {
            // Reproduzir áudio
            const audio = new Audio(data.audio_url);
            audio.play().catch(error => {
                console.error('Erro ao reproduzir áudio:', error);
                showNotification('Erro ao reproduzir áudio', 'error');
            });
            
            audio.onended = () => {
                showNotification('Áudio finalizado', 'success');
            };
            
            audio.onerror = () => {
                showNotification('Erro ao carregar áudio', 'error');
            };
        } else {
            console.error('Erro ao gerar áudio:', data);
            showNotification(data.error || 'Erro ao gerar áudio', 'error');
        }
    } catch (error) {
        console.error('Erro ao gerar áudio:', error);
        showNotification('Erro ao gerar áudio: ' + (error.message || 'Erro desconhecido'), 'error');
    }
}

// Inicializar polling para atualização em tempo real
document.addEventListener('DOMContentLoaded', function() {
    startPolling();
    document.addEventListener('visibilitychange', handleChatVisibilityChange);
});

// Parar polling quando a página for fechada
window.addEventListener('beforeunload', function() {
    stopPolling();
    document.removeEventListener('visibilitychange', handleChatVisibilityChange);
});
</script>

<!-- MathJax para renderização de equações matemáticas -->
<script>
window.MathJax = {
    tex: {
        inlineMath: [['\\(', '\\)']],
        displayMath: [['\\[', '\\]']],
        processEscapes: true,
        processEnvironments: true
    },
    options: {
        ignoreHtmlClass: '.*',
        processHtmlClass: 'math-content'
    }
};
</script>
<script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
<script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js"></script>
<script>
// Função para renderizar MathJax após adicionar mensagens
function renderMathJax() {
    if (window.MathJax && window.MathJax.typesetPromise) {
        MathJax.typesetPromise().catch(function (err) {
            console.log('Erro ao renderizar MathJax:', err);
        });
    }
}

// Interceptar função de renderização de mensagens para incluir MathJax
const originalRenderMessage = renderMessage;
if (typeof renderMessage !== 'undefined') {
    renderMessage = function(mensagem) {
        originalRenderMessage(mensagem);
        // Renderizar MathJax após renderizar mensagem
        setTimeout(renderMathJax, 100);
    };
}

// Renderizar MathJax ao carregar mensagens
if (typeof loadMessages === 'function') {
    const originalLoadMessages = loadMessages;
    loadMessages = function(conversaId) {
        originalLoadMessages(conversaId).then(() => {
            setTimeout(renderMathJax, 500);
        });
    };
}

// Renderizar MathJax ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(renderMathJax, 1000);
    
    // Adicionar classe math-content aos containers de mensagens
    const messagesArea = document.getElementById('messagesArea');
    if (messagesArea) {
        messagesArea.classList.add('math-content');
    }
});

// Observer para renderizar MathJax quando novas mensagens forem adicionadas
if (typeof MutationObserver !== 'undefined') {
    const observer = new MutationObserver(function(mutations) {
        let shouldRender = false;
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length > 0) {
                mutation.addedNodes.forEach(function(node) {
                    if (node.nodeType === 1) { // Element node
                        const text = node.textContent || '';
                        if (text.includes('\\[') || text.includes('\\(') || text.includes('$$')) {
                            shouldRender = true;
                        }
                    }
                });
            }
        });
        if (shouldRender) {
            setTimeout(renderMathJax, 200);
        }
    });
    
    document.addEventListener('DOMContentLoaded', function() {
        const messagesArea = document.getElementById('messagesArea');
        if (messagesArea) {
            observer.observe(messagesArea, { childList: true, subtree: true });
        }
    });
}
</script>
