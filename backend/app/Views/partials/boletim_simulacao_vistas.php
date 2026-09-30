<?php
/**
 * Toggle Demonstrativo de Notas / Boletim na simulação (mesmo padrão do Configurar Notas).
 *
 * Variáveis:
 * - $cols, $linhas, $decimalPlaces
 * - $linhasBoletim (opcional): linhas com group_line forçado para a vista Boletim
 * - $formatNotaBoletim (callable, opcional)
 * - $simVistaId (string único no DOM)
 */
if (!class_exists('BoletimQuadroLayoutHelper', false)) {
    require_once dirname(__DIR__, 2) . '/Helpers/BoletimQuadroLayoutHelper.php';
}

$colsVista = is_array($cols ?? null) ? $cols : [];
$linhasVista = is_array($linhas ?? null) ? $linhas : [];
$linhasVistaBoletim = is_array($linhasBoletim ?? null) && $linhasBoletim !== []
    ? $linhasBoletim
    : $linhasVista;
$decVista = ((int) ($decimalPlaces ?? 2) === 1) ? 1 : 2;
$fmtVista = is_callable($formatNotaBoletim ?? null)
    ? $formatNotaBoletim
    : static function ($valor) use ($decVista): string {
        return number_format((float) $valor, $decVista, ',', '.');
    };
$simVistaId = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($simVistaId ?? 'sim')) ?: 'sim';
$colsResumo = BoletimQuadroLayoutHelper::filtrarColunasResumoBoletim($colsVista);
?>
<div class="boletim-sim-vistas" data-sim-vistas="<?= htmlspecialchars($simVistaId, ENT_QUOTES, 'UTF-8') ?>">
    <div class="flex flex-wrap gap-2 mb-3">
        <button type="button" data-sim-vista="demonstrativo"
            class="px-3 py-1.5 text-xs font-medium rounded-lg border"
            style="background: var(--sidebar-bg-color, #1e3a5f); border-color: var(--sidebar-bg-color, #1e3a5f); color: var(--sidebar-text-color, #fff);">
            Demonstrativo de Notas
        </button>
        <button type="button" data-sim-vista="boletim"
            class="px-3 py-1.5 text-xs font-medium rounded-lg border bg-white text-gray-700 border-gray-300">
            Boletim
        </button>
    </div>

    <div data-sim-panel="demonstrativo">
        <?php
        $cols = $colsVista;
        $linhas = $linhasVista;
        $decimalPlaces = $decVista;
        include __DIR__ . '/boletim_quadro_tabela.php';
        ?>
    </div>

    <div data-sim-panel="boletim" class="hidden">
        <?php if ($colsResumo === []): ?>
            <p class="text-sm text-gray-500">Não há colunas de resultado ou faltas neste evento.</p>
        <?php else: ?>
            <?php
            $cols = $colsResumo;
            $linhas = $linhasVistaBoletim;
            $decimalPlaces = $decVista;
            $tituloTabelaSimples = 'Matérias';
            // Vista Boletim: sem semanas e sem hierarquia de filhas (só a linha agrupada).
            $ocultar_grupo_hierarquia = false;
            include __DIR__ . '/boletim_quadro_tabela_simples.php';
            ?>
        <?php endif; ?>
    </div>
</div>
