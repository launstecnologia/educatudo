<?php
$regra = $regra ?? [];
$componentes = $regra['componentes'] ?? [];
$alunos = $alunos ?? [];
$blocosProvas = $blocos_provas ?? [];
$materias = $materias ?? [];
$series = $series ?? [];
$turmas = $turmas ?? [];
$regrasCatalogo = $regras_catalogo ?? [];
$faltasEventosCatalogo = $faltas_eventos_catalogo ?? [];
$selectedRegraId = (int) ($selected_regra_id ?? 0);
$materiasSelecionadasRegra = array_map('intval', (array) ($regra['materias_ids_array'] ?? []));
$seriesSelecionadasRegra = array_map('intval', (array) ($regra['series_ids_array'] ?? []));
$turmasSelecionadasRegra = array_map('intval', (array) ($regra['turmas_ids_array'] ?? []));
$formulaMateriasMap = (array) ($regra['formula_materias_map'] ?? []);
$selectedAlunoId = (int) ($selected_aluno_id ?? 0);
$periodoRef = (string) ($periodo_ref ?? '');
$dataInicio = (string) ($data_inicio ?? '');
$dataFim = (string) ($data_fim ?? '');
$simulacao = $simulacao ?? null;
$flashMessage = (string) ($flash_message ?? '');
$flashType = (string) ($flash_type ?? 'success');
$csrfToken = (string) ($csrf_token ?? '');
$somenteTabela = !empty($somente_tabela);
$boletimAssistenteDisponivel = !empty($boletim_assistente_disponivel);
$roundModeSelected = strtolower(trim((string) ($regra['round_mode'] ?? 'none')));
if (!in_array($roundModeSelected, ['none', 'half'], true)) {
    $roundModeSelected = 'none';
}
$decimalPlacesSelected = ((int) ($regra['decimal_places'] ?? 2) === 1) ? 1 : 2;
$formatNotaBoletim = static function ($valor) use ($decimalPlacesSelected): string {
    return number_format((float) $valor, $decimalPlacesSelected, ',', '.');
};
/**
 * Monta layout agrupado tipo boletim oficial:
 * 1º BIMESTRE | 2º BIMESTRE | 3º BIMESTRE | 4º BIMESTRE | FINAL
 * com subcolunas (Média, Faltas, Rec., Resultado).
 *
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

$componentesInicial = [];
$materiasById = [];
foreach ($materias as $materiaItem) {
    $materiaId = (int) ($materiaItem['id'] ?? 0);
    if ($materiaId > 0) {
        $materiasById[$materiaId] = (string) ($materiaItem['nome'] ?? ('Matéria #' . $materiaId));
    }
}

foreach ($componentes as $comp) {
    $rawBlocos = trim((string) ($comp['blocos_ids'] ?? ''));
    $blocosArr = [];
    if ($rawBlocos !== '') {
        foreach (explode(',', $rawBlocos) as $part) {
            $bid = (int) trim($part);
            if ($bid > 0) {
                $blocosArr[] = $bid;
            }
        }
    }
    $cfgJ = [
        'jornada_ids' => [],
        'data_ini' => '',
        'data_fim' => '',
        'faixas_percentuais' => [],
        'expressao' => '',
        'regra_codigo' => '',
        'componente_codigo' => '',
        'faltas_evento_id' => 0,
        'formula_materias' => [],
        'formula_mode' => 'single',
        'group_line' => [
            'enabled' => false,
            'key' => '',
            'label' => '',
            'mode' => 'media',
            'divisor' => 0,
            'materias_ids' => [],
        ],
        'layout_group' => '',
        'layout_type' => '',
        'traco_abaixo_minimo' => false,
        'distribuicao_notas' => 'por_materia',
        'nota_unica_omitir_materias' => [],
        'nota_unica_fonte_por_materia' => [],
        'nota_unica_fonte_por_grupo' => [],
    ];
    $materiasIdsComp = [];
    $rawMateriasComp = trim((string) ($comp['materias_ids'] ?? ''));
    if ($rawMateriasComp !== '') {
        $decMat = json_decode($rawMateriasComp, true);
        if (is_array($decMat)) {
            foreach ($decMat as $midComp) {
                $midComp = (int) $midComp;
                if ($midComp > 0) {
                    $materiasIdsComp[] = $midComp;
                }
            }
            $materiasIdsComp = array_values(array_unique($materiasIdsComp));
        }
    }
    $rawCj = trim((string) ($comp['config_json'] ?? ''));
    if ($rawCj !== '') {
        $dec = json_decode($rawCj, true);
        if (is_array($dec)) {
            $jids = [];
            foreach ((array) ($dec['jornada_ids'] ?? []) as $jid) {
                $jid = (int) $jid;
                if ($jid > 0) {
                    $jids[] = $jid;
                }
            }
            $cfgJ['jornada_ids'] = array_values(array_unique($jids));
            $cfgJ['data_ini'] = (string) ($dec['data_ini'] ?? '');
            $cfgJ['data_fim'] = (string) ($dec['data_fim'] ?? '');
            if (isset($dec['faixas_percentuais']) && is_array($dec['faixas_percentuais'])) {
                $fx = [];
                foreach ($dec['faixas_percentuais'] as $fItem) {
                    if (!is_array($fItem)) {
                        continue;
                    }
                    $pctMin = (int) ($fItem['percentual_min'] ?? 0);
                    $nota = is_numeric($fItem['nota'] ?? null) ? (float) $fItem['nota'] : null;
                    if ($pctMin >= 0 && $pctMin <= 100 && $nota !== null) {
                        $fx[] = [
                            'percentual_min' => $pctMin,
                            'nota' => $nota,
                        ];
                    }
                }
                $cfgJ['faixas_percentuais'] = $fx;
            }
            $cfgJ['expressao'] = (string) ($dec['expressao'] ?? '');
            $cfgJ['traco_abaixo_minimo'] = !empty($dec['traco_abaixo_minimo']);
            $cfgJ['regra_codigo'] = (string) ($dec['regra_codigo'] ?? '');
            $cfgJ['componente_codigo'] = (string) ($dec['componente_codigo'] ?? '');
            $cfgJ['faltas_evento_id'] = (int) ($dec['faltas_evento_id'] ?? 0);
            if (isset($dec['formula_materias']) && is_array($dec['formula_materias'])) {
                $fm = [];
                foreach ($dec['formula_materias'] as $midCfg => $exprCfg) {
                    $midCfg = (int) $midCfg;
                    $exprCfg = trim((string) $exprCfg);
                    if ($midCfg > 0 && $exprCfg !== '') {
                        $fm[$midCfg] = $exprCfg;
                    }
                }
                $cfgJ['formula_materias'] = $fm;
            }
            $modeFm = strtolower(trim((string) ($dec['formula_mode'] ?? '')));
            $hasFmEx = isset($cfgJ['formula_materias']) && is_array($cfgJ['formula_materias']) && $cfgJ['formula_materias'] !== [];
            $cfgJ['formula_mode'] = ($modeFm === 'per_materia' || $hasFmEx) ? 'per_materia' : 'single';
            if (isset($dec['group_line']) && is_array($dec['group_line'])) {
                $g = $dec['group_line'];
                $gm = [];
                foreach ((array) ($g['materias_ids'] ?? []) as $gmid) {
                    $gmid = (int) $gmid;
                    if ($gmid > 0) {
                        $gm[] = $gmid;
                    }
                }
                $cfgJ['group_line'] = [
                    'enabled' => !empty($g['enabled']),
                    'key' => (string) ($g['key'] ?? ''),
                    'label' => (string) ($g['label'] ?? ''),
                    'mode' => (string) ($g['mode'] ?? 'media'),
                    'divisor' => (float) ($g['divisor'] ?? 0),
                    'materias_ids' => array_values(array_unique($gm)),
                ];
            }
            if (isset($dec['layout']) && is_array($dec['layout'])) {
                $cfgJ['layout_group'] = strtolower(trim((string) ($dec['layout']['group'] ?? '')));
                $cfgJ['layout_type'] = strtolower(trim((string) ($dec['layout']['type'] ?? '')));
            }
            $dNotas = strtolower(trim((string) ($dec['distribuicao_notas'] ?? '')));
            $cfgJ['distribuicao_notas'] = ($dNotas === 'nota_unica_todas_linhas') ? 'nota_unica_todas_linhas' : 'por_materia';
            $omits = [];
            foreach ((array) ($dec['nota_unica_omitir_materias'] ?? []) as $omid) {
                $omid = (int) $omid;
                if ($omid !== 0) {
                    $omits[] = $omid;
                }
            }
            $cfgJ['nota_unica_omitir_materias'] = array_values(array_unique($omits));
            $fpMat = [];
            foreach ((array) ($dec['nota_unica_fonte_por_materia'] ?? []) as $tk => $list) {
                $tki = (int) $tk;
                if ($tki === 0) {
                    continue;
                }
                $idsF = [];
                foreach ((array) $list as $z) {
                    $zi = (int) $z;
                    if ($zi > 0) {
                        $idsF[] = $zi;
                    }
                }
                $idsF = array_values(array_unique($idsF));
                if ($idsF !== []) {
                    $fpMat[$tki] = $idsF;
                }
            }
            $cfgJ['nota_unica_fonte_por_materia'] = $fpMat;
            $fpGrp = [];
            foreach ((array) ($dec['nota_unica_fonte_por_grupo'] ?? []) as $gk => $list) {
                $gk = trim((string) $gk);
                if ($gk === '') {
                    continue;
                }
                $idsG = [];
                foreach ((array) $list as $z) {
                    $zi = (int) $z;
                    if ($zi > 0) {
                        $idsG[] = $zi;
                    }
                }
                $idsG = array_values(array_unique($idsG));
                if ($idsG !== []) {
                    $fpGrp[$gk] = $idsG;
                }
            }
            $cfgJ['nota_unica_fonte_por_grupo'] = $fpGrp;
        }
    }

    $componentesInicial[] = [
        'id' => (int) ($comp['id'] ?? 0),
        'codigo' => (string) ($comp['codigo'] ?? ''),
        'nome' => (string) ($comp['nome'] ?? ''),
        'source_type' => (string) ($comp['source_type'] ?? 'provas_sistema'),
        'calc_type' => (string) ($comp['calc_type'] ?? 'media'),
        'peso' => (float) ($comp['peso'] ?? 1),
        'filtro_titulo' => (string) ($comp['filtro_titulo'] ?? ''),
        'bloco_id' => (int) ($comp['bloco_id'] ?? 0),
        'blocos_ids' => $blocosArr,
        'config' => $cfgJ,
        'materia_id' => (int) ($comp['materia_id'] ?? 0),
        'materias_ids' => $materiasIdsComp,
        'materia_unica' => (int) ($comp['materia_unica'] ?? 0) === 1,
        'usar_percentual' => (int) ($comp['usar_percentual'] ?? 0) === 1,
        'escala_max' => (float) ($comp['escala_max'] ?? 10),
        'obrigatorio' => (int) ($comp['obrigatorio'] ?? 0) === 1,
    ];
}

$manualRows = [];
if (is_array($simulacao) && !empty($simulacao['componentes'])) {
    foreach ($simulacao['componentes'] as $item) {
        if (($item['source_type'] ?? '') !== 'manual') {
            continue;
        }
        $manualRows[] = $item;
    }
}

$regraIdBoletim = (int) ($regra['id'] ?? 0);
$matrizSimGravar = is_array($simulacao) ? ($simulacao['matriz_materias'] ?? null) : null;
$linhasBoletimGravar = is_array($matrizSimGravar) && !empty($matrizSimGravar['linhas']) ? $matrizSimGravar['linhas'] : [];
$colunasBoletimGravar = is_array($matrizSimGravar) && !empty($matrizSimGravar['colunas']) ? $matrizSimGravar['colunas'] : [];
$podeGravarBoletimOficialAluno = $regraIdBoletim > 0 && $selectedAlunoId > 0 && $linhasBoletimGravar !== [] && $colunasBoletimGravar !== [];
?>

<?php if ($somenteTabela): ?>
    <div class="p-3">
        <?php if (is_array($simulacao)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
                <div class="px-4 py-3 border-b border-gray-200">
                    <h2 class="text-base font-semibold text-gray-900">Resultado da Simulação</h2>
                    <p class="text-xs text-gray-500 mt-1">
                        Aluno: <strong><?= htmlspecialchars((string) (($simulacao['aluno']['nome'] ?? '-') )) ?></strong>
                    </p>
                </div>

                <div class="p-4">
                    <?php
                    $matrizSim = $simulacao['matriz_materias'] ?? null;
                    $matrizLinhas = is_array($matrizSim) && !empty($matrizSim['linhas']) ? $matrizSim['linhas'] : [];
                    $matrizColunas = is_array($matrizSim) && !empty($matrizSim['colunas']) ? $matrizSim['colunas'] : [];
                    $groupedHeader = $buildGroupedBoletimHeader((array) $matrizColunas);
                    ?>
                    <?php if (!empty($matrizLinhas) && !empty($matrizColunas)): ?>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-slate-50">
                                    <?php if (!empty($groupedHeader['enabled'])): ?>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-700 uppercase tracking-wide sticky left-0 bg-slate-50 z-10 border-r border-gray-200" rowspan="2">Matérias</th>
                                            <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 uppercase border-l border-gray-200" colspan="<?= (int) count((array) ($grp['cols'] ?? [])) ?>">
                                                    <?= htmlspecialchars((string) ($grp['label'] ?? '')) ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                        <tr>
                                            <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                                <?php foreach ((array) ($grp['cols'] ?? []) as $mc): ?>
                                                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 bg-amber-100/70 min-w-[5.5rem]" title="<?= htmlspecialchars((string) ($mc['nome'] ?? '')) ?> (<?= htmlspecialchars((string) ($mc['codigo'] ?? '')) ?>)">
                                                        <span class="block truncate max-w-[7rem] mx-auto"><?= htmlspecialchars((string) ($mc['_sublabel'] ?? $mc['nome'] ?? $mc['codigo'] ?? '')) ?></span>
                                                    </th>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-700 uppercase tracking-wide sticky left-0 bg-slate-50 z-10 border-r border-gray-200">Matéria</th>
                                            <?php foreach ($matrizColunas as $mc): ?>
                                                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 min-w-[5.5rem]" title="<?= htmlspecialchars((string) ($mc['nome'] ?? '')) ?> (<?= htmlspecialchars((string) ($mc['codigo'] ?? '')) ?>)">
                                                    <span class="block truncate max-w-[7rem] mx-auto"><?= htmlspecialchars((string) ($mc['nome'] ?? $mc['codigo'] ?? '')) ?></span>
                                                    <span class="block text-[10px] font-normal text-slate-400 normal-case">(<?= htmlspecialchars((string) ($mc['codigo'] ?? '')) ?>)</span>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endif; ?>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <?php foreach ($matrizLinhas as $idxLin => $lin): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 font-medium text-gray-900 sticky left-0 bg-white z-10 border-r border-gray-200"><?= htmlspecialchars((string) ($lin['materia_nome'] ?? '-')) ?></td>
                                            <?php
                                            $notasLin = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
                                            $iterCols = $matrizColunas;
                                            if (!empty($groupedHeader['enabled'])) {
                                                $iterCols = [];
                                                foreach ((array) ($groupedHeader['groups'] ?? []) as $g) {
                                                    foreach ((array) ($g['cols'] ?? []) as $gc) {
                                                        $iterCols[] = $gc;
                                                    }
                                                }
                                            }
                                            foreach ($iterCols as $mc):
                                                $codM = (string) ($mc['codigo'] ?? '');
                                                $nv = $notasLin[$codM] ?? null;
                                                $colGlobal = !empty($mc['valor_global']);
                                                // Colunas de faltas devem mostrar inteiros tanto em bimestre (source_type='faltas_evento')
                                                // quanto na linha FINAL (calculado/evento_boletim com layout_type='faltas').
                                                $isFaltasCol = ((string) ($mc['source_type'] ?? '')) === 'faltas_evento'
                                                    || strtolower((string) ($mc['layout_type'] ?? '')) === 'faltas';
                                            ?>
                                                <td class="px-3 py-2 text-center <?= is_numeric($nv) ? 'text-emerald-700 font-semibold' : (is_string($nv) && trim($nv) !== '' ? 'text-slate-700 font-medium' : 'text-gray-400') ?>">
                                                    <?php if (is_numeric($nv) && $colGlobal && $idxLin > 0): ?>
                                                        <span class="text-xs font-medium text-slate-500">idem</span>
                                                    <?php elseif (is_numeric($nv)): ?>
                                                        <?= $isFaltasCol ? number_format((float) round((float) $nv), 0, ',', '.') : $formatNotaBoletim($nv) ?>
                                                    <?php elseif (is_string($nv) && trim($nv) !== ''): ?>
                                                        <?= htmlspecialchars($nv) ?>
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
                    <?php else: ?>
                        <div class="text-sm text-gray-500">Sem dados de simulação para o aluno/evento selecionado.</div>
                    <?php endif; ?>
                    <?php if ($podeGravarBoletimOficialAluno): ?>
                        <form method="POST" action="<?= URL ?>/admin/boletim-configuracao/publicar-boletim-aluno" class="mt-4 pt-4 border-t border-gray-200 space-y-2">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                            <input type="hidden" name="regra_id" value="<?= $regraIdBoletim ?>">
                            <input type="hidden" name="aluno_id" value="<?= $selectedAlunoId ?>">
                            <input type="hidden" name="periodo_ref" value="<?= htmlspecialchars($periodoRef) ?>">
                            <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
                            <input type="hidden" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
                            <p class="text-xs text-gray-600">Grava no sistema o boletim <strong>oficial</strong> só deste aluno e período (substitui boletim anterior dele, se existir). Não gera para os demais alunos do evento.</p>
                            <button type="submit" class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2 text-sm font-semibold rounded-lg hover:opacity-90 shadow-sm">
                                <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                                Gravar boletim oficial (só este aluno)
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="p-3 text-sm text-gray-500 border border-gray-200 rounded-lg bg-white">Selecione um aluno e salve o evento para visualizar a tabela.</div>
        <?php endif; ?>
    </div>
<?php else: ?>
<div class="space-y-6">
    <div class="flex items-start justify-between gap-3 flex-wrap">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Configuração de Boletim</h1>
            <p class="text-sm text-gray-500 mt-1">Monte a regra da escola por blocos e simule a nota final com dados reais do aluno.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button" id="boletim-abrir-assistente-guiado"
                    class="inline-flex items-center gap-2 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg text-sm font-medium shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                Assistente guiado
            </button>
            <a href="<?= URL ?>/admin/boletim"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 rounded-lg text-sm font-medium shrink-0">
                <i class="fa-solid fa-arrow-left"></i>
                Todos os eventos
            </a>
            <a href="<?= URL ?>/admin/boletim-configuracao/gerados"
               class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-indigo-200 hover:bg-indigo-50 text-indigo-700 rounded-lg text-sm font-medium shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 17v-6m4 6V7m4 10v-4M3 5h18v14H3V5z" />
                </svg>
                Boletins Gerados
            </a>
        </div>
    </div>
    <div>
        <p class="text-sm text-indigo-800 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2">
            <strong>Coordenação:</strong> use o <button type="button" class="underline font-medium text-indigo-900" onclick="document.getElementById('boletim-assistente-toggle')?.click()">Assistente guiado</button>
            (passos + chat) ou o <a href="<?= URL ?>/admin/boletim-guia" class="underline font-medium">Guia do Boletim</a> com modelos prontos.
            <strong>Jornadas:</strong> na origem do bloco use <strong>Jornadas (automático)</strong> — vale conclusão da jornada, não acerto de questões.
            Na <strong>fórmula final</strong> você pode usar <code class="bg-white px-1 rounded">max(a,b)</code> e <code class="bg-white px-1 rounded">min(a,b)</code>.
        </p>
    </div>

    <?php if ($flashMessage !== ''): ?>
        <?php $bg = $flashType === 'success' ? 'bg-green-50 border-green-200 text-green-800' : 'bg-red-50 border-red-200 text-red-800'; ?>
        <div class="p-4 rounded-lg border <?= $bg ?>"><?= htmlspecialchars($flashMessage) ?></div>
    <?php endif; ?>

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Evento do Boletim</h2>
            </div>

            <form method="POST" action="<?= URL ?>/admin/boletim-configuracao/salvar" class="p-5 space-y-5" id="form-regra-boletim">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                <input type="hidden" name="regra_id" value="<?= (int) ($regra['id'] ?? 0) ?>">
                <input type="hidden" name="componentes_json" id="componentes-json" value="<?= htmlspecialchars(json_encode($componentesInicial, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?>">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="flex flex-col">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nome do evento</label>
                        <input type="text" id="regra-nome" name="regra_nome" required value="<?= htmlspecialchars((string) ($regra['nome'] ?? 'Evento padrão da escola')) ?>" class="w-full h-10 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Bimestral 1 - Ensino Médio 2026">
                        <div class="mt-1 min-h-[2.75rem]" aria-hidden="true"></div>
                    </div>
                    <div class="flex flex-col">
                        <label class="block text-sm font-medium text-gray-700 mb-1">
                            Código do evento (slug)
                            <span class="text-xs font-normal text-gray-400">— gerado automaticamente</span>
                        </label>
                        <input type="text" id="regra-codigo" name="regra_codigo" value="<?= htmlspecialchars((string) ($regra['codigo'] ?? '')) ?>" readonly class="w-full h-10 px-3 border border-gray-200 bg-gray-50 text-gray-600 rounded-lg" placeholder="bimestral-1-ensino-medio-2026">
                        <button type="button" id="btn-editar-codigo-evento" class="text-xs text-indigo-600 hover:underline mt-1 text-left">Editar código manualmente</button>
                        <p class="text-xs text-gray-500 mt-1 min-h-[1.5rem] leading-snug">Esse código é usado para outros eventos buscarem a nota deste aqui (ex.: o Boletim final busca a nota no evento bimestral).</p>
                    </div>
                    <div class="md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Descrição curta (opcional)</label>
                        <input type="text" id="regra-descricao-curta" name="regra_descricao_curta" maxlength="255" value="<?= htmlspecialchars((string) ($regra['descricao_curta'] ?? '')) ?>" class="w-full h-10 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Ex.: Evento do 1º bimestre para composição da média">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Arredondamento final</label>
                        <select name="round_mode" class="w-full h-10 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="none" <?= $roundModeSelected === 'none' ? 'selected' : '' ?>>Sem arredondamento especial (2 casas)</option>
                            <option value="half" <?= $roundModeSelected === 'half' ? 'selected' : '' ?>>Faixa .00 / .50 / próximo inteiro</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1 min-h-[2.75rem] leading-snug">Regra: decimal &lt; 0,25 = .00, de 0,25 a &lt; 0,75 = .50, e ≥ 0,75 sobe para o próximo inteiro.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Casas decimais no boletim</label>
                        <select name="decimal_places" class="w-full h-10 px-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="2" <?= $decimalPlacesSelected === 2 ? 'selected' : '' ?>>2 casas (ex.: 9,00)</option>
                            <option value="1" <?= $decimalPlacesSelected === 1 ? 'selected' : '' ?>>1 casa (ex.: 9,0)</option>
                        </select>
                        <p class="text-xs text-gray-500 mt-1 min-h-[2.75rem] leading-snug">Define a exibição das notas geradas para alunos, pais e coordenação.</p>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Matérias que entram no boletim (opcional)</label>
                    <div class="border border-gray-300 rounded-lg p-3 max-h-64 overflow-y-auto bg-white">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <?php foreach ($materias as $materia): ?>
                                <?php $mid = (int) ($materia['id'] ?? 0); ?>
                                <?php if ($mid <= 0) { continue; } ?>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                    <input
                                        type="checkbox"
                                        name="materias_ids[]"
                                        value="<?= $mid ?>"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        <?= in_array($mid, $materiasSelecionadasRegra, true) ? 'checked' : '' ?>
                                    >
                                    <span><?= htmlspecialchars((string) ($materia['nome'] ?? ('Matéria #' . $mid))) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">
                        Se não selecionar nenhuma, o sistema usa todas as matérias.
                    </p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Séries que podem usar este evento (opcional)</label>
                    <div class="border border-gray-300 rounded-lg p-3 max-h-64 overflow-y-auto bg-white">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <?php foreach ($series as $serie): ?>
                                <?php $sid = (int) ($serie['id'] ?? 0); ?>
                                <?php if ($sid <= 0) { continue; } ?>
                                <?php
                                $nomeSerie = trim((string) ($serie['nome'] ?? ('Série #' . $sid)));
                                $cursoSerie = trim((string) ($serie['curso_nome'] ?? ''));
                                ?>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                    <input
                                        type="checkbox"
                                        name="series_ids[]"
                                        value="<?= $sid ?>"
                                        class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                        <?= in_array($sid, $seriesSelecionadasRegra, true) ? 'checked' : '' ?>
                                    >
                                    <span><?= htmlspecialchars($nomeSerie . ($cursoSerie !== '' ? (' - ' . $cursoSerie) : '')) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Se não selecionar nenhuma, o evento fica disponível para todas as séries.</p>
                </div>

                <div>
                    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 mb-2">
                        <label class="block text-sm font-medium text-gray-700">Turmas que podem usar este evento (opcional)</label>
                        <input type="search" id="filtro-turmas-regra" placeholder="Buscar turma"
                               class="w-full sm:w-64 h-9 px-3 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div class="border border-gray-300 rounded-lg p-3 max-h-64 overflow-y-auto bg-white" id="lista-turmas-regra">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                            <?php foreach ($turmas as $turma): ?>
                                <?php
                                $tid = (int) ($turma['id'] ?? 0);
                                if ($tid <= 0) { continue; }
                                $turmaNome = trim((string) ($turma['nome'] ?? ('Turma #' . $tid)));
                                $serieNome = trim((string) ($turma['serie_nome'] ?? ''));
                                $cursoNome = trim((string) ($turma['curso_nome'] ?? ''));
                                $anoTurma = (int) ($turma['ano_letivo'] ?? 0);
                                $rotuloTurma = $turmaNome;
                                if ($serieNome !== '' && stripos($turmaNome, $serieNome) === false) {
                                    $rotuloTurma .= ' · ' . $serieNome;
                                }
                                if ($cursoNome !== '' && stripos($rotuloTurma, $cursoNome) === false) {
                                    $rotuloTurma .= ' · ' . $cursoNome;
                                }
                                if ($serieNome === '') {
                                    $rotuloTurma .= ' · Sem série';
                                }
                                if ($anoTurma > 0) {
                                    $rotuloTurma .= ' · ' . $anoTurma;
                                }
                                ?>
                                <label class="turma-regra-item inline-flex items-center gap-2 text-sm text-gray-800"
                                       data-serie-id="<?= (int) ($turma['serie_id'] ?? 0) ?>"
                                       data-busca="<?= htmlspecialchars(mb_strtolower($rotuloTurma, 'UTF-8')) ?>">
                                    <input type="checkbox" name="turmas_ids[]" value="<?= $tid ?>"
                                           class="turma-regra-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500"
                                           <?= in_array($tid, $turmasSelecionadasRegra, true) ? 'checked' : '' ?>>
                                    <span><?= htmlspecialchars($rotuloTurma) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <p class="text-xs text-gray-500 mt-1">Turmas sem série continuam disponíveis para seleção. Quando selecionar uma ou mais turmas, elas terão prioridade sobre o filtro de séries.</p>
                </div>

                <script>
                (function () {
                    var busca = document.getElementById('filtro-turmas-regra');
                    var itens = Array.prototype.slice.call(document.querySelectorAll('.turma-regra-item'));
                    var seriesChecks = Array.prototype.slice.call(document.querySelectorAll('input[name="series_ids[]"]'));
                    function normalizar(valor) {
                        return String(valor || '').toLocaleLowerCase('pt-BR').normalize('NFD').replace(/[\u0300-\u036f]/g, '');
                    }
                    function filtrarTurmas() {
                        var termo = normalizar(busca ? busca.value : '');
                        var seriesAtivas = {};
                        seriesChecks.forEach(function (el) { if (el.checked) seriesAtivas[String(el.value)] = true; });
                        var temSerie = Object.keys(seriesAtivas).length > 0;
                        itens.forEach(function (item) {
                            var checkbox = item.querySelector('.turma-regra-checkbox');
                            var serieId = String(item.dataset.serieId || '');
                            var semSerie = serieId === '' || serieId === '0';
                            var atendeSerie = !temSerie || semSerie || !!seriesAtivas[serieId] || (checkbox && checkbox.checked);
                            var atendeBusca = termo === '' || normalizar(item.dataset.busca).indexOf(termo) !== -1;
                            item.style.display = atendeSerie && atendeBusca ? '' : 'none';
                        });
                    }
                    if (busca) busca.addEventListener('input', filtrarTurmas);
                    seriesChecks.forEach(function (el) { el.addEventListener('change', filtrarTurmas); });
                    filtrarTurmas();
                })();
                </script>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Exibição</label>
                        <?php $exibirEm = strtolower(trim((string) ($regra['exibir_em'] ?? 'boletim'))); ?>
                        <div class="flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                <input type="radio" name="exibir_em" value="notas" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= $exibirEm === 'notas' ? 'checked' : '' ?>>
                                <span>Notas</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                <input type="radio" name="exibir_em" value="boletim" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= $exibirEm !== 'notas' ? 'checked' : '' ?>>
                                <span>Boletim</span>
                            </label>
                        </div>
                    </div>
                    <div id="wrap-exibicao-filtros" class="md:col-span-2 grid grid-cols-1 md:grid-cols-3 gap-4 md:items-start">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Ano letivo</label>
                            <select name="ano_letivo" id="regra-ano-letivo" class="w-full h-10 px-3 border border-gray-300 rounded-lg">
                                <?php $anoRegra = (int) ($regra['ano_letivo'] ?? (int) date('Y')); ?>
                                <?php foreach (($anos_letivos_catalogo ?? []) as $anoOpt): ?>
                                    <?php $anoOpt = (int) $anoOpt; if ($anoOpt <= 0) { continue; } ?>
                                    <option value="<?= $anoOpt ?>" <?= $anoRegra === $anoOpt ? 'selected' : '' ?>><?= $anoOpt ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nota mínima para passar</label>
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:flex-wrap sm:gap-x-3">
                                <input type="number" name="nota_minima_aprovacao" min="0" max="10" step="0.01" value="<?= htmlspecialchars(number_format((float) (($regra['nota_minima_aprovacao'] ?? 6)), 2, '.', '')) ?>" class="w-full sm:w-32 sm:shrink-0 h-10 px-3 border border-gray-300 rounded-lg box-border">
                                <label class="inline-flex items-center gap-2 text-xs text-gray-700 whitespace-nowrap shrink-0">
                                    <input type="checkbox" name="usar_resultado_aprovacao" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !isset($regra['usar_resultado_aprovacao']) || (int) $regra['usar_resultado_aprovacao'] === 1 ? 'checked' : '' ?>>
                                    <span>Mostrar aprovado/reprovado</span>
                                </label>
                            </div>
                        </div>
                        <div id="wrap-exibicao-bimestre">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Bimestre</label>
                            <?php $bimRegra = (int) ($regra['bimestre'] ?? 0); ?>
                            <select name="bimestre" id="regra-bimestre" class="w-full h-10 px-3 border border-gray-300 rounded-lg">
                                <option value="">Selecione</option>
                                <option value="1" <?= $bimRegra === 1 ? 'selected' : '' ?>>1º Bimestre</option>
                                <option value="2" <?= $bimRegra === 2 ? 'selected' : '' ?>>2º Bimestre</option>
                                <option value="3" <?= $bimRegra === 3 ? 'selected' : '' ?>>3º Bimestre</option>
                                <option value="4" <?= $bimRegra === 4 ? 'selected' : '' ?>>4º Bimestre</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-2 pt-2 border-t border-gray-100 md:col-span-2">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Visível para</label>
                        <div class="flex flex-wrap gap-4">
                            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="vis_aluno" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !isset($regra['vis_aluno']) || (int) $regra['vis_aluno'] === 1 ? 'checked' : '' ?>>
                                <span>Aluno</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="vis_pais" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !isset($regra['vis_pais']) || (int) $regra['vis_pais'] === 1 ? 'checked' : '' ?>>
                                <span>Pais</span>
                            </label>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" name="vis_coordenacao" value="1" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= !isset($regra['vis_coordenacao']) || (int) $regra['vis_coordenacao'] === 1 ? 'checked' : '' ?>>
                                <span>Coordenação</span>
                            </label>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900">Blocos da regra</h3>
                    </div>
                    <button type="button" id="btn-add-bloco" class="btn-primary-custom shrink-0 h-10 px-3 rounded-lg hover:opacity-90 text-sm font-medium inline-flex items-center justify-center">+ Adicionar bloco</button>
                </div>

                <div id="lista-blocos" class="space-y-3"></div>

                <div class="pt-2">
                    <button type="submit" id="btn-salvar-regra-boletim" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 font-medium">Salvar evento</button>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-xl shadow-sm border border-gray-200">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Simulação Real</h2>
                <p class="text-sm text-gray-500 mt-1">Escolha o aluno para testar o resultado.</p>
            </div>

            <form method="GET" action="<?= URL ?>/admin/boletim-configuracao" class="p-5 space-y-4">
                <input type="hidden" name="regra_id" value="<?= (int) ($regra['id'] ?? 0) ?>">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Aluno</label>
                    <select name="aluno_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" required>
                        <option value="">Selecione</option>
                        <?php foreach ($alunos as $aluno): ?>
                            <?php $label = trim((string) ($aluno['nome'] ?? '')) . ' (' . ((string) ($aluno['turma_nome'] ?? 'Sem turma')) . ')'; ?>
                            <option value="<?= (int) ($aluno['id'] ?? 0) ?>" <?= ((int) ($aluno['id'] ?? 0) === $selectedAlunoId) ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="periodo_ref" value="<?= htmlspecialchars($periodoRef) ?>">
                <button type="submit" class="btn-primary-custom w-full px-4 py-2 rounded-lg hover:opacity-90 font-medium">Simular</button>
            </form>

            <div class="px-5 pb-5">
                <div class="border border-slate-200 bg-slate-50 rounded-lg p-4">
                    <p class="text-sm font-semibold text-slate-900 mb-2">Simular em lote (turma ou todo o escopo)</p>
                    <div class="flex flex-wrap items-end gap-2">
                        <div class="flex-1 min-w-[10rem]">
                            <label class="block text-xs font-medium text-slate-600 mb-1">Turma (opcional)</label>
                            <select id="lote-turma-id" class="w-full px-2.5 py-2 border border-gray-300 rounded-lg text-sm">
                                <option value="">Todo o escopo do evento</option>
                                <?php foreach ($turmas as $turmaOpt): ?>
                                    <option value="<?= (int) ($turmaOpt['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($turmaOpt['nome'] ?? '')) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" id="btn-simular-lote" class="px-4 py-2 bg-slate-700 text-white rounded-lg hover:bg-slate-800 text-sm font-medium">Simular lote</button>
                    </div>
                    <p class="text-xs text-slate-500 mt-1.5">Testa até 60 alunos de uma vez (sem gravar nada), pra pegar caso de borda antes de gerar pra todo mundo.</p>
                    <div id="lote-resultado" class="hidden mt-3 max-h-72 overflow-y-auto border border-slate-200 rounded-lg bg-white"></div>
                </div>
            </div>

            <div class="px-5 pb-5 space-y-3">
                <div class="border border-indigo-200 bg-indigo-50 rounded-lg p-4">
                    <div class="flex items-center justify-between gap-2">
                        <p class="text-sm font-semibold text-indigo-900">Checklist antes de gerar em massa</p>
                        <button type="button" id="btn-checklist-pre-geracao" class="px-3 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-xs font-medium shrink-0">Verificar agora</button>
                    </div>
                    <p class="text-xs text-indigo-700 mt-1">Confere matéria que está no evento mas falta em algum bloco, blocos com evento de origem de bimestre/série diferente, e quantos alunos têm nota faltando.</p>
                    <div id="checklist-resultado" class="hidden mt-3 space-y-2 text-sm"></div>
                </div>

                <form method="POST" action="<?= URL ?>/admin/boletim-configuracao/gerar-boletins" class="border border-emerald-200 bg-emerald-50 rounded-lg p-4 space-y-3">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="regra_id" value="<?= (int) ($regra['id'] ?? 0) ?>">
                    <input type="hidden" name="periodo_ref" value="<?= htmlspecialchars($periodoRef) ?>">
                    <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
                    <input type="hidden" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
                    <p class="text-sm text-emerald-900">
                        Recalcula e grava a tabela de notas por matéria para <strong>todos os alunos vinculados</strong> a este evento (séries / escopo). Quem ainda não tinha boletim neste período passa a ter; quem já tinha tem as linhas substituídas pela regra atual.
                    </p>
                    <button type="submit" class="btn-primary-custom w-full px-4 py-2 rounded-lg hover:opacity-90 font-medium">
                        Gerar boletins de todos os alunos vinculados
                    </button>
                </form>

                <form method="POST" action="<?= URL ?>/admin/boletim-configuracao/atualizar-boletins-gravados" class="border border-amber-200 bg-amber-50 rounded-lg p-4 space-y-3">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="regra_id" value="<?= (int) ($regra['id'] ?? 0) ?>">
                    <input type="hidden" name="periodo_ref" value="<?= htmlspecialchars($periodoRef) ?>">
                    <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
                    <input type="hidden" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
                    <p class="text-sm text-amber-950">
                        <strong>Atualizar só quem já tem boletim gravado</strong> neste evento e período (mesma data início/fim da tela). Útil depois de ajustar a regra ou provas, sem percorrer alunos que ainda não foram gerados pela primeira vez. Não substitui o botão verde quando há alunos novos no escopo.
                    </p>
                    <button type="submit" class="w-full px-4 py-2 bg-amber-600 text-white rounded-lg hover:bg-amber-700 font-medium">
                        Atualizar boletins já gravados neste período
                    </button>
                </form>

                <?php if (!empty($regra['id'])): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="flex items-center justify-between">
                        <p class="text-sm font-semibold text-gray-900">Histórico de gerações</p>
                        <button type="button" id="btn-ver-logs-geracao" class="text-xs text-indigo-600 hover:underline">Ver últimas 10</button>
                    </div>
                    <div id="logs-geracao-resultado" class="hidden mt-2 text-sm space-y-1.5"></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (is_array($simulacao)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Resultado da Simulação</h2>
                <p class="text-xs text-gray-500 mt-1">A leitura principal é a <strong>tabela por matéria</strong> (cada disciplina em uma linha). A nota final em destaque no rodapé usa a <strong>visão global</strong> (componentes já agregados entre matérias).</p>
                <p class="text-sm text-gray-500 mt-1">
                    Aluno: <strong><?= htmlspecialchars((string) (($simulacao['aluno']['nome'] ?? '-') )) ?></strong>
                </p>
            </div>

            <div class="p-5">
                <?php
                $matrizSim = $simulacao['matriz_materias'] ?? null;
                $matrizLinhas = is_array($matrizSim) && !empty($matrizSim['linhas']) ? $matrizSim['linhas'] : [];
                $matrizColunas = is_array($matrizSim) && !empty($matrizSim['colunas']) ? $matrizSim['colunas'] : [];
                $groupedHeader = $buildGroupedBoletimHeader((array) $matrizColunas);
                ?>
                <?php if (!empty($matrizLinhas) && !empty($matrizColunas)): ?>
                    <div class="mb-8">
                        <h3 class="text-base font-semibold text-gray-900 mb-1">Notas por matéria</h3>
                        <div class="overflow-x-auto border border-gray-200 rounded-lg">
                            <table class="min-w-full divide-y divide-gray-200 text-sm">
                                <thead class="bg-slate-50">
                                    <?php if (!empty($groupedHeader['enabled'])): ?>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-700 uppercase tracking-wide sticky left-0 bg-slate-50 z-10 border-r border-gray-200" rowspan="2">Matérias</th>
                                            <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 uppercase border-l border-gray-200" colspan="<?= (int) count((array) ($grp['cols'] ?? [])) ?>">
                                                    <?= htmlspecialchars((string) ($grp['label'] ?? '')) ?>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                        <tr>
                                            <?php foreach ($groupedHeader['groups'] as $grp): ?>
                                                <?php foreach ((array) ($grp['cols'] ?? []) as $mc): ?>
                                                    <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 bg-amber-100/70 min-w-[5.5rem]" title="<?= htmlspecialchars((string) ($mc['nome'] ?? '')) ?> (<?= htmlspecialchars((string) ($mc['codigo'] ?? '')) ?>)">
                                                        <span class="block truncate max-w-[7rem] mx-auto"><?= htmlspecialchars((string) ($mc['_sublabel'] ?? $mc['nome'] ?? $mc['codigo'] ?? '')) ?></span>
                                                    </th>
                                                <?php endforeach; ?>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php else: ?>
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-semibold text-slate-700 uppercase tracking-wide sticky left-0 bg-slate-50 z-10 border-r border-gray-200">Matéria</th>
                                            <?php foreach ($matrizColunas as $mc): ?>
                                                <th class="px-3 py-2 text-center text-xs font-semibold text-slate-700 min-w-[5.5rem]" title="<?= htmlspecialchars((string) ($mc['nome'] ?? '')) ?> (<?= htmlspecialchars((string) ($mc['codigo'] ?? '')) ?>)">
                                                    <span class="block truncate max-w-[7rem] mx-auto"><?= htmlspecialchars((string) ($mc['nome'] ?? $mc['codigo'] ?? '')) ?></span>
                                                    <span class="block text-[10px] font-normal text-slate-400 normal-case">(<?= htmlspecialchars((string) ($mc['codigo'] ?? '')) ?>)</span>
                                                </th>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endif; ?>
                                </thead>
                                <tbody class="divide-y divide-gray-100 bg-white">
                                    <?php foreach ($matrizLinhas as $idxLin => $lin): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-3 py-2 font-medium text-gray-900 sticky left-0 bg-white z-10 border-r border-gray-200"><?= htmlspecialchars((string) ($lin['materia_nome'] ?? '-')) ?></td>
                                            <?php
                                            $notasLin = is_array($lin['notas'] ?? null) ? $lin['notas'] : [];
                                            $iterCols = $matrizColunas;
                                            if (!empty($groupedHeader['enabled'])) {
                                                $iterCols = [];
                                                foreach ((array) ($groupedHeader['groups'] ?? []) as $g) {
                                                    foreach ((array) ($g['cols'] ?? []) as $gc) {
                                                        $iterCols[] = $gc;
                                                    }
                                                }
                                            }
                                            foreach ($iterCols as $mc):
                                                $codM = (string) ($mc['codigo'] ?? '');
                                                $nv = $notasLin[$codM] ?? null;
                                                $colGlobal = !empty($mc['valor_global']);
                                                // Colunas de faltas devem mostrar inteiros tanto em bimestre (source_type='faltas_evento')
                                                // quanto na linha FINAL (calculado/evento_boletim com layout_type='faltas').
                                                $isFaltasCol = ((string) ($mc['source_type'] ?? '')) === 'faltas_evento'
                                                    || strtolower((string) ($mc['layout_type'] ?? '')) === 'faltas';
                                                $stColM = (string) ($mc['source_type'] ?? '');
                                                $isManualCol = ($stColM === 'manual');
                                                $isPorMateriaCol = in_array($stColM, ['provas_sistema', 'jornadas', 'calculado'], true)
                                                    && (int) ($lin['materia_id'] ?? 0) !== 0;
                                                $isCalcEditavel = (int) ($mc['id'] ?? 0) > 0 && ($isManualCol || $isPorMateriaCol);
                                                $materiaIdEdicao = $isManualCol ? 0 : (int) ($lin['materia_id'] ?? 0);
                                            ?>
                                                <td
                                                    class="px-3 py-2 text-center <?= is_numeric($nv) ? 'text-emerald-700 font-semibold' : (is_string($nv) && trim($nv) !== '' ? 'text-slate-700 font-medium' : 'text-gray-400') ?> <?= $isCalcEditavel ? 'boletim-cell-editavel cursor-pointer hover:bg-indigo-50' : '' ?>"
                                                    <?php if ($isCalcEditavel): ?>
                                                    data-cell-editavel="1"
                                                    data-componente-id="<?= (int) $mc['id'] ?>"
                                                    data-materia-id="<?= $materiaIdEdicao ?>"
                                                    data-regra-id="<?= (int) ($regra['id'] ?? 0) ?>"
                                                    data-aluno-id="<?= $selectedAlunoId ?>"
                                                    data-periodo-ref="<?= htmlspecialchars($periodoRef, ENT_QUOTES, 'UTF-8') ?>"
                                                    data-escala-max="<?= htmlspecialchars(number_format((float) ($mc['escala_max'] ?? 10), 2, '.', '')) ?>"
                                                    title="<?= $isManualCol ? 'Clique para editar (vale para todas as matérias deste bloco)' : 'Clique para sobrescrever só essa matéria, só para este aluno' ?>"
                                                    <?php endif; ?>
                                                >
                                                    <span class="boletim-cell-valor">
                                                    <?php if (is_numeric($nv) && $colGlobal && $idxLin > 0): ?>
                                                        <?php
                                                        $nvFmt = $formatNotaBoletim($nv);
                                                        $tip = 'Nota única do componente manual: ' . $nvFmt . ' (igual em todas as matérias; ver primeira linha).';
                                                        ?>
                                                        <span class="text-xs font-medium text-slate-500 cursor-help border-b border-dotted border-slate-300" title="<?= htmlspecialchars($tip, ENT_QUOTES, 'UTF-8') ?>">idem</span>
                                                    <?php elseif (is_numeric($nv)): ?>
                                                        <?= $isFaltasCol ? number_format((float) round((float) $nv), 0, ',', '.') : $formatNotaBoletim($nv) ?>
                                                    <?php elseif (is_string($nv) && trim($nv) !== ''): ?>
                                                        <?= htmlspecialchars($nv) ?>
                                                    <?php else: ?>
                                                        —
                                                    <?php endif; ?>
                                                    </span>
                                                    <?php if ($isCalcEditavel): ?>
                                                        <i class="fa-solid fa-pen text-[10px] text-indigo-400 ml-1 align-middle"></i>
                                                    <?php endif; ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($podeGravarBoletimOficialAluno): ?>
                    <form method="POST" action="<?= URL ?>/admin/boletim-configuracao/publicar-boletim-aluno" class="mt-6 pt-5 border-t border-gray-200 space-y-2">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                        <input type="hidden" name="regra_id" value="<?= $regraIdBoletim ?>">
                        <input type="hidden" name="aluno_id" value="<?= $selectedAlunoId ?>">
                        <input type="hidden" name="periodo_ref" value="<?= htmlspecialchars($periodoRef) ?>">
                        <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
                        <input type="hidden" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">
                        <p class="text-sm text-gray-600">Grava no sistema o boletim <strong>oficial</strong> só deste aluno e período (substitui o boletim anterior dele, se existir). Os demais alunos do evento não são alterados — use o botão verde abaixo para gerar em lote.</p>
                        <button type="submit" class="btn-primary-custom inline-flex items-center gap-2 px-4 py-2.5 text-sm font-semibold rounded-lg hover:opacity-90 shadow-sm">
                            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                            Gravar boletim oficial (só este aluno)
                        </button>
                    </form>
                <?php endif; ?>


                <?php if (!empty($simulacao['faltantes_obrigatorios'])): ?>
                    <div class="mt-4 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
                        Faltam componentes obrigatórios: <?= htmlspecialchars(implode(', ', $simulacao['faltantes_obrigatorios'])) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($simulacao['erro_formula'])): ?>
                    <div class="mt-4 p-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm">
                        <?= htmlspecialchars((string) $simulacao['erro_formula']) ?>
                    </div>
                <?php endif; ?>

            </div>
        </div>

        <?php if (!empty($manualRows)): ?>
            <div class="bg-white rounded-xl shadow-sm border border-gray-200">
                <div class="px-5 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-900">Notas Manuais Complementares</h2>
                    <p class="text-sm text-gray-500 mt-1">Use para ENAC, trabalho, atividade extra. Ao travar, o valor não poderá mais ser editado.</p>
                </div>
                <form method="POST" action="<?= URL ?>/admin/boletim-configuracao/notas-manuais" class="p-5 space-y-4" id="form-notas-manuais">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrfToken) ?>">
                    <input type="hidden" name="regra_id" value="<?= (int) ($regra['id'] ?? 0) ?>">
                    <input type="hidden" name="aluno_id" value="<?= $selectedAlunoId ?>">
                    <input type="hidden" name="periodo_ref" value="<?= htmlspecialchars($periodoRef) ?>">
                    <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($dataInicio) ?>">
                    <input type="hidden" name="data_fim" value="<?= htmlspecialchars($dataFim) ?>">

                    <?php foreach ($manualRows as $item): ?>
                        <?php
                        $compId = (int) ($item['id'] ?? 0);
                        $locked = !empty($item['bloqueado']);
                        $valor = is_numeric($item['valor'] ?? null) ? (float) $item['valor'] : null;
                        $escalaMax = max(0.01, (float) ($item['escala_max'] ?? 10));
                        ?>
                        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 border border-gray-200 rounded-lg p-3">
                            <div class="md:col-span-2">
                                <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars((string) ($item['nome'] ?? 'Componente manual')) ?></p>
                                <p class="text-xs text-gray-500">Código: <?= htmlspecialchars((string) ($item['codigo'] ?? '-')) ?> | Escala: <?= htmlspecialchars(number_format($escalaMax, 2, ',', '.')) ?></p>
                            </div>
                            <div>
                                <label class="block text-xs text-gray-600 mb-1">Nota</label>
                                <input
                                    type="number"
                                    min="0"
                                    max="<?= htmlspecialchars(number_format($escalaMax, 2, '.', '')) ?>"
                                    step="0.01"
                                    data-lock-target="<?= $compId ?>"
                                    name="manual_notas[<?= $compId ?>]"
                                    value="<?= $valor !== null ? htmlspecialchars(number_format($valor, 2, '.', '')) : '' ?>"
                                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
                                    <?= $locked ? 'disabled' : '' ?>
                                >
                            </div>
                            <div class="flex items-end">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                                    <input type="checkbox" class="rounded border-gray-300" name="manual_lock[<?= $compId ?>]" value="1" <?= $locked ? 'checked disabled' : '' ?>>
                                    Travar nota
                                </label>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 font-medium">Salvar notas manuais</button>
                </form>
            </div>
        <?php endif; ?>

    <?php endif; ?>
</div>
<?php endif; ?>

<div id="modal-bloco" class="hidden fixed inset-0 z-50 p-3 sm:p-6 overflow-y-auto">
    <div class="absolute inset-0 z-0 bg-black/50" data-close-modal="1" aria-hidden="true"></div>
    <div class="relative z-10 mx-auto my-4 sm:my-8 max-w-4xl bg-white rounded-xl shadow-xl border border-gray-200 pointer-events-auto max-h-[calc(100vh-2rem)] sm:max-h-[calc(100vh-4rem)] flex flex-col">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-lg font-semibold text-gray-900" id="modal-titulo">Novo bloco</h3>
            <button type="button" class="text-gray-500 hover:text-gray-700" data-close-modal="1">Fechar</button>
        </div>

        <form id="form-modal-bloco" class="p-5 grid grid-cols-1 md:grid-cols-2 gap-4 overflow-y-auto">
            <input type="hidden" id="bloco-edit-index" value="-1">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Nome</label>
                <input id="bloco-nome" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Prova semanal" required>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Código (fórmula)</label>
                <input id="bloco-codigo" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="semanal">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Origem</label>
                <select id="bloco-source" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="nenhuma">-</option>
                    <option value="provas_sistema">Provas do sistema</option>
                    <option value="jornadas">Jornadas (automático)</option>
                    <option value="evento_boletim">Evento de boletim (por código)</option>
                    <option value="faltas_evento">Faltas (evento)</option>
                    <option value="calculado">Coluna calculada (fórmula por código)</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Regra de cálculo</label>
                <select id="bloco-calc" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="media">Média</option>
                    <option value="soma">Soma</option>
                    <option value="maior">Maior nota</option>
                    <option value="ultima">Última prova</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Peso</label>
                <input id="bloco-peso" type="number" step="0.01" min="0.01" value="1" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Escala máxima</label>
                <input id="bloco-escala" type="number" step="0.01" min="0.01" value="10" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Grupo no boletim (layout)</label>
                <select id="bloco-layout-group" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Automático pelo nome</option>
                    <option value="b1">1º Bimestre</option>
                    <option value="b2">2º Bimestre</option>
                    <option value="b3">3º Bimestre</option>
                    <option value="b4">4º Bimestre</option>
                    <option value="final">Final</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Tipo da coluna (layout)</label>
                <select id="bloco-layout-type" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Automático pelo nome</option>
                    <option value="media">Média</option>
                    <option value="faltas">Faltas</option>
                    <option value="rec">Rec.</option>
                    <option value="resultado">Resultado</option>
                    <option value="other">Outro</option>
                </select>
            </div>
            <div class="md:col-span-2 hidden" id="wrap-calculado">
                <div class="mb-2 flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" name="bloco-calculado-modo" id="bloco-calculado-modo-geral" value="geral" class="border-gray-300 text-indigo-600 focus:ring-indigo-500" checked>
                        Usar a mesma expressão em todas as matérias
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                        <input type="radio" name="bloco-calculado-modo" id="bloco-calculado-modo-materia" value="materia" class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        Definir exceções por matéria
                    </label>
                </div>
                <div id="wrap-calculado-geral">
                <label class="block text-sm font-medium text-gray-700 mb-1">Expressão (códigos dos blocos)</label>
                <textarea id="bloco-expressao-calculado" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-lg font-mono text-sm" placeholder="(semanal + bimestral) / 2"></textarea>
                <p class="text-xs text-gray-500 mt-1">Use os mesmos nomes do campo <strong>Código (fórmula)</strong> dos outros blocos. Operadores <code class="bg-gray-100 px-1 rounded">+ - * / ( )</code> e funções <code class="bg-gray-100 px-1 rounded">max(a,b)</code>, <code class="bg-gray-100 px-1 rounded">min(a,b)</code>. Exemplos: <code class="bg-gray-100 px-1 rounded">(semanal + bimestral + jornadas) / 3</code>, <code class="bg-gray-100 px-1 rounded">max(media,(media+enac)/2)</code>. A expressão é avaliada <strong>por matéria</strong> na simulação; blocos calculados devem ficar <strong>depois</strong> na lista dos que eles referenciam.</p>
                <label class="mt-3 inline-flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" id="bloco-calculado-traco-abaixo-minimo" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm leading-snug">Se a <strong>nota desta coluna calculada</strong> ficar <strong>abaixo da nota mínima para aprovação</strong> deste evento, exibir <strong>“-”</strong> aqui (o cálculo interno continua com o valor numérico para outras fórmulas e para o resultado aprovado/reprovado). Para ocultar a <strong>jornada</strong> abaixo do mínimo, use a opção homônima no bloco <strong>Jornadas</strong>.</span>
                </label>
                </div>
                <div id="wrap-calculado-por-materia" class="hidden border border-indigo-100 bg-indigo-50 rounded-lg p-3 mt-2">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <label class="text-sm font-medium text-indigo-900">Exceções por matéria</label>
                        <button type="button" id="btn-add-formula-materia-modal" class="btn-primary-custom text-xs px-2.5 py-1 rounded hover:opacity-90">+ adicionar matéria</button>
                    </div>
                    <div id="lista-formulas-materia-modal" class="space-y-2"></div>
                    <p class="text-xs text-indigo-800 mt-2">A expressão geral será usada em todas as matérias; aqui você informa apenas as matérias que terão fórmula diferente.</p>
                </div>
            </div>
            <div class="md:col-span-2 hidden" id="wrap-evento-boletim">
                <label class="block text-sm font-medium text-gray-700 mb-1">Evento de origem (código)</label>
                <?php
                $seriesNomesPorIdModal = [];
                foreach ($series as $serieOpt) {
                    $sidOpt = (int) ($serieOpt['id'] ?? 0);
                    if ($sidOpt > 0) {
                        $seriesNomesPorIdModal[$sidOpt] = trim((string) ($serieOpt['nome'] ?? ''));
                    }
                }
                $bimestreLabelModal = ['', '1º Bimestre', '2º Bimestre', '3º Bimestre', '4º Bimestre'];
                ?>
                <select id="evento-regra-codigo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Selecione o evento</option>
                    <?php foreach ($regrasCatalogo as $regCat): ?>
                        <?php $codCat = trim((string) ($regCat['codigo'] ?? '')); ?>
                        <?php if ($codCat === '') { continue; } ?>
                        <?php
                        $bimCat = (int) ($regCat['bimestre'] ?? 0);
                        $bimLabelCat = ($bimCat >= 1 && $bimCat <= 4) ? $bimestreLabelModal[$bimCat] : 'sem bimestre definido';
                        $seriesIdsCat = [];
                        $rawSeriesCat = json_decode((string) ($regCat['series_ids'] ?? ''), true);
                        if (is_array($rawSeriesCat)) {
                            $seriesIdsCat = array_map('intval', $rawSeriesCat);
                        }
                        $seriesNomesCat = array_filter(array_map(static function ($sid) use ($seriesNomesPorIdModal) {
                            return $seriesNomesPorIdModal[$sid] ?? null;
                        }, $seriesIdsCat));
                        $seriesLabelCat = $seriesNomesCat !== [] ? implode('/', $seriesNomesCat) : 'todas as séries';
                        $contextoCat = $bimLabelCat . ' · ' . $seriesLabelCat;
                        ?>
                        <option value="<?= htmlspecialchars($codCat) ?>"
                                data-bimestre="<?= $bimCat ?>"
                                data-series-ids="<?= htmlspecialchars(json_encode($seriesIdsCat)) ?>"
                                data-contexto="<?= htmlspecialchars($contextoCat) ?>">
                            <?= htmlspecialchars($codCat . ' - ' . ((string) ($regCat['nome'] ?? 'Evento')) . ' (' . $contextoCat . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <p id="evento-regra-codigo-aviso" class="hidden text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-2 py-1.5 mt-1">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <span id="evento-regra-codigo-aviso-texto"></span>
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Bloco/coluna do evento</label>
                        <select id="evento-componente-codigo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            <option value="">Selecione primeiro o evento</option>
                        </select>
                    </div>
                </div>
                <p class="text-xs text-gray-500 mt-2">Selecione o evento e depois escolha o bloco/coluna que deseja usar.</p>
            </div>
            <div class="md:col-span-2 hidden" id="wrap-faltas-evento">
                <label class="block text-sm font-medium text-gray-700 mb-1">Evento de faltas</label>
                <select id="faltas-evento-id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    <option value="">Selecione o evento de faltas</option>
                    <?php foreach ($faltasEventosCatalogo as $evf): ?>
                        <?php
                        $evfId = (int) ($evf['id'] ?? 0);
                        if ($evfId <= 0) { continue; }
                        $evfNome = trim((string) ($evf['nome'] ?? 'Evento'));
                        $evfB = trim((string) ($evf['bimestre'] ?? ''));
                        $evfAno = (int) ($evf['ano_letivo'] ?? 0);
                        ?>
                        <option value="<?= $evfId ?>"><?= htmlspecialchars($evfNome . ' | ' . $evfB . ' | ' . $evfAno) ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="text-xs text-gray-500 mt-2">Usa as faltas lançadas no módulo de faltas para o aluno selecionado na simulação.</p>
            </div>
            <div class="md:col-span-2 hidden" id="wrap-jornadas">
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-3">
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Data início (filtro da jornada)</label>
                        <input id="jornada-data-ini" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-600 mb-1">Data fim (filtro da jornada)</label>
                        <input id="jornada-data-fim" type="date" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Como aplicar a nota no boletim</label>
                    <select id="jornada-distribuicao-notas" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="por_materia">Por matéria (cada linha conforme as jornadas daquela matéria)</option>
                        <option value="nota_unica_todas_linhas">Uma nota em todas as linhas (concluídas no escopo ÷ jornadas no escopo; repetida em cada matéria exibida)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">No modo &quot;uma nota em todas as linhas&quot;, média/soma/maior/última do bloco não se aplica: usa só o percentual global de jornadas concluídas (mesma regra de escala ou faixas do bloco).</p>
                </div>
                <div id="wrap-jornada-nota-unica-extras" class="hidden mt-4 border border-slate-200 bg-slate-50 rounded-lg p-3 space-y-4">
                    <p class="text-xs font-medium text-slate-800">Nota única: exceções por linha do boletim</p>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Matérias com coluna Jornadas (nota única)</label>
                        <div id="jornada-nota-unica-materias-wrap" class="border border-gray-300 rounded-lg p-3 max-h-44 overflow-y-auto bg-white">
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                <?php foreach ($materias as $materiaJu): ?>
                                    <?php
                                    $midJu = (int) ($materiaJu['id'] ?? 0);
                                    if ($midJu <= 0) {
                                        continue;
                                    }
                                    $nomJu = htmlspecialchars((string) ($materiaJu['nome'] ?? ('Matéria #' . $midJu)));
                                    ?>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                        <input type="checkbox" class="jornada-nota-unica-materia-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="<?= $midJu ?>" checked>
                                        <span><?= $nomJu ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-2 mt-1">
                            <button type="button" id="btn-jornada-nota-unica-materias-todas" class="text-xs px-2 py-1 rounded-md bg-indigo-50 text-indigo-900 border border-indigo-100 hover:bg-indigo-100 font-medium">Marcar todas</button>
                            <button type="button" id="btn-jornada-nota-unica-materias-nenhuma" class="text-xs px-2 py-1 rounded-md bg-gray-100 text-gray-800 border border-gray-200 hover:bg-gray-200 font-medium">Desmarcar todas</button>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">Igual a &quot;Matérias deste bloco&quot;: <strong>desmarque</strong> uma matéria (ex.: Educação Física) para ela <strong>não</strong> receber nota na coluna Jornadas (fica vazio no boletim). Com todas marcadas, todas recebem a nota única.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fonte alternativa por matéria do catálogo</label>
                        <p class="text-xs text-gray-500 mb-2">Para a linha escolhida, a nota de Jornadas usa só as jornadas cuja matéria está na lista (útil quando a disciplina não tem jornada própria).</p>
                        <div id="lista-jornada-fonte-por-materia" class="space-y-2"></div>
                        <button type="button" id="btn-jornada-fonte-materia-add" class="mt-2 text-xs px-2.5 py-1 rounded-md bg-white text-slate-800 border border-slate-200 hover:bg-slate-100 font-medium">+ Linha (matéria)</button>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-gray-700 mb-1">Fonte alternativa por grupo (linha única)</label>
                        <p class="text-xs text-gray-500 mb-2">Use o mesmo <strong>código do grupo</strong> configurado em &quot;Agrupar em linha única&quot; em qualquer bloco (ex.: Língua Portuguesa).</p>
                        <div id="lista-jornada-fonte-por-grupo" class="space-y-2"></div>
                        <button type="button" id="btn-jornada-fonte-grupo-add" class="mt-2 text-xs px-2.5 py-1 rounded-md bg-white text-slate-800 border border-slate-200 hover:bg-slate-100 font-medium">+ Linha de grupo</button>
                    </div>
                </div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Jornadas no escopo (opcional)</label>
                <select id="jornada-ids" multiple class="hidden"></select>

                <div class="flex flex-wrap items-end gap-2 mb-2 bg-gray-50 border border-gray-200 rounded-lg p-2.5">
                    <div class="flex-1 min-w-[160px]">
                        <label for="jornada-tabela-filtro-titulo" class="block text-xs font-medium text-gray-600 mb-1">Buscar</label>
                        <input id="jornada-tabela-filtro-titulo" type="text" placeholder="Título, matéria ou professor" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm">
                    </div>
                    <div class="min-w-[160px]">
                        <label for="jornada-tabela-filtro-materia" class="block text-xs font-medium text-gray-600 mb-1">Matéria</label>
                        <select id="jornada-tabela-filtro-materia" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm">
                            <option value="">Todas</option>
                        </select>
                    </div>
                    <div class="min-w-[140px]">
                        <label for="jornada-tabela-filtro-bimestre" class="block text-xs font-medium text-gray-600 mb-1">Bimestre</label>
                        <select id="jornada-tabela-filtro-bimestre" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm">
                            <option value="">Todos</option>
                            <option value="1">1º Bimestre</option>
                            <option value="2">2º Bimestre</option>
                            <option value="3">3º Bimestre</option>
                            <option value="4">4º Bimestre</option>
                        </select>
                    </div>
                    <div class="min-w-[120px]">
                        <label for="jornada-tabela-filtro-ano" class="block text-xs font-medium text-gray-600 mb-1">Ano letivo</label>
                        <select id="jornada-tabela-filtro-ano" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm">
                            <option value="">Todos</option>
                        </select>
                    </div>
                    <button type="button" id="btn-jornada-tabela-limpar-filtros" class="text-xs px-2.5 py-1.5 rounded-md bg-white text-gray-700 border border-gray-300 hover:bg-gray-100 font-medium">Limpar filtros</button>
                </div>

                <div class="border border-gray-300 rounded-lg overflow-hidden">
                    <div class="max-h-64 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 w-8"></th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Jornada</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bimestre</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data fim</th>
                                </tr>
                            </thead>
                            <tbody id="jornada-tabela-corpo" class="bg-white divide-y divide-gray-100">
                                <tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">Carregando jornadas...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 mt-2">
                    <button type="button" id="btn-jornadas-marcar-todas" class="text-xs px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-900 border border-indigo-100 hover:bg-indigo-100 font-medium">Marcar todas</button>
                    <button type="button" id="btn-jornadas-selecionar-filtradas" class="text-xs px-2.5 py-1 rounded-md bg-indigo-50 text-indigo-900 border border-indigo-100 hover:bg-indigo-100 font-medium">Selecionar todas filtradas</button>
                    <button type="button" id="btn-jornadas-limpar" class="text-xs px-2.5 py-1 rounded-md bg-gray-100 text-gray-800 border border-gray-200 hover:bg-gray-200 font-medium">Limpar seleção</button>
                    <span id="jornada-tabela-contador" class="text-xs text-gray-600 font-medium">0 selecionada(s)</span>
                </div>
                <p class="text-xs text-gray-500 mt-1">Vazio = todas as jornadas da turma do aluno que couberem no intervalo de datas. Marque na tabela ou use <strong>Marcar todas</strong>. A pontuação é por <strong>jornada concluída</strong> (não por desempenho nas questões). Com <strong>por matéria</strong>, a simulação agrupa por matéria da jornada; várias jornadas na mesma matéria usam média/soma/maior/última do bloco. Com <strong>uma nota em todas as linhas</strong>, calcula-se um percentual global no escopo e a mesma nota aparece em cada matéria da tabela.</p>
                <p class="text-xs text-slate-600 bg-slate-50 border border-slate-100 rounded px-2 py-1.5 mt-2">Ao preencher as datas acima, a lista mostra somente jornadas nesse intervalo (início e encerramento da jornada). O <strong>bimestre</strong> e o <strong>ano letivo</strong> exibidos são os mesmos cadastrados na jornada (não são calculados pela data).</p>
                <label class="mt-3 inline-flex items-start gap-2 text-sm text-gray-800">
                    <input type="checkbox" id="bloco-jornada-traco-abaixo-minimo" class="mt-0.5 rounded border-gray-300 text-indigo-600 focus:ring-indigo-500">
                    <span class="text-sm leading-snug">Se a <strong>nota da jornada</strong> ficar <strong>abaixo da nota mínima para aprovação</strong> deste evento, exibir <strong>“-”</strong> nesta coluna (o valor numérico continua disponível para fórmulas de outras colunas, ex.: média com <code class="bg-gray-100 px-1 rounded text-xs">max()</code>).</span>
                </label>
                <div id="wrap-jornadas-faixas" class="hidden mt-3 border border-amber-200 bg-amber-50 rounded-lg p-3">
                    <label class="block text-sm font-medium text-amber-900 mb-2">Tabela por conclusão de jornadas</label>
                    <p class="text-xs text-amber-800 mb-2">Preencha a nota para cada faixa mínima de conclusão (10%, 20%, ... 100%). O aluno recebe a nota da maior faixa atingida.</p>
                    <div id="lista-jornadas-faixas" class="grid grid-cols-1 sm:grid-cols-2 gap-2"></div>
                </div>
            </div>
            <div class="md:col-span-2" id="wrap-filtro-titulo">
                <label class="block text-sm font-medium text-gray-700 mb-1">Filtro por título da prova (opcional)</label>
                <input id="bloco-filtro" type="text" class="w-full px-3 py-2 border border-gray-300 rounded-lg" placeholder="Ex: semanal, bimestral, simulado">
                <p id="hint-filtro-blocos" class="text-xs text-amber-800 bg-amber-50 border border-amber-100 rounded px-2 py-1.5 mt-1 hidden">Com <strong>blocos de prova</strong> selecionados, este filtro <strong>não é aplicado</strong> na busca (o título da prova costuma ser diferente do nome do bloco). Use só blocos para bimestrais por bloco, ou só o filtro quando não marcar blocos.</p>
            </div>
            <div class="md:col-span-2" id="wrap-bloco-prova">
                <label class="block text-sm font-medium text-gray-700 mb-1">Blocos de prova (opcional)</label>
                <select id="bloco-ids" multiple class="hidden">
                    <?php foreach ($blocosProvas as $bloco): ?>
                        <?php
                        $blocoTitulo = (string) ($bloco['titulo'] ?? ('Bloco #' . (int) ($bloco['id'] ?? 0)));
                        $blocoData = !empty($bloco['data_prova']) ? (' - ' . $bloco['data_prova']) : '';
                        ?>
                        <option value="<?= (int) ($bloco['id'] ?? 0) ?>"><?= htmlspecialchars('#' . (int) ($bloco['id'] ?? 0) . ' - ' . $blocoTitulo . $blocoData) ?></option>
                    <?php endforeach; ?>
                </select>

                <?php
                $tiposAvaliacaoBlocos = [];
                foreach ($blocosProvas as $bloco) {
                    $tipoNome = trim((string) ($bloco['tipo_avaliacao_nome'] ?? ''));
                    if ($tipoNome !== '') {
                        $tiposAvaliacaoBlocos[$tipoNome] = true;
                    }
                }
                $tiposAvaliacaoBlocos = array_keys($tiposAvaliacaoBlocos);
                sort($tiposAvaliacaoBlocos);
                ?>
                <div class="flex flex-wrap items-end gap-2 mb-2 bg-gray-50 border border-gray-200 rounded-lg p-2.5">
                    <div class="flex-1 min-w-[160px]">
                        <label for="bloco-tabela-filtro-titulo" class="block text-xs font-medium text-gray-600 mb-1">Buscar título</label>
                        <input id="bloco-tabela-filtro-titulo" type="text" placeholder="Ex: simulado" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm">
                    </div>
                    <div class="min-w-[160px]">
                        <label for="bloco-tabela-filtro-tipo" class="block text-xs font-medium text-gray-600 mb-1">Tipo de avaliação</label>
                        <select id="bloco-tabela-filtro-tipo" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm">
                            <option value="">Todos</option>
                            <?php foreach ($tiposAvaliacaoBlocos as $tipoNome): ?>
                                <option value="<?= htmlspecialchars($tipoNome) ?>"><?= htmlspecialchars($tipoNome) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="min-w-[140px]">
                        <label for="bloco-tabela-filtro-bimestre" class="block text-xs font-medium text-gray-600 mb-1">Bimestre</label>
                        <select id="bloco-tabela-filtro-bimestre" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm">
                            <option value="">Todos</option>
                            <option value="1">1º Bimestre</option>
                            <option value="2">2º Bimestre</option>
                            <option value="3">3º Bimestre</option>
                            <option value="4">4º Bimestre</option>
                        </select>
                    </div>
                    <div class="min-w-[120px]">
                        <label for="bloco-tabela-filtro-ano" class="block text-xs font-medium text-gray-600 mb-1">Ano letivo</label>
                        <input id="bloco-tabela-filtro-ano" type="number" placeholder="Ex: 2026" class="w-full px-2.5 py-1.5 border border-gray-300 rounded-md text-sm">
                    </div>
                    <button type="button" id="btn-bloco-tabela-limpar-filtros" class="text-xs px-2.5 py-1.5 rounded-md bg-white text-gray-700 border border-gray-300 hover:bg-gray-100 font-medium">Limpar filtros</button>
                </div>

                <div class="border border-gray-300 rounded-lg overflow-hidden">
                    <div class="max-h-64 overflow-y-auto">
                        <table class="min-w-full divide-y divide-gray-200 text-sm">
                            <thead class="bg-gray-50 sticky top-0">
                                <tr>
                                    <th class="px-3 py-2 w-8"></th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bloco</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo de avaliação</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bimestre</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                                    <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                                </tr>
                            </thead>
                            <tbody id="bloco-tabela-corpo" class="bg-white divide-y divide-gray-100">
                                <?php foreach ($blocosProvas as $bloco): ?>
                                    <?php
                                    $blocoIdRow = (int) ($bloco['id'] ?? 0);
                                    $blocoTitulo = (string) ($bloco['titulo'] ?? ('Bloco #' . $blocoIdRow));
                                    $blocoDataRaw = (string) ($bloco['data_prova'] ?? '');
                                    $blocoData = $blocoDataRaw !== '' && strtotime($blocoDataRaw) !== false ? date('d/m/Y', strtotime($blocoDataRaw)) : '';
                                    $blocoAno = (string) ($bloco['ano_letivo'] ?? '');
                                    $blocoBimestre = (string) ($bloco['bimestre'] ?? '');
                                    $blocoTipo = trim((string) ($bloco['tipo_avaliacao_nome'] ?? ''));
                                    ?>
                                    <tr class="bloco-tabela-row hover:bg-gray-50 cursor-pointer"
                                        data-bloco-id="<?= $blocoIdRow ?>"
                                        data-titulo="<?= htmlspecialchars(mb_strtolower($blocoTitulo)) ?>"
                                        data-tipo="<?= htmlspecialchars($blocoTipo) ?>"
                                        data-bimestre="<?= htmlspecialchars($blocoBimestre) ?>"
                                        data-ano="<?= htmlspecialchars($blocoAno) ?>">
                                        <td class="px-3 py-2">
                                            <input type="checkbox" class="bloco-tabela-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="<?= $blocoIdRow ?>">
                                        </td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-900">#<?= $blocoIdRow ?> - <?= htmlspecialchars($blocoTitulo) ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?= $blocoTipo !== '' ? htmlspecialchars($blocoTipo) : 'N/A' ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?= $blocoBimestre !== '' ? $blocoBimestre . 'º' : 'N/A' ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?= $blocoAno !== '' ? $blocoAno : 'N/A' ?></td>
                                        <td class="px-3 py-2 whitespace-nowrap text-gray-600"><?= $blocoData !== '' ? htmlspecialchars($blocoData) : 'N/A' ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if (empty($blocosProvas)): ?>
                                    <tr><td colspan="6" class="px-3 py-6 text-center text-gray-400">Nenhum bloco de prova encontrado</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-2 mt-1">
                    <p class="text-xs text-gray-500 flex-1 min-w-[200px]">Marque um ou mais blocos (clique na linha ou no checkbox). Entram provas vinculadas a <strong>qualquer</strong> bloco escolhido (união). Se houver blocos marcados, o filtro por título acima <strong>não vale</strong> para esta busca.</p>
                    <span id="bloco-tabela-contador" class="text-xs text-gray-600 font-medium shrink-0">0 selecionado(s)</span>
                    <button type="button" id="btn-blocos-selecionar-filtrados" class="text-xs text-indigo-600 hover:underline shrink-0">Selecionar todos filtrados</button>
                    <button type="button" id="btn-blocos-limpar" class="text-xs text-indigo-600 hover:underline shrink-0">Limpar seleção</button>
                </div>
            </div>
            <div class="md:col-span-2" id="wrap-materia">
                <label id="bloco-materias-label" class="block text-sm font-medium text-gray-700 mb-1">Matérias deste bloco (opcional)</label>
                <div id="bloco-materias-checkboxes" class="border border-gray-300 rounded-lg p-3 max-h-44 overflow-y-auto bg-white">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                        <?php foreach ($materias as $materia): ?>
                            <?php $midModal = (int) ($materia['id'] ?? 0); ?>
                            <?php if ($midModal <= 0) { continue; } ?>
                            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                <input type="checkbox" class="bloco-materia-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="<?= $midModal ?>">
                                <span><?= htmlspecialchars((string) ($materia['nome'] ?? ('Matéria #' . $midModal))) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <p id="bloco-materias-hint" class="text-xs text-gray-500 mt-1">Se não marcar nenhuma, usa todas as matérias no escopo.</p>
                <input type="hidden" id="bloco-materia-id" value="">
            </div>
            <div class="md:col-span-2 border border-indigo-100 bg-indigo-50 rounded-lg p-3" id="wrap-group-line">
                <label class="inline-flex items-center gap-2 text-sm text-indigo-900 font-medium">
                    <input id="bloco-group-enabled" type="checkbox" class="rounded border-indigo-300 text-indigo-600 focus:ring-indigo-500">
                    Agrupar em linha única (ex.: Língua Portuguesa)
                </label>
                <div id="bloco-group-fields" class="hidden mt-3 space-y-3">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-indigo-900 mb-1">Código do grupo</label>
                            <input id="bloco-group-key" type="text" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm" placeholder="lingua_portuguesa">
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-indigo-900 mb-1">Nome da linha</label>
                            <input id="bloco-group-label" type="text" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm" placeholder="Língua Portuguesa">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        <div>
                            <label class="block text-xs font-medium text-indigo-900 mb-1">Cálculo no grupo</label>
                            <select id="bloco-group-mode" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm">
                                <option value="media">Média</option>
                                <option value="soma">Soma</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-indigo-900 mb-1">Divisor fixo (opcional)</label>
                            <input id="bloco-group-divisor" type="number" min="0" step="0.01" class="w-full px-3 py-2 border border-indigo-200 rounded-lg text-sm" placeholder="4">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-indigo-900 mb-1">Matérias do grupo</label>
                        <div id="bloco-group-materias-checkboxes" class="border border-indigo-200 rounded-lg p-3 max-h-44 overflow-y-auto bg-white">
                            <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-2">
                                <?php foreach ($materias as $materia): ?>
                                    <?php $midGroup = (int) ($materia['id'] ?? 0); ?>
                                    <?php if ($midGroup <= 0) { continue; } ?>
                                    <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                                        <input type="checkbox" class="bloco-group-materia-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="<?= $midGroup ?>">
                                        <span><?= htmlspecialchars((string) ($materia['nome'] ?? ('Matéria #' . $midGroup))) ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <p class="text-xs text-indigo-800">Use para montar linha única por área. Cada bloco pode usar cálculo diferente para o mesmo grupo.</p>
                </div>
            </div>
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                <div class="space-y-1">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input id="bloco-percentual" type="checkbox" class="rounded border-gray-300" checked>
                    <span id="bloco-percentual-label">Calcular por acertos/questões</span>
                </label>
                <p id="hint-percentual-jornadas" class="text-xs text-slate-600 hidden pl-7">Marcado: nota = (% de jornadas <strong>concluídas</strong> no escopo) ÷ 100 × escala máx. Desmarcado: <strong>tabela por faixas</strong> sobre esse mesmo percentual (ex.: 40%–100% → 5–10). Em ambos os casos vale só conclusão da jornada, não acerto em questões.</p>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700" id="wrap-materia-unica">
                    <input id="bloco-materia-unica" type="checkbox" class="rounded border-gray-300">
                    Matérias únicas (somar notas da mesma matéria entre professores)
                </label>
            </div>
            <div class="md:col-span-2 grid grid-cols-1 md:grid-cols-2 gap-3">
                <label class="inline-flex items-center gap-2 text-sm text-gray-700">
                    <input id="bloco-obrigatorio" type="checkbox" class="rounded border-gray-300">
                    Componente obrigatório
                </label>
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">Arredondamento deste bloco</label>
                    <select id="bloco-round-mode-override" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                        <option value="herdar">Herdar do evento (padrão)</option>
                        <option value="none">Sem arredondamento especial (2 casas)</option>
                        <option value="half">Faixa .00/.50/próximo inteiro</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-1">Sobrescreve só esse bloco. Por padrão, usa o mesmo arredondamento configurado no evento.</p>
                </div>
            </div>

            <div class="md:col-span-2 flex justify-end gap-2 pt-2 border-t border-gray-100">
                <button type="button" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200" data-close-modal="1">Cancelar</button>
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90">Salvar bloco</button>
            </div>
        </form>
    </div>
</div>

<?php if (!$somenteTabela): ?>
<?php include __DIR__ . '/_assistente_wizard.php'; ?>
<script>
(function () {
    var btn = document.getElementById('boletim-abrir-assistente-guiado');
    var toggle = document.getElementById('boletim-assistente-toggle');
    if (btn && toggle) {
        btn.addEventListener('click', function () { toggle.click(); });
    }
})();
</script>
<?php endif; ?>

<style>
.bloco-card {
    border: 1px solid #e5e7eb;
    border-radius: 0.75rem;
    padding: 0.875rem;
    background: #ffffff;
}
.bloco-card.dragging {
    opacity: 0.5;
}
.bloco-handle {
    cursor: grab;
}
</style>

<script>
(function () {
    const lista = document.getElementById('lista-blocos');
    const inputJson = document.getElementById('componentes-json');
    const modal = document.getElementById('modal-bloco');
    const formModal = document.getElementById('form-modal-bloco');
    const btnAdd = document.getElementById('btn-add-bloco');
    const formRegra = document.getElementById('form-regra-boletim');

    if (!lista || !inputJson || !modal || !formModal || !btnAdd || !formRegra) {
        return;
    }

    let componentes = [];
    try {
        componentes = JSON.parse(inputJson.value || '[]');
        if (!Array.isArray(componentes)) {
            componentes = [];
        }
    } catch (e) {
        componentes = [];
    }

    const JORNADAS_JSON_URL = <?= json_encode(URL . '/admin/boletim-configuracao/jornadas', JSON_UNESCAPED_SLASHES) ?>;
    const EVENTO_COMPONENTES_JSON_URL = <?= json_encode(URL . '/admin/boletim-configuracao/evento-componentes', JSON_UNESCAPED_SLASHES) ?>;
    const CHECKLIST_PRE_GERACAO_URL = <?= json_encode(URL . '/admin/boletim-configuracao/checklist-pre-geracao', JSON_UNESCAPED_SLASHES) ?>;
    const LOGS_GERACAO_URL = <?= json_encode(URL . '/admin/boletim-configuracao/logs-geracao', JSON_UNESCAPED_SLASHES) ?>;
    const SIMULAR_LOTE_URL = <?= json_encode(URL . '/admin/boletim-configuracao/simular-lote', JSON_UNESCAPED_SLASHES) ?>;
    const REGRA_ATUAL_ID = <?= (int) ($regra['id'] ?? 0) ?>;
    const REGRA_ATUAL_BIMESTRE = <?= (int) ($regra['bimestre'] ?? 0) ?>;
    const REGRA_ATUAL_SERIES_IDS = <?= json_encode(array_values(array_map('intval', $seriesSelecionadasRegra))) ?>;

    const MODELOS_BOLETIM = {
        media3: {
            regra_nome: 'Boletim — semanal, bimestral e jornadas',
            formula_final: '(semanal + bimestral + jornadas) / 3',
            componentes: [
                { id: 0, codigo: 'semanal', nome: 'Prova semanal', source_type: 'provas_sistema', calc_type: 'media', peso: 1, filtro_titulo: 'semanal', bloco_id: 0, blocos_ids: [], materia_id: 0, materia_unica: 0, usar_percentual: 1, escala_max: 10, obrigatorio: 0 },
                { id: 0, codigo: 'bimestral', nome: 'Prova bimestral', source_type: 'provas_sistema', calc_type: 'media', peso: 1, filtro_titulo: 'bimestral', bloco_id: 0, blocos_ids: [], materia_id: 0, materia_unica: 0, usar_percentual: 1, escala_max: 10, obrigatorio: 0 },
                { id: 0, codigo: 'jornadas', nome: 'Jornadas', source_type: 'jornadas', calc_type: 'media', peso: 1, filtro_titulo: '', bloco_id: 0, blocos_ids: [], materia_id: 0, materia_unica: 0, usar_percentual: 1, escala_max: 10, obrigatorio: 0, config: { jornada_ids: [], data_ini: '', data_fim: '' } }
            ]
        },
        media3_enac: {
            regra_nome: 'Boletim — média 3 + ENAC (só melhora)',
            formula_final: 'max((semanal + bimestral + jornadas) / 3, ((semanal + bimestral + jornadas) / 3 + enac) / 2)',
            componentes: [
                { id: 0, codigo: 'semanal', nome: 'Prova semanal', source_type: 'provas_sistema', calc_type: 'media', peso: 1, filtro_titulo: 'semanal', bloco_id: 0, blocos_ids: [], materia_id: 0, materia_unica: 0, usar_percentual: 1, escala_max: 10, obrigatorio: 0 },
                { id: 0, codigo: 'bimestral', nome: 'Prova bimestral', source_type: 'provas_sistema', calc_type: 'media', peso: 1, filtro_titulo: 'bimestral', bloco_id: 0, blocos_ids: [], materia_id: 0, materia_unica: 0, usar_percentual: 1, escala_max: 10, obrigatorio: 0 },
                { id: 0, codigo: 'jornadas', nome: 'Jornadas', source_type: 'jornadas', calc_type: 'media', peso: 1, filtro_titulo: '', bloco_id: 0, blocos_ids: [], materia_id: 0, materia_unica: 0, usar_percentual: 1, escala_max: 10, obrigatorio: 0, config: { jornada_ids: [], data_ini: '', data_fim: '' } },
                { id: 0, codigo: 'enac', nome: 'ENAC', source_type: 'provas_sistema', calc_type: 'media', peso: 1, filtro_titulo: 'enac', bloco_id: 0, blocos_ids: [], materia_id: 0, materia_unica: 0, usar_percentual: 1, escala_max: 10, obrigatorio: 0 }
            ]
        }
    };

    try {
        var modeloKey = sessionStorage.getItem('boletim_aplicar_modelo');
        if (modeloKey && MODELOS_BOLETIM[modeloKey]) {
            sessionStorage.removeItem('boletim_aplicar_modelo');
            var md = MODELOS_BOLETIM[modeloKey];
            var nomeRegraEl = document.querySelector('input[name="regra_nome"]');
            var formFormulaEl = document.querySelector('input[name="formula_final"]');
            if (nomeRegraEl) {
                nomeRegraEl.value = md.regra_nome;
            }
            if (formFormulaEl) {
                formFormulaEl.value = md.formula_final;
            }
            componentes = JSON.parse(JSON.stringify(md.componentes));
        }
    } catch (eModelo) {
        /* ignore */
    }

    const fields = {
        idx: document.getElementById('bloco-edit-index'),
        nome: document.getElementById('bloco-nome'),
        codigo: document.getElementById('bloco-codigo'),
        source: document.getElementById('bloco-source'),
        calc: document.getElementById('bloco-calc'),
        peso: document.getElementById('bloco-peso'),
        escala: document.getElementById('bloco-escala'),
        layoutGroup: document.getElementById('bloco-layout-group'),
        layoutType: document.getElementById('bloco-layout-type'),
        filtro: document.getElementById('bloco-filtro'),
        blocoIds: document.getElementById('bloco-ids'),
        materiaId: document.getElementById('bloco-materia-id'),
        materiaUnica: document.getElementById('bloco-materia-unica'),
        percentual: document.getElementById('bloco-percentual'),
        obrigatorio: document.getElementById('bloco-obrigatorio'),
        roundModeOverride: document.getElementById('bloco-round-mode-override'),
        wrapFiltro: document.getElementById('wrap-filtro-titulo'),
        wrapBloco: document.getElementById('wrap-bloco-prova'),
        wrapMateria: document.getElementById('wrap-materia'),
        wrapGroupLine: document.getElementById('wrap-group-line'),
        wrapMateriaUnica: document.getElementById('wrap-materia-unica'),
        wrapJornadas: document.getElementById('wrap-jornadas'),
        wrapEventoBoletim: document.getElementById('wrap-evento-boletim'),
        wrapFaltasEvento: document.getElementById('wrap-faltas-evento'),
        wrapCalculado: document.getElementById('wrap-calculado'),
        wrapCalculadoGeral: document.getElementById('wrap-calculado-geral'),
        wrapCalculadoPorMateria: document.getElementById('wrap-calculado-por-materia'),
        expressaoCalculado: document.getElementById('bloco-expressao-calculado'),
        calcModoGeral: document.getElementById('bloco-calculado-modo-geral'),
        calcModoMateria: document.getElementById('bloco-calculado-modo-materia'),
        calculadoTracoAbaixoMinimo: document.getElementById('bloco-calculado-traco-abaixo-minimo'),
        jornadaTracoAbaixoMinimo: document.getElementById('bloco-jornada-traco-abaixo-minimo'),
        btnAddFormulaMateriaModal: document.getElementById('btn-add-formula-materia-modal'),
        listaFormulasMateriaModal: document.getElementById('lista-formulas-materia-modal'),
        eventoRegraCodigo: document.getElementById('evento-regra-codigo'),
        eventoComponenteCodigo: document.getElementById('evento-componente-codigo'),
        faltasEventoId: document.getElementById('faltas-evento-id'),
        jornadaIds: document.getElementById('jornada-ids'),
        jornadaDataIni: document.getElementById('jornada-data-ini'),
        jornadaDataFim: document.getElementById('jornada-data-fim'),
        jornadaDistribuicaoNotas: document.getElementById('jornada-distribuicao-notas'),
        wrapJornadasFaixas: document.getElementById('wrap-jornadas-faixas'),
        listaJornadasFaixas: document.getElementById('lista-jornadas-faixas'),
        modalTitulo: document.getElementById('modal-titulo'),
        hintPercentualJornadas: document.getElementById('hint-percentual-jornadas'),
        percentualLabel: document.getElementById('bloco-percentual-label')
        ,materiasLabel: document.getElementById('bloco-materias-label')
        ,materiasHint: document.getElementById('bloco-materias-hint')
        ,groupEnabled: document.getElementById('bloco-group-enabled')
        ,groupFields: document.getElementById('bloco-group-fields')
        ,groupKey: document.getElementById('bloco-group-key')
        ,groupLabel: document.getElementById('bloco-group-label')
        ,groupMode: document.getElementById('bloco-group-mode')
        ,groupDivisor: document.getElementById('bloco-group-divisor')
        ,wrapJornadaNotaUnicaExtras: document.getElementById('wrap-jornada-nota-unica-extras')
        ,listaJornadaFontePorMateria: document.getElementById('lista-jornada-fonte-por-materia')
        ,btnJornadaFonteMateriaAdd: document.getElementById('btn-jornada-fonte-materia-add')
        ,listaJornadaFontePorGrupo: document.getElementById('lista-jornada-fonte-por-grupo')
        ,btnJornadaFonteGrupoAdd: document.getElementById('btn-jornada-fonte-grupo-add')
    };

    var jornadasOptionsCacheKey = '';
    var eventoComponentesCache = {};
    var jornadasFaixas = [];
    var jornadaFontePorMateriaRows = [];
    var jornadaFontePorGrupoRows = [];

    function defaultJornadasFaixas() {
        return [10, 20, 30, 40, 50, 60, 70, 80, 90, 100].map(function (p) {
            return { percentual_min: p, nota: '' };
        });
    }

    function renderJornadasFaixas() {
        if (!fields.listaJornadasFaixas) {
            return;
        }
        fields.listaJornadasFaixas.innerHTML = '';
        jornadasFaixas.forEach(function (item, idx) {
            var row = document.createElement('div');
            row.className = 'flex items-center gap-2';

            var lb = document.createElement('span');
            lb.className = 'text-xs text-amber-900 min-w-[6rem] font-medium';
            lb.textContent = item.percentual_min + '%';

            var inp = document.createElement('input');
            inp.type = 'number';
            inp.min = '0';
            inp.step = '0.01';
            inp.className = 'w-full px-2 py-1.5 border border-amber-200 rounded text-sm';
            inp.placeholder = 'Nota';
            inp.value = item.nota === '' ? '' : String(item.nota);
            inp.addEventListener('input', function () {
                jornadasFaixas[idx].nota = inp.value;
            });

            row.appendChild(lb);
            row.appendChild(inp);
            fields.listaJornadasFaixas.appendChild(row);
        });
    }

    function collectGroupKeysFromRule() {
        var keys = [];
        if (!Array.isArray(componentes)) {
            return keys;
        }
        componentes.forEach(function (c) {
            if (c.config && c.config.group_line && c.config.group_line.enabled) {
                var k = String(c.config.group_line.key || '').trim();
                if (k && keys.indexOf(k) === -1) {
                    keys.push(k);
                }
            }
        });
        return keys;
    }

    function fillMateriaMultiSelect(sel, selectedIds) {
        if (!sel) {
            return;
        }
        var set = {};
        (selectedIds || []).forEach(function (x) {
            set[String(Number(x) || 0)] = true;
        });
        sel.innerHTML = '';
        Object.keys(materiaNames).forEach(function (mid) {
            var op = document.createElement('option');
            op.value = String(mid);
            op.textContent = materiaNames[mid];
            if (set[String(mid)]) {
                op.selected = true;
            }
            sel.appendChild(op);
        });
    }

    function setJornadaNotaUnicaMateriasCheckboxesAll(checked) {
        document.querySelectorAll('.jornada-nota-unica-materia-checkbox').forEach(function (el) {
            el.checked = !!checked;
        });
    }

    function getJornadaNotaUnicaOmitirFromCheckboxes() {
        var omit = [];
        document.querySelectorAll('.jornada-nota-unica-materia-checkbox').forEach(function (el) {
            var id = Number(el.value || 0);
            if (id > 0 && !el.checked) {
                omit.push(id);
            }
        });
        return omit;
    }

    function loadJornadaNotaUnicaMateriasFromOmitList(omitList) {
        var omitSet = {};
        (omitList || []).forEach(function (x) {
            omitSet[String(Number(x) || 0)] = true;
        });
        document.querySelectorAll('.jornada-nota-unica-materia-checkbox').forEach(function (el) {
            var id = String(el.value || '');
            el.checked = !omitSet[id];
        });
    }

    function loadJornadaNotaUnicaExtrasFromConfig(cfg) {
        cfg = cfg && typeof cfg === 'object' ? cfg : {};
        loadJornadaNotaUnicaMateriasFromOmitList(cfg.nota_unica_omitir_materias || []);
        jornadaFontePorMateriaRows = [];
        var fm = cfg.nota_unica_fonte_por_materia || {};
        Object.keys(fm).forEach(function (k) {
            var tid = Number(k);
            var arr = (fm[k] || []).map(function (x) { return Number(x); }).filter(function (x) { return x > 0; });
            if (tid !== 0 && arr.length > 0) {
                jornadaFontePorMateriaRows.push({ materia_id: tid, fonte_ids: arr });
            }
        });
        jornadaFontePorGrupoRows = [];
        var fg = cfg.nota_unica_fonte_por_grupo || {};
        Object.keys(fg).forEach(function (gk) {
            var arr = (fg[gk] || []).map(function (x) { return Number(x); }).filter(function (x) { return x > 0; });
            if (String(gk).trim() !== '' && arr.length > 0) {
                jornadaFontePorGrupoRows.push({ grupo_key: String(gk), fonte_ids: arr });
            }
        });
        renderJornadaNotaUnicaFonteRows();
    }

    function renderJornadaNotaUnicaFonteRows() {
        if (fields.listaJornadaFontePorMateria) {
            fields.listaJornadaFontePorMateria.innerHTML = '';
            if (jornadaFontePorMateriaRows.length === 0) {
                var em = document.createElement('p');
                em.className = 'text-xs text-slate-600';
                em.textContent = 'Nenhuma linha extra. A nota padrão (escopo inteiro) vale para todas, exceto omissões abaixo.';
                fields.listaJornadaFontePorMateria.appendChild(em);
            } else {
                jornadaFontePorMateriaRows.forEach(function (row, idx) {
                    var wrap = document.createElement('div');
                    wrap.className = 'grid grid-cols-1 md:grid-cols-12 gap-2 items-start border border-slate-200 rounded p-2 bg-white';

                    var lb1 = document.createElement('label');
                    lb1.className = 'md:col-span-3 text-xs text-slate-700 font-medium pt-1';
                    lb1.textContent = 'Linha (matéria)';

                    var selT = document.createElement('select');
                    selT.className = 'md:col-span-3 px-2 py-1.5 border border-slate-200 rounded text-sm';
                    var o0 = document.createElement('option');
                    o0.value = '';
                    o0.textContent = 'Selecione';
                    selT.appendChild(o0);
                    Object.keys(materiaNames).forEach(function (mid) {
                        var op = document.createElement('option');
                        op.value = String(mid);
                        op.textContent = materiaNames[mid];
                        if (Number(mid) === Number(row.materia_id || 0)) {
                            op.selected = true;
                        }
                        selT.appendChild(op);
                    });
                    selT.addEventListener('change', function () {
                        jornadaFontePorMateriaRows[idx].materia_id = Number(selT.value || 0);
                    });

                    var lb2 = document.createElement('label');
                    lb2.className = 'md:col-span-2 text-xs text-slate-700 font-medium pt-1';
                    lb2.textContent = 'Jornadas destas matérias';

                    var selF = document.createElement('select');
                    selF.multiple = true;
                    selF.size = 4;
                    selF.className = 'md:col-span-3 px-2 py-1 border border-slate-200 rounded text-sm';
                    fillMateriaMultiSelect(selF, row.fonte_ids);
                    selF.addEventListener('change', function () {
                        jornadaFontePorMateriaRows[idx].fonte_ids = Array.from(selF.selectedOptions)
                            .map(function (o) { return Number(o.value || 0); })
                            .filter(function (x) { return x > 0; });
                    });

                    var del = document.createElement('button');
                    del.type = 'button';
                    del.className = 'md:col-span-1 px-2 py-1 text-xs rounded bg-red-50 text-red-700 hover:bg-red-100 self-center';
                    del.textContent = 'Remover';
                    del.addEventListener('click', function () {
                        jornadaFontePorMateriaRows.splice(idx, 1);
                        renderJornadaNotaUnicaFonteRows();
                    });

                    wrap.appendChild(lb1);
                    wrap.appendChild(selT);
                    wrap.appendChild(lb2);
                    wrap.appendChild(selF);
                    wrap.appendChild(del);
                    fields.listaJornadaFontePorMateria.appendChild(wrap);
                });
            }
        }
        if (fields.listaJornadaFontePorGrupo) {
            fields.listaJornadaFontePorGrupo.innerHTML = '';
            if (jornadaFontePorGrupoRows.length === 0) {
                var eg = document.createElement('p');
                eg.className = 'text-xs text-slate-600';
                eg.textContent = 'Nenhum mapeamento por grupo.';
                fields.listaJornadaFontePorGrupo.appendChild(eg);
            } else {
                var gkeys = collectGroupKeysFromRule();
                jornadaFontePorGrupoRows.forEach(function (row, idx) {
                    var wrap = document.createElement('div');
                    wrap.className = 'grid grid-cols-1 md:grid-cols-12 gap-2 items-start border border-slate-200 rounded p-2 bg-white';

                    var lb1 = document.createElement('label');
                    lb1.className = 'md:col-span-3 text-xs text-slate-700 font-medium pt-1';
                    lb1.textContent = 'Código do grupo';

                    var selG = document.createElement('select');
                    selG.className = 'md:col-span-3 px-2 py-1.5 border border-slate-200 rounded text-sm';
                    var o0g = document.createElement('option');
                    o0g.value = '';
                    o0g.textContent = gkeys.length ? 'Selecione' : 'Nenhum grupo na regra';
                    selG.appendChild(o0g);
                    gkeys.forEach(function (gk) {
                        var op = document.createElement('option');
                        op.value = String(gk);
                        op.textContent = String(gk);
                        if (String(row.grupo_key || '') === String(gk)) {
                            op.selected = true;
                        }
                        selG.appendChild(op);
                    });
                    if (gkeys.length === 0 && String(row.grupo_key || '').trim() !== '') {
                        var opx = document.createElement('option');
                        opx.value = String(row.grupo_key);
                        opx.textContent = String(row.grupo_key) + ' (salvo)';
                        opx.selected = true;
                        selG.appendChild(opx);
                    }
                    selG.addEventListener('change', function () {
                        jornadaFontePorGrupoRows[idx].grupo_key = String(selG.value || '').trim();
                    });

                    var lb2g = document.createElement('label');
                    lb2g.className = 'md:col-span-2 text-xs text-slate-700 font-medium pt-1';
                    lb2g.textContent = 'Jornadas destas matérias';

                    var selFg = document.createElement('select');
                    selFg.multiple = true;
                    selFg.size = 4;
                    selFg.className = 'md:col-span-3 px-2 py-1 border border-slate-200 rounded text-sm';
                    fillMateriaMultiSelect(selFg, row.fonte_ids);
                    selFg.addEventListener('change', function () {
                        jornadaFontePorGrupoRows[idx].fonte_ids = Array.from(selFg.selectedOptions)
                            .map(function (o) { return Number(o.value || 0); })
                            .filter(function (x) { return x > 0; });
                    });

                    var delg = document.createElement('button');
                    delg.type = 'button';
                    delg.className = 'md:col-span-1 px-2 py-1 text-xs rounded bg-red-50 text-red-700 hover:bg-red-100 self-center';
                    delg.textContent = 'Remover';
                    delg.addEventListener('click', function () {
                        jornadaFontePorGrupoRows.splice(idx, 1);
                        renderJornadaNotaUnicaFonteRows();
                    });

                    wrap.appendChild(lb1);
                    wrap.appendChild(selG);
                    wrap.appendChild(lb2g);
                    wrap.appendChild(selFg);
                    wrap.appendChild(delg);
                    fields.listaJornadaFontePorGrupo.appendChild(wrap);
                });
            }
        }
    }

    function normalizeJornadasFaixas(raw) {
        var base = defaultJornadasFaixas();
        if (!Array.isArray(raw) || raw.length === 0) {
            return base;
        }
        var map = {};
        raw.forEach(function (it) {
            if (!it || typeof it !== 'object') {
                return;
            }
            var p = Number(it.percentual_min || 0);
            if (!Number.isFinite(p)) {
                return;
            }
            p = Math.max(0, Math.min(100, Math.round(p)));
            if (p % 10 !== 0 || p < 10) {
                return;
            }
            var n = (it.nota === '' || it.nota === null || typeof it.nota === 'undefined') ? '' : String(it.nota);
            map[p] = n;
        });
        return base.map(function (b) {
            return { percentual_min: b.percentual_min, nota: Object.prototype.hasOwnProperty.call(map, b.percentual_min) ? map[b.percentual_min] : '' };
        });
    }

    function getJornadasFaixasFromModal() {
        return jornadasFaixas
            .map(function (it) {
                var notaRaw = String(it.nota === undefined || it.nota === null ? '' : it.nota).trim();
                if (notaRaw === '') {
                    return null;
                }
                var nota = Number(notaRaw.replace(',', '.'));
                if (!Number.isFinite(nota) || nota < 0) {
                    return null;
                }
                return { percentual_min: Number(it.percentual_min || 0), nota: nota };
            })
            .filter(function (it) { return !!it; });
    }

    function loadJornadasOptionsIfNeeded(done) {
        if (!fields.jornadaIds) {
            if (done) {
                done();
            }
            return;
        }
        var ini = fields.jornadaDataIni ? String(fields.jornadaDataIni.value || '').trim() : '';
        var fim = fields.jornadaDataFim ? String(fields.jornadaDataFim.value || '').trim() : '';
        var cacheKey = [ini, fim].join('|');
        if (jornadasOptionsCacheKey === cacheKey) {
            if (done) {
                done();
            }
            return;
        }
        var url = JORNADAS_JSON_URL;
        var qs = [];
        if (ini) {
            qs.push('data_ini=' + encodeURIComponent(ini));
        }
        if (fim) {
            qs.push('data_fim=' + encodeURIComponent(fim));
        }
        if (qs.length > 0) {
            url += '?' + qs.join('&');
        }
        fetch(url, { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var sel = fields.jornadaIds;
                sel.innerHTML = '';
                var lista = data.jornadas || [];
                lista.forEach(function (j) {
                    var opt = document.createElement('option');
                    opt.value = String(j.id);
                    var rotulo = String(j.rotulo || '').trim();
                    if (!rotulo) {
                        var materia = String(j.materia_nome || '').trim().toUpperCase() || 'SEM MATERIA';
                        var prof = String(j.professor_nome || '').trim().toUpperCase() || 'SEM PROFESSOR';
                        var dataTxt = String(j.data_fim_jornada || '').trim().toUpperCase() || 'SEM DATA FINAL';
                        rotulo = ('#' + j.id + ' - ' + materia + ' - ' + prof + ' - ' + dataTxt).toUpperCase();
                    }
                    opt.textContent = rotulo;
                    sel.appendChild(opt);
                });
                renderJornadaTabela(lista);
                jornadasOptionsCacheKey = cacheKey;
                if (done) {
                    done();
                }
            })
            .catch(function () {
                jornadasOptionsCacheKey = '';
                if (done) {
                    done();
                }
            });
    }

    function formatarDataBr(dataYmd) {
        var m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(dataYmd || ''));
        if (!m) {
            return '';
        }
        return m[3] + '/' + m[2] + '/' + m[1];
    }

    function renderJornadaTabela(lista) {
        var corpo = document.getElementById('jornada-tabela-corpo');
        if (!corpo) {
            return;
        }
        corpo.innerHTML = '';
        if (!lista || lista.length === 0) {
            corpo.innerHTML = '<tr><td colspan="7" class="px-3 py-6 text-center text-gray-400">Nenhuma jornada encontrada</td></tr>';
        }

        var materiasSet = {};
        var anosSet = {};

        (lista || []).forEach(function (j) {
            var jid = parseInt(j.id, 10);
            var materiaNome = String(j.materia_nome || 'SEM MATERIA').trim();
            var profNome = String(j.professor_nome || 'SEM PROFESSOR').trim();
            var dataFim = String(j.data_fim_jornada || '').trim();
            var bimestre = (j.bimestre !== null && j.bimestre !== undefined && j.bimestre !== '') ? String(j.bimestre) : '';
            var ano = (j.ano_letivo !== null && j.ano_letivo !== undefined && j.ano_letivo !== '')
                ? String(j.ano_letivo)
                : (/^\d{4}/.exec(dataFim) ? dataFim.substring(0, 4) : '');
            var titulo = String(j.titulo || ('Jornada #' + jid)).trim();
            var tituloBusca = (titulo + ' ' + materiaNome + ' ' + profNome).toLowerCase();

            if (materiaNome) { materiasSet[materiaNome] = true; }
            if (ano) { anosSet[ano] = true; }

            var tr = document.createElement('tr');
            tr.className = 'jornada-tabela-row hover:bg-gray-50 cursor-pointer';
            tr.dataset.jornadaId = String(jid);
            tr.dataset.titulo = tituloBusca;
            tr.dataset.materia = materiaNome;
            tr.dataset.bimestre = bimestre;
            tr.dataset.ano = ano;
            tr.innerHTML =
                '<td class="px-3 py-2"><input type="checkbox" class="jornada-tabela-checkbox rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" value="' + jid + '"></td>' +
                '<td class="px-3 py-2 whitespace-nowrap text-gray-900">#' + jid + '</td>' +
                '<td class="px-3 py-2 whitespace-nowrap text-gray-600">' + escapeHtml(materiaNome) + '</td>' +
                '<td class="px-3 py-2 whitespace-nowrap text-gray-600">' + escapeHtml(profNome) + '</td>' +
                '<td class="px-3 py-2 whitespace-nowrap text-gray-600">' + (bimestre ? bimestre + 'º' : 'N/A') + '</td>' +
                '<td class="px-3 py-2 whitespace-nowrap text-gray-600">' + (ano || 'N/A') + '</td>' +
                '<td class="px-3 py-2 whitespace-nowrap text-gray-600">' + (formatarDataBr(dataFim) || 'N/A') + '</td>';
            corpo.appendChild(tr);
        });

        var filtroMateria = document.getElementById('jornada-tabela-filtro-materia');
        if (filtroMateria) {
            var valorAtualMateria = filtroMateria.value;
            filtroMateria.innerHTML = '<option value="">Todas</option>';
            Object.keys(materiasSet).sort().forEach(function (m) {
                var opt = document.createElement('option');
                opt.value = m;
                opt.textContent = m;
                filtroMateria.appendChild(opt);
            });
            filtroMateria.value = valorAtualMateria;
        }
        var filtroAno = document.getElementById('jornada-tabela-filtro-ano');
        if (filtroAno) {
            var valorAtualAno = filtroAno.value;
            filtroAno.innerHTML = '<option value="">Todos</option>';
            Object.keys(anosSet).sort().forEach(function (a) {
                var opt = document.createElement('option');
                opt.value = a;
                opt.textContent = a;
                filtroAno.appendChild(opt);
            });
            filtroAno.value = valorAtualAno;
        }

        syncJornadaTabelaFromSelect();
        aplicarFiltrosJornadaTabela();
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = String(str || '');
        return div.innerHTML;
    }

    function syncJornadaTabelaFromSelect() {
        if (!fields.jornadaIds) {
            return;
        }
        var selecionados = getSelectedJornadaIdsFromModal();
        var set = {};
        selecionados.forEach(function (id) { set[id] = true; });
        document.querySelectorAll('.jornada-tabela-checkbox').forEach(function (cb) {
            cb.checked = !!set[parseInt(cb.value, 10)];
        });
        var contador = document.getElementById('jornada-tabela-contador');
        if (contador) {
            contador.textContent = selecionados.length + ' selecionada(s)';
        }
    }

    function toggleJornadaTabelaRow(jornadaId) {
        if (!fields.jornadaIds) {
            return;
        }
        var opt = Array.from(fields.jornadaIds.options).find(function (o) {
            return parseInt(o.value, 10) === jornadaId;
        });
        if (!opt) {
            return;
        }
        opt.selected = !opt.selected;
        syncJornadaTabelaFromSelect();
    }

    function aplicarFiltrosJornadaTabela() {
        var filtroTitulo = (document.getElementById('jornada-tabela-filtro-titulo') || {}).value || '';
        var filtroMateria = (document.getElementById('jornada-tabela-filtro-materia') || {}).value || '';
        var filtroBimestre = (document.getElementById('jornada-tabela-filtro-bimestre') || {}).value || '';
        var filtroAno = (document.getElementById('jornada-tabela-filtro-ano') || {}).value || '';
        var tituloLower = filtroTitulo.trim().toLowerCase();
        document.querySelectorAll('.jornada-tabela-row').forEach(function (row) {
            var visivel = true;
            if (tituloLower && row.dataset.titulo.indexOf(tituloLower) === -1) {
                visivel = false;
            }
            if (filtroMateria && row.dataset.materia !== filtroMateria) {
                visivel = false;
            }
            if (filtroBimestre && row.dataset.bimestre !== filtroBimestre) {
                visivel = false;
            }
            if (filtroAno && row.dataset.ano !== filtroAno) {
                visivel = false;
            }
            row.classList.toggle('hidden', !visivel);
        });
    }

    function selecionarJornadasFiltradas() {
        if (!fields.jornadaIds) {
            return;
        }
        var idsVisiveis = {};
        document.querySelectorAll('.jornada-tabela-row:not(.hidden)').forEach(function (row) {
            idsVisiveis[parseInt(row.dataset.jornadaId, 10)] = true;
        });
        Array.from(fields.jornadaIds.options).forEach(function (opt) {
            var v = parseInt(opt.value, 10);
            if (idsVisiveis[v]) {
                opt.selected = true;
            }
        });
        syncJornadaTabelaFromSelect();
    }

    function selectEventoComponenteValue(selectEl, codigo) {
        if (!selectEl) {
            return;
        }
        var target = String(codigo || '').trim();
        if (!target) {
            selectEl.value = '';
            return;
        }
        var found = Array.from(selectEl.options).some(function (opt) {
            return String(opt.value || '').trim() === target;
        });
        if (!found) {
            var custom = document.createElement('option');
            custom.value = target;
            custom.textContent = target + ' (não encontrado na lista)';
            selectEl.appendChild(custom);
        }
        selectEl.value = target;
    }

    function fillEventoComponentesSelect(componentes, selectedCode) {
        if (!fields.eventoComponenteCodigo) {
            return;
        }
        var sel = fields.eventoComponenteCodigo;
        sel.innerHTML = '';
        var opt0 = document.createElement('option');
        opt0.value = '';
        opt0.textContent = 'Selecione o bloco/coluna';
        sel.appendChild(opt0);

        (componentes || []).forEach(function (c) {
            var cod = String((c && c.codigo) || '').trim();
            if (!cod) {
                return;
            }
            var nome = String((c && c.nome) || cod).trim();
            var op = document.createElement('option');
            op.value = cod;
            op.textContent = cod + ' - ' + nome;
            sel.appendChild(op);
        });

        selectEventoComponenteValue(sel, selectedCode || '');
    }

    function loadEventoComponentesOptions(regraCodigo, selectedCode) {
        if (!fields.eventoComponenteCodigo) {
            return;
        }
        var codRegra = String(regraCodigo || '').trim();
        if (!codRegra) {
            fillEventoComponentesSelect([], '');
            return;
        }
        if (eventoComponentesCache[codRegra]) {
            fillEventoComponentesSelect(eventoComponentesCache[codRegra], selectedCode || '');
            return;
        }
        fillEventoComponentesSelect([], '');
        fields.eventoComponenteCodigo.disabled = true;
        fetch(EVENTO_COMPONENTES_JSON_URL + '?regra_codigo=' + encodeURIComponent(codRegra), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var comps = Array.isArray(data.componentes) ? data.componentes : [];
                eventoComponentesCache[codRegra] = comps;
                fillEventoComponentesSelect(comps, selectedCode || '');
            })
            .catch(function () {
                fillEventoComponentesSelect([], selectedCode || '');
            })
            .finally(function () {
                fields.eventoComponenteCodigo.disabled = false;
            });
    }

    function getSelectedJornadaIdsFromModal() {
        if (!fields.jornadaIds) {
            return [];
        }
        return Array.from(fields.jornadaIds.selectedOptions)
            .map(function (o) { return parseInt(o.value, 10); })
            .filter(function (id) { return id > 0; });
    }

    function setJornadaIdsModalSelection(ids) {
        if (!fields.jornadaIds) {
            return;
        }
        var set = {};
        (ids || []).forEach(function (id) {
            if (id > 0) {
                set[id] = true;
            }
        });
        Array.from(fields.jornadaIds.options).forEach(function (opt) {
            var v = parseInt(opt.value, 10);
            opt.selected = !!set[v];
        });
        syncJornadaTabelaFromSelect();
    }

    const calcLabel = {
        media: 'Média',
        soma: 'Soma',
        maior: 'Maior',
        ultima: 'Última'
    };
    const materiaNames = <?= json_encode($materiasById, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const formulaMateriasInicial = <?= json_encode($formulaMateriasMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
    const formulaMateriasJsonInput = document.getElementById('formula-materias-json');
    const listaFormulasMateria = document.getElementById('lista-formulas-materia');
    const btnAddFormulaMateria = document.getElementById('btn-add-formula-materia');
    let formulasMateriaRows = [];
    let formulasMateriaModalRows = [];

    function slugify(text) {
        return (text || '')
            .toString()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '')
            .substring(0, 120);
    }

    const regraNomeInput = document.getElementById('regra-nome');
    const regraCodigoInput = document.getElementById('regra-codigo');
    const wrapExibicaoBimestre = document.getElementById('wrap-exibicao-bimestre');
    const regraBimestreSelect = document.getElementById('regra-bimestre');
    let regraCodigoEditadoManual = false;
    if (regraCodigoInput) {
        regraCodigoInput.addEventListener('input', function () {
            var atual = String(regraCodigoInput.value || '').trim();
            regraCodigoEditadoManual = atual !== '';
        });
    }
    var btnEditarCodigoEvento = document.getElementById('btn-editar-codigo-evento');
    if (btnEditarCodigoEvento && regraCodigoInput) {
        btnEditarCodigoEvento.addEventListener('click', function () {
            regraCodigoInput.readOnly = false;
            regraCodigoInput.classList.remove('bg-gray-50', 'text-gray-600', 'border-gray-200');
            regraCodigoInput.classList.add('bg-white', 'border-gray-300');
            regraCodigoInput.focus();
            btnEditarCodigoEvento.classList.add('hidden');
        });
    }
    if (regraNomeInput && regraCodigoInput) {
        var inicialCodigo = String(regraCodigoInput.value || '').trim();
        if (inicialCodigo === '') {
            var sugIni = slugify(String(regraNomeInput.value || '').trim());
            if (sugIni !== '') {
                regraCodigoInput.value = sugIni;
            }
        } else {
            var nomeSlug = slugify(String(regraNomeInput.value || '').trim());
            regraCodigoEditadoManual = nomeSlug !== '' && inicialCodigo !== nomeSlug;
        }
        regraNomeInput.addEventListener('input', function () {
            if (regraCodigoEditadoManual) {
                return;
            }
            regraCodigoInput.value = slugify(String(regraNomeInput.value || '').trim());
        });
    }

    function syncExibicaoCampos() {
        var exibirSel = document.querySelector('input[name="exibir_em"]:checked');
        var exibirVal = exibirSel ? String(exibirSel.value || 'boletim') : 'boletim';
        var isNotas = exibirVal === 'notas';
        if (wrapExibicaoBimestre) {
            wrapExibicaoBimestre.classList.toggle('hidden', !isNotas);
        }
        if (regraBimestreSelect) {
            regraBimestreSelect.disabled = !isNotas;
            if (!isNotas) {
                regraBimestreSelect.value = '';
            }
        }
    }
    document.querySelectorAll('input[name="exibir_em"]').forEach(function (el) {
        el.addEventListener('change', syncExibicaoCampos);
    });
    syncExibicaoCampos();

    function syncJson() {
        inputJson.value = JSON.stringify(componentes);
    }

    function getSelectedMateriasFromModal() {
        return Array.from(document.querySelectorAll('.bloco-materia-checkbox:checked'))
            .map(function (el) { return parseInt(el.value, 10); })
            .filter(function (id) { return id > 0; });
    }

    function setSelectedMateriasOnModal(ids) {
        const set = {};
        (ids || []).forEach(function (id) {
            if (id > 0) {
                set[id] = true;
            }
        });
        Array.from(document.querySelectorAll('.bloco-materia-checkbox')).forEach(function (el) {
            var id = parseInt(el.value, 10);
            el.checked = !!set[id];
        });
    }

    function getSelectedGroupMateriasFromModal() {
        return Array.from(document.querySelectorAll('.bloco-group-materia-checkbox:checked'))
            .map(function (el) { return parseInt(el.value, 10); })
            .filter(function (id) { return id > 0; });
    }

    function setSelectedGroupMateriasOnModal(ids) {
        const set = {};
        (ids || []).forEach(function (id) {
            if (id > 0) {
                set[id] = true;
            }
        });
        Array.from(document.querySelectorAll('.bloco-group-materia-checkbox')).forEach(function (el) {
            var id = parseInt(el.value, 10);
            el.checked = !!set[id];
        });
    }

    function toggleGroupLineFields() {
        if (!fields.groupFields || !fields.groupEnabled) {
            return;
        }
        var ativo = !!fields.groupEnabled.checked;
        fields.groupFields.classList.toggle('hidden', !ativo);
    }

    function syncFormulaMateriasJson() {
        if (!formulaMateriasJsonInput) {
            return;
        }
        var payload = formulasMateriaRows
            .filter(function (row) { return row && row.materia_id > 0 && String(row.formula || '').trim() !== ''; })
            .map(function (row) {
                return {
                    materia_id: Number(row.materia_id || 0),
                    formula: String(row.formula || '').trim()
                };
            });
        formulaMateriasJsonInput.value = JSON.stringify(payload);
    }

    function getCalcModoModal() {
        return fields.calcModoMateria && fields.calcModoMateria.checked ? 'materia' : 'geral';
    }

    function renderFormulasMateriaModal() {
        if (!fields.listaFormulasMateriaModal) {
            return;
        }
        fields.listaFormulasMateriaModal.innerHTML = '';
        if (!formulasMateriaModalRows.length) {
            var empty = document.createElement('p');
            empty.className = 'text-xs text-indigo-700';
            empty.textContent = 'Sem exceções por matéria.';
            fields.listaFormulasMateriaModal.appendChild(empty);
            return;
        }
        formulasMateriaModalRows.forEach(function (row, idx) {
            var wrap = document.createElement('div');
            wrap.className = 'grid grid-cols-1 md:grid-cols-12 gap-2 items-center';

            var sel = document.createElement('select');
            sel.className = 'md:col-span-4 px-2 py-1.5 border border-indigo-200 rounded bg-white text-sm';
            var opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = 'Selecione a matéria';
            sel.appendChild(opt0);
            Object.keys(materiaNames).forEach(function (mid) {
                var op = document.createElement('option');
                op.value = String(mid);
                op.textContent = materiaNames[mid];
                if (Number(mid) === Number(row.materia_id || 0)) {
                    op.selected = true;
                }
                sel.appendChild(op);
            });
            sel.addEventListener('change', function () {
                formulasMateriaModalRows[idx].materia_id = Number(sel.value || 0);
            });

            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'md:col-span-7 px-2 py-1.5 border border-indigo-200 rounded bg-white text-sm font-mono';
            inp.placeholder = 'ex.: max(media, (media + enac) / 2)';
            inp.setAttribute('data-fm-modal-idx', String(idx));
            inp.value = String(row.formula || '');
            inp.addEventListener('input', function () {
                formulasMateriaModalRows[idx].formula = inp.value;
            });

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'md:col-span-1 px-2 py-1.5 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200';
            del.textContent = 'Remover';
            del.addEventListener('click', function () {
                formulasMateriaModalRows.splice(idx, 1);
                renderFormulasMateriaModal();
            });

            wrap.appendChild(sel);
            wrap.appendChild(inp);
            wrap.appendChild(del);
            fields.listaFormulasMateriaModal.appendChild(wrap);
        });
    }

    function syncCalculadoModoView() {
        var byMateria = getCalcModoModal() === 'materia';
        if (fields.wrapCalculadoGeral) {
            // A expressão geral é sempre a base para todas as matérias.
            // O modo "por matéria" adiciona apenas exceções.
            fields.wrapCalculadoGeral.classList.remove('hidden');
        }
        if (fields.wrapCalculadoPorMateria) {
            fields.wrapCalculadoPorMateria.classList.toggle('hidden', !byMateria);
        }
        if (byMateria && formulasMateriaModalRows.length === 0) {
            formulasMateriaModalRows.push({ materia_id: 0, formula: '' });
            renderFormulasMateriaModal();
        }
    }

    function renderFormulaMaterias() {
        if (!listaFormulasMateria) {
            return;
        }
        listaFormulasMateria.innerHTML = '';
        if (!formulasMateriaRows.length) {
            var empty = document.createElement('p');
            empty.className = 'text-xs text-indigo-700';
            empty.textContent = 'Sem override por matéria. A fórmula final geral será usada para todas.';
            listaFormulasMateria.appendChild(empty);
            syncFormulaMateriasJson();
            return;
        }
        formulasMateriaRows.forEach(function (row, idx) {
            var wrap = document.createElement('div');
            wrap.className = 'grid grid-cols-1 md:grid-cols-12 gap-2 items-center';

            var sel = document.createElement('select');
            sel.className = 'md:col-span-4 px-2 py-1.5 border border-indigo-200 rounded bg-white text-sm';
            var opt0 = document.createElement('option');
            opt0.value = '';
            opt0.textContent = 'Selecione a matéria';
            sel.appendChild(opt0);
            Object.keys(materiaNames).forEach(function (mid) {
                var op = document.createElement('option');
                op.value = String(mid);
                op.textContent = materiaNames[mid];
                if (Number(mid) === Number(row.materia_id || 0)) {
                    op.selected = true;
                }
                sel.appendChild(op);
            });
            sel.addEventListener('change', function () {
                formulasMateriaRows[idx].materia_id = Number(sel.value || 0);
                syncFormulaMateriasJson();
            });

            var inp = document.createElement('input');
            inp.type = 'text';
            inp.className = 'md:col-span-7 px-2 py-1.5 border border-indigo-200 rounded bg-white text-sm';
            inp.placeholder = 'ex.: max(media, (media + enac) / 2)';
            inp.value = String(row.formula || '');
            inp.addEventListener('input', function () {
                formulasMateriaRows[idx].formula = inp.value;
                syncFormulaMateriasJson();
            });

            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'md:col-span-1 px-2 py-1.5 text-xs rounded bg-red-100 text-red-700 hover:bg-red-200';
            del.textContent = 'Remover';
            del.addEventListener('click', function () {
                formulasMateriaRows.splice(idx, 1);
                renderFormulaMaterias();
            });

            wrap.appendChild(sel);
            wrap.appendChild(inp);
            wrap.appendChild(del);
            listaFormulasMateria.appendChild(wrap);
        });
        syncFormulaMateriasJson();
    }

    function getSelectedBlocoIdsFromModal() {
        if (!fields.blocoIds) {
            return [];
        }
        return Array.from(fields.blocoIds.selectedOptions)
            .map(function (o) { return parseInt(o.value, 10); })
            .filter(function (id) { return id > 0; });
    }

    function setBlocoIdsModalSelection(ids) {
        if (!fields.blocoIds) {
            return;
        }
        const set = {};
        (ids || []).forEach(function (id) {
            if (id > 0) {
                set[id] = true;
            }
        });
        Array.from(fields.blocoIds.options).forEach(function (opt) {
            const v = parseInt(opt.value, 10);
            opt.selected = !!set[v];
        });
        syncBlocoTabelaFromSelect();
        updateFiltroBlocoHint();
    }

    function syncBlocoTabelaFromSelect() {
        if (!fields.blocoIds) {
            return;
        }
        const selecionados = getSelectedBlocoIdsFromModal();
        const set = {};
        selecionados.forEach(function (id) { set[id] = true; });
        document.querySelectorAll('.bloco-tabela-checkbox').forEach(function (cb) {
            cb.checked = !!set[parseInt(cb.value, 10)];
        });
        const contador = document.getElementById('bloco-tabela-contador');
        if (contador) {
            contador.textContent = selecionados.length + ' selecionado(s)';
        }
    }

    function toggleBlocoTabelaRow(blocoId) {
        if (!fields.blocoIds) {
            return;
        }
        const opt = Array.from(fields.blocoIds.options).find(function (o) {
            return parseInt(o.value, 10) === blocoId;
        });
        if (!opt) {
            return;
        }
        opt.selected = !opt.selected;
        syncBlocoTabelaFromSelect();
        fields.blocoIds.dispatchEvent(new Event('change'));
    }

    function aplicarFiltrosBlocoTabela() {
        const filtroTitulo = (document.getElementById('bloco-tabela-filtro-titulo') || {}).value || '';
        const filtroTipo = (document.getElementById('bloco-tabela-filtro-tipo') || {}).value || '';
        const filtroBimestre = (document.getElementById('bloco-tabela-filtro-bimestre') || {}).value || '';
        const filtroAno = (document.getElementById('bloco-tabela-filtro-ano') || {}).value || '';
        const tituloLower = filtroTitulo.trim().toLowerCase();
        document.querySelectorAll('.bloco-tabela-row').forEach(function (row) {
            let visivel = true;
            if (tituloLower && row.dataset.titulo.indexOf(tituloLower) === -1) {
                visivel = false;
            }
            if (filtroTipo && row.dataset.tipo !== filtroTipo) {
                visivel = false;
            }
            if (filtroBimestre && row.dataset.bimestre !== filtroBimestre) {
                visivel = false;
            }
            if (filtroAno && row.dataset.ano !== String(filtroAno)) {
                visivel = false;
            }
            row.classList.toggle('hidden', !visivel);
        });
    }

    function selecionarBlocosFiltrados() {
        if (!fields.blocoIds) {
            return;
        }
        var idsVisiveis = {};
        document.querySelectorAll('.bloco-tabela-row:not(.hidden)').forEach(function (row) {
            idsVisiveis[parseInt(row.dataset.blocoId, 10)] = true;
        });
        Array.from(fields.blocoIds.options).forEach(function (opt) {
            var v = parseInt(opt.value, 10);
            if (idsVisiveis[v]) {
                opt.selected = true;
            }
        });
        syncBlocoTabelaFromSelect();
        fields.blocoIds.dispatchEvent(new Event('change'));
    }

    function updateFiltroBlocoHint() {
        var hint = document.getElementById('hint-filtro-blocos');
        if (!hint || !fields.blocoIds) {
            return;
        }
        var jorn = fields.source.value === 'jornadas';
        var evento = fields.source.value === 'evento_boletim';
        var faltasEvento = fields.source.value === 'faltas_evento';
        var calc = fields.source.value === 'calculado';
        var temBlocos = getSelectedBlocoIdsFromModal().length > 0;
        hint.classList.toggle('hidden', jorn || evento || faltasEvento || calc || !temBlocos);
    }

    var pendingJornadaSel = [];

    function renderLista() {
        lista.innerHTML = '';

        if (componentes.length === 0) {
            const empty = document.createElement('div');
            empty.className = 'p-4 rounded-lg border border-dashed border-gray-300 text-sm text-gray-500';
            empty.textContent = 'Nenhum bloco adicionado ainda. Clique em "Adicionar bloco".';
            lista.appendChild(empty);
            syncJson();
            return;
        }

        componentes.forEach((item, index) => {
            const card = document.createElement('div');
            card.className = 'bloco-card flex items-center justify-between gap-3';
            card.setAttribute('draggable', 'true');
            card.dataset.index = String(index);

            const left = document.createElement('div');
            left.className = 'min-w-0';

            const nome = document.createElement('div');
            nome.className = 'text-sm font-semibold text-gray-900';
            nome.textContent = item.nome || 'Sem nome';

            const meta = document.createElement('div');
            meta.className = 'text-xs text-gray-500 mt-1';
            let tipo = 'Provas';
            if (item.source_type === 'nenhuma') {
                tipo = '-';
            } else if (item.source_type === 'jornadas') {
                tipo = 'Jornadas';
            } else if (item.source_type === 'evento_boletim') {
                tipo = 'Evento';
            } else if (item.source_type === 'faltas_evento') {
                tipo = 'Faltas';
            } else if (item.source_type === 'calculado') {
                tipo = 'Calculada';
            }
            const calc = calcLabel[item.calc_type] || 'Média';
            let blocoTxt = '';
            if (item.source_type === 'calculado' && item.config && item.config.expressao) {
                var exs = String(item.config.expressao).trim();
                if (exs.length > 48) {
                    exs = exs.substring(0, 48) + '…';
                }
                blocoTxt = ' | ' + exs;
                if (item.config.formula_materias && typeof item.config.formula_materias === 'object' && Object.keys(item.config.formula_materias).length > 0) {
                    blocoTxt += ' | com fórmulas por matéria';
                }
            }
            if (item.source_type === 'jornadas' && item.config && item.config.distribuicao_notas === 'nota_unica_todas_linhas') {
                blocoTxt += ' | nota única em todas as linhas';
            }
            if (item.source_type === 'jornadas' && item.config && Array.isArray(item.config.jornada_ids) && item.config.jornada_ids.length > 0) {
                blocoTxt += ' | jornadas selecionadas';
            }
            if (item.source_type === 'evento_boletim' && item.config) {
                var evCode = String(item.config.regra_codigo || '').trim();
                var evComp = String(item.config.componente_codigo || '').trim();
                if (evCode || evComp) {
                    blocoTxt += ' | evento: ' + (evCode || '-') + ' | coluna: ' + (evComp || '-');
                }
            }
            if (item.source_type === 'faltas_evento' && item.config) {
                var evf = Number(item.config.faltas_evento_id || 0);
                if (evf > 0) {
                    blocoTxt += ' | evento faltas: #' + evf;
                }
            }
            if (Array.isArray(item.blocos_ids) && item.blocos_ids.length >= 2) {
                blocoTxt += ' | blocos: #' + item.blocos_ids.join(', #');
            } else if (Array.isArray(item.blocos_ids) && item.blocos_ids.length === 1) {
                blocoTxt += ' | bloco: #' + item.blocos_ids[0];
            } else if (Number(item.bloco_id || 0) > 0) {
                blocoTxt += ' | bloco: #' + Number(item.bloco_id);
            }
            const materiaId = Number(item.materia_id || 0);
            const materiaTxt = materiaId > 0 ? ' | matéria: ' + (materiaNames[String(materiaId)] || ('Matéria #' + materiaId)) : '';
            var materiasTxt = '';
            if (Array.isArray(item.materias_ids) && item.materias_ids.length > 0) {
                var nomesMat = item.materias_ids
                    .map(function (mid) {
                        var n = materiaNames[String(Number(mid) || 0)] || '';
                        return n !== '' ? n : ('#' + Number(mid));
                    })
                    .filter(function (v) { return String(v).trim() !== ''; });
                if (nomesMat.length > 0) {
                    materiasTxt = ' | matérias: ' + nomesMat.join(', ');
                }
            }
            const materiaUnicaTxt = item.materia_unica ? ' | matérias únicas' : '';
            var groupTxt = '';
            if (item.config && item.config.group_line && item.config.group_line.enabled) {
                var gl = item.config.group_line;
                var glNome = String(gl.label || gl.key || '').trim();
                var glModo = String(gl.mode || 'media').trim();
                groupTxt = ' | grupo: ' + glNome + ' (' + glModo + ')';
            }
            var layoutTxt = '';
            if (item.config && (item.config.layout_group || item.config.layout_type)) {
                layoutTxt = ' | layout: ' + (item.config.layout_group || 'auto') + '/' + (item.config.layout_type || 'auto');
            }
            meta.textContent = 'codigo: ' + (item.codigo || '-') + ' | tipo: ' + tipo + ' | calculo: ' + calc + ' | peso: ' + (item.peso || 1) + blocoTxt + materiaTxt + materiasTxt + materiaUnicaTxt + groupTxt + layoutTxt;

            left.appendChild(nome);
            left.appendChild(meta);

            const right = document.createElement('div');
            right.className = 'flex items-center gap-2 flex-shrink-0';

            const handle = document.createElement('span');
            handle.className = 'bloco-handle text-gray-400 text-lg';
            handle.textContent = '::';

            const editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'px-2 py-1 text-xs rounded bg-gray-100 text-gray-700 hover:bg-gray-200';
            editBtn.textContent = 'Editar';
            editBtn.addEventListener('click', () => openModal(index));

            const delBtn = document.createElement('button');
            delBtn.type = 'button';
            delBtn.className = 'px-2 py-1 text-xs rounded bg-red-50 text-red-700 hover:bg-red-100';
            delBtn.textContent = 'Remover';
            delBtn.addEventListener('click', () => {
                componentes.splice(index, 1);
                renderLista();
            });

            right.appendChild(handle);
            right.appendChild(editBtn);
            right.appendChild(delBtn);

            card.appendChild(left);
            card.appendChild(right);

            card.addEventListener('dragstart', () => card.classList.add('dragging'));
            card.addEventListener('dragend', () => card.classList.remove('dragging'));
            card.addEventListener('dragover', (e) => e.preventDefault());
            card.addEventListener('drop', (e) => {
                e.preventDefault();
                const from = Number(document.querySelector('.bloco-card.dragging')?.dataset.index || -1);
                const to = Number(card.dataset.index || -1);
                if (from < 0 || to < 0 || from === to) {
                    return;
                }
                const [moved] = componentes.splice(from, 1);
                componentes.splice(to, 0, moved);
                renderLista();
            });

            lista.appendChild(card);
        });

        syncJson();
    }

    function sugerirProximoCodigoComponente() {
        var usados = {};
        (componentes || []).forEach(function (c) {
            var cod = String((c && c.codigo) || '').trim();
            if (cod !== '') {
                usados[cod] = true;
            }
        });
        var n = 1;
        while (usados['c' + n]) {
            n++;
        }
        return 'c' + n;
    }

    function resetModal() {
        fields.idx.value = '-1';
        fields.nome.value = '';
        fields.codigo.value = sugerirProximoCodigoComponente();
        fields.source.value = 'provas_sistema';
        fields.calc.value = 'media';
        fields.peso.value = '1';
        fields.escala.value = '10';
        if (fields.layoutGroup) {
            fields.layoutGroup.value = '';
        }
        if (fields.layoutType) {
            fields.layoutType.value = '';
        }
        fields.filtro.value = '';
        setBlocoIdsModalSelection([]);
        fields.materiaId.value = '';
        setSelectedMateriasOnModal([]);
        fields.materiaUnica.checked = false;
        fields.percentual.checked = true;
        fields.obrigatorio.checked = false;
        if (fields.roundModeOverride) {
            fields.roundModeOverride.value = 'herdar';
        }
        fields.modalTitulo.textContent = 'Novo bloco';
        pendingJornadaSel = [];
        jornadasFaixas = defaultJornadasFaixas();
        renderJornadasFaixas();
        if (fields.jornadaDataIni) {
            fields.jornadaDataIni.value = '';
        }
        if (fields.jornadaDataFim) {
            fields.jornadaDataFim.value = '';
        }
        if (fields.jornadaDistribuicaoNotas) {
            fields.jornadaDistribuicaoNotas.value = 'por_materia';
        }
        loadJornadaNotaUnicaExtrasFromConfig({});
        setJornadaIdsModalSelection([]);
        if (fields.expressaoCalculado) {
            fields.expressaoCalculado.value = '';
        }
        if (fields.calculadoTracoAbaixoMinimo) {
            fields.calculadoTracoAbaixoMinimo.checked = false;
        }
        if (fields.jornadaTracoAbaixoMinimo) {
            fields.jornadaTracoAbaixoMinimo.checked = false;
        }
        formulasMateriaModalRows = [];
        if (fields.calcModoGeral) {
            fields.calcModoGeral.checked = true;
        }
        if (fields.calcModoMateria) {
            fields.calcModoMateria.checked = false;
        }
        renderFormulasMateriaModal();
        syncCalculadoModoView();
        if (fields.eventoRegraCodigo) {
            fields.eventoRegraCodigo.value = '';
        }
        if (fields.eventoComponenteCodigo) {
            fillEventoComponentesSelect([], '');
        }
        if (fields.faltasEventoId) {
            fields.faltasEventoId.value = '';
        }
        if (fields.groupEnabled) {
            fields.groupEnabled.checked = false;
        }
        if (fields.groupKey) {
            fields.groupKey.value = '';
        }
        if (fields.groupLabel) {
            fields.groupLabel.value = '';
        }
        if (fields.groupMode) {
            fields.groupMode.value = 'media';
        }
        if (fields.groupDivisor) {
            fields.groupDivisor.value = '';
        }
        setSelectedGroupMateriasOnModal([]);
        toggleGroupLineFields();
        toggleBySource();
    }

    function openModal(index) {
        if (typeof index === 'number' && index >= 0 && componentes[index]) {
            const item = componentes[index];
            const cfg = item.config && typeof item.config === 'object' ? item.config : {};
            pendingJornadaSel = (item.source_type === 'jornadas' && Array.isArray(cfg.jornada_ids))
                ? cfg.jornada_ids.map(function (x) { return parseInt(x, 10); }).filter(function (x) { return x > 0; })
                : [];
            fields.idx.value = String(index);
            fields.nome.value = item.nome || '';
            fields.codigo.value = item.codigo || '';
            if (item.source_type === 'nenhuma') {
                fields.source.value = 'nenhuma';
            } else if (item.source_type === 'jornadas') {
                fields.source.value = 'jornadas';
            } else if (item.source_type === 'evento_boletim') {
                fields.source.value = 'evento_boletim';
            } else if (item.source_type === 'faltas_evento') {
                fields.source.value = 'faltas_evento';
            } else if (item.source_type === 'calculado') {
                fields.source.value = 'calculado';
            } else {
                fields.source.value = 'provas_sistema';
            }
            if (fields.expressaoCalculado) {
                fields.expressaoCalculado.value = (cfg.expressao || '').toString();
            }
            if (fields.calculadoTracoAbaixoMinimo) {
                fields.calculadoTracoAbaixoMinimo.checked = (item.source_type === 'calculado') && !!cfg.traco_abaixo_minimo;
            }
            if (fields.jornadaTracoAbaixoMinimo) {
                fields.jornadaTracoAbaixoMinimo.checked = (item.source_type === 'jornadas') && !!cfg.traco_abaixo_minimo;
            }
            var fmMap = (cfg.formula_materias && typeof cfg.formula_materias === 'object') ? cfg.formula_materias : {};
            formulasMateriaModalRows = [];
            Object.keys(fmMap).forEach(function (mid) {
                var mInt = Number(mid || 0);
                var fExp = String(fmMap[mid] || '').trim();
                if (mInt > 0 && fExp !== '') {
                    formulasMateriaModalRows.push({ materia_id: mInt, formula: fExp });
                }
            });
            if (fields.calcModoMateria && fields.calcModoGeral) {
                var modeCfg = String(cfg.formula_mode || '').toLowerCase().trim();
                if (modeCfg === 'per_materia' || formulasMateriaModalRows.length > 0) {
                    fields.calcModoMateria.checked = true;
                    fields.calcModoGeral.checked = false;
                } else {
                    fields.calcModoMateria.checked = false;
                    fields.calcModoGeral.checked = true;
                }
            }
            renderFormulasMateriaModal();
            syncCalculadoModoView();
            if (fields.eventoRegraCodigo) {
                fields.eventoRegraCodigo.value = (cfg.regra_codigo || '').toString();
                checarCompatibilidadeEventoOrigem();
            }
            if (fields.eventoComponenteCodigo) {
                loadEventoComponentesOptions((cfg.regra_codigo || '').toString(), (cfg.componente_codigo || '').toString());
            }
            if (fields.faltasEventoId) {
                fields.faltasEventoId.value = String(Number(cfg.faltas_evento_id || 0) || '');
            }
            var grp = (cfg.group_line && typeof cfg.group_line === 'object') ? cfg.group_line : {};
            if (fields.groupEnabled) {
                fields.groupEnabled.checked = !!grp.enabled;
            }
            if (fields.groupKey) {
                fields.groupKey.value = String(grp.key || '');
            }
            if (fields.groupLabel) {
                fields.groupLabel.value = String(grp.label || '');
            }
            if (fields.groupMode) {
                fields.groupMode.value = (String(grp.mode || 'media') === 'soma') ? 'soma' : 'media';
            }
            if (fields.groupDivisor) {
                fields.groupDivisor.value = (grp.divisor && Number(grp.divisor) > 0) ? String(Number(grp.divisor)) : '';
            }
            setSelectedGroupMateriasOnModal(Array.isArray(grp.materias_ids) ? grp.materias_ids : []);
            toggleGroupLineFields();
            fields.calc.value = item.calc_type || 'media';
            fields.peso.value = String(item.peso || 1);
            fields.escala.value = String(item.escala_max || 10);
            if (fields.layoutGroup) {
                fields.layoutGroup.value = String(cfg.layout_group || '');
            }
            if (fields.layoutType) {
                fields.layoutType.value = String(cfg.layout_type || '');
            }
            fields.filtro.value = item.filtro_titulo || '';
            let blSel = [];
            if (Array.isArray(item.blocos_ids) && item.blocos_ids.length) {
                blSel = item.blocos_ids.map(function (x) { return parseInt(x, 10); }).filter(function (x) { return x > 0; });
            } else if (Number(item.bloco_id || 0) > 0) {
                blSel = [Number(item.bloco_id)];
            }
            setBlocoIdsModalSelection(blSel);
            fields.materiaId.value = item.materia_id ? String(item.materia_id) : '';
            setSelectedMateriasOnModal(Array.isArray(item.materias_ids) ? item.materias_ids : []);
            fields.materiaUnica.checked = !!item.materia_unica;
            fields.percentual.checked = !!item.usar_percentual;
            fields.obrigatorio.checked = !!item.obrigatorio;
            if (fields.roundModeOverride) {
                var rmOverride = String(cfg.round_mode_override || 'herdar').toLowerCase();
                fields.roundModeOverride.value = (rmOverride === 'none' || rmOverride === 'half') ? rmOverride : 'herdar';
            }
            fields.modalTitulo.textContent = 'Editar bloco';
            if (fields.jornadaDataIni) {
                fields.jornadaDataIni.value = (cfg.data_ini || '').toString().substring(0, 10);
            }
            if (fields.jornadaDataFim) {
                fields.jornadaDataFim.value = (cfg.data_fim || '').toString().substring(0, 10);
            }
            if (fields.jornadaDistribuicaoNotas) {
                var distN = String(cfg.distribuicao_notas || '').trim();
                fields.jornadaDistribuicaoNotas.value = (distN === 'nota_unica_todas_linhas') ? 'nota_unica_todas_linhas' : 'por_materia';
            }
            jornadasFaixas = normalizeJornadasFaixas(cfg.faixas_percentuais);
            renderJornadasFaixas();
            loadJornadaNotaUnicaExtrasFromConfig(cfg);
        } else {
            resetModal();
        }

        toggleBySource();
        updateFiltroBlocoHint();
        modal.classList.remove('hidden');
    }

    function closeModal() {
        modal.classList.add('hidden');
    }

    function toggleBySource() {
        const v = fields.source.value;
        const isJornadas = v === 'jornadas';
        const isEvento = v === 'evento_boletim';
        const isFaltasEvento = v === 'faltas_evento';
        const isCalculado = v === 'calculado';
        const isNenhuma = v === 'nenhuma';
        const hideProvasUi = isJornadas || isEvento || isFaltasEvento || isCalculado || isNenhuma;
        const showMateriaFilter = !hideProvasUi || isFaltasEvento;
        fields.wrapFiltro.style.display = hideProvasUi ? 'none' : 'block';
        fields.wrapBloco.style.display = hideProvasUi ? 'none' : 'block';
        fields.wrapMateria.style.display = showMateriaFilter ? 'block' : 'none';
        fields.wrapMateriaUnica.style.display = hideProvasUi ? 'none' : 'inline-flex';
        if (fields.materiasLabel) {
            fields.materiasLabel.textContent = isFaltasEvento
                ? 'Matérias que aparecem neste bloco de faltas (opcional)'
                : 'Matérias deste bloco (opcional)';
        }
        if (fields.materiasHint) {
            fields.materiasHint.textContent = isFaltasEvento
                ? 'Marque as matérias que devem aparecer na coluna de faltas. Se não marcar nenhuma, usa todas com lançamento no evento.'
                : 'Se não marcar nenhuma, usa todas as matérias no escopo.';
        }
        if (fields.wrapGroupLine) {
            fields.wrapGroupLine.style.display = (isCalculado ? 'none' : 'block');
        }
        if (fields.wrapJornadas) {
            fields.wrapJornadas.classList.toggle('hidden', !isJornadas);
        }
        if (fields.wrapEventoBoletim) {
            fields.wrapEventoBoletim.classList.toggle('hidden', !isEvento);
        }
        if (fields.wrapFaltasEvento) {
            fields.wrapFaltasEvento.classList.toggle('hidden', !isFaltasEvento);
        }
        if (fields.wrapCalculado) {
            fields.wrapCalculado.classList.toggle('hidden', !isCalculado);
        }
        if (isCalculado && fields.jornadaTracoAbaixoMinimo) {
            fields.jornadaTracoAbaixoMinimo.checked = false;
        }
        if (isJornadas && fields.calculadoTracoAbaixoMinimo) {
            fields.calculadoTracoAbaixoMinimo.checked = false;
        }
        syncCalculadoModoView();
        var jornadasNotaUnicaLinhas = isJornadas && fields.jornadaDistribuicaoNotas
            && fields.jornadaDistribuicaoNotas.value === 'nota_unica_todas_linhas';
        fields.calc.disabled = isCalculado || isNenhuma || jornadasNotaUnicaLinhas;
        fields.percentual.disabled = isEvento || isFaltasEvento || isCalculado || isNenhuma;
        if (fields.hintPercentualJornadas) {
            fields.hintPercentualJornadas.classList.toggle('hidden', !isJornadas);
        }
        if (fields.wrapJornadasFaixas) {
            fields.wrapJornadasFaixas.classList.toggle('hidden', !isJornadas);
        }
        if (fields.wrapJornadaNotaUnicaExtras) {
            fields.wrapJornadaNotaUnicaExtras.classList.toggle('hidden', !jornadasNotaUnicaLinhas);
        }
        if (fields.percentualLabel) {
            fields.percentualLabel.textContent = isJornadas
                ? 'Usar nota proporcional ao % de jornadas concluídas'
                : 'Calcular por acertos/questões';
        }
        if (fields.blocoIds) {
            fields.blocoIds.disabled = hideProvasUi;
        }
        fields.materiaId.disabled = !showMateriaFilter;
        fields.materiaUnica.disabled = hideProvasUi;
        if (isJornadas) {
            if (fields.idx.value === '-1') {
                fields.percentual.checked = true;
            }
            fields.filtro.value = '';
            setBlocoIdsModalSelection([]);
            fields.materiaId.value = '';
            setSelectedMateriasOnModal([]);
            fields.materiaUnica.checked = false;
            if (fields.expressaoCalculado) {
                fields.expressaoCalculado.value = '';
            }
            loadJornadasOptionsIfNeeded(function () {
                setJornadaIdsModalSelection(pendingJornadaSel);
            });
            if (fields.idx.value === '-1') {
                loadJornadaNotaUnicaExtrasFromConfig({});
            }
        } else if (isEvento) {
            fields.percentual.checked = false;
            fields.filtro.value = '';
            setBlocoIdsModalSelection([]);
            fields.materiaId.value = '';
            setSelectedMateriasOnModal([]);
            fields.materiaUnica.checked = false;
            if (fields.expressaoCalculado) {
                fields.expressaoCalculado.value = '';
            }
            if (fields.jornadaIds) {
                setJornadaIdsModalSelection([]);
            }
            if (fields.eventoRegraCodigo) {
                loadEventoComponentesOptions(fields.eventoRegraCodigo.value || '', fields.eventoComponenteCodigo ? fields.eventoComponenteCodigo.value : '');
            }
        } else if (isFaltasEvento) {
            fields.percentual.checked = false;
            fields.filtro.value = '';
            setBlocoIdsModalSelection([]);
            fields.materiaId.value = '';
            fields.materiaUnica.checked = false;
            if (fields.expressaoCalculado) {
                fields.expressaoCalculado.value = '';
            }
            if (fields.jornadaIds) {
                setJornadaIdsModalSelection([]);
            }
        } else if (isCalculado) {
            fields.percentual.checked = false;
            fields.filtro.value = '';
            setBlocoIdsModalSelection([]);
            fields.materiaId.value = '';
            setSelectedMateriasOnModal([]);
            fields.materiaUnica.checked = false;
            if (fields.jornadaIds) {
                setJornadaIdsModalSelection([]);
            }
        } else if (fields.jornadaIds) {
            setJornadaIdsModalSelection([]);
        }
        updateFiltroBlocoHint();
    }

    btnAdd.addEventListener('click', () => openModal(-1));

    modal.querySelectorAll('[data-close-modal="1"]').forEach((el) => {
        el.addEventListener('click', closeModal);
    });

    fields.source.addEventListener('change', toggleBySource);
    if (fields.jornadaDistribuicaoNotas) {
        fields.jornadaDistribuicaoNotas.addEventListener('change', toggleBySource);
    }
    function checarCompatibilidadeEventoOrigem() {
        var aviso = document.getElementById('evento-regra-codigo-aviso');
        var avisoTexto = document.getElementById('evento-regra-codigo-aviso-texto');
        if (!fields.eventoRegraCodigo || !aviso || !avisoTexto) {
            return;
        }
        var opt = fields.eventoRegraCodigo.options[fields.eventoRegraCodigo.selectedIndex];
        if (!opt || !opt.value) {
            aviso.classList.add('hidden');
            return;
        }
        var bimOrigem = parseInt(opt.getAttribute('data-bimestre') || '0', 10);
        var seriesOrigem = [];
        try {
            seriesOrigem = JSON.parse(opt.getAttribute('data-series-ids') || '[]');
        } catch (e) {
            seriesOrigem = [];
        }
        var motivos = [];
        if (REGRA_ATUAL_BIMESTRE > 0 && bimOrigem > 0 && bimOrigem !== REGRA_ATUAL_BIMESTRE) {
            motivos.push('o evento de origem é do ' + bimOrigem + 'º bimestre, diferente do bimestre deste evento (' + REGRA_ATUAL_BIMESTRE + 'º)');
        }
        if (REGRA_ATUAL_SERIES_IDS.length > 0 && seriesOrigem.length > 0) {
            var temInterseccao = seriesOrigem.some(function (sid) { return REGRA_ATUAL_SERIES_IDS.indexOf(sid) !== -1; });
            if (!temInterseccao) {
                motivos.push('o evento de origem não cobre as mesmas séries deste evento');
            }
        }
        if (motivos.length > 0) {
            avisoTexto.textContent = 'Atenção: ' + motivos.join('; ') + '. Confirme se é mesmo este o evento que você quer usar.';
            aviso.classList.remove('hidden');
        } else {
            aviso.classList.add('hidden');
        }
    }
    if (fields.eventoRegraCodigo) {
        fields.eventoRegraCodigo.addEventListener('change', function () {
            loadEventoComponentesOptions(fields.eventoRegraCodigo.value || '', '');
            checarCompatibilidadeEventoOrigem();
        });
    }
    if (fields.jornadaDataIni) {
        fields.jornadaDataIni.addEventListener('change', function () {
            jornadasOptionsCacheKey = '';
            if (fields.source.value === 'jornadas') {
                loadJornadasOptionsIfNeeded(function () {
                    setJornadaIdsModalSelection(pendingJornadaSel);
                });
            }
        });
    }
    if (fields.jornadaDataFim) {
        fields.jornadaDataFim.addEventListener('change', function () {
            jornadasOptionsCacheKey = '';
            if (fields.source.value === 'jornadas') {
                loadJornadasOptionsIfNeeded(function () {
                    setJornadaIdsModalSelection(pendingJornadaSel);
                });
            }
        });
    }
    fields.percentual.addEventListener('change', function () {
        toggleBySource();
    });
    if (fields.groupEnabled) {
        fields.groupEnabled.addEventListener('change', toggleGroupLineFields);
    }
    if (fields.blocoIds) {
        fields.blocoIds.addEventListener('change', function () {
            if (getSelectedBlocoIdsFromModal().length > 0 && fields.source.value === 'provas_sistema') {
                fields.materiaUnica.checked = true;
            }
            updateFiltroBlocoHint();
        });
    }
    var btnBlocosLimpar = document.getElementById('btn-blocos-limpar');
    if (btnBlocosLimpar && fields.blocoIds) {
        btnBlocosLimpar.addEventListener('click', function () {
            setBlocoIdsModalSelection([]);
        });
    }
    var btnBlocosSelecionarFiltrados = document.getElementById('btn-blocos-selecionar-filtrados');
    if (btnBlocosSelecionarFiltrados && fields.blocoIds) {
        btnBlocosSelecionarFiltrados.addEventListener('click', function () {
            selecionarBlocosFiltrados();
        });
    }
    document.querySelectorAll('.bloco-tabela-row').forEach(function (row) {
        row.addEventListener('click', function (e) {
            if (e.target && e.target.classList.contains('bloco-tabela-checkbox')) {
                return;
            }
            toggleBlocoTabelaRow(parseInt(row.dataset.blocoId, 10));
        });
    });
    document.querySelectorAll('.bloco-tabela-checkbox').forEach(function (cb) {
        cb.addEventListener('change', function () {
            toggleBlocoTabelaRow(parseInt(cb.value, 10));
        });
    });
    ['bloco-tabela-filtro-titulo', 'bloco-tabela-filtro-tipo', 'bloco-tabela-filtro-bimestre', 'bloco-tabela-filtro-ano'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', aplicarFiltrosBlocoTabela);
            el.addEventListener('change', aplicarFiltrosBlocoTabela);
        }
    });
    var btnBlocoTabelaLimparFiltros = document.getElementById('btn-bloco-tabela-limpar-filtros');
    if (btnBlocoTabelaLimparFiltros) {
        btnBlocoTabelaLimparFiltros.addEventListener('click', function () {
            var t = document.getElementById('bloco-tabela-filtro-titulo');
            var tp = document.getElementById('bloco-tabela-filtro-tipo');
            var b = document.getElementById('bloco-tabela-filtro-bimestre');
            var a = document.getElementById('bloco-tabela-filtro-ano');
            if (t) { t.value = ''; }
            if (tp) { tp.value = ''; }
            if (b) { b.value = ''; }
            if (a) { a.value = ''; }
            aplicarFiltrosBlocoTabela();
        });
    }
    syncBlocoTabelaFromSelect();
    var btnJornadasMarcarTodas = document.getElementById('btn-jornadas-marcar-todas');
    if (btnJornadasMarcarTodas && fields.jornadaIds) {
        btnJornadasMarcarTodas.addEventListener('click', function () {
            loadJornadasOptionsIfNeeded(function () {
                Array.from(fields.jornadaIds.options).forEach(function (o) {
                    var v = parseInt(o.value, 10);
                    o.selected = v > 0;
                });
                syncJornadaTabelaFromSelect();
            });
        });
    }
    var btnJornadasLimpar = document.getElementById('btn-jornadas-limpar');
    if (btnJornadasLimpar && fields.jornadaIds) {
        btnJornadasLimpar.addEventListener('click', function () {
            setJornadaIdsModalSelection([]);
        });
    }
    var btnJornadasSelecionarFiltradas = document.getElementById('btn-jornadas-selecionar-filtradas');
    if (btnJornadasSelecionarFiltradas && fields.jornadaIds) {
        btnJornadasSelecionarFiltradas.addEventListener('click', function () {
            selecionarJornadasFiltradas();
        });
    }
    var jornadaTabelaCorpo = document.getElementById('jornada-tabela-corpo');
    if (jornadaTabelaCorpo) {
        jornadaTabelaCorpo.addEventListener('click', function (e) {
            var checkbox = e.target.closest('.jornada-tabela-checkbox');
            if (checkbox) {
                toggleJornadaTabelaRow(parseInt(checkbox.value, 10));
                return;
            }
            var row = e.target.closest('.jornada-tabela-row');
            if (row) {
                toggleJornadaTabelaRow(parseInt(row.dataset.jornadaId, 10));
            }
        });
    }
    ['jornada-tabela-filtro-titulo', 'jornada-tabela-filtro-materia', 'jornada-tabela-filtro-bimestre', 'jornada-tabela-filtro-ano'].forEach(function (id) {
        var el = document.getElementById(id);
        if (el) {
            el.addEventListener('input', aplicarFiltrosJornadaTabela);
            el.addEventListener('change', aplicarFiltrosJornadaTabela);
        }
    });
    var btnJornadaTabelaLimparFiltros = document.getElementById('btn-jornada-tabela-limpar-filtros');
    if (btnJornadaTabelaLimparFiltros) {
        btnJornadaTabelaLimparFiltros.addEventListener('click', function () {
            var t = document.getElementById('jornada-tabela-filtro-titulo');
            var m = document.getElementById('jornada-tabela-filtro-materia');
            var b = document.getElementById('jornada-tabela-filtro-bimestre');
            var a = document.getElementById('jornada-tabela-filtro-ano');
            if (t) { t.value = ''; }
            if (m) { m.value = ''; }
            if (b) { b.value = ''; }
            if (a) { a.value = ''; }
            aplicarFiltrosJornadaTabela();
        });
    }
    if (fields.btnJornadaFonteMateriaAdd) {
        fields.btnJornadaFonteMateriaAdd.addEventListener('click', function () {
            jornadaFontePorMateriaRows.push({ materia_id: 0, fonte_ids: [] });
            renderJornadaNotaUnicaFonteRows();
        });
    }
    if (fields.btnJornadaFonteGrupoAdd) {
        fields.btnJornadaFonteGrupoAdd.addEventListener('click', function () {
            jornadaFontePorGrupoRows.push({ grupo_key: '', fonte_ids: [] });
            renderJornadaNotaUnicaFonteRows();
        });
    }
    var btnJnMatTodas = document.getElementById('btn-jornada-nota-unica-materias-todas');
    var btnJnMatNenhuma = document.getElementById('btn-jornada-nota-unica-materias-nenhuma');
    if (btnJnMatTodas) {
        btnJnMatTodas.addEventListener('click', function () {
            setJornadaNotaUnicaMateriasCheckboxesAll(true);
        });
    }
    if (btnJnMatNenhuma) {
        btnJnMatNenhuma.addEventListener('click', function () {
            setJornadaNotaUnicaMateriasCheckboxesAll(false);
        });
    }

    if (fields.calcModoGeral) {
        fields.calcModoGeral.addEventListener('change', syncCalculadoModoView);
    }
    if (fields.calcModoMateria) {
        fields.calcModoMateria.addEventListener('change', function () {
            if (fields.calcModoMateria.checked && formulasMateriaModalRows.length === 0) {
                formulasMateriaModalRows.push({ materia_id: 0, formula: '' });
                renderFormulasMateriaModal();
            }
            syncCalculadoModoView();
        });
    }
    if (fields.btnAddFormulaMateriaModal) {
        fields.btnAddFormulaMateriaModal.addEventListener('click', function () {
            formulasMateriaModalRows.push({ materia_id: 0, formula: '' });
            renderFormulasMateriaModal();
        });
    }

    formModal.addEventListener('submit', function (e) {
        e.preventDefault();

        const nome = (fields.nome.value || '').trim();
        if (!nome) {
            alert('Informe o nome do bloco.');
            return;
        }

        let codigo = (fields.codigo.value || '').trim();
        if (!codigo) {
            codigo = slugify(nome);
        }

        const selBlocos = getSelectedBlocoIdsFromModal();
        const srcVal = fields.source.value;
        var stType = srcVal === 'nenhuma'
            ? 'nenhuma'
            : (srcVal === 'jornadas'
                ? 'jornadas'
                : (srcVal === 'evento_boletim'
                    ? 'evento_boletim'
                    : (srcVal === 'faltas_evento'
                        ? 'faltas_evento'
                        : (srcVal === 'calculado' ? 'calculado' : 'provas_sistema'))));
        const payload = {
            codigo,
            nome,
            source_type: stType,
            calc_type: fields.calc.value || 'media',
            peso: Number(fields.peso.value || 1),
            filtro_titulo: fields.filtro.value || '',
            blocos_ids: selBlocos,
            bloco_id: selBlocos.length === 1 ? selBlocos[0] : 0,
            materia_id: Number(fields.materiaId.value || 0),
            materias_ids: getSelectedMateriasFromModal(),
            materia_unica: fields.materiaUnica.checked,
            usar_percentual: fields.percentual.checked,
            escala_max: Number(fields.escala.value || 10),
            obrigatorio: fields.obrigatorio.checked
        };
        var layoutGroupVal = fields.layoutGroup ? String(fields.layoutGroup.value || '').trim() : '';
        var layoutTypeVal = fields.layoutType ? String(fields.layoutType.value || '').trim() : '';
        var groupLinePayload = null;
        if (fields.groupEnabled && fields.groupEnabled.checked) {
            var gMats = getSelectedGroupMateriasFromModal();
            var gKey = fields.groupKey ? String(fields.groupKey.value || '').trim() : '';
            var gLabel = fields.groupLabel ? String(fields.groupLabel.value || '').trim() : '';
            var gMode = fields.groupMode ? String(fields.groupMode.value || 'media').trim() : 'media';
            var gDiv = fields.groupDivisor ? Number(fields.groupDivisor.value || 0) : 0;
            if (!gKey) {
                gKey = slugify(gLabel);
            }
            if (!gLabel) {
                gLabel = gKey;
            }
            if (!gKey || gMats.length === 0) {
                alert('No agrupamento em linha única, informe código/nome e selecione as matérias do grupo.');
                return;
            }
            if (gMode !== 'soma') {
                gMode = 'media';
            }
            groupLinePayload = {
                enabled: true,
                key: gKey,
                label: gLabel,
                mode: gMode,
                divisor: gDiv > 0 ? gDiv : 0,
                materias_ids: gMats
            };
        }
        if (payload.source_type === 'jornadas') {
            var distJn = (fields.jornadaDistribuicaoNotas && fields.jornadaDistribuicaoNotas.value)
                ? String(fields.jornadaDistribuicaoNotas.value).trim()
                : 'por_materia';
            if (distJn !== 'nota_unica_todas_linhas') {
                distJn = 'por_materia';
            }
            payload.config = {
                jornada_ids: getSelectedJornadaIdsFromModal(),
                data_ini: (fields.jornadaDataIni && fields.jornadaDataIni.value) ? fields.jornadaDataIni.value.trim() : '',
                data_fim: (fields.jornadaDataFim && fields.jornadaDataFim.value) ? fields.jornadaDataFim.value.trim() : '',
                faixas_percentuais: getJornadasFaixasFromModal(),
                distribuicao_notas: distJn,
                traco_abaixo_minimo: !!(fields.jornadaTracoAbaixoMinimo && fields.jornadaTracoAbaixoMinimo.checked),
                group_line: groupLinePayload,
                layout_group: layoutGroupVal,
                layout_type: layoutTypeVal
            };
            if (distJn === 'nota_unica_todas_linhas') {
                payload.config.nota_unica_omitir_materias = getJornadaNotaUnicaOmitirFromCheckboxes();
                var fpMat = {};
                jornadaFontePorMateriaRows.forEach(function (r) {
                    var tid = Number(r.materia_id || 0);
                    var fids = (r.fonte_ids || []).map(function (x) { return Number(x); }).filter(function (x) { return x > 0; });
                    if (tid > 0 && fids.length > 0) {
                        fpMat[String(tid)] = fids;
                    }
                });
                if (Object.keys(fpMat).length > 0) {
                    payload.config.nota_unica_fonte_por_materia = fpMat;
                }
                var fpGrp = {};
                jornadaFontePorGrupoRows.forEach(function (r) {
                    var gk = String(r.grupo_key || '').trim();
                    var fids = (r.fonte_ids || []).map(function (x) { return Number(x); }).filter(function (x) { return x > 0; });
                    if (gk !== '' && fids.length > 0) {
                        fpGrp[gk] = fids;
                    }
                });
                if (Object.keys(fpGrp).length > 0) {
                    payload.config.nota_unica_fonte_por_grupo = fpGrp;
                }
            }
        } else if (payload.source_type === 'evento_boletim') {
            payload.config = {
                regra_codigo: fields.eventoRegraCodigo ? String(fields.eventoRegraCodigo.value || '').trim() : '',
                componente_codigo: fields.eventoComponenteCodigo ? String(fields.eventoComponenteCodigo.value || '').trim() : '',
                group_line: groupLinePayload,
                layout_group: layoutGroupVal,
                layout_type: layoutTypeVal
            };
            if (!payload.config.regra_codigo) {
                alert('Informe o código (slug) do evento de boletim.');
                return;
            }
            payload.filtro_titulo = '';
            payload.blocos_ids = [];
            payload.bloco_id = 0;
            payload.materia_id = 0;
            payload.materia_unica = false;
            payload.usar_percentual = false;
        } else if (payload.source_type === 'faltas_evento') {
            payload.config = {
                faltas_evento_id: fields.faltasEventoId ? Number(fields.faltasEventoId.value || 0) : 0,
                group_line: groupLinePayload,
                layout_group: layoutGroupVal,
                layout_type: layoutTypeVal
            };
            if (!payload.config.faltas_evento_id) {
                alert('Selecione o evento de faltas.');
                return;
            }
            payload.filtro_titulo = '';
            payload.blocos_ids = [];
            payload.bloco_id = 0;
            payload.materia_id = 0;
            payload.materia_unica = false;
            payload.usar_percentual = false;
        } else if (payload.source_type === 'calculado') {
            var exCalc = fields.expressaoCalculado ? String(fields.expressaoCalculado.value || '').trim() : '';
            var useFormulaByMateria = !!(fields.calcModoMateria && fields.calcModoMateria.checked);
            var fmCalc = {};
            if (useFormulaByMateria) {
                formulasMateriaModalRows.forEach(function (row) {
                    var mid = Number(row.materia_id || 0);
                    var fexp = String(row.formula || '').trim();
                    if (mid > 0 && fexp !== '') {
                        fmCalc[mid] = fexp;
                    }
                });
            }
            payload.config = {
                expressao: exCalc,
                formula_materias: fmCalc,
                formula_mode: useFormulaByMateria ? 'per_materia' : 'single',
                traco_abaixo_minimo: !!(fields.calculadoTracoAbaixoMinimo && fields.calculadoTracoAbaixoMinimo.checked),
                jornada_ids: [],
                data_ini: '',
                data_fim: '',
                group_line: groupLinePayload,
                layout_group: layoutGroupVal,
                layout_type: layoutTypeVal
            };
            payload.filtro_titulo = '';
            payload.blocos_ids = [];
            payload.bloco_id = 0;
            payload.materia_id = 0;
            payload.materias_ids = [];
            payload.materia_unica = false;
            payload.usar_percentual = false;
            if (Object.keys(fmCalc).length === 0 && !exCalc) {
                alert('Informe a expressão geral ou cadastre expressão por matéria.');
                return;
            }
        } else if (groupLinePayload) {
            payload.config = {
                group_line: groupLinePayload,
                layout_group: layoutGroupVal,
                layout_type: layoutTypeVal
            };
        } else if (layoutGroupVal || layoutTypeVal) {
            payload.config = {
                layout_group: layoutGroupVal,
                layout_type: layoutTypeVal
            };
        }
        var roundModeOverrideVal = fields.roundModeOverride ? String(fields.roundModeOverride.value || 'herdar').trim() : 'herdar';
        if (roundModeOverrideVal === 'none' || roundModeOverrideVal === 'half') {
            if (!payload.config) {
                payload.config = {};
            }
            payload.config.round_mode_override = roundModeOverrideVal;
        }

        const editIndex = Number(fields.idx.value || -1);
        if (editIndex >= 0 && componentes[editIndex]) {
            const merged = Object.assign({}, componentes[editIndex], payload);
            if (merged.source_type === 'nenhuma') {
                merged.filtro_titulo = '';
                merged.blocos_ids = [];
                merged.bloco_id = 0;
                merged.materia_id = 0;
                merged.materias_ids = [];
                merged.usar_percentual = false;
                if (payload.config && (payload.config.group_line || payload.config.layout_group || payload.config.layout_type)) {
                    merged.config = payload.config;
                } else {
                    delete merged.config;
                }
            } else if (merged.source_type === 'jornadas') {
                /* config já veio no payload */
            } else if (merged.source_type === 'evento_boletim') {
                merged.config = payload.config;
            } else if (merged.source_type === 'faltas_evento') {
                merged.config = payload.config;
            } else if (merged.source_type === 'calculado') {
                merged.config = payload.config;
            } else if (payload.config && (payload.config.group_line || payload.config.layout_group || payload.config.layout_type)) {
                merged.config = payload.config;
            } else {
                delete merged.config;
            }
            componentes[editIndex] = merged;
        } else {
            if (payload.source_type === 'nenhuma') {
                payload.filtro_titulo = '';
                payload.blocos_ids = [];
                payload.bloco_id = 0;
                payload.materia_id = 0;
                payload.materias_ids = [];
                payload.usar_percentual = false;
                if (!(payload.config && (payload.config.group_line || payload.config.layout_group || payload.config.layout_type))) {
                    delete payload.config;
                }
            } else if (payload.source_type !== 'jornadas' && payload.source_type !== 'evento_boletim' && payload.source_type !== 'faltas_evento' && payload.source_type !== 'calculado' && !(payload.config && (payload.config.group_line || payload.config.layout_group || payload.config.layout_type))) {
                delete payload.config;
            }
            componentes.push(payload);
        }

        renderLista();
        closeModal();
    });

    formRegra.addEventListener('submit', function (e) {
        if (componentes.length === 0) {
            e.preventDefault();
            alert('Adicione pelo menos um bloco para salvar a regra.');
            return;
        }
        var seenCodes = {};
        for (var i = 0; i < componentes.length; i++) {
            var c = componentes[i] || {};
            var code = String(c.codigo || '').trim().toLowerCase();
            if (!code) {
                e.preventDefault();
                alert('Existe bloco sem código. Edite o bloco e preencha o campo código.');
                return;
            }
            if (seenCodes[code]) {
                e.preventDefault();
                alert('Existem blocos com código repetido: "' + code + '". Cada bloco precisa de código único.');
                return;
            }
            seenCodes[code] = true;
        }
        syncFormulaMateriasJson();
        syncJson();
        const submitBtn = document.getElementById('btn-salvar-regra-boletim');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.textContent = 'Salvando...';
        }
    });

    if (btnAddFormulaMateria) {
        btnAddFormulaMateria.addEventListener('click', function () {
            formulasMateriaRows.push({ materia_id: 0, formula: '' });
            renderFormulaMaterias();
        });
    }

    if (formulaMateriasInicial && typeof formulaMateriasInicial === 'object') {
        Object.keys(formulaMateriasInicial).forEach(function (mid) {
            var v = String(formulaMateriasInicial[mid] || '').trim();
            var midInt = Number(mid || 0);
            if (midInt > 0 && v !== '') {
                formulasMateriaRows.push({ materia_id: midInt, formula: v });
            }
        });
    }
    renderFormulaMaterias();
    renderLista();

    window.boletimRenderComponentes = function (novos) {
        if (!Array.isArray(novos)) return;
        componentes = novos;
        renderLista();
        syncJson();
    };

    // Mantém sessão ativa enquanto coordenação passa tempo montando o evento.
    const keepaliveUrl = <?= json_encode(URL . '/admin/boletim-configuracao/keepalive', JSON_UNESCAPED_SLASHES) ?>;
    function boletimKeepalive() {
        fetch(keepaliveUrl, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'no-store'
        }).catch(function () {});
    }
    setInterval(boletimKeepalive, 2 * 60 * 1000);

    var btnChecklist = document.getElementById('btn-checklist-pre-geracao');
    if (btnChecklist) {
        btnChecklist.addEventListener('click', function () {
            var resultadoBox = document.getElementById('checklist-resultado');
            btnChecklist.disabled = true;
            btnChecklist.textContent = 'Verificando...';
            var qs = 'regra_id=' + encodeURIComponent(REGRA_ATUAL_ID)
                + '&periodo_ref=' + encodeURIComponent(<?= json_encode($periodoRef) ?>)
                + '&data_inicio=' + encodeURIComponent(<?= json_encode($dataInicio) ?>)
                + '&data_fim=' + encodeURIComponent(<?= json_encode($dataFim) ?>);
            fetch(CHECKLIST_PRE_GERACAO_URL + '?' + qs, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderChecklistResultado(data, resultadoBox);
                })
                .catch(function () {
                    resultadoBox.classList.remove('hidden');
                    resultadoBox.innerHTML = '<p class="text-red-700">Erro ao verificar. Tente novamente.</p>';
                })
                .finally(function () {
                    btnChecklist.disabled = false;
                    btnChecklist.textContent = 'Verificar agora';
                });
        });
    }

    function renderChecklistResultado(data, box) {
        if (!box) { return; }
        box.classList.remove('hidden');
        if (data.erro) {
            box.innerHTML = '<p class="text-red-700">' + data.erro + '</p>';
            return;
        }
        var html = '';
        var orfas = data.materias_orfas || [];
        var incompat = data.eventos_incompativeis || [];
        var cobertura = data.cobertura || { total: 0, completos: 0, incompletos: [] };

        if (orfas.length === 0 && incompat.length === 0) {
            html += '<p class="text-emerald-700"><i class="fa-solid fa-circle-check"></i> Nenhuma matéria órfã nem evento de origem incompatível encontrado.</p>';
        }
        if (orfas.length > 0) {
            html += '<div class="text-amber-800"><strong>⚠️ ' + orfas.length + ' matéria(s) ausente(s) em algum bloco:</strong><ul class="list-disc pl-5 mt-1">';
            orfas.slice(0, 10).forEach(function (o) {
                html += '<li>' + o.materia_nome + ' não está na lista do bloco "' + o.componente_nome + '" (' + o.componente_codigo + ')</li>';
            });
            html += '</ul></div>';
        }
        if (incompat.length > 0) {
            html += '<div class="text-red-800"><strong>⚠️ ' + incompat.length + ' bloco(s) com evento de origem incompatível:</strong><ul class="list-disc pl-5 mt-1">';
            incompat.forEach(function (i) {
                html += '<li>Bloco "' + i.componente_nome + '" usa o evento "' + i.evento_origem_nome + '" (' + i.evento_origem_codigo + '), que ' + i.motivo + '</li>';
            });
            html += '</ul></div>';
        }
        html += '<p class="text-gray-700 mt-2"><strong>Cobertura:</strong> ' + cobertura.completos + ' de ' + cobertura.total + ' aluno(s) testado(s) com todos os componentes preenchidos.</p>';
        if ((cobertura.incompletos || []).length > 0) {
            html += '<details class="mt-1"><summary class="cursor-pointer text-gray-600">Ver alunos com lacunas (' + cobertura.incompletos.length + ')</summary><ul class="list-disc pl-5 mt-1 text-gray-700">';
            cobertura.incompletos.slice(0, 20).forEach(function (a) {
                html += '<li>' + a.nome + ' — ' + a.lacunas + ' componente(s) vazio(s)</li>';
            });
            html += '</ul></details>';
        }
        box.innerHTML = html;
    }

    var btnSimularLote = document.getElementById('btn-simular-lote');
    if (btnSimularLote) {
        btnSimularLote.addEventListener('click', function () {
            var loteBox = document.getElementById('lote-resultado');
            var turmaSelect = document.getElementById('lote-turma-id');
            btnSimularLote.disabled = true;
            btnSimularLote.textContent = 'Simulando...';
            var body = new URLSearchParams();
            body.set('_token', <?= json_encode($csrfToken) ?>);
            body.set('regra_id', String(REGRA_ATUAL_ID));
            body.set('turma_id', turmaSelect ? turmaSelect.value : '');
            body.set('periodo_ref', <?= json_encode($periodoRef) ?>);
            body.set('data_inicio', <?= json_encode($dataInicio) ?>);
            body.set('data_fim', <?= json_encode($dataFim) ?>);
            fetch(SIMULAR_LOTE_URL, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    renderLoteResultado(data, loteBox);
                })
                .catch(function () {
                    loteBox.classList.remove('hidden');
                    loteBox.innerHTML = '<p class="p-3 text-red-700">Erro ao simular. Tente novamente.</p>';
                })
                .finally(function () {
                    btnSimularLote.disabled = false;
                    btnSimularLote.textContent = 'Simular lote';
                });
        });
    }

    function renderLoteResultado(data, box) {
        if (!box) { return; }
        box.classList.remove('hidden');
        var alunosLote = data.alunos || [];
        if (alunosLote.length === 0) {
            box.innerHTML = '<p class="p-3 text-gray-500">Nenhum aluno encontrado para simular.</p>';
            return;
        }
        var html = '<table class="min-w-full text-sm"><thead class="bg-gray-50 sticky top-0"><tr>'
            + '<th class="px-3 py-2 text-left font-medium text-gray-500">Aluno</th>'
            + '<th class="px-3 py-2 text-left font-medium text-gray-500">Nota final (média)</th>'
            + '<th class="px-3 py-2 text-left font-medium text-gray-500">Status</th>'
            + '</tr></thead><tbody class="divide-y divide-gray-100">';
        alunosLote.forEach(function (a) {
            var status = a.erro
                ? '<span class="text-red-700">Erro: ' + a.erro + '</span>'
                : (a.tem_lacuna ? '<span class="text-amber-700">Tem componente vazio</span>' : '<span class="text-emerald-700">Completo</span>');
            html += '<tr><td class="px-3 py-2 text-gray-900">' + a.nome + '</td>'
                + '<td class="px-3 py-2 text-gray-700">' + (a.media_final !== null && a.media_final !== undefined ? a.media_final : '—') + '</td>'
                + '<td class="px-3 py-2">' + status + '</td></tr>';
        });
        html += '</tbody></table>';
        box.innerHTML = html;
    }

    var btnVerLogs = document.getElementById('btn-ver-logs-geracao');
    if (btnVerLogs) {
        btnVerLogs.addEventListener('click', function () {
            var logsBox = document.getElementById('logs-geracao-resultado');
            fetch(LOGS_GERACAO_URL + '?regra_id=' + encodeURIComponent(REGRA_ATUAL_ID), { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    logsBox.classList.remove('hidden');
                    var logs = data.logs || [];
                    if (logs.length === 0) {
                        logsBox.innerHTML = '<p class="text-gray-500">Nenhuma geração registrada ainda.</p>';
                        return;
                    }
                    var html = '';
                    logs.forEach(function (l) {
                        html += '<div class="border-b border-gray-100 pb-1.5">'
                            + '<span class="text-gray-900 font-medium">' + (l.created_at_fmt || '') + '</span>'
                            + ' — ' + (l.usuario_nome || 'usuário desconhecido')
                            + ' · ' + l.alunos_processados + ' aluno(s), ' + l.linhas_geradas + ' linha(s)'
                            + (parseInt(l.erros, 10) > 0 ? ', <span class="text-red-700">' + l.erros + ' erro(s)</span>' : '')
                            + (parseInt(l.alunos_mudanca_significativa, 10) > 0 ? ', <span class="text-amber-700">' + l.alunos_mudanca_significativa + ' aluno(s) com mudança grande</span>' : '')
                            + '</div>';
                    });
                    logsBox.innerHTML = html;
                })
                .catch(function () {
                    logsBox.classList.remove('hidden');
                    logsBox.innerHTML = '<p class="text-red-700">Erro ao carregar.</p>';
                });
        });
    }

    // Edição inline das células calculadas na tabela "Notas por matéria":
    // sobrescreve a fórmula só naquela matéria, só para o aluno selecionado.
    (function () {
        var csrfTokenCell = <?= json_encode($csrfToken ?? '', JSON_UNESCAPED_UNICODE) ?>;
        var ajaxUrlCell = <?= json_encode(URL . '/admin/boletim-configuracao/nota-manual-materia-ajax', JSON_UNESCAPED_SLASHES) ?>;

        function parseValorAtual(td) {
            var txt = (td.querySelector('.boletim-cell-valor') || {}).textContent || '';
            txt = txt.trim().replace(/\./g, '').replace(',', '.');
            var n = parseFloat(txt);
            return isNaN(n) ? '' : n;
        }

        function enterEditMode(td) {
            if (td.querySelector('.boletim-cell-edit-form')) {
                return;
            }
            var valorAtual = parseValorAtual(td);
            var escalaMax = td.getAttribute('data-escala-max') || '10';
            var wrap = document.createElement('div');
            wrap.className = 'boletim-cell-edit-form flex items-center gap-1 justify-center';
            wrap.innerHTML = ''
                + '<input type="number" step="0.01" min="0" max="' + escalaMax + '" value="' + valorAtual + '" '
                + 'class="w-16 px-1 py-0.5 border border-indigo-300 rounded text-center text-sm" />'
                + '<button type="button" class="btn-ok text-emerald-600 hover:text-emerald-800" title="Salvar"><i class="fa-solid fa-check"></i></button>'
                + '<button type="button" class="btn-cancel text-gray-400 hover:text-gray-600" title="Cancelar"><i class="fa-solid fa-xmark"></i></button>'
                + '<button type="button" class="btn-clear text-amber-600 hover:text-amber-800" title="Remover sobrescrita (voltar a usar a fórmula)"><i class="fa-solid fa-rotate-left"></i></button>';
            td.dataset.originalHtml = td.innerHTML;
            td.innerHTML = '';
            td.appendChild(wrap);
            var input = wrap.querySelector('input');
            input.focus();
            input.select();

            function close() {
                td.innerHTML = td.dataset.originalHtml;
                delete td.dataset.originalHtml;
            }

            function postCell(payload) {
                var fd = new FormData();
                fd.append('_token', csrfTokenCell);
                fd.append('regra_id', td.getAttribute('data-regra-id'));
                fd.append('componente_id', td.getAttribute('data-componente-id'));
                fd.append('aluno_id', td.getAttribute('data-aluno-id'));
                fd.append('materia_id', td.getAttribute('data-materia-id'));
                fd.append('periodo_ref', td.getAttribute('data-periodo-ref'));
                Object.keys(payload).forEach(function (k) { fd.append(k, payload[k]); });
                return fetch(ajaxUrlCell, { method: 'POST', credentials: 'same-origin', body: fd })
                    .then(function (r) { return r.json(); });
            }

            wrap.querySelector('.btn-cancel').addEventListener('click', function (e) {
                e.stopPropagation();
                close();
            });

            wrap.querySelector('.btn-ok').addEventListener('click', function (e) {
                e.stopPropagation();
                var val = input.value.trim();
                if (val === '') {
                    alert('Informe uma nota.');
                    return;
                }
                postCell({ valor: val }).then(function (res) {
                    if (!res || !res.success) {
                        alert((res && res.message) || 'Erro ao salvar.');
                        return;
                    }
                    location.reload();
                });
            });

            wrap.querySelector('.btn-clear').addEventListener('click', function (e) {
                e.stopPropagation();
                postCell({ limpar: '1' }).then(function (res) {
                    if (!res || !res.success) {
                        alert((res && res.message) || 'Erro ao remover.');
                        return;
                    }
                    location.reload();
                });
            });

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    wrap.querySelector('.btn-ok').click();
                } else if (e.key === 'Escape') {
                    close();
                }
            });
        }

        document.querySelectorAll('[data-cell-editavel="1"]').forEach(function (td) {
            td.addEventListener('click', function () {
                enterEditMode(td);
            });
        });
    })();
})();
</script>
