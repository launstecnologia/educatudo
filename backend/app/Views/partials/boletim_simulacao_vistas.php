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
            class="px-3 py-1.5 text-xs font-medium rounded-lg border text-white"
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
            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <div class="px-3 py-1.5 text-sm font-semibold border-b"
                     style="background: var(--sidebar-bg-color, #1e3a5f); color: var(--sidebar-text-color, #fff); border-color: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 75%, #000);">
                    Boletim (resultado e faltas)
                </div>
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead style="background: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 12%, #fff);">
                        <tr>
                            <th class="px-3 py-2 text-left text-xs font-semibold uppercase tracking-wide sticky left-0 z-10 border-r border-gray-200"
                                style="color: var(--sidebar-bg-color, #1e3a5f); background: color-mix(in srgb, var(--sidebar-bg-color, #1e3a5f) 12%, #fff);">Matéria</th>
                            <?php foreach ($colsResumo as $mc): ?>
                                <?php $isFaltasCab = BoletimQuadroLayoutHelper::colunaEhFaltas($mc); ?>
                                <th class="px-3 py-2 text-center text-xs font-semibold min-w-[5.5rem]"
                                    style="color: var(--sidebar-bg-color, #1e3a5f);"
                                    title="<?= htmlspecialchars((string) ($mc['nome'] ?? '')) ?>">
                                    <span class="block truncate max-w-[8rem] mx-auto"><?= htmlspecialchars((string) ($mc['nome'] ?? $mc['codigo'] ?? '')) ?></span>
                                    <?php if (!$isFaltasCab): ?>
                                        <span class="block text-[10px] font-normal opacity-70 normal-case">Valor 10</span>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        <?php foreach ($linhasVistaBoletim as $lin): ?>
                            <?php $notasLin = is_array($lin['notas'] ?? null) ? $lin['notas'] : []; ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-3 py-2 font-medium text-gray-900 sticky left-0 bg-white z-10 border-r border-gray-200"><?= htmlspecialchars((string) ($lin['materia_nome'] ?? '-')) ?></td>
                                <?php foreach ($colsResumo as $mc):
                                    $codM = (string) ($mc['codigo'] ?? '');
                                    $nv = $notasLin[$codM] ?? null;
                                    $isFaltasCol = BoletimQuadroLayoutHelper::colunaEhFaltas($mc);
                                ?>
                                    <td class="px-3 py-2 text-center <?= is_numeric($nv) ? 'text-emerald-700 font-semibold' : (is_string($nv) && trim($nv) !== '' ? 'text-slate-700 font-medium' : 'text-gray-400') ?>">
                                        <?php if (is_numeric($nv)): ?>
                                            <?= $isFaltasCol ? number_format((float) round((float) $nv), 0, ',', '.') : $fmtVista($nv) ?>
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
        <?php endif; ?>
    </div>
</div>
