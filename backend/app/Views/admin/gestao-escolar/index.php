<?php
if (!class_exists('AdminPermissionMatrix')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
}
if (!class_exists('AdminSecretariaAccess')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminSecretariaAccess.php';
}

$hub_title = 'Gestão Escolar';
$hub_subtitle = 'Acompanhe matrícula, frequência, documentos e a rotina da escola.';
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

if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula')) {
    $hub_cards[] = [
        'href' => URL . '/admin/enrollment',
        'title' => 'Matrículas',
        'description' => 'Processos de matrícula, rematrícula e acompanhamento de vagas.',
        'icon' => 'fa-solid fa-file-signature',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/faltas',
    'title' => 'Faltas',
    'description' => 'Registre e acompanhe as faltas dos alunos.',
    'icon' => 'fa-regular fa-clipboard',
];

if ($podeHub('presenca') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/presenca',
        'title' => 'Presença',
        'description' => 'Controle de entrada e presença dos alunos na escola.',
        'icon' => 'fa-solid fa-right-to-bracket',
    ];
}

if ($podeHub('diario_classe') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('diario_classe'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/diario',
        'title' => 'Diário de Classe',
        'description' => 'Acompanhe aulas, frequência e registros do diário.',
        'icon' => 'fa-regular fa-address-book',
    ];
}

if ($podeHub('conselho_classe') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conselho_classe'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/conselhos',
        'title' => 'Conselho de Classe',
        'description' => 'Organize sessões, atas e deliberações do conselho.',
        'icon' => 'fa-solid fa-chalkboard-user',
    ];
}

if ($podeHub('censo_escolar') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('censo_escolar'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/censo',
        'title' => 'Censo Escolar',
        'description' => 'Preparação da Matrícula Inicial e da Situação do Aluno para o Educacenso.',
        'icon' => 'fa-solid fa-school-flag',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/conformidade',
    'title' => 'Conformidade',
    'description' => 'Painel de pendências e conformidade operacional da escola.',
    'icon' => 'fa-solid fa-clipboard-check',
];

$hub_cards[] = [
    'href' => URL . '/admin/calendario-letivo',
    'title' => 'Calendário Letivo',
    'description' => 'Eventos, feriados e datas do calendário escolar.',
    'icon' => 'fa-solid fa-calendar-check',
];

$hub_cards[] = [
    'href' => URL . '/admin/bncc',
    'title' => 'BNCC / Plano de Curso',
    'description' => 'Habilidades da BNCC e plano de curso da escola.',
    'icon' => 'fa-solid fa-list-check',
];

$hub_cards[] = [
    'href' => URL . '/admin/documentos-institucionais',
    'title' => 'Documentos Institucionais',
    'description' => 'Regimento, PPP e demais documentos oficiais da escola.',
    'icon' => 'fa-solid fa-file-shield',
];

if ($podeHub('modelos_documentos')) {
    $hub_cards[] = [
        'href' => URL . '/admin/modelos-documentos',
        'title' => 'Layout de documentos',
        'description' => 'Modelos e layouts usados na emissão de documentos.',
        'icon' => 'fa-solid fa-file-contract',
    ];
}

if ($podeHub('ocorrencias') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('ocorrencias'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/ocorrencias',
        'title' => 'Ocorrências',
        'description' => 'Registre e acompanhe ocorrências pedagógicas e disciplinares.',
        'icon' => 'fa-regular fa-clock',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/reunioes/geral',
    'title' => 'Reuniões',
    'description' => 'Agende e registre reuniões da equipe escolar.',
    'icon' => 'fa-solid fa-people-group',
];

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

$hub_cards[] = [
    'href' => URL . '/admin/saude-academica',
    'title' => 'Saúde Acadêmica',
    'description' => 'Indicadores de desempenho, frequência e risco pedagógico.',
    'icon' => 'fa-solid fa-heart-pulse',
];

if ($podeHub('almoxarifado')) {
    $hub_cards[] = [
        'href' => URL . '/admin/almoxarifado',
        'title' => 'Almoxarifado',
        'description' => 'Controle de estoque e materiais da escola.',
        'icon' => 'fa-solid fa-boxes-stacked',
    ];
}

if ($podeHub('patrimonio')) {
    $hub_cards[] = [
        'href' => URL . '/admin/patrimonio',
        'title' => 'Patrimônio',
        'description' => 'Cadastro e controle dos bens patrimoniais.',
        'icon' => 'fa-solid fa-barcode',
    ];
}

if (class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::isSecretaria($user ?? [])) {
    $hub_cards = array_values(array_filter($hub_cards, static function (array $card): bool {
        $href = (string) ($card['href'] ?? '');
        $path = parse_url($href, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $href;
        }
        return AdminSecretariaAccess::requestPathIsAllowed($path);
    }));
}

include __DIR__ . '/../_partials/hub_modulos.php';
