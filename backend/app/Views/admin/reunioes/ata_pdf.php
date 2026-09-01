<?php
$reuniao = is_array($reuniao ?? null) ? $reuniao : [];
$anexos = is_array($anexos ?? null) ? $anexos : [];
$escola = (string) ($escola ?? 'Escola');
$titulo = (string) ($reuniao['titulo'] ?? 'Reunião');
$data = !empty($reuniao['data_reuniao']) ? date('d/m/Y', strtotime((string) $reuniao['data_reuniao'])) : '—';
$horaIni = !empty($reuniao['hora_inicio']) ? substr((string) $reuniao['hora_inicio'], 0, 5) : '';
$horaFim = !empty($reuniao['hora_fim']) ? substr((string) $reuniao['hora_fim'], 0, 5) : '';
$horario = trim($horaIni . ($horaFim !== '' ? ' às ' . $horaFim : ''));
$descricaoHtml = function_exists('rich_text_render')
    ? rich_text_render((string) ($reuniao['descricao'] ?? ''))
    : nl2br(htmlspecialchars((string) ($reuniao['descricao'] ?? '')));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ata de Reunião — <?= htmlspecialchars($titulo) ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 4px; }
        h2 { font-size: 13px; margin: 16px 0 6px; border-bottom: 1px solid #ddd; padding-bottom: 3px; }
        p { margin: 0 0 8px; }
        .muted { color: #555; font-size: 11px; }
        .meta td { padding: 2px 8px 2px 0; vertical-align: top; }
        .assin { margin-top: 48px; width: 100%; }
        .assin td { width: 33%; text-align: center; padding-top: 36px; font-size: 11px; }
        .assin span { display: block; border-top: 1px solid #333; margin: 0 14px; padding-top: 4px; }
        ul { margin: 0 0 8px 16px; padding: 0; }
    </style>
</head>
<body>
    <h1>Ata de Reunião</h1>
    <p class="muted"><?= htmlspecialchars($escola) ?> · emitida em <?= date('d/m/Y H:i') ?></p>

    <table class="meta">
        <tr><td><strong>Pauta / título</strong></td><td><?= htmlspecialchars($titulo) ?></td></tr>
        <tr><td><strong>Data</strong></td><td><?= htmlspecialchars($data) ?><?= $horario !== '' ? ' · ' . htmlspecialchars($horario) : '' ?></td></tr>
        <?php if (!empty($reuniao['local_reuniao'])): ?>
        <tr><td><strong>Local</strong></td><td><?= htmlspecialchars((string) $reuniao['local_reuniao']) ?></td></tr>
        <?php endif; ?>
        <?php if (!empty($reuniao['link_reuniao'])): ?>
        <tr><td><strong>Link</strong></td><td><?= htmlspecialchars((string) $reuniao['link_reuniao']) ?></td></tr>
        <?php endif; ?>
        <tr><td><strong>Turmas</strong></td><td><?= htmlspecialchars((string) (($reuniao['turmas_nomes'] ?? '') !== '' ? $reuniao['turmas_nomes'] : 'Toda a escola')) ?></td></tr>
        <?php if (!empty($reuniao['relator_nome'])): ?>
        <tr><td><strong>Relator</strong></td><td><?= htmlspecialchars((string) $reuniao['relator_nome']) ?></td></tr>
        <?php endif; ?>
    </table>

    <h2>Participantes</h2>
    <p><?= !empty($reuniao['participantes']) ? nl2br(htmlspecialchars((string) $reuniao['participantes'])) : '—' ?></p>

    <h2>Registro da reunião</h2>
    <div><?= $descricaoHtml !== '' ? $descricaoHtml : '—' ?></div>

    <h2>Decisões e encaminhamentos</h2>
    <p><?= !empty($reuniao['encaminhamentos']) ? nl2br(htmlspecialchars((string) $reuniao['encaminhamentos'])) : '—' ?></p>

    <?php if (!empty($anexos)): ?>
    <h2>Anexos</h2>
    <ul>
        <?php foreach ($anexos as $anexo): ?>
        <li><?= htmlspecialchars((string) ($anexo['nome'] ?? '')) ?></li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <table class="assin">
        <tr>
            <td><span>Relator</span></td>
            <td><span>Coordenação</span></td>
            <td><span>Direção</span></td>
        </tr>
    </table>
</body>
</html>
