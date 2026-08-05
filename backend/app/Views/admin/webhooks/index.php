<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Configuração de Webhooks 🔗</h1>
            <p class="text-gray-600 mt-2">Gerencie os webhooks para integração com serviços externos</p>
        </div>
        <div class="flex items-center space-x-4">
            <button onclick="openCreateWebhookModal()" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                <svg class="w-5 h-5 inline mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                </svg>
                Novo Webhook
            </button>
        </div>
    </div>
</div>

<!-- Webhooks List -->
<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Webhooks Configurados</h2>
    </div>
    <div class="p-6">
        <div id="webhooksList" class="space-y-4">
            <!-- Webhooks serão carregados aqui -->
        </div>
    </div>
</div>

<!-- Create Webhook Modal -->
<div id="createWebhookModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Novo Webhook</h3>
                    <button onclick="closeCreateWebhookModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <form id="createWebhookForm">
                    <input type="hidden" name="_token" value="<?= $this->generateCsrfToken() ?>">
                    
                    <div class="mb-4">
                        <label for="webhookNome" class="block text-sm font-medium text-gray-700 mb-2">Nome do Webhook</label>
                        <input type="text" id="webhookNome" name="nome" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Ex: Tudinha Oficial">
                    </div>
                    
                    <div class="mb-4">
                        <label for="webhookEndpoint" class="block text-sm font-medium text-gray-700 mb-2">Endpoint URL</label>
                        <input type="url" id="webhookEndpoint" name="endpoint" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="https://exemplo.com/webhook">
                    </div>
                    
                    <div class="mb-4">
                        <label for="webhookTipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                        <select id="webhookTipo" name="tipo" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Selecione o tipo</option>
                            <option value="chat_ia">Chat IA</option>
                            <option value="chat">Chat Geral</option>
                            <option value="geral">Geral</option>
                        </select>
                    </div>
                    
                    <div class="mb-4">
                        <label for="webhookEscola" class="block text-sm font-medium text-gray-700 mb-2">Escola (opcional)</label>
                        <select id="webhookEscola" name="escola_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <option value="">Webhook Global</option>
                            <!-- Opções de escolas serão carregadas aqui -->
                        </select>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="button" onclick="closeCreateWebhookModal()" 
                                class="flex-1 px-4 py-2 bg-gray-300 text-gray-700 rounded-lg hover:bg-gray-400 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" 
                                class="btn-primary-custom flex-1 px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                            Criar Webhook
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Test Webhook Modal -->
<div id="testWebhookModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full">
            <div class="p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Testar Webhook</h3>
                    <button onclick="closeTestWebhookModal()" class="text-gray-400 hover:text-gray-600">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
                
                <div id="testWebhookContent">
                    <!-- Conteúdo do teste será carregado aqui -->
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Load webhooks
function loadWebhooks() {
    fetch('<?= URL ?>/admin/dev/webhooks', {
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayWebhooks(data.webhooks);
            } else {
                showNotification(data.error || 'Erro ao carregar webhooks', 'error');
            }
        })
        .catch(error => {
            showNotification('Erro de conexão', 'error');
            console.error('Error:', error);
        });
}

// Display webhooks
function displayWebhooks(webhooks) {
    const webhooksList = document.getElementById('webhooksList');
    
    if (webhooks.length === 0) {
        webhooksList.innerHTML = `
            <div class="text-center py-8">
                <svg class="w-12 h-12 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                </svg>
                <p class="text-gray-500">Nenhum webhook configurado</p>
                <p class="text-sm text-gray-400">Crie seu primeiro webhook para começar</p>
            </div>
        `;
        return;
    }
    
    let html = '';
    
    webhooks.forEach(webhook => {
        const statusClass = webhook.ativo ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
        const statusText = webhook.ativo ? 'Ativo' : 'Inativo';
        
        html += `
            <div class="flex items-center justify-between p-4 bg-gray-50 rounded-lg">
                <div class="flex-1">
                    <div class="flex items-center space-x-3">
                        <h3 class="font-medium text-gray-900">${webhook.nome}</h3>
                        <span class="px-2 py-1 ${statusClass} text-xs rounded-full">${statusText}</span>
                        <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">${webhook.tipo}</span>
                    </div>
                    <p class="text-sm text-gray-600 mt-1">${webhook.endpoint}</p>
                    <p class="text-xs text-gray-500">Criado em: ${new Date(webhook.created_at).toLocaleDateString('pt-BR')}</p>
                </div>
                <div class="flex items-center space-x-2">
                    <button onclick="testWebhook(${webhook.id})" 
                            class="px-3 py-1 bg-green-500 text-white text-xs rounded hover:bg-green-600 transition-colors">
                        Testar
                    </button>
                    <button onclick="editWebhook(${webhook.id})" 
                            class="btn-primary-custom px-3 py-1 text-xs rounded transition-colors hover:opacity-90">
                        Editar
                    </button>
                    <button onclick="deleteWebhook(${webhook.id})" 
                            class="px-3 py-1 bg-red-500 text-white text-xs rounded hover:bg-red-600 transition-colors">
                        Excluir
                    </button>
                </div>
            </div>
        `;
    });
    
    webhooksList.innerHTML = html;
}

// Create webhook
document.getElementById('createWebhookForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('<?= URL ?>/admin/dev/webhooks', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification('Webhook criado com sucesso!');
            closeCreateWebhookModal();
            loadWebhooks();
        } else {
            showNotification(data.error || 'Erro ao criar webhook', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
});

// Test webhook
function testWebhook(webhookId) {
    document.getElementById('testWebhookModal').classList.remove('hidden');
    
    const testContent = document.getElementById('testWebhookContent');
    testContent.innerHTML = `
        <div class="text-center py-8">
            <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto mb-4"></div>
            <p class="text-gray-500">Testando webhook...</p>
        </div>
    `;
    
    fetch(`<?= URL ?>/admin/dev/webhooks/${webhookId}/test`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            _token: document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            testContent.innerHTML = `
                <div class="bg-green-50 border border-green-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-green-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="font-medium text-green-800">Teste realizado com sucesso!</span>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-2">Resposta do Webhook:</h4>
                    <pre class="text-sm text-gray-700 whitespace-pre-wrap">${data.response}</pre>
                </div>
            `;
        } else {
            testContent.innerHTML = `
                <div class="bg-red-50 border border-red-200 rounded-lg p-4 mb-4">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                        <span class="font-medium text-red-800">Erro no teste</span>
                    </div>
                </div>
                <div class="bg-gray-50 rounded-lg p-4">
                    <h4 class="font-medium text-gray-900 mb-2">Erro:</h4>
                    <p class="text-sm text-red-700">${data.error}</p>
                </div>
            `;
        }
    })
    .catch(error => {
        testContent.innerHTML = `
            <div class="bg-red-50 border border-red-200 rounded-lg p-4">
                <div class="flex items-center">
                    <svg class="w-5 h-5 text-red-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                    <span class="font-medium text-red-800">Erro de conexão</span>
                </div>
                <p class="text-sm text-red-700 mt-2">Verifique sua conexão e tente novamente</p>
            </div>
        `;
    });
}

// Modal functions
function openCreateWebhookModal() {
    document.getElementById('createWebhookModal').classList.remove('hidden');
}

function closeCreateWebhookModal() {
    document.getElementById('createWebhookModal').classList.add('hidden');
    document.getElementById('createWebhookForm').reset();
}

function closeTestWebhookModal() {
    document.getElementById('testWebhookModal').classList.add('hidden');
}

// Load webhooks on page load
document.addEventListener('DOMContentLoaded', function() {
    loadWebhooks();
});
</script>
