<?php
$tabela = $tabela ?? [];
$labels = $labels ?? [];
$itens = $itens ?? [];
$flash = $flash ?? [];
$csrf_token = $csrf_token ?? '';
$id = (int) ($tabela['id'] ?? 0);
require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php';
?>
<div class="mb-6">
    <a href="<?= URL ?>/master/creditos-catalogo/tabelas" class="text-sm text-blue-600 hover:underline">&larr; Voltar às tabelas de preço</a>
    <div class="mt-2 flex flex-wrap items-center gap-3">
        <h2 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($tabela['nome'] ?? '') ?></h2>
        <?php if (!empty($tabela['padrao'])): ?>
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">Padrão (escolas novas)</span>
        <?php endif; ?>
    </div>
    <p class="text-slate-600 text-sm mt-1">Defina se cada módulo cobra TudiCoins e o custo em TudiCoins por uso. Depois vincule esta tabela na escola.</p>
</div>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<form method="post" action="<?= URL ?>/master/creditos-catalogo/tabelas/<?= $id ?>/salvar-itens" class="bg-white rounded-xl shadow border border-slate-200 overflow-hidden">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-slate-900">Módulos</h3>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">Salvar</button>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Módulo</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">Nome exibição</th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">
                        <label class="inline-flex cursor-pointer items-center gap-2">
                            <input type="checkbox" id="selecionarTodasCobra" class="rounded border-slate-300">
                            <span>Cobra</span>
                        </label>
                    </th>
                    <th class="px-4 py-2 text-left text-xs font-medium text-slate-500 uppercase">TudiCoins</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                $grupoAnt = null;
                foreach ($labels as $moduloKey => $nomePadrao):
                    $row = $itens[$moduloKey] ?? null;
                    $creditos = $row ? \CreditosDecimalHelper::fromScalar($row['creditos'] ?? 1, 1.0) : 1.0;
                    $cobra = $row ? !empty($row['cobra']) : false;
                    $nomeEx = $row && !empty($row['nome_exibicao']) ? (string) $row['nome_exibicao'] : $nomePadrao;
                    $grupo = \CreditosModuleRegistry::getGrupo($moduloKey);
                ?>
                <?php if ($grupo !== $grupoAnt): $grupoAnt = $grupo; ?>
                <tr class="bg-blue-50/80">
                    <td colspan="4" class="px-4 py-2 text-xs font-semibold text-slate-900 uppercase"><?= htmlspecialchars($grupo) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-2 text-sm font-mono text-slate-600"><?= htmlspecialchars($moduloKey) ?></td>
                    <td class="px-4 py-2">
                        <input type="text" name="nome_exibicao[<?= htmlspecialchars($moduloKey) ?>]" value="<?= htmlspecialchars($nomeEx) ?>" class="w-full max-w-xs px-2 py-1 border border-slate-300 rounded text-sm">
                    </td>
                    <td class="px-4 py-2">
                        <input type="checkbox" name="cobra[<?= htmlspecialchars($moduloKey) ?>]" value="1" <?= $cobra ? 'checked' : '' ?> class="cobra-checkbox rounded border-slate-300">
                    </td>
                    <td class="px-4 py-2">
                        <input type="number" name="creditos[<?= htmlspecialchars($moduloKey) ?>]" value="<?= htmlspecialchars(\CreditosDecimalHelper::formatInput($creditos)) ?>" min="0" step="0.0001" class="w-28 px-2 py-1 border border-slate-300 rounded text-sm">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const selecionarTodas = document.getElementById('selecionarTodasCobra');
    const checkboxes = Array.from(document.querySelectorAll('.cobra-checkbox'));

    function atualizarSelecionarTodas() {
        if (!selecionarTodas || checkboxes.length === 0) return;

        const totalMarcados = checkboxes.filter(checkbox => checkbox.checked).length;
        selecionarTodas.checked = totalMarcados === checkboxes.length;
        selecionarTodas.indeterminate = totalMarcados > 0 && totalMarcados < checkboxes.length;
    }

    selecionarTodas?.addEventListener('change', function() {
        checkboxes.forEach(checkbox => {
            checkbox.checked = selecionarTodas.checked;
        });
        atualizarSelecionarTodas();
    });

    checkboxes.forEach(checkbox => {
        checkbox.addEventListener('change', atualizarSelecionarTodas);
    });

    atualizarSelecionarTodas();
});
</script>
