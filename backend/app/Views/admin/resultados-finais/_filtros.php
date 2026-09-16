<?php
require_once __DIR__ . '/../../../Models/Education/ResultadoAcademico.php';
$anos = is_array($anos ?? null) ? $anos : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$turmaId = (int) ($turma_id ?? 0);
$action = (string) ($filtros_action ?? URL . '/admin/resultados-finais');
$mostrarTurma = !empty($filtros_mostrar_turma);
$extraHidden = is_array($filtros_hidden ?? null) ? $filtros_hidden : [];
$filtrosOffcanvas = !empty($filtros_offcanvas);
$selectCls = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';

ob_start();
foreach ($extraHidden as $k => $v):
?>
        <input type="hidden" name="<?= htmlspecialchars((string) $k) ?>" value="<?= htmlspecialchars((string) $v) ?>">
<?php
endforeach;
$filtrosHiddenHtml = ob_get_clean();

ob_start();
?>
    <div>
        <label for="filtro_ano_letivo" class="block text-sm font-medium text-gray-700 mb-1.5">Ano letivo</label>
        <select id="filtro_ano_letivo" name="ano_letivo" class="<?= $selectCls ?>">
            <?php foreach ($anos as $ano): ?>
                <option value="<?= (int) $ano ?>" <?= $anoLetivo === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="filtro_periodo_tipo" class="block text-sm font-medium text-gray-700 mb-1.5">Período</label>
        <select id="filtro_periodo_tipo" name="periodo_tipo" class="<?= $selectCls ?>">
            <?php foreach (ResultadoAcademico::PERIODO_TIPOS as $cod => $lab): ?>
                <option value="<?= htmlspecialchars($cod) ?>" <?= $periodoTipo === $cod ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="filtro_periodo_numero" class="block text-sm font-medium text-gray-700 mb-1.5">Nº do período</label>
        <select id="filtro_periodo_numero" name="periodo_numero" class="<?= $selectCls ?>">
            <option value="0" <?= $periodoNumero === 0 ? 'selected' : '' ?>>— (ano inteiro)</option>
            <?php for ($n = 1; $n <= 4; $n++): ?>
                <option value="<?= $n ?>" <?= $periodoNumero === $n ? 'selected' : '' ?>><?= $n ?></option>
            <?php endfor; ?>
        </select>
    </div>
    <?php if ($mostrarTurma): ?>
    <div>
        <label for="filtro_turma_id" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
        <select id="filtro_turma_id" name="turma_id" class="<?= $selectCls ?>">
            <option value="0">Todas</option>
            <?php foreach ($turmas as $turma): ?>
                <option value="<?= (int) $turma['id'] ?>" <?= $turmaId === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php endif; ?>
<?php
$filtrosCamposHtml = ob_get_clean();

if ($filtrosOffcanvas):
?>
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar turmas</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= htmlspecialchars($action) ?>" class="flex flex-col flex-1 overflow-hidden">
        <?= $filtrosHiddenHtml ?>
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <?= $filtrosCamposHtml ?>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex flex-col-reverse sm:flex-row gap-3 bg-gray-50">
            <button type="button" onclick="clearFilters()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 btn-primary-custom rounded-lg text-sm font-semibold hover:opacity-90 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>
<script>
function openFilterDrawer() {
    var backdrop = document.getElementById('filterDrawerBackdrop');
    var drawer = document.getElementById('filterDrawer');
    if (!backdrop || !drawer) return;
    backdrop.classList.remove('hidden');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () {
        drawer.classList.remove('translate-x-full');
    });
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    var backdrop = document.getElementById('filterDrawerBackdrop');
    var drawer = document.getElementById('filterDrawer');
    if (!backdrop || !drawer) return;
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    backdrop.classList.add('hidden');
    document.body.style.overflow = '';
}
function clearFilters() {
    window.location.href = <?= json_encode($action) ?>;
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
    }
});
</script>
<?php else: ?>
<form method="GET" action="<?= htmlspecialchars($action) ?>" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
    <?= $filtrosHiddenHtml ?>
    <?= $filtrosCamposHtml ?>
    <div class="flex gap-2">
        <button type="submit" class="px-4 py-2 bg-primary text-primary rounded-lg text-sm font-medium hover:opacity-90">Filtrar</button>
    </div>
</form>
<?php
endif;
unset($filtros_offcanvas, $filtrosOffcanvas, $filtrosHiddenHtml, $filtrosCamposHtml, $selectCls);
?>
