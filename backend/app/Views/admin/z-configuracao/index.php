<?php
if (!class_exists('AdminPermissionMatrix')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
}

$hub_title = 'Z-Configuração';
$hub_subtitle = 'Parâmetros da instituição, manutenção e ferramentas internas.';
$hub_cards = [];

$permsHub = AdminPermissionMatrix::effectivePermissionsForUser(
    Database::getInstance(),
    $user ?? []
);
$podeHub = static function (string $chave) use ($permsHub): bool {
    return !empty($permsHub[$chave]['visualizar']);
};

if (($user['perfil_admin'] ?? '') === 'dev') {
    $hub_cards[] = [
        'href' => URL . '/admin/dev',
        'title' => 'Dev Settings',
        'description' => 'Ferramentas internas de desenvolvimento.',
        'icon' => 'fa-solid fa-code',
    ];
}

if ($podeHub('unidades')) {
    $hub_cards[] = [
        'href' => URL . '/admin/unidades',
        'title' => 'Instituição',
        'description' => 'Unidades, dados da escola e estrutura institucional.',
        'icon' => 'fa-solid fa-building',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/maintenance/painel',
    'title' => 'Modo Manutenção',
    'description' => 'Ative o modo manutenção e avise a comunidade escolar.',
    'icon' => 'fa-solid fa-screwdriver-wrench',
];

$hub_cards[] = [
    'href' => URL . '/admin/settings#slider-dashboard',
    'title' => 'Slider Dashboard',
    'description' => 'Banners e imagens do slider na área do aluno.',
    'icon' => 'fa-solid fa-images',
];

if (($user['perfil_admin'] ?? '') === 'dev') {
    $hub_cards[] = [
        'href' => URL . '/admin/configuracao/ui-modelos',
        'title' => 'UI Modelos',
        'description' => 'Modelos visuais e referências de interface.',
        'icon' => 'fa-solid fa-palette',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
