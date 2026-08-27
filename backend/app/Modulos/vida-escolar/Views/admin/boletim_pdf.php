<?php
$quadro = is_array($quadro ?? null) ? $quadro : [];
$ficha = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : [];
$grid = is_array($quadro['grid'] ?? null) ? $quadro['grid'] : [];
$periodos = is_array($periodos ?? null) ? $periodos : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = static function ($c): string {
    if (!is_array($c)) {
        return '—';
    }
    if (!empty($c['conceito'])) {
        return (string) $c['conceito'];
    }
    if ($c['nota'] === null || $c['nota'] === '') {
        return '—';
    }
    return number_format((float) $c['nota'], 1, ',', '');
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Boletim Escolar</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        h2 { font-size: 13px; margin: 4px 0 12px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; }
        th { background: #f3f4f6; font-size: 10px; }
        .muted { color: #555; font-size: 10px; text-align: center; }
        .ass { margin-top: 36px; display: flex; justify-content: space-between; }
        .ass div { width: 45%; text-align: center; border-top: 1px solid #333; padding-top: 4px; font-size: 10px; }
    </style>
</head>
<body>
    <h1><?= $esc($ficha['turma_nome'] ?? 'Escola') ?></h1>
    <h2>Boletim Escolar</h2>
    <p class="muted"><?= $esc($ficha['aluno_nome'] ?? '') ?> · <?= (int) ($ficha['ano_letivo'] ?? 0) ?> · <?= $esc($ficha['serie_nome'] ?? $ficha['turma_serie'] ?? '') ?></p>
    <table>
        <thead>
            <tr>
                <th rowspan="2">Componentes curriculares</th>
                <?php foreach ([1, 2, 3, 4, 0] as $p): ?>
                    <th colspan="2"><?= $esc($periodos[$p] ?? $p) ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <?php foreach ([1, 2, 3, 4, 0] as $p): ?>
                    <th>Nota</th><th>Falta</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($grid as $row): ?>
            <tr>
                <td><?= $esc($row['linha']['componente_nome'] ?? '') ?></td>
                <?php foreach ([1, 2, 3, 4, 0] as $p):
                    $c = $row['celulas'][$p] ?? null;
                    $nota = $fmt($c);
                    if (is_array($c) && ($c['origem'] ?? '') === 'externa' && $nota !== '—') {
                        $nota .= '¹';
                    }
                ?>
                    <td style="text-align:center"><?= $esc($nota) ?></td>
                    <td style="text-align:center"><?= isset($c['faltas']) && $c['faltas'] !== null ? (int) $c['faltas'] : '—' ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="muted" style="margin-top:10px;text-align:left">¹ Resultado recebido da escola de origem.</p>
    <p class="muted">Status: <?= $esc($ficha['status'] ?? '') ?> · versão <?= (int) ($ficha['versao'] ?? 1) ?></p>
    <div class="ass">
        <div>Assinatura do responsável</div>
        <div>Assinatura da escola</div>
    </div>
</body>
</html>
