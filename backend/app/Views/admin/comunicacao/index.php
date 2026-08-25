<?php
$hub_title = 'Comunicação';
$hub_subtitle = 'Fórum, recados, avisos e notificações da escola.';
$hub_cards = [
    [
        'href' => URL . '/forum/moderation/reports',
        'title' => 'Denúncias Fórum',
        'description' => 'Modere denúncias e conteúdos reportados no fórum.',
        'icon' => 'fa-solid fa-triangle-exclamation',
    ],
    [
        'href' => URL . '/forum',
        'title' => 'Fórum',
        'description' => 'Acesse o fórum da escola e acompanhe as discussões.',
        'icon' => 'fa-regular fa-comments',
    ],
    [
        'href' => URL . '/admin/mural-recados',
        'title' => 'Mural de Recados',
        'description' => 'Publique recados para alunos, pais e professores.',
        'icon' => 'fa-solid fa-bullhorn',
    ],
    [
        'href' => URL . '/admin/comunicacao-escolar',
        'title' => 'Comunicação Escolar',
        'description' => 'Envie comunicados oficiais para a comunidade escolar.',
        'icon' => 'fa-solid fa-envelope-open-text',
    ],
    [
        'href' => URL . '/admin/notifications',
        'title' => 'Notificações',
        'description' => 'Histórico e envio de notificações internas.',
        'icon' => 'fa-regular fa-bell',
    ],
    [
        'href' => URL . '/admin/notificacoes-push',
        'title' => 'Notificações Push',
        'description' => 'Dispare avisos push para o aplicativo da família.',
        'icon' => 'fa-solid fa-bell-concierge',
    ],
];
include __DIR__ . '/../_partials/hub_modulos.php';
