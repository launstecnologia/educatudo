<?php /** @var array $contract @var array $vencidas @var float $total_divida @var string $csrf_token */ ?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Renegociação de Dívida</h2>
            <p class="text-sm text-gray-600"><?= htmlspecialchars((string)($contract['aluno_nome'] ?? '')) ?></p>
        </div>
    </div>
</div>

<!-- Parcelas vencidas -->
<div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
    <h3 class="text-sm font-semibold text-red-700 mb-2">Parcelas em atraso</h3>
    <div class="space-y-1">
        <?php foreach ($vencidas as $v): ?>
        <div class="flex justify-between text-sm">
            <span class="text-gray-700"><?= htmlspecialchars((string)($v['descricao'] ?? '')) ?> — venc. <?= date('d/m/Y', strtotime($v['data_vencimento'])) ?></span>
            <span class="font-medium text-red-700">R$ <?= number_format($v['valor_cobrado'], 2, ',', '.') ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <div class="flex justify-between text-sm font-bold text-red-800 border-t border-red-200 pt-2 mt-2">
        <span>Total em atraso</span>
        <span>R$ <?= number_format($total_divida, 2, ',', '.') ?></span>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden w-full">
    <form method="POST" action="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>/renegotiation" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="valor_total_divida" value="<?= $total_divida ?>">

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Condições da Renegociação</h3>
                <p class="mt-1 text-sm text-gray-500">Defina entrada, número de parcelas e vencimento acordados com o responsável.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="valor_entrada" class="block text-sm font-medium text-gray-700 mb-2">Valor de Entrada (R$)</label>
                    <input type="text" id="entrada-input" name="valor_entrada" value="0,00"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Valor a Parcelar</label>
                    <input type="text" readonly id="parcelado-display"
                           value="R$ <?= number_format($total_divida, 2, ',', '.') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-gray-50 text-gray-500 focus:outline-none">
                </div>

                <div>
                    <label for="num_parcelas" class="block text-sm font-medium text-gray-700 mb-2">
                        N de Parcelas <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="num_parcelas" name="num_parcelas" value="3" min="1" max="24" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div>
                    <label for="dia_vencimento" class="block text-sm font-medium text-gray-700 mb-2">
                        Dia de Vencimento <span class="text-red-500">*</span>
                    </label>
                    <input type="number" id="dia_vencimento" name="dia_vencimento" value="10" min="1" max="31" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Mês/Ano Início</label>
                    <div class="grid grid-cols-2 gap-3">
                        <input type="number" name="mes_inicio" value="<?= date('n') ?>" min="1" max="12"
                               placeholder="Mês"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <input type="number" name="ano_inicio" value="<?= date('Y') ?>" min="2020" max="2040"
                               placeholder="Ano"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>

                <div class="md:col-span-2">
                    <label for="observacoes" class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="3"
                              placeholder="Condições acordadas com o responsável..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                </div>
            </div>
        </section>

        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200">
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>"
                   class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    <i class="fa-solid fa-check mr-2"></i>
                    Criar Renegociação
                </button>
            </div>
        </div>
    </form>
</div>

<script>
document.getElementById('entrada-input').addEventListener('input', function() {
    const total = <?= $total_divida ?>;
    const entrada = parseFloat(this.value.replace(',', '.')) || 0;
    const parcelado = Math.max(0, total - entrada);
    document.getElementById('parcelado-display').value = 'R$ ' + parcelado.toFixed(2).replace('.', ',');
});
</script>
