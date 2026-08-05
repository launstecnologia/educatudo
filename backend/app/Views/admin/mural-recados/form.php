<?php
$base = $base_url ?? (URL . '/admin/mural-recados');
$isEdit = !empty($recado['id']);
$titulo = $isEdit ? 'Editar Recado' : 'Novo Recado';
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= $titulo ?></h2>
            <p class="text-gray-600">Título, conteúdo e destinatários (turmas ou todos os alunos).</p>
        </div>
        <a href="<?= $base ?>" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<form action="<?= $isEdit ? $base . '/atualizar' : $base . '/salvar' ?>" method="post" class="space-y-6 bg-white rounded-xl shadow-lg p-6">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int)$recado['id'] ?>"><?php endif; ?>

    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
        <input type="text" name="titulo" required maxlength="255" value="<?= htmlspecialchars($recado['titulo'] ?? '') ?>"
               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-1">Conteúdo</label>
        <textarea name="conteudo" id="conteudo" class="hidden"><?= htmlspecialchars($recado['conteudo'] ?? '') ?></textarea>
        <div id="editor-conteudo-mural" class="quill-editor-wrapper border border-gray-300 rounded-lg overflow-hidden bg-white" style="min-height: 200px;"></div>
    </div>
    <div>
        <label class="block text-sm font-medium text-gray-700 mb-2">Destinatários *</label>
        <label class="inline-flex items-center mr-6">
            <input type="radio" name="enviar_para_todos" value="1" <?= (!empty($recado['enviar_para_todos']) || !$isEdit && empty($recado)) ? 'checked' : '' ?> class="mr-2">
            Enviar para todos
        </label>
        <label class="inline-flex items-center">
            <input type="radio" name="enviar_para_todos" value="0" <?= $isEdit && empty($recado['enviar_para_todos']) ? 'checked' : '' ?> class="mr-2" id="radio_turmas">
            Selecionar turmas
        </label>
        <div id="turmas_container" class="mt-3 flex flex-wrap gap-3 <?= (!$isEdit || !empty($recado['enviar_para_todos'])) ? 'hidden' : '' ?>">
            <?php foreach ($turmas_opcoes as $t): ?>
            <label class="inline-flex items-center">
                <input type="checkbox" name="turmas[]" value="<?= (int)$t['id'] ?>" class="mr-2"
                    <?= $isEdit && in_array($t['id'], $recado['turmas_ids'] ?? []) ? 'checked' : '' ?>>
                <?= htmlspecialchars($t['nome']) ?>
            </label>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="flex gap-3">
        <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg hover:opacity-90"><?= $isEdit ? 'Atualizar' : 'Publicar' ?></button>
        <a href="<?= $base ?>" class="bg-gray-200 text-gray-800 px-6 py-2 rounded-lg hover:bg-gray-300">Cancelar</a>
    </div>
</form>

<link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
<script src="https://cdn.quilljs.com/1.3.6/quill.js"></script>
<style>
.quill-editor-wrapper .ql-container { font-size: 1rem; }
.quill-editor-wrapper .ql-editor { min-height: 180px; }
.quill-editor-wrapper .ql-toolbar.ql-snow { border: none; border-bottom: 1px solid #e5e7eb; background: #f9fafb; }
.quill-editor-wrapper .ql-container.ql-snow { border: none; }
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var rTodos = document.querySelector('input[name="enviar_para_todos"][value="1"]');
    var rTurmas = document.getElementById('radio_turmas');
    var container = document.getElementById('turmas_container');
    function toggle() { container.classList.toggle('hidden', rTodos && rTodos.checked); }
    if (rTodos) rTodos.addEventListener('change', toggle);
    if (rTurmas) rTurmas.addEventListener('change', toggle);

    var conteudoEl = document.getElementById('conteudo');
    var quillMural = new Quill('#editor-conteudo-mural', {
        theme: 'snow',
        placeholder: 'Digite o conteúdo do recado...',
        modules: { toolbar: [['bold', 'italic', 'underline'], [{ 'list': 'ordered'}, { 'list': 'bullet' }], ['link'], ['clean']] }
    });
    if (conteudoEl && conteudoEl.value) quillMural.root.innerHTML = conteudoEl.value;
    quillMural.on('text-change', function() {
        if (conteudoEl) conteudoEl.value = quillMural.root.innerHTML;
    });
    document.querySelector('form').addEventListener('submit', function() {
        if (conteudoEl) conteudoEl.value = quillMural.root.innerHTML;
    });
});
</script>
