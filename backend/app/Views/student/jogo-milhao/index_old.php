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
#gameContent {
    width: 100% !important;
    max-width: 100% !important;
    overflow: visible !important;
}

#gameContent * {
    visibility: visible !important;
    opacity: 1 !important;
}

/* Remover qualquer duplicação */
#gameContent .flex.justify-between.items-center.mb-8 {
    display: none !important;
}

#gameContent .game-layout {
    display: flex !important;
    width: 100% !important;
    max-width: 100% !important;
}

#gameContent .alternativa-btn,
#gameContent .ajuda-btn {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    height: auto !important;
    min-height: 45px !important;
    width: 100% !important;
}

#gameContent .ajudas-container {
    display: flex !important;
    visibility: visible !important;
    opacity: 1 !important;
    width: 100% !important;
}

/* CSS GLOBAL PARA REMOVER TODAS AS BORDAS */
* {
    border: none !important;
    outline: none !important;
}

/* Remover borda do container principal do layout */
main .p-6,
main > div.p-6,
body main div.p-6 {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
    background: transparent !important;
    margin: 0 !important;
    padding: 0 !important;
}

/* Remover qualquer espaçamento que possa causar efeito de borda */
.tudinha-bg.p-6 {
    margin: 0 !important;
    padding: 0 !important;
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

/* Estilos personalizados para o Tudinha do Milhão */
.tudinha-bg {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
    min-height: 100vh;
    position: relative;
    overflow: hidden;
}

.tudinha-bg::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
    z-index: 1;
}

.tudinha-container {
    position: relative;
    z-index: 10;
}

/* Garantir que o background seja aplicado em elementos com p-6 */
.p-6.tudinha-bg {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%) !important;
    min-height: 100vh;
    position: relative;
    overflow: hidden;
    border: none !important;
    outline: none !important;
}

.p-6.tudinha-bg::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="50" cy="50" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
    opacity: 0.3;
    z-index: 1;
}

/* Remover qualquer borda do elemento p-6 */
.p-6 {
    border: none !important;
    outline: none !important;
    box-shadow: none !important;
}

/* Remover TODAS as bordas dos cards de estatísticas */
.stat-card,
.stat-card *,
div[class*="stat-card"],
div[class*="bg-white"],
div[class*="rounded-lg"],
div[class*="bg-blue-100"],
div[class*="bg-green-100"],
div[class*="bg-yellow-100"],
div[class*="bg-purple-100"] {
    border: none !important;
    outline: none !important;
    border-width: 0 !important;
    border-style: none !important;
    border-color: transparent !important;
}

/* CSS específico para remover bordas do Tailwind */
.bg-white,
.bg-blue-100,
.bg-green-100,
.bg-yellow-100,
.bg-purple-100 {
    border: none !important;
    outline: none !important;
}

.rounded-lg {
    border: none !important;
    outline: none !important;
}

/* Forçar remoção de bordas em todos os elementos */
div[style*="border"],
div[class*="border"],
*[class*="bg-"] {
    border: none !important;
    outline: none !important;
}

/* CSS específico para elementos com classes de background do Tailwind */
[class*="bg-blue-"],
[class*="bg-green-"],
[class*="bg-yellow-"],
[class*="bg-purple-"],
[class*="bg-white"] {
    border: none !important;
    outline: none !important;
    border-width: 0 !important;
    border-style: none !important;
    border-color: transparent !important;
}

.tudinha-logo {
    background: linear-gradient(45deg, #fbbf24, #f59e0b);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
    text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
    font-family: 'Arial Black', sans-serif;
    font-weight: 900;
    text-transform: uppercase;
    letter-spacing: 2px;
}

.tudinha-character {
    width: 300px;
    height: 200px;
    background: transparent;
    border-radius: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto;
    box-shadow: none;
    position: relative;
    animation: float 3s ease-in-out infinite;
}

.tudinha-character img {
    width: 100%;
    height: auto;
    max-width: 300px;
    filter: drop-shadow(0 10px 20px rgba(0,0,0,0.3));
}

.tudinha-character::before {
    display: none;
}

@keyframes float {
    0%, 100% { transform: translateY(0px); }
    50% { transform: translateY(-10px); }
}

.game-card {
    background: rgba(255, 255, 255, 0.95);
    backdrop-filter: blur(10px);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    border: 1px solid rgba(0, 0, 0, 0.05);
}

.prize-ladder {
    background: linear-gradient(180deg, #10b981, #059669);
    border-radius: 15px;
    padding: 30px;
    color: white;
    position: relative;
    overflow: hidden;
}

.prize-ladder::before {
    content: '💰';
    position: absolute;
    top: 10px;
    right: 10px;
    font-size: 24px;
    opacity: 0.7;
}

.prize-item {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    padding: 15px 20px;
    margin: 8px 0;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.3s ease;
}

.prize-item:hover {
    background: rgba(255, 255, 255, 0.3);
    transform: translateX(5px);
}

.prize-item.milestone {
    background: linear-gradient(45deg, #fbbf24, #f59e0b);
    color: #1f2937;
    font-weight: bold;
}

.start-button {
    background: linear-gradient(45deg, #10b981, #059669);
    border: none;
    border-radius: 50px;
    padding: 20px 40px;
    color: white;
    font-size: 24px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 2px;
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.4);
    transition: all 0.3s ease;
    position: relative;
    overflow: hidden;
}

.start-button::before {
    content: '';
    position: absolute;
    top: 0;
    left: -100%;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    transition: left 0.5s ease;
}

.start-button:hover::before {
    left: 100%;
}

.start-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(16, 185, 129, 0.6);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 20px;
    margin: 30px 0;
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

/* Container para alinhar cards com a mesma largura dos stats */
.content-container {
    max-width: 1200px;
    margin-left: auto;
    margin-right: auto;
}

.stat-card {
    background: rgba(255, 255, 255, 0.9);
    border-radius: 15px;
    padding: 20px;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0,0,0,0.1);
    transition: all 0.3s ease;
    border: none !important;
    outline: none !important;
    border-width: 0 !important;
    border-style: none !important;
    border-color: transparent !important;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 15px 40px rgba(59, 130, 246, 0.2);
}

.stat-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 15px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    border: none !important;
    outline: none !important;
}

.stat-value {
    font-size: 32px;
    font-weight: bold;
    color: #1f2937;
    margin-bottom: 5px;
}

.stat-label {
    color: #6b7280;
    font-size: 14px;
    text-transform: uppercase;
    letter-spacing: 1px;
}

/* Modal do jogo com estilo imersivo */
.game-modal {
    background: linear-gradient(135deg, #1e3a8a 0%, #3b82f6 50%, #06b6d4 100%);
    backdrop-filter: blur(20px);
}

.game-stage {
    background: linear-gradient(180deg, #1f2937 0%, #111827 100%);
    border-radius: 15px;
    padding: 20px;
    position: relative;
    overflow: visible;
    min-height: auto;
    margin: 0 auto;
    max-width: 100%;
}

.game-stage::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 50% 0%, rgba(59, 130, 246, 0.3) 0%, transparent 50%);
}

.question-bubble {
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 20px;
    padding: 30px;
    margin: 20px 0;
    color: white;
    position: relative;
    box-shadow: 0 15px 40px rgba(16, 185, 129, 0.3);
}

.question-bubble::before {
    content: '💡';
    position: absolute;
    top: -10px;
    right: 20px;
    font-size: 30px;
    background: white;
    border-radius: 50%;
    width: 50px;
    height: 50px;
    display: flex;
    align-items: center;
    justify-content: center;
}

.answer-button {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 15px;
    padding: 20px;
    margin: 10px 0;
    color: white;
    font-size: 18px;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
    position: relative;
    overflow: hidden;
}

.answer-button:hover {
    background: rgba(255, 255, 255, 0.2);
    border-color: #10b981;
    transform: translateY(-2px);
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3);
}

.answer-button.selected {
    background: linear-gradient(45deg, #10b981, #059669);
    border-color: #10b981;
    box-shadow: 0 10px 30px rgba(16, 185, 129, 0.5);
}

/* CSS COMPACTO PARA ALTERNATIVAS E AJUDAS */
.alternativas-container {
    display: block !important;
    width: 100% !important;
    margin-bottom: 15px !important;
}

.alternativa-btn {
    display: block !important;
    width: 100% !important;
    margin-bottom: 8px !important;
    padding: 12px 16px !important;
    background: rgba(255, 255, 255, 0.1) !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    border-radius: 10px !important;
    color: white !important;
    text-align: left !important;
    visibility: visible !important;
    opacity: 1 !important;
    height: auto !important;
    min-height: 45px !important;
    font-size: 14px !important;
}

.alternativa-btn:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    border-color: #10b981 !important;
    transform: translateY(-1px) !important;
}

.letra-alt {
    font-weight: bold !important;
    font-size: 18px !important;
    margin-right: 12px !important;
    color: #fbbf24 !important;
    display: inline-block !important;
}

.texto-alt {
    font-size: 14px !important;
    display: inline-block !important;
}

.ajudas-container {
    display: flex !important;
    justify-content: center !important;
    gap: 10px !important;
    margin: 20px 0 !important;
    flex-wrap: wrap !important;
    visibility: visible !important;
    opacity: 1 !important;
    width: 100% !important;
}

.ajuda-btn {
    display: block !important;
    visibility: visible !important;
    opacity: 1 !important;
    background: rgba(255, 255, 255, 0.1) !important;
    border: 2px solid rgba(255, 255, 255, 0.3) !important;
    border-radius: 25px !important;
    padding: 10px 20px !important;
    color: white !important;
    font-weight: bold !important;
    transition: all 0.3s ease !important;
    min-width: 120px !important;
    height: auto !important;
    font-size: 12px !important;
}

.ajuda-btn:hover {
    background: rgba(255, 255, 255, 0.2) !important;
    transform: translateY(-1px) !important;
}

.help-button {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 50px;
    padding: 15px 25px;
    color: white;
    font-size: 14px;
    font-weight: 500;
    transition: all 0.3s ease;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 8px;
}

.help-button:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.2);
    transform: translateY(-2px);
}

.help-button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.result-screen {
    text-align: center;
    padding: 40px;
}

.result-character {
    width: 150px;
    height: 150px;
    background: linear-gradient(135deg, #10b981, #059669);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 30px;
    font-size: 60px;
    animation: bounce 1s ease-in-out;
}

@keyframes bounce {
    0%, 20%, 50%, 80%, 100% { transform: translateY(0); }
    40% { transform: translateY(-20px); }
    60% { transform: translateY(-10px); }
}

.result-title {
    font-size: 36px;
    font-weight: bold;
    margin-bottom: 20px;
}

.result-title.correct {
    color: #10b981;
}

.result-title.wrong {
    color: #ef4444;
}

.result-message {
    font-size: 18px;
    color: #6b7280;
    margin-bottom: 30px;
    line-height: 1.6;
}

.continue-button {
    background: linear-gradient(45deg, #3b82f6, #1d4ed8);
    border: none;
    border-radius: 50px;
    padding: 20px 40px;
    color: white;
    font-size: 20px;
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 1px;
    box-shadow: 0 10px 30px rgba(59, 130, 246, 0.4);
    transition: all 0.3s ease;
    cursor: pointer;
}

.continue-button:hover {
    transform: translateY(-3px);
    box-shadow: 0 15px 40px rgba(59, 130, 246, 0.6);
}

/* Responsividade */
@media (max-width: 768px) {
    .tudinha-character {
        width: 250px;
        height: 150px;
    }
    
    .tudinha-character img {
        max-width: 250px;
    }
    
    .tudinha-logo {
        font-size: 3rem;
    }
    
    .start-button {
        padding: 15px 30px;
        font-size: 20px;
    }
    
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 15px;
    }
    
    .stat-card {
        padding: 15px;
    }
    
    .stat-value {
        font-size: 24px;
    }
    
    .help-buttons {
        flex-wrap: wrap;
    }
    
    .help-button {
        flex: 1;
        min-width: 120px;
    }
}

/* Layout Responsivo Compacto */
.game-layout {
    display: flex;
    gap: 20px;
    width: 100%;
    max-width: 100%;
}

.game-main {
    flex: 1;
    min-width: 0;
}

.tudinha-sidebar {
    width: 200px;
    flex-shrink: 0;
}

.game-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 10px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.header-logo {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    border: 2px solid #fbbf24;
}

.game-title {
    font-size: 24px;
    font-weight: bold;
    color: white;
    margin: 0;
}

.game-progress {
    font-size: 14px;
    color: #93c5fd;
    margin: 0;
}

.header-controls {
    display: flex;
    gap: 10px;
}

.control-btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: bold;
    font-size: 14px;
    transition: all 0.3s ease;
}

.pause-btn {
    background: #f59e0b;
    color: white;
}

.exit-btn {
    background: #dc2626;
    color: white;
}

.prize-display {
    text-align: center;
    margin-bottom: 20px;
}

.prize-card {
    background: linear-gradient(to right, #fbbf24, #f59e0b);
    color: black;
    padding: 15px 30px;
    border-radius: 10px;
    display: inline-block;
}

.prize-label {
    font-size: 18px;
    font-weight: bold;
    margin: 0 0 5px 0;
}

.prize-value {
    font-size: 24px;
    font-weight: 900;
    margin: 0;
}

.question-bubble {
    background: rgba(255, 255, 255, 0.1);
    border: 2px solid rgba(255, 255, 255, 0.2);
    border-radius: 15px;
    padding: 20px;
    margin-bottom: 20px;
    backdrop-filter: blur(10px);
}

.question-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.question-title {
    font-size: 20px;
    font-weight: bold;
    color: white;
    margin: 0;
}

.question-icon {
    font-size: 20px;
}

.question-text {
    font-size: 16px;
    color: #f3f4f6;
    line-height: 1.5;
    margin: 0;
}

.tudinha-card {
    background: rgba(255, 255, 255, 0.1);
    border-radius: 10px;
    padding: 15px;
    text-align: center;
}

.tudinha-image {
    width: 100%;
    height: auto;
    max-height: 200px;
    object-fit: contain;
    margin-bottom: 10px;
}

.tudinha-name {
    font-size: 18px;
    font-weight: bold;
    color: white;
    margin: 0 0 5px 0;
}

.tudinha-desc {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.8);
    margin: 0;
}

/* Responsividade */
@media (max-width: 1200px) {
    .tudinha-sidebar {
        width: 150px;
    }
}

@media (max-width: 768px) {
    .game-layout {
        flex-direction: column;
        gap: 15px;
    }
    
    .tudinha-sidebar {
        width: 100%;
        order: -1;
    }
    
    .tudinha-image {
        max-height: 150px;
    }
    
    .game-header {
        flex-direction: column;
        align-items: flex-start;
    }
    
    .header-controls {
        width: 100%;
        justify-content: center;
    }
    
    .control-btn {
        flex: 1;
        max-width: 120px;
    }
}
</style>

<div class="tudinha-bg p-6">
    <div class="tudinha-container">
        <!-- Header com Logo -->
        <div class="text-center py-12">
            <div class="tudinha-character mb-8">
                <img src="<?= URL ?>/uploads/layout/tudina_do_milhao.png" alt="Tudinha do Milhão" />
            </div>
            <h1 class="tudinha-logo text-6xl mb-4">TUDINHA DO MILHÃO</h1>
            <p class="text-white text-xl opacity-90 mb-8">
                Teste seus conhecimentos e ganhe até R$ 1.000.000! 🎓💰
            </p>
</div>

        <!-- Estatísticas -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon bg-blue-100 text-blue-600">🎮</div>
                <div class="stat-value"><?= $stats['total_partidas'] ?? 0 ?></div>
                <div class="stat-label">Partidas Jogadas</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon bg-green-100 text-green-600">🏆</div>
                <div class="stat-value"><?= $stats['partidas_vencidas'] ?? 0 ?></div>
                <div class="stat-label">Vitórias</div>
    </div>
    
            <div class="stat-card">
                <div class="stat-icon bg-yellow-100 text-yellow-600">💰</div>
                <div class="stat-value">R$ <?= number_format($stats['total_premio'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Total Ganho</div>
    </div>
    
            <div class="stat-card">
                <div class="stat-icon bg-purple-100 text-purple-600">⭐</div>
                <div class="stat-value"><?= $stats['nivel_atual'] ?? 'Iniciante' ?></div>
                <div class="stat-label">Nível</div>
    </div>
    
            <!-- Card do Maior Prêmio integrado -->
            <div class="stat-card">
                <div class="stat-icon bg-green-100 text-green-600">🏅</div>
                <div class="stat-value">R$ <?= number_format($stats['maior_premio'] ?? 0, 0, ',', '.') ?></div>
                <div class="stat-label">Maior Prêmio</div>
    </div>
</div>

<!-- Partida Ativa ou Iniciar Nova Partida -->
        <div class="content-container">
<?php if ($partidaAtiva): ?>
                <div class="game-card p-8 mb-8 text-center">
                    <div class="text-6xl mb-4">⏰</div>
                    <h3 class="text-2xl font-bold text-orange-600 mb-4">Partida em Andamento!</h3>
                    <p class="text-gray-600 mb-6">Você tem uma partida em andamento. Continue jogando!</p>
            <button onclick="continuarPartida(<?= $partidaAtiva['id'] ?>)" 
                            class="start-button">
                        ▶️ Continuar Partida
            </button>
    </div>
<?php else: ?>
                <div class="game-card p-8 mb-8 text-center">
                    <div class="text-6xl mb-4">🎯</div>
                    <h3 class="text-2xl font-bold text-gray-900 mb-4">Pronto para Jogar?</h3>
                    <p class="text-gray-600 mb-6 text-lg">
                Responda 15 perguntas corretamente e ganhe R$ 1.000.000!<br>
                Você tem 3 ajudas especiais para usar durante o jogo.
            </p>
                    <button onclick="iniciarNovaPartida()" class="start-button">
            🎮 Iniciar Nova Partida
        </button>
    </div>
<?php endif; ?>

            <!-- Escada de Prêmios -->
            <div class="prize-ladder mb-8">
                <h3 class="text-2xl font-bold mb-6 text-center">🏆 Escada de Prêmios</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <?php 
            $niveis = [
                'Fácil' => array_slice($premios, 0, 5, true),
                'Médio' => array_slice($premios, 5, 5, true),
                'Difícil' => array_slice($premios, 10, 5, true)
            ];
            
            foreach ($niveis as $nivel => $premiosNivel): ?>
                        <div>
                            <h4 class="font-bold text-lg mb-3 text-center"><?= $nivel ?></h4>
                    <?php foreach ($premiosNivel as $pergunta => $valor): ?>
                                <div class="prize-item <?= $pergunta == 5 || $pergunta == 10 || $pergunta == 15 ? 'milestone' : '' ?>">
                                    <span class="font-medium">Pergunta <?= $pergunta ?></span>
                                    <span class="font-bold">R$ <?= number_format($valor, 0, ',', '.') ?></span>
                                </div>
                            <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>
        
                <div class="mt-6 p-6 bg-white bg-opacity-20 rounded-lg text-center">
                    <h4 class="font-bold text-lg mb-2">🎉 Prêmio Máximo</h4>
                    <p class="text-lg">R$ 1.000.000 para quem responder todas as 15 perguntas corretamente!</p>
                </div>
            </div>
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
function iniciarNovaPartida() {
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    
    fetch('<?= URL ?>/jogo-milhao/iniciar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentPartidaId = data.partida_id;
            currentPergunta = data.pergunta;
            ajudasUsadas = {
                plateia: false,
                universitarios: false,
                pular: false
            };
            carregarJogo(data);
        } else {
            showNotification(data.error || 'Erro ao iniciar partida', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Continuar partida existente
function continuarPartida(partidaId) {
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    formData.append('partida_id', partidaId);
    
    fetch('<?= URL ?>/jogo-milhao/continuar', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            currentPartidaId = data.partida_id;
            currentPergunta = data.pergunta;
            if (data.ajudas_usadas) {
                ajudasUsadas = data.ajudas_usadas;
            }
            carregarJogo(data);
        } else {
            showNotification(data.error || 'Erro ao continuar partida', 'error');
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
    document.querySelectorAll('.answer-button').forEach(btn => {
        btn.classList.remove('selected');
    });
    
    // Selecionar nova resposta
    event.target.classList.add('selected');
    
    // Confirmar resposta após 1 segundo
    setTimeout(() => {
        responderPergunta(resposta);
    }, 1000);
}

// Responder pergunta
function responderPergunta(resposta) {
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    formData.append('partida_id', currentPartidaId);
    formData.append('pergunta_id', currentPergunta.id);
    formData.append('resposta', resposta);
    
    fetch('<?= URL ?>/jogo-milhao/responder', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.jogo_finalizado) {
                mostrarResultadoFinal(data);
            } else {
                // Próxima pergunta
                currentPergunta = data.proxima_pergunta;
                carregarJogo({
                    pergunta: data.proxima_pergunta,
                    premio_atual: data.premio_atual,
                    pergunta_numero: data.pergunta_numero
                });
            }
        } else {
            showNotification(data.error || 'Erro ao responder pergunta', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Usar ajuda especial
function usarAjuda(tipo) {
    if (ajudasUsadas[tipo]) {
        showNotification('Esta ajuda já foi usada!', 'error');
        return;
    }
    
    const formData = new FormData();
    formData.append('_token', document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '');
    formData.append('partida_id', currentPartidaId);
    formData.append('tipo_ajuda', tipo);
    formData.append('pergunta_id', currentPergunta.id);
    
    fetch('<?= URL ?>/jogo-milhao/ajuda', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            ajudasUsadas[tipo] = true;
            mostrarAjuda(data.resultado);
        } else {
            showNotification(data.error || 'Erro ao usar ajuda', 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
        console.error('Error:', error);
    });
}

// Mostrar resultado da ajuda
function mostrarAjuda(resultado) {
    let mensagem = '';
    
    switch (resultado.tipo) {
        case 'plateia':
            mensagem = `${resultado.mensagem}\n\n`;
            Object.entries(resultado.percentuais).forEach(([alt, perc]) => {
                mensagem += `${alt}: ${perc}%\n`;
            });
            break;
            
        case 'universitarios':
            mensagem = `${resultado.mensagem}\n\n`;
            resultado.opinioes.forEach(uni => {
                mensagem += `${uni.nome} (${uni.curso}): ${uni.resposta} (${uni.confianca}% de certeza)\n`;
            });
            break;
            
        case 'pular':
            mensagem = resultado.mensagem;
            currentPergunta = resultado.nova_pergunta;
            carregarJogo({
                pergunta: resultado.nova_pergunta,
                premio_atual: 0,
                pergunta_numero: 1
            });
            return;
    }
    
    alert(mensagem);
}

// Mostrar resultado final
function mostrarResultadoFinal(data) {
    const gameContent = document.getElementById('gameContent');
    
    gameContent.innerHTML = `
        <div class="result-screen">
            <div class="result-character">
                ${data.acertou ? '🎉' : '😢'}
            </div>
            
            <h2 class="result-title ${data.acertou ? 'correct' : 'wrong'}">
                    ${data.acertou ? 'Parabéns! 🎉' : 'Ahhh, você errou! 😢'}
                </h2>
            
            <p class="result-message">${data.mensagem}</p>
            
            ${data.acertou ? '' : `
                <div class="bg-yellow-100 border border-yellow-300 rounded-lg p-6 mb-6">
                    <p class="text-yellow-800 font-bold text-xl">💰 Seu prêmio: R$ ${data.premio_final.toLocaleString('pt-BR')}</p>
                </div>
            `}
            
            ${data.explicacao ? `
                <div class="bg-blue-50 border border-blue-200 rounded-lg p-6 mb-6">
                    <p class="text-blue-800 font-bold text-lg mb-2">💡 Explicação:</p>
                    <p class="text-blue-700">${data.explicacao}</p>
            </div>
            ` : ''}
            
            <button onclick="fecharJogo()" class="continue-button">
                🏠 Voltar ao Menu
                </button>
        </div>
    `;
}

// Pausar jogo
function pausarJogo() {
    if (confirm('Deseja realmente pausar o jogo? Você poderá continuar depois.')) {
        // Aqui você pode implementar a lógica de pausar
        // Por enquanto, apenas fecha o modal
        fecharJogo();
        showNotification('Jogo pausado! Você pode continuar a qualquer momento.', 'success');
    }
}

// Fechar jogo
function fecharJogo() {
    document.getElementById('gameModal').classList.add('hidden');
    window.location.reload();
}

// Mostrar notificação
function showNotification(message, type = 'success') {
    // Implementar sistema de notificações mais elaborado
    const notification = document.createElement('div');
    notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
        type === 'success' ? 'bg-green-500 text-white' : 'bg-red-500 text-white'
    }`;
    notification.textContent = message;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.remove();
    }, 3000);
}
</script>