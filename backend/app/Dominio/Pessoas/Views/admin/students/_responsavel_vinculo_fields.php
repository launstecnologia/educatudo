<?php
/**
 * Campos de vínculo do responsável (parentesco, profissão e flags).
 * Variáveis esperadas:
 *   $prefix  string  prefixo de id (ex: 'pai_' ou 'resp_edit_')
 */
$prefix = $prefix ?? '';
$opcoesParentesco = ['Mãe', 'Pai', 'Avó', 'Avô', 'Madrasta', 'Padrasto', 'Tio(a)', 'Irmão(ã)', 'Responsável legal', 'Outro'];
$flags = [
    'pode_retirar' => 'Autorizado a retirar o aluno',
    'recebe_boletos' => 'Recebe boletos / financeiro',
    'recebe_boletim' => 'Recebe boletim',
    'recebe_notificacoes' => 'Recebe notificações',
    'responsavel_pedagogico' => 'Responsável pedagógico',
    'guarda_judicial' => 'Possui guarda judicial',
    'assina_documentos' => 'Assina documentos',
];
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-3">
    <div>
        <label for="<?= $prefix ?>parentesco" class="block text-sm font-medium text-gray-700 mb-1">Parentesco</label>
        <select id="<?= $prefix ?>parentesco" name="parentesco"
                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500">
            <option value="">Não informado</option>
            <?php foreach ($opcoesParentesco as $op): ?>
            <option value="<?= htmlspecialchars($op, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($op, ENT_QUOTES, 'UTF-8') ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="<?= $prefix ?>profissao" class="block text-sm font-medium text-gray-700 mb-1">Profissão</label>
        <input type="text" id="<?= $prefix ?>profissao" name="profissao" maxlength="120"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
               placeholder="Profissão">
    </div>
    <div class="md:col-span-2">
        <label for="<?= $prefix ?>empresa" class="block text-sm font-medium text-gray-700 mb-1">Empresa / Local de trabalho</label>
        <input type="text" id="<?= $prefix ?>empresa" name="empresa" maxlength="120"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
               placeholder="Empresa onde trabalha">
    </div>
</div>
<div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2">
    <?php foreach ($flags as $campo => $label): ?>
    <label class="inline-flex items-center gap-2">
        <input type="checkbox" id="<?= $prefix . $campo ?>" name="<?= $campo ?>" value="1" class="rounded border-gray-300 text-indigo-600">
        <span class="text-sm text-gray-700"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></span>
    </label>
    <?php endforeach; ?>
</div>
