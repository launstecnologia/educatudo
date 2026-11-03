<?php
$token = $token ?? '';
$mensagem = $mensagem ?? '';
$titulo = $titulo ?? 'Gabarito';
$valido = !empty($valido);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fotografar gabarito</title>
    <style>
        body { margin: 0; font-family: sans-serif; background: #f8fafc; color: #111; }
        main { max-width: 28rem; margin: 0 auto; padding: 24px 16px 40px; }
        h1 { font-size: 1.25rem; margin: 0 0 8px; }
        p { color: #475569; line-height: 1.45; }
        .aviso { background: #fff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 12px 14px; margin: 16px 0; }
        label.botao { display: block; text-align: center; background: #0f766e; color: #fff; font-weight: 700; border-radius: 12px; padding: 16px; margin-top: 16px; }
        input[type=file] { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); }
    </style>
</head>
<body>
<main>
    <h1>Fotografar gabarito</h1>
    <p><?= htmlspecialchars($titulo) ?></p>
    <?php if ($mensagem !== ''): ?>
    <div class="aviso"><?= htmlspecialchars($mensagem) ?></div>
    <?php endif; ?>
    <?php if ($valido): ?>
    <p>Apoie a folha numa mesa, com as quatro marcas pretas dos cantos aparecendo, e tire a foto.</p>
    <form method="post" action="<?= htmlspecialchars(rtrim((string) URL, '/') . '/captura-gabarito/' . $token) ?>" enctype="multipart/form-data">
        <label class="botao">
            Abrir a câmera
            <input type="file" name="foto" accept="image/*" capture="environment" required onchange="this.form.submit()">
        </label>
    </form>
    <?php endif; ?>
</main>
</body>
</html>
