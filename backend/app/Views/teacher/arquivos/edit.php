<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
.quill-descricao-wrap .ql-toolbar.ql-snow { border: 1px solid #d1d5db; border-bottom: none; border-radius: 8px 8px 0 0; background: #f9fafb; }
.quill-descricao-wrap .ql-container.ql-snow { border: 1px solid #d1d5db; border-radius: 0 0 8px 8px; min-height: 120px; }
.anexo-preview-card { max-width: 280px; }
.anexo-preview-card img, .anexo-preview-card video { max-height: 200px; object-fit: contain; }
.anexo-preview-card iframe { width: 100%; height: 280px; border: 1px solid #e5e7eb; border-radius: 8px; }
#arquivos-loading-overlay { display: none; position: fixed; inset: 0; z-index: 9999; background: rgba(255,255,255,0.9); align-items: center; justify-content: center; flex-direction: column; }
#arquivos-loading-overlay.show { display: flex; }
.arquivos-loading-spinner { width: 56px; height: 56px; border: 4px solid #e5e7eb; border-top-color: #4f46e5; border-radius: 50%; animation: arquivos-spin 0.9s linear infinite; }
@keyframes arquivos-spin { to { transform: rotate(360deg); } }
</style>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Editar publicação</h2>
            <p class="text-gray-600">Altere turma, disciplina, título, descrição ou adicione mais anexos.</p>
        </div>
        <a href="<?= URL ?>/professor/arquivos" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Voltar</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg border border-gray-200">
    <form method="post" action="<?= URL ?>/professor/arquivos/atualizar" enctype="multipart/form-data" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">

        <div>
            <label class="flex items-center gap-2 cursor-pointer mb-3">
                <input type="checkbox" id="apenas-aluno-edit" name="apenas_aluno" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !empty($aluno_atual) ? 'checked' : '' ?>>
                <span class="text-sm font-medium text-gray-700">Enviar apenas para um aluno específico</span>
            </label>
            <p class="text-xs text-gray-500 mb-3">Quando marcado, somente o aluno escolhido poderá ver esta publicação.</p>
        </div>

        <div id="bloco-turmas-edit">
            <span class="block text-sm font-medium text-gray-700 mb-2">Turmas <span class="text-red-500">*</span></span>
            <div class="border border-gray-300 rounded-lg p-4 bg-gray-50/50 max-h-48 overflow-y-auto">
                <div class="space-y-2">
                    <?php
                    $sel = array_map('intval', $item_turma_ids ?? ($item['aluno_id'] ? [] : [$item['turma_id']]));
                    foreach ($turmas as $t):
                        $id = (int)$t['id'];
                    ?>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-100 rounded px-2 py-1.5 -mx-2 -my-1.5">
                        <input type="checkbox" name="turma_ids[]" value="<?= $id ?>" <?= in_array($id, $sel, true) ? 'checked' : '' ?> class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 turma-cb-edit">
                        <span class="text-gray-800"><?= htmlspecialchars($t['nome']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="mt-1 text-xs text-gray-500">Marque uma ou mais turmas para esta publicação.</p>
        </div>

        <div id="bloco-aluno-edit" class="<?= !empty($aluno_atual) ? '' : 'hidden' ?>">
            <label for="turma_aluno_edit" class="block text-sm font-medium text-gray-700 mb-1">Turma do aluno <span class="text-red-500">*</span></label>
            <select id="turma_aluno_edit" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 mb-3">
                <option value="">Selecione a turma</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int)$t['id'] ?>" <?= !empty($aluno_atual) && (int)$t['id'] === (int)($aluno_atual['turma_id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="aluno_id_edit" class="block text-sm font-medium text-gray-700 mb-1">Aluno <span class="text-red-500">*</span></label>
            <select id="aluno_id_edit" name="aluno_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                <option value="">Selecione primeiro a turma</option>
                <?php if (!empty($aluno_atual)): ?>
                    <option value="<?= (int)$aluno_atual['id'] ?>" selected><?= htmlspecialchars($aluno_atual['nome']) ?></option>
                <?php endif; ?>
            </select>
        </div>

        <div>
            <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-1">Disciplina <span class="text-red-500">*</span></label>
            <select id="materia_id" name="materia_id" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                <?php foreach ($materias as $m): ?>
                    <option value="<?= (int)$m['id'] ?>" <?= (int)$m['id'] === (int)$item['materia_id'] ? 'selected' : '' ?>><?= htmlspecialchars($m['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
            <input type="text" id="titulo" name="titulo" required maxlength="255" value="<?= htmlspecialchars($item['titulo']) ?>" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
        </div>

        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="recuperacao" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !empty($item['recuperacao']) ? 'checked' : '' ?>>
                <span class="text-sm font-medium text-gray-700">Recuperação</span>
            </label>
            <p class="text-xs text-gray-500 mt-1">Se marcado, o aluno verá este material no menu Recuperação (não em Arquivos).</p>
        </div>

        <div>
            <label for="descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
            <div id="descricao-quill-wrap" class="quill-descricao-wrap bg-white rounded-lg">
                <div id="descricao-quill-toolbar">
                    <span class="ql-formats"><button type="button" class="ql-bold"></button><button type="button" class="ql-italic"></button><button type="button" class="ql-underline"></button></span>
                    <span class="ql-formats"><button type="button" class="ql-list" value="ordered"></button><button type="button" class="ql-list" value="bullet"></button></span>
                    <span class="ql-formats"><button type="button" class="ql-link"></button></span>
                </div>
                <div id="descricao-quill-editor" style="min-height: 120px;"></div>
            </div>
            <input type="hidden" name="descricao" id="descricao-hidden" value="<?= htmlspecialchars($item['descricao'] ?? '') ?>">
            <p class="mt-1 text-xs text-gray-500">Formatação rica para os alunos.</p>
        </div>

        <?php if (!empty($anexos)): ?>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Anexos atuais</label>
                <ul id="lista-anexos-atuais" class="space-y-1.5">
                    <?php foreach ($anexos as $a): ?>
                        <li class="anexo-row flex items-center justify-between gap-2 py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm">
                            <input type="hidden" name="anexos_manter[]" value="<?= (int)$a['id'] ?>">
                            <span class="truncate text-gray-700" title="<?= htmlspecialchars($a['nome_original']) ?>"><?= htmlspecialchars($a['nome_original']) ?></span>
                            <button type="button" class="btn-excluir-anexo text-red-600 hover:text-red-800 hover:underline shrink-0">Excluir</button>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Adicionar mais anexos</label>
            <div id="anexos-preview-edit" class="mb-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
            <input type="file" id="input-anexos-edit" name="anexos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.bmp,.mp4,.webm,.ogg,.mp3,.wav" class="w-full border border-gray-300 rounded-lg px-4 py-2">
            <p class="mt-1 text-xs text-gray-500">Permitido: PDF, Word, Excel, PowerPoint, texto, imagens, vídeo e áudio.</p>
        </div>

        <div>
            <label for="video_urls" class="block text-sm font-medium text-gray-700 mb-1">Links de vídeo (opcional)</label>
            <textarea id="video_urls" name="video_urls" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500" placeholder="Um link por linha. Ex: https://www.youtube.com/watch?v=... ou https://vimeo.com/..."><?php
                if (!empty($videos)) {
                    echo htmlspecialchars(implode("\n", array_map(function ($v) { return $v['url']; }, $videos)));
                }
            ?></textarea>
            <p class="mt-1 text-xs text-gray-500">YouTube, Vimeo ou outro player com embed. Os alunos verão o vídeo em player na plataforma.</p>
        </div>

        <div class="flex gap-3">
            <button type="submit" id="btn-atualizar-arquivo" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Atualizar</button>
            <a href="<?= URL ?>/professor/arquivos" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
        </div>
    </form>
</div>
<div id="arquivos-loading-overlay" aria-hidden="true">
    <div class="arquivos-loading-spinner"></div>
    <p class="mt-4 text-gray-700 font-medium">Atualizando... Enviando arquivos.</p>
    <p class="mt-1 text-sm text-gray-500">Aguarde até a página recarregar.</p>
</div>
<script>
(function() {
    // Excluir anexo atual (remove da lista enviada no submit)
    document.querySelectorAll('.btn-excluir-anexo').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var row = btn.closest('.anexo-row');
            if (row) row.remove();
        });
    });    // Pré-visualização dos novos anexos (imagem, vídeo, PDF)
    var input = document.getElementById('input-anexos-edit');
    var preview = document.getElementById('anexos-preview-edit');
    var objectUrlsEdit = [];
    function formatSize(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(1) + ' MB';
    }
    function isImage(file) {
        var t = (file.type || '').toLowerCase();
        return /^image\/(jpeg|png|gif|webp|bmp)$/.test(t) || /\.(jpg|jpeg|png|gif|webp|bmp)$/i.test(file.name || '');
    }
    function isVideo(file) {
        var t = (file.type || '').toLowerCase();
        return /^video\//.test(t) || /\.(mp4|webm|ogg)$/i.test(file.name || '');
    }
    function isPdf(file) {
        return (file.type || '').toLowerCase() === 'application/pdf' || /\.pdf$/i.test(file.name || '');
    }
    function renderList() {
        objectUrlsEdit.forEach(function(u) { try { URL.revokeObjectURL(u); } catch (e) {} });
        objectUrlsEdit = [];
        preview.innerHTML = '';
        var files = input.files;
        for (var i = 0; i < files.length; i++) {
            (function(idx) {
                var file = files[idx];
                var url = URL.createObjectURL(file);
                objectUrlsEdit.push(url);
                var card = document.createElement('div');
                card.className = 'anexo-preview-card border border-gray-200 rounded-lg overflow-hidden bg-gray-50';
                var nome = (file.name || 'Arquivo').replace(/"/g, '&quot;');
                var sizeStr = formatSize(file.size || 0);
                var previewHtml = '';
                if (isImage(file)) {
                    previewHtml = '<div class="p-2"><img src="' + url + '" alt="" class="w-full rounded"></div>';
                } else if (isVideo(file)) {
                    previewHtml = '<div class="p-2"><video src="' + url + '" controls class="w-full rounded"></video></div>';
                } else if (isPdf(file)) {
                    previewHtml = '<div class="p-2"><iframe src="' + url + '" class="w-full rounded" title="PDF"></iframe></div>';
                } else {
                    previewHtml = '<div class="p-2 text-sm text-gray-600 truncate" title="' + nome + '">' + nome + '</div>';
                }
                card.innerHTML = previewHtml +
                    '<div class="px-2 pb-2 pt-0 flex items-center justify-between gap-2 text-xs text-gray-500">' +
                    '<span class="truncate">' + nome + '</span> ' + sizeStr + ' ' +
                    '<button type="button" class="text-red-600 hover:text-red-800 shrink-0">Excluir</button></div>';
                card.querySelector('button').addEventListener('click', function() {
                    var dt = new DataTransfer();
                    for (var j = 0; j < input.files.length; j++) {
                        if (j !== idx) dt.items.add(input.files[j]);
                    }
                    input.files = dt.files;
                    renderList();
                });
                preview.appendChild(card);
            })(i);
        }
    }
    if (input && preview) input.addEventListener('change', renderList);

    // Quill na descrição (edição)
    var descricaoHiddenEdit = document.getElementById('descricao-hidden');
    var quillDescEdit = null;
    if (typeof Quill !== 'undefined' && descricaoHiddenEdit && document.getElementById('descricao-quill-editor')) {
        quillDescEdit = new Quill('#descricao-quill-editor', {
            theme: 'snow',
            modules: { toolbar: '#descricao-quill-toolbar' }
        });
        var initialHtml = (descricaoHiddenEdit.value || '').trim();
        if (initialHtml) quillDescEdit.root.innerHTML = initialHtml;
        quillDescEdit.on('text-change', function() { descricaoHiddenEdit.value = quillDescEdit.root.innerHTML; });
        var formEdit = descricaoHiddenEdit.closest('form');
        if (formEdit) formEdit.addEventListener('submit', function() { descricaoHiddenEdit.value = quillDescEdit.root.innerHTML; });
    }
    var formEdit = document.querySelector('form[action*="arquivos/atualizar"]');
    var overlayEdit = document.getElementById('arquivos-loading-overlay');
    if (formEdit && overlayEdit) {
        formEdit.addEventListener('submit', function(e) {
            if (!formEdit.dataset.submitting) {
                e.preventDefault();
                if (quillDescEdit) descricaoHiddenEdit.value = quillDescEdit.root.innerHTML;
                formEdit.dataset.submitting = '1';
                overlayEdit.classList.add('show');
                setTimeout(function() { formEdit.submit(); }, 80);
            }
        });
    }

    // Enviar apenas para um aluno (igual ao create)
    var apenasAlunoEdit = document.getElementById('apenas-aluno-edit');
    var blocoTurmasEdit = document.getElementById('bloco-turmas-edit');
    var blocoAlunoEdit = document.getElementById('bloco-aluno-edit');
    var turmaAlunoEdit = document.getElementById('turma_aluno_edit');
    var alunoSelectEdit = document.getElementById('aluno_id_edit');
    var baseUrlEdit = '<?= rtrim(URL, "/") ?>';
    if (apenasAlunoEdit) {
        apenasAlunoEdit.addEventListener('change', function() {
            if (this.checked) {
                blocoTurmasEdit.classList.add('hidden');
                blocoAlunoEdit.classList.remove('hidden');
                document.querySelectorAll('.turma-cb-edit').forEach(function(cb) { cb.checked = false; cb.disabled = true; });
                if (alunoSelectEdit) alunoSelectEdit.required = true;
            } else {
                blocoTurmasEdit.classList.remove('hidden');
                blocoAlunoEdit.classList.add('hidden');
                document.querySelectorAll('.turma-cb-edit').forEach(function(cb) { cb.disabled = false; });
                if (alunoSelectEdit) { alunoSelectEdit.required = false; alunoSelectEdit.value = ''; }
            }
        });
    }
    if (turmaAlunoEdit && alunoSelectEdit) {
        turmaAlunoEdit.addEventListener('change', function() {
            var tid = this.value;
            alunoSelectEdit.innerHTML = '<option value="">Carregando...</option>';
            if (!tid) {
                alunoSelectEdit.innerHTML = '<option value="">Selecione primeiro a turma</option>';
                return;
            }
            fetch(baseUrlEdit + '/professor/arquivos/alunos-por-turma?turma_id=' + encodeURIComponent(tid))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    alunoSelectEdit.innerHTML = '<option value="">Selecione o aluno</option>';
                    (data.alunos || []).forEach(function(a) {
                        var opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = a.nome;
                        alunoSelectEdit.appendChild(opt);
                    });
                })
                .catch(function() { alunoSelectEdit.innerHTML = '<option value="">Erro ao carregar</option>'; });
        });
    }
})();
</script>
