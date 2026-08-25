<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Tudinha do Milhão - Jogando' ?></title>
    <?php include __DIR__ . '/../../layouts/components/estilos_plataforma.php'; ?>
    <link rel="stylesheet" href="<?= URL ?>/assets/css/jogo-milhao.css">
    <!-- Game Security Script -->
    <script src="<?= URL ?>/app/Views/student/jogo-milhao/game-security.js?v=<?= time() ?>"></script>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token ?? '') ?>">
    
    <!-- Inicializar segurança imediatamente -->
    <script>
        console.log('🔐 Inicializando Game Security...');
        console.log('📝 SessionStorage:', sessionStorage.getItem('shouldGoFullscreen'));
    </script>
    
</head>
<body>

<div class="game-bg w-screen h-screen relative">
    <!-- Container Principal com Grid -->
    <div class="w-full h-full grid grid-cols-[1fr_240px] gap-4 relative z-10 p-4">
        
        <!-- Coluna Esquerda - Jogo -->
        <div class="flex flex-col justify-between">
            
            <!-- Header com Logo, Badges e Categoria -->
            <div class="flex items-center justify-between mb-2">
                <!-- Logo Tudinha do Milhão -->
                <div class="flex items-center">
                    <img src="<?= URL ?>/public/uploads/layout/tudina_do_milhao.png" 
                         alt="Tudinha do Milhão Logo" 
                         class="h-20 w-auto drop-shadow-xl">
                </div>

                <!-- Barra de Progresso Central -->
                <div class="flex items-center gap-3">
                    <div class="score-badge rounded-full px-5 py-2 flex items-center gap-2">
                        <span class="text-xl">👑</span>
                        <span class="text-white font-black text-lg">+<span id="crown-count">0</span></span>
                    </div>
                    
                    <div class="score-badge rounded-full px-5 py-2 flex items-center gap-2">
                        <span class="text-xl">🏛️</span>
                        <span class="text-white font-black text-lg">+<span id="temple-count">0</span></span>
                    </div>
                    
                    <div class="score-badge rounded-full px-6 py-2 flex items-center gap-2">
                        <span class="text-yellow-400 text-2xl">💰</span>
                        <span class="text-white font-black text-xl" id="money-count">0</span>
                    </div>
                </div>
                
                <!-- Badge de Categoria -->
                <div class="category-badge rounded-full px-6 py-2">
                    <span class="text-white font-black tracking-wider text-lg">CLÁSSICO</span>
                </div>
            </div>
            
            <!-- Área da Pergunta -->
            <div class="question-card rounded-3xl p-4 mb-2">
                <h2 class="text-white font-black text-center leading-tight text-xl" id="question-text">
                    Carregando pergunta...
                </h2>
                <input type="hidden" id="pergunta-id" value="">
            </div>
            
            <!-- Alternativas -->
            <div class="grid grid-cols-1 gap-2 mb-2">
                <button class="alternative-btn rounded-full flex items-center group py-2 px-5 gap-3" onclick="selecionarResposta('A')">
                    <div class="alternative-number rounded-full flex items-center justify-center text-white font-black flex-shrink-0" 
                         style="width: 36px; height: 36px; font-size: 16px;">
                        A
                    </div>
                    <span class="text-white font-black flex-1 text-left text-base" id="alt-A">Carregando...</span>
                </button>
                
                <button class="alternative-btn rounded-full flex items-center group py-2 px-5 gap-3" onclick="selecionarResposta('B')">
                    <div class="alternative-number rounded-full flex items-center justify-center text-white font-black flex-shrink-0" 
                         style="width: 36px; height: 36px; font-size: 16px;">
                        B
                    </div>
                    <span class="text-white font-black flex-1 text-left text-base" id="alt-B">Carregando...</span>
                </button>
                
                <button class="alternative-btn rounded-full flex items-center group py-2 px-5 gap-3" onclick="selecionarResposta('C')">
                    <div class="alternative-number rounded-full flex items-center justify-center text-white font-black flex-shrink-0" 
                         style="width: 36px; height: 36px; font-size: 16px;">
                        C
                    </div>
                    <span class="text-white font-black flex-1 text-left text-base" id="alt-C">Carregando...</span>
                </button>
                
                <button class="alternative-btn rounded-full flex items-center group py-2 px-5 gap-3" onclick="selecionarResposta('D')">
                    <div class="alternative-number rounded-full flex items-center justify-center text-white font-black flex-shrink-0" 
                         style="width: 36px; height: 36px; font-size: 16px;">
                        D
                    </div>
                    <span class="text-white font-black flex-1 text-left text-base" id="alt-D">Carregando...</span>
                </button>
            </div>
            
            <!-- Botões de Ajuda -->
            <div class="flex justify-center gap-3 mb-2">
                <button id="help-plateia" class="help-btn rounded-xl flex flex-col items-center justify-center px-4 py-3 gap-1" onclick="usarAjuda('plateia')">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>
                    </svg>
                    <span class="text-white font-bold text-xs">CARTAS</span>
                </button>
                
                <button id="help-universitarios" class="help-btn rounded-xl flex flex-col items-center justify-center px-4 py-3 gap-1" onclick="usarAjuda('universitarios')">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
                    </svg>
                    <span class="text-white font-bold text-xs">UNIVERSIT.</span>
                </button>
                
                <button id="help-pular" class="help-btn rounded-xl flex flex-col items-center justify-center px-4 py-3 gap-1" onclick="usarAjuda('pular')">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14H9V8h2v8zm4 0h-2V8h2v8z"/>
                    </svg>
                    <span class="text-white font-bold text-xs">PULAR</span>
                </button>
                
                <button id="exit-game" class="help-btn rounded-xl flex flex-col items-center justify-center px-4 py-3 gap-1" onclick="fecharJogo()">
                    <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm5 13.59L15.59 17 12 13.41 8.41 17 7 15.59 10.59 12 7 8.41 8.41 7 12 10.59 15.59 7 17 8.41 13.41 12 17 15.59z"/>
                    </svg>
                    <span class="text-white font-bold text-xs">SAIR</span>
                </button>
            </div>
        </div>
        
        <!-- Coluna Direita - Tudinha -->
        <div class="flex items-center justify-center relative z-50">
            <img src="<?= URL ?>/public/uploads/layout/tudinha.png" 
                 alt="Tudinha" 
                 class="tudinha-sem-contorno h-auto max-h-[600px] w-auto object-contain">
        </div>
    </div>
</div>

<!-- Modal de Ajuda -->
<div id="helpModal" class="fixed inset-0 bg-black bg-opacity-50 hidden" style="z-index: 9999;">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full max-h-[80vh] overflow-hidden">
            <!-- Header do Modal -->
            <div class="bg-gradient-to-r from-blue-600 to-purple-600 p-6 text-white">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-12 h-12 bg-white bg-opacity-20 rounded-full flex items-center justify-center">
                            <span class="text-2xl" id="helpModalIcon">🎓</span>
                        </div>
                        <div>
                            <h3 class="text-xl font-bold" id="helpModalTitle">Ajuda dos Universitários</h3>
                            <p class="text-blue-100 text-sm" id="helpModalSubtitle">Opiniões dos especialistas</p>
                        </div>
                    </div>
                    <button onclick="fecharModalAjuda()" class="text-white hover:text-gray-200 transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                        </svg>
                    </button>
                </div>
            </div>
            
            <!-- Conteúdo do Modal -->
            <div class="p-6 overflow-y-auto max-h-[60vh]">
                <div id="helpModalContent">
                    <!-- Conteúdo será inserido aqui via JavaScript -->
                </div>
            </div>
            
            <!-- Footer do Modal -->
            <div class="bg-gray-50 px-6 py-4 flex justify-end">
                <button onclick="fecharModalAjuda()" class="bg-blue-600 text-white px-6 py-2 rounded-lg hover:bg-blue-700 transition-colors font-semibold">
                    Entendi!
                </button>
            </div>
        </div>
    </div>
</div>

<script>
let currentPartidaId = null;
let currentPergunta = null;
let ajudasUsadas = {
    plateia: false,
    universitarios: false,
    pular: false
};
let heartbeatInterval = null;

// Inicializar jogo quando a página carregar
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se há partida ativa
    fetch('<?= URL ?>/jogo-milhao/verificar-partida', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: new FormData()
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.partida_ativa) {
            console.log('Partida ativa encontrada:', data.partida_id);
            currentPartidaId = data.partida_id;
            iniciarHeartbeat();
            // Continuar partida existente
            continuarPartida();
        } else {
            console.log('Nenhuma partida ativa - iniciando nova');
            // Iniciar nova partida
            iniciarPartida();
        }
    })
    .catch(error => {
        console.error('Erro ao verificar partida:', error);
        // Em caso de erro, tentar iniciar nova partida
        iniciarPartida();
    });
});

// Funções do jogo (copiadas do index.php)
function iniciarPartida() {
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    
    fetch('<?= URL ?>/jogo-milhao/iniciar', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentPartidaId = data.partida_id;
            iniciarHeartbeat();
            carregarJogo(data);
        } else {
            showNotification(data.message || 'Erro ao iniciar partida', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

function continuarPartida() {
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    formData.append('partida_id', currentPartidaId);
    
    fetch('<?= URL ?>/jogo-milhao/continuar', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            carregarJogo(data);
        } else {
            showNotification(data.message || 'Erro ao continuar partida', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

function carregarJogo(data) {
    // Resetar flag de processamento para permitir novas respostas
    isProcessing = false;
    
    // Atualizar interface com dados da pergunta
    document.getElementById('question-text').textContent = data.pergunta.pergunta;
    document.getElementById('pergunta-id').value = data.pergunta.id;
    document.getElementById('alt-A').textContent = data.pergunta.alternativa_a;
    document.getElementById('alt-B').textContent = data.pergunta.alternativa_b;
    document.getElementById('alt-C').textContent = data.pergunta.alternativa_c;
    document.getElementById('alt-D').textContent = data.pergunta.alternativa_d;
    
    // Atualizar pontuação
    document.getElementById('money-count').textContent = data.premio_atual || 0;
    
    // Resetar ajudas
    ajudasUsadas = {
        plateia: false,
        universitarios: false,
        pular: false
    };
    
    // Atualizar botões de ajuda
    document.getElementById('help-plateia').disabled = false;
    document.getElementById('help-universitarios').disabled = false;
    document.getElementById('help-pular').disabled = false;
    
    // REABILITAR TODOS OS BOTÕES DE ALTERNATIVAS
    document.querySelectorAll('.alternative-btn').forEach(btn => {
        btn.disabled = false;
        btn.style.opacity = '1';
        btn.style.cursor = 'pointer';
        btn.classList.remove('selected', 'wrong');
    });
}

let isProcessing = false; // Flag para prevenir múltiplos cliques

function selecionarResposta(resposta) {
    if (!currentPartidaId) {
        showNotification('Erro: Nenhuma partida ativa encontrada. Recarregue a página.', 'error');
        return;
    }
    
    // PREVENIR MÚLTIPLOS CLIQUES
    if (isProcessing) {
        console.log('Resposta já sendo processada, ignorando clique...');
        return;
    }
    
    // Marcar como processando e desabilitar botões
    isProcessing = true;
    document.querySelectorAll('.alternative-btn').forEach(btn => {
        btn.disabled = true;
        btn.style.opacity = '0.5';
        btn.style.cursor = 'not-allowed';
    });
    
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    formData.append('partida_id', currentPartidaId);
    formData.append('pergunta_id', document.getElementById('pergunta-id').value);
    formData.append('resposta', resposta);
    formData.append('ajuda_usada', 'nenhuma');
    
    fetch('<?= URL ?>/jogo-milhao/responder', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.acertou) {
                showNotification('Resposta correta!', 'success');
                setTimeout(() => {
                    isProcessing = false; // Resetar flag
                    if (data.proxima_pergunta) {
                        const gameData = {
                            pergunta: data.proxima_pergunta,
                            premio_atual: data.premio_atual,
                            pergunta_numero: data.pergunta_numero
                        };
                        carregarJogo(gameData);
                    } else {
                        showNotification('Parabéns! Você ganhou o jogo!', 'success');
                        setTimeout(() => {
                            window.location.href = '<?= URL ?>/jogo-milhao';
                        }, 2000);
                    }
                }, 1500);
            } else {
                showNotification('Resposta incorreta! Fim de jogo.', 'error');
                setTimeout(() => {
                    isProcessing = false; // Resetar flag
                    window.location.href = '<?= URL ?>/jogo-milhao';
                }, 2000);
            }
        } else {
            isProcessing = false; // Resetar flag em caso de erro
            showNotification(data.message || 'Erro ao processar resposta', 'error');
        }
    })
    .catch(error => {
        isProcessing = false; // Resetar flag
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

function usarAjuda(tipo) {
    if (ajudasUsadas[tipo]) return;
    
    if (!currentPartidaId) {
        showNotification('Erro: Nenhuma partida ativa encontrada.', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    formData.append('partida_id', currentPartidaId);
    formData.append('tipo_ajuda', tipo);
    formData.append('pergunta_id', document.getElementById('pergunta-id').value);
    
    fetch('<?= URL ?>/jogo-milhao/ajuda', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            ajudasUsadas[tipo] = true;
            showNotification(`Ajuda "${tipo}" utilizada!`, 'info');
            
            // Atualizar botão
            const btn = document.querySelector(`[onclick="usarAjuda('${tipo}')"]`);
            btn.disabled = true;
            btn.style.opacity = '0.5';
            
            // Processar resultado da ajuda
            if (data.resultado) {
                processarResultadoAjuda(tipo, data.resultado);
            }
        } else {
            showNotification(data.message || 'Erro ao usar ajuda', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

function fecharModalAjuda() {
    document.getElementById('helpModal').classList.add('hidden');
}

function mostrarModalAjuda(tipo, resultado) {
    const modal = document.getElementById('helpModal');
    const icon = document.getElementById('helpModalIcon');
    const title = document.getElementById('helpModalTitle');
    const subtitle = document.getElementById('helpModalSubtitle');
    const content = document.getElementById('helpModalContent');
    
    if (tipo === 'plateia') {
        icon.textContent = '👥';
        title.textContent = 'Ajuda da Plateia';
        subtitle.textContent = 'Votação da audiência';
        
        let html = '<div class="space-y-4">';
        html += '<div class="text-center mb-6">';
        html += '<h4 class="text-lg font-semibold text-gray-800 mb-2">A plateia votou!</h4>';
        html += '<p class="text-gray-600">Veja como a audiência se dividiu:</p>';
        html += '</div>';
        
        const alternativas = ['A', 'B', 'C', 'D'];
        alternativas.forEach(alt => {
            if (resultado[alt]) {
                const percentual = resultado[alt];
                const barWidth = percentual;
                html += `
                    <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                        <div class="w-8 h-8 bg-blue-600 text-white rounded-full flex items-center justify-center font-bold text-sm">
                            ${alt}
                        </div>
                        <div class="flex-1">
                            <div class="flex justify-between items-center mb-1">
                                <span class="text-gray-700 font-medium">Alternativa ${alt}</span>
                                <span class="text-blue-600 font-bold">${percentual}%</span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2">
                                <div class="bg-blue-600 h-2 rounded-full transition-all duration-500" style="width: ${barWidth}%"></div>
                            </div>
                        </div>
                    </div>
                `;
            }
        });
        
        html += '</div>';
        content.innerHTML = html;
        
    } else if (tipo === 'universitarios') {
        icon.textContent = '🎓';
        title.textContent = 'Ajuda dos Universitários';
        subtitle.textContent = 'Opiniões dos especialistas';
        
        let html = '<div class="space-y-4">';
        html += '<div class="text-center mb-6">';
        html += '<h4 class="text-lg font-semibold text-gray-800 mb-2">Os universitários opinaram!</h4>';
        html += '<p class="text-gray-600">Veja as opiniões dos especialistas:</p>';
        html += '</div>';
        
        if (resultado.opinioes && Array.isArray(resultado.opinioes)) {
            // Contar votos por alternativa
            const votos = { A: 0, B: 0, C: 0, D: 0 };
            resultado.opinioes.forEach(opiniao => {
                if (votos.hasOwnProperty(opiniao.resposta)) {
                    votos[opiniao.resposta]++;
                }
            });
            
            // Calcular percentuais
            const total = resultado.opinioes.length;
            const percentuais = {};
            Object.keys(votos).forEach(alt => {
                percentuais[alt] = Math.round((votos[alt] / total) * 100);
            });
            
            // Mostrar percentuais
            const alternativas = ['A', 'B', 'C', 'D'];
            alternativas.forEach(alt => {
                if (percentuais[alt]) {
                    const barWidth = percentuais[alt];
                    html += `
                        <div class="flex items-center gap-3 p-3 bg-gray-50 rounded-lg">
                            <div class="w-8 h-8 bg-purple-600 text-white rounded-full flex items-center justify-center font-bold text-sm">
                                ${alt}
                            </div>
                            <div class="flex-1">
                                <div class="flex justify-between items-center mb-1">
                                    <span class="text-gray-700 font-medium">Alternativa ${alt}</span>
                                    <span class="text-purple-600 font-bold">${percentuais[alt]}%</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2">
                                    <div class="bg-purple-600 h-2 rounded-full transition-all duration-500" style="width: ${barWidth}%"></div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            });
            
            // Mostrar detalhes dos universitários
            html += '<div class="mt-6 p-4 bg-blue-50 rounded-lg">';
            html += '<h5 class="font-semibold text-blue-800 mb-3">Detalhes das Opiniões:</h5>';
            html += '<div class="space-y-2">';
            
            resultado.opinioes.forEach(opiniao => {
                const confiancaColor = opiniao.confianca >= 70 ? 'text-green-600' : opiniao.confianca >= 50 ? 'text-yellow-600' : 'text-red-600';
                html += `
                    <div class="flex items-center justify-between p-2 bg-white rounded border">
                        <div>
                            <span class="font-medium text-gray-800">${opiniao.nome}</span>
                            <span class="text-gray-500 text-sm">(${opiniao.curso})</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="px-2 py-1 bg-blue-100 text-blue-800 rounded text-sm font-bold">${opiniao.resposta}</span>
                            <span class="${confiancaColor} font-semibold text-sm">${opiniao.confianca}%</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div></div>';
        }
        
        html += '</div>';
        content.innerHTML = html;
    }
    
    modal.classList.remove('hidden');
}

function processarResultadoAjuda(tipo, resultado) {
    if (tipo === 'plateia') {
        const percentuais = resultado.percentuais || resultado;
        const alternativas = ['A', 'B', 'C', 'D'];
        
        alternativas.forEach(alt => {
            const elemento = document.getElementById(`alt-${alt}`);
            if (elemento && percentuais[alt]) {
                const percentual = percentuais[alt];
                elemento.innerHTML = `${elemento.textContent} <span class="text-yellow-300 font-bold">(${percentual}%)</span>`;
            }
        });
        
        mostrarModalAjuda('plateia', percentuais);
        
    } else if (tipo === 'universitarios') {
        if (resultado.opinioes && Array.isArray(resultado.opinioes)) {
            const votos = { A: 0, B: 0, C: 0, D: 0 };
            resultado.opinioes.forEach(opiniao => {
                if (votos.hasOwnProperty(opiniao.resposta)) {
                    votos[opiniao.resposta]++;
                }
            });
            
            const total = resultado.opinioes.length;
            const percentuais = {};
            Object.keys(votos).forEach(alt => {
                percentuais[alt] = Math.round((votos[alt] / total) * 100);
            });
            
            const alternativas = ['A', 'B', 'C', 'D'];
            alternativas.forEach(alt => {
                const elemento = document.getElementById(`alt-${alt}`);
                if (elemento && percentuais[alt]) {
                    elemento.innerHTML = `${elemento.textContent} <span class="text-blue-300 font-bold">(${percentuais[alt]}%)</span>`;
                }
            });
            
            mostrarModalAjuda('universitarios', resultado);
        }
    }
}

function fecharJogo() {
    if (confirm('⚠️ ATENÇÃO!\n\nSe você sair agora, perderá toda a partida e terá que começar do zero!\n\nTem certeza que deseja sair?')) {
        if (currentPartidaId) {
            const formData = new FormData();
            formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
            formData.append('partida_id', currentPartidaId);
            
            fetch('<?= URL ?>/jogo-milhao/abandonar', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log('Partida abandonada:', data);
                pararHeartbeat();
                currentPartidaId = null;
            })
            .catch(error => {
                console.error('Erro ao abandonar partida:', error);
                pararHeartbeat();
                currentPartidaId = null;
            });
        }
        
        window.location.href = '<?= URL ?>/jogo-milhao';
    }
}

function iniciarHeartbeat() {
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
    }
    heartbeatInterval = setInterval(() => {
        if (currentPartidaId) {
            fetch('<?= URL ?>/jogo-milhao/heartbeat', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData()
            }).catch(() => {
                encerrarPartidaAutomaticamente();
                clearInterval(heartbeatInterval);
            });
        }
    }, 30000);
}

function pararHeartbeat() {
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
        heartbeatInterval = null;
    }
}

function encerrarPartidaAutomaticamente() {
    if (currentPartidaId) {
        const formData = new FormData();
        formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
        formData.append('partida_id', currentPartidaId);
        
        if (navigator.sendBeacon) {
            const data = new URLSearchParams(formData);
            navigator.sendBeacon('<?= URL ?>/jogo-milhao/abandonar', data);
        } else {
            fetch('<?= URL ?>/jogo-milhao/abandonar', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                keepalive: true
            }).catch(() => {});
        }
    }
}

window.addEventListener('beforeunload', function(e) {
    encerrarPartidaAutomaticamente();
});

// Função para mostrar notificações
function showNotification(message, type) {
    // Criar elemento de notificação
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' : 
        type === 'error' ? 'bg-red-500 text-white' : 
        'bg-blue-500 text-white'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    // Remover após 3 segundos
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>
</body>
</html>
