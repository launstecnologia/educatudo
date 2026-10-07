<?php
/** Consolidado final da turma. Variáveis: $payload, $vars, $esc. */
$payload = is_array($payload ?? null) ? $payload : [];
$vars = is_array($vars ?? null) ? $vars : [];
$tabela = $vars['tabela_html'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ata de resultados finais</title>
    <?php $timbre_parte = 'css'; include __DIR__ . '/_timbre.php'; ?>
</head>
<body>
    <?php $timbre_parte = 'cabecalho'; include __DIR__ . '/_timbre.php'; ?>
    <?= $tabela ?>
    <?php $timbre_parte = 'rodape'; include __DIR__ . '/_timbre.php'; ?>
</body>
</html>
