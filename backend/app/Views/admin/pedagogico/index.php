<?php
if (!class_exists('FeatureGate')) {
    require_once dirname(__DIR__, 3) . '/Core/FeatureGate.php';
}
if (!class_exists('AdminPermissionMatrix')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminPermissionMatrix.php';
}
if (!class_exists('AdminSecretariaAccess')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminSecretariaAccess.php';
}

$hub_title = 'Pedagógico';
$hub_subtitle = 'Gerencie as atividades pedagógicas da escola.';
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
$podePath = static function (string $path) use ($user): bool {
    if (class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::isSecretaria($user ?? [])) {
        return AdminSecretariaAccess::requestPathIsAllowed($path);
    }
    return true;
};

if ($podePath('/admin/aulas-online') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aulas_online'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/aulas-online',
        'title' => 'Aulas Online',
        'description' => 'Gerencie as aulas ao vivo, gravações e salas virtuais.',
        'icon' => 'fa-solid fa-video',
    ];
}

if ($podeHub('ava') && $podePath('/admin/ava') && class_exists('FeatureGate') && FeatureGate::isModuleEnabled('ead')) {
    $hub_cards[] = [
        'href' => URL . '/admin/ava',
        'title' => 'AVA / EAD',
        'description' => 'Ambiente virtual de aprendizagem e cursos a distância.',
        'icon' => 'fa-solid fa-graduation-cap',
    ];
}

if ($podeHub('bncc') && $podePath('/admin/bncc') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('bncc'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/bncc',
        'title' => 'BNCC / Plano de Curso',
        'description' => 'Habilidades da BNCC e plano de curso da escola.',
        'icon' => 'fa-solid fa-list-check',
    ];
}

if ($podeHub('minicursos') && $podePath('/admin/minicursos') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('aluno_minicursos'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/minicursos',
        'title' => 'EducaCursos',
        'description' => 'Crie e gerencie minicursos para alunos e professores.',
        'icon' => 'fa-solid fa-circle-play',
    ];
}

if ($podeHub('planos_aula') && $podePath('/admin/planos-aula') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('professor_planos_aula'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/planos-aula',
        'title' => 'Plano de Aula',
        'description' => 'Visualize e aprove os planos de aula dos professores.',
        'icon' => 'fa-regular fa-file-lines',
    ];
}

if ($podeHub('inclusao') && $podePath('/admin/inclusao') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('inclusao'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/inclusao/versoes',
        'title' => 'Avaliação Adaptativa',
        'description' => 'Versões adaptadas para alunos com necessidades especiais.',
        'icon' => 'fa-solid fa-universal-access',
    ];
}

if ($podeHub('redacao_professor') && $podePath('/admin/redacao-professor') && (!class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled('redacao_configuravel'))) {
    $hub_cards[] = [
        'href' => URL . '/admin/redacao-professor',
        'title' => 'Jornada da Redação',
        'description' => 'Acompanhe as redações enviadas e corrija com IA.',
        'icon' => 'fa-solid fa-pen-to-square',
    ];
}

if ($podeHub('jornadas_aluno') && $podePath('/admin/jornadas') && class_exists('FeatureGate') && FeatureGate::isModuleEnabled('jornadas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/jornadas',
        'title' => 'Jornada do Aluno',
        'description' => 'Trilhas de aprendizagem personalizadas por aluno.',
        'icon' => 'fa-solid fa-route',
    ];
}

include __DIR__ . '/../_partials/hub_modulos.php';
