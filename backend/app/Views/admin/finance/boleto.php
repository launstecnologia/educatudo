<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

$page_header_title    = 'Boleto — ' . $installment['descricao'];
$page_header_subtitle = 'Simulado · Sem validade bancária real';
ob_start(); ?>
<a href="<?= URL ?>/admin/finance/installments/<?= (int)$installment['id'] ?>" class="btn-secondary text-sm">← Voltar</a>
<button onclick="window.print()" class="btn-primary text-sm"><i class="fa-solid fa-print mr-1"></i> Imprimir</button>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<div id="boleto" class="max-w-3xl mx-auto bg-white border border-gray-300 rounded-2xl shadow-sm overflow-hidden print:shadow-none print:rounded-none print:border-0">

    <!-- Cabeçalho -->
    <div class="bg-indigo-700 text-white px-6 py-4 flex items-center justify-between">
        <div>
            <p class="text-xs opacity-70 uppercase tracking-wide">Boleto Escolar Simulado</p>
            <p class="font-bold text-lg"><?= defined('TENANT_SLUG') ? strtoupper(TENANT_SLUG) : 'ESCOLA' ?></p>
        </div>
        <div class="text-right">
            <p class="text-xs opacity-70">Banco (simulado)</p>
            <p class="font-bold text-xl">001</p>
        </div>
    </div>

    <!-- Linha digitável -->
    <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
        <p class="text-xs text-gray-400 mb-1">Linha Digitável</p>
        <p class="font-mono text-base font-bold text-gray-900 tracking-widest break-all"><?= $esc($boleto_linha) ?></p>
    </div>

    <!-- Campos do boleto -->
    <div class="px-6 py-4 grid grid-cols-2 gap-4 text-sm border-b border-gray-200">
        <div>
            <p class="text-xs text-gray-400">Beneficiário</p>
            <p class="font-medium text-gray-900"><?= defined('TENANT_SLUG') ? strtoupper(TENANT_SLUG) : 'ESCOLA' ?></p>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-400">Data de Vencimento</p>
            <p class="font-bold text-gray-900 text-base"><?= date('d/m/Y', strtotime($installment['data_vencimento'])) ?></p>
        </div>
        <div>
            <p class="text-xs text-gray-400">Pagador</p>
            <p class="font-medium text-gray-900"><?= $esc($installment['responsavel_nome'] ?? '') ?></p>
            <?php if ($installment['aluno_nome']): ?>
            <p class="text-xs text-gray-500">Aluno: <?= $esc($installment['aluno_nome']) ?></p>
            <?php endif; ?>
        </div>
        <div class="text-right">
            <p class="text-xs text-gray-400">Valor do Documento</p>
            <p class="font-bold text-gray-900 text-xl"><?= $brl($installment['valor_cobrado']) ?></p>
        </div>
        <div class="col-span-2">
            <p class="text-xs text-gray-400">Descrição</p>
            <p class="text-gray-800"><?= $esc($installment['descricao']) ?></p>
        </div>
    </div>

    <!-- Encargos (se houver) -->
    <?php if ($encargos['juros'] > 0 || $encargos['multa'] > 0): ?>
    <div class="px-6 py-3 bg-red-50 border-b border-red-100 text-sm flex gap-6">
        <div><span class="text-red-500">Multa (2%)</span> <strong class="text-red-700"><?= $brl($encargos['multa']) ?></strong></div>
        <div><span class="text-red-500">Juros (<?= $encargos['dias'] ?> dias)</span> <strong class="text-red-700"><?= $brl($encargos['juros']) ?></strong></div>
        <div class="ml-auto"><span class="text-red-600 font-bold">Total com encargos: <?= $brl($encargos['total']) ?></span></div>
    </div>
    <?php endif; ?>

    <!-- Código de barras visual simulado -->
    <div class="px-6 py-8 flex flex-col items-center gap-3">
        <div class="flex gap-0.5">
            <?php
            $codigo = $boleto_codigo;
            for ($i = 0; $i < strlen($codigo); $i++) {
                $w = (int)$codigo[$i] % 3 + 1;
                $color = $i % 2 === 0 ? 'bg-gray-900' : 'bg-white';
                echo "<div class=\"{$color} h-16\" style=\"width:{$w}px\"></div>";
            }
            ?>
        </div>
        <p class="font-mono text-xs text-gray-400 tracking-widest"><?= chunk_split($boleto_codigo, 10, ' ') ?></p>
    </div>

    <!-- Rodapé -->
    <div class="px-6 py-4 bg-amber-50 border-t border-amber-100 text-center">
        <p class="text-xs text-amber-700 font-medium">⚠️ Este boleto é SIMULADO para fins de demonstração. Não possui validade bancária real.</p>
        <p class="text-xs text-amber-600 mt-1">Código: <?= $esc($boleto_codigo) ?></p>
    </div>
</div>

<style>
@media print {
    .btn-primary, .btn-secondary, nav, aside, header { display: none !important; }
    #boleto { border: 1px solid #000 !important; }
}
</style>
