<?php
/**
 * Modal + JS para exclusão individual/em lote com confirmação por senha.
 *
 * Variáveis esperadas:
 * - $bulk_delete_csrf_token
 * - $bulk_delete_bulk_url
 * - $bulk_delete_ids_param (ex.: curso_ids)
 * - $bulk_delete_entity_singular (ex.: curso)
 * - $bulk_delete_entity_plural (ex.: cursos)
 * - $bulk_delete_single_url_template (ex.: /admin/curso/{id}/delete) — {id} será substituído
 * - $bulk_delete_single_method (POST|DELETE, default POST)
 */
$bulk_delete_csrf_token = $bulk_delete_csrf_token ?? '';
$bulk_delete_bulk_url = $bulk_delete_bulk_url ?? '';
$bulk_delete_ids_param = $bulk_delete_ids_param ?? 'ids';
$bulk_delete_entity_singular = $bulk_delete_entity_singular ?? 'item';
$bulk_delete_entity_plural = $bulk_delete_entity_plural ?? 'itens';
$bulk_delete_single_url_template = $bulk_delete_single_url_template ?? '';
$bulk_delete_single_method = strtoupper($bulk_delete_single_method ?? 'POST');
?>

<div id="bulk-delete-bar" class="hidden px-6 py-3 bg-red-50 border-b border-red-100 flex items-center justify-between gap-4">
    <span id="bulk-delete-count" class="text-sm font-medium text-red-800">0 selecionados</span>
    <button type="button" id="bulk-delete-btn"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-red-300 bg-red-600 text-white text-sm font-medium hover:bg-red-700 transition-colors">
        <i class="fa-solid fa-trash-can text-xs"></i>
        Excluir selecionados
    </button>
</div>

<div id="bulk-delete-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-24 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium text-gray-900 mb-2" id="bulk-delete-modal-title">Confirmar exclusão</h3>
        <p class="text-sm text-gray-600 mb-4" id="bulk-delete-modal-desc">
            Digite sua senha de admin para confirmar a exclusão.
        </p>
        <div class="mb-4">
            <label for="bulk-delete-senha" class="block text-sm font-medium text-gray-700 mb-2">Sua senha *</label>
            <input type="password"
                   id="bulk-delete-senha"
                   class="w-full px-3 py-2 border border-red-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                   placeholder="Digite sua senha para confirmar"
                   autocomplete="current-password">
            <p id="bulk-delete-error" class="mt-2 text-sm text-red-600 hidden"></p>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" id="bulk-delete-cancel"
                    class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">
                Cancelar
            </button>
            <button type="button" id="bulk-delete-confirm"
                    class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">
                Confirmar exclusão
            </button>
        </div>
    </div>
</div>

<script>
(function () {
    var cfg = {
        csrf: <?= json_encode($bulk_delete_csrf_token) ?>,
        bulkUrl: <?= json_encode($bulk_delete_bulk_url) ?>,
        idsParam: <?= json_encode($bulk_delete_ids_param) ?>,
        entitySingular: <?= json_encode($bulk_delete_entity_singular) ?>,
        entityPlural: <?= json_encode($bulk_delete_entity_plural) ?>,
        singleUrlTemplate: <?= json_encode($bulk_delete_single_url_template) ?>,
        singleMethod: <?= json_encode($bulk_delete_single_method) ?>
    };

    var modal = document.getElementById('bulk-delete-modal');
    var bar = document.getElementById('bulk-delete-bar');
    var countEl = document.getElementById('bulk-delete-count');
    var senhaInput = document.getElementById('bulk-delete-senha');
    var errorEl = document.getElementById('bulk-delete-error');
    var titleEl = document.getElementById('bulk-delete-modal-title');
    var descEl = document.getElementById('bulk-delete-modal-desc');
    var pendingIds = [];
    var isBulk = false;

    function getRowCheckboxes() {
        return Array.prototype.slice.call(document.querySelectorAll('.bulk-row-checkbox'));
    }

    function getSelectedIds() {
        return getRowCheckboxes()
            .filter(function (cb) { return cb.checked; })
            .map(function (cb) { return parseInt(cb.value, 10); })
            .filter(function (id) { return id > 0; });
    }

    function updateBar() {
        var ids = getSelectedIds();
        if (!bar) return;
        if (ids.length === 0) {
            bar.classList.add('hidden');
            return;
        }
        bar.classList.remove('hidden');
        countEl.textContent = ids.length + ' selecionado' + (ids.length === 1 ? '' : 's');
    }

    var selectAll = document.getElementById('bulk-select-all');
    if (selectAll) {
        selectAll.addEventListener('change', function () {
            getRowCheckboxes().forEach(function (cb) { cb.checked = selectAll.checked; });
            updateBar();
        });
    }

    getRowCheckboxes().forEach(function (cb) {
        cb.addEventListener('change', updateBar);
    });

    var bulkBtn = document.getElementById('bulk-delete-btn');
    if (bulkBtn) {
        bulkBtn.addEventListener('click', function () {
            var ids = getSelectedIds();
            if (ids.length === 0) return;
            openModal(ids, true);
        });
    }

    function openModal(ids, bulk) {
        pendingIds = ids;
        isBulk = bulk;
        senhaInput.value = '';
        errorEl.classList.add('hidden');
        errorEl.textContent = '';
        if (bulk) {
            titleEl.textContent = 'Excluir ' + ids.length + ' ' + (ids.length === 1 ? cfg.entitySingular : cfg.entityPlural);
            descEl.textContent = 'Esta ação não pode ser desfeita. Digite sua senha de admin para confirmar.';
        } else {
            titleEl.textContent = 'Excluir ' + cfg.entitySingular;
            descEl.textContent = 'Esta ação não pode ser desfeita. Digite sua senha de admin para confirmar.';
        }
        modal.classList.remove('hidden');
        senhaInput.focus();
    }

    window.openBulkDeleteSingle = function (id) {
        openModal([id], false);
    };

    function closeModal() {
        modal.classList.add('hidden');
        pendingIds = [];
    }

    document.getElementById('bulk-delete-cancel').addEventListener('click', closeModal);
    modal.addEventListener('click', function (e) {
        if (e.target === modal) closeModal();
    });

    document.getElementById('bulk-delete-confirm').addEventListener('click', function () {
        var senha = senhaInput.value.trim();
        if (!senha) {
            errorEl.textContent = 'Digite sua senha para confirmar.';
            errorEl.classList.remove('hidden');
            return;
        }

        var confirmBtn = document.getElementById('bulk-delete-confirm');
        confirmBtn.disabled = true;

        if (isBulk) {
            var body = new URLSearchParams();
            body.append('_token', cfg.csrf);
            body.append('senha', senha);
            pendingIds.forEach(function (id) { body.append(cfg.idsParam + '[]', String(id)); });

            fetch(cfg.bulkUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(handleResponse)
            .catch(function () { showError('Erro de conexão. Tente novamente.'); })
            .finally(function () { confirmBtn.disabled = false; });
        } else {
            var id = pendingIds[0];
            var url = cfg.singleUrlTemplate.replace('{id}', String(id));
            var body = new URLSearchParams();
            body.append('_token', cfg.csrf);
            body.append('senha', senha);

            fetch(url, {
                method: cfg.singleMethod,
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
            .then(function (r) { return r.json(); })
            .then(handleResponse)
            .catch(function () { showError('Erro de conexão. Tente novamente.'); })
            .finally(function () { confirmBtn.disabled = false; });
        }
    });

    function handleResponse(data) {
        if (data.success) {
            location.reload();
            return;
        }
        showError(data.error || data.message || 'Falha ao excluir.');
    }

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.classList.remove('hidden');
    }

    senhaInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('bulk-delete-confirm').click();
        }
    });
})();
</script>
