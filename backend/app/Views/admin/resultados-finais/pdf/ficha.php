<?php
/** Fallback PHP da ficha individual. Variáveis: $payload, $vars, $esc. */
$payload = is_array($payload ?? null) ? $payload : [];
$vars = is_array($vars ?? null) ? $vars : [];
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$aluno = is_array($payload['aluno'] ?? null) ? $payload['aluno'] : [];
$periodo = is_array($payload['periodo'] ?? null) ? $payload['periodo'] : [];
$avaliado = is_array($payload['avaliado'] ?? null) ? $payload['avaliado'] : [];
$quadro = $vars['quadro_notas_html'] ?? '';
$freqTxt = $vars['frequencia_percentual'] ?? '—';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ficha Individual</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; }
        h1 { font-size: 15px; margin: 0 0 2px; text-align: center; }
        h2 { font-size: 12px; margin: 14px 0 8px; }
        p { margin: 0 0 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; text-align: left; }
        th, td.label { background: #f3f4f6; font-size: 9px; }
        .muted { color: #555; font-size: 9px; text-align: center; }
        table.dados td.label { width: 28%; font-weight: bold; }
        table.comp td { font-size: 9px; }
    </style>
</head>
<body>
    <h1><?= $vars['escola_nome'] ?? 'Escola' ?></h1>
    <p class="muted"><?= $vars['escola_unidade'] ?? '' ?></p>
    <p class="muted"><?= $vars['escola_endereco'] ?? '' ?></p>
    <p class="muted"><?= $vars['escola_docs'] ?? '' ?></p>
    <h2 style="text-align:center;margin-top:8px;">Ficha Individual do Aluno</h2>
    <p class="muted">Ano letivo <?= $vars['ano_letivo'] ?? '' ?></p>

    <table class="dados">
        <tr><td class="label">Aluno(a)</td><td><?= $vars['aluno_nome'] ?? $esc($aluno['nome'] ?? '') ?></td></tr>
        <tr><td class="label">Matrícula / RA</td><td><?= $vars['aluno_codigo'] ?? '' ?></td></tr>
        <tr><td class="label">Nascimento</td><td><?= $vars['aluno_data_nasc'] ?? '—' ?></td></tr>
        <tr><td class="label">Curso / etapa</td><td><?= $vars['curso_nome'] ?? '—' ?></td></tr>
        <tr><td class="label">Série / ano</td><td><?= $vars['serie'] ?? '—' ?></td></tr>
        <tr><td class="label">Turma</td><td><?= $vars['turma_nome'] ?? '' ?></td></tr>
        <tr><td class="label">Turno</td><td><?= $vars['turno'] ?? '—' ?></td></tr>
        <tr><td class="label">Situação da matrícula</td><td><?= $vars['situacao_matricula'] ?? '—' ?></td></tr>
        <tr><td class="label">Frequência geral</td><td><?= $freqTxt ?></td></tr>
        <tr><td class="label">Resultado final</td><td><?= $vars['situacao_final'] ?? $esc($avaliado['rotulo'] ?? '—') ?></td></tr>
    </table>

    <h2>Componentes curriculares</h2>
    <?= $quadro ?>

    <h2>Observações</h2>
    <p><?= !empty($vars['observacoes']) ? $vars['observacoes'] : '—' ?></p>

    <p class="muted" style="margin-top:24px;">Emitido em <?= $vars['data_hoje'] ?? date('d/m/Y') ?> · nº <?= $vars['numero'] ?? '' ?>/<?= $vars['ano'] ?? '' ?></p>
</body>
</html>
