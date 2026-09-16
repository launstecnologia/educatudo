<?php
/**
 * Resumo da aba Notas: média semanal, prova bimestral, trabalho, média bimestral e faltas.
 *
 * Aceita $resumos_notas já montado, ou monta a partir de eventos gerados / quadro.
 *
 * @var list<array<string,mixed>>|null $resumos_notas
 * @var list<array<string,mixed>>|null $boletins_gerados_notas
 * @var array<int,array<string,mixed>>|null $boletins_gerados_notas_por_regra
 * @var list<array<string,mixed>>|null $boletim_eventos_notas
 * @var list<array<string,mixed>>|null $paineis_notas
 * @var array<string,mixed>|null $quadro
 * @var array<string,mixed>|null $quadro_oficial
 */
if (!class_exists('BoletimQuadroLayoutHelper', false)) {
    require_once dirname(__DIR__, 2) . '/Helpers/BoletimQuadroLayoutHelper.php';
}

$resumosNotas = is_array($resumos_notas ?? null) ? $resumos_notas : null;
if ($resumosNotas === null) {
    $eventosParaResumo = [];
    if (!empty($boletins_gerados_notas) && is_array($boletins_gerados_notas)) {
        $eventosParaResumo = $boletins_gerados_notas;
    } elseif (!empty($boletins_gerados_notas_por_regra) && is_array($boletins_gerados_notas_por_regra)) {
        $listaEventos = is_array($boletim_eventos_notas ?? null) ? $boletim_eventos_notas : [];
        if ($listaEventos !== []) {
            foreach ($listaEventos as $evLista) {
                $rid = (int) ($evLista['id'] ?? 0);
                $gerado = $rid > 0 ? ($boletins_gerados_notas_por_regra[$rid] ?? null) : null;
                if (is_array($gerado) && !empty($gerado['linhas'])) {
                    $eventosParaResumo[] = $gerado;
                }
            }
        } else {
            $eventosParaResumo = array_values($boletins_gerados_notas_por_regra);
        }
    }
    $quadroResumo = is_array($quadro ?? null) ? $quadro : [];
    if ($quadroResumo === [] && is_array($quadro_oficial ?? null)) {
        $quadroResumo = $quadro_oficial;
    }
    $resumosNotas = BoletimQuadroLayoutHelper::montarResumosNotas($eventosParaResumo, $quadroResumo);
    if ($resumosNotas === []) {
        $bimFaltas = null;
        foreach ($eventosParaResumo as $evR) {
            if (!is_array($evR)) {
                continue;
            }
            if (isset($evR['bimestre']) && $evR['bimestre'] !== null) {
                $bimFaltas = (int) $evR['bimestre'];
                break;
            }
        }
        if ($bimFaltas === null) {
            foreach (is_array($boletim_eventos_notas ?? null) ? $boletim_eventos_notas : [] as $evLista) {
                if (isset($evLista['bimestre']) && $evLista['bimestre'] !== null) {
                    $bimFaltas = (int) $evLista['bimestre'];
                    break;
                }
            }
        }
        if ($bimFaltas === null && isset($filtro_bimestre) && (int) $filtro_bimestre > 0) {
            $bimFaltas = (int) $filtro_bimestre;
        }
        $resumosNotas = BoletimQuadroLayoutHelper::montarResumosDoPainelQuadro(
            is_array($paineis_notas ?? null) ? $paineis_notas : [],
            $quadroResumo,
            $bimFaltas
        );
    }
}

if ($resumosNotas === []) {
    return;
}

if (!empty($resumo_notas_somente_montar)) {
    return;
}

$fmtResumo = static function ($valor, string $chave, int $dec): string {
    if ($valor === null || $valor === '') {
        return '—';
    }
    if ($chave === 'faltas') {
        return number_format((float) round((float) $valor), 0, ',', '.');
    }
    return number_format((float) $valor, $dec, ',', '.');
};
?>
<div class="space-y-8 mb-8">
    <?php foreach ($resumosNotas as $resumo): ?>
        <?php
        $colunasR = is_array($resumo['colunas'] ?? null) ? $resumo['colunas'] : BoletimQuadroLayoutHelper::chavesResumoNotas();
        $rotulosR = is_array($resumo['rotulos'] ?? null) ? $resumo['rotulos'] : BoletimQuadroLayoutHelper::rotulosResumoNotas();
        $linhasR = is_array($resumo['linhas'] ?? null) ? $resumo['linhas'] : [];
        $decR = ((int) ($resumo['decimal_places'] ?? 2) === 1) ? 1 : 2;
        $tituloR = trim((string) ($resumo['titulo'] ?? 'Notas'));
        $subR = trim((string) ($resumo['subtitulo'] ?? ''));
        ?>
        <section>
            <h2 class="text-lg font-semibold text-gray-900 mb-1">Notas<?= $tituloR !== '' && $tituloR !== 'Notas' ? ' — ' . htmlspecialchars($tituloR, ENT_QUOTES, 'UTF-8') : '' ?></h2>
            <?php if ($subR !== ''): ?>
                <p class="text-sm text-gray-500 mb-4"><?= htmlspecialchars($subR, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>
            <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white">
                <table class="min-w-full text-sm text-left">
                    <thead class="bg-gray-100 text-gray-700">
                        <tr>
                            <th class="px-4 py-2 font-semibold sticky left-0 bg-gray-100 z-10">Matéria</th>
                            <?php foreach ($colunasR as $codCol): ?>
                                <?php $destaque = in_array($codCol, ['media_semanal', 'media_bimestral'], true); ?>
                                <th class="px-3 py-2 font-semibold text-center whitespace-nowrap <?= $destaque ? 'bg-violet-50 text-violet-900' : '' ?>">
                                    <?= htmlspecialchars((string) ($rotulosR[$codCol] ?? $codCol), ENT_QUOTES, 'UTF-8') ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if ($linhasR === []): ?>
                            <tr>
                                <td colspan="<?= 1 + count($colunasR) ?>" class="px-4 py-6 text-center text-gray-500">Nenhuma matéria neste período.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($linhasR as $linhaR): ?>
                                <?php $celR = is_array($linhaR['celulas'] ?? null) ? $linhaR['celulas'] : []; ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-4 py-2 sticky left-0 bg-white z-10">
                                        <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($linhaR['materia_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php if (!empty($linhaR['pai_nome'])): ?>
                                            <div class="text-xs text-gray-500"><?= htmlspecialchars((string) $linhaR['pai_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <?php foreach ($colunasR as $codCol): ?>
                                        <?php
                                        $destaque = in_array($codCol, ['media_semanal', 'media_bimestral'], true);
                                        $valR = $celR[$codCol] ?? null;
                                        ?>
                                        <td class="px-3 py-2 text-center <?= $destaque ? 'bg-violet-50 font-semibold text-gray-900' : 'text-gray-800' ?>">
                                            <?= htmlspecialchars($fmtResumo($valR, (string) $codCol, $decR), ENT_QUOTES, 'UTF-8') ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</div>
