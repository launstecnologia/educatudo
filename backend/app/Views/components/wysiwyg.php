<?php
/**
 * EducaTudo - Componente WYSIWYG reutilizável (CKEditor 5).
 *
 * Padrão único de editor de texto rico do sistema, alinhado ao já usado em
 * Minicursos (CKEditor 5 Classic via CDN). Renderiza um <textarea> estilizado
 * conforme admin_form_ui_sistema.md; o CKEditor assume o controle e sincroniza
 * o conteúdo de volta para o textarea automaticamente no submit (mesmo "name").
 *
 * Uso:
 *   require_once __DIR__ . '/../../components/wysiwyg.php';
 *   wysiwyg_field([
 *     'name' => 'descricao',
 *     'label' => 'Descrição',
 *     'value' => $curso['descricao'] ?? '',
 *     'required' => false,
 *     'rows' => 4,
 *     'help' => 'Texto opcional de ajuda.',
 *     'placeholder' => 'Digite...',
 *   ]);
 */

if (!function_exists('wysiwyg_field')) {

    function wysiwyg_assets(): void
    {
        if (!empty($GLOBALS['__wysiwyg_assets_done'])) {
            return;
        }
        $GLOBALS['__wysiwyg_assets_done'] = true;
        ?>
        <script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/classic/ckeditor.js"></script>
        <style>
            .ck-wysiwyg-box .ck-editor__editable { min-height: 8rem; }
            .ck-wysiwyg-box .ck.ck-editor { width: 100%; }
            .ck-wysiwyg-box .ck.ck-editor__main > .ck-editor__editable { border-bottom-left-radius: 0.5rem; border-bottom-right-radius: 0.5rem; }
            .ck-wysiwyg-box .ck.ck-toolbar { border-top-left-radius: 0.5rem; border-top-right-radius: 0.5rem; }
            .ck-wysiwyg-box .ck-focused { border-color: #22c55e !important; box-shadow: 0 0 0 2px rgba(34,197,94,.4) !important; }
        </style>
        <script>
        (function () {
            function initOne(ta) {
                if (!ta || ta.getAttribute('data-ck-inited') || typeof ClassicEditor === 'undefined') return;
                ta.setAttribute('data-ck-inited', '1');
                ClassicEditor.create(ta, {
                    toolbar: ['heading', '|', 'bold', 'italic', 'underline', '|', 'link', 'bulletedList', 'numberedList', '|', 'blockQuote', '|', 'undo', 'redo'],
                    language: 'pt-br'
                }).catch(function (err) { console.error(err); });
            }
            function initAll(root) {
                (root || document).querySelectorAll('textarea.ck-wysiwyg:not([data-ck-inited])').forEach(initOne);
            }
            document.addEventListener('DOMContentLoaded', function () { initAll(document); });
            window.EducatudoWysiwyg = { init: initAll };
        })();
        </script>
        <?php
    }

    /**
     * @param array{name:string,label?:string,value?:string,required?:bool,rows?:int,help?:string,placeholder?:string} $opts
     */
    function wysiwyg_field(array $opts): void
    {
        wysiwyg_assets();
        static $seq = 0;
        $seq++;

        $name = (string) ($opts['name'] ?? ('campo_' . $seq));
        $label = (string) ($opts['label'] ?? '');
        $value = (string) ($opts['value'] ?? '');
        $required = !empty($opts['required']);
        $rows = max(2, (int) ($opts['rows'] ?? 4));
        $help = (string) ($opts['help'] ?? '');
        $placeholder = (string) ($opts['placeholder'] ?? '');
        $id = 'wy_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $name) . '_' . $seq;
        ?>
        <div class="ck-wysiwyg-box">
            <?php if ($label !== ''): ?>
            <label for="<?= htmlspecialchars($id) ?>" class="block text-sm font-medium text-gray-700 mb-2">
                <?= htmlspecialchars($label) ?><?php if ($required): ?> <span class="text-red-500">*</span><?php endif; ?>
            </label>
            <?php endif; ?>
            <textarea id="<?= htmlspecialchars($id) ?>" name="<?= htmlspecialchars($name) ?>" rows="<?= $rows ?>"
                      class="ck-wysiwyg w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                      placeholder="<?= htmlspecialchars($placeholder) ?>"><?= htmlspecialchars($value) ?></textarea>
            <?php if ($help !== ''): ?>
            <p class="mt-1 text-xs text-gray-500"><?= htmlspecialchars($help) ?></p>
            <?php endif; ?>
        </div>
        <?php
    }
}
