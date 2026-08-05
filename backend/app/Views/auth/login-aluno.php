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
    <title>Portal do Aluno - <?= htmlspecialchars($system_title) ?></title>
    <?php
        $pwaThemeColor = LayoutHelper::get('pwa_aluno_theme_color', LayoutHelper::get('primary_color', '#a855f7'));
        $pwaIcon192 = LayoutHelper::get('pwa_aluno_icon_192', LayoutHelper::getLogo1x1Url());
    ?>
    <link rel="manifest" href="<?= URL ?>/manifest-aluno.json">
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
        <!-- Lado Esquerdo - Imagem de Fundo (oculto no mobile para manter o formulário inteiro na tela) -->
        <div class="hidden md:flex flex-1 relative bg-cover bg-center bg-no-repeat" 
             style="background-image: url('<?= !empty($login_cover_url) ? htmlspecialchars($login_cover_url) : 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iIzM3NDE1MSIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjE0IiBmaWxsPSJ3aGl0ZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZHk9Ii4zZW0iPkNhcGE8L3RleHQ+PC9zdmc+' ?>');">
        </div>

        <!-- Lado Direito - Formulário de Login (no mobile ocupa toda a tela, formulário inteiro visível) -->
        <div class="flex-1 w-full bg-gray-50 flex flex-col items-center justify-center p-4 md:p-8 min-h-screen">
        <div class="w-full max-w-md flex flex-col">
            <!-- Logo pequena no topo (tamanho configurável no Master) -->
            <?php $logoLoginRem = (float) LayoutHelper::get('logo_size_login', '1') * 4; ?>
            <div class="text-center mb-6 md:mb-8">
                <?php if (!empty($logo_horizontal_url)): ?>
                    <img src="<?= htmlspecialchars($logo_horizontal_url) ?>" alt="Logo" class="mx-auto mb-3 md:mb-4 object-contain" style="max-height: <?= $logoLoginRem ?>rem; width: auto;">
                <?php else: ?>
                    <div class="w-12 h-12 md:w-16 md:h-16 bg-gradient-to-r from-blue-600 to-indigo-600 rounded-xl flex items-center justify-center mx-auto mb-3 md:mb-4">
                        <span class="text-white font-bold text-lg md:text-xl"><?= substr($system_title, 0, 1) ?></span>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Título -->
            <div class="text-center mb-6 md:mb-8">
                <h2 class="text-2xl md:text-3xl font-bold text-gray-900 mb-2">Portal do Aluno</h2>
                <p class="text-sm md:text-base text-gray-600">Entre com seu usuário e senha para acessar</p>
            </div>

            <!-- Formulário (submit tradicional: cookie enviado na mesma requisição, evita erro em aba anônima) -->
            <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
            <?php if (!empty($flash_msg)): ?>
            <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                <?= htmlspecialchars($flash_msg) ?>
            </div>
            <?php endif; ?>
            <form id="loginForm" action="<?= URL ?>/login" method="post" class="space-y-4 md:space-y-6">
                <input type="hidden" name="tipo" value="aluno">
                <!-- Honeypot: não preencher (proteção contra bots) -->
                <input type="text" name="site_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;">
                
                <!-- Código do Aluno -->
                <div>
                    <label for="login" class="block text-sm font-medium text-gray-700 mb-2">
                        Usuário
                    </label>
                    <input id="login" name="login" type="text" required
                           class="w-full px-4 py-2 md:py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors text-base"
                           placeholder="Digite seu usuário">
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
                            class="w-full bg-gradient-to-r from-blue-600 to-purple-600 hover:from-blue-700 hover:to-purple-700 text-white font-semibold py-2 md:py-3 px-4 rounded-lg transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 text-base">
                        Acessar Painel do Aluno
                    </button>
                </div>
            </form>

            <!-- Links -->
            <div class="mt-4 md:mt-6 text-center space-y-2">
                <a href="<?= URL ?>/primeiro-acesso" class="text-sm text-blue-600 hover:text-blue-500 block">
                    Primeiro acesso
                </a>
                <a href="<?= URL ?>/aluno/recuperar-senha" class="text-sm text-blue-600 hover:text-blue-500 block">
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

        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= URL ?>/service-worker.js').then(function (reg) {
                if (reg.active) {
                    reg.active.postMessage({ type: 'CLEAR_AUTH_CACHES' });
                }
            }).catch(function () {});
            navigator.serviceWorker.getRegistrations().then(function (regs) {
                regs.forEach(function (reg) {
                    if (reg.active) reg.active.postMessage({ type: 'CLEAR_AUTH_CACHES' });
                });
            }).catch(function () {});
        }
        try {
            localStorage.setItem('educatudo_auth_user', '');
            localStorage.removeItem('educatudo_auth_user');
        } catch (e) {}
    </script>
</body>
</html>
