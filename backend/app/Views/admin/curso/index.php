<?php
$list = $list ?? [];
$schema_ready = $schema_ready ?? false;
$status = (string) ($status ?? '');
$message = (string) ($message ?? '');
$csrf_token = $csrf_token ?? '';
$has_tipo_possui_serie = (bool) ($has_tipo_possui_serie ?? false);

$page_header_title = 'Cursos';
$page_header_subtitle = 'Cadastre os cursos (ex.: Ensino Fundamental, Médio) para vincular às séries';
if ($schema_ready) {
    ob_start();
    ?>
    <button type="button" onclick="openCursoDrawer()"
       class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Novo Curso
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
    Estrutura ainda não disponível. Execute as migrations 022 a 026 no banco do tenant.
</div>
<?php elseif (empty($list)): ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-12 text-center text-gray-500">
    <i class="fa-solid fa-graduation-cap text-4xl text-gray-300 mb-4"></i>
    <p>Nenhum curso cadastrado</p>
    <button type="button" onclick="openCursoDrawer()"
       class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i>
        Cadastrar o primeiro curso
    </button>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <?php
    $bulk_delete_csrf_token = $csrf_token;
    $bulk_delete_bulk_url = URL . '/admin/curso/bulk-delete';
    $bulk_delete_ids_param = 'curso_ids';
    $bulk_delete_entity_singular = 'curso';
    $bulk_delete_entity_plural = 'cursos';
    $bulk_delete_single_url_template = URL . '/admin/curso/{id}/delete';
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Séries</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ordem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($list as $row): ?>
                <?php
                $tipo = $row['tipo'] ?? 'regular';
                $tipoLabel = $tipo === 'extra' ? 'Extra' : 'Regular';
                $tipoBadge = $tipo === 'extra' ? 'bg-indigo-100 text-indigo-800' : 'bg-blue-100 text-blue-800';
                $importLabel = $tipo === 'extra' ? 'Importar / vincular' : 'Importar alunos';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-4 whitespace-nowrap">
                        <input type="checkbox" class="bulk-row-checkbox rounded border-gray-300 text-red-600" value="<?= (int) $row['id'] ?>">
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                        <?= htmlspecialchars($row['nome']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $tipoBadge ?>">
                            <?= $tipoLabel ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?= (int) ($row['total_series'] ?? 0) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                        <?= (int) $row['ordem'] ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $row['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $row['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/curso/<?= (int) $row['id'] ?>/importar-alunos"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-file-import text-gray-400 w-4 text-center"></i> <?= htmlspecialchars($importLabel) ?>
                        </a>
                        <button type="button" onclick="openCursoDrawer(<?= (int) $row['id'] ?>)"
                           class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="openBulkDeleteSingle(<?= (int) $row['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-curso-' . (int) $row['id'];
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
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> curso(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/curso<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/curso<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/curso<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($schema_ready): ?>
<!-- Offcanvas: Cadastrar/Editar Curso -->
<div id="cursoDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeCursoDrawer()"></div>
<aside id="cursoDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="cursoDrawerTitle" class="text-xl font-bold text-gray-900">Novo Curso</h2>
        <button type="button" onclick="closeCursoDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="curso-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="cu_id" value="">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do curso</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="cu_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="cu_nome" name="nome" required
                               placeholder="Ex.: Ensino Fundamental, Ensino Médio"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>

                    <?php if ($has_tipo_possui_serie): ?>
                    <div>
                        <label for="cu_tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                        <select id="cu_tipo" name="tipo"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="regular">Regular (com série)</option>
                            <option value="extra">Extra (sem série — ex.: Música, Robótica)</option>
                        </select>
                    </div>
                    <div id="cu-wrap-possui-serie" class="flex items-end pb-1">
                        <div>
                            <label class="flex items-center">
                                <input type="checkbox" id="cu_possui_serie" name="possui_serie" value="1" checked
                                       class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                                <span class="ml-2 text-sm text-gray-700">Possui série (ex.: 1º Ano, 2º Ano)</span>
                            </label>
                            <p class="ml-6 text-xs text-gray-500 mt-1">Desmarque para cursos extras sem série.</p>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="sm:col-span-2">
                        <label for="cu_descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea id="cu_descricao" name="descricao" rows="3"
                                  placeholder="Descrição opcional do curso"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>

                    <div>
                        <label for="cu_ordem" class="block text-sm font-medium text-gray-700 mb-1">Ordem</label>
                        <input type="number" id="cu_ordem" name="ordem" value="0"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="mt-1 text-xs text-gray-500">Use múltiplos de 10 (10, 20, 30…) para ordenar na listagem.</p>
                    </div>

                    <div class="flex items-end pb-1">
                        <label class="flex items-center">
                            <input type="checkbox" id="cu_ativo" name="ativo" value="1" checked
                                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span class="ml-2 text-sm text-gray-700">Curso ativo</span>
                        </label>
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeCursoDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="cu-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<script>
<?php if ($has_tipo_possui_serie): ?>
(function () {
    var tipo = document.getElementById('cu_tipo');
    if (!tipo) return;
    function syncPossuiSerie() {
        var cb = document.getElementById('cu_possui_serie');
        var wrap = document.getElementById('cu-wrap-possui-serie');
        if (!cb || !wrap) return;
        if (tipo.value === 'extra') {
            cb.checked = false;
            cb.disabled = true;
            wrap.classList.add('opacity-60');
        } else {
            cb.disabled = false;
            wrap.classList.remove('opacity-60');
            if (!cb.checked) cb.checked = true;
        }
    }
    tipo.addEventListener('change', syncPossuiSerie);
})();
<?php endif; ?>

function openCursoDrawer(id) {
    var form = document.getElementById('curso-form');
    form.reset();
    document.getElementById('cu_id').value = '';
    document.getElementById('cu_ativo').checked = true;
    var possuiSerieCb = document.getElementById('cu_possui_serie');
    var possuiSerieWrap = document.getElementById('cu-wrap-possui-serie');
    if (possuiSerieCb) { possuiSerieCb.checked = true; possuiSerieCb.disabled = false; }
    if (possuiSerieWrap) { possuiSerieWrap.classList.remove('opacity-60'); }

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('cursoDrawerTitle').textContent = 'Novo Curso';
        document.getElementById('cu-form-submit-label').textContent = 'Salvar';
        showCursoDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('cursoDrawerTitle').textContent = 'Editar Curso';
    document.getElementById('cu-form-submit-label').textContent = 'Salvar Alterações';
    showCursoDrawer();

    fetch('<?= URL ?>/admin/curso/' + id + '/dados')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) { alert('Erro: ' + (data.error || '')); closeCursoDrawer(); return; }
            document.getElementById('cu_id').value = data.item.id;
            document.getElementById('cu_nome').value = data.item.nome;
            document.getElementById('cu_descricao').value = data.item.descricao || '';
            document.getElementById('cu_ordem').value = data.item.ordem;
            document.getElementById('cu_ativo').checked = !!parseInt(data.item.ativo, 10);
            var tipoSel = document.getElementById('cu_tipo');
            if (tipoSel) {
                tipoSel.value = data.item.tipo || 'regular';
                if (possuiSerieCb) {
                    var possuiSerie = !!parseInt(data.item.possui_serie ?? 1, 10);
                    if (tipoSel.value === 'extra') {
                        possuiSerieCb.checked = false;
                        possuiSerieCb.disabled = true;
                        if (possuiSerieWrap) possuiSerieWrap.classList.add('opacity-60');
                    } else {
                        possuiSerieCb.checked = possuiSerie;
                    }
                }
            }
        })
        .catch(function () { alert('Erro de conexão.'); closeCursoDrawer(); });
}

function showCursoDrawer() {
    document.getElementById('cursoDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('cursoDrawer');
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
}

function closeCursoDrawer() {
    var drawer = document.getElementById('cursoDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    setTimeout(function () { document.getElementById('cursoDrawerBackdrop').classList.add('hidden'); }, 300);
}

document.getElementById('curso-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var mode = this.dataset.mode;
    var id = document.getElementById('cu_id').value;
    var url = mode === 'create' ? '<?= URL ?>/admin/curso' : '<?= URL ?>/admin/curso/' + id + '/update';
    fetch(url, { method: 'POST', body: new FormData(this) })
        .then(function (r) { return r.json(); })
        .then(function (result) {
            if (result.success) { window.location.reload(); }
            else { alert('Erro: ' + result.error); }
        })
        .catch(function () { alert('Erro de conexão. Tente novamente.'); });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeCursoDrawer(); }
});
</script>
<?php endif; ?>
