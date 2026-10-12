<div class="min-h-screen bg-gray-50">
    <input type="hidden" id="csrf_token_transcrever" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Transcrição de Imagem</h1>
                    <p class="text-gray-600 mt-1">Faça upload de uma imagem e transcreva o texto para redação</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.location.href='<?= URL ?>/redacoes'" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Área Principal -->
            <div class="lg:col-span-3">
                <!-- Upload de Imagem -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="border-l-4 border-orange-500 pl-4">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">Upload da Imagem</h2>
                        <div class="flex gap-2 mb-4">
                            <span class="px-2 py-1 bg-orange-100 text-orange-800 text-xs rounded-full">TRANSCRIÇÃO</span>
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">ENEM</span>
                        </div>
                        
                        <div class="mb-4">
                            <label for="imagemRedacao" class="block text-sm font-medium text-gray-700 mb-2">
                                Selecione uma imagem da sua redação:
                            </label>
                            <div class="mt-1 flex justify-center px-6 pt-5 pb-6 border-2 border-gray-300 border-dashed rounded-lg hover:border-orange-400 transition-colors">
                                <div class="space-y-1 text-center">
                                    <i class="fas fa-cloud-upload-alt text-4xl text-gray-400 mb-4"></i>
                                    <div class="flex text-sm text-gray-600">
                                        <label for="imagemRedacao" class="relative cursor-pointer bg-white rounded-md font-medium text-orange-600 hover:text-orange-500 focus-within:outline-none focus-within:ring-2 focus-within:ring-offset-2 focus-within:ring-orange-500">
                                            <span>Clique para fazer upload</span>
                                            <input id="imagemRedacao" name="imagem" type="file" class="sr-only" accept="image/*" onchange="previewImage(this)">
                                        </label>
                                        <p class="pl-1">ou arraste e solte aqui</p>
                                    </div>
                                    <p class="text-xs text-gray-500">PNG, JPG, JPEG até 10MB</p>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Preview da Imagem -->
                        <div id="imagePreview" class="hidden mb-4">
                            <h3 class="text-sm font-medium text-gray-700 mb-2">Preview da Imagem:</h3>
                            <img id="previewImg" class="max-w-full h-auto rounded-lg border border-gray-300" alt="Preview da imagem">
                            <div class="mt-2 flex gap-2">
                                <button onclick="transcreverImagem()" 
                                    class="px-4 py-2 bg-orange-600 text-white rounded-lg hover:bg-orange-700 transition-colors">
                                    <i class="fas fa-magic mr-2"></i>Transcrever Texto
                                </button>
                                <button onclick="removerImagem()" 
                                    class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                                    <i class="fas fa-trash mr-2"></i>Remover
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tema da Redação -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="border-l-4 border-blue-500 pl-4">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">Tema da Redação</h2>
                        <div class="mb-4">
                            <label for="temaRedacao" class="block text-sm font-medium text-gray-700 mb-2">
                                Digite o tema da sua redação:
                            </label>
                            <input type="text" id="temaRedacao" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                placeholder="Ex: A importância da educação na sociedade moderna"
                                value="">
                        </div>
                    </div>
                </div>

                <!-- Área de Escrita -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Texto Transcrito</h3>
                        <div class="flex items-center gap-4">
                            <div class="text-sm text-gray-600">
                                <span id="wordCount">0</span> palavras
                            </div>
                        </div>
                    </div>
                    
                    <textarea id="redacaoText" 
                        class="w-full h-96 p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                        placeholder="O texto transcrito aparecerá aqui... Você pode editá-lo conforme necessário."
                        spellcheck="false"
                        autocorrect="off"
                        autocapitalize="off"
                        autocomplete="off"
                        oncontextmenu="return false"
                        style="font-family: 'Times New Roman', serif; font-size: 14px; line-height: 1.6;"></textarea>
                    
                    <div class="mt-4 flex justify-between items-center">
                        <button onclick="salvarRascunho()" 
                            class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                            </svg>
                            Salvar Rascunho
                        </button>
                        <button onclick="finalizarRedacao()" 
                            class="px-6 py-2 bg-gradient-to-r from-purple-500 to-blue-600 text-white rounded-lg hover:from-purple-600 hover:to-blue-700 transition-all font-semibold flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"></path>
                            </svg>
                            Finalizar e Corrigir
                        </button>
                    </div>
                </div>
            </div>

            <!-- Sidebar -->
            <div class="lg:col-span-1">
                <!-- Status da Transcrição -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Status</h3>
                    <div id="transcriptionStatus" class="text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
                            <i class="fas fa-image text-gray-400 text-2xl"></i>
                        </div>
                        <p class="text-sm text-gray-600">Aguardando upload</p>
                    </div>
                </div>

                <!-- Dicas -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Dicas para Upload</h3>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="flex items-start">
                            <i class="fas fa-camera text-orange-500 mr-2 mt-1"></i>
                            <span>Use boa iluminação</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-align-center text-blue-500 mr-2 mt-1"></i>
                            <span>Mantenha o texto alinhado</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-font text-green-500 mr-2 mt-1"></i>
                            <span>Evite textos muito pequenos</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-edit text-purple-500 mr-2 mt-1"></i>
                            <span>Revise a transcrição antes de enviar</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let uploadedImage = null;

function previewImage(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        
        // Validar tamanho do arquivo (10MB)
        if (file.size > 10 * 1024 * 1024) {
            alert('Arquivo muito grande! Máximo 10MB.');
            return;
        }
        
        // Validar tipo de arquivo
        if (!file.type.startsWith('image/')) {
            alert('Por favor, selecione apenas arquivos de imagem.');
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').classList.remove('hidden');
            
            // Atualizar status
            document.getElementById('transcriptionStatus').innerHTML = `
                <div class="w-16 h-16 bg-orange-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check text-orange-600 text-2xl"></i>
                </div>
                <p class="text-sm text-orange-600">Imagem carregada</p>
            `;
            
            uploadedImage = file;
        };
        reader.readAsDataURL(file);
    }
}

function removerImagem() {
    document.getElementById('imagemRedacao').value = '';
    document.getElementById('imagePreview').classList.add('hidden');
    document.getElementById('redacaoText').value = '';
    
    // Atualizar status
    document.getElementById('transcriptionStatus').innerHTML = `
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-image text-gray-400 text-2xl"></i>
        </div>
        <p class="text-sm text-gray-600">Aguardando upload</p>
    `;
    
    uploadedImage = null;
}

function mostrarErroTranscricao(mensagem) {
    // Remove qualquer modal de erro existente
    const erroExistente = document.getElementById('erroTranscricaoModal');
    if (erroExistente) {
        erroExistente.remove();
    }
    
    // Cria modal de erro
    const modalErro = document.createElement('div');
    modalErro.id = 'erroTranscricaoModal';
    modalErro.className = 'fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50';
    modalErro.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl p-8 max-w-md w-full mx-4">
            <div class="flex items-center justify-between mb-4">
                <h3 class="text-2xl font-bold text-red-600">Erro na Transcrição</h3>
                <button onclick="document.getElementById('erroTranscricaoModal').remove()" class="text-gray-400 hover:text-gray-600">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="mb-6">
                <div class="flex items-center mb-4">
                    <div class="w-12 h-12 bg-red-100 rounded-full flex items-center justify-center mr-4">
                        <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                    </div>
                    <div class="flex-1">
                        <p class="text-gray-700 whitespace-pre-wrap break-words">${mensagem}</p>
                    </div>
                </div>
            </div>
            <button onclick="document.getElementById('erroTranscricaoModal').remove()" class="w-full bg-red-600 text-white py-2 px-4 rounded-lg hover:bg-red-700 transition-colors font-semibold">
                Fechar
            </button>
        </div>
    `;
    
    document.body.appendChild(modalErro);
    
    // Fecha ao clicar fora
    modalErro.addEventListener('click', function(e) {
        if (e.target === modalErro) {
            modalErro.remove();
        }
    });
}

function transcreverImagem() {
    if (!uploadedImage) {
        alert('Por favor, selecione uma imagem primeiro!');
        return;
    }
    
    showTranscriptionModal();
    document.getElementById('transcriptionStatus').innerHTML = `
        <div class="w-16 h-16 bg-blue-100 rounded-full flex items-center justify-center mx-auto mb-3">
            <i class="fas fa-spinner fa-spin text-blue-600 text-2xl"></i>
        </div>
        <p class="text-sm text-blue-600">Processando...</p>
    `;
    
    const controller = new AbortController();
    const timeoutId = setTimeout(() => controller.abort(), 300000);
    
    function doRequest(isRetry) {
        const formData = new FormData();
        formData.append('imagem', uploadedImage);
        const csrfInput = document.getElementById('csrf_token_transcrever');
        const csrfToken = csrfInput ? csrfInput.value : '';
        if (!csrfToken) {
            hideTranscriptionModal();
            alert('Erro: Token de segurança não encontrado. Recarregue a página.');
            return Promise.reject(new Error('Token não encontrado'));
        }
        formData.append('_token', csrfToken);
        
        return fetch('<?= URL ?>/redacoes/transcrever-imagem', {
            method: 'POST',
            body: formData,
            signal: controller.signal
        }).then(async function(response) {
            const text = await response.text();
            if (!response.ok) {
                if (response.status === 404) {
                    throw new Error('Rota não encontrada. Verifique se a URL está correta.');
                }
                const raw = text.replace(/^\uFEFF/, '').trim();
                let errorData = null;
                try {
                    errorData = JSON.parse(raw);
                } catch (_) {
                    const start = raw.indexOf('{');
                    const end = raw.lastIndexOf('}');
                    if (start !== -1 && end > start) {
                        try {
                            errorData = JSON.parse(raw.substring(start, end + 1));
                        } catch (__) {}
                    }
                }
                if (errorData && typeof errorData === 'object') {
                    const mensagemErro = (errorData.error || errorData.message || '').toString();
                    if (errorData.csrf_token) {
                        const el = document.getElementById('csrf_token_transcrever');
                        if (el) el.value = errorData.csrf_token;
                    }
                    if (mensagemErro.indexOf('CSRF') !== -1 || mensagemErro.indexOf('Token') !== -1) {
                        if (!isRetry) return doRequest(true);
                        throw new Error('Sessão atualizada. Clique em "Transcrever Texto" novamente.');
                    }
                    if (errorData.tipo_erro === 'quota' || errorData.is_quota_error) {
                        throw new Error('A cota de uso da API foi excedida ou o limite de pagamento foi atingido. Por favor, verifique sua conta ou tente novamente mais tarde.');
                    } else if (errorData.tipo_erro === 'credenciais') {
                        throw new Error('Erro de autenticação com o serviço de IA. As credenciais podem estar inválidas ou expiradas. Contate o administrador.');
                    } else if (errorData.tipo_erro === 'timeout') {
                        throw new Error('O tempo de processamento expirou. Tente novamente com uma imagem menor ou mais clara.');
                    } else if (mensagemErro) {
                        throw new Error(mensagemErro);
                    }
                }
                if (response.status === 400 && raw.indexOf('csrf_token') !== -1 && (raw.indexOf('CSRF') !== -1 || raw.indexOf('Token') !== -1)) {
                    const match = raw.match(/"csrf_token"\s*:\s*"([^"]+)"/);
                    if (match && match[1]) {
                        const el = document.getElementById('csrf_token_transcrever');
                        if (el) el.value = match[1];
                    }
                    if (!isRetry) return doRequest(true);
                    throw new Error('Sessão atualizada. Clique em "Transcrever Texto" novamente.');
                }
                if (response.status === 500) {
                    throw new Error('Erro interno do servidor. O processamento pode estar demorando ou há um problema com os serviços de IA. Tente novamente em alguns instantes.');
                }
                throw new Error('Erro na requisição. Recarregue a página e tente novamente.');
            }
            try {
                return JSON.parse(text.replace(/^\uFEFF/, '').trim());
            } catch (e) {
                throw new Error('Resposta do servidor não é JSON válido. O servidor pode estar retornando um erro HTML.');
            }
        });
    }
    
    doRequest(false)
    .then(data => {
        clearTimeout(timeoutId);
        // Esconder modal de loading
        hideTranscriptionModal();
        
        if (data.success) {
            document.getElementById('redacaoText').value = data.texto_transcrito;
            
            // Atualizar contador de palavras
            const wordCount = data.texto_transcrito.trim() ? data.texto_transcrito.trim().split(/\s+/).length : 0;
            document.getElementById('wordCount').textContent = wordCount;
            
            // Atualizar status para sucesso
            document.getElementById('transcriptionStatus').innerHTML = `
                <div class="w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-check text-green-600 text-2xl"></i>
                </div>
                <p class="text-sm text-green-600">Transcrição concluída</p>
            `;
            
            // Mostrar alerta de verificação
            showTranscriptionWarning();
            
            // Scroll automático para o texto transcrito
            setTimeout(() => {
                document.getElementById('redacaoText').scrollIntoView({ 
                    behavior: 'smooth', 
                    block: 'center' 
                });
            }, 500);
        } else {
            // Extrair mensagem de erro
            let mensagemErro = data.error || data.message || 'Erro desconhecido na transcrição';
            
            // Verificar se é erro de quota
            if (data.is_quota_error || data.tipo_erro === 'quota') {
                mensagemErro = 'A cota de uso da API foi excedida ou o limite de pagamento foi atingido. Por favor, verifique sua conta ou tente novamente mais tarde.';
            } else if (data.tipo_erro === 'timeout') {
                mensagemErro = 'O tempo de processamento expirou. Tente novamente com uma imagem menor ou mais clara.';
            } else if (data.tipo_erro === 'imagem_invalida') {
                mensagemErro = 'A imagem enviada é inválida ou não pôde ser processada. Verifique o formato e tente novamente.';
            }
            
            // Mostrar erro em modal detalhado
            mostrarErroTranscricao(mensagemErro);
            
            // Atualizar status para erro
            document.getElementById('transcriptionStatus').innerHTML = `
                <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                    <i class="fas fa-times text-red-600 text-2xl"></i>
                </div>
                <p class="text-sm text-red-600">Erro na transcrição</p>
            `;
        }
    })
    .catch(error => {
        clearTimeout(timeoutId);
        
        // Esconder modal de loading
        hideTranscriptionModal();
        
        console.error('Erro completo:', error);
        console.error('Nome do erro:', error.name);
        console.error('Mensagem do erro:', error.message);
        console.error('Stack:', error.stack);
        
        let mensagemErro = error.message || 'Erro desconhecido na transcrição';
        if ((mensagemErro.indexOf('CSRF') !== -1 || mensagemErro.indexOf('Token') !== -1) && mensagemErro.indexOf('csrf_token') !== -1) {
            const match = mensagemErro.match(/"csrf_token"\s*:\s*"([^"]+)"/);
            if (match && match[1]) {
                const el = document.getElementById('csrf_token_transcrever');
                if (el) el.value = match[1];
            }
            mensagemErro = 'Sessão atualizada. Clique em "Transcrever Texto" novamente.';
        }
        // Verificar se é timeout
        if (error.name === 'AbortError' || mensagemErro.toLowerCase().includes('timeout') || mensagemErro.toLowerCase().includes('aborted')) {
            mensagemErro = 'O tempo de processamento expirou (5 minutos). Isso pode indicar que o serviço de IA está demorando muito para responder. Tente novamente com uma imagem menor ou mais clara.';
        }
        // Verificar se é erro de quota/limite
        else if (mensagemErro.toLowerCase().includes('quota') || 
            mensagemErro.toLowerCase().includes('limit') ||
            mensagemErro.toLowerCase().includes('billing') ||
            mensagemErro.toLowerCase().includes('payment')) {
            mensagemErro = 'A cota de uso da API foi excedida ou o limite de pagamento foi atingido. Por favor, verifique sua conta ou tente novamente mais tarde.';
        }
        // Verificar se é erro de rede
        else if (mensagemErro.toLowerCase().includes('network') || 
                 mensagemErro.toLowerCase().includes('connection') ||
                 mensagemErro.toLowerCase().includes('failed to fetch')) {
            mensagemErro = 'Erro de conexão com o servidor. Verifique sua conexão com a internet e tente novamente.';
        }
        // Verificar se é erro 500
        else if (mensagemErro.toLowerCase().includes('500') || 
                 mensagemErro.toLowerCase().includes('internal server error')) {
            mensagemErro = 'Erro interno do servidor. O processamento pode estar demorando ou há um problema com os serviços de IA. Tente novamente em alguns instantes ou contate o suporte.';
        }
        
        // Mostrar erro em modal detalhado
        mostrarErroTranscricao(mensagemErro);
        
        // Atualizar status para erro
        document.getElementById('transcriptionStatus').innerHTML = `
            <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-3">
                <i class="fas fa-times text-red-600 text-2xl"></i>
            </div>
            <p class="text-sm text-red-600">Erro na transcrição</p>
        `;
    });
}

// Bloquear menu de contexto e verificação ortográfica
const redacaoTextarea = document.getElementById('redacaoText');
redacaoTextarea.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
});

// Contador de palavras
redacaoTextarea.addEventListener('input', function() {
    const text = this.value;
    const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
    
    document.getElementById('wordCount').textContent = wordCount;
});

async function salvarRascunho() {
    const tema = document.getElementById('temaRedacao').value;
    const texto = document.getElementById('redacaoText').value;
    
    if (!tema.trim()) {
        alert('Por favor, digite o tema da redação!');
        return;
    }
    
    if (!texto.trim()) {
        alert('Por favor, transcreva ou escreva sua redação!');
        return;
    }
    
    try {
        const formData = new FormData();
        const csrfEl = document.getElementById('csrf_token_transcrever');
        formData.append('_token', csrfEl ? csrfEl.value : '');
        formData.append('titulo', tema);
        formData.append('conteudo', texto);
        formData.append('tema_texto', tema);
        
        const response = await fetch('<?= URL ?>/redacoes/salvar-rascunho', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Limpar localStorage já que agora está no servidor
            localStorage.removeItem('rascunho_transcricao');
            
            // Mostrar mensagem de sucesso
            showNotification(data.message || 'Rascunho salvo com sucesso!', 'success');
            
            // Redirecionar após 1 segundo para a página de redações
            setTimeout(() => {
                window.location.href = '<?= URL ?>/redacoes';
            }, 1000);
        } else {
            showNotification('Erro ao salvar rascunho: ' + (data.error || 'Erro desconhecido'), 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro de conexão. Tente novamente.', 'error');
    }
}


function finalizarRedacao() {
    const tema = document.getElementById('temaRedacao').value;
    const texto = document.getElementById('redacaoText').value;
    const wordCount = texto.trim() ? texto.trim().split(/\s+/).length : 0;
    
    if (!tema.trim()) {
        alert('Por favor, digite o tema da redação!');
        document.getElementById('temaRedacao').focus();
        document.getElementById('temaRedacao').style.borderColor = '#ef4444';
        setTimeout(() => {
            document.getElementById('temaRedacao').style.borderColor = '';
        }, 3000);
        return;
    }
    
    if (!texto.trim()) {
        alert('Por favor, transcreva ou escreva sua redação!');
        return;
    }
    
    // Mostrar modal de correção
    showCorrectionModal();
    
    // Criar formulário para envio
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= URL ?>/redacoes/corrigir';
    
    // Adicionar campos
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = (document.getElementById('csrf_token_transcrever') || {}).value || '';
    form.appendChild(csrfToken);
    
    const conteudo = document.createElement('input');
    conteudo.type = 'hidden';
    conteudo.name = 'conteudo';
    conteudo.value = texto;
    form.appendChild(conteudo);
    
    const titulo = document.createElement('input');
    titulo.type = 'hidden';
    titulo.name = 'titulo';
    titulo.value = tema;
    form.appendChild(titulo);
    
    const temaTexto = document.createElement('input');
    temaTexto.type = 'hidden';
    temaTexto.name = 'tema';
    temaTexto.value = tema;
    form.appendChild(temaTexto);
    
    // Adicionar ao DOM e submeter
    document.body.appendChild(form);
    form.submit();
}

// Carregar rascunho salvo
window.addEventListener('load', function() {
    const rascunho = localStorage.getItem('rascunho_transcricao');
    if (rascunho) {
        try {
            const data = JSON.parse(rascunho);
            // Verificar se o rascunho não é muito antigo (24 horas)
            if (Date.now() - data.timestamp < 24 * 60 * 60 * 1000) {
                document.getElementById('temaRedacao').value = data.tema;
                document.getElementById('redacaoText').value = data.texto;
                
                // Atualizar contador de palavras
                const wordCount = data.texto.trim() ? data.texto.trim().split(/\s+/).length : 0;
                document.getElementById('wordCount').textContent = wordCount;
            }
        } catch (e) {
            console.log('Erro ao carregar rascunho:', e);
        }
    }
});

// Funções para modal de loading
function showTranscriptionModal() {
    const modal = document.getElementById('transcriptionModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function hideTranscriptionModal() {
    const modal = document.getElementById('transcriptionModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function showCorrectionModal() {
    const modal = document.getElementById('correctionModal');
    if (modal) {
        modal.classList.remove('hidden');
    }
}

function hideCorrectionModal() {
    const modal = document.getElementById('correctionModal');
    if (modal) {
        modal.classList.add('hidden');
    }
}

function showTranscriptionWarning() {
    // Criar modal de aviso
    const modalDiv = document.createElement('div');
    modalDiv.id = 'transcriptionWarningModal';
    modalDiv.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4';
    modalDiv.style.opacity = '0';
    modalDiv.innerHTML = `
        <div class="bg-white rounded-xl shadow-2xl max-w-md w-full transform scale-95 transition-all duration-300">
            <div class="p-6">
                <div class="flex items-start mb-4">
                    <div class="flex-shrink-0">
                        <div class="w-12 h-12 bg-amber-100 rounded-full flex items-center justify-center">
                            <svg class="h-6 w-6 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                        </div>
                    </div>
                    <div class="ml-4 flex-1">
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">
                            Importante: Revise o texto transcrito
                        </h3>
                        <p class="text-sm text-gray-600 mb-4">
                            A transcrição automática pode conter erros de interpretação. Por favor, revise cuidadosamente o texto e corrija quaisquer palavras, pontuações ou formatações que possam estar incorretas antes de finalizar sua redação.
                        </p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button onclick="hideTranscriptionWarning()" 
                        class="px-6 py-2 bg-gradient-to-r from-purple-500 to-blue-600 text-white rounded-lg hover:from-purple-600 hover:to-blue-700 transition-all font-semibold">
                        Entendi, vou revisar
                    </button>
                </div>
            </div>
        </div>
    `;
    
    document.body.appendChild(modalDiv);
    
    // Animar entrada do modal
    setTimeout(() => {
        modalDiv.style.transition = 'opacity 0.3s';
        modalDiv.style.opacity = '1';
        const content = modalDiv.querySelector('.transform');
        content.style.transform = 'scale(1)';
    }, 10);
    
    // Prevenir fechamento clicando fora do modal
    modalDiv.addEventListener('click', function(e) {
        if (e.target === modalDiv) {
            // Não permite fechar clicando fora - deve clicar no botão
            return;
        }
    });
}

function hideTranscriptionWarning() {
    const modal = document.getElementById('transcriptionWarningModal');
    if (modal) {
        modal.style.transition = 'opacity 0.3s';
        modal.style.opacity = '0';
        setTimeout(() => {
            modal.remove();
        }, 300);
    }
}
</script>

<!-- Modal de Loading para Transcrição -->
<div id="transcriptionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div class="p-8 text-center">
                <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-indigo-600 rounded-full flex items-center justify-center mx-auto mb-6">
                    <div class="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent"></div>
                </div>
                
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Transcrevendo Imagem</h3>
                <p class="text-gray-600 mb-4">A IA está analisando sua imagem e transcrevendo o texto...</p>
                
                <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                    <div class="bg-gradient-to-r from-blue-500 to-indigo-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                </div>
                
                <p class="text-sm text-gray-500">Isso pode levar alguns segundos</p>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Loading para Correção -->
<div id="correctionModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div class="p-8 text-center">
                <div class="w-20 h-20 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center mx-auto mb-6">
                    <div class="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent"></div>
                </div>
                
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Corrigindo Redação</h3>
                <p class="text-gray-600 mb-4">A IA está analisando sua redação e aplicando os critérios do ENEM...</p>
                
                <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
                </div>
                
                <p class="text-sm text-gray-500">Aguarde enquanto processamos sua correção</p>
            </div>
        </div>
    </div>
</div>
