<?php
$compra = $compra ?? [];
$compraId = (int) ($compra['id'] ?? 0);
$creditos = $compra['creditos'] ?? 0;
$pacoteNome = $compra['pacote_nome'] ?? '';
$pagar_action = $pagar_action ?? null;
$pix_checkout = $pix_checkout ?? null;
$erro_pagamento = $erro_pagamento ?? null;
$csrf_token = $csrf_token ?? null;
$pagador = $pagador ?? [];
$verificar_action = $verificar_action ?? null;
$status_action = $status_action ?? null;
?>
<div class="max-w-xl mx-auto">
    <div class="mb-6">
        <a href="<?= URL ?>/admin/tudicoins" class="text-sm text-gray-600 hover:underline">&larr; Voltar à carteira da escola</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Aguardando pagamento</h1>
        <p class="text-gray-600 mt-1">Conclua o pagamento para creditar TudiCoins na carteira da escola.</p>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <p class="text-gray-700"><strong>Pacote:</strong> <?= htmlspecialchars($pacoteNome) ?></p>
        <p class="text-gray-700 mt-2"><strong>TudiCoins:</strong> <?= htmlspecialchars((string) $creditos) ?></p>
        <?php if (!empty($erro_pagamento)): ?>
        <div id="paymentStatusBox" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <?= htmlspecialchars((string) $erro_pagamento) ?>
        </div>
        <?php else: ?>
        <div id="paymentStatusBox" class="mt-4 hidden rounded-lg border px-4 py-3 text-sm"></div>
        <?php endif; ?>
        <?php if (!empty($pix_checkout)): ?>
        <div class="mt-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-sm font-semibold text-indigo-900">PIX gerado</p>
            <?php if (!empty($pix_checkout['encodedImage'])): ?>
            <div class="flex justify-center mt-4">
                <img alt="QR Code PIX" class="w-64 max-w-full rounded-xl border border-indigo-100 bg-white p-3" src="data:image/png;base64,<?= htmlspecialchars((string) $pix_checkout['encodedImage']) ?>">
            </div>
            <?php endif; ?>
            <textarea id="pixPayloadEscola" readonly class="mt-4 w-full min-h-[120px] rounded-lg border border-gray-300 bg-white p-3 text-xs text-gray-700"><?= htmlspecialchars((string) ($pix_checkout['payload'] ?? '')) ?></textarea>
            <button type="button" class="mt-3 px-4 py-2 rounded-lg text-white text-sm font-medium" style="background-color:#4f46e5" onclick="navigator.clipboard.writeText(document.getElementById('pixPayloadEscola').value).then(function(){ alert('Código PIX copiado.'); });">Copiar PIX</button>
            <?php if (!empty($verificar_action) && !empty($csrf_token)): ?>
            <form method="post" action="<?= htmlspecialchars($verificar_action) ?>" class="mt-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="px-4 py-2 rounded-lg bg-green-600 text-white text-sm font-medium hover:bg-green-700">Já paguei, verificar</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($pagar_action) && !empty($csrf_token)): ?>
        <form method="post" action="<?= htmlspecialchars($pagar_action) ?>" class="mt-4 space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Nome / Razão social</span>
                    <input type="text" name="payer_nome" value="<?= htmlspecialchars((string) ($pagador['nome'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">E-mail</span>
                    <input type="email" name="payer_email" value="<?= htmlspecialchars((string) ($pagador['email'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">CPF ou CNPJ</span>
                    <input type="text" name="payer_cpf_cnpj" value="<?= htmlspecialchars((string) ($pagador['cpf_cnpj'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Telefone</span>
                    <input type="text" name="payer_phone" value="<?= htmlspecialchars((string) ($pagador['phone'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm">
                </label>
            </div>
            <div class="flex flex-wrap gap-3">
                <input type="hidden" name="billing_type" value="PIX">
                <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-medium" style="background-color:#4f46e5">Pagar com PIX</button>
                <button type="submit" name="billing_type" value="CREDIT_CARD" class="px-4 py-2 rounded-lg border border-gray-300 text-sm font-medium hover:bg-gray-50">Pagar com cartão</button>
            </div>
        </form>
        <?php endif; ?>
    </div>
</div>
<?php if (!empty($status_action)): ?>
<script>
(function() {
    var url = <?= json_encode($status_action) ?>;
    var box = document.getElementById('paymentStatusBox');
    setInterval(function() {
        fetch(url, { credentials: 'same-origin' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.status === 'paid') {
                    window.location.href = <?= json_encode(URL . '/admin/tudicoins') ?>;
                }
            }).catch(function() {});
    }, 8000);
})();
</script>
<?php endif; ?>
