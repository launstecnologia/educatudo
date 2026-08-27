<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$aluno = is_array($aluno ?? null) ? $aluno : [];
$unidade = is_array($unidade ?? null) ? $unidade : [];
$planilha = is_array($planilha_sed ?? null) ? $planilha_sed : [];
$sed = is_array($sed ?? null) ? $sed : ['itens' => []];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>SED — <?= $esc($aluno['nome'] ?? '') ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; margin: 24px; }
        h1 { font-size: 16px; margin: 0 0 8px; text-align: center; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 6px 8px; }
        th { background: #f3f4f6; text-align: left; width: 40%; }
        .muted { color: #555; font-size: 10px; text-align: center; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>
    <p class="no-print muted"><button onclick="window.print()">Imprimir / salvar PDF</button></p>
    <h1>Digitação assistida — SED / GDAE</h1>
    <p class="muted"><?= $esc($unidade['nome'] ?? '') ?> · <?= $esc($aluno['nome'] ?? '') ?> · <?= $esc($gerado_em ?? '') ?></p>
    <p class="muted">Copie os valores para o portal da Secretaria. Não há envio automático.</p>
    <table>
        <?php foreach ($planilha as $linha): ?>
        <tr>
            <th><?= $esc($linha['campo'] ?? '') ?></th>
            <td><?= $esc(($linha['valor'] ?? '') !== '' ? $linha['valor'] : '—') ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
    <p class="muted" style="margin-top:12px;text-align:left">
        Campos obrigatórios faltando:
        <?php
        $faltas = [];
        foreach ($sed['itens'] ?? [] as $item) {
            if (empty($item['ok']) && !empty($item['obrigatorio'])) {
                $faltas[] = (string) ($item['mensagem'] ?? '');
            }
        }
        echo $faltas === [] ? 'nenhum.' : $esc(implode('; ', $faltas));
        ?>
    </p>
</body>
</html>
