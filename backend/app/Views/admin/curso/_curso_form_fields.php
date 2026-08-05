<?php
/**
 * Campos do formulário de curso (create/edit).
 *
 * Variáveis:
 * - $curso_form_item (array|null) — null no create
 * - $has_tipo_possui_serie (bool)
 */
$curso_form_item = $curso_form_item ?? null;
$has_tipo_possui_serie = (bool) ($has_tipo_possui_serie ?? false);
$isEdit = is_array($curso_form_item);
$itemTipo = $isEdit ? ($curso_form_item['tipo'] ?? 'regular') : 'regular';
$itemPossuiSerie = $isEdit ? (int) ($curso_form_item['possui_serie'] ?? 1) : 1;
$inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="md:col-span-2">
        <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
        <input type="text" id="nome" name="nome" required
               placeholder="Ex.: Ensino Fundamental, Ensino Médio"
               value="<?= $isEdit ? htmlspecialchars($curso_form_item['nome']) : '' ?>"
               class="<?= $inputClass ?>">
    </div>

    <?php if ($has_tipo_possui_serie): ?>
    <div>
        <label for="tipo" class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
        <select id="tipo" name="tipo" class="<?= $inputClass ?>">
            <option value="regular" <?= $itemTipo === 'regular' ? 'selected' : '' ?>>Regular (com série)</option>
            <option value="extra" <?= $itemTipo === 'extra' ? 'selected' : '' ?>>Extra (sem série — ex.: Música, Robótica)</option>
        </select>
    </div>
    <div id="wrap-possui-serie" class="flex items-end pb-1 <?= $itemTipo === 'extra' ? 'opacity-60' : '' ?>">
        <div>
            <label class="flex items-center">
                <input type="checkbox" id="possui_serie" name="possui_serie" value="1"
                       <?= $itemPossuiSerie ? 'checked' : '' ?>
                       <?= $itemTipo === 'extra' ? 'disabled' : '' ?>
                       class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                <span class="ml-2 text-sm text-gray-700">Possui série (ex.: 1º Ano, 2º Ano)</span>
            </label>
            <p class="ml-6 text-xs text-gray-500 mt-1">Desmarque para cursos extras sem série.</p>
        </div>
    </div>
    <?php endif; ?>

    <div class="md:col-span-2">
        <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
        <textarea id="descricao" name="descricao" rows="3"
                  class="<?= $inputClass ?>"
                  placeholder="Descrição opcional do curso"><?= $isEdit ? htmlspecialchars($curso_form_item['descricao'] ?? '') : '' ?></textarea>
    </div>

    <div>
        <label for="ordem" class="block text-sm font-medium text-gray-700 mb-2">Ordem</label>
        <input type="number" id="ordem" name="ordem"
               value="<?= $isEdit ? (int) $curso_form_item['ordem'] : 0 ?>"
               class="<?= $inputClass ?>">
        <p class="mt-1 text-xs text-gray-500">Use múltiplos de 10 (10, 20, 30…) para ordenar na listagem.</p>
    </div>

    <div class="flex items-end pb-1">
        <label class="flex items-center">
            <input type="checkbox" name="ativo" value="1"
                   <?= (!$isEdit || !empty($curso_form_item['ativo'])) ? 'checked' : '' ?>
                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
            <span class="ml-2 text-sm text-gray-700">Curso ativo</span>
        </label>
    </div>
</div>

<?php if ($has_tipo_possui_serie): ?>
<script>
(function () {
    var tipo = document.getElementById('tipo');
    if (!tipo) return;
    function syncPossuiSerie() {
        var cb = document.getElementById('possui_serie');
        var wrap = document.getElementById('wrap-possui-serie');
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
    syncPossuiSerie();
})();
</script>
<?php endif; ?>
