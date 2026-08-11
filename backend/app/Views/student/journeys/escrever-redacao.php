<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Mensagens de Erro/Sucesso -->
        <?php if (isset($_SESSION['error_message'])): ?>
            <div id="errorMessage" class="bg-red-500 text-white px-6 py-4 rounded-lg shadow-lg mb-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium"><?= htmlspecialchars($_SESSION['error_message']) ?></span>
                </div>
                <button onclick="document.getElementById('errorMessage').remove()" class="text-white hover:text-red-200 ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <?php unset($_SESSION['error_message']); ?>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['success_message'])): ?>
            <div id="successMessage" class="bg-green-500 text-white px-6 py-4 rounded-lg shadow-lg mb-6 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                    </svg>
                    <span class="font-medium"><?= htmlspecialchars($_SESSION['success_message']) ?></span>
                </div>
                <button onclick="document.getElementById('successMessage').remove()" class="text-white hover:text-green-200 ml-4">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <?php unset($_SESSION['success_message']); ?>
        <?php endif; ?>
        
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Redação da Jornada</h1>
                    <p class="text-gray-600 mt-1"><?= htmlspecialchars($redacao_jornada['jornada_titulo'] ?? 'Jornada') ?></p>
                </div>
                <div class="flex gap-3">
                    <a href="<?= URL ?>/jornadas/<?= $redacao_jornada['jornada_id'] ?>" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </a>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6">
            <!-- Área Principal de Escrita -->
            <div>
                <!-- Tema da Redação -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="border-l-4 border-blue-500 pl-4">
                        <div class="flex items-center justify-between mb-2">
                            <h2 class="text-xl font-bold text-gray-900">Tema da Redação</h2>
                            <?php if ($redacao_jornada['documento_tema']): ?>
                                <div class="flex gap-2">
                                    <button onclick="abrirVisualizadorDocumento()" 
                                            class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2 text-sm font-medium"
                                            title="Abrir documento ao lado da área de escrita">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                        </svg>
                                        Visualizar ao Lado
                                    </button>
                                    <button onclick="abrirModalDocumento()" 
                                            class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors flex items-center gap-2 text-sm font-medium"
                                            title="Abrir documento em tela cheia">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"></path>
                                        </svg>
                                        Abrir em Tela Cheia
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex gap-2 mb-4">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">JORNADA</span>
                        </div>
                        <div class="mb-4">
                            <h3 class="text-lg font-semibold text-gray-900 mb-2">
                                <?= htmlspecialchars($redacao_jornada['tema_sugerido']) ?>
                            </h3>
                            <?php if (!empty($redacao_jornada['descricao_tema'])): ?>
                                <?php
                                $desc = $redacao_jornada['descricao_tema'];
                                $isHtml = (strpos($desc, '<') !== false);
                                $descSafe = $isHtml ? \App\Utils\HtmlSanitizer::cleanEnunciadoWithImages($desc) : nl2br(htmlspecialchars($desc, ENT_QUOTES, 'UTF-8'));
                                ?>
                                <div class="prose prose-sm max-w-none text-gray-700 mb-4 descricao-tema-content">
                                    <?= $descSafe ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($redacao_jornada['imagem_tema']): ?>
                                <div class="mb-4">
                                    <img src="<?= URL ?>/<?= htmlspecialchars($redacao_jornada['imagem_tema']) ?>" 
                                         alt="Imagem do tema" 
                                         class="max-w-full h-auto rounded-lg border border-gray-200">
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Área de Escrita -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6" id="areaEscrita">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Sua Redação</h3>
                    </div>
                    
                    <div id="containerEscrita" class="relative mb-4" style="min-height: 600px;">
                        <textarea id="redacaoText" 
                            class="w-full p-4 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent resize-none"
                            placeholder="Digite sua redação aqui..."
                            spellcheck="false"
                            autocorrect="off"
                            autocapitalize="off"
                            autocomplete="off"
                            oncontextmenu="return false"
                            style="font-family: 'Times New Roman', serif; font-size: 14px; line-height: 1.6; min-height: 600px; height: 600px;"><?= isset($redacao_existente) && $redacao_existente ? htmlspecialchars($redacao_existente['conteudo'] ?? '') : '' ?></textarea>
                        
                        <!-- Visualizador de Documento (inicialmente oculto) -->
                        <div id="visualizadorDocumento" class="hidden absolute top-0 right-0 bg-white border-l-2 border-gray-300 shadow-lg" style="width: 0%; height: 600px; transition: width 0.3s ease; z-index: 10;">
                            <div class="h-full flex flex-col">
                                <!-- Cabeçalho do Visualizador -->
                                <div class="bg-blue-600 text-white px-4 py-2 flex items-center justify-between flex-shrink-0">
                                    <div class="flex items-center gap-2">
                                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                                        </svg>
                                        <span class="font-medium">Documento do Tema</span>
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button onclick="ajustarTamanhoDocumento('diminuir')" 
                                                class="p-1 hover:bg-blue-700 rounded transition-colors" 
                                                title="Diminuir">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 12H4"></path>
                                            </svg>
                                        </button>
                                        <button onclick="ajustarTamanhoDocumento('aumentar')" 
                                                class="p-1 hover:bg-blue-700 rounded transition-colors" 
                                                title="Aumentar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                            </svg>
                                        </button>
                                        <button onclick="fecharVisualizadorDocumento()" 
                                                class="p-1 hover:bg-blue-700 rounded transition-colors" 
                                                title="Fechar">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </div>
                                <!-- Iframe do PDF -->
                                <div class="flex-1 overflow-hidden" style="height: calc(600px - 48px);">
                                    <iframe id="iframeDocumento" 
                                            src="<?= $redacao_jornada['documento_tema'] ? URL . '/' . htmlspecialchars($redacao_jornada['documento_tema']) : '' ?>" 
                                            class="w-full h-full border-0"></iframe>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex justify-between items-center relative z-20">
                        <div class="flex items-center gap-3">
                            <button onclick="salvarRascunho()" 
                                class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4"></path>
                                </svg>
                                Salvar Rascunho
                            </button>
                            <span id="autosaveStatus" class="text-xs text-gray-500">Autosave ativo</span>
                        </div>
                        <button onclick="enviarEContinuar()" 
                            class="px-6 py-2 bg-gradient-to-r from-blue-500 to-blue-600 text-white rounded-lg hover:from-blue-600 hover:to-blue-700 transition-all font-semibold flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Enviar e Continuar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal para Documento em Tela Cheia -->
<div id="modalDocumento" class="hidden fixed inset-0 bg-black bg-opacity-75 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-2xl w-full h-full max-w-7xl max-h-[95vh] flex flex-col">
        <!-- Cabeçalho do Modal -->
        <div class="bg-purple-600 text-white px-6 py-4 flex items-center justify-between rounded-t-lg">
            <div class="flex items-center gap-3">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
                </svg>
                <span class="text-lg font-semibold">Documento do Tema - <?= htmlspecialchars($redacao_jornada['tema_sugerido']) ?></span>
            </div>
            <button onclick="fecharModalDocumento()" 
                    class="p-2 hover:bg-purple-700 rounded transition-colors"
                    title="Fechar (ESC)">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
        <!-- Conteúdo do Modal -->
        <div class="flex-1 overflow-hidden p-4">
            <iframe id="iframeModalDocumento" 
                    src="<?= $redacao_jornada['documento_tema'] ? URL . '/' . htmlspecialchars($redacao_jornada['documento_tema']) : '' ?>" 
                    class="w-full h-full border-0 rounded-lg"
                    style="min-height: 100%;"></iframe>
        </div>
    </div>
</div>

<script>
let isRunning = false;
let startTime = null;
let timerInterval = null;
let tempoAcumulado = 0; // Tempo total acumulado (incluindo de rascunhos anteriores)
let tamanhoDocumento = 50; // Porcentagem inicial do documento (50% da largura - maior)
let documentoAberto = false;
let autoSaveTimer = null;
let autoSaveInFlight = false;
let hasPendingChanges = false;
const AUTO_SAVE_DEBOUNCE_MS = 8000;

// Funções para controlar o visualizador de documento
function abrirVisualizadorDocumento() {
    const visualizador = document.getElementById('visualizadorDocumento');
    const textarea = document.getElementById('redacaoText');
    
    if (!visualizador || !textarea) return;
    
    visualizador.classList.remove('hidden');
    documentoAberto = true;
    ajustarTamanhoDocumento('reset');
}

function fecharVisualizadorDocumento() {
    const visualizador = document.getElementById('visualizadorDocumento');
    if (!visualizador) return;
    
    visualizador.classList.add('hidden');
    documentoAberto = false;
    ajustarLayoutEscrita(100);
}

function ajustarTamanhoDocumento(acao) {
    if (!documentoAberto) return;
    
    if (acao === 'aumentar') {
        tamanhoDocumento = Math.min(tamanhoDocumento + 10, 75); // Máximo 75%
    } else if (acao === 'diminuir') {
        tamanhoDocumento = Math.max(tamanhoDocumento - 10, 30); // Mínimo 30%
    } else if (acao === 'reset') {
        tamanhoDocumento = 50; // Reset para 50% (maior que antes)
    }
    
    ajustarLayoutEscrita(100 - tamanhoDocumento);
    const visualizador = document.getElementById('visualizadorDocumento');
    if (visualizador) {
        visualizador.style.width = tamanhoDocumento + '%';
    }
}

function ajustarLayoutEscrita(porcentagemEscrita) {
    const textarea = document.getElementById('redacaoText');
    if (textarea) {
        textarea.style.width = porcentagemEscrita + '%';
        textarea.style.transition = 'width 0.3s ease';
    }
}

// Funções para o modal de documento
function abrirModalDocumento() {
    const modal = document.getElementById('modalDocumento');
    if (modal) {
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden'; // Prevenir scroll do body
    }
}

function fecharModalDocumento() {
    const modal = document.getElementById('modalDocumento');
    if (modal) {
        modal.classList.add('hidden');
        document.body.style.overflow = ''; // Restaurar scroll do body
    }
}

// Fechar modal com tecla ESC
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        fecharModalDocumento();
    }
});

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
        document.getElementById('timerBtn').className = 'w-full px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors';
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

// TAB dentro da redação: insere recuo de parágrafo sem perder foco
redacaoTextarea.addEventListener('keydown', function(e) {
    if (e.key !== 'Tab') return;
    e.preventDefault();

    const start = this.selectionStart;
    const end = this.selectionEnd;
    const value = this.value;
    const indent = '    '; // 4 espaços

    if (start === end) {
        this.value = value.slice(0, start) + indent + value.slice(end);
        this.selectionStart = this.selectionEnd = start + indent.length;
    } else {
        const selectedText = value.slice(start, end);
        const indentedText = selectedText
            .split('\n')
            .map(function(line) { return indent + line; })
            .join('\n');

        this.value = value.slice(0, start) + indentedText + value.slice(end);
        this.selectionStart = start;
        this.selectionEnd = start + indentedText.length;
    }

    hasPendingChanges = true;
    scheduleAutosave();
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

    scheduleAutosave();
});

let redacaoId = <?= isset($redacao_existente) && $redacao_existente ? ($redacao_existente['redacao_id'] ?? $redacao_existente['id'] ?? 'null') : 'null' ?>;

function setAutosaveStatus(message, type = 'info') {
    const el = document.getElementById('autosaveStatus');
    if (!el) return;
    el.textContent = message;
    el.className = 'text-xs ' + (
        type === 'success' ? 'text-green-600' :
        type === 'error' ? 'text-red-600' :
        type === 'saving' ? 'text-blue-600' :
        'text-gray-500'
    );
}

function scheduleAutosave() {
    hasPendingChanges = true;
    setAutosaveStatus('Alterações pendentes...', 'info');
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
    }
    autoSaveTimer = setTimeout(() => {
        salvarRascunho({ silent: true, reason: 'autosave' });
    }, AUTO_SAVE_DEBOUNCE_MS);
}

function triggerImmediateAutosave() {
    if (!hasPendingChanges) return;
    if (autoSaveTimer) {
        clearTimeout(autoSaveTimer);
        autoSaveTimer = null;
    }
    salvarRascunho({ silent: true, reason: 'autosave-immediate' });
}

async function salvarRascunho(options = {}) {
    const silent = !!options.silent;
    const reason = options.reason || 'manual';
    const texto = document.getElementById('redacaoText').value;
    
    if (!texto.trim()) {
        if (!silent) {
            alert('Por favor, escreva sua redação!');
        }
        return;
    }

    if (autoSaveInFlight) {
        return;
    }
    
    // Calcular tempo total decorrido
    const tempoTotal = getTempoTotalEmSegundos();
    
    try {
        autoSaveInFlight = true;
        setAutosaveStatus(reason === 'manual' ? 'Salvando...' : 'Salvando automaticamente...', 'saving');

        const formData = new FormData();
        formData.append('_token', '<?= htmlspecialchars($csrf_token) ?>');
        formData.append('jornada_redacao_id', '<?= $redacao_jornada['id'] ?>');
        formData.append('conteudo', texto);
        formData.append('tempo_escrita', tempoTotal);
        
        if (redacaoId) {
            formData.append('redacao_id', redacaoId);
        }
        
        const response = await fetch('<?= URL ?>/jornadas/redacao/salvar', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            if (!redacaoId) {
                redacaoId = data.redacao_id;
            }
            hasPendingChanges = false;
            
            if (!silent) {
                showNotification(data.message || 'Rascunho salvo com sucesso!', 'success');
            }
            setAutosaveStatus('Último salvamento: ' + new Date().toLocaleTimeString('pt-BR'), 'success');
        } else {
            // Exibir erro detalhado na tela
            const errorMsg = data.error || 'Erro desconhecido ao salvar rascunho';
            console.error('Erro ao salvar rascunho:', errorMsg);
            if (!silent) {
                showNotification('Erro ao salvar rascunho: ' + errorMsg, 'error');
            }
            setAutosaveStatus('Erro ao salvar automaticamente', 'error');
            
            // Também mostrar alerta para garantir que o usuário veja
            if (!silent) {
                alert('ERRO AO SALVAR RASCUNHO:\n\n' + errorMsg + '\n\nPor favor, verifique sua conexão e tente novamente.');
            }
        }
    } catch (error) {
        console.error('Erro de conexão:', error);
        const errorMsg = 'Erro de conexão. Verifique sua internet e tente novamente.';
        if (!silent) {
            showNotification(errorMsg, 'error');
            alert('ERRO DE CONEXÃO:\n\n' + errorMsg + '\n\nDetalhes técnicos: ' + error.message);
        }
        setAutosaveStatus('Falha no autosave (sem conexão)', 'error');
    } finally {
        autoSaveInFlight = false;
    }
}

function showNotification(message, type = 'info') {
    // Remover notificações anteriores do mesmo tipo
    const existingNotifications = document.querySelectorAll('.notification-message');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification-message fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 max-w-md ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white border-2 border-red-600' :
        type === 'warning' ? 'bg-yellow-500 text-white' :
        'bg-blue-500 text-white'
    }`;

    const icon = type === 'success' 
        ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        : type === 'error'
        ? '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>'
        : '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>';

    notification.innerHTML = `
        <div class="flex items-start gap-3">
            ${icon}
            <div class="flex-1">
                <p class="font-medium notification-text"></p>
            </div>
            <button onclick="this.closest('.notification-message').remove()" class="text-white hover:opacity-75 transition-opacity flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    `;
    notification.querySelector('.notification-text').textContent = message;

    document.body.appendChild(notification);

    // Para erros, manter por mais tempo e fazer scroll para cima
    const timeout = type === 'error' ? 10000 : 5000;
    
    if (type === 'error') {
        notification.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    setTimeout(() => {
        if (notification.parentElement) {
            notification.style.opacity = '0';
            notification.style.transition = 'opacity 0.5s';
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 500);
        }
    }, timeout);
}

function enviarEContinuar() {
    const texto = document.getElementById('redacaoText').value;
    const wordCount = texto.trim() ? texto.trim().split(/\s+/).length : 0;
    
    console.log('=== ENVIAR E CONTINUAR REDAÇÃO - INÍCIO ===');
    console.log('Texto length:', texto.length);
    console.log('Texto preview (primeiros 200 chars):', texto.substring(0, 200));
    console.log('RedacaoId:', redacaoId);
    console.log('JornadaRedacaoId:', '<?= $redacao_jornada['id'] ?>');
    
    if (!texto.trim()) {
        console.error('ERRO: Texto vazio!');
        alert('Por favor, escreva sua redação!');
        return;
    }
    
    // Sempre enviar para correção do professor
    if (!confirm('Tem certeza que deseja enviar sua redação? Após enviar, ela será enviada para correção do professor e você continuará para a próxima etapa.')) {
        console.log('Usuário cancelou o envio');
        return;
    }
    
    // Criar formulário para envio
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '<?= URL ?>/jornadas/redacao/finalizar';
    form.enctype = 'application/x-www-form-urlencoded';
    
    // Adicionar campos
    const csrfToken = document.createElement('input');
    csrfToken.type = 'hidden';
    csrfToken.name = '_token';
    csrfToken.value = '<?= htmlspecialchars($csrf_token) ?>';
    form.appendChild(csrfToken);
    console.log('CSRF Token adicionado');
    
    const jornadaRedacaoId = document.createElement('input');
    jornadaRedacaoId.type = 'hidden';
    jornadaRedacaoId.name = 'jornada_redacao_id';
    jornadaRedacaoId.value = '<?= $redacao_jornada['id'] ?>';
    form.appendChild(jornadaRedacaoId);
    console.log('JornadaRedacaoId adicionado:', jornadaRedacaoId.value);
    
    // Usar textarea para conteúdo longo (evita problemas com caracteres especiais)
    const conteudo = document.createElement('textarea');
    conteudo.style.display = 'none';
    conteudo.name = 'conteudo';
    conteudo.value = texto;
    form.appendChild(conteudo);
    console.log('Conteúdo adicionado ao form - length:', conteudo.value.length);
    
    // Log para debug
    console.log('Finalizando redação - Conteúdo length:', texto.length);
    console.log('Finalizando redação - RedacaoId:', redacaoId);
    
    const tempoEscrita = document.createElement('input');
    tempoEscrita.type = 'hidden';
    tempoEscrita.name = 'tempo_escrita';
    tempoEscrita.value = getTempoTotalEmSegundos();
    form.appendChild(tempoEscrita);
    
    // Se for redação existente, adicionar ID
    if (redacaoId) {
        const redacaoIdInput = document.createElement('input');
        redacaoIdInput.type = 'hidden';
        redacaoIdInput.name = 'redacao_id';
        redacaoIdInput.value = redacaoId;
        form.appendChild(redacaoIdInput);
        console.log('Finalizar Redacao - Sending redacao_id:', redacaoId);
    } else {
        console.warn('AVISO: redacaoId não está definido!');
    }
    
    console.log('TempoEscrita adicionado:', getTempoTotalEmSegundos());
    
    // Verificar todos os campos antes de enviar
    console.log('=== VERIFICAÇÃO FINAL DO FORM ===');
    const formDataCheck = new FormData(form);
    for (let [key, value] of formDataCheck.entries()) {
        if (key === 'conteudo') {
            console.log(`${key}: length=${value.length}, preview=${value.substring(0, 100)}`);
        } else {
            console.log(`${key}: ${value}`);
        }
    }
    console.log('=== ENVIANDO FORM ===');
    
    // Adicionar ao DOM e submeter
    document.body.appendChild(form);
    
    // Mostrar loading
    const loadingModal = document.createElement('div');
    loadingModal.id = 'loadingModal';
    loadingModal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center';
    // Sempre mostrar mensagem de correção pelo professor
    loadingModal.innerHTML = `
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-8 text-center">
            <div class="w-20 h-20 bg-gradient-to-r from-blue-500 to-blue-600 rounded-full flex items-center justify-center mx-auto mb-6">
                <div class="animate-spin rounded-full h-12 w-12 border-4 border-white border-t-transparent"></div>
            </div>
                <h3 class="text-2xl font-bold text-gray-900 mb-3">Enviando Redação</h3>
                <p class="text-gray-600 mb-4">Sua redação está sendo enviada e será corrigida pelo professor. Em seguida, você continuará para a próxima etapa...</p>
            <div class="w-full bg-gray-200 rounded-full h-2 mb-4">
                <div class="bg-gradient-to-r from-blue-500 to-blue-600 h-2 rounded-full animate-pulse" style="width: 100%"></div>
            </div>
            <p class="text-sm text-gray-500">Aguarde enquanto processamos</p>
        </div>
    `;
    document.body.appendChild(loadingModal);
    
    form.submit();
}

// Carregar rascunho do servidor
window.addEventListener('load', function() {
    <?php if (isset($redacao_existente) && $redacao_existente): ?>
    const texto = document.getElementById('redacaoText').value;
    if (texto.trim()) {
        const wordCount = texto.trim() ? texto.trim().split(/\s+/).length : 0;
        document.getElementById('wordCount').textContent = wordCount;
    }
    
    // Restaurar tempo do rascunho (se houver campo tempo_escrita na redação)
    <?php if (isset($redacao_existente['tempo_escrita']) && $redacao_existente['tempo_escrita'] > 0): ?>
    tempoAcumulado = <?= intval($redacao_existente['tempo_escrita']) ?>;
    const tempoRestaurado = <?= intval($redacao_existente['tempo_escrita']) ?>;
    const hours = Math.floor(tempoRestaurado / 3600);
    const minutes = Math.floor((tempoRestaurado % 3600) / 60);
    const seconds = tempoRestaurado % 60;
    document.getElementById('timer').textContent = 
        String(hours).padStart(2, '0') + ':' + 
        String(minutes).padStart(2, '0') + ':' + 
        String(seconds).padStart(2, '0');
    <?php endif; ?>
    <?php endif; ?>

    setAutosaveStatus('Autosave ativo', 'info');
});

document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
        triggerImmediateAutosave();
    }
});

window.addEventListener('beforeunload', function() {
    triggerImmediateAutosave();
});
</script>
