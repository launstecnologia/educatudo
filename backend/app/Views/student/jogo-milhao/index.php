<?php
/**
 * EducaTudo - Tudinha do Milhão
 * Interface imersiva do jogo inspirada no Show do Milhão
 */

$premios = $data['premios'] ?? [];
$stats = $data['stats'] ?? [];
$partidaAtiva = $data['partidaAtiva'] ?? null;
?>

<link rel="stylesheet" href="<?= URL ?>/assets/css/jogo-milhao.css">

<div class="lobby-bg h-screen flex items-center justify-center relative overflow-hidden">
    
    <!-- Container Principal -->
    <div class="w-full h-full flex max-w-full">
        
        <!-- Coluna 9 - Conteúdo Principal -->
        <div class="w-9/12 flex flex-col justify-center items-center px-8 py-2 relative z-10">
            
            <!-- Logo e Título -->
            <div class="text-center mb-4">
                <img src="<?= URL ?>/public/uploads/layout/tudina_do_milhao.png" 
                     alt="Tudinha do Milhão Logo" 
                     class="h-24 w-auto mx-auto mb-2">
                <h1 class="text-white text-4xl font-black drop-shadow-lg">
                    Tudinha do Milhão
                </h1>
            </div>

            <!-- Cards de Estatísticas -->
            <div class="grid grid-cols-4 gap-4 mb-4 w-full">
                <!-- Partidas Jogadas -->
                <div class="card-stat p-4 text-center">
                    <div class="icon-circle mx-auto mb-2" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); width: 40px; height: 40px; font-size: 18px;">
                        🎮
                    </div>
                    <h3 class="text-2xl font-black text-gray-800 mb-1"><?= $stats['total_partidas'] ?? 0 ?></h3>
                    <p class="text-gray-600 font-bold text-sm">Partidas Jogadas</p>
                </div>

                <!-- Vitórias -->
                <div class="card-stat p-4 text-center">
                    <div class="icon-circle mx-auto mb-2" style="background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%); width: 40px; height: 40px; font-size: 18px;">
                        🏆
            </div>
                    <h3 class="text-2xl font-black text-gray-800 mb-1"><?= $stats['partidas_vencidas'] ?? 0 ?></h3>
                    <p class="text-gray-600 font-bold text-sm">Vitórias</p>
        </div>

                <!-- Maior Prêmio -->
                <div class="card-stat p-4 text-center">
                    <div class="icon-circle mx-auto mb-2" style="background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%); width: 40px; height: 40px; font-size: 18px;">
                        💰
            </div>
                    <h3 class="text-lg font-black text-gray-800 mb-1">R$ <?= number_format($stats['maior_premio'] ?? 0, 2, ',', '.') ?></h3>
                    <p class="text-gray-600 font-bold text-sm">Maior Prêmio</p>
            </div>

                <!-- Taxa de Acerto -->
                <div class="card-stat p-4 text-center">
                    <div class="icon-circle mx-auto mb-2" style="background: linear-gradient(135deg, #9b59b6 0%, #8e44ad 100%); width: 40px; height: 40px; font-size: 18px;">
                        📊
            </div>
                    <h3 class="text-2xl font-black text-gray-800 mb-1"><?= $stats['taxa_acerto'] ?? 0 ?>%</h3>
                    <p class="text-gray-600 font-bold text-sm">Taxa de Acerto</p>
            </div>
        </div>

            <!-- Botão de Começar -->
            <div class="mb-4 relative z-10">
                <a href="<?= URL ?>/jogo-milhao/jogar" class="btn-start-green text-white font-black text-lg py-3 px-8 rounded-xl uppercase tracking-wider flex items-center gap-2 relative z-20 cursor-pointer" onclick="ativarFullscreenAntesJogar(event)">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M6.3 2.841A1.5 1.5 0 004 4.11V15.89a1.5 1.5 0 002.3 1.269l9.344-5.89a1.5 1.5 0 000-2.538L6.3 2.84z"/>
                    </svg>
                    Começar Nova Partida
                </a>
        </div>
        
        <script>
        function ativarFullscreenAntesJogar(e) {
            console.log('🖱️ Botão "Começar Partida" clicado!');
            // Marcar que deve ativar fullscreen
            sessionStorage.setItem('shouldGoFullscreen', 'true');
            console.log('✅ SessionStorage definido:', sessionStorage.getItem('shouldGoFullscreen'));
        }
        
        // Debug: verificar sessionStorage ao carregar
        console.log('📝 SessionStorage atual:', sessionStorage.getItem('shouldGoFullscreen'));
        </script>

            <!-- Como Jogar -->
            <div class="how-to-play p-4 w-full">
                <h3 class="text-lg font-black text-gray-800 mb-3 text-center flex items-center justify-center gap-2">
                    📖 Como Jogar
                </h3>
                <div class="grid grid-cols-3 gap-4 text-center">
                    <div>
                        <div class="text-3xl mb-2">❓</div>
                        <p class="text-gray-800 font-bold text-sm leading-tight">Responda perguntas</p>
                    </div>
                    <div>
                        <div class="text-3xl mb-2">💡</div>
                        <p class="text-gray-800 font-bold text-sm leading-tight">Use ajudas</p>
                    </div>
                    <div>
                        <div class="text-3xl mb-2">🏆</div>
                        <p class="text-gray-800 font-bold text-sm leading-tight">Chegue ao milhão!</p>
                    </div>
                    </div>
            </div>
        </div>

        <!-- Coluna 3 - Imagem da Tudinha -->
        <div class="w-3/12 flex items-center justify-center relative z-50">
            <img src="<?= URL ?>/public/uploads/layout/tudinha.png" 
                 alt="Tudinha" 
                 class="tudinha-sem-contorno h-auto max-h-[500px] w-auto object-contain">
        </div>
    </div>
</div>

<!-- Modal do Jogo Imersivo -->
<div id="gameModal" class="fixed inset-0 game-modal hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="game-stage">
            <div id="gameContent">
                <!-- Conteúdo do jogo será carregado aqui -->
            </div>
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

// Função para encerrar partida automaticamente
function encerrarPartidaAutomaticamente() {
    if (currentPartidaId) {
        const formData = new FormData();
        formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
        formData.append('partida_id', currentPartidaId);
        
        // Usar sendBeacon para garantir que a requisição seja enviada mesmo ao fechar a página
        if (navigator.sendBeacon) {
            const data = new URLSearchParams(formData);
            navigator.sendBeacon('<?= URL ?>/jogo-milhao/abandonar', data);
        } else {
            // Fallback para navegadores que não suportam sendBeacon
            fetch('<?= URL ?>/jogo-milhao/abandonar', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: formData,
                keepalive: true
            }).catch(() => {}); // Ignorar erros pois a página está fechando
        }
    }
}

// Função para iniciar heartbeat (ping periódico)
function iniciarHeartbeat() {
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
    }
    
    heartbeatInterval = setInterval(() => {
        if (currentPartidaId) {
            // Ping simples para manter a partida ativa
            fetch('<?= URL ?>/jogo-milhao/heartbeat', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                },
                body: new FormData()
            }).catch(() => {
                // Se o ping falhar, encerrar a partida
                encerrarPartidaAutomaticamente();
                clearInterval(heartbeatInterval);
            });
        }
    }, 30000); // Ping a cada 30 segundos
}

// Função para parar heartbeat
function pararHeartbeat() {
    if (heartbeatInterval) {
        clearInterval(heartbeatInterval);
        heartbeatInterval = null;
    }
}

// Detectar quando o usuário está saindo da página
window.addEventListener('beforeunload', function(e) {
    encerrarPartidaAutomaticamente();
});

// Detectar quando a página perde o foco (usuário mudou de aba/janela)
window.addEventListener('blur', function() {
    // Opcional: encerrar após um tempo sem foco
    setTimeout(() => {
        if (document.hidden && currentPartidaId) {
            encerrarPartidaAutomaticamente();
        }
    }, 60000); // 1 minuto sem foco
});

// Detectar quando a página volta a ter foco
window.addEventListener('focus', function() {
    // Verificar se ainda há partida ativa
    if (currentPartidaId) {
        fetch('<?= URL ?>/jogo-milhao/verificar-partida', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: new FormData()
        })
        .then(response => response.json())
        .then(data => {
            if (!data.partida_ativa) {
                // Partida foi encerrada, limpar estado local
                currentPartidaId = null;
                pararHeartbeat();
                location.reload(); // Recarregar para voltar ao lobby
            }
        })
        .catch(() => {
            // Em caso de erro, assumir que a partida foi encerrada
            currentPartidaId = null;
            pararHeartbeat();
        });
    }
});

// Verificar partidas órfãs ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se há partida ativa e limpar se necessário
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
            // Verificar se a partida é muito antiga (mais de 5 minutos)
            // Se for, pode ser uma partida órfã que não foi limpa
            console.warn('ATENÇÃO: Partida órfã detectada! O sistema deveria ter limpo automaticamente.');
        } else if (data.success && !data.partida_ativa) {
            console.log('Nenhuma partida ativa encontrada - sistema limpo');
        }
    })
    .catch(() => {
        // Ignorar erros de verificação
    });
});

// Função para forçar limpeza de partidas órfãs
function limparPartidasOrfas() {
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    
    fetch('<?= URL ?>/jogo-milhao/limpar-orfas', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('Partidas órfãs limpas com sucesso');
        }
    })
    .catch(error => {
        console.error('Erro ao limpar partidas órfãs:', error);
    });
}

// Iniciar nova partida
function iniciarPartida() {
    // Primeiro, tentar limpar partidas órfãs
    limparPartidasOrfas();
    
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
            iniciarHeartbeat(); // Iniciar heartbeat para monitorar a partida
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


// Variáveis globais do timer
let timerInterval = null;
let tempoRestante = 15;
let tempoInicio = null;
let respostaBloqueada = false;

// Carregar interface do jogo imersiva
function carregarJogo(data) {
    const gameContent = document.getElementById('gameContent');
    
    // Resetar timer
    tempoRestante = 15;
    respostaBloqueada = false;
    tempoInicio = Date.now();
    
    gameContent.innerHTML = `
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
                                <span class="text-white font-black text-xl" id="money-count">${data.premio_atual || 0}</span>
                </div>
            </div>

                        <!-- Badge de Categoria -->
                        <div class="category-badge rounded-full px-6 py-2">
                            <span class="text-white font-black tracking-wider text-lg">CLÁSSICO</span>
                        </div>
            </div>

                    <!-- Timer de 15 segundos -->
                    <div class="mb-2 flex justify-center">
                        <div class="timer-container bg-black bg-opacity-50 rounded-full px-6 py-2 flex items-center gap-3">
                            <svg class="w-6 h-6 text-yellow-400" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8zm.5-13H11v6l5.25 3.15.75-1.23-4.5-2.67z"/>
                            </svg>
                            <span class="text-white font-black text-2xl" id="timer-countdown">15</span>
                            <span class="text-white text-sm">segundos</span>
                        </div>
                    </div>

                    <!-- Área da Pergunta -->
                    <div class="question-card rounded-3xl p-4 mb-2">
                        <h2 class="text-white font-black text-center leading-tight text-xl" id="question-text">
                            ${data.pergunta.pergunta}
                        </h2>
                        <input type="hidden" id="pergunta-id" value="${data.pergunta.id}">
            </div>

                    <!-- Alternativas - COMPACTADAS -->
                    <div class="grid grid-cols-1 gap-2 mb-2">
                        <button class="alternative-btn rounded-full flex items-center group py-2 px-5 gap-3" onclick="selecionarResposta('A')">
                            <div class="alternative-number rounded-full flex items-center justify-center text-white font-black flex-shrink-0" 
                                 style="width: 36px; height: 36px; font-size: 16px;">
                                A
                            </div>
                            <span class="text-white font-black flex-1 text-left text-base" id="alt-A">${data.pergunta.alternativa_a}</span>
                </button>

                        <button class="alternative-btn rounded-full flex items-center group py-2 px-5 gap-3" onclick="selecionarResposta('B')">
                            <div class="alternative-number rounded-full flex items-center justify-center text-white font-black flex-shrink-0" 
                                 style="width: 36px; height: 36px; font-size: 16px;">
                                B
                            </div>
                            <span class="text-white font-black flex-1 text-left text-base" id="alt-B">${data.pergunta.alternativa_b}</span>
                </button>

                        <button class="alternative-btn rounded-full flex items-center group py-2 px-5 gap-3" onclick="selecionarResposta('C')">
                            <div class="alternative-number rounded-full flex items-center justify-center text-white font-black flex-shrink-0" 
                                 style="width: 36px; height: 36px; font-size: 16px;">
                                C
                            </div>
                            <span class="text-white font-black flex-1 text-left text-base" id="alt-C">${data.pergunta.alternativa_c}</span>
                </button>

                        <button class="alternative-btn rounded-full flex items-center group py-2 px-5 gap-3" onclick="selecionarResposta('D')">
                            <div class="alternative-number rounded-full flex items-center justify-center text-white font-black flex-shrink-0" 
                                 style="width: 36px; height: 36px; font-size: 16px;">
                                D
                            </div>
                            <span class="text-white font-black flex-1 text-left text-base" id="alt-D">${data.pergunta.alternativa_d}</span>
                </button>
            </div>

            <!-- Botões de Ajuda -->
                    <div class="flex justify-center gap-3 mb-2">
                        <button id="help-plateia" class="help-btn rounded-xl flex flex-col items-center justify-center px-4 py-3 gap-1" onclick="usarAjuda('plateia')" ${ajudasUsadas.plateia ? 'disabled' : ''}>
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M21 5c-1.11-.35-2.33-.5-3.5-.5-1.95 0-4.05.4-5.5 1.5-1.45-1.1-3.55-1.5-5.5-1.5S2.45 4.9 1 6v14.65c0 .25.25.5.5.5.1 0 .15-.05.25-.05C3.1 20.45 5.05 20 6.5 20c1.95 0 4.05.4 5.5 1.5 1.35-.85 3.8-1.5 5.5-1.5 1.65 0 3.35.3 4.75 1.05.1.05.15.05.25.05.25 0 .5-.25.5-.5V6c-.6-.45-1.25-.75-2-1zm0 13.5c-1.1-.35-2.3-.5-3.5-.5-1.7 0-4.15.65-5.5 1.5V8c1.35-.85 3.8-1.5 5.5-1.5 1.2 0 2.4.15 3.5.5v11.5z"/>
                            </svg>
                            <span class="text-white font-bold text-xs">CARTAS</span>
                        </button>

                        <button id="help-universitarios" class="help-btn rounded-xl flex flex-col items-center justify-center px-4 py-3 gap-1" onclick="usarAjuda('universitarios')" ${ajudasUsadas.universitarios ? 'disabled' : ''}>
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/>
                            </svg>
                            <span class="text-white font-bold text-xs">UNIVERSIT.</span>
                </button>

                        <button id="help-pular" class="help-btn rounded-xl flex flex-col items-center justify-center px-4 py-3 gap-1" onclick="usarAjuda('pular')" ${ajudasUsadas.pular ? 'disabled' : ''}>
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/>
                            </svg>
                            <span class="text-white font-bold text-xs">PULAR</span>
                </button>

                        <button class="help-btn rounded-xl flex flex-col items-center justify-center px-4 py-3 gap-1" onclick="fecharJogo()">
                            <svg class="w-6 h-6 text-white" fill="currentColor" viewBox="0 0 24 24">
                                <path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/>
                            </svg>
                            <span class="text-white font-bold text-xs">SAIR</span>
                </button>
            </div>
        </div>

                <!-- Coluna Direita - Personagem -->
                <div class="flex flex-col h-full justify-end">
                    <!-- Personagem -->
                    <div class="flex items-end justify-center h-full">
                        <img src="<?= URL ?>/public/uploads/layout/tudinha.png" 
                             alt="Tudinha" 
                             class="w-full h-auto object-contain object-bottom">
                    </div>
                </div>
            </div>
        </div>
    `;
    
    document.getElementById('gameModal').classList.remove('hidden');
    
    // Iniciar timer de 15 segundos após o modal aparecer
    setTimeout(() => {
        iniciarTimer();
    }, 100);
}

// Iniciar timer de 15 segundos
function iniciarTimer() {
    tempoRestante = 15;
    respostaBloqueada = false;
    tempoInicio = Date.now();
    const timerElement = document.getElementById('timer-countdown');
    
    if (!timerElement) return;
    
    timerElement.textContent = tempoRestante;
    timerElement.className = 'text-white font-black text-2xl';
    
    // Limpar timer anterior se existir
    if (timerInterval) {
        clearInterval(timerInterval);
    }
    
    timerInterval = setInterval(() => {
        tempoRestante--;
        if (timerElement) {
            timerElement.textContent = tempoRestante;
            
            // Mudar cor quando falta pouco tempo
            if (tempoRestante <= 5) {
                timerElement.className = 'text-red-400 font-black text-2xl animate-pulse';
            } else if (tempoRestante <= 10) {
                timerElement.className = 'text-yellow-400 font-black text-2xl';
            } else {
                timerElement.className = 'text-white font-black text-2xl';
            }
        }
        
        if (tempoRestante <= 0) {
            clearInterval(timerInterval);
            respostaBloqueada = true;
            
            // Desabilitar botões de resposta
            document.querySelectorAll('.alternative-btn').forEach(btn => {
                btn.disabled = true;
                btn.classList.add('opacity-50', 'cursor-not-allowed');
            });
            
            showNotification('⏰ Tempo esgotado! Use uma ajuda para continuar.', 'warning');
        }
    }, 1000);
}

// Parar timer (quando usar ajuda ou responder)
function pararTimer() {
    if (timerInterval) {
        clearInterval(timerInterval);
        timerInterval = null;
    }
}

// Selecionar resposta
function selecionarResposta(resposta) {
    // Verificar se resposta está bloqueada (tempo esgotado)
    if (respostaBloqueada) {
        showNotification('Tempo esgotado! Você precisa usar uma ajuda para continuar.', 'warning');
        return;
    }
    
    // Verificar se há partida ativa
    if (!currentPartidaId) {
        showNotification('Erro: Nenhuma partida ativa encontrada. Recarregue a página.', 'error');
        return;
    }
    
    // Parar timer
    pararTimer();
    
    // Calcular tempo gasto
    const tempoGasto = Math.max(0, 15 - tempoRestante);
    
    // Remover seleção anterior
    document.querySelectorAll('.alternative-btn').forEach(btn => {
        btn.classList.remove('selected', 'wrong');
        btn.disabled = true;
    });
    
    // Marcar resposta selecionada
    const selectedBtn = document.querySelector(`[onclick*="selecionarResposta('${resposta}')"]`);
    if (selectedBtn) {
        selectedBtn.classList.add('selected');
    }
    
    // Verificar qual ajuda foi usada (se houver)
    const ajudaUsadaAtual = ajudasUsadas.plateia ? 'plateia' : 
                           ajudasUsadas.universitarios ? 'universitarios' :
                           ajudasUsadas.pular ? 'pular' : 'nenhuma';
    
    // Enviar resposta
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    formData.append('partida_id', currentPartidaId);
    formData.append('pergunta_id', document.getElementById('pergunta-id').value);
    formData.append('resposta', resposta);
    formData.append('ajuda_usada', ajudaUsadaAtual);
    formData.append('tempo_resposta', tempoGasto);
    
    // Debug logs
    console.log('Enviando resposta:', {
        partida_id: currentPartidaId,
        pergunta_id: document.getElementById('pergunta-id').value,
        resposta: resposta,
        ajuda_usada: ajudaUsadaAtual,
        tempo_resposta: tempoGasto
    });
    
    fetch('<?= URL ?>/jogo-milhao/responder', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Resposta recebida:', data);
        
        if (data.success) {
            if (data.acertou) {
                showNotification('Resposta correta!', 'success');
                setTimeout(() => {
                    if (data.proxima_pergunta) {
                        console.log('Carregando próxima pergunta:', data.proxima_pergunta);
                        // Reformatar dados para carregarJogo
                        const gameData = {
                            pergunta: data.proxima_pergunta,
                            premio_atual: data.premio_atual,
                            pergunta_numero: data.pergunta_numero
                        };
                        carregarJogo(gameData);
                    } else {
                        showNotification('Parabéns! Você ganhou o jogo!', 'success');
                        fecharJogo();
                    }
                }, 1500);
            } else {
                showNotification('Resposta incorreta! Fim de jogo.', 'error');
                setTimeout(() => {
                    fecharJogo();
                }, 2000);
            }
        } else {
            // Se o erro indica que a partida não existe, limpar estado e recarregar
            if (data.message && (data.message.includes('não encontrada') || data.message.includes('finalizada'))) {
                showNotification('Partida não encontrada. Recarregando página...', 'error');
                currentPartidaId = null;
                pararHeartbeat();
                setTimeout(() => {
                    location.reload();
                }, 2000);
        } else {
            showNotification(data.message || 'Erro ao processar resposta', 'error');
            }
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Usar ajuda
function usarAjuda(tipo) {
    if (ajudasUsadas[tipo]) return;
    
    // Verificar se há partida ativa
    if (!currentPartidaId) {
        showNotification('Erro: Nenhuma partida ativa encontrada.', 'error');
        return;
    }
    
    // Parar timer quando usar ajuda
    // Se for "pular", o timer será reiniciado quando a nova pergunta aparecer
    pararTimer();
    
    // Reabilitar botões se estavam bloqueados por tempo
    if (respostaBloqueada) {
        respostaBloqueada = false;
        document.querySelectorAll('.alternative-btn').forEach(btn => {
            btn.disabled = false;
            btn.classList.remove('opacity-50', 'cursor-not-allowed');
        });
    }
    
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    formData.append('partida_id', currentPartidaId);
    formData.append('tipo_ajuda', tipo);
    formData.append('pergunta_id', document.getElementById('pergunta-id').value);
    
    // Debug logs
    console.log('Enviando ajuda:', {
        partida_id: currentPartidaId,
        tipo_ajuda: tipo,
        pergunta_id: document.getElementById('pergunta-id').value
    });
    
    fetch('<?= URL ?>/jogo-milhao/ajuda', {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log('Resposta da ajuda recebida:', data);
        
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

// Fechar modal de ajuda
function fecharModalAjuda() {
    document.getElementById('helpModal').classList.add('hidden');
}

// Mostrar modal de ajuda
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

// Processar resultado da ajuda
function processarResultadoAjuda(tipo, resultado) {
    console.log(`Processando resultado da ajuda ${tipo}:`, resultado);
    
    if (tipo === 'plateia') {
        // Processar percentuais da plateia
        const percentuais = resultado.percentuais || resultado;
        
        // Mostrar percentuais nas alternativas (plateia)
        const alternativas = ['A', 'B', 'C', 'D'];
        
        alternativas.forEach(alt => {
            const elemento = document.getElementById(`alt-${alt}`);
            if (elemento && percentuais[alt]) {
                const percentual = percentuais[alt];
                elemento.innerHTML = `${elemento.textContent} <span class="text-yellow-300 font-bold">(${percentual}%)</span>`;
            }
        });
        
        // Mostrar modal da plateia
        mostrarModalAjuda('plateia', percentuais);
        
    } else if (tipo === 'universitarios') {
        // Processar opiniões dos universitários
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
            
            // Mostrar percentuais nas alternativas
            const alternativas = ['A', 'B', 'C', 'D'];
            alternativas.forEach(alt => {
                const elemento = document.getElementById(`alt-${alt}`);
                if (elemento && percentuais[alt]) {
                    elemento.innerHTML = `${elemento.textContent} <span class="text-blue-300 font-bold">(${percentuais[alt]}%)</span>`;
                }
            });
            
            // Mostrar modal dos universitários
            mostrarModalAjuda('universitarios', resultado);
        }
        
    } else if (tipo === 'pular') {
        // Se for pular pergunta, carregar nova pergunta
        if (resultado.nova_pergunta) {
            const gameData = {
                pergunta: resultado.nova_pergunta,
                premio_atual: resultado.premio_atual || 0,
                pergunta_numero: resultado.pergunta_numero || 1
            };
            carregarJogo(gameData);
        }
    }
}

// Fechar jogo e finalizar partida
function fecharJogo() {
    if (confirm('⚠️ ATENÇÃO!\n\nSe você sair agora, perderá toda a partida e terá que começar do zero!\n\nTem certeza que deseja sair?')) {
        // Se há uma partida ativa, finalizá-la como abandonada
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
                pararHeartbeat(); // Parar heartbeat
                currentPartidaId = null; // Limpar ID da partida
            })
            .catch(error => {
                console.error('Erro ao abandonar partida:', error);
                pararHeartbeat(); // Parar heartbeat mesmo em caso de erro
                currentPartidaId = null;
            });
        }
        
        // Fechar modal e recarregar página
    document.getElementById('gameModal').classList.add('hidden');
    setTimeout(() => {
        location.reload();
    }, 500);
    }
}

</script>
