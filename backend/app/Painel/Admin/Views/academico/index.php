<?php
$modOn = static function (string $key): bool {
    return !class_exists('LayoutHelper') || \App\Core\LayoutHelper::isModuleEnabled($key);
};

$hub_title = 'Acadêmico';
$hub_subtitle = 'Estrutura da escola, pessoas e como a escola avalia — nesta ordem.';
$hub_cards = [
    [
        'href' => URL . '/admin/ano-letivo',
        'title' => 'Ano Letivo',
        'description' => 'Crie e gerencie os anos letivos da escola, períodos e datas.',
        'icon' => 'fa-regular fa-calendar-days',
    ],
    [
        'href' => URL . '/admin/cursos-series',
        'title' => 'Cursos e Séries',
        'description' => 'Cursos da escola com as séries aninhadas em cada um.',
        'icon' => 'fa-solid fa-graduation-cap',
    ],
];

if ($modOn('componentes_curriculares')) {
    $hub_cards[] = [
        'href' => URL . '/admin/componentes-curriculares',
        'title' => 'Componentes Curriculares',
        'description' => 'Gerencie os componentes curriculares oferecidos pela escola.',
        'icon' => 'fa-solid fa-book',
    ];
}

if ($modOn('matriz_curricular')) {
    $hub_cards[] = [
        'href' => URL . '/admin/matrizes-curriculares',
        'title' => 'Matriz Curricular',
        'description' => 'Defina o componente oficial de cada série. Desdobramentos (ex.: Gramática) somam a carga da área (ex.: Língua Portuguesa).',
        'icon' => 'fa-solid fa-table',
    ];
}

if ($modOn('calendario_letivo')) {
    $hub_cards[] = [
        'href' => URL . '/admin/calendario-letivo',
        'title' => 'Calendário Letivo',
        'description' => 'Dias letivos, recessos e organização do período escolar.',
        'icon' => 'fa-solid fa-calendar-check',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/teachers',
    'title' => 'Professores',
    'description' => 'Cadastro de professores e vínculos com turmas.',
    'icon' => 'fa-solid fa-chalkboard-user',
];

if ($modOn('salas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/salas',
        'title' => 'Salas / Ambientes',
        'description' => 'Cadastre salas de aula, laboratórios, quadra e outros ambientes para usar como Sala Padrão da turma.',
        'icon' => 'fa-solid fa-door-open',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/turmas',
    'title' => 'Turmas',
    'description' => 'Gerencie as turmas, vincule alunos e configure a grade horária.',
    'icon' => 'fa-solid fa-users',
];
$hub_cards[] = [
    'href' => URL . '/admin/students',
    'title' => 'Alunos',
    'description' => 'Cadastro, matrícula e acompanhamento dos alunos da escola.',
    'icon' => 'fa-solid fa-user-graduate',
];

if ($modOn('grade_horaria')) {
    $hub_cards[] = [
        'href' => URL . '/admin/grade-horaria',
        'title' => 'Grade Horária',
        'description' => 'Monte os horários das turmas, professores e ambientes.',
        'icon' => 'fa-regular fa-calendar-days',
    ];
}

if ($modOn('regras_academicas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/regras-academicas',
        'title' => 'Regras de Aprovação',
        'description' => 'Média mínima, frequência, recuperação e situação de aprovação — versionadas por ano letivo.',
        'icon' => 'fa-solid fa-scale-balanced',
    ];
}

$modeloEventoAvaliativo = class_exists('LayoutHelper') && \App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos');
$provasHubOn = !class_exists('LayoutHelper') || \App\Core\LayoutHelper::isModuleEnabled('aluno_provas') || \App\Core\LayoutHelper::isModuleEnabled('professor_provas');

if ($modeloEventoAvaliativo && $provasHubOn) {
    $hub_cards[] = [
        'href' => URL . '/admin/eventos-avaliativos',
        'title' => 'Eventos Avaliativos',
        'description' => 'Crie o tipo de nota, o quadro e as datas no mesmo lugar. Depois lance a nota pelo evento.',
        'icon' => 'fa-solid fa-clipboard-list',
    ];
}

if (!$modeloEventoAvaliativo && $provasHubOn) {
    $hub_cards[] = [
        'href' => URL . '/admin/provas/tipos-avaliacao',
        'title' => 'Tipos de Nota',
        'description' => 'Cadastre os tipos usados nos eventos (prova bimestral, trabalho, recuperação…).',
        'icon' => 'fa-solid fa-tags',
    ];
}

if (!$modeloEventoAvaliativo && class_exists('LayoutHelper') && \App\Core\LayoutHelper::isModuleEnabled('grupos_regras_notas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/quadros-notas',
        'title' => 'Quadro de Notas',
        'description' => 'Colunas (S1, AV1…) e, se precisar, blocos de disciplinas (A/B). As provas são criadas em Lançamento de Notas.',
        'icon' => 'fa-solid fa-layer-group',
    ];
}

if (!$modeloEventoAvaliativo && $provasHubOn) {
    $hub_cards[] = [
        'href' => URL . '/admin/provas',
        'title' => 'Lançamento de Notas',
        'description' => 'Digitação de notas das provas e trabalhos.',
        'icon' => 'fa-regular fa-clipboard',
    ];
}

if ($modOn('boletim') && $modeloEventoAvaliativo) {
    $hub_cards[] = [
        'href' => URL . '/admin/boletins',
        'title' => 'Demonstrativo de notas',
        'description' => 'Monte o quadro de notas: períodos, colunas e fórmulas.',
        'icon' => 'fa-solid fa-table-cells',
    ];
    $hub_cards[] = [
        'href' => URL . '/admin/eventos-avaliativos/boletim',
        'title' => 'Boletim',
        'description' => 'Layout oficial do boletim da vida escolar.',
        'icon' => 'fa-solid fa-file-circle-check',
    ];
    $hub_cards[] = [
        'href' => URL . '/admin/reports/boletim-coordenacao?fonte=vida_escolar',
        'title' => 'Relatórios',
        'description' => 'Emissão do boletim e do demonstrativo de notas.',
        'icon' => 'fa-solid fa-chart-column',
    ];
} elseif ($modOn('boletim')) {
    $hub_cards[] = [
        'href' => URL . '/admin/boletins',
        'title' => 'Boletins',
        'description' => 'Configure os períodos, acompanhe os resultados e emita relatórios.',
        'icon' => 'fa-solid fa-file-circle-check',
    ];
}

include \App\Core\ViewPaths::adminPartial('hub_modulos.php');
