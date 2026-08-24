<?php
$current_page = $current_page ?? '';
$master_nome = $master_nome ?? ($_SESSION['master_user_nome'] ?? 'Admin');
$master_avatar_url = $master_avatar_url ?? ($_SESSION['master_user_avatar'] ?? '');
if (!class_exists('MasterAvatarService')) {
    require_once __DIR__ . '/../../../Services/MasterAvatarService.php';
}
$master_iniciais = MasterAvatarService::iniciais($master_nome);

$navItemClass = static function (bool $active): string {
    return $active
        ? 'flex items-center px-3 py-2.5 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg transition-all duration-200'
        : 'flex items-center px-3 py-2.5 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 rounded-lg transition-all duration-200';
};

$iconClass = static function (bool $active): string {
    return 'w-5 h-5 mr-3 shrink-0 ' . ($active ? 'text-blue-600' : 'text-slate-500');
};
?>
<aside id="sidebar" class="w-64 md:w-64 border-r border-slate-200 h-full max-h-screen shrink-0 transition-all duration-300 ease-in-out fixed md:static md:left-0 left-0 top-0 z-50 bg-white flex flex-col overflow-hidden">
    <div class="p-6 pb-2 shrink-0">
        <div class="flex items-center justify-between mb-6">
            <div class="flex-1 min-w-0 flex items-center">
                <img src="<?= defined('URL') ? URL : '' ?>/assets/logos/logo-educatudo-black.png" alt="EducaTudo" class="max-h-12 w-auto object-contain">
            </div>
            <button id="sidebarToggle" type="button" class="text-slate-500 hover:bg-slate-100 p-2 rounded-lg transition-colors md:hidden" aria-label="Menu">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
            </button>
        </div>

        <div class="p-3 border border-slate-200 rounded-xl bg-white shadow-sm">
            <div class="flex items-center gap-3 min-w-0">
                <?php if (!empty($master_avatar_url)): ?>
                <img src="<?= htmlspecialchars($master_avatar_url) ?>" alt="<?= htmlspecialchars($master_nome) ?>"
                     class="w-10 h-10 rounded-full object-cover shrink-0 border border-slate-200">
                <?php else: ?>
                <div class="w-10 h-10 rounded-full bg-slate-800 flex items-center justify-center text-white text-sm font-semibold shrink-0">
                    <?= htmlspecialchars($master_iniciais) ?>
                </div>
                <?php endif; ?>
                <div class="min-w-0">
                    <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars($master_nome) ?></p>
                    <p class="text-xs text-slate-500">Admin Master</p>
                </div>
            </div>
        </div>
    </div>

    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain px-6 pb-6">
        <nav class="space-y-1">
            <?php
            $subItemClass = static function (bool $active): string {
                return $active
                    ? 'flex items-center px-3 py-2 text-sm font-medium text-blue-600 bg-blue-50 rounded-lg transition-all duration-200'
                    : 'flex items-center px-3 py-2 text-sm text-slate-600 hover:bg-slate-100 hover:text-slate-900 rounded-lg transition-all duration-200';
            };
            ?>

            <?php $active = $current_page === 'dashboard'; ?>
            <a href="<?= URL ?>/master/dashboard" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"></path>
                </svg>
                <span>Dashboard</span>
            </a>

            <?php $active = $current_page === 'escolas'; ?>
            <a href="<?= URL ?>/master/escolas" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
                <span>Escolas</span>
            </a>

            <?php
            $activePrecif = in_array(($current_page ?? ''), [
                'creditos_catalogo_tabelas',
                'creditos_catalogo_pacotes',
            ], true);
            $activeExtrato = ($current_page ?? '') === 'creditos_extrato';
            $activeLlmCustos = ($current_page ?? '') === 'llm_custos';
            $tudicoinsOpen = $activePrecif || $activeExtrato || $activeLlmCustos;
            ?>
            <div class="space-y-1">
                <button type="button"
                        class="w-full <?= $navItemClass($tudicoinsOpen) ?> justify-between"
                        onclick="(function(btn){var s=document.getElementById('tudicoins-submenu');var c=document.getElementById('tudicoins-chevron');if(!s)return;var open=s.classList.toggle('hidden');c&&c.classList.toggle('rotate-180',!open);btn.setAttribute('aria-expanded', open?'false':'true');})(this)"
                        aria-expanded="<?= $tudicoinsOpen ? 'true' : 'false' ?>"
                        aria-controls="tudicoins-submenu">
                    <span class="flex items-center min-w-0">
                        <svg class="<?= $iconClass($tudicoinsOpen) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <span>TudiCoins</span>
                    </span>
                    <svg id="tudicoins-chevron" class="w-4 h-4 shrink-0 text-slate-400 transition-transform duration-200 <?= $tudicoinsOpen ? 'rotate-180' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="tudicoins-submenu" class="<?= $tudicoinsOpen ? '' : 'hidden' ?> ml-4 space-y-1 border-l border-slate-200 pl-2">
                    <a href="<?= URL ?>/master/creditos-catalogo/tabelas" class="<?= $subItemClass($activePrecif) ?>">
                        <span>Precificação</span>
                    </a>
                    <a href="<?= URL ?>/master/creditos/extrato" class="<?= $subItemClass($activeExtrato) ?>">
                        <span>Extrato</span>
                    </a>
                    <a href="<?= URL ?>/master/llm-custos" class="<?= $subItemClass($activeLlmCustos) ?>">
                        <span>Custo LLM</span>
                    </a>
                </div>
            </div>

            <?php $active = ($current_page ?? '') === 'faturamento'; ?>
            <a href="<?= URL ?>/master/faturamento" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 14h6M7 10h10M5 6h14a2 2 0 012 2v10a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2z"></path>
                </svg>
                <span>Faturamento</span>
            </a>

            <?php $active = ($current_page ?? '') === 'fila_ia'; ?>
            <a href="<?= URL ?>/master/fila-ia" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                </svg>
                <span>Fila IA</span>
            </a>

            <?php $active = $current_page === 'migrations'; ?>
            <a href="<?= URL ?>/master/migrations" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path>
                </svg>
                <span>Migrations</span>
            </a>

            <?php $active = ($current_page ?? '') === 'logs'; ?>
            <a href="<?= URL ?>/master/logs" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Logs</span>
            </a>

            <?php if (!empty($_ENV['APP_DEBUG']) && $_ENV['APP_DEBUG'] === 'true'): ?>
            <?php $active = $current_page === 'performance'; ?>
            <a href="<?= URL ?>/master/performance" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                </svg>
                <span>Performance</span>
            </a>
            <?php endif; ?>

            <?php $active = ($current_page ?? '') === 'documentacao'; ?>
            <a href="<?= URL ?>/master/documentacao" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
                </svg>
                <span>Documentação</span>
            </a>

            <?php $active = $current_page === 'educa_hits'; ?>
            <a href="<?= URL ?>/master/educa-hits/pedidos" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19V6l12-3v13M9 19c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zm12-3c0 1.105-1.343 2-3 2s-3-.895-3-2 1.343-2 3-2 3 .895 3 2zM9 10l12-3"></path>
                </svg>
                <span>EducaHits</span>
            </a>

            <?php $active = $current_page === 'tickets'; ?>
            <a href="<?= URL ?>/master/tickets" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z"></path>
                </svg>
                <span>Tickets</span>
            </a>

            <?php $active = ($current_page ?? '') === 'log_provas'; ?>
            <a href="<?= URL ?>/master/log-provas" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <span>Log de Provas</span>
            </a>

            <?php
            $activeAsaas = ($current_page ?? '') === 'asaas';
            $configOpen = $activeAsaas;
            ?>
            <div class="space-y-1">
                <button type="button"
                        class="w-full <?= $navItemClass($configOpen) ?> justify-between"
                        onclick="(function(btn){var s=document.getElementById('config-submenu');var c=document.getElementById('config-chevron');if(!s)return;var open=s.classList.toggle('hidden');c&&c.classList.toggle('rotate-180',!open);btn.setAttribute('aria-expanded', open?'false':'true');})(this)"
                        aria-expanded="<?= $configOpen ? 'true' : 'false' ?>"
                        aria-controls="config-submenu">
                    <span class="flex items-center min-w-0">
                        <svg class="<?= $iconClass($configOpen) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                        </svg>
                        <span>Configuração</span>
                    </span>
                    <svg id="config-chevron" class="w-4 h-4 shrink-0 text-slate-400 transition-transform duration-200 <?= $configOpen ? 'rotate-180' : '' ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>
                <div id="config-submenu" class="<?= $configOpen ? '' : 'hidden' ?> ml-4 space-y-1 border-l border-slate-200 pl-2">
                    <a href="<?= URL ?>/master/asaas" class="<?= $subItemClass($activeAsaas) ?>">
                        <span>Asaas (pagamentos)</span>
                    </a>
                </div>
            </div>

            <?php $active = $current_page === 'usuarios'; ?>
            <a href="<?= URL ?>/master/usuarios" class="<?= $navItemClass($active) ?>">
                <svg class="<?= $iconClass($active) ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
                <span>Usuários Master</span>
            </a>

            <div class="pt-4 mt-4 border-t border-slate-200">
                <a href="<?= URL ?>/master/logout" class="flex items-center px-3 py-2.5 text-sm text-red-600 hover:bg-red-50 rounded-lg transition-all duration-200">
                    <svg class="w-5 h-5 mr-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                    </svg>
                    <span>Sair</span>
                </a>
            </div>
        </nav>
    </div>
</aside>
<style>
@media (max-width: 768px) {
    #sidebar {
        left: -100%;
        height: 100vh;
        max-height: 100dvh;
    }
    #sidebar.mobile-open { left: 0; }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var btn = document.getElementById('sidebarToggle');
    var sidebar = document.getElementById('sidebar');
    var overlay = document.getElementById('sidebar-overlay');
    if (btn && sidebar) {
        btn.addEventListener('click', function() {
            sidebar.classList.toggle('mobile-open');
            if (overlay) {
                overlay.classList.toggle('hidden', !sidebar.classList.contains('mobile-open'));
            }
        });
    }
    if (overlay) {
        overlay.addEventListener('click', function() {
            if (sidebar) sidebar.classList.remove('mobile-open');
            overlay.classList.add('hidden');
        });
    }
});
</script>
