<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <title><?= htmlspecialchars($title ?? 'EducaTudo - Pais') ?></title>
    <link rel="manifest" href="<?= URL ?>/manifest-pais.json">
    <?php require_once __DIR__ . '/../../Core/LayoutHelper.php'; ?>
    <meta name="theme-color" content="<?= htmlspecialchars(LayoutHelper::get('primary_color', '#a855f7')) ?>">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="mobile-web-app-capable" content="yes">
    <script src="https://cdn.tailwindcss.com"></script>
    <?php
    $filhos = $filhos ?? [];
    $filho = $filho ?? null;
    $current_page = $current_page ?? 'dashboard';
    ?>
    <style>
        <?= LayoutHelper::generateCustomCSS() ?>
        @media (max-width: 768px) {
            #sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                z-index: 50;
                transition: left 0.3s ease;
                height: 100vh;
                height: 100dvh;
                overflow-y: auto;
            }
            #sidebar.mobile-open { left: 0; }
            .sidebar-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.5); z-index: 40; }
            .sidebar-overlay.active { display: block; }
            .pais-mobile-content { padding-bottom: 4.5rem; }
        }
        @supports (padding-bottom: env(safe-area-inset-bottom)) {
            .pais-bottom-nav { padding-bottom: env(safe-area-inset-bottom); }
        }

    </style>
    <?php include __DIR__ . '/components/form_control_safari.php'; ?>
    <?php if (isset($additional_css)): ?><?= $additional_css ?><?php endif; ?>
</head>
<body class="bg-gray-50 min-h-screen min-h-dvh font-sans antialiased flex flex-col">
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    <div class="flex flex-1 relative">
        <?php include __DIR__ . '/components/parent_sidebar.php'; ?>

        <main class="flex-1 w-full md:w-auto">
            <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-6 py-3 md:py-4 sticky top-0 z-30">
                <div class="flex justify-between items-center gap-2">
                    <button id="mobileMenuToggle" class="md:hidden p-2 text-gray-600 hover:bg-gray-100 rounded-lg" aria-label="Menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-lg md:text-2xl font-bold text-gray-900 truncate"><?= htmlspecialchars($page_title ?? 'Portal dos Pais') ?></h1>
                        <p class="text-xs md:text-sm text-gray-600 truncate">Bem-vindo, <?= htmlspecialchars($user['nome'] ?? 'Responsável') ?>!</p>
                    </div>
                    <div class="flex items-center space-x-2 flex-shrink-0">
                        <?php
                        $usuario = $user ?? null;
                        $tipoUsuario = 'pai';
                        $notificacoesNaoLidas = 0;
                        $notificacoesRecentes = [];
                        if (isset($user['id'])) {
                            try {
                                require_once __DIR__ . '/../../Models/Notifications/Notification.php';
                                $notificacaoModel = new Notification();
                                $notificacoesNaoLidas = $notificacaoModel->getNaoLidas($user['id'], 'pai');
                                $notificacoesRecentes = $notificacaoModel->getByDestinatario($user['id'], 'pai', 5);
                            } catch (Exception $e) {
                                $notificacoesRecentes = [];
                            }
                        }
                        include __DIR__ . '/components/notification-banner.php';
                        ?>
                        <a href="<?= URL ?>/logout?portal=pais" class="p-2 text-gray-600 hover:bg-gray-100 rounded-lg text-sm font-medium">Sair</a>
                    </div>
                </div>
            </header>

            <div class="p-4 md:p-6 pais-mobile-content md:!pb-6">
                <?php $ios_install_storage_key = 'ios_install_dismissed_pais'; include __DIR__ . '/../components/ios-install-banner.php'; ?>
                <?= $content ?>
                <footer class="mt-8 pt-4 border-t border-gray-200 text-center">
                    <p class="text-xs text-gray-600">Todos os direitos reservados Educatudo</p>
                    <p class="text-xs text-gray-600 mt-2">
                        <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a> •
                        <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a> •
                        <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
                    </p>
                </footer>
            </div>
        </main>
    </div>

    <!-- Bottom navigation (mobile only) - estilo app -->
    <nav class="md:hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 z-40 pais-bottom-nav safe-area-pb" style="padding-bottom: max(env(safe-area-inset-bottom), 0.5rem);">
        <div class="flex justify-around items-center h-14">
            <a href="<?= URL ?>/pais/dashboard" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:bg-gray-50 <?= ($current_page ?? '') === 'dashboard' ? 'bg-purple-50 text-purple-700 font-medium' : '' ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11v2a1 1 0 01-1 1h-2m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path></svg>
                <span class="text-xs mt-0.5">Início</span>
            </a>
            <a href="<?= URL ?>/pais/filhos" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:bg-gray-50 <?= ($current_page ?? '') === 'filhos' ? 'bg-purple-50 text-purple-700 font-medium' : '' ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path></svg>
                <span class="text-xs mt-0.5">Filhos</span>
            </a>
            <a href="<?= URL ?>/pais/notificacoes" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:bg-gray-50 <?= ($current_page ?? '') === 'notificacoes' ? 'bg-purple-50 text-purple-700 font-medium' : '' ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                <span class="text-xs mt-0.5">Notificações</span>
            </a>
            <a href="<?= URL ?>/logout?portal=pais" class="flex flex-col items-center justify-center flex-1 py-2 text-gray-600 hover:bg-gray-50">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                <span class="text-xs mt-0.5">Sair</span>
            </a>
        </div>
    </nav>

    <a href="https://wa.me/5516997360690" target="_blank" rel="noopener noreferrer" class="fixed bottom-20 md:bottom-6 right-4 md:right-6 bg-green-500 hover:bg-green-600 text-white p-3 md:px-4 md:py-3 rounded-full shadow-lg flex items-center space-x-2 z-50">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/></svg>
        <span class="hidden md:inline font-semibold">Suporte</span>
    </a>

    <script>
        document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('mobile-open');
            document.getElementById('sidebar-overlay').classList.toggle('active');
        });
        document.getElementById('sidebar-overlay')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.remove('mobile-open');
            this.classList.remove('active');
        });
        document.getElementById('sidebarToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('mobile-open');
            document.getElementById('sidebar-overlay').classList.toggle('active');
        });
    </script>
    <?php if (isset($additional_js)): ?><?= $additional_js ?><?php endif; ?>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= URL ?>/service-worker.js').catch(function() {});
        }
    </script>
    <?php
    $base_url = defined('URL') ? URL : '';
    if (!class_exists('LayoutHelper')) { require_once __DIR__ . '/../Core/LayoutHelper.php'; }
    $onesignal_app_id = class_exists('LayoutHelper') ? trim(LayoutHelper::get('onesignal_app_id', '')) : '';
    if ($onesignal_app_id === '' && function_exists('env')) { $onesignal_app_id = trim(env('ONESIGNAL_APP_ID', '')); }
    include __DIR__ . '/../components/onesignal-init.php';
    ?>
</body>
</html>
