<?php
/**
 * Renderiza boletim no layout quadro semanal (S1 N|Q + totais + médias).
 *
 * Variáveis esperadas:
 * - $cols (list)
 * - $linhas (list)
 * - $decimalPlaces (int)
 * - $tituloRegraHtml (string, opcional)
 */
if (!class_exists('BoletimQuadroLayoutHelper', false)) {
    require_once dirname(__DIR__, 2) . '/Helpers/BoletimQuadroLayoutHelper.php';
}

$colsQuadro = is_array($cols ?? null) ? $cols : [];
$linhasQuadro = is_array($linhas ?? null) ? $linhas : [];
$decQuadro = ((int) ($decimalPlaces ?? 2) === 1) ? 1 : 2;
$tabelasQuadro = BoletimQuadroLayoutHelper::partirTabelas($colsQuadro);

$fmtNotaQuadro = static function ($valor) use ($decQuadro): string {
    return number_format((float) $valor, $decQuadro, ',', '.');
};
$fmtQtdQuadro = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return (string) (int) $v;
};

if ($tabelasQuadro === [] || $linhasQuadro === []) {
    return;
}
?>
<div class="space-y-8">
    <?php foreach ($tabelasQuadro as $tabQ):
        $colsTab = is_array($tabQ['cols'] ?? null) ? $tabQ['cols'] : [];
        $semanasCols = [];
        $outrasCols = [];
        foreach ($colsTab as $cq) {
            if (BoletimQuadroLayoutHelper::colunaEhSemanaNq($cq)) {
                $semanasCols[] = $cq;
            } else {
                $outrasCols[] = $cq;
            }
        }
        $semanasPorBloco = ['a' => [], 'b' => []];
        foreach ($tabelasQuadro as $tabPre) {
            $kPre = strtolower((string) ($tabPre['key'] ?? ''));
            if ($kPre !== 'a' && $kPre !== 'b') {
                continue;
            }
            foreach ((array) ($tabPre['cols'] ?? []) as $cPre) {
                if (is_array($cPre) && BoletimQuadroLayoutHelper::colunaEhSemanaNq($cPre)) {
                    $semanasPorBloco[$kPre][] = $cPre;
                }
            }
        }
        $linhasTab = [];
        foreach ($linhasQuadro as $linQ) {
            $notasQ = is_array($linQ['notas'] ?? null) ? $linQ['notas'] : [];
            $keyTab = strtolower((string) ($tabQ['key'] ?? 'a'));
            $semanasOutro = $keyTab === 'b' ? $semanasPorBloco['a'] : $semanasPorBloco['b'];
            if (!BoletimQuadroLayoutHelper::linhaVisivelNoQuadro($keyTab, $semanasCols, $semanasOutro, $outrasCols, $notasQ)) {
                continue;
            }
            $linhasTab[] = $linQ;
        }
        if ($linhasTab === [] && $semanasCols === []) {
            continue;
        }
    ?>
        <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
            <?php if (!empty($tabQ['subtitulo'])): ?>
                <div class="px-3 py-2 text-sm font-semibold text-gray-800 bg-gray-50 border-b border-gray-200">
                    <?= htmlspecialchars((string) $tabQ['subtitulo'], ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php endif; ?>
            <table class="min-w-full border-collapse text-xs sm:text-sm text-center">
                <thead>
                    <tr class="bg-teal-700 text-white">
                        <th rowspan="2" class="border border-teal-800 px-3 py-2 text-left font-semibold align-middle whitespace-nowrap">
                            <?= htmlspecialchars((string) ($tabQ['titulo'] ?? 'Matérias'), ENT_QUOTES, 'UTF-8') ?>
                        </th>
                        <?php foreach ($semanasCols as $sc): ?>
                            <th colspan="2" class="border border-teal-800 px-2 py-1 font-semibold">
                                <?= htmlspecialchars((string) ($sc['nome'] ?? $sc['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                        <?php endforeach; ?>
                        <?php if ($semanasCols !== []): ?>
                            <th colspan="2" class="border border-teal-800 px-2 py-1 font-semibold">Total</th>
                        <?php endif; ?>
                        <?php foreach ($outrasCols as $oc):
                            $ltOc = strtolower(trim((string) ($oc['layout_type'] ?? '')));
                            $mostraValor10 = in_array($ltOc, ['media', 'media_sem', 'resultado', ''], true)
                                || $ltOc === 'other';
                        ?>
                            <th rowspan="2" class="border border-teal-800 px-2 py-1 font-semibold align-middle leading-tight min-w-[4.5rem]">
                                <?= htmlspecialchars((string) ($oc['nome'] ?? $oc['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                <?php if ($mostraValor10 && $ltOc !== 'faltas' && $ltOc !== 'rec'): ?>
                                    <div class="text-[10px] font-normal opacity-80">Valor 10</div>
                                <?php endif; ?>
                            </th>
                        <?php endforeach; ?>
                    </tr>
                    <tr class="bg-teal-600 text-white">
                        <?php foreach ($semanasCols as $_sc): ?>
                            <th class="border border-teal-800 px-1 py-1 font-semibold" title="Questões certas">N</th>
                            <th class="border border-teal-800 px-1 py-1 font-semibold" title="Total de questões">Q</th>
                        <?php endforeach; ?>
                        <?php if ($semanasCols !== []): ?>
                            <th class="border border-teal-800 px-1 py-1 font-semibold" title="Total de acertos">N</th>
                            <th class="border border-teal-800 px-1 py-1 font-semibold" title="Total de questões">Q</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php $iQ = 0; foreach ($linhasTab as $linQ): $iQ++;
                        $notasQ = is_array($linQ['notas'] ?? null) ? $linQ['notas'] : [];
                        $bgQ = ($iQ % 2 === 0) ? 'bg-teal-50/40' : 'bg-white';
                        $totN = 0;
                        $totQ = 0;
                        $temTot = false;
                    ?>
                        <tr class="<?= $bgQ ?>">
                            <td class="border border-gray-300 px-3 py-1.5 text-left font-medium text-gray-900 whitespace-nowrap">
                                <?= htmlspecialchars((string) ($linQ['materia_nome'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                            </td>
                            <?php foreach ($semanasCols as $sc):
                                $codS = (string) ($sc['codigo'] ?? '');
                                $nq = BoletimQuadroLayoutHelper::celulaNq($notasQ, $codS);
                                if ($nq['n'] !== null) {
                                    $totN += $nq['n'];
                                    $temTot = true;
                                }
                                if ($nq['q'] !== null) {
                                    $totQ += $nq['q'];
                                    $temTot = true;
                                }
                            ?>
                                <td class="border border-gray-300 px-1 py-1"><?= $fmtQtdQuadro($nq['n'] !== null ? $nq['n'] : 0) ?></td>
                                <td class="border border-gray-300 px-1 py-1"><?= $fmtQtdQuadro($nq['q'] !== null ? $nq['q'] : 0) ?></td>
                            <?php endforeach; ?>
                            <?php if ($semanasCols !== []): ?>
                                <td class="border border-gray-300 px-1 py-1 font-semibold"><?= $temTot ? $fmtQtdQuadro($totN) : '—' ?></td>
                                <td class="border border-gray-300 px-1 py-1 font-semibold"><?= $temTot ? $fmtQtdQuadro($totQ) : '—' ?></td>
                            <?php endif; ?>
                            <?php foreach ($outrasCols as $oc):
                                $codO = (string) ($oc['codigo'] ?? '');
                                $nv = $notasQ[$codO] ?? null;
                                $isFaltas = ((string) ($oc['source_type'] ?? '')) === 'faltas_evento'
                                    || strtolower((string) ($oc['layout_type'] ?? '')) === 'faltas';
                            ?>
                                <td class="border border-gray-300 px-1 py-1 <?= is_numeric($nv) ? 'text-emerald-800 font-semibold' : 'text-gray-500' ?>">
                                    <?php if (is_numeric($nv)): ?>
                                        <?= $isFaltas
                                            ? htmlspecialchars(number_format((float) round((float) $nv), 0, ',', '.'), ENT_QUOTES, 'UTF-8')
                                            : htmlspecialchars($fmtNotaQuadro($nv), ENT_QUOTES, 'UTF-8') ?>
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
    <?php endforeach; ?>
</div>
