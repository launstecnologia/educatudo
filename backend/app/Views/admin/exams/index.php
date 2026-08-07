<?php
$filters = $filters ?? [];
$filterTitulo = $filters['titulo'] ?? '';
$filterData = $filters['data_prova'] ?? '';
$filterBlocoModeloId = (int)($filters['bloco_modelo_id'] ?? 0);
$filterTurmaId = (int)($filters['turma_id'] ?? 0);
$filterMateriaId = (int)($filters['materia_id'] ?? 0);
$filterStatus = $filters['status'] ?? '';
$filterBimestre = (int)($filters['bimestre'] ?? 0);
$filterTipoAvaliacaoId = (int)($filters['tipo_avaliacao_id'] ?? 0);
$turmas = $turmas ?? [];
$materias = $materias ?? [];
$tiposAvaliacaoParaFiltro = $tipos_avaliacao_para_filtro ?? [];
$blocosParaFiltro = $blocos_para_filtro ?? [];
$filtrosAtivosCount = 0;
foreach (['titulo', 'data_prova', 'bloco_modelo_id', 'turma_id', 'materia_id', 'status', 'bimestre', 'tipo_avaliacao_id'] as $fk) {
    if (!empty($filters[$fk])) {
        $filtrosAtivosCount++;
    }
}
?>
<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Avaliações e Notas
            </h2>
            <p class="text-gray-600">
                Gerencie todos os blocos de provas online do sistema
            </p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
            <a href="<?= URL ?>/admin/provas/tipos-avaliacao"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-layer-group mr-2 text-gray-500"></i>
                Tipo de Avaliação
            </a>
            <a href="<?= URL ?>/admin/blocos-modelo"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-table-cells mr-2 text-gray-500"></i>
                Bloco Professor
            </a>
            <a href="<?= URL ?>/admin/provas/blocos/criar"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Evento
            </a>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<?php if (isset($stats)): ?>
<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-blue-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Total de Blocos</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['total_blocos'] ?? 0 ?></p>
            </div>
            <div class="bg-blue-100 rounded-full p-3">
                <svg class="w-8 h-8 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-amber-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Aguardando</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['blocos_aguardando'] ?? 0 ?></p>
            </div>
            <div class="bg-amber-100 rounded-full p-3">
                <svg class="w-8 h-8 text-amber-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-indigo-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Aprovado</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['blocos_aprovados'] ?? 0 ?></p>
            </div>
            <div class="bg-indigo-100 rounded-full p-3">
                <svg class="w-8 h-8 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-green-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Liberado</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['blocos_liberados'] ?? 0 ?></p>
            </div>
            <div class="bg-green-100 rounded-full p-3">
                <svg class="w-8 h-8 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-gray-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Concluído</p>
                <p class="text-3xl font-bold text-gray-900"><?= $stats['blocos_concluidos'] ?? 0 ?></p>
            </div>
            <div class="bg-gray-100 rounded-full p-3">
                <svg class="w-8 h-8 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border-l-4 border-purple-500">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-gray-600">Provas Pendentes</p>
                <p class="text-3xl font-bold text-gray-900"><?= count($provas_pendentes ?? []) ?></p>
            </div>
            <div class="bg-purple-100 rounded-full p-3">
                <svg class="w-8 h-8 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Provas Pendentes Alert -->
<?php if (!empty($provas_pendentes)): ?>
<div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-6 rounded-lg">
    <div class="flex">
        <div class="flex-shrink-0">
            <svg class="h-5 w-5 text-yellow-400" fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path>
            </svg>
        </div>
        <div class="ml-3">
            <p class="text-sm text-yellow-700">
                <strong><?= count($provas_pendentes) ?> prova(s) pendente(s)</strong> aguardando agrupamento em blocos.
                <a href="<?= URL ?>/admin/provas/blocos/criar" class="font-medium underline ml-1">Criar novo bloco</a>
            </p>
        </div>
    </div>
</div>
<?php endif; ?>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar provas</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/provas" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_titulo" class="block text-sm font-medium text-gray-700 mb-1.5">Título</label>
                <input type="text" id="filtro_titulo" name="titulo" value="<?= htmlspecialchars($filterTitulo, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="Parte do nome do evento"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_data" class="block text-sm font-medium text-gray-700 mb-1.5">Data da prova</label>
                <input type="date" id="filtro_data" name="data_prova" value="<?= htmlspecialchars($filterData, ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="aguardando" <?= $filterStatus === 'aguardando' ? 'selected' : '' ?>>Aguardando</option>
                    <option value="aprovado" <?= $filterStatus === 'aprovado' ? 'selected' : '' ?>>Aprovado</option>
                    <option value="liberado" <?= $filterStatus === 'liberado' ? 'selected' : '' ?>>Liberado</option>
                    <option value="concluido" <?= $filterStatus === 'concluido' ? 'selected' : '' ?>>Concluído</option>
                </select>
            </div>
            <div>
                <label for="filtro_tipo_avaliacao" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de avaliação</label>
                <select id="filtro_tipo_avaliacao" name="tipo_avaliacao_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($tiposAvaliacaoParaFiltro as $ta): ?>
                        <option value="<?= (int)$ta['id'] ?>" <?= $filterTipoAvaliacaoId === (int)$ta['id'] ? 'selected' : '' ?>><?= htmlspecialchars($ta['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_bimestre" class="block text-sm font-medium text-gray-700 mb-1.5">Bimestre</label>
                <select id="filtro_bimestre" name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="1" <?= $filterBimestre === 1 ? 'selected' : '' ?>>1º Bimestre</option>
                    <option value="2" <?= $filterBimestre === 2 ? 'selected' : '' ?>>2º Bimestre</option>
                    <option value="3" <?= $filterBimestre === 3 ? 'selected' : '' ?>>3º Bimestre</option>
                    <option value="4" <?= $filterBimestre === 4 ? 'selected' : '' ?>>4º Bimestre</option>
                </select>
            </div>
            <div>
                <label for="filtro_bloco" class="block text-sm font-medium text-gray-700 mb-1.5">Bloco</label>
                <select id="filtro_bloco" name="bloco_modelo_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($blocosParaFiltro as $b): ?>
                        <option value="<?= (int)$b['id'] ?>" <?= $filterBlocoModeloId === (int)$b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_materia" class="block text-sm font-medium text-gray-700 mb-1.5">Matéria</label>
                <select id="filtro_materia" name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($materias as $mat): ?>
                        <option value="<?= (int)$mat['id'] ?>" <?= $filterMateriaId === (int)$mat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mat['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_turma" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="filtro_turma" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($turmas as $t): ?>
                        <option value="<?= (int)$t['id'] ?>" <?= $filterTurmaId === (int)$t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <button type="button" onclick="clearFilters()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<script>
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}

function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function clearFilters() {
    window.location.href = <?= json_encode(URL . '/admin/provas') ?>;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
    }
});
</script>

<!-- Blocos Table -->
<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Evento de Provas</h3>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data/Horário</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bimestre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de Avaliação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Provas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($blocos)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center">
                            <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                            <p class="text-gray-500 text-lg mb-2">Nenhum bloco criado ainda</p>
                            <p class="text-sm text-gray-400 mb-4">Comece criando um novo bloco de provas</p>
                            <a href="<?= URL ?>/admin/provas/blocos/criar" 
                               class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg hover:opacity-90">
                                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                                </svg>
                                Criar Primeiro Bloco
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($blocos as $bloco): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <?php
                                $turmasTexto = trim($bloco['turmas_demarcadas'] ?? '');
                                if ($turmasTexto === '' && !empty(trim($bloco['turmas_por_professor'] ?? ''))) {
                                    $turmasTexto = trim($bloco['turmas_por_professor']);
                                }
                                if ($turmasTexto === '') {
                                    $turmasTexto = !empty($bloco['turma_nome']) ? (string)$bloco['turma_nome'] : 'Todas';
                                }
                                $nomeBloco = trim((string)($bloco['bloco_modelo_nome'] ?? ''));
                                $bimestreNumero = (int)($bloco['bimestre'] ?? 0);
                                $bimestreTexto = $bimestreNumero >= 1 && $bimestreNumero <= 4 ? ($bimestreNumero . 'º') : '';
                                $linhaSecundaria = trim(($nomeBloco !== '' ? $nomeBloco : 'Bloco') . ($bimestreTexto !== '' ? (' ' . $bimestreTexto) : '') . ' - ' . $turmasTexto);
                                ?>
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($bloco['titulo']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($linhaSecundaria) ?></div>
                                <?php $qtdCanceladasBloco = (int)(($canceladas_por_bloco ?? [])[(int)$bloco['id']] ?? 0); ?>
                                <?php if ($qtdCanceladasBloco > 0): ?>
                                <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/canceladas"
                                   class="inline-flex items-center gap-1 mt-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 border border-amber-300 hover:bg-amber-200"
                                   title="Provas canceladas aguardando ação do coordenador">
                                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                    <?= $qtdCanceladasBloco ?> cancelada(s)
                                </a>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">
                                    <?= !empty($bloco['created_at']) ? date('d/m/Y', strtotime($bloco['created_at'])) : date('d/m/Y', strtotime($bloco['data_prova'])) ?>
                                </div>
                                <div class="text-sm text-gray-500">
                                    <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> - <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?php
                                $bimestreLabel = [
                                    1 => '1º Bimestre',
                                    2 => '2º Bimestre',
                                    3 => '3º Bimestre',
                                    4 => '4º Bimestre',
                                ];
                                $bim = (int)($bloco['bimestre'] ?? 0);
                                echo isset($bimestreLabel[$bim]) ? htmlspecialchars($bimestreLabel[$bim]) : '<span class="text-gray-400">—</span>';
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                <?= !empty($bloco['tipo_avaliacao_nome']) ? htmlspecialchars($bloco['tipo_avaliacao_nome']) : '<span class="text-gray-400">—</span>' ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $entregues = (int)($bloco['total_provas_entregues'] ?? 0);
                                $esperadas = (int)($bloco['total_provas_esperadas'] ?? 0);
                                $textoProvas = $esperadas > 0 ? "{$entregues}/{$esperadas}" : ($bloco['total_provas'] ?? 0);
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800">
                                    <?= $textoProvas ?> <?= $esperadas > 0 ? '' : 'prova(s)' ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $st = $bloco['status'] ?? 'aguardando';
                                $labels = ['aguardando' => ['Aguardando', 'bg-amber-100 text-amber-800'], 'aprovado' => ['Aprovado', 'bg-indigo-100 text-indigo-800'], 'liberado' => ['Liberado', 'bg-green-100 text-green-800'], 'concluido' => ['Concluído', 'bg-gray-100 text-gray-800']];
                                $lb = $labels[$st] ?? ['Aguardando', 'bg-amber-100 text-amber-800'];
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $lb[1] ?>"><?= $lb[0] ?></span>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium">
                                <button type="button" 
                                        class="btn-acoes-bloco inline-flex items-center gap-2 px-4 py-2 rounded-lg border transition-colors text-white hover:opacity-90"
                                        style="background-color: <?= htmlspecialchars($primary_color ?? '#3b82f6') ?>; color: <?= htmlspecialchars($primary_text_color ?? '#ffffff') ?>; border-color: <?= htmlspecialchars($primary_color ?? '#3b82f6') ?>"
                                        title="Ações"
                                        data-bloco-id="<?= (int)$bloco['id'] ?>"
                                        data-status="<?= htmlspecialchars($st) ?>"
                                        data-gerenciar="<?= htmlspecialchars(URL . '/admin/provas/blocos/' . $bloco['id'] . '/gerenciar') ?>"
                                        data-editar="<?= htmlspecialchars(URL . '/admin/provas/blocos/' . $bloco['id'] . '/editar') ?>"
                                        data-duplicar="<?= htmlspecialchars(URL . '/admin/provas/blocos/' . $bloco['id'] . '/duplicar') ?>"
                                        onclick="abrirDropdownAcoes(this)">
                                    <span>Ações</span>
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pag = $pagination ?? [];
    $total = (int)($pag['total'] ?? 0);
    $perPage = (int)($pag['per_page'] ?? 10);
    $page = (int)($pag['page'] ?? 1);
    $totalPages = (int)($pag['total_pages'] ?? 1);
    $queryParams = array_merge($_GET ?? [], []);
    unset($queryParams['page']);
    $baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
    $sep = $baseQuery === '' ? '?' : '&';
    ?>
    <?php if ($total > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> bloco(s)
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="<?= URL ?>/admin/provas<?= $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= URL ?>/admin/provas<?= $baseQuery . $sep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= URL ?>/admin/provas<?= $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Portal do dropdown (fora do card/table para não ser cortado) -->
<div id="dropdown-actions-portal" class="hidden fixed z-[100] w-56 py-1 bg-white rounded-lg shadow-xl border border-gray-200"></div>

<script>
function abrirDropdownAcoes(btn) {
    var portal = document.getElementById('dropdown-actions-portal');
    if (!portal) return;
    var blocoId = btn.getAttribute('data-bloco-id');
    var status = btn.getAttribute('data-status') || '';
    var gerenciar = btn.getAttribute('data-gerenciar') || '';
    var editar = btn.getAttribute('data-editar') || '';
    var duplicar = btn.getAttribute('data-duplicar') || '';
    var rect = btn.getBoundingClientRect();
    portal.style.left = '';
    portal.style.right = (window.innerWidth - rect.right) + 'px';
    portal.style.top = (rect.bottom + 4) + 'px';
    var html =
        '<a href="' + gerenciar + '" class="flex items-center gap-2 px-4 py-2 text-sm text-green-700 hover:bg-green-50 rounded-t-lg">' +
        '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path></svg> Gerenciar</a>' +
        '<a href="' + editar + '" class="flex items-center gap-2 px-4 py-2 text-sm text-amber-700 hover:bg-amber-50">' +
        '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg> Editar</a>' +
        '<form action="' + duplicar + '" method="post" class="block" onsubmit="return confirm(\'Duplicar este bloco? Serão copiados o evento e todas as provas com as questões já cadastradas. O novo bloco ficará como Não liberado.\');">' +
        '<button type="submit" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-left text-blue-700 hover:bg-blue-50">' +
        '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg> Duplicar</button></form>';
    if (status !== 'concluido') {
        html +=
            '<button type="button" onclick="fecharDropdownActions(); marcarBlocoConcluido(' + blocoId + ');" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-left text-gray-700 hover:bg-gray-50">' +
            '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> Marcar como concluído</button>';
    }
    html +=
        '<button type="button" onclick="fecharDropdownActions(); excluirBloco(' + blocoId + ');" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-left text-red-700 hover:bg-red-50 rounded-b-lg">' +
        '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg> Excluir</button>';
    portal.innerHTML = html;
    portal.classList.remove('hidden');
}

function marcarBlocoConcluido(blocoId) {
    if (!confirm('Marcar este evento como concluído?')) {
        return;
    }
    fetch('<?= URL ?>/admin/provas/blocos/' + blocoId + '/marcar-concluido', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(function(response) { return response.json(); })
    .then(function(data) {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(function(error) {
        console.error('Erro:', error);
        alert('Erro ao marcar o bloco como concluído');
    });
}
function fecharDropdownActions() {
    var portal = document.getElementById('dropdown-actions-portal');
    if (portal) portal.classList.add('hidden');
}
document.addEventListener('click', function(e) {
    var portal = document.getElementById('dropdown-actions-portal');
    if (!portal || portal.classList.contains('hidden')) return;
    if (!e.target.closest('#dropdown-actions-portal') && !e.target.closest('.btn-acoes-bloco')) {
        fecharDropdownActions();
    }
});

function toggleLiberado(blocoId, novoStatus) {
    if (!confirm('Tem certeza que deseja ' + (novoStatus ? 'liberar' : 'bloquear') + ' este bloco?')) {
        return;
    }
    
    fetch(`<?= URL ?>/admin/provas/blocos/${blocoId}/toggle-liberado`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro desconhecido'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao alterar status do bloco');
    });
}

var blocoIdExcluir = null;
function excluirBloco(blocoId) {
    blocoIdExcluir = blocoId;
    document.getElementById('modalExcluirSenha').classList.remove('hidden');
    document.getElementById('inputSenhaExcluir').value = '';
    document.getElementById('inputSenhaExcluir').focus();
}
function fecharModalExcluir() {
    blocoIdExcluir = null;
    document.getElementById('modalExcluirSenha').classList.add('hidden');
}
function confirmarExcluirComSenha() {
    if (!blocoIdExcluir) return;
    var senha = document.getElementById('inputSenhaExcluir').value.trim();
    if (!senha) {
        alert('Digite sua senha para confirmar.');
        return;
    }
    var btn = document.getElementById('btnConfirmarExcluir');
    if (btn) { btn.disabled = true; btn.textContent = 'Excluindo...'; }
    fetch(`<?= URL ?>/admin/provas/blocos/${blocoIdExcluir}/excluir`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ senha: senha })
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            fecharModalExcluir();
            location.reload();
        } else {
            alert(data.error || 'Erro ao excluir');
            if (btn) { btn.disabled = false; btn.textContent = 'Excluir'; }
        }
    })
    .catch(function(err) {
        console.error(err);
        alert('Erro de conexão.');
        if (btn) { btn.disabled = false; btn.textContent = 'Excluir'; }
    });
}
</script>

<!-- Modal: confirmar exclusão com senha -->
<div id="modalExcluirSenha" class="hidden fixed inset-0 z-50 overflow-y-auto" aria-modal="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50" onclick="fecharModalExcluir()"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-md w-full p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-2">Confirmar exclusão</h3>
            <p class="text-sm text-gray-600 mb-4">Para desativar este bloco, digite sua senha. O bloco deixará de aparecer para alunos e professores; os dados são mantidos (LGPD). Quem desativou ficará registrado.</p>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
            <input type="password" id="inputSenhaExcluir" placeholder="Senha" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4" onkeydown="if (event.key==='Enter') confirmarExcluirComSenha();">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="fecharModalExcluir()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="button" id="btnConfirmarExcluir" onclick="confirmarExcluirComSenha()" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Excluir</button>
            </div>
        </div>
    </div>
</div>
