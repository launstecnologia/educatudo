<?php
$itens = is_array($itens ?? null) ? $itens : [];
$dias_semana = is_array($dias_semana ?? null) ? $dias_semana : [];
$tipos_ensino = is_array($tipos_ensino ?? null) ? $tipos_ensino : [];
$series = is_array($series ?? null) ? $series : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$turmas_filtro = is_array($turmas_filtro ?? null) ? $turmas_filtro : $turmas;
$professores = is_array($professores ?? null) ? $professores : [];
$materias = is_array($materias ?? null) ? $materias : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];
$csrf_token = (string) ($csrf_token ?? '');

$visao = (string) ($filtros['visao'] ?? 'semana');
$diaFiltro = (int) ($filtros['dia'] ?? 0);
$turmaFiltrada = (int) ($filtros['turma_id'] ?? 0) > 0;
$mostrarTurmaNoCard = !$turmaFiltrada;

$diasCurtos = [
    1 => 'Segunda',
    2 => 'Terça',
    3 => 'Quarta',
    4 => 'Quinta',
    5 => 'Sexta',
    6 => 'Sábado',
    7 => 'Domingo',
];

$diasComAula = [];
foreach ($itens as $item) {
    $diasComAula[(int) ($item['dia_semana'] ?? 0)] = true;
}

$diasVisiveis = [];
foreach ([1, 2, 3, 4, 5] as $num) {
    $diasVisiveis[$num] = $diasCurtos[$num];
}
foreach ([6, 7] as $num) {
    if (!empty($diasComAula[$num])) {
        $diasVisiveis[$num] = $diasCurtos[$num];
    }
}

$diasNavegacao = [1, 2, 3, 4, 5];
foreach ([6, 7] as $num) {
    if (!empty($diasComAula[$num])) {
        $diasNavegacao[] = $num;
    }
}

if ($visao === 'dia') {
    if ($diaFiltro < 1 || $diaFiltro > 7) {
        $diaFiltro = (int) date('N');
    }
    if (!in_array($diaFiltro, $diasNavegacao, true)) {
        $diasNavegacao[] = $diaFiltro;
        sort($diasNavegacao);
    }
    $diasVisiveis = [
        $diaFiltro => $diasCurtos[$diaFiltro] ?? ('Dia ' . $diaFiltro),
    ];
}

$paletaCores = [
    ['bg' => 'bg-violet-100', 'text' => 'text-violet-900', 'dot' => 'bg-violet-400', 'border' => 'border-violet-200', 'hex_bg' => '#ede9fe', 'hex_text' => '#4c1d95', 'hex_border' => '#ddd6fe'],
    ['bg' => 'bg-sky-100', 'text' => 'text-sky-900', 'dot' => 'bg-sky-400', 'border' => 'border-sky-200', 'hex_bg' => '#e0f2fe', 'hex_text' => '#0c4a6e', 'hex_border' => '#bae6fd'],
    ['bg' => 'bg-emerald-100', 'text' => 'text-emerald-900', 'dot' => 'bg-emerald-400', 'border' => 'border-emerald-200', 'hex_bg' => '#d1fae5', 'hex_text' => '#064e3b', 'hex_border' => '#a7f3d0'],
    ['bg' => 'bg-amber-100', 'text' => 'text-amber-900', 'dot' => 'bg-amber-400', 'border' => 'border-amber-200', 'hex_bg' => '#fef3c7', 'hex_text' => '#78350f', 'hex_border' => '#fde68a'],
    ['bg' => 'bg-rose-100', 'text' => 'text-rose-900', 'dot' => 'bg-rose-400', 'border' => 'border-rose-200', 'hex_bg' => '#ffe4e6', 'hex_text' => '#881337', 'hex_border' => '#fecdd3'],
    ['bg' => 'bg-indigo-100', 'text' => 'text-indigo-900', 'dot' => 'bg-indigo-400', 'border' => 'border-indigo-200', 'hex_bg' => '#e0e7ff', 'hex_text' => '#312e81', 'hex_border' => '#c7d2fe'],
    ['bg' => 'bg-teal-100', 'text' => 'text-teal-900', 'dot' => 'bg-teal-400', 'border' => 'border-teal-200', 'hex_bg' => '#ccfbf1', 'hex_text' => '#134e4a', 'hex_border' => '#99f6e4'],
    ['bg' => 'bg-orange-100', 'text' => 'text-orange-900', 'dot' => 'bg-orange-400', 'border' => 'border-orange-200', 'hex_bg' => '#ffedd5', 'hex_text' => '#7c2d12', 'hex_border' => '#fed7aa'],
    ['bg' => 'bg-fuchsia-100', 'text' => 'text-fuchsia-900', 'dot' => 'bg-fuchsia-400', 'border' => 'border-fuchsia-200', 'hex_bg' => '#fae8ff', 'hex_text' => '#701a75', 'hex_border' => '#f5d0fe'],
    ['bg' => 'bg-lime-100', 'text' => 'text-lime-900', 'dot' => 'bg-lime-400', 'border' => 'border-lime-200', 'hex_bg' => '#ecfccb', 'hex_text' => '#365314', 'hex_border' => '#d9f99d'],
    ['bg' => 'bg-cyan-100', 'text' => 'text-cyan-900', 'dot' => 'bg-cyan-400', 'border' => 'border-cyan-200', 'hex_bg' => '#cffafe', 'hex_text' => '#164e63', 'hex_border' => '#a5f3fc'],
    ['bg' => 'bg-pink-100', 'text' => 'text-pink-900', 'dot' => 'bg-pink-400', 'border' => 'border-pink-200', 'hex_bg' => '#fce7f3', 'hex_text' => '#831843', 'hex_border' => '#fbcfe8'],
];

$coresPorMateria = [];
$componentesResumo = [];
$professoresIds = [];
$salasNomes = [];
foreach ($itens as $item) {
    $materiaId = (int) ($item['materia_id'] ?? 0);
    if (!isset($coresPorMateria[$materiaId])) {
        $coresPorMateria[$materiaId] = $paletaCores[$materiaId % count($paletaCores)];
    }
    if (!isset($componentesResumo[$materiaId])) {
        $componentesResumo[$materiaId] = [
            'nome' => (string) ($item['materia_nome'] ?? 'Componente'),
            'qtd' => 0,
            'cor' => $coresPorMateria[$materiaId],
        ];
    }
    $componentesResumo[$materiaId]['qtd']++;
    $professoresIds[(int) ($item['professor_id'] ?? 0)] = true;
    $salaNome = trim((string) ($item['sala_nome'] ?? ''));
    if ($salaNome !== '') {
        $salasNomes[$salaNome] = true;
    }
}
uasort($componentesResumo, static function ($a, $b) {
    return ($b['qtd'] <=> $a['qtd']) ?: strcasecmp((string) $a['nome'], (string) $b['nome']);
});

$resumo = [
    'aulas' => count($itens),
    'componentes' => count($componentesResumo),
    'professores' => count($professoresIds),
    'salas' => count($salasNomes),
];

$slotsMap = [];
foreach ($itens as $item) {
    $de = substr((string) ($item['horario_de'] ?? ''), 0, 5);
    $ate = substr((string) ($item['horario_ate'] ?? ''), 0, 5);
    if ($de === '' || $ate === '') {
        continue;
    }
    $slotsMap[$de . '|' . $ate] = ['de' => $de, 'ate' => $ate];
}
$slots = array_values($slotsMap);
usort($slots, static function ($a, $b) {
    return strcmp($a['de'], $b['de']) ?: strcmp($a['ate'], $b['ate']);
});

$linhasGrade = [];
$horarioAnteriorFim = null;
foreach ($slots as $slot) {
    if ($horarioAnteriorFim !== null && strcmp($slot['de'], $horarioAnteriorFim) > 0) {
        $linhasGrade[] = [
            'tipo' => 'intervalo',
            'de' => $horarioAnteriorFim,
            'ate' => $slot['de'],
        ];
    }
    $aulasPorDia = [];
    foreach (array_keys($diasVisiveis) as $num) {
        $aulasPorDia[$num] = [];
    }
    foreach ($itens as $item) {
        $de = substr((string) ($item['horario_de'] ?? ''), 0, 5);
        $ate = substr((string) ($item['horario_ate'] ?? ''), 0, 5);
        $dia = (int) ($item['dia_semana'] ?? 0);
        if ($de === $slot['de'] && $ate === $slot['ate'] && isset($aulasPorDia[$dia])) {
            $aulasPorDia[$dia][] = $item;
        }
    }
    $linhasGrade[] = [
        'tipo' => 'aula',
        'de' => $slot['de'],
        'ate' => $slot['ate'],
        'aulas' => $aulasPorDia,
    ];
    $horarioAnteriorFim = $slot['ate'];
}

$filtrosAtivos = 0;
foreach (['tipo_ensino', 'serie', 'periodo'] as $chave) {
    if (($filtros[$chave] ?? '') !== '') {
        $filtrosAtivos++;
    }
}
foreach (['turma_id', 'professor_id', 'materia_id'] as $chave) {
    if ((int) ($filtros[$chave] ?? 0) > 0) {
        $filtrosAtivos++;
    }
}

$montarQuery = static function (array $overrides = []) use ($filtros): string {
    $params = [
        'tipo_ensino' => $overrides['tipo_ensino'] ?? ($filtros['tipo_ensino'] ?? ''),
        'serie' => $overrides['serie'] ?? ($filtros['serie'] ?? ''),
        'turma_id' => $overrides['turma_id'] ?? ($filtros['turma_id'] ?? 0),
        'periodo' => $overrides['periodo'] ?? ($filtros['periodo'] ?? ''),
        'professor_id' => $overrides['professor_id'] ?? ($filtros['professor_id'] ?? 0),
        'materia_id' => $overrides['materia_id'] ?? ($filtros['materia_id'] ?? 0),
        'visao' => $overrides['visao'] ?? ($filtros['visao'] ?? 'semana'),
        'dia' => $overrides['dia'] ?? ($filtros['dia'] ?? 0),
    ];
    $limpo = [];
    foreach ($params as $chave => $valor) {
        if ($chave === 'visao' && ($valor === '' || $valor === 'semana')) {
            continue;
        }
        if ($valor === '' || $valor === null || $valor === 0 || $valor === '0') {
            continue;
        }
        $limpo[$chave] = $valor;
    }
    return $limpo === [] ? '' : ('?' . http_build_query($limpo));
};

$nomeProfessorCurto = static function (string $nome): string {
    $nome = trim($nome);
    if ($nome === '') {
        return '—';
    }
    $partes = preg_split('/\s+/', $nome) ?: [];
    $primeiro = $partes[0] ?? $nome;
    if (count($partes) < 2) {
        return 'Prof. ' . $primeiro;
    }
    $ultimo = $partes[count($partes) - 1];
    return 'Prof. ' . $primeiro . ' ' . mb_strtoupper(mb_substr($ultimo, 0, 1)) . '.';
};

$nomePorId = static function (array $lista, int $id): string {
    foreach ($lista as $row) {
        if ((int) ($row['id'] ?? 0) === $id) {
            return (string) ($row['nome'] ?? '');
        }
    }
    return '';
};

$urlGrade = URL . '/admin/grade-horaria';
$hojeN = (int) date('N');
$indiceDiaAtual = array_search($diaFiltro, $diasNavegacao, true);
$diaAnterior = ($visao === 'dia' && $indiceDiaAtual !== false && $indiceDiaAtual > 0)
    ? (int) $diasNavegacao[$indiceDiaAtual - 1]
    : 0;
$diaProximo = ($visao === 'dia' && $indiceDiaAtual !== false && $indiceDiaAtual < count($diasNavegacao) - 1)
    ? (int) $diasNavegacao[$indiceDiaAtual + 1]
    : 0;

$rotuloToolbar = 'Grade Semanal';
if ($visao === 'dia') {
    $rotuloToolbar = $dias_semana[$diaFiltro] ?? ($diasCurtos[$diaFiltro] ?? 'Dia');
} elseif ($visao === 'lista') {
    $rotuloToolbar = 'Lista de aulas';
}

$colunasDias = count($diasVisiveis);
$pdfPaisagem = $visao !== 'dia';

$partesFiltro = [];
if (($filtros['tipo_ensino'] ?? '') !== '') {
    $partesFiltro[] = 'Ensino: ' . $filtros['tipo_ensino'];
}
if (($filtros['serie'] ?? '') !== '') {
    $partesFiltro[] = 'Série: ' . $filtros['serie'];
}
if ((int) ($filtros['turma_id'] ?? 0) > 0) {
    $nomeTurma = $nomePorId($turmas_filtro !== [] ? $turmas_filtro : $turmas, (int) $filtros['turma_id']);
    $partesFiltro[] = 'Turma: ' . ($nomeTurma !== '' ? $nomeTurma : '#' . (int) $filtros['turma_id']);
}
if (($filtros['periodo'] ?? '') !== '') {
    $partesFiltro[] = 'Período: ' . (($filtros['periodo'] === 'tarde') ? 'Tarde' : 'Manhã');
}
if ((int) ($filtros['professor_id'] ?? 0) > 0) {
    $nomeProf = $nomePorId($professores, (int) $filtros['professor_id']);
    $partesFiltro[] = 'Professor: ' . ($nomeProf !== '' ? $nomeProf : '#' . (int) $filtros['professor_id']);
}
if ((int) ($filtros['materia_id'] ?? 0) > 0) {
    $nomeMat = $nomePorId($materias, (int) $filtros['materia_id']);
    $partesFiltro[] = 'Componente: ' . ($nomeMat !== '' ? $nomeMat : '#' . (int) $filtros['materia_id']);
}
$rotuloFiltrosTxt = $partesFiltro !== [] ? implode(' · ', $partesFiltro) : 'Toda a escola';
$urlPdf = $urlGrade . '/pdf' . $montarQuery();
