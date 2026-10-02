<?php
/**
 * Resultado da simulação no visual do detalhe do aluno, para qualquer evento.
 *
 * Notas: tabela oficial sem semanas N/Q.
 * Quadro de notas: demonstrativo com blocos e semanas, quando o evento tem esse layout.
 *
 * Variáveis:
 * - $cols, $linhas, $decimalPlaces
 * - $linhasBoletim (opcional)
 * - $simVistaId (string)
 * - $edicaoSimulacao (array|null) — clique para sobrescrever nota na tabela Notas
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
$ehQuadroSim = BoletimQuadroLayoutHelper::ehLayoutQuadro($colsSim);
$linhasBoletimSim = is_array($linhasBoletim ?? null) && $linhasBoletim !== []
    ? $linhasBoletim
    : $linhasSim;
$vistaIdSim = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($simVistaId ?? 'sim')) ?: 'sim';
?>
<section class="mb-8">
    <h3 class="text-base font-semibold text-gray-900 mb-1">Notas</h3>
    <p class="text-xs text-gray-500 mb-2">Como a coordenação vê em Notas, no detalhe do aluno.</p>
    <?php
    $cols = $colsSim;
    $linhas = $linhasSim;
    $decimalPlaces = $decSim;
    $tituloTabelaSimples = 'Matéria';
    $ocultar_grupo_hierarquia = false;
    include __DIR__ . '/boletim_quadro_tabela_simples.php';
    $edicaoSimulacao = null;
    ?>
</section>
<?php if ($ehQuadroSim): ?>
    <section class="mb-8">
        <h3 class="text-base font-semibold text-gray-900 mb-1">Quadro de notas</h3>
        <p class="text-xs text-gray-500 mb-2">Como a coordenação vê em Quadro de notas, no detalhe do aluno.</p>
        <?php
        $cols = $colsSim;
        $linhas = $linhasSim;
        $linhasBoletim = $linhasBoletimSim;
        $decimalPlaces = $decSim;
        $simVistaId = $vistaIdSim;
        include __DIR__ . '/boletim_simulacao_vistas.php';
        ?>
    </section>
<?php endif; ?>
