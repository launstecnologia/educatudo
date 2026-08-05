<?php
$labels = [
    'pendente' => 'Pendente',
    'rascunho' => 'Rascunho',
    'finalizada' => 'Finalizada',
    'cancelada' => 'Não realizada',
];
$statusClasses = [
    'pendente' => 'bg-amber-100 text-amber-800',
    'rascunho' => 'bg-blue-100 text-blue-800',
    'finalizada' => 'bg-green-100 text-green-800',
    'cancelada' => 'bg-red-100 text-red-800',
];
$inicioPadrao = date('Y-m-01');
$fimPadrao = date('Y-m-d');
$filtrosAtivos = 0;
if ($inicio !== $inicioPadrao) $filtrosAtivos++;
if ($fim !== $fimPadrao) $filtrosAtivos++;
if ($professor_id > 0) $filtrosAtivos++;
if ($status_filtro !== '') $filtrosAtivos++;
if (($turma_id ?? 0) > 0) $filtrosAtivos++;

$exportQuery = http_build_query(['inicio' => $inicio, 'fim' => $fim, 'professor_id' => $professor_id, 'turma_id' => $turma_id ?? 0]);
ob_start();
?>
<a href="<?= URL ?>/admin/diario/indicadores?<?= htmlspecialchars($exportQuery) ?>"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-chart-column mr-2 text-gray-500"></i>
    Indicadores
</a>
<a href="<?= URL ?>/admin/diario/relatorio/pdf?<?= htmlspecialchars($exportQuery) ?>"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-file-pdf mr-2 text-red-500"></i>
    PDF
</a>
<a href="<?= URL ?>/admin/diario/relatorio/excel?<?= htmlspecialchars($exportQuery) ?>"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-file-excel mr-2 text-green-600"></i>
    Excel
</a>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <?php if ($filtrosAtivos > 0): ?>
        <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
    <?php endif; ?>
</button>
<?php
$page_header_actions = ob_get_clean();
$page_header_title = 'Acompanhamento do Diário';
$page_header_subtitle = 'Acompanhe chamadas pendentes, finalizadas e aulas não realizadas.';
include __DIR__ . '/../_partials/page_header_list.php';

$pag = $pagination ?? [];
$total = (int) ($pag['total'] ?? 0);
$perPage = (int) ($pag['per_page'] ?? 10);
$page = (int) ($pag['page'] ?? 1);
$totalPages = (int) ($pag['total_pages'] ?? 1);
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Data e hora', 'Professor', 'Turma', 'Matéria', 'Status', 'Faltas', 'Ação'] as $header): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($aulas)): ?>
                    <tr>
                        <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                            <i class="fa-regular fa-clipboard text-4xl text-gray-300 mb-4"></i>
                            <p>Nenhum diário encontrado no período.</p>
                            <?php if ($filtrosAtivos > 0): ?>
                                <a href="<?= URL ?>/admin/diario" class="inline-block mt-3 text-sm text-blue-600 hover:text-blue-800">Limpar filtros</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($aulas as $aula):
                        $status = (string) ($aula['status'] ?? 'pendente');
                        $justificadas = (int) ($aula['justificadas'] ?? 0);
                        $aulaId = (int) ($aula['id'] ?? 0);
                        $clicavel = $status !== 'pendente' && $aulaId > 0;
                        $lancarUrl = $aulaId > 0
                            ? URL . '/admin/diario/lancar?aula_id=' . $aulaId
                            : URL . '/admin/diario/lancar?grade_id=' . (int) ($aula['grade_horaria_id'] ?? 0) . '&data=' . urlencode((string) $aula['data_aula']);
                    ?>
                        <tr class="hover:bg-gray-50 transition-colors <?= $clicavel ? 'cursor-pointer' : '' ?>"
                            <?= $clicavel ? 'onclick="window.location=\'' . URL . '/admin/diario/aula?id=' . $aulaId . '\'"' : '' ?>>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm font-medium text-gray-900"><?= date('d/m/Y', strtotime((string) $aula['data_aula'])) ?></div>
                                <div class="mt-0.5 text-xs text-gray-500">
                                    <i class="fa-regular fa-clock mr-1"></i><?= htmlspecialchars(substr((string) $aula['horario_de'], 0, 5)) ?>–<?= htmlspecialchars(substr((string) $aula['horario_ate'], 0, 5)) ?>
                                </div>
                            </td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($aula['professor_nome'] ?? '')) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars((string) ($aula['turma_nome'] ?? '')) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($aula['materia_nome'] ?? '')) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $statusClasses[$status] ?? 'bg-slate-100 text-slate-700' ?>">
                                    <?= htmlspecialchars($labels[$status] ?? $status) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                <?php if ($status === 'pendente'): ?>
                                    <span class="text-gray-400">—</span>
                                <?php else: ?>
                                    <?= (int) ($aula['faltas'] ?? 0) ?>
                                    <?php if ($justificadas > 0): ?>
                                        <span class="block mt-0.5 text-xs text-gray-500"><?= $justificadas ?> justificada<?= $justificadas === 1 ? '' : 's' ?></span>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <a href="<?= htmlspecialchars($lancarUrl) ?>" onclick="event.stopPropagation()"
                                   class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold <?= $status === 'finalizada' ? 'bg-gray-100 text-gray-700 hover:bg-gray-200' : 'bg-indigo-50 text-indigo-700 hover:bg-indigo-100' ?>">
                                    <i class="fa-solid <?= $status === 'finalizada' ? 'fa-pen' : 'fa-clipboard-check' ?> mr-1.5"></i>
                                    <?= $status === 'finalizada' ? 'Editar' : 'Lançar' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($total > 0):
        $queryParams = $_GET ?? [];
        unset($queryParams['page']);
        $baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
        $separator = $baseQuery === '' ? '?' : '&';
    ?>
        <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> registro(s)
            </p>
            <?php if ($totalPages > 1): ?>
                <div class="flex items-center gap-1">
                    <?php if ($page > 1): ?><a href="<?= URL ?>/admin/diario<?= $baseQuery . $separator ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a><?php endif; ?>
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="<?= URL ?>/admin/diario<?= $baseQuery . $separator ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if ($page < $totalPages): ?><a href="<?= URL ?>/admin/diario<?= $baseQuery . $separator ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a><?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col" aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Filtrar diários</h3>
            <p class="mt-0.5 text-xs text-gray-500">Refine o período, professor ou situação da chamada.</p>
        </div>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar filtros"><i class="fa-solid fa-xmark text-xl"></i></button>
    </div>
    <form method="get" action="<?= URL ?>/admin/diario" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_inicio" class="block text-sm font-medium text-gray-700 mb-1.5">Data inicial</label>
                <input type="date" id="filtro_inicio" name="inicio" value="<?= htmlspecialchars($inicio) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_fim" class="block text-sm font-medium text-gray-700 mb-1.5">Data final</label>
                <input type="date" id="filtro_fim" name="fim" value="<?= htmlspecialchars($fim) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_turma" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="filtro_turma" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todas as turmas</option>
                    <?php foreach ($turmas ?? [] as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>" <?= ($turma_id ?? 0) === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_professor" class="block text-sm font-medium text-gray-700 mb-1.5">Professor</label>
                <select id="filtro_professor" name="professor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todos os professores</option>
                    <?php foreach ($professores as $professor): ?>
                        <option value="<?= (int) $professor['id'] ?>" <?= $professor_id === (int) $professor['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $professor['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos os status</option>
                    <?php foreach ($labels as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>" <?= $status_filtro === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= URL ?>/admin/diario" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">Limpar</a>
            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">Aplicar filtros</button>
        </div>
    </form>
</aside>

<script>
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeFilterDrawer();
});
</script>
