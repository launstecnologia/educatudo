<?php
$isEdit = !empty($item['id']);
$tituloPagina = $isEdit ? 'Editar arquivo' : 'Novo arquivo';
$turmaIdsSelecionadas = array_map('intval', $turma_ids ?? []);
?>
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
.quill-descricao-wrap .ql-toolbar.ql-snow { border: 1px solid #d1d5db; border-bottom: none; border-radius: 8px 8px 0 0; background: #f9fafb; }
.quill-descricao-wrap .ql-container.ql-snow { border: 1px solid #d1d5db; border-radius: 0 0 8px 8px; min-height: 140px; }
</style>
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>

<div class="max-w-3xl mx-auto space-y-6">
    <?php if (!empty($flash['message'])): ?>
        <div class="rounded-lg px-4 py-3 <?= ($flash['type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
            <?= htmlspecialchars($flash['message']) ?>
        </div>
    <?php endif; ?>

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900"><?= $tituloPagina ?></h2>
            <p class="text-sm text-gray-600 mt-1"><?= $isEdit ? 'Altere os dados do arquivo publicado.' : 'Preencha os dados e envie o arquivo para as turmas selecionadas.' ?></p>
        </div>
        <a href="<?= URL ?>/admin/arquivos" class="text-gray-600 hover:text-gray-900 text-sm font-medium">← Voltar para listagem</a>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <form method="post" action="<?= $isEdit ? URL . '/admin/arquivos/editar' : URL . '/admin/arquivos/upload' ?>" enctype="multipart/form-data" class="space-y-5" id="form-arquivo-admin">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <?php if ($isEdit): ?>
                <input type="hidden" name="id" value="<?= (int)$item['id'] ?>">
            <?php endif; ?>

            <div>
                <label for="titulo" class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                <input type="text" id="titulo" name="titulo" required maxlength="180" value="<?= htmlspecialchars((string)($item['titulo'] ?? '')) ?>" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2.5" placeholder="Ex.: Lista de exercícios - Física">
            </div>

            <div>
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="recuperacao" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !empty($item['recuperacao']) ? 'checked' : '' ?>>
                    <span class="text-sm font-medium text-gray-700">Recuperação</span>
                </label>
                <p class="text-xs text-gray-500 mt-1">Se marcado, o aluno verá este material no menu Recuperação (não em Arquivos).</p>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                <div id="descricao-quill-wrap" class="quill-descricao-wrap bg-white rounded-lg">
                    <div id="descricao-quill-toolbar">
                        <span class="ql-formats"><button type="button" class="ql-bold"></button><button type="button" class="ql-italic"></button><button type="button" class="ql-underline"></button></span>
                        <span class="ql-formats"><button type="button" class="ql-list" value="ordered"></button><button type="button" class="ql-list" value="bullet"></button></span>
                        <span class="ql-formats"><button type="button" class="ql-link"></button></span>
                    </div>
                    <div id="descricao-quill-editor"></div>
                </div>
                <input type="hidden" name="descricao" id="descricao-hidden" value="<?= htmlspecialchars((string)($item['descricao'] ?? '')) ?>">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
                    <select id="materia_id" name="materia_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2.5">
                        <option value="">Todas/Não informada</option>
                        <?php foreach (($materias ?? []) as $materia): ?>
                            <option value="<?= (int)$materia['id'] ?>" <?= ((int)($item['materia_id'] ?? 0) === (int)$materia['id']) ? 'selected' : '' ?>><?= htmlspecialchars($materia['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="professor_id" class="block text-sm font-medium text-gray-700 mb-1">Professor (opcional)</label>
                    <select id="professor_id" name="professor_id" class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2.5">
                        <option value="">Não vincular professor</option>
                        <?php foreach (($professores ?? []) as $prof): ?>
                            <option value="<?= (int)$prof['id'] ?>" <?= ((int)($item['professor_id'] ?? 0) === (int)$prof['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($prof['nome']) ?><?= !empty($prof['email']) ? ' (' . htmlspecialchars($prof['email']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div>
                <span class="block text-sm font-medium text-gray-700 mb-2">Turmas que podem visualizar <span class="text-red-500">*</span></span>
                <div class="max-h-48 overflow-auto border border-gray-300 rounded-lg p-3 space-y-1.5 bg-gray-50">
                    <?php foreach (($turmas ?? []) as $turma): ?>
                        <?php $checked = in_array((int)$turma['id'], $turmaIdsSelecionadas, true); ?>
                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer hover:bg-gray-100 rounded px-2 py-1">
                            <input type="checkbox" name="turma_ids[]" value="<?= (int)$turma['id'] ?>" <?= $checked ? 'checked' : '' ?> class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span><?= htmlspecialchars($turma['nome']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <?php if ($isEdit && !empty($anexos)): ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Arquivo anexado</label>
                    <ul class="space-y-2">
                        <?php foreach ($anexos as $anexo): ?>
                            <li class="flex items-center justify-between gap-3 py-2 px-3 bg-gray-50 border border-gray-200 rounded-lg text-sm">
                                <span class="truncate text-gray-700" title="<?= htmlspecialchars((string)($anexo['nome_original'] ?? '')) ?>">
                                    <?= htmlspecialchars((string)($anexo['nome_original'] ?? 'Arquivo')) ?>
                                    <?php if (!empty($anexo['tamanho'])): ?>
                                        <span class="text-gray-400">(<?= number_format(((int)$anexo['tamanho']) / 1024, 1, ',', '.') ?> KB)</span>
                                    <?php endif; ?>
                                </span>
                                <a href="<?= URL ?>/admin/arquivos/baixar/<?= (int)$anexo['id'] ?>" class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-emerald-700 bg-emerald-50 rounded-lg hover:bg-emerald-100">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v2a2 2 0 002 2h12a2 2 0 002-2v-2M7 10l5 5m0 0l5-5m-5 5V4"></path></svg>
                                    Baixar
                                </a>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p class="text-xs text-gray-500 mt-1">Para trocar o arquivo, exclua esta publicação e crie uma nova.</p>
                </div>
            <?php else: ?>
                <div>
                    <label for="arquivo" class="block text-sm font-medium text-gray-700 mb-1">Arquivo <span class="text-red-500">*</span></label>
                    <input type="file" id="arquivo" name="arquivo" required class="block w-full text-sm text-gray-700 border border-gray-300 rounded-lg p-2.5">
                    <p class="text-xs text-gray-500 mt-1">PDF, imagens, vídeo ou áudio.</p>
                </div>
            <?php endif; ?>

            <div class="flex gap-3 pt-2">
                <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg text-sm font-medium hover:opacity-90">
                    <?= $isEdit ? 'Salvar alterações' : 'Enviar arquivo' ?>
                </button>
                <a href="<?= URL ?>/admin/arquivos" class="px-5 py-2.5 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 text-sm font-medium">Cancelar</a>
            </div>
        </form>
    </div>
</div>

<script>
(function() {
    var descricaoHidden = document.getElementById('descricao-hidden');
    var quillDesc = null;
    if (typeof Quill !== 'undefined' && descricaoHidden && document.getElementById('descricao-quill-editor')) {
        quillDesc = new Quill('#descricao-quill-editor', {
            theme: 'snow',
            modules: { toolbar: '#descricao-quill-toolbar' },
            placeholder: 'Descreva o conteúdo do arquivo...'
        });
        var initialHtml = (descricaoHidden.value || '').trim();
        if (initialHtml) quillDesc.root.innerHTML = initialHtml;
        quillDesc.on('text-change', function() {
            descricaoHidden.value = quillDesc.root.innerHTML;
        });
    }
    var form = document.getElementById('form-arquivo-admin');
    if (form) {
        form.addEventListener('submit', function() {
            if (quillDesc) descricaoHidden.value = quillDesc.root.innerHTML;
        });
    }
})();
</script>
