<?php
$hub_title = 'Acadêmico';
$hub_subtitle = 'Gerencie a estrutura acadêmica da escola.';
$hub_cards = [
    [
        'href' => URL . '/admin/ano-letivo',
        'title' => 'Ano Letivo',
        'description' => 'Crie e gerencie os anos letivos da escola, períodos e datas.',
        'icon' => 'fa-regular fa-calendar-days',
    ],
    [
        'href' => URL . '/admin/curso',
        'title' => 'Curso',
        'description' => 'Configure os cursos oferecidos pela instituição.',
        'icon' => 'fa-solid fa-book-open',
    ],
    [
        'href' => URL . '/admin/students/remanejamento',
        'title' => 'Movimentação de Alunos',
        'description' => 'Transferências, remanejamentos e histórico de movimentação.',
        'icon' => 'fa-solid fa-arrow-right-arrow-left',
    ],
    [
        'href' => URL . '/admin/serie',
        'title' => 'Série',
        'description' => 'Cadastre e organize as séries/anos de cada curso.',
        'icon' => 'fa-solid fa-layer-group',
    ],
    [
        'href' => URL . '/admin/componentes-curriculares',
        'title' => 'Componentes Curriculares',
        'description' => 'Gerencie os componentes curriculares oferecidos pela escola.',
        'icon' => 'fa-solid fa-book',
    ],
    [
        'href' => URL . '/admin/matrizes-curriculares',
        'title' => 'Matriz Curricular',
        'description' => 'Defina o que cada série deve cursar: componentes, aulas por semana e carga horária.',
        'icon' => 'fa-solid fa-table',
    ],
    [
        'href' => URL . '/admin/regras-academicas',
        'title' => 'Regras Acadêmicas',
        'description' => 'Média mínima, frequência, recuperação e situação de aprovação — versionadas por ano letivo.',
        'icon' => 'fa-solid fa-scale-balanced',
    ],
    [
        'href' => URL . '/admin/resultados-finais',
        'title' => 'Resultados finais',
        'description' => 'Fechamento por turma, homologação imutável, ficha, ata, boletim oficial e relatórios.',
        'icon' => 'fa-solid fa-clipboard-check',
    ],
    [
        'href' => URL . '/admin/turmas',
        'title' => 'Turmas',
        'description' => 'Gerencie as turmas, vincule alunos e configure a grade horária.',
        'icon' => 'fa-solid fa-users',
    ],
    [
        'href' => URL . '/admin/salas',
        'title' => 'Salas / Ambientes',
        'description' => 'Cadastre salas de aula, laboratórios, quadra e outros ambientes para usar como Sala Padrão da turma.',
        'icon' => 'fa-solid fa-door-open',
    ],
];
include __DIR__ . '/../_partials/hub_modulos.php';
