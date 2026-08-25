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

if (class_exists('LayoutHelper') && LayoutHelper::isModuleVisible('educa_hits')) {
    if (!class_exists('EducaHitsConfig')) {
        require_once dirname(__DIR__, 3) . '/Core/EducaHitsConfig.php';
    }
    $hub_cards[] = [
        'href' => EducaHitsConfig::portalLoginUrl(),
        'title' => 'EducaHits (portal)',
        'description' => 'Abre o portal EducaHits em uma nova aba.',
        'icon' => 'fa-solid fa-music',
        'target' => '_blank',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/apostilas-ia',
    'title' => 'Meu Material',
    'description' => 'Crie e organize materiais didáticos com apoio de IA.',
    'icon' => 'fa-solid fa-wand-magic-sparkles',
];

include __DIR__ . '/../_partials/hub_modulos.php';
