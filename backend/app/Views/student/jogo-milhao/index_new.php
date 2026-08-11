<?php
/**
 * EducaTudo - Tudinha do Milhão
 * Interface imersiva do jogo inspirada no Show do Milhão
 */

$premios = $data['premios'] ?? [];
$stats = $data['stats'] ?? [];
$partidaAtiva = $data['partidaAtiva'] ?? null;
?>

<link rel="stylesheet" href="<?= URL ?>/app/Views/student/jogo-milhao/game-styles.css">

<div class="tudinha-bg p-6">
    <div class="tudinha-container">
        <!-- Header com Logo -->
        <div class="text-center py-12">
            <div class="tudinha-character">
                <img src="<?= URL ?>/uploads/layout/tudina_do_milhao.png" alt="Tudinha do Milhão" />
            </div>
            <h1 class="tudinha-logo">Tudinha do Milhão</h1>
            <p class="text-white text-xl mt-4">Teste seus conhecimentos e ganhe até R$ 1.000.000!</p>
        </div>

        <!-- Estatísticas do Jogador -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-blue-500">🎮</div>
                <div class="stat-value"><?= $stats['total_partidas'] ?? 0 ?></div>
                <div class="stat-label">Partidas Jogadas</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-green-500">🏆</div>
                <div class="stat-value"><?= $stats['partidas_vencidas'] ?? 0 ?></div>
                <div class="stat-label">Vitórias</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-yellow-500">💰</div>
                <div class="stat-value">R$ <?= number_format($stats['maior_premio'] ?? 0, 2, ',', '.') ?></div>
                <div class="stat-label">Maior Prêmio</div>
            </div>
            <div class="stat-card">
                <div class="stat-icon bg-purple-500">📊</div>
                <div class="stat-value"><?= $stats['taxa_acerto'] ?? 0 ?>%</div>
                <div class="stat-label">Taxa de Acerto</div>
            </div>
        </div>

        <!-- Escada de Prêmios -->
        <div class="prize-ladder">
            <h3 class="text-white text-2xl font-bold mb-6 text-center">Escada de Prêmios</h3>
            <div class="space-y-2">
                <?php foreach ($premios as $index => $premio): ?>
                    <div class="prize-item <?= in_array($index, [4, 9]) ? 'milestone' : '' ?>">
                        <span class="text-white font-semibold">Pergunta <?= $index + 1 ?></span>
                        <span class="text-white font-bold">R$ <?= number_format($premio, 2, ',', '.') ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Ações do Jogo -->
        <div class="text-center space-y-4">
            <?php if ($partidaAtiva): ?>
                <div class="bg-yellow-500 bg-opacity-20 border border-yellow-500 rounded-lg p-6 mb-6">
                    <h3 class="text-yellow-400 text-xl font-bold mb-2">Partida em Andamento</h3>
                    <p class="text-white">Você tem uma partida ativa na pergunta <?= $partidaAtiva['pergunta_atual'] ?? 1 ?></p>
                    <p class="text-white">Prêmio atual: R$ <?= number_format($partidaAtiva['premio_atual'] ?? 0, 2, ',', '.') ?></p>
                </div>
                <button onclick="continuarPartida()" class="continue-button">
                    ▶️ Continuar Partida
                </button>
            <?php else: ?>
                <button onclick="iniciarPartida()" class="start-button">
                    🚀 Iniciar Nova Partida
                </button>
            <?php endif; ?>
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

<script>
let currentPartidaId = null;
let currentPergunta = null;
let ajudasUsadas = {
    plateia: false,
    universitarios: false,
    pular: false
};

// Iniciar nova partida
function iniciarPartida() {
    fetch('<?= URL ?>/jogo-milhao/iniciar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentPartidaId = data.partida_id;
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

// Continuar partida existente
function continuarPartida() {
    fetch('<?= URL ?>/jogo-milhao/continuar', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({})
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentPartidaId = data.partida_id;
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

// Carregar interface do jogo imersiva
function carregarJogo(data) {
    const gameContent = document.getElementById('gameContent');
    
    gameContent.innerHTML = `
        <!-- Área de Conteúdo -->
        <div class="game-content">
            <!-- Header -->
            <div class="game-header">
                <div class="header-left">
                    <div class="header-logo">🎓</div>
                    <div>
                        <h1 class="game-title">Tudinha do Milhão</h1>
                        <p class="game-progress">Pergunta ${data.pergunta_numero} de 15</p>
                    </div>
                </div>
                <!-- Botões de Ação -->
                <div class="header-controls">
                    <button onclick="pausarJogo()" class="control-btn pause-btn">
                        ⏸️ Pausar
                    </button>
                    <button onclick="fecharJogo()" class="control-btn exit-btn">
                        🚪 Sair
                    </button>
                </div>
            </div>

            <!-- Prêmio Atual -->
            <div class="prize-display">
                <h3 class="prize-label">Prêmio Atual</h3>
                <div class="prize-value">R$ ${data.premio_atual.toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</div>
            </div>

            <!-- Pergunta -->
            <div class="question-bubble">
                <div class="question-icon">💡</div>
                <h2 class="question-title">Pergunta ${data.pergunta_numero}</h2>
                <p class="question-text">${data.pergunta.pergunta}</p>
            </div>

            <!-- Alternativas -->
            <div class="alternativas-container">
                <button onclick="selecionarResposta('A')" class="alternativa-btn">
                    <div class="letra-alt">A</div>
                    <span class="texto-alt">${data.pergunta.alternativa_a}</span>
                </button>
                <button onclick="selecionarResposta('B')" class="alternativa-btn">
                    <div class="letra-alt">B</div>
                    <span class="texto-alt">${data.pergunta.alternativa_b}</span>
                </button>
                <button onclick="selecionarResposta('C')" class="alternativa-btn">
                    <div class="letra-alt">C</div>
                    <span class="texto-alt">${data.pergunta.alternativa_c}</span>
                </button>
                <button onclick="selecionarResposta('D')" class="alternativa-btn">
                    <div class="letra-alt">D</div>
                    <span class="texto-alt">${data.pergunta.alternativa_d}</span>
                </button>
            </div>

            <!-- Botões de Ajuda -->
            <div class="ajudas-container">
                <button onclick="usarAjuda('plateia')" class="ajuda-btn" ${ajudasUsadas.plateia ? 'disabled' : ''}>
                    💡 Ajuda da Plateia
                </button>
                <button onclick="usarAjuda('universitarios')" class="ajuda-btn" ${ajudasUsadas.universitarios ? 'disabled' : ''}>
                    👥 Universitários
                </button>
                <button onclick="usarAjuda('pular')" class="ajuda-btn" ${ajudasUsadas.pular ? 'disabled' : ''}>
                    ⏭️ Pular Pergunta
                </button>
            </div>
        </div>

        <!-- Área da Tudinha -->
        <div class="tudinha-sidebar">
            <img src="<?= URL ?>/uploads/layout/tudinha.png" alt="Tudinha" class="tudinha-image" />
            <div class="tudinha-info">
                <h3 class="tudinha-name">Tudinha</h3>
                <p class="tudinha-desc">Sua assistente educacional está aqui para te ajudar!</p>
            </div>
        </div>
    `;
    
    document.getElementById('gameModal').classList.remove('hidden');
}

// Selecionar resposta
function selecionarResposta(resposta) {
    // Remover seleção anterior
    document.querySelectorAll('.alternativa-btn').forEach(btn => {
        btn.classList.remove('selected', 'wrong');
    });
    
    // Marcar resposta selecionada
    const selectedBtn = document.querySelector(`[onclick="selecionarResposta('${resposta}')"]`);
    selectedBtn.classList.add('selected');
    
    // Enviar resposta
    fetch('<?= URL ?>/jogo-milhao/responder', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            partida_id: currentPartidaId,
            resposta: resposta
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.correta) {
                showNotification('Resposta correta!', 'success');
                setTimeout(() => {
                    if (data.proxima_pergunta) {
                        carregarJogo(data);
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
            showNotification(data.message || 'Erro ao processar resposta', 'error');
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
    
    fetch('<?= URL ?>/jogo-milhao/ajuda', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            partida_id: currentPartidaId,
            tipo_ajuda: tipo
        })
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
        } else {
            showNotification(data.message || 'Erro ao usar ajuda', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Pausar jogo
function pausarJogo() {
    showNotification('Jogo pausado', 'info');
    fecharJogo();
}

// Fechar jogo
function fecharJogo() {
    document.getElementById('gameModal').classList.add('hidden');
    // Recarregar página para atualizar estatísticas
    setTimeout(() => {
        location.reload();
    }, 500);
}

// Função de notificação
function showNotification(message, type = 'info') {
    // Implementar sistema de notificação
    console.log(`${type.toUpperCase()}: ${message}`);
}
</script>
