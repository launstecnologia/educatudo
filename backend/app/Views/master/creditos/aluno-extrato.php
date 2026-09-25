<?php
require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php';

$aluno = $aluno ?? [];
$escola = $escola ?? [];
$movimentacoes = $movimentacoes ?? [];
$mes = (string) ($mes ?? date('Y-m'));
$mesLabel = (string) ($mes_label ?? $mes);
$escolaId = (int) ($escola_id ?? 0);
$alunoId = (int) ($aluno_id ?? 0);
$entradas = (float) ($entradas ?? 0);
$saidas = (float) ($saidas ?? 0);
$saldo = (float) ($saldo ?? 0);
$baseUrl = URL . '/master/creditos/alunos/extrato';
$pdfUrl = $baseUrl . '/pdf?escola_id=' . $escolaId . '&aluno_id=' . $alunoId . '&mes=' . rawurlencode($mes);
$tipos = [
    'recarga_mensal' => 'Recarga mensal',
    'recarga_inicial' => 'TudiCoins iniciais',
    'cortesia' => 'Cortesia',
    'compra' => 'Compra',
    'consumo' => 'Consumo',
    'estorno' => 'Estorno',
    'recarga_plano' => 'Recarga plano',
];
?>
<div class="mb-6 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
    <div>
        <a href="<?= URL ?>/master/creditos/alunos" class="text-sm text-slate-500 hover:text-slate-800">Alunos TudiCoins</a>
        <h2 class="text-2xl font-bold text-slate-900 mt-1"><?= htmlspecialchars((string) ($aluno['nome'] ?? '')) ?></h2>
        <p class="text-slate-600 text-sm mt-1">
            <?= htmlspecialchars((string) ($escola['nome'] ?? '')) ?>
            <?php if (!empty($aluno['turma_nome'])): ?> · <?= htmlspecialchars((string) $aluno['turma_nome']) ?><?php endif; ?>
            <?php if (!empty($aluno['ra'])): ?> · RA <?= htmlspecialchars((string) $aluno['ra']) ?><?php endif; ?>
        </p>
    </div>
    <div class="flex flex-wrap items-end gap-2">
        <form method="get" action="<?= $baseUrl ?>" class="flex items-end gap-2">
            <input type="hidden" name="escola_id" value="<?= $escolaId ?>">
            <input type="hidden" name="aluno_id" value="<?= $alunoId ?>">
            <div>
                <label for="mes" class="block text-xs font-medium text-slate-600 mb-1">Mês</label>
                <input type="month" id="mes" name="mes" value="<?= htmlspecialchars($mes) ?>" class="px-3 py-2.5 border border-slate-300 rounded-lg text-sm bg-white">
            </div>
            <button type="submit" class="px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700">Ver mês</button>
        </form>
        <a href="<?= htmlspecialchars($pdfUrl) ?>" class="inline-flex items-center gap-2 px-4 py-2.5 border border-slate-300 text-sm font-medium text-slate-700 rounded-lg hover:bg-slate-50">
            <i class="fa-solid fa-file-pdf text-red-600"></i> Baixar PDF
        </a>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Entradas em <?= htmlspecialchars($mesLabel) ?></p>
        <p class="text-2xl font-bold text-green-600 mt-1"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay($entradas)) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Saídas em <?= htmlspecialchars($mesLabel) ?></p>
        <p class="text-2xl font-bold text-red-600 mt-1"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay($saidas)) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Saldo atual</p>
        <p class="text-2xl font-bold text-amber-600 mt-1"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay($saldo)) ?></p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Descrição</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Valor</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php if (empty($movimentacoes)): ?>
                <tr>
                    <td colspan="4" class="px-6 py-10 text-center text-sm text-slate-500">Nenhuma movimentação em <?= htmlspecialchars($mesLabel) ?>.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($movimentacoes as $mov): ?>
                <?php $valor = (float) ($mov['valor'] ?? 0); ?>
                <tr>
                    <td class="px-6 py-4 text-sm text-slate-600 whitespace-nowrap"><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($mov['created_at'] ?? 'now')))) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars($tipos[$mov['tipo'] ?? ''] ?? (string) ($mov['tipo'] ?? '—')) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-900"><?= htmlspecialchars((string) ($mov['exibicao']['label'] ?? '')) ?></td>
                    <td class="px-6 py-4 text-sm text-right tabular-nums font-medium <?= $valor >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                        <?= $valor >= 0 ? '+' : '' ?><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay($valor)) ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
