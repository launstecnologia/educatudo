<?php
/**
 * Resultado da simulação: só o demonstrativo de notas (quadro com blocos e semanas).
 *
 * Variáveis:
 * - $cols, $linhas, $decimalPlaces
 * - $linhasBoletim (opcional)
 * - $simVistaId (string)
 * - $edicaoSimulacao (array|null) — usado só quando o evento não tem quadro
 */
if (!class_exists('BoletimQuadroLayoutHelper', false)) {
    require_once dirname(__DIR__, 2) . '/Helpers/BoletimQuadroLayoutHelper.php';
}

$colsSim = is_array($cols ?? null) ? $cols : [];
$linhasSim = is_array($linhas ?? null) ? $linhas : [];
if ($colsSim === [] || $linhasSim === []) {
    echo '<div class="text-sm text-gray-500">Sem dados de simulação para o aluno/evento selecionado.</div>';
    return;
}

$decSim = ((int) ($decimalPlaces ?? 2) === 1) ? 1 : 2;
$ehQuadroSim = BoletimQuadroLayoutHelper::partirTabelas($colsSim) !== [];
$edicaoGuardada = is_array($edicaoSimulacao ?? null) ? $edicaoSimulacao : null;
$cols = $colsSim;
$linhas = $linhasSim;
$decimalPlaces = $decSim;
ob_start();
if ($ehQuadroSim) {
    include __DIR__ . '/boletim_quadro_tabela.php';
}
$htmlDemonstrativo = ob_get_clean();
if (trim($htmlDemonstrativo) === '') {
    $edicaoSimulacao = $edicaoGuardada;
    $tituloTabelaSimples = 'Matéria';
    $ocultar_grupo_hierarquia = false;
    ob_start();
    include __DIR__ . '/boletim_quadro_tabela_simples.php';
    $htmlDemonstrativo = ob_get_clean();
}
if (trim($htmlDemonstrativo) === '') {
    echo '<div class="text-sm text-gray-500">Sem dados de simulação para o aluno/evento selecionado.</div>';
    return;
}
?>
<section class="mb-8">
    <h3 class="text-base font-semibold text-gray-900 mb-1">Demonstrativo de Notas</h3>
    <?= $htmlDemonstrativo ?>
</section>
