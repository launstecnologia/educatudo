<?php
/**
 * Inputs dos campos criados no layout do documento.
 *
 * @var list<array{chave:string,rotulo:string,mascara:string,obrigatorio:bool}> $campos_variaveis
 * @var string $campos_variaveis_prefix
 */
$campos = is_array($campos_variaveis ?? null) ? $campos_variaveis : [];
$prefixo = preg_replace('/[^a-z0-9_-]/i', '', (string) ($campos_variaveis_prefix ?? 'cv')) ?: 'cv';
$escCampo = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$placeholders = [
    'texto' => '',
    'valor' => '0,00',
    'cpf' => '000.000.000-00',
    'telefone' => '(00) 00000-0000',
    'numero' => '0',
];
?>
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<?php foreach ($campos as $campo):
    $chave = (string) ($campo['chave'] ?? '');
    $mascara = (string) ($campo['mascara'] ?? 'texto');
    $rotulo = (string) ($campo['rotulo'] ?? $chave);
    $obrigatorio = !empty($campo['obrigatorio']);
    $id = $prefixo . '-' . $chave;
    $req = $obrigatorio ? 'required' : '';
    $nome = 'c[' . $chave . ']';
?>
    <div class="<?= $mascara === 'texto' ? 'md:col-span-2' : '' ?>">
        <label for="<?= $escCampo($id) ?>" class="block text-sm font-medium text-gray-700 mb-2">
            <?= $escCampo($rotulo) ?><?= $obrigatorio ? ' *' : '' ?>
        </label>
        <?php if ($mascara === 'data'): ?>
        <input type="date" id="<?= $escCampo($id) ?>" name="<?= $escCampo($nome) ?>" data-mascara="data" <?= $req ?>
               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
        <?php elseif ($mascara === 'hora'): ?>
        <input type="time" id="<?= $escCampo($id) ?>" name="<?= $escCampo($nome) ?>" data-mascara="hora" <?= $req ?>
               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
        <?php else: ?>
        <input type="text" id="<?= $escCampo($id) ?>" name="<?= $escCampo($nome) ?>"
               data-mascara="<?= $escCampo($mascara) ?>"
               inputmode="<?= $mascara === 'valor' || $mascara === 'numero' ? 'decimal' : 'text' ?>"
               placeholder="<?= $escCampo($placeholders[$mascara] ?? '') ?>"
               autocomplete="off" <?= $req ?>
               class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
        <?php endif; ?>
    </div>
<?php endforeach; ?>
</div>
