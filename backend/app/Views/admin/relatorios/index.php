<?php
$hub_title = 'Relatórios';
$hub_subtitle = 'Consultas e exportações para coordenação e censo escolar.';
$hub_cards = [];

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('boletim')) {
    $hub_cards[] = [
        'href' => URL . '/admin/reports/boletim-coordenacao',
        'title' => 'Notas da Coordenação',
        'description' => 'Conferência por turma e PDF em lote do boletim oficial da Vida Escolar.',
        'icon' => 'fa-solid fa-file-signature',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('jornadas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/reports',
        'title' => 'Jornada do Aluno',
        'description' => 'Relatório de progresso nas jornadas de aprendizagem.',
        'icon' => 'fa-solid fa-chart-column',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
