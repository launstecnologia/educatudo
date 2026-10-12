<?php
$turma = isset($turma) && is_array($turma) ? $turma : [];
$headers = isset($headers) && is_array($headers) ? $headers : [];
$rows = isset($rows) && is_array($rows) ? $rows : [];
$geradoEm = isset($gerado_em) ? (string) $gerado_em : date('d/m/Y H:i');
$logoUrl = isset($logo_url) ? (string) $logo_url : '';
$orientacao = isset($orientacao) ? (string) $orientacao : 'vertical';
$totalAlunos = isset($total_alunos) ? (int) $total_alunos : count($rows);
$totalMasculino = isset($total_masculino) ? (int) $total_masculino : 0;
$totalFeminino = isset($total_feminino) ? (int) $total_feminino : 0;
$isLandscape = $orientacao === 'horizontal';
$fontSize = $isLandscape ? '10px' : '11px';
$cellPad = $isLandscape ? '4px 5px' : '6px 8px';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: <?= $fontSize ?>; color: #111; margin: 0; padding: 0; }
        .header { text-align: center; margin-bottom: 14px; }
        .header-logo { max-width: 220px; max-height: 72px; margin: 0 auto 10px; display: block; }
        h1 { font-size: <?= $isLandscape ? '16px' : '18px' ?>; margin: 0 0 4px; }
        .meta { font-size: 10px; color: #555; margin-bottom: 12px; text-align: center; }
        table { width: 100%; border-collapse: collapse; table-layout: fixed; }
        th, td { border: 1px solid #ccc; padding: <?= $cellPad ?>; text-align: left; vertical-align: middle; word-wrap: break-word; }
        th { background: #f3f4f6; font-size: <?= $isLandscape ? '9px' : '10px' ?>; text-transform: uppercase; }
        .col-assinatura { width: 90px; min-height: 28px; }
        .footnote { margin-top: 10px; font-size: 9px; color: #666; }
        .totais { margin-top: 12px; font-size: 10px; color: #333; }
        .totais span { margin-right: 16px; }
    </style>
</head>
<body>
    <div class="header">
        <?php if ($logoUrl !== ''): ?>
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="header-logo">
        <?php endif; ?>
        <h1>Lista de Chamada — <?= htmlspecialchars($turma['nome'] ?? '') ?></h1>
        <div class="meta">Gerado em <?= htmlspecialchars($geradoEm) ?></div>
    </div>
    <table>
        <thead>
            <tr>
                <?php foreach ($headers as $header): ?>
                    <?php
                    $isAssinatura = strcasecmp($header, 'Assinatura') === 0;
                    $thStyle = $isAssinatura ? ' style="width:90px"' : '';
                    ?>
                    <th<?= $thStyle ?>><?= htmlspecialchars($header) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php if ($rows === []): ?>
                <tr>
                    <td colspan="<?= max(1, count($headers)) ?>" style="text-align:center;color:#666;">Nenhum aluno na lista.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <?php foreach ($row as $idx => $cell): ?>
                            <?php
                            $headerLabel = $headers[$idx] ?? '';
                            $isAssinatura = strcasecmp($headerLabel, 'Assinatura') === 0;
                            $tdClass = $isAssinatura ? ' class="col-assinatura"' : '';
                            ?>
                            <td<?= $tdClass ?>><?= htmlspecialchars((string) $cell) ?></td>
                        <?php endforeach; ?>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <div class="totais">
        <span><strong>Total de alunos:</strong> <?= $totalAlunos ?></span>
        <span><strong>Total masculino:</strong> <?= $totalMasculino ?></span>
        <span><strong>Total feminino:</strong> <?= $totalFeminino ?></span>
    </div>
    <p class="footnote">Documento gerado pelo EducaTudo.</p>
</body>
</html>
