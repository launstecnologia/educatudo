<?php
/** Conteúdo de envio da carteirinha, dentro do painel. Variáveis: $cartoes, $escola, $pdfUrl */
$cartoes = is_array($cartoes ?? null) ? $cartoes : [];
$escola = (string) ($escola ?? 'EducaTudo');
$pdfUrl = (string) ($pdfUrl ?? '');
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uma = count($cartoes) === 1;
$cartaoUnico = $uma ? $cartoes[0] : [];
$urlCelular = (string) ($cartaoUnico['url_celular'] ?? '');
$qrCelular = (string) ($cartaoUnico['qr_celular'] ?? '');
$nomeUnico = (string) ($cartaoUnico['nome'] ?? '');
$titulo = $uma
    ? ($nomeUnico !== '' ? $nomeUnico : 'Carteirinha')
    : (count($cartoes) . ' carteirinhas');
$whatsapp = '';
if ($uma && $urlCelular !== '') {
    $texto = 'Carteirinha de ' . ($nomeUnico !== '' ? $nomeUnico : 'aluno')
        . ' — ' . $escola
        . '. Abra no celular: ' . $urlCelular;
    $whatsapp = 'https://wa.me/?text=' . rawurlencode($texto);
}
?>
<style>
.carteirinha-envio-acoes{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:16px}
.carteirinha-envio-btn{display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 14px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;font-size:14px;font-weight:600;text-decoration:none;cursor:pointer}
.carteirinha-envio-btn-forte{background:#0f766e;border-color:#0f766e;color:#fff}
.carteirinha-envio-btn-wa{background:#128C7E;border-color:#128C7E;color:#fff}
.carteirinha-envio-folha{display:flex;flex-wrap:wrap;gap:24px;align-items:flex-start}
.carteirinha-envio-cartoes{display:flex;flex-wrap:wrap;gap:16px;flex:1 1 320px;min-width:0;width:100%}
.carteirinha-envio-cartoes-um{flex:1 1 400px;max-width:400px;min-width:min(100%,400px)}
.carteirinha-envio-painel{flex:0 1 280px;width:min(100%,280px);max-width:100%;box-sizing:border-box;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;text-align:center}
.carteirinha-envio-painel h2{margin:0;font-size:16px;color:#0f172a}
.carteirinha-envio-painel p{margin:8px 0 0;font-size:13px;line-height:1.45;color:#475569}
.carteirinha-envio-painel img{width:200px;height:200px;margin-top:14px}
.carteirinha-envio-codigo{margin-top:8px;font-size:12px;letter-spacing:.08em;color:#64748b}
@media print{
  body.carteirinha-imprimindo *{visibility:hidden}
  body.carteirinha-imprimindo .carteirinha-envio-cartoes,
  body.carteirinha-imprimindo .carteirinha-envio-cartoes *{visibility:visible}
  body.carteirinha-imprimindo .carteirinha-envio-cartoes{position:absolute;left:0;top:0}
  body.carteirinha-imprimindo .carteirinha-envio-acoes,
  body.carteirinha-imprimindo .carteirinha-envio-painel{display:none !important}
}
</style>
<div class="carteirinha-envio">
    <div class="carteirinha-envio-acoes">
        <?php if ($pdfUrl !== ''): ?>
        <a class="carteirinha-envio-btn" href="<?= $e($pdfUrl) ?>">Baixar PDF</a>
        <?php endif; ?>
        <button type="button" class="carteirinha-envio-btn carteirinha-envio-btn-forte" onclick="imprimirCarteirinha()">Imprimir</button>
        <script>
        function imprimirCarteirinha() {
            document.body.classList.add('carteirinha-imprimindo');
            var limpar = function () {
                document.body.classList.remove('carteirinha-imprimindo');
                window.removeEventListener('afterprint', limpar);
            };
            window.addEventListener('afterprint', limpar);
            window.print();
        }
        </script>
        <?php if ($whatsapp !== ''): ?>
        <a class="carteirinha-envio-btn carteirinha-envio-btn-wa" href="<?= $e($whatsapp) ?>" target="_blank" rel="noopener">Enviar para WhatsApp</a>
        <?php endif; ?>
    </div>
    <div class="carteirinha-envio-folha">
        <section class="carteirinha-envio-cartoes<?= $uma ? ' carteirinha-envio-cartoes-um' : '' ?>">
            <?php foreach ($cartoes as $cartao): ?>
                <?php include __DIR__ . '/compartilhado/cartao.php'; ?>
            <?php endforeach; ?>
        </section>
        <?php if ($uma && $qrCelular !== ''): ?>
        <aside class="carteirinha-envio-painel">
            <h2>Abrir carteirinha no celular</h2>
            <p>Aponte a câmera. A carteirinha abre deitada, no mesmo formato do cartão. O QR impresso nela é o da portaria.</p>
            <img src="<?= $e($qrCelular) ?>" alt="QR para abrir a carteirinha no celular">
            <div class="carteirinha-envio-codigo"><?= $e($cartaoUnico['codigo'] ?? '') ?></div>
        </aside>
        <?php endif; ?>
    </div>
</div>
