<?php
require_once __DIR__ . '/../../Core/LayoutHelper.php';

// Buscar configurações de layout (logo na login conforme config Master: logo_use_login)
$logo_url = LayoutHelper::getLogoUrl();
$logo_horizontal_url = LayoutHelper::getLoginLogoUrl();
$login_cover_url = LayoutHelper::getLoginCoverUrl();
$system_title = LayoutHelper::getSystemTitle();
$system_subtitle = LayoutHelper::getSystemSubtitle();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>Portal do Professor - <?= htmlspecialchars($system_title) ?></title>
    <?php
        $pwaThemeColor = LayoutHelper::get('pwa_professor_theme_color', LayoutHelper::get('primary_color', '#a855f7'));
        $pwaIcon192 = LayoutHelper::get('pwa_professor_icon_192', LayoutHelper::getLogo1x1Url());
    ?>
    <link rel="manifest" href="<?= URL ?>/manifest-professor.json">
    <meta name="theme-color" content="<?= htmlspecialchars($pwaThemeColor) ?>">
    <?php if (!empty($pwaIcon192)): ?>
        <link rel="apple-touch-icon" href="<?= htmlspecialchars($pwaIcon192) ?>">
    <?php endif; ?>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563eb',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                        }
                    }
                }
            }
        }
    </script>
</head>
<body class="min-h-screen flex flex-col bg-gray-50">
    <div class="flex flex-1">
        <!-- Lado Esquerdo - Imagem de Fundo (oculto no mobile) -->
        <div class="hidden md:flex flex-1 relative bg-cover bg-center bg-no-repeat" 
             style="background-image: url('<?= !empty($login_cover_url) ? htmlspecialchars($login_cover_url) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iIzM3NDE1MSIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkNhcGE8L3RleHQ+PC9zdmc+' ?>');">
            
            <!-- Overlay azul -->
            <div class="absolute inset-0 bg-blue-900 bg-opacity-60"></div>
            
        </div>

        <!-- Lado Direito - Formulário de Login -->
        <div class="flex-1 w-full bg-gray-50 flex flex-col items-center justify-center p-4 md:p-8 min-h-screen">
        <div class="w-full max-w-md flex flex-col">
            <!-- Logo pequena no topo -->
            <div class="text-center mb-6 md:mb-8">
                <?php if (!empty($logo_horizontal_url)): ?>
                    <img src="<?= htmlspecialchars($logo_horizontal_url) ?>" alt="Logo" class="h-12 md:h-16 mx-auto mb-3 md:mb-4">
                <?php else: ?>
                    <div class="w-12 h-12 md:w-16 md:h-16 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center mx-auto mb-3 md:mb-4">
                        <span class="text-white font-bold text-lg md:text-xl"><?= substr($system_title, 0, 1) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Título -->
            <div class="text-center mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Portal do Professor</h2>
                <p class="text-sm md:text-base text-gray-600">Entre com seu email e senha para acessar</p>
            </div>

            <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
            <?php if (!empty($flash_msg)): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <?= htmlspecialchars($flash_msg) ?>
            </div>
            <?php endif; ?>
            <!-- Formulário de Login (submit tradicional evita erro de sessão em aba anônima) -->
            <form id="loginForm" action="<?= URL ?>/login" method="post" class="space-y-4 md:space-y-6">
                <input type="hidden" name="tipo" value="professor">
                <!-- Honeypot: não preencher (proteção contra bots) -->
                <input type="text" name="site_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;">
                
                <!-- Email do Professor -->
                <div>
                    <label for="login" class="block text-sm font-medium text-gray-700 mb-2">
                        Email
                    </label>
                    <input id="login" name="login" type="email" required
                           class="w-full px-4 py-2 md:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-base"
                           placeholder="Digite seu email">
                </div>

                <!-- Senha -->
                <div>
                    <label for="senha" class="block text-sm font-medium text-gray-700 mb-2">
                        Senha
                    </label>
                    <div class="relative">
                        <input id="senha" name="senha" type="password" required
                               class="w-full px-4 py-2 md:py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-base"
                               placeholder="Digite sua senha">
                        <button type="button" 
                                onclick="togglePassword('senha')"
                                class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 focus:outline-none transition-colors">
                            <svg id="senha-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                            </svg>
                            <svg id="senha-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Botão Entrar -->
                <div>
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 md:py-3 px-4 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-base">
                        Acessar Painel do Professor
                    </button>
                </div>
            </form>
            
            <!-- Formulário de Alteração de Senha (inicialmente oculto) -->
            <div id="passwordChangeForm" class="hidden space-y-4 md:space-y-6">
                <div class="text-center mb-4 md:mb-6">
                    <div class="inline-flex items-center justify-center w-12 h-12 md:w-16 md:h-16 bg-orange-100 rounded-full mb-3 md:mb-4">
                        <svg class="w-6 h-6 md:w-8 md:h-8 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                        </svg>
                    </div>
                    <h3 class="text-xl md:text-2xl font-bold text-gray-900 mb-2">Alteração Obrigatória de Senha 🔒</h3>
                    <p class="text-gray-600 text-xs md:text-sm">Por segurança, você deve alterar sua senha padrão antes de continuar</p>
                </div>
                
                <form id="changePasswordForm" class="space-y-6">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="tipo" value="professor">
                    <input type="hidden" id="changeEmail" name="email">
                    
                    <!-- Current Password Info -->
                    <div class="bg-orange-50 border border-orange-200 rounded-lg p-4">
                        <div class="flex items-center">
                            <svg class="w-5 h-5 text-orange-600 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-sm text-orange-800">
                                <strong>Senha atual:</strong> 123456 (padrão do sistema)
                            </p>
                        </div>
                    </div>
                    
                    <!-- Nova Senha -->
                    <div>
                        <label for="nova_senha" class="block text-sm font-medium text-gray-700 mb-2">
                            Nova Senha
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="nova_senha" 
                                   name="nova_senha" 
                                   class="w-full px-4 py-2 md:py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-base"
                                   placeholder="Digite sua nova senha"
                                   required
                                   minlength="8">
                            <button type="button" 
                                    onclick="togglePassword('nova_senha')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <svg id="nova_senha-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="nova_senha-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            </button>
                        </div>
                        
                        <!-- Password Strength Bar -->
                        <div class="mt-2">
                            <div class="flex items-center space-x-2 mb-1">
                                <div class="flex-1 h-2 bg-gray-200 rounded-full overflow-hidden">
                                    <div id="password-strength-bar" class="h-full transition-all duration-300 rounded-full" style="width: 0%"></div>
                                </div>
                                <span id="password-strength-text" class="text-xs font-medium text-gray-500">Fraca</span>
                            </div>
                        </div>
                        
                        <p class="text-xs text-gray-500 mt-1">Mínimo 8 caracteres com letras maiúsculas, minúsculas e números</p>
                    </div>
                    
                    <!-- Confirmar Senha -->
                    <div>
                        <label for="confirmar_senha" class="block text-sm font-medium text-gray-700 mb-2">
                            Confirmar Nova Senha
                        </label>
                        <div class="relative">
                            <input type="password" 
                                   id="confirmar_senha" 
                                   name="confirmar_senha" 
                                   class="w-full px-4 py-2 md:py-3 pr-12 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent transition-all duration-200 text-base"
                                   placeholder="Confirme sua nova senha"
                                   required
                                   minlength="8">
                            <button type="button" 
                                    onclick="togglePassword('confirmar_senha')"
                                    class="absolute right-3 top-1/2 transform -translate-y-1/2 text-gray-400 hover:text-gray-600 focus:outline-none">
                                <svg id="confirmar_senha-eye" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                                </svg>
                                <svg id="confirmar_senha-eye-off" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"></path>
                                </svg>
                            </button>
                        </div>
                        <div id="password-match" class="mt-1 text-xs"></div>
                    </div>
                    
                    <!-- Error Message -->
                    <div id="changePasswordError" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
                    
                    <!-- Submit Button -->
                    <button type="submit" 
                            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-3 px-4 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-base">
                        Alterar Senha e Entrar
                    </button>
                </form>
            </div>

            <!-- Links -->
            <div class="mt-4 md:mt-6 text-center">
                <a href="<?= URL ?>/professor/recuperar-senha" class="text-sm text-blue-600 hover:text-blue-500">
                    Esqueceu sua senha?
                </a>
            </div>
            
            <!-- Footer -->
            <footer class="mt-6 md:mt-8 pt-4 border-t border-gray-200">
                <div class="text-center">
                    <p class="text-xs text-gray-600 mb-1">Todos os direitos reservados Educatudo</p>
                    <p class="text-xs text-gray-600">
                        Feito com carinho por <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-gray-800 underline">Launs</a>
                    </p>
                    <p class="text-xs text-gray-600 mt-2">
                        <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a> •
                        <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a> •
                        <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
                    </p>
                </div>
            </footer>
        </div>
    </div>
    </div>


    <script>
        // Função para alternar visualização de senha
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const eye = document.getElementById(inputId + '-eye');
            const eyeOff = document.getElementById(inputId + '-eye-off');
            
            if (input.type === 'password') {
                input.type = 'text';
                eye.classList.add('hidden');
                eyeOff.classList.remove('hidden');
            } else {
                input.type = 'password';
                eye.classList.remove('hidden');
                eyeOff.classList.add('hidden');
            }
        }

        // Função para calcular força da senha
        function calculatePasswordStrength(password) {
            let strength = 0;
            let checks = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[^A-Za-z0-9]/.test(password)
            };
            
            if (checks.length) strength += 20;
            if (checks.uppercase) strength += 20;
            if (checks.lowercase) strength += 20;
            if (checks.number) strength += 20;
            if (checks.special) strength += 20;
            
            return { strength, checks };
        }

        // Função para atualizar barra de força
        function updatePasswordStrength(password) {
            const { strength, checks } = calculatePasswordStrength(password);
            const bar = document.getElementById('password-strength-bar');
            const text = document.getElementById('password-strength-text');
            
            if (!bar || !text) return;
            
            bar.style.width = strength + '%';
            
            if (strength < 40) {
                bar.className = 'h-full transition-all duration-300 rounded-full bg-red-500';
                text.textContent = 'Fraca';
                text.className = 'text-xs font-medium text-red-600';
            } else if (strength < 60) {
                bar.className = 'h-full transition-all duration-300 rounded-full bg-orange-500';
                text.textContent = 'Média';
                text.className = 'text-xs font-medium text-orange-600';
            } else if (strength < 80) {
                bar.className = 'h-full transition-all duration-300 rounded-full bg-yellow-500';
                text.textContent = 'Boa';
                text.className = 'text-xs font-medium text-yellow-600';
            } else {
                bar.className = 'h-full transition-all duration-300 rounded-full bg-green-500';
                text.textContent = 'Forte';
                text.className = 'text-xs font-medium text-green-600';
            }
        }

        function checkPasswordMatch() {
            const novaSenha = document.getElementById('nova_senha')?.value;
            const confirmarSenha = document.getElementById('confirmar_senha')?.value;
            const matchDiv = document.getElementById('password-match');
            
            if (!matchDiv) return;
            
            if (confirmarSenha) {
                if (novaSenha === confirmarSenha) {
                    matchDiv.textContent = '✓ Senhas coincidem';
                    matchDiv.className = 'mt-1 text-xs text-green-600';
                } else {
                    matchDiv.textContent = '✗ Senhas não coincidem';
                    matchDiv.className = 'mt-1 text-xs text-red-600';
                }
            } else {
                matchDiv.textContent = '';
            }
        }

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= URL ?>/service-worker.js').catch(() => {});
        }

        // Event listeners para formulário de alteração de senha
        const novaSenhaInput = document.getElementById('nova_senha');
        const confirmarSenhaInput = document.getElementById('confirmar_senha');
        
        if (novaSenhaInput) {
            novaSenhaInput.addEventListener('input', function() {
                updatePasswordStrength(this.value);
                if (confirmarSenhaInput?.value) {
                    checkPasswordMatch();
                }
            });
        }
        
        if (confirmarSenhaInput) {
            confirmarSenhaInput.addEventListener('input', checkPasswordMatch);
        }

        // Submit do formulário de alteração de senha
        const changePasswordForm = document.getElementById('changePasswordForm');
        if (changePasswordForm) {
            changePasswordForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const errorDiv = document.getElementById('changePasswordError');
                const submitBtn = this.querySelector('button[type="submit"]');
                
                errorDiv.classList.add('hidden');
                
                const novaSenha = formData.get('nova_senha');
                const confirmarSenha = formData.get('confirmar_senha');
                
                // Validações client-side
                if (novaSenha.length < 8) {
                    errorDiv.textContent = 'A senha deve ter pelo menos 8 caracteres';
                    errorDiv.classList.remove('hidden');
                    return;
                }
                
                if (novaSenha === '123456') {
                    errorDiv.textContent = 'A nova senha não pode ser a senha padrão';
                    errorDiv.classList.remove('hidden');
                    return;
                }
                
                if (novaSenha !== confirmarSenha) {
                    errorDiv.textContent = 'As senhas não coincidem';
                    errorDiv.classList.remove('hidden');
                    return;
                }
                
                submitBtn.disabled = true;
                submitBtn.textContent = 'Alterando...';
                
                try {
                    console.log('Enviando requisição para alterar senha...');
                    const response = await fetch('<?= URL ?>/auth/alterar-senha-obrigatoria', {
                        method: 'POST',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: formData
                    });
                    
                    console.log('Status da resposta:', response.status);
                    console.log('Headers:', response.headers);
                    
                    // Tentar ler como texto primeiro para debug
                    const textResponse = await response.clone().text();
                    console.log('Resposta em texto:', textResponse);
                    
                    // Verificar se a resposta é JSON
                    const contentType = response.headers.get('content-type');
                    console.log('Content-Type:', contentType);
                    
                    let result;
                    try {
                        result = JSON.parse(textResponse);
                        console.log('Resposta parseada:', result);
                    } catch (parseError) {
                        console.error('Erro ao parsear JSON:', parseError);
                        console.error('Texto recebido:', textResponse);
                        errorDiv.textContent = 'Erro no servidor. Resposta inválida: ' + textResponse.substring(0, 100);
                        errorDiv.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Alterar Senha e Entrar';
                        return;
                    }
                    
                    if (result.success) {
                        // Redireciona após alterar senha
                        console.log('Sucesso! Redirecionando para:', result.redirect);
                        window.location.href = result.redirect || '<?= URL ?>/professor/dashboard';
                    } else {
                        errorDiv.textContent = result.error || 'Erro ao alterar senha';
                        errorDiv.classList.remove('hidden');
                        submitBtn.disabled = false;
                        submitBtn.textContent = 'Alterar Senha e Entrar';
                    }
                } catch (error) {
                    console.error('Erro na requisição:', error);
                    errorDiv.textContent = 'Erro de conexão. Tente novamente. Erro: ' + error.message;
                    errorDiv.classList.remove('hidden');
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Alterar Senha e Entrar';
                }
            });
        }
    </script>
</body>
</html>
