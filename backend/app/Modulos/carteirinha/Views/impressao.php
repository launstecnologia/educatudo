<?php
/** Tela de impressão e envio. Variáveis: $cartoes, $escola, $pdfUrl */
$cartoes = is_array($cartoes ?? null) ? $cartoes : [];
$escola = (string) ($escola ?? 'EducaTudo');
$pdfUrl = (string) ($pdfUrl ?? '');
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uma = count($cartoes) === 1;
$cartaoUnico = $uma ? $cartoes[0] : [];
$urlCelular = (string) ($cartaoUnico['url'] ?? '');
$qrCelular = (string) ($cartaoUnico['qr'] ?? '');
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
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= $e($titulo) ?> · <?= $e($escola) ?></title>
<style>
body{margin:0;background:#e7edf3;color:#0f172a;font-family:ui-sans-serif,system-ui,-apple-system,sans-serif}
.barra{position:sticky;top:0;z-index:2;display:flex;justify-content:space-between;align-items:center;gap:16px;flex-wrap:wrap;padding:16px 20px;background:#fff;border-bottom:1px solid #e2e8f0}
.olho{margin:0;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#64748b}
.barra h1{margin:2px 0 0;font-size:18px}
.acoes{display:flex;flex-wrap:wrap;gap:8px}
.btn{display:inline-flex;align-items:center;justify-content:center;height:40px;padding:0 14px;border-radius:10px;border:1px solid #cbd5e1;background:#fff;color:#0f172a;font-size:14px;font-weight:600;text-decoration:none;cursor:pointer}
.btn-forte{background:#0f766e;border-color:#0f766e;color:#fff}
.btn-wa{background:#128C7E;border-color:#128C7E;color:#fff}
.folha{max-width:920px;margin:0 auto;padding:28px 16px 48px;display:flex;flex-wrap:wrap;gap:28px;align-items:flex-start;justify-content:center}
.cartoes{display:flex;flex-wrap:wrap;gap:16px;justify-content:center}
.painel{width:min(100%,280px);background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:18px;text-align:center;box-shadow:0 10px 30px rgba(15,23,42,.06)}
.painel h2{margin:0;font-size:16px}
.painel p{margin:8px 0 0;font-size:13px;line-height:1.45;color:#475569}
.painel img{width:210px;height:210px;margin-top:14px}
.codigo{margin-top:8px;font-size:12px;letter-spacing:.08em;color:#64748b}
@media print{
  .barra,.painel{display:none !important}
  body{background:#fff}
  .folha{max-width:none;padding:0;display:block}
  .cartoes{display:block}
  .cartoes .carteirinha-cartao{margin:0 0 12px}
}
</style>
</head>
<body>
<header class="barra">
    <div>
        <p class="olho"><?= $e($escola) ?></p>
        <h1><?= $e($titulo) ?></h1>
    </div>
    <div class="acoes">
        <?php if ($pdfUrl !== ''): ?>
        <a class="btn" href="<?= $e($pdfUrl) ?>">Baixar PDF</a>
        <?php endif; ?>
        <button type="button" class="btn btn-forte" onclick="window.print()">Imprimir</button>
        <?php if ($whatsapp !== ''): ?>
        <a class="btn btn-wa" href="<?= $e($whatsapp) ?>" target="_blank" rel="noopener">Enviar para WhatsApp</a>
        <?php endif; ?>
    </div>
</header>
<main class="folha">
    <section class="cartoes">
        <?php foreach ($cartoes as $cartao): ?>
            <?php include __DIR__ . '/compartilhado/cartao.php'; ?>
        <?php endforeach; ?>
    </section>
    <?php if ($uma && $qrCelular !== ''): ?>
    <aside class="painel">
        <h2>Abrir no celular</h2>
        <p>Aponte a câmera para este QR. A carteirinha abre no celular, sem precisar entrar na plataforma.</p>
        <img src="<?= $e($qrCelular) ?>" alt="QR para abrir a carteirinha no celular">
        <div class="codigo"><?= $e($cartaoUnico['codigo'] ?? '') ?></div>
    </aside>
    <?php endif; ?>
</main>
</body>
</html>
