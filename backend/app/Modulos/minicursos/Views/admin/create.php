<?php $csrf_token = $csrf_token ?? ''; ?>
<div class="mb-6">
    <a href="<?= URL ?>/admin/minicursos" class="text-indigo-600 hover:underline text-sm">← Voltar</a>
    <h1 class="text-2xl font-bold text-gray-900 mt-2">Novo Minicurso</h1>
</div>
<form method="post" action="<?= URL ?>/admin/minicursos/salvar" enctype="multipart/form-data" class="bg-white rounded-xl shadow border border-gray-200 p-6 space-y-4 max-w-2xl">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
        <input type="text" name="titulo" required class="w-full border border-gray-300 rounded-lg px-4 py-2" placeholder="Ex: Introdução à Programação">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
        <textarea name="descricao" rows="3" class="w-full border border-gray-300 rounded-lg px-4 py-2" placeholder="Breve descrição do minicurso"></textarea>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Capa do minicurso</label>
        <p class="text-xs text-gray-500 mb-1">Upload de imagem ou URL (opcional). Se enviar arquivo, a URL é ignorada.</p>
        <div class="space-y-2">
            <div>
                <label class="text-xs text-gray-600">Upload da capa</label>
                <input type="file" name="imagem_capa" accept=".jpg,.jpeg,.png,.gif,.webp" class="w-full border border-gray-300 rounded-lg px-4 py-2 text-sm">
            </div>
            <div>
                <label class="text-xs text-gray-600">ou URL da imagem</label>
                <input type="text" name="imagem_url" class="w-full border border-gray-300 rounded-lg px-4 py-2" placeholder="https://...">
            </div>
        </div>
    </div>
    <div id="arquivos-container">
        <label class="block text-sm font-medium text-gray-700 mb-1">Arquivos do minicurso (opcional)</label>
        <p class="text-xs text-gray-500 mb-2">Adicione links ou envie arquivos para os alunos acessarem.</p>
        <div id="arquivos-rows" class="space-y-3"></div>
        <button type="button" id="btn-add-arquivo" class="mt-2 text-sm text-indigo-600 hover:underline">+ Adicionar arquivo ou link</button>
    </div>
    <div class="flex items-center gap-4">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="ativo" value="1" checked>
            <span class="text-sm text-gray-700">Ativo</span>
        </label>
        <label class="text-sm text-gray-700">Ordem:</label>
        <input type="number" name="ordem" value="0" min="0" class="w-20 border border-gray-300 rounded px-2 py-1">
    </div>
    <div class="flex gap-3">
        <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90">Salvar</button>
        <a href="<?= URL ?>/admin/minicursos" class="bg-gray-200 text-gray-700 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
    </div>
</form>
<script>
(function() {
    var idx = 0;
    var container = document.getElementById('arquivos-rows');
    var btn = document.getElementById('btn-add-arquivo');
    if (!container || !btn) return;
    function addRow() {
        var i = idx++;
        var row = document.createElement('div');
        row.className = 'flex flex-wrap items-end gap-2 p-3 bg-gray-50 rounded-lg border border-gray-200';
        row.innerHTML =
            '<div class="flex-1 min-w-[120px]"><label class="text-xs text-gray-600">Rótulo</label><input type="text" name="arquivos_label[]" placeholder="Ex: Material PDF" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"></div>' +
            '<input type="hidden" name="arquivos_tipo[]" value="link" class="arquivo-tipo-val">' +
            '<div class="flex gap-2 items-end flex-wrap">' +
            '<label class="flex items-center gap-1 text-sm"><input type="radio" value="link" checked class="arquivo-tipo-radio"> Link</label>' +
            '<label class="flex items-center gap-1 text-sm"><input type="radio" value="upload" class="arquivo-tipo-radio"> Upload</label>' +
            '</div>' +
            '<div class="arquivo-link flex-1 min-w-[200px]"><label class="text-xs text-gray-600">URL</label><input type="url" name="arquivos_url[]" placeholder="https://..." class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"></div>' +
            '<div class="arquivo-upload flex-1 min-w-[200px] hidden"><label class="text-xs text-gray-600">Arquivo</label><input type="file" name="arquivos_file_' + i + '" class="w-full border border-gray-300 rounded px-2 py-1.5 text-sm"></div>' +
            '<button type="button" class="remove-arquivo text-red-600 hover:underline text-sm">Remover</button>';
        row.querySelector('.remove-arquivo').onclick = function() { row.remove(); };
        row.querySelectorAll('.arquivo-tipo-radio').forEach(function(radio) {
            radio.onchange = function() {
                var val = row.querySelector('.arquivo-tipo-radio:checked').value;
                row.querySelector('.arquivo-tipo-val').value = val;
                var isLink = val === 'link';
                row.querySelector('.arquivo-link').classList.toggle('hidden', !isLink);
                row.querySelector('.arquivo-upload').classList.toggle('hidden', isLink);
            };
        });
        container.appendChild(row);
    }
    btn.addEventListener('click', addRow);
})();
</script>
