<?php
$layout = 'professor';
$title = $title ?? 'Agente de IA - EducaTudo';
?>

<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900"><?= htmlspecialchars($agente['nome']) ?> 🤖</h1>
            <?php if (!empty($agente['descricao'])): ?>
                <p class="text-gray-600 mt-2"><?= htmlspecialchars($agente['descricao']) ?></p>
            <?php endif; ?>
        </div>
        <div class="flex space-x-2">
            <a href="<?= URL ?>/professor/ai-agents" 
               class="px-4 py-2 bg-gray-600 text-white rounded-lg hover:bg-gray-700 transition-colors">
                ← Voltar
            </a>
            <a href="<?= URL ?>/professor/ai-agents/<?= $agente['id'] ?>/editar" 
               class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                Editar Agente
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Coluna Esquerda: Documentos -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-xl shadow-lg p-6 mb-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Documentos</h2>
            
            <!-- Upload -->
            <form id="formUpload" class="mb-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                    <input type="file" id="arquivoInput" name="arquivo" accept=".pdf,.docx,.doc,.txt,.md,.jpg,.jpeg,.png" class="hidden">
                    <label for="arquivoInput" class="cursor-pointer">
                        <svg class="w-12 h-12 text-gray-400 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"></path>
                        </svg>
                        <p class="text-sm text-gray-600">Clique para fazer upload</p>
                        <p class="text-xs text-gray-500 mt-1">PDF, DOCX, TXT, Imagens</p>
                    </label>
                </div>
            </form>
            
            <!-- Lista de Documentos -->
            <div id="documentosList" class="space-y-2 max-h-96 overflow-y-auto">
                <?php if (empty($documentos)): ?>
                    <p class="text-sm text-gray-500 text-center py-4">Nenhum documento enviado</p>
                <?php else: ?>
                    <?php foreach ($documentos as $doc): ?>
                        <div class="bg-gray-50 rounded-lg p-3 flex items-center justify-between" data-documento-id="<?= $doc['id'] ?>">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($doc['nome_original']) ?></p>
                                <p class="text-xs text-gray-500">
                                    <?php
                                    $statusLabels = [
                                        'pendente' => 'Pendente',
                                        'processando' => 'Processando...',
                                        'concluido' => 'Concluído',
                                        'erro' => 'Erro'
                                    ];
                                    echo $statusLabels[$doc['status_processamento']] ?? $doc['status_processamento'];
                                    ?>
                                    <?php if ($doc['status_processamento'] === 'concluido' && $doc['total_chunks'] > 0): ?>
                                        • <?= $doc['total_chunks'] ?> chunks
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="flex items-center space-x-2 ml-2">
                                <?php if ($doc['status_processamento'] === 'processando'): ?>
                                    <div class="animate-spin rounded-full h-5 w-5 border-b-2 border-blue-600"></div>
                                <?php else: ?>
                                    <button onclick="excluirDocumento(<?= $doc['id'] ?>)" 
                                            class="text-red-500 hover:text-red-700 transition-colors p-1 rounded hover:bg-red-50"
                                            title="Excluir documento">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                        </svg>
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Coluna Direita: Chat -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-lg p-6">
            <h2 class="text-xl font-semibold text-gray-900 mb-4">Chat</h2>
            
            <!-- Área de Mensagens -->
            <div id="chatArea" class="bg-gray-50 rounded-lg p-4 mb-4 min-h-[400px] max-h-[500px] overflow-y-auto">
                <div id="messagesContainer" class="space-y-4">
                    <?php if (empty($mensagens)): ?>
                        <!-- Mensagem inicial se não houver conversa -->
                        <div class="flex items-start space-x-3">
                            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-white text-sm font-bold">AI</span>
                            </div>
                            <div class="flex-1 bg-blue-100 rounded-lg p-3">
                                <p class="text-gray-800">Olá! Sou <?= htmlspecialchars($agente['nome']) ?>. Como posso ajudá-lo hoje?</p>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Carrega mensagens salvas -->
                        <?php foreach ($mensagens as $msg): ?>
                            <?php if ($msg['role'] === 'user'): ?>
                                <div class="flex items-start justify-end space-x-2 mb-4">
                                    <div class="max-w-[70%] bg-green-100 rounded-lg p-3">
                                        <p class="text-gray-800"><?= htmlspecialchars($msg['conteudo']) ?></p>
                                    </div>
                                    <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden" style="min-width: 32px; min-height: 32px;">
                                        <?php 
                                        $avatarUrl = isset($user['avatar_url']) && !empty($user['avatar_url']) ? $user['avatar_url'] : null;
                                        $userName = isset($user['nome']) && !empty($user['nome']) ? $user['nome'] : 'P';
                                        
                                        if ($avatarUrl): 
                                        ?>
                                            <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($userName) ?>" class="w-full h-full object-cover rounded-full" style="display: block;" onerror="console.error('Erro ao carregar avatar:', this.src); this.style.display='none'; this.nextElementSibling.style.display='block';">
                                            <span class="text-white text-sm font-bold" style="display: none;"><?= strtoupper(substr($userName, 0, 1)) ?></span>
                                        <?php else: ?>
                                            <span class="text-white text-sm font-bold" style="display: block;"><?= strtoupper(substr($userName, 0, 1)) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="flex items-start space-x-2 mb-4">
                                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-white text-sm font-bold">AI</span>
                                    </div>
                                    <div class="max-w-[70%] bg-blue-100 rounded-lg p-3">
                                        <div class="text-gray-800">
                                            <?= isset($msg['conteudo_formatado']) ? $msg['conteudo_formatado'] : htmlspecialchars($msg['conteudo']) ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Input de Mensagem -->
            <div class="flex space-x-2">
                <input type="text" id="mensagemInput" placeholder="Digite sua pergunta..." 
                       class="flex-1 px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                <button id="sendButton" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-medium transition-colors">
                    Enviar
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentConversaId = <?= !empty($conversa_atual) ? $conversa_atual['id'] : 'null' ?>;
const agenteId = <?= $agente['id'] ?>;
const csrfToken = <?= json_encode($csrf_token) ?>;
<?php 
// DEBUG usando Logger
require_once __DIR__ . '/../../../Core/Logger.php';
Logger::info("DEBUG JavaScript - User existe: " . (isset($user) ? 'SIM' : 'NÃO'), [], 'debug');
Logger::info("DEBUG JavaScript - User avatar_url: " . (isset($user['avatar_url']) ? ($user['avatar_url'] ?? 'NULL') : 'USER NÃO EXISTE'), ['avatar_url' => $user['avatar_url'] ?? null], 'debug');

$userAvatarUrl = isset($user['avatar_url']) && !empty($user['avatar_url']) ? $user['avatar_url'] : null;
$userName = isset($user['nome']) && !empty($user['nome']) ? $user['nome'] : 'P';
?>
const userAvatarUrl = <?= $userAvatarUrl ? json_encode($userAvatarUrl) : 'null' ?>;
const userName = <?= json_encode($userName) ?>;


// Scroll para o final das mensagens ao carregar
document.addEventListener('DOMContentLoaded', function() {
    const chatArea = document.getElementById('chatArea');
    if (chatArea) {
        chatArea.scrollTop = chatArea.scrollHeight;
    }
});

// Upload de arquivo
document.getElementById('arquivoInput').addEventListener('change', async function(e) {
    const file = e.target.files[0];
    if (!file) return;
    
    const formData = new FormData();
    formData.append('arquivo', file);
    formData.append('_token', csrfToken);
    
    const btn = this;
    btn.disabled = true;
    
    try {
        const response = await fetch(`<?= URL ?>/professor/ai-agents/${agenteId}/upload`, {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            const text = await response.text();
            let errorMsg = `Erro HTTP ${response.status}`;
            try {
                const jsonError = JSON.parse(text);
                errorMsg = jsonError.error || errorMsg;
            } catch (e) {
                if (text.length < 200) {
                    errorMsg = text;
                }
            }
            throw new Error(errorMsg);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Resposta do servidor não é JSON');
        }
        
        const result = await response.json();
        
        if (result.success) {
            location.reload(); // Recarrega para mostrar novo documento
        } else {
            throw new Error(result.error || 'Erro ao fazer upload');
        }
    } catch (error) {
        console.error('Erro no upload:', error);
        let errorMsg = 'Erro de conexão';
        if (error.message) {
            errorMsg = error.message;
        } else if (error instanceof TypeError && error.message.includes('fetch')) {
            errorMsg = 'Erro de conexão com o servidor. Verifique sua internet.';
        }
        alert('Erro: ' + errorMsg);
    } finally {
        btn.disabled = false;
        e.target.value = ''; // Limpa input
    }
});

// Enviar mensagem
document.getElementById('sendButton').addEventListener('click', enviarMensagem);
document.getElementById('mensagemInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        enviarMensagem();
    }
});

async function enviarMensagem() {
    const input = document.getElementById('mensagemInput');
    const mensagem = input.value.trim();
    
    if (!mensagem) return;
    
    // Adiciona mensagem do usuário na tela
    adicionarMensagem('user', mensagem);
    input.value = '';
    
    const btn = document.getElementById('sendButton');
    btn.disabled = true;
    btn.textContent = 'Enviando...';
    
    try {
        const response = await fetch(`<?= URL ?>/professor/ai-agents/${agenteId}/enviar-mensagem`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                mensagem: mensagem,
                conversa_id: currentConversaId,
                _token: csrfToken
            })
        });
        
        // Verifica se a resposta é OK
        if (!response.ok) {
            const text = await response.text();
            let errorMsg = `Erro HTTP ${response.status}`;
            try {
                const jsonError = JSON.parse(text);
                errorMsg = jsonError.error || errorMsg;
            } catch (e) {
                // Se não for JSON, usa o texto da resposta
                if (text.length < 200) {
                    errorMsg = text;
                }
            }
            throw new Error(errorMsg);
        }
        
        // Tenta parsear como JSON
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            const text = await response.text();
            throw new Error('Resposta do servidor não é JSON. Verifique o console para mais detalhes.');
        }
        
        const result = await response.json();
        
        if (result.success) {
            currentConversaId = result.conversa_id;
            // Usar resposta_formatada se disponível, senão usar resposta
            const respostaFormatada = result.resposta_formatada || result.resposta;
            adicionarMensagem('assistant', respostaFormatada, result.resposta_formatada ? true : false);
        } else {
            throw new Error(result.error || 'Erro ao enviar mensagem');
        }
    } catch (error) {
        console.error('Erro ao enviar mensagem:', error);
        let errorMsg = 'Erro de conexão';
        if (error.message) {
            errorMsg = error.message;
        } else if (error instanceof TypeError && error.message.includes('fetch')) {
            errorMsg = 'Erro de conexão com o servidor. Verifique sua internet.';
        }
        alert(errorMsg);
        // Remove a mensagem do usuário se houve erro
        const messagesContainer = document.getElementById('messagesContainer');
        const lastMessage = messagesContainer.lastElementChild;
        if (lastMessage && lastMessage.querySelector('.bg-green-100')) {
            lastMessage.remove();
        }
    } finally {
        btn.disabled = false;
        btn.textContent = 'Enviar';
    }
}

function adicionarMensagem(role, conteudo, isHtml = false) {
    const container = document.getElementById('messagesContainer');
    const div = document.createElement('div');
    
    if (role === 'user') {
        
        const avatarHtml = userAvatarUrl 
            ? `<img src="${escapeHtml(userAvatarUrl)}" alt="${escapeHtml(userName)}" class="w-full h-full object-cover rounded-full" style="display: block;" onerror="console.error('Erro ao carregar avatar:', this.src); this.style.display='none'; this.nextElementSibling.style.display='block';"><span class="text-white text-sm font-bold" style="display: none;">${escapeHtml(userName.charAt(0).toUpperCase())}</span>`
            : `<span class="text-white text-sm font-bold" style="display: block;">${escapeHtml(userName.charAt(0).toUpperCase())}</span>`;
        
        div.className = 'flex items-start justify-end space-x-2 mb-4';
        div.innerHTML = `
            <div class="max-w-[70%] bg-green-100 rounded-lg p-3">
                <p class="text-gray-800">${escapeHtml(conteudo)}</p>
            </div>
            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center flex-shrink-0 overflow-hidden" style="min-width: 32px; min-height: 32px;">
                ${avatarHtml}
            </div>
        `;
    } else {
        div.className = 'flex items-start space-x-2 mb-4';
        
        // Para mensagens da IA, se isHtml for true, renderizar HTML diretamente
        const conteudoHtml = isHtml ? conteudo : escapeHtml(conteudo);
        
        div.innerHTML = `
            <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-white text-sm font-bold">AI</span>
            </div>
            <div class="max-w-[70%] bg-blue-100 rounded-lg p-3">
                <div class="text-gray-800">${conteudoHtml}</div>
            </div>
        `;
    }
    
    container.appendChild(div);
    
    // Scroll para baixo
    const chatArea = document.getElementById('chatArea');
    chatArea.scrollTop = chatArea.scrollHeight;
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Atualiza status dos documentos a cada 5 segundos (se houver processando)
setInterval(() => {
    const processando = document.querySelectorAll('[data-status="processando"]');
    if (processando.length > 0) {
        location.reload();
    }
}, 5000);

// Função para excluir documento
async function excluirDocumento(documentoId) {
    if (!confirm('Tem certeza que deseja excluir este documento? Esta ação não pode ser desfeita.')) {
        return;
    }
    
    try {
        const response = await fetch(`<?= URL ?>/professor/ai-agents/documentos/${documentoId}/excluir`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                _token: csrfToken
            })
        });
        
        if (!response.ok) {
            const text = await response.text();
            let errorMsg = `Erro HTTP ${response.status}`;
            try {
                const jsonError = JSON.parse(text);
                errorMsg = jsonError.error || errorMsg;
            } catch (e) {
                if (text.length < 200) {
                    errorMsg = text;
                }
            }
            throw new Error(errorMsg);
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            throw new Error('Resposta do servidor não é JSON');
        }
        
        const result = await response.json();
        
        if (result.success) {
            // Remove o elemento do DOM
            const documentoElement = document.querySelector(`[data-documento-id="${documentoId}"]`);
            if (documentoElement) {
                documentoElement.remove();
            }
            
            // Se não houver mais documentos, mostra mensagem
            const documentosList = document.getElementById('documentosList');
            if (documentosList && documentosList.children.length === 0) {
                documentosList.innerHTML = '<p class="text-sm text-gray-500 text-center py-4">Nenhum documento enviado</p>';
            }
            
            alert('Documento excluído com sucesso!');
        } else {
            throw new Error(result.error || 'Erro ao excluir documento');
        }
    } catch (error) {
        console.error('Erro ao excluir documento:', error);
        alert('Erro: ' + (error.message || 'Erro ao excluir documento'));
    }
}
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

// Atualizar função adicionarMensagem para renderizar MathJax
const originalAdicionarMensagem = adicionarMensagem;
adicionarMensagem = function(role, conteudo, isHtml = false) {
    originalAdicionarMensagem(role, conteudo, isHtml);
    // Renderizar MathJax após adicionar mensagem
    setTimeout(renderMathJax, 200);
};

// Renderizar MathJax ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    setTimeout(renderMathJax, 1000);
});

// Adicionar classe math-content aos containers de mensagens para processamento
document.addEventListener('DOMContentLoaded', function() {
    const messagesContainer = document.getElementById('messagesContainer');
    if (messagesContainer) {
        messagesContainer.classList.add('math-content');
    }
});
</script>

