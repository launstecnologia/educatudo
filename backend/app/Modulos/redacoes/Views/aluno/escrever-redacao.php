<div class="min-h-screen bg-gray-50">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <!-- Header -->
        <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
            <div class="flex items-center justify-between">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Redação ENEM</h1>
                    <p class="text-gray-600 mt-1">Pratique e aprimore suas habilidades de escrita</p>
                </div>
                <div class="flex gap-3">
                    <button onclick="window.location.href='<?= URL ?>/redacoes'" 
                        class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        <i class="fas fa-arrow-left mr-2"></i>Voltar
                    </button>
                    <button onclick="novoTema()" 
                        class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        <i class="fas fa-plus mr-2"></i>Novo Tema
                    </button>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
            <!-- Área Principal de Escrita -->
            <div class="lg:col-span-3">
                <!-- Tema da Redação -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="border-l-4 border-purple-500 pl-4">
                        <?php if ($tema || (isset($rascunho) && $rascunho['tema_texto'])): ?>
                            <?php 
                            // Se for rascunho sem tema na variável, usar tema_texto
                            if (!$tema && isset($rascunho) && $rascunho['tema_texto']) {
                                $tema_decodificado = json_decode($rascunho['tema_texto'], true);
                                if ($tema_decodificado) {
                                    $tema = $tema_decodificado;
                                } else {
                                    $tema = ['titulo' => $rascunho['tema_texto']];
                                }
                            }
                            ?>
                            <h2 class="text-xl font-bold text-gray-900 mb-2">
                                <?= htmlspecialchars($tema['titulo'] ?? 'Redação') ?>
                            </h2>
                            <div class="flex gap-2 mb-4">
                                <span class="px-2 py-1 bg-blue-100 text-blue-800 text-xs rounded-full">ENEM</span>
                                <span class="px-2 py-1 bg-green-100 text-green-800 text-xs rounded-full">Médio</span>
                            </div>
                            <div class="text-gray-700 mb-4">
                                <p class="mb-3"><?= htmlspecialchars($tema['descricao']) ?></p>
                                <?php if (isset($tema['proposta_intervencao'])): ?>
                                    <p class="mb-3"><strong>Proposta de Intervenção:</strong> <?= htmlspecialchars($tema['proposta_intervencao']) ?></p>
                                <?php endif; ?>
                                <?php if (isset($tema['contexto'])): ?>
                                    <p><strong>Contexto:</strong> <?= htmlspecialchars($tema['contexto']) ?></p>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <h2 class="text-xl font-bold text-gray-900 mb-2">
                                Tema não encontrado
                            </h2>
                            <p class="text-gray-600">Por favor, gere um tema primeiro.</p>
                        <?php endif; ?>
                    </div>
                    
                    <div class="mt-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Textos de apoio:
                        </label>
                        <div class="bg-gray-50 rounded-lg p-4 text-gray-500 text-sm">
                            <i class="fas fa-info-circle mr-2"></i>
                            Use seus conhecimentos adquiridos ao longo da sua formação
                        </div>
                    </div>
                </div>

                <!-- Área de Escrita -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-semibold text-gray-900">Redação</h3>
                        <div class="text-sm text-gray-500">
                            <span id="wordCount">0</span> palavras
                        </div>
                    </div>
                    
                    <textarea id="redacaoText" rows="20" 
                        class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent resize-none"
                        placeholder="Comece a escrever sua redação aqui..."
                        spellcheck="false"
                        autocorrect="off"
                        autocapitalize="off"
                        autocomplete="off"
                        oncontextmenu="return false"><?= isset($rascunho) ? htmlspecialchars($rascunho['conteudo'] ?? '') : '' ?></textarea>
                    
                    <div class="flex justify-between items-center mt-4">
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
            <div class="space-y-6">
                <!-- Cronômetro -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Cronômetro</h3>
                    <div class="text-center">
                        <div id="timer" class="text-3xl font-bold text-purple-600 mb-4">0:00</div>
                        <button id="timerBtn" onclick="toggleTimer()" 
                            class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            Iniciar
                        </button>
                    </div>
                </div>

                <!-- Metas -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Metas de Redação</h3>
                    <div class="space-y-3">
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span>Palavras</span>
                                <span id="wordCount">0</span>
                            </div>
                        </div>
                        <div>
                            <div class="flex justify-between text-sm mb-1">
                                <span>Tempo ideal</span>
                                <span id="timeProgress">0:00/90:00</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div id="timeBar" class="bg-green-600 h-2 rounded-full transition-all duration-300" style="width: 0%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estrutura Ideal -->
                <div class="bg-white rounded-lg shadow-sm p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Estrutura Ideal</h3>
                    <div class="space-y-2">
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-blue-500 rounded-full mr-3"></div>
                            <span class="text-sm">Introdução (20-25 linhas)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-green-500 rounded-full mr-3"></div>
                            <span class="text-sm">Desenvolvimento 1 (35-40 linhas)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-orange-500 rounded-full mr-3"></div>
                            <span class="text-sm">Desenvolvimento 2 (35-40 linhas)</span>
                        </div>
                        <div class="flex items-center">
                            <div class="w-3 h-3 bg-purple-500 rounded-full mr-3"></div>
                            <span class="text-sm">Conclusão (25-30 linhas)</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let timerInterval;
let startTime;
let isRunning = false;

// Contador de palavras e início automático do cronômetro
// Bloquear menu de contexto e verificação ortográfica
const redacaoTextarea = document.getElementById('redacaoText');
redacaoTextarea.addEventListener('contextmenu', function(e) {
    e.preventDefault();
    return false;
});

redacaoTextarea.addEventListener('input', function() {
    const text = this.value;
    const wordCount = text.trim() ? text.trim().split(/\s+/).length : 0;
    
    document.getElementById('wordCount').textContent = wordCount;
    
    // Iniciar cronômetro automaticamente quando começar a escrever
    if (wordCount > 0 && !isRunning) {
        toggleTimer();
    }
});

// Cronômetro
function toggleTimer() {
    const btn = document.getElementById('timerBtn');
    const timer = document.getElementById('timer');
    
    if (!isRunning) {
        startTime = Date.now();
        isRunning = true;
        btn.textContent = 'Pausar';
        btn.classList.remove('bg-purple-600', 'hover:bg-purple-700');
        btn.classList.add('bg-red-600', 'hover:bg-red-700');
        
        timerInterval = setInterval(updateTimer, 1000);
        
        // Atualizar imediatamente para mostrar 0:01
        updateTimer();
    } else {
        isRunning = false;
        btn.textContent = 'Continuar';
        btn.classList.remove('bg-red-600', 'hover:bg-red-700');
        btn.classList.add('bg-purple-600', 'hover:bg-purple-700');
        
        clearInterval(timerInterval);
    }
}

function updateTimer() {
    const elapsed = Math.floor((Date.now() - startTime) / 1000);
    const minutes = Math.floor(elapsed / 60);
    const seconds = elapsed % 60;
    
    const timeStr = `${minutes}:${seconds.toString().padStart(2, '0')}`;
    document.getElementById('timer').textContent = timeStr;
    document.getElementById('timeProgress').textContent = `${timeStr}/90:00`;
    
    const progress = Math.min((elapsed / (90 * 60)) * 100, 100);
    document.getElementById('timeBar').style.width = progress + '%';
}

function novoTema() {
    window.location.href = '<?= URL ?>/redacoes';
}

let rascunhoId = <?= isset($rascunho) && $rascunho['id'] ? $rascunho['id'] : 'null' ?>;
let temaTexto = <?= isset($tema) ? json_encode($tema) : 'null' ?>;

async function salvarRascunho() {
    const texto = document.getElementById('redacaoText').value;
    if (!texto.trim()) {
        alert('Digite algo antes de salvar!');
        return;
    }
    
    const titulo = '<?= isset($tema) && isset($tema['titulo']) ? htmlspecialchars($tema['titulo'], ENT_QUOTES) : 'Rascunho' ?>';
    
    try {
        const formData = new FormData();
        formData.append('_token', <?= json_encode($_SESSION['csrf_token'] ?? '') ?>);
        formData.append('titulo', titulo);
        formData.append('conteudo', texto);
        if (temaTexto) {
            formData.append('tema_texto', JSON.stringify(temaTexto));
        }
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
            showNotification(data.message || 'Rascunho salvo com sucesso!', 'success');
        } else {
            showNotification('Erro ao salvar rascunho: ' + (data.error || 'Erro desconhecido'), 'error');
        }
    } catch (error) {
        console.error('Erro:', error);
        showNotification('Erro de conexão. Tente novamente.', 'error');
    }
}


function finalizarRedacao() {
    const texto = document.getElementById('redacaoText').value;
    
    if (!texto.trim()) {
        alert('Por favor, escreva sua redação!');
        return;
    }
    
    // Se for rascunho, converter para redação final
    if (rascunhoId) {
        if (!confirm('Deseja finalizar esta redação? O rascunho será convertido em redação final e enviado para correção.')) {
            return;
        }
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
    titulo.value = '<?= isset($tema) && isset($tema['titulo']) ? htmlspecialchars($tema['titulo'], ENT_QUOTES) : 'Redação' ?>';
    form.appendChild(titulo);
    
    const temaInput = document.createElement('input');
    temaInput.type = 'hidden';
    temaInput.name = 'tema';
    temaInput.value = '<?= isset($tema) && isset($tema['titulo']) ? htmlspecialchars($tema['titulo'], ENT_QUOTES) : 'Redação' ?>';
    form.appendChild(temaInput);
    
    // Se for rascunho, adicionar ID para converter
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

// Carregar rascunho salvo (se vier do banco, já está carregado no PHP)
document.addEventListener('DOMContentLoaded', function() {
    // Se não tiver rascunho do banco, tentar localStorage como fallback
    <?php if (!isset($rascunho)): ?>
    const rascunho = localStorage.getItem('rascunho_redacao');
    if (rascunho) {
        document.getElementById('redacaoText').value = rascunho;
        // Atualizar contador de palavras
        document.getElementById('redacaoText').dispatchEvent(new Event('input'));
    }
    <?php else: ?>
    // Atualizar contador de palavras se houver rascunho do banco
    document.getElementById('redacaoText').dispatchEvent(new Event('input'));
    <?php endif; ?>
    
    // Auto-salvar rascunho a cada 30 segundos
    setInterval(() => {
        const texto = document.getElementById('redacaoText').value;
        if (texto.trim().length > 10) { // Salvar apenas se tiver mais de 10 caracteres
            salvarRascunho();
        }
    }, 30000);
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
