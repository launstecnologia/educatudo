<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

$statusBadge = match($installment['status']) {
    'pago'        => 'bg-green-100 text-green-800',
    'vencido'     => 'bg-red-100 text-red-800',
    'cancelado'   => 'bg-slate-100 text-slate-700',
    'renegociado' => 'bg-indigo-100 text-indigo-800',
    default       => 'bg-amber-100 text-amber-800',
};
?>

<!-- Cabeçalho -->
<div class="mb-6">
    <div class="flex items-start gap-4 flex-wrap">
        <a href="<?= URL ?>/admin/finance/contracts/<?= (int)$installment['contract_id'] ?>"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors flex-shrink-0"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= $esc($installment['descricao']) ?></h2>
            <p class="text-sm text-gray-600"><?= $esc($installment['aluno_nome'] ?? '') ?></p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <a href="<?= URL ?>/admin/finance/contracts/<?= (int)$installment['contract_id'] ?>"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Contrato
            </a>
            <a href="<?= URL ?>/admin/finance/installments/<?= (int)$installment['id'] ?>/boleto"
               target="_blank"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-barcode mr-2 text-gray-500"></i> Boleto
            </a>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

<!-- Dados da parcela -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 space-y-4">
    <h3 class="text-sm font-semibold text-gray-900">Dados da Parcela</h3>
    <dl class="space-y-3 text-sm">
        <div class="flex justify-between">
            <dt class="text-gray-500">Aluno</dt>
            <dd class="font-medium text-gray-900"><?= $esc($installment['aluno_nome'] ?? '') ?></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-500">Responsável</dt>
            <dd class="text-gray-900"><?= $esc($installment['responsavel_nome'] ?? '') ?></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-500">Vencimento</dt>
            <dd class="text-gray-900"><?= date('d/m/Y', strtotime($installment['data_vencimento'])) ?></dd>
        </div>
        <div class="flex justify-between">
            <dt class="text-gray-500">Valor cobrado</dt>
            <dd class="font-semibold text-gray-900"><?= $brl($installment['valor_cobrado']) ?></dd>
        </div>
        <?php if ($encargos['multa'] > 0): ?>
        <div class="flex justify-between text-red-600">
            <dt>Multa (2%)</dt>
            <dd><?= $brl($encargos['multa']) ?></dd>
        </div>
        <?php endif; ?>
        <?php if ($encargos['juros'] > 0): ?>
        <div class="flex justify-between text-red-600">
            <dt>Juros (<?= (int)$encargos['dias'] ?>d)</dt>
            <dd><?= $brl($encargos['juros']) ?></dd>
        </div>
        <?php endif; ?>
        <div class="flex justify-between border-t border-gray-100 pt-3 font-bold text-gray-900">
            <dt>Total a pagar</dt>
            <dd class="text-lg"><?= $brl($encargos['total']) ?></dd>
        </div>
    </dl>

    <?php if ($boleto_linha): ?>
    <div class="p-3 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-xs font-medium text-gray-500 mb-1">Linha Digitável (simulada)</p>
        <p class="font-mono text-sm text-gray-700 break-all"><?= $esc($boleto_linha) ?></p>
    </div>
    <?php endif; ?>

    <div>
        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $statusBadge ?>">
            <?= $esc(ucfirst($installment['status'])) ?>
        </span>
        <?php if ($installment['status'] === 'pago'): ?>
        <p class="text-xs text-gray-400 mt-2">
            Pago em <?= date('d/m/Y', strtotime($installment['data_pagamento'])) ?> &middot; <?= $brl($installment['valor_pago']) ?>
        </p>
        <?php endif; ?>
    </div>
</div>

<!-- Registrar pagamento / confirmação -->
<?php if (!in_array($installment['status'], ['pago','cancelado'])): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
    <h3 class="text-sm font-semibold text-gray-900 mb-4">Registrar Pagamento</h3>
    <form method="POST" action="<?= URL ?>/admin/finance/installments/<?= (int)$installment['id'] ?>/pay" class="space-y-4">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Valor pago <span class="text-red-500">*</span></label>
            <input type="text" name="valor_pago" required
                   value="<?= number_format($encargos['total'], 2, ',', '.') ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm font-mono text-lg">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Data de pagamento <span class="text-red-500">*</span></label>
            <input type="date" name="data_pagamento" required value="<?= date('Y-m-d') ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Forma de pagamento</label>
            <select name="forma_pagamento"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                <option value="pix">PIX</option>
                <option value="boleto">Boleto</option>
                <option value="dinheiro">Dinheiro</option>
                <option value="transferencia">Transferência</option>
                <option value="cartao">Cartão</option>
                <option value="outro">Outro</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Observações</label>
            <textarea name="observacoes" rows="2"
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm resize-none"></textarea>
        </div>
        <button type="submit"
                class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
            <i class="fa-solid fa-check mr-2"></i> Confirmar Baixa
        </button>
    </form>
</div>
<?php else: ?>
<div class="bg-green-50 border border-green-200 rounded-xl p-8 text-center">
    <i class="fa-solid fa-circle-check text-green-500 text-4xl mb-4 block"></i>
    <p class="font-semibold text-green-800 text-lg">Parcela quitada</p>
    <p class="text-sm text-green-600 mt-1">
        <?= $brl($installment['valor_pago'] ?? 0) ?> em <?= $installment['data_pagamento'] ? date('d/m/Y', strtotime($installment['data_pagamento'])) : '—' ?>
    </p>
</div>
<?php endif; ?>

</div>
