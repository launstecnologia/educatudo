<?php
// Garantir que o user tem avatar_url se não foi passado
if (isset($user) && empty($user['avatar_url'])) {
    require_once __DIR__ . '/../../../Core/Database.php';
    require_once __DIR__ . '/../../../Core/AuthManager.php';
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
            } else {
                $user['avatar_url'] = $avatarUrl;
            }
        }
    }
}
?>
<aside id="sidebar" class="w-64 md:w-64 sidebar-custom shadow-2xl min-h-screen transition-all duration-300 ease-in-out fixed md:relative md:left-0 left-0 z-50">
    <div class="p-6">
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
                    <?php
                    $logoSidebar = LayoutHelper::getContextualLogo('sidebar', 'max-h-full w-auto', 'Logo');
                    if ($logoSidebar): ?>
                        <?= $logoSidebar ?>
                        <div class="sidebar-logo-fallback hidden">
                            <h1 class="text-xl font-bold text-white sidebar-text"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                            <p class="text-sm text-purple-100 sidebar-text">Painel do Professor</p>
                        </div>
                    <?php else: ?>
                        <h1 class="text-xl font-bold text-white sidebar-text"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                        <p class="text-sm text-purple-100 sidebar-text">Painel do Professor</p>
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
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 mb-6 sidebar-user-info">
            <div class="flex items-center">
                <div class="relative group">
                    <div id="btnTrocarFoto" class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3 overflow-hidden cursor-pointer hover:bg-white/30 transition-colors">
                    <?php if (!empty($user['avatar_url'])): ?>
                            <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" class="w-full h-full object-cover rounded-full pointer-events-none" onerror="if(!this.dataset.avatarFallback){this.dataset.avatarFallback='1';this.src=this.src.replace('/public/uploads/','/uploads/');}">
                    <?php else: ?>
                            <span class="text-white font-semibold text-sm pointer-events-none"><?= strtoupper(substr($user['nome'] ?? '', 0, 2)) ?></span>
                    <?php endif; ?>
                    </div>
                    <div class="absolute bottom-0 right-3 w-4 h-4 bg-purple-500 rounded-full border-2 border-white flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity">
                        <svg class="w-2.5 h-2.5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 13a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="sidebar-text-container">
                    <p class="text-sm font-medium text-white sidebar-text"><?= htmlspecialchars($user['nome'] ?? '') ?></p>
                    <p class="text-xs text-purple-100 sidebar-text">Professor</p>
                </div>
            </div>
        </div>
        
        <?php
        // Verificar módulos do professor
        require_once __DIR__ . '/../../../Core/LayoutHelper.php';
        $modulosProfessor = [
            'professor_alunos' => LayoutHelper::get('module_professor_alunos', '1') === '1',
            'professor_planos_aula' => LayoutHelper::get('module_professor_planos_aula', '1') === '1',
            'professor_jornadas' => LayoutHelper::get('module_professor_jornadas', '1') === '1',
            'professor_provas' => LayoutHelper::get('module_professor_provas', '1') === '1',
            'professor_redacao_configuravel' => LayoutHelper::get('module_redacao_configuravel', '1') === '1' && LayoutHelper::get('module_professor_redacao_configuravel', '1') === '1',
            'professor_redacao_livre' => LayoutHelper::get('module_professor_redacao_livre', '0') === '1',
            'professor_gerar_slides' => LayoutHelper::isModuleEnabled('professor_gerar_slides'),
            'professor_ai_agents' => LayoutHelper::isModuleEnabled('professor_ai_agents'),
            'professor_notifications' => LayoutHelper::get('module_professor_notifications', '1') === '1',
            'professor_arquivos' => LayoutHelper::get('module_professor_arquivos', '1') === '1',
            'professor_apostilas' => LayoutHelper::get('module_professor_apostilas', '1') === '1',
        ];

        $externalAppsLinks = json_decode(LayoutHelper::get('external_apps_links', '[]'), true);
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
            return rtrim($value, '/');
        };
        $professorExternalApps = [];
        foreach ($externalAppsLinks as $idx => $externalLink) {
            if (!is_array($externalLink)) {
                continue;
            }
            $showProfessor = !empty($externalLink['professor']);
            $externalId = (string) ($externalLink['id'] ?? $idx);
            $externalUrl = trim((string) ($externalLink['url'] ?? ''));
            $externalNome = trim((string) ($externalLink['nome'] ?? $externalLink['label'] ?? ''));
            if (!$showProfessor || $externalId === '' || $externalUrl === '' || $externalNome === '') {
                continue;
            }
            $normalizedApp = strtolower((string) ($externalLink['app'] ?? $externalId));
            $normalizedNome = strtolower($externalNome);
            $isEducaHitsApp = str_contains($normalizedApp, 'educahits')
                || str_contains($normalizedApp, 'educa-hits')
                || str_contains($normalizedApp, 'educa_hits')
                || str_contains($normalizedNome, 'educahits')
                || str_contains($normalizedNome, 'educa hits')
                || str_contains(strtolower($externalId), 'educahits');
            if ($isEducaHitsApp) {
                continue;
            }
            $professorExternalApps[] = [
                'id' => $externalId,
                'nome' => $externalNome,
                'href' => URL . '/external-apps/abrir/' . rawurlencode($externalId),
                'app' => $normalizedApp,
                'current_page' => preg_replace('/[^a-z0-9_-]+/i', '-', $normalizedApp) ?: $externalId,
            ];
        }
        if (empty($professorExternalApps)) {
            $professorExternalApps[] = [
                'id' => 'educaprof',
                'nome' => 'Educa Prof',
                'href' => URL . '/external-apps/abrir/educaprof',
                'app' => 'educaprof',
                'current_page' => 'educaprof',
            ];
        }
        ?>
        <nav class="space-y-1">
            <!-- Dashboard -->
            <a href="<?= URL ?>/professor/dashboard" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'dashboard' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                </svg>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <?php if (LayoutHelper::get('creditos_habilitado', '0') === '1'): ?>
            <a href="<?= URL ?>/professor/carteira" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'carteira' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7.5A2.5 2.5 0 015.5 5H18a2 2 0 012 2v2H6a2 2 0 00-2 2v6.5A2.5 2.5 0 016.5 20H18a2 2 0 002-2v-2H6a2 2 0 01-2-2V7.5z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 14h.01"></path>
                </svg>
                <span class="sidebar-text">Minha Carteira</span>
            </a>
            <a href="<?= URL ?>/professor/carteira/comprar" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'educashop' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h2l.4 2M7 13h10l4-8H6.4M7 13L5.4 6M7 13l-1.2 4.2A1 1 0 006.76 18h10.48a1 1 0 00.96-.73L19 13M10 21a1 1 0 100-2 1 1 0 000 2zm8 0a1 1 0 100-2 1 1 0 000 2z"></path>
                </svg>
                <span class="sidebar-text">EducaShop</span>
            </a>
            <?php foreach ($professorExternalApps as $externalApp): ?>
            <?php
                $isEducaProf = $externalApp['app'] === 'educaprof';
                $isCurrentExternal = ($current_page ?? '') === $externalApp['current_page'] || (($current_page ?? '') === 'educaprof' && $isEducaProf);
            ?>
            <a href="<?= htmlspecialchars($externalApp['href']) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center px-4 py-3 <?= $isCurrentExternal ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                <?php if ($isEducaProf): ?>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422A12.083 12.083 0 0112 20.055a12.083 12.083 0 01-6.16-9.477L12 14z"></path>
                </svg>
                <?php else: ?>
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 015.656 0l1.414 1.414a4 4 0 010 5.656l-1.414 1.414a4 4 0 01-5.656 0M10.172 13.828a4 4 0 01-5.656 0L3.102 12.414a4 4 0 010-5.656L4.516 5.344a4 4 0 015.656 0"></path>
                </svg>
                <?php endif; ?>
                <span class="sidebar-text"><?= htmlspecialchars($externalApp['nome']) ?></span>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- AVA / EAD -->
            <?php require_once __DIR__ . '/../../../Core/FeatureGate.php'; if (FeatureGate::isModuleEnabled('ead')): ?>
            <a href="<?= URL ?>/professor/ava"
               class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'ava' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"></path>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422A12.083 12.083 0 0112 21.5a12.083 12.083 0 01-6.16-10.922L12 14z"></path>
                </svg>
                <span class="sidebar-text">AVA / EAD</span>
            </a>
            <?php endif; ?>

            <!-- Alunos -->
            <?php $alunosEnabled = $modulosProfessor['professor_alunos']; ?>
            <a href="<?= $alunosEnabled ? URL . '/professor/student' : '#' ?>" 
               onclick="<?= $alunosEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Alunos\'); return false;' ?>"
               class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'student' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 <?= !$alunosEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <span class="sidebar-text">Alunos</span>
            </a>
            
            <!-- EducaTudo (Menu Expansível) -->
            <?php 
            require_once __DIR__ . '/../../../Core/LayoutHelper.php';
            $menuColagNome = LayoutHelper::get('menu_colag_nome', 'Colag');
            ?>
            <div class="menu-group">
                <button type="button" onclick="toggleMenuGroup('academico')" class="w-full flex items-center justify-between px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200 hover:scale-105">
                    <div class="flex items-center">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <span class="sidebar-text"><?= htmlspecialchars($menuColagNome) ?></span>
                    </div>
                    <svg id="academico-arrow" class="w-5 h-5 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="academico-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <?php if (LayoutHelper::isModuleEnabled('expo_colag')): ?>
                    <?php $expoColagEnabled = LayoutHelper::isModuleEnabled('expo_colag'); ?>
                    <a href="<?= $expoColagEnabled ? URL . '/professor/expo-colag' : '#' ?>"
                       onclick="<?= $expoColagEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Expo Colag\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'expo-colag' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$expoColagEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Expo Colag</span>
                    </a>
                    <?php endif; ?>

                    <!-- Planos de Aula -->
                    <?php $planosAulaEnabled = $modulosProfessor['professor_planos_aula']; ?>
                    <a href="<?= $planosAulaEnabled ? URL . '/professor/planos-aula' : '#' ?>" 
                       onclick="<?= $planosAulaEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Planos de Aula\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'planos-aula' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$planosAulaEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Planos de Aula</span>
                    </a>

                    <!-- Diários de Classe -->
                    <?php
                    require_once __DIR__ . '/../../../Core/FeatureGate.php';
                    if (FeatureGate::isModuleEnabled('diario_classe')):
                    ?>
                    <a href="<?= URL ?>/professor/diarios"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'diarios_classe' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Diários de Classe</span>
                    </a>
                    <?php endif; ?>
                    <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conselho_classe')): ?>
                    <a href="<?= URL ?>/professor/conselhos"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'conselho_classe' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Conselho de Classe</span>
                    </a>
                    <?php endif; ?>

                    <!-- Jornadas do Aluno -->
                    <?php 
                    require_once __DIR__ . '/../../../Core/FeatureGate.php';
                    $jornadasEnabled = FeatureGate::isModuleEnabled('jornadas') && $modulosProfessor['professor_jornadas'];
                    ?>
                    <?php if (FeatureGate::isModuleEnabled('jornadas')): ?>
                    <a href="<?= $jornadasEnabled ? URL . '/professor/jornadas' : '#' ?>" 
                       onclick="<?= $jornadasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Jornada do Aluno\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'jornadas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$jornadasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Jornada do Aluno</span>
                    </a>
                    <a href="<?= $jornadasEnabled ? URL . '/professor/jornadas/relatorio' : '#' ?>"
                       onclick="<?= $jornadasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Jornada do Aluno\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'jornadas_relatorio' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$jornadasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Relatório de jornadas</span>
                    </a>
                    <?php endif; ?>
                    
                    <!-- Provas Online -->
                    <?php $provasEnabled = $modulosProfessor['professor_provas']; ?>
                    <a href="<?= $provasEnabled ? URL . '/professor/provas' : '#' ?>" 
                       onclick="<?= $provasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Provas Online\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'provas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$provasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Provas Online</span>
                    </a>
                    <a href="<?= $provasEnabled ? URL . '/professor/provas-bimestral' : '#' ?>" 
                       onclick="<?= $provasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Provas Bimestral\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'provas_bimestral' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$provasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Provas Bimestral</span>
                    </a>

                    <a href="<?= URL ?>/professor/questoes"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'questoes' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9h8M8 13h6m-6 4h8M5 5h14a2 2 0 012 2v10a2 2 0 01-2 2H7l-4 3V7a2 2 0 012-2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Questões</span>
                    </a>
                    
                    <!-- Módulo de Arquivos -->
                    <?php $arquivosEnabled = $modulosProfessor['professor_arquivos']; ?>
                    <a href="<?= $arquivosEnabled ? URL . '/professor/arquivos' : '#' ?>"
                       onclick="<?= $arquivosEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Arquivos\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'arquivos' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$arquivosEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Arquivos</span>
                    </a>

                    <!-- Meu Material (módulo novo, sem feature flag dedicada ainda - ver FeatureGate.php) -->
                    <a href="<?= URL ?>/professor/apostilas-ia"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'apostilas-ia' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Meu Material</span>
                    </a>

                    <!-- Apostilas -->
                    <?php $apostilasEnabled = $modulosProfessor['professor_apostilas']; ?>
                    <a href="<?= $apostilasEnabled ? URL . '/professor/apostilas' : '#' ?>"
                       onclick="<?= $apostilasEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Minha Apostila\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'apostilas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$apostilasEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Minha Apostila</span>
                    </a>

                    <!-- Propostas de Redação (Redação Configurável) -->
                    <?php $redacaoConfigEnabled = $modulosProfessor['professor_redacao_configuravel']; ?>
                    <details class="sidebar-nav-item" <?= in_array(($current_page ?? ''), ['essays', 'essays-report'], true) ? 'open' : '' ?>>
                        <summary class="flex items-center px-4 py-2 <?= in_array(($current_page ?? ''), ['essays', 'essays-report'], true) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 list-none cursor-pointer <?= !$redacaoConfigEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>"
                            <?= !$redacaoConfigEnabled ? 'onclick="event.preventDefault(); mostrarModalModuloDesabilitado(\'Propostas de Redação\'); return false;"' : '' ?>>
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                            </svg>
                            <span class="sidebar-text text-sm flex-1">Propostas de Redação</span>
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </summary>
                        <div class="mt-1 ml-4 border-l border-white/20 space-y-1">
                            <a href="<?= $redacaoConfigEnabled ? URL . '/professor/redacao-configuravel' : '#' ?>"
                               onclick="<?= $redacaoConfigEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Propostas de Redação\'); return false;' ?>"
                               class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'essays' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm <?= !$redacaoConfigEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                <span class="sidebar-text text-sm">Listagem</span>
                            </a>
                            <a href="<?= $redacaoConfigEnabled ? URL . '/professor/redacao-configuravel/relatorio' : '#' ?>"
                               onclick="<?= $redacaoConfigEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Relatório de Redações\'); return false;' ?>"
                               class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'essays-report' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm <?= !$redacaoConfigEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                                <span class="sidebar-text text-sm">Relatório</span>
                            </a>
                        </div>
                    </details>

                    <!-- Redação Livre -->
                    <?php $redacaoLivreEnabled = $modulosProfessor['professor_redacao_livre']; ?>
                    <a href="<?= $redacaoLivreEnabled ? URL . '/professor/redacao-livre' : '#' ?>"
                       onclick="<?= $redacaoLivreEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Redação Livre\'); return false;' ?>"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'redacao-livre' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 <?= !$redacaoLivreEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Redação Livre</span>
                    </a>

                    <!-- Mural de Recados -->
                    <a href="<?= URL ?>/professor/mural-recados"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'mural-recados' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Mural de Recados</span>
                    </a>

                    <?php if (LayoutHelper::isModuleVisible('professor_links_uteis')): ?>
                    <?php
                    $linksUteisProfessorEnabled = LayoutHelper::isModuleEnabled('professor_links_uteis');
                    $customMenuLinks = json_decode(LayoutHelper::get('menu_links_submenu', '[]'), true);
                    if (!is_array($customMenuLinks)) {
                        $customMenuLinks = [];
                    }
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
                    foreach ($customMenuLinks as $link) {
                        $label = trim($link['nome'] ?? $link['label'] ?? '');
                        $url = trim($link['url'] ?? '');
                        $showProfessor = !empty($link['professor']);
                        $openNewTab = !empty($link['nova_guia']);
                        if ($label === '' || $url === '' || !$showProfessor) {
                            continue;
                        }
                        $href = $linksUteisProfessorEnabled ? $url : '#';
                        if ($linksUteisProfessorEnabled) {
                            $externalGateway = $externalAppsRouteByUrl[$normalizeExternalUrl($url)] ?? '';
                            if ($externalGateway !== '') {
                                $href = $externalGateway;
                            }
                        }
                        if ($linksUteisProfessorEnabled && !preg_match('#^https?://#i', $href)) {
                            $href = URL . '/' . ltrim($href, '/');
                        }
                    ?>
                        <a href="<?= htmlspecialchars($href) ?>" <?= ($linksUteisProfessorEnabled && $openNewTab) ? 'target="_blank" rel="noopener noreferrer"' : '' ?>
                           onclick="<?= $linksUteisProfessorEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Links Úteis\'); return false;' ?>"
                           class="flex items-center px-4 py-2 text-purple-100 hover:bg-white/20 hover:text-white rounded-lg transition-all duration-200 <?= !$linksUteisProfessorEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 015.656 0l1.414 1.414a4 4 0 010 5.656l-1.414 1.414a4 4 0 01-5.656 0M10.172 13.828a4 4 0 01-5.656 0L3.102 12.414a4 4 0 010-5.656L4.516 5.344a4 4 0 015.656 0"></path>
                            </svg>
                            <span class="sidebar-text text-sm"><?= htmlspecialchars($label) ?></span>
                        </a>
                    <?php } ?>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Gerar Slides -->
            <?php if (LayoutHelper::isModuleVisible('professor_gerar_slides')): ?>
            <?php $gerarSlidesEnabled = $modulosProfessor['professor_gerar_slides']; ?>
            <a href="<?= $gerarSlidesEnabled ? URL . '/professor/gerar-slides' : '#' ?>" 
               onclick="<?= $gerarSlidesEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Gerar Slides\'); return false;' ?>"
               class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'gerar-slides' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 <?= !$gerarSlidesEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"></path>
                </svg>
                <span class="sidebar-text">Gerar Slides</span>
            </a>
            <?php endif; ?>
            
            <!-- Tutoriais -->
            <a href="<?= URL ?>/professor/tutoriais" 
               class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'tutoriais' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path>
                </svg>
                <span class="sidebar-text">Tutoriais</span>
            </a>
            
            <!-- Notificações -->
            <?php $notificationsEnabled = $modulosProfessor['professor_notifications']; ?>
            <a href="<?= $notificationsEnabled ? URL . '/professor/notifications' : '#' ?>" 
               onclick="<?= $notificationsEnabled ? '' : 'event.preventDefault(); mostrarModalModuloDesabilitado(\'Notificações\'); return false;' ?>"
               class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'notifications' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 hover:scale-105 <?= !$notificationsEnabled ? 'opacity-50 cursor-not-allowed' : '' ?>">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span class="sidebar-text">Notificações</span>
            </a>
            
            <!-- Logout Button -->
            <a href="<?= URL ?>/logout?portal=professor" class="flex items-center px-4 py-3 text-red-200 hover:bg-red-500/20 hover:text-red-100 rounded-xl transition-all duration-200 hover:scale-105 mt-4">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span class="sidebar-text">Sair</span>
            </a>
        </nav>
    </div>
</aside>

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

/* Hide expand button when sidebar is expanded */
#sidebar:not(.collapsed) #expandSidebar {
    display: none;
}

/* Show expand button when sidebar is collapsed */
#sidebar.collapsed #expandSidebar {
    display: flex;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebar = document.getElementById('sidebar');
    const toggleButton = document.getElementById('sidebarToggle');
    const expandButton = document.getElementById('expandSidebar');
    
    // No mobile, desabilitar collapse/expand
    const isMobile = window.innerWidth <= 768;
    
    // No mobile, não aplicar collapsed
    if (!isMobile) {
        // Check if sidebar was previously collapsed
        const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (isCollapsed) {
            sidebar.classList.add('collapsed');
        }
    } else {
        // No mobile, garantir que não está collapsed
        sidebar.classList.remove('collapsed');
    }
    
    // Toggle sidebar function (apenas desktop)
    function toggleSidebar() {
        if (isMobile) return; // Não fazer nada no mobile
        
        sidebar.classList.toggle('collapsed');
        
        // Save state to localStorage
        const collapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', collapsed);
        
        // Update main content margin
        updateMainContentMargin(collapsed);
    }
    
    // Toggle sidebar (apenas desktop)
    if (toggleButton) {
        toggleButton.addEventListener('click', toggleSidebar);
    }
    
    // Expand sidebar (when collapsed) - apenas desktop
    if (expandButton) {
        expandButton.addEventListener('click', function() {
            if (isMobile) return; // Não fazer nada no mobile
            if (sidebar.classList.contains('collapsed')) {
                toggleSidebar();
            }
        });
    }
    
    // Update main content margin based on sidebar state
    function updateMainContentMargin(collapsed) {
        const mainContent = document.querySelector('main');
        if (mainContent) {
            // Remove any existing margin-left
            mainContent.style.marginLeft = '';
            
            // The flex layout already handles the spacing correctly
            // No need to add extra margins
        }
    }
    
    // Set initial margin (apenas desktop)
    if (!isMobile) {
        updateMainContentMargin(isCollapsed);
    }
    
    // Add tooltip data attributes to navigation links
    const navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(link => {
        const textElement = link.querySelector('.sidebar-text');
        if (textElement) {
            link.setAttribute('data-tooltip', textElement.textContent.trim());
        }
    });
});

// Função para toggle dos menus expansíveis
function toggleMenuGroup(groupId) {
    const submenu = document.getElementById(`${groupId}-submenu`);
    const arrow = document.getElementById(`${groupId}-arrow`);
    
    if (submenu) {
        const isHidden = submenu.classList.contains('hidden');
        
        if (isHidden) {
            submenu.classList.remove('hidden');
            if (arrow) {
                arrow.style.transform = 'rotate(180deg)';
            }
            localStorage.setItem(`menu-${groupId}-expanded`, 'true');
        } else {
            submenu.classList.add('hidden');
            if (arrow) {
                arrow.style.transform = 'rotate(0deg)';
            }
            localStorage.setItem(`menu-${groupId}-expanded`, 'false');
        }
    }
}

// Verificar estado salvo dos menus e expandir automaticamente se necessário
document.addEventListener('DOMContentLoaded', function() {
    const currentPage = '<?= $current_page ?? '' ?>';
    
    // Se estiver em uma das páginas do menu Acadêmico, expandir automaticamente
    if (['planos-aula', 'diarios_classe', 'conselho_classe', 'provas', 'jornadas', 'jornadas_relatorio', 'essays', 'arquivos', 'apostilas'].includes(currentPage)) {
        const wasExpanded = localStorage.getItem('menu-academico-expanded') === 'true';
        if (!wasExpanded) {
            toggleMenuGroup('academico');
        } else {
            // Apenas garantir que está visível
            const submenu = document.getElementById('academico-submenu');
            const arrow = document.getElementById('academico-arrow');
            if (submenu && !submenu.classList.contains('hidden')) {
                if (arrow) {
                    arrow.style.transform = 'rotate(180deg)';
                }
            }
        }
    }
});

// Função para mostrar modal quando módulo está desabilitado
function mostrarModalModuloDesabilitado(nomeModulo) {
    // Criar modal se não existir
    let modal = document.getElementById('modal-modulo-desabilitado');
    if (!modal) {
        modal = document.createElement('div');
        modal.id = 'modal-modulo-desabilitado';
        modal.className = 'fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center hidden';
        modal.innerHTML = `
            <div class="bg-white rounded-lg shadow-xl max-w-md w-full mx-4">
                <div class="p-6">
                    <div class="flex items-center mb-4">
                        <div class="flex-shrink-0 w-12 h-12 bg-yellow-100 rounded-full flex items-center justify-center">
                            <svg class="w-6 h-6 text-yellow-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                        </div>
                        <h3 class="ml-3 text-lg font-semibold text-gray-900">Funcionalidade Desativada</h3>
                    </div>
                    <div class="mb-4">
                        <p class="text-gray-700">
                            A funcionalidade <strong id="nome-modulo-modal">${nomeModulo}</strong> está desativada no momento.
                        </p>
                        <p class="text-gray-600 text-sm mt-2">
                            Entre em contato com o administrador do sistema para mais informações.
                        </p>
                    </div>
                    <div class="flex justify-end">
                        <button onclick="fecharModalModuloDesabilitado()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                            Entendi
                        </button>
                    </div>
                </div>
            </div>
        `;
        document.body.appendChild(modal);
    }
    
    // Atualizar nome do módulo no modal
    const nomeModuloElement = document.getElementById('nome-modulo-modal');
    if (nomeModuloElement) {
        nomeModuloElement.textContent = nomeModulo;
    }
    
    // Mostrar modal
    modal.classList.remove('hidden');
}

function fecharModalModuloDesabilitado() {
    const modal = document.getElementById('modal-modulo-desabilitado');
    if (modal) {
        modal.classList.add('hidden');
    }
}

// Fechar modal ao clicar fora
document.addEventListener('click', function(e) {
    const modal = document.getElementById('modal-modulo-desabilitado');
    if (modal && !modal.querySelector('.bg-white').contains(e.target) && !e.target.closest('a[onclick*="mostrarModalModuloDesabilitado"]')) {
        fecharModalModuloDesabilitado();
    }
});

// Função para escapar HTML
function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

</script>
