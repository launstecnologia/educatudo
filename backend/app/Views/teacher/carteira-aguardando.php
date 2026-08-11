<?php
$compra = $compra ?? [];
$compraId = $compra['id'] ?? 0;
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
        <a href="<?= URL ?>/professor/carteira" class="text-sm text-gray-600 hover:underline">&larr; Voltar à carteira</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Aguardando pagamento</h1>
        <p class="text-gray-600 mt-1">Sua compra foi registrada. Conclua o pagamento para receber os créditos.</p>
    </div>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <p class="text-gray-700"><strong>Pacote:</strong> <?= htmlspecialchars($pacoteNome) ?></p>
        <p class="text-gray-700 mt-2"><strong>Créditos:</strong> <?= htmlspecialchars((string) $creditos) ?></p>
        <?php if (!empty($erro_pagamento)): ?>
        <div id="paymentStatusBox" class="mt-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <?= htmlspecialchars((string) $erro_pagamento) ?>
        </div>
        <?php else: ?>
        <div id="paymentStatusBox" class="mt-4 hidden rounded-lg border px-4 py-3 text-sm"></div>
        <?php endif; ?>
        <?php if (!empty($pix_checkout)): ?>
        <div class="mt-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-sm font-semibold text-indigo-900">PIX gerado com sucesso</p>
            <?php if (!empty($pix_checkout['expirationDate'])): ?>
            <p class="text-xs text-indigo-700 mt-1">Expira em <?= htmlspecialchars((string) $pix_checkout['expirationDate']) ?></p>
            <?php endif; ?>
            <?php if (!empty($pix_checkout['encodedImage'])): ?>
            <div class="flex justify-center mt-4">
                <img alt="QR Code PIX" class="w-64 max-w-full rounded-xl border border-indigo-100 bg-white p-3" src="data:image/png;base64,<?= htmlspecialchars((string) $pix_checkout['encodedImage']) ?>">
            </div>
            <?php endif; ?>
            <p class="text-sm font-medium text-gray-800 mt-4">Copia e cola PIX</p>
            <textarea id="pixPayloadProfessor" readonly class="mt-2 w-full min-h-[120px] rounded-lg border border-gray-300 bg-white p-3 text-xs text-gray-700"><?= htmlspecialchars((string) ($pix_checkout['payload'] ?? '')) ?></textarea>
            <button type="button" class="mt-3 px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90" style="background-color: #4f46e5" onclick="navigator.clipboard.writeText(document.getElementById('pixPayloadProfessor').value).then(function(){ alert('Codigo PIX copiado.'); });">Copiar código PIX</button>
            <?php if (!empty($verificar_action) && !empty($csrf_token)): ?>
            <form method="post" action="<?= htmlspecialchars($verificar_action) ?>" class="mt-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="px-4 py-2 rounded-lg border border-green-300 bg-green-600 text-white text-sm font-medium hover:bg-green-700">Ja paguei, verificar agora</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php if (!empty($pagar_action) && !empty($csrf_token)): ?>
        <p class="text-gray-600 text-sm mt-4">Preencha os dados do pagador e escolha a forma de pagamento:</p>
        <form method="post" action="<?= htmlspecialchars($pagar_action) ?>" target="_blank" class="mt-4 space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div class="grid gap-4 md:grid-cols-2">
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Nome completo</span>
                    <input type="text" name="payer_nome" value="<?= htmlspecialchars((string) ($pagador['nome'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">E-mail</span>
                    <input type="email" name="payer_email" value="<?= htmlspecialchars((string) ($pagador['email'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" required>
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">CPF ou CNPJ</span>
                    <input type="text" name="payer_cpf_cnpj" value="<?= htmlspecialchars((string) ($pagador['cpf_cnpj'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="Somente números">
                </label>
                <label class="block">
                    <span class="text-sm font-medium text-gray-700">Telefone</span>
                    <input type="text" name="payer_phone" value="<?= htmlspecialchars((string) ($pagador['phone'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm" placeholder="DDD + número">
                </label>
            </div>
            <div class="flex flex-wrap gap-3">
                <input type="hidden" name="billing_type" value="PIX">
                <button type="submit" class="px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90" style="background-color: #4f46e5">Pagar com PIX</button>
                <button type="submit" name="billing_type" value="CREDIT_CARD" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-800 text-sm font-medium hover:bg-gray-50">Pagar com cartão</button>
            </div>
        </form>
        <p class="text-xs text-gray-500 mt-3">O PIX fica disponível nesta tela. O cartão abre diretamente no checkout seguro do Asaas.</p>
        <?php else: ?>
        <p class="text-amber-800 text-sm mt-4">Não foi possível iniciar o pagamento nesta compra.</p>
        <?php endif; ?>
    </div>
</div>
<?php if (!empty($pix_checkout) && !empty($status_action) && !empty($csrf_token)): ?>
<script>
(function () {
    const statusUrl = <?= json_encode($status_action) ?>;
    const csrfToken = <?= json_encode($csrf_token) ?>;
    const statusBox = document.getElementById('paymentStatusBox');
    let checking = false;
    let timerId = null;

    function setStatus(message, kind) {
        if (!statusBox) return;
        statusBox.classList.remove('hidden', 'border-red-200', 'bg-red-50', 'text-red-700', 'border-green-200', 'bg-green-50', 'text-green-700', 'border-blue-200', 'bg-blue-50', 'text-blue-700');
        if (kind === 'success') {
            statusBox.classList.add('border-green-200', 'bg-green-50', 'text-green-700');
        } else if (kind === 'info') {
            statusBox.classList.add('border-blue-200', 'bg-blue-50', 'text-blue-700');
        } else {
            statusBox.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
        }
        statusBox.textContent = message;
    }

    async function checkPayment() {
        if (checking) return;
        checking = true;
        try {
            const response = await fetch(statusUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: new URLSearchParams({ _token: csrfToken }).toString()
            });
            const data = await response.json();
            if (data.paid) {
                setStatus((data.message || 'Pagamento confirmado.') + ' Creditos adicionados: ' + (data.creditos || 0) + '.', 'success');
                if (timerId) clearInterval(timerId);
                setTimeout(function () {
                    window.location.href = data.redirect_url || <?= json_encode(URL . '/professor/carteira') ?>;
                }, 2000);
                return;
            }
            if (data.message) {
                setStatus(data.message, 'info');
            }
        } catch (error) {
            setStatus('Nao foi possivel consultar o pagamento agora. Vamos tentar novamente automaticamente.', 'info');
        } finally {
            checking = false;
        }
    }

    timerId = setInterval(checkPayment, 10000);
    setTimeout(checkPayment, 3000);
})();
</script>
<?php endif; ?>
