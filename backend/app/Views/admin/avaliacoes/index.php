<?php
if (!class_exists('FeatureGate')) {
    require_once dirname(__DIR__, 3) . '/Core/FeatureGate.php';
}

$hub_title = 'Avaliações';
$hub_subtitle = 'Gerencie avaliações, jornadas e o boletim da escola.';
$hub_cards = [];

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('inclusao')) {
    $hub_cards[] = [
        'href' => URL . '/admin/inclusao/versoes',
        'title' => 'Avaliação Adaptativa',
        'description' => 'Versões adaptadas de avaliações para alunos com necessidades especiais.',
        'icon' => 'fa-solid fa-universal-access',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('professor_provas') || LayoutHelper::isModuleEnabled('aluno_provas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/provas',
        'title' => 'Avaliações / Notas',
        'description' => 'Crie provas, aplique e registre notas dos alunos.',
        'icon' => 'fa-regular fa-clipboard',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('redacao_configuravel')) {
    $hub_cards[] = [
        'href' => URL . '/admin/redacao-professor',
        'title' => 'Jornada da Redação',
        'description' => 'Acompanhe as redações enviadas e corrija com IA.',
        'icon' => 'fa-solid fa-pen-to-square',
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

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('boletim')) {
    $hub_cards[] = [
        'href' => URL . '/admin/boletim',
        'title' => 'Notas e Boletim',
        'description' => 'Modelo de boletim, pesos e exibição de notas.',
        'icon' => 'fa-regular fa-file-lines',
    ];
    $hub_cards[] = [
        'href' => URL . '/admin/boletim-guia',
        'title' => 'Guia do Boletim',
        'description' => 'Documentação de como o boletim é calculado e exibido.',
        'icon' => 'fa-solid fa-book-open',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
