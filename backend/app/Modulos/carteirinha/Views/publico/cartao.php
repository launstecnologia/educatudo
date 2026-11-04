<?php
/** Carteirinha no celular. Variável: $info */
$info = is_array($info ?? null) ? $info : [];
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$encontrado = !empty($info['encontrado']);
$uso = !empty($info['uso']);
$motivo = (string) ($info['motivo'] ?? '');
$escola = (string) ($info['escola'] ?? 'EducaTudo');
$logo = (string) ($info['logo'] ?? '');
$cartao = is_array($info['cartao'] ?? null) ? $info['cartao'] : [];
if (!$uso && $motivo === 'indisponivel') {
    $aviso = 'A carteirinha não está disponível agora.';
} elseif (!$uso) {
    $aviso = 'Esta escola não usa carteirinha.';
} else {
    $aviso = 'Não localizamos esta carteirinha.';
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Carteirinha · <?= $e($escola) ?></title>
<style>
body{margin:0;background:#e7edf3;color:#0f172a;font-family:ui-sans-serif,system-ui,-apple-system,sans-serif}
.pagina{min-height:100vh;display:flex;flex-direction:column;align-items:center;justify-content:center;padding:24px 16px;box-sizing:border-box}
.cartao-wrap{width:100%;max-width:520px}
.aviso{width:100%;max-width:420px;background:#fff;border:1px solid #e2e8f0;border-radius:16px;padding:28px 22px;text-align:center}
.aviso img{height:48px;width:auto;max-width:160px;object-fit:contain;margin:0 auto 12px;display:block}
.aviso h1{margin:0;font-size:18px}
.aviso p{margin:8px 0 0;font-size:14px;line-height:1.45;color:#475569}
.nota{width:100%;max-width:520px;margin:14px 0 0;text-align:center;font-size:13px;line-height:1.45;color:#64748b}
</style>
</head>
<body>
<div class="pagina">
    <?php if ($encontrado && $cartao !== []): ?>
        <div class="cartao-wrap">
            <?php include dirname(__DIR__) . '/compartilhado/cartao.php'; ?>
        </div>
        <p class="nota">Mostre o QR desta carteirinha na portaria. A leitura confirma a matrícula.</p>
    <?php else: ?>
        <div class="aviso">
            <?php if ($logo !== ''): ?>
                <img src="<?= $e($logo) ?>" alt="">
            <?php endif; ?>
            <h1><?= $e($escola) ?></h1>
            <p><?= $e($aviso) ?></p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
