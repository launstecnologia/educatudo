<?php
$hub_title = 'Sistema';
$hub_subtitle = 'Configurações de prompt de IA e tickets de suporte.';
$hub_cards = [
    [
        'href' => URL . '/admin/redacao-configuravel',
        'title' => 'Configuração de Prompt',
        'description' => 'Ajuste os prompts usados na correção de redação.',
        'icon' => 'fa-solid fa-sliders',
    ],
];

if (($user['perfil_admin'] ?? '') === 'dev') {
    $hub_cards[] = [
        'href' => URL . '/admin/dev/tickets',
        'title' => 'Tickets',
        'description' => 'Tickets de suporte abertos pela escola.',
        'icon' => 'fa-solid fa-ticket',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
