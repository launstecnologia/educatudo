<?php
/**
 * Template do relatório do Diário de Classe (Dompdf, A4 landscape).
 * Variáveis: $dados (['periodo','indicadores','resumo','pendencias']),
 * $professor_nome, $gerado_em.
 */
$periodo = $dados['periodo'] ?? ['inicio' => '', 'fim' => ''];
$indicadores = $dados['indicadores'] ?? [];
$resumo = $dados['resumo'] ?? [];
$labels = ['em_dia' => 'Em dia', 'atencao' => 'Atenção', 'atraso' => 'Em atraso'];
$cores = ['em_dia' => '#16a34a', 'atencao' => '#d97706', 'atraso' => '#dc2626'];
$fmtData = static function (string $d): string {
    if ($d === '') return '-';
    $ts = strtotime($d);
    return $ts !== false ? date('d/m/Y', $ts) : '-';
};
$periodoTxt = $fmtData((string) ($periodo['inicio'] ?? '')) . ' a ' . $fmtData((string) ($periodo['fim'] ?? ''));
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { color: #1f2937; font-size: 11px; margin: 0; }
    h1 { font-size: 16px; margin: 0 0 2px; }
    .sub { color: #6b7280; font-size: 10px; margin: 0 0 12px; }
    .cards { width: 100%; margin-bottom: 12px; }
    .cards td { padding: 8px 10px; border: 1px solid #e5e7eb; border-radius: 6px; }
    .card-label { color: #6b7280; font-size: 9px; text-transform: uppercase; }
    .card-value { font-size: 15px; font-weight: bold; }
    table.grid { width: 100%; border-collapse: collapse; }
    table.grid th { background: #f3f4f6; text-align: left; padding: 6px 8px; font-size: 9px; text-transform: uppercase; color: #374151; border-bottom: 1px solid #d1d5db; }
    table.grid td { padding: 6px 8px; border-bottom: 1px solid #eef2f7; }
    .pill { padding: 2px 6px; border-radius: 10px; color: #fff; font-size: 9px; font-weight: bold; }
    .right { text-align: right; }
    .muted { color: #9ca3af; }
    .foot { margin-top: 14px; color: #9ca3af; font-size: 9px; }
</style>
</head>
<body>
    <h1>Relatório do Diário de Classe</h1>
    <p class="sub">
        Período: <?= htmlspecialchars($periodoTxt) ?>
        <?php if (($professor_nome ?? '') !== ''): ?> &middot; Professor: <?= htmlspecialchars((string) $professor_nome) ?><?php endif; ?>
        &middot; Gerado em <?= htmlspecialchars((string) ($gerado_em ?? '')) ?>
    </p>

    <table class="cards">
        <tr>
            <td><div class="card-label">Linhas</div><div class="card-value"><?= (int) ($resumo['total'] ?? 0) ?></div></td>
            <td><div class="card-label">Em dia</div><div class="card-value" style="color:#16a34a"><?= (int) ($resumo['em_dia'] ?? 0) ?></div></td>
            <td><div class="card-label">Atenção</div><div class="card-value" style="color:#d97706"><?= (int) ($resumo['atencao'] ?? 0) ?></div></td>
            <td><div class="card-label">Em atraso</div><div class="card-value" style="color:#dc2626"><?= (int) ($resumo['atraso'] ?? 0) ?></div></td>
            <td><div class="card-label">Cobertura média</div><div class="card-value"><?= $resumo['cobertura_media'] !== null ? number_format((float) $resumo['cobertura_media'], 1, ',', '') . '%' : '-' ?></div></td>
            <td><div class="card-label">Horas (min./prev.)</div><div class="card-value"><?= number_format((float) ($resumo['horas_ministradas'] ?? 0), 1, ',', '') ?>/<?= number_format((float) ($resumo['horas_previstas'] ?? 0), 1, ',', '') ?></div></td>
        </tr>
    </table>

    <table class="grid">
        <thead>
            <tr>
                <th>Professor</th><th>Turma</th><th>Matéria</th>
                <th class="right">Previstas</th><th class="right">Ministradas</th>
                <th class="right">Pend. vencidas</th><th class="right">Cobertura</th>
                <th>Última atualização</th><th>Situação</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($indicadores)): ?>
                <tr><td colspan="9" class="muted">Nenhuma aula prevista na grade horária para o período.</td></tr>
            <?php else: foreach ($indicadores as $i):
                $sit = (string) ($i['situacao'] ?? 'em_dia'); ?>
                <tr>
                    <td><?= htmlspecialchars((string) ($i['professor_nome'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($i['turma_nome'] ?? '')) ?></td>
                    <td><?= htmlspecialchars((string) ($i['materia_nome'] ?? '')) ?></td>
                    <td class="right"><?= (int) ($i['aulas_previstas'] ?? 0) ?></td>
                    <td class="right"><?= (int) ($i['aulas_ministradas'] ?? 0) ?></td>
                    <td class="right"><?= (int) ($i['pendentes_vencidas'] ?? 0) ?></td>
                    <td class="right"><?= $i['percentual'] !== null ? number_format((float) $i['percentual'], 1, ',', '') . '%' : '-' ?></td>
                    <td><?= htmlspecialchars($i['ultima_data'] ? date('d/m/Y H:i', strtotime((string) $i['ultima_data'])) : '-') ?></td>
                    <td><span class="pill" style="background:<?= $cores[$sit] ?? '#6b7280' ?>"><?= htmlspecialchars($labels[$sit] ?? $sit) ?></span></td>
                </tr>
            <?php endforeach; endif; ?>
        </tbody>
    </table>

    <p class="foot">EducaTudo &middot; Relatório gerado automaticamente a partir do Diário de Classe.</p>
</body>
</html>
