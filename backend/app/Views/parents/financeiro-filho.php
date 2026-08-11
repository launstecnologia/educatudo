<?php
/** @var array $filho @var array $faturas */
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$dt  = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';

$statusCls = [
    'pago'     => 'bg-green-100 text-green-700',
    'pendente' => 'bg-amber-100 text-amber-700',
    'vencido'  => 'bg-red-100 text-red-700',
];
$statusLabel = ['pago' => 'Pago', 'pendente' => 'A pagar', 'vencido' => 'Vencido'];

$catLabel = [
    'mensalidade' => 'Mensalidade', 'matricula' => 'Matrícula', 'material' => 'Material',
    'uniforme' => 'Uniforme', 'passeio' => 'Passeio', 'ingresso' => 'Ingresso',
    'evento' => 'Evento', 'outros' => 'Outros',
];

$totPendente = array_sum(array_column(array_filter($faturas, fn($f) => in_array($f['status'], ['pendente','vencido'])), 'valor_total'));
$totPago     = array_sum(array_column(array_filter($faturas, fn($f) => $f['status'] === 'pago'), 'valor_total'));
$totalGeral  = array_sum(array_column($faturas, 'valor_total'));
?>

<div class="space-y-6 px-4 py-4">

    <!-- Header -->
    <div class="flex items-center gap-3">
        <a href="<?= URL ?>/pais/filhos/<?= (int)$filho['id'] ?>"
           class="inline-flex items-center justify-center w-9 h-9 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left text-sm"></i>
        </a>
        <div>
            <h2 class="text-lg font-bold text-gray-900">Financeiro</h2>
            <p class="text-sm text-gray-500"><?= $esc($filho['nome']) ?></p>
        </div>
    </div>

    <!-- Resumo -->
    <div class="grid grid-cols-3 gap-3">
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-4 text-center">
            <p class="text-xs font-medium text-gray-500 mb-1">Total</p>
            <p class="text-base font-bold text-gray-800"><?= $brl($totalGeral) ?></p>
        </div>
        <div class="bg-amber-50 rounded-xl border border-amber-200 shadow-sm p-4 text-center">
            <p class="text-xs font-medium text-amber-600 mb-1">A pagar</p>
            <p class="text-base font-bold text-amber-700"><?= $brl($totPendente) ?></p>
        </div>
        <div class="bg-green-50 rounded-xl border border-green-200 shadow-sm p-4 text-center">
            <p class="text-xs font-medium text-green-600 mb-1">Pago</p>
            <p class="text-base font-bold text-green-700"><?= $brl($totPago) ?></p>
        </div>
    </div>

    <!-- Lista de faturas -->
    <?php if (empty($faturas)): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-10 text-center">
        <i class="fa-solid fa-file-invoice text-4xl text-gray-200 mb-4 block"></i>
        <p class="text-gray-500">Nenhuma fatura disponível.</p>
    </div>
    <?php else: ?>

    <!-- Agrupar: pendentes primeiro, depois pagas -->
    <?php
    $pendentes = array_filter($faturas, fn($f) => in_array($f['status'], ['pendente','vencido']));
    $pagas     = array_filter($faturas, fn($f) => $f['status'] === 'pago');
    ?>

    <?php if (!empty($pendentes)): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-amber-500"></i>
                Em aberto (<?= count($pendentes) ?>)
            </h3>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($pendentes as $f): ?>
            <?php $st = $statusCls[$f['status']] ?? 'bg-gray-100 text-gray-600'; ?>
            <div class="px-4 py-4 flex items-start justify-between gap-3">
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 mb-0.5">
                        <?php if (!empty($f['tipo']) && $f['tipo'] === 'cobrança'): ?>
                        <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700">
                            <?= $esc($catLabel[$f['categoria'] ?? ''] ?? ucfirst($f['categoria'] ?? 'Extra')) ?>
                        </span>
                        <?php endif; ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $st ?>">
                            <?= $statusLabel[$f['status']] ?? ucfirst($f['status']) ?>
                        </span>
                    </div>
                    <p class="text-sm font-semibold text-gray-900 truncate"><?= $esc($f['descricao'] ?? ($catLabel[$f['categoria'] ?? ''] ?? 'Fatura')) ?></p>
                    <p class="text-xs text-gray-500 mt-0.5">Vence em <?= $dt($f['data_vencimento']) ?></p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-base font-bold text-gray-900"><?= $brl($f['valor_total']) ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($pagas)): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-4 py-3 border-b border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-green-500"></i>
                Pagos (<?= count($pagas) ?>)
            </h3>
        </div>
        <div class="divide-y divide-gray-100">
            <?php foreach ($pagas as $f): ?>
            <div class="px-4 py-3 flex items-start justify-between gap-3 opacity-75">
                <div class="flex-1 min-w-0">
                    <?php if (!empty($f['tipo']) && $f['tipo'] === 'cobrança'): ?>
                    <span class="inline-flex px-1.5 py-0.5 rounded text-xs font-medium bg-indigo-100 text-indigo-700 mb-1">
                        <?= $esc($catLabel[$f['categoria'] ?? ''] ?? ucfirst($f['categoria'] ?? 'Extra')) ?>
                    </span>
                    <?php endif; ?>
                    <p class="text-sm font-medium text-gray-700 truncate"><?= $esc($f['descricao'] ?? ($catLabel[$f['categoria'] ?? ''] ?? 'Fatura')) ?></p>
                    <p class="text-xs text-gray-400 mt-0.5">Pago em <?= $dt($f['data_pagamento']) ?></p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-semibold text-green-700"><?= $brl($f['valor_total']) ?></p>
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold bg-green-100 text-green-700">Pago</span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>
