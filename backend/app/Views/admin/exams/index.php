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
foreach (['titulo', 'data_prova', 'bloco_modelo_id', 'turma_id', 'materia_id', 'bimestre', 'tipo_avaliacao_id'] as $fk) {
    if (!empty($filters[$fk])) {
        $filtrosAtivosCount++;
    }
}
if (!class_exists('PeriodoLetivo')) {
    require_once __DIR__ . '/../../../../Core/PeriodoLetivo.php';
}
$periodoFiltro = PeriodoLetivo::doAno((int) date('Y'));
// Conta status só quando o usuário escolheu um filtro explícito (não o padrão "exceto concluídos")
if (!empty($filterStatus)) {
    $filtrosAtivosCount++;
}
$qsListaAtual = http_build_query($_GET ?? []);
$voltarListaPath = '/admin/provas' . ($qsListaAtual !== '' ? ('?' . $qsListaAtual) : '');
$editarVoltarQs = $qsListaAtual !== '' ? ('?voltar=' . rawurlencode($voltarListaPath)) : '';
$btnSecundario = 'inline-flex items-center px-4 py-2.5 border border-[#E5EAF1] rounded-lg text-sm font-medium text-[#172033] bg-white hover:bg-gray-50 transition-colors';

$formatarTurmasChip = static function (string $turmasTexto): array {
    $turmasTexto = trim($turmasTexto);
    if ($turmasTexto === '') {
        return ['Todas', 'Todas'];
    }
    $partes = array_values(array_filter(array_map('trim', preg_split('/\s*,\s*/', $turmasTexto) ?: [])));
    if (count($partes) <= 2) {
        $txt = implode(', ', $partes);
        return [$txt, $txt];
    }
    $visiveis = array_slice($partes, 0, 2);
    $resto = count($partes) - 2;
    return [implode(', ', $visiveis) . ' +' . $resto, implode(', ', $partes)];
};
?>

<!-- Breadcrumb -->
<nav class="mb-3 text-sm text-[#667085]" aria-label="Breadcrumb">
    <ol class="flex flex-wrap items-center gap-1.5">
        <li><a href="<?= URL ?>/admin/dashboard" class="hover:text-[#172033]">Dashboard</a></li>
        <li aria-hidden="true">›</li>
        <li><span>Acadêmico</span></li>
        <li aria-hidden="true">›</li>
        <li class="font-medium text-[#172033]">Lançamento de Notas</li>
    </ol>
</nav>

<!-- Header Section -->
<div class="mb-5">
    <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-3">
        <div class="min-w-0">
            <h2 class="text-xl sm:text-2xl font-bold text-[#172033] leading-tight">
                Lançamento de Notas
            </h2>
            <p class="text-sm text-[#667085] mt-1">
                Eventos de prova por bloco, semana, bimestre e turma
            </p>
        </div>
        <div class="flex items-center gap-2 flex-wrap lg:justify-end shrink-0">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative <?= $btnSecundario ?>">
                <i class="fa-solid fa-filter mr-2 text-[#667085]"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-[#0B6EDC] text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
            <a href="<?= URL ?>/admin/provas/tipos-avaliacao" class="<?= $btnSecundario ?>">
                <i class="fa-solid fa-layer-group mr-2 text-[#667085]"></i>
                Tipo de Nota
            </a>
            <a href="<?= URL ?>/admin/blocos-modelo" class="<?= $btnSecundario ?>">
                <i class="fa-solid fa-table-cells mr-2 text-[#667085]"></i>
                Bloco Professor
            </a>
            <a href="<?= URL ?>/admin/provas/blocos/criar<?= htmlspecialchars($editarVoltarQs) ?>"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Evento
            </a>
        </div>
    </div>
</div>

<!-- Stats Cards -->
<?php if (isset($stats)): ?>
<?php
$cardsStats = [
    [
        'label' => 'Total de Blocos',
        'valor' => (int) ($stats['total_blocos'] ?? 0),
        'desc' => 'Todos os eventos',
        'icon' => 'fa-file-lines',
        'tone' => 'bg-[#EAF3FF] text-[#1769C2]',
        'cardBg' => 'bg-[#EAF3FF]/50',
    ],
    [
        'label' => 'Aguardando',
        'valor' => (int) ($stats['blocos_aguardando'] ?? 0),
        'desc' => 'Em lançamento ou revisão',
        'icon' => 'fa-clock',
        'tone' => 'bg-[#FFF5D9] text-[#B76A00]',
        'cardBg' => 'bg-[#FFF5D9]/60',
    ],
    [
        'label' => 'Aprovado',
        'valor' => (int) ($stats['blocos_aprovados'] ?? 0),
        'desc' => 'Eventos finalizados',
        'icon' => 'fa-circle-check',
        'tone' => 'bg-[#E8F8EF] text-[#16834A]',
        'cardBg' => 'bg-[#E8F8EF]/60',
    ],
    [
        'label' => 'Liberado',
        'valor' => (int) ($stats['blocos_liberados'] ?? 0),
        'desc' => 'Disponível para visualização',
        'icon' => 'fa-lock-open',
        'tone' => 'bg-[#F3EAFE] text-[#7541C8]',
        'cardBg' => 'bg-[#F3EAFE]/60',
    ],
    [
        'label' => 'Concluído',
        'valor' => (int) ($stats['blocos_concluidos'] ?? 0),
        'desc' => 'Notas lançadas e encerradas',
        'icon' => 'fa-flag-checkered',
        'tone' => 'bg-slate-100 text-slate-700',
        'cardBg' => 'bg-slate-50',
    ],
    [
        'label' => 'Provas Pendentes',
        'valor' => count($provas_pendentes ?? []),
        'desc' => 'Aguardando agrupamento',
        'icon' => 'fa-triangle-exclamation',
        'tone' => 'bg-[#FDECEC] text-[#C93636]',
        'cardBg' => 'bg-[#FDECEC]/60',
    ],
];
?>
<div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mb-5">
    <?php foreach ($cardsStats as $card): ?>
    <div class="<?= $card['cardBg'] ?> rounded-xl border border-[#E5EAF1] px-3.5 py-3">
        <div class="flex items-start gap-2.5">
            <div class="w-8 h-8 rounded-lg <?= $card['tone'] ?> flex items-center justify-center shrink-0">
                <i class="fa-solid <?= $card['icon'] ?> text-sm" aria-hidden="true"></i>
            </div>
            <div class="min-w-0">
                <p class="text-xs text-[#667085] truncate"><?= htmlspecialchars($card['label']) ?></p>
                <p class="text-2xl font-bold text-[#172033] leading-tight mt-0.5"><?= $card['valor'] ?></p>
                <p class="text-[11px] text-[#667085] mt-0.5 truncate"><?= htmlspecialchars($card['desc']) ?></p>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Provas Pendentes Alert -->
<?php if (!empty($provas_pendentes)): ?>
<div class="bg-[#FFF5D9] border border-[#F5D98A] border-l-4 border-l-[#B76A00] p-3.5 mb-5 rounded-xl flex items-start gap-3">
    <div class="w-8 h-8 rounded-lg bg-[#FFF0C2] text-[#B76A00] flex items-center justify-center shrink-0">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
    </div>
    <p class="text-sm text-[#8A5200] pt-1.5">
        <strong><?= count($provas_pendentes) ?> prova(s) pendente(s)</strong> aguardando agrupamento em blocos.
        <a href="<?= URL ?>/admin/provas/blocos/criar<?= htmlspecialchars($editarVoltarQs) ?>" class="font-semibold underline ml-1 text-[#B76A00] hover:text-[#8A5200]">Criar novo bloco</a>
    </p>
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
                    <option value="" <?= $filterStatus === '' ? 'selected' : '' ?>>Todos (exceto concluídos)</option>
                    <option value="todos" <?= $filterStatus === 'todos' ? 'selected' : '' ?>>Todos (inclui concluídos)</option>
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
                <label for="filtro_bimestre" class="block text-sm font-medium text-gray-700 mb-1.5"><?= htmlspecialchars($periodoFiltro['rotulo_campo']) ?></label>
                <select id="filtro_bimestre" name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?= PeriodoLetivo::optionsHtml((int) date('Y'), $filterBimestre, ['todos' => true]) ?>
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
    try { sessionStorage.removeItem('educatudo:admin-provas-filtros'); } catch (e) {}
    window.location.href = <?= json_encode(URL . '/admin/provas') ?>;
}

(function () {
    var qs = window.location.search || '';
    try {
        if (qs && qs !== '?') {
            sessionStorage.setItem('educatudo:admin-provas-filtros', qs);
        }
    } catch (e) {}
})();

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
    }
});
</script>

<!-- Blocos Table -->
<?php
$statusGetAtual = isset($_GET['status']) ? (string) $_GET['status'] : '';
?>
<div class="bg-white rounded-xl border border-[#E5EAF1] overflow-hidden">
    <div class="px-4 sm:px-5 py-4 border-b border-[#E5EAF1] flex flex-col xl:flex-row xl:items-center xl:justify-between gap-3">
        <div class="min-w-0">
            <h3 class="text-base sm:text-lg font-semibold text-[#172033]">Evento de Provas</h3>
            <p class="text-sm text-[#667085] mt-0.5">Gerencie e acompanhe todos os eventos de avaliação cadastrados no sistema.</p>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-2 shrink-0">
            <form method="GET" action="<?= URL ?>/admin/provas" class="flex flex-wrap items-center gap-2" id="formBuscaRapidaProvas">
                <?php if ($statusGetAtual !== ''): ?>
                <input type="hidden" name="status" value="<?= htmlspecialchars($statusGetAtual) ?>">
                <?php endif; ?>
                <?php if ($filterData !== ''): ?>
                <input type="hidden" name="data_prova" value="<?= htmlspecialchars($filterData) ?>">
                <?php endif; ?>
                <?php if ($filterTipoAvaliacaoId > 0): ?>
                <input type="hidden" name="tipo_avaliacao_id" value="<?= (int) $filterTipoAvaliacaoId ?>">
                <?php endif; ?>
                <?php if ($filterBlocoModeloId > 0): ?>
                <input type="hidden" name="bloco_modelo_id" value="<?= (int) $filterBlocoModeloId ?>">
                <?php endif; ?>
                <?php if ($filterMateriaId > 0): ?>
                <input type="hidden" name="materia_id" value="<?= (int) $filterMateriaId ?>">
                <?php endif; ?>
                <?php if ($filterTurmaId > 0): ?>
                <input type="hidden" name="turma_id" value="<?= (int) $filterTurmaId ?>">
                <?php endif; ?>
                <?php if (!empty($pagination['per_page'])): ?>
                <input type="hidden" name="per_page" value="<?= (int) $pagination['per_page'] ?>">
                <?php endif; ?>
                <div class="relative">
                    <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-[#667085] text-sm" aria-hidden="true"></i>
                    <input type="search" name="titulo" value="<?= htmlspecialchars($filterTitulo, ENT_QUOTES, 'UTF-8') ?>"
                           placeholder="Buscar por título, bloco ou turma..."
                           class="pl-9 pr-3 py-2 w-64 sm:w-72 rounded-lg border border-[#E5EAF1] text-sm text-[#172033] placeholder:text-[#667085] focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400 bg-white">
                </div>
                <div class="relative">
                    <i class="fa-regular fa-calendar absolute left-3 top-1/2 -translate-y-1/2 text-[#667085] text-sm" aria-hidden="true"></i>
                    <select name="bimestre" onchange="this.form.submit()"
                            class="appearance-none pl-9 pr-8 py-2 rounded-lg border border-[#E5EAF1] text-sm text-[#172033] bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20 focus:border-blue-400">
                        <?= PeriodoLetivo::optionsHtml((int) date('Y'), $filterBimestre, ['todos' => true, 'todos_label' => 'Todos os períodos']) ?>
                    </select>
                </div>
            </form>
            <div id="barraAcoesLote" class="hidden flex-wrap items-center gap-2">
                <span id="contadorSelecionados" class="text-sm text-[#667085] mr-1">0 selecionado(s)</span>
                <button type="button" onclick="marcarSelecionadosConcluidos()"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium bg-gray-800 text-white hover:bg-gray-900">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    Marcar como concluído
                </button>
                <button type="button" onclick="excluirSelecionados()"
                        class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium bg-red-600 text-white hover:bg-red-700">
                    <i class="fa-solid fa-trash" aria-hidden="true"></i>
                    Excluir
                </button>
            </div>
        </div>
    </div>
    
    <div class="overflow-x-auto">
        <table class="min-w-full">
            <thead class="bg-[#F7F9FC]">
                <tr class="border-b border-[#E5EAF1]">
                    <th class="px-3 py-2.5 text-left w-10">
                        <input type="checkbox" id="selecionarTodosBlocos" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" title="Selecionar todos" onchange="toggleSelecionarTodos(this)">
                    </th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-[#667085] uppercase tracking-wide">Título</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-[#667085] uppercase tracking-wide whitespace-nowrap">Data/Horário</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-[#667085] uppercase tracking-wide whitespace-nowrap"><?= htmlspecialchars($periodoFiltro['rotulo_campo']) ?></th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-[#667085] uppercase tracking-wide">Tipo de Nota</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-[#667085] uppercase tracking-wide">Provas</th>
                    <th class="px-3 py-2.5 text-left text-[11px] font-semibold text-[#667085] uppercase tracking-wide">Status</th>
                    <th class="px-3 py-2.5 text-right text-[11px] font-semibold text-[#667085] uppercase tracking-wide">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[#E5EAF1]">
                <?php if (empty($blocos)): ?>
                    <tr>
                        <td colspan="8" class="px-6 py-12 text-center">
                            <i class="fa-solid fa-file-lines text-4xl text-gray-300 mb-3" aria-hidden="true"></i>
                            <p class="text-gray-500 text-lg mb-2">Nenhum bloco criado ainda</p>
                            <p class="text-sm text-gray-400 mb-4">Comece criando um novo bloco de provas</p>
                            <a href="<?= URL ?>/admin/provas/blocos/criar<?= htmlspecialchars($editarVoltarQs) ?>"
                               class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg hover:opacity-90">
                                <i class="fa-solid fa-plus mr-2" aria-hidden="true"></i>
                                Criar Primeiro Bloco
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($blocos as $bloco): ?>
                        <?php
                        $turmasTexto = trim($bloco['turmas_demarcadas'] ?? '');
                        if ($turmasTexto === '' && !empty(trim($bloco['turmas_por_professor'] ?? ''))) {
                            $turmasTexto = trim($bloco['turmas_por_professor']);
                        }
                        if ($turmasTexto === '') {
                            $turmasTexto = !empty($bloco['turma_nome']) ? (string)$bloco['turma_nome'] : 'Todas';
                        }
                        [$turmasChip, $turmasTitle] = $formatarTurmasChip($turmasTexto);
                        $bimestreNumero = (int)($bloco['bimestre'] ?? 0);
                        $anoBloco = (int)($bloco['ano_letivo'] ?? 0);
                        $bimestreTexto = $bimestreNumero > 0 ? PeriodoLetivo::rotulo($anoBloco, $bimestreNumero) : '';
                        $destinosQuadro = $rotulos_quadro[(int) ($bloco['id'] ?? 0)] ?? [];
                        if ($destinosQuadro === []) {
                            $semanaNum = (int) ($bloco['semana'] ?? 0);
                            $nomeBlocoLegado = trim((string) ($bloco['bloco_modelo_nome'] ?? ''));
                            if ($nomeBlocoLegado !== '' || ($semanaNum >= 1 && $semanaNum <= 20)) {
                                $destinosQuadro = [[
                                    'bloco' => $nomeBlocoLegado,
                                    'semana' => ($semanaNum >= 1 && $semanaNum <= 20) ? ('S' . $semanaNum) : '',
                                ]];
                            }
                        }
                        $st = $bloco['status'] ?? 'aguardando';
                        $statusMeta = [
                            'aguardando' => ['Aguardando', 'bg-[#FFF5D9] text-[#B76A00]', 'fa-clock'],
                            'aprovado' => ['Aprovado', 'bg-[#E8F8EF] text-[#16834A]', 'fa-check'],
                            'liberado' => ['Liberado', 'bg-[#EAF3FF] text-[#1769C2]', 'fa-check'],
                            'concluido' => ['Concluído', 'bg-slate-100 text-slate-700', 'fa-flag-checkered'],
                        ];
                        $lb = $statusMeta[$st] ?? $statusMeta['aguardando'];
                        $entregues = (int)($bloco['total_provas_entregues'] ?? 0);
                        $esperadas = (int)($bloco['total_provas_esperadas'] ?? 0);
                        $textoProvas = $esperadas > 0 ? "{$entregues}/{$esperadas}" : (string) ($bloco['total_provas'] ?? 0);
                        $qtdCanceladasBloco = (int)(($canceladas_por_bloco ?? [])[(int)$bloco['id']] ?? 0);
                        ?>
                        <tr class="hover:bg-[#F7F9FC]/80">
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <input type="checkbox"
                                       class="checkbox-bloco-lote rounded border-gray-300 text-blue-600 focus:ring-blue-500"
                                       value="<?= (int) $bloco['id'] ?>"
                                       onchange="atualizarBarraAcoesLote()">
                            </td>
                            <td class="px-3 py-2.5 min-w-[220px] max-w-[340px]">
                                <div class="text-sm font-semibold text-[#172033] leading-snug"><?= htmlspecialchars($bloco['titulo']) ?></div>
                                <div class="mt-1 flex flex-wrap items-center gap-1">
                                    <?php foreach ($destinosQuadro as $destino): ?>
                                        <?php if (trim((string) ($destino['bloco'] ?? '')) !== ''): ?>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-[#EAF3FF] text-[#1769C2]"><?= htmlspecialchars((string) $destino['bloco']) ?></span>
                                        <?php endif; ?>
                                        <?php if (trim((string) ($destino['semana'] ?? '')) !== ''): ?>
                                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-sky-50 text-sky-700"><?= htmlspecialchars((string) $destino['semana']) ?></span>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                    <?php if ($bimestreTexto !== ''): ?>
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-gray-100 text-gray-700"><?= htmlspecialchars($bimestreTexto) ?></span>
                                    <?php endif; ?>
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[11px] font-medium bg-[#E8F8EF] text-[#16834A] max-w-[220px] truncate" title="<?= htmlspecialchars($turmasTitle) ?>"><?= htmlspecialchars($turmasChip) ?></span>
                                </div>
                                <?php if ($qtdCanceladasBloco > 0): ?>
                                <a href="<?= URL ?>/admin/provas/blocos/<?= (int)$bloco['id'] ?>/canceladas"
                                   class="inline-flex items-center gap-1 mt-1 px-1.5 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800 hover:bg-amber-200"
                                   title="Provas canceladas aguardando ação do coordenador">
                                    <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
                                    <?= $qtdCanceladasBloco ?> cancelada(s)
                                </a>
                                <?php endif; ?>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <div class="flex items-start gap-1.5">
                                    <i class="fa-regular fa-calendar text-[#667085] mt-0.5 text-xs" aria-hidden="true"></i>
                                    <div>
                                        <div class="text-sm font-medium text-[#172033]">
                                            <?= !empty($bloco['data_prova']) ? date('d/m/Y', strtotime($bloco['data_prova'])) : (!empty($bloco['created_at']) ? date('d/m/Y', strtotime($bloco['created_at'])) : '—') ?>
                                        </div>
                                        <div class="text-xs text-[#667085]">
                                            <?= date('H:i', strtotime($bloco['hora_inicio'])) ?> - <?= date('H:i', strtotime($bloco['hora_fim'])) ?>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3 py-2.5 text-sm text-[#172033] whitespace-nowrap">
                                <?php
                                $bim = (int)($bloco['bimestre'] ?? 0);
                                $labBim = $bim > 0 ? PeriodoLetivo::rotulo((int)($bloco['ano_letivo'] ?? 0), $bim) : '';
                                echo $labBim !== '' ? htmlspecialchars($labBim) : '<span class="text-gray-400">—</span>';
                                ?>
                            </td>
                            <td class="px-3 py-2.5 text-sm text-[#172033]">
                                <?= !empty($bloco['tipo_avaliacao_nome']) ? htmlspecialchars($bloco['tipo_avaliacao_nome']) : '<span class="text-gray-400">—</span>' ?>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#F3EAFE] text-[#7541C8]">
                                    <?= htmlspecialchars($textoProvas) ?><?= $esperadas > 0 ? '' : ' prova(s)' ?>
                                </span>
                            </td>
                            <td class="px-3 py-2.5 whitespace-nowrap">
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold <?= $lb[1] ?>">
                                    <i class="fa-solid <?= $lb[2] ?> text-[10px]" aria-hidden="true"></i>
                                    <?= htmlspecialchars($lb[0]) ?>
                                </span>
                            </td>
                            <td class="px-3 py-2.5 text-right whitespace-nowrap">
                                <button type="button"
                                        class="btn-acoes-bloco btn-primary-custom inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-sm font-semibold hover:opacity-90"
                                        title="Ações"
                                        data-bloco-id="<?= (int)$bloco['id'] ?>"
                                        data-status="<?= htmlspecialchars($st) ?>"
                                        data-gerenciar="<?= htmlspecialchars(URL . '/admin/provas/blocos/' . $bloco['id'] . '/gerenciar') ?>"
                                        data-editar="<?= htmlspecialchars(URL . '/admin/provas/blocos/' . $bloco['id'] . '/editar' . $editarVoltarQs) ?>"
                                        data-duplicar="<?= htmlspecialchars(URL . '/admin/provas/blocos/' . $bloco['id'] . '/duplicar') ?>"
                                        onclick="abrirDropdownAcoes(this)">
                                    <span>Ações</span>
                                    <i class="fa-solid fa-chevron-down text-[10px]" aria-hidden="true"></i>
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
    $inicio = $total > 0 ? min(($page - 1) * $perPage + 1, $total) : 0;
    $fim = $total > 0 ? min($page * $perPage, $total) : 0;
    ?>
    <?php if ($total > 0): ?>
    <div class="px-4 sm:px-5 py-3 border-t border-[#E5EAF1] flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
        <p class="text-sm text-[#667085]">
            Mostrando <?= $inicio ?> a <?= $fim ?> de <?= $total ?> registros
        </p>
        <div class="flex flex-wrap items-center gap-2">
            <form method="GET" action="<?= URL ?>/admin/provas" class="inline-flex items-center gap-1.5">
                <?php foreach ($_GET as $gk => $gv): ?>
                    <?php if ($gk === 'page' || $gk === 'per_page' || is_array($gv)) continue; ?>
                    <input type="hidden" name="<?= htmlspecialchars((string) $gk) ?>" value="<?= htmlspecialchars((string) $gv) ?>">
                <?php endforeach; ?>
                <select name="per_page" onchange="this.form.submit()"
                        class="px-2.5 py-1.5 rounded-lg border border-[#E5EAF1] text-sm text-[#172033] bg-white focus:outline-none focus:ring-2 focus:ring-blue-500/20">
                    <?php foreach ([10, 15, 25, 50] as $pp): ?>
                    <option value="<?= $pp ?>" <?= $perPage === $pp ? 'selected' : '' ?>><?= $pp ?> por página</option>
                    <?php endforeach; ?>
                </select>
            </form>
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= URL ?>/admin/provas<?= $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="w-8 h-8 inline-flex items-center justify-center text-sm text-[#667085] border border-[#E5EAF1] rounded-lg hover:bg-gray-50" aria-label="Anterior"><i class="fa-solid fa-chevron-left text-xs"></i></a>
                <?php endif; ?>
                <?php
                $windowStart = max(1, $page - 2);
                $windowEnd = min($totalPages, $page + 2);
                if ($windowStart > 1): ?>
                    <a href="<?= URL ?>/admin/provas<?= $baseQuery . $sep ?>page=1" class="w-8 h-8 inline-flex items-center justify-center text-sm font-medium rounded-lg border border-[#E5EAF1] text-[#172033] hover:bg-gray-50">1</a>
                    <?php if ($windowStart > 2): ?><span class="px-1 text-[#667085]">…</span><?php endif; ?>
                <?php endif; ?>
                <?php for ($i = $windowStart; $i <= $windowEnd; $i++): ?>
                    <a href="<?= URL ?>/admin/provas<?= $baseQuery . $sep ?>page=<?= $i ?>"
                       class="w-8 h-8 inline-flex items-center justify-center text-sm font-medium rounded-lg <?= $i === $page ? 'btn-primary-custom' : 'border border-[#E5EAF1] text-[#172033] hover:bg-gray-50' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($windowEnd < $totalPages): ?>
                    <?php if ($windowEnd < $totalPages - 1): ?><span class="px-1 text-[#667085]">…</span><?php endif; ?>
                    <a href="<?= URL ?>/admin/provas<?= $baseQuery . $sep ?>page=<?= $totalPages ?>" class="w-8 h-8 inline-flex items-center justify-center text-sm font-medium rounded-lg border border-[#E5EAF1] text-[#172033] hover:bg-gray-50"><?= $totalPages ?></a>
                <?php endif; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= URL ?>/admin/provas<?= $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="w-8 h-8 inline-flex items-center justify-center text-sm text-[#667085] border border-[#E5EAF1] rounded-lg hover:bg-gray-50" aria-label="Próxima"><i class="fa-solid fa-chevron-right text-xs"></i></a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Portal do dropdown (fora do card/table para não ser cortado) -->
<div id="dropdown-actions-portal" class="hidden fixed z-[100] w-56 py-1 bg-white rounded-lg shadow-xl border border-gray-200"></div>

<script>
var CSRF_TOKEN_PROVAS = <?= json_encode((string) ($csrf_token ?? ''), JSON_UNESCAPED_UNICODE) ?>;

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
    if (status === 'liberado') {
        html +=
            '<button type="button" onclick="fecharDropdownActions(); toggleLiberado(' + blocoId + ', 0);" class="flex items-center gap-2 w-full px-4 py-2 text-sm text-left text-amber-800 hover:bg-amber-50">' +
            '<svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg> Retirar da visão do aluno</button>';
    }
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
    if (!confirm(novoStatus
        ? 'Tem certeza que deseja liberar este bloco para os alunos?'
        : 'Retirar este evento da visão dos alunos? O status deixa de ser Liberado e a prova some do portal do aluno.')) {
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
var blocoIdsExcluirLote = null;

function idsBlocosSelecionados() {
    return Array.prototype.map.call(
        document.querySelectorAll('.checkbox-bloco-lote:checked'),
        function(cb) { return parseInt(cb.value, 10); }
    ).filter(function(id) { return id > 0; });
}

function atualizarBarraAcoesLote() {
    var ids = idsBlocosSelecionados();
    var barra = document.getElementById('barraAcoesLote');
    var contador = document.getElementById('contadorSelecionados');
    var master = document.getElementById('selecionarTodosBlocos');
    var todos = document.querySelectorAll('.checkbox-bloco-lote');
    if (contador) {
        contador.textContent = ids.length + ' selecionado(s)';
    }
    if (barra) {
        if (ids.length > 0) {
            barra.classList.remove('hidden');
            barra.classList.add('flex');
        } else {
            barra.classList.add('hidden');
            barra.classList.remove('flex');
        }
    }
    if (master && todos.length) {
        master.checked = ids.length === todos.length;
        master.indeterminate = ids.length > 0 && ids.length < todos.length;
    }
}

function toggleSelecionarTodos(master) {
    document.querySelectorAll('.checkbox-bloco-lote').forEach(function(cb) {
        cb.checked = !!master.checked;
    });
    atualizarBarraAcoesLote();
}

function marcarSelecionadosConcluidos() {
    var ids = idsBlocosSelecionados();
    if (!ids.length) {
        alert('Selecione pelo menos um evento.');
        return;
    }
    if (!confirm('Marcar ' + ids.length + ' evento(s) como concluído(s)?')) {
        return;
    }
    fetch('<?= URL ?>/admin/provas/blocos/lote/marcar-concluido', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ ids: ids, _token: CSRF_TOKEN_PROVAS })
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
        alert('Erro ao marcar os eventos como concluídos');
    });
}

function excluirSelecionados() {
    var ids = idsBlocosSelecionados();
    if (!ids.length) {
        alert('Selecione pelo menos um evento.');
        return;
    }
    blocoIdExcluir = null;
    blocoIdsExcluirLote = ids;
    var titulo = document.getElementById('tituloModalExcluir');
    var texto = document.getElementById('textoModalExcluir');
    if (titulo) titulo.textContent = 'Confirmar exclusão em lote';
    if (texto) {
        texto.textContent = 'Para desativar ' + ids.length + ' evento(s), digite sua senha. Os blocos deixarão de aparecer para alunos e professores; os dados são mantidos (LGPD).';
    }
    document.getElementById('modalExcluirSenha').classList.remove('hidden');
    document.getElementById('inputSenhaExcluir').value = '';
    document.getElementById('inputSenhaExcluir').focus();
}

function excluirBloco(blocoId) {
    blocoIdsExcluirLote = null;
    blocoIdExcluir = blocoId;
    var titulo = document.getElementById('tituloModalExcluir');
    var texto = document.getElementById('textoModalExcluir');
    if (titulo) titulo.textContent = 'Confirmar exclusão';
    if (texto) {
        texto.textContent = 'Para desativar este bloco, digite sua senha. O bloco deixará de aparecer para alunos e professores; os dados são mantidos (LGPD). Quem desativou ficará registrado.';
    }
    document.getElementById('modalExcluirSenha').classList.remove('hidden');
    document.getElementById('inputSenhaExcluir').value = '';
    document.getElementById('inputSenhaExcluir').focus();
}
function fecharModalExcluir() {
    blocoIdExcluir = null;
    blocoIdsExcluirLote = null;
    document.getElementById('modalExcluirSenha').classList.add('hidden');
}
function confirmarExcluirComSenha() {
    var senha = document.getElementById('inputSenhaExcluir').value.trim();
    if (!senha) {
        alert('Digite sua senha para confirmar.');
        return;
    }
    var btn = document.getElementById('btnConfirmarExcluir');
    if (btn) { btn.disabled = true; btn.textContent = 'Excluindo...'; }

    var url;
    var payload;
    if (blocoIdsExcluirLote && blocoIdsExcluirLote.length) {
        url = '<?= URL ?>/admin/provas/blocos/lote/excluir';
        payload = { senha: senha, ids: blocoIdsExcluirLote, _token: CSRF_TOKEN_PROVAS };
    } else if (blocoIdExcluir) {
        url = '<?= URL ?>/admin/provas/blocos/' + blocoIdExcluir + '/excluir';
        payload = { senha: senha };
    } else {
        if (btn) { btn.disabled = false; btn.textContent = 'Excluir'; }
        return;
    }

    fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
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
            <h3 id="tituloModalExcluir" class="text-lg font-semibold text-gray-900 mb-2">Confirmar exclusão</h3>
            <p id="textoModalExcluir" class="text-sm text-gray-600 mb-4">Para desativar este bloco, digite sua senha. O bloco deixará de aparecer para alunos e professores; os dados são mantidos (LGPD). Quem desativou ficará registrado.</p>
            <label class="block text-sm font-medium text-gray-700 mb-1">Sua senha</label>
            <input type="password" id="inputSenhaExcluir" placeholder="Senha" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-4" onkeydown="if (event.key==='Enter') confirmarExcluirComSenha();">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="fecharModalExcluir()" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="button" id="btnConfirmarExcluir" onclick="confirmarExcluirComSenha()" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700">Excluir</button>
            </div>
        </div>
    </div>
</div>
