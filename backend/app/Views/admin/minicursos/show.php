<?php
$minicurso = $minicurso ?? [];
$modulos = $modulos ?? [];
$csrf_token = $csrf_token ?? '';
$id = (int)($minicurso['id'] ?? 0);
$tiposAula = ['video' => 'Vídeo', 'slides' => 'Slides', 'pdf' => 'PDF', 'link' => 'Link', 'texto' => 'Texto (editor)'];
?>
<div class="mb-6">
    <a href="<?= URL ?>/admin/minicursos" class="text-indigo-600 hover:underline text-sm">← Minicursos</a>
    <a href="<?= URL ?>/admin/minicursos/editar/<?= $id ?>" class="text-indigo-600 hover:underline text-sm ml-3">Editar minicurso</a>
    <h1 class="text-2xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($minicurso['titulo']) ?></h1>
    <?php if (!empty($minicurso['descricao'])): ?>
        <p class="text-gray-600 mt-1"><?= nl2br(htmlspecialchars($minicurso['descricao'])) ?></p>
    <?php endif; ?>
</div>

<?php if (!empty($_SESSION['flash_message'])): ?>
    <div class="mb-4 p-4 rounded-lg <?= ($_SESSION['flash_type'] ?? '') === 'error' ? 'bg-red-100 text-red-800' : 'bg-green-100 text-green-800' ?>">
        <?= htmlspecialchars($_SESSION['flash_message']) ?>
    </div>
    <?php unset($_SESSION['flash_message'], $_SESSION['flash_type']); ?>
<?php endif; ?>

<!-- Adicionar Módulo -->
<div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-6">
    <h2 class="text-lg font-semibold text-gray-900 mb-4">+ Adicionar Módulo</h2>
    <form method="post" action="<?= URL ?>/admin/minicursos/modulo/salvar" class="flex flex-wrap gap-4 items-end">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="minicurso_id" value="<?= $id ?>">
        <div class="flex-1 min-w-[200px]">
            <label class="block text-sm font-medium text-gray-700 mb-1">Título do módulo <span class="text-red-500">*</span></label>
            <input type="text" name="titulo" required class="w-full border border-gray-300 rounded-lg px-4 py-2" placeholder="Ex: Módulo 1 - Introdução">
        </div>
        <div class="w-24">
            <label class="block text-sm font-medium text-gray-700 mb-1">Ordem</label>
            <input type="number" name="ordem" value="0" min="0" class="w-full border border-gray-300 rounded-lg px-2 py-2">
        </div>
        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Adicionar</button>
    </form>
</div>

<!-- Lista de Módulos e Aulas -->
<?php if (empty($modulos)): ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 p-8 text-center text-gray-500">
        Nenhum módulo ainda. Adicione um módulo acima e depois adicione aulas a cada módulo.
    </div>
<?php else: ?>
    <?php foreach ($modulos as $mod): ?>
        <div class="bg-white rounded-xl shadow border border-gray-200 mb-6 overflow-hidden">
            <div class="px-6 py-4 bg-gray-50 border-b border-gray-200 flex justify-between items-center flex-wrap gap-2">
                <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($mod['titulo']) ?></h3>
                <div class="flex gap-2">
                    <button type="button" onclick="document.getElementById('form-aula-<?= $mod['id'] ?>').classList.toggle('hidden')" class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded hover:bg-indigo-700">+ Aula</button>
                    <form method="post" action="<?= URL ?>/admin/minicursos/modulo/excluir" class="inline" onsubmit="return confirm('Excluir este módulo e todas as aulas?');">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="id" value="<?= (int)$mod['id'] ?>">
                        <button type="submit" class="text-sm text-red-600 hover:underline">Excluir módulo</button>
                    </form>
                </div>
            </div>
            <!-- Form add aula (collapsible) -->
            <div id="form-aula-<?= $mod['id'] ?>" class="hidden p-6 border-b border-gray-200 bg-gray-50">
                <form method="post" action="<?= URL ?>/admin/minicursos/aula/salvar" enctype="multipart/form-data" class="space-y-3 form-aula-nova" data-modulo-id="<?= (int)$mod['id'] ?>">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="modulo_id" value="<?= (int)$mod['id'] ?>">
                    <input type="hidden" name="minicurso_id" value="<?= $id ?>">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Título da aula <span class="text-red-500">*</span></label>
                            <input type="text" name="titulo" required class="w-full border border-gray-300 rounded-lg px-3 py-2">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                            <select name="tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2 aula-tipo-select">
                                <?php foreach ($tiposAula as $k => $v): ?>
                                    <option value="<?= $k ?>"><?= $v ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="aula-url-campo">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Documentos que podem anexar</label>
                        <p class="text-xs text-gray-500 mb-1">URL ou envie um arquivo (PDF, vídeo, slides, etc.):</p>
                        <input type="text" name="url_ou_caminho" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-2" placeholder="https://... ou /public/uploads/...">
                        <input type="file" name="arquivo_aula" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.webm,.mp3,.wav,.jpg,.jpeg,.png,.gif,.webp" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                        <p class="text-xs text-gray-500 mt-1 mb-1">Para tipo <strong>Link</strong>: texto do botão que o aluno verá (ex: Abrir material, Acessar Google Drive)</p>
                        <input type="text" name="link_nome" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Ex: Abrir material">
                    </div>
                    <div class="aula-texto-campo">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Editor de texto – conteúdo da aula</label>
                        <p class="text-xs text-gray-500 mb-1">O aluno verá este conteúdo com a mesma formatação. Use quando o tipo for "Texto (editor)" ou como complemento.</p>
                        <textarea name="conteudo_html" id="conteudo_html_new_<?= (int)$mod['id'] ?>" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 editor-wysiwyg-aula" placeholder="Digite o conteúdo da aula..."></textarea>
                    </div>
                    <div class="flex gap-3">
                        <input type="number" name="duracao_minutos" placeholder="Duração (min)" min="0" class="w-32 border border-gray-300 rounded-lg px-3 py-2">
                        <input type="number" name="ordem" value="0" min="0" placeholder="Ordem" class="w-24 border border-gray-300 rounded-lg px-3 py-2">
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Adicionar aula</button>
                    </div>
                </form>
            </div>
            <!-- Aulas do módulo -->
            <ul class="divide-y divide-gray-200">
                <?php if (empty($mod['aulas'])): ?>
                    <li class="px-6 py-4 text-sm text-gray-500">Nenhuma aula. Clique em "+ Aula" para adicionar.</li>
                <?php else: ?>
                    <?php foreach ($mod['aulas'] as $aula): ?>
                        <li class="px-6 py-3">
                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="font-medium text-gray-900"><?= htmlspecialchars($aula['titulo']) ?></span>
                                    <span class="ml-2 text-xs px-2 py-0.5 rounded bg-gray-100 text-gray-600"><?= $tiposAula[$aula['tipo']] ?? $aula['tipo'] ?></span>
                                    <?php if (!empty($aula['duracao_minutos'])): ?>
                                        <span class="text-xs text-gray-500 ml-1"><?= (int)$aula['duracao_minutos'] ?> min</span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex gap-2">
                                    <button type="button" onclick="document.getElementById('edit-aula-<?= (int)$aula['id'] ?>').classList.toggle('hidden')" class="text-sm text-indigo-600 hover:underline">Editar</button>
                                    <form method="post" action="<?= URL ?>/admin/minicursos/aula/excluir" class="inline" onsubmit="return confirm('Excluir esta aula?');">
                                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                        <input type="hidden" name="id" value="<?= (int)$aula['id'] ?>">
                                        <input type="hidden" name="minicurso_id" value="<?= $id ?>">
                                        <button type="submit" class="text-sm text-red-600 hover:underline">Excluir</button>
                                    </form>
                                </div>
                            </div>
                            <div id="edit-aula-<?= (int)$aula['id'] ?>" class="hidden mt-3 p-4 bg-gray-50 rounded-lg border border-gray-200">
                                <form method="post" action="<?= URL ?>/admin/minicursos/aula/atualizar" enctype="multipart/form-data" class="space-y-3 form-aula-editar" data-aula-id="<?= (int)$aula['id'] ?>">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$aula['id'] ?>">
                                    <input type="hidden" name="minicurso_id" value="<?= $id ?>">
                                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Título <span class="text-red-500">*</span></label>
                                            <input type="text" name="titulo" required value="<?= htmlspecialchars($aula['titulo']) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                                        </div>
                                        <div>
                                            <label class="block text-sm font-medium text-gray-700 mb-1">Tipo</label>
                                            <select name="tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2 aula-tipo-select-edit">
                                                <?php foreach ($tiposAula as $k => $v): ?>
                                                    <option value="<?= $k ?>" <?= ($aula['tipo'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="aula-url-campo-edit <?= ($aula['tipo'] ?? '') === 'texto' ? 'hidden' : '' ?>">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Documentos que podem anexar</label>
                                        <p class="text-xs text-gray-500 mb-1">URL ou envie um novo arquivo para substituir (PDF, vídeo, slides, etc.):</p>
                                        <input type="text" name="url_ou_caminho" value="<?= htmlspecialchars($aula['url_ou_caminho'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2 mb-2">
                                        <input type="file" name="arquivo_aula" accept=".pdf,.doc,.docx,.ppt,.pptx,.mp4,.webm,.mp3,.wav,.jpg,.jpeg,.png,.gif,.webp" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                                        <p class="text-xs text-gray-500 mt-1 mb-1">Para tipo <strong>Link</strong>: texto do botão (ex: Abrir material)</p>
                                        <input type="text" name="link_nome" value="<?= htmlspecialchars($aula['link_nome'] ?? '') ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Ex: Abrir material">
                                    </div>
                                    <div class="aula-texto-campo-edit">
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Editor de texto – conteúdo da aula</label>
                                        <p class="text-xs text-gray-500 mb-1">O aluno verá este conteúdo com a mesma formatação.</p>
                                        <textarea name="conteudo_html" id="conteudo_html_edit_<?= (int)$aula['id'] ?>" rows="8" class="w-full border border-gray-300 rounded-lg px-3 py-2 editor-wysiwyg-aula-edit"><?= htmlspecialchars($aula['conteudo_html'] ?? '') ?></textarea>
                                    </div>
                                    <div class="flex gap-3">
                                        <input type="number" name="duracao_minutos" value="<?= (int)($aula['duracao_minutos'] ?? 0) ?>" min="0" placeholder="Duração (min)" class="w-32 border border-gray-300 rounded-lg px-3 py-2">
                                        <input type="number" name="ordem" value="<?= (int)($aula['ordem'] ?? 0) ?>" min="0" placeholder="Ordem" class="w-24 border border-gray-300 rounded-lg px-3 py-2">
                                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Salvar</button>
                                    </div>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                <?php endif; ?>
            </ul>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/classic/ckeditor.js"></script>
<style>.ck-editor__editable { min-height: 200px !important; }</style>
<script>
(function() {
    var editoresAula = {};
    function initEditor(id) {
        if (editoresAula[id] || !document.getElementById(id)) return;
        var el = document.getElementById(id);
        if (el.getAttribute('data-ck-inited')) return;
        ClassicEditor.create(el, {
            toolbar: ['heading', '|', 'bold', 'italic', 'underline', '|', 'link', 'bulletedList', 'numberedList', '|', 'undo', 'redo'],
            language: 'pt-br'
        }).then(function(editor) {
            editoresAula[id] = editor;
            el.setAttribute('data-ck-inited', '1');
        }).catch(function(err) { console.error(err); });
    }
    function initEditorNoForm(container) {
        var form = container && container.tagName === 'FORM' ? container : (container && container.querySelector ? container.querySelector('form') : null);
        if (!form) form = container;
        var ta = form ? form.querySelector('textarea.editor-wysiwyg-aula, textarea.editor-wysiwyg-aula-edit') : null;
        if (ta && ta.id) {
            setTimeout(function() { initEditor(ta.id); }, 200);
        }
    }
    function toggleTipoAula(form, isTexto) {
        var urlCampo = form.querySelector('.aula-url-campo, .aula-url-campo-edit');
        if (urlCampo) urlCampo.classList.toggle('hidden', isTexto);
        var urlInput = form.querySelector('input[name="url_ou_caminho"]');
        if (urlInput) urlInput.required = !isTexto;
    }
    document.querySelectorAll('.aula-tipo-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            toggleTipoAula(sel.closest('form'), sel.value === 'texto');
        });
    });
    document.querySelectorAll('.aula-tipo-select-edit').forEach(function(sel) {
        sel.addEventListener('change', function() {
            toggleTipoAula(sel.closest('form'), sel.value === 'texto');
        });
    });
    document.querySelectorAll('button[onclick*="form-aula-"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var m = btn.getAttribute('onclick').match(/form-aula-(\d+)/);
            if (m) {
                var div = document.getElementById('form-aula-' + m[1]);
                if (div) setTimeout(function() { initEditorNoForm(div); }, 300);
            }
        });
    });
    document.querySelectorAll('button[onclick*="edit-aula-"]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var m = btn.getAttribute('onclick').match(/edit-aula-(\d+)/);
            if (m) {
                var div = document.getElementById('edit-aula-' + m[1]);
                if (div) setTimeout(function() {
                    if (!div.classList.contains('hidden')) initEditorNoForm(div);
                }, 300);
            }
        });
    });
    // Inicializar editor em formulários já visíveis (ex.: após F5 com painel aberto)
    setTimeout(function() {
        document.querySelectorAll('[id^="form-aula-"]:not(.hidden), [id^="edit-aula-"]:not(.hidden)').forEach(function(el) {
            initEditorNoForm(el);
        });
    }, 500);
})();
</script>
