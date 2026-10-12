<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Editar Pagamento</h1>
            <p class="text-gray-600 mt-2">Ajuste valores e datas do pagamento.</p>
        </div>
        <a href="<?= URL ?>/admin/financeiro"
           class="bg-gray-600 hover:bg-gray-700 text-white font-semibold py-2 px-4 rounded-lg transition-colors">
            Voltar
        </a>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="<?= URL ?>/admin/financeiro/pagamentos/<?= (int) $pagamento['id'] ?>/atualizar" class="space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Mês de Referência</label>
                <input type="text" disabled
                       value="<?= date('m/Y', strtotime($pagamento['mes_referencia'])) ?>"
                       class="w-full px-4 py-2 border border-gray-200 rounded-lg bg-gray-100 text-gray-600">
            </div>
            <div>
                <label for="valor_total" class="block text-sm font-medium text-gray-700 mb-2">Valor Total</label>
                <input type="text" id="valor_total" name="valor_total"
                       value="<?= number_format($pagamento['valor_total'] ?? 0, 2, ',', '.') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="data_vencimento" class="block text-sm font-medium text-gray-700 mb-2">Data de Vencimento</label>
                <input type="date" id="data_vencimento" name="data_vencimento"
                       value="<?= !empty($pagamento['data_vencimento']) ? htmlspecialchars($pagamento['data_vencimento']) : '' ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="data_pagamento" class="block text-sm font-medium text-gray-700 mb-2">Data de Pagamento</label>
                <input type="date" id="data_pagamento" name="data_pagamento"
                       value="<?= !empty($pagamento['data_pagamento']) ? htmlspecialchars($pagamento['data_pagamento']) : '' ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                <select id="status" name="status"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    <option value="aberto" <?= ($pagamento['status'] ?? '') === 'aberto' ? 'selected' : '' ?>>Em aberto</option>
                    <option value="pago" <?= ($pagamento['status'] ?? '') === 'pago' ? 'selected' : '' ?>>Pago</option>
                </select>
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                Salvar alterações
            </button>
        </div>
    </form>
</div>

