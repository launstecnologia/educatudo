<?php
$escola = $escola ?? [];
$resumo = $resumo ?? [];
$historico = $historico ?? [];
$csrf_token = $csrf_token ?? '';
$flash = $flash ?? null;
$escolaId = (int) ($escola['id'] ?? $resumo['escola_id'] ?? 0);
$fmtMoeda = static function ($valor): string {
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
};
?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <div class="flex justify-between items-start gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1"><?= htmlspecialchars((string) ($escola['nome'] ?? 'Escola')) ?></h2>
            <p class="text-slate-600 text-sm">Mensalidade da plataforma com base nos usuários pagantes.</p>
        </div>
        <a href="<?= URL ?>/master/mensalidade" class="text-slate-600 hover:text-slate-900 text-sm font-medium">← Voltar</a>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Alunos pagantes</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= number_format((int) ($resumo['alunos_pagantes'] ?? 0), 0, ',', '.') ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= number_format((int) ($resumo['alunos_ativos'] ?? 0), 0, ',', '.') ?> ativos</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Professores pagantes</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= number_format((int) ($resumo['professores_pagantes'] ?? 0), 0, ',', '.') ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Valor por usuário</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= htmlspecialchars($fmtMoeda($resumo['valor_por_usuario'] ?? 0)) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Total estimado</p>
        <p class="text-2xl font-bold text-green-600 mt-1"><?= htmlspecialchars($fmtMoeda($resumo['valor_total'] ?? 0)) ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= (int) ($resumo['total_pagantes'] ?? 0) ?> usuários pagantes</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 mb-6">
    <h3 class="text-lg font-semibold text-slate-900 mb-1">Definir valor por usuário</h3>
    <p class="text-sm text-slate-500 mb-4">Este valor era configurado na escola e agora pode ser definido aqui. Ele é multiplicado pelos alunos e professores pagantes.</p>
    <form method="post" action="<?= URL ?>/master/mensalidade/salvar" class="flex flex-wrap items-end gap-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
        <input type="hidden" name="escola_id" value="<?= $escolaId ?>">
        <input type="hidden" name="origem" value="escola">
        <div class="min-w-[200px]">
            <label for="valor_por_usuario" class="block text-sm font-medium text-slate-700 mb-1.5">Valor (R$)</label>
            <input type="number" id="valor_por_usuario" name="valor_por_usuario" step="0.01" min="0"
                   value="<?= htmlspecialchars(number_format((float) ($resumo['valor_por_usuario'] ?? 0), 2, '.', '')) ?>"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <button type="submit" class="px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
            Salvar valor
        </button>
    </form>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Faturas geradas</h3>
        <p class="text-sm text-slate-500 mt-0.5">Histórico mensal da cobrança desta escola.</p>
    </div>
    <?php if (empty($historico)): ?>
    <div class="px-6 py-10 text-center text-sm text-slate-500">Nenhuma fatura mensal gerada ainda. O total estimado acima usa a contagem atual de pagantes.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Mês</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Alunos</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Professores</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Valor / usuário</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Total</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Vencimento</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($historico as $fatura): ?>
            <?php $status = (string) ($fatura['status'] ?? ''); ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-4 text-sm text-slate-900"><?= !empty($fatura['mes_referencia']) ? htmlspecialchars(date('m/Y', strtotime((string) $fatura['mes_referencia']))) : '—' ?></td>
                <td class="px-6 py-4 text-sm text-slate-700"><?= number_format((int) ($fatura['total_alunos_pagantes'] ?? 0), 0, ',', '.') ?></td>
                <td class="px-6 py-4 text-sm text-slate-700"><?= number_format((int) ($fatura['total_professores_pagantes'] ?? 0), 0, ',', '.') ?></td>
                <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars($fmtMoeda($fatura['valor_por_usuario'] ?? 0)) ?></td>
                <td class="px-6 py-4 text-sm font-semibold text-green-700"><?= htmlspecialchars($fmtMoeda($fatura['valor_total'] ?? 0)) ?></td>
                <td class="px-6 py-4 text-sm text-slate-600"><?= !empty($fatura['data_vencimento']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $fatura['data_vencimento']))) : '—' ?></td>
                <td class="px-6 py-4 text-sm">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold <?= $status === 'pago' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                        <?= $status === 'pago' ? 'Pago' : 'Em aberto' ?>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>
