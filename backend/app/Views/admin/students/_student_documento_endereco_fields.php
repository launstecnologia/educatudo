<?php
require_once __DIR__ . '/../../../Helpers/StudentFormHelper.php';
$student = is_array($student ?? null) ? $student : [];
$ufs = StudentFormHelper::ufsBrasil();
$cpfDisplay = StudentFormHelper::formatCpfDisplay($student['cpf'] ?? '');
$rgDisplay = StudentFormHelper::formatRgDisplay($student['rg'] ?? '');
$cepDisplay = StudentFormHelper::formatCepDisplay($student['cep'] ?? '');
$dataNasc = StudentFormHelper::formatDataNascInput($student['data_nasc'] ?? null);
$ufAtual = strtoupper(trim((string) ($student['uf'] ?? '')));
$ufRgAtual = strtoupper(trim((string) ($student['uf_rg'] ?? '')));
$parteDoc = $parteDoc ?? 'tudo';
$mostraDocumentos = $parteDoc === 'tudo' || $parteDoc === 'documentos';
$mostraNascimento = $parteDoc === 'tudo' || $parteDoc === 'nascimento';
$mostraEndereco = $parteDoc === 'tudo' || $parteDoc === 'endereco';
?>
<?php if ($mostraDocumentos): ?>
<div>
    <label for="cpf" class="block text-sm font-medium text-gray-700 mb-2">CPF / CIN</label>
    <input type="text" id="cpf" name="cpf" inputmode="numeric" maxlength="14" autocomplete="off"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-cpf"
           value="<?= htmlspecialchars($cpfDisplay, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="000.000.000-00">
</div>
<div>
    <label for="rg" class="block text-sm font-medium text-gray-700 mb-2">RG <span class="text-gray-400 font-normal">(opcional)</span></label>
    <input type="text" id="rg" name="rg" maxlength="15" autocomplete="off"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-rg"
           value="<?= htmlspecialchars($rgDisplay, ENT_QUOTES, 'UTF-8') ?>"
           placeholder="00.000.000-0">
</div>
<div>
    <label for="orgao_emissor" class="block text-sm font-medium text-gray-700 mb-2">Órgão emissor do RG</label>
    <input type="text" id="orgao_emissor" name="orgao_emissor" maxlength="30"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
           value="<?= htmlspecialchars((string) ($student['orgao_emissor'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
           placeholder="Ex: SSP">
</div>
<div>
    <label for="uf_rg" class="block text-sm font-medium text-gray-700 mb-2">UF do RG</label>
    <select id="uf_rg" name="uf_rg"
            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        <option value="">Selecione</option>
        <?php foreach ($ufs as $uf): ?>
        <option value="<?= $uf ?>" <?= $ufRgAtual === $uf ? 'selected' : '' ?>><?= $uf ?></option>
        <?php endforeach; ?>
    </select>
</div>
<?php endif; ?>
<?php if ($mostraNascimento): ?>
<div>
    <label for="data_nasc" class="block text-sm font-medium text-gray-700 mb-2">Data de nascimento <span class="text-red-600">*</span></label>
    <input type="date" id="data_nasc" name="data_nasc"
           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
           value="<?= htmlspecialchars($dataNasc, ENT_QUOTES, 'UTF-8') ?>">
</div>
<?php endif; ?>

<?php if ($mostraEndereco): ?>
<div class="<?= $parteDoc === 'tudo' ? 'md:col-span-2 pt-2' : '' ?>">
    <?php if ($parteDoc === 'tudo'): ?>
    <h4 class="text-base font-semibold text-gray-900 mb-4">Endereço</h4>
    <?php endif; ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <div class="md:col-span-2">
            <label for="cep" class="block text-sm font-medium text-gray-700 mb-2">CEP</label>
            <div class="flex gap-2 items-center max-w-md">
                <input type="text" id="cep" name="cep" inputmode="numeric" maxlength="9" autocomplete="off"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 js-mask-cep"
                       value="<?= htmlspecialchars($cepDisplay, ENT_QUOTES, 'UTF-8') ?>"
                       placeholder="00000-000">
                <button type="button" id="btn-busca-cep"
                        class="shrink-0 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                    Buscar
                </button>
            </div>
            <p class="mt-1 text-xs text-gray-500" id="cep-status"></p>
        </div>
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
    </div>
</div>
<?php endif; ?>
