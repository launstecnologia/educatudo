// Exemplo de JavaScript para renderizar mensagens formatadas do Chat Tudinha
// Este código deve ser adicionado ao arquivo app/Views/student/chat.php

<script>
// Função para carregar mensagens de uma conversa
async function loadMessages(conversaId) {
    try {
        const response = await fetch(`<?= URL ?>/chat/mensagens?conversa_id=${conversaId}`);
        const data = await response.json();
        
        if (data.success) {
            renderMessages(data.mensagens);
        } else {
            console.error('Erro ao carregar mensagens:', data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

// Função para renderizar mensagens no chat
function renderMessages(mensagens) {
    const messagesContainer = document.getElementById('messagesContainer');
    messagesContainer.innerHTML = '';
    
    mensagens.forEach(mensagem => {
        const messageElement = createMessageElement(mensagem);
        messagesContainer.appendChild(messageElement);
    });
    
    // Scroll para a última mensagem
    messagesContainer.scrollTop = messagesContainer.scrollHeight;
}

// Função para criar elemento de mensagem
function createMessageElement(mensagem) {
    const messageDiv = document.createElement('div');
    messageDiv.className = `flex ${mensagem.is_ia ? 'justify-start' : 'justify-end'} mb-4`;
    
    const messageContent = document.createElement('div');
    messageContent.className = `max-w-xs lg:max-w-md px-4 py-3 rounded-lg ${
        mensagem.is_ia 
            ? 'bg-gray-100 text-gray-800' 
            : 'bg-green-500 text-white'
    }`;
    
    // Se for mensagem da IA, usar conteúdo formatado
    if (mensagem.is_ia && mensagem.mensagem_formatada) {
        messageContent.innerHTML = mensagem.mensagem_formatada;
    } else {
        // Para mensagens do usuário, escapar HTML
        messageContent.textContent = mensagem.mensagem;
    }
    
    // Adicionar timestamp
    const timestamp = document.createElement('div');
    timestamp.className = `text-xs mt-2 ${
        mensagem.is_ia ? 'text-gray-500' : 'text-green-100'
    }`;
    timestamp.textContent = formatTimestamp(mensagem.created_at);
    
    const messageWrapper = document.createElement('div');
    messageWrapper.className = 'flex flex-col';
    messageWrapper.appendChild(messageContent);
    messageWrapper.appendChild(timestamp);
    
    messageDiv.appendChild(messageWrapper);
    return messageDiv;
}

// Função para formatar timestamp
function formatTimestamp(timestamp) {
    const date = new Date(timestamp);
    return date.toLocaleTimeString('pt-BR', { 
        hour: '2-digit', 
        minute: '2-digit' 
    });
}

// Função para enviar mensagem
async function sendMessage() {
    const form = document.getElementById('messageForm');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('<?= URL ?>/chat/mensagem', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Limpar campo de mensagem
            document.getElementById('messageText').value = '';
            
            // Recarregar mensagens
            const conversaId = document.getElementById('currentConversationId').value;
            await loadMessages(conversaId);
        } else {
            console.error('Erro ao enviar mensagem:', data.error);
            alert('Erro ao enviar mensagem: ' + data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
        alert('Erro ao enviar mensagem');
    }
}

// Event listeners
document.addEventListener('DOMContentLoaded', function() {
    // Form de envio de mensagem
    const messageForm = document.getElementById('messageForm');
    if (messageForm) {
        messageForm.addEventListener('submit', function(e) {
            e.preventDefault();
            sendMessage();
        });
    }
    
    // Form de criação de conversa
    const createForm = document.getElementById('createConversationForm');
    if (createForm) {
        createForm.addEventListener('submit', function(e) {
            e.preventDefault();
            createConversation();
        });
    }
});

// Função para criar nova conversa
async function createConversation() {
    const form = document.getElementById('createConversationForm');
    const formData = new FormData(form);
    
    try {
        const response = await fetch('<?= URL ?>/chat/conversa', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Fechar modal
            closeCreateConversationModal();
            
            // Recarregar lista de conversas
            loadConversations();
            
            // Abrir nova conversa
            openConversation(data.conversa_id);
        } else {
            console.error('Erro ao criar conversa:', data.error);
            alert('Erro ao criar conversa: ' + data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
        alert('Erro ao criar conversa');
    }
}

// Função para abrir conversa
function openConversation(conversaId) {
    // Atualizar ID da conversa atual
    document.getElementById('currentConversationId').value = conversaId;
    
    // Mostrar área de mensagens e input
    document.getElementById('messagesContainer').style.display = 'block';
    document.getElementById('messageInput').style.display = 'block';
    
    // Carregar mensagens
    loadMessages(conversaId);
    
    // Destacar conversa selecionada
    document.querySelectorAll('.conversation-item').forEach(item => {
        item.classList.remove('bg-green-50', 'border-green-200');
        item.classList.add('hover:bg-gray-50');
    });
    
    const selectedItem = document.querySelector(`[data-conversa-id="${conversaId}"]`);
    if (selectedItem) {
        selectedItem.classList.add('bg-green-50', 'border-green-200');
        selectedItem.classList.remove('hover:bg-gray-50');
    }
}

// Função para carregar conversas
async function loadConversations() {
    try {
        const response = await fetch('<?= URL ?>/chat/conversas');
        const data = await response.json();
        
        if (data.success) {
            renderConversations(data.conversas);
        } else {
            console.error('Erro ao carregar conversas:', data.error);
        }
    } catch (error) {
        console.error('Erro na requisição:', error);
    }
}

// Função para renderizar lista de conversas
function renderConversations(conversas) {
    const conversationsList = document.getElementById('conversationsList');
    conversationsList.innerHTML = '';
    
    conversas.forEach(conversa => {
        const conversationElement = createConversationElement(conversa);
        conversationsList.appendChild(conversationElement);
    });
}

// Função para criar elemento de conversa
function createConversationElement(conversa) {
    const conversationDiv = document.createElement('div');
    conversationDiv.className = 'conversation-item p-4 border-b border-gray-200 hover:bg-gray-50 cursor-pointer transition-colors';
    conversationDiv.setAttribute('data-conversa-id', conversa.id);
    conversationDiv.onclick = () => openConversation(conversa.id);
    
    const title = document.createElement('h3');
    title.className = 'font-medium text-gray-900 truncate';
    title.textContent = conversa.titulo;
    
    const materia = document.createElement('p');
    materia.className = 'text-sm text-gray-500 mt-1';
    materia.textContent = conversa.materia || 'Sem matéria específica';
    
    const timestamp = document.createElement('p');
    timestamp.className = 'text-xs text-gray-400 mt-2';
    timestamp.textContent = formatConversationTimestamp(conversa.updated_at);
    
    conversationDiv.appendChild(title);
    conversationDiv.appendChild(materia);
    conversationDiv.appendChild(timestamp);
    
    return conversationDiv;
}

// Função para formatar timestamp da conversa
function formatConversationTimestamp(timestamp) {
    const date = new Date(timestamp);
    const now = new Date();
    const diffTime = Math.abs(now - date);
    const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
    
    if (diffDays === 1) {
        return 'Hoje';
    } else if (diffDays === 2) {
        return 'Ontem';
    } else if (diffDays <= 7) {
        return `${diffDays - 1} dias atrás`;
    } else {
        return date.toLocaleDateString('pt-BR');
    }
}

// Funções para modais
function openCreateConversationModal() {
    document.getElementById('createConversationModal').classList.remove('hidden');
}

function closeCreateConversationModal() {
    document.getElementById('createConversationModal').classList.add('hidden');
    document.getElementById('createConversationForm').reset();
}

// Carregar conversas ao inicializar
document.addEventListener('DOMContentLoaded', function() {
    loadConversations();
});
</script>
