<?php
$hub_title = 'Relatórios';
$hub_subtitle = 'Consultas e exportações da escola.';
$hub_cards = [];

if (!class_exists('LayoutHelper') || \App\Core\LayoutHelper::isModuleEnabled('jornadas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/reports',
        'title' => 'Jornada do Aluno',
        'description' => 'Relatório de progresso nas jornadas de aprendizagem.',
        'icon' => 'fa-solid fa-chart-column',
    ];
}

include \App\Core\ViewPaths::adminPartial('hub_modulos.php');
