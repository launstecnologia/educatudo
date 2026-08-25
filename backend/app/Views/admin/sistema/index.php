<?php
$hub_title = 'Sistema';
$hub_subtitle = 'Configurações de prompt de IA e segurança de acesso.';
$hub_cards = [
    [
        'href' => URL . '/admin/redacao-configuravel',
        'title' => 'Configuração de Prompt',
        'description' => 'Ajuste os prompts usados na correção de redação.',
        'icon' => 'fa-solid fa-sliders',
    ],
    [
        'href' => URL . '/admin/tentativas-login',
        'title' => 'Tentativas de login',
        'description' => 'Audite tentativas de acesso à plataforma.',
        'icon' => 'fa-solid fa-lock',
    ],
];
include __DIR__ . '/../_partials/hub_modulos.php';
