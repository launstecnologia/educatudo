<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Relatório por Proposta — Redação</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: Arial, sans-serif; font-size: 12px; color: #1f2937; background: #fff; }
  .header { padding: 16px 24px; border-bottom: 2px solid #6366f1; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: flex-start; }
  .header h1 { font-size: 18px; font-weight: bold; color: #4f46e5; }
  .kpis { display: grid; grid-template-columns: repeat(6, 1fr); gap: 8px; margin-bottom: 20px; }
  .kpi { border: 1px solid #e5e7eb; border-radius: 8px; padding: 10px; text-align: center; }
  .kpi .val { font-size: 18px; font-weight: bold; color: #111827; }
  .kpi .lbl { font-size: 10px; color: #9ca3af; margin-top: 2px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
  th { background: #f9fafb; text-align: left; padding: 6px 10px; font-size: 10px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
  td { padding: 6px 10px; font-size: 11px; border-bottom: 1px solid #f3f4f6; }
  .badge { display: inline-block; padding: 1px 7px; border-radius: 20px; font-size: 10px; font-weight: 600; }
  .badge-green { background: #d1fae5; color: #065f46; }
  .badge-amber { background: #fef3c7; color: #92400e; }
  .badge-gray  { background: #f3f4f6; color: #4b5563; }
  .badge-red   { background: #fee2e2; color: #991b1b; }
  .section-title { font-size: 13px; font-weight: 600; color: #374151; margin-bottom: 8px; padding-bottom: 4px; border-bottom: 1px solid #e5e7eb; }
  @media print {
    body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .no-print { display: none !important; }
    @page { margin: 1.5cm; }
  }
</style>
</head>
<body>

<div class="no-print" style="background:#f3f4f6;padding:10px 24px;display:flex;gap:10px;align-items:center;">
    <button onclick="window.print()" style="background:#6366f1;color:#fff;border:none;padding:8px 18px;border-radius:6px;font-size:13px;cursor:pointer;font-weight:600;">🖨️ Imprimir / Salvar PDF</button>
    <button onclick="window.close()" style="background:#e5e7eb;color:#374151;border:none;padding:8px 18px;border-radius:6px;font-size:13px;cursor:pointer;">Fechar</button>
</div>

<div class="header">
    <div>
        <h1>Relatório de Redação — Por Proposta</h1>
        <p style="font-size:13px;font-weight:600;color:#1f2937;margin-top:4px;"><?= htmlspecialchars((string)($proposta['titulo'] ?? '')) ?></p>
        <p style="color:#6b7280;font-size:11px;">Banca: <?= htmlspecialchars((string)($proposta['banca'] ?? '—')) ?></p>
    </div>
    <div style="font-size:11px;color:#6b7280;text-align:right;">
        <p>Gerado em <?= date('d/m/Y H:i') ?></p>
        <?php if ($filtro_from || $filtro_to): ?>
        <p>Período: <?= $filtro_from ? date('d/m/Y', strtotime($filtro_from)) : '…' ?> até <?= $filtro_to ? date('d/m/Y', strtotime($filtro_to)) : 'hoje' ?></p>
        <?php endif; ?>
    </div>
</div>

<!-- KPIs -->
<div class="kpis">
    <div class="kpi"><div class="val"><?= (int)($kpis['total_envios'] ?? 0) ?></div><div class="lbl">Envios</div></div>
    <div class="kpi"><div class="val"><?= (int)($kpis['corrigidos'] ?? 0) ?></div><div class="lbl">Corrigidos</div></div>
    <div class="kpi"><div class="val"><?= (int)($kpis['pendentes'] ?? 0) ?></div><div class="lbl">Pendentes</div></div>
    <div class="kpi"><div class="val"><?= $kpis['media_nota'] !== null ? number_format((float)$kpis['media_nota'], 0) : '—' ?></div><div class="lbl">Média</div></div>
    <div class="kpi"><div class="val"><?= $kpis['nota_maxima'] !== null ? number_format((float)$kpis['nota_maxima'], 0) : '—' ?></div><div class="lbl">Maior nota</div></div>
    <div class="kpi"><div class="val"><?= $kpis['nota_minima'] !== null ? number_format((float)$kpis['nota_minima'], 0) : '—' ?></div><div class="lbl">Menor nota</div></div>
</div>

<?php
$media = $kpis['media_nota'] !== null ? (float)$kpis['media_nota'] : null;
if ($media !== null) {
    if ($media >= 700)     { $tag = 'Fácil';  $style = 'background:#d1fae5;color:#065f46'; }
    elseif ($media >= 500) { $tag = 'Médio';  $style = 'background:#fef3c7;color:#92400e'; }
    else                   { $tag = 'Difícil'; $style = 'background:#fee2e2;color:#991b1b'; }
    echo '<p style="margin-bottom:16px;"><span style="' . $style . ';padding:3px 12px;border-radius:20px;font-size:11px;font-weight:600;">Dificuldade percebida: ' . $tag . '</span></p>';
}
?>

<!-- Tabela de alunos -->
<p class="section-title">Alunos — desempenho nesta proposta</p>
<table>
    <thead>
        <tr>
            <th>#</th><th>Aluno</th><th>Turma</th><th>Status</th><th>Data envio</th><th style="text-align:center">Nota</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($alunos as $i => $row): ?>
    <tr>
        <td style="color:#9ca3af"><?= $i + 1 ?></td>
        <td><?= htmlspecialchars((string)($row['aluno_nome'] ?? '')) ?></td>
        <td><?= htmlspecialchars((string)($row['turma_nome'] ?? '—')) ?></td>
        <td>
            <?php if ($row['status'] === 'corrected'): ?>
                <span class="badge badge-green">Corrigido</span>
            <?php elseif ($row['status'] === 'submitted'): ?>
                <span class="badge badge-amber">Enviado</span>
            <?php else: ?>
                <span class="badge badge-gray">Rascunho</span>
            <?php endif; ?>
        </td>
        <td><?= !empty($row['submitted_at']) ? date('d/m/Y', strtotime($row['submitted_at'])) : '—' ?></td>
        <td style="text-align:center">
            <?php $n = $row['nota'] !== null ? (float)$row['nota'] : null; ?>
            <?php if ($n !== null): ?>
            <span class="badge <?= $n >= 600 ? 'badge-green' : ($n >= 400 ? 'badge-amber' : 'badge-red') ?>"><?= number_format($n, 0) ?></span>
            <?php else: ?>—<?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<p style="font-size:10px;color:#9ca3af;text-align:center;padding-top:16px;border-top:1px solid #e5e7eb;">
    EducaTudo — Relatório gerado em <?= date('d/m/Y \à\s H:i') ?>
</p>
</body>
</html>
