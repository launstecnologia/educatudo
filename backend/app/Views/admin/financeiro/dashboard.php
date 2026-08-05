<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Financeiro 💰</h1>
            <p class="text-gray-600 mt-2">Dashboard de pagamentos financeiros</p>
        </div>
    </div>
</div>

<!-- KPIs -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl shadow-lg p-6 border border-indigo-200">
        <p class="text-sm text-gray-500">Total Usuários (pagos)</p>
        <p class="text-2xl font-bold text-gray-900"><?= (int) ($kpis['total_usuarios'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-indigo-200">
        <p class="text-sm text-gray-500">Valor Unitário</p>
        <p class="text-2xl font-bold text-gray-900">R$ <?= number_format($kpis['valor_unitario'] ?? 0, 2, ',', '.') ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-indigo-200">
        <p class="text-sm text-gray-500">Valor Total</p>
        <p class="text-2xl font-bold text-gray-900">R$ <?= number_format($kpis['valor_total'] ?? 0, 2, ',', '.') ?></p>
    </div>
    <div class="bg-white rounded-xl shadow-lg p-6 border border-indigo-200">
        <p class="text-sm text-gray-500">Dia do Vencimento</p>
        <p class="text-2xl font-bold text-gray-900">
            <?= !empty($kpis['data_vencimento']) ? date('d/m/Y', strtotime($kpis['data_vencimento'])) : '-' ?>
        </p>
    </div>
</div>

<!-- Lista de Pagamentos -->
<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200">
        <h2 class="text-xl font-semibold text-gray-900">Pagamentos Mensais</h2>
    </div>
    <div class="p-6">
        <?php if (empty($pagamentos)): ?>
            <div class="text-center py-12 text-gray-500">Nenhum pagamento gerado.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mês</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pagantes</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Total</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimento</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pago em</th>
                            <?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($pagamentos as $pagamento): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                    <?= date('m/Y', strtotime($pagamento['mes_referencia'])) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= (int) ($pagamento['total_usuarios_pagantes'] ?? 0) ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-green-700">
                                    R$ <?= number_format($pagamento['valor_total'] ?? 0, 2, ',', '.') ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= !empty($pagamento['data_vencimento']) ? date('d/m/Y', strtotime($pagamento['data_vencimento'])) : '-' ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= ($pagamento['status'] ?? '') === 'pago' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800' ?>">
                                        <?= ($pagamento['status'] ?? '') === 'pago' ? 'Pago' : 'Em aberto' ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                                    <?= !empty($pagamento['data_pagamento']) ? date('d/m/Y', strtotime($pagamento['data_pagamento'])) : '-' ?>
                                </td>
                                <?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <div class="flex items-center gap-2">
                                            <?php if (($pagamento['status'] ?? '') !== 'pago'): ?>
                                                <form method="POST" action="<?= URL ?>/admin/financeiro/pagamentos/<?= (int) $pagamento['id'] ?>/pagar">
                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <button type="submit" class="px-3 py-1 rounded-lg bg-blue-600 text-white hover:bg-blue-700 transition-colors text-sm">
                                                        Marcar como pago
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" action="<?= URL ?>/admin/financeiro/pagamentos/<?= (int) $pagamento['id'] ?>/reabrir">
                                                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                                                    <button type="submit" class="px-3 py-1 rounded-lg bg-gray-600 text-white hover:bg-gray-700 transition-colors text-sm">
                                                        Reabrir
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                            <a href="<?= URL ?>/admin/financeiro/pagamentos/<?= (int) $pagamento['id'] ?>/editar"
                                               class="btn-primary-custom px-3 py-1 rounded-lg transition-colors text-sm hover:opacity-90">
                                                Editar
                                            </a>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

