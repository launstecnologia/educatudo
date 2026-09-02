<?php
/**
 * Template do PDF do Boletim (modo paisagem).
 *
 * Variáveis disponíveis (extraídas pelo controller):
 * - $aluno: array{id, nome, ra, turma_nome}
 * - $boletins_gerados: list<array{colunas, linhas, decimal_places, ...}>
 * - $observacao: string
 * - $ano_letivo: int
 * - $logo_data: string (data URI da logo, pode estar vazio)
 * - $gerado_em: string (dd/mm/YYYY HH:ii)
 */
$aluno = isset($aluno) && is_array($aluno) ? $aluno : [];
$boletins_gerados = isset($boletins_gerados) && is_array($boletins_gerados) ? $boletins_gerados : [];
$observacao = isset($observacao) ? trim((string) $observacao) : '';
$anoLetivo = isset($ano_letivo) ? (int) $ano_letivo : (int) date('Y');
$logoData = isset($logo_data) ? (string) $logo_data : '';
$geradoEm = isset($gerado_em) ? (string) $gerado_em : date('d/m/Y H:i');

if (!class_exists('BoletimQuadroLayoutHelper', false)) {
    require_once dirname(__DIR__, 3) . '/Helpers/BoletimQuadroLayoutHelper.php';
}
if (!class_exists('StudentFormHelper', false)) {
    require_once dirname(__DIR__, 3) . '/Helpers/StudentFormHelper.php';
}

// Reutiliza o agrupador de colunas (mesma lógica do partial).
$buildGroupedBoletimHeader = static function (array $cols): array {
    $groupOrder = ['b1', 'b2', 'b3', 'b4', 'final', 'outros'];
    $groupLabel = [
        'b1' => '1º BIMESTRE',
        'b2' => '2º BIMESTRE',
        'b3' => '3º BIMESTRE',
        'b4' => '4º BIMESTRE',
        'final' => 'FINAL',
        'outros' => 'OUTROS',
    ];
    $subLabel = [
        'media' => 'Média',
        'faltas' => 'Faltas',
        'rec' => 'Rec.',
        'resultado' => 'Resultado',
        'other' => '',
    ];

    // Mesma lógica do partial `partials/boletins_gerados.php`. Quando a coluna
    // tem layout_group cadastrado, usamos direto. Caso contrário, fazemos
    // fallback parseando o nome/código (ex.: "Média 2º Bimestre", "faltas-3bim").
    $parse = static function (array $c) use ($subLabel): array {
        $groupMeta = strtolower(trim((string) ($c['layout_group'] ?? '')));
        $typeMeta = strtolower(trim((string) ($c['layout_type'] ?? '')));
        $allowedGroups = ['b1', 'b2', 'b3', 'b4', 'final'];
        $allowedTypes = ['media', 'faltas', 'rec', 'resultado', 'other'];
        if (in_array($groupMeta, $allowedGroups, true)) {
            $subMeta = in_array($typeMeta, $allowedTypes, true) ? $typeMeta : 'other';
            $labelMeta = $subLabel[$subMeta] !== '' ? $subLabel[$subMeta] : (string) ($c['nome'] ?? $c['codigo'] ?? '');
            return [$groupMeta, $subMeta, $labelMeta];
        }

        $nm = function_exists('mb_strtolower') ? mb_strtolower(trim((string) ($c['nome'] ?? '')), 'UTF-8') : strtolower(trim((string) ($c['nome'] ?? '')));
        $cd = function_exists('mb_strtolower') ? mb_strtolower(trim((string) ($c['codigo'] ?? '')), 'UTF-8') : strtolower(trim((string) ($c['codigo'] ?? '')));
        $full = $nm . ' ' . $cd;
        $full = str_replace(['_', '-'], ' ', $full);
        $group = '';
        if (preg_match('/(^|[^0-9])1[\sºo]*bim/', $full)) { $group = 'b1'; }
        elseif (preg_match('/(^|[^0-9])2[\sºo]*bim/', $full)) { $group = 'b2'; }
        elseif (preg_match('/(^|[^0-9])3[\sºo]*bim/', $full)) { $group = 'b3'; }
        elseif (preg_match('/(^|[^0-9])4[\sºo]*bim/', $full)) { $group = 'b4'; }
        elseif (strpos($full, 'final') !== false || strpos($full, 'semestre') !== false) { $group = 'final'; }

        $sub = 'other';
        if (strpos($full, 'falt') !== false) { $sub = 'faltas'; }
        elseif (strpos($full, 'result') !== false || strpos($full, 'status') !== false) { $sub = 'resultado'; }
        elseif (strpos($full, 'rec') !== false || strpos($full, 'recup') !== false) { $sub = 'rec'; }
        elseif (strpos($full, 'média') !== false || strpos($full, 'media') !== false || strpos($full, 'nota') !== false) { $sub = 'media'; }
        elseif ($group !== '') { $sub = 'media'; }

        $label = $subLabel[$sub] !== '' ? $subLabel[$sub] : (string) ($c['nome'] ?? $c['codigo'] ?? '');
        if ($group === '') {
            $group = 'outros';
        }
        return [$group, $sub, $label];
    };

    $grouped = [];
    $hasAnyGroup = false;
    foreach ($cols as $c) {
        [$g, $s, $label] = $parse((array) $c);
        if ($g === '') {
            continue;
        }
        $hasAnyGroup = true;
        if (!isset($grouped[$g])) {
            $grouped[$g] = [];
        }
        $c['_subkey'] = $s;
        $c['_sublabel'] = $label;
        $grouped[$g][] = $c;
    }
    if (!$hasAnyGroup) {
        return ['enabled' => false, 'groups' => []];
    }

    $groupsOut = [];
    foreach ($groupOrder as $gk) {
        $arr = $grouped[$gk] ?? [];
        if ($arr === []) {
            continue;
        }
        $groupsOut[] = ['key' => $gk, 'label' => $groupLabel[$gk], 'cols' => $arr];
    }

    return ['enabled' => true, 'groups' => $groupsOut];
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Boletim Escolar - <?= (int) $anoLetivo ?></title>
    <style>
        @page { margin: 18mm 14mm 18mm 14mm; }
        * { box-sizing: border-box; }
        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #1f2937;
            font-size: 10pt;
            margin: 0;
        }
        .header {
            display: table;
            width: 100%;
            border-bottom: 2px solid #064e3b;
            padding-bottom: 8px;
            margin-bottom: 14px;
        }
        .header .logo-cell {
            display: table-cell;
            width: 110px;
            vertical-align: middle;
        }
        .header .title-cell {
            display: table-cell;
            vertical-align: middle;
            padding-left: 14px;
        }
        .header img {
            max-height: 56px;
            max-width: 100px;
        }
        .header h1 {
            margin: 0 0 2px 0;
            font-size: 18pt;
            color: #064e3b;
        }
        .header h2 {
            margin: 0;
            font-size: 11pt;
            color: #374151;
            font-weight: normal;
        }
        .student-info {
            margin-bottom: 12px;
            font-size: 10pt;
        }
        .student-info strong { color: #111827; }
        .student-info .row {
            margin-bottom: 2px;
        }
        table.boletim {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 14px;
            font-size: 9pt;
        }
        table.boletim th, table.boletim td {
            border: 1px solid #d1d5db;
            padding: 4px 6px;
            vertical-align: middle;
        }
        table.boletim thead th {
            background: #f3f4f6;
            color: #1f2937;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            font-size: 8pt;
        }
        table.boletim thead th.sub {
            background: #fef3c7;
            font-weight: bold;
            font-size: 8pt;
        }
        table.boletim td.materia {
            text-align: left;
            font-weight: bold;
            color: #111827;
            background: inherit;
        }
        table.boletim td.cell {
            text-align: center;
        }
        table.boletim td.num {
            color: #047857;
            font-weight: bold;
        }
        table.boletim td.txt {
            color: #374151;
        }
        table.boletim td.empty {
            color: #9ca3af;
        }
        table.boletim tbody tr.even td {
            background: #f9fafb;
        }
        table.boletim tbody tr.odd td {
            background: #ffffff;
        }
        .observacao {
            margin-top: 10px;
            border: 1px solid #d1d5db;
            border-radius: 4px;
            padding: 10px 12px;
            background: #f9fafb;
        }
        .observacao h3 {
            margin: 0 0 6px 0;
            font-size: 11pt;
            color: #064e3b;
        }
        .observacao p {
            margin: 0;
            font-size: 10pt;
            color: #1f2937;
            white-space: pre-wrap;
            word-wrap: break-word;
            line-height: 1.45;
        }
        .footer {
            margin-top: 14px;
            text-align: right;
            font-size: 8pt;
            color: #6b7280;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-cell">
            <?php if ($logoData !== ''): ?>
                <img src="<?= htmlspecialchars($logoData, ENT_QUOTES, 'UTF-8') ?>" alt="Logo">
            <?php endif; ?>
        </div>
        <div class="title-cell">
            <h1>Boletim Escolar - <?= (int) $anoLetivo ?></h1>
            <h2>
                <?= \StudentFormHelper::nomeOficialHtml($aluno, static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8')) ?>
                <?php if (!empty($aluno['turma_nome'])): ?>
                    &nbsp;|&nbsp; <?= htmlspecialchars((string) $aluno['turma_nome'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
                <?php if (!empty($aluno['ra'])): ?>
                    &nbsp;|&nbsp; RA: <?= htmlspecialchars((string) $aluno['ra'], ENT_QUOTES, 'UTF-8') ?>
                <?php endif; ?>
            </h2>
        </div>
    </div>

    <?php if (empty($boletins_gerados)): ?>
        <p>Nenhum boletim gerado para este aluno.</p>
    <?php else: ?>
        <?php foreach ($boletins_gerados as $ev): ?>
            <?php
            $cols = is_array($ev['colunas'] ?? null) ? $ev['colunas'] : [];
            $linhas = is_array($ev['linhas'] ?? null) ? $ev['linhas'] : [];
            $decimalPlaces = ((int) ($ev['decimal_places'] ?? 2) === 1) ? 1 : 2;
            if ($cols === [] || $linhas === []) {
                continue;
            }
            $exibirEmEv = strtolower(trim((string) ($ev['exibir_em'] ?? 'boletim')));
            $groupedHeader = BoletimQuadroLayoutHelper::deveAgruparCabecalhoBoletimOficial($exibirEmEv)
                ? $buildGroupedBoletimHeader($cols)
                : ['enabled' => false, 'groups' => []];
            ?>
            <table class="boletim">
                <thead>
                    <?php if (!empty($groupedHeader['enabled'])): ?>
                        <tr>
                            <th rowspan="2" style="width: 22%;">Matéria</th>
                            <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                <th colspan="<?= (int) count((array) ($grp['cols'] ?? [])) ?>">
                                    <?= htmlspecialchars((string) ($grp['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr>
                            <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                <?php foreach ((array) ($grp['cols'] ?? []) as $mc): ?>
                                    <th class="sub">
                                        <?= htmlspecialchars((string) ($mc['_sublabel'] ?? $mc['nome'] ?? $mc['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </th>
                                <?php endforeach; ?>
                            <?php endforeach; ?>
                        </tr>
                    <?php else: ?>
                        <tr>
                            <th style="width: 22%;">Matéria</th>
                            <?php foreach ($cols as $mc): ?>
                                <th><?= htmlspecialchars((string) ($mc['nome'] ?? $mc['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    <?php endif; ?>
                </thead>
                <tbody>
                    <?php foreach ($linhas as $idxLin => $lin): ?>
                        <?php
                        $notasLin = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
                        $rowClass = ($idxLin % 2 === 0) ? 'odd' : 'even';
                        ?>
                        <tr class="<?= $rowClass ?>">
                            <td class="materia">
                                <?= htmlspecialchars((string) ($lin['materia_nome'] ?? '-'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <?php
                            $iterCols = $cols;
                            if (!empty($groupedHeader['enabled'])) {
                                $iterCols = [];
                                foreach ((array) ($groupedHeader['groups'] ?? []) as $grp) {
                                    foreach ((array) ($grp['cols'] ?? []) as $gc) {
                                        $iterCols[] = $gc;
                                    }
                                }
                            }
                            foreach ($iterCols as $mc):
                                $cod = (string) ($mc['codigo'] ?? '');
                                $nv = $notasLin[$cod] ?? null;
                                $isFaltasCol = ((string) ($mc['source_type'] ?? '')) === 'faltas_evento'
                                    || strtolower((string) ($mc['layout_type'] ?? '')) === 'faltas';
                                $cellClass = is_numeric($nv) ? 'cell num' : (is_string($nv) && trim($nv) !== '' ? 'cell txt' : 'cell empty');
                            ?>
                                <td class="<?= $cellClass ?>">
                                    <?php if (is_numeric($nv)): ?>
                                        <?= $isFaltasCol
                                            ? htmlspecialchars(number_format((float) round((float) $nv), 0, ',', '.'), ENT_QUOTES, 'UTF-8')
                                            : htmlspecialchars(number_format((float) $nv, $decimalPlaces, ',', '.'), ENT_QUOTES, 'UTF-8') ?>
                                    <?php elseif (is_string($nv) && trim($nv) !== ''): ?>
                                        <?= htmlspecialchars($nv, ENT_QUOTES, 'UTF-8') ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endforeach; ?>
    <?php endif; ?>

    <?php if ($observacao !== ''): ?>
        <div class="observacao">
            <h3>Observação</h3>
            <p><?= nl2br(htmlspecialchars($observacao, ENT_QUOTES, 'UTF-8')) ?></p>
        </div>
    <?php endif; ?>

    <div class="footer">
        Gerado em <?= htmlspecialchars($geradoEm, ENT_QUOTES, 'UTF-8') ?>
    </div>
</body>
</html>
