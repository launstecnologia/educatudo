<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <?php
    // Avatar do aluno via ContextoAluno (sessão — sem query extra por página)
    $avatar_url = null;
    if (isset($user['id']) && ($user['tipo'] ?? '') === 'aluno') {
        if (!class_exists('ContextoAluno')) {
            require_once __DIR__ . '/../../Core/ContextoAluno.php';
        }
        try {
            $avatar_url = ContextoAluno::getAvatarUrl(Database::getInstance());
        } catch (Exception $e) {
            $avatar_url = $_SESSION['avatar_url'] ?? null;
            if (is_string($avatar_url)) {
                $avatar_url = str_replace('/public/assets/', '/assets/', $avatar_url);
                $avatar_url = str_replace('/public/uploads/', '/uploads/', $avatar_url);
            }
        }
    }
    ?>
    <title><?= htmlspecialchars($title ?? 'EducaTudo') ?></title>
    <?php if (($current_page ?? '') === 'dashboard' && !empty($dashboard_sliders) && is_array($dashboard_sliders)): ?>
        <?php
        $sliderPreloadUrl = trim((string) ($dashboard_sliders[0]['image_url'] ?? ''));
        if ($sliderPreloadUrl !== ''):
            $sliderPreloadHost = parse_url($sliderPreloadUrl, PHP_URL_HOST);
        ?>
    <link rel="preload" as="image" href="<?= htmlspecialchars($sliderPreloadUrl) ?>" fetchpriority="high">
        <?php if (is_string($sliderPreloadHost) && $sliderPreloadHost !== '' && $sliderPreloadHost !== ($_SERVER['HTTP_HOST'] ?? '')): ?>
    <link rel="dns-prefetch" href="//<?= htmlspecialchars($sliderPreloadHost) ?>">
    <link rel="preconnect" href="https://<?= htmlspecialchars($sliderPreloadHost) ?>" crossorigin>
        <?php endif; ?>
        <?php endif; ?>
    <?php endif; ?>
    <link rel="manifest" href="<?= URL ?>/manifest-aluno.json">
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/htmx.org@2.0.4" crossorigin="anonymous"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Atkinson+Hyperlegible:wght@400;700&family=Lexend:wght@400;500;600;700&display=swap">
    <?php 
    // Incluir LayoutHelper
    require_once __DIR__ . '/../../Core/LayoutHelper.php';
    require_once __DIR__ . '/../../Core/GamesAccessSchedule.php';
    // Games deve seguir apenas as regras de módulo/horário configuradas.
    $gamesBloqueadoAluno = false;
    $gamesHorarioLiberadoAluno = GamesAccessSchedule::isAccessAllowedNow();
    if ($gamesBloqueadoAluno) {
        $gamesHorarioLiberadoAluno = false;
    }
    $gamesHorarioBloqueioMsgJs = json_encode(GamesAccessSchedule::studentBlockedMessage(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);

    $mobileDefaults = [
        'enabled' => false,
        'breakpoint' => 768,
        'layout' => 'bottom-navigation',
        'components' => [
            'header' => true,
            'breadcrumb' => false,
            'sidebar' => false
        ],
        'bottom_nav' => [
            'enabled' => true,
            'highlight_index' => 2,
            'items' => [
                ['label' => 'Dashboard', 'route' => '/dashboard', 'icon' => 'home'],
                ['label' => 'Exercícios', 'route' => '/exercicios-personalizados/criar', 'icon' => 'books'],
                ['label' => LayoutHelper::getIaName(), 'route' => '/chat', 'icon' => 'chat'],
                ['label' => 'Redações', 'route' => '/redacoes', 'icon' => 'pencil'],
                ['label' => 'Jogos', 'route' => '/jogos/access', 'icon' => 'game']
            ]
        ],
        'fab' => [
            'enabled' => false,
            'action' => ''
        ],
        'appearance' => [
            'spacing' => 'compact',
            'font_size' => 'base'
        ]
    ];

    $mobileConfig = json_decode(LayoutHelper::get('mobile_layout_aluno', ''), true);
    $mobileConfig = is_array($mobileConfig) ? array_replace_recursive($mobileDefaults, $mobileConfig) : $mobileDefaults;
    $mobileEnabled = !empty($mobileConfig['enabled']);
    $mobileBreakpoint = (int) ($mobileConfig['breakpoint'] ?? 768);
    $mobileShowSidebar = (bool) ($mobileConfig['components']['sidebar'] ?? false);
    $mobileShowHeader = (bool) ($mobileConfig['components']['header'] ?? true);
    $mobileBottomNavEnabled = (bool) ($mobileConfig['bottom_nav']['enabled'] ?? true);
    $mobileBottomNavItems = $mobileConfig['bottom_nav']['items'] ?? [];
    if ($gamesBloqueadoAluno && is_array($mobileBottomNavItems)) {
        $mobileBottomNavItems = array_values(array_filter($mobileBottomNavItems, static function ($item) {
            $route = (string) ($item['route'] ?? '');
            $label = mb_strtolower((string) ($item['label'] ?? ''));
            if ($route === '/jogos/access' || strpos($route, '/jogos/') === 0 || strpos($route, '/jogo-milhao') === 0) {
                return false;
            }
            return strpos($label, 'jogo') === false;
        }));
    }
    if (is_array($mobileBottomNavItems)) {
        $rotaModuloMobileNav = [
            '/chat' => 'chat',
            '/exercicios-personalizados/criar' => 'exercicios_ia',
            '/exercicios' => 'exercicios',
            '/redacoes' => 'redacoes',
            '/jogos/access' => 'jogos',
            '/jogos' => 'jogos',
            '/simulados' => 'simulados',
            '/ingles' => 'ingles',
            '/forum' => 'forum',
            '/drive' => 'drive',
            '/flashcards' => 'aluno_flashcards',
            '/cursos' => 'ead',
            '/aluno/aulas-online' => 'aulas_online',
        ];
        $mobileBottomNavItems = array_values(array_filter($mobileBottomNavItems, static function ($item) use ($rotaModuloMobileNav) {
            $route = '/' . trim((string) ($item['route'] ?? ''), '/');
            if ($route === '/' || $route === '/dashboard') {
                return true;
            }
            $modulo = null;
            foreach ($rotaModuloMobileNav as $prefixo => $chaveModulo) {
                if ($route === $prefixo || strpos($route, $prefixo . '/') === 0) {
                    $modulo = $chaveModulo;
                    break;
                }
            }
            if ($modulo === null) {
                return true;
            }
            return LayoutHelper::isModuleEnabled($modulo);
        }));
    }
    $mobileBottomNavHighlightIndex = (int) ($mobileConfig['bottom_nav']['highlight_index'] ?? 2);
    $mobileBottomNavHighlightIndex = max(0, min($mobileBottomNavHighlightIndex, count($mobileBottomNavItems) - 1));
    $mobileFabEnabled = (bool) ($mobileConfig['fab']['enabled'] ?? false);
    $mobileFabAction = (string) ($mobileConfig['fab']['action'] ?? '');
    $mobileSpacing = $mobileConfig['appearance']['spacing'] ?? 'compact';
    $mobileFontSize = $mobileConfig['appearance']['font_size'] ?? 'base';

    if (!function_exists('renderMobileIcon')) {
    function renderMobileIcon($icon)
    {
        if (is_string($icon) && strpos($icon, 'fa-') !== false) {
            return '<i class="' . htmlspecialchars($icon) . ' w-5 h-5" style="font-size: 1.25rem;"></i>';
        }
        $icons = [
            'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10.5L12 3l9 7.5v9a1.5 1.5 0 01-1.5 1.5H4.5A1.5 1.5 0 013 19.5v-9z" />',
            'chat' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7.5 17.25L3 21v-4.5A9 9 0 1112 21c-1.6 0-3.1-.4-4.5-1.1" />',
            'game' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 14h12l2 5H4l2-5zm3-6h6l-1 6H10l-1-6z" />',
            'books' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13" />',
            'user' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 14a4 4 0 10-6 0m6 0a6 6 0 01-6 0m6 0v4H9v-4" />',
            'pencil' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z" />'
        ];
        $path = $icons[$icon] ?? $icons['home'];
        return '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . $path . '</svg>';
    }
    }
    ?>
    <style>
        <?= LayoutHelper::generateCustomCSS() ?>

        /* SIDEBAR-FIX-20260731-v5 — override local do layout aluno (só item ativo destaca) */
        <?php
        $__sb = LayoutHelper::getSidebarColors();
        $__sbText = htmlspecialchars($__sb['text'], ENT_QUOTES, 'UTF-8');
        $__sbBg = htmlspecialchars($__sb['bg'], ENT_QUOTES, 'UTF-8');
        ?>
        #sidebar.sidebar-custom {
            --sidebar-text-color: <?= $__sbText ?> !important;
            --sidebar-bg-color: <?= $__sbBg ?> !important;
        }
        #sidebar.sidebar-custom a,
        #sidebar.sidebar-custom button,
        #sidebar.sidebar-custom .sidebar-text,
        #sidebar.sidebar-custom .text-white,
        #sidebar.sidebar-custom .text-purple-100,
        #sidebar.sidebar-custom .text-purple-200,
        #sidebar.sidebar-custom .text-blue-100,
        #sidebar.sidebar-custom .text-indigo-100,
        #sidebar.sidebar-custom #sidebar-user-name,
        #sidebar.sidebar-custom .sidebar-user-info p {
            color: <?= $__sbText ?> !important;
        }
        #sidebar.sidebar-custom nav a svg,
        #sidebar.sidebar-custom nav button svg,
        #sidebar.sidebar-custom .sidebar-toggle svg,
        #sidebar.sidebar-custom .sidebar-header button svg {
            color: <?= $__sbText ?> !important;
            stroke: <?= $__sbText ?> !important;
        }
        #sidebar.sidebar-custom .bg-white\/10,
        #sidebar.sidebar-custom .bg-white\/20,
        #sidebar.sidebar-custom nav a.bg-white\/20,
        #sidebar.sidebar-custom nav a.bg-white\/10 {
            background-color: rgba(255, 255, 255, 0.18) !important;
        }
        /* Itens inativos sem caixa (padrão Colag / anexo 2) */
        #sidebar.sidebar-custom nav > a:not(.bg-white\/10):not(.bg-white\/20),
        #sidebar.sidebar-custom nav .menu-group > button:not(.bg-white\/10):not(.bg-white\/20),
        #sidebar.sidebar-custom nav .menu-group a:not(.bg-white\/10):not(.bg-white\/20) {
            background-color: transparent;
        }
        #sidebar.sidebar-custom .hover\:bg-white\/10:hover,
        #sidebar.sidebar-custom .hover\:bg-white\/20:hover,
        #sidebar.sidebar-custom .hover\:bg-white\/15:hover {
            background-color: rgba(255, 255, 255, 0.12) !important;
        }
        #sidebar.sidebar-custom .sidebar-user-info {
            background-color: rgba(255, 255, 255, 0.12) !important;
        }
        #sidebar.sidebar-custom a.text-red-200,
        #sidebar.sidebar-custom a.text-red-200 .sidebar-text,
        #sidebar.sidebar-custom a.text-red-200 svg {
            color: #fca5a5 !important;
            stroke: #fca5a5 !important;
        }
        a.educahits-sublink,
        a.educahits-sublink .sidebar-text {
            color: <?= $__sbText ?> !important;
        }

        @media (max-width: <?= $mobileBreakpoint ?>px) {
            body[data-mobile-enabled="1"] .mobile-hide-sidebar {
                display: <?= $mobileShowSidebar ? 'block' : 'none' ?> !important;
            }
            body[data-mobile-enabled="1"] .mobile-hide-header {
                display: <?= $mobileShowHeader ? 'block' : 'none' ?> !important;
            }
            body[data-mobile-enabled="1"] .mobile-bottom-nav {
                display: <?= $mobileBottomNavEnabled ? 'flex' : 'none' ?> !important;
            }
            body[data-mobile-enabled="1"] .mobile-content {
                padding-bottom: <?= $mobileBottomNavEnabled ? '4.5rem' : '0' ?> !important;
            }
        }

        body.mobile-spacing-compact .mobile-content {
            padding-top: 0.5rem;
        }
        body.mobile-spacing-comfortable .mobile-content {
            padding-top: 1rem;
        }
        body.mobile-font-sm {
            font-size: 0.875rem;
        }
        body.mobile-font-lg {
            font-size: 1.05rem;
        }
        
        /* Scroll da sidebar do aluno: visível quando o menu estoura */
        .sidebar-scroll {
            overflow-y: auto;
            overflow-x: hidden;
            scrollbar-width: thin;
            scrollbar-color: rgba(255,255,255,0.6) rgba(255,255,255,0.1);
        }
        /* Indicador de navegação HTMX (sem refresh completo) */
        #htmx-progress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0;
            z-index: 10000;
            background: linear-gradient(90deg, #a855f7, #6366f1);
            opacity: 0;
            pointer-events: none;
            transition: width 0.25s ease, opacity 0.2s ease;
        }
        #htmx-progress.htmx-progress-active {
            opacity: 1;
            width: 70%;
            transition: width 8s cubic-bezier(0.1, 0.05, 0, 1), opacity 0.15s ease;
        }
        #htmx-progress.htmx-progress-done {
            opacity: 1;
            width: 100%;
            transition: width 0.15s ease, opacity 0.3s ease 0.15s;
        }
        .sidebar-scroll::-webkit-scrollbar {
            width: 8px;
        }
        .sidebar-scroll::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
            border-radius: 4px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.5);
            border-radius: 4px;
        }
        .sidebar-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(255,255,255,0.7);
        }
        @font-face {
            font-family: 'OpenDyslexic';
            src: url('https://cdn.jsdelivr.net/npm/@fontsource/opendyslexic@5.0.0/files/opendyslexic-latin-400-normal.woff2') format('woff2');
            font-weight: 400;
            font-display: swap;
        }
        @font-face {
            font-family: 'OpenDyslexic';
            src: url('https://cdn.jsdelivr.net/npm/@fontsource/opendyslexic@5.0.0/files/opendyslexic-latin-700-normal.woff2') format('woff2');
            font-weight: 700;
            font-display: swap;
        }

        /* Mobile sidebar e overlay (igual ao Professor) */
        @media (max-width: 768px) {
            #sidebar {
                position: fixed;
                left: -100%;
                top: 0;
                z-index: 50;
                transition: left 0.3s ease;
                height: 100vh;
                overflow-y: auto;
            }
            #sidebar.mobile-open {
                left: 0;
            }
            .sidebar-overlay {
                display: none;
                position: fixed;
                inset: 0;
                background: rgba(0, 0, 0, 0.5);
                z-index: 40;
            }
            .sidebar-overlay.active {
                display: block;
            }
        }

        .ei-accessible.ei-family-lexend,
        .ei-accessible.ei-family-lexend p,
        .ei-accessible.ei-family-lexend span,
        .ei-accessible.ei-family-lexend label,
        .ei-accessible.ei-family-lexend li,
        .ei-accessible.ei-family-lexend div,
        .ei-accessible.ei-family-lexend button {
            font-family: 'Lexend', 'Atkinson Hyperlegible', Verdana, Arial, sans-serif !important;
        }
        .ei-accessible.ei-family-opendyslexic,
        .ei-accessible.ei-family-opendyslexic p,
        .ei-accessible.ei-family-opendyslexic span,
        .ei-accessible.ei-family-opendyslexic label,
        .ei-accessible.ei-family-opendyslexic li,
        .ei-accessible.ei-family-opendyslexic div,
        .ei-accessible.ei-family-opendyslexic button {
            font-family: 'OpenDyslexic', 'Comic Sans MS', Verdana, sans-serif !important;
        }
        .ei-accessible.ei-font-small { font-size: .95rem; }
        .ei-accessible.ei-font-medium { font-size: 1.08rem; }
        .ei-accessible.ei-font-large { font-size: 1.18rem; }
        .ei-accessible.ei-font-xlarge { font-size: 1.32rem; }
        .ei-accessible.ei-font-small .text-sm { font-size: .82rem !important; }
        .ei-accessible.ei-font-medium .text-sm { font-size: .95rem !important; }
        .ei-accessible.ei-font-large .text-sm { font-size: 1.03rem !important; }
        .ei-accessible.ei-font-xlarge .text-sm { font-size: 1.15rem !important; }
        .ei-accessible.ei-text-spacing-medium p,
        .ei-accessible.ei-text-spacing-medium label,
        .ei-accessible.ei-text-spacing-medium li,
        .ei-accessible.ei-text-spacing-medium .text-sm,
        .ei-accessible.ei-text-spacing-medium .text-base { line-height: 1.75 !important; letter-spacing: .01em !important; }
        .ei-accessible.ei-text-spacing-large p,
        .ei-accessible.ei-text-spacing-large label,
        .ei-accessible.ei-text-spacing-large li,
        .ei-accessible.ei-text-spacing-large .text-sm,
        .ei-accessible.ei-text-spacing-large .text-base { line-height: 2 !important; letter-spacing: .025em !important; }
        .ei-accessible.ei-element-spacing-medium .space-y-3 > * + * { margin-top: 1rem !important; }
        .ei-accessible.ei-element-spacing-medium .space-y-4 > * + * { margin-top: 1.35rem !important; }
        .ei-accessible.ei-element-spacing-medium .gap-2 { gap: .75rem !important; }
        .ei-accessible.ei-element-spacing-medium .gap-3 { gap: 1rem !important; }
        .ei-accessible.ei-element-spacing-large .space-y-3 > * + * { margin-top: 1.35rem !important; }
        .ei-accessible.ei-element-spacing-large .space-y-4 > * + * { margin-top: 1.75rem !important; }
        .ei-accessible.ei-element-spacing-large .gap-2 { gap: 1rem !important; }
        .ei-accessible.ei-element-spacing-large .gap-3 { gap: 1.25rem !important; }
        .ei-accessible.ei-button-large button,
        .ei-accessible.ei-button-large a.inline-flex,
        .ei-accessible.ei-button-large input[type=submit] { min-height: 3rem !important; padding: .75rem 1.1rem !important; font-size: 1rem !important; }
        .ei-accessible.ei-button-xlarge button,
        .ei-accessible.ei-button-xlarge a.inline-flex,
        .ei-accessible.ei-button-xlarge input[type=submit] { min-height: 3.5rem !important; padding: 1rem 1.35rem !important; font-size: 1.125rem !important; }
        .ei-accessible.ei-highlight-buttons button,
        .ei-accessible.ei-highlight-buttons a.inline-flex,
        .ei-accessible.ei-highlight-buttons input[type=submit] { border-width: 2px !important; box-shadow: 0 0 0 2px rgba(37,99,235,.12) !important; }
        .ei-accessible.ei-highlight-focus :focus,
        .ei-accessible.ei-highlight-focus :focus-visible { outline: 4px solid #f59e0b !important; outline-offset: 3px !important; box-shadow: 0 0 0 6px rgba(245,158,11,.18) !important; }
        .ei-accessible.ei-contrast-high {
            background: #000 !important;
            color: #fff !important;
        }
        .ei-accessible.ei-contrast-high #student-main-content,
        .ei-accessible.ei-contrast-high .mobile-content,
        .ei-accessible.ei-contrast-high header,
        .ei-accessible.ei-contrast-high footer,
        .ei-accessible.ei-contrast-high .bg-white,
        .ei-accessible.ei-contrast-high .bg-gray-50,
        .ei-accessible.ei-contrast-high .bg-gray-100,
        .ei-accessible.ei-contrast-high .bg-blue-50,
        .ei-accessible.ei-contrast-high .bg-indigo-50,
        .ei-accessible.ei-contrast-high .bg-purple-50,
        .ei-accessible.ei-contrast-high .bg-green-50,
        .ei-accessible.ei-contrast-high .bg-yellow-50,
        .ei-accessible.ei-contrast-high .rounded-xl,
        .ei-accessible.ei-contrast-high .rounded-lg {
            background: #000 !important;
            color: #fff !important;
        }
        .ei-accessible.ei-contrast-high .text-gray-900,
        .ei-accessible.ei-contrast-high .text-gray-800,
        .ei-accessible.ei-contrast-high .text-gray-700,
        .ei-accessible.ei-contrast-high .text-gray-600,
        .ei-accessible.ei-contrast-high .text-gray-500,
        .ei-accessible.ei-contrast-high .text-blue-800,
        .ei-accessible.ei-contrast-high .text-indigo-700,
        .ei-accessible.ei-contrast-high .text-purple-700,
        .ei-accessible.ei-contrast-high .text-green-700,
        .ei-accessible.ei-contrast-high .text-yellow-700 {
            color: #fff !important;
        }
        .ei-accessible.ei-contrast-high .text-gray-400,
        .ei-accessible.ei-contrast-high .text-gray-300 {
            color: #e5e7eb !important;
        }
        .ei-accessible.ei-contrast-high .border,
        .ei-accessible.ei-contrast-high .border-gray-100,
        .ei-accessible.ei-contrast-high .border-gray-200,
        .ei-accessible.ei-contrast-high .border-gray-300,
        .ei-accessible.ei-contrast-high .border-blue-200,
        .ei-accessible.ei-contrast-high .border-indigo-200,
        .ei-accessible.ei-contrast-high .border-purple-200 {
            border-color: #fff !important;
        }
        .ei-accessible.ei-contrast-high input,
        .ei-accessible.ei-contrast-high select,
        .ei-accessible.ei-contrast-high textarea {
            background: #000 !important;
            color: #fff !important;
            border-color: #fff !important;
        }
        .ei-accessible.ei-contrast-high input::placeholder,
        .ei-accessible.ei-contrast-high textarea::placeholder {
            color: #d1d5db !important;
        }
        .ei-accessible.ei-contrast-high button,
        .ei-accessible.ei-contrast-high a.inline-flex,
        .ei-accessible.ei-contrast-high .shadow,
        .ei-accessible.ei-contrast-high .shadow-lg,
        .ei-accessible.ei-contrast-high .shadow-xl {
            box-shadow: 0 0 0 2px #fff !important;
        }
        .ei-accessible.ei-contrast-high a { color: #67e8f9 !important; }
        .ei-accessible.ei-contrast-high img,
        .ei-accessible.ei-contrast-high video,
        .ei-accessible.ei-contrast-high iframe {
            filter: contrast(1.15) brightness(.9) !important;
        }
        /* Alto contraste: navbar/sidebar (e a area do logo) tambem ficam pretos,
           removendo o azul da cor padrao do navbar. */
        .ei-accessible.ei-contrast-high .navbar-custom,
        .ei-accessible.ei-contrast-high .sidebar-custom,
        .ei-accessible.ei-contrast-high .sidebar-custom .sidebar-header,
        .ei-accessible.ei-contrast-high .sidebar-icon,
        .ei-accessible.ei-contrast-high .sidebar-user-info,
        .ei-accessible.ei-contrast-high .logo-navbar-wrap {
            background: #000 !important;
            background-image: none !important;
            background-color: #000 !important;
            color: #fff !important;
        }
        .ei-accessible.ei-contrast-high .sidebar-custom { border-right: 2px solid #fff !important; }
        .ei-accessible.ei-contrast-high .navbar-custom { border-bottom: 2px solid #fff !important; }
        .ei-accessible.ei-contrast-high .sidebar-icon { border: 1px solid #fff !important; }
        .ei-accessible.ei-contrast-high .navbar-custom .text-white,
        .ei-accessible.ei-contrast-high .sidebar-custom .text-white,
        .ei-accessible.ei-contrast-high .sidebar-text,
        .ei-accessible.ei-contrast-high .text-purple-100,
        .ei-accessible.ei-contrast-high .text-purple-200 { color: #fff !important; }
        .ei-accessible.ei-contrast-inverted { filter: invert(1) hue-rotate(180deg) !important; background: #000 !important; }
        .ei-accessible.ei-contrast-inverted img,
        .ei-accessible.ei-contrast-inverted video,
        .ei-accessible.ei-contrast-inverted iframe { filter: invert(1) hue-rotate(180deg) !important; }
        .ei-accessible.ei-grayscale,
        .ei-accessible.ei-contrast-grayscale { filter: grayscale(1) !important; }
        .ei-accessible.ei-focus-mode { background: #fff !important; }
        .ei-accessible.ei-focus-mode .sidebar-user-info,
        .ei-accessible.ei-focus-mode [data-secondary-widget] { display: none !important; }
        .ei-accessible.ei-reduce-motion *,
        .ei-accessible.ei-reduce-motion *::before,
        .ei-accessible.ei-reduce-motion *::after { animation: none !important; transition: none !important; scroll-behavior: auto !important; }
        .ei-accessible.ei-highlight-cursor,
        .ei-accessible.ei-highlight-cursor * {
            cursor: url("data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 width=%2732%27 height=%2732%27 viewBox=%270 0 32 32%27%3E%3Ccircle cx=%2710%27 cy=%2710%27 r=%278%27 fill=%27none%27 stroke=%27%23f59e0b%27 stroke-width=%274%27/%3E%3Cpath d=%27M10 10 L26 26%27 stroke=%27%23000%27 stroke-width=%273%27/%3E%3C/svg%3E") 10 10, auto !important;
        }
    </style>
    <?php include __DIR__ . '/components/form_control_safari.php'; ?>
    <?php if (isset($additional_css)): ?>
        <?= $additional_css ?>
    <?php endif; ?>
    <?php
    $studentAccessibilityEnabled = (($user['tipo'] ?? '') === 'aluno' && empty($preview));
    ?>
</head>
<body class="bg-gradient-to-br from-purple-50 via-indigo-50 to-blue-50 font-sans antialiased overflow-hidden flex flex-col mobile-spacing-<?= htmlspecialchars($mobileSpacing) ?> mobile-font-<?= htmlspecialchars($mobileFontSize) ?>"
      style="height: 100vh;"
      data-mobile-enabled="<?= $mobileEnabled ? '1' : '0' ?>"
      data-auth-user="<?= htmlspecialchars((string)(($user['tipo'] ?? '') . ':' . (int)($user['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>"
      data-auth-name="<?= htmlspecialchars((string)($user['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
      data-sidebar-fix="20260731-v5">
    <!-- sidebar-fix=20260731-v5 — padrão Colag: só o item ativo com highlight -->
    <div id="htmx-progress" aria-hidden="true"></div>
    <!-- Overlay para mobile sidebar (igual ao Professor) -->
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    
    <div class="flex flex-1 relative" style="height: 100vh; overflow: hidden;">
        <!-- Sidebar: no mobile fica fixo e desliza (igual ao Professor) -->
        <?php $__sbInline = LayoutHelper::getSidebarColors(); ?>
        <aside id="sidebar"
               class="w-64 md:w-64 sidebar-custom shadow-2xl h-screen max-h-screen flex flex-col transition-all duration-300 ease-in-out fixed md:relative md:left-0 left-0 z-50"
               style="color: <?= htmlspecialchars($__sbInline['text'], ENT_QUOTES, 'UTF-8') ?>; --sidebar-text-color: <?= htmlspecialchars($__sbInline['text'], ENT_QUOTES, 'UTF-8') ?>; --sidebar-bg-color: <?= htmlspecialchars($__sbInline['bg'], ENT_QUOTES, 'UTF-8') ?>;"
               data-sidebar-text="<?= htmlspecialchars($__sbInline['text'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="sidebar-scroll p-6 flex flex-col flex-1 min-h-0 overflow-y-auto overflow-x-hidden">
                <!-- Logo e Toggle -->
                <div class="flex items-center justify-between mb-8 sidebar-header">
                    <div class="flex items-center sidebar-logo-container">
                        <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sidebar-icon">
                            <button id="expandSidebar" class="text-white hover:text-purple-200 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="sidebar-text-container logo-navbar-wrap">
                            <?php if (LayoutHelper::getContextualLogo('sidebar', '', 'Logo')): ?>
                                <?= LayoutHelper::getContextualLogo('sidebar', 'max-h-full w-auto', 'Logo') ?>
                            <?php else: ?>
                                <h1 class="text-xl font-bold text-white sidebar-text"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                                <p class="text-sm text-purple-100 sidebar-text">Ecossistema Educacional</p>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button id="sidebarToggle" class="text-white hover:bg-white/20 p-2 rounded-lg transition-colors sidebar-toggle">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                </div>

            <!-- User Info -->
            <div id="sidebar-user-card" class="bg-white/10 backdrop-blur-sm rounded-xl p-4 mb-6 sidebar-user-info" data-sidebar-auth-user="<?= htmlspecialchars((string)(($user['tipo'] ?? '') . ':' . (int)($user['id'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>">
                <div class="flex items-center">
                    <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3 overflow-hidden">
                        <?php if (!empty($avatar_url)): ?>
                            <img src="<?= htmlspecialchars($avatar_url) ?>" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" class="w-full h-full object-cover rounded-full" onerror="if(!this.dataset.avatarFallback){this.dataset.avatarFallback='1';this.src=this.src.replace('/public/assets/','/assets/').replace('/public/uploads/','/uploads/');}">
                        <?php else: ?>
                            <span class="text-white font-semibold text-sm"><?= strtoupper(substr($user['nome'] ?? 'Aluno', 0, 2)) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="sidebar-text-container">
                        <p id="sidebar-user-name" class="text-sm font-medium text-white sidebar-text"><?= htmlspecialchars($user['nome'] ?? '') ?></p>
                        <p class="text-xs text-purple-100 sidebar-text">Aluno</p>
                    </div>
                </div>
            </div>

            <!-- Navigation Menu: navegação FULL PAGE (sem hx-boost).
                 Soft-nav do HTMX deixava a sidebar stale e misturava alunos em PCs de lab. -->
            <nav class="space-y-1">
                <?php if (!empty($preview)): ?>
                <a href="<?= URL ?>/professor/jornadas<?= !empty($jornada['id']) ? '/' . (int)$jornada['id'] : '' ?>" class="flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
                    </svg>
                    <span class="sidebar-text">Voltar ao painel da jornada</span>
                </a>
                <?php else: ?>
                <!-- Dashboard -->
                <a href="<?= URL ?>/dashboard" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'dashboard' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                    </svg>
                    <span class="sidebar-text">Dashboard</span>
                </a>
                <a href="<?= URL ?>/agenda" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'agenda' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="sidebar-text">Agenda</span>
                </a>
                <?php
                if (!function_exists('educatudo_render_external_apps_aluno_rows')) {
                    /**
                     * Links de apps externos (aluno) abrindo via /external-apps/abrir/{id}.
                     *
                     * @param array<int, array{id:string,nome:string,nova_guia:bool,menu_icone?:string}> $items
                     */
                    function educatudo_render_external_apps_aluno_rows(array $items): void
                    {
                        $gamesHorarioMsgJs = json_encode(GamesAccessSchedule::studentBlockedMessage(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
                        $iconDs = [
                            'link' => ['M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14'],
                            'book' => ['M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5-1.253'],
                            'game' => ['M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5-1.253'],
                            'music' => ['M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3'],
                            'document' => ['M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
                            'globe' => ['M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064M21 12a9 9 0 11-18 0 9 9 0 0118 0z'],
                            'chat' => ['M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z'],
                            'sparkles' => ['M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 2.143L13 21l-2.286-6.857L5 12l5.714-2.143L13 3z'],
                        ];
                        foreach ($items as $app) {
                            $id = (string) ($app['id'] ?? '');
                            $nome = (string) ($app['nome'] ?? '');
                            if ($id === '' || $nome === '') {
                                continue;
                            }
                            $href = rtrim(URL, '/') . '/external-apps/abrir/' . rawurlencode($id);
                            $newTab = !empty($app['nova_guia']);
                            $foraGames = !empty($app['fora_do_horario_games']);
                            $ik = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($app['menu_icone'] ?? 'link')));
                            if ($ik === '' || !isset($iconDs[$ik])) {
                                $ik = 'link';
                            }
                            ?>
                            <a href="<?= $foraGames ? '#' : htmlspecialchars($href) ?>"
                               <?php if ($foraGames): ?>onclick="event.preventDefault(); alert(<?= $gamesHorarioMsgJs ?>); return false;" aria-disabled="true"<?php else: ?><?= $newTab ? ' target="_blank" rel="noopener noreferrer"' : '' ?><?php endif; ?>
                               class="flex items-center px-4 py-2 text-purple-100 hover:bg-white/20 hover:text-white rounded-lg transition-all duration-200<?= $foraGames ? ' opacity-55 cursor-not-allowed' : '' ?>">
                                <svg class="w-5 h-5 mr-3 flex-shrink-0 opacity-90" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                    <?php foreach ($iconDs[$ik] as $d): ?>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= htmlspecialchars((string) $d, ENT_QUOTES, 'UTF-8') ?>"></path>
                                    <?php endforeach; ?>
                                </svg>
                                <span class="sidebar-text text-sm"><?= htmlspecialchars($nome) ?></span>
                            </a>
                            <?php
                        }
                    }
                }

                $normExtUrlAluno = static function (string $value): string {
                    $value = strtolower(trim($value));
                    if ($value === '') {
                        return '';
                    }

                    return rtrim($value, '/');
                };

                $allowedExternalMenuGrupos = ['estudo', 'colag', 'entretenimento', 'sistema'];
                $allowedMenuIcones = ['link', 'book', 'game', 'music', 'document', 'globe', 'chat', 'sparkles'];
                $externalAppsAlunoByMenu = [
                    'estudo' => [],
                    'colag' => [],
                    'entretenimento' => [],
                    'sistema' => [],
                ];
                $nativoSubstituido = array_fill_keys(['jogos', 'educalabs', 'educa_hits', 'notes'], false);
                $externalAlunoUrlSet = [];
                $educahitsEscutarNovaGuiaFromApps = false;
                $moduloNativoPorSubstituicao = [
                    'jogos' => 'jogos',
                    'educalabs' => 'educalabs',
                    'educa_hits' => 'educa_hits',
                    'notes' => 'aluno_caderno_novo',
                ];
                $rawExtAppsMenu = json_decode((string) LayoutHelper::get('external_apps_links', '[]'), true);
                if (!is_array($rawExtAppsMenu)) {
                    $rawExtAppsMenu = [];
                }
                if ($rawExtAppsMenu === []) {
                    $legacyExternalForMenu = [
                        ['id' => 'educalabs', 'nome' => 'EducaLabs', 'menu_grupo' => 'estudo', 'menu_icone' => 'sparkles', 'substituir_nativo' => 'educalabs', 'aluno' => true, 'nova_guia' => true, 'url' => trim((string) LayoutHelper::get('educalabs_external_url', ''))],
                        ['id' => 'games', 'nome' => 'Games', 'menu_grupo' => 'entretenimento', 'menu_icone' => 'game', 'substituir_nativo' => 'jogos', 'aluno' => true, 'nova_guia' => true, 'url' => trim((string) LayoutHelper::get('games_external_url', ''))],
                        ['id' => 'notes', 'nome' => 'Notes', 'menu_grupo' => 'estudo', 'menu_icone' => 'document', 'substituir_nativo' => 'notes', 'aluno' => true, 'nova_guia' => true, 'url' => trim((string) LayoutHelper::get('notes_external_url', ''))],
                    ];
                    foreach ($legacyExternalForMenu as $legacyLink) {
                        if (trim((string) ($legacyLink['url'] ?? '')) !== '') {
                            $rawExtAppsMenu[] = $legacyLink;
                        }
                    }
                }
                foreach ($rawExtAppsMenu as $idx => $link) {
                    if (!is_array($link)) {
                        continue;
                    }
                    if (empty($link['aluno'])) {
                        continue;
                    }
                    $nome = trim((string) ($link['nome'] ?? $link['label'] ?? ''));
                    $url = trim((string) ($link['url'] ?? ''));
                    if ($nome === '' || $url === '') {
                        continue;
                    }
                    if ($gamesBloqueadoAluno) {
                        $idLower = strtolower(trim((string) ($link['id'] ?? '')));
                        $nomeLower = strtolower($nome);
                        $urlLower = strtolower($url);
                        $subNatLower = strtolower(trim((string) ($link['substituir_nativo'] ?? '')));
                        $isGamesApp = (
                            $idLower === 'games'
                            || $subNatLower === 'jogos'
                            || strpos($nomeLower, 'game') !== false
                            || strpos($urlLower, 'games') !== false
                        );
                        if ($isGamesApp) {
                            continue;
                        }
                    }
                    $mg = strtolower(trim((string) ($link['menu_grupo'] ?? 'colag')));
                    if (!in_array($mg, $allowedExternalMenuGrupos, true)) {
                        $mg = 'colag';
                    }
                    $mic = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) ($link['menu_icone'] ?? 'link')));
                    if ($mic === '' || !in_array($mic, $allowedMenuIcones, true)) {
                        $mic = 'link';
                    }
                    $subNat = strtolower(trim((string) ($link['substituir_nativo'] ?? '')));
                    if ($subNat !== '' && isset($moduloNativoPorSubstituicao[$subNat]) && !LayoutHelper::isModuleEnabled($moduloNativoPorSubstituicao[$subNat])) {
                        continue;
                    }
                    $externalAppsAlunoByMenu[$mg][] = [
                        'id' => (string) ($link['id'] ?? $idx),
                        'nome' => $nome,
                        'nova_guia' => !empty($link['nova_guia']),
                        'menu_icone' => $mic,
                        'fora_do_horario_games' => GamesAccessSchedule::shouldBlockStudentGamesLink($link),
                    ];
                    $nu = $normExtUrlAluno($url);
                    if ($nu !== '') {
                        $externalAlunoUrlSet[$nu] = true;
                    }
                    if (in_array($subNat, ['jogos', 'educalabs', 'educa_hits', 'notes'], true)) {
                        $nativoSubstituido[$subNat] = true;
                    }
                    $ln = strtolower($nome);
                    $lu = strtolower($url);
                    if ((strpos($ln, 'educahits') !== false || strpos($lu, 'educahits') !== false) && !empty($link['nova_guia'])) {
                        $educahitsEscutarNovaGuiaFromApps = true;
                    }
                }
                foreach ($externalAppsAlunoByMenu as $grupoExt => $appsExt) {
                    usort($externalAppsAlunoByMenu[$grupoExt], static function ($a, $b) {
                        $na = strtolower((string) ($a['nome'] ?? ''));
                        $nb = strtolower((string) ($b['nome'] ?? ''));
                        return $na <=> $nb;
                    });
                }
                ?>


                <!-- Colag (Nome configurável) -->
                <?php 
                $menuColagNome = LayoutHelper::get('menu_colag_nome', 'Colag');
                ?>
                <div class="menu-group">
                    <button type="button" onclick="toggleMenuGroup('colag')" class="w-full flex items-center justify-between px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200 hover:scale-105">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span class="sidebar-text"><?= htmlspecialchars($menuColagNome) ?></span>
                        </div>
                        <svg id="colag-arrow" class="w-5 h-5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="colag-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                        <?php 
                        // Verificar módulos do aluno (Colag) — habilitado = só 1
                        $modulosAlunoColag = [
                            'planos_aula' => LayoutHelper::isModuleEnabled('aluno_planos_aula'),
                            'jornadas' => LayoutHelper::isModuleEnabled('jornadas'),
                            'provas' => LayoutHelper::isModuleEnabled('aluno_provas'),
                            'chat_professor' => LayoutHelper::isModuleEnabled('chat_professor'),
                            'redacao_configuravel' => LayoutHelper::isModuleEnabled('redacao_configuravel') && LayoutHelper::isModuleEnabled('aluno_redacao_configuravel'),
                        ];
                        ?>

<?php if (LayoutHelper::isModuleEnabled('aluno_arquivos')): ?>
                        <?php $arquivosColagEnabled = LayoutHelper::isModuleEnabled('aluno_arquivos'); ?>
                        <a href="<?= $arquivosColagEnabled ? URL . '/aluno/arquivos' : '#' ?>"
                           onclick="<?= $arquivosColagEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Arquivos\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= (($current_page ?? '') === 'arquivos') || ((($current_page ?? '') !== 'recuperacao') && strpos($_SERVER['REQUEST_URI'] ?? '', '/aluno/arquivos') !== false) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$arquivosColagEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Arquivos</span>
                        </a>
                        <a href="<?= $arquivosColagEnabled ? URL . '/aluno/recuperacao' : '#' ?>"
                           onclick="<?= $arquivosColagEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Recuperação\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= (($current_page ?? '') === 'recuperacao') || strpos($_SERVER['REQUEST_URI'] ?? '', '/aluno/recuperacao') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$arquivosColagEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Recuperação</span>
                        </a>
                        <?php endif; ?>

<?php if (LayoutHelper::isModuleEnabled('chat_professor')): ?>
                        <?php $chatProfessorEnabled = $modulosAlunoColag['chat_professor']; ?>
                        <a href="<?= $chatProfessorEnabled ? URL . '/chat-professor' : '#' ?>" 
                           onclick="<?= $chatProfessorEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Chat com Professor\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/chat-professor') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$chatProfessorEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Chat com Professor</span>
                        </a>
                        <?php endif; ?>

<?php if (LayoutHelper::isModuleEnabled('redacao_configuravel') && LayoutHelper::isModuleEnabled('aluno_redacao_configuravel')): ?>
                        <?php $jornadaRedacaoEnabled = $modulosAlunoColag['redacao_configuravel']; ?>
                        <a href="<?= $jornadaRedacaoEnabled ? URL . '/jornada-redacao' : '#' ?>"
                           hx-boost="false"
                           onclick="<?= $jornadaRedacaoEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Jornada da Redação\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/jornada-redacao') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$jornadaRedacaoEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Jornada da Redação</span>
                        </a>
                        <?php endif; ?>

<?php if (LayoutHelper::isModuleEnabled('jornadas')): ?>
                        <?php $jornadasEnabled = $modulosAlunoColag['jornadas']; ?>
                        <?php $jornadasAtivoMenu = (bool) preg_match('#/jornadas(/|\\?|$)#', (string) ($_SERVER['REQUEST_URI'] ?? '')); ?>
                        <a href="<?= $jornadasEnabled ? URL . '/jornadas' : '#' ?>" 
                           onclick="<?= $jornadasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Jornada do Aluno\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= $jornadasAtivoMenu ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$jornadasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Jornada do Aluno</span>
                        </a>
                        <?php endif; ?>

<?php if (LayoutHelper::isModuleEnabled('aluno_apostilas')): ?>
                        <?php $apostilasEnabled = true; ?>
                        <!-- Meu Material substitui a antiga entrada "Apostilas" (mesmo lugar no menu,
                             agora aponta para o módulo com IA, igual ao que aparece para o professor). -->
                        <a href="<?= URL . '/aluno/apostilas-ia' ?>"
                           onclick="<?= $apostilasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Meu Material\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/aluno/apostilas-ia') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$apostilasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Meu Material</span>
                        </a>
                        <?php endif; ?>

                        <?php if (LayoutHelper::isModuleEnabled('aluno_provas')): ?>
                        <?php $provasEnabled = $modulosAlunoColag['provas']; ?>
                        <a href="<?= $provasEnabled ? URL . '/notas-boletins' : '#' ?>"
                           onclick="<?= $provasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Notas/Boletins\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'notas_boletins' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$provasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 6V7m3 10v-4m4 6H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Notas/Boletins</span>
                        </a>
                        <?php endif; ?>

<?php if (LayoutHelper::isModuleEnabled('aluno_planos_aula')): ?>
                        <?php $planosAulaEnabled = $modulosAlunoColag['planos_aula']; ?>
                        <a href="<?= $planosAulaEnabled ? URL . '/aluno/planos-aula' : '#' ?>" 
                           onclick="<?= $planosAulaEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Plano de Aula\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/aluno/planos-aula') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$planosAulaEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Plano de Aula</span>
                        </a>
                        <?php endif; ?>

                        <?php if (LayoutHelper::isModuleEnabled('aluno_provas')): ?>
                        <?php $provasEnabled = $modulosAlunoColag['provas']; ?>
                        <a href="<?= $provasEnabled ? URL . '/aluno/provas' : '#' ?>" 
                           onclick="<?= $provasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Prova Online\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/aluno/provas') !== false || strpos($_SERVER['REQUEST_URI'] ?? '', '/provas') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$provasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Prova Online</span>
                        </a>
                        <?php endif; ?>

<?php if (LayoutHelper::isModuleEnabled('aluno_links_uteis')): ?>
                        <?php
                        $linksUteisAlunoEnabled = LayoutHelper::isModuleEnabled('aluno_links_uteis');
                        $customMenuLinks = json_decode(LayoutHelper::get('menu_links_submenu', '[]'), true);
                        $externalAppsLinks = json_decode(LayoutHelper::get('external_apps_links', '[]'), true);
                        if (!is_array($customMenuLinks)) {
                            $customMenuLinks = [];
                        }
                        if (!is_array($externalAppsLinks)) {
                            $externalAppsLinks = [];
                        }
                        if (empty($externalAppsLinks)) {
                            $legacyExternalLinks = [
                                ['id' => 'educalabs', 'url' => trim((string) LayoutHelper::get('educalabs_external_url', ''))],
                                ['id' => 'games', 'url' => trim((string) LayoutHelper::get('games_external_url', ''))],
                                ['id' => 'notes', 'url' => trim((string) LayoutHelper::get('notes_external_url', ''))],
                            ];
                            foreach ($legacyExternalLinks as $legacyLink) {
                                if (trim((string) ($legacyLink['url'] ?? '')) !== '') {
                                    $externalAppsLinks[] = $legacyLink;
                                }
                            }
                        }
                        $normalizeExternalUrl = function ($value) {
                            $value = strtolower(trim((string) $value));
                            if ($value === '') {
                                return '';
                            }
                            if (substr($value, -1) === '/') {
                                $value = rtrim($value, '/');
                            }
                            return $value;
                        };
                        $externalAppsRouteByUrl = [];
                        foreach ($externalAppsLinks as $idx => $externalLink) {
                            if (!is_array($externalLink)) {
                                continue;
                            }
                            $externalUrl = $normalizeExternalUrl($externalLink['url'] ?? '');
                            if ($externalUrl === '') {
                                continue;
                            }
                            $externalId = (string) ($externalLink['id'] ?? $idx);
                            $externalAppsRouteByUrl[$externalUrl] = URL . '/external-apps/abrir/' . rawurlencode($externalId);
                        }
                        usort($customMenuLinks, static function ($a, $b) {
                            $na = strtolower(trim((string) ($a['nome'] ?? $a['label'] ?? '')));
                            $nb = strtolower(trim((string) ($b['nome'] ?? $b['label'] ?? '')));
                            return $na <=> $nb;
                        });
                        foreach ($customMenuLinks as $link) {
                            $label = trim($link['nome'] ?? $link['label'] ?? '');
                            $url = trim($link['url'] ?? '');
                            $showAluno = !empty($link['aluno']);
                            $openNewTab = !empty($link['nova_guia']);
                            if ($label === '' || $url === '' || !$showAluno) {
                                continue;
                            }
                            if (!empty($externalAlunoUrlSet[$normalizeExternalUrl($url)])) {
                                continue;
                            }
                            $href = $linksUteisAlunoEnabled ? $url : '#';
                            if ($linksUteisAlunoEnabled) {
                                $externalGateway = $externalAppsRouteByUrl[$normalizeExternalUrl($url)] ?? '';
                                if ($externalGateway !== '') {
                                    $href = $externalGateway;
                                }
                            }
                            if ($linksUteisAlunoEnabled && !preg_match('#^https?://#i', $href)) {
                                $href = URL . '/' . ltrim($href, '/');
                            }
                        ?>
                            <a href="<?= htmlspecialchars($href) ?>" <?= ($linksUteisAlunoEnabled && $openNewTab) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                               onclick="<?= $linksUteisAlunoEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Links Úteis\'); return false;' ?>"
                               class="flex items-center px-4 py-2 text-purple-100 hover:bg-white/20 hover:text-white rounded-lg transition-all duration-200 <?= !$linksUteisAlunoEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 015.656 0l1.414 1.414a4 4 0 010 5.656l-1.414 1.414a4 4 0 01-5.656 0M10.172 13.828a4 4 0 01-5.656 0L3.102 12.414a4 4 0 010-5.656L4.516 5.344a4 4 0 015.656 0"></path>
                                </svg>
                                <span class="sidebar-text text-sm"><?= htmlspecialchars($label) ?></span>
                            </a>
                        <?php } ?>
                        <?php endif; ?>
                        <?php educatudo_render_external_apps_aluno_rows($externalAppsAlunoByMenu['colag']); ?>
                    </div>
                </div>

                <?php if (LayoutHelper::get('creditos_habilitado', '0') === '1' && LayoutHelper::creditosExibirMenuComprarAluno()): ?>
                <a href="<?= URL ?>/educashop" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'educashop' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                    <svg class="w-6 h-6 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                    </svg>
                    <span class="sidebar-text">EducaShop</span>
                </a>
                <?php endif; ?>
                <!-- Entretenimento -->
                <div class="menu-group">
                    <button type="button" onclick="toggleMenuGroup('entretenimento')" class="w-full flex items-center justify-between px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200 hover:scale-105">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="sidebar-text">Entretenimento</span>
                        </div>
                        <svg id="entretenimento-arrow" class="w-5 h-5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="entretenimento-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
<?php if (LayoutHelper::isModuleEnabled('educa_hits') && !$nativoSubstituido['educa_hits']): ?>
                        <?php
                        require_once __DIR__ . '/../../Core/EducaHitsConfig.php';
                        $educaHitsEnabled = LayoutHelper::isModuleEnabled('educa_hits');
                        $ehEscutarUrl = URL . '/hits';
                        $ehPedirUrl = URL . '/hits/request';
                        ?>
                        <button type="button"
                                onclick="<?= $educaHitsEnabled ? "toggleMenuGroup('educahits-entretenimento')" : "mostrarModalModuloDesabilitado('EducaHits')" ?>"
                                class="w-full flex items-center justify-between px-4 py-2 text-purple-100 hover:bg-white/20 hover:text-white rounded-lg transition-all duration-200 <?= !$educaHitsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <div class="flex items-center">
                                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                                </svg>
                                <span class="sidebar-text text-sm">EducaHits</span>
                            </div>
                            <svg id="educahits-entretenimento-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <style>
                            /* Sublinks EducaHits: mesma cor clara da sidebar (não cinza escuro) */
                            a.educahits-sublink,
                            a.educahits-sublink .sidebar-text {
                                color: color-mix(in srgb, var(--sidebar-text-color) 92%, var(--sidebar-bg-color)) !important;
                            }
                            a.educahits-sublink:hover,
                            a.educahits-sublink:hover .sidebar-text {
                                color: var(--sidebar-text-color) !important;
                            }
                        </style>
                        <div id="educahits-entretenimento-submenu" class="hidden ml-2 space-y-1 border-l border-white/15 pl-2">
                            <a href="<?= $educaHitsEnabled ? htmlspecialchars($ehEscutarUrl) : '#' ?>"
                               <?= $educaHitsEnabled ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                               onclick="<?= $educaHitsEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'EducaHits\'); return false;' ?>"
                               class="educahits-sublink flex items-center px-4 py-2 hover:bg-white/15 rounded-lg transition-all duration-200 text-sm <?= !$educaHitsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                <span class="sidebar-text text-sm pl-2">Escutar música</span>
                            </a>
                            <a href="<?= $educaHitsEnabled ? htmlspecialchars($ehPedirUrl) : '#' ?>"
                               onclick="<?= $educaHitsEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'EducaHits\'); return false;' ?>"
                               class="educahits-sublink flex items-center px-4 py-2 hover:bg-white/15 rounded-lg transition-all duration-200 text-sm <?= !$educaHitsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                <span class="sidebar-text text-sm pl-2">Pedir músicas</span>
                            </a>
                        </div>
                        <?php endif; ?>
                        <?php if (!$gamesBloqueadoAluno && LayoutHelper::isModuleEnabled('jogos') && !$nativoSubstituido['jogos']): ?>
                        <?php
                        $jogosEnabled = LayoutHelper::isModuleEnabled('jogos');
                        $jogosPodeHorario = $jogosEnabled && $gamesHorarioLiberadoAluno;
                        $jogosOnclick = '';
                        if (!$jogosEnabled) {
                            $jogosOnclick = 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Games\'); return false;';
                        } elseif (!$gamesHorarioLiberadoAluno) {
                            $jogosOnclick = 'event.preventDefault(); alert(' . $gamesHorarioBloqueioMsgJs . '); return false;';
                        }
                        ?>
                        <a href="<?= $jogosPodeHorario ? URL . '/jogos/access' : '#' ?>"
                           <?php if ($jogosOnclick !== ''): ?>onclick="<?= htmlspecialchars($jogosOnclick, ENT_QUOTES, 'UTF-8') ?>"<?php endif; ?>
                           <?= $jogosPodeHorario ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                           class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'jogos' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$jogosEnabled || !$gamesHorarioLiberadoAluno ? 'opacity-55 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Games</span>
                        </a>
                        <?php endif; ?>
                        <a href="<?= URL ?>/dashboard#noticias" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'noticias' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6m4-4h-1m-1 4h1m-1 4h1m1.5-8h-1m-1 4h1m-1 4h1M17 8h1m-1 4h1m-1 4h1"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Notícias</span>
                        </a>
                        <?php educatudo_render_external_apps_aluno_rows($externalAppsAlunoByMenu['entretenimento']); ?>
                    </div>
                </div>

                <!-- Estudo -->
                <div class="menu-group">
                    <button type="button" onclick="toggleMenuGroup('estudo')" class="w-full flex items-center justify-between px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200 hover:scale-105">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span class="sidebar-text">Estudo</span>
                        </div>
                        <svg id="estudo-arrow" class="w-5 h-5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="estudo-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                        <?php 
                        require_once __DIR__ . '/../../Core/FeatureGate.php';
                        require_once __DIR__ . '/../../Core/LayoutHelper.php';
                        
                        // Menu do aluno: só módulos habilitados (status 1). Desativado/inativo somem.
                        $modulosAluno = [
                            'chat' => LayoutHelper::isModuleEnabled('chat'),
                            // O link do menu vai pra /exercicios-personalizados/criar (geração
                            // por IA) — o módulo certo a checar é 'exercicios_ia', não
                            // 'exercicios' (que é o módulo separado "Banco de Dados").
                            'exercicios' => LayoutHelper::isModuleEnabled('exercicios_ia'),
                            'simulados' => LayoutHelper::isModuleEnabled('simulados'),
                            'redacoes' => LayoutHelper::isModuleEnabled('redacoes'),
                            'ingles' => LayoutHelper::isModuleEnabled('ingles'),
                            'educa_livros' => LayoutHelper::isModuleEnabled('educa_livros'),
                            'educalabs' => LayoutHelper::isModuleEnabled('educalabs'),
                            'aluno_flashcards' => LayoutHelper::isModuleEnabled('aluno_flashcards'),
                        ];

                        // Ordem e nomes customizáveis por escola (drag-and-drop + renomear em
                        // /admin/dev). Cada item do grupo é capturado num buffer próprio (chave
                        // estável) em vez de ecoado direto — assim dá pra reordenar no fim sem
                        // tocar na lógica (condições, onclick, nova aba etc.) de cada item.
                        if (!function_exists('educatudo_menu_label')) {
                            function educatudo_menu_label(array $menuLabels, string $key, string $default): string
                            {
                                $custom = trim((string) ($menuLabels[$key] ?? ''));
                                return $custom !== '' ? $custom : $default;
                            }
                        }
                        if (!function_exists('educatudo_render_menu_group_ordered')) {
                            function educatudo_render_menu_group_ordered(array $items, array $defaultOrder, array $customOrder, array $labels = []): void
                            {
                                $extKeys = [];
                                foreach (array_keys($items) as $k) {
                                    if (strpos((string) $k, '_ext_') === 0) {
                                        $extKeys[] = $k;
                                    }
                                }

                                $expand = static function (array $keys) use ($extKeys): array {
                                    $out = [];
                                    foreach ($keys as $k) {
                                        $k = (string) $k;
                                        if ($k === '_apps_externos') {
                                            foreach ($extKeys as $ek) {
                                                if (!in_array($ek, $out, true)) {
                                                    $out[] = $ek;
                                                }
                                            }
                                            continue;
                                        }
                                        if (!in_array($k, $out, true)) {
                                            $out[] = $k;
                                        }
                                    }
                                    return $out;
                                };

                                $norm = static function (string $value): string {
                                    $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
                                    return strtolower(is_string($ascii) && $ascii !== '' ? $ascii : $value);
                                };

                                $order = [];
                                if ($customOrder === [] && $labels !== []) {
                                    $keys = array_keys($items);
                                    usort($keys, static function ($a, $b) use ($labels, $norm) {
                                        $la = $norm((string) ($labels[$a] ?? $a));
                                        $lb = $norm((string) ($labels[$b] ?? $b));
                                        return $la <=> $lb;
                                    });
                                    $order = $keys;
                                } else {
                                    foreach ($expand($customOrder) as $k) {
                                        if (isset($items[$k]) && !in_array($k, $order, true)) {
                                            $order[] = $k;
                                        }
                                    }
                                    foreach ($expand($defaultOrder) as $k) {
                                        if (isset($items[$k]) && !in_array($k, $order, true)) {
                                            $order[] = $k;
                                        }
                                    }
                                    foreach (array_keys($items) as $k) {
                                        if (!in_array($k, $order, true)) {
                                            $order[] = $k;
                                        }
                                    }
                                }

                                foreach ($order as $k) {
                                    echo $items[$k] ?? '';
                                }
                            }
                        }
                        $menuLabels = json_decode((string) LayoutHelper::get('menu_labels', '[]'), true);
                        if (!is_array($menuLabels)) {
                            $menuLabels = [];
                        }
                        $menuOrderEstudo = json_decode((string) LayoutHelper::get('menu_order_estudo', '[]'), true);
                        if (!is_array($menuOrderEstudo)) {
                            $menuOrderEstudo = [];
                        }
                        $itemsEstudo = [];
                        ?>
                        
                        <?php
                        $chatEnabled = $modulosAluno['chat'];
                        $exerciciosEnabled = $modulosAluno['exercicios'];
                        $educaLabsEnabled = $modulosAluno['educalabs'];
                        $simuladosEnabled = $modulosAluno['simulados'];
                        $redacoesEnabled = $modulosAluno['redacoes'];
                        $inglesEnabled = $modulosAluno['ingles'];
                        $educaLivrosEnabled = $modulosAluno['educa_livros'];
                        $flashcardsEnabled = $modulosAluno['aluno_flashcards'];
                        ?>
                        <?php ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('educa_livros')): ?>
                        <a href="<?= $educaLivrosEnabled ? URL . '/livros' : '#' ?>"
                           onclick="<?= $educaLivrosEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Educa Livros\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'livros' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$educaLivrosEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'educa_livros', 'Educa Livros')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['educa_livros'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('educalabs') && !$nativoSubstituido['educalabs']): ?>
                        <a href="<?= $educaLabsEnabled ? URL . '/educalabs/access' : '#' ?>"
                           onclick="<?= $educaLabsEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'EducaLabs\'); return false;' ?>"
                           target="<?= $educaLabsEnabled ? '_blank' : '' ?>" rel="<?= $educaLabsEnabled ? 'noopener noreferrer' : '' ?>"
                           class="flex items-center px-4 py-2 text-purple-100 hover:bg-white/20 hover:text-white rounded-lg transition-all duration-200 <?= !$educaLabsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'educalabs', 'EducaLabs')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['educalabs'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('aluno_flashcards')): ?>
                        <a href="<?= $flashcardsEnabled ? URL . '/flashcards' : '#' ?>"
                           onclick="<?= $flashcardsEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Flash Cards\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/flashcards') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$flashcardsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'aluno_flashcards', 'Flash Card')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['aluno_flashcards'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('exercicios_ia')): ?>
                        <a href="<?= $exerciciosEnabled ? URL . '/exercicios-personalizados/criar' : '#' ?>"
                           onclick="<?= $exerciciosEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Exercícios\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/exercicios') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$exerciciosEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'exercicios', 'Exercícios')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['exercicios'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('ingles')): ?>
                        <a href="<?= $inglesEnabled ? URL . '/ingles' : '#' ?>"
                           onclick="<?= $inglesEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Inglês\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'ingles' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$inglesEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5h12M9 3v2m1.048 9.5A18.022 18.022 0 016.412 9m6.088 9h7M11 21l5-10 5 10M12.751 5C11.783 10.77 8.07 15.61 3 18.129"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'ingles', 'Inglês')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['ingles'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('redacoes')): ?>
                        <a href="<?= $redacoesEnabled ? URL . '/redacoes' : '#' ?>"
                           onclick="<?= $redacoesEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Redação\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'redacoes' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$redacoesEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'redacoes', 'Redação')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['redacoes'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('simulados')): ?>
                        <a href="<?= $simuladosEnabled ? URL . '/simulados' : '#' ?>"
                           onclick="<?= $simuladosEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Simulados\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'simulados' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$simuladosEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'simulados', 'Simulados')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['simulados'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('aulas_online')): ?>
                        <a href="<?= URL ?>/aluno/aulas-online"
                           class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'aulas_online' || strpos($_SERVER['REQUEST_URI'] ?? '', '/aluno/aulas-online') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14m-9 4h8a2 2 0 002-2V8a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'aulas_online', 'Aulas Online')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['aulas_online'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('aluno_minicursos')): ?>
                        <?php $minicursosAlunoEnabled = LayoutHelper::isModuleEnabled('aluno_minicursos'); ?>
                        <a href="<?= $minicursosAlunoEnabled ? URL . '/minicursos' : '#' ?>"
                           onclick="<?= $minicursosAlunoEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'EducaCursos\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/minicursos') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$minicursosAlunoEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'aluno_minicursos', 'EducaCursos')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['aluno_minicursos'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('ead')): ?>
                        <?php $eadEnabled = LayoutHelper::isModuleEnabled('ead'); ?>
                        <a href="<?= $eadEnabled ? URL . '/cursos' : '#' ?>"
                           onclick="<?= $eadEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Meus Cursos\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/cursos') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$eadEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422A12.083 12.083 0 0112 21.5a12.083 12.083 0 01-6.16-10.922L12 14z"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'ead', 'Meus Cursos')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['ead'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('forum')): ?>
                        <?php $forumEnabled = LayoutHelper::isModuleEnabled('forum'); ?>
                        <a href="<?= $forumEnabled ? URL . '/forum' : '#' ?>"
                           onclick="<?= $forumEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Fórum\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/forum') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$forumEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'forum', 'Fórum')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['forum'] = ob_get_clean(); ob_start(); ?>
                        <?php if (LayoutHelper::isModuleEnabled('drive')): ?>
                        <?php $driveEnabled = LayoutHelper::isModuleEnabled('drive'); ?>
                        <a href="<?= $driveEnabled ? URL . '/drive' : '#' ?>"
                           onclick="<?= $driveEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Drive\'); return false;' ?>"
                           class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/drive') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$driveEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-6l-2-2H5a2 2 0 00-2 2z"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars(educatudo_menu_label($menuLabels, 'drive', 'Drive')) ?></span>
                        </a>
                        <?php endif; ?>
                        <?php $itemsEstudo['drive'] = ob_get_clean(); ?>
                        <?php
                        $labelsEstudo = [
                            'educa_livros' => educatudo_menu_label($menuLabels, 'educa_livros', 'Educa Livros'),
                            'educalabs' => educatudo_menu_label($menuLabels, 'educalabs', 'EducaLabs'),
                            'aluno_flashcards' => educatudo_menu_label($menuLabels, 'aluno_flashcards', 'Flash Card'),
                            'exercicios' => educatudo_menu_label($menuLabels, 'exercicios', 'Exercícios'),
                            'ingles' => educatudo_menu_label($menuLabels, 'ingles', 'Inglês'),
                            'redacoes' => educatudo_menu_label($menuLabels, 'redacoes', 'Redação'),
                            'simulados' => educatudo_menu_label($menuLabels, 'simulados', 'Simulados'),
                            'aulas_online' => educatudo_menu_label($menuLabels, 'aulas_online', 'Aulas Online'),
                            'aluno_minicursos' => educatudo_menu_label($menuLabels, 'aluno_minicursos', 'EducaCursos'),
                            'ead' => educatudo_menu_label($menuLabels, 'ead', 'Meus Cursos'),
                            'forum' => educatudo_menu_label($menuLabels, 'forum', 'Fórum'),
                            'drive' => educatudo_menu_label($menuLabels, 'drive', 'Drive'),
                        ];
                        foreach ($externalAppsAlunoByMenu['estudo'] as $extIdx => $extApp) {
                            if (!is_array($extApp)) {
                                continue;
                            }
                            $extId = preg_replace('/[^a-zA-Z0-9_\-]/', '', (string) ($extApp['id'] ?? $extIdx));
                            if ($extId === '') {
                                $extId = (string) $extIdx;
                            }
                            $extKey = '_ext_' . $extId;
                            ob_start();
                            educatudo_render_external_apps_aluno_rows([$extApp]);
                            $itemsEstudo[$extKey] = ob_get_clean();
                            $labelsEstudo[$extKey] = trim((string) ($extApp['nome'] ?? $extKey));
                        }
                        educatudo_render_menu_group_ordered(
                            $itemsEstudo,
                            [
                                'aulas_online',
                                'drive',
                                'educa_livros',
                                'aluno_minicursos',
                                'educalabs',
                                'exercicios',
                                'aluno_flashcards',
                                'forum',
                                'ingles',
                                'ead',
                                'redacoes',
                                'simulados',
                                '_apps_externos',
                            ],
                            $menuOrderEstudo,
                            $labelsEstudo
                        );
                        ?>
                    </div>
                </div>

                <?php if (LayoutHelper::get('creditos_habilitado', '0') === '1' && LayoutHelper::creditosExibirMenuCarteiraAluno()): ?>
                <a href="<?= URL ?>/carteira" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'carteira' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                    <svg class="w-6 h-6 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/>
                    </svg>
                    <span class="sidebar-text">Minha Carteira</span>
                </a>
                <?php endif; ?>
                <!-- Sistema -->
                <div class="menu-group">
                    <button type="button" onclick="toggleMenuGroup('sistema')" class="w-full flex items-center justify-between px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200 hover:scale-105">
                        <div class="flex items-center">
                            <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            </svg>
                            <span class="sidebar-text">Sistema</span>
                        </div>
                        <svg id="sistema-arrow" class="w-5 h-5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                    <div id="sistema-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                        <a href="<?= URL ?>/notifications" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'notifications' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Notificações</span>
                        </a>
                        
                        <a href="<?= URL ?>/tickets" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'tickets' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                            <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Suporte</span>
                        </a>
                        <?php educatudo_render_external_apps_aluno_rows($externalAppsAlunoByMenu['sistema']); ?>
                    </div>
                </div>
                
                <!-- Logout Button -->
                <a href="<?= URL ?>/logout?portal=aluno" class="flex items-center px-4 py-3 text-red-200 hover:bg-red-500/20 hover:text-red-100 rounded-xl transition-all duration-200 hover:scale-105 mt-4">
                    <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="sidebar-text">Sair</span>
                </a>
                <?php endif; ?>
            </nav>
            </div>
        </aside>
        
        <!-- Main Content -->
        <main id="student-main-content" class="flex-1 w-full md:w-auto flex flex-col min-h-0 <?= isset($current_page) && $current_page === 'chat' ? 'overflow-hidden' : 'overflow-y-auto' ?> mobile-content">
            <!-- Header: menu hamburger, título, notificações e avatar (padrão Professor) -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-6 py-3 md:py-4 sticky -top-2 z-30">
                <div class="flex justify-between items-center gap-2">
                    <!-- Botão menu mobile -->
                    <button id="mobileMenuToggle" type="button" class="md:hidden p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" aria-label="Abrir menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-lg md:text-2xl font-bold text-gray-900 truncate"><?= htmlspecialchars($page_title ?? 'Portal do Aluno') ?></h1>
                        <p class="text-xs md:text-sm text-gray-600 truncate">Bem-vindo, <?= htmlspecialchars($user['nome'] ?? 'Aluno') ?>!</p>
                    </div>
                    <div class="flex items-center gap-2 md:gap-4 flex-shrink-0">
                        <div class="flex items-center gap-1.5 md:gap-2 flex-shrink-0">
                        <!-- Notificações (sino) + saldo ao lado -->
                        <?php 
                        $notificacoesNaoLidas = 0;
                        $notificacoesRecentes = [];
                        $usuario = $user ?? null;
                        $tipoUsuario = 'aluno';
                        
                        if (isset($user['id'])) {
                            try {
                                require_once __DIR__ . '/../../Models/Notifications/Notification.php';
                                $notificacaoModel = new Notification();
                                $notificacoesNaoLidas = $notificacaoModel->getNaoLidas($user['id'], 'aluno');
                                $notificacoesRecentes = $notificacaoModel->getByDestinatario($user['id'], 'aluno', 5);
                            } catch (Exception $e) {
                                $notificacoesNaoLidas = 0;
                                $notificacoesRecentes = [];
                            }
                        }
                        
                        include __DIR__ . '/components/notification-banner.php';
                        ?>
                        <?php
                        $mostrarCreditosTopo = LayoutHelper::creditosExibirSaldoTopoAluno()
                            && empty($preview)
                            && isset($user['id'])
                            && ($user['tipo'] ?? '') === 'aluno';
                        $studentSaldoCreditosTopo = null;
                        if ($mostrarCreditosTopo) {
                            try {
                                require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
                                $uidTopo = (int) $user['id'];
                                $creditosSvcPath = __DIR__ . '/../../Services/CreditosService.php';
                                if (is_file($creditosSvcPath)) {
                                    require_once $creditosSvcPath;
                                    $svcCredTopo = new \App\Services\CreditosService();
                                    $svcCredTopo->aplicarRecargaInicialSeAplicavel('aluno', $uidTopo);
                                    $studentSaldoCreditosTopo = $svcCredTopo->getSaldo('aluno', $uidTopo);
                                } else {
                                    $dbCredTopo = Database::getInstance();
                                    $rCredTopo = $dbCredTopo->fetch(
                                        'SELECT saldo FROM carteira_usuarios WHERE user_type = ? AND user_id = ? LIMIT 1',
                                        ['aluno', $uidTopo]
                                    );
                                    $studentSaldoCreditosTopo = CreditosDecimalHelper::fromScalar(
                                        is_array($rCredTopo) ? ($rCredTopo['saldo'] ?? 0) : 0,
                                        0.0
                                    );
                                }
                            } catch (Throwable $e) {
                                $studentSaldoCreditosTopo = null;
                            }
                        }
                        ?>
                        <?php if ($studentSaldoCreditosTopo !== null): ?>
                        <a href="<?= URL ?>/carteira" class="flex items-center gap-1.5 shrink-0 px-2.5 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-1" title="Minha carteira de TudiCoins">
                            <svg class="w-4 h-4 text-amber-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs font-semibold tabular-nums leading-none"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay((float) $studentSaldoCreditosTopo)) ?></span>
                            <span class="hidden sm:inline text-xs text-amber-800/90 font-medium leading-none">TudiCoins</span>
                        </a>
                        <?php endif; ?>
                        </div>
                        
                        <!-- Avatar do usuário com dropdown -->
                        <div class="relative">
                            <button onclick="toggleUserDropdown()" class="flex items-center space-x-2 md:space-x-3 hover:bg-gray-50 px-2 md:px-3 py-2 rounded-lg transition-colors cursor-pointer">
                                <?php if (!empty($avatar_url)): ?>
                                    <img src="<?= htmlspecialchars($avatar_url) ?>" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" class="w-8 h-8 object-cover rounded-full" onerror="if(!this.dataset.avatarFallback){this.dataset.avatarFallback='1';this.src=this.src.replace('/public/assets/','/assets/').replace('/public/uploads/','/uploads/');}">
                                <?php else: ?>
                                    <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white text-sm font-medium overflow-hidden">
                                        <?= strtoupper(substr($user['nome'] ?? 'A', 0, 1)) ?>
                                    </div>
                                <?php endif; ?>
                                <span class="hidden md:inline text-sm font-medium text-gray-700"><?= htmlspecialchars($user['nome'] ?? 'Aluno') ?></span>
                                <svg class="w-4 h-4 text-gray-500 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div id="userDropdown" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-xl border border-gray-200 py-1 z-50">
                                <a href="<?= URL ?>/avatar" class="flex items-center px-4 py-2 text-sm text-gray-700 hover:bg-gray-100 transition-colors">
                                    <svg class="w-4 h-4 mr-3 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Editar Perfil
                                </a>
                                <div class="border-t border-gray-200 my-1"></div>
                                <a href="<?= URL ?>/logout?portal=aluno" class="flex items-center px-4 py-2 text-sm text-red-600 hover:bg-red-50 transition-colors">
                                    <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Sair
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </header>
            
            <?php $isChatPage = (isset($current_page) && $current_page === 'chat'); ?>
            <div class="<?= $isChatPage ? 'chat-content-wrap p-0 flex flex-col flex-1 min-h-0 h-full' : 'p-6' ?>">
                <div id="student-page-reader-content" data-ei-reader-source="1" class="<?= $isChatPage ? 'flex flex-col flex-1 min-h-0 overflow-hidden h-full' : '' ?>">
                    <?= $content ?>
                </div>
                <!-- Footer: oculto na página do chat para dar altura total à área de mensagens + caixa de escrever -->
                <footer class="mt-8 pt-4 border-t border-gray-200 flex-shrink-0 <?= $isChatPage ? 'hidden' : '' ?>">
                    <div class="text-center">
                        <p class="text-xs mb-1 text-gray-600">Todos os direitos reservados Educatudo</p>
                        <p class="text-xs text-gray-600">
                            Feito com carinho por <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="text-gray-600 hover:text-gray-800 underline">Launs</a>
                        </p>
                        <p class="text-xs mt-2 text-gray-600">
                            <a href="<?= URL ?>/termos-de-uso" class="hover:underline text-gray-600 hover:text-gray-800">Termos de Uso</a> •
                            <a href="<?= URL ?>/politica-privacidade" class="hover:underline text-gray-600 hover:text-gray-800">Política de Privacidade</a> •
                            <a href="<?= URL ?>/politica-retencao" class="hover:underline text-gray-600 hover:text-gray-800">Política de Retenção de Dados</a>
                        </p>
                    </div>
                </footer>
            </div>
        </main>
        <?php if (empty($preview)): ?>
        <?php $navPrimary = LayoutHelper::get('primary_color', '#6366f1'); ?>
        <nav class="mobile-bottom-nav hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-2 py-2 flex justify-center items-stretch gap-0 z-40 safe-area-pb">
            <?php foreach ($mobileBottomNavItems as $idx => $item): ?>
                <?php
                    $label = $item['label'] ?? 'Item';
                    $route = $item['route'] ?? '/dashboard';
                    $icon = $item['icon'] ?? 'home';
                    $isGamesNavItem = (
                        $route === '/jogos/access'
                        || strpos((string) $route, '/jogos/') === 0
                    );
                    $gamesNavBloqueado = $isGamesNavItem && !$gamesHorarioLiberadoAluno;
                    $navHref = $gamesNavBloqueado ? '#' : (URL . $route);
                    $navOnclick = $gamesNavBloqueado ? ' onclick="event.preventDefault(); alert(' . $gamesHorarioBloqueioMsgJs . '); return false;"' : '';
                    $isActive = !$gamesNavBloqueado && strpos($_SERVER['REQUEST_URI'] ?? '', $route) === 0;
                    $isHighlight = ($idx === $mobileBottomNavHighlightIndex);
                ?>
                <a href="<?= htmlspecialchars($navHref) ?>"<?= $navOnclick ?> class="flex-1 min-w-0 flex flex-col items-center justify-center text-xs py-1 <?= $gamesNavBloqueado ? 'opacity-55 text-gray-400' : ($isHighlight ? '' : ($isActive ? 'text-indigo-600' : 'text-gray-500')) ?>">
                    <?php if ($isHighlight): ?>
                    <span class="flex items-center justify-center w-12 h-10 rounded-full mb-0.5 text-white" style="background-color: <?= htmlspecialchars($navPrimary) ?>">
                        <?= renderMobileIcon($icon) ?>
                    </span>
                    <?php else: ?>
                    <span class="flex items-center justify-center h-10"><?= renderMobileIcon($icon) ?></span>
                    <?php endif; ?>
                    <span class="mt-0.5 truncate w-full text-center px-0.5"><?= htmlspecialchars($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>
        <?php if (empty($preview) && $mobileFabEnabled && !empty($mobileFabAction)): ?>
            <a href="<?= htmlspecialchars(URL . $mobileFabAction) ?>" class="mobile-bottom-nav hidden fixed bottom-20 right-4 bg-indigo-600 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg z-50">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                </svg>
            </a>
        <?php endif; ?>
    </div>

    <?php
    $needsConsent = false;
    // Em modo preview (professor vendo como aluno) não exibir nem exigir declaração de ciência
    if (!empty($preview)) {
        $needsConsent = false;
    } elseif (!empty($user['id'])) {
        $db = Database::getInstance();
        $docs = ['terms', 'privacy', 'retention'];
        $version = 'v1.0';
        foreach ($docs as $doc) {
            $row = $db->fetch(
                "SELECT id FROM usuarios_consentimentos 
                 WHERE user_id = :user_id AND user_role = 'student'
                   AND document_type = :document_type AND document_version = :document_version
                 LIMIT 1",
                [
                    'user_id' => $user['id'],
                    'document_type' => $doc,
                    'document_version' => $version
                ]
            );
            if (!$row) {
                $needsConsent = true;
                break;
            }
        }
    }
    ?>

    <?php if ($needsConsent): ?>
    <div id="consentOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-[9999]">
        <div class="bg-white rounded-lg max-w-lg w-full p-6 shadow-lg">
            <h3 class="text-lg font-semibold mb-3 text-gray-900">Declaração de Ciência do Aluno</h3>
            <div class="text-xs text-gray-700 space-y-2 max-h-72 overflow-y-auto pr-2">
                <p>Declaro que estou ciente de que a plataforma EducaTudo é um ambiente educacional digital utilizado pela minha instituição de ensino para apoio ao aprendizado, realização de atividades, avaliações, acompanhamento pedagógico e comunicação educacional.</p>
                <p><strong>A plataforma é monitorada por inteligência artificial (IA) para detectar assuntos sensíveis e situações de risco, visando a segurança e o bem-estar de todos.</strong></p>
                <p>Estou ciente de que:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Minhas atividades, avaliações, notas, desempenho acadêmico e registros pedagógicos fazem parte da minha vida acadêmica dentro da plataforma;</li>
                    <li>Minhas interações, mensagens e uso do sistema podem ser monitorados e analisados, inclusive por sistemas de inteligência artificial, para fins educacionais, de segurança, prevenção de riscos e melhoria do aprendizado;</li>
                    <li>Esses tratamentos ocorrem conforme autorização do meu responsável legal e de acordo com os Termos de Uso, Política de Privacidade e Política de Retenção de Dados da EducaTudo.</li>
                </ul>
                <p>Declaro que li e estou ciente do conteúdo desses documentos, em sua versão vigente.</p>
            </div>
            <label class="flex items-start gap-2 mt-4 text-xs text-gray-700">
                <input type="checkbox" id="consentCheckbox" class="mt-1">
                <span>Li e estou ciente dos Termos de Uso, da Política de Privacidade e da Política de Retenção de Dados.</span>
            </label>
            <div class="mt-4 flex items-center justify-between text-xs text-gray-600">
                <div>
                    <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a> •
                    <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a> •
                    <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
                </div>
                <button id="consentConfirm" class="px-3 py-2 bg-blue-600 text-white rounded-lg text-xs">Aceitar e continuar</button>
            </div>
        </div>
    </div>
    <script>
        document.getElementById('consentConfirm')?.addEventListener('click', async function() {
            const checkbox = document.getElementById('consentCheckbox');
            if (!checkbox || !checkbox.checked) {
                return;
            }
            const formData = new FormData();
            formData.append('_token', '<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>');
            formData.append('consent_accept', '1');
            const response = await fetch('<?= URL ?>/consent/accept', { method: 'POST', body: formData });
            if (response.ok) {
                window.location.reload();
            }
        });
    </script>
    <?php endif; ?>

    <?php if ($studentAccessibilityEnabled): ?>
    <style>
        #ei-accessibility-toggle {
            background-color: #71c7bf;
        }
        #ei-accessibility-toggle:hover {
            filter: brightness(0.9);
        }
        #ei-accessibility-toggle:focus {
            box-shadow: 0 0 0 4px color-mix(in srgb, #71c7bf 30%, transparent);
        }
    </style>
    <button type="button"
            id="ei-accessibility-toggle"
            class="ei-read-ignore fixed bottom-5 right-5 z-40 inline-flex items-center justify-center w-12 h-12 rounded-full shadow-lg focus:outline-none transition-all"
            aria-label="Abrir configurações de acessibilidade">
        <i class="fa-solid fa-universal-access text-xl" style="color: #111827;"></i>
    </button>

    <button type="button"
            id="ei-global-reader"
            class="ei-read-ignore hidden fixed bottom-20 right-5 z-40 items-center gap-2 px-4 py-3 bg-indigo-600 text-white rounded-full shadow-lg hover:bg-indigo-700 focus:outline-none focus:ring-4 focus:ring-indigo-200 transition-colors"
            aria-pressed="false"
            aria-label="Ouvir conteúdo da página">
        <i class="fa-solid fa-play"></i>
        <span class="hidden sm:inline text-sm font-semibold">Reproduzir leitura</span>
    </button>

    <div id="ei-accessibility-modal" class="ei-read-ignore fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/60 px-4">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-200 max-w-3xl w-full max-h-[90vh] overflow-hidden flex flex-col">
            <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-gray-900">Acessibilidade</h3>
                    <p class="text-xs text-gray-500">Ajuste a visualização deste acesso. As preferências ficam salvas neste navegador.</p>
                </div>
                <button type="button" id="ei-accessibility-close" class="inline-flex items-center justify-center w-9 h-9 rounded-lg text-gray-500 hover:text-gray-800 hover:bg-gray-100" aria-label="Fechar acessibilidade">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>
            <div class="p-6 overflow-y-auto">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-800 mb-2">Tamanho da fonte</span>
                        <select data-ei-pref="fontSize" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                            <option value="normal">Normal</option>
                            <option value="small">Pequena</option>
                            <option value="medium">Média</option>
                            <option value="large">Grande</option>
                            <option value="xlarge">Extra grande</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-800 mb-2">Contraste</span>
                        <select data-ei-pref="contrast" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                            <option value="default">Padrão</option>
                            <option value="high">Alto contraste</option>
                            <option value="inverted">Invertido</option>
                            <option value="grayscale">Escala de cinza</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-800 mb-2">Fonte de leitura</span>
                        <select data-ei-pref="fontFamily" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                            <option value="default">Padrão</option>
                            <option value="lexend">Lexend</option>
                            <option value="opendyslexic">OpenDyslexic</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-800 mb-2">Espaçamento de texto</span>
                        <select data-ei-pref="textSpacing" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                            <option value="normal">Normal</option>
                            <option value="medium">Médio</option>
                            <option value="large">Grande</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-800 mb-2">Espaçamento entre elementos</span>
                        <select data-ei-pref="elementSpacing" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                            <option value="normal">Normal</option>
                            <option value="medium">Médio</option>
                            <option value="large">Grande</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-800 mb-2">Tamanho dos botões</span>
                        <select data-ei-pref="buttonSize" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                            <option value="normal">Normal</option>
                            <option value="large">Grande</option>
                            <option value="xlarge">Extra grande</option>
                        </select>
                    </label>
                    <label class="block">
                        <span class="block text-sm font-medium text-gray-800 mb-2">Velocidade da leitura</span>
                        <select data-ei-pref="readSpeed" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                            <option value="lenta">Lenta</option>
                            <option value="normal">Normal</option>
                            <option value="rapida">Rápida</option>
                        </select>
                    </label>
                </div>
                <div class="mt-5 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label class="flex items-start gap-3 border border-gray-200 rounded-lg p-3">
                        <input type="checkbox" data-ei-pref="highlightButtons" class="mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded">
                        <span><span class="block text-sm font-medium text-gray-800">Destacar botões</span><span class="block text-xs text-gray-500">Realça ações clicáveis.</span></span>
                    </label>
                    <label class="flex items-start gap-3 border border-gray-200 rounded-lg p-3">
                        <input type="checkbox" data-ei-pref="highlightFocus" class="mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded">
                        <span><span class="block text-sm font-medium text-gray-800">Destacar campo ativo</span><span class="block text-xs text-gray-500">Mostra melhor onde está o foco.</span></span>
                    </label>
                    <label class="flex items-start gap-3 border border-gray-200 rounded-lg p-3">
                        <input type="checkbox" data-ei-pref="readAloud" class="mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded">
                        <span><span class="block text-sm font-medium text-gray-800">Leitura por voz</span><span class="block text-xs text-gray-500">Habilita o botão de reproduzir leitura.</span></span>
                    </label>
                    <label class="flex items-start gap-3 border border-gray-200 rounded-lg p-3">
                        <input type="checkbox" data-ei-pref="autoRead" class="mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded">
                        <span><span class="block text-sm font-medium text-gray-800">Ler automaticamente</span><span class="block text-xs text-gray-500">Inicia a leitura ao abrir a página.</span></span>
                    </label>
                    <label class="flex items-start gap-3 border border-gray-200 rounded-lg p-3">
                        <input type="checkbox" data-ei-pref="focusMode" class="mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded">
                        <span><span class="block text-sm font-medium text-gray-800">Modo foco</span><span class="block text-xs text-gray-500">Reduz distrações visuais.</span></span>
                    </label>
                    <label class="flex items-start gap-3 border border-gray-200 rounded-lg p-3">
                        <input type="checkbox" data-ei-pref="reduceMotion" class="mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded">
                        <span><span class="block text-sm font-medium text-gray-800">Reduzir animações</span><span class="block text-xs text-gray-500">Desliga transições e movimentos.</span></span>
                    </label>
                    <label class="flex items-start gap-3 border border-gray-200 rounded-lg p-3">
                        <input type="checkbox" data-ei-pref="highlightCursor" class="mt-1 h-4 w-4 text-purple-600 border-gray-300 rounded">
                        <span><span class="block text-sm font-medium text-gray-800">Cursor destacado</span><span class="block text-xs text-gray-500">Aumenta a visibilidade do ponteiro.</span></span>
                    </label>
                </div>
            </div>
            <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex flex-wrap justify-end gap-2">
                <button type="button" id="ei-accessibility-reset" class="px-4 py-2 rounded-lg bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-semibold">Restaurar padrão</button>
                <button type="button" id="ei-accessibility-save" class="px-4 py-2 rounded-lg bg-purple-600 hover:bg-purple-700 text-white text-sm font-semibold">Salvar</button>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Modal para Módulos Desabilitados -->
    <div id="moduleDisabledModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50">
        <div class="relative top-20 mx-auto p-5 border w-96 shadow-lg rounded-md bg-white">
            <div class="mt-3 text-center">
                <h3 class="text-lg leading-6 font-medium text-gray-900">Funcionalidade Desativada</h3>
                <div class="mt-2 px-7 py-3">
                    <p class="text-sm text-gray-500" id="moduleDisabledMessage">
                        Esta funcionalidade está desativada no momento. Por favor, entre em contato com o administrador.
                    </p>
                </div>
                <div class="items-center px-4 py-3">
                    <button id="closeModuleDisabledModal" class="px-4 py-2 bg-indigo-600 text-white text-base font-medium rounded-md w-full shadow-sm hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        Entendi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript for Sidebar Toggle -->
    <script>
        // Mobile Menu Toggle (igual ao Professor)
        (function() {
            const mobileMenuToggle = document.getElementById('mobileMenuToggle');
            const sidebar = document.getElementById('sidebar');
            const sidebarOverlay = document.getElementById('sidebar-overlay');
            if (mobileMenuToggle && sidebar && sidebarOverlay) {
                mobileMenuToggle.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                    sidebarOverlay.classList.toggle('active');
                    document.body.style.overflow = sidebar.classList.contains('mobile-open') ? 'hidden' : '';
                });
                sidebarOverlay.addEventListener('click', function() {
                    sidebar.classList.remove('mobile-open');
                    sidebarOverlay.classList.remove('active');
                    document.body.style.overflow = '';
                });
                const sidebarLinks = sidebar.querySelectorAll('a');
                sidebarLinks.forEach(link => {
                    link.addEventListener('click', function() {
                        if (window.innerWidth <= 768) {
                            sidebar.classList.remove('mobile-open');
                            sidebarOverlay.classList.remove('active');
                            document.body.style.overflow = '';
                        }
                    });
                });
            }
        })();
        
        document.addEventListener('DOMContentLoaded', function() {
            const sidebar = document.getElementById('sidebar');
            const sidebarToggle = document.getElementById('sidebarToggle');
            const expandSidebar = document.getElementById('expandSidebar');
            
            const isMobile = window.innerWidth <= 768;
            const isSmallDesktop = !isMobile && window.innerWidth < 1280;
            const savedCollapsed = localStorage.getItem('sidebarCollapsed');
            const isCollapsed = !isMobile && (savedCollapsed === 'true' || (isSmallDesktop && savedCollapsed !== 'false'));
            if (isCollapsed && sidebar) {
                sidebar.classList.add('collapsed');
            }
            if (isSmallDesktop && savedCollapsed !== 'false' && sidebar) {
                localStorage.setItem('sidebarCollapsed', 'true');
            }
            
            function toggleSidebar() {
                if (isMobile || !sidebar) return;
                const nowCollapsed = sidebar.classList.toggle('collapsed');
                localStorage.setItem('sidebarCollapsed', nowCollapsed);
            }
            
            function expandSidebarOnly() {
                if (isMobile || !sidebar) return;
                if (sidebar.classList.contains('collapsed')) {
                    toggleSidebar();
                }
            }
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', toggleSidebar);
            }
            if (expandSidebar) {
                expandSidebar.addEventListener('click', expandSidebarOnly);
            }
            
            const savedState = localStorage.getItem('sidebarCollapsed');
            if (savedState === 'true' && sidebar && !isMobile) {
                sidebar.classList.add('collapsed');
            }
            
            // Add tooltip data attributes to navigation links
            const navLinks = document.querySelectorAll('nav a');
            navLinks.forEach(link => {
                const textElement = link.querySelector('.sidebar-text');
                if (textElement) {
                    link.setAttribute('data-tooltip', textElement.textContent.trim());
                }
            });

            // Expandir menu automaticamente se a página atual estiver em um submenu
            const menuGroups = ['estudo', 'entretenimento', 'colag', 'sistema'];
            const currentUrl = window.location.pathname;
            let activeGroup = null;
            
            menuGroups.forEach(group => {
                const submenu = document.getElementById(group + '-submenu');
                if (submenu) {
                    const links = submenu.querySelectorAll('a');
                    links.forEach(link => {
                        const hrefAttr = link.getAttribute('href') || '';
                        if (!hrefAttr || hrefAttr === '#' || hrefAttr.startsWith('javascript:')) {
                            return;
                        }
                        try {
                            const linkUrl = new URL(link.href);
                            if (linkUrl.origin !== window.location.origin) {
                                return;
                            }
                            if (linkUrl.pathname === '/' && currentUrl !== '/') {
                                return;
                            }
                            const pathMatches = currentUrl === linkUrl.pathname
                                || (linkUrl.pathname !== '/' && currentUrl.startsWith(linkUrl.pathname + '/'));
                            if (!pathMatches) {
                                return;
                            }
                            // Links com âncora (ex.: /dashboard#noticias) só ativam o grupo
                            // quando o hash da página atual coincide — evita abrir Entretenimento no login.
                            if (linkUrl.hash && window.location.hash !== linkUrl.hash) {
                                return;
                            }
                            activeGroup = group;
                        } catch (e) {
                            // Se não conseguir criar URL, usar comparação simples
                            if (hrefAttr !== '/' && currentUrl.includes(hrefAttr)) {
                                activeGroup = group;
                            }
                        }
                    });
                }
            });

            // Garante que apenas o menu da página atual fique aberto
            menuGroups.forEach(group => {
                const submenu = document.getElementById(group + '-submenu');
                const arrow = document.getElementById(group + '-arrow');
                if (!submenu || !arrow) {
                    return;
                }

                if (group === activeGroup) {
                    submenu.classList.remove('hidden');
                    arrow.style.transform = 'rotate(180deg)';
                    localStorage.setItem('menuGroup_' + group, 'expanded');
                } else {
                    submenu.classList.add('hidden');
                    arrow.style.transform = 'rotate(0deg)';
                    localStorage.setItem('menuGroup_' + group, 'collapsed');
                }
            });

            // Fechar modal
            const closeModalButton = document.getElementById('closeModuleDisabledModal');
            if (closeModalButton) {
                closeModalButton.addEventListener('click', function() {
                    document.getElementById('moduleDisabledModal').classList.add('hidden');
                });
            }
        });

        // Função para mostrar modal de módulo desabilitado
        function mostrarModalModuloDesabilitado(moduleName) {
            document.getElementById('moduleDisabledMessage').textContent = `A funcionalidade '${moduleName}' está desativada no momento. Por favor, entre em contato com o administrador.`;
            document.getElementById('moduleDisabledModal').classList.remove('hidden');
        }

        // Função para alternar grupos de menu (accordion)
        function toggleMenuGroup(groupName) {
            const submenu = document.getElementById(groupName + '-submenu');
            const arrow = document.getElementById(groupName + '-arrow');
            
            if (submenu && arrow) {
                const isHidden = submenu.classList.contains('hidden');
                
                if (isHidden) {
                    submenu.classList.remove('hidden');
                    arrow.style.transform = 'rotate(180deg)';
                    // Salvar estado expandido
                    localStorage.setItem('menuGroup_' + groupName, 'expanded');
                } else {
                    submenu.classList.add('hidden');
                    arrow.style.transform = 'rotate(0deg)';
                    // Salvar estado colapsado
                    localStorage.setItem('menuGroup_' + groupName, 'collapsed');
                }
            }
        }
    </script>

    <!-- Common JavaScript -->
    <script>
        // Global utility functions
        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 p-4 rounded-lg shadow-lg z-50 ${
                type === 'success' ? 'bg-green-500 text-white' : 
                type === 'error' ? 'bg-red-500 text-white' : 
                'bg-blue-500 text-white'
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

        // Toggle User Dropdown
        function toggleUserDropdown() {
            const dropdown = document.getElementById('userDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Fechar dropdown ao clicar fora
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const button = event.target.closest('button[onclick="toggleUserDropdown()"]');
            const buttonParent = event.target.closest('.relative');
            
            if (!button && !buttonParent && dropdown && !dropdown.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    </script>

    <!-- Sidebar Collapsed Styles -->
    <style>
    /* Sidebar Collapsed Styles */
    #sidebar.collapsed {
        width: 4rem !important; /* 64px */
    }

    #sidebar.collapsed .sidebar-text {
        opacity: 0 !important;
        transform: translateX(-10px);
        pointer-events: none;
        width: 0;
        overflow: hidden;
    }

    #sidebar.collapsed .sidebar-text-container {
        width: 0 !important;
        overflow: hidden;
        margin: 0;
        padding: 0;
    }

    /* Header adjustments */
    #sidebar.collapsed .sidebar-header {
        justify-content: center !important;
        flex-direction: column !important;
        gap: 0.5rem;
    }

    #sidebar.collapsed .sidebar-logo-container {
            justify-content: center !important;
        }
        
    #sidebar.collapsed .sidebar-icon {
            margin-right: 0 !important;
        }
        
    #sidebar.collapsed .sidebar-toggle {
            position: absolute !important;
        top: 0.5rem !important;
            right: 0.5rem !important;
    }

    /* User info adjustments */
    #sidebar.collapsed .sidebar-user-info {
        padding: 0.5rem !important;
    }

    #sidebar.collapsed .sidebar-user-info .flex {
            justify-content: center !important;
        }
        
    #sidebar.collapsed .sidebar-user-info .mr-3 {
            margin-right: 0 !important;
        }
        
    /* Navigation adjustments */
    #sidebar.collapsed nav a {
            justify-content: center !important;
            padding: 0.75rem !important;
        }
        
    #sidebar.collapsed nav a .mr-3 {
            margin-right: 0 !important;
        }
        
    /* Ensure icons are same size as admin when collapsed */
    #sidebar.collapsed nav a svg {
        width: 1.5rem !important;
        height: 1.5rem !important;
    }
        
    /* Container adjustments */
    #sidebar.collapsed > div {
        padding: 0.5rem !important;
    }

    #sidebar.collapsed .mb-8 {
        margin-bottom: 1rem !important;
    }

    #sidebar.collapsed .mb-6 {
        margin-bottom: 0.75rem !important;
    }

    /* Smooth transitions */
    .sidebar-text {
            transition: all 0.3s ease-in-out;
        }
        
    .sidebar-text-container {
        transition: all 0.3s ease-in-out;
        }
        
    .sidebar-user-info {
        transition: all 0.3s ease-in-out;
        }
        
    .sidebar-header {
        transition: all 0.3s ease-in-out;
        }
        
    .sidebar-logo-container {
        transition: all 0.3s ease-in-out;
        }
        
    nav a {
        transition: all 0.3s ease-in-out;
        }
        
    /* Tooltip styles for collapsed state */
    #sidebar.collapsed nav a {
            position: relative;
    }

    #sidebar.collapsed nav a:hover::after {
        content: attr(data-tooltip);
        position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        background: rgba(0, 0, 0, 0.8);
        color: white;
        padding: 0.5rem 0.75rem;
        border-radius: 0.375rem;
        font-size: 0.875rem;
        white-space: nowrap;
        z-index: 1000;
        margin-left: 0.5rem;
        pointer-events: none;
    }

    #sidebar.collapsed nav a:hover::before {
        content: '';
            position: absolute;
        left: 100%;
        top: 50%;
        transform: translateY(-50%);
        border: 5px solid transparent;
        border-right-color: rgba(0, 0, 0, 0.8);
        z-index: 1000;
        margin-left: -0.25rem;
        pointer-events: none;
    }

    /* Expand button styles */
    #expandSidebar {
        width: 100%;
        height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        border: none;
        background: none;
        cursor: pointer;
        border-radius: 0.75rem;
    }

    #expandSidebar:hover {
        background: rgba(255, 255, 255, 0.1);
    }

    #expandSidebar:active {
        transform: scale(0.95);
    }

    /* Larger icon when sidebar is collapsed */
    #sidebar.collapsed #expandSidebar svg {
        width: 1.5rem !important;
        height: 1.5rem !important;
    }

    /* Hide expand button when sidebar is expanded */
    #sidebar:not(.collapsed) #expandSidebar {
        display: none;
    }

    /* Show expand button when sidebar is collapsed */
    #sidebar.collapsed #expandSidebar {
        display: flex;
    }

    /* Menu group styles */
    .menu-group {
        transition: all 0.3s ease-in-out;
    }

    /* Submenu styles */
    #estudo-submenu,
    #entretenimento-submenu,
    #colag-submenu,
    #sistema-submenu {
        transition: all 0.3s ease-in-out;
        /* Alto o suficiente pra nunca cortar o último item (ex.: "Tudinha" em
           Estudo) à medida que itens novos são adicionados ao menu. */
        max-height: 1600px;
        overflow: hidden;
    }

    #estudo-submenu.hidden,
    #entretenimento-submenu.hidden,
    #colag-submenu.hidden,
    #sistema-submenu.hidden {
        max-height: 0;
        margin: 0;
        padding: 0;
        overflow: hidden;
    }

    /* Hide submenus when sidebar is collapsed */
    #sidebar.collapsed #estudo-submenu,
    #sidebar.collapsed #entretenimento-submenu,
    #sidebar.collapsed #colag-submenu,
    #sidebar.collapsed #sistema-submenu {
        display: none !important;
    }

    /* Hide group arrows when sidebar is collapsed */
    #sidebar.collapsed #estudo-arrow,
    #sidebar.collapsed #entretenimento-arrow,
    #sidebar.collapsed #colag-arrow,
    #sidebar.collapsed #sistema-arrow {
        display: none !important;
    }

    /* Smooth arrow rotation */
    #estudo-arrow,
    #entretenimento-arrow,
    #colag-arrow,
    #sistema-arrow {
        transition: transform 0.3s ease-in-out;
    }
    </style>

    <?php if (isset($additional_js)): ?>
        <?= $additional_js ?>
    <?php endif; ?>

    <?php if ($studentAccessibilityEnabled): ?>
    <script>
        (function() {
            const storageKey = 'educatudo_student_accessibility';
            const defaults = {
                fontSize: 'normal',
                contrast: 'default',
                fontFamily: 'default',
                textSpacing: 'normal',
                elementSpacing: 'normal',
                buttonSize: 'normal',
                highlightButtons: false,
                highlightFocus: false,
                readAloud: false,
                autoRead: false,
                readSpeed: 'normal',
                focusMode: false,
                reduceMotion: false,
                highlightCursor: false
            };
            const modal = document.getElementById('ei-accessibility-modal');
            const toggle = document.getElementById('ei-accessibility-toggle');
            const close = document.getElementById('ei-accessibility-close');
            const save = document.getElementById('ei-accessibility-save');
            const reset = document.getElementById('ei-accessibility-reset');
            const btn = document.getElementById('ei-global-reader');
            const speedMap = { lenta: 0.75, normal: 0.95, rapida: 1.15 };
            let readerState = 'idle';
            let currentPrefs = loadPrefs();

            function loadPrefs() {
                try {
                    const stored = JSON.parse(localStorage.getItem(storageKey) || '{}');
                    return Object.assign({}, defaults, stored && typeof stored === 'object' ? stored : {});
                } catch (e) {
                    return Object.assign({}, defaults);
                }
            }

            function savePrefs(prefs) {
                currentPrefs = Object.assign({}, defaults, prefs || {});
                localStorage.setItem(storageKey, JSON.stringify(currentPrefs));
                applyPrefs(currentPrefs);
                syncForm(currentPrefs);
            }

            function setClass(prefix, value) {
                Array.from(document.body.classList).forEach(function(cls) {
                    if (cls.indexOf(prefix) === 0) {
                        document.body.classList.remove(cls);
                    }
                });
                if (value) {
                    document.body.classList.add(prefix + value);
                }
            }

            function applyPrefs(prefs) {
                const hasAny = JSON.stringify(Object.assign({}, defaults, prefs)) !== JSON.stringify(defaults);
                document.body.classList.toggle('ei-accessible', hasAny);
                setClass('ei-font-', prefs.fontSize !== 'normal' ? prefs.fontSize : '');
                setClass('ei-contrast-', prefs.contrast !== 'default' ? prefs.contrast : '');
                setClass('ei-family-', prefs.fontFamily !== 'default' ? prefs.fontFamily : '');
                setClass('ei-text-spacing-', prefs.textSpacing !== 'normal' ? prefs.textSpacing : '');
                setClass('ei-element-spacing-', prefs.elementSpacing !== 'normal' ? prefs.elementSpacing : '');
                setClass('ei-button-', prefs.buttonSize !== 'normal' ? prefs.buttonSize : '');
                document.body.classList.toggle('ei-highlight-buttons', !!prefs.highlightButtons);
                document.body.classList.toggle('ei-highlight-focus', !!prefs.highlightFocus);
                document.body.classList.toggle('ei-focus-mode', !!prefs.focusMode);
                document.body.classList.toggle('ei-reduce-motion', !!prefs.reduceMotion);
                document.body.classList.toggle('ei-highlight-cursor', !!prefs.highlightCursor);
                if (btn) {
                    btn.classList.toggle('hidden', !prefs.readAloud && !prefs.autoRead);
                    btn.classList.toggle('inline-flex', !!prefs.readAloud || !!prefs.autoRead);
                }
                if (toggle) {
                    toggle.classList.toggle('bottom-36', !!prefs.readAloud || !!prefs.autoRead);
                    toggle.classList.toggle('bottom-5', !prefs.readAloud && !prefs.autoRead);
                }
            }

            function syncForm(prefs) {
                document.querySelectorAll('[data-ei-pref]').forEach(function(input) {
                    const key = input.getAttribute('data-ei-pref');
                    if (!key) return;
                    if (input.type === 'checkbox') {
                        input.checked = !!prefs[key];
                    } else {
                        input.value = prefs[key] || defaults[key] || '';
                    }
                });
            }

            function collectForm() {
                const prefs = Object.assign({}, currentPrefs);
                document.querySelectorAll('[data-ei-pref]').forEach(function(input) {
                    const key = input.getAttribute('data-ei-pref');
                    if (!key) return;
                    prefs[key] = input.type === 'checkbox' ? input.checked : input.value;
                });
                return prefs;
            }

            function openModal() {
                syncForm(currentPrefs);
                if (modal) {
                    modal.classList.remove('hidden');
                    modal.classList.add('flex');
                }
            }

            function closeModal() {
                if (modal) {
                    modal.classList.add('hidden');
                    modal.classList.remove('flex');
                }
            }

            function setState(nextState) {
                readerState = nextState;
                if (!btn) return;
                btn.setAttribute('aria-pressed', nextState === 'playing' ? 'true' : 'false');
                const icon = btn.querySelector('i');
                const label = btn.querySelector('span');
                const states = {
                    idle: ['fa-solid fa-play', 'Reproduzir leitura', 'Ouvir conteúdo da página'],
                    playing: ['fa-solid fa-pause', 'Pausar leitura', 'Pausar leitura da página'],
                    paused: ['fa-solid fa-play', 'Continuar leitura', 'Continuar leitura da página']
                };
                const current = states[nextState] || states.idle;
                if (icon) {
                    icon.className = current[0];
                }
                if (label) {
                    label.textContent = current[1];
                }
                btn.setAttribute('aria-label', current[2]);
            }

            function pageText() {
                const source = document.querySelector('[data-ei-reader-source="1"]') || document.getElementById('student-main-content');
                if (!source) return '';
                const clone = source.cloneNode(true);
                clone.querySelectorAll('script,style,noscript,svg,button,input,select,textarea,[aria-hidden="true"],.ei-read-ignore,#ei-global-reader').forEach(function(node) {
                    node.remove();
                });
                return (clone.textContent || '').replace(/\s+/g, ' ').trim().slice(0, 12000);
            }

            function toggleReader() {
                if (!('speechSynthesis' in window)) return;
                if (readerState === 'playing') {
                    window.speechSynthesis.pause();
                    setState('paused');
                    return;
                }
                if (readerState === 'paused') {
                    window.speechSynthesis.resume();
                    setState('playing');
                    return;
                }
                const text = pageText();
                if (!text) return;
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'pt-BR';
                utterance.rate = speedMap[currentPrefs.readSpeed] || speedMap.normal;
                utterance.onend = function() { setState('idle'); };
                utterance.onerror = function() { setState('idle'); };
                setState('playing');
                window.speechSynthesis.speak(utterance);
            }

            if (toggle) toggle.addEventListener('click', openModal);
            if (close) close.addEventListener('click', closeModal);
            if (save) save.addEventListener('click', function() {
                savePrefs(collectForm());
                closeModal();
            });
            if (reset) reset.addEventListener('click', function() {
                localStorage.removeItem(storageKey);
                currentPrefs = Object.assign({}, defaults);
                applyPrefs(currentPrefs);
                syncForm(currentPrefs);
            });
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === modal) closeModal();
                });
            }
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeModal();
            });
            if (btn) {
                btn.addEventListener('click', toggleReader);
            }
            window.addEventListener('beforeunload', function() {
                try { window.speechSynthesis.cancel(); } catch (e) {}
            });
            applyPrefs(currentPrefs);
            syncForm(currentPrefs);
            if (currentPrefs.autoRead) {
                setTimeout(toggleReader, 700);
            }
        })();
    </script>
    <?php endif; ?>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= URL ?>/service-worker.js').catch(() => {});
        }
    </script>
    <!-- MathJax v3: renderização de fórmulas matemáticas (LaTeX) na Jornada do Aluno -->
    <script>
      window.MathJax = {
        tex: {
          inlineMath: [['$', '$'], ['\\(', '\\)']],
          displayMath: [['$$', '$$'], ['\\[', '\\]']]
        },
        svg: { fontCache: 'global' }
      };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>
    <script>
      (function() {
        function runTypeset() {
          if (window.MathJax && window.MathJax.typesetPromise) {
            MathJax.typesetPromise().catch(function(err) { console.warn('MathJax typeset:', err); });
          }
        }
        if (document.readyState === 'loading') {
          document.addEventListener('DOMContentLoaded', function() { runTypeset(); setTimeout(runTypeset, 500); setTimeout(runTypeset, 1500); });
        } else {
          runTypeset();
          setTimeout(runTypeset, 500);
          setTimeout(runTypeset, 1500);
        }
        var checkMathJax = setInterval(function() {
          if (window.MathJax && window.MathJax.typesetPromise) {
            clearInterval(checkMathJax);
            runTypeset();
          }
        }, 100);
        setTimeout(function() { clearInterval(checkMathJax); }, 10000);
      })();
    </script>
    <?php
    $base_url = defined('URL') ? URL : '';
    if (!class_exists('LayoutHelper')) { require_once __DIR__ . '/../Core/LayoutHelper.php'; }
    $onesignal_app_id = class_exists('LayoutHelper') ? trim(LayoutHelper::get('onesignal_app_id', '')) : '';
    if ($onesignal_app_id === '' && function_exists('env')) { $onesignal_app_id = trim(env('ONESIGNAL_APP_ID', '')); }
    include __DIR__ . '/../components/onesignal-init.php';
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.notes-open-new-tab').forEach(function(el) {
            el.addEventListener('click', function(e) {
                e.preventDefault();
                var apiUrl = el.getAttribute('data-notes-url');
                if (!apiUrl) return;
                fetch(apiUrl, { credentials: 'same-origin' }).then(function(r) { return r.json(); }).then(function(data) {
                    if (data && data.url) window.open(data.url, '_blank', 'noopener,noreferrer');
                }).catch(function() { window.open(el.getAttribute('href'), '_blank'); });
            });
        });
    });
    </script>
    <?php if (isset($user['tipo']) && $user['tipo'] === 'aluno'): ?>
    <script>window.EDUCATUDO_URL = <?= json_encode(defined('URL') ? URL : '') ?>;</script>
    <script src="<?= URL ?>/public/static/js/student-presence.js?v=20250608b"></script>
    <?php endif; ?>
    <?php include __DIR__ . '/components/websocket-presence.php'; ?>

    <?php if (LayoutHelper::isModuleEnabled('vlibras')): ?>
    <!-- VLibras (tradução para Libras, acessibilidade) -->
    <!--
        O widget usa `position: fixed` internamente (botão, balão de intro,
        avatar) com posições que mudam de versão pra versão, e não dá pra
        confiar em sobrescrever isso elemento por elemento via seletor/JS.
        Truque de CSS: qualquer ancestral com `transform` diferente de `none`
        passa a ser o "containing block" de tudo que é `position: fixed` lá
        dentro (em vez do viewport). Então em vez de mexer no widget, criamos
        uma caixinha fixa do tamanho do botão, ao lado do botão de
        acessibilidade, e tudo que o VLibras tentar posicionar "fixed" cai
        dentro dessa caixinha — independente da estrutura interna dele.
    -->
    <div id="vlibras-fixed-context" style="position: fixed; top: 50%; right: 12px; width: 64px; height: 64px; transform: translateY(-50%) translateZ(0); z-index: 40; overflow: visible;">
        <div vw class="enabled">
            <div vw-access-button class="active"></div>
            <div vw-plugin-wrapper>
                <div class="vw-plugin-top-wrapper"></div>
            </div>
        </div>
    </div>
    <script src="https://vlibras.gov.br/app/vlibras-plugin.js"></script>
    <script>
        new window.VLibras.Widget('https://vlibras.gov.br/app');
    </script>
    <?php endif; ?>
    <script>
    (function () {
        var progress = document.getElementById('htmx-progress');
        var progressHideTimer = null;

        function setProgress(state) {
            if (!progress) return;
            progress.classList.remove('htmx-progress-active', 'htmx-progress-done');
            if (state === 'start') {
                if (progressHideTimer) clearTimeout(progressHideTimer);
                // reflow para reiniciar a transição
                void progress.offsetWidth;
                progress.classList.add('htmx-progress-active');
            } else if (state === 'done') {
                progress.classList.add('htmx-progress-done');
                progressHideTimer = setTimeout(function () {
                    progress.classList.remove('htmx-progress-done');
                    progress.style.width = '';
                }, 350);
            }
        }

        function closeMobileSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebar-overlay');
            if (sidebar) sidebar.classList.remove('mobile-open');
            if (overlay) overlay.classList.remove('active');
        }

        function syncSidebarActiveFromUrl() {
            var path = (window.location.pathname || '/').replace(/\/+$/, '') || '/';
            var hash = window.location.hash || '';
            var links = document.querySelectorAll('#sidebar nav a[href]');
            var best = null;
            var bestLen = -1;

            links.forEach(function (link) {
                var href = link.getAttribute('href') || '';
                if (!href || href === '#' || href.indexOf('logout') !== -1 || href.indexOf('javascript:') === 0) {
                    return;
                }
                if (link.hasAttribute('target') && link.getAttribute('target') === '_blank') {
                    return;
                }
                try {
                    var url = new URL(link.href, window.location.origin);
                    if (url.origin !== window.location.origin) return;
                    var linkPath = (url.pathname || '/').replace(/\/+$/, '') || '/';
                    if (linkPath === '/' && path !== '/') return;
                    var matches = path === linkPath || (linkPath !== '/' && path.indexOf(linkPath + '/') === 0);
                    if (!matches) return;
                    if (url.hash && hash !== url.hash) return;
                    if (linkPath.length > bestLen) {
                        best = link;
                        bestLen = linkPath.length;
                    }
                } catch (e) {}
            });

            links.forEach(function (link) {
                if (link.classList.contains('text-red-200') || (link.getAttribute('href') || '').indexOf('logout') !== -1) {
                    return;
                }
                link.classList.remove('text-white', 'bg-white/20');
                if (!link.classList.contains('text-purple-100')) {
                    link.classList.add('text-purple-100');
                }
            });

            if (best) {
                best.classList.remove('text-purple-100');
                best.classList.add('text-white', 'bg-white/20');
            }

            // Abre o grupo do item ativo
            var menuGroups = ['estudo', 'entretenimento', 'colag', 'sistema'];
            var activeGroup = null;
            if (best) {
                menuGroups.forEach(function (group) {
                    var submenu = document.getElementById(group + '-submenu');
                    if (submenu && submenu.contains(best)) {
                        activeGroup = group;
                    }
                });
            }
            menuGroups.forEach(function (group) {
                var submenu = document.getElementById(group + '-submenu');
                var arrow = document.getElementById(group + '-arrow');
                if (!submenu || !arrow) return;
                if (group === activeGroup) {
                    submenu.classList.remove('hidden');
                    arrow.style.transform = 'rotate(180deg)';
                }
            });
        }

        /**
         * Sessão de aluno em PCs de laboratório:
         * 1) Navegação do menu é FULL PAGE (hx-boost desligado) — sidebar e header
         *    sempre vêm da mesma resposta.
         * 2) Se outra aba logar outro aluno, localStorage força reload nesta aba.
         * 3) No foco da aba, se sidebar e header mostrarem nomes diferentes, reload.
         */
        var AUTH_STORAGE_KEY = 'educatudo_auth_user';
        var currentAuthUser = (document.body.getAttribute('data-auth-user') || '').trim();

        function forceFullReload() {
            try {
                window.location.reload();
            } catch (e) {
                window.location.href = window.location.href.split('#')[0];
            }
        }

        function syncAuthAcrossTabs() {
            if (!currentAuthUser || currentAuthUser === ':' || /:0$/.test(currentAuthUser)) {
                return;
            }
            try {
                var prev = (localStorage.getItem(AUTH_STORAGE_KEY) || '').trim();
                if (prev && prev !== currentAuthUser) {
                    // Outra aba já autenticou usuário diferente antes desta página pintar
                    localStorage.setItem(AUTH_STORAGE_KEY, currentAuthUser);
                    forceFullReload();
                    return;
                }
                localStorage.setItem(AUTH_STORAGE_KEY, currentAuthUser);
            } catch (e) {}
            window.addEventListener('storage', function (e) {
                if (e.key !== AUTH_STORAGE_KEY) return;
                var next = (e.newValue || '').trim();
                if (next !== currentAuthUser) {
                    forceFullReload();
                }
            });
        }
        syncAuthAcrossTabs();

        function headerWelcomeName() {
            var el = document.querySelector('header p.text-gray-600, header .truncate');
            if (!el) return '';
            var t = (el.textContent || '').trim();
            // "Bem-vindo, NOME!" → NOME
            var m = t.match(/Bem-vindo,\s*(.+?)!?\s*$/i);
            return m ? m[1].trim() : '';
        }

        function sidebarName() {
            var el = document.getElementById('sidebar-user-name');
            return el ? (el.textContent || '').trim() : '';
        }

        function ensureIdentityConsistent() {
            var side = sidebarName();
            var head = headerWelcomeName();
            if (!side || !head) return;
            // Normaliza espaços; se forem pessoas diferentes, a página está inconsistente
            if (side.toLowerCase() !== head.toLowerCase()) {
                forceFullReload();
            }
        }

        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                ensureIdentityConsistent();
            }
        });
        window.addEventListener('focus', ensureIdentityConsistent);
        setTimeout(ensureIdentityConsistent, 300);

        function updateDocumentTitle(xhr) {
            if (!xhr || !xhr.responseText) return;
            var match = xhr.responseText.match(/<title[^>]*>([^<]*)<\/title>/i);
            if (match && match[1]) {
                document.title = match[1].trim();
            }
        }

        function isSoftNav(evt) {
            var target = evt.detail && evt.detail.target;
            if (target && target.id === 'student-main-content') return true;
            var boosted = evt.detail && (evt.detail.boosted || (evt.detail.requestConfig && evt.detail.requestConfig.boosted));
            return !!boosted;
        }

        // Se restar algum soft-nav pontual, ainda bloqueia mistura de usuário
        function authUserFromXhr(xhr) {
            if (!xhr || typeof xhr.getResponseHeader !== 'function') return '';
            return (xhr.getResponseHeader('X-Educatudo-Auth-User') || '').trim();
        }
        function authUserFromHtml(html) {
            if (!html) return '';
            var m = String(html).match(/data-auth-user=["']([^"']+)["']/i);
            return m && m[1] ? m[1].trim() : '';
        }
        function ensureSameAuthOrReload(xhr) {
            var next = authUserFromXhr(xhr) || authUserFromHtml(xhr && xhr.responseText);
            if (!next || !currentAuthUser) return true;
            if (next !== currentAuthUser) {
                try { localStorage.setItem(AUTH_STORAGE_KEY, next); } catch (e) {}
                forceFullReload();
                return false;
            }
            return true;
        }

        document.body.addEventListener('htmx:beforeSwap', function (evt) {
            if (!isSoftNav(evt)) return;
            if (!ensureSameAuthOrReload(evt.detail && evt.detail.xhr)) {
                evt.preventDefault();
            }
        });

        document.body.addEventListener('htmx:beforeRequest', function (evt) {
            if (isSoftNav(evt)) {
                setProgress('start');
            }
        });

        document.body.addEventListener('htmx:afterSettle', function (evt) {
            if (!isSoftNav(evt)) return;
            if (!ensureSameAuthOrReload(evt.detail && evt.detail.xhr)) return;
            setProgress('done');
            closeMobileSidebar();
            syncSidebarActiveFromUrl();
            updateDocumentTitle(evt.detail.xhr);
            ensureIdentityConsistent();
            var main = document.getElementById('student-main-content');
            if (main) {
                try { main.scrollTop = 0; } catch (e) {}
            }
            var dropdown = document.getElementById('userDropdown');
            if (dropdown) dropdown.classList.add('hidden');
            if (window.MathJax && window.MathJax.typesetPromise) {
                window.MathJax.typesetPromise([main].filter(Boolean)).catch(function () {});
            }
        });

        function hardNavigateFromHtmxEvent(evt) {
            try {
                var cfg = evt.detail && evt.detail.requestConfig;
                var path = (evt.detail && evt.detail.pathInfo && (evt.detail.pathInfo.requestPath || evt.detail.pathInfo.finalRequestPath))
                    || (cfg && cfg.path)
                    || '';
                if (!path && cfg && cfg.elt && cfg.elt.getAttribute) {
                    path = cfg.elt.getAttribute('href') || '';
                }
                if (path && path !== '#' && path.indexOf('javascript:') !== 0) {
                    window.location.href = path;
                    return true;
                }
            } catch (e) {}
            return false;
        }

        document.body.addEventListener('htmx:responseError', function (evt) {
            setProgress('done');
            // Soft-nav falhou (ex.: 500): faz reload completo para não ficar na mesma tela
            if (isSoftNav(evt)) {
                hardNavigateFromHtmxEvent(evt);
            }
        });
        document.body.addEventListener('htmx:sendError', function (evt) {
            setProgress('done');
            if (isSoftNav(evt)) {
                hardNavigateFromHtmxEvent(evt);
            }
        });
        document.body.addEventListener('htmx:swapError', function (evt) {
            setProgress('done');
            if (isSoftNav(evt)) {
                hardNavigateFromHtmxEvent(evt);
            }
        });
    })();
    </script>

    <?php
    // CSS final (depois de tudo) + JS: garante sidebar legível mesmo se outro CSS ganhar a briga
    $__sbEnd = LayoutHelper::getSidebarColors();
    $__sbEndText = htmlspecialchars($__sbEnd['text'], ENT_QUOTES, 'UTF-8');
    ?>
    <style id="sidebar-fix-final-v5">
    /* sidebar-fix-final-v5 — padrão Colag (anexo 2):
       texto branco; highlight SOMENTE no item ativo (bg-white/10|20).
       NÃO usar [class*="bg-white/"] — isso pega hover:bg-white/20 de TODOS os links. */
    #sidebar.sidebar-custom,
    #sidebar.sidebar-custom .sidebar-text,
    #sidebar.sidebar-custom a:not(.text-red-200),
    #sidebar.sidebar-custom button,
    #sidebar.sidebar-custom p,
    #sidebar.sidebar-custom span.sidebar-text {
        color: <?= $__sbEndText ?> !important;
    }
    #sidebar.sidebar-custom a:not(.text-red-200) svg,
    #sidebar.sidebar-custom button svg {
        stroke: <?= $__sbEndText ?> !important;
        color: <?= $__sbEndText ?> !important;
    }
    #sidebar.sidebar-custom a.text-red-200,
    #sidebar.sidebar-custom a.text-red-200 * {
        color: #fca5a5 !important;
        stroke: #fca5a5 !important;
    }
    /* Itens inativos: sem caixa */
    #sidebar.sidebar-custom nav > a,
    #sidebar.sidebar-custom nav .menu-group > button,
    #sidebar.sidebar-custom nav .menu-group a {
        background-color: transparent;
    }
    /* Só ativo / card do usuário */
    #sidebar.sidebar-custom nav a.bg-white\/10,
    #sidebar.sidebar-custom nav a.bg-white\/20,
    #sidebar.sidebar-custom nav summary.bg-white\/20 {
        background-color: rgba(255, 255, 255, 0.18) !important;
    }
    #sidebar.sidebar-custom .sidebar-user-info {
        background-color: rgba(255, 255, 255, 0.12) !important;
    }
    #sidebar.sidebar-custom nav a.hover\:bg-white\/10:hover,
    #sidebar.sidebar-custom nav a.hover\:bg-white\/20:hover,
    #sidebar.sidebar-custom nav button.hover\:bg-white\/20:hover,
    #sidebar.sidebar-custom nav a.hover\:bg-white\/15:hover {
        background-color: rgba(255, 255, 255, 0.12) !important;
    }
    </style>
    <script>
    (function () {
        var sb = document.getElementById('sidebar');
        if (!sb) return;
        var want = (sb.getAttribute('data-sidebar-text') || '#ffffff').trim();
        function paint() {
            var nodes = sb.querySelectorAll('a, button, .sidebar-text, #sidebar-user-name, .sidebar-user-info p');
            for (var i = 0; i < nodes.length; i++) {
                var el = nodes[i];
                if (el.classList && (el.classList.contains('text-red-200') || (el.closest && el.closest('a.text-red-200')))) {
                    el.style.setProperty('color', '#fca5a5', 'important');
                    continue;
                }
                el.style.setProperty('color', want, 'important');
            }
            var svgs = sb.querySelectorAll('svg');
            for (var j = 0; j < svgs.length; j++) {
                var a = svgs[j].closest('a.text-red-200');
                svgs[j].style.setProperty('stroke', a ? '#fca5a5' : want, 'important');
                svgs[j].style.setProperty('color', a ? '#fca5a5' : want, 'important');
            }
        }
        paint();
        document.addEventListener('DOMContentLoaded', paint);
        setTimeout(paint, 50);
        setTimeout(paint, 400);
    })();
    </script>
</body>
</html>
