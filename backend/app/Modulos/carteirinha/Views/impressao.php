<?php
/** Impressão das carteirinhas. Variáveis: $cartoes, $escola */
$cartoes = is_array($cartoes ?? null) ? $cartoes : [];
$escola = (string) ($escola ?? 'EducaTudo');
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Carteirinhas · <?= $e($escola) ?></title>
<style>
body { margin: 0; background: #f3f4f6; font-family: ui-sans-serif, system-ui, sans-serif; }
.barra { display: flex; justify-content: space-between; align-items: center; gap: 12px; padding: 16px 20px; }
.grade { display: flex; flex-wrap: wrap; gap: 16px; padding: 0 16px 24px; }
@media screen and (max-width: 480px) {
    .barra { padding: 12px 16px; }
    .grade { padding: 0 12px 24px; }
}
button { background: #0f766e; color: #fff; border: 0; border-radius: 8px; padding: 8px 14px; font-weight: 600; cursor: pointer; }
@media print {
    .barra { display: none; }
    body { background: #fff; }
    .grade { padding: 0; gap: 8px; }
    .carteirinha-cartao { break-inside: avoid; box-shadow: none !important; }
}
</style>
</head>
<body>
<div class="barra">
    <strong><?= count($cartoes) === 1 ? '1 carteirinha' : count($cartoes) . ' carteirinhas' ?></strong>
    <button type="button" onclick="window.print()">Imprimir</button>
</div>
<div class="grade">
<?php foreach ($cartoes as $cartao): ?>
    <?php include __DIR__ . '/compartilhado/cartao.php'; ?>
<?php endforeach; ?>
</div>
</body>
</html>
