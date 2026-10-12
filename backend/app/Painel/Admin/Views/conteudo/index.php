<?php
$hub_title = 'Conteúdo';
$hub_subtitle = 'Materiais, arquivos e conteúdos da escola.';
$hub_cards = [
    [
        'href' => URL . '/admin/arquivos',
        'title' => 'Arquivos',
        'description' => 'Repositório de arquivos da escola para a equipe.',
        'icon' => 'fa-regular fa-folder',
    ],
];

if (class_exists('LayoutHelper') && !\App\Core\LayoutHelper::isModuleEnabled('aluno_arquivos')) {
    $hub_cards = [];
}

if (class_exists('LayoutHelper') && \App\Core\LayoutHelper::isModuleEnabled('educa_hits')) {
    if (!class_exists('EducaHitsConfig')) {
    }
    $hub_cards[] = [
        'href' => \App\Core\EducaHitsConfig::portalLoginUrl(),
        'title' => 'EducaHits (portal)',
        'description' => 'Abre o portal EducaHits em uma nova aba.',
        'icon' => 'fa-solid fa-music',
        'target' => '_blank',
    ];
}

if (!class_exists('LayoutHelper') || \App\Core\LayoutHelper::isModuleEnabled('aluno_apostilas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/apostilas-ia',
        'title' => 'Meu Material',
        'description' => 'Crie e organize materiais didáticos com apoio de IA.',
        'icon' => 'fa-solid fa-wand-magic-sparkles',
    ];
}

include \App\Core\ViewPaths::adminPartial('hub_modulos.php');
