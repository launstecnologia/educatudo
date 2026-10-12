<!-- Header -->
<div class="mb-8">
    <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
            <h2 class="text-lg font-bold uppercase tracking-wide text-gray-900 mb-2 break-words">
                Resultados da Jornada - <?= htmlspecialchars($jornada['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?> • 
                <?= htmlspecialchars($turmas_nomes_texto ?? $jornada['turma_nome'] ?? 'Sem turma') ?>
            </p>
        </div>
        <div class="shrink-0">
            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>" 
               class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center shadow-lg hover:shadow-xl">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                </svg>
                Voltar
            </a>
        </div>
    </div>
</div>

<?php 
$etapas = $etapas ?? [];
$limiteExibicaoTempoSegundos = 8 * 3600; // 8h
$tiposEtapaLabel = [
    'video' => 'Conteúdo',
    'conteudo' => 'Conteúdo',
    'dica_professor' => 'Dica do Professor',
    'exercicios' => 'Exercícios',
    'exercicio' => 'Exercícios',
    'resumo_aluno' => 'Resumo'
];
$primeiraSecaoVisivel = null;
$opcoesTipoExibicao = [];
?>
<?php
foreach ($etapas as $etapaOpt) {
    $tipoOpt = $etapaOpt['tipo_modulo'] ?? '';
    if (!in_array($tipoOpt, ['video', 'conteudo', 'dica_professor'], true)) {
        continue;
    }
    $slugOpt = 'etapa-' . (int)$etapaOpt['id'];
    if ($primeiraSecaoVisivel === null) {
        $primeiraSecaoVisivel = $slugOpt;
    }
    $rotuloBase = $tipoOpt === 'dica_professor'
        ? 'Dica do Professor'
        : 'Conteúdo';
    $contadorMesmoTipo = 1;
    foreach ($opcoesTipoExibicao as $optExistente) {
        if (($optExistente['base'] ?? '') === $rotuloBase) {
            $contadorMesmoTipo++;
        }
    }
    $rotuloOpt = $rotuloBase . ' ' . $contadorMesmoTipo;
    $opcoesTipoExibicao[] = [
        'value' => $slugOpt,
        'label' => $rotuloOpt,
        'base' => $rotuloBase
    ];
}
if (!empty($temExercicios) && !empty($alunosExercicios)) {
    if ($primeiraSecaoVisivel === null) $primeiraSecaoVisivel = 'exercicios';
    $opcoesTipoExibicao[] = ['value' => 'exercicios', 'label' => 'Exercícios', 'base' => 'Exercícios'];
}
if (!empty($resumos)) {
    if ($primeiraSecaoVisivel === null) $primeiraSecaoVisivel = 'resumos';
    $opcoesTipoExibicao[] = ['value' => 'resumos', 'label' => 'Resumo', 'base' => 'Resumo'];
}
if (!empty($redacoes)) {
    if ($primeiraSecaoVisivel === null) $primeiraSecaoVisivel = 'redacoes';
    $opcoesTipoExibicao[] = ['value' => 'redacoes', 'label' => 'Redação', 'base' => 'Redação'];
}

$turmasFiltroGlobal = [];
foreach (($alunosExercicios ?? []) as $alunoFiltroGlobal) {
    $turmaNomeFiltroGlobal = trim((string)($alunoFiltroGlobal['turma_nome'] ?? $alunoFiltroGlobal['serie'] ?? ''));
    if ($turmaNomeFiltroGlobal !== '') $turmasFiltroGlobal[$turmaNomeFiltroGlobal] = true;
}
foreach (($etapas ?? []) as $etapaFiltroGlobal) {
    foreach (($etapaFiltroGlobal['alunos'] ?? []) as $alunoEtapaFiltroGlobal) {
        $turmaNomeFiltroGlobal = trim((string)($alunoEtapaFiltroGlobal['turma_nome'] ?? $alunoEtapaFiltroGlobal['serie'] ?? ''));
        if ($turmaNomeFiltroGlobal !== '') $turmasFiltroGlobal[$turmaNomeFiltroGlobal] = true;
    }
}
ksort($turmasFiltroGlobal, SORT_NATURAL | SORT_FLAG_CASE);
?>

<!-- Filtro Global -->
<div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 mb-4">
    <div class="flex flex-wrap items-end gap-4">
        <div class="flex-1 min-w-[200px]">
            <label for="filtroNomeAluno" class="block text-sm font-medium text-gray-700 mb-2">Buscar por nome do aluno</label>
            <div class="relative">
                <input type="text" id="filtroNomeAluno" placeholder="Digite o nome do aluno..."
                       class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </div>
        </div>
        <div class="w-48">
            <label for="filtroTipoConteudo" class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
            <select id="filtroTipoConteudo" class="select-safari w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <?php foreach ($opcoesTipoExibicao as $optTipo): ?>
                    <option value="<?= htmlspecialchars($optTipo['value']) ?>"><?= htmlspecialchars($optTipo['label']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-48">
            <label for="filtroStatus" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
            <select id="filtroStatus" class="select-safari w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">Todos</option>
                <option value="realizou">Realizou a Jornada</option>
                <option value="nao_realizou">Não realizou a Jornada</option>
            </select>
        </div>
        <div class="w-48">
            <label for="filtroTurma" class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
            <select id="filtroTurma" class="select-safari w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                <option value="">Todas</option>
                <?php foreach (array_keys($turmasFiltroGlobal) as $turmaOptGlobal): ?>
                    <option value="<?= htmlspecialchars(strtolower($turmaOptGlobal)) ?>"><?= htmlspecialchars($turmaOptGlobal) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button id="limparFiltroAluno" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Limpar</button>
        </div>
    </div>
    <div class="mt-2 text-sm text-gray-600">
        <span id="contadorAlunos">0</span> aluno(s) encontrado(s)
    </div>
</div>

<!-- Seções por Etapa (Conteúdo / Dica: alunos e tempo) -->
<?php foreach ($etapas as $etapa): 
    $tipo = $etapa['tipo_modulo'] ?? '';
    if (!in_array($tipo, ['video', 'conteudo', 'dica_professor'], true)) continue;
    $slug = 'etapa-' . (int)$etapa['id'];
    $alunosEtapa = $etapa['alunos'] ?? [];
    $mostrarPrimeiro = ($primeiraSecaoVisivel === $slug);
?>
<div id="section-<?= $slug ?>" class="section-content <?= $mostrarPrimeiro ? '' : 'hidden' ?>">
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 mb-4">
        <h3 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars($etapa['titulo']) ?></h3>
        <p class="text-sm text-gray-600">Alunos que concluíram esta etapa e tempo gasto.</p>
    </div>
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-hidden">
            <table class="w-full table-fixed divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Tempo</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($alunosEtapa as $a): 
                        $fez = !empty($a['fez']);
                        $seg = (int)($a['tempo_gasto_segundos'] ?? 0);
                    ?>
                    <tr class="hover:bg-gray-50 aluno-row"
                        data-nome="<?= strtolower(htmlspecialchars($a['aluno_nome'])) ?>"
                        data-status="<?= $fez ? '1' : '0' ?>"
                        data-turma="<?= strtolower(htmlspecialchars($a['turma_nome'] ?? $a['serie'] ?? '')) ?>">
                        <td class="px-4 py-4 text-sm font-medium text-gray-900 break-words"><?= htmlspecialchars($a['aluno_nome']) ?></td>
                        <td class="px-4 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($a['turma_nome']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $fez ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                <?= $fez ? 'Concluiu' : 'Não concluiu' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700" title="Formato m:ss ou h:mm:ss">
                            <?php
                            if ($seg > 0) {
                                if ($seg > $limiteExibicaoTempoSegundos) {
                                    echo '8h+';
                                } else {
                                    $h = intdiv($seg, 3600);
                                    $m = intdiv($seg % 3600, 60);
                                    $s = $seg % 60;
                                    echo $h > 0
                                        ? sprintf('%d:%02d:%02d', $h, $m, $s)
                                        : sprintf('%d:%02d', $m, $s);
                                }
                            } else {
                                echo '<span class="text-gray-400">-</span>';
                            }
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endforeach; ?>

<!-- Seção Exercícios -->
<?php 
$mostrarExerciciosPrimeiro = ($primeiraSecaoVisivel === 'exercicios') || (empty($etapas) && !empty($temExercicios) && !empty($alunosExercicios));
?>
<div id="section-exercicios" class="section-content <?= ($mostrarExerciciosPrimeiro && !empty($temExercicios) && !empty($alunosExercicios)) ? '' : 'hidden' ?>">
    <?php if (empty($alunosExercicios)): ?>
        <div class="bg-white rounded-xl shadow-lg p-12 border border-gray-200 text-center">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
            </svg>
            <p class="text-gray-500 text-lg">Nenhum aluno respondeu exercícios nesta jornada ainda</p>
        </div>
    <?php else: ?>
        <?php
        $turmasFiltroExercicios = [];
        foreach ($alunosExercicios as $alunoFiltro) {
            $turmaNomeFiltro = trim((string)($alunoFiltro['turma_nome'] ?? $alunoFiltro['serie'] ?? ''));
            if ($turmaNomeFiltro !== '') $turmasFiltroExercicios[$turmaNomeFiltro] = true;
        }
        $turmasFiltroExercicios = array_keys($turmasFiltroExercicios);
        natcasesort($turmasFiltroExercicios);
        ?>
        <!-- Filtro de Busca e Status -->
        <div class="hidden bg-white rounded-xl shadow-lg border border-gray-200 p-4 mb-4">
            <div class="flex flex-wrap items-end gap-4">
                <div class="flex-1 min-w-[200px]">
                    <label for="filtroNomeAluno" class="block text-sm font-medium text-gray-700 mb-2">
                        Buscar por nome do aluno
                    </label>
                    <div class="relative">
                        <input type="text" id="filtroNomeAluno" placeholder="Digite o nome do aluno..." 
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                        </svg>
                    </div>
                </div>
                <div class="w-48">
                    <label for="filtroTipoConteudo" class="block text-sm font-medium text-gray-700 mb-2">
                        Tipo
                    </label>
                    <select id="filtroTipoConteudo" class="select-safari w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <?php foreach ($opcoesTipoExibicao as $optTipo): ?>
                            <option value="<?= htmlspecialchars($optTipo['value']) ?>"><?= htmlspecialchars($optTipo['label']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="w-48">
                    <label for="filtroStatus" class="block text-sm font-medium text-gray-700 mb-2">
                        Status
                    </label>
                    <select id="filtroStatus" class="select-safari w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <option value="">Todos</option>
                        <option value="realizou">Realizou a Jornada</option>
                        <option value="nao_realizou">Não realizou a Jornada</option>
                    </select>
                </div>
                <div class="w-48">
                    <label for="filtroTurma" class="block text-sm font-medium text-gray-700 mb-2">
                        Turma
                    </label>
                    <select id="filtroTurma" class="select-safari w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <option value="">Todas</option>
                        <?php foreach ($turmasFiltroExercicios as $turmaOpt): ?>
                            <option value="<?= htmlspecialchars(strtolower($turmaOpt)) ?>"><?= htmlspecialchars($turmaOpt) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <button id="limparFiltroAluno" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">
                        Limpar
                    </button>
                </div>
            </div>
            <div class="mt-2 text-sm text-gray-600">
                <span id="contadorAlunos"><?= count($alunosExercicios) ?></span> aluno(s) encontrado(s)
            </div>
        </div>
        
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden max-w-full">
            <div class="overflow-x-hidden">
                <table class="w-full table-fixed divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="w-[30%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="nome" data-type="text" title="Clique para ordenar">
                                Nome <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="w-[10%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="serie" data-type="text" title="Clique para ordenar">
                                Série <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="w-[14%] px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="status" data-type="number" title="Clique para ordenar">
                                Status <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="w-[8%] px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="nota" data-type="number" title="Clique para ordenar">
                                Nota <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="w-[8%] px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="acertos" data-type="number" title="Clique para ordenar">
                                ✓ <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="w-[8%] px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="erros" data-type="number" title="Clique para ordenar">
                                ✕ <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="w-[10%] px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="tempo" data-type="number" title="Clique para ordenar">
                                Tempo <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="w-[12%] px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody id="tbodyAlunos" class="bg-white divide-y divide-gray-200">
                        <?php foreach ($alunosExercicios as $aluno): ?>
                            <?php 
                            $percentual = $aluno['total_exercicios'] > 0 
                                ? round(($aluno['acertos'] / $aluno['total_exercicios']) * 100, 1) 
                                : 0;
                            $notaExibir = ($aluno['total_exercicios'] > 0) ? ($aluno['acertos'] . '/' . $aluno['total_exercicios']) : '-';
                            $status = $aluno['status_exercicios'] ?? 'nao_viu';
                            $realizou = in_array($status, ['viu', 'fez', 'fez_e_nao_viu'], true);
                            $statusTexto = $realizou ? 'Realizou a Jornada' : 'Não realizou a Jornada';
                            $statusCor = $realizou ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                            ?>
                            <tr class="hover:bg-gray-50 aluno-row" 
                                data-nome="<?= strtolower(htmlspecialchars($aluno['aluno_nome'])) ?>" 
                                data-serie="<?= strtolower(htmlspecialchars($aluno['turma_nome'] ?? $aluno['serie'] ?? '')) ?>"
                                data-status="<?= $realizou ? '1' : '0' ?>"
                                data-turma="<?= strtolower(htmlspecialchars($aluno['turma_nome'] ?? $aluno['serie'] ?? '')) ?>"
                                data-nota="<?= $percentual ?>"
                                data-acertos="<?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? (int)$aluno['acertos'] : -1 ?>"
                                data-erros="<?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? (int)$aluno['erros'] : -1 ?>"
                                data-tempo="<?= (int)($aluno['tempo_total_segundos'] ?? 0) ?>">
                                <td class="px-4 py-4 text-sm font-medium text-gray-900 break-words">
                                    <div>
                                        <?= htmlspecialchars($aluno['aluno_nome']) ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600">
                                        <?= htmlspecialchars($aluno['turma_nome'] ?? $aluno['serie'] ?? '-') ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $statusCor ?>">
                                        <?= $realizou ? 'Realizou' : 'Não realizou' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm font-semibold <?= $percentual >= 70 ? 'text-green-600' : ($percentual >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                                        <?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? $notaExibir : '-' ?>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                        <?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? $aluno['acertos'] : '-' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                        <?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? $aluno['erros'] : '-' ?>
                                    </span>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center text-sm text-gray-700" title="Formato m:ss ou h:mm:ss">
                                    <?php 
                                    $seg = (int)($aluno['tempo_total_segundos'] ?? 0); 
                                    if ($seg > 0) {
                                        if ($seg > $limiteExibicaoTempoSegundos) {
                                            echo '8h+';
                                        } else {
                                            $h = intdiv($seg, 3600);
                                            $m = intdiv($seg % 3600, 60);
                                            $s = $seg % 60;
                                            echo $h > 0
                                                ? sprintf('%d:%02d:%02d', $h, $m, $s)
                                                : sprintf('%d:%02d', $m, $s);
                                        }
                                    } else {
                                        echo '<span class="text-gray-400">-</span>';
                                    }
                                    ?>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap text-center">
                                    <?php if ($status === 'fez' || $status === 'fez_e_nao_viu'): ?>
                                        <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/aluno/<?= $aluno['aluno_id'] ?>/exercicios" 
                                           class="p-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg transition-colors inline-flex items-center justify-center"
                                           title="Ver respostas"
                                           aria-label="Ver respostas">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h8M8 14h5m-7 6h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                            </svg>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-gray-400 text-sm">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Seção Redações -->
<?php if (!empty($redacoes)): ?>
<div id="section-redacoes" class="section-content <?= !empty($temExercicios) && !empty($alunosExercicios) ? 'hidden' : '' ?>">
    <div class="space-y-6">
        <?php foreach ($redacoes as $redacao): ?>
            <div class="bg-white rounded-xl shadow-lg p-6 border border-gray-200">
                <div class="mb-4">
                    <div class="flex justify-between items-start mb-2">
                        <div class="flex-1">
                            <h3 class="text-lg font-semibold text-gray-900 mb-1">
                                <?= htmlspecialchars($redacao['tema'] ?? 'Redação') ?>
                            </h3>
                            <p class="text-sm text-gray-500 mb-2">
                                <?= htmlspecialchars($redacao['descricao'] ?? '') ?>
                            </p>
                        </div>
                        <div class="flex flex-col items-end space-y-2">
                            <span class="px-3 py-1 bg-purple-100 text-purple-800 text-sm rounded-full">
                                <?= $redacao['total_alunos'] ?? 0 ?> aluno(s)
                            </span>
                            <?php if (($redacao['pendentes_correcao'] ?? 0) > 0): ?>
                                <span class="px-3 py-1 bg-red-100 text-red-800 text-sm rounded-full">
                                    <?= $redacao['pendentes_correcao'] ?> pendente(s)
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Alunos que fizeram -->
                <div class="border-t border-gray-200 pt-4">
                    <h4 class="text-md font-semibold text-gray-900 mb-4">Alunos que Fizeram</h4>
                    
                    <?php if (empty($redacao['alunos'])): ?>
                        <p class="text-gray-500 text-sm">Nenhum aluno fez esta redação ainda.</p>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RA</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Versão</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nota Final</th>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($redacao['alunos'] as $alunoRedacao): ?>
                                        <?php
                                        $statusRedacao = $alunoRedacao['status_redacao'] ?? 'nao_viu';
                                        $statusLabels = [
                                            'nao_viu' => ['text' => 'Não Viu', 'color' => 'bg-gray-100 text-gray-800'],
                                            'viu' => ['text' => 'Viu', 'color' => 'bg-yellow-100 text-yellow-800'],
                                            'fez' => ['text' => 'Fez', 'color' => 'bg-green-100 text-green-800'],
                                            'fez_e_nao_viu' => ['text' => 'Fez e não viu', 'color' => 'bg-orange-100 text-orange-800']
                                        ];
                                        $statusInfo = $statusLabels[$statusRedacao] ?? $statusLabels['nao_viu'];
                                        ?>
                                        <tr>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900">
                                                <?= htmlspecialchars($alunoRedacao['aluno_nome']) ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                <?= htmlspecialchars($alunoRedacao['aluno_ra'] ?? '-') ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $statusInfo['color'] ?>">
                                                    <?= $statusInfo['text'] ?>
                                                </span>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                <?= $statusRedacao === 'fez' ? htmlspecialchars($alunoRedacao['redacao_titulo'] ?? '-') : '-' ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                <?= $statusRedacao === 'fez' ? ($alunoRedacao['versao'] ?? 1) : '-' ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-600">
                                                <?php if ($statusRedacao === 'fez'): ?>
                                                    <?php
                                                    // Verificar se deve usar média
                                                    $usarMedia = ($alunoRedacao['usar_media_notas'] ?? 0) == 1 || ($alunoRedacao['r_usar_media_notas'] ?? 0) == 1;
                                                    
                                                    // Determinar qual nota mostrar
                                                    $notaFinal = null;
                                                    
                                                    if ($usarMedia) {
                                                        if (!empty($alunoRedacao['nota_media'])) {
                                                            $notaFinal = (int)$alunoRedacao['nota_media'];
                                                        } elseif (!empty($alunoRedacao['r_nota_media'])) {
                                                            $notaFinal = (int)$alunoRedacao['r_nota_media'];
                                                        } elseif (!empty($alunoRedacao['nota_final_utilizada'])) {
                                                            $notaFinal = (int)$alunoRedacao['nota_final_utilizada'];
                                                        } elseif (!empty($alunoRedacao['r_nota_final_utilizada'])) {
                                                            $notaFinal = (int)$alunoRedacao['r_nota_final_utilizada'];
                                                        }
                                                    } else {
                                                        if (!empty($alunoRedacao['nota_final_professor'])) {
                                                            $notaFinal = (int)$alunoRedacao['nota_final_professor'];
                                                        } elseif (!empty($alunoRedacao['nota_final_utilizada'])) {
                                                            $notaFinal = (int)$alunoRedacao['nota_final_utilizada'];
                                                        } elseif (!empty($alunoRedacao['r_nota_final_utilizada'])) {
                                                            $notaFinal = (int)$alunoRedacao['r_nota_final_utilizada'];
                                                        } elseif (!empty($alunoRedacao['nota_final'])) {
                                                            $notaFinal = (int)$alunoRedacao['nota_final'];
                                                        }
                                                    }
                                                    
                                                    if ($notaFinal !== null && $notaFinal > 0) {
                                                        echo '<span class="font-semibold text-gray-900">' . number_format($notaFinal, 0, ',', '.') . '/1000</span>';
                                                    } else {
                                                        echo '<span class="text-gray-400">-</span>';
                                                    }
                                                    ?>
                                                <?php else: ?>
                                                    <span class="text-gray-400">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-4 py-3 whitespace-nowrap text-sm">
                                                <?php if ($statusRedacao === 'fez' && isset($alunoRedacao['redacao_id'])): ?>
                                                    <div class="flex items-center space-x-3">
                                                        <?php if (in_array($alunoRedacao['status'], ['entregue', 'corrigida_ia', 'retornada']) && ($alunoRedacao['correcao_professor_feita'] ?? 0) == 0): ?>
                                                            <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/redacao/<?= $alunoRedacao['redacao_id'] ?>/corrigir" 
                                                               class="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                                Fazer Correção
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/redacao/<?= $alunoRedacao['redacao_id'] ?>/ver" 
                                                           class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                            Ver Redação
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <span class="text-gray-400 text-sm">-</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Seção Resumos (mesmo layout da aba Exercícios: filtros + Nome, Série, Status, Nota, botão Resumo) -->
<?php if (!empty($resumos)): ?>
<?php $alunosResumos = $alunosResumos ?? []; ?>
<div id="section-resumos" class="section-content hidden">
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-hidden">
            <table class="w-full table-fixed divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Nota</th>
                        <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($alunosResumos as $ar): ?>
                        <?php
                        $realizou = ($ar['status_resumo'] ?? '') === 'fez';
                        $statusTexto = $realizou ? 'Realizou a Jornada' : 'Não realizou a Jornada';
                        $statusCor = $realizou ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800';
                        $notaExibir = $ar['nota'] !== null && $ar['nota'] !== '' ? number_format((float)$ar['nota'], 1) : '-';
                        ?>
                        <tr class="hover:bg-gray-50 resumo-row aluno-row" data-nome="<?= strtolower(htmlspecialchars($ar['aluno_nome'])) ?>" data-realizou="<?= $realizou ? '1' : '0' ?>" data-status="<?= $realizou ? '1' : '0' ?>" data-turma="<?= strtolower(htmlspecialchars($ar['turma_nome'] ?? '')) ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($ar['aluno_nome']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($ar['turma_nome']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $statusCor ?>"><?= $realizou ? 'Realizou' : 'Não realizou' ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900"><?= $notaExibir ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($realizou && !empty($ar['resumo_id'])): ?>
                                    <a href="<?= URL ?>/professor/jornadas/<?= $jornada['id'] ?>/resumos/<?= (int)$ar['resumo_id'] ?>" 
                                       class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-medium rounded-lg transition-colors inline-block">
                                        Resumo
                                    </a>
                                <?php else: ?>
                                    <span class="text-gray-400 text-sm">-</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <p class="mt-3 text-sm text-gray-500">Ao clicar em <strong>Resumo</strong>, você verá o texto do aluno, poderá atribuir uma nota e escrever observações (as observações podem ser exibidas ao aluno).</p>
</div>
<?php endif; ?>

<style>
.section-content {
    animation: fadeIn 0.3s ease-in;
}

.select-safari {
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 20 20" fill="none"><path d="M5 7.5L10 12.5L15 7.5" stroke="%236B7280" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>');
    background-repeat: no-repeat;
    background-position: right 0.65rem center;
    background-size: 0.95rem;
    padding-right: 2rem;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
function showSection(section) {
    // Esconde todas as seções
    const sections = document.querySelectorAll('.section-content');
    sections.forEach(sec => {
        sec.classList.add('hidden');
    });
    
    // Mostra a seção selecionada
    const sectionElement = document.getElementById('section-' + section);
    if (sectionElement) {
        sectionElement.classList.remove('hidden');
    }

    const filtroTipoConteudo = document.getElementById('filtroTipoConteudo');
    if (filtroTipoConteudo && filtroTipoConteudo.value !== section) {
        filtroTipoConteudo.value = section;
    }
    const evt = new Event('change');
    const filtroStatus = document.getElementById('filtroStatus');
    if (filtroStatus) filtroStatus.dispatchEvent(evt);
}

// Mostra a primeira seção disponível por padrão
document.addEventListener('DOMContentLoaded', function() {
    const urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab === 'resumos' && document.getElementById('section-resumos')) {
        showSection('resumos');
        return;
    }
    if (urlTab === 'redacoes' && document.getElementById('section-redacoes')) {
        showSection('redacoes');
        return;
    }
    // Seção já visível por padrão (PHP define qual): ativa no select correspondente
    const sections = document.querySelectorAll('.section-content');
    let firstVisibleId = null;
    sections.forEach(sec => {
        if (!sec.classList.contains('hidden') && sec.id) {
            const id = sec.id.replace('section-', '');
            if (firstVisibleId === null) firstVisibleId = id;
        }
    });
    if (firstVisibleId) {
        showSection(firstVisibleId);
    }

    const filtroTipoConteudo = document.getElementById('filtroTipoConteudo');
    if (filtroTipoConteudo) {
        filtroTipoConteudo.addEventListener('change', function() {
            if (this.value) showSection(this.value);
        });
    }
    
    // Filtro de busca por nome e por status
    const filtroNomeAluno = document.getElementById('filtroNomeAluno');
    const filtroStatus = document.getElementById('filtroStatus');
    const filtroTurma = document.getElementById('filtroTurma');
    const limparFiltroAluno = document.getElementById('limparFiltroAluno');
    const contadorAlunos = document.getElementById('contadorAlunos');
    
    function aplicarFiltros() {
        const busca = (filtroNomeAluno && filtroNomeAluno.value) ? filtroNomeAluno.value.toLowerCase().trim() : '';
        const statusFiltro = (filtroStatus && filtroStatus.value) ? filtroStatus.value.trim() : '';
        const turmaFiltro = (filtroTurma && filtroTurma.value) ? filtroTurma.value.trim() : '';
        let visiveis = 0;
        
        const secaoVisivel = document.querySelector('.section-content:not(.hidden)');
        const alunoRows = secaoVisivel ? secaoVisivel.querySelectorAll('.aluno-row') : [];
        alunoRows.forEach(row => {
            const nome = row.getAttribute('data-nome') || '';
            const realizou = row.getAttribute('data-status') || '0';
            const turma = row.getAttribute('data-turma') || '';
            const nomeOk = !busca || nome.includes(busca);
            const statusOk = !statusFiltro || (statusFiltro === 'realizou' && realizou === '1') || (statusFiltro === 'nao_realizou' && realizou === '0');
            const turmaOk = !turmaFiltro || turma === turmaFiltro;
            if (nomeOk && statusOk && turmaOk) {
                row.style.display = '';
                visiveis++;
            } else {
                row.style.display = 'none';
            }
        });
        
        if (contadorAlunos) {
            contadorAlunos.textContent = visiveis;
        }
    }
    
    if (filtroNomeAluno) {
        filtroNomeAluno.addEventListener('input', aplicarFiltros);
    }
    if (filtroStatus) {
        filtroStatus.addEventListener('change', aplicarFiltros);
    }
    if (filtroTurma) {
        filtroTurma.addEventListener('change', aplicarFiltros);
    }
    
    if (limparFiltroAluno) {
        limparFiltroAluno.addEventListener('click', function() {
            if (filtroNomeAluno) filtroNomeAluno.value = '';
            if (filtroStatus) filtroStatus.value = '';
            if (filtroTurma) filtroTurma.value = '';
            aplicarFiltros();
        });
    }
    aplicarFiltros();
    
    // Ordenação pela tabela (clique no título da coluna: menor→maior / maior→menor)
    const tbodyAlunos = document.getElementById('tbodyAlunos');
    const thSortables = document.querySelectorAll('#section-exercicios .th-sortable');
    let sortColumn = null;
    let sortDir = 'asc';
    
    function getSortValue(row, key, type) {
        const val = row.getAttribute('data-' + key);
        if (type === 'number') {
            const n = parseFloat(val);
            return isNaN(n) ? -999 : n;
        }
        return (val || '').toLowerCase();
    }
    
    function sortTable(key, type) {
        if (!tbodyAlunos) return;
        const rows = Array.from(tbodyAlunos.querySelectorAll('.aluno-row'));
        const dir = sortColumn === key ? (sortDir === 'asc' ? 'desc' : 'asc') : 'asc';
        sortColumn = key;
        sortDir = dir;
        
        rows.sort(function(a, b) {
            const va = getSortValue(a, key, type);
            const vb = getSortValue(b, key, type);
            if (type === 'number') {
                return dir === 'asc' ? (va - vb) : (vb - va);
            }
            const cmp = String(va).localeCompare(String(vb), 'pt-BR');
            return dir === 'asc' ? cmp : -cmp;
        });
        
        rows.forEach(r => tbodyAlunos.appendChild(r));
        
        thSortables.forEach(th => {
            const icon = th.querySelector('.sort-icon');
            const k = th.getAttribute('data-sort');
            if (icon) {
                if (k === key) {
                    icon.textContent = dir === 'asc' ? ' ▲' : ' ▼';
                    icon.classList.add('text-blue-600', 'font-bold');
                } else {
                    icon.textContent = '';
                    icon.classList.remove('text-blue-600', 'font-bold');
                }
            }
        });
    }
    
    thSortables.forEach(th => {
        th.addEventListener('click', function() {
            const key = this.getAttribute('data-sort');
            const type = this.getAttribute('data-type') || 'text';
            if (key) sortTable(key, type);
        });
    });
    
});
</script>
