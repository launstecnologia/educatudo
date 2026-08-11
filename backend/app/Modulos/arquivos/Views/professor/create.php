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
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Nova publicação de arquivos</h2>
            <p class="text-gray-600">Selecione turma, disciplina, título, descrição e anexe um ou mais arquivos.</p>
        </div>
        <a href="<?= URL ?>/professor/arquivos" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700">Voltar</a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg border border-gray-200">
    <form method="post" action="<?= URL ?>/professor/arquivos/salvar" enctype="multipart/form-data" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div>
            <label class="flex items-center gap-2 cursor-pointer mb-3">
                <input type="checkbox" id="apenas-aluno" name="apenas_aluno" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                <span class="text-sm font-medium text-gray-700">Enviar apenas para um aluno específico</span>
            </label>
            <p class="text-xs text-gray-500 mb-3">Quando marcado, somente o aluno escolhido poderá ver esta publicação.</p>
        </div>

        <div id="bloco-turmas">
            <span class="block text-sm font-medium text-gray-700 mb-2">Turmas <span class="text-red-500">*</span></span>
            <div class="border border-gray-300 rounded-lg p-4 bg-gray-50/50 max-h-48 overflow-y-auto">
                <div class="space-y-2">
                    <?php foreach ($turmas as $t): ?>
                    <label class="flex items-center gap-2 cursor-pointer hover:bg-gray-100 rounded px-2 py-1.5 -mx-2 -my-1.5">
                        <input type="checkbox" name="turma_ids[]" value="<?= (int)$t['id'] ?>" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500 turma-cb">
                        <span class="text-gray-800"><?= htmlspecialchars($t['nome']) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <p class="mt-1 text-xs text-gray-500">Marque uma ou mais turmas para esta publicação.</p>
        </div>

        <div id="bloco-aluno" class="hidden">
            <label for="turma_aluno" class="block text-sm font-medium text-gray-700 mb-1">Turma do aluno <span class="text-red-500">*</span></label>
            <select id="turma_aluno" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 mb-3">
                <option value="">Selecione a turma</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int)$t['id'] ?>"><?= htmlspecialchars($t['nome']) ?></option>
                <?php endforeach; ?>
            </select>
            <label for="aluno_id" class="block text-sm font-medium text-gray-700 mb-1">Aluno <span class="text-red-500">*</span></label>
            <select id="aluno_id" name="aluno_id" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                <option value="">Selecione primeiro a turma</option>
            </select>
        </div>

        <div>
            <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-1">Disciplina <span class="text-red-500">*</span></label>
            <select id="materia_id" name="materia_id" required class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500">
                <option value="">Selecione a disciplina</option>
                <?php foreach ($materias as $m): ?>
                    <option value="<?= (int)$m['id'] ?>"><?= htmlspecialchars($m['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label for="titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
            <input type="text" id="titulo" name="titulo" required maxlength="255" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500" placeholder="Ex: Material da aula 5">
        </div>

        <div>
            <label class="flex items-center gap-2 cursor-pointer">
                <input type="checkbox" name="recuperacao" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
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
            <input type="hidden" name="descricao" id="descricao-hidden">
            <p class="mt-1 text-xs text-gray-500">Texto opcional para os alunos (formatação rica).</p>
        </div>

        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Anexos (múltiplos) <span class="text-red-500">*</span></label>
            <div id="anexos-preview" class="mb-3 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3"></div>
            <input type="file" id="input-anexos" name="anexos[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.csv,.jpg,.jpeg,.png,.gif,.webp,.bmp,.mp4,.webm,.ogg,.mp3,.wav" class="w-full border border-gray-300 rounded-lg px-4 py-2">
            <p class="mt-1 text-xs text-gray-500">Permitido: PDF, Word, Excel, PowerPoint, texto, imagens, vídeo e áudio.</p>
        </div>

        <div>
            <label for="video_urls" class="block text-sm font-medium text-gray-700 mb-1">Links de vídeo (opcional)</label>
            <textarea id="video_urls" name="video_urls" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500" placeholder="Um link por linha. Ex: https://www.youtube.com/watch?v=... ou https://vimeo.com/..."></textarea>
            <p class="mt-1 text-xs text-gray-500">YouTube, Vimeo ou outro player com embed. Os alunos verão o vídeo em player na plataforma.</p>
        </div>

        <div class="flex gap-3">
            <button type="submit" id="btn-salvar-arquivo" class="bg-indigo-600 text-white px-6 py-2 rounded-lg hover:bg-indigo-700">Salvar</button>
            <a href="<?= URL ?>/professor/arquivos" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
        </div>
    </form>
</div>
<div id="arquivos-loading-overlay" aria-hidden="true">
    <div class="arquivos-loading-spinner"></div>
    <p class="mt-4 text-gray-700 font-medium">Salvando... Enviando arquivos.</p>
    <p class="mt-1 text-sm text-gray-500">Aguarde até a página recarregar.</p>
</div>
<script>
(function() {
    var apenasAluno = document.getElementById('apenas-aluno');
    var blocoTurmas = document.getElementById('bloco-turmas');
    var blocoAluno = document.getElementById('bloco-aluno');
    var turmaAluno = document.getElementById('turma_aluno');
    var alunoSelect = document.getElementById('aluno_id');
    var baseUrl = '<?= rtrim(URL, "/") ?>';

    if (apenasAluno) {
        apenasAluno.addEventListener('change', function() {
            if (this.checked) {
                blocoTurmas.classList.add('hidden');
                blocoAluno.classList.remove('hidden');
                document.querySelectorAll('.turma-cb').forEach(function(cb) { cb.checked = false; cb.disabled = true; });
                alunoSelect.required = true;
            } else {
                blocoTurmas.classList.remove('hidden');
                blocoAluno.classList.add('hidden');
                document.querySelectorAll('.turma-cb').forEach(function(cb) { cb.disabled = false; });
                alunoSelect.required = false;
                alunoSelect.value = '';
                alunoSelect.innerHTML = '<option value="">Selecione primeiro a turma</option>';
            }
        });
    }
    if (turmaAluno && alunoSelect) {
        turmaAluno.addEventListener('change', function() {
            var tid = this.value;
            alunoSelect.innerHTML = '<option value="">Carregando...</option>';
            if (!tid) {
                alunoSelect.innerHTML = '<option value="">Selecione primeiro a turma</option>';
                return;
            }
            fetch(baseUrl + '/professor/arquivos/alunos-por-turma?turma_id=' + encodeURIComponent(tid))
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    alunoSelect.innerHTML = '<option value="">Selecione o aluno</option>';
                    (data.alunos || []).forEach(function(a) {
                        var opt = document.createElement('option');
                        opt.value = a.id;
                        opt.textContent = a.nome;
                        alunoSelect.appendChild(opt);
                    });
                })
                .catch(function() {
                    alunoSelect.innerHTML = '<option value="">Erro ao carregar</option>';
                });
        });
    }

    var input = document.getElementById('input-anexos');
    var preview = document.getElementById('anexos-preview');
    var objectUrls = [];
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
        objectUrls.forEach(function(u) { try { URL.revokeObjectURL(u); } catch (e) {} });
        objectUrls = [];
        preview.innerHTML = '';
        var files = input.files;
        for (var i = 0; i < files.length; i++) {
            (function(idx) {
                var file = files[idx];
                var url = URL.createObjectURL(file);
                objectUrls.push(url);
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
                    removeFile(idx);
                });
                preview.appendChild(card);
            })(i);
        }
    }
    function removeFile(indexToRemove) {
        var dt = new DataTransfer();
        for (var i = 0; i < input.files.length; i++) {
            if (i !== indexToRemove) dt.items.add(input.files[i]);
        }
        input.files = dt.files;
        renderList();
    }
    if (input && preview) input.addEventListener('change', renderList);

    // Quill na descrição
    var descricaoHidden = document.getElementById('descricao-hidden');
    var quillDesc = null;
    if (typeof Quill !== 'undefined' && descricaoHidden && document.getElementById('descricao-quill-editor')) {
        quillDesc = new Quill('#descricao-quill-editor', {
            theme: 'snow',
            modules: { toolbar: '#descricao-quill-toolbar' }
        });
        quillDesc.on('text-change', function() { descricaoHidden.value = quillDesc.root.innerHTML; });
    }
    var form = document.querySelector('form[action*="arquivos/salvar"]');
    var overlay = document.getElementById('arquivos-loading-overlay');
    if (form) {
        if (quillDesc) {
            form.addEventListener('submit', function() { descricaoHidden.value = quillDesc.root.innerHTML; });
        }
        form.addEventListener('submit', function(e) {
            if (overlay && !form.dataset.submitting) {
                e.preventDefault();
                if (quillDesc) descricaoHidden.value = quillDesc.root.innerHTML;
                form.dataset.submitting = '1';
                overlay.classList.add('show');
                setTimeout(function() { form.submit(); }, 80);
            }
        });
    }
})();
</script>
