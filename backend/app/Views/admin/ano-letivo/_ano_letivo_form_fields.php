<?php
/**
 * Campos do formulário de ano letivo (create/edit).
 *
 * Variáveis:
 * - $ano_letivo_form_item (array|null)
 */
$ano_letivo_form_item = $ano_letivo_form_item ?? null;
$isEdit = is_array($ano_letivo_form_item);
$inputClass = 'w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500';
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div>
        <label for="ano" class="block text-sm font-medium text-gray-700 mb-2">Ano <span class="text-red-500">*</span></label>
        <input type="number" id="ano" name="ano" required min="2000" max="2100"
               value="<?= $isEdit ? (int) $ano_letivo_form_item['ano'] : (int) date('Y') ?>"
               class="<?= $inputClass ?>">
    </div>
    <div class="flex items-end pb-1">
        <label class="flex items-center">
            <input type="checkbox" name="ativo" value="1"
                   <?= (!$isEdit || !empty($ano_letivo_form_item['ativo'])) ? 'checked' : '' ?>
                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
            <span class="ml-2 text-sm text-gray-700">Ano letivo ativo</span>
        </label>
    </div>
    <div>
        <label for="data_inicio" class="block text-sm font-medium text-gray-700 mb-2">Data início</label>
        <input type="date" id="data_inicio" name="data_inicio"
               value="<?= $isEdit ? htmlspecialchars($ano_letivo_form_item['data_inicio'] ?? '') : '' ?>"
               class="<?= $inputClass ?>">
    </div>
    <div>
        <label for="data_fim" class="block text-sm font-medium text-gray-700 mb-2">Data fim</label>
        <input type="date" id="data_fim" name="data_fim"
               value="<?= $isEdit ? htmlspecialchars($ano_letivo_form_item['data_fim'] ?? '') : '' ?>"
               class="<?= $inputClass ?>">
    </div>
</div>
