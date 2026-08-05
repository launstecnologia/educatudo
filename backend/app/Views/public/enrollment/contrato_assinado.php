<?php
$esc     = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$fmtDt   = fn($d) => $d ? date('d/m/Y H:i', strtotime($d)) : '—';
$tipoLabel = ['nova' => 'Matrícula', 'rematricula' => 'Rematrícula', 'transferencia' => 'Transferência'];
$tipo    = $tipoLabel[$enrollment['tipo'] ?? 'nova'] ?? 'Matrícula';
?>

<div class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="bg-green-600 text-white px-6 py-8 text-center">
        <div class="w-16 h-16 bg-white/20 rounded-full flex items-center justify-center mx-auto mb-4">
            <i class="fa-solid fa-check text-white text-3xl"></i>
        </div>
        <h1 class="font-bold text-2xl">Contrato Assinado!</h1>
        <p class="text-green-100 mt-1">Sua <?= $esc($tipo) ?> foi confirmada com sucesso.</p>
    </div>

    <div class="p-6 space-y-4">
        <div class="bg-gray-50 rounded-xl p-4 space-y-2 text-sm">
            <div class="flex justify-between">
                <span class="text-gray-500">Aluno(a)</span>
                <span class="font-medium text-gray-800"><?= $esc($enrollment['aluno_nome']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Turma</span>
                <span class="font-medium text-gray-800"><?= $esc($enrollment['turma_nome'] ?? '—') ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Ano letivo</span>
                <span class="font-medium text-gray-800"><?= $esc($enrollment['ano_letivo_nome'] ?? date('Y')) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Assinado por</span>
                <span class="font-medium text-gray-800"><?= $esc($enrollment['assinante_nome'] ?? $enrollment['resp_nome']) ?></span>
            </div>
            <div class="flex justify-between">
                <span class="text-gray-500">Data da assinatura</span>
                <span class="font-medium text-gray-800"><?= $fmtDt($enrollment['assinado_em']) ?></span>
            </div>
        </div>

        <p class="text-center text-sm text-gray-500">
            Guarde esta tela como comprovante. A secretaria entrará em contato com os próximos passos.
        </p>
    </div>
</div>
