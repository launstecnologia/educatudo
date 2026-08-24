<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'EducaTudo Admin') ?></title>
    <link rel="manifest" href="<?= URL ?>/manifest-admin.json">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#faf5ff',
                            100: '#f3e8ff',
                            200: '#e9d5ff',
                            300: '#d8b4fe',
                            400: '#c084fc',
                            500: '#a855f7',
                            600: '#9333ea',
                            700: '#7c3aed',
                            800: '#6b21a8',
                            900: '#581c87',
                        },
                        secondary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        },
                        accent: {
                            50: '#fdf4ff',
                            100: '#fae8ff',
                            200: '#f5d0fe',
                            300: '#f0abfc',
                            400: '#e879f9',
                            500: '#d946ef',
                            600: '#c026d3',
                            700: '#a21caf',
                            800: '#86198f',
                            900: '#701a75',
                        }
                    }
                }
            }
        }
    </script>
    <?php 
    // Incluir LayoutHelper
    require_once __DIR__ . '/../../Core/LayoutHelper.php';
    require_once __DIR__ . '/../../Helpers/AvatarUrlHelper.php';

    if (isset($user) && is_array($user) && in_array($user['tipo'] ?? '', ['admin', 'admin_escola'], true)) {
        $resolvedAvatar = AvatarUrlHelper::resolveAdminUserAvatar($user);
        if ($resolvedAvatar !== null) {
            $user['avatar_url'] = $resolvedAvatar;
            $_SESSION['avatar_url'] = $resolvedAvatar;
        }
    }

    $mobileDefaults = [
        'enabled' => false,
        'breakpoint' => 768,
        'layout' => 'bottom-navigation',
        'components' => [
            'header' => true,
            'breadcrumb' => true,
            'sidebar' => false
        ],
        'bottom_nav' => [
            'enabled' => true,
            'items' => [
                ['label' => 'Dashboard', 'route' => '/admin/dashboard', 'icon' => 'home'],
                ['label' => 'Alunos', 'route' => '/admin/students', 'icon' => 'users'],
                ['label' => 'Ocorrências', 'route' => '/admin/ocorrencias', 'icon' => 'alert']
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

    $mobileConfig = json_decode(LayoutHelper::get('mobile_layout_admin', ''), true);
    $mobileConfig = is_array($mobileConfig) ? array_replace_recursive($mobileDefaults, $mobileConfig) : $mobileDefaults;
    $mobileEnabled = !empty($mobileConfig['enabled']);
    $mobileBreakpoint = (int) ($mobileConfig['breakpoint'] ?? 768);
    $mobileShowSidebar = (bool) ($mobileConfig['components']['sidebar'] ?? false);
    $mobileShowHeader = (bool) ($mobileConfig['components']['header'] ?? true);
    $mobileBottomNavEnabled = (bool) ($mobileConfig['bottom_nav']['enabled'] ?? true);
    $mobileBottomNavItems = $mobileConfig['bottom_nav']['items'] ?? [];
    $mobileFabEnabled = (bool) ($mobileConfig['fab']['enabled'] ?? false);
    $mobileFabAction = (string) ($mobileConfig['fab']['action'] ?? '');
    $mobileSpacing = $mobileConfig['appearance']['spacing'] ?? 'compact';
    $mobileFontSize = $mobileConfig['appearance']['font_size'] ?? 'base';

    function renderMobileAdminIcon($icon)
    {
        $icons = [
            'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10.5L12 3l9 7.5v9a1.5 1.5 0 01-1.5 1.5H4.5A1.5 1.5 0 013 19.5v-9z" />',
            'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20v-2a4 4 0 00-4-4H7a4 4 0 00-4 4v2m12-10a4 4 0 10-8 0 4 4 0 008 0zm6 10v-2a4 4 0 00-3-3.87" />',
            'alert' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v4m0 4h.01M5.07 19h13.86c1.1 0 1.83-1.16 1.25-2.1L13.26 4.1a1.5 1.5 0 00-2.52 0L3.82 16.9c-.58.94.15 2.1 1.25 2.1z" />',
            'settings' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9.75A2.25 2.25 0 1112 14.25 2.25 2.25 0 0112 9.75zm7.5 2.25a7.5 7.5 0 01-.09 1.17l2.02 1.57-2 3.46-2.44-.98a7.48 7.48 0 01-2.02 1.17l-.37 2.59h-4l-.37-2.59a7.48 7.48 0 01-2.02-1.17l-2.44.98-2-3.46 2.02-1.57A7.5 7.5 0 014.5 12c0-.4.03-.79.09-1.17L2.57 9.26l2-3.46 2.44.98c.62-.49 1.3-.9 2.02-1.17L9.4 3h4l.37 2.59c.72.27 1.4.68 2.02 1.17l2.44-.98 2 3.46-2.02 1.57c.06.38.09.77.09 1.17z" />'
        ];
        $path = $icons[$icon] ?? $icons['home'];
        return '<svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . $path . '</svg>';
    }
    ?>
    <style>
        <?= LayoutHelper::generateCustomCSS() ?>

        @media (min-width: 768px) {
            body {
                overflow: hidden;
            }

            #sidebar {
                height: 100vh;
                overflow-y: auto;
                overscroll-behavior: contain;
            }

            main {
                height: 100vh;
                overflow-y: auto;
                overscroll-behavior: contain;
            }
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
    </style>
    <?php if (isset($additional_css)): ?>
        <?= $additional_css ?>
    <?php endif; ?>
</head>
<body class="page-background min-h-screen font-sans antialiased flex flex-col mobile-spacing-<?= htmlspecialchars($mobileSpacing) ?> mobile-font-<?= htmlspecialchars($mobileFontSize) ?>" style="background-color: #f8fafc; overflow-x: hidden;" data-mobile-enabled="<?= $mobileEnabled ? '1' : '0' ?>">
    <!-- Overlay para mobile sidebar (igual ao Professor) -->
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    
    <!-- Sidebar + Main (navbar igual ao Professor) -->
    <div class="flex flex-1 relative min-h-0 md:h-screen md:overflow-hidden">
        <?php include __DIR__ . '/components/admin_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 w-full md:w-auto overflow-x-hidden min-h-0">
            <!-- Header com menu hamburger, título, notificações e avatar (padrão Professor) -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-6 py-3 md:py-4 sticky top-0 z-30">
                <div class="flex justify-between items-center gap-2">
                    <!-- Botão menu mobile -->
                    <button id="mobileMenuToggle" type="button" class="md:hidden p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors" aria-label="Abrir menu">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-lg md:text-2xl font-bold text-gray-900 truncate"><?= htmlspecialchars($page_title ?? 'Dashboard Admin') ?></h1>
                        <p class="text-xs md:text-sm text-gray-600 truncate"><?= htmlspecialchars($page_subtitle ?? ('Bem-vindo, ' . ($user['nome'] ?? 'Administrador') . '!')) ?></p>
                    </div>
                    <div class="flex items-center space-x-2 md:space-x-4 flex-shrink-0">
                        <!-- Notificações -->
                        <?php 
                        $notificacoesNaoLidas = 0;
                        $notificacoesRecentes = [];
                        $usuario = $user ?? null;
                        $tipoUsuario = 'admin';
                        
                        if (isset($user['id'])) {
                            try {
                                require_once __DIR__ . '/../../Models/Notifications/Notification.php';
                                $notificacaoModel = new Notification();
                                $notificacoesNaoLidas = $notificacaoModel->getNaoLidas($user['id'], 'admin');
                                $notificacoesRecentes = $notificacaoModel->getByDestinatario($user['id'], 'admin', 5);
                            } catch (Exception $e) {
                                $notificacoesNaoLidas = 0;
                                $notificacoesRecentes = [];
                            }
                        }
                        
                        include __DIR__ . '/components/notification-banner.php';
                        ?>
                        
                        <!-- Avatar do usuário -->
                        <div class="flex items-center space-x-2 md:space-x-3">
                            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white text-sm font-medium overflow-hidden">
                                <?php if (!empty($user['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" class="w-full h-full object-cover rounded-full" onerror="this.remove(); this.parentElement.querySelector('[data-avatar-initials]')?.classList.remove('hidden');">
                                    <span data-avatar-initials class="hidden"><?= strtoupper(substr($user['nome'] ?? 'A', 0, 1)) ?></span>
                                <?php else: ?>
                                    <?= strtoupper(substr($user['nome'] ?? 'A', 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <span class="hidden md:inline text-sm font-medium text-gray-700"><?= htmlspecialchars($user['nome'] ?? 'Admin') ?></span>
                        </div>
                    </div>
                </div>
            </header>
            
            <div class="p-6 mobile-content">
                <?= $content ?>
                
                <!-- Footer -->
                <footer class="mt-8 pt-4 border-t border-gray-200">
                    <div class="space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <p class="text-xs text-gray-600 flex items-center justify-center sm:justify-start gap-2">
                                <span>Todos os direitos reservados</span>
                                <img src="<?= URL ?>/public/assets/logos/logo-educatudo-black.png" alt="EducaTudo" class="h-4 w-auto inline-block" loading="lazy">
                            </p>
                            <p class="text-xs text-gray-600 flex items-center justify-center sm:justify-end gap-2">
                                <span>Desenvolvido por</span>
                                <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="inline-flex items-center">
                                    <img src="<?= URL ?>/public/assets/logos/logo-launs-black.png" alt="Launs" class="h-3 w-auto inline-block" loading="lazy">
                                </a>
                            </p>
                        </div>
                        <p class="text-xs text-gray-600 text-center">
                            <a href="<?= URL ?>/termos-de-uso" class="hover:underline">Termos de Uso</a> •
                            <a href="<?= URL ?>/politica-privacidade" class="hover:underline">Política de Privacidade</a> •
                            <a href="<?= URL ?>/politica-retencao" class="hover:underline">Política de Retenção de Dados</a>
                        </p>
                    </div>
                </footer>
            </div>
        </main>
    </div>
    <!-- Mobile sidebar: mesmo padrão do Professor -->
    <style>
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

    </style>
    <?php include __DIR__ . '/components/form_control_safari.php'; ?>

    <nav class="mobile-bottom-nav hidden fixed bottom-0 left-0 right-0 bg-white border-t border-gray-200 px-4 py-2 justify-around items-center z-40">
        <?php foreach ($mobileBottomNavItems as $item): ?>
            <?php
                $label = $item['label'] ?? 'Item';
                $route = $item['route'] ?? '/admin/dashboard';
                $icon = $item['icon'] ?? 'home';
                $isActive = strpos($_SERVER['REQUEST_URI'] ?? '', $route) === 0;
            ?>
            <a href="<?= htmlspecialchars(URL . $route) ?>" class="flex flex-col items-center text-xs <?= $isActive ? 'text-indigo-600' : 'text-gray-500' ?>">
                <?= renderMobileAdminIcon($icon) ?>
                <span class="mt-1"><?= htmlspecialchars($label) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>
    <?php if ($mobileFabEnabled && !empty($mobileFabAction)): ?>
        <a href="<?= htmlspecialchars(URL . $mobileFabAction) ?>" class="mobile-bottom-nav hidden fixed bottom-20 right-4 bg-indigo-600 text-white w-12 h-12 rounded-full flex items-center justify-center shadow-lg z-50">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
            </svg>
        </a>
    <?php endif; ?>

    <?php
    $needsConsent = false;
    if (!empty($user['id'])) {
        if (!isset($db) || $db === null) {
            require_once __DIR__ . '/../../Core/Database.php';
            $db = Database::getInstance();
        }
        $docs = ['terms', 'privacy'];
        $version = 'v1.0';
        try {
            foreach ($docs as $doc) {
                $row = $db->fetch(
                    "SELECT id FROM usuarios_consentimentos 
                     WHERE user_id = :user_id AND user_role = 'admin'
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
        } catch (Throwable $e) {
            $needsConsent = false;
        }
    }
    ?>

    <?php if ($needsConsent): ?>
    <div id="consentOverlay" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center p-4 z-[9999]">
        <div class="bg-white rounded-lg max-w-lg w-full p-6 shadow-lg">
            <h3 class="text-lg font-semibold mb-3 text-gray-900">Termo de Aceite do Usuário Institucional</h3>
            <div class="text-xs text-gray-700 space-y-2 max-h-72 overflow-y-auto pr-2">
                <p>Declaro que li, compreendi e ACEITO os Termos de Uso e a Política de Privacidade da plataforma EducaTudo, comprometendo-me a utilizar o sistema exclusivamente para fins educacionais, institucionais e administrativos.</p>
                <p><strong>A plataforma é monitorada por inteligência artificial (IA) para detectar assuntos sensíveis e situações de risco, visando a segurança e o bem-estar de todos.</strong></p>
                <p>Estou ciente de que:</p>
                <ul class="list-disc pl-5 space-y-1">
                    <li>Minhas ações na plataforma, incluindo acessos, registros, comunicações e operações realizadas, podem ser monitoradas, registradas e auditadas;</li>
                    <li>O acesso a dados acadêmicos, pedagógicos e pessoais de alunos deve ocorrer estritamente dentro das atribuições do meu perfil;</li>
                    <li>O uso inadequado do sistema poderá resultar em medidas administrativas, institucionais e legais.</li>
                </ul>
                <p>Declaro ciência de que a plataforma adota políticas rigorosas de segurança da informação, governança de dados e conformidade com a LGPD.</p>
            </div>
            <label class="flex items-start gap-2 mt-4 text-xs text-gray-700">
                <input type="checkbox" id="consentCheckbox" class="mt-1">
                <span>Li e ACEITO os Termos de Uso e a Política de Privacidade.</span>
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
        
        // Mobile Menu Toggle (igual ao Professor)
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
        
    </script>

    <!-- Modal Alunos Online -->
    <div id="modalAlunosOnline" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full max-h-[90vh] overflow-hidden">
            <div class="bg-gradient-to-r from-green-500 to-green-600 px-6 py-4 flex justify-between items-center">
                <h3 class="text-xl font-bold text-white">🟢 Alunos Online</h3>
                <button onclick="fecharModalAlunosOnline()" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-6 overflow-y-auto max-h-[calc(90vh-120px)]">
                <div id="alunosOnlineContent" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div class="text-center text-gray-500 py-8">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-green-500 mx-auto mb-4"></div>
                        <p>Carregando alunos online...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
    let alunosOnlineSource = null;
    let ultimoSnapshot = null;

    // Funções para modal de alunos online
    function abrirModalAlunosOnline() {
        document.getElementById('modalAlunosOnline').classList.remove('hidden');
        if (ultimoSnapshot) {
            atualizarAlunosOnlineUI(ultimoSnapshot);
        } else {
            carregarAlunosOnline();
        }
    }
    
    function fecharModalAlunosOnline() {
        document.getElementById('modalAlunosOnline').classList.add('hidden');
    }
    
    function carregarAlunosOnline() {
        const apiUrl = '<?= URL ?>/admin/api/alunos-online';
        
        fetch(apiUrl)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('Erro:', data.error);
                    return;
                }

                atualizarAlunosOnlineUI(data);
            })
            .catch(error => {
                console.error('Erro ao carregar alunos online:', error);
            });
    }

    function atualizarAlunosOnlineUI(data) {
        ultimoSnapshot = data;

        // Atualizar badge
        const badge = document.getElementById('badge-online-count');
        if (badge) {
            badge.textContent = data.total;
            if (data.total === 0) {
                badge.classList.add('hidden');
            } else {
                badge.classList.remove('hidden');
            }
        }

        // Atualizar conteúdo do modal
        const content = document.getElementById('alunosOnlineContent');
        if (content) {
            if (data.alunos.length === 0) {
                content.innerHTML = '<div class="col-span-full text-center text-gray-500 py-8"><p>Nenhum aluno online no momento.</p></div>';
            } else {
                content.innerHTML = data.alunos.map(aluno => `
                    <div class="bg-gradient-to-br from-green-50 to-green-100 border-2 border-green-200 rounded-lg p-4 hover:shadow-lg transition-shadow">
                        <div class="flex items-center justify-between mb-2">
                            <div class="flex items-center">
                                <div class="w-3 h-3 bg-green-500 rounded-full mr-2 animate-pulse"></div>
                                <span class="font-semibold text-gray-900">${aluno.nome}</span>
                            </div>
                        </div>
                        <div class="text-sm text-gray-600 space-y-1">
                            <p><strong>RA:</strong> ${aluno.ra}</p>
                            <p><strong>Turma:</strong> ${aluno.turma_nome}</p>
                            <p><strong>Tempo Online:</strong> <span class="font-mono text-green-700">${aluno.tempo_online.formatado}</span></p>
                        </div>
                    </div>
                `).join('');
            }
        }
    }

    function iniciarStreamAlunosOnline() {
        if (!window.EventSource) {
            // Fallback para polling
            carregarAlunosOnline();
            setInterval(carregarAlunosOnline, 30000);
            return;
        }

        const streamUrl = '<?= URL ?>/admin/api/alunos-online/stream';
        alunosOnlineSource = new EventSource(streamUrl);

        alunosOnlineSource.addEventListener('online', function(event) {
            try {
                const data = JSON.parse(event.data || '{}');
                atualizarAlunosOnlineUI(data);
            } catch (e) {
                console.error('Erro SSE alunos online:', e);
            }
        });

        alunosOnlineSource.addEventListener('error', function() {
            alunosOnlineSource.close();
            setTimeout(iniciarStreamAlunosOnline, 5000);
        });
    }
    
    // Desativado por performance: não iniciar alunos online automaticamente
    document.addEventListener('DOMContentLoaded', function() {
        // carregarAlunosOnline(); // manual apenas
    });
    
    // Fechar modal ao clicar fora
    document.getElementById('modalAlunosOnline')?.addEventListener('click', function(e) {
        if (e.target === this) {
            fecharModalAlunosOnline();
        }
    });
    </script>

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('<?= URL ?>/service-worker.js').catch(() => {});
        }
    </script>

    <!-- MathJax v3: renderização de fórmulas matemáticas (LaTeX) na Jornada -->
    <script>
      window.MathJax = {
        tex: {
          inlineMath: [['\\(', '\\)']],
          displayMath: [['\\[', '\\]']]
        },
        svg: {
          fontCache: 'global'
        }
      };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>

    <?php if (isset($additional_js)): ?>
        <?= $additional_js ?>
    <?php endif; ?>
    <?php
    $base_url = defined('URL') ? URL : '';
    if (!class_exists('LayoutHelper')) { require_once __DIR__ . '/../Core/LayoutHelper.php'; }
    $onesignal_app_id = class_exists('LayoutHelper') ? trim(LayoutHelper::get('onesignal_app_id', '')) : '';
    if ($onesignal_app_id === '' && function_exists('env')) { $onesignal_app_id = trim(env('ONESIGNAL_APP_ID', '')); }
    include __DIR__ . '/../components/onesignal-init.php';
    ?>
    <?php include __DIR__ . '/components/websocket-presence.php'; ?>

    <?php include __DIR__ . '/components/row_actions_dropdown_js.php'; ?>
</body>
</html>
