<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$oficios = is_array($oficios ?? null) ? $oficios : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];
$pagination = is_array($pagination ?? null) ? $pagination : [];
$schemaPronto = !empty($schema_pronto);
$token = (string) ($csrf_token ?? '');

$filtrosAtivosCount = 0;
foreach ([$filtros['status'] ?? '', $filtros['q'] ?? '', (int) ($filtros['aluno_id'] ?? 0)] as $fv) {
    if (!empty($fv)) {
        $filtrosAtivosCount++;
    }
}

$page_header_title = 'Ofícios';
$page_header_subtitle = 'Correspondência oficial da escola, com numeração anual e PDF no papel timbrado.';
ob_start();
?>
<a href="<?= URL ?>/admin/vida-escolar"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-scroll mr-2 text-gray-500"></i>
    Documentos
</a>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <?php if ($filtrosAtivosCount > 0): ?>
    <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
    <?php endif; ?>
</button>
<a href="<?= URL ?>/admin/vida-escolar/oficios/novo"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo ofício
</a>
<?php
$page_header_actions = ob_get_clean();
include dirname(__DIR__, 5) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 5) . '/Views/admin/_partials/flash_message.php';
?>

<?php if (!$schemaPronto): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-900 text-sm">
    Aplique a migration <code>2026_08_31_secretaria_oficios</code> no painel Master para ativar os ofícios.
</div>
<?php else: ?>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar ofícios</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/vida-escolar/oficios" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_ano" class="block text-sm font-medium text-gray-700 mb-1.5">Ano</label>
                <input type="number" id="filtro_ano" name="ano" min="2000" max="2100"
                       value="<?= (int) ($filtros['ano'] ?? date('Y')) ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                    <option value="">Todos</option>
                    <option value="rascunho" <?= ($filtros['status'] ?? '') === 'rascunho' ? 'selected' : '' ?>>Rascunho</option>
                    <option value="emitido" <?= ($filtros['status'] ?? '') === 'emitido' ? 'selected' : '' ?>>Emitido</option>
                    <option value="cancelado" <?= ($filtros['status'] ?? '') === 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div>
                <label for="filtro_q" class="block text-sm font-medium text-gray-700 mb-1.5">Destinatário ou assunto</label>
                <input type="text" id="filtro_q" name="q" value="<?= $esc($filtros['q'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <?php if ((int) ($filtros['aluno_id'] ?? 0) > 0): ?>
            <input type="hidden" name="aluno_id" value="<?= (int) $filtros['aluno_id'] ?>">
            <?php endif; ?>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex justify-end gap-3">
            <a href="<?= URL ?>/admin/vida-escolar/oficios" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700">Limpar</a>
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Aplicar</button>
        </div>
    </form>
</aside>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Número</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Destinatário</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Assunto</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($oficios === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-envelope-open-text text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum ofício neste filtro.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($oficios as $o): ?>
                    <?php
                    $st = (string) ($o['status'] ?? 'rascunho');
                    $ui_badge_variant = $st === 'emitido' ? 'ativo' : ($st === 'cancelado' ? 'inativo' : 'rascunho');
                    $ui_badge_label = $st === 'emitido' ? 'Emitido' : ($st === 'cancelado' ? 'Cancelado' : 'Rascunho');
                    $dataFmt = '';
                    if (!empty($o['data_oficio'])) {
                        $dt = DateTime::createFromFormat('Y-m-d', substr((string) $o['data_oficio'], 0, 10));
                        $dataFmt = $dt ? $dt->format('d/m/Y') : (string) $o['data_oficio'];
                    }
                    $numeroTxt = (int) ($o['numero'] ?? 0) > 0
                        ? 'nº ' . (int) $o['numero'] . '/' . (int) ($o['ano'] ?? 0)
                        : '—';
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $esc($numeroTxt) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <div class="font-medium"><?= $esc($o['destinatario'] ?? '') ?></div>
                            <?php if (!empty($o['instituicao'])): ?>
                            <div class="text-gray-500 text-xs"><?= $esc($o['instituicao']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($o['aluno_nome'])): ?>
                            <div class="text-gray-500 text-xs">Aluno: <?= $esc($o['aluno_nome']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= $esc($o['assunto'] ?? '') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= $esc($dataFmt) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <?php include dirname(__DIR__, 5) . '/Views/admin/_partials/ui/badge.php'; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?php ob_start(); ?>
                            <?php if ($st !== 'cancelado'): ?>
                            <a href="<?= URL ?>/admin/vida-escolar/oficios/<?= (int) $o['id'] ?>/pdf"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-file-pdf text-gray-400 w-4 text-center"></i> <?= $st === 'rascunho' ? 'Prévia PDF' : 'PDF' ?>
                            </a>
                            <?php endif; ?>
                            <?php if ($st === 'rascunho'): ?>
                            <a href="<?= URL ?>/admin/vida-escolar/oficios/<?= (int) $o['id'] ?>/editar"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </a>
                            <form method="post" action="<?= URL ?>/admin/vida-escolar/oficios/<?= (int) $o['id'] ?>/emitir">
                                <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-stamp text-gray-400 w-4 text-center"></i> Emitir
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($st !== 'cancelado'): ?>
                            <div class="border-t border-gray-100 my-1"></div>
                            <form method="post" action="<?= URL ?>/admin/vida-escolar/oficios/<?= (int) $o['id'] ?>/cancelar"
                                  onsubmit="return confirm('Cancelar este ofício?');">
                                <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-ban text-red-400 w-4 text-center"></i> Cancelar
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'row-actions-oficio-' . (int) $o['id']; ?>
                            <?php include dirname(__DIR__, 5) . '/Views/admin/_partials/row_actions_dropdown.php'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ((int) ($pagination['total'] ?? 0) > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex items-center justify-between flex-wrap gap-3">
        <p class="text-sm text-gray-600">Exibindo <?= count($oficios) ?> de <?= (int) $pagination['total'] ?></p>
        <?php if ((int) ($pagination['total_pages'] ?? 1) > 1): ?>
        <div class="flex gap-1">
            <?php
            $qs = $_GET;
            unset($qs['page']);
            $baseQs = $qs === [] ? '' : ('&' . http_build_query($qs));
            $cur = (int) ($pagination['page'] ?? 1);
            $tp = (int) $pagination['total_pages'];
            ?>
            <?php if ($cur > 1): ?>
            <a href="<?= URL ?>/admin/vida-escolar/oficios?page=<?= $cur - 1 ?><?= $esc($baseQs) ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $cur - 2); $i <= min($tp, $cur + 2); $i++): ?>
            <a href="<?= URL ?>/admin/vida-escolar/oficios?page=<?= $i ?><?= $esc($baseQs) ?>"
               class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $cur ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($cur < $tp): ?>
            <a href="<?= URL ?>/admin/vida-escolar/oficios?page=<?= $cur + 1 ?><?= $esc($baseQs) ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<script>
function openFilterDrawer() {
    document.getElementById('filterDrawer').classList.remove('translate-x-full');
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
}
function closeFilterDrawer() {
    document.getElementById('filterDrawer').classList.add('translate-x-full');
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
}
</script>
<?php endif; ?>
