<?php
$planos = is_array($planos_aula ?? null) ? $planos_aula : [];
$opcoesProfessor = is_array($opcoes_professor ?? null) ? $opcoes_professor : [];
$opcoesMateria = is_array($opcoes_materia ?? null) ? $opcoes_materia : [];
$opcoesData = is_array($opcoes_data ?? null) ? $opcoes_data : [];
$filtroProfessor = trim((string) ($filtro_professor ?? ''));
$filtroMateria = trim((string) ($filtro_materia ?? ''));
$filtroData = trim((string) ($filtro_data ?? ''));
$totalPlanos = (int) ($total_planos ?? count($planos));
$pag = is_array($paginacao ?? null) ? $paginacao : [];
$pagPage = max(1, (int) ($pag['page'] ?? 1));
$pagPerPage = max(1, (int) ($pag['per_page'] ?? 15));
$pagTotal = (int) ($pag['total'] ?? count($planos));
$pagTotalPages = max(1, (int) ($pag['total_pages'] ?? 1));
$filhoId = (int) ($filho['id'] ?? 0);
$baseUrl = URL . '/pais/filhos/' . $filhoId . '/plano-aula';
$queryFiltro = array_filter([
    'professor' => $filtroProfessor !== '' ? $filtroProfessor : null,
    'materia' => $filtroMateria !== '' ? $filtroMateria : null,
    'data' => $filtroData !== '' ? $filtroData : null,
], static function ($v) {
    return $v !== null;
});
$filtrosAtivos = count($queryFiltro);
$mostrarFiltro = $totalPlanos > 0 || $filtrosAtivos > 0;

$urlPagina = static function (int $page) use ($baseUrl, $queryFiltro): string {
    $q = $queryFiltro;
    if ($page > 1) {
        $q['page'] = $page;
    }
    return $baseUrl . ($q !== [] ? ('?' . http_build_query($q)) : '');
};
?>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3 mb-4">
        <div class="min-w-0">
            <h2 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($filho['nome'] ?? '') ?></h2>
            <p class="text-gray-600 mt-1">Planos de aula da turma<?= !empty($filho['turma_nome']) ? ' ' . htmlspecialchars((string) $filho['turma_nome']) : '' ?>.</p>
        </div>
        <?php if ($mostrarFiltro): ?>
        <button type="button"
                onclick="openFiltroPlanoAulaDrawer()"
                class="relative inline-flex items-center justify-center gap-2 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 shrink-0">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
            </svg>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
                <span class="inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-primary text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
        <?php endif; ?>
    </div>

    <?php if ($filtrosAtivos > 0): ?>
        <div class="flex flex-wrap items-center gap-2 mb-4">
            <?php if ($filtroProfessor !== ''): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"><?= htmlspecialchars($filtroProfessor) ?></span>
            <?php endif; ?>
            <?php if ($filtroMateria !== ''): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"><?= htmlspecialchars($filtroMateria) ?></span>
            <?php endif; ?>
            <?php if ($filtroData !== ''): ?>
                <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-medium bg-gray-100 text-gray-700"><?= htmlspecialchars($filtroData) ?></span>
            <?php endif; ?>
            <a href="<?= htmlspecialchars($baseUrl) ?>" class="text-xs font-medium text-gray-500 hover:text-gray-800">Limpar</a>
        </div>
    <?php endif; ?>

    <?php if (!empty($filho['turma_nome'])): ?>
    <div class="flex items-center gap-3 rounded-lg border border-gray-200 p-3 mb-6">
        <div class="w-10 h-10 bg-blue-100 rounded-full flex items-center justify-center shrink-0">
            <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
        </div>
        <div>
            <p class="text-xs text-gray-500">Turma do aluno</p>
            <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) $filho['turma_nome']) ?></p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($pagTotal === 0): ?>
        <div class="text-center py-12">
            <p class="text-lg font-medium text-gray-900 mb-1"><?= $filtrosAtivos > 0 ? 'Nenhum plano encontrado com os filtros' : 'Nenhum plano de aula disponível' ?></p>
            <p class="text-sm text-gray-500"><?= $filtrosAtivos > 0 ? 'Ajuste ou limpe os filtros para ver outros planos.' : 'Não há planos de aula para a turma do aluno no momento.' ?></p>
        </div>
    <?php else: ?>
        <div class="border border-gray-200 rounded-lg overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 text-left">Título</th>
                            <th class="px-4 py-2 text-left w-40">Data</th>
                            <th class="px-4 py-2 text-right w-48">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($planos as $plano): ?>
                            <?php
                            $planoId = (int) ($plano['id'] ?? 0);
                            $professorNome = (string) ($plano['professor_nome'] ?? '—');
                            $materiaNome = (string) ($plano['materia_nome'] ?? '—');
                            ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-4 py-3">
                                    <div class="font-medium text-gray-900"><?= htmlspecialchars($plano['titulo'] ?? 'Sem título') ?></div>
                                    <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($professorNome) ?> – <?= htmlspecialchars($materiaNome) ?></div>
                                </td>
                                <td class="px-4 py-3 text-gray-600 whitespace-nowrap"><?= htmlspecialchars((string) ($plano['data_exibicao'] ?? '—')) ?></td>
                                <td class="px-4 py-3 text-right">
                                    <div class="flex flex-wrap items-center justify-end gap-2">
                                        <a href="<?= URL ?>/pais/filhos/<?= $filhoId ?>/plano-aula/visualizar/<?= $planoId ?>" class="inline-flex items-center px-3 py-1.5 btn-primary-custom text-xs font-medium rounded-lg hover:opacity-90" title="Visualizar plano de aula">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                            Visualizar
                                        </a>
                                        <a href="<?= URL ?>/pais/filhos/<?= $filhoId ?>/plano-aula/pdf/<?= $planoId ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center px-3 py-1.5 bg-gray-700 text-white text-xs font-medium rounded-lg hover:bg-gray-800" title="Abrir PDF">
                                            <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                            PDF
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="px-4 py-3 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2 bg-white">
                <p class="text-sm text-gray-600">
                    Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> plano(s)
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

<?php if ($mostrarFiltro): ?>
<div id="filtroPlanoAulaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFiltroPlanoAulaDrawer()"></div>
<aside id="filtroPlanoAulaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true"
       role="dialog"
       aria-labelledby="filtroPlanoAulaDrawerTitle">
    <div class="flex items-center justify-between px-6 py-5 border-b border-gray-200">
        <h2 id="filtroPlanoAulaDrawerTitle" class="text-xl font-bold text-gray-900">Filtros</h2>
        <button type="button" onclick="closeFiltroPlanoAulaDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
    <form method="get" action="<?= htmlspecialchars($baseUrl) ?>" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-6 space-y-4">
            <div>
                <label for="filtro_plano_professor" class="block text-sm font-medium text-gray-700 mb-1">Professor</label>
                <select id="filtro_plano_professor" name="professor" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Todos os professores</option>
                    <?php foreach ($opcoesProfessor as $prof): ?>
                        <option value="<?= htmlspecialchars((string) $prof) ?>" <?= $filtroProfessor === (string) $prof ? 'selected' : '' ?>><?= htmlspecialchars((string) $prof) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_plano_materia" class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
                <select id="filtro_plano_materia" name="materia" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Todas as matérias</option>
                    <?php foreach ($opcoesMateria as $mat): ?>
                        <option value="<?= htmlspecialchars((string) $mat) ?>" <?= $filtroMateria === (string) $mat ? 'selected' : '' ?>><?= htmlspecialchars((string) $mat) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_plano_data" class="block text-sm font-medium text-gray-700 mb-1">Data</label>
                <select id="filtro_plano_data" name="data" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Todas as datas</option>
                    <?php foreach ($opcoesData as $d): ?>
                        <option value="<?= htmlspecialchars((string) $d) ?>" <?= $filtroData === (string) $d ? 'selected' : '' ?>><?= htmlspecialchars((string) $d) ?></option>
                    <?php endforeach; ?>
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
    function openFiltroPlanoAulaDrawer() {
        var drawer = document.getElementById('filtroPlanoAulaDrawer');
        var backdrop = document.getElementById('filtroPlanoAulaDrawerBackdrop');
        if (!drawer || !backdrop) return;
        backdrop.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    }
    function closeFiltroPlanoAulaDrawer() {
        var drawer = document.getElementById('filtroPlanoAulaDrawer');
        var backdrop = document.getElementById('filtroPlanoAulaDrawerBackdrop');
        if (!drawer || !backdrop) return;
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    }
    window.openFiltroPlanoAulaDrawer = openFiltroPlanoAulaDrawer;
    window.closeFiltroPlanoAulaDrawer = closeFiltroPlanoAulaDrawer;
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closeFiltroPlanoAulaDrawer();
    });
})();
</script>
<?php endif; ?>
