<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$pendentes = is_array($pendentes ?? null) ? $pendentes : [];
$gravados = is_array($gravados ?? null) ? $gravados : [];
$placeholders = is_array($placeholders ?? null) ? $placeholders : [];
$folha = is_array($pendentes[0] ?? null) ? $pendentes[0] : [];
$campos = is_array($folha['campos'] ?? null) ? $folha['campos'] : [];
$titulo = (string) ($folha['titulo'] ?? 'Modelo importado');

$page_header_back_url = URL . '/admin/modelos-documentos/importar';
$page_header_title = 'Conferir campos';
$page_header_subtitle = 'Esta folha não é um modelo 1127/1128. Confirme para qual variável cada rótulo deve ir antes de gravar.';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if ($gravados !== []): ?>
<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800 mb-6">
    Folhas reconhecidas já foram gravadas:
    <?php foreach ($gravados as $g): ?>
        <span class="font-medium"><?= $esc($g['nome'] ?? '') ?></span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<form method="post" action="<?= URL ?>/admin/modelos-documentos/importar/conferir" class="bg-white rounded-xl shadow-lg p-6 w-full">
    <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div>
            <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome do modelo</label>
            <input id="nome" name="nome" value="<?= $esc($titulo) ?>"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
        </div>
        <div>
            <label for="orientacao" class="block text-sm font-medium text-gray-700 mb-2">Orientação</label>
            <select id="orientacao" name="orientacao"
                    class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <option value="retrato">Retrato</option>
                <option value="paisagem">Paisagem</option>
            </select>
        </div>
    </div>

    <div class="overflow-x-auto mb-6">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Rótulo na planilha</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Gravar em</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Origem</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            <?php foreach ($campos as $i => $campo):
                $ph = (string) ($campo['placeholder'] ?? '');
                $conf = (string) ($campo['confianca'] ?? 'manual');
                $origem = $conf === 'dicionario' ? 'Dicionário' : ($conf === 'sugestao' ? 'Sugestão' : 'Manual');
            ?>
                <tr>
                    <td class="px-4 py-3">
                        <input type="hidden" name="rotulo[<?= (int) $i ?>]" value="<?= $esc($campo['rotulo'] ?? '') ?>">
                        <?= $esc($campo['rotulo'] ?? '') ?>
                    </td>
                    <td class="px-4 py-3">
                        <select name="placeholder[<?= (int) $i ?>]"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                            <option value="_ignorar">Não gravar</option>
                            <?php foreach ($placeholders as $chave => $label): ?>
                            <option value="<?= $esc($chave) ?>" <?= $ph === $chave ? 'selected' : '' ?>><?= $esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="px-4 py-3 text-gray-500"><?= $esc($origem) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($campos === []): ?>
    <p class="text-sm text-gray-600 mb-6">Nenhum rótulo foi encontrado nesta folha. Volte e envie outra planilha.</p>
    <?php endif; ?>

    <div class="flex justify-end">
        <button type="submit" class="btn-primary" <?= $campos === [] ? 'disabled' : '' ?>>Gravar modelo</button>
    </div>
</form>
