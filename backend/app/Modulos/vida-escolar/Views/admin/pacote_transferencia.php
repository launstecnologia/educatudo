<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$aluno = is_array($aluno ?? null) ? $aluno : [];
$unidade = is_array($unidade ?? null) ? $unidade : [];
$matricula = is_array($matricula ?? null) ? $matricula : [];
$capa = is_array($capa ?? null) ? $capa : [];
$quadro = is_array($quadro ?? null) ? $quadro : [];
$grid = is_array($quadro['grid'] ?? null) ? $quadro['grid'] : [];
$ficha = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : [];
$traj = is_array($trajetoria['anos'] ?? null) ? $trajetoria['anos'] : [];
$periodos = is_array($periodos ?? null) ? $periodos : [1 => '1º', 2 => '2º', 3 => '3º', 4 => '4º', 0 => 'FINAL'];
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
$dataBr = static function ($v): string {
    $s = trim((string) $v);
    if ($s === '' || $s === '0000-00-00') {
        return '—';
    }
    $ts = strtotime($s);
    return $ts ? date('d/m/Y', $ts) : $s;
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Pacote de transferência — <?= $esc($aluno['nome'] ?? '') ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 4px; text-align: center; }
        h2 { font-size: 13px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th, td { border: 1px solid #ccc; padding: 4px 5px; }
        th { background: #f3f4f6; font-size: 10px; }
        .muted { color: #555; font-size: 10px; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print muted"><button onclick="window.print()">Imprimir / salvar PDF</button></p>
    <h1><?= $esc($unidade['nome'] ?? 'Escola') ?></h1>
    <p class="muted" style="text-align:center">INEP <?= $esc($unidade['inep'] ?? '—') ?> · CNPJ <?= $esc($unidade['cnpj'] ?? '—') ?></p>
    <h1>Pacote de transferência</h1>
    <p class="muted" style="text-align:center"><?= $esc($aluno['nome'] ?? '') ?> · gerado em <?= $esc($gerado_em ?? '') ?></p>

    <h2>1. Identificação</h2>
    <table>
        <tr><th>Nome</th><td><?= $esc($aluno['nome'] ?? '') ?></td><th>CPF</th><td><?= $esc($aluno['cpf'] ?? '—') ?></td></tr>
        <tr><th>Nascimento</th><td><?= $esc($dataBr($aluno['data_nasc'] ?? null)) ?></td><th>Filiação</th><td><?= $esc(trim(($aluno['nome_mae'] ?? '') . ' / ' . ($aluno['nome_pai'] ?? ''), ' /')) ?></td></tr>
        <tr><th>Turma / série</th><td><?= $esc(($aluno['turma_nome'] ?? '') . ' · ' . ($aluno['turma_serie'] ?? '')) ?></td><th>Situação</th><td><?= $esc($capa['situacao'] ?? '') ?></td></tr>
        <tr><th>Entrada</th><td><?= $esc($dataBr($matricula['data_entrada'] ?? null)) ?></td><th>Saída</th><td><?= $esc($dataBr($matricula['data_saida'] ?? null)) ?></td></tr>
    </table>
    <p class="muted">Períodos fechados constam como definitivos. O ano em curso (ficha <?= $esc($ficha['status'] ?? 'sem ficha') ?>) deve ser tratado como parcial se ainda não homologado.</p>

    <h2>2. Trajetória</h2>
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
        <?php if ($traj === []): ?>
        <tr><td colspan="5">Sem anos de escolarização registrados.</td></tr>
        <?php endif; ?>
    </table>

    <h2>3. Boletim do ano (parcial ou homologado)</h2>
    <table>
        <tr>
            <th rowspan="2">Componente</th>
            <?php foreach ([1, 2, 3, 4, 0] as $p): ?>
                <th colspan="2"><?= $esc($periodos[$p] ?? $p) ?></th>
            <?php endforeach; ?>
        </tr>
        <tr>
            <?php foreach ([1, 2, 3, 4, 0] as $p): ?>
                <th>Nota</th><th>Falta</th>
            <?php endforeach; ?>
        </tr>
        <?php foreach ($grid as $row): ?>
        <tr>
            <td><?= $esc($row['linha']['componente_nome'] ?? '') ?></td>
            <?php foreach ([1, 2, 3, 4, 0] as $p):
                $c = $row['celulas'][$p] ?? null;
            ?>
                <td style="text-align:center"><?= $esc($fmt($c)) ?></td>
                <td style="text-align:center"><?= isset($c['faltas']) && $c['faltas'] !== null ? (int) $c['faltas'] : '—' ?></td>
            <?php endforeach; ?>
        </tr>
        <?php endforeach; ?>
        <?php if ($grid === []): ?>
        <tr><td colspan="11">Sem ficha de boletim para este ano.</td></tr>
        <?php endif; ?>
    </table>
    <p class="muted">Emita também o Histórico Escolar oficial e a Ficha Individual no prontuário. Débito financeiro não impede a expedição destes documentos acadêmicos.</p>
</body>
</html>
