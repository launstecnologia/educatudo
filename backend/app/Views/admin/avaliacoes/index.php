<?php
if (!class_exists('FeatureGate')) {
    require_once dirname(__DIR__, 3) . '/Core/FeatureGate.php';
}

$hub_title = 'Avaliações';
$hub_subtitle = 'Gerencie avaliações, exercícios e jornadas de aprendizagem.';
$hub_cards = [
    [
        'href' => URL . '/admin/provas',
        'title' => 'Avaliações / Notas',
        'description' => 'Crie provas, aplique e registre notas dos alunos.',
        'icon' => 'fa-regular fa-clipboard',
    ],
];

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleVisible('exercicios')) {
    $hub_cards[] = [
        'href' => URL . '/admin/exercises',
        'title' => 'Exercícios',
        'description' => 'Banco de exercícios e atividades para os alunos.',
        'icon' => 'fa-solid fa-check-double',
    ];
}

if (class_exists('FeatureGate') && FeatureGate::isModuleEnabled('jornadas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/jornadas',
        'title' => 'Jornada do Aluno',
        'description' => 'Trilhas de aprendizagem personalizadas por aluno.',
        'icon' => 'fa-solid fa-route',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/redacao-professor',
    'title' => 'Jornada da Redação',
    'description' => 'Acompanhe as redações enviadas e corrija com IA.',
    'icon' => 'fa-solid fa-pen-to-square',
];

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleVisible('inclusao')) {
    $hub_cards[] = [
        'href' => URL . '/admin/inclusao',
        'title' => 'EducaInclui',
        'description' => 'Versões adaptadas de avaliações para alunos com necessidades especiais.',
        'icon' => 'fa-solid fa-universal-access',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
