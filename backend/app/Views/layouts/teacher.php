<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'EducaTudo - Professor') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <?php 
    // Incluir LayoutHelper
    require_once __DIR__ . '/../../Core/LayoutHelper.php';
    ?>
    <style>
        <?= LayoutHelper::generateCustomCSS() ?>
    </style>
    <?php include __DIR__ . '/components/form_control_safari.php'; ?>
    <?php if (isset($additional_css)): ?>
        <?= $additional_css ?>
    <?php endif; ?>
</head>
<body class="bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50 min-h-screen font-sans antialiased flex flex-col">
    <!-- Navbar -->
    <nav class="navbar-custom shadow-lg">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16">
                <div class="flex items-center">
                    <div class="flex-shrink-0">
                        <?php
                        $logoNavbar = LayoutHelper::getContextualLogo('navbar', 'h-8 w-auto', 'Logo');
                        if ($logoNavbar): ?>
                            <?= $logoNavbar ?>
                            <h1 class="sidebar-logo-fallback hidden text-white text-xl font-bold"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                        <?php else: ?>
                            <h1 class="text-white text-xl font-bold"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                        <?php endif; ?>
                    </div>
                    <div class="ml-10 flex items-baseline space-x-4">
                        <a href="<?= URL ?>/professor/dashboard" class="text-white hover:text-gray-200 px-3 py-2 rounded-md text-sm font-medium">
                            Dashboard
                        </a>
                        <a href="<?= URL ?>/professor/jornadas" class="text-white hover:text-gray-200 px-3 py-2 rounded-md text-sm font-medium">
                            Jornadas
                        </a>
                        <a href="<?= URL ?>/professor/exercicios" class="text-white hover:text-gray-200 px-3 py-2 rounded-md text-sm font-medium">
                            Exercícios
                        </a>
                        <a href="<?= URL ?>/forum" class="text-white hover:text-gray-200 px-3 py-2 rounded-md text-sm font-medium">
                            Fórum
                        </a>
                        <a href="<?= URL ?>/professor/drive" class="text-white hover:text-gray-200 px-3 py-2 rounded-md text-sm font-medium">
                            Drive
                        </a>
                    </div>
                </div>
                
                <div class="flex items-center space-x-4">
                    <span class="text-white">Prof. <?= htmlspecialchars($user['nome'] ?? 'Professor') ?></span>
                    <a href="<?= URL ?>/logout?portal=professor" class="text-white hover:text-gray-200 px-3 py-2 rounded-md text-sm font-medium">
                        Sair
                    </a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="flex-1 p-6 plataforma-conteudo">
        <?= $content ?>
    </main>

    <!-- Botão Flutuante WhatsApp Suporte -->
    <a href="https://wa.me/5516997360690" 
       target="_blank" 
       rel="noopener noreferrer"
       class="fixed bottom-6 right-6 bg-green-500 hover:bg-green-600 text-white px-4 py-3 rounded-full shadow-lg flex items-center space-x-2 z-50 transition-all duration-300 hover:scale-110">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
        </svg>
        <span class="font-semibold">Suporte</span>
    </a>

    <!-- Common JavaScript -->
    <script>
        // Global utility functions
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-success-custom text-white' : 
                type === 'error' ? 'bg-error-custom text-white' : 
                'bg-info-custom text-white'
            }`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // CSRF Token Helper
        function getCsrfToken() {
            const tokenElement = document.querySelector('meta[name="csrf-token"]');
            return tokenElement ? tokenElement.getAttribute('content') : '';
        }
    </script>

    <?php if (isset($additional_js)): ?>
        <?= $additional_js ?>
    <?php endif; ?>

    <?php include __DIR__ . '/components/plataforma_footer.php'; ?>
</body>
</html>
