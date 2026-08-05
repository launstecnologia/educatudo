<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Login Pais - EducaTudo</title>
    <link rel="manifest" href="<?= URL ?>/manifest-pais.json">
    <?php require_once __DIR__ . '/../../Core/LayoutHelper.php'; ?>
    <meta name="theme-color" content="<?= htmlspecialchars(LayoutHelper::get('primary_color', '#a855f7')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        <?= LayoutHelper::generateCustomCSS() ?>
    </style>
</head>
<body class="bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50 min-h-screen min-h-dvh flex flex-col items-center justify-center p-4">
    <div class="w-full max-w-md mb-4">
        <?php $ios_install_storage_key = 'ios_install_dismissed_pais_login'; include __DIR__ . '/../components/ios-install-banner.php'; ?>
    </div>
    <div class="max-w-md w-full">
        <!-- Login Form -->
        <div class="bg-white py-8 px-6 shadow-xl rounded-2xl border">
            <!-- Logo e Título dentro do card -->
            <div class="text-center mb-8">
                <?php if (LayoutHelper::getContextualLogo('login', 'h-16 w-auto mx-auto', 'Logo EducaTudo')): ?>
                    <div class="mb-4">
                        <?= LayoutHelper::getContextualLogo('login', 'h-16 w-auto mx-auto', 'Logo EducaTudo') ?>
                    </div>
                <?php else: ?>
                    <div class="w-16 h-16 bg-primary rounded-2xl flex items-center justify-center mx-auto mb-4 shadow-lg">
                        <span class="text-white font-bold text-2xl">👨‍👩‍👧‍👦</span>
                    </div>
                <?php endif; ?>
                <h2 class="text-lg text-gray-600 mb-2">Área dos Pais</h2>
            </div>
            <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
            <?php if (!empty($flash_msg)): ?>
            <div class="mb-4 bg-red-500 text-white px-4 py-3 rounded-lg">
                <?= htmlspecialchars($flash_msg) ?>
            </div>
            <?php endif; ?>
            <form id="loginForm" action="<?= URL ?>/login" method="post" class="space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <input type="hidden" name="tipo" value="pai">
                <!-- Honeypot: não preencher (proteção contra bots) -->
                <input type="text" name="site_url" value="" tabindex="-1" autocomplete="off" style="position:absolute;left:-9999px;width:1px;height:1px;">
                
                <!-- CPF Field -->
                <div>
                    <label for="login" class="block text-sm font-semibold text-gray-700 mb-2">
                        CPF
                    </label>
                    <input id="login" name="login" type="text" inputmode="numeric" required
                           class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
                           placeholder="Digite o CPF do responsável">
                </div>

                <!-- Senha -->
                <div>
                    <label for="senha" class="block text-sm font-semibold text-gray-700 mb-2">
                        Senha
                    </label>
                    <div class="relative">
                        <input id="senha" name="senha" type="password" required
                               class="w-full px-4 py-3 pr-12 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary focus:border-primary transition-colors"
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

                <!-- Submit Button -->
                <div>
                    <button type="submit" 
                            class="w-full flex justify-center py-3 px-4 border border-transparent rounded-lg shadow-sm text-sm font-semibold btn-primary-custom focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary transition-all duration-200">
                        Acessar Área dos Pais
                    </button>
                </div>
            </form>

            <!-- Links -->
            <div class="mt-6 text-center space-y-2">
                <a href="<?= URL ?>/recuperar-senha" class="text-sm text-gray-600 hover:text-primary transition-colors">
                    Esqueceu sua senha?
                </a>
            </div>
        </div>

    </div>

    <!-- Footer -->
    <footer class="mt-6 py-4 text-center border-t border-gray-200">
        <p class="text-sm text-gray-900 mb-1">Todos os direitos reservados Educatudo</p>
        <p class="text-sm text-gray-900">
            Feito com carinho por <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="text-gray-900 hover:text-gray-700 underline">Launs</a>
        </p>
        <p class="text-sm text-gray-900 mt-2">
            <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a> •
            <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a> •
            <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
        </p>
    </footer>


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

    </script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= URL ?>/service-worker.js').catch(function() {});
        }
    </script>
</body>
</html>
