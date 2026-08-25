<?php
if (!class_exists('FeatureGate')) {
    require_once dirname(__DIR__, 3) . '/Core/FeatureGate.php';
}

$hub_title = 'Pedagógico';
$hub_subtitle = 'Gerencie as atividades pedagógicas da escola.';
$hub_cards = [];

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aulas_online')) {
    $hub_cards[] = [
        'href' => URL . '/admin/aulas-online',
        'title' => 'Aulas Online',
        'description' => 'Gerencie as aulas ao vivo, gravações e salas virtuais.',
        'icon' => 'fa-solid fa-video',
    ];
}

if (class_exists('FeatureGate') && FeatureGate::isModuleEnabled('ead')) {
    $hub_cards[] = [
        'href' => URL . '/admin/ava',
        'title' => 'AVA / EAD',
        'description' => 'Ambiente virtual de aprendizagem e cursos a distância.',
        'icon' => 'fa-solid fa-graduation-cap',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/bncc',
    'title' => 'BNCC / Plano de Curso',
    'description' => 'Habilidades da BNCC e plano de curso da escola.',
    'icon' => 'fa-solid fa-list-check',
];

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aluno_minicursos')) {
    $hub_cards[] = [
        'href' => URL . '/admin/minicursos',
        'title' => 'EducaCursos',
        'description' => 'Crie e gerencie minicursos para alunos e professores.',
        'icon' => 'fa-solid fa-circle-play',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('professor_planos_aula')) {
    $hub_cards[] = [
        'href' => URL . '/admin/planos-aula',
        'title' => 'Plano de Aula',
        'description' => 'Visualize e aprove os planos de aula dos professores.',
        'icon' => 'fa-regular fa-file-lines',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
