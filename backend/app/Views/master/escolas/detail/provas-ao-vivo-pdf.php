<?php
/**
 * Relatório de efetividade da prova na plataforma (Dompdf, A4 paisagem).
 *
 * Variáveis: $escola, $bloco, $materias, $alunos, $efetividade, $eventos,
 *            $logo_data, $gerado_em, $rotulos_evento
 */
$escola = is_array($escola ?? null) ? $escola : [];
$bloco = is_array($bloco ?? null) ? $bloco : [];
$materias = is_array($materias ?? null) ? $materias : [];
$alunos = is_array($alunos ?? null) ? $alunos : [];
$efetividade = is_array($efetividade ?? null) ? $efetividade : [];
$eventos = is_array($eventos ?? null) ? $eventos : [];
$rotulosEvento = is_array($rotulos_evento ?? null) ? $rotulos_evento : MasterProvasAoVivoService::rotulosTipoEvento();
$logoData = (string) ($logo_data ?? '');
$geradoEm = (string) ($gerado_em ?? date('d/m/Y H:i'));
$tiposSaida = MasterProvasAoVivoService::TIPOS_SAIDA_PROVA;

$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$fmtHora = static function (?string $dt): string {
    if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('H:i', $ts) : '—';
};

$fmtDataHora = static function (?string $dt): string {
    if ($dt === null || $dt === '' || $dt === '0000-00-00 00:00:00') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y H:i', $ts) : '—';
};

$fmtPct = static function ($v): string {
    return $v === null ? '—' : number_format((float) $v, 1, ',', '.') . '%';
};

$fmtDuracao = static function ($min): string {
    if ($min === null || $min === '') {
        return '—';
    }
    $min = (int) $min;
    if ($min < 1) {
        return '< 1 min';
    }
    if ($min < 60) {
        return $min . ' min';
    }
    $h = intdiv($min, 60);
    $m = $min % 60;
    return $m > 0 ? $h . 'h ' . $m . 'min' : $h . 'h';
};

$duracaoCelula = static function (array $cel) use ($fmtDuracao): string {
    if (isset($cel['tempo_gasto']) && $cel['tempo_gasto'] !== null && (int) $cel['tempo_gasto'] > 0) {
        return $fmtDuracao((int) $cel['tempo_gasto']);
    }
    $ini = $cel['iniciado_em'] ?? null;
    $fim = $cel['finalizado_em'] ?? null;
    if (!$ini || !$fim) {
        return '—';
    }
    $a = strtotime((string) $ini);
    $b = strtotime((string) $fim);
    if (!$a || !$b || $b < $a) {
        return '—';
    }
    return $fmtDuracao((int) round(($b - $a) / 60));
};

$labelStatus = static function (string $status): string {
    return match ($status) {
        'finalizado' => 'Concluiu',
        'iniciado' => 'Em prova',
        'cancelada' => 'Cancelada',
        default => 'Não iniciou',
    };
};

$plural = static function (int $n, string $sing, string $plur): string {
    return $n . ' ' . ($n === 1 ? $sing : $plur);
};

$dataBloco = !empty($bloco['data_prova']) ? date('d/m/Y', strtotime((string) $bloco['data_prova'])) : '';
$horaBloco = !empty($bloco['hora_inicio']) ? substr((string) $bloco['hora_inicio'], 0, 5) : '';
$horaFimBloco = !empty($bloco['hora_fim']) ? substr((string) $bloco['hora_fim'], 0, 5) : '';
$horarioBloco = trim($horaBloco . ($horaFimBloco !== '' ? ' às ' . $horaFimBloco : ''));

$participantes = [];
$naoIniciaram = [];
foreach ($alunos as $aluno) {
    if (($aluno['por_materia'] ?? []) === []) {
        $naoIniciaram[] = $aluno;
    } else {
        $participantes[] = $aluno;
    }
}

$eventosSaida = array_values(array_filter($eventos, static function ($ev) use ($tiposSaida) {
    return in_array((string) ($ev['tipo_evento'] ?? ''), $tiposSaida, true);
}));

$pctPart = $fmtPct($efetividade['pct_participacao'] ?? null);
$pctConc = $fmtPct($efetividade['pct_conclusao'] ?? null);
$totalAlunos = (int) ($efetividade['total_alunos'] ?? 0);
$participaram = (int) ($efetividade['participaram'] ?? 0);
$concluiuTodas = (int) ($efetividade['concluiu_todas'] ?? 0);
$naoComecou = (int) ($efetividade['nao_comecou'] ?? 0);
$tentaramSair = (int) ($efetividade['tentaram_sair'] ?? 0);
$eventosSaidaQtd = (int) ($efetividade['eventos_saida'] ?? 0);
$totalMaterias = (int) ($efetividade['total_materias'] ?? count($materias));
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<style>
    * { font-family: "DejaVu Sans", sans-serif; }
    body { color: #1f2937; font-size: 9px; margin: 0; }
    @page { margin: 12mm 12mm 16mm 12mm; }
    .header { width: 100%; border-bottom: 2px solid #1e3a8a; padding-bottom: 8px; margin-bottom: 12px; }
    .header td { vertical-align: middle; }
    .logo img { max-height: 42px; max-width: 160px; }
    h1 { font-size: 16px; margin: 0 0 2px; color: #1e3a8a; }
    .meta { color: #6b7280; font-size: 9px; }
    h2 { font-size: 11px; color: #1e3a8a; margin: 14px 0 6px; border-bottom: 1px solid #e5e7eb; padding-bottom: 3px; }
    .cards { width: 100%; margin-bottom: 10px; border-collapse: separate; border-spacing: 5px 0; }
    .cards td { background: #f8fafc; border: 1px solid #e5e7eb; padding: 7px 8px; text-align: center; width: 14%; }
    .card-label { color: #6b7280; font-size: 7px; text-transform: uppercase; letter-spacing: 0.3px; }
    .card-value { font-size: 14px; font-weight: bold; margin-top: 2px; color: #111827; }
    .resumo { background: #eff6ff; border: 1px solid #bfdbfe; padding: 8px 10px; margin-bottom: 10px; line-height: 1.45; }
    table.grid { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
    table.grid th { background: #1e3a8a; color: #fff; text-align: left; padding: 4px 5px; font-size: 7.5px; font-weight: bold; }
    table.grid td { padding: 4px 5px; border-bottom: 1px solid #e5e7eb; vertical-align: top; }
    table.grid tr:nth-child(even) td { background: #f8fafc; }
    .right { text-align: right; }
    .center { text-align: center; }
    .muted { color: #9ca3af; }
    .ok { color: #15803d; }
    .warn { color: #b45309; }
    .bad { color: #b91c1c; }
    .aluno-bloco { page-break-inside: avoid; border: 1px solid #e5e7eb; margin-bottom: 8px; }
    .aluno-cab { background: #f1f5f9; padding: 5px 7px; border-bottom: 1px solid #e5e7eb; }
    .aluno-cab strong { font-size: 10px; }
    .tag { display: inline-block; padding: 1px 5px; border-radius: 8px; font-size: 7px; font-weight: bold; }
    .tag-ok { background: #dcfce7; color: #166534; }
    .tag-run { background: #cffafe; color: #0e7490; }
    .tag-bad { background: #fee2e2; color: #991b1b; }
    .tag-warn { background: #fef3c7; color: #92400e; }
    .tag-mute { background: #f3f4f6; color: #6b7280; }
    table.mat { width: 100%; border-collapse: collapse; }
    table.mat th { background: #eef2ff; color: #3730a3; font-size: 7px; padding: 3px 5px; text-align: left; }
    table.mat td { padding: 3px 5px; border-top: 1px solid #eef2ff; font-size: 8px; }
    .foot { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 7px; color: #9ca3af; border-top: 1px solid #e5e7eb; padding-top: 3px; }
</style>
</head>
<body>

<div class="foot">
    EducaTudo · Relatório de efetividade da prova na plataforma · Gerado em <?= $esc($geradoEm) ?>
</div>

<table class="header">
    <tr>
        <td class="logo" style="width:170px;">
            <?php if ($logoData !== ''): ?>
            <img src="<?= $esc($logoData) ?>" alt="EducaTudo">
            <?php else: ?>
            <strong style="font-size:16px;color:#1e3a8a;">EducaTudo</strong>
            <?php endif; ?>
        </td>
        <td>
            <h1>Relatório de efetividade da prova</h1>
            <div class="meta">
                Escola: <strong><?= $esc($escola['nome'] ?? '') ?></strong>
                <?php if (!empty($escola['slug'])): ?> · <?= $esc($escola['slug']) ?><?php endif; ?>
            </div>
            <div class="meta">
                Bloco: <strong><?= $esc($bloco['titulo'] ?? 'Prova') ?></strong>
                <?php if ($dataBloco !== ''): ?> · <?= $esc($dataBloco) ?><?php endif; ?>
                <?php if ($horarioBloco !== ''): ?> · <?= $esc($horarioBloco) ?><?php endif; ?>
                · <?= $esc($plural($totalMaterias, 'matéria', 'matérias')) ?>
            </div>
        </td>
    </tr>
</table>

<div class="resumo">
    De <?= $esc($plural($totalAlunos, 'aluno previsto', 'alunos previstos')) ?> nesta prova,
    <?= $esc($plural($participaram, 'participou', 'participaram')) ?> na plataforma
    (<?= $esc($pctPart) ?>).
    <?= $esc($plural($concluiuTodas, 'concluiu todas as matérias', 'concluíram todas as matérias')) ?>
    (<?= $esc($pctConc) ?> da turma).
    <?php if ($naoComecou > 0): ?>
    <?= $esc($plural($naoComecou, 'ainda não iniciou', 'ainda não iniciaram')) ?>.
    <?php endif; ?>
    <?php if ($tentaramSair > 0): ?>
    <span class="bad"><strong><?= $esc($plural($tentaramSair, 'aluno tentou sair da prova', 'alunos tentaram sair da prova')) ?></strong>
    (<?= $esc($plural($eventosSaidaQtd, 'evento de modo seguro', 'eventos de modo seguro')) ?>).</span>
    <?php else: ?>
    <span class="ok">Nenhum aluno tentou sair da prova (modo seguro).</span>
    <?php endif; ?>
</div>

<table class="cards">
    <tr>
        <td><div class="card-label">Previstos</div><div class="card-value"><?= (int) ($efetividade['total_alunos'] ?? 0) ?></div></td>
        <td><div class="card-label">Participaram</div><div class="card-value" style="color:#1d4ed8"><?= (int) ($efetividade['participaram'] ?? 0) ?></div></td>
        <td><div class="card-label">Concluiu todas</div><div class="card-value" style="color:#15803d"><?= (int) ($efetividade['concluiu_todas'] ?? 0) ?></div></td>
        <td><div class="card-label">Não iniciou</div><div class="card-value"><?= (int) ($efetividade['nao_comecou'] ?? 0) ?></div></td>
        <td><div class="card-label">Canceladas</div><div class="card-value" style="color:#b91c1c"><?= (int) ($efetividade['canceladas'] ?? 0) ?></div></td>
        <td><div class="card-label">Tentou sair</div><div class="card-value" style="color:#b45309"><?= (int) ($efetividade['tentaram_sair'] ?? 0) ?></div></td>
        <td><div class="card-label">Participação</div><div class="card-value"><?= $esc($pctPart) ?></div></td>
    </tr>
</table>

<?php if ($eventosSaida !== []): ?>
<h2>Alunos que tentaram sair da prova</h2>
<table class="grid">
    <thead>
        <tr>
            <th>Horário</th>
            <th>Aluno</th>
            <th>RA</th>
            <th>Evento</th>
            <th>Detalhe</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($eventosSaida as $ev): ?>
        <tr>
            <td><?= $esc($fmtDataHora($ev['created_at'] ?? null)) ?></td>
            <td><?= $esc($ev['aluno_nome'] ?? 'Não identificado') ?></td>
            <td><?= $esc($ev['aluno_ra'] ?? '—') ?></td>
            <td><?= $esc($rotulosEvento[$ev['tipo_evento'] ?? ''] ?? ($ev['tipo_evento'] ?? '')) ?></td>
            <td><?= $esc($ev['detalhe'] ?? '') ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<?php if ($naoIniciaram !== []): ?>
<h2>Alunos que não iniciaram a prova (<?= count($naoIniciaram) ?>)</h2>
<table class="grid">
    <thead>
        <tr>
            <th>Aluno</th>
            <th>RA</th>
            <th>Turma</th>
            <th>Situação</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($naoIniciaram as $aluno): ?>
        <tr>
            <td><?= $esc($aluno['aluno_nome'] ?? '') ?></td>
            <td><?= $esc($aluno['aluno_ra'] ?? '—') ?></td>
            <td><?= $esc($aluno['turma_nome'] ?? '—') ?></td>
            <td class="muted">Não iniciou nenhuma matéria</td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>
<?php endif; ?>

<h2>Métricas por aluno — quem participou (<?= count($participantes) ?>)</h2>
<?php if ($participantes === []): ?>
<p class="muted">Nenhum aluno iniciou esta prova na plataforma.</p>
<?php else: ?>
<?php foreach ($participantes as $aluno):
    $ok = (int) ($aluno['materias_ok'] ?? 0);
    $totalMat = count($materias);
    $statusGeral = !empty($aluno['em_prova'])
        ? 'Em prova'
        : (!empty($aluno['tem_cancelada'])
            ? 'Com cancelamento'
            : ($totalMat > 0 && $ok >= $totalMat ? 'Concluiu todas' : 'Parcial'));
    $tagClass = !empty($aluno['em_prova'])
        ? 'tag-run'
        : (!empty($aluno['tem_cancelada'])
            ? 'tag-bad'
            : ($totalMat > 0 && $ok >= $totalMat ? 'tag-ok' : 'tag-warn'));
?>
<div class="aluno-bloco">
    <div class="aluno-cab">
        <strong><?= $esc($aluno['aluno_nome'] ?? '') ?></strong>
        <?php if (!empty($aluno['aluno_ra'])): ?> · RA <?= $esc($aluno['aluno_ra']) ?><?php endif; ?>
        · Turma <?= $esc($aluno['turma_nome'] ?: '—') ?>
        · Progresso <?= $ok ?>/<?= $totalMat ?>
        · Início <?= $esc($fmtHora($aluno['iniciado_em_primeiro'] ?? null)) ?>
        · Término <?= $esc($fmtHora($aluno['finalizado_em_ultimo'] ?? null)) ?>
        · Tempo <?= $esc($fmtDuracao($aluno['tempo_total_min'] ?? null)) ?>
        · <span class="tag <?= $tagClass ?>"><?= $esc($statusGeral) ?></span>
        <?php if (!empty($aluno['tentou_sair'])): ?>
        · <span class="tag tag-warn">Tentou sair (<?= (int) ($aluno['total_saidas'] ?? 0) ?>)</span>
        <?php endif; ?>
    </div>
    <table class="mat">
        <thead>
            <tr>
                <th style="width:28%">Matéria</th>
                <th style="width:14%">Situação</th>
                <th style="width:14%">Início</th>
                <th style="width:14%">Término</th>
                <th style="width:14%">Duração</th>
                <th>Observação</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($materias as $mat):
                $provaId = (int) ($mat['id'] ?? 0);
                $cel = $aluno['por_materia'][$provaId] ?? null;
                $status = (string) ($cel['status'] ?? '');
                $nomeMat = (string) ($mat['materia_nome'] ?: $mat['titulo'] ?? 'Matéria');
                $obs = '';
                if ($status === 'cancelada') {
                    $obs = 'Cancelada (saída ou interrupção do modo seguro)';
                } elseif ($status === 'iniciado') {
                    $obs = 'Ainda em andamento';
                } elseif ($status === '') {
                    $obs = 'Não iniciou esta matéria';
                }
            ?>
            <tr>
                <td><?= $esc($nomeMat) ?></td>
                <td><?= $esc($labelStatus($status)) ?></td>
                <td><?= $esc($cel ? $fmtHora($cel['iniciado_em'] ?? null) : '—') ?></td>
                <td><?= $esc($cel ? $fmtHora($cel['finalizado_em'] ?? null) : '—') ?></td>
                <td><?= $esc($cel ? $duracaoCelula($cel) : '—') ?></td>
                <td class="muted"><?= $esc($obs) ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endforeach; ?>
<?php endif; ?>

</body>
</html>
