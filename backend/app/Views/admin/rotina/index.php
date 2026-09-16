<?php
if (!class_exists('AdminPermissionMatrix')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
}

$hub_title = 'Rotina';
$hub_subtitle = 'O dia a dia da escola: diário, frequência e ocorrências.';
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

if ($podeHub('diario_classe') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('diario_classe'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/diario',
        'title' => 'Diário de Classe',
        'description' => 'Aulas, frequência e registros do diário.',
        'icon' => 'fa-regular fa-address-book',
    ];
}

if ((!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('faltas'))
    || ($podeHub('presenca') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca')))) {
    $hub_cards[] = [
        'href' => URL . '/admin/frequencia',
        'title' => 'Frequência',
        'description' => 'Faltas da sala de aula e presença na portaria, no mesmo lugar.',
        'icon' => 'fa-solid fa-user-check',
    ];
}

if ($podeHub('ocorrencias') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/ocorrencias',
        'title' => 'Ocorrências',
        'description' => 'Registros pedagógicos e disciplinares.',
        'icon' => 'fa-regular fa-clock',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
