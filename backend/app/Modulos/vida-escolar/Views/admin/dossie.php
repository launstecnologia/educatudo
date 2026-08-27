<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$aluno = is_array($aluno ?? null) ? $aluno : [];
$unidade = is_array($unidade ?? null) ? $unidade : [];
$capa = is_array($capa ?? null) ? $capa : [];
$sed = is_array($sed ?? null) ? $sed : ['itens' => []];
$inep = is_array($inep ?? null) ? $inep : [];
$docsChecklist = is_array($docs_checklist ?? null) ? $docs_checklist : ['itens' => []];
$traj = is_array($trajetoria['anos'] ?? null) ? $trajetoria['anos'] : [];
$planilha = is_array($planilha_sed ?? null) ? $planilha_sed : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Dossiê — <?= $esc($aluno['nome'] ?? '') ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        h2 { font-size: 13px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; }
        th { background: #f3f4f6; font-size: 10px; text-align: left; }
        .muted { color: #555; font-size: 10px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print muted"><button onclick="window.print()">Imprimir / salvar PDF</button></p>
    <h1><?= $esc($unidade['nome'] ?? 'Escola') ?></h1>
    <p class="muted" style="text-align:center">Dossiê individual · <?= $esc($aluno['nome'] ?? '') ?> · <?= $esc($gerado_em ?? '') ?></p>
    <p class="muted" style="text-align:center">
        Situação <?= $esc($capa['situacao'] ?? '') ?>
        · ficha <?= $esc($capa['status_ficha_label'] ?? '') ?>
        · documentos <?= $esc($capa['docs_txt'] ?? '') ?>
        · SED <?= $esc($capa['sed_txt'] ?? '') ?>
    </p>

    <h2>Identidade</h2>
    <table>
        <?php foreach ($planilha as $linha): ?>
        <tr><th style="width:40%"><?= $esc($linha['campo'] ?? '') ?></th><td><?= $esc($linha['valor'] ?? '') ?></td></tr>
        <?php endforeach; ?>
    </table>

    <h2>Trajetória</h2>
    <table>
        <tr><th>Ano</th><th>Série</th><th>Escola</th><th>Origem</th><th>Resultado</th></tr>
        <?php foreach ($traj as $ano): ?>
        <tr>
            <td><?= $esc($ano['ano_letivo'] ?? '') ?></td>
            <td><?= $esc($ano['serie_ano'] ?? '') ?></td>
            <td><?= $esc($ano['escola_nome'] ?? '—') ?></td>
            <td><?= $esc($ano['origem'] ?? '') ?></td>
            <td><?= $esc($ano['resultado'] ?? '—') ?></td>
        </tr>
        <?php endforeach; ?>
        <?php if ($traj === []): ?><tr><td colspan="5">Sem anos registrados.</td></tr><?php endif; ?>
    </table>

    <h2>Documentos de matrícula</h2>
    <table>
        <tr><th>Documento</th><th>Status</th></tr>
        <?php foreach ($docsChecklist['itens'] ?? [] as $item): ?>
        <tr><td><?= $esc($item['label'] ?? '') ?></td><td><?= $esc($item['status'] ?? '') ?></td></tr>
        <?php endforeach; ?>
    </table>

    <h2>SED — pendências</h2>
    <table>
        <tr><th>Campo</th><th>Situação</th></tr>
        <?php foreach ($sed['itens'] ?? [] as $item): ?>
        <tr><td><?= $esc($item['mensagem'] ?? '') ?></td><td><?= !empty($item['ok']) ? 'Ok' : 'Falta' ?></td></tr>
        <?php endforeach; ?>
    </table>

    <h2>Educacenso</h2>
    <p><?= $esc($inep['resumo'] ?? '') ?> · INEP escola <?= $esc($inep['codigo_escola'] ?? '—') ?> · INEP aluno <?= $esc($inep['codigo_aluno'] ?? '—') ?></p>
    <p class="muted">Documento de conferência interna. Não substitui o Histórico Escolar assinado nem o TXT do Educacenso.</p>
</body>
</html>
