<?php
if (!class_exists('AdminPermissionMatrix')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
}

$hub_title = 'Usuários';
$hub_subtitle = 'Administradores, professores, monitores e permissões da escola.';
$hub_cards = [
    [
        'href' => URL . '/admin/usuarios',
        'title' => 'Administradores',
        'description' => 'Cadastre usuários do painel e defina o perfil de acesso.',
        'icon' => 'fa-solid fa-user-shield',
    ],
    [
        'href' => URL . '/admin/monitors',
        'title' => 'Monitores',
        'description' => 'Gerencie monitores de prova e supervisão.',
        'icon' => 'fa-regular fa-eye',
    ],
    [
        'href' => URL . '/admin/permissoes-perfis',
        'title' => 'Perfis de Permissão',
        'description' => 'Monte perfis com o que cada administrador pode ver.',
        'icon' => 'fa-regular fa-id-card',
    ],
    [
        'href' => URL . '/admin/teachers',
        'title' => 'Professores',
        'description' => 'Cadastro de professores e vínculos com turmas.',
        'icon' => 'fa-solid fa-chalkboard-user',
    ],
];

$permsHub = AdminPermissionMatrix::effectivePermissionsForUser(
    Database::getInstance(),
    $user ?? []
);
if (!empty($permsHub['unidades']['visualizar'])) {
    $hub_cards[] = [
        'href' => URL . '/admin/unidades',
        'title' => 'Instituição',
        'description' => 'Unidades, dados da escola e estrutura institucional.',
        'icon' => 'fa-solid fa-building',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
