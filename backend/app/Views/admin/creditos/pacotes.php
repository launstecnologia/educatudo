<?php
if (!class_exists('CreditosDecimalHelper')) {
    require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php';
}
$pacotes = $pacotes ?? [];
?>
<div class="max-w-6xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Pacotes de Créditos</h1>
        <p class="text-gray-600 mt-1">Cadastre os pacotes exibidos na EducaShop para compra avulsa dos alunos.</p>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Pacotes ativos no tenant</h2>
                <p class="text-sm text-gray-500 mt-1">Os créditos comprados aqui entram no saldo permanente do aluno e não expiram com a recarga mensal da escola.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Pacote</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Créditos</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Preço</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($pacotes as $pacote): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($pacote['nome'] ?? '') ?></div>
                                <div class="text-xs text-gray-500">ID <?= (int) ($pacote['id'] ?? 0) ?></div>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay(\CreditosDecimalHelper::fromScalar($pacote['creditos'] ?? 0, 0.0))) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-700">R$ <?= number_format(((int) ($pacote['valor_centavos'] ?? 0)) / 100, 2, ',', '.') ?></td>
                            <td class="px-6 py-4 text-sm">
                                <?php if (!empty($pacote['ativo'])): ?>
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-700">Ativo</span>
                                <?php else: ?>
                                <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-semibold bg-gray-100 text-gray-600">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 text-sm">
                                <form method="post" action="<?= URL ?>/admin/creditos/pacotes/toggle" class="inline">
                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                    <input type="hidden" name="pacote_id" value="<?= (int) ($pacote['id'] ?? 0) ?>">
                                    <input type="hidden" name="pacote_ativo" value="<?= !empty($pacote['ativo']) ? '0' : '1' ?>">
                                    <button type="submit" class="text-indigo-600 hover:text-indigo-800 font-medium">
                                        <?= !empty($pacote['ativo']) ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pacotes)): ?>
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-gray-500">Nenhum pacote cadastrado ainda.</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
            <h2 class="text-lg font-semibold text-gray-900">Novo pacote</h2>
            <p class="text-sm text-gray-500 mt-1 mb-5">Esse pacote aparecerá para o aluno na EducaShop e será liberado automaticamente após confirmação do pagamento pelo Asaas.</p>
            <form method="post" action="<?= URL ?>/admin/creditos/pacotes" class="space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nome do pacote</label>
                    <input type="text" name="pacote_nome" placeholder="Ex: Pacote 100 créditos" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Quantidade de créditos</label>
                    <input type="number" name="pacote_creditos" value="100" min="0.0001" step="0.0001" inputmode="decimal" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Preço (centavos)</label>
                    <input type="number" name="pacote_valor_centavos" value="1990" min="1" step="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" required>
                    <p class="text-xs text-gray-500 mt-1">Exemplo: <code>1990</code> = R$ 19,90.</p>
                </div>
                <button type="submit" class="btn-primary-custom w-full px-4 py-2.5 rounded-lg font-medium text-sm hover:opacity-90">Salvar pacote</button>
            </form>
        </div>
    </div>
</div>
