<?php
$senha_forca_prefix = $senha_forca_prefix ?? 'senha';
?>
<div>
    <div class="flex items-center gap-2 mb-2">
        <div class="flex-1 h-1.5 bg-gray-200 rounded-full overflow-hidden">
            <div id="<?= htmlspecialchars($senha_forca_prefix) ?>-strength-bar" class="h-full rounded-full transition-all duration-300 bg-gray-300" style="width: 0%"></div>
        </div>
        <span id="<?= htmlspecialchars($senha_forca_prefix) ?>-strength-text" class="text-xs font-medium text-gray-500 w-12 text-right">Fraca</span>
    </div>
    <ul class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1 text-xs text-gray-500">
        <li id="<?= htmlspecialchars($senha_forca_prefix) ?>-req-length" class="flex items-center gap-1.5">
            <i class="fa-solid fa-circle text-[7px] req-icon"></i>
            <span>Mínimo 8 caracteres</span>
        </li>
        <li id="<?= htmlspecialchars($senha_forca_prefix) ?>-req-upper" class="flex items-center gap-1.5">
            <i class="fa-solid fa-circle text-[7px] req-icon"></i>
            <span>1 letra maiúscula</span>
        </li>
        <li id="<?= htmlspecialchars($senha_forca_prefix) ?>-req-lower" class="flex items-center gap-1.5">
            <i class="fa-solid fa-circle text-[7px] req-icon"></i>
            <span>1 letra minúscula</span>
        </li>
        <li id="<?= htmlspecialchars($senha_forca_prefix) ?>-req-number" class="flex items-center gap-1.5">
            <i class="fa-solid fa-circle text-[7px] req-icon"></i>
            <span>1 número</span>
        </li>
        <li id="<?= htmlspecialchars($senha_forca_prefix) ?>-req-special" class="flex items-center gap-1.5 sm:col-span-2">
            <i class="fa-solid fa-circle text-[7px] req-icon"></i>
            <span>1 caractere especial (!@#$%...)</span>
        </li>
    </ul>
</div>
