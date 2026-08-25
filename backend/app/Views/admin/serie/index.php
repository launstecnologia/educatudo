<?php
$list = $list ?? [];
$schema_ready = $schema_ready ?? false;
$status = (string)($status ?? '');
$message = (string)($message ?? '');
$csrf_token = $csrf_token ?? '';
$cursos = $cursos ?? [];

$page_header_title = 'Séries';
$page_header_subtitle = 'Cadastre as séries por curso (ex.: 1º Ano, 2º Ano) para vincular às turmas';
if ($schema_ready) {
    ob_start();
    ?>
    <button type="button" onclick="openSerieDrawer()"
       class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Nova Série
    </button>
    <?php
    $page_header_actions = ob_get_clean();
} else {
    $page_header_actions = '';
}
include __DIR__ . '/../_partials/page_header_list.php';

$flash_status = $status;
$flash_message = $message;
include __DIR__ . '/../_partials/flash_message.php';
?>

<?php if (!$schema_ready): ?>
<div class="bg-amber-50 border border-amber-200 text-amber-800 px-4 py-3 rounded-lg text-sm">
    Estrutura ainda não disponível. Execute as migrations 022 a 026 e cadastre cursos antes.
</div>
<?php elseif (empty($list)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-500">
    <i class="fa-solid fa-layer-group text-4xl text-gray-300 mb-4"></i>
    <p>Nenhuma série cadastrada</p>
    <button type="button" onclick="openSerieDrawer()"
       class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Cadastrar a primeira série
    </button>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <?php
    $bulk_delete_csrf_token = $csrf_token;
    $bulk_delete_bulk_url = URL . '/admin/serie/bulk-delete';
    $bulk_delete_ids_param = 'serie_ids';
    $bulk_delete_entity_singular = 'série';
    $bulk_delete_entity_plural = 'séries';
    $bulk_delete_single_url_template = URL . '/admin/serie/{id}/delete';
    $bulk_delete_single_method = 'POST';
    include __DIR__ . '/../_partials/bulk_delete_modal.php';
    ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left w-10">
                        <input type="checkbox" id="bulk-select-all" class="rounded border-gray-300 text-red-600" title="Selecionar todos">
                    </th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Curso</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Série</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ordem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($list as $row): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap">
                        <input type="checkbox" class="bulk-row-checkbox rounded border-gray-300 text-red-600" value="<?= (int)$row['id'] ?>">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?= htmlspecialchars($row['curso_nome'] ?? '') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?= htmlspecialchars($row['nome']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?= (int)$row['ordem'] ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $row['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $row['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openSerieDrawer(<?= (int)$row['id'] ?>)"
                           class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="openBulkDeleteSingle(<?= (int)$row['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-serie-' . (int)$row['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pag = $pagination ?? [];
    $pagTotal = (int)($pag['total'] ?? 0);
    $pagPerPage = (int)($pag['per_page'] ?? 10);
    $pagPage = (int)($pag['page'] ?? 1);
    $pagTotalPages = (int)($pag['total_pages'] ?? 1);
    $pagQueryParams = $_GET ?? [];
    unset($pagQueryParams['page']);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> série(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/serie<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/serie<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/serie<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($schema_ready): ?>
<!-- Offcanvas: Cadastrar/Editar Série -->
<div id="serieDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeSerieDrawer()"></div>
<aside id="serieDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="serieDrawerTitle" class="text-xl font-bold text-gray-900">Nova Série</h2>
        <button type="button" onclick="closeSerieDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="serie-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="se_id" value="">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados da série</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="se_curso_id" class="block text-sm font-medium text-gray-700 mb-1">Curso <span class="text-red-500">*</span></label>
                        <select id="se_curso_id" name="curso_id" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Selecione o curso</option>
                            <?php foreach ($cursos as $c): ?>
                            <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nome']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label for="se_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome da série <span class="text-red-500">*</span></label>
                        <input type="text" id="se_nome" name="nome" required placeholder="Ex.: 1º Ano, 2º Ano"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="se_ordem" class="block text-sm font-medium text-gray-700 mb-1">Ordem</label>
                        <input type="number" id="se_ordem" name="ordem" value="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="mt-1 text-xs text-gray-500">Use múltiplos de 10 (10, 20, 30…) para ordenar na listagem.</p>
                    </div>
                    <div class="flex items-end pb-1">
                        <label class="flex items-center">
                            <input type="checkbox" id="se_ativo" name="ativo" value="1" checked
                                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Série ativa</span>
                        </label>
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeSerieDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="se-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<script>
function openSerieDrawer(id) {
    var form = document.getElementById('serie-form');
    form.reset();
    document.getElementById('se_id').value = '';
    document.getElementById('se_ativo').checked = true;

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('serieDrawerTitle').textContent = 'Nova Série';
        document.getElementById('se-form-submit-label').textContent = 'Salvar';
        showSerieDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('serieDrawerTitle').textContent = 'Editar Série';
    document.getElementById('se-form-submit-label').textContent = 'Salvar Alterações';
    showSerieDrawer();

    fetch('<?= URL ?>/admin/serie/' + id + '/dados')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert('Erro: ' + (data.error || '')); closeSerieDrawer(); return; }
            document.getElementById('se_id').value = data.item.id;
            document.getElementById('se_curso_id').value = data.item.curso_id;
            document.getElementById('se_nome').value = data.item.nome;
            document.getElementById('se_ordem').value = data.item.ordem;
            document.getElementById('se_ativo').checked = !!parseInt(data.item.ativo, 10);
        })
        .catch(function () { alert('Erro de conexão.'); closeSerieDrawer(); });
}

function showSerieDrawer() {
    document.getElementById('serieDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('serieDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
}

function closeSerieDrawer() {
    var drawer = document.getElementById('serieDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    setTimeout(function () { document.getElementById('serieDrawerBackdrop').classList.add('hidden'); }, 300);
}

document.getElementById('serie-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var mode = this.dataset.mode;
    var id = document.getElementById('se_id').value;
    var url = mode === 'create' ? '<?= URL ?>/admin/serie' : '<?= URL ?>/admin/serie/' + id + '/update';
    fetch(url, { method: 'POST', body: new FormData(this) })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) { window.location.reload(); }
            else { alert('Erro: ' + result.error); }
        })
        .catch(function () { alert('Erro de conexão. Tente novamente.'); });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeSerieDrawer(); }
});
</script>
<?php endif; ?>
