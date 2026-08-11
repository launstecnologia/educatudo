<?php
if (!class_exists('CreditosDecimalHelper')) {
    require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php';
}
$wallet = $wallet ?? ['saldo_total' => 0, 'saldo_escola' => 0, 'saldo_comprado' => 0];
$pacotes = $pacotes ?? [];
$movimentacoes = $movimentacoes ?? [];
$modo_pool = !empty($modo_pool);
$csrf_token = $csrf_token ?? '';
?>
<div class="max-w-6xl mx-auto">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">TudiCoins da Escola</h1>
            <p class="text-gray-600 mt-1">Saldo institucional para consumo de IA (EducaInclui, pool, etc.).</p>
        </div>
        <a href="<?= URL ?>/admin/creditos/pacotes" class="text-sm text-indigo-600 hover:underline">Gerenciar pacotes</a>
    </div>

    <?php if ($modo_pool): ?>
    <div class="mb-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
        Modo pool ativo: o consumo de alunos/professores debita esta carteira.
    </div>
    <?php endif; ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Saldo total</p>
            <p class="text-2xl font-bold text-gray-900 mt-1"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay((float) ($wallet['saldo_total'] ?? 0))) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Cota / cortesia</p>
            <p class="text-2xl font-bold text-slate-800 mt-1"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay((float) ($wallet['saldo_escola'] ?? 0))) ?></p>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-5">
            <p class="text-xs font-medium text-gray-500 uppercase">Comprado</p>
            <p class="text-2xl font-bold text-indigo-700 mt-1"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay((float) ($wallet['saldo_comprado'] ?? 0))) ?></p>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6 mb-8">
        <div class="xl:col-span-2 bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Comprar pacote (Asaas)</h2>
                <p class="text-sm text-gray-500 mt-1">O crédito entra no saldo comprado da escola.</p>
            </div>
            <div class="p-6">
                <?php if (empty($pacotes)): ?>
                <p class="text-sm text-gray-500">Nenhum pacote ativo. Cadastre em Pacotes de TudiCoins ou peça ao Master para vincular o catálogo.</p>
                <?php else: ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <?php foreach ($pacotes as $pk): ?>
                    <form method="post" action="<?= URL ?>/admin/tudicoins/comprar" class="rounded-xl border border-gray-200 p-4 hover:border-indigo-300 transition-colors">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                        <input type="hidden" name="pacote_id" value="<?= (int) ($pk['id'] ?? 0) ?>">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars($pk['nome'] ?? '') ?></p>
                        <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay(\CreditosDecimalHelper::fromScalar($pk['creditos'] ?? 0, 0.0))) ?> TudiCoins</p>
                        <p class="text-lg font-bold text-indigo-700 mt-2">R$ <?= number_format(((int) ($pk['valor_centavos'] ?? 0)) / 100, 2, ',', '.') ?></p>
                        <button type="submit" class="mt-4 w-full px-4 py-2 rounded-lg bg-indigo-600 text-white text-sm font-medium hover:bg-indigo-700">Comprar</button>
                    </form>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Últimas movimentações</h2>
            </div>
            <div class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
                <?php if (empty($movimentacoes)): ?>
                <p class="p-4 text-sm text-gray-500">Sem movimentações ainda.</p>
                <?php else: ?>
                <?php foreach ($movimentacoes as $m): ?>
                <div class="px-4 py-3 text-sm">
                    <div class="flex justify-between gap-2">
                        <span class="font-medium text-gray-800"><?= htmlspecialchars((string) ($m['tipo'] ?? '')) ?></span>
                        <span class="<?= ((float) ($m['valor'] ?? 0)) < 0 ? 'text-red-600' : 'text-emerald-600' ?> font-semibold">
                            <?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay((float) ($m['valor'] ?? 0))) ?>
                        </span>
                    </div>
                    <p class="text-xs text-gray-500 mt-0.5">
                        <?= htmlspecialchars((string) ($m['modulo_key'] ?? '—')) ?>
                        · <?= htmlspecialchars((string) ($m['created_at'] ?? '')) ?>
                    </p>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
