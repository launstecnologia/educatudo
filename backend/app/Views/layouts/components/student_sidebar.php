<aside id="sidebar" class="w-64 bg-gradient-to-b from-green-600 to-emerald-700 shadow-2xl h-screen fixed left-0 top-0 z-50 transition-all duration-300 ease-in-out">
    <div class="p-6">
        <!-- Logo e Toggle -->
        <div class="flex items-center justify-between mb-8 sidebar-header">
            <div class="flex items-center sidebar-logo-container">
                <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3">
                    <span class="text-white font-bold text-lg">🎓</span>
                </div>
                <div class="sidebar-text-container">
                    <h1 class="text-xl font-bold text-white">EducaTudo</h1>
                    <p class="text-sm text-green-100">Área do Aluno</p>
                </div>
            </div>
            <button id="sidebarToggle" class="text-white hover:bg-white/20 p-2 rounded-lg transition-colors sidebar-toggle">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
            <button id="expandSidebar" class="hidden text-white hover:bg-white/20 p-2 rounded-lg transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                </svg>
            </button>
        </div>
        
        <!-- User Info -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 mb-6 sidebar-user-info">
            <div class="flex items-center sidebar-user-info">
                <div class="w-12 h-12 bg-white/20 rounded-full flex items-center justify-center mr-3 sidebar-user-avatar overflow-hidden">
                    <?php
                    $sidebarAvatarUrl = $avatar_url ?? null;
                    if (empty($sidebarAvatarUrl) && isset($aluno['id'])) {
                        try {
                            require_once __DIR__ . '/../../../Core/ContextoAluno.php';
                            $sidebarAvatarUrl = ContextoAluno::getAvatarUrl(Database::getInstance());
                        } catch (Exception $e) {
                            $sidebarAvatarUrl = null;
                        }
                    }
                    if (!empty($sidebarAvatarUrl)): ?>
                        <img src="<?= htmlspecialchars($sidebarAvatarUrl) ?>" alt="Avatar" class="w-full h-full object-cover" onerror="if(!this.dataset.avatarFallback){this.dataset.avatarFallback='1';this.src=this.src.replace('/public/assets/','/assets/').replace('/public/uploads/','/uploads/');}">
                    <?php else: ?>
                        <span class="text-white font-semibold text-sm"><?= strtoupper(substr($aluno['nome_aluno'] ?? '', 0, 2)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="sidebar-user-details">
                    <p class="text-sm font-medium text-white"><?= htmlspecialchars($aluno['nome_aluno'] ?? 'Aluno') ?></p>
                    <p class="text-xs text-green-100"><?= htmlspecialchars($aluno['turma_nome'] ?? '') ?></p>
                </div>
            </div>
        </div>
        
        <nav class="space-y-1 sidebar-nav">
            <a href="<?= URL ?>/dashboard" class="flex items-center px-4 py-3 <?= $current_page === 'dashboard' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                </svg>
                <span class="sidebar-text">Dashboard</span>
            </a>
            
            <a href="<?= URL ?>/avatar" class="flex items-center px-4 py-3 <?= $current_page === 'avatar' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
                <span class="sidebar-text">Perfil</span>
            </a>
            
            <a href="<?= URL ?>/chat" class="flex items-center px-4 py-3 <?= $current_page === 'chat' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <span class="sidebar-text">Chat <?= htmlspecialchars(LayoutHelper::getIaName()) ?></span>
            </a>
            
            <a href="<?= URL ?>/exercicios-personalizados/criar" class="flex items-center px-4 py-3 <?= $current_page === 'exercises' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
                <span class="sidebar-text">Exercícios</span>
            </a>
            
            <a href="<?= URL ?>/redacoes" class="flex items-center px-4 py-3 <?= $current_page === 'essays' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <span class="sidebar-text">Redação</span>
            </a>
            
            <a href="<?= URL ?>/jogos" class="flex items-center px-4 py-3 <?= $current_page === 'jogos' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                <span class="sidebar-text">Jogos</span>
            </a>
            
            <a href="<?= URL ?>/jornadas" class="flex items-center px-4 py-3 <?= $current_page === 'journeys' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <span class="sidebar-text">Jornadas</span>
            </a>

            <?php require_once __DIR__ . '/../../../Core/FeatureGate.php'; if (FeatureGate::isModuleEnabled('ead')): ?>
            <a href="<?= URL ?>/cursos" class="flex items-center px-4 py-3 <?= $current_page === 'cursos' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422A12.083 12.083 0 0112 21.5a12.083 12.083 0 01-6.16-10.922L12 14z"></path>
                </svg>
                <span class="sidebar-text">Meus Cursos</span>
            </a>

            <a href="<?= URL ?>/cursos/agenda" class="flex items-center px-4 py-3 <?= $current_page === 'cursos-agenda' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                </svg>
                <span class="sidebar-text">Agenda</span>
            </a>
            <?php endif; ?>
            
            <a href="<?= URL ?>/simulados" class="flex items-center px-4 py-3 <?= $current_page === 'simulados' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                </svg>
                <span class="sidebar-text">Simulados</span>
            </a>
            
            <a href="<?= URL ?>/mural-recados" class="flex items-center px-4 py-3 <?= $current_page === 'mural-recados' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <span class="sidebar-text">Mural de Recados</span>
            </a>

            <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aulas_online')): ?>
            <a href="<?= URL ?>/aluno/aulas-online" class="flex items-center px-4 py-3 <?= $current_page === 'aulas_online' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14m-9 4h8a2 2 0 002-2V8a2 2 0 00-2-2H6a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                <span class="sidebar-text">Aulas Online</span>
            </a>
            <?php endif; ?>
            
            <a href="<?= URL ?>/aluno/apostilas-ia" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'apostilas-ia' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                </svg>
                <span class="sidebar-text">Meu Material</span>
            </a>

            <a href="<?= URL ?>/livros" class="flex items-center px-4 py-3 <?= $current_page === 'livros' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <span class="sidebar-text">Educa Livros</span>
            </a>

            <a href="<?= URL ?>/minicursos" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'minicursos' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <span class="sidebar-text">Minicursos</span>
            </a>

            <?php
            require_once __DIR__ . '/../../../Core/EducaHitsConfig.php';
            $educaHitsEnabled = LayoutHelper::isModuleEnabled('educa_hits');
            $ehEscutarUrl = URL . '/hits';
            $educahitsEscutarNovaGuiaFromApps = false;
            $rawExtAppsEh = json_decode((string) LayoutHelper::get('external_apps_links', '[]'), true);
            if (!is_array($rawExtAppsEh)) {
                $rawExtAppsEh = [];
            }
            foreach ($rawExtAppsEh as $idx => $linkEh) {
                if (!is_array($linkEh) || empty($linkEh['aluno'])) {
                    continue;
                }
                $nomeEh = trim((string) ($linkEh['nome'] ?? $linkEh['label'] ?? ''));
                $urlEh = trim((string) ($linkEh['url'] ?? ''));
                if ($nomeEh === '' || $urlEh === '') {
                    continue;
                }
                $lnEh = strtolower($nomeEh);
                $luEh = strtolower($urlEh);
                if ((strpos($lnEh, 'educahits') !== false || strpos($luEh, 'educahits') !== false) && !empty($linkEh['nova_guia'])) {
                    $educahitsEscutarNovaGuiaFromApps = true;
                    break;
                }
            }
            $ehPedirUrl = URL . '/hits/request';
            ?>
            <details class="sidebar-nav-item">
                <summary class="list-none flex items-center px-4 py-3 text-green-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200 cursor-pointer <?= !$educaHitsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>" <?= !$educaHitsEnabled ? 'onclick="event.preventDefault(); typeof mostrarModalModuloDesabilitado === \'function\' && mostrarModalModuloDesabilitado(\'EducaHits\'); return false;"' : '' ?>>
                    <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                    </svg>
                    <span class="sidebar-text">EducaHits</span>
                </summary>
                <a href="<?= $educaHitsEnabled ? htmlspecialchars($ehEscutarUrl) : '#' ?>" <?= $educaHitsEnabled ? 'target="_blank" rel="noopener noreferrer"' : '' ?> <?= !$educaHitsEnabled ? 'onclick="event.preventDefault(); typeof mostrarModalModuloDesabilitado === \'function\' && mostrarModalModuloDesabilitado(\'EducaHits\'); return false;"' : '' ?> class="flex items-center px-4 py-2 ml-4 text-green-200/90 hover:bg-white/15 hover:text-white rounded-lg transition-all duration-200 text-sm border-l border-white/20 sidebar-nav-item <?= !$educaHitsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    <span class="sidebar-text text-sm pl-2">Escutar música</span>
                </a>
                <a href="<?= $educaHitsEnabled ? htmlspecialchars($ehPedirUrl) : '#' ?>" <?= !$educaHitsEnabled ? 'onclick="event.preventDefault(); typeof mostrarModalModuloDesabilitado === \'function\' && mostrarModalModuloDesabilitado(\'EducaHits\'); return false;"' : '' ?> class="flex items-center px-4 py-2 ml-4 text-green-200/90 hover:bg-white/15 hover:text-white rounded-lg transition-all duration-200 text-sm border-l border-white/20 sidebar-nav-item <?= !$educaHitsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                    <span class="sidebar-text text-sm pl-2">Pedir músicas</span>
                </a>
            </details>

            <a href="<?= URL ?>/caderno" class="flex items-center px-4 py-3 <?= $current_page === 'caderno' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                </svg>
                <span class="sidebar-text">Meu Caderno</span>
            </a>
            <a href="<?= URL ?>/notes/access" target="_blank" rel="noopener noreferrer" class="notes-open-new-tab flex items-center px-4 py-3 <?= $current_page === 'notes' ? 'text-white bg-white/20' : 'text-green-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 sidebar-nav-item" data-notes-url="<?= htmlspecialchars(URL . '/notes/access-url') ?>">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span class="sidebar-text">Meu Caderno</span>
            </a>
            
            <!-- Logout Button -->
            <a href="<?= URL ?>/logout?portal=aluno" class="flex items-center px-4 py-3 text-red-200 hover:bg-red-500/20 hover:text-red-100 rounded-xl transition-all duration-200 hover:scale-105 mt-4 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 sidebar-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span class="sidebar-text">Sair</span>
            </a>
        </nav>
    </div>
</aside>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const expandSidebar = document.getElementById('expandSidebar');
    
    // Verificar estado salvo
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed) {
        sidebar.classList.add('sidebar-collapsed');
        sidebarToggle.style.display = 'none';
        expandSidebar.style.display = 'block';
    }
    
    // Toggle sidebar
    function toggleSidebar() {
        sidebar.classList.toggle('sidebar-collapsed');
        const isCollapsed = sidebar.classList.contains('sidebar-collapsed');
        localStorage.setItem('sidebarCollapsed', isCollapsed);
        
        if (isCollapsed) {
            sidebarToggle.style.display = 'none';
            expandSidebar.style.display = 'block';
        } else {
            sidebarToggle.style.display = 'block';
            expandSidebar.style.display = 'none';
        }
    }
    
    // Event listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', toggleSidebar);
    }
    
    if (expandSidebar) {
        expandSidebar.addEventListener('click', toggleSidebar);
    }
});
</script>
