/**
 * Game Security - Proteções de segurança no frontend
 * Previne trapaças no jogo do milhão
 */

class GameSecurity {
    constructor() {
        this.isFullscreen = false;
        this.focusCount = 0;
        this.lastActionTime = Date.now();
        this.minActionInterval = 2000; // 2 segundos
        
        this.init();
    }
    
    init() {
        this.disableDevTools();
        this.blockMultipleTabs();
        this.monitorFocus();
        this.blockPageReload();
        this.enforceFullscreen();
        this.blockRapidActions();
    }
    
    /**
     * Desabilita DevTools (console, F12, etc.)
     */
    disableDevTools() {
        // Bloquear F12
        document.addEventListener('keydown', (e) => {
            // F12, Ctrl+Shift+I, Ctrl+Shift+J, Ctrl+U
            if (
                e.key === 'F12' ||
                (e.ctrlKey && e.shiftKey && (e.key === 'I' || e.key === 'J')) ||
                (e.ctrlKey && e.key === 'U')
            ) {
                e.preventDefault();
                this.showWarning('Ferramentas de desenvolvedor estão desabilitadas durante o jogo.');
                return false;
            }
        });
        
        // Bloquear clique direito
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            return false;
        });
        
        // Detectar quando DevTools é aberto
        setInterval(() => {
            const start = performance.now();
            debugger; // Isso pausa quando DevTools está aberto
            const end = performance.now();
            
            if (end - start > 100) {
                console.log('DevTools detectado!');
                // Não redirecionar, apenas avisar
                this.showWarning('⚠️ DevTools detectado! Por favor, feche as ferramentas de desenvolvedor.');
            }
        }, 2000); // Verificar a cada 2 segundos
    }
    
    /**
     * Bloqueia múltiplas abas/janelas
     */
    blockMultipleTabs() {
        // Usar BroadcastChannel para comunicação entre abas
        const channel = new BroadcastChannel('game_session');
        
        channel.onmessage = (e) => {
            if (e.data === 'ping') {
                channel.postMessage('pong');
                // Outra aba está ativa
                alert('⚠️ Você já tem uma sessão ativa do jogo em outra aba!\n\nPor favor, feche as outras abas e continue apenas nesta.');
            }
        };
        
        // Ping outras abas a cada 2 segundos
        setInterval(() => {
            channel.postMessage('ping');
        }, 2000);
        
        // Verificar no carregamento
        window.addEventListener('load', () => {
            const timer = setTimeout(() => {
                channel.postMessage('timeout');
            }, 500);
            
            window.addEventListener('focus', () => {
                clearTimeout(timer);
                channel.postMessage('active');
            });
        });
    }
    
    /**
     * Monitora mudanças de foco (troca de aba/janela)
     */
    monitorFocus() {
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                // Pausar timer do jogo
                if (window.gameTimer) {
                    window.gameTimer.pause();
                }
            } else {
                // Retomar timer
                if (window.gameTimer) {
                    window.gameTimer.resume();
                }
            }
        });
        
        window.addEventListener('blur', () => {
            this.focusCount++;
            if (this.focusCount > 5) {
                this.showWarning('Você saiu da janela do jogo muitas vezes. O jogo pode ser pausado.');
            }
        });
        
        window.addEventListener('focus', () => {
            // Resetar contador quando volta a focar
            if (Date.now() - this.lastActionTime > 10000) {
                // Se ficou mais de 10 segundos fora, registrar
                this.logFocusLoss();
            }
        });
    }
    
    /**
     * Bloqueia reload de página (F5, Ctrl+R)
     */
    blockPageReload() {
        document.addEventListener('keydown', (e) => {
            // F5 ou Ctrl+R
            if (e.key === 'F5' || (e.ctrlKey && e.key === 'r')) {
                e.preventDefault();
                this.showWarning('⚠️ Atualizar a página durante o jogo não é permitido!');
                return false;
            }
            
            // Bloquear Ctrl+Shift+R (Reload forçado)
            if (e.ctrlKey && e.shiftKey && e.key === 'R') {
                e.preventDefault();
                this.showWarning('⚠️ Recarregar a página não é permitido!');
                return false;
            }
        });
        
        // Bloquear antes de sair da página
        window.addEventListener('beforeunload', (e) => {
            // Só bloquear se estiver no jogo (não no lobby)
            if (window.location.pathname.includes('/jogar')) {
                e.preventDefault();
                e.returnValue = '⚠️ Tem certeza que deseja sair do jogo? Seu progresso será perdido.';
            }
        });
    }
    
    /**
     * Força modo tela cheia obrigatório
     */
    enforceFullscreen() {
        let hasExited = false;
        let fullscreenRequested = false;
        
        // Verificar se veio do botão "Começar Partida"
        const shouldGoFullscreen = sessionStorage.getItem('shouldGoFullscreen') === 'true';
        
        // Solicitar fullscreen (precisa ser chamado por ação do usuário)
        const requestFullscreen = () => {
            if (fullscreenRequested) return;
            
            fullscreenRequested = true;
            const elem = document.documentElement;
            if (elem.requestFullscreen) {
                elem.requestFullscreen()
                    .then(() => {
                        this.isFullscreen = true;
                        console.log('✅ Fullscreen ativado');
                        // Limpar flag do sessionStorage
                        sessionStorage.removeItem('shouldGoFullscreen');
                        // Esconder overlay
                        const overlay = document.getElementById('fullscreen-overlay');
                        if (overlay) overlay.remove();
                    })
                    .catch(err => {
                        console.error('Erro ao ativar fullscreen:', err);
                        fullscreenRequested = false;
                    });
            }
        };
        
        // Criar overlay pedindo para clicar
        const createFullscreenOverlay = () => {
            const overlay = document.createElement('div');
            overlay.id = 'fullscreen-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100vw;
                height: 100vh;
                background: rgba(0, 0, 0, 0.95);
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 9999;
                color: white;
                text-align: center;
                font-family: Arial, sans-serif;
            `;
            overlay.innerHTML = `
                <h1 style="font-size: 2em; margin-bottom: 20px;">🎮 Tudinha do Milhão</h1>
                <p style="font-size: 1.2em; margin-bottom: 30px;">Para uma melhor experiência</p>
                <button onclick="window.requestFullscreenActivation()" 
                        style="padding: 20px 40px; font-size: 1.2em; background: #10b981; color: white; border: none; border-radius: 10px; cursor: pointer; transition: all 0.3s;">
                    🖥️ ENTRAR EM TELA CHEIA
                </button>
                <p style="margin-top: 20px; font-size: 0.9em; opacity: 0.7;">Clique no botão acima para iniciar</p>
            `;
            document.body.appendChild(overlay);
        };
        
        // Expor função global para o botão
        window.requestFullscreenActivation = requestFullscreen;
        
        // Se veio do botão "Começar", ativar imediatamente (com pequeno delay)
        if (shouldGoFullscreen) {
            console.log('✅ Flag de fullscreen detectada! Ativando...');
            // Tentar ativar múltiplas vezes para garantir
            setTimeout(() => requestFullscreen(), 100);
            setTimeout(() => requestFullscreen(), 300);
            setTimeout(() => requestFullscreen(), 500);
            setTimeout(() => requestFullscreen(), 1000);
        } else {
            console.log('⚠️ Flag de fullscreen não detectada, criando overlay');
            // Se não, criar overlay
            createFullscreenOverlay();
        }
        
        // Bloquear ESC - se pressionar ESC, cancela o jogo
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                if (document.fullscreenElement) {
                    e.preventDefault();
                    // Cancelar o jogo
                    if (confirm('⚠️ Deseja realmente sair do jogo? Seu progresso será perdido.')) {
                        hasExited = true;
                        document.exitFullscreen();
                        window.location.href = '/jogo-milhao?canceled=true';
                    }
                }
            }
        });
        
        // Monitorar saída de fullscreen
        document.addEventListener('fullscreenchange', () => {
            if (!document.fullscreenElement && !hasExited) {
                // Tentou sair do fullscreen de forma não autorizada
                this.isFullscreen = false;
                fullscreenRequested = false;
                
                // Tentar reativar
                setTimeout(() => {
                    if (!hasExited) {
                        createFullscreenOverlay();
                        this.showWarning('⚠️ O jogo deve ser jogado em tela cheia!');
                    }
                }, 500);
            } else if (document.fullscreenElement) {
                this.isFullscreen = true;
            }
        });
    }
    
    /**
     * Bloqueia ações muito rápidas (anti-spam)
     */
    blockRapidActions() {
        // Interceptar todos os eventos de clique em botões
        document.addEventListener('click', (e) => {
            const now = Date.now();
            const timeSinceLastAction = now - this.lastActionTime;
            
            if (timeSinceLastAction < this.minActionInterval) {
                e.preventDefault();
                e.stopPropagation();
                this.showWarning('Aguarde um momento antes de clicar novamente.');
                return false;
            }
            
            this.lastActionTime = now;
        }, true); // Captura na fase de captura
    }
    
    /**
     * Mostra aviso ao usuário
     */
    showWarning(message) {
        const warning = document.createElement('div');
        warning.className = 'fixed top-20 left-1/2 transform -translate-x-1/2 bg-red-600 text-white px-6 py-4 rounded-lg shadow-xl z-50';
        warning.textContent = message;
        document.body.appendChild(warning);
        
        setTimeout(() => {
            warning.remove();
        }, 3000);
    }
    
    /**
     * Registra perda de foco no servidor
     */
    logFocusLoss() {
        fetch('/jogo-milhao/heartbeat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': this.getCsrfToken()
            },
            body: JSON.stringify({
                focus_lost: true,
                timestamp: new Date().toISOString()
            })
        });
    }
    
    getCsrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    }
}

// INICIALIZAÇÃO IMEDIATA - Executa assim que carregar
(function() {
    console.log('🚀 Game Security - Iniciando...');
    
    // Criar instância imediatamente
    try {
        window.gameSecurity = new GameSecurity();
        console.log('✅ Game Security inicializado com sucesso!');
    } catch (error) {
        console.error('❌ Erro ao inicializar Game Security:', error);
    }
})();

// Expor para debug
window.GameSecurity = GameSecurity;

