<?php
/**
 * Tabela no visual do quadro (cabeçalho azul), sem Bloco A/B e sem semanas N/Q.
 *
 * Variáveis:
 * - $cols (list)
 * - $linhas (list)
 * - $decimalPlaces (int)
 * - $tituloTabelaSimples (string, opcional) — default "Matérias"
 * - $ocultar_grupo_hierarquia (bool, opcional) — se true, omite linhas-mãe do group_line
 */
if (!class_exists('BoletimQuadroLayoutHelper', false)) {
    require_once dirname(__DIR__, 2) . '/Helpers/BoletimQuadroLayoutHelper.php';
}

$colsSimples = is_array($cols ?? null) ? $cols : [];
$linhasSimples = is_array($linhas ?? null) ? $linhas : [];
$decSimples = ((int) ($decimalPlaces ?? 2) === 1) ? 1 : 2;
$tituloSimples = trim((string) ($tituloTabelaSimples ?? 'Matérias'));
if ($tituloSimples === '') {
    $tituloSimples = 'Matérias';
}
$ocultarGrupo = !empty($ocultar_grupo_hierarquia);
$edicaoSimples = is_array($edicaoSimulacao ?? null) ? $edicaoSimulacao : null;

$colsVisiveis = [];
foreach ($colsSimples as $col) {
    if (!is_array($col)) {
        continue;
    }
    if (BoletimQuadroLayoutHelper::colunaEhSemanaNq($col)) {
        continue;
    }
    $colsVisiveis[] = $col;
}

$linhasVisiveis = [];
foreach ($linhasSimples as $lin) {
    if (!is_array($lin)) {
        continue;
    }
    if ($ocultarGrupo && !empty($lin['eh_grupo_pai'])) {
        continue;
    }
    $linhasVisiveis[] = $lin;
}

if ($colsVisiveis === [] || $linhasVisiveis === []) {
    return;
}

$fmtNotaSimples = static function ($valor) use ($decSimples): string {
    return number_format((float) $valor, $decSimples, ',', '.');
};
?>
<style>
.boletim-quadro-th {
    background: var(--sidebar-bg-color, #1e3a5f);
    color: var(--sidebar-text-color, #fff);
}
.boletim-quadro-th-border {
    border: 1px solid color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 75%, #000);
}
.boletim-quadro-row-alt {
    background: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 6%, #fff);
}
</style>
<div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
    <table class="boletim-quadro-tabela min-w-full border-collapse text-xs sm:text-sm text-center">
        <thead>
            <tr class="boletim-quadro-th text-white">
                <th class="boletim-quadro-th-border px-3 py-2 text-left font-semibold align-middle whitespace-nowrap">
                    <?= htmlspecialchars($tituloSimples, ENT_QUOTES, 'UTF-8') ?>
                </th>
                <?php foreach ($colsVisiveis as $oc):
                    $ltOc = strtolower(trim((string) ($oc['layout_type'] ?? '')));
                    $isFaltasCab = BoletimQuadroLayoutHelper::colunaEhFaltas($oc);
                    $mostraValor10 = !$isFaltasCab && (
                        in_array($ltOc, ['media', 'media_sem', 'resultado', ''], true)
                        || $ltOc === 'other'
                        || $ltOc === 'prova'
                        || $ltOc === 'jornada'
                        || $ltOc === 'enac'
                    );
                ?>
                    <th class="boletim-quadro-th-border px-2 py-1 font-semibold align-middle leading-tight min-w-[4.5rem]">
                        <?= htmlspecialchars(BoletimQuadroLayoutHelper::rotuloColunaQuadro($oc), ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($mostraValor10): ?>
                            <div class="text-[10px] font-normal opacity-80">Valor 10</div>
                        <?php endif; ?>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php $iS = 0; foreach ($linhasVisiveis as $linS): $iS++;
                $notasS = is_array($linS['notas'] ?? null) ? $linS['notas'] : [];
                $bgS = ($iS % 2 === 0) ? 'boletim-quadro-row-alt' : 'bg-white';
                $ehPai = !empty($linS['eh_grupo_pai']);
                $ehFilho = !empty($linS['eh_grupo_filho']);
            ?>
                <tr class="<?= $bgS ?><?= $ehPai ? ' font-semibold' : '' ?>">
                    <td class="border border-gray-300 px-3 py-1.5 text-left text-gray-900 whitespace-nowrap<?= $ehPai ? ' font-bold' : ' font-medium' ?><?= $ehFilho ? ' pl-7 text-gray-800' : '' ?>">
                        <?php if ($ehFilho): ?>
                            <span class="text-gray-400 mr-1" aria-hidden="true">↳</span>
                        <?php endif; ?>
                        <?= htmlspecialchars((string) ($linS['materia_nome'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <?php foreach ($colsVisiveis as $oc):
                        $codO = (string) ($oc['codigo'] ?? '');
                        $nv = $notasS[$codO] ?? null;
                        if (is_array($nv)) {
                            $nv = $nv['texto'] ?? $nv['valor'] ?? null;
                        }
                        $isFaltas = BoletimQuadroLayoutHelper::colunaEhFaltas($oc);
                        $stCol = (string) ($oc['source_type'] ?? '');
                        $isManualCol = $stCol === 'manual';
                        $isPorMateriaCol = $edicaoSimples !== null
                            && in_array($stCol, ['provas_sistema', 'jornadas', 'calculado'], true)
                            && (int) ($linS['materia_id'] ?? 0) !== 0;
                        $isCalcEditavel = $edicaoSimples !== null
                            && (int) ($oc['id'] ?? 0) > 0
                            && ($isManualCol || $isPorMateriaCol);
                        $materiaIdEdicao = $isManualCol ? 0 : (int) ($linS['materia_id'] ?? 0);
                        $colGlobal = !empty($oc['valor_global']);
                        $mostrarIdem = $edicaoSimples !== null && is_numeric($nv) && $colGlobal && $iS > 1;
                    ?>
                        <td class="border border-gray-300 px-1 py-1 <?= is_numeric($nv) ? 'text-emerald-800 font-semibold' : 'text-gray-500' ?><?= $isCalcEditavel ? ' boletim-cell-editavel cursor-pointer' : '' ?>"
                            <?php if ($isCalcEditavel): ?>
                            data-cell-editavel="1"
                            data-componente-id="<?= (int) ($oc['id'] ?? 0) ?>"
                            data-materia-id="<?= $materiaIdEdicao ?>"
                            data-regra-id="<?= (int) ($edicaoSimples['regra_id'] ?? 0) ?>"
                            data-aluno-id="<?= (int) ($edicaoSimples['aluno_id'] ?? 0) ?>"
                            data-periodo-ref="<?= htmlspecialchars((string) ($edicaoSimples['periodo_ref'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-escala-max="<?= htmlspecialchars(number_format((float) ($oc['escala_max'] ?? 10), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>"
                            title="<?= $isManualCol ? 'Clique para editar (vale para todas as matérias deste bloco)' : 'Clique para sobrescrever só essa matéria, só para este aluno' ?>"
                            <?php endif; ?>
                        >
                            <span class="boletim-cell-valor">
                            <?php if ($mostrarIdem): ?>
                                <span class="text-xs font-medium text-slate-500" title="Nota única, igual em todas as matérias.">idem</span>
                            <?php elseif (is_numeric($nv)): ?>
                                <?= $isFaltas
                                    ? htmlspecialchars(number_format((float) round((float) $nv), 0, ',', '.'), ENT_QUOTES, 'UTF-8')
                                    : htmlspecialchars($fmtNotaSimples($nv), ENT_QUOTES, 'UTF-8') ?>
                            <?php elseif (is_string($nv) && trim($nv) !== ''): ?>
                                <?= htmlspecialchars($nv, ENT_QUOTES, 'UTF-8') ?>
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
