<?php
$boletins_gerados = is_array($boletins_gerados ?? null) ? $boletins_gerados : [];

/**
 * @param array<int, array<string, mixed>> $cols
 * @return array{enabled:bool,groups:array<int,array{key:string,label:string,cols:array<int,array<string,mixed>>}>}
 */
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

        $nm = mb_strtolower(trim((string) ($c['nome'] ?? '')), 'UTF-8');
        $cd = mb_strtolower(trim((string) ($c['codigo'] ?? '')), 'UTF-8');
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
        // Mantém exatamente a ordem dos blocos cadastrados.
        $groupsOut[] = ['key' => $gk, 'label' => $groupLabel[$gk], 'cols' => $arr];
    }

    return ['enabled' => true, 'groups' => $groupsOut];
};
?>

<?php
// Quando $boletim_pode_excluir for true (visão de coordenação/admin), exibimos um
// pequeno cabeçalho por tabela com o nome/código/ID da regra, para identificar
// rapidamente cada boletim quando há mais de um. A exclusão deixou de ser feita
// nesta tela — a coordenação gerencia tudo em /admin/boletim-configuracao/gerados.
$_mostrarHeaderRegra = (bool) ($boletim_pode_excluir ?? false);
?>
<?php if (!empty($boletins_gerados)): ?>
    <div class="space-y-6">
        <?php foreach ($boletins_gerados as $ev): ?>
            <?php
            $cols = is_array($ev['colunas'] ?? null) ? $ev['colunas'] : [];
            $linhas = is_array($ev['linhas'] ?? null) ? $ev['linhas'] : [];
            $decimalPlaces = ((int) ($ev['decimal_places'] ?? 2) === 1) ? 1 : 2;
            if ($cols === [] || $linhas === []) {
                continue;
            }
            $groupedHeader = $buildGroupedBoletimHeader($cols);
            $_regraIdLinha = (int) ($ev['regra_id'] ?? 0);
            $_regraNomeLinha = (string) ($ev['regra_nome'] ?? '');
            $_regraCodigoLinha = (string) ($ev['regra_codigo'] ?? '');
            $_mostrarHeaderInfo = $_mostrarHeaderRegra && $_regraIdLinha > 0;
            if (!class_exists('BoletimQuadroLayoutHelper', false)) {
                require_once dirname(__DIR__, 2) . '/Helpers/BoletimQuadroLayoutHelper.php';
            }
            $ehQuadroSemanal = BoletimQuadroLayoutHelper::ehLayoutQuadro($cols);
            ?>
            <div class="boletim-tabela-wrapper" data-regra-id="<?= $_regraIdLinha ?>" data-regra-nome="<?= htmlspecialchars($_regraNomeLinha, ENT_QUOTES, 'UTF-8') ?>">
                <?php if ($_mostrarHeaderInfo): ?>
                    <div class="mb-2">
                        <div class="text-sm font-semibold text-gray-900 flex items-center gap-2 flex-wrap">
                            <span><?= htmlspecialchars($_regraNomeLinha !== '' ? $_regraNomeLinha : ('Regra #' . $_regraIdLinha), ENT_QUOTES, 'UTF-8') ?></span>
                            <span class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">#<?= $_regraIdLinha ?></span>
                            <?php if ($_regraCodigoLinha !== ''): ?>
                                <span class="text-[11px] font-medium px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700"><?= htmlspecialchars($_regraCodigoLinha, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
                <?php if ($ehQuadroSemanal): ?>
                    <?php include __DIR__ . '/boletim_quadro_tabela.php'; ?>
                <?php else: ?>
                <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-slate-50">
                        <?php if (!empty($groupedHeader['enabled'])): ?>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-700 uppercase tracking-wide sticky left-0 bg-slate-50 z-10 border-r border-gray-200" rowspan="2">Matéria</th>
                                <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 uppercase border-l border-gray-200" colspan="<?= (int) count((array) ($grp['cols'] ?? [])) ?>">
                                        <?= htmlspecialchars((string) ($grp['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                            <tr>
                                <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                    <?php foreach ((array) ($grp['cols'] ?? []) as $mc): ?>
                                        <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 bg-amber-100/70 min-w-[5.5rem]" title="<?= htmlspecialchars((string) ($mc['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars((string) ($mc['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)">
                                            <span class="block truncate max-w-[8rem] mx-auto"><?= htmlspecialchars((string) ($mc['_sublabel'] ?? $mc['nome'] ?? $mc['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        </th>
                                    <?php endforeach; ?>
                                <?php endforeach; ?>
                            </tr>
                        <?php else: ?>
                            <tr>
                                <th class="px-3 py-2 text-left text-xs font-semibold text-slate-700 uppercase tracking-wide sticky left-0 bg-slate-50 z-10 border-r border-gray-200">Matéria</th>
                                <?php foreach ($cols as $mc): ?>
                                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 min-w-[5.5rem]">
                                        <span class="block truncate max-w-[8rem] mx-auto"><?= htmlspecialchars((string) ($mc['nome'] ?? $mc['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="block text-[10px] font-normal text-slate-400 normal-case">(<?= htmlspecialchars((string) ($mc['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</span>
                                    </th>
                                <?php endforeach; ?>
                            </tr>
                        <?php endif; ?>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($linhas as $idxLin => $lin): ?>
                            <?php
                            $notasLin = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
                            // Zebra striping: linhas pares cinza claro, ímpares branco.
                            $rowBg = ($idxLin % 2 === 0) ? 'bg-white' : 'bg-gray-50';
                            ?>
                            <tr class="<?= $rowBg ?> hover:bg-gray-100">
                                <td class="px-3 py-2 font-medium text-gray-900 sticky left-0 <?= $rowBg ?> z-10 border-r border-gray-200">
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
                                ?>
                                    <?php
                                    $cod = (string) ($mc['codigo'] ?? '');
                                    $nv = $notasLin[$cod] ?? null;
                                    // Colunas de faltas devem mostrar inteiros (faltas_evento direto OU layout_type='faltas' no FINAL/calculado).
                                    $isFaltasCol = ((string) ($mc['source_type'] ?? '')) === 'faltas_evento'
                                        || strtolower((string) ($mc['layout_type'] ?? '')) === 'faltas';
                                    ?>
                                    <td class="px-3 py-2 text-center <?= is_numeric($nv) ? 'text-emerald-700 font-semibold' : (is_string($nv) && trim($nv) !== '' ? 'text-slate-700 font-medium' : 'text-gray-400') ?>">
                                        <?php if (is_numeric($nv)): ?>
                                            <?= $isFaltasCol ? htmlspecialchars(number_format((float) round((float) $nv), 0, ',', '.'), ENT_QUOTES, 'UTF-8') : htmlspecialchars(number_format((float) $nv, $decimalPlaces, ',', '.'), ENT_QUOTES, 'UTF-8') ?>
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
                </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
