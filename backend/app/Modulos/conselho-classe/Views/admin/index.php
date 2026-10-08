<?php
require_once __DIR__ . '/../../Models/ConselhoSessao.php';
require_once __DIR__ . '/../../Services/ConselhoService.php';

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
use App\Modulos\ConselhoClasse\Services\ConselhoService;

$linhas = is_array($linhas ?? null) ? $linhas : [];
$anos = is_array($anos ?? null) ? $anos : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$bimestre = (int) ($bimestre ?? 1);
$turmaId = (int) ($turma_id ?? 0);
$csrf_token = $csrf_token ?? '';

if (!class_exists('PeriodoLetivo', false)) {
    require_once dirname(__DIR__, 4) . '/Core/PeriodoLetivo.php';
}
$anoPadrao = (int) ($anos[0] ?? date('Y'));
$periodoPadrao = (int) PeriodoLetivo::numeroPadrao($anoLetivo);
$filtrosAtivosCount = 0;
if ($anoLetivo !== $anoPadrao) {
    $filtrosAtivosCount++;
}
if ($bimestre !== $periodoPadrao) {
    $filtrosAtivosCount++;
}
if ($turmaId > 0) {
    $filtrosAtivosCount++;
}
$selectCls = 'w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';

$page_header_title = 'Conselho de Classe';
$page_header_subtitle = 'Etapa colegiada de análise pedagógica. Consome boletim, diário e ocorrências.';
ob_start();
$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFilterDrawer()';
$ui_btn_filter_count = $filtrosAtivosCount;
include __DIR__ . '/../../../../Views/admin/_partials/ui/btn.php';
?>
<a href="<?= URL ?>/admin/conselhos/novo?ano_letivo=<?= $anoLetivo ?>&bimestre=<?= $bimestre ?>"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Iniciar Conselho
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true"
       role="dialog"
       aria-labelledby="conselhoFiltroTitulo">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 id="conselhoFiltroTitulo" class="text-lg font-semibold text-gray-900">Filtrar conselhos</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/conselhos" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_ano_letivo" class="block text-sm font-medium text-gray-700 mb-1.5">Ano letivo</label>
                <select id="filtro_ano_letivo" name="ano_letivo" class="<?= $selectCls ?>" data-periodo-ano>
                    <?php foreach ($anos as $ano): ?>
                        <option value="<?= (int) $ano ?>" <?= $anoLetivo === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_bimestre" class="block text-sm font-medium text-gray-700 mb-1.5">Período</label>
                <select id="filtro_bimestre" name="bimestre" class="<?= $selectCls ?>" data-periodo-letivo-select data-periodo-selecionado="<?= (int) $bimestre ?>">
                    <?= PeriodoLetivo::optionsHtml($anoLetivo, $bimestre) ?>
                </select>
            </div>
            <div>
                <label for="filtro_turma_id" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="filtro_turma_id" name="turma_id" class="<?= $selectCls ?>">
                    <option value="0">Todas</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>" <?= $turmaId === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
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
    window.location.href = <?= json_encode(URL . '/admin/conselhos') ?>;
}
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var drawer = document.getElementById('filterDrawer');
    if (!drawer || drawer.getAttribute('aria-hidden') === 'true') return;
    closeFilterDrawer();
});
</script>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Período</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pendências</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($linhas === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-chalkboard-user text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma turma encontrada para este filtro.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($linhas as $linha):
                    $status = (string) ($linha['status_exibicao'] ?? 'nao_iniciado');
                    $pend = (int) (($linha['pendencias']['total'] ?? 0));
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <div class="font-medium"><?= htmlspecialchars((string) $linha['turma_nome']) ?></div>
                        <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($linha['turma_serie'] ?? '')) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars(class_exists('PeriodoLetivo') ? PeriodoLetivo::rotulo($anoLetivo, $bimestre) : ($bimestre . 'º Bimestre')) ?> / <?= (int) $anoLetivo ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= (int) ($linha['total_alunos'] ?? 0) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm <?= $pend > 0 ? 'text-amber-700 font-medium' : 'text-gray-700' ?>"><?= $pend ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $ui_badge_variant = ConselhoService::statusBadge($status);
                        $ui_badge_label = ConselhoService::statusLabel($status);
                        include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php';
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <?php if (!empty($linha['sessao_id'])): ?>
                        <a href="<?= URL ?>/admin/conselhos/<?= (int) $linha['sessao_id'] ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-table text-gray-400 w-4 text-center"></i> Abrir matriz
                        </a>
                        <?php else: ?>
                        <a href="<?= URL ?>/admin/conselhos/novo?turma_id=<?= (int) $linha['turma_id'] ?>&ano_letivo=<?= $anoLetivo ?>&bimestre=<?= $bimestre ?>"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-play text-gray-400 w-4 text-center"></i> Iniciar
                        </a>
                        <?php endif; ?>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-conselho-' . (int) $linha['turma_id'];
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
