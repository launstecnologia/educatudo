<?php
$precos = $precos ?? [];
$labels = $labels ?? [];
$erroTabela = $erro_tabela ?? null;
$flash = $flash ?? [];
$csrf_token = $csrf_token ?? '';
require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';
require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php';
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-slate-900 mb-2">Precificação por créditos</h2>
    <p class="text-slate-600">Custo padrão em créditos por ação/módulo. Usado quando a escola não define override na seção Créditos da escola. <span class="text-slate-500">Valores decimais são permitidos (ex.: 0,5 ou 1,25).</span></p>
</div>

<?php $catalogo_tab = 'precos'; include __DIR__ . '/../creditos_catalogo/_nav_tabs.php'; ?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<?php if ($erroTabela): ?>
<div class="mb-6 px-4 py-3 rounded-lg bg-amber-100 border border-amber-200 text-amber-800">
    <?= htmlspecialchars($erroTabela) ?>
</div>
<?php else: ?>
<form method="post" action="<?= URL ?>/master/precificacao/salvar" class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
    <div class="px-6 py-4 border-b border-slate-200 flex justify-between items-center">
        <h3 class="text-lg font-semibold text-slate-900">Módulos consumíveis</h3>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium text-sm">Salvar</button>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Módulo (chave)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Nome (exibição)</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Créditos</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Ativo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php
                $grupoAnterior = null;
                foreach ($labels as $moduloKey => $nomePadrao):
                    $row = $precos[$moduloKey] ?? null;
                    $creditos = $row
                        ? \CreditosDecimalHelper::fromScalar($row['creditos'] ?? 1, 1.0)
                        : 1.0;
                    if ($creditos <= 0) {
                        $creditos = 1.0;
                    }
                    $nomeExibicao = $row ? ($row['nome_exibicao'] ?? $nomePadrao) : $nomePadrao;
                    $ativo = $row ? (int)($row['ativo'] ?? 1) : 1;
                    $grupo = \CreditosModuleRegistry::getGrupo($moduloKey);
                ?>
                <?php if ($grupo !== $grupoAnterior): $grupoAnterior = $grupo; ?>
                <tr class="bg-blue-50/80">
                    <td colspan="4" class="px-4 py-2 text-xs font-semibold text-slate-900 uppercase tracking-wide"><?= htmlspecialchars($grupo) ?></td>
                </tr>
                <?php endif; ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm font-mono text-slate-700"><?= htmlspecialchars($moduloKey) ?></td>
                    <td class="px-4 py-3">
                        <input type="text" name="nome_exibicao[<?= htmlspecialchars($moduloKey) ?>]" value="<?= htmlspecialchars($nomeExibicao) ?>"
                               class="w-full max-w-md px-2 py-1.5 border border-slate-300 rounded text-sm">
                    </td>
                    <td class="px-4 py-3">
                        <input type="number" name="creditos[<?= htmlspecialchars($moduloKey) ?>]" value="<?= htmlspecialchars(\CreditosDecimalHelper::formatInput($creditos)) ?>"
                               min="0" step="0.0001" inputmode="decimal" lang="pt-BR" class="w-24 px-2 py-1.5 border border-slate-300 rounded text-sm">
                    </td>
                    <td class="px-4 py-3">
                        <label class="inline-flex items-center">
                            <input type="checkbox" name="ativo[<?= htmlspecialchars($moduloKey) ?>]" value="1" <?= $ativo ? 'checked' : '' ?> class="rounded border-slate-300">
                            <span class="ml-2 text-sm text-slate-600">Sim</span>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>
<?php endif; ?>
