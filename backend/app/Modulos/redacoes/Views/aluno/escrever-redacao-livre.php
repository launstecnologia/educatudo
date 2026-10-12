<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Redação Livre</h1>
                    <p class="text-gray-600 mt-1">Escreva sobre qualquer tema e receba correção automática</p>
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
            <!-- Área Principal de Escrita -->
            <div class="lg:col-span-3">
                <!-- Tema da Redação -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="border-l-4 border-green-500 pl-4">
                        <h2 class="text-xl font-bold text-gray-900 mb-2">Tema da Redação</h2>
                        <div class="flex gap-2 mb-4">
                            <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">LIVRE</span>
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">ENEM</span>
                        </div>
                        <div class="mb-4">
                            <label for="temaRedacao" class="block text-sm font-medium text-gray-700 mb-2">
                                Digite o tema da sua redação:
                            </label>
                            <input type="text" id="temaRedacao" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent"
                                placeholder="Ex: A importância da educação na sociedade moderna"
                                value="<?php 
                                if (isset($rascunho) && $rascunho) {
                                    $tema_texto = $rascunho['tema_texto'] ?? $rascunho['titulo'] ?? '';
                                    // Tentar decodificar JSON se for tema gerado pela IA
                                    $tema_decodificado = json_decode($tema_texto, true);
                                    if ($tema_decodificado && is_array($tema_decodificado) && isset($tema_decodificado['titulo'])) {
                                        // É JSON válido - pegar apenas o título
                                        echo htmlspecialchars($tema_decodificado['titulo']);
                                    } else {
                                        // É texto simples - usar como está
                                        echo htmlspecialchars($tema_texto);
                                    }
                                }
                                ?>">
                        </div>
                    </div>
                </div>

                <!-- Área de Escrita -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Sua Redação</h3>
                        <div class="flex items-center gap-4">
                            <div class="text-sm text-gray-600">
                                <span id="wordCount">0</span> palavras
                            </div>
                        </div>
                    </div>
                    
                    <textarea id="redacaoText" 
                        class="w-full h-96 p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-transparent resize-none"
                        placeholder="Digite sua redação aqui..."
                        spellcheck="false"
                        autocorrect="off"
                        autocapitalize="off"
                        autocomplete="off"
                        oncontextmenu="return false"
                        style="font-family: 'Times New Roman', serif; font-size: 14px; line-height: 1.6;"><?= isset($rascunho) && $rascunho ? htmlspecialchars($rascunho['conteudo'] ?? '') : '' ?></textarea>
                    
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
                <!-- Cronômetro -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Cronômetro</h3>
                    <div class="text-center">
                        <div id="timer" class="text-3xl font-bold text-green-600 mb-2">00:00:00</div>
                        <div class="text-sm text-gray-600 mb-4">Tempo de escrita</div>
                        <button id="timerBtn" onclick="toggleTimer()" 
                            class="w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                            <i class="fas fa-play mr-2"></i>Iniciar
                        </button>
                    </div>
                </div>

                <!-- Dicas -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Dicas para Redação</h3>
                    <div class="space-y-3 text-sm text-gray-600">
                        <div class="flex items-start">
                            <i class="fas fa-lightbulb text-yellow-500 mr-2 mt-1"></i>
                            <span>Escolha um tema atual e relevante</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-pen text-blue-500 mr-2 mt-1"></i>
                            <span>Use linguagem formal e objetiva</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-list text-green-500 mr-2 mt-1"></i>
                            <span>Estruture com introdução, desenvolvimento e conclusão</span>
                        </div>
                        <div class="flex items-start">
                            <i class="fas fa-check text-purple-500 mr-2 mt-1"></i>
                            <span>Inclua proposta de intervenção</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let isRunning = false;
let startTime = null;
let timerInterval = null;
let tempoAcumulado = 0; // Tempo total acumulado (incluindo de rascunhos anteriores)

function toggleTimer() {
    if (isRunning) {
        // Parar cronômetro
        clearInterval(timerInterval);
        // Adicionar o tempo decorrido ao acumulado
        if (startTime) {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            tempoAcumulado += elapsed;
            startTime = null;
        }
        document.getElementById('timerBtn').innerHTML = '<i class="fas fa-play mr-2"></i>Iniciar';
        document.getElementById('timerBtn').className = 'w-full px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors';
        isRunning = false;
    } else {
        // Iniciar cronômetro
        startTime = Date.now();
        timerInterval = setInterval(updateTimer, 1000);
        document.getElementById('timerBtn').innerHTML = '<i class="fas fa-pause mr-2"></i>Pausar';
        document.getElementById('timerBtn').className = 'w-full px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors';
        isRunning = true;
    }
}

function updateTimer() {
    const elapsed = Date.now() - startTime;
    const totalSeconds = tempoAcumulado + Math.floor(elapsed / 1000);
    const hours = Math.floor(totalSeconds / 3600);
    const minutes = Math.floor((totalSeconds % 3600) / 60);
    const seconds = totalSeconds % 60;
    
    document.getElementById('timer').textContent = 
        String(hours).padStart(2, '0') + ':' + 
        String(minutes).padStart(2, '0') + ':' + 
        String(seconds).padStart(2, '0');
}

function getTempoTotalEmSegundos() {
    let tempoAtual = 0;
    if (isRunning && startTime) {
        tempoAtual = Math.floor((Date.now() - startTime) / 1000);
    }
    return tempoAcumulado + tempoAtual;
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
    
    // Iniciar cronômetro automaticamente quando começar a escrever
    if (wordCount > 0 && !isRunning) {
        toggleTimer();
    }
});

let rascunhoId = <?= isset($rascunho) && $rascunho ? $rascunho['id'] : 'null' ?>;

async function salvarRascunho() {
    const tema = document.getElementById('temaRedacao').value;
    const texto = document.getElementById('redacaoText').value;
    
    if (!tema.trim()) {
        alert('Por favor, digite o tema da redação!');
        return;
    }
    
    if (!texto.trim()) {
        alert('Por favor, escreva sua redação!');
        return;
    }
    
    // Calcular tempo total decorrido
    const tempoTotal = getTempoTotalEmSegundos();
    
    try {
        const formData = new FormData();
        formData.append('_token', <?= json_encode($_SESSION['csrf_token'] ?? '') ?>);
        formData.append('titulo', tema);
        formData.append('conteudo', texto);
        formData.append('tema_texto', tema);
        formData.append('tempo_escrita', tempoTotal);
        
        if (rascunhoId) {
            formData.append('rascunho_id', rascunhoId);
        }
        
        const response = await fetch('<?= URL ?>/redacoes/salvar-rascunho', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (!rascunhoId) {
                rascunhoId = data.rascunho_id;
            }
            
            // Mostrar mensagem de sucesso
            showNotification(data.message || 'Rascunho salvo com sucesso!', 'success');
            
            // Limpar localStorage já que agora está no servidor
            localStorage.removeItem('rascunho_redacao_livre');
            
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

function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' :
        type === 'error' ? 'bg-red-500 text-white' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;
    notification.textContent = message;
    document.body.appendChild(notification);
    setTimeout(() => {
        if (notification.parentElement) {
            notification.remove();
        }
    }, 5000);
}

function finalizarRedacao() {
    const tema = document.getElementById('temaRedacao').value;
    const texto = document.getElementById('redacaoText').value;
    const wordCount = texto.trim() ? texto.trim().split(/\s+/).length : 0;
    
    if (!tema.trim()) {
        alert('Por favor, digite o tema da redação!');
        return;
    }
    
    if (!texto.trim()) {
        alert('Por favor, escreva sua redação!');
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
    csrfToken.value = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;
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
    
    // Se for rascunho, adicionar ID para converter em redação final
    const rascunhoId = <?= isset($rascunho) && $rascunho ? $rascunho['id'] : 'null' ?>;
    if (rascunhoId) {
        const redacaoId = document.createElement('input');
        redacaoId.type = 'hidden';
        redacaoId.name = 'redacao_id';
        redacaoId.value = rascunhoId;
        form.appendChild(redacaoId);
    }
    
    // Adicionar ao DOM e submeter
    document.body.appendChild(form);
    form.submit();
}

// Funções para modal de correção
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

// Carregar rascunho do servidor ou do localStorage (fallback)
window.addEventListener('load', function() {
    // Se já temos um rascunho do servidor, usar ele
    <?php if (isset($rascunho) && $rascunho): ?>
    const texto = document.getElementById('redacaoText').value;
    if (texto.trim()) {
        const wordCount = texto.trim() ? texto.trim().split(/\s+/).length : 0;
        document.getElementById('wordCount').textContent = wordCount;
    }
    
    // Restaurar tempo do rascunho
    <?php if (isset($rascunho['tempo_escrita']) && $rascunho['tempo_escrita'] > 0): ?>
    tempoAcumulado = <?= intval($rascunho['tempo_escrita']) ?>;
    const tempoRestaurado = <?= intval($rascunho['tempo_escrita']) ?>;
    const hours = Math.floor(tempoRestaurado / 3600);
    const minutes = Math.floor((tempoRestaurado % 3600) / 60);
    const seconds = tempoRestaurado % 60;
    document.getElementById('timer').textContent = 
        String(hours).padStart(2, '0') + ':' + 
        String(minutes).padStart(2, '0') + ':' + 
        String(seconds).padStart(2, '0');
    <?php endif; ?>
    <?php else: ?>
    // Fallback: tentar carregar do localStorage se não houver rascunho no servidor
    const rascunho = localStorage.getItem('rascunho_redacao_livre');
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
    <?php endif; ?>
});
</script>

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
