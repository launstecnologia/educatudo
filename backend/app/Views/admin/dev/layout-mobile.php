<?php
require_once __DIR__ . '/../../../Core/LayoutHelper.php';

$defaultStudent = [
    'enabled' => false,
    'breakpoint' => 768,
    'layout' => 'bottom-navigation',
    'order' => ['header', 'content', 'bottom_nav', 'fab'],
    'components' => [
        'header' => true,
        'breadcrumb' => false,
        'sidebar' => false
    ],
    'bottom_nav' => [
        'enabled' => true,
        'behavior' => 'fixed',
        'highlight_index' => 2,
        'items' => [
            ['label' => 'Dashboard', 'route' => '/dashboard', 'icon' => 'home'],
            ['label' => 'Exercícios', 'route' => '/exercicios', 'icon' => 'books'],
            ['label' => 'Tudinha', 'route' => '/chat', 'icon' => 'chat'],
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
        'font_size' => 'base',
        'density' => 'comfortable'
    ]
];

$defaultAdmin = [
    'enabled' => false,
    'breakpoint' => 768,
    'layout' => 'bottom-navigation',
    'order' => ['header', 'content', 'bottom_nav'],
    'components' => [
        'header' => true,
        'breadcrumb' => true,
        'sidebar' => false
    ],
    'bottom_nav' => [
        'enabled' => true,
        'behavior' => 'fixed',
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
        'font_size' => 'base',
        'density' => 'comfortable'
    ]
];

$studentConfig = json_decode(LayoutHelper::get('mobile_layout_aluno', ''), true);
$adminConfig = json_decode(LayoutHelper::get('mobile_layout_admin', ''), true);
$studentConfig = is_array($studentConfig) ? array_replace_recursive($defaultStudent, $studentConfig) : $defaultStudent;
$adminConfig = is_array($adminConfig) ? array_replace_recursive($defaultAdmin, $adminConfig) : $defaultAdmin;
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Layout Mobile (Aluno e Admin)</h2>
            <p class="text-sm text-gray-600 mt-1">Configurações mobile-first controladas pelo painel.</p>
        </div>
        <div class="p-6">
            <form id="layout-mobile-form" method="post" action="<?= URL ?>/admin/dev/layout/save" class="space-y-8">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="config[mobile_layout_aluno]" id="mobile_layout_aluno_json" value="">
                <input type="hidden" name="config[mobile_layout_admin]" id="mobile_layout_admin_json" value="">

                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Aluno</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="aluno_enabled" <?= $studentConfig['enabled'] ? 'checked' : '' ?>>
                            Ativar layout mobile
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Breakpoint (px)</label>
                            <input type="number" id="aluno_breakpoint" value="<?= (int)$studentConfig['breakpoint'] ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Layout padrão</label>
                            <select id="aluno_layout" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="sidebar" <?= $studentConfig['layout'] === 'sidebar' ? 'selected' : '' ?>>Sidebar</option>
                                <option value="bottom-navigation" <?= $studentConfig['layout'] === 'bottom-navigation' ? 'selected' : '' ?>>Bottom Navigation</option>
                                <option value="drawer" <?= $studentConfig['layout'] === 'drawer' ? 'selected' : '' ?>>Drawer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Espaçamento</label>
                            <select id="aluno_spacing" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="compact" <?= $studentConfig['appearance']['spacing'] === 'compact' ? 'selected' : '' ?>>Compacto</option>
                                <option value="comfortable" <?= $studentConfig['appearance']['spacing'] === 'comfortable' ? 'selected' : '' ?>>Confortável</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tamanho da fonte</label>
                            <select id="aluno_font_size" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="sm" <?= $studentConfig['appearance']['font_size'] === 'sm' ? 'selected' : '' ?>>Pequena</option>
                                <option value="base" <?= $studentConfig['appearance']['font_size'] === 'base' ? 'selected' : '' ?>>Normal</option>
                                <option value="lg" <?= $studentConfig['appearance']['font_size'] === 'lg' ? 'selected' : '' ?>>Grande</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="aluno_show_header" <?= $studentConfig['components']['header'] ? 'checked' : '' ?>>
                            Mostrar header
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="aluno_show_breadcrumb" <?= $studentConfig['components']['breadcrumb'] ? 'checked' : '' ?>>
                            Mostrar breadcrumb
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="aluno_show_sidebar" <?= $studentConfig['components']['sidebar'] ? 'checked' : '' ?>>
                            Mostrar sidebar
                        </label>
                    </div>
                    <div class="mt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="aluno_bottom_nav_enabled" <?= $studentConfig['bottom_nav']['enabled'] ? 'checked' : '' ?>>
                            Ativar Bottom Bar
                        </label>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Botão em destaque (índice)</label>
                            <p class="text-xs text-gray-500 mb-1">0 = primeiro, 2 = terceiro (meio). O botão fica com círculo e ícone invertido.</p>
                            <input type="number" id="aluno_bottom_nav_highlight_index" min="0" max="9" value="<?= (int)($studentConfig['bottom_nav']['highlight_index'] ?? 2) ?>" class="w-24 border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div class="mt-3">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Itens do Bottom Bar (JSON)</label>
                            <p class="text-xs text-gray-500 mb-1">Recomendado: 5 itens. Cada item: <code class="bg-gray-100 px-1 rounded">{"label": "Nome", "route": "/rota", "icon": "home"}</code>. Ícones: home, books, chat, pencil, game, user.</p>
                            <textarea id="aluno_bottom_nav_items" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs font-mono"><?= htmlspecialchars(json_encode($studentConfig['bottom_nav']['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></textarea>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="aluno_fab_enabled" <?= $studentConfig['fab']['enabled'] ? 'checked' : '' ?>>
                            Ativar FAB
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ação do FAB (rota)</label>
                            <input type="text" id="aluno_fab_action" value="<?= htmlspecialchars($studentConfig['fab']['action']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="/chat">
                        </div>
                    </div>
                </div>

                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="text-md font-semibold text-gray-900 mb-4">Admin</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="admin_enabled" <?= $adminConfig['enabled'] ? 'checked' : '' ?>>
                            Ativar layout mobile
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Breakpoint (px)</label>
                            <input type="number" id="admin_breakpoint" value="<?= (int)$adminConfig['breakpoint'] ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Layout padrão</label>
                            <select id="admin_layout" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="sidebar" <?= $adminConfig['layout'] === 'sidebar' ? 'selected' : '' ?>>Sidebar</option>
                                <option value="bottom-navigation" <?= $adminConfig['layout'] === 'bottom-navigation' ? 'selected' : '' ?>>Bottom Navigation</option>
                                <option value="drawer" <?= $adminConfig['layout'] === 'drawer' ? 'selected' : '' ?>>Drawer</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Espaçamento</label>
                            <select id="admin_spacing" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="compact" <?= $adminConfig['appearance']['spacing'] === 'compact' ? 'selected' : '' ?>>Compacto</option>
                                <option value="comfortable" <?= $adminConfig['appearance']['spacing'] === 'comfortable' ? 'selected' : '' ?>>Confortável</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tamanho da fonte</label>
                            <select id="admin_font_size" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                <option value="sm" <?= $adminConfig['appearance']['font_size'] === 'sm' ? 'selected' : '' ?>>Pequena</option>
                                <option value="base" <?= $adminConfig['appearance']['font_size'] === 'base' ? 'selected' : '' ?>>Normal</option>
                                <option value="lg" <?= $adminConfig['appearance']['font_size'] === 'lg' ? 'selected' : '' ?>>Grande</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="admin_show_header" <?= $adminConfig['components']['header'] ? 'checked' : '' ?>>
                            Mostrar header
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="admin_show_breadcrumb" <?= $adminConfig['components']['breadcrumb'] ? 'checked' : '' ?>>
                            Mostrar breadcrumb
                        </label>
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="admin_show_sidebar" <?= $adminConfig['components']['sidebar'] ? 'checked' : '' ?>>
                            Mostrar sidebar
                        </label>
                    </div>
                    <div class="mt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="admin_bottom_nav_enabled" <?= $adminConfig['bottom_nav']['enabled'] ? 'checked' : '' ?>>
                            Ativar Bottom Bar
                        </label>
                        <label class="block text-xs text-gray-500 mt-2">Itens do Bottom Bar (JSON)</label>
                        <textarea id="admin_bottom_nav_items" rows="5" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-xs"><?= htmlspecialchars(json_encode($adminConfig['bottom_nav']['items'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT)) ?></textarea>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <label class="flex items-center gap-2 text-sm text-gray-700">
                            <input type="checkbox" id="admin_fab_enabled" <?= $adminConfig['fab']['enabled'] ? 'checked' : '' ?>>
                            Ativar FAB
                        </label>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ação do FAB (rota)</label>
                            <input type="text" id="admin_fab_action" value="<?= htmlspecialchars($adminConfig['fab']['action']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="/admin/students">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Layout Mobile
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    function parseJsonTextarea(textareaId) {
        const raw = document.getElementById(textareaId)?.value || '[]';
        return JSON.parse(raw);
    }

    function buildMobileConfig(prefix) {
        const enabled = document.getElementById(`${prefix}_enabled`)?.checked || false;
        const breakpoint = parseInt(document.getElementById(`${prefix}_breakpoint`)?.value || '768', 10);
        const layout = document.getElementById(`${prefix}_layout`)?.value || 'bottom-navigation';
        const spacing = document.getElementById(`${prefix}_spacing`)?.value || 'compact';
        const fontSize = document.getElementById(`${prefix}_font_size`)?.value || 'base';

        const showHeader = document.getElementById(`${prefix}_show_header`)?.checked || false;
        const showBreadcrumb = document.getElementById(`${prefix}_show_breadcrumb`)?.checked || false;
        const showSidebar = document.getElementById(`${prefix}_show_sidebar`)?.checked || false;

        const bottomNavEnabled = document.getElementById(`${prefix}_bottom_nav_enabled`)?.checked || false;
        const bottomNavItems = parseJsonTextarea(`${prefix}_bottom_nav_items`);
        const bottomNavHighlightEl = document.getElementById(`${prefix}_bottom_nav_highlight_index`);
        const bottomNavHighlightIndex = bottomNavHighlightEl ? Math.max(0, Math.min(9, parseInt(bottomNavHighlightEl.value, 10) || 0)) : 2;
        const fabEnabled = document.getElementById(`${prefix}_fab_enabled`)?.checked || false;
        const fabAction = document.getElementById(`${prefix}_fab_action`)?.value || '';

        return {
            enabled,
            breakpoint: Number.isFinite(breakpoint) ? breakpoint : 768,
            layout,
            order: ['header', 'content', 'bottom_nav', 'fab'],
            components: {
                header: showHeader,
                breadcrumb: showBreadcrumb,
                sidebar: showSidebar
            },
            bottom_nav: {
                enabled: bottomNavEnabled,
                behavior: 'fixed',
                highlight_index: prefix === 'aluno' ? bottomNavHighlightIndex : undefined,
                items: Array.isArray(bottomNavItems) ? bottomNavItems : []
            },
            fab: {
                enabled: fabEnabled,
                action: fabAction
            },
            appearance: {
                spacing,
                font_size: fontSize,
                density: 'comfortable'
            }
        };
    }

    document.getElementById('layout-mobile-form')?.addEventListener('submit', function(event) {
        try {
            const alunoConfig = buildMobileConfig('aluno');
            const adminConfig = buildMobileConfig('admin');
            document.getElementById('mobile_layout_aluno_json').value = JSON.stringify(alunoConfig);
            document.getElementById('mobile_layout_admin_json').value = JSON.stringify(adminConfig);
        } catch (err) {
            event.preventDefault();
            alert('Erro no JSON do Bottom Bar. Verifique os itens informados.');
        }
    });
</script>

