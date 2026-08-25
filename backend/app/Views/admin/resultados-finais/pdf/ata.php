<?php
/** Fallback PHP da ata de resultados finais. Variáveis: $payload, $vars, $esc. */
$payload = is_array($payload ?? null) ? $payload : [];
$vars = is_array($vars ?? null) ? $vars : [];
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$turma = is_array($payload['turma'] ?? null) ? $payload['turma'] : [];
$tabela = $vars['tabela_html'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ata de Resultados Finais</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        h2 { font-size: 13px; margin: 8px 0 12px; text-align: center; }
        p { margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        th, td.label { background: #f3f4f6; font-size: 10px; }
        .muted { color: #555; font-size: 10px; text-align: center; }
        .assin { margin-top: 36px; }
        .assin td { border: none; text-align: center; padding-top: 28px; width: 50%; }
        .assin span { display: block; border-top: 1px solid #333; margin: 0 24px; padding-top: 4px; font-size: 10px; }
    </style>
</head>
<body>
    <h1><?= $vars['escola_nome'] ?? 'Escola' ?></h1>
    <p class="muted"><?= $vars['escola_docs'] ?? '' ?></p>
    <h2>Ata de Resultados Finais</h2>
    <p class="muted">Turma <?= $vars['turma_nome'] ?? $esc($turma['nome'] ?? '') ?> · <?= $vars['periodo_label'] ?? '' ?> / <?= $vars['ano_letivo'] ?? '' ?></p>

    <p>A direção registra os resultados finais dos estudantes da turma <strong><?= $vars['turma_nome'] ?? '' ?></strong>
        no período <strong><?= $vars['periodo_label'] ?? '' ?></strong> do ano letivo <strong><?= $vars['ano_letivo'] ?? '' ?></strong>.</p>

    <?= $tabela ?>

    <p style="margin-top:12px;">Total: <?= $vars['total_alunos'] ?? '0' ?> alunos · Homologados: <?= $vars['total_homologados'] ?? '0' ?> · Pendências: <?= $vars['total_pendencias'] ?? '0' ?></p>

    <table class="assin">
        <tr>
            <td><span><?= $vars['diretor_nome'] ?? 'Direção' ?><br>Direção</span></td>
            <td><span><?= $vars['secretario_nome'] ?? 'Secretaria' ?><br>Secretaria</span></td>
        </tr>
    </table>
    <p class="muted" style="margin-top:16px;">nº <?= $vars['numero'] ?? '' ?>/<?= $vars['ano'] ?? '' ?> · <?= $vars['data_hoje'] ?? date('d/m/Y') ?></p>
</body>
</html>
