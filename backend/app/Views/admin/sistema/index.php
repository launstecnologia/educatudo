<?php
$hub_title = 'Sistema';
$hub_subtitle = 'Configurações de prompt de IA e tickets de suporte.';
$hub_cards = [];

$hub_cards[] = [
    'href' => URL . '/admin/avatares-alunos',
    'title' => 'Avatares dos Alunos',
    'description' => 'Adicione e remova os avatares 500x500px que os alunos podem escolher no perfil.',
    'icon' => 'fa-regular fa-images',
];

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('redacao_configuravel')) {
    $hub_cards[] = [
        'href' => URL . '/admin/redacao-configuravel',
        'title' => 'Configuração de Prompt',
        'description' => 'Ajuste os prompts usados na correção de redação.',
        'icon' => 'fa-solid fa-sliders',
    ];
}

if (($user['perfil_admin'] ?? '') === 'dev') {
    $hub_cards[] = [
        'href' => URL . '/admin/dev/tickets',
        'title' => 'Tickets',
        'description' => 'Tickets de suporte abertos pela escola.',
        'icon' => 'fa-solid fa-ticket',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
