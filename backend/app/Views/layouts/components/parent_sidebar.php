<?php
$filhos = $filhos ?? [];
$filho = $filho ?? null;
$current_page = $current_page ?? 'dashboard';
$filhoId = $filho['id'] ?? null;
require_once __DIR__ . '/../../../Core/LayoutHelper.php';
require_once __DIR__ . '/../../../Core/FeatureGate.php';
?>
<aside id="sidebar" class="w-64 md:w-64 sidebar-custom shadow-2xl min-h-screen transition-all duration-300 ease-in-out fixed md:relative md:left-0 left-0 z-50">
    <div class="p-6">
        <!-- Logo e Toggle -->
        <div class="flex items-center justify-between mb-6 sidebar-header">
            <div class="flex items-center sidebar-logo-container">
                <div class="w-10 h-10 bg-white/20 backdrop-blur-sm rounded-xl flex items-center justify-center mr-3 sidebar-icon overflow-hidden flex-shrink-0">
                    <?php if (LayoutHelper::getContextualLogo('favicon', 'w-full h-full object-contain p-1', 'Logo')): ?>
                        <?= LayoutHelper::getContextualLogo('favicon', 'w-full h-full object-contain p-1', 'Logo') ?>
                    <?php else: ?>
                        <span class="text-white font-bold text-lg">E</span>
                    <?php endif; ?>
                </div>
                <div class="sidebar-text-container min-w-0">
                    <?php
                    $logoSidebar = LayoutHelper::getContextualLogo('sidebar', 'h-8 w-auto max-w-full', 'Logo');
                    if ($logoSidebar): ?>
                        <?= $logoSidebar ?>
                        <div class="sidebar-logo-fallback hidden">
                            <h1 class="text-xl font-bold text-white sidebar-text"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                        </div>
                        <p class="text-sm text-white/80 sidebar-text mt-0.5">Portal dos Pais</p>
                    <?php else: ?>
                        <h1 class="text-xl font-bold text-white sidebar-text"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                        <p class="text-sm text-white/80 sidebar-text">Portal dos Pais</p>
                    <?php endif; ?>
                </div>
            </div>
            <button id="sidebarToggle" class="text-white hover:bg-white/20 p-2 rounded-lg transition-colors sidebar-toggle flex-shrink-0">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>

        <!-- Seletor de filho -->
        <?php if (!empty($filhos)): ?>
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-3 mb-4">
            <label class="block text-xs font-medium text-white/80 mb-2">Ver dados de</label>
            <form action="<?= URL ?>/pais/selecionar-filho" method="get" id="form-selecionar-filho">
                <select name="id" onchange="this.form.submit()" class="w-full bg-white/20 text-white rounded-lg border border-white/30 px-3 py-2 text-sm focus:ring-2 focus:ring-white/50 focus:border-white/50 [&>option]:bg-gray-800">
                    <option value="">Selecione um filho</option>
                    <?php foreach ($filhos as $f): ?>
                    <option value="<?= (int)$f['id'] ?>" <?= ($filhoId && (int)$f['id'] === (int)$filhoId) ? 'selected' : '' ?>><?= htmlspecialchars($f['nome'] ?? 'Aluno') ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php endif; ?>

        <!-- User Info -->
        <div class="bg-white/10 backdrop-blur-sm rounded-xl p-4 mb-6 sidebar-user-info">
            <div class="flex items-center">
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3 overflow-hidden flex-shrink-0">
                    <span class="text-white font-semibold text-sm sidebar-text"><?= strtoupper(substr($user['nome'] ?? 'R', 0, 2)) ?></span>
                </div>
                <div class="sidebar-text-container min-w-0">
                    <p class="text-sm font-medium text-white sidebar-text"><?= htmlspecialchars($user['nome'] ?? 'Responsável') ?></p>
                    <p class="text-xs text-white/80 sidebar-text">Responsável</p>
                </div>
            </div>
        </div>

        <nav class="space-y-1">
            <a href="<?= URL ?>/pais/dashboard" class="flex items-center px-4 py-3 <?= $current_page === 'dashboard' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11v2a1 1 0 01-1 1h-2m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span class="sidebar-text">Dashboard</span>
            </a>

            <a href="<?= URL ?>/pais/filhos" class="flex items-center px-4 py-3 <?= $current_page === 'filhos' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <span class="sidebar-text">Meus Filhos</span>
            </a>

            <?php if ($filhoId): ?>
            <a href="<?= URL ?>/pais/filhos/<?= (int)$filhoId ?>/notas" class="flex items-center px-4 py-3 <?= $current_page === 'notas' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 6V7m3 10v-4m4 6H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z"></path>
                </svg>
                <span class="sidebar-text">Notas</span>
            </a>
            <a href="<?= URL ?>/pais/filhos/<?= (int)$filhoId ?>/jornadas" class="flex items-center px-4 py-3 <?= $current_page === 'jornadas' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                </svg>
                <span class="sidebar-text">Histórico de Jornadas</span>
            </a>
            <a href="<?= URL ?>/pais/filhos/<?= (int)$filhoId ?>/plano-aula" class="flex items-center px-4 py-3 <?= $current_page === 'plano-aula' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path>
                </svg>
                <span class="sidebar-text">Plano de Aula</span>
            </a>
            <a href="<?= URL ?>/pais/filhos/<?= (int)$filhoId ?>/mural-recados" class="flex items-center px-4 py-3 <?= $current_page === 'mural-recados' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <span class="sidebar-text">Recados</span>
            </a>
            <?php if (FeatureGate::isModuleEnabled('aluno_arquivos')): ?>
            <a href="<?= URL ?>/pais/filhos/<?= (int)$filhoId ?>/arquivos" class="flex items-center px-4 py-3 <?= $current_page === 'arquivos' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"></path>
                </svg>
                <span class="sidebar-text">Arquivos</span>
            </a>
            <?php endif; ?>
            <?php else: ?>
            <span class="flex items-center px-4 py-3 text-white/70 rounded-xl cursor-not-allowed sidebar-text" title="Selecione um filho na lista acima">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 6V7m3 10v-4m4 6H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v12a2 2 0 01-2 2z"></path></svg>
                <span class="text-sm">Notas (selecione um filho)</span>
            </span>
            <span class="flex items-center px-4 py-3 text-white/70 rounded-xl cursor-not-allowed sidebar-text" title="Selecione um filho na lista acima">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                <span class="text-sm">Jornadas (selecione um filho)</span>
            </span>
            <span class="flex items-center px-4 py-3 text-white/70 rounded-xl cursor-not-allowed sidebar-text" title="Selecione um filho na lista acima">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h.01M9 16h.01"></path></svg>
                <span class="text-sm">Plano de Aula (selecione um filho)</span>
            </span>
            <span class="flex items-center px-4 py-3 text-white/70 rounded-xl cursor-not-allowed sidebar-text" title="Selecione um filho na lista acima">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path></svg>
                <span class="text-sm">Recados (selecione um filho)</span>
            </span>
            <?php if (FeatureGate::isModuleEnabled('aluno_arquivos')): ?>
            <span class="flex items-center px-4 py-3 text-white/70 rounded-xl cursor-not-allowed sidebar-text" title="Selecione um filho na lista acima">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2V7z"></path></svg>
                <span class="text-sm">Arquivos (selecione um filho)</span>
            </span>
            <?php endif; ?>
            <?php endif; ?>

            <a href="<?= URL ?>/pais/mensagens" class="flex items-center px-4 py-3 <?= $current_page === 'mensagens' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                </svg>
                <span class="sidebar-text">Mensagens</span>
            </a>

            <a href="<?= URL ?>/pais/notificacoes" class="flex items-center px-4 py-3 <?= $current_page === 'notificacoes' ? 'text-white bg-white/20' : 'text-white/90 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
                <span class="sidebar-text">Notificações</span>
            </a>

            <a href="<?= URL ?>/logout?portal=pais" class="flex items-center px-4 py-3 text-red-200 hover:bg-red-500/20 hover:text-red-100 rounded-xl transition-all duration-200 mt-4 sidebar-nav-item">
                <svg class="w-5 h-5 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span class="sidebar-text">Sair</span>
            </a>
        </nav>
    </div>
</aside>
