<?php
if (!class_exists('AdminPermissionMatrix')) {
}

$hub_title = 'Z-Configuração';
$hub_subtitle = 'Parâmetros da instituição, manutenção e ferramentas internas.';
$hub_cards = [];

$permsHub = \App\Core\AdminPermissionMatrix::effectivePermissionsForUser(
    \App\Core\Database::getInstance(),
    $user ?? []
);
$podeHub = static function (string $chave) use ($permsHub): bool {
    return !empty($permsHub[$chave]['visualizar']);
};

if (($user['perfil_admin'] ?? '') === 'dev') {
    $hub_cards[] = [
        'href' => URL . '/admin/dev',
        'title' => 'Dev Settings',
        'description' => 'Ferramentas internas de desenvolvimento.',
        'icon' => 'fa-solid fa-code',
    ];
}

if (!class_exists('LayoutHelper') || \App\Core\LayoutHelper::isModuleEnabled('documentos_institucionais')) {
    $hub_cards[] = [
        'href' => URL . '/admin/documentos-institucionais',
        'title' => 'Documentos Institucionais',
        'description' => 'Regimento, PPP e demais documentos oficiais da escola.',
        'icon' => 'fa-solid fa-file-shield',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/tudicoins',
    'title' => 'TudiCoins da Escola',
    'description' => 'Saldo e uso de créditos de IA da escola.',
    'icon' => 'fa-solid fa-wallet',
];

$hub_cards[] = [
    'href' => URL . '/admin/creditos/pacotes',
    'title' => 'Pacotes de TudiCoins',
    'description' => 'Pacotes de créditos disponíveis para a escola.',
    'icon' => 'fa-solid fa-coins',
];

if ($podeHub('unidades')) {
    $hub_cards[] = [
        'href' => URL . '/admin/unidades',
        'title' => 'Instituição',
        'description' => 'Unidades, dados da escola e estrutura institucional.',
        'icon' => 'fa-solid fa-building',
    ];
}

if ($podeHub('modo_manutencao')) {
    $hub_cards[] = [
        'href' => URL . '/admin/maintenance/painel',
        'title' => 'Modo Manutenção',
        'description' => 'Ative o modo manutenção e avise a comunidade escolar.',
        'icon' => 'fa-solid fa-screwdriver-wrench',
    ];
}

if ($podeHub('slider_dashboard')) {
    $hub_cards[] = [
        'href' => URL . '/admin/settings#slider-dashboard',
        'title' => 'Slider Dashboard',
        'description' => 'Banners e imagens do slider na área do aluno.',
        'icon' => 'fa-solid fa-images',
    ];
}

if ($podeHub('resultados_finais') && (!class_exists('LayoutHelper', false) || \App\Core\LayoutHelper::isModuleEnabled('resultados_finais'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/resultados-finais/layouts',
        'title' => 'Layouts',
        'description' => 'Modelos dos documentos do fechamento.',
        'icon' => 'fa-solid fa-table-columns',
    ];
}

if (($user['perfil_admin'] ?? '') === 'dev') {
    $hub_cards[] = [
        'href' => URL . '/admin/configuracao/ui-modelos',
        'title' => 'UI Modelos',
        'description' => 'Modelos visuais e referências de interface.',
        'icon' => 'fa-solid fa-palette',
    ];
}

$mostraZerarPopularEduca = false;
try {
    $pdoHubEduca = \App\Core\Database::getInstance()->getPdo();
    if ($pdoHubEduca instanceof \PDO) {
        $mostraZerarPopularEduca = \App\Painel\Master\Services\LimparDadosEscolaEducaService::conexaoAtualPermitida($pdoHubEduca);
    }
} catch (\Throwable $e) {
    $mostraZerarPopularEduca = false;
}
if ($mostraZerarPopularEduca) {
    $hub_cards[] = [
        'href' => URL . '/admin/z-configuracao/zerar-popular',
        'title' => 'Zerar e popular EM 2025',
        'description' => 'Esvazia os cadastros desta escola e carrega a base de teste do Ensino Médio 2025.',
        'icon' => 'fa-solid fa-rotate-left',
    ];
}

include \App\Core\ViewPaths::adminPartial('hub_modulos.php');
