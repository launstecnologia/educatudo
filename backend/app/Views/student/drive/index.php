<?php
$current_page = 'drive';
$flash = $flash ?? null;
$baseUrl = $baseUrl ?? (URL . '/drive');
?>
<div class="mobile-content flex-1 overflow-y-auto w-full min-h-0 p-4 md:p-6">
    <div class="w-full max-w-5xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Meu Drive</h1>
                <p class="text-gray-600 mt-1">Seus arquivos e pastas na nuvem.</p>
            </div>
            <!-- Overlay de loading no upload -->
        <div id="drive-upload-overlay" class="hidden fixed inset-0 bg-black/50 z-[100] flex items-center justify-center">
            <div class="bg-white rounded-xl shadow-xl px-8 py-6 flex flex-col items-center gap-4 min-w-[200px]">
                <svg class="animate-spin h-10 w-10 text-indigo-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                <p class="text-gray-700 font-medium">Enviando arquivo...</p>
            </div>
        </div>

            <?php if ($canEdit ?? true): ?>
            <div class="flex flex-wrap gap-2">
                <button type="button" id="btn-new-folder" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m5 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                    Nova pasta
                </button>
                <form id="form-upload" action="<?= $baseUrl ?>/upload" method="post" enctype="multipart/form-data" class="inline">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="parent_id" value="<?= $currentFolderId ?? '' ?>">
                    <input type="file" name="file" id="input-file" class="hidden" multiple>
                    <button type="button" id="btn-upload" class="inline-flex items-center px-4 py-2.5 bg-white border border-indigo-300 text-indigo-700 hover:bg-indigo-50 rounded-lg font-medium transition-colors shadow-sm">
                        <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"></path></svg>
                        Enviar arquivo
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($flash && !empty($flash['message'])): ?>
            <div class="mb-4 p-4 rounded-lg <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border border-red-200 text-red-700' : 'bg-green-50 border border-green-200 text-green-700' ?>">
                <?= htmlspecialchars($flash['message']) ?>
            </div>
        <?php endif; ?>

        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-gray-600 mb-4">
            <?php foreach ($breadcrumb ?? [] as $i => $b): ?>
                <?php if ($i > 0): ?><span class="text-gray-400">/</span><?php endif; ?>
                <?php if ($b['id'] === null): ?>
                    <a href="<?= $baseUrl ?>" class="hover:text-indigo-600"><?= htmlspecialchars($b['name']) ?></a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/folder/<?= (int)$b['id'] ?>" class="hover:text-indigo-600"><?= htmlspecialchars($b['name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <!-- Lista de itens -->
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="divide-y divide-gray-100">
                <?php if (empty($items)): ?>
                    <div class="p-8 text-center text-gray-500">
                        <?= ($currentFolderId ?? null) ? 'Esta pasta está vazia.' : 'Nenhum arquivo ou pasta ainda. Crie uma pasta ou envie um arquivo.' ?>
                    </div>
                <?php else: ?>
                    <?php foreach ($items as $it): ?>
                        <div class="flex items-center gap-4 p-3 hover:bg-gray-50 group" data-item-id="<?= (int)$it['id'] ?>" data-item-name="<?= htmlspecialchars($it['name']) ?>" data-item-type="<?= htmlspecialchars($it['type']) ?>">
                            <?php if ($it['type'] === 'folder'): ?>
                                <a href="<?= $baseUrl ?>/folder/<?= (int)$it['id'] ?>" class="flex-1 flex items-center gap-3 min-w-0">
                                    <span class="flex-shrink-0 text-amber-500"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg></span>
                                    <span class="font-medium text-gray-900 truncate"><?= htmlspecialchars($it['name']) ?></span>
                                </a>
                            <?php else: ?>
                                <a href="<?= $baseUrl ?>/view/<?= (int)$it['id'] ?>" class="flex-1 flex items-center gap-3 min-w-0">
                                    <span class="flex-shrink-0 text-blue-500"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path></svg></span>
                                    <span class="font-medium text-gray-900 truncate"><?= htmlspecialchars($it['name']) ?></span>
                                    <span class="text-sm text-gray-400 flex-shrink-0"><?= number_format((int)($it['file_size'] ?? 0) / 1024, 1) ?> KB</span>
                                </a>
                            <?php endif; ?>
                            <?php if ($canEdit ?? true): ?>
                            <div class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                                <button type="button" class="drive-action-rename p-2 text-gray-500 hover:text-indigo-600 rounded" title="Renomear"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg></button>
                                <button type="button" class="drive-action-share p-2 text-gray-500 hover:text-indigo-600 rounded" title="Compartilhar"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg></button>
                                <button type="button" class="drive-action-delete p-2 text-gray-500 hover:text-red-600 rounded" title="Excluir"><svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg></button>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($shared) && ($currentFolderId ?? null) === null): ?>
            <h2 class="text-lg font-semibold text-gray-900 mt-8 mb-3">Compartilhados comigo</h2>
            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
                <div class="divide-y divide-gray-100">
                    <?php foreach ($shared as $s): ?>
                        <?php if ($s['type'] === 'folder'): ?>
                            <a href="<?= $baseUrl ?>/folder/<?= (int)$s['item_id'] ?>" class="flex items-center gap-4 p-3 hover:bg-gray-50">
                                <span class="text-amber-500"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M2 6a2 2 0 012-2h5l2 2h5a2 2 0 012 2v6a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"></path></svg></span>
                                <span class="font-medium text-gray-900"><?= htmlspecialchars($s['name']) ?></span>
                            </a>
                        <?php else: ?>
                            <a href="<?= $baseUrl ?>/view/<?= (int)$s['item_id'] ?>" class="flex items-center gap-4 p-3 hover:bg-gray-50">
                                <span class="text-blue-500"><svg class="w-8 h-8" fill="currentColor" viewBox="0 0 20 20"><path d="M4 4a2 2 0 012-2h4.586A2 2 0 0112 2.586L15.414 6A2 2 0 0116 7.414V16a2 2 0 01-2 2H6a2 2 0 01-2-2V4z"></path></svg></span>
                                <span class="font-medium text-gray-900"><?= htmlspecialchars($s['name']) ?></span>
                            </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Modal Nova Pasta -->
<div id="modal-new-folder" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Nova pasta</h3>
        <form action="<?= $baseUrl ?>/create-folder" method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="parent_id" value="<?= $currentFolderId ?? '' ?>">
            <input type="text" name="name" required placeholder="Nome da pasta" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 mb-4">
            <div class="flex justify-end gap-2">
                <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Criar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Renomear -->
<div id="modal-rename" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Renomear</h3>
        <form id="form-rename" action="<?= $baseUrl ?>/rename" method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="item_id" id="rename-item-id" value="">
            <input type="text" name="name" id="rename-name" required class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 mb-4">
            <div class="flex justify-end gap-2">
                <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Excluir -->
<div id="modal-delete" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6">
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Excluir</h3>
        <p class="text-gray-600 mb-4">Excluir <strong id="delete-item-name"></strong>? Esta ação não pode ser desfeita.</p>
        <form id="form-delete" action="<?= $baseUrl ?>/delete" method="post">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="item_id" id="delete-item-id" value="">
            <div class="flex justify-end gap-2">
                <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700">Excluir</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal Compartilhar -->
<div id="modal-share" class="hidden fixed inset-0 bg-black/50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl max-w-md w-full p-6 max-h-[90vh] overflow-hidden flex flex-col">
        <h3 class="text-lg font-semibold text-gray-900 mb-4">Compartilhar</h3>
        <input type="hidden" id="share-item-id" value="">
        <div class="mb-4">
            <input type="text" id="share-search" placeholder="Buscar por nome ou e-mail..." class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500">
            <div id="share-results" class="mt-2 border border-gray-200 rounded-lg max-h-40 overflow-y-auto hidden"></div>
        </div>
        <div class="mb-4 flex-1 min-h-0 overflow-y-auto">
            <p class="text-sm text-gray-600 mb-2">Compartilhado com:</p>
            <ul id="share-list" class="space-y-2"></ul>
        </div>
        <div class="flex justify-end pt-2">
            <button type="button" class="modal-close px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Fechar</button>
        </div>
    </div>
</div>

<script>
(function() {
    var baseUrl = <?= json_encode($baseUrl) ?>;
    var csrf = <?= json_encode($csrf_token ?? '') ?>;

    document.getElementById('btn-new-folder')?.addEventListener('click', function() {
        document.getElementById('modal-new-folder').classList.remove('hidden');
    });
    document.getElementById('btn-upload')?.addEventListener('click', function() {
        document.getElementById('input-file').click();
    });

    var formUpload = document.getElementById('form-upload');
    var overlay = document.getElementById('drive-upload-overlay');
    var currentFolderId = <?= json_encode($currentFolderId ?? '') ?>;
    formUpload?.addEventListener('submit', function(e) {
        e.preventDefault();
        if (!document.getElementById('input-file').files.length) return;
        overlay?.classList.remove('hidden');
        var formData = new FormData(formUpload);
        fetch(formUpload.action, { method: 'POST', body: formData, redirect: 'manual' })
            .then(function(res) {
                if (res.type === 'opaqueredirect' || res.status === 0 || res.status === 302) {
                    var url = res.headers.get('Location');
                    if (url) window.location.href = url;
                    else window.location.href = currentFolderId ? baseUrl + '/folder/' + currentFolderId : baseUrl;
                } else if (res.status >= 200 && res.status < 300) {
                    window.location.reload();
                } else {
                    return res.text().then(function(text) {
                        overlay?.classList.add('hidden');
                        alert('Erro ao enviar. Tente novamente ou recarregue a página (F5).');
                    });
                }
            })
            .catch(function() {
                overlay?.classList.add('hidden');
                alert('Erro ao enviar. Verifique sua conexão e tente novamente.');
            });
    });
    document.getElementById('input-file')?.addEventListener('change', function() {
        if (this.files.length) formUpload?.dispatchEvent(new Event('submit', { cancelable: true }));
    });

    document.querySelectorAll('.modal-close').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('[id^="modal-"]').forEach(function(m) { m.classList.add('hidden'); });
        });
    });

    document.querySelectorAll('.drive-action-rename').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var row = btn.closest('[data-item-id]');
            if (!row) return;
            document.getElementById('rename-item-id').value = row.dataset.itemId;
            document.getElementById('rename-name').value = row.dataset.itemName || '';
            document.getElementById('modal-rename').classList.remove('hidden');
            document.getElementById('rename-name').focus();
        });
    });

    document.querySelectorAll('.drive-action-delete').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var row = btn.closest('[data-item-id]');
            if (!row) return;
            document.getElementById('delete-item-id').value = row.dataset.itemId;
            document.getElementById('delete-item-name').textContent = row.dataset.itemName || 'este item';
            document.getElementById('modal-delete').classList.remove('hidden');
        });
    });

    var shareSearchTimer;
    document.getElementById('share-search')?.addEventListener('input', function() {
        var q = this.value.trim();
        clearTimeout(shareSearchTimer);
        var resultsEl = document.getElementById('share-results');
        if (q.length < 2) {
            resultsEl.classList.add('hidden');
            return;
        }
        shareSearchTimer = setTimeout(function() {
            fetch(baseUrl + '/search-users?q=' + encodeURIComponent(q))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    resultsEl.innerHTML = '';
                    (data.users || []).forEach(function(u) {
                        var li = document.createElement('div');
                        li.className = 'px-3 py-2 hover:bg-gray-100 cursor-pointer flex justify-between items-center';
                        li.textContent = u.label || u.nome;
                        li.dataset.id = u.id;
                        li.dataset.type = u.tipo;
                        li.dataset.name = u.nome;
                        li.addEventListener('click', function() {
                            addShare(u.id, u.tipo, u.nome);
                            resultsEl.classList.add('hidden');
                            document.getElementById('share-search').value = '';
                        });
                        resultsEl.appendChild(li);
                    });
                    resultsEl.classList.toggle('hidden', !(data.users && data.users.length));
                });
        }, 300);
    });

    function addShare(uid, utype, name) {
        var itemId = document.getElementById('share-item-id').value;
        if (!itemId) return;
        var form = document.createElement('form');
        form.method = 'POST';
        form.action = baseUrl + '/share';
        form.innerHTML = '<input type="hidden" name="_token" value="' + csrf + '"><input type="hidden" name="item_id" value="' + itemId + '"><input type="hidden" name="shared_with_id" value="' + uid + '"><input type="hidden" name="shared_with_type" value="' + utype + '"><input type="hidden" name="permission" value="view">';
        document.body.appendChild(form);
        form.submit();
    }

    document.querySelectorAll('.drive-action-share').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            var row = btn.closest('[data-item-id]');
            if (!row) return;
            document.getElementById('share-item-id').value = row.dataset.itemId;
            document.getElementById('share-list').innerHTML = '';
            document.getElementById('modal-share').classList.remove('hidden');
            fetch(baseUrl + '/share-list/' + row.dataset.itemId)
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.shares && data.shares.length) {
                        data.shares.forEach(function(s) {
                            var li = document.createElement('li');
                            li.className = 'flex items-center justify-between text-sm';
                            li.innerHTML = '<span>' + (s.nome || '') + '</span><form method="post" action="' + baseUrl + '/unshare" class="inline"><input type="hidden" name="_token" value="' + csrf + '"><input type="hidden" name="item_id" value="' + s.item_id + '"><input type="hidden" name="shared_with_id" value="' + s.shared_with_id + '"><input type="hidden" name="shared_with_type" value="' + s.shared_with_type_label + '"><button type="submit" class="text-red-600 hover:underline">Remover</button></form>';
                            document.getElementById('share-list').appendChild(li);
                        });
                    }
                })
                .catch(function() {});
        });
    });
})();
</script>
