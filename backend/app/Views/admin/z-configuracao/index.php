<?php
if (!class_exists('AdminPermissionMatrix')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
}

$hub_title = 'Z-Configuração';
$hub_subtitle = 'Ajustes de boletim, matrícula, manutenção e parâmetros da escola.';
$hub_cards = [];

$permsHub = AdminPermissionMatrix::effectivePermissionsForUser(
    Database::getInstance(),
    $user ?? []
);
$podeHub = static function (string $chave) use ($permsHub): bool {
    return !empty($permsHub[$chave]['visualizar']);
};

if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula') && $podeHub('processos_matricula')) {
    $hub_cards[] = [
        'href' => URL . '/admin/enrollment/config',
        'title' => 'Configuração de Matrícula',
        'description' => 'Etapas, documentos e regras do processo de matrícula.',
        'icon' => 'fa-solid fa-graduation-cap',
    ];
    $hub_cards[] = [
        'href' => URL . '/admin/configuracao/assinatura-digital',
        'title' => 'Assinatura Digital',
        'description' => 'Configure a assinatura digital dos documentos.',
        'icon' => 'fa-solid fa-pen-nib',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/boletim',
    'title' => 'Notas e Boletim',
    'description' => 'Modelo de boletim, pesos e exibição de notas.',
    'icon' => 'fa-regular fa-file-lines',
];

if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('notas_semanais') && $podeHub('notas_semanais')) {
    $hub_cards[] = [
        'href' => URL . '/admin/notas-semanais',
        'title' => 'Quadro semanal',
        'description' => 'Configure o quadro de notas semanais da escola.',
        'icon' => 'fa-solid fa-table',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/boletim-guia',
    'title' => 'Guia do Boletim',
    'description' => 'Documentação de como o boletim é calculado e exibido.',
    'icon' => 'fa-solid fa-book-open',
];

$hub_cards[] = [
    'href' => URL . '/admin/maintenance/painel',
    'title' => 'Modo Manutenção',
    'description' => 'Ative o modo manutenção e avise a comunidade escolar.',
    'icon' => 'fa-solid fa-screwdriver-wrench',
];

$hub_cards[] = [
    'href' => URL . '/admin/settings#slider-dashboard',
    'title' => 'Slider Dashboard',
    'description' => 'Banners e imagens do slider na área do aluno.',
    'icon' => 'fa-solid fa-images',
];

if (($user['perfil_admin'] ?? '') === 'dev') {
    $hub_cards[] = [
        'href' => URL . '/admin/configuracao/ui-modelos',
        'title' => 'UI Modelos',
        'description' => 'Modelos visuais e referências de interface.',
        'icon' => 'fa-solid fa-palette',
    ];
    $hub_cards[] = [
        'href' => URL . '/admin/dev',
        'title' => 'Dev Settings',
        'description' => 'Ferramentas internas de desenvolvimento.',
        'icon' => 'fa-solid fa-code',
    ];
    $hub_cards[] = [
        'href' => URL . '/admin/dev/tickets',
        'title' => 'Tickets',
        'description' => 'Tickets de suporte abertos pela escola.',
        'icon' => 'fa-solid fa-ticket',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
