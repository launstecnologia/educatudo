<?php
$layout_config = $layout_config ?? [];
$escola_id = $escola_id ?? 0;
$csrf_token = $csrf_token ?? '';
$mobile_layout_default = [
    'enabled' => false,
    'breakpoint' => 768,
    'layout' => 'bottom-navigation',
    'order' => ['header', 'content', 'bottom_nav', 'fab'],
    'components' => ['header' => true, 'breadcrumb' => false, 'sidebar' => false],
    'bottom_nav' => ['enabled' => true, 'behavior' => 'fixed', 'highlight_index' => 2, 'items' => [
        ['label' => 'Dashboard', 'route' => '/dashboard', 'icon' => 'home'],
        ['label' => 'Exercícios', 'route' => '/exercicios', 'icon' => 'books'],
        ['label' => 'Tudinha', 'route' => '/chat', 'icon' => 'chat'],
        ['label' => 'Redações', 'route' => '/redacoes', 'icon' => 'pencil'],
        ['label' => 'Jogos', 'route' => '/jogos/access', 'icon' => 'game']
    ]],
    'fab' => ['enabled' => false, 'action' => ''],
    'appearance' => ['spacing' => 'compact', 'font_size' => 'base', 'density' => 'comfortable']
];
$mobile_layout_raw = $layout_config['mobile_layout_aluno'] ?? '';
$mobile_layout = $mobile_layout_raw !== '' ? (json_decode($mobile_layout_raw, true) ?: $mobile_layout_default) : $mobile_layout_default;
$mobile_layout = array_replace_recursive($mobile_layout_default, is_array($mobile_layout) ? $mobile_layout : []);
$bottom_nav_items = $mobile_layout['bottom_nav']['items'] ?? $mobile_layout_default['bottom_nav']['items'];
$bottom_nav_highlight = (int)($mobile_layout['bottom_nav']['highlight_index'] ?? 2);
$icons_available = ['home' => 'Casa', 'books' => 'Livros', 'chat' => 'Chat', 'pencil' => 'Redação', 'game' => 'Jogos', 'user' => 'Usuário'];
// Mapa legado (chave antiga) -> Font Awesome class para o formulário
$icon_legacy_to_fa = ['home' => 'fas fa-home', 'books' => 'fas fa-book', 'chat' => 'fas fa-comment-dots', 'pencil' => 'fas fa-pen', 'game' => 'fas fa-gamepad', 'user' => 'fas fa-user'];
// Banco de ícones Font Awesome (classe => label) para o seletor
$fa_icons = [
    'fas fa-home' => 'Casa', 'fas fa-book' => 'Livro', 'fas fa-books' => 'Livros', 'fas fa-comment-dots' => 'Chat',
    'fas fa-gamepad' => 'Jogos', 'fas fa-pen' => 'Redação', 'fas fa-user' => 'Usuário', 'fas fa-graduation-cap' => 'Formação',
    'fas fa-file-alt' => 'Documento', 'fas fa-star' => 'Estrela', 'fas fa-trophy' => 'Troféu', 'fas fa-clipboard-list' => 'Lista',
    'fas fa-calendar-alt' => 'Calendário', 'fas fa-bell' => 'Sino', 'fas fa-cog' => 'Config', 'fas fa-chart-line' => 'Gráfico',
    'fas fa-video' => 'Vídeo', 'fas fa-image' => 'Imagem', 'fas fa-music' => 'Música', 'fas fa-plus' => 'Mais',
    'fas fa-bullhorn' => 'Recados', 'fas fa-pencil-ruler' => 'Caderno', 'fas fa-newspaper' => 'Notícias',
    'fas fa-sign-out-alt' => 'Sair', 'fas fa-compass' => 'Explorar', 'fas fa-heart' => 'Favorito',
];
$navbar_links_aluno = [
    ['label' => 'Dashboard', 'route' => '/dashboard', 'icon' => 'home'],
    ['label' => 'Perfil / Editar perfil', 'route' => '/avatar', 'icon' => 'user'],
    ['label' => 'Tudinha (Chat)', 'route' => '/chat', 'icon' => 'chat'],
    ['label' => 'Exercícios', 'route' => '/exercicios', 'icon' => 'books'],
    ['label' => 'Redações', 'route' => '/redacoes', 'icon' => 'pencil'],
    ['label' => 'Jogos', 'route' => '/jogos/access', 'icon' => 'game'],
    ['label' => 'Jornadas', 'route' => '/jornadas', 'icon' => 'books'],
    ['label' => 'Simulados', 'route' => '/simulados', 'icon' => 'books'],
    ['label' => 'Mural de Recados', 'route' => '/mural-recados', 'icon' => 'books'],
    ['label' => 'Flashcards', 'route' => '/flashcards', 'icon' => 'books'],
    ['label' => 'Educa Livros', 'route' => '/livros', 'icon' => 'books'],
    ['label' => 'Minicursos', 'route' => '/minicursos', 'icon' => 'books'],
    ['label' => 'Meu Caderno', 'route' => '/caderno', 'icon' => 'pencil'],
    ['label' => 'Notícias', 'route' => '/dashboard#noticias', 'icon' => 'books'],
    ['label' => 'Sair', 'route' => '/logout?portal=aluno', 'icon' => 'user'],
];
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-6 mb-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-2">Exportar / Importar configuração</h3>
    <p class="text-sm text-slate-500 mb-4">Use para replicar módulos, layout e demais config desta escola em outra. Exporte em JSON e depois importe na escola de destino.</p>
    <div class="flex flex-wrap items-center gap-4 mb-4">
        <a href="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/exportar-config-json" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-medium bg-blue-600 text-white hover:bg-blue-700">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            Exportar configuração (JSON)
        </a>
    </div>
    <div class="pt-4 border-t border-slate-200">
        <label class="block text-sm font-medium text-slate-700 mb-2">Importar em esta escola (JSON exportado de outra)</label>
        <form method="post" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/importar-config-json" enctype="multipart/form-data" class="flex flex-wrap items-end gap-3">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="flex-1 min-w-[200px]">
                <textarea name="json_config" rows="3" placeholder="Cole aqui o JSON ou use o arquivo abaixo" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono focus:ring-2 focus:ring-blue-500 focus:border-blue-500"></textarea>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <input type="file" name="json_file" accept=".json,application/json" class="text-sm text-slate-500 file:mr-2 file:py-1.5 file:px-3 file:rounded file:border file:border-slate-300 file:text-xs file:font-medium">
                <button type="submit" class="px-4 py-2 rounded-lg text-sm font-medium bg-gray-800 text-white hover:bg-gray-700">Importar</button>
            </div>
        </form>
    </div>
</div>

<form method="post" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/layout" enctype="multipart/form-data" class="space-y-6">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Cores e Identidade Visual</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Cor primária</label>
                <div class="flex gap-2 items-center">
                    <input type="color" id="lc_color_picker" value="<?= htmlspecialchars($layout_config['primary_color'] ?? '#6366f1') ?>"
                           class="h-10 w-14 rounded border border-slate-300 cursor-pointer">
                    <input type="text" name="layout_primary_color" id="lc_color_text" value="<?= htmlspecialchars($layout_config['primary_color'] ?? '#6366f1') ?>"
                           class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Cor do texto (sobre primária)</label>
                <input type="text" name="layout_primary_text_color" value="<?= htmlspecialchars($layout_config['primary_text_color'] ?? '#ffffff') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Logos e Imagens</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <?php
            $imageFields = [
                ['label' => 'Logo principal', 'url_name' => 'layout_logo_url', 'file_name' => 'layout_logo_upload', 'config_key' => 'logo_url', 'preview_class' => 'h-20 object-contain'],
                ['label' => 'Logo quadrado (1x1)', 'url_name' => 'layout_logo_1x1_url', 'file_name' => 'layout_logo_1x1_upload', 'config_key' => 'logo_1x1_url', 'preview_class' => 'h-20 w-20 object-cover rounded-lg'],
                ['label' => 'Logo branca (modo dark/navbar)', 'url_name' => 'layout_logo_white_url', 'file_name' => 'layout_logo_white_upload', 'config_key' => 'logo_white_url', 'preview_class' => 'h-20 object-contain'],
                ['label' => 'Logo horizontal branca (modo dark/navbar)', 'url_name' => 'layout_logo_horizontal_white_url', 'file_name' => 'layout_logo_horizontal_white_upload', 'config_key' => 'logo_horizontal_white_url', 'preview_class' => 'h-20 object-contain'],
                ['label' => 'Capa da página de login', 'url_name' => 'layout_login_cover_url', 'file_name' => 'layout_login_cover_upload', 'config_key' => 'login_cover_url', 'preview_class' => 'h-24 w-full object-cover rounded-lg'],
            ];
            foreach ($imageFields as $idx => $field):
                $currentUrl = $layout_config[$field['config_key']] ?? '';
            ?>
            <div class="flex flex-col">
                <label class="block text-sm font-medium text-slate-700 mb-2"><?= $field['label'] ?></label>
                <div class="mb-2 flex items-center justify-center bg-slate-50 border-2 border-dashed border-slate-300 rounded-xl p-3 min-h-[100px]" id="preview-box-<?= $idx ?>">
                    <?php if ($currentUrl): ?>
                        <img src="<?= htmlspecialchars($currentUrl) ?>" class="<?= $field['preview_class'] ?>" id="preview-img-<?= $idx ?>">
                    <?php else: ?>
                        <span class="text-sm text-gray-400" id="preview-placeholder-<?= $idx ?>">Sem imagem</span>
                        <img src="" class="<?= $field['preview_class'] ?> hidden" id="preview-img-<?= $idx ?>">
                    <?php endif; ?>
                </div>
                <input type="text" name="<?= $field['url_name'] ?>" value="<?= htmlspecialchars($currentUrl) ?>"
                       placeholder="https://..." class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500 mb-1"
                       data-preview-idx="<?= $idx ?>" onchange="updatePreviewFromUrl(this)">
                <input type="file" name="<?= $field['file_name'] ?>" accept=".jpg,.jpeg,.png,.gif,.webp,.svg"
                       class="block w-full text-sm text-slate-500 file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100"
                       data-preview-idx="<?= $idx ?>" onchange="updatePreviewFromFile(this)">
            </div>
            <?php endforeach; ?>
        </div>
        <?php
        $logoOptions = [
            '' => 'Automático (conforme disponibilidade)',
            'logo' => 'Logo principal',
            'logo_horizontal' => 'Logo horizontal',
            'logo_1x1' => 'Logo quadrada (1x1)',
            'logo_white' => 'Logo branca (modo dark)',
            'logo_horizontal_white' => 'Logo horizontal branca (modo dark)',
        ];
        $currentLogoLogin = $layout_config['logo_use_login'] ?? '';
        $currentLogoNavbar = $layout_config['logo_use_navbar'] ?? '';
        ?>
        <div class="mt-6 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Logo na tela de login</label>
                <select name="layout_logo_use_login" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($logoOptions as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $currentLogoLogin === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 mt-1">Qual logo exibir na tela de login (aluno e professor).</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Logo na navbar (sidebar)</label>
                <select name="layout_logo_use_navbar" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($logoOptions as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $currentLogoNavbar === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 mt-1">Qual logo exibir no menu lateral do aluno e professor.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Tamanho da logo na página de login</label>
                <select name="layout_logo_size_login" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php
                    $sizes = ['1' => 'Normal (100%)', '1.25' => 'Grande (125%)', '1.5' => 'Muito grande (150%)', '2' => 'Extra grande (200%)'];
                    $currentLogin = $layout_config['logo_size_login'] ?? '1';
                    foreach ($sizes as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $currentLogin === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 mt-1">Ajusta o tamanho da logo exibida na tela de login do aluno.</p>
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Tamanho da logo na navbar (sidebar)</label>
                <select name="layout_logo_size_navbar" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php
                    $currentNavbar = $layout_config['logo_size_navbar'] ?? '1';
                    foreach ($sizes as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>" <?= $currentNavbar === $val ? 'selected' : '' ?>><?= $label ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-slate-500 mt-1">Ajusta o tamanho da logo no menu lateral (navbar) do aluno e professor.</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Títulos</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Título do sistema</label>
                <input type="text" name="layout_system_title" value="<?= htmlspecialchars($layout_config['system_title'] ?? 'EducaTudo') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Subtítulo</label>
                <input type="text" name="layout_system_subtitle" value="<?= htmlspecialchars($layout_config['system_subtitle'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Dashboard do Aluno – Jornadas ativas</h3>
        <p class="text-sm text-slate-600 mb-3">Escolha como a seção "Jornadas ativas" aparece no dashboard do aluno.</p>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Layout da seção Jornadas ativas</label>
            <select name="layout_jornadas_ativas" class="w-full sm:w-64 px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <option value="default" <?= ($layout_config['jornadas_ativas_layout'] ?? 'default') === 'default' ? 'selected' : '' ?>>Padrão (cards com título, matéria, professor e datas)</option>
                <option value="compact" <?= ($layout_config['jornadas_ativas_layout'] ?? '') === 'compact' ? 'selected' : '' ?>>Compacto (uma linha: título + botão)</option>
                <option value="list" <?= ($layout_config['jornadas_ativas_layout'] ?? '') === 'list' ? 'selected' : '' ?>>Lista (título + matéria + botão, sem datas em destaque)</option>
            </select>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Navbar do Aluno</h3>
        <div class="grid grid-cols-1 gap-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nome do Menu Colag (Navbar do Aluno)</label>
                <input type="text" name="layout_menu_colag_nome"
                       value="<?= htmlspecialchars($layout_config['menu_colag_nome'] ?? 'Colag') ?>"
                       placeholder="Ex.: Colag"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <p class="text-sm text-slate-500 mt-2">Nome do menu que aparece na navbar do aluno (ex.: nome da escola). Padrão: "Colag".</p>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Bottom Nav (Mobile – Aluno)</h3>
        <p class="text-sm text-slate-600 mb-4">Configure a barra inferior do app do aluno. O botão em destaque (índice) aparece com círculo e ícone invertido.</p>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous">
        <input type="hidden" name="layout_mobile_layout_aluno" id="layout_mobile_layout_aluno" value="">
        <div class="space-y-4">
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" id="mobile_nav_enabled" <?= !empty($mobile_layout['enabled']) ? 'checked' : '' ?>>
                Ativar layout mobile
            </label>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Breakpoint (px)</label>
                    <input type="number" id="mobile_nav_breakpoint" value="<?= (int)($mobile_layout['breakpoint'] ?? 768) ?>" min="320" max="1200" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Botão em destaque (índice)</label>
                    <p class="text-xs text-slate-500 mb-1">0 = 1º, 2 = 3º (meio). Esse botão fica com círculo.</p>
                    <input type="number" id="mobile_nav_highlight" value="<?= $bottom_nav_highlight ?>" min="0" max="9" class="w-full px-3 py-2 border border-slate-300 rounded-lg">
                </div>
            </div>
            <label class="flex items-center gap-2 text-sm text-slate-700">
                <input type="checkbox" id="mobile_nav_bottom_enabled" <?= !empty($mobile_layout['bottom_nav']['enabled']) ? 'checked' : '' ?>>
                Ativar barra inferior (Bottom Nav)
            </label>
            <div>
                <div class="flex flex-wrap items-center gap-2 mb-3">
                    <label class="block text-sm font-medium text-slate-700 w-full sm:w-auto">Itens do menu</label>
                    <div class="flex flex-wrap items-center gap-2 flex-1">
                        <select id="mobile_nav_preset_link" class="px-3 py-1.5 border border-slate-300 rounded-lg text-sm w-full sm:w-56">
                            <option value="">— Link do navbar —</option>
                            <?php foreach ($navbar_links_aluno as $nav): ?>
                                <option value="<?= htmlspecialchars(json_encode($nav)) ?>"><?= htmlspecialchars($nav['label']) ?> (<?= htmlspecialchars($nav['route']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="button" id="mobile_nav_add_preset" class="px-3 py-1.5 bg-blue-100 text-blue-700 rounded-lg text-sm font-medium hover:bg-blue-100">Adicionar este link</button>
                        <button type="button" id="mobile_nav_add_item" class="text-sm text-blue-600 hover:text-blue-800 font-medium">+ Adicionar item livre</button>
                    </div>
                </div>
                <div id="mobile_nav_items_list" class="space-y-3">
                    <?php foreach ($bottom_nav_items as $i => $item):
                        $curIcon = $item['icon'] ?? 'home';
                        $curIcon = $icon_legacy_to_fa[$curIcon] ?? (strpos($curIcon, 'fa-') !== false ? $curIcon : 'fas fa-home');
                    ?>
                    <div class="mobile-nav-row flex flex-wrap items-center gap-2 p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <input type="text" class="mobile-nav-label w-28 sm:w-32 px-2 py-1.5 border border-slate-300 rounded text-sm" placeholder="Label" value="<?= htmlspecialchars($item['label'] ?? '') ?>">
                        <input type="text" class="mobile-nav-route flex-1 min-w-0 px-2 py-1.5 border border-slate-300 rounded text-sm" placeholder="Rota (ex: /dashboard)" value="<?= htmlspecialchars($item['route'] ?? '') ?>">
                        <input type="hidden" class="mobile-nav-icon" value="<?= htmlspecialchars($curIcon) ?>">
                        <span class="mobile-nav-icon-preview w-8 h-8 flex items-center justify-center rounded border border-slate-300 bg-white text-slate-600"><i class="<?= htmlspecialchars($curIcon) ?>"></i></span>
                        <button type="button" class="mobile-nav-pick-icon px-2 py-1.5 border border-slate-300 rounded text-sm bg-white hover:bg-slate-50">Escolher ícone</button>
                        <button type="button" class="mobile-nav-remove px-2 py-1 text-red-600 hover:bg-red-50 rounded text-sm">Remover</button>
                    </div>
                    <?php endforeach; ?>
                </div>
                <template id="mobile_nav_row_tpl">
                    <div class="mobile-nav-row flex flex-wrap items-center gap-2 p-3 bg-slate-50 rounded-lg border border-slate-200">
                        <input type="text" class="mobile-nav-label w-28 sm:w-32 px-2 py-1.5 border border-slate-300 rounded text-sm" placeholder="Label">
                        <input type="text" class="mobile-nav-route flex-1 min-w-0 px-2 py-1.5 border border-slate-300 rounded text-sm" placeholder="Rota (ex: /dashboard)">
                        <input type="hidden" class="mobile-nav-icon" value="fas fa-home">
                        <span class="mobile-nav-icon-preview w-8 h-8 flex items-center justify-center rounded border border-slate-300 bg-white text-slate-600"><i class="fas fa-home"></i></span>
                        <button type="button" class="mobile-nav-pick-icon px-2 py-1.5 border border-slate-300 rounded text-sm bg-white hover:bg-slate-50">Escolher ícone</button>
                        <button type="button" class="mobile-nav-remove px-2 py-1 text-red-600 hover:bg-red-50 rounded text-sm">Remover</button>
                    </div>
                </template>
            </div>
            <!-- Modal banco de ícones (Font Awesome) -->
            <div id="icon-picker-modal" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-modal="true" role="dialog">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-black/50" id="icon-picker-backdrop"></div>
                    <div class="relative bg-white rounded-xl shadow-xl max-w-2xl w-full max-h-[80vh] overflow-hidden flex flex-col">
                        <div class="px-4 py-3 border-b border-slate-200 flex justify-between items-center">
                            <h4 class="text-lg font-semibold text-slate-800">Escolher ícone (Font Awesome)</h4>
                            <button type="button" id="icon-picker-close" class="p-2 text-slate-500 hover:bg-slate-100 rounded-lg">&times;</button>
                        </div>
                        <div class="p-4 overflow-y-auto flex-1 grid grid-cols-4 sm:grid-cols-6 gap-2">
                            <?php foreach ($fa_icons as $faClass => $faLabel): ?>
                                <button type="button" class="icon-picker-option flex flex-col items-center justify-center p-3 rounded-lg border border-slate-200 hover:bg-blue-50 hover:border-blue-300 text-slate-600 hover:text-blue-700" data-icon="<?= htmlspecialchars($faClass) ?>" title="<?= htmlspecialchars($faLabel) ?>">
                                    <i class="<?= htmlspecialchars($faClass) ?> text-xl mb-1"></i>
                                    <span class="text-xs truncate w-full text-center"><?= htmlspecialchars($faLabel) ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Salvar</button>
</form>

<script>
function updatePreviewFromFile(input) {
    var idx = input.getAttribute('data-preview-idx');
    var img = document.getElementById('preview-img-' + idx);
    var placeholder = document.getElementById('preview-placeholder-' + idx);
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            img.classList.remove('hidden');
            if (placeholder) placeholder.classList.add('hidden');
        };
        reader.readAsDataURL(input.files[0]);
    }
}
function updatePreviewFromUrl(input) {
    var idx = input.getAttribute('data-preview-idx');
    var img = document.getElementById('preview-img-' + idx);
    var placeholder = document.getElementById('preview-placeholder-' + idx);
    var url = input.value.trim();
    if (url) {
        img.src = url;
        img.classList.remove('hidden');
        if (placeholder) placeholder.classList.add('hidden');
    } else {
        img.src = '';
        img.classList.add('hidden');
        if (placeholder) placeholder.classList.remove('hidden');
    }
}
</script>

<script>
(function() {
    var picker = document.getElementById('lc_color_picker');
    var text = document.getElementById('lc_color_text');
    picker.oninput = function() { text.value = this.value; };
    text.oninput = function() { if (/^#[0-9A-Fa-f]{6}$/.test(this.value)) picker.value = this.value; };
})();

(function() {
    var form = document.querySelector('form[action*="/layout"]');
    var list = document.getElementById('mobile_nav_items_list');
    var tpl = document.getElementById('mobile_nav_row_tpl');
    var addBtn = document.getElementById('mobile_nav_add_item');
    var hiddenInput = document.getElementById('layout_mobile_layout_aluno');

    if (!list || !tpl || !form) return;

    list.addEventListener('click', function(e) {
        if (e.target.classList.contains('mobile-nav-remove')) {
            var row = e.target.closest('.mobile-nav-row');
            if (row && list.querySelectorAll('.mobile-nav-row').length > 1) row.remove();
        }
    });

    addBtn.addEventListener('click', function() {
        var clone = tpl.content.cloneNode(true);
        list.appendChild(clone);
    });

    var presetSelect = document.getElementById('mobile_nav_preset_link');
    var addPresetBtn = document.getElementById('mobile_nav_add_preset');
    if (presetSelect && addPresetBtn) {
        addPresetBtn.addEventListener('click', function() {
            var val = presetSelect.value;
            if (!val) return;
            var item;
            try { item = JSON.parse(val); } catch (e) { return; }
            var clone = tpl.content.cloneNode(true);
            clone.querySelector('.mobile-nav-label').value = item.label || '';
            clone.querySelector('.mobile-nav-route').value = item.route || '';
            var iconVal = (item.icon && item.icon.indexOf('fa-') !== -1) ? item.icon : (legacyToFa[item.icon] || 'fas fa-home');
            var iconInput = clone.querySelector('.mobile-nav-icon');
            var preview = clone.querySelector('.mobile-nav-icon-preview');
            if (iconInput) iconInput.value = iconVal;
            if (preview) preview.innerHTML = '<i class="' + iconVal.replace(/"/g, '&quot;') + '"></i>';
            list.appendChild(clone);
        });
    }

    var iconPickerModal = document.getElementById('icon-picker-modal');
    var iconPickerBackdrop = document.getElementById('icon-picker-backdrop');
    var iconPickerClose = document.getElementById('icon-picker-close');
    var iconPickerTargetRow = null;
    var legacyToFa = { home: 'fas fa-home', books: 'fas fa-book', chat: 'fas fa-comment-dots', pencil: 'fas fa-pen', game: 'fas fa-gamepad', user: 'fas fa-user' };
    function openIconPicker(row) {
        iconPickerTargetRow = row;
        if (iconPickerModal) iconPickerModal.classList.remove('hidden');
    }
    function closeIconPicker() {
        iconPickerTargetRow = null;
        if (iconPickerModal) iconPickerModal.classList.add('hidden');
    }
    list.addEventListener('click', function(e) {
        if (e.target.closest && e.target.closest('.mobile-nav-pick-icon')) {
            var row = e.target.closest('.mobile-nav-row');
            if (row) openIconPicker(row);
        }
    });
    if (iconPickerBackdrop) iconPickerBackdrop.addEventListener('click', closeIconPicker);
    if (iconPickerClose) iconPickerClose.addEventListener('click', closeIconPicker);
    document.querySelectorAll('.icon-picker-option').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var icon = this.getAttribute('data-icon');
            if (icon && iconPickerTargetRow) {
                var hidden = iconPickerTargetRow.querySelector('.mobile-nav-icon');
                var preview = iconPickerTargetRow.querySelector('.mobile-nav-icon-preview');
                if (hidden) hidden.value = icon;
                if (preview) preview.innerHTML = '<i class="' + icon.replace(/"/g, '&quot;') + '"></i>';
            }
            closeIconPicker();
        });
    });

    form.addEventListener('submit', function() {
        var enabled = document.getElementById('mobile_nav_enabled').checked;
        var breakpoint = parseInt(document.getElementById('mobile_nav_breakpoint').value, 10) || 768;
        var highlightIndex = Math.max(0, Math.min(9, parseInt(document.getElementById('mobile_nav_highlight').value, 10) || 0));
        var bottomEnabled = document.getElementById('mobile_nav_bottom_enabled').checked;
        var rows = list.querySelectorAll('.mobile-nav-row');
        var items = [];
        rows.forEach(function(row) {
            var label = (row.querySelector('.mobile-nav-label').value || '').trim();
            var route = (row.querySelector('.mobile-nav-route').value || '').trim();
            var iconInput = row.querySelector('.mobile-nav-icon');
            var icon = (iconInput && iconInput.value) ? iconInput.value : 'fas fa-home';
            if (label || route) items.push({ label: label || 'Item', route: route || '/', icon: icon });
        });
        if (items.length === 0) items = [{ label: 'Dashboard', route: '/dashboard', icon: 'fas fa-home' }];

        var config = {
            enabled: enabled,
            breakpoint: breakpoint,
            layout: 'bottom-navigation',
            order: ['header', 'content', 'bottom_nav', 'fab'],
            components: { header: true, breadcrumb: false, sidebar: false },
            bottom_nav: { enabled: bottomEnabled, behavior: 'fixed', highlight_index: highlightIndex, items: items },
            fab: { enabled: false, action: '' },
            appearance: { spacing: 'compact', font_size: 'base', density: 'comfortable' }
        };
        hiddenInput.value = JSON.stringify(config);
    });
})();
</script>
