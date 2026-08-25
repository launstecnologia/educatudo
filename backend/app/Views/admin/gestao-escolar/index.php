<?php
if (!class_exists('AdminPermissionMatrix')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
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

if ($podeHub('censo_escolar') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('censo_escolar'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/censo',
        'title' => 'Censo Escolar',
        'description' => 'Preparação da Matrícula Inicial e da Situação do Aluno para o Educacenso.',
        'icon' => 'fa-solid fa-school-flag',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('conformidade')) {
    $hub_cards[] = [
        'href' => URL . '/admin/conformidade',
        'title' => 'Conformidade',
        'description' => 'Painel de pendências e conformidade operacional da escola.',
        'icon' => 'fa-solid fa-clipboard-check',
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

if ($podeHub('diario_classe') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('diario_classe'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/diario',
        'title' => 'Diário de Classe',
        'description' => 'Acompanhe aulas, frequência e registros do diário.',
        'icon' => 'fa-regular fa-address-book',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('faltas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/faltas',
        'title' => 'Faltas',
        'description' => 'Registre e acompanhe as faltas dos alunos.',
        'icon' => 'fa-regular fa-clipboard',
    ];
}

if ($podeHub('presenca') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('presenca'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/presenca',
        'title' => 'Presença',
        'description' => 'Controle de entrada e presença dos alunos na escola.',
        'icon' => 'fa-solid fa-right-to-bracket',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('documentos_institucionais')) {
    $hub_cards[] = [
        'href' => URL . '/admin/documentos-institucionais',
        'title' => 'Documentos Institucionais',
        'description' => 'Regimento, PPP e demais documentos oficiais da escola.',
        'icon' => 'fa-solid fa-file-shield',
    ];
}

if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula') && $podeHub('processos_matricula')) {
    $hub_cards[] = [
        'href' => URL . '/admin/configuracao/assinatura-digital',
        'title' => 'Assinatura Digital',
        'description' => 'Configure a assinatura digital dos documentos.',
        'icon' => 'fa-solid fa-pen-nib',
    ];
}

if ($podeHub('modelos_documentos')) {
    $hub_cards[] = [
        'href' => URL . '/admin/modelos-documentos',
        'title' => 'Layout de documentos',
        'description' => 'Modelos e layouts usados na emissão de documentos.',
        'icon' => 'fa-solid fa-file-contract',
    ];
}

if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('processo_matricula')) {
    $hub_cards[] = [
        'href' => URL . '/admin/enrollment',
        'title' => 'Matrículas',
        'description' => 'Processos de matrícula, rematrícula e acompanhamento de vagas.',
        'icon' => 'fa-solid fa-file-signature',
    ];
    if ($podeHub('processos_matricula')) {
        $hub_cards[] = [
            'href' => URL . '/admin/enrollment/config',
            'title' => 'Configuração de Matrícula',
            'description' => 'Etapas, documentos e regras do processo de matrícula.',
            'icon' => 'fa-solid fa-graduation-cap',
        ];
    }
}

if ($podeHub('transferencia')) {
    $hub_cards[] = [
        'href' => URL . '/admin/students/remanejamento',
        'title' => 'Movimentação de alunos',
        'description' => 'Transferências, remanejamentos e histórico de movimentação.',
        'icon' => 'fa-solid fa-people-arrows',
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

if ((!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('recursos_fisicos')) && $podeHub('almoxarifado')) {
    $hub_cards[] = [
        'href' => URL . '/admin/almoxarifado',
        'title' => 'Almoxarifado',
        'description' => 'Controle de estoque e materiais da escola.',
        'icon' => 'fa-solid fa-boxes-stacked',
    ];
}

if ((!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('recursos_fisicos')) && $podeHub('patrimonio')) {
    $hub_cards[] = [
        'href' => URL . '/admin/patrimonio',
        'title' => 'Patrimônio',
        'description' => 'Cadastro e controle dos bens patrimoniais.',
        'icon' => 'fa-solid fa-barcode',
    ];
}

if ($podeHub('resultados_finais') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('resultados_finais'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/resultados-finais',
        'title' => 'Resultados Finais',
        'description' => 'Fechamento por turma, homologação, ficha, ata e boletim oficial.',
        'icon' => 'fa-solid fa-clipboard-check',
    ];
}

if (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('saude_academica')) {
    $hub_cards[] = [
        'href' => URL . '/admin/saude-academica',
        'title' => 'Saúde Acadêmica',
        'description' => 'Indicadores de desempenho, frequência e risco pedagógico.',
        'icon' => 'fa-solid fa-heart-pulse',
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

include __DIR__ . '/../_partials/hub_modulos.php';
