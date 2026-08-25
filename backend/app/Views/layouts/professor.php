<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes, viewport-fit=cover">
    <?php include __DIR__ . '/components/estilos_plataforma.php'; ?>
    <meta name="theme-color" content="#9333ea">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="EducaTudo">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="description" content="Plataforma educacional para professores">
    <title><?= htmlspecialchars($title ?? 'EducaTudo Professor') ?></title>
    <?php require_once __DIR__ . '/../../Core/LayoutHelper.php'; ?>
    <?php $logo1x1 = LayoutHelper::getLogo1x1Url(); ?>
    <link rel="manifest" href="<?= URL ?>/manifest-professor.json">
    <link rel="apple-touch-icon" href="<?= htmlspecialchars($logo1x1 ?: (URL . '/public/uploads/layout/logo-1x1_1761250311.png')) ?>">
    <?php 
    // Incluir LayoutHelper
    require_once __DIR__ . '/../../Core/LayoutHelper.php';
    $csrf_token = $_SESSION['csrf_token'] ?? '';
    
    // Garantir que o user tem avatar_url se não foi passado
    if (isset($user) && empty($user['avatar_url'])) {
        require_once __DIR__ . '/../../Core/Database.php';
        require_once __DIR__ . '/../../Core/AuthManager.php';
        $authManager = new AuthManager();
        $currentUser = $authManager->getUser();
        
        if ($currentUser && $currentUser['tipo'] === 'professor') {
            $db = Database::getInstance();
            $professor = $db->fetch(
                "SELECT * FROM professores WHERE id = :user_id",
                ['user_id' => $currentUser['id']]
            );
            
            if ($professor && !empty($professor['avatar_url'])) {
                $user['avatar_url'] = $professor['avatar_url'];
            }
        }
    }
    if (isset($user['avatar_url']) && is_string($user['avatar_url'])) {
        $avatarUrl = trim($user['avatar_url']);
        if ($avatarUrl !== '') {
            $normalizedPath = $avatarUrl;
            $pathOnly = parse_url($avatarUrl, PHP_URL_PATH);
            if (is_string($pathOnly) && $pathOnly !== '') {
                $normalizedPath = $pathOnly;
            }

            if (strpos($normalizedPath, '/media/serve') !== false) {
                $user['avatar_url'] = $avatarUrl;
            } else {
                $legacyPrefix = null;
                if (strpos($normalizedPath, '/public/uploads/professores/') !== false) {
                    $legacyPrefix = '/public/uploads/professores/';
                } elseif (strpos($normalizedPath, '/uploads/professores/') !== false) {
                    $legacyPrefix = '/uploads/professores/';
                } elseif (strpos($normalizedPath, 'professores/') === 0) {
                    $legacyPrefix = 'professores/';
                }

                if ($legacyPrefix !== null) {
                    $filename = basename($normalizedPath);
                    if ($filename !== '' && $filename !== '.' && $filename !== '..') {
                        $user['avatar_url'] = URL . '/media/serve?type=professores&key=' . rawurlencode($filename);
                    }
                } elseif (preg_match('/s3\.amazonaws\.com|s3\.[a-z0-9\-]+\.amazonaws\.com/i', $avatarUrl)) {
                    // URL antiga do S3: reescrever para /media/serve (servir do local após migração)
                    $pathOnly = is_string($pathOnly) ? trim($pathOnly, '/') : '';
                    $segments = $pathOnly !== '' ? explode('/', $pathOnly) : [];
                    $filename = $pathOnly !== '' ? basename($pathOnly) : '';
                    $tenant = null;
                    if (count($segments) >= 3 && $segments[1] === 'professores') {
                        $tenant = $segments[0];
                    }
                    if ($filename !== '' && $filename !== '.' && $filename !== '..') {
                        $user['avatar_url'] = URL . '/media/serve?type=professores&key=' . rawurlencode($filename)
                            . ($tenant !== null ? '&tenant=' . rawurlencode($tenant) : '');
                    } else {
                        $user['avatar_url'] = $avatarUrl;
                    }
                } else {
                    $user['avatar_url'] = $avatarUrl;
                }
            }
        }
    }
    ?>
    <meta name="csrf-token" content="<?= htmlspecialchars($csrf_token) ?>">
    <style>
        <?= LayoutHelper::generateCustomCSS() ?>
        
        /* Forçar background branco para professor */
        .page-background {
            background-color: #ffffff !important;
        }
        
        /* Remover qualquer fundo rosa */
        body {
            background-color: #ffffff !important;
        }
        
        main {
            background-color: #ffffff !important;
        }
        
        /* Mobile e PWA Styles */
        @media (max-width: 768px) {
            /* Sidebar mobile - overlay */
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
            
            /* Overlay para fechar sidebar */
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
            
            /* Header mobile */
            header {
                padding: 1rem !important;
            }
            
            header h1 {
                font-size: 1.25rem !important;
            }
            
            header p {
                font-size: 0.75rem !important;
            }
            
            /* Conteúdo mobile */
            main > div {
                padding: 1rem !important;
            }
            
            /* Botão flutuante mobile */
            .fixed.bottom-6.right-6 {
                bottom: 1rem !important;
                right: 1rem !important;
                padding: 0.75rem !important;
            }
            
            .fixed.bottom-6.right-6 span {
                display: none;
            }
            
            .fixed.bottom-6.right-6 svg {
                width: 1.5rem !important;
                height: 1.5rem !important;
            }
        }
        
        /* PWA - Safe area para iPhone */
        @supports (padding: max(0px)) {
            body {
                padding-left: env(safe-area-inset-left);
                padding-right: env(safe-area-inset-right);
            }
            
            #sidebar {
                padding-top: env(safe-area-inset-top);
            }
        }
    </style>
    <?php include __DIR__ . '/components/form_control_safari.php'; ?>
    <?php if (isset($additional_css)): ?>
        <?= $additional_css ?>
    <?php endif; ?>
</head>
<body class="page-background min-h-screen font-sans antialiased flex flex-col">
    <!-- Overlay para mobile sidebar -->
    <div id="sidebar-overlay" class="sidebar-overlay"></div>
    
    <!-- Sidebar Navigation -->
    <div class="flex flex-1 relative">
        <?php include __DIR__ . '/components/professor_sidebar.php'; ?>

        <!-- Main Content -->
        <main class="flex-1 w-full md:w-auto flex flex-col">
            <div class="plataforma-pagina plataforma-pagina-tela">
            <!-- Header com Notificações -->
            <header class="bg-white shadow-sm border-b border-gray-200 px-4 md:px-6 py-3 md:py-4 sticky top-0 z-30 shrink-0">
                <div class="flex justify-between items-center gap-2">
                    <!-- Botão menu mobile -->
                    <button id="mobileMenuToggle" class="md:hidden p-2 text-gray-600 hover:bg-gray-100 rounded-lg transition-colors">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    
                    <div class="flex-1 min-w-0">
                        <h1 class="text-lg md:text-2xl font-bold text-gray-900 truncate"><?= htmlspecialchars($page_title ?? 'Dashboard Professor') ?></h1>
                        <p class="text-xs md:text-sm text-gray-600 truncate">Bem-vindo, Professor <?= htmlspecialchars($user['nome'] ?? '') ?>!</p>
                    </div>
                    <div class="flex items-center space-x-2 md:space-x-4 flex-shrink-0">
                        <?php
                        $mostrarCreditosTopo = (bool) (
                            LayoutHelper::get('creditos_habilitado', '0') === '1'
                            && empty($preview)
                            && isset($user['id'])
                            && ($user['tipo'] ?? '') === 'professor'
                        );
                        $teacherSaldoCreditosTopo = null;
                        if ($mostrarCreditosTopo) {
                            try {
                                require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
                                $uidTopo = (int) $user['id'];
                                $creditosSvcPath = __DIR__ . '/../../Services/CreditosService.php';
                                if (is_file($creditosSvcPath)) {
                                    require_once $creditosSvcPath;
                                    $dbCredTopo = Database::getInstance();
                                    $professorCarteira = $dbCredTopo->fetch(
                                        'SELECT id FROM professores WHERE id = ? AND ativo = 1 LIMIT 1',
                                        [$uidTopo]
                                    );
                                    if (!$professorCarteira && !empty($user['codigo_prof'])) {
                                        $professorCarteira = $dbCredTopo->fetch(
                                            'SELECT id FROM professores WHERE codigo_prof = ? AND ativo = 1 LIMIT 1',
                                            [$user['codigo_prof']]
                                        );
                                    }
                                    if ($professorCarteira) {
                                        $uidTopo = (int) $professorCarteira['id'];
                                    }
                                    $svcCredTopo = new \App\Services\CreditosService();
                                    $svcCredTopo->aplicarRecargaInicialSeAplicavel('professor', $uidTopo);
                                    $teacherSaldoCreditosTopo = $svcCredTopo->getSaldo('professor', $uidTopo);
                                } else {
                                    $dbCredTopo = Database::getInstance();
                                    $rCredTopo = $dbCredTopo->fetch(
                                        'SELECT saldo FROM carteira_usuarios WHERE user_type = ? AND user_id = ? LIMIT 1',
                                        ['professor', $uidTopo]
                                    );
                                    $teacherSaldoCreditosTopo = CreditosDecimalHelper::fromScalar(
                                        is_array($rCredTopo) ? ($rCredTopo['saldo'] ?? 0) : 0,
                                        0.0
                                    );
                                }
                            } catch (Throwable $e) {
                                $teacherSaldoCreditosTopo = null;
                            }
                        }
                        ?>
                        <!-- Notificações -->
                        <?php 
                        // Buscar notificações não lidas
                        $notificacoesNaoLidas = 0;
                        $notificacoesRecentes = [];
                        $usuario = $user ?? null;
                        $tipoUsuario = 'professor';
                        
                        if (isset($user['id'])) {
                            try {
                                require_once __DIR__ . '/../../Models/Notifications/Notification.php';
                                $notificacaoModel = new Notification();
                                $notificacoesNaoLidas = $notificacaoModel->getNaoLidas($user['id'], 'professor');
                                $notificacoesRecentes = $notificacaoModel->getByDestinatario($user['id'], 'professor', 5);
                            } catch (Exception $e) {
                                // Se houver erro, usar valores padrão
                                $notificacoesNaoLidas = 0;
                                $notificacoesRecentes = [];
                            }
                        }
                        
                        include __DIR__ . '/components/notification-banner.php';
                        ?>

                        <?php if ($teacherSaldoCreditosTopo !== null): ?>
                        <a href="<?= URL ?>/professor/carteira" class="flex items-center gap-1.5 shrink-0 px-2.5 py-1.5 rounded-lg border border-amber-200 bg-amber-50 text-amber-950 hover:bg-amber-100 transition-colors focus:outline-none focus:ring-2 focus:ring-amber-400 focus:ring-offset-1" title="Minha carteira de créditos">
                            <svg class="w-4 h-4 text-amber-700 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span class="text-xs font-semibold tabular-nums leading-none"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay((float) $teacherSaldoCreditosTopo)) ?></span>
                            <span class="hidden sm:inline text-xs text-amber-800/90 font-medium leading-none">créditos</span>
                        </a>
                        <?php endif; ?>
                        
                        <!-- Avatar do usuário -->
                        <div class="flex items-center space-x-2 md:space-x-3">
                            <div class="w-8 h-8 bg-purple-500 rounded-full flex items-center justify-center text-white text-sm font-medium overflow-hidden">
                                <?php if (!empty($user['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" width="32" height="32" class="w-full h-full object-cover rounded-full" style="width:32px;height:32px;object-fit:cover;border-radius:50%" onerror="if(!this.dataset.avatarFallback){this.dataset.avatarFallback='1';this.src=this.src.replace('/public/uploads/','/uploads/');}">
                                <?php else: ?>
                                    <?= strtoupper(substr($user['nome'] ?? 'P', 0, 1)) ?>
                                <?php endif; ?>
                            </div>
                            <span class="hidden md:inline text-sm font-medium text-gray-700"><?= htmlspecialchars($user['nome'] ?? 'Professor') ?></span>
                        </div>
                    </div>
                </div>
            </header>
            
            <div class="p-4 md:p-6 plataforma-conteudo">
                <?= $content ?>
            </div>
            <?php include __DIR__ . '/components/plataforma_footer.php'; ?>
            </div>
        </main>
    </div>

    <?php
    $needsConsent = false;
    if (!empty($user['id'])) {
        if (!isset($db) || $db === null) {
            require_once __DIR__ . '/../../Core/Database.php';
            $db = Database::getInstance();
        }
        $docs = ['terms', 'privacy'];
        $version = 'v1.0';
        foreach ($docs as $doc) {
            $row = $db->fetch(
                "SELECT id FROM usuarios_consentimentos 
                 WHERE user_id = :user_id AND user_role = 'teacher'
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

    <!-- Botão Flutuante WhatsApp Suporte -->
    <a href="https://wa.me/5516997360690" 
       target="_blank" 
       rel="noopener noreferrer"
       class="fixed bottom-4 md:bottom-6 right-4 md:right-6 bg-green-500 hover:bg-green-600 text-white p-3 md:px-4 md:py-3 rounded-full shadow-lg flex items-center space-x-2 z-50 transition-all duration-300 hover:scale-110">
        <svg class="w-6 h-6" fill="currentColor" viewBox="0 0 24 24">
            <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413Z"/>
        </svg>
        <span class="hidden md:inline font-semibold">Suporte</span>
    </a>

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
        
        // Mobile Menu Toggle
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
            
            // Fechar ao clicar em link no mobile
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
        const apiUrl = '<?= URL ?>/professor/api/alunos-online';
        
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
            carregarAlunosOnline();
            setInterval(carregarAlunosOnline, 30000);
            return;
        }

        const streamUrl = '<?= URL ?>/professor/api/alunos-online/stream';
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

    <!-- Modal Trocar Foto -->
    <div id="modalTrocarFoto" class="hidden fixed inset-0 bg-black bg-opacity-50 z-[9999] flex items-center justify-center p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div class="bg-gradient-to-r from-purple-500 to-purple-600 px-6 py-4 flex justify-between items-center rounded-t-xl">
                <h3 class="text-xl font-bold text-white">Trocar Foto de Perfil</h3>
                <button onclick="fecharModalTrocarFoto()" class="text-white hover:text-gray-200 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="p-6">
                <form id="formTrocarFoto" enctype="multipart/form-data">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
                    
                    <div class="mb-6 text-center">
                        <div class="relative inline-block">
                            <div id="previewFoto" class="w-32 h-32 bg-gray-200 rounded-full mx-auto mb-4 overflow-hidden flex items-center justify-center">
                                <?php if (!empty($user['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="Foto atual" class="w-full h-full object-cover rounded-full" id="imgPreview" onerror="if(!this.dataset.avatarFallback){this.dataset.avatarFallback='1';this.src=this.src.replace('/public/uploads/','/uploads/');}">
                                <?php else: ?>
                                    <span class="text-gray-500 font-semibold text-2xl"><?= strtoupper(substr($user['nome'] ?? 'P', 0, 2)) ?></span>
                                <?php endif; ?>
                            </div>
                            <label for="inputFoto" class="absolute bottom-0 right-0 bg-purple-500 text-white rounded-full p-2 cursor-pointer hover:bg-purple-600 transition-colors">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                </svg>
                            </label>
                            <input type="file" id="inputFoto" name="foto" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" onchange="previewFoto(this)">
                        </div>
                        <p class="text-sm text-gray-500 mt-2">Clique no ícone para escolher uma foto</p>
                        <p class="text-xs text-gray-400 mt-1">Formatos: JPG, PNG, GIF, WEBP (máx. 5MB)</p>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="button" onclick="fecharModalTrocarFoto()" class="flex-1 px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" class="flex-1 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 transition-colors">
                            Salvar Foto
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
    // Funções para modal de trocar foto
    function abrirModalTrocarFoto() {
        const modal = document.getElementById('modalTrocarFoto');
        if (modal) {
            modal.classList.remove('hidden');
        } else {
            console.error('Modal não encontrado');
        }
    }
    
    function fecharModalTrocarFoto() {
        const modal = document.getElementById('modalTrocarFoto');
        if (modal) {
            modal.classList.add('hidden');
            const form = document.getElementById('formTrocarFoto');
            if (form) {
                form.reset();
            }
            // Restaurar preview original
            const preview = document.getElementById('previewFoto');
            const imgPreview = document.getElementById('imgPreview');
            if (preview && imgPreview) {
                const originalSrc = imgPreview.getAttribute('data-original-src');
                if (originalSrc) {
                    imgPreview.src = originalSrc;
                } else {
                    // Se não tinha imagem, restaurar iniciais
                    const initials = '<?= strtoupper(substr($user['nome'] ?? 'P', 0, 2)) ?>';
                    preview.innerHTML = '<span class="text-gray-500 font-semibold text-2xl">' + initials + '</span>';
                }
            }
        }
    }
    
    function previewFoto(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.getElementById('previewFoto');
                const imgPreview = document.getElementById('imgPreview');
                if (imgPreview) {
                    // Salvar src original se existir
                    if (!imgPreview.getAttribute('data-original-src')) {
                        imgPreview.setAttribute('data-original-src', imgPreview.src);
                    }
                }
                if (preview) {
                    preview.innerHTML = '<img src="' + e.target.result + '" alt="Preview" class="w-full h-full object-cover rounded-full" id="imgPreview" data-original-src="' + (imgPreview ? imgPreview.getAttribute('data-original-src') || '' : '') + '">';
                }
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    // Inicializar quando o DOM estiver pronto
    document.addEventListener('DOMContentLoaded', function() {
        // Adicionar event listener ao botão de trocar foto
        const btnTrocarFoto = document.getElementById('btnTrocarFoto');
        if (btnTrocarFoto) {
            btnTrocarFoto.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                abrirModalTrocarFoto();
            });
        }
        
        const formTrocarFoto = document.getElementById('formTrocarFoto');
        if (formTrocarFoto) {
            formTrocarFoto.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData(this);
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.textContent;
                submitBtn.disabled = true;
                submitBtn.textContent = 'Salvando...';
                
                try {
                    const response = await fetch('<?= URL ?>/professor/upload-foto-perfil', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        // Atualizar avatar no sidebar
                        const avatarContainer = document.querySelector('.sidebar-user-info .w-10.h-10');
                        if (avatarContainer) {
                            avatarContainer.innerHTML = '<img src="' + result.url + '" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" class="w-full h-full object-cover rounded-full" onerror="if(!this.dataset.avatarFallback){this.dataset.avatarFallback=\'1\';this.src=this.src.replace(\'/public/uploads/\',\'/uploads/\');}">';
                        }
                        
                        // Atualizar avatar no header
                        const headerAvatar = document.querySelector('header .w-8.h-8');
                        if (headerAvatar) {
                            headerAvatar.innerHTML = '<img src="' + result.url + '" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" class="w-full h-full object-cover rounded-full" onerror="if(!this.dataset.avatarFallback){this.dataset.avatarFallback=\'1\';this.src=this.src.replace(\'/public/uploads/\',\'/uploads/\');}">';
                        }
                        
                        alert('Foto de perfil atualizada com sucesso!');
                        fecharModalTrocarFoto();
                        location.reload(); // Recarregar para atualizar em todos os lugares
                    } else {
                        alert('Erro: ' + (result.error || 'Erro ao atualizar foto'));
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText;
                    }
                } catch (error) {
                    console.error('Erro:', error);
                    alert('Erro ao atualizar foto de perfil');
                    submitBtn.disabled = false;
                    submitBtn.textContent = originalText;
                }
            });
        }
        
        // Fechar modal ao clicar fora
        const modalTrocarFoto = document.getElementById('modalTrocarFoto');
        if (modalTrocarFoto) {
            modalTrocarFoto.addEventListener('click', function(e) {
                if (e.target === this) {
                    fecharModalTrocarFoto();
                }
            });
        }
        
        // Salvar src original da imagem se existir
        const imgPreview = document.getElementById('imgPreview');
        if (imgPreview && imgPreview.src && !imgPreview.getAttribute('data-original-src')) {
            imgPreview.setAttribute('data-original-src', imgPreview.src);
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
          inlineMath: [['$', '$'], ['\\(', '\\)']],
          displayMath: [['$$', '$$'], ['\\[', '\\]']]
        },
        svg: { fontCache: 'global' }
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
