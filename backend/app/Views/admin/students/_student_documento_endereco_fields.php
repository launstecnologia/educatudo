<?php
require_once __DIR__ . '/../../../Helpers/StudentFormHelper.php';
$student = is_array($student ?? null) ? $student : [];
$ufs = StudentFormHelper::ufsBrasil();
$cpfDisplay = StudentFormHelper::formatCpfDisplay($student['cpf'] ?? '');
$rgDisplay = StudentFormHelper::formatRgDisplay($student['rg'] ?? '');
$cepDisplay = StudentFormHelper::formatCepDisplay($student['cep'] ?? '');
$dataNasc = StudentFormHelper::formatDataNascInput($student['data_nasc'] ?? null);
$ufAtual = strtoupper(trim((string) ($student['uf'] ?? '')));
?>
<div>
    <label for="cpf" class="block text-sm font-medium text-gray-700 mb-2">CPF</label>
    <input type="text" id="cpf" name="cpf" inputmode="numeric" maxlength="14" autocomplete="off"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-cpf"
           value="<?= htmlspecialchars($cpfDisplay, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="000.000.000-00">
</div>
<div>
    <label for="rg" class="block text-sm font-medium text-gray-700 mb-2">RG</label>
    <input type="text" id="rg" name="rg" maxlength="15" autocomplete="off"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-rg"
           value="<?= htmlspecialchars($rgDisplay, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="00.000.000-0">
</div>
<div>
    <label for="data_nasc" class="block text-sm font-medium text-gray-700 mb-2">Data de nascimento</label>
    <input type="date" id="data_nasc" name="data_nasc"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
           value="<?= htmlspecialchars($dataNasc, ENT_QUOTES, 'UTF-8') ?>">
</div>

<div class="md:col-span-2 pt-2">
    <h4 class="text-base font-semibold text-gray-900 mb-4">Endereço</h4>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
            <label for="logradouro" class="block text-sm font-medium text-gray-700 mb-2">Logradouro</label>
            <input type="text" id="logradouro" name="logradouro"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= htmlspecialchars($student['logradouro'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Rua, avenida, travessa...">
        </div>
        <div>
            <label for="numero" class="block text-sm font-medium text-gray-700 mb-2">Número</label>
            <input type="text" id="numero" name="numero"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= htmlspecialchars($student['numero'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="123">
        </div>
        <div>
            <label for="complemento" class="block text-sm font-medium text-gray-700 mb-2">Complemento</label>
            <input type="text" id="complemento" name="complemento"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= htmlspecialchars($student['complemento'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Apto, bloco, casa...">
        </div>
        <div>
            <label for="bairro" class="block text-sm font-medium text-gray-700 mb-2">Bairro</label>
            <input type="text" id="bairro" name="bairro"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= htmlspecialchars($student['bairro'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Bairro">
        </div>
        <div>
            <label for="cidade" class="block text-sm font-medium text-gray-700 mb-2">Cidade</label>
            <input type="text" id="cidade" name="cidade"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                   value="<?= htmlspecialchars($student['cidade'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Cidade">
        </div>
        <div>
            <label for="uf" class="block text-sm font-medium text-gray-700 mb-2">UF</label>
            <select id="uf" name="uf"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Selecione</option>
                <?php foreach ($ufs as $uf): ?>
                <option value="<?= $uf ?>" <?= $ufAtual === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="cep" class="block text-sm font-medium text-gray-700 mb-2">CEP</label>
            <input type="text" id="cep" name="cep" inputmode="numeric" maxlength="9" autocomplete="off"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-cep"
                   value="<?= htmlspecialchars($cepDisplay, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="00000-000">
        </div>
    </div>
</div>
