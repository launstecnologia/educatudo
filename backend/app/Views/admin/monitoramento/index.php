<?php
$hub_title = 'Monitoramento';
$hub_subtitle = 'Acompanhe alertas e a presença dos alunos em tempo real.';
$hub_cards = [];

$perfilAdmin = (string) ($user['perfil_admin'] ?? '');
$podeVerAlertas = !in_array($perfilAdmin, ['financeiro', 'secretaria'], true)
    && ($perfilAdmin === '' || in_array($perfilAdmin, ['dev', 'diretor', 'coordenador'], true));

if ($podeVerAlertas) {
    $hub_cards[] = [
        'href' => URL . '/admin/monitoramento/alertas',
        'title' => 'Alertas Sensíveis',
        'description' => 'Revise conteúdos sinalizados e tome as providências.',
        'icon' => 'fa-regular fa-clock',
    ];
}

$hub_cards[] = [
    'href' => '#',
    'title' => 'Alunos Online',
    'description' => 'Veja quem está conectado na plataforma neste momento.',
    'icon' => 'fa-solid fa-circle-check',
    'onclick' => 'abrirModalAlunosOnline(); return false;',
];

include __DIR__ . '/../_partials/hub_modulos.php';
