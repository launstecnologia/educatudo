<?php
require_once __DIR__ . '/../../Models/ConselhoSessao.php';

use App\Modulos\ConselhoClasse\Models\ConselhoSessao;
$sessao = is_array($sessao ?? null) ? $sessao : [];
$ata = is_array($ata ?? null) ? $ata : [];
$matriz = is_array($matriz ?? null) ? $matriz : [];
$escola = (string) ($escola ?? 'Escola');
$periodoLabel = (string) ($periodo_label ?? '');
$participantes = is_array($matriz['participantes'] ?? null) ? $matriz['participantes'] : [];
$linhas = is_array($matriz['linhas'] ?? null) ? $matriz['linhas'] : [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ata do Conselho de Classe</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 18px 0 6px; }
        p { margin: 0 0 8px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #ccc; padding: 5px 6px; text-align: left; }
        th { background: #f3f4f6; font-size: 11px; }
        .muted { color: #555; font-size: 11px; }
        .assin { margin-top: 36px; display: table; width: 100%; }
        .assin div { display: table-cell; width: 33%; text-align: center; padding-top: 28px; }
        .assin span { display: block; border-top: 1px solid #333; margin: 0 12px; padding-top: 4px; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Ata do Conselho de Classe</h1>
    <p class="muted"><?= htmlspecialchars($escola) ?> · gerada em <?= date('d/m/Y H:i') ?></p>
    <p>
        Turma: <strong><?= htmlspecialchars((string) ($sessao['turma_nome'] ?? '')) ?></strong><br>
        Período: <?= htmlspecialchars($periodoLabel) ?> / <?= (int) ($sessao['ano_letivo'] ?? 0) ?><br>
        Data da reunião: <?= !empty($sessao['data_reuniao']) ? date('d/m/Y', strtotime((string) $sessao['data_reuniao'])) : '—' ?>
    </p>

    <h2>Pauta</h2>
    <p><?= nl2br(htmlspecialchars((string) (($ata['pauta'] ?? '') ?: ($sessao['pauta'] ?? '—')))) ?></p>

    <h2>Participantes</h2>
    <table>
        <thead><tr><th>Nome</th><th>Cargo</th><th>Presente</th></tr></thead>
        <tbody>
        <?php foreach ($participantes as $p): ?>
            <tr>
                <td><?= htmlspecialchars((string) $p['nome']) ?></td>
                <td><?= htmlspecialchars(ConselhoSessao::CARGOS[$p['cargo']] ?? (string) $p['cargo']) ?></td>
                <td><?= !empty($p['presente']) ? 'Sim' : 'Não' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <h2>Síntese</h2>
    <p><?= nl2br(htmlspecialchars((string) ($ata['sintese'] ?? '—'))) ?></p>

    <h2>Decisões e encaminhamentos</h2>
    <p><?= nl2br(htmlspecialchars((string) ($ata['decisoes'] ?? '—'))) ?></p>

    <h2>Situações acadêmicas</h2>
    <table>
        <thead><tr><th>Aluno</th><th>Preliminar</th><th>Homologado</th><th>Frequência</th></tr></thead>
        <tbody>
        <?php foreach ($linhas as $linha):
            $homolog = $linha['resultado_homologado'] ?? null;
            $freq = $linha['frequencia']['percentual'] ?? null;
        ?>
            <tr>
                <td><?= htmlspecialchars((string) $linha['aluno']['nome']) ?></td>
                <td><?= htmlspecialchars((string) ($linha['resultado_preliminar']['label'] ?? '—')) ?></td>
                <td><?= $homolog ? htmlspecialchars(ConselhoSessao::RESULTADOS[$homolog] ?? $homolog) : '—' ?></td>
                <td><?= $freq !== null ? number_format((float) $freq, 1, ',', '.') . '%' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <div class="assin">
        <div><span>Coordenação</span></div>
        <div><span>Direção</span></div>
        <div><span>Secretaria</span></div>
    </div>
</body>
</html>
