<?php
/** Fallback PHP do boletim oficial. Variáveis: $payload, $vars, $esc. */
$payload = is_array($payload ?? null) ? $payload : [];
$vars = is_array($vars ?? null) ? $vars : [];
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$quadro = $vars['quadro_notas_html'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Boletim Escolar</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        h2 { font-size: 13px; margin: 6px 0 12px; text-align: center; }
        p { margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        th, td.label { background: #f3f4f6; font-size: 10px; }
        .muted { color: #555; font-size: 10px; text-align: center; }
    </style>
</head>
<body>
    <h1><?= $vars['escola_nome'] ?? 'Escola' ?></h1>
    <h2>Boletim Escolar</h2>
    <p class="muted"><?= $vars['aluno_nome'] ?? '' ?> · <?= $vars['turma_nome'] ?? '' ?> · <?= $vars['ano_letivo'] ?? '' ?></p>
    <p><strong>Situação:</strong> <?= $vars['situacao_final'] ?? '—' ?>
        &nbsp;|&nbsp; <strong>Frequência:</strong> <?= $vars['frequencia_percentual'] ?? '—' ?></p>
    <?= $quadro ?>
    <p class="muted" style="margin-top:20px;">Emitido em <?= $vars['data_hoje'] ?? date('d/m/Y') ?> · <?= $vars['assinante_nome'] ?? '' ?> (<?= $vars['assinante_cargo'] ?? 'Direção' ?>)</p>
</body>
</html>
