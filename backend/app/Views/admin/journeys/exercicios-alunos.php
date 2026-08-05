<!-- Header -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Resultados da Jornada - <?= htmlspecialchars($jornada['titulo']) ?>
            </h2>
            <p class="text-gray-600">
                <?= htmlspecialchars($jornada['materia_nome'] ?? 'Sem matéria') ?> • 
                <?= htmlspecialchars($turmas_nomes_texto ?? $jornada['turma_nome'] ?? 'Sem turma') ?>
            </p>
        </div>
        <div>
            <a href="<?= $base_url_jornadas ?? (URL.'/professor/jornadas') ?>/<?= $jornada['id'] ?>" 
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
$tiposEtapaLabel = [
    'video' => 'Conteúdo',
    'conteudo' => 'Conteúdo',
    'dica_professor' => 'Dica do Professor',
    'exercicios' => 'Exercícios',
    'exercicio' => 'Exercícios',
    'resumo_aluno' => 'Resumo'
];
$primeiraSecaoVisivel = null;
?>
<!-- Cards de Menu (Tabs) -->
<div class="bg-white rounded-lg shadow-sm border-b border-gray-200 mb-6">
    <div class="flex space-x-1 overflow-x-auto">
        <!-- Tabs por Etapa (Conteúdo, Exercício, Dica, etc.) -->
        <?php 
        $jaTemTabExercicios = false;
        foreach ($etapas as $idx => $etapa): 
            $tipo = $etapa['tipo_modulo'] ?? '';
            $ehConteudoOuDica = in_array($tipo, ['video', 'conteudo', 'dica_professor'], true);
            $ehExercicio = in_array($tipo, ['exercicios', 'exercicio'], true);
            $slug = 'etapa-' . (int)$etapa['id'];
            $label = $tiposEtapaLabel[$tipo] ?? $etapa['titulo'];
            $countFez = count(array_filter($etapa['alunos'] ?? [], function($a) { return !empty($a['fez']); }));
            if ($primeiraSecaoVisivel === null) {
                $primeiraSecaoVisivel = $ehExercicio ? 'exercicios' : $slug;
            }
        ?>
        <?php if ($ehConteudoOuDica): ?>
        <button id="tab-<?= $slug ?>" 
                onclick="showSection('<?= $slug ?>')"
                class="tab-button flex items-center space-x-2 px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-teal-600 hover:border-teal-300 transition-all whitespace-nowrap"
                data-color="teal">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path>
            </svg>
            <span><?= htmlspecialchars($etapa['titulo']) ?></span>
            <span class="ml-2 px-2 py-0.5 bg-teal-100 text-teal-800 rounded-full text-xs font-semibold"><?= $countFez ?></span>
        </button>
        <?php elseif ($ehExercicio && !empty($temExercicios) && !empty($alunosExercicios)): ?>
        <button id="tab-<?= $jaTemTabExercicios ? $slug : 'exercicios' ?>" 
                onclick="showSection('exercicios')"
                class="tab-button flex items-center space-x-2 px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-300 transition-all whitespace-nowrap"
                data-color="blue">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <span><?= htmlspecialchars($etapa['titulo']) ?></span>
            <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold"><?= count($alunosExercicios) ?></span>
        </button>
        <?php if ($ehExercicio) { $jaTemTabExercicios = true; } ?>
        <?php endif; ?>
        <?php endforeach; ?>
        
        <!-- Tab Exercícios (legado: se não houver etapas ou como fallback) -->
        <?php if (!empty($temExercicios) && !empty($alunosExercicios) && empty($etapas)): ?>
        <button id="tab-exercicios" 
                onclick="showSection('exercicios')"
                class="tab-button flex items-center space-x-2 px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-blue-600 hover:border-blue-300 transition-all whitespace-nowrap"
                data-color="blue">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
            </svg>
            <span>Exercícios</span>
            <span class="ml-2 px-2 py-0.5 bg-blue-100 text-blue-800 rounded-full text-xs font-semibold"><?= count($alunosExercicios) ?></span>
        </button>
        <?php endif; ?>
        <?php if (!empty($temExercicios) && !empty($alunosExercicios) && $primeiraSecaoVisivel === null): $primeiraSecaoVisivel = 'exercicios'; endif; ?>

        <!-- Tab Redação -->
        <?php if (!empty($redacoes)): ?>
        <button id="tab-redacoes" 
                onclick="showSection('redacoes')"
                class="tab-button <?= empty($temExercicios) || empty($alunosExercicios) ? 'active border-purple-600 text-purple-600' : 'border-transparent text-gray-500' ?> flex items-center space-x-2 px-6 py-4 text-sm font-medium border-b-2 hover:text-purple-600 hover:border-purple-300 transition-all whitespace-nowrap"
                data-color="purple">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
            </svg>
            <span>Redação</span>
            <span class="ml-2 px-2 py-0.5 bg-purple-100 text-purple-800 rounded-full text-xs font-semibold">
                <?= count($redacoes) ?>
            </span>
            <?php 
            $totalPendentes = array_sum(array_column($redacoes, 'pendentes_correcao'));
            if ($totalPendentes > 0): 
            ?>
                <span class="ml-1 px-2 py-0.5 bg-red-100 text-red-800 rounded-full text-xs font-semibold">
                    <?= $totalPendentes ?> pendente(s)
                </span>
            <?php endif; ?>
        </button>
        <?php endif; ?>

        <!-- Tab Resumo (número = total de resumos entregues por alunos, não quantidade de módulos) -->
        <?php 
        $totalResumosEntregues = 0;
        if (!empty($resumos)) {
            foreach ($resumos as $r) {
                $totalResumosEntregues += count(array_filter($r['alunos'] ?? [], function($a) { return ($a['status_resumo'] ?? '') === 'fez'; }));
            }
        }
        ?>
        <?php if (!empty($resumos)): ?>
        <button id="tab-resumos" 
                onclick="showSection('resumos')"
                class="tab-button flex items-center space-x-2 px-6 py-4 text-sm font-medium border-b-2 border-transparent text-gray-500 hover:text-green-600 hover:border-green-300 transition-all whitespace-nowrap"
                data-color="green">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
            </svg>
            <span>Resumo</span>
            <span class="ml-2 px-2 py-0.5 bg-green-100 text-green-800 rounded-full text-xs font-semibold">
                <?= $totalResumosEntregues ?>
            </span>
        </button>
        <?php endif; ?>
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
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
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
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($a['aluno_nome']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($a['turma_nome']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-center">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $fez ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                <?= $fez ? 'Concluiu' : 'Não concluiu' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700">
                            <?= $seg > 0 ? gmdate('H:i:s', $seg) : '<span class="text-gray-400">-</span>' ?>
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
        <!-- Filtro de Busca e Status -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 mb-4">
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
                    <label for="filtroStatus" class="block text-sm font-medium text-gray-700 mb-2">
                        Status
                    </label>
                    <select id="filtroStatus" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500 bg-white">
                        <option value="">Todos</option>
                        <option value="realizou">Realizou a Jornada</option>
                        <option value="nao_realizou">Não realizou a Jornada</option>
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
        
        <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="nome" data-type="text" title="Clique para ordenar">
                                Nome <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="serie" data-type="text" title="Clique para ordenar">
                                Série <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="status" data-type="number" title="Clique para ordenar">
                                Status <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="nota" data-type="number" title="Clique para ordenar">
                                Nota <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="acertos" data-type="number" title="Clique para ordenar">
                                Acertos <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="erros" data-type="number" title="Clique para ordenar">
                                Erros <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100 select-none th-sortable" data-sort="tempo" data-type="number" title="Clique para ordenar">
                                Tempo <span class="sort-icon ml-1 inline-block w-4 text-gray-400"></span>
                            </th>
                            <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
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
                                data-nota="<?= $percentual ?>"
                                data-acertos="<?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? (int)$aluno['acertos'] : -1 ?>"
                                data-erros="<?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? (int)$aluno['erros'] : -1 ?>"
                                data-tempo="<?= (int)($aluno['tempo_total_segundos'] ?? 0) ?>">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900">
                                        <?= htmlspecialchars($aluno['aluno_nome']) ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-600">
                                        <?= htmlspecialchars($aluno['turma_nome'] ?? $aluno['serie'] ?? '-') ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $statusCor ?>">
                                        <?= htmlspecialchars($statusTexto) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <div class="text-sm font-semibold <?= $percentual >= 70 ? 'text-green-600' : ($percentual >= 50 ? 'text-yellow-600' : 'text-red-600') ?>">
                                        <?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? $notaExibir : '-' ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 bg-green-100 text-green-800 text-xs font-semibold rounded-full">
                                        <?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? $aluno['acertos'] : '-' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <span class="px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                                        <?= ($status === 'fez' || $status === 'fez_e_nao_viu') ? $aluno['erros'] : '-' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-700">
                                    <?php 
                                    $seg = (int)($aluno['tempo_total_segundos'] ?? 0); 
                                    echo $seg > 0 ? gmdate('H:i:s', $seg) : '<span class="text-gray-400">-</span>'; 
                                    ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-center">
                                    <?php if ($status === 'fez' || $status === 'fez_e_nao_viu'): ?>
                                        <a href="<?= $base_url_jornadas ?? (URL.'/professor/jornadas') ?>/<?= $jornada['id'] ?>/aluno/<?= $aluno['aluno_id'] ?>/exercicios" 
                                           class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium rounded-lg transition-colors inline-block">
                                            Respostas
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
                                                            <a href="<?= $base_url_jornadas ?? (URL.'/professor/jornadas') ?>/<?= $jornada['id'] ?>/redacao/<?= $alunoRedacao['redacao_id'] ?>/corrigir" 
                                                               class="px-3 py-1.5 bg-orange-600 hover:bg-orange-700 text-white text-xs font-medium rounded-lg transition-colors">
                                                                Fazer Correção
                                                            </a>
                                                        <?php endif; ?>
                                                        <a href="<?= $base_url_jornadas ?? (URL.'/professor/jornadas') ?>/<?= $jornada['id'] ?>/redacao/<?= $alunoRedacao['redacao_id'] ?>/ver" 
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
    <!-- Filtro de Busca e Status -->
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-4 mb-4">
        <div class="flex flex-wrap items-end gap-4">
            <div class="flex-1 min-w-[200px]">
                <label for="filtroNomeAlunoResumo" class="block text-sm font-medium text-gray-700 mb-2">Buscar por nome do aluno</label>
                <div class="relative">
                    <input type="text" id="filtroNomeAlunoResumo" placeholder="Digite o nome do aluno..." 
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    <svg class="w-5 h-5 text-gray-400 absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                    </svg>
                </div>
            </div>
            <div class="w-48">
                <label for="filtroStatusResumo" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="filtroStatusResumo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500 bg-white">
                    <option value="">Todos</option>
                    <option value="realizou">Realizou a Jornada</option>
                    <option value="nao_realizou">Não realizou a Jornada</option>
                </select>
            </div>
            <div>
                <button id="limparFiltroResumo" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors">Limpar</button>
            </div>
        </div>
        <div class="mt-2 text-sm text-gray-600">
            <span id="contadorAlunosResumos"><?= count($alunosResumos) ?></span> aluno(s) encontrado(s)
        </div>
    </div>
    
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
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
                        <tr class="hover:bg-gray-50 resumo-row" data-nome="<?= strtolower(htmlspecialchars($ar['aluno_nome'])) ?>" data-realizou="<?= $realizou ? '1' : '0' ?>">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($ar['aluno_nome']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($ar['turma_nome']) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <span class="px-2 py-1 rounded-full text-xs font-semibold <?= $statusCor ?>"><?= htmlspecialchars($statusTexto) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-center text-sm font-semibold text-gray-900"><?= $notaExibir ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-center">
                                <?php if ($realizou && !empty($ar['resumo_id'])): ?>
                                    <a href="<?= $base_url_jornadas ?? (URL.'/professor/jornadas') ?>/<?= $jornada['id'] ?>/resumos/<?= (int)$ar['resumo_id'] ?>" 
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
.tab-button {
    position: relative;
    transition: all 0.3s ease;
}

.tab-button.active {
    color: #2563eb;
    border-bottom-color: #2563eb !important;
}

.tab-button:hover:not(.active) {
    color: #374151;
    border-bottom-color: #d1d5db;
}

.section-content {
    animation: fadeIn 0.3s ease-in;
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
    
    // Remove active de todas as tabs e reseta cores
    const tabs = document.querySelectorAll('.tab-button');
    tabs.forEach(tab => {
        tab.classList.remove('active');
        tab.classList.remove('border-blue-600', 'text-blue-600');
        tab.classList.remove('border-purple-600', 'text-purple-600');
        tab.classList.remove('border-green-600', 'text-green-600');
        tab.classList.remove('border-teal-600', 'text-teal-600');
        tab.classList.add('border-transparent', 'text-gray-500');
    });
    
    // Mostra a seção selecionada
    const sectionElement = document.getElementById('section-' + section);
    if (sectionElement) {
        sectionElement.classList.remove('hidden');
    }
    
    // Ativa a tab selecionada com a cor apropriada
    const tabElement = document.getElementById('tab-' + section);
    if (tabElement) {
        const color = tabElement.getAttribute('data-color') || 'blue';
        tabElement.classList.add('active');
        tabElement.classList.remove('border-transparent', 'text-gray-500');
        
        // Aplica a cor baseada no atributo data-color
        if (color === 'purple') {
            tabElement.classList.add('border-purple-600', 'text-purple-600');
        } else if (color === 'green') {
            tabElement.classList.add('border-green-600', 'text-green-600');
        } else if (color === 'teal') {
            tabElement.classList.add('border-teal-600', 'text-teal-600');
        } else {
            tabElement.classList.add('border-blue-600', 'text-blue-600');
        }
    }
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
    // Seção já visível por padrão (PHP define qual): ativa a tab correspondente
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
    
    // Filtro de busca por nome e por status
    const filtroNomeAluno = document.getElementById('filtroNomeAluno');
    const filtroStatus = document.getElementById('filtroStatus');
    const limparFiltroAluno = document.getElementById('limparFiltroAluno');
    const contadorAlunos = document.getElementById('contadorAlunos');
    const alunoRows = document.querySelectorAll('.aluno-row');
    
    function aplicarFiltros() {
        const busca = (filtroNomeAluno && filtroNomeAluno.value) ? filtroNomeAluno.value.toLowerCase().trim() : '';
        const statusFiltro = (filtroStatus && filtroStatus.value) ? filtroStatus.value.trim() : '';
        let visiveis = 0;
        
        alunoRows.forEach(row => {
            const nome = row.getAttribute('data-nome') || '';
            const realizou = row.getAttribute('data-realizou') || '0';
            const nomeOk = !busca || nome.includes(busca);
            const statusOk = !statusFiltro || (statusFiltro === 'realizou' && realizou === '1') || (statusFiltro === 'nao_realizou' && realizou === '0');
            if (nomeOk && statusOk) {
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
    
    if (limparFiltroAluno) {
        limparFiltroAluno.addEventListener('click', function() {
            if (filtroNomeAluno) filtroNomeAluno.value = '';
            if (filtroStatus) filtroStatus.value = '';
            aplicarFiltros();
        });
    }
    
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
    
    // Filtros da aba Resumo
    const filtroNomeResumo = document.getElementById('filtroNomeAlunoResumo');
    const filtroStatusResumo = document.getElementById('filtroStatusResumo');
    const limparFiltroResumo = document.getElementById('limparFiltroResumo');
    const contadorResumos = document.getElementById('contadorAlunosResumos');
    const resumoRows = document.querySelectorAll('.resumo-row');
    
    function aplicarFiltrosResumo() {
        if (!resumoRows.length) return;
        const busca = (filtroNomeResumo && filtroNomeResumo.value) ? filtroNomeResumo.value.toLowerCase().trim() : '';
        const statusFiltro = (filtroStatusResumo && filtroStatusResumo.value) ? filtroStatusResumo.value.trim() : '';
        let visiveis = 0;
        resumoRows.forEach(row => {
            const nome = row.getAttribute('data-nome') || '';
            const realizou = row.getAttribute('data-realizou') || '0';
            const nomeOk = !busca || nome.includes(busca);
            const statusOk = !statusFiltro || (statusFiltro === 'realizou' && realizou === '1') || (statusFiltro === 'nao_realizou' && realizou === '0');
            if (nomeOk && statusOk) {
                row.style.display = '';
                visiveis++;
            } else {
                row.style.display = 'none';
            }
        });
        if (contadorResumos) contadorResumos.textContent = visiveis;
    }
    
    if (filtroNomeResumo) filtroNomeResumo.addEventListener('input', aplicarFiltrosResumo);
    if (filtroStatusResumo) filtroStatusResumo.addEventListener('change', aplicarFiltrosResumo);
    if (limparFiltroResumo) {
        limparFiltroResumo.addEventListener('click', function() {
            if (filtroNomeResumo) filtroNomeResumo.value = '';
            if (filtroStatusResumo) filtroStatusResumo.value = '';
            aplicarFiltrosResumo();
        });
    }
});
</script>
