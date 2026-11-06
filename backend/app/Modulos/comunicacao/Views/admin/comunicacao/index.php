<?php
$modOn = static function (string $key): bool {
    return !class_exists('LayoutHelper') || \App\Core\LayoutHelper::isModuleEnabled($key);
};

$hub_title = 'Comunicação';
$hub_subtitle = 'Fórum, mural, avisos aos pais, calendário e reuniões da escola.';
$hub_cards = [
    [
        'href' => URL . '/admin/comunicacao-escolar',
        'title' => 'Comunicação Escolar',
        'description' => 'Avisos aos pais, com resposta, mídia e push opcional.',
        'icon' => 'fa-solid fa-envelope-open-text',
    ],
    [
        'href' => URL . '/admin/documentos-familia',
        'title' => 'Documentos da família',
        'description' => 'Arquivos enviados, ficha de saúde e declarações aceitas no aplicativo.',
        'icon' => 'fa-regular fa-folder-open',
    ],
];

if ($modOn('calendario_escolar')) {
    $hub_cards[] = [
        'href' => URL . '/admin/calendario-escolar',
        'title' => 'Avisos à família',
        'description' => 'Avisos já enviados aos responsáveis no app. O cadastro fica no Calendário Letivo.',
        'icon' => 'fa-regular fa-calendar-days',
    ];
}

if (!class_exists('LayoutHelper') || \App\Core\LayoutHelper::isModuleEnabled('forum')) {
    $hub_cards[] = [
        'href' => URL . '/forum',
        'title' => 'Fórum',
        'description' => 'Acesse o fórum da escola e acompanhe as discussões.',
        'icon' => 'fa-regular fa-comments',
    ];
    $hub_cards[] = [
        'href' => URL . '/forum/moderation/reports',
        'title' => 'Denúncias Fórum',
        'description' => 'Modere denúncias e conteúdos reportados no fórum.',
        'icon' => 'fa-solid fa-triangle-exclamation',
    ];
}

if (!class_exists('LayoutHelper') || \App\Core\LayoutHelper::isModuleEnabled('mural_recados')) {
    $hub_cards[] = [
        'href' => URL . '/admin/mural-recados',
        'title' => 'Mural de Recados',
        'description' => 'Recados no portal do aluno ou do professor, com sino e confirmação de leitura.',
        'icon' => 'fa-solid fa-bullhorn',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/reunioes/geral',
    'title' => 'Reuniões',
    'description' => 'Agende e registre reuniões da equipe escolar.',
    'icon' => 'fa-solid fa-people-group',
];

include __DIR__ . '/../_partials/hub_modulos.php';
