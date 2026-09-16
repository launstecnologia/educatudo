<?php
if (!class_exists('AdminPermissionMatrix')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
}

$hub_title = 'Painéis';
$hub_subtitle = 'Indicadores, saúde acadêmica, conformidade e relatórios.';
$hub_cards = [];

$permsHub = [];
if (class_exists('AdminPermissionMatrix')) {
    $permsHub = AdminPermissionMatrix::effectivePermissionsForUser(
        Database::getInstance(),
        $user ?? []
    );
}
$podeHub = static function (string $chave) use ($permsHub): bool {
    return !empty($permsHub[$chave]['visualizar']);
};

if ($podeHub('dashboard')) {
    $hub_cards[] = [
        'href' => URL . '/admin/dashboard',
        'title' => 'Dashboard',
        'description' => 'Visão geral da escola e ponto de partida do painel.',
        'icon' => 'fa-solid fa-gauge-high',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('saude_academica')) {
    $hub_cards[] = [
        'href' => URL . '/admin/saude-academica',
        'title' => 'Saúde Acadêmica',
        'description' => 'Desempenho, frequência e risco pedagógico.',
        'icon' => 'fa-solid fa-heart-pulse',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conformidade')) {
    $hub_cards[] = [
        'href' => URL . '/admin/conformidade',
        'title' => 'Conformidade',
        'description' => 'Pendências operacionais da escola.',
        'icon' => 'fa-solid fa-clipboard-check',
    ];
}

if ($podeHub('relatorios_gerais')) {
    $hub_cards[] = [
        'href' => URL . '/admin/relatorios',
        'title' => 'Relatórios',
        'description' => 'Relatórios acadêmicos e da coordenação.',
        'icon' => 'fa-solid fa-chart-column',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
