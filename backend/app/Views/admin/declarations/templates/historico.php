<?php
/**
 * Histórico Escolar (PDF paisagem). Reúne todos os boletins gerados do aluno,
 * agrupados por ano letivo, exibindo as notas reais por matéria/bimestre.
 *
 * Variáveis: $titulo, $dados['aluno'], $dados['unidade'], $dados['historico'],
 *            $logo_data, $numero, $ano, $gerado_em, $cidade_data
 */
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$unidade = is_array($dados['unidade'] ?? null) ? $dados['unidade'] : [];
$historico = is_array($dados['historico'] ?? null) ? $dados['historico'] : [];
$logoData = (string) ($logo_data ?? '');
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');

$nomeUnidade = trim((string) ($unidade['razao_social'] ?? '')) ?: trim((string) ($unidade['nome'] ?? ''));
if ($nomeUnidade === '') {
    $nomeUnidade = 'Instituição de Ensino';
}
$linhaEndereco = trim(implode(', ', array_filter([
    trim((string) ($unidade['endereco'] ?? '')) . (trim((string) ($unidade['numero'] ?? '')) !== '' ? ', ' . $unidade['numero'] : ''),
    trim((string) ($unidade['bairro'] ?? '')),
    trim(trim((string) ($unidade['cidade'] ?? '')) . (trim((string) ($unidade['uf'] ?? '')) !== '' ? ' / ' . $unidade['uf'] : '')),
    trim((string) ($unidade['cep'] ?? '')) !== '' ? 'CEP ' . $unidade['cep'] : '',
])));
$linhaDocs = trim(implode(' • ', array_filter([
    trim((string) ($unidade['cnpj'] ?? '')) !== '' ? 'CNPJ: ' . $unidade['cnpj'] : '',
    trim((string) ($unidade['inep'] ?? '')) !== '' ? 'INEP: ' . $unidade['inep'] : '',
])));

$alunoNome = trim((string) ($aluno['nome'] ?? '—'));
$alunoRa = trim((string) ($aluno['codigo_aluno'] ?? $aluno['ra'] ?? ''));
$alunoCpf = trim((string) ($aluno['cpf'] ?? ''));

// Agrupa boletins por ano letivo.
$porAno = [];
foreach ($historico as $ev) {
    $ano = (int) ($ev['ano_letivo_calc'] ?? $ev['ano_letivo'] ?? 0);
    if ($ano <= 0) {
        $ano = (int) date('Y');
    }
    $porAno[$ano][] = $ev;
}
ksort($porAno);

// Mesma lógica de agrupamento de colunas usada no boletim_pdf.
$buildGroupedBoletimHeader = static function (array $cols): array {
    $groupOrder = ['b1', 'b2', 'b3', 'b4', 'final', 'outros'];
    $groupLabel = ['b1' => '1º BIM', 'b2' => '2º BIM', 'b3' => '3º BIM', 'b4' => '4º BIM', 'final' => 'FINAL', 'outros' => 'OUTROS'];
    $subLabel = ['media' => 'Média', 'faltas' => 'Faltas', 'rec' => 'Rec.', 'resultado' => 'Result.', 'other' => ''];
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
        $full = str_replace(['_', '-'], ' ', $nm . ' ' . $cd);
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
        if ($group === '') { $group = 'outros'; }
        return [$group, $sub, $label];
    };
    $grouped = [];
    $hasAnyGroup = false;
    foreach ($cols as $c) {
        [$g, $s, $label] = $parse((array) $c);
        if ($g === '') { continue; }
        $hasAnyGroup = true;
        $c['_subkey'] = $s;
        $c['_sublabel'] = $label;
        $grouped[$g][] = $c;
    }
    if (!$hasAnyGroup) { return ['enabled' => false, 'groups' => []]; }
    $groupsOut = [];
    foreach ($groupOrder as $gk) {
        $arr = $grouped[$gk] ?? [];
        if ($arr === []) { continue; }
        $groupsOut[] = ['key' => $gk, 'label' => $groupLabel[$gk], 'cols' => $arr];
    }
    return ['enabled' => true, 'groups' => $groupsOut];
};
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= $esc($titulo ?? 'Histórico Escolar') ?></title>
    <style>
        @page { margin: 14mm 12mm 16mm 12mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; color: #1f2937; font-size: 9pt; margin: 0; }
        .header { display: table; width: 100%; border-bottom: 2px solid #064e3b; padding-bottom: 8px; margin-bottom: 6px; }
        .header .logo-cell { display: table-cell; width: 80px; vertical-align: middle; }
        .header .title-cell { display: table-cell; vertical-align: middle; padding-left: 12px; }
        .header img { max-height: 58px; max-width: 76px; }
        .header .escola { font-size: 13pt; font-weight: bold; color: #064e3b; margin: 0 0 2px 0; }
        .header .meta { font-size: 8pt; color: #4b5563; margin: 1px 0; }
        .doc-num { text-align: right; font-size: 8pt; color: #6b7280; margin: 4px 0 6px 0; }
        h1.doc-title { text-align: center; font-size: 14pt; color: #111827; letter-spacing: 1px; text-transform: uppercase; margin: 4px 0 12px 0; }
        .aluno-info { margin-bottom: 12px; font-size: 9.5pt; }
        .aluno-info strong { color: #111827; }
        .ano-bloco { margin-bottom: 14px; }
        .ano-titulo { font-size: 10pt; font-weight: bold; color: #064e3b; background: #ecfdf5; border: 1px solid #a7f3d0; padding: 4px 8px; margin-bottom: 4px; }
        table.boletim { width: 100%; border-collapse: collapse; margin-bottom: 8px; font-size: 8pt; }
        table.boletim th, table.boletim td { border: 1px solid #d1d5db; padding: 3px 5px; vertical-align: middle; }
        table.boletim thead th { background: #f3f4f6; color: #1f2937; font-weight: bold; text-align: center; text-transform: uppercase; font-size: 7.5pt; }
        table.boletim thead th.sub { background: #fef3c7; }
        table.boletim td.materia { text-align: left; font-weight: bold; color: #111827; }
        table.boletim td.cell { text-align: center; }
        table.boletim td.num { color: #047857; font-weight: bold; }
        table.boletim tbody tr.even td { background: #f9fafb; }
        .vazio { color: #9ca3af; font-style: italic; padding: 8px 0; }
        .assinaturas { margin-top: 40px; width: 100%; display: table; }
        .assinaturas .sig { display: table-cell; width: 50%; text-align: center; vertical-align: bottom; padding: 0 16px; }
        .assinaturas .line { border-top: 1px solid #374151; margin: 0 auto 4px auto; width: 70%; padding-top: 4px; }
        .assinaturas .nome { font-size: 9pt; font-weight: bold; }
        .assinaturas .cargo { font-size: 8pt; color: #4b5563; }
        .fecho { margin-top: 24px; text-align: right; font-size: 9.5pt; }
        .footer { position: fixed; bottom: -10mm; left: 0; right: 0; text-align: center; font-size: 7pt; color: #9ca3af; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo-cell">
            <?php if ($logoData !== ''): ?><img src="<?= $esc($logoData) ?>" alt="Logo"><?php endif; ?>
        </div>
        <div class="title-cell">
            <p class="escola"><?= $esc($nomeUnidade) ?></p>
            <?php if ($linhaEndereco !== ''): ?><p class="meta"><?= $esc($linhaEndereco) ?></p><?php endif; ?>
            <?php if ($linhaDocs !== ''): ?><p class="meta"><?= $esc($linhaDocs) ?></p><?php endif; ?>
        </div>
    </div>

    <?php if (!empty($numero)): ?>
        <div class="doc-num">Documento nº <?= (int) $numero ?>/<?= (int) ($ano ?? date('Y')) ?></div>
    <?php endif; ?>

    <h1 class="doc-title"><?= $esc($titulo ?? 'Histórico Escolar') ?></h1>

    <div class="aluno-info">
        <strong>Aluno(a):</strong> <?= $esc($alunoNome) ?>
        <?php if ($alunoRa !== ''): ?> &nbsp;|&nbsp; <strong>Matrícula:</strong> <?= $esc($alunoRa) ?><?php endif; ?>
        <?php if ($alunoCpf !== ''): ?> &nbsp;|&nbsp; <strong>CPF:</strong> <?= $esc($alunoCpf) ?><?php endif; ?>
    </div>

    <?php if (empty($porAno)): ?>
        <p class="vazio">Nenhum boletim foi gerado para este aluno. O histórico será preenchido conforme os boletins forem gerados.</p>
    <?php else: ?>
        <?php foreach ($porAno as $anoLetivo => $eventos): ?>
            <div class="ano-bloco">
                <div class="ano-titulo">Ano Letivo <?= (int) $anoLetivo ?></div>
                <?php foreach ($eventos as $ev): ?>
                    <?php
                    $cols = is_array($ev['colunas'] ?? null) ? $ev['colunas'] : [];
                    $linhas = is_array($ev['linhas'] ?? null) ? $ev['linhas'] : [];
                    $decimalPlaces = ((int) ($ev['decimal_places'] ?? 2) === 1) ? 1 : 2;
                    if ($cols === [] || $linhas === []) { continue; }
                    $groupedHeader = $buildGroupedBoletimHeader($cols);
                    ?>
                    <table class="boletim">
                        <thead>
                            <?php if (!empty($groupedHeader['enabled'])): ?>
                                <tr>
                                    <th rowspan="2" style="width: 20%;">Matéria</th>
                                    <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                        <th colspan="<?= (int) count((array) ($grp['cols'] ?? [])) ?>"><?= $esc($grp['label'] ?? '') ?></th>
                                    <?php endforeach; ?>
                                </tr>
                                <tr>
                                    <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                        <?php foreach ((array) ($grp['cols'] ?? []) as $mc): ?>
                                            <th class="sub"><?= $esc($mc['_sublabel'] ?? $mc['nome'] ?? $mc['codigo'] ?? '') ?></th>
                                        <?php endforeach; ?>
                                    <?php endforeach; ?>
                                </tr>
                            <?php else: ?>
                                <tr>
                                    <th style="width: 20%;">Matéria</th>
                                    <?php foreach ($cols as $mc): ?>
                                        <th><?= $esc($mc['nome'] ?? $mc['codigo'] ?? '') ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endif; ?>
                        </thead>
                        <tbody>
                            <?php foreach ($linhas as $idxLin => $lin): ?>
                                <?php
                                $notasLin = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
                                $rowClass = ($idxLin % 2 === 0) ? 'odd' : 'even';
                                $iterCols = $cols;
                                if (!empty($groupedHeader['enabled'])) {
                                    $iterCols = [];
                                    foreach ((array) ($groupedHeader['groups'] ?? []) as $grp) {
                                        foreach ((array) ($grp['cols'] ?? []) as $gc) { $iterCols[] = $gc; }
                                    }
                                }
                                ?>
                                <tr class="<?= $rowClass ?>">
                                    <td class="materia"><?= $esc($lin['materia_nome'] ?? '-') ?></td>
                                    <?php foreach ($iterCols as $mc):
                                        $cod = (string) ($mc['codigo'] ?? '');
                                        $nv = $notasLin[$cod] ?? null;
                                        $isFaltasCol = ((string) ($mc['source_type'] ?? '')) === 'faltas_evento'
                                            || strtolower((string) ($mc['layout_type'] ?? '')) === 'faltas';
                                        $cellClass = is_numeric($nv) ? 'cell num' : 'cell';
                                    ?>
                                        <td class="<?= $cellClass ?>">
                                            <?php if (is_numeric($nv)): ?>
                                                <?= $isFaltasCol
                                                    ? $esc(number_format((float) round((float) $nv), 0, ',', '.'))
                                                    : $esc(number_format((float) $nv, $decimalPlaces, ',', '.')) ?>
                                            <?php elseif (is_string($nv) && trim($nv) !== ''): ?>
                                                <?= $esc($nv) ?>
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
            </div>
        <?php endforeach; ?>
    <?php endif; ?>

    <div class="fecho"><?= $esc($cidade_data ?? '') ?>.</div>

    <div class="assinaturas">
        <div class="sig">
            <div class="line"></div>
            <div class="nome"><?= $esc(trim((string) ($unidade['secretario_nome'] ?? ''))) ?: '&nbsp;' ?></div>
            <div class="cargo">Secretaria</div>
        </div>
        <div class="sig">
            <div class="line"></div>
            <div class="nome"><?= $esc(trim((string) ($unidade['diretor_nome'] ?? ''))) ?: '&nbsp;' ?></div>
            <div class="cargo">Direção</div>
        </div>
    </div>

    <div class="footer">
        Documento emitido eletronicamente em <?= $esc($gerado_em ?? date('d/m/Y')) ?> pela plataforma EducaTudo.
    </div>
</body>
</html>
