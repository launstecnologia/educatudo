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
                    <?php
                    $logoSidebar = LayoutHelper::getContextualLogo('sidebar', 'h-9 w-auto max-w-full object-contain', 'Logo');
                    if ($logoSidebar): ?>
                        <?= $logoSidebar ?>
                        <div class="sidebar-logo-fallback hidden">
                            <h1 class="text-xl font-bold text-white sidebar-text"><?= htmlspecialchars(LayoutHelper::getSystemTitle()) ?></h1>
                            <p class="text-sm text-purple-100 sidebar-text">Admin Panel</p>
                        </div>
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
                        <img src="<?= htmlspecialchars($user['avatar_url']) ?>" alt="<?= htmlspecialchars($user['nome'] ?? '') ?>" width="40" height="40" class="w-full h-full object-cover rounded-full" style="width:40px;height:40px;object-fit:cover;border-radius:50%" onerror="this.remove(); this.parentElement.querySelector('[data-avatar-initials]')?.classList.remove('hidden');">
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
        $modOn = static function (string $key): bool {
            return !class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled($key);
        };
        $showDashboardMenu = $canViewSidebar(['dashboard']);
        $showAlunosMenu = $canViewSidebar(['alunos']);
        $showUsuariosGroup = $canViewSidebar(['administradores']);
        $showAcademicoGroup = $canViewSidebar([
            'alunos', 'ano_letivo', 'calendario_letivo', 'materias',
            'curso', 'grade_horaria', 'matriz_curricular', 'professores', 'notas_semanais',
            'regras_academicas', 'salas', 'series', 'turmas',
        ]);
        $showInventoryGroup = $modOn('recursos_fisicos') && $canViewSidebar(['almoxarifado', 'patrimonio']);
        $cp = $current_page ?? '';
        $uriAtual = $_SERVER['REQUEST_URI'] ?? '';
        $curMovimentacao = in_array($cp, ['students_remanejamento', 'students_transferencia_escolar'], true);
        $forumDenunciasAtivo = strpos($uriAtual, '/forum/moderation') !== false;
        $forumAtivo = strpos($uriAtual, '/forum') !== false && !$forumDenunciasAtivo;
        $permissoesAtivo = strpos($uriAtual, '/admin/permissoes-perfis') !== false;
        $academicoOpen = in_array($cp, [
            'academico', 'students', 'ano_letivo', 'calendario_letivo',
            'componentes-curriculares', 'curso', 'grade_horaria', 'matriz-curricular',
            'teachers', 'notas_semanais', 'regras-academicas', 'salas', 'serie', 'turmas',
        ], true);
        $avaliacoesOpen = in_array($cp, [
            'avaliacoes', 'inclusao', 'provas', 'provas_blocos', 'exercises',
            'journeys', 'journeys_relatorio', 'essays_teacher', 'essays_teacher_report',
            'boletim_config', 'boletim_guia', 'reports_boletim_coordenacao', 'relatorios',
        ], true);
        $comunicacaoOpen = in_array($cp, [
            'comunicacao', 'school-communication', 'school-calendar', 'mural-recados', 'notifications',
            'notificacoes-push', 'reunioes_geral', 'reunioes',
        ], true) || $forumAtivo || $forumDenunciasAtivo;
        $conteudoOpen = in_array($cp, ['conteudo', 'arquivos', 'apostilas-ia', 'expo-colag'], true);
        $gestaoOpen = in_array($cp, [
            'gestao_escolar', 'censo_escolar', 'conformidade', 'conselho_classe',
            'diario_classe', 'faltas', 'presenca', 'documentos_institucionais',
            'assinatura_digital', 'modelos_documentos', 'enrollment', 'enrollment_config',
            'ocorrencias', 'almoxarifado', 'patrimonio', 'resultados-finais',
            'vida_escolar', 'vida_escolar_oficios',
            'saude_academica', 'tudicoins_escola', 'creditos_pacotes',
        ], true) || $curMovimentacao;
        $monitoramentoOpen = in_array($cp, ['monitoramento', 'monitoramento_alertas', 'tentativas_login'], true);
        $pedagogicoOpen = in_array($cp, ['pedagogico', 'aulas_online', 'planos-aula', 'ava', 'minicursos', 'bncc'], true);
        $sistemaOpen = in_array($cp, ['sistema', 'avatares_alunos', 'essays_config', 'dev_tickets'], true);
        $usuariosOpen = in_array($cp, ['gestao_usuarios', 'usuarios', 'monitors'], true) || $permissoesAtivo;
        $zConfigOpen = in_array($cp, ['z_configuracao', 'dev', 'unidades', 'maintenance_panel', 'settings', 'ui_modelos'], true);
        $boletimNestedOpen = in_array($cp, ['boletim_config', 'boletim_guia'], true);
        $forumNestedOpen = $forumDenunciasAtivo;
        $notificacoesNestedOpen = in_array($cp, ['notifications', 'notificacoes-push'], true);
        $diarioNestedOpen = in_array($cp, ['diario_classe', 'faltas', 'presenca'], true);
        $documentosNestedOpen = in_array($cp, ['documentos_institucionais', 'assinatura_digital', 'modelos_documentos'], true);
        $matriculasNestedOpen = in_array($cp, ['enrollment', 'enrollment_config'], true) || $curMovimentacao;
        $recursosNestedOpen = in_array($cp, ['almoxarifado', 'patrimonio'], true);
        $tudicoinsNestedOpen = in_array($cp, ['tudicoins_escola', 'creditos_pacotes'], true);
        $vidaEscolarNestedOpen = in_array($cp, ['vida_escolar', 'vida_escolar_oficios'], true);
        $cobrancasNestedOpen = in_array($cp, ['finance_charges', 'finance_charge_batch'], true);
        $finPages = [
            'finance', 'finance_contracts', 'finance_plans', 'finance_charges', 'finance_charge_batch',
            'finance_bills', 'finance_cashflow', 'finance_dre', 'finance_dfc', 'finance_balanco',
            'finance_dmpl', 'finance_dlpa', 'finance_price', 'finance_discounts',
            'finance_inadimplencia', 'finance_settings', 'financeiro',
        ];
        $finItems = [
            ['url' => '/admin/finance/reports/balanco', 'label' => 'Balanço Patrimonial', 'page' => 'finance_balanco', 'icon' => 'fa-scale-balanced'],
            [
                'url' => '/admin/finance/charges',
                'label' => 'Cobranças Avulsas',
                'page' => 'finance_charges',
                'icon' => 'fa-bolt',
                'children' => [
                    ['url' => '/admin/finance/charges/batch', 'label' => 'Nova em Lote', 'page' => 'finance_charge_batch'],
                ],
            ],
            ['url' => '/admin/finance/settings', 'label' => 'Configurações', 'page' => 'finance_settings', 'icon' => 'fa-sliders'],
            ['url' => '/admin/finance/bills', 'label' => 'Contas a Pagar', 'page' => 'finance_bills', 'icon' => 'fa-file-invoice-dollar'],
            ['url' => '/admin/finance/contracts', 'label' => 'Contratos', 'page' => 'finance_contracts', 'icon' => 'fa-file-contract'],
            ['url' => '/admin/finance', 'label' => 'Dashboard', 'page' => 'finance', 'icon' => 'fa-gauge-high'],
            ['url' => '/admin/finance/discount-rules', 'label' => 'Descontos', 'page' => 'finance_discounts', 'icon' => 'fa-percent'],
            ['url' => '/admin/finance/reports/dfc', 'label' => 'DFC', 'page' => 'finance_dfc', 'icon' => 'fa-arrow-right-arrow-left'],
            ['url' => '/admin/finance/reports/dlpa', 'label' => 'DLPA', 'page' => 'finance_dlpa', 'icon' => 'fa-trophy'],
            ['url' => '/admin/finance/reports/dmpl', 'label' => 'DMPL', 'page' => 'finance_dmpl', 'icon' => 'fa-table'],
            ['url' => '/admin/finance/reports/dre', 'label' => 'DRE', 'page' => 'finance_dre', 'icon' => 'fa-chart-bar'],
            ['url' => '/admin/finance/cashflow', 'label' => 'Fluxo de Caixa', 'page' => 'finance_cashflow', 'icon' => 'fa-water'],
            ['url' => '/admin/finance/report/inadimplencia', 'label' => 'Inadimplência', 'page' => 'finance_inadimplencia', 'icon' => 'fa-chart-line'],
            ['url' => '/admin/finance/plans', 'label' => 'Planos e Preços', 'page' => 'finance_plans', 'icon' => 'fa-layer-group'],
        ];
        $renderFinNav = static function (array $items, string $cpFin) use ($cobrancasNestedOpen): void {
            foreach ($items as $fi) {
                $active = $cpFin === $fi['page'];
                $children = $fi['children'] ?? [];
                $childActive = false;
                foreach ($children as $ch) {
                    if ($cpFin === ($ch['page'] ?? '')) {
                        $childActive = true;
                        break;
                    }
                }
                $nestedId = 'fin-' . preg_replace('/[^a-z0-9]+/i', '-', (string) $fi['page']);
                $nestedOpen = $active || $childActive || ($fi['page'] === 'finance_charges' && $cobrancasNestedOpen);
                if ($children !== []) {
                    ?>
                    <div class="flex items-center rounded-lg <?= $active ? 'bg-white/20' : '' ?>">
                        <a href="<?= URL . $fi['url'] ?>" class="flex-1 flex items-center px-4 py-2 text-sm <?= $active ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                            <i class="fa-solid <?= $fi['icon'] ?> w-4 h-4 mr-3 flex-shrink-0"></i>
                            <span class="sidebar-text"><?= $fi['label'] ?></span>
                        </a>
                        <button type="button" onclick="toggleNestedMenu('<?= htmlspecialchars($nestedId, ENT_QUOTES) ?>')" class="px-2 py-2 text-purple-100 hover:text-white transition-colors" title="Expandir submenu">
                            <svg id="<?= htmlspecialchars($nestedId) ?>-arrow" class="w-3 h-3 transition-transform duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </div>
                    <div id="<?= htmlspecialchars($nestedId) ?>-nested" class="<?= $nestedOpen ? '' : 'hidden' ?> ml-6 mt-1 space-y-1 border-l border-white/20 pl-2">
                        <?php foreach ($children as $ch): ?>
                        <a href="<?= URL . $ch['url'] ?>" class="flex items-center px-4 py-2 text-sm <?= $cpFin === $ch['page'] ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                            <span class="sidebar-text"><?= $ch['label'] ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                    <?php
                } else {
                    ?>
                    <a href="<?= URL . $fi['url'] ?>" class="flex items-center px-4 py-2 text-sm <?= $active ? 'text-white bg-white/20' : 'text-purple-100 hover:bg-white/20 hover:text-white' ?> rounded-lg transition-all duration-200">
                        <i class="fa-solid <?= $fi['icon'] ?> w-4 h-4 mr-3 flex-shrink-0"></i>
                        <span class="sidebar-text"><?= $fi['label'] ?></span>
                    </a>
                    <?php
                }
            }
        };
        ?>
        <nav class="admin-sidebar-nav space-y-0.5">
            <?php if (($user['perfil_admin'] ?? '') === 'financeiro'): ?>
                <?php if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('financeiro')): ?>
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
                        <?php $renderFinNav($finItems, $cp); ?>
                    </div>
                </div>
                <?php endif; ?>

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
            <?php require __DIR__ . '/admin_sidebar_menu_principal.php'; ?>

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
        const menuGroups = ['academico', 'avaliacoes', 'comunicacao', 'conteudo', 'financeiro', 'financeiro-escolar', 'gestao-escolar', 'monitoramento', 'pedagogico', 'sistema', 'usuarios', 'z-configuracao', 'sec-academico', 'sec-avaliacoes', 'sec-gestao-escolar'];
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
    const menuGroups = ['academico', 'avaliacoes', 'comunicacao', 'conteudo', 'financeiro', 'financeiro-escolar', 'gestao-escolar', 'monitoramento', 'pedagogico', 'sistema', 'usuarios', 'z-configuracao', 'sec-academico', 'sec-avaliacoes', 'sec-gestao-escolar'];

    // Auto-open menu group if current page is inside it
    const currentPage = '<?= addslashes($current_page ?? '') ?>';
    const financePages = ['finance','finance_contracts','finance_plans','finance_charges','finance_charge_batch','finance_bills','finance_cashflow','finance_dre','finance_dfc','finance_balanco','finance_dmpl','finance_dlpa','finance_price','finance_discounts','finance_inadimplencia','finance_settings','financeiro'];
    const autoOpenMap = {
        'academico': 'academico',
        'students': 'academico',
        'ano_letivo': 'academico',
        'calendario_letivo': 'academico',
        'componentes-curriculares': 'academico',
        'curso': 'academico',
        'grade_horaria': 'academico',
        'matriz-curricular': 'academico',
        'teachers': 'academico',
        'notas_semanais': 'academico',
        'regras-academicas': 'academico',
        'salas': 'academico',
        'serie': 'academico',
        'turmas': 'academico',
        'avaliacoes': 'avaliacoes',
        'inclusao': 'avaliacoes',
        'provas': 'avaliacoes',
        'provas_blocos': 'avaliacoes',
        'exercises': 'avaliacoes',
        'journeys': 'avaliacoes',
        'journeys_relatorio': 'avaliacoes',
        'essays_teacher': 'avaliacoes',
        'essays_teacher_report': 'avaliacoes',
        'boletim_config': 'avaliacoes',
        'boletim_guia': 'avaliacoes',
        'reports_boletim_coordenacao': 'avaliacoes',
        'relatorios': 'avaliacoes',
        'comunicacao': 'comunicacao',
        'school-communication': 'comunicacao',
        'school-calendar': 'comunicacao',
        'mural-recados': 'comunicacao',
        'notifications': 'comunicacao',
        'notificacoes-push': 'comunicacao',
        'reunioes': 'comunicacao',
        'reunioes_geral': 'comunicacao',
        'conteudo': 'conteudo',
        'arquivos': 'conteudo',
        'apostilas-ia': 'conteudo',
        'expo-colag': 'conteudo',
        'gestao_escolar': 'gestao-escolar',
        'censo_escolar': 'gestao-escolar',
        'conformidade': 'gestao-escolar',
        'conselho_classe': 'gestao-escolar',
        'diario_classe': 'gestao-escolar',
        'faltas': 'gestao-escolar',
        'presenca': 'gestao-escolar',
        'documentos_institucionais': 'gestao-escolar',
        'assinatura_digital': 'gestao-escolar',
        'modelos_documentos': 'gestao-escolar',
        'enrollment': 'gestao-escolar',
        'enrollment_config': 'gestao-escolar',
        'students_remanejamento': 'gestao-escolar',
        'students_transferencia_escolar': 'gestao-escolar',
        'ocorrencias': 'gestao-escolar',
        'almoxarifado': 'gestao-escolar',
        'patrimonio': 'gestao-escolar',
        'resultados-finais': 'gestao-escolar',
        'vida_escolar': 'gestao-escolar',
        'vida_escolar_oficios': 'gestao-escolar',
        'saude_academica': 'gestao-escolar',
        'tudicoins_escola': 'gestao-escolar',
        'creditos_pacotes': 'gestao-escolar',
        'monitoramento': 'monitoramento',
        'monitoramento_alertas': 'monitoramento',
        'tentativas_login': 'monitoramento',
        'pedagogico': 'pedagogico',
        'aulas_online': 'pedagogico',
        'planos-aula': 'pedagogico',
        'ava': 'pedagogico',
        'minicursos': 'pedagogico',
        'bncc': 'pedagogico',
        'sistema': 'sistema',
        'essays_config': 'sistema',
        'dev_tickets': 'sistema',
        'gestao_usuarios': 'usuarios',
        'usuarios': 'usuarios',
        'monitors': 'usuarios',
        'z_configuracao': 'z-configuracao',
        'dev': 'z-configuracao',
        'unidades': 'z-configuracao',
        'maintenance_panel': 'z-configuracao',
        'settings': 'z-configuracao',
        'ui_modelos': 'z-configuracao',
    };
    if (autoOpenMap[currentPage]) {
        let group = autoOpenMap[currentPage];
        if (document.getElementById(`sec-${group}-submenu`)) {
            group = `sec-${group}`;
        }
        localStorage.setItem(`menu-${group}-expanded`, 'true');
    }
    if (financePages.includes(currentPage)) {
        const finGroup = document.getElementById('financeiro-submenu') && !document.getElementById('financeiro-escolar-submenu')
            ? 'financeiro'
            : 'financeiro-escolar';
        localStorage.setItem(`menu-${finGroup}-expanded`, 'true');
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

    // PHP já abre o grupo da página atual; garante a seta girada
    menuGroups.forEach(group => {
        const submenu = document.getElementById(`${group}-submenu`);
        const arrow = document.getElementById(`${group}-arrow`);
        if (submenu && !submenu.classList.contains('hidden') && arrow) {
            arrow.style.transform = 'rotate(180deg)';
        }
    });

    document.querySelectorAll('[id$="-nested"]').forEach(el => {
        const nestedId = el.id.replace(/-nested$/, '');
        const arrow = document.getElementById(`${nestedId}-arrow`);
        if (localStorage.getItem(`menu-${nestedId}-expanded`) === 'true') {
            el.classList.remove('hidden');
        }
        if (!el.classList.contains('hidden') && arrow) {
            arrow.style.transform = 'rotate(180deg)';
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

function toggleNestedMenu(groupId) {
    const submenu = document.getElementById(`${groupId}-nested`);
    const arrow = document.getElementById(`${groupId}-arrow`);
    if (!submenu) {
        return;
    }
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
</script>
