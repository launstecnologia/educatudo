<?php
// Avatar do admin é resolvido em layouts/admin.php via AvatarUrlHelper.
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
<aside id="sidebar" class="w-72 md:w-72 sidebar-custom shadow-2xl min-h-screen transition-all duration-300 ease-in-out fixed md:relative md:left-0 left-0 z-50">
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
                    <?php if (LayoutHelper::getContextualLogo('sidebar', '', 'Logo')): ?>
                        <?= LayoutHelper::getContextualLogo('sidebar', 'h-9 w-auto max-w-full object-contain', 'Logo') ?>
                    <?php else: ?>
                        <h1 class="text-xl font-bold text-white sidebar-text"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                        <p class="text-sm text-purple-100 sidebar-text">Admin Panel</p>
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
                <div class="w-10 h-10 bg-white/20 rounded-full flex items-center justify-center mr-3 overflow-hidden">
                    <?php if (!empty($user['avatar_url'])): ?>
                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" class="w-full h-full object-cover rounded-full" onerror="this.remove(); this.parentElement.querySelector('[data-avatar-initials]')?.classList.remove('hidden');">
                        <span data-avatar-initials class="hidden text-white font-semibold text-sm pointer-events-none"><?= strtoupper(substr($user['nome'] ?? '', 0, 2)) ?></span>
                    <?php else: ?>
                        <span class="text-white font-semibold text-sm"><?= strtoupper(substr($user['nome'] ?? '', 0, 2)) ?></span>
                    <?php endif; ?>
                </div>
                <div class="sidebar-text-container">
                    <p class="text-sm font-medium text-white sidebar-text"><?= htmlspecialchars($user['nome'] ?? '') ?></p>
                    <p class="text-xs text-purple-100 sidebar-text"><?= ucfirst($user['perfil_admin'] ?? '') ?></p>
                </div>
            </div>
        </div>
        
        <?php
        $openTicketsCount = 0;
        $alertasSensiveisNovos = 0;
        $perfilAdmin = $user['perfil_admin'] ?? '';
        $podeVerAlertas = !in_array($perfilAdmin, ['financeiro', 'secretaria'], true)
            && ($perfilAdmin === '' || in_array($perfilAdmin, ['dev', 'diretor', 'coordenador'], true));
        if (($user['perfil_admin'] ?? '') === 'dev' || $podeVerAlertas) {
            require_once __DIR__ . '/../../../Core/Database.php';
            $db = Database::getInstance();
            try {
                if (($user['perfil_admin'] ?? '') === 'dev') {
                    $openTicketsCount = $db->fetch("SELECT COUNT(*) as total FROM suporte_tickets WHERE status = 'aberto'")['total'] ?? 0;
                }
                if ($podeVerAlertas) {
                    $alertasSensiveisNovos = $db->fetch("SELECT COUNT(*) as total FROM alertas_sensiveis WHERE status = 'novo'")['total'] ?? 0;
                }
            } catch (Throwable $e) {
                $openTicketsCount = 0;
                $alertasSensiveisNovos = 0;
            }
        }
        if (!class_exists('AdminPermissionMatrix')) {
            require_once __DIR__ . '/../../../Core/AdminPermissionMatrix.php';
        }
        $adminPermissionsSidebar = AdminPermissionMatrix::effectivePermissionsForUser($db ?? Database::getInstance(), $user ?? []);
        $canViewSidebar = static function (array $keys) use ($adminPermissionsSidebar): bool {
            foreach ($keys as $k) {
                if (!empty($adminPermissionsSidebar[$k]['visualizar'])) {
                    return true;
                }
            }
            return false;
        };
        $showDashboardMenu = $canViewSidebar(['dashboard']);
        $showAlunosMenu = $canViewSidebar(['alunos']);
        $showUsuariosGroup = $canViewSidebar(['administradores', 'professores', 'unidades']);
        $showAcademicoGroup = $canViewSidebar(['ano_letivo', 'curso', 'series', 'matriz_curricular', 'regras_academicas', 'turmas', 'salas', 'transferencia']);
        $showInventoryGroup = $canViewSidebar(['almoxarifado', 'patrimonio']);
        $curMovimentacao = in_array($current_page ?? '', ['students_remanejamento', 'students_transferencia_escolar'], true);
        ?>
        <nav class="admin-sidebar-nav space-y-0.5">
            <?php if (($user['perfil_admin'] ?? '') === 'financeiro'): ?>
                <div class="menu-group">
                    <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'financeiro' ? 'bg-white/20' : '' ?>">
                        <a href="<?= URL ?>/admin/financeiro-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                            <i class="fa-regular fa-money-bill-1 w-5 h-5 mr-3"></i>
                            <span class="sidebar-text">Financeiro</span>
                        </a>
                        <button type="button" onclick="toggleMenuGroup('financeiro')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                            <svg id="financeiro-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="financeiro-submenu" class="ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                        <?php
                        $finItemsFin = [
                            ['url'=>'/admin/finance',                        'label'=>'Dashboard',       'page'=>'finance',               'icon'=>'fa-gauge-high'],
                            ['url'=>'/admin/finance/contracts',              'label'=>'Contratos',         'page'=>'finance_contracts',     'icon'=>'fa-file-contract'],
                            ['url'=>'/admin/finance/plans',                  'label'=>'Planos e Preços', 'page'=>'finance_plans',         'icon'=>'fa-layer-group'],
                            ['url'=>'/admin/finance/charges',                'label'=>'Cobranças Avulsas','page'=>'finance_charges',       'icon'=>'fa-bolt'],
                            ['url'=>'/admin/finance/charges/batch',          'label'=>'Nova em Lote',    'page'=>'finance_charge_batch',  'icon'=>'fa-layer-group'],
                            ['url'=>'/admin/finance/bills',                  'label'=>'Contas a Pagar',  'page'=>'finance_bills',         'icon'=>'fa-file-invoice-dollar'],
                            ['url'=>'/admin/finance/cashflow',               'label'=>'Fluxo de Caixa',  'page'=>'finance_cashflow',      'icon'=>'fa-water'],
                            ['url'=>'/admin/finance/reports/dre',            'label'=>'DRE',             'page'=>'finance_dre',           'icon'=>'fa-chart-bar'],
                            ['url'=>'/admin/finance/reports/balanco',        'label'=>'Balanço',         'page'=>'finance_balanco',       'icon'=>'fa-scale-balanced'],
                            ['url'=>'/admin/finance/reports/dfc',            'label'=>'DFC',             'page'=>'finance_dfc',           'icon'=>'fa-arrow-right-arrow-left'],
                            ['url'=>'/admin/finance/reports/dmpl',           'label'=>'DMPL',            'page'=>'finance_dmpl',          'icon'=>'fa-table'],
                            ['url'=>'/admin/finance/reports/dlpa',           'label'=>'DLPA',            'page'=>'finance_dlpa',          'icon'=>'fa-trophy'],
                            ['url'=>'/admin/finance/discount-rules',         'label'=>'Descontos',       'page'=>'finance_discounts',     'icon'=>'fa-percent'],
                            ['url'=>'/admin/finance/report/inadimplencia',   'label'=>'Inadimplência',   'page'=>'finance_inadimplencia', 'icon'=>'fa-chart-line'],
                            ['url'=>'/admin/finance/settings',               'label'=>'Configurações',   'page'=>'finance_settings',      'icon'=>'fa-sliders'],
                        ];
                        $cpFin = $current_page ?? '';
                        foreach ($finItemsFin as $fi): ?>
                        <a href="<?= URL . $fi['url'] ?>" class="flex items-center px-4 py-2 text-sm <?= $cpFin === $fi['page'] ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                            <i class="fa-solid <?= $fi['icon'] ?> w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span class="sidebar-text"><?= $fi['label'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Logout Button -->
                <a href="<?= URL ?>/logout?portal=admin" class="flex items-center px-4 py-3 text-red-200 hover:bg-red-500/20 hover:text-red-100 rounded-xl transition-all duration-200 mt-4">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="sidebar-text">Sair</span>
                </a>
            <?php elseif (($user['perfil_admin'] ?? '') === 'secretaria'): ?>
                <?php require __DIR__ . '/admin_sidebar_menu_secretaria.php'; ?>
                <a href="<?= URL ?>/logout?portal=admin" class="flex items-center px-4 py-3 text-red-200 hover:bg-red-500/20 hover:text-red-100 rounded-xl transition-all duration-200 mt-4">
                    <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span class="sidebar-text">Sair</span>
                </a>
            <?php else: ?>
            <!-- Dashboard -->
            <?php if ($showDashboardMenu): ?>
            <a href="<?= URL ?>/admin/dashboard" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'dashboard' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2H5a2 2 0 00-2-2z"></path>
                </svg>
                <span class="sidebar-text">Dashboard</span>
            </a>
            <?php endif; ?>
            <?php if ($showAlunosMenu): ?>
            <a href="<?= URL ?>/admin/students" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'students' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                </svg>
                <span class="sidebar-text">Alunos</span>
            </a>
            <?php endif; ?>
            <?php if (in_array(($user['perfil_admin'] ?? ''), ['dev', 'diretor', 'coordenador'], true)): ?>
            <a href="<?= URL ?>/admin/assistente" class="flex items-center px-4 py-3 <?= ($current_page ?? '') === 'assistente' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-xl transition-all duration-200">
                <i class="fa-solid fa-comments w-5 h-5 mr-3"></i>
                <span class="sidebar-text">Assistente</span>
            </a>
            <?php endif; ?>
            
            <!-- Acadêmico (Menu Expansível) -->
            <?php if ($showAcademicoGroup): ?>
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'academico' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/academico" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-solid fa-book-open w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Acadêmico</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('academico')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="academico-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="academico-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <a href="<?= URL ?>/admin/ano-letivo" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'ano_letivo' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-calendar w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Ano Letivo</span>
                    </a>
                    <a href="<?= URL ?>/admin/curso" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'curso' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-graduation-cap w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Curso</span>
                    </a>
                    <a href="<?= URL ?>/admin/serie" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'serie' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-layer-group w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Série</span>
                    </a>
                    <a href="<?= URL ?>/admin/componentes-curriculares" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'componentes-curriculares' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-book w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Componentes Curriculares</span>
                    </a>
                    <?php if ($canViewSidebar(['matriz_curricular'])): ?>
                    <a href="<?= URL ?>/admin/matrizes-curriculares" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'matriz-curricular' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-sitemap w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Matriz Curricular</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canViewSidebar(['regras_academicas'])): ?>
                    <a href="<?= URL ?>/admin/regras-academicas" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'regras-academicas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-scale-balanced w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Regras Acadêmicas</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canViewSidebar(['resultados_finais'])): ?>
                    <a href="<?= URL ?>/admin/resultados-finais" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'resultados-finais' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-clipboard-check w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Resultados Finais</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/turmas" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'turmas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-school w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Turmas</span>
                    </a>
                    <?php if ($canViewSidebar(['salas'])): ?>
                    <a href="<?= URL ?>/admin/salas" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'salas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-door-open w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Salas / Ambientes</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/grade-horaria" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'grade_horaria' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Grade Horária</span>
                    </a>
                    <a href="<?= URL ?>/admin/calendario-escolar" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'school-calendar' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-calendar-days w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Calendário Escolar</span>
                    </a>
                    <?php if ($canViewSidebar(['transferencia'])): ?>
                    <a href="<?= URL ?>/admin/students/remanejamento" class="flex items-center px-4 py-2 <?= $curMovimentacao ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-people-arrows w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Movimentação de alunos</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Pedagógico (Menu Expansível) -->
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'pedagogico' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/pedagogico" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-id-card w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Pedagógico</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('pedagogico')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="pedagogico-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="pedagogico-submenu" class="<?= in_array(($current_page ?? ''), ['aulas_online', 'planos-aula', 'ava', 'minicursos']) ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aulas_online')): ?>
                    <a href="<?= URL ?>/admin/aulas-online" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'aulas_online' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-video w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Aulas Online</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/planos-aula" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'planos-aula' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-file-lines w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Plano de Aula</span>
                    </a>
                    <?php if (class_exists('FeatureGate') && FeatureGate::isModuleEnabled('ead')): ?>
                    <a href="<?= URL ?>/admin/ava" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'ava' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-graduation-cap w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">AVA / EAD</span>
                    </a>
                    <?php endif; ?>
                    <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleVisible('aluno_minicursos')): ?>
                    <a href="<?= URL ?>/admin/minicursos" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'minicursos' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-play-circle w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">EducaCursos</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Avaliações (Menu Expansível) -->
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'avaliacoes' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/avaliacoes" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-clipboard w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Avaliações</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('avaliacoes')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="avaliacoes-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="avaliacoes-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <a href="<?= URL ?>/admin/provas" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'provas' || ($current_page ?? '') === 'provas_blocos' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Avaliações/Notas</span>
                    </a>
                    <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleVisible('exercicios')): ?>
                    <a href="<?= URL ?>/admin/exercises" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'exercises' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-check-double w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Exercícios</span>
                    </a>
                    <?php endif; ?>
                    <?php require_once __DIR__ . '/../../../Core/LayoutHelper.php'; require_once __DIR__ . '/../../../Core/FeatureGate.php'; ?>
                    <?php if (FeatureGate::isModuleEnabled('jornadas')): ?>
                    <details class="sidebar-nav-item" <?= in_array(($current_page ?? ''), ['journeys', 'journeys_relatorio'], true) ? 'open' : '' ?>>
                        <summary class="flex items-center px-4 py-2 <?= in_array(($current_page ?? ''), ['journeys', 'journeys_relatorio'], true) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 list-none cursor-pointer">
                            <i class="fa-solid fa-route w-4 h-4 mr-3"></i>
                            <span class="sidebar-text text-sm flex-1">Jornada do Aluno</span>
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </summary>
                        <div class="ml-6 mt-1 space-y-1">
                            <a href="<?= URL ?>/admin/jornadas" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'journeys' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                                <span class="sidebar-text text-sm">Listagem</span>
                            </a>
                            <a href="<?= URL ?>/admin/jornadas/relatorio" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'journeys_relatorio' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                                <span class="sidebar-text text-sm">Relatório</span>
                            </a>
                        </div>
                    </details>
                    <?php endif; ?>
                    <details class="sidebar-nav-item" <?= in_array(($current_page ?? ''), ['essays_teacher', 'essays_teacher_report'], true) ? 'open' : '' ?>>
                        <summary class="flex items-center px-4 py-2 <?= in_array(($current_page ?? ''), ['essays_teacher', 'essays_teacher_report'], true) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 list-none cursor-pointer">
                            <i class="fa-solid fa-pen-to-square w-4 h-4 mr-3"></i>
                            <span class="sidebar-text text-sm flex-1">Jornada da Redação</span>
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </summary>
                        <div class="ml-6 mt-1 space-y-1">
                            <a href="<?= URL ?>/admin/redacao-professor"
                               class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'essays_teacher' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                                <span class="sidebar-text text-sm">Listagem</span>
                            </a>
                            <a href="<?= URL ?>/admin/redacao-professor/relatorio"
                               class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'essays_teacher_report' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                                <span class="sidebar-text text-sm">Relatório</span>
                            </a>
                        </div>
                    </details>
                    <?php if ($canViewSidebar(['inclusao']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleVisible('inclusao'))): ?>
                    <a href="<?= URL ?>/admin/inclusao/versoes"
                       class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'inclusao' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                        <i class="fa-solid fa-universal-access w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Avaliação Adaptativa</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Comunicação (Menu Expansível) -->
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'comunicacao' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/comunicacao" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-comments w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Comunicação</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('comunicacao')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="comunicacao-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="comunicacao-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <a href="<?= URL ?>/forum/moderation/reports" class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/forum/moderation') !== false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-triangle-exclamation w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Denúncias Fórum</span>
                    </a>
                    <a href="<?= URL ?>/forum" class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/forum') !== false && strpos($_SERVER['REQUEST_URI'] ?? '', '/forum/moderation') === false ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-comments w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Fórum</span>
                    </a>
                    <a href="<?= URL ?>/admin/mural-recados" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'mural-recados' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-bullhorn w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Mural de Recados</span>
                    </a>
                    <a href="<?= URL ?>/admin/comunicacao-escolar" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'school-communication' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-envelope-open-text w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Comunicação Escolar</span>
                    </a>
                    <a href="<?= URL ?>/admin/notifications" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'notifications' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-bell w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Notificações</span>
                    </a>
                    <a href="<?= URL ?>/admin/notificacoes-push" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'notificacoes-push' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-bell-concierge w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Notificações Push</span>
                    </a>
                </div>
            </div>
            
            <!-- Conteúdo (Menu Expansível) -->
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'conteudo' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/conteudo" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-folder w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Conteúdo</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('conteudo')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="conteudo-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="conteudo-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <!-- Meu Material substitui as antigas entradas separadas "IA da Apostila" e
                         "Apostilas" no menu. O módulo "Apostilas" simples continua existindo
                         (rota /admin/apostilas, controller e dados intactos) só não tem mais
                         item próprio aqui — fica acessível por quem já tem o link direto. -->
                    <a href="<?= URL ?>/admin/apostilas-ia" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'apostilas-ia' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-wand-magic-sparkles w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Meu Material</span>
                    </a>
                    <a href="<?= URL ?>/admin/arquivos" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'arquivos' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-folder w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Arquivos</span>
                    </a>
                    <?php if ($canViewSidebar(['expo_colag']) && class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('expo_colag')): ?>
                    <a href="<?= URL ?>/admin/expo-colag" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'expo-colag' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-building-columns w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Expo Colag</span>
                    </a>
                    <?php endif; ?>
                    <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleVisible('educa_hits')): ?>
                    <?php require_once __DIR__ . '/../../../Core/EducaHitsConfig.php'; ?>
                    <a href="<?= htmlspecialchars(EducaHitsConfig::portalLoginUrl()) ?>" target="_blank" rel="noopener noreferrer" class="flex items-center px-4 py-2 text-purple-100 hover:bg-white/20 hover:text-white rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-music w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">EducaHits (portal)</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            
            <!-- Gestão Escolar (Menu Expansível) -->
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'gestao_escolar' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/gestao-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-chart-bar w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Gestão Escolar</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('gestao-escolar')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="gestao-escolar-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="gestao-escolar-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <?php if (LayoutHelper::isModuleEnabled('processo_matricula')): ?>
                    <a href="<?= URL ?>/admin/enrollment" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'enrollment' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-file-signature w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Matrículas</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/faltas" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'faltas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-clipboard w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Faltas</span>
                    </a>
                    <?php if ($canViewSidebar(['presenca']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca'))): ?>
                    <a href="<?= URL ?>/admin/presenca" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'presenca' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-right-to-bracket w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Presença</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canViewSidebar(['diario_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('diario_classe'))): ?>
                    <a href="<?= URL ?>/admin/diario" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'diario_classe' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 12h6m-6 4h6"></path></svg>
                        <span class="sidebar-text">Diário de Classe</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canViewSidebar(['conselho_classe']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conselho_classe'))): ?>
                    <a href="<?= URL ?>/admin/conselhos" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'conselho_classe' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-chalkboard-user w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Conselho de Classe</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canViewSidebar(['censo_escolar']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('censo_escolar'))): ?>
                    <a href="<?= URL ?>/admin/censo" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'censo_escolar' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-school-flag w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Censo Escolar</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/conformidade" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'conformidade' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-clipboard-check w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Conformidade</span>
                    </a>
                    <a href="<?= URL ?>/admin/calendario-letivo" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'calendario_letivo' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-calendar-check w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Calendário Letivo</span>
                    </a>
                    <a href="<?= URL ?>/admin/bncc" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'bncc' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-list-check w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">BNCC / Plano de Curso</span>
                    </a>
                    <a href="<?= URL ?>/admin/documentos-institucionais" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'documentos_institucionais' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-file-shield w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Documentos Institucionais</span>
                    </a>
                    <?php if ($canViewSidebar(['modelos_documentos'])): ?>
                    <a href="<?= URL ?>/admin/modelos-documentos" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'modelos_documentos' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-file-contract w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Layout de documentos</span>
                    </a>
                    <?php endif; ?>
                    <?php if ($canViewSidebar(['ocorrencias']) && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))): ?>
                    <a href="<?= URL ?>/admin/ocorrencias" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'ocorrencias' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-regular fa-clock w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Ocorrências</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/reunioes/geral" class="flex items-center px-4 py-2 <?= in_array($current_page ?? '', ['reunioes_geral','reunioes']) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-people-group w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Reuniões</span>
                    </a>
                    <a href="<?= URL ?>/admin/tudicoins" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'tudicoins_escola' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-wallet w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">TudiCoins da Escola</span>
                    </a>
                    <a href="<?= URL ?>/admin/creditos/pacotes" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'creditos_pacotes' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-coins w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Pacotes de TudiCoins</span>
                    </a>
                    <a href="<?= URL ?>/admin/saude-academica" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'saude_academica' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-heart-pulse w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Saúde Acadêmica</span>
                    </a>
                    <?php if ($showInventoryGroup): ?>
                    <details class="sidebar-nav-item" <?= in_array(($current_page ?? ''), ['almoxarifado', 'patrimonio'], true) ? 'open' : '' ?>>
                        <summary class="flex items-center px-4 py-2 <?= in_array(($current_page ?? ''), ['almoxarifado', 'patrimonio'], true) ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 list-none cursor-pointer">
                            <i class="fa-solid fa-boxes-stacked w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span class="sidebar-text text-sm flex-1">Recursos Físicos</span>
                            <i class="fa-solid fa-chevron-down text-xs"></i>
                        </summary>
                        <div class="ml-6 mt-1 space-y-1">
                            <?php if ($canViewSidebar(['almoxarifado'])): ?>
                            <a href="<?= URL ?>/admin/almoxarifado" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'almoxarifado' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                                <span class="sidebar-text text-sm">Almoxarifado</span>
                            </a>
                            <?php endif; ?>
                            <?php if ($canViewSidebar(['patrimonio'])): ?>
                            <a href="<?= URL ?>/admin/patrimonio" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'patrimonio' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200 text-sm">
                                <span class="sidebar-text text-sm">Patrimônio</span>
                            </a>
                            <?php endif; ?>
                        </div>
                    </details>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Financeiro Escolar (Menu de nível 1) -->
            <?php
            $finItems = [
                ['url'=>'/admin/finance',                      'label'=>'Dashboard',         'page'=>'finance',               'icon'=>'fa-gauge-high'],
                ['url'=>'/admin/finance/contracts',            'label'=>'Contratos',         'page'=>'finance_contracts',     'icon'=>'fa-file-contract'],
                ['url'=>'/admin/finance/plans',                'label'=>'Planos e Preços',   'page'=>'finance_plans',         'icon'=>'fa-layer-group'],
                ['url'=>'/admin/finance/charges',              'label'=>'Cobranças Avulsas', 'page'=>'finance_charges',       'icon'=>'fa-bolt'],
                ['url'=>'/admin/finance/charges/batch',        'label'=>'Nova em Lote',      'page'=>'finance_charge_batch',  'icon'=>'fa-layer-group'],
                ['url'=>'/admin/finance/bills',                'label'=>'Contas a Pagar',    'page'=>'finance_bills',         'icon'=>'fa-file-invoice-dollar'],
                ['url'=>'/admin/finance/cashflow',             'label'=>'Fluxo de Caixa',    'page'=>'finance_cashflow',      'icon'=>'fa-water'],
                ['url'=>'/admin/finance/reports/dre',          'label'=>'DRE',               'page'=>'finance_dre',           'icon'=>'fa-chart-bar'],
                ['url'=>'/admin/finance/reports/dfc',          'label'=>'DFC',               'page'=>'finance_dfc',           'icon'=>'fa-arrow-right-arrow-left'],
                ['url'=>'/admin/finance/reports/balanco',      'label'=>'Balanço Patrimonial','page'=>'finance_balanco',      'icon'=>'fa-scale-balanced'],
                ['url'=>'/admin/finance/reports/dmpl',         'label'=>'DMPL',              'page'=>'finance_dmpl',          'icon'=>'fa-table'],
                ['url'=>'/admin/finance/reports/dlpa',         'label'=>'DLPA',              'page'=>'finance_dlpa',          'icon'=>'fa-trophy'],
                ['url'=>'/admin/finance/discount-rules',       'label'=>'Descontos',         'page'=>'finance_discounts',     'icon'=>'fa-percent'],
                ['url'=>'/admin/finance/report/inadimplencia', 'label'=>'Inadimplência',     'page'=>'finance_inadimplencia', 'icon'=>'fa-chart-line'],
                ['url'=>'/admin/finance/settings',             'label'=>'Configurações',     'page'=>'finance_settings',      'icon'=>'fa-sliders'],
            ];
            $finPages = ['finance','finance_contracts','finance_plans','finance_charges','finance_charge_batch','finance_bills','finance_cashflow','finance_dre','finance_dfc','finance_balanco','finance_dmpl','finance_dlpa','finance_price','finance_discounts','finance_inadimplencia','finance_settings'];
            $cpFin = $current_page ?? '';
            ?>
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'financeiro' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/financeiro-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-money-bill-1 w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Financeiro</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('financeiro-escolar')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="financeiro-escolar-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="financeiro-escolar-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <?php foreach ($finItems as $fi): ?>
                    <a href="<?= URL . $fi['url'] ?>" class="flex items-center px-4 py-2 text-sm <?= $cpFin === $fi['page'] ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid <?= $fi['icon'] ?> w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text"><?= $fi['label'] ?></span>
                    </a>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Monitoramento (Menu Expansível) -->
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'monitoramento' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/monitoramento-escolar" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-circle-check w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Monitoramento</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('monitoramento')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="monitoramento-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="monitoramento-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <?php if ($podeVerAlertas): ?>
                        <a href="<?= URL ?>/admin/monitoramento/alertas" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'monitoramento_alertas' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                            <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <span class="sidebar-text text-sm">Alertas Sensíveis</span>
                            <?php if ($alertasSensiveisNovos > 0): ?>
                                <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full sidebar-text">
                                    <?= (int)$alertasSensiveisNovos ?>
                                </span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    <button onclick="abrirModalAlunosOnline()" class="w-full flex items-center px-4 py-2 text-green-200 hover:bg-green-500/20 hover:text-green-100 rounded-lg transition-all duration-200 text-left">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Alunos Online</span>
                        <span id="badge-online-count" class="ml-auto bg-green-500 text-white text-xs font-bold px-2 py-1 rounded-full sidebar-text">0</span>
                    </button>
                </div>
            </div>
            
            <!-- Relatórios (Menu Expansível) -->
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'relatorios' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/relatorios" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-chart-bar w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Relatórios</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('relatorios')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="relatorios-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="relatorios-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <a href="<?= URL ?>/admin/reports/boletim-coordenacao" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'reports_boletim_coordenacao' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-file-signature w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Notas da Coordenação</span>
                    </a>
                    <a href="<?= URL ?>/admin/reports" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'reports' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Jornada do Aluno</span>
                    </a>
                </div>
            </div>
            
            <!-- Sistema (Menu Expansível) -->
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'sistema' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/sistema" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-solid fa-gear w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Sistema</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('sistema')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="sistema-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="sistema-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <a href="<?= URL ?>/admin/redacao-configuravel" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'essays_config' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-sliders w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Configuração de Prompt</span>
                    </a>
                    <a href="<?= URL ?>/admin/tentativas-login" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'tentativas_login' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-lock w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Tentativas de login</span>
                    </a>
                </div>
            </div>
            
            <!-- Usuários (Menu Expansível) -->
            <?php if ($showUsuariosGroup): ?>
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'gestao_usuarios' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/gestao-usuarios" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-regular fa-user w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Usuários</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('usuarios')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="usuarios-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="usuarios-submenu" class="hidden ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <a href="<?= URL ?>/admin/usuarios" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'usuarios' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Administradores</span>
                    </a>
                    <a href="<?= URL ?>/admin/monitors" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'monitors' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Monitores</span>
                    </a>
                    <a href="<?= URL ?>/admin/permissoes-perfis" class="flex items-center px-4 py-2 <?= strpos($_SERVER['REQUEST_URI'] ?? '', '/admin/permissoes-perfis') === 0 ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6M7 4h10a2 2 0 012 2v12a2 2 0 01-2 2H7a2 2 0 01-2-2V6a2 2 0 012-2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Perfis de Permissão</span>
                    </a>
                    <a href="<?= URL ?>/admin/teachers" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'teachers' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Professores</span>
                    </a>
                    <?php if ($canViewSidebar(['unidades'])): ?>
                    <a href="<?= URL ?>/admin/unidades" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'unidades' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-building w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text text-sm">Instituição</span>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Z-Configuração (final do menu) -->
            <?php
            $zConfigPages = [
                'boletim_config', 'boletim_guia', 'dev', 'dev_tickets', 'maintenance_panel',
                'settings', 'enrollment_config', 'assinatura_digital', 'ui_modelos',
                'notas_semanais',
            ];
            $zConfigOpen = in_array(($current_page ?? ''), $zConfigPages, true);
            ?>
            <div class="menu-group">
                <div class="flex items-center rounded-xl <?= ($current_page ?? '') === 'z_configuracao' ? 'bg-white/20' : '' ?>">
                    <a href="<?= URL ?>/admin/z-configuracao" class="flex-1 flex items-center px-4 py-3 text-purple-100 hover:bg-white/20 hover:text-white rounded-xl transition-all duration-200">
                        <i class="fa-solid fa-sliders w-5 h-5 mr-3"></i>
                        <span class="sidebar-text">Z-Configuração</span>
                    </a>
                    <button type="button" onclick="toggleMenuGroup('z-configuracao')" class="px-3 py-3 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                        <svg id="z-configuracao-arrow" class="w-4 h-4 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>
                </div>
                <div id="z-configuracao-submenu" class="<?= $zConfigOpen ? '' : 'hidden' ?> ml-4 mt-1 space-y-1 border-l-2 border-white/20 pl-2">
                    <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula') && $canViewSidebar(['processos_matricula'])): ?>
                    <a href="<?= URL ?>/admin/enrollment/config" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'enrollment_config' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-graduation-cap w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Configuração de Matrícula</span>
                    </a>
                    <?php endif; ?>
                    <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula') && $canViewSidebar(['processos_matricula'])): ?>
                    <a href="<?= URL ?>/admin/configuracao/assinatura-digital" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'assinatura_digital' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-pen-nib w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Assinatura Digital</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/boletim" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'boletim_config' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-6m3 6V7m3 10v-4m3 8H6a2 2 0 01-2-2V5a2 2 0 012-2h8l6 6v10a2 2 0 01-2 2z"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Notas e Boletim</span>
                    </a>
                    <?php if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('notas_semanais') && $canViewSidebar(['notas_semanais'])): ?>
                    <a href="<?= URL ?>/admin/notas-semanais" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'notas_semanais' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-table w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Quadro semanal</span>
                    </a>
                    <?php endif; ?>
                    <a href="<?= URL ?>/admin/boletim-guia" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'boletim_guia' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <svg class="w-4 h-4 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.746 0 3.332.477 4.5 1.253v13C19.832 18.477 18.246 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                        </svg>
                        <span class="sidebar-text text-sm">Guia do Boletim</span>
                    </a>
                    <a href="<?= URL ?>/admin/maintenance/painel" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'maintenance_panel' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-screwdriver-wrench w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Modo Manutenção</span>
                    </a>
                    <a href="<?= URL ?>/admin/settings#slider-dashboard" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'settings' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-images w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Slider Dashboard</span>
                    </a>
                    <?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
                    <a href="<?= URL ?>/admin/configuracao/ui-modelos" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'ui_modelos' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-palette w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">UI Modelos</span>
                    </a>
                    <a href="<?= URL ?>/admin/dev" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'dev' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-code w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Dev Settings</span>
                    </a>
                    <a href="<?= URL ?>/admin/dev/tickets" class="flex items-center px-4 py-2 <?= ($current_page ?? '') === 'dev_tickets' ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid fa-ticket w-4 h-4 mr-3"></i>
                        <span class="sidebar-text text-sm">Tickets</span>
                        <?php if ($openTicketsCount > 0): ?>
                            <span class="ml-auto bg-red-500 text-white text-xs font-bold px-2 py-1 rounded-full sidebar-text"><?= (int) $openTicketsCount ?></span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Logout Button -->
            <a href="<?= URL ?>/logout?portal=admin" class="flex items-center px-4 py-3 text-red-200 hover:bg-red-500/20 hover:text-red-100 rounded-xl transition-all duration-200 mt-4">
                <svg class="w-5 h-5 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                </svg>
                <span class="sidebar-text">Sair</span>
            </a>
            <?php endif; ?>
        </nav>
    </div>
</aside>

<style>
/* Sidebar nav: textos longos quebram linha; collapsed mantém ícones compactos */
.admin-sidebar-nav .sidebar-text {
    white-space: normal;
    line-height: 1.35;
    word-break: break-word;
}
.admin-sidebar-nav a,
.admin-sidebar-nav .menu-group > button {
    min-height: 2.5rem;
    justify-content: flex-start;
    text-align: left;
}
.admin-sidebar-nav .menu-group > button > div {
    min-width: 0;
    flex: 0 1 auto;
    justify-content: flex-start;
}
.admin-sidebar-nav .menu-group > button > div .sidebar-text {
    width: auto;
    text-align: left;
}
.admin-sidebar-nav .menu-group > button > svg[id$='-arrow'] {
    flex-shrink: 0;
    margin-left: auto;
}
.admin-sidebar-nav [class*="mr-3"] {
    flex-shrink: 0;
}

/* Itens de 1º nível: mais compactos, ícones menores */
.admin-sidebar-nav > a,
.admin-sidebar-nav .menu-group > button {
    min-height: 2.125rem;
    padding-top: 0.45rem;
    padding-bottom: 0.45rem;
}

.admin-sidebar-nav .menu-group > div.flex > a,
.admin-sidebar-nav .menu-group > div.flex > button {
    min-height: 2.125rem;
    padding-top: 0.45rem;
    padding-bottom: 0.45rem;
}

.admin-sidebar-nav > a > i[class*="fa-"],
.admin-sidebar-nav .menu-group > button i[class*="fa-"],
.admin-sidebar-nav .menu-group > div.flex > a > i[class*="fa-"] {
    font-size: 0.95rem;
    width: 1.125rem;
    text-align: center;
}

.admin-sidebar-nav > a > svg,
.admin-sidebar-nav .menu-group > button > div > svg {
    width: 1.125rem;
    height: 1.125rem;
}

.admin-sidebar-nav .menu-group > button svg[id$="-arrow"],
.admin-sidebar-nav .menu-group > div.flex > button svg {
    width: 0.875rem;
    height: 0.875rem;
}

/* Sidebar Collapsed Styles (desktop only) */
@media (min-width: 768px) {
    #sidebar.collapsed {
        width: 3.5rem !important; /* 56px */
    }

    /* Texto some de verdade: width:0 + word-break empilhava letras e alongava os itens */
    #sidebar.collapsed .sidebar-text,
    #sidebar.collapsed .sidebar-text-container {
        display: none !important;
    }

    /* Header adjustments */
    #sidebar.collapsed .sidebar-header {
        justify-content: center !important;
        flex-direction: column !important;
        gap: 0;
        margin-bottom: 0.5rem !important;
        position: relative;
    }

    #sidebar.collapsed .sidebar-logo-container {
        justify-content: center !important;
        width: 100%;
    }

    #sidebar.collapsed .sidebar-icon {
        margin-right: 0 !important;
        width: 2rem;
        height: 2rem;
    }

    #sidebar.collapsed .sidebar-toggle {
        display: none !important;
    }

    /* Navigation adjustments */
    #sidebar.collapsed nav a,
    #sidebar.collapsed .menu-group > button {
        justify-content: center !important;
        align-items: center !important;
        padding: 0.4rem !important;
        min-height: 2.25rem;
        width: 100%;
    }

    #sidebar.collapsed nav a .mr-3,
    #sidebar.collapsed nav a [class*="mr-3"],
    #sidebar.collapsed .menu-group > button [class*="mr-3"] {
        margin-right: 0 !important;
    }

    /* Container adjustments */
    #sidebar.collapsed > div {
        padding: 0.4rem 0.3rem !important;
    }

    #sidebar.collapsed .mb-8 {
        margin-bottom: 0.5rem !important;
    }

    #sidebar.collapsed .mb-6 {
        margin-bottom: 0.5rem !important;
    }

    /* Keep compact mode clean: hide expandables details/arrows and nested menus */
    #sidebar.collapsed .menu-group > button > div {
        justify-content: center !important;
        width: auto !important;
        flex: 0 0 auto !important;
    }

    #sidebar.collapsed .menu-group > button > svg[id$='-arrow'],
    #sidebar.collapsed .menu-group > button .fa-chevron-down,
    #sidebar.collapsed .menu-group > button .fa-chevron-up {
        display: none !important;
    }

    #sidebar.collapsed .menu-group > div,
    #sidebar.collapsed details.sidebar-nav-item > div,
    #sidebar.collapsed details.sidebar-nav-item[open] > div {
        display: none !important;
    }

    #sidebar.collapsed details.sidebar-nav-item {
        display: none !important;
    }

    #sidebar.collapsed nav {
        gap: 0.125rem !important;
    }

    #sidebar.collapsed nav > * + * {
        margin-top: 0 !important;
    }

    #sidebar.collapsed .sidebar-user-info {
        display: none !important;
    }
}

/* Smooth transitions */
.sidebar-text {
    transition: all 0.3s ease-in-out;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
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

/* Expandable menus: label à esquerda (ícone + texto), seta à direita */
#sidebar .menu-group > button > div {
    min-width: 0;
    flex: 0 1 auto;
    justify-content: flex-start;
}

#sidebar .menu-group > button > div .sidebar-text {
    display: inline;
    width: auto;
    text-align: left;
}

#sidebar .menu-group > button > svg[id$='-arrow'] {
    flex: 0 0 auto;
    margin-left: auto;
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
    const isMobile = window.innerWidth <= 768;

    if (!sidebar || !toggleButton || !expandButton) {
        return;
    }
    
    // Check if sidebar was previously collapsed
    const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
    if (isCollapsed && !isMobile) {
        sidebar.classList.add('collapsed');
    } else if (isMobile) {
        sidebar.classList.remove('collapsed');
    }
    
    // Toggle sidebar function
    function toggleSidebar() {
        if (window.innerWidth <= 768) {
            return;
        }
        sidebar.classList.toggle('collapsed');
        
        // Save state to localStorage
        const collapsed = sidebar.classList.contains('collapsed');
        localStorage.setItem('sidebarCollapsed', collapsed);
        
        // Update main content margin
        updateMainContentMargin(collapsed);
    }

    // Ensure compact mode always starts with closed groups (visual cleanliness)
    function closeAllMenuGroupsWhenCollapsed() {
        if (!sidebar.classList.contains('collapsed')) return;
        const menuGroups = ['usuarios', 'academico', 'gestao-escolar', 'conteudo', 'avaliacoes', 'comunicacao', 'sistema', 'relatorios', 'monitoramento', 'z-configuracao', 'financeiro', 'financeiro-escolar'];
        menuGroups.forEach(group => {
            const submenu = document.getElementById(`${group}-submenu`);
            const arrow = document.getElementById(`${group}-arrow`);
            if (submenu) submenu.classList.add('hidden');
            if (arrow) arrow.style.transform = 'rotate(0deg)';
        });
    }
    
    // Toggle sidebar
    toggleButton.addEventListener('click', toggleSidebar);
    
    // Expand sidebar (when collapsed)
    expandButton.addEventListener('click', function() {
        if (sidebar.classList.contains('collapsed')) {
            toggleSidebar();
        }
    });
    
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
    
    // Set initial margin
    updateMainContentMargin(isCollapsed);
    closeAllMenuGroupsWhenCollapsed();
    
    // Add tooltip data attributes to navigation links
    const navLinks = document.querySelectorAll('nav a');
    navLinks.forEach(link => {
        const textElement = link.querySelector('.sidebar-text');
        if (textElement) {
            link.setAttribute('data-tooltip', textElement.textContent.trim());
        }
    });
    
    // Menu groups functionality
    const menuGroups = ['usuarios', 'academico', 'gestao-escolar', 'conteudo', 'avaliacoes', 'comunicacao', 'sistema', 'relatorios', 'monitoramento', 'z-configuracao', 'financeiro', 'financeiro-escolar'];

    // Auto-open menu group if current page is inside it
    const currentPage = '<?= addslashes($current_page ?? '') ?>';
    const financePages = ['finance','finance_contracts','finance_plans','finance_charges','finance_charge_batch','finance_bills','finance_cashflow','finance_dre','finance_dfc','finance_balanco','finance_dmpl','finance_dlpa','finance_price','finance_discounts','finance_inadimplencia','finance_settings'];
    const autoOpenMap = {
        'enrollment': 'gestao-escolar',
        'faltas': 'gestao-escolar',
        'presenca': 'gestao-escolar',
        'diario_classe': 'gestao-escolar',
        'conselho_classe': 'gestao-escolar',
        'censo_escolar': 'gestao-escolar',
        'conformidade': 'gestao-escolar',
        'calendario_letivo': 'gestao-escolar',
        'grade_horaria': 'gestao-escolar',
        'ocorrencias': 'gestao-escolar',
        'reunioes': 'gestao-escolar',
        'saude_academica': 'gestao-escolar',
        'regras-academicas': 'academico',
        'resultados-finais': 'academico',
        'enrollment_config': 'z-configuracao',
        'assinatura_digital': 'z-configuracao',
        'boletim_config': 'z-configuracao',
        'boletim_guia': 'z-configuracao',
        'notas_semanais': 'z-configuracao',
        'maintenance_panel': 'z-configuracao',
        'settings': 'z-configuracao',
        'ui_modelos': 'z-configuracao',
        'dev': 'z-configuracao',
        'dev_tickets': 'z-configuracao',
        'monitoramento_alertas': 'monitoramento',
        'monitoramento': 'monitoramento',
        'tentativas_login': 'sistema',
        'essays_config': 'sistema',
        'reports': 'relatorios',
        'reports_boletim_coordenacao': 'relatorios',
        'reports_censo': 'relatorios',
        'usuarios': 'usuarios',
        'monitors': 'usuarios',
        'teachers': 'usuarios',
        'unidades': 'usuarios',
    };
    if (autoOpenMap[currentPage]) {
        localStorage.setItem(`menu-${autoOpenMap[currentPage]}-expanded`, 'true');
    }
    if (financePages.includes(currentPage)) {
        localStorage.setItem('menu-financeiro-escolar-expanded', 'true');
    }

    // Restore expanded state from localStorage
    menuGroups.forEach(group => {
        const isExpanded = localStorage.getItem(`menu-${group}-expanded`) === 'true';
        if (isExpanded) {
            const submenu = document.getElementById(`${group}-submenu`);
            const arrow = document.getElementById(`${group}-arrow`);
            if (submenu) {
                submenu.classList.remove('hidden');
                if (arrow) {
                    arrow.style.transform = 'rotate(180deg)';
                }
            }
        }
    });
});

// Toggle menu group function
function toggleMenuGroup(groupId) {
    const submenu = document.getElementById(`${groupId}-submenu`);
    const arrow = document.getElementById(`${groupId}-arrow`);

    if (submenu) {
        const isHidden = submenu.classList.contains('hidden');

        // Fechar todos os outros submenus antes de abrir o atual
        if (isHidden) {
            document.querySelectorAll('[id$="-submenu"]').forEach(el => {
                if (el.id !== `${groupId}-submenu` && !el.classList.contains('hidden')) {
                    el.classList.add('hidden');
                    const otherId = el.id.replace('-submenu', '');
                    const otherArrow = document.getElementById(`${otherId}-arrow`);
                    if (otherArrow) otherArrow.style.transform = 'rotate(0deg)';
                    localStorage.setItem(`menu-${otherId}-expanded`, 'false');
                }
            });
        }

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
</script>
