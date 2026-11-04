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
.carteirinha-envio-folha{display:flex;flex-direction:column;align-items:flex-start;gap:16px}
.carteirinha-envio-uma{width:min(100%,460px)}
.carteirinha-envio-cartoes{display:flex;flex-wrap:wrap;gap:16px;width:100%}
.carteirinha-envio-painel{width:100%;box-sizing:border-box;display:flex;align-items:center;gap:14px;text-align:left;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:12px 14px}
.carteirinha-envio-texto{min-width:0;flex:1}
.carteirinha-envio-painel h2{margin:0;font-size:15px;color:#0f172a}
.carteirinha-envio-painel p{margin:4px 0 0;font-size:13px;line-height:1.4;color:#475569}
.carteirinha-envio-painel img{width:96px;height:96px;margin:0;flex-shrink:0}
.carteirinha-envio-codigo{margin-top:6px;font-size:12px;letter-spacing:.08em;color:#64748b}
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
    <div class="carteirinha-envio-folha<?= $uma ? ' carteirinha-envio-uma' : '' ?>">
        <section class="carteirinha-envio-cartoes">
            <?php foreach ($cartoes as $cartao): ?>
                <?php include __DIR__ . '/compartilhado/cartao.php'; ?>
            <?php endforeach; ?>
        </section>
        <?php if ($uma && $qrCelular !== ''): ?>
        <aside class="carteirinha-envio-painel">
            <div class="carteirinha-envio-texto">
                <h2>Abrir carteirinha no celular</h2>
                <p>Aponte a câmera. O cartão abre deitado. O QR impresso nele é o da portaria.</p>
                <div class="carteirinha-envio-codigo"><?= $e($cartaoUnico['codigo'] ?? '') ?></div>
            </div>
            <img src="<?= $e($qrCelular) ?>" alt="QR para abrir a carteirinha no celular">
        </aside>
        <?php endif; ?>
    </div>
</div>
