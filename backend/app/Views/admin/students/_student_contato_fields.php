<?php
require_once __DIR__ . '/../../../Helpers/StudentFormHelper.php';
$student = is_array($student ?? null) ? $student : [];
$telefoneDisplay = StudentFormHelper::formatTelefoneDisplay($student['telefone'] ?? '');
$celularDisplay = StudentFormHelper::formatTelefoneDisplay($student['celular'] ?? '');
$whatsappDisplay = StudentFormHelper::formatTelefoneDisplay($student['whatsapp'] ?? '');
$escc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div>
    <label for="telefone" class="block text-sm font-medium text-gray-700 mb-2">Telefone</label>
    <input type="text" id="telefone" name="telefone" inputmode="tel" maxlength="15" autocomplete="tel"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-telefone"
           value="<?= htmlspecialchars($telefoneDisplay, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="(00) 0000-0000">
</div>
<div>
    <label for="celular" class="block text-sm font-medium text-gray-700 mb-2">Celular</label>
    <input type="text" id="celular" name="celular" inputmode="tel" maxlength="16" autocomplete="tel"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-celular"
           value="<?= htmlspecialchars($celularDisplay, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="(00) 00000-0000">
</div>
<div>
    <label for="whatsapp" class="block text-sm font-medium text-gray-700 mb-2">WhatsApp</label>
    <input type="text" id="whatsapp" name="whatsapp" inputmode="tel" maxlength="16" autocomplete="tel"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-celular"
           value="<?= htmlspecialchars($whatsappDisplay, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="(00) 00000-0000">
</div>
<div>
    <label for="email" class="block text-sm font-medium text-gray-700 mb-2">E-mail</label>
    <input type="email" id="email" name="email" autocomplete="email"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
           value="<?= $escc($student['email'] ?? '') ?>"
           placeholder="email@exemplo.com">
</div>
<div class="md:col-span-2">
    <label for="email_secundario" class="block text-sm font-medium text-gray-700 mb-2">E-mail secundário</label>
    <input type="email" id="email_secundario" name="email_secundario" autocomplete="email"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
           value="<?= $escc($student['email_secundario'] ?? '') ?>"
           placeholder="email.secundario@exemplo.com">
</div>
