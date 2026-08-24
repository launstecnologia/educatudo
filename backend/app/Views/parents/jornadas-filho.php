<?php
$jornadas = is_array($jornadas ?? null) ? $jornadas : [];
$kpis = is_array($kpis_jornadas ?? null) ? $kpis_jornadas : [];
$totaisAlt = is_array($totais_alternativas ?? null) ? $totais_alternativas : [];
$filtroAnoLetivo = isset($filtro_ano_letivo) ? (int) $filtro_ano_letivo : 0;
$filtroBimestre = isset($filtro_bimestre) ? (int) $filtro_bimestre : 0;
$anosDisponiveis = is_array($anos_disponiveis ?? null) ? $anos_disponiveis : [];
$pag = is_array($paginacao ?? null) ? $paginacao : [];
$pagPage = max(1, (int) ($pag['page'] ?? 1));
$pagPerPage = max(1, (int) ($pag['per_page'] ?? 15));
$pagTotal = (int) ($pag['total'] ?? count($jornadas));
$pagTotalPages = max(1, (int) ($pag['total_pages'] ?? 1));
$filhoId = (int) ($filho['id'] ?? 0);
$baseUrl = URL . '/pais/filhos/' . $filhoId . '/jornadas';
$queryFiltro = array_filter([
    'ano_letivo' => $filtroAnoLetivo > 0 ? $filtroAnoLetivo : null,
    'bimestre' => $filtroBimestre > 0 ? $filtroBimestre : null,
], static function ($v) {
    return $v !== null;
});
$filtrosAtivos = count($queryFiltro);

$urlPagina = static function (int $page) use ($baseUrl, $queryFiltro): string {
    $q = $queryFiltro;
    if ($page > 1) {
        $q['page'] = $page;
    }
    return $baseUrl . ($q !== [] ? ('?' . http_build_query($q)) : '');
};

$totalAlt = (int) ($totaisAlt['total'] ?? 0);
$totalAltFeitas = (int) ($totaisAlt['feitos'] ?? 0);
$totalAltAcertos = (int) ($totaisAlt['acertos'] ?? 0);
$totalAltErros = (int) ($totaisAlt['erros'] ?? 0);
?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-2">
        <div class="min-w-0">
            <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($filho['nome'] ?? '') ?></h2>
            <p class="text-gray-600 mt-1">Detalhamento completo das jornadas: fez/não fez, conclusão e progresso.</p>
        </div>
        <button type="button"
                onclick="openFiltroJornadasDrawer()"
                class="relative inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 shrink-0">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-primary text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
    </div>

    <?php if ($filtrosAtivos > 0): ?>
        <div class="flex flex-wrap items-center gap-2 mb-6">
            <?php if ($filtroAnoLetivo > 0): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Ano letivo <?= (int) $filtroAnoLetivo ?></span>
            <?php endif; ?>
            <?php if ($filtroBimestre > 0): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"><?= (int) $filtroBimestre ?>º bimestre</span>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($baseUrl) ?>" class="text-xs font-medium text-gray-500 hover:text-gray-800">Limpar</a>
        </div>
    <?php else: ?>
        <div class="mb-6"></div>
    <?php endif; ?>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="rounded-lg border border-gray-200 p-3"><p class="text-xs text-gray-500">Total</p><p class="text-xl font-bold"><?= (int) ($kpis['total'] ?? 0) ?></p></div>
        <div class="rounded-lg border border-green-200 bg-green-50 p-3"><p class="text-xs text-green-700">Fez</p><p class="text-xl font-bold text-green-800"><?= (int) ($kpis['feitas'] ?? 0) ?></p></div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-3"><p class="text-xs text-red-700">Não fez</p><p class="text-xl font-bold text-red-800"><?= (int) ($kpis['nao_feitas'] ?? 0) ?></p></div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3"><p class="text-xs text-blue-700">Concluídas</p><p class="text-xl font-bold text-blue-800"><?= (int) ($kpis['concluidas'] ?? 0) ?></p></div>
        <div class="rounded-lg border border-purple-200 bg-purple-50 p-3"><p class="text-xs text-purple-700">Taxa conclusão</p><p class="text-xl font-bold text-purple-800"><?= (int) ($kpis['taxa_conclusao'] ?? 0) ?>%</p></div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="rounded-lg border p-3"><p class="text-xs text-gray-500">Exercícios alternativas</p><p class="text-xl font-bold"><?= $totalAlt ?></p></div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3"><p class="text-xs text-blue-700">Feitos</p><p class="text-xl font-bold text-blue-800"><?= $totalAltFeitas ?></p></div>
        <div class="rounded-lg border border-green-200 bg-green-50 p-3"><p class="text-xs text-green-700">Acertos</p><p class="text-xl font-bold text-green-800"><?= $totalAltAcertos ?></p></div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-3"><p class="text-xs text-red-700">Erros</p><p class="text-xl font-bold text-red-800"><?= $totalAltErros ?></p></div>
    </div>

    <?php if ($pagTotal === 0): ?>
        <div class="text-center py-12"><p class="text-gray-500">Nenhuma jornada encontrada</p></div>
    <?php else: ?>
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-3 py-2 text-left">Jornada</th>
                            <th class="px-3 py-2 text-center min-w-[120px]">Status</th>
                            <th class="px-3 py-2 text-center">Exercício</th>
                            <th class="px-3 py-2 text-center">Acertos</th>
                            <th class="px-3 py-2 text-center">Erro</th>
                            <th class="px-3 py-2 text-center">Score</th>
                            <th class="px-3 py-2 text-center">Criada em</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($jornadas as $jornada): ?>
                            <?php
                            $status = !empty($jornada['concluiu']) ? 'Concluída' : (!empty($jornada['fez']) ? 'Em andamento' : (!empty($jornada['expirada']) ? 'Expirado' : 'Não iniciada'));
                            $statusClass = !empty($jornada['concluiu'])
                                ? 'bg-green-100 text-green-800'
                                : (!empty($jornada['fez'])
                                    ? 'bg-amber-100 text-amber-800'
                                    : (!empty($jornada['expirada']) ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'));
                            $professorMateria = trim((string) ($jornada['professor_nome'] ?? ''));
                            $materia = trim((string) ($jornada['materia_nome'] ?? ''));
                            if ($professorMateria !== '' && $materia !== '') {
                                $professorMateria .= ' - ' . $materia;
                            } elseif ($materia !== '') {
                                $professorMateria = $materia;
                            }
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($jornada['titulo'] ?? 'Jornada') ?></div>
                                    <div class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($professorMateria !== '' ? $professorMateria : 'Professor/Matéria não informados') ?></div>
                                </td>
                                <td class="px-3 py-2 text-center min-w-[120px]">
                                    <span class="inline-flex items-center justify-center px-2 py-1 text-xs rounded-full whitespace-nowrap leading-none <?= $statusClass ?>">
                                        <?= $status ?>
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-center text-gray-900"><?= (int) ($jornada['exercicios_alternativa_feitos'] ?? 0) ?>/<?= (int) ($jornada['total_exercicios_alternativa'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-center text-green-700 font-semibold"><?= (int) ($jornada['exercicios_alternativa_acertos'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-center text-red-700 font-semibold"><?= (int) ($jornada['exercicios_alternativa_erros'] ?? 0) ?></td>
                                <td class="px-3 py-2 text-center text-gray-900"><?= (int) ($jornada['percentual_exercicios_alternativa_acerto'] ?? 0) ?>%</td>
                                <td class="px-3 py-2 text-center text-gray-600"><?= !empty($jornada['created_at']) ? date('d/m/Y', strtotime($jornada['created_at'])) : '—' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2 bg-white">
                <p class="text-sm text-gray-600">
                    Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> jornada(s)
                </p>
                <?php if ($pagTotalPages > 1): ?>
                    <nav class="flex items-center gap-1" aria-label="Paginação">
                        <?php if ($pagPage > 1): ?>
                            <a href="<?= htmlspecialchars($urlPagina($pagPage - 1)) ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
                        <?php endif; ?>
                        <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                            <a href="<?= htmlspecialchars($urlPagina($i)) ?>"
                               class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"
                               <?= $i === $pagPage ? 'aria-current="page"' : '' ?>><?= $i ?></a>
                        <?php endfor; ?>
                        <?php if ($pagPage < $pagTotalPages): ?>
                            <a href="<?= htmlspecialchars($urlPagina($pagPage + 1)) ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
                        <?php endif; ?>
                    </nav>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<div id="filtroJornadasDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFiltroJornadasDrawer()"></div>
<aside id="filtroJornadasDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true"
       role="dialog"
       aria-labelledby="filtroJornadasDrawerTitle">
    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
        <h2 id="filtroJornadasDrawerTitle" class="text-xl font-bold text-gray-900">Filtros</h2>
        <button type="button" onclick="closeFiltroJornadasDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <form method="get" action="<?= htmlspecialchars($baseUrl) ?>" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-4">
            <div>
                <label for="filtro_ano_letivo" class="block text-sm font-medium text-gray-700 mb-1">Ano letivo</label>
                <select id="filtro_ano_letivo" name="ano_letivo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Todos</option>
                    <?php foreach ($anosDisponiveis as $anoOpt): ?>
                        <option value="<?= (int) $anoOpt ?>" <?= $filtroAnoLetivo === (int) $anoOpt ? 'selected' : '' ?>><?= (int) $anoOpt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_bimestre" class="block text-sm font-medium text-gray-700 mb-1">Bimestre</label>
                <select id="filtro_bimestre" name="bimestre" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Todos</option>
                    <option value="1" <?= $filtroBimestre === 1 ? 'selected' : '' ?>>1º bimestre</option>
                    <option value="2" <?= $filtroBimestre === 2 ? 'selected' : '' ?>>2º bimestre</option>
                    <option value="3" <?= $filtroBimestre === 3 ? 'selected' : '' ?>>3º bimestre</option>
                    <option value="4" <?= $filtroBimestre === 4 ? 'selected' : '' ?>>4º bimestre</option>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3">
            <a href="<?= htmlspecialchars($baseUrl) ?>" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">Limpar</a>
            <button type="submit" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 btn-primary-custom rounded-lg text-sm font-semibold hover:opacity-90">Aplicar filtros</button>
        </div>
    </form>
</aside>
<script>
(function () {
    function openFiltroJornadasDrawer() {
        var drawer = document.getElementById('filtroJornadasDrawer');
        var backdrop = document.getElementById('filtroJornadasDrawerBackdrop');
        if (!drawer || !backdrop) return;
        backdrop.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }
    function closeFiltroJornadasDrawer() {
        var drawer = document.getElementById('filtroJornadasDrawer');
        var backdrop = document.getElementById('filtroJornadasDrawerBackdrop');
        if (!drawer || !backdrop) return;
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    window.openFiltroJornadasDrawer = openFiltroJornadasDrawer;
    window.closeFiltroJornadasDrawer = closeFiltroJornadasDrawer;
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeFiltroJornadasDrawer();
    });
})();
</script>
