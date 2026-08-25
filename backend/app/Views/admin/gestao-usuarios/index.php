<?php
$hub_title = 'Usuários';
$hub_subtitle = 'Administradores, monitores e permissões da escola.';
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
];

include __DIR__ . '/../_partials/hub_modulos.php';
