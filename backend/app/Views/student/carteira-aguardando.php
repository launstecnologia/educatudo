<?php
if (!class_exists('CreditosDecimalHelper')) {
    require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
}
$compra = $compra ?? [];
$compraId = $compra['id'] ?? 0;
$creditos = \CreditosDecimalHelper::fromScalar($compra['creditos'] ?? 0, 0.0);
$pacoteNome = $compra['pacote_nome'] ?? '';
$valorCentavos = (int) ($compra['valor_centavos'] ?? 0);
$valorReais = 'R$ ' . number_format($valorCentavos / 100, 2, ',', '.');
$pagar_action = $pagar_action ?? null;
$pix_checkout = $pix_checkout ?? null;
$erro_pagamento = $erro_pagamento ?? null;
$csrf_token = $csrf_token ?? null;
$pagador = $pagador ?? [];
$verificar_action = $verificar_action ?? null;
$status_action = $status_action ?? null;
$voltar_url = $voltar_url ?? (URL . '/educashop');
$voltar_label = $voltar_label ?? 'Voltar ao EducaShop';
?>
<div class="max-w-2xl mx-auto space-y-6">
    <div>
        <a href="<?= htmlspecialchars($voltar_url) ?>" class="text-sm text-gray-600 hover:underline inline-flex items-center gap-1">
            <i class="fa-solid fa-arrow-left text-xs"></i> <?= htmlspecialchars($voltar_label) ?>
        </a>
        <h1 class="text-2xl font-bold text-gray-900 mt-3">Finalizar compra</h1>
        <p class="text-gray-600 mt-1 text-sm">Pagamento seguro processado pelo <strong>Asaas</strong> — escolha PIX ou cartão.</p>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-6 py-4 bg-gray-50 border-b border-gray-200">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Resumo do pedido</p>
            <p class="text-lg font-bold text-gray-900 mt-1"><?= htmlspecialchars($pacoteNome) ?></p>
        </div>
        <div class="px-6 py-5 grid grid-cols-2 gap-4">
            <div>
                <p class="text-xs text-gray-500">TudiCoins</p>
                <p class="text-xl font-bold text-accent tabular-nums"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($creditos)) ?></p>
            </div>
            <div class="text-right">
                <p class="text-xs text-gray-500">Total</p>
                <p class="text-xl font-bold text-gray-900 tabular-nums"><?= htmlspecialchars($valorReais) ?></p>
            </div>
        </div>

        <?php if (!empty($erro_pagamento)): ?>
        <div class="mx-6 mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700" id="paymentStatusBox">
            <?= htmlspecialchars((string) $erro_pagamento) ?>
        </div>
        <?php else: ?>
        <div id="paymentStatusBox" class="mx-6 mb-4 hidden rounded-lg border px-4 py-3 text-sm"></div>
        <?php endif; ?>

        <?php if (!empty($pix_checkout)): ?>
        <div class="mx-6 mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4">
            <p class="text-sm font-semibold text-indigo-900"><i class="fa-brands fa-pix mr-1"></i> PIX gerado com sucesso</p>
            <?php if (!empty($pix_checkout['expirationDate'])): ?>
            <p class="text-xs text-indigo-700 mt-1">Expira em <?= htmlspecialchars((string) $pix_checkout['expirationDate']) ?></p>
            <?php endif; ?>
            <?php if (!empty($pix_checkout['encodedImage'])): ?>
            <div class="flex justify-center mt-4">
                <img alt="QR Code PIX" class="w-64 max-w-full rounded-xl border border-indigo-100 bg-white p-3" src="data:image/png;base64,<?= htmlspecialchars((string) $pix_checkout['encodedImage']) ?>">
            </div>
            <?php endif; ?>
            <p class="text-sm font-medium text-gray-800 mt-4">Copia e cola PIX</p>
            <textarea id="pixPayloadAluno" readonly class="mt-2 w-full min-h-[120px] rounded-lg border border-gray-300 bg-white p-3 text-xs text-gray-700"><?= htmlspecialchars((string) ($pix_checkout['payload'] ?? '')) ?></textarea>
            <button type="button" class="mt-3 px-4 py-2 rounded-lg bg-primary text-white text-sm font-medium hover:opacity-90" onclick="navigator.clipboard.writeText(document.getElementById('pixPayloadAluno').value).then(function(){ alert('Código PIX copiado.'); });">Copiar código PIX</button>
            <?php if (!empty($verificar_action) && !empty($csrf_token)): ?>
            <form method="post" action="<?= htmlspecialchars($verificar_action) ?>" class="mt-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit" class="px-4 py-2 rounded-lg border border-green-300 bg-green-600 text-white text-sm font-medium hover:bg-green-700">Já paguei, verificar agora</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!empty($pagar_action) && !empty($csrf_token)): ?>
        <div class="px-6 pb-6">
            <p class="text-sm font-medium text-gray-800 mb-3">Dados do pagador</p>
            <form method="post" action="<?= htmlspecialchars($pagar_action) ?>" target="_blank" class="space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="grid gap-4 sm:grid-cols-2">
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-gray-700">Nome completo</span>
                        <input type="text" name="payer_nome" value="<?= htmlspecialchars((string) ($pagador['nome'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">E-mail</span>
                        <input type="email" name="payer_email" value="<?= htmlspecialchars((string) ($pagador['email'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </label>
                    <label class="block">
                        <span class="text-sm font-medium text-gray-700">CPF ou CNPJ</span>
                        <input type="text" name="payer_cpf_cnpj" value="<?= htmlspecialchars((string) ($pagador['cpf_cnpj'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="Somente números">
                    </label>
                    <label class="block sm:col-span-2">
                        <span class="text-sm font-medium text-gray-700">Telefone</span>
                        <input type="text" name="payer_phone" value="<?= htmlspecialchars((string) ($pagador['phone'] ?? '')) ?>" class="mt-1 w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500" placeholder="DDD + número">
                    </label>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 pt-2">
                    <button type="submit" name="billing_type" value="PIX" class="inline-flex items-center justify-center gap-2 flex-1 px-4 py-3 rounded-xl bg-primary text-white text-sm font-semibold hover:opacity-90">
                        <i class="fa-brands fa-pix"></i> Pagar com PIX
                    </button>
                    <button type="submit" name="billing_type" value="CREDIT_CARD" class="inline-flex items-center justify-center gap-2 flex-1 px-4 py-3 rounded-xl border border-gray-300 text-gray-800 text-sm font-semibold hover:bg-gray-50">
                        <i class="fa-regular fa-credit-card"></i> Pagar com cartão
                    </button>
                </div>
            </form>
            <p class="text-xs text-gray-500 mt-3 flex items-start gap-2">
                <i class="fa-solid fa-lock mt-0.5 text-gray-400"></i>
                O PIX fica nesta tela. O cartão abre no checkout seguro do Asaas. Após a confirmação, os TudiCoins entram na sua carteira automaticamente.
            </p>
        </div>
        <?php else: ?>
        <p class="px-6 pb-6 text-amber-800 text-sm">Não foi possível iniciar o pagamento nesta compra.</p>
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
                setStatus((data.message || 'Pagamento confirmado.') + ' TudiCoins creditados na sua carteira.', 'success');
                if (timerId) clearInterval(timerId);
                setTimeout(function () {
                    window.location.href = data.redirect_url || <?= json_encode(URL . '/carteira') ?>;
                }, 2000);
                return;
            }
            if (data.message) {
                setStatus(data.message, 'info');
            }
        } catch (error) {
            setStatus('Não foi possível consultar o pagamento agora. Tentaremos novamente automaticamente.', 'info');
        } finally {
            checking = false;
        }
    }

    timerId = setInterval(checkPayment, 10000);
    setTimeout(checkPayment, 3000);
})();
</script>
<?php endif; ?>
