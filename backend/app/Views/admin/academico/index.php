<?php
$modOn = static function (string $key): bool {
    return !class_exists('LayoutHelper') || LayoutHelper::isModuleEnabled($key);
};

$hub_title = 'Acadêmico';
$hub_subtitle = 'Gerencie a estrutura acadêmica da escola.';
$hub_cards = [
    [
        'href' => URL . '/admin/students',
        'title' => 'Alunos',
        'description' => 'Cadastro, matrícula e acompanhamento dos alunos da escola.',
        'icon' => 'fa-solid fa-user-graduate',
    ],
    [
        'href' => URL . '/admin/ano-letivo',
        'title' => 'Ano Letivo',
        'description' => 'Crie e gerencie os anos letivos da escola, períodos e datas.',
        'icon' => 'fa-regular fa-calendar-days',
    ],
];

if ($modOn('calendario_letivo')) {
    $hub_cards[] = [
        'href' => URL . '/admin/calendario-letivo',
        'title' => 'Calendário Letivo',
        'description' => 'Dias letivos, recessos e organização do período escolar.',
        'icon' => 'fa-solid fa-calendar-check',
    ];
}

if ($modOn('componentes_curriculares')) {
    $hub_cards[] = [
        'href' => URL . '/admin/componentes-curriculares',
        'title' => 'Componentes Curriculares',
        'description' => 'Gerencie os componentes curriculares oferecidos pela escola.',
        'icon' => 'fa-solid fa-book',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/curso',
    'title' => 'Curso',
    'description' => 'Configure os cursos oferecidos pela instituição.',
    'icon' => 'fa-solid fa-book-open',
];

if ($modOn('grade_horaria')) {
    $hub_cards[] = [
        'href' => URL . '/admin/grade-horaria',
        'title' => 'Grade Horária',
        'description' => 'Monte os horários das turmas, professores e ambientes.',
        'icon' => 'fa-regular fa-calendar-days',
    ];
}

if ($modOn('matriz_curricular')) {
    $hub_cards[] = [
        'href' => URL . '/admin/matrizes-curriculares',
        'title' => 'Matriz Curricular',
        'description' => 'Defina o que cada série deve cursar: componentes, aulas por semana e carga horária.',
        'icon' => 'fa-solid fa-table',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/teachers',
    'title' => 'Professores',
    'description' => 'Cadastro de professores e vínculos com turmas.',
    'icon' => 'fa-solid fa-chalkboard-user',
];

if (class_exists('LayoutHelper') && LayoutHelper::isModuleEnabled('notas_semanais')) {
    $hub_cards[] = [
        'href' => URL . '/admin/notas-semanais',
        'title' => 'Quadro semanal',
        'description' => 'Configure o quadro de notas semanais da escola.',
        'icon' => 'fa-solid fa-table',
    ];
}

if ($modOn('regras_academicas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/regras-academicas',
        'title' => 'Regras Acadêmicas',
        'description' => 'Média mínima, frequência, recuperação e situação de aprovação — versionadas por ano letivo.',
        'icon' => 'fa-solid fa-scale-balanced',
    ];
}

if ($modOn('salas')) {
    $hub_cards[] = [
        'href' => URL . '/admin/salas',
        'title' => 'Salas / Ambientes',
        'description' => 'Cadastre salas de aula, laboratórios, quadra e outros ambientes para usar como Sala Padrão da turma.',
        'icon' => 'fa-solid fa-door-open',
    ];
}

$hub_cards[] = [
    'href' => URL . '/admin/serie',
    'title' => 'Série',
    'description' => 'Cadastre e organize as séries/anos de cada curso.',
    'icon' => 'fa-solid fa-layer-group',
];
$hub_cards[] = [
    'href' => URL . '/admin/turmas',
    'title' => 'Turmas',
    'description' => 'Gerencie as turmas, vincule alunos e configure a grade horária.',
    'icon' => 'fa-solid fa-users',
];

include __DIR__ . '/../_partials/hub_modulos.php';
