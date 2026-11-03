<?php
$habilitada = !empty($habilitada);
$schemaPronto = !empty($schema_pronto);
$cartao = is_array($cartao ?? null) ? $cartao : null;
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Carteirinha</h1>
    <p class="text-gray-600 mt-1">Mostre o QR na portaria. A leitura abre a confirmação da escola.</p>
</div>

<?php if (!$schemaPronto || !$habilitada): ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 p-8 text-center text-gray-600">
        A escola ainda não habilitou a carteirinha.
    </div>
<?php elseif ($cartao === null): ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 p-8 text-center text-gray-600">
        A secretaria ainda não emitiu a sua carteirinha.
    </div>
<?php else: ?>
    <div class="flex flex-col items-stretch gap-4 w-full max-w-[420px]">
        <?php include dirname(__DIR__) . '/compartilhado/cartao.php'; ?>
        <a href="<?= URL ?>/aluno/carteirinha/imprimir" class="inline-flex px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700">Abrir para impressão</a>
        <?php if (($cartao['situacao'] ?? '') !== 'valida'): ?>
            <p class="text-sm text-amber-800">Esta carteirinha não está válida. Procure a secretaria para emitir outra.</p>
        <?php endif; ?>
    </div>
<?php endif; ?>
