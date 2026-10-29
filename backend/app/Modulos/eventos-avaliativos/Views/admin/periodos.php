<?php
$ano = (int) ($ano ?? date('Y'));
$regraId = (int) ($regra_id ?? 0);
$regraNome = trim((string) ($regra_nome ?? ''));
$sufixoRegra = $regraId > 0 ? '&regra=' . $regraId : '';
$periodo = is_array($periodo ?? null) ? $periodo : [];
$rotulos = is_array($periodo['rotulos'] ?? null) ? $periodo['rotulos'] : [];
$campo = (string) ($periodo['rotulo_campo'] ?? 'Bimestre');
$contagens = is_array($contagens ?? null) ? $contagens : [];
$rascunhos = is_array($rascunhos ?? null) ? $rascunhos : [];
$schemaPronto = !empty($schema_pronto);

$page_header_title = $regraNome !== '' ? 'Eventos Avaliativos - ' . $regraNome : 'Eventos Avaliativos';
$page_header_subtitle = 'Visualize a estrutura dos eventos avaliativos do período letivo.';
$page_header_actions = '<a href="' . URL . '/admin/eventos-avaliativos" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50"><i class="fa-solid fa-arrow-left mr-2 text-gray-500" aria-hidden="true"></i>Regras de Nota</a>';
include \App\Core\ViewPaths::adminPartial('page_header_list.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_10_26_eventos_avaliativos.sql</code> no painel Master antes de cadastrar.
</div>
<?php endif; ?>

<?php
$slots = is_array($slots ?? null) ? $slots : [];
$temRecNoPeriodo = !empty($tem_rec_no_periodo);
$periodosChave = [];
foreach ($rotulos as $numero => $rotulo) {
    $periodosChave[] = ['numero' => (int) $numero, 'rotulo' => (string) $rotulo];
}
$paresPorIndice = [];
$ciclosPorPeriodo = [];
$finalSlot = null;
foreach ($slots as $slot) {
    $codigoSlot = (int) ($slot['codigo'] ?? 0);
    if ($codigoSlot === \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_FINAL) {
        $finalSlot = $slot;
        continue;
    }
    if ($codigoSlot > \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_PAR_BASE
        && $codigoSlot < \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_FINAL) {
        $paresPorIndice[$codigoSlot - \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_PAR_BASE] = $slot;
        continue;
    }
    if ($codigoSlot > \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_CICLO_BASE
        && $codigoSlot < \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_PAR_BASE) {
        $ciclosPorPeriodo[$codigoSlot - \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_CICLO_BASE] = $slot;
    }
}
$gruposChave = [];
$totalPeriodosChave = count($periodosChave);
for ($indiceGrupo = 0; $indiceGrupo < $totalPeriodosChave; $indiceGrupo += 2) {
    $gruposChave[] = [
        'periodos' => array_slice($periodosChave, $indiceGrupo, 2),
        'par' => $paresPorIndice[(int) floor($indiceGrupo / 2) + 1] ?? null,
    ];
}
$temPar = $paresPorIndice !== [];
$temCiclo = $ciclosPorPeriodo !== [];
$temFinal = $finalSlot !== null;
$legendaRecNoPeriodo = $temRecNoPeriodo && !$temCiclo;
$colunasChave = [];
if ($temPar) {
    foreach ($gruposChave as $grupo) {
        $itens = [];
        foreach ($grupo['periodos'] as $periodoCard) {
            $numero = (int) $periodoCard['numero'];
            $itens[] = [
                'periodo' => $periodoCard,
                'ciclo' => $ciclosPorPeriodo[$numero] ?? null,
            ];
        }
        $colunasChave[] = [
            'itens' => $itens,
            'meio' => $grupo['par'],
            'mostrar_meio' => true,
        ];
    }
} else {
    foreach ($periodosChave as $periodoCard) {
        $numero = (int) $periodoCard['numero'];
        $colunasChave[] = [
            'itens' => [[
                'periodo' => $periodoCard,
                'ciclo' => $ciclosPorPeriodo[$numero] ?? null,
            ]],
            'meio' => null,
            'mostrar_meio' => false,
        ];
    }
}
$mostrarChave = $temPar || $temCiclo || $temFinal;
$qtdColunas = count($colunasChave);
$textoEventos = static function (int $total): string {
    return $total === 1 ? '1 evento' : ($total . ' eventos');
};
$caixa = static function (
    string $href,
    string $tipo,
    string $selo,
    string $titulo,
    string $subtitulo,
    string $tipoValor,
    string $eventos,
    string $sigla,
    string $estado = 'vazio'
): string {
    $recuperacao = $tipo === 'rec' || $tipo === 'final';
    if (str_starts_with($titulo, 'Rec ')) {
        $titulo = 'Recuperação ' . substr($titulo, 4);
    }
    $borda = $recuperacao ? 'border' : 'border border-gray-200 hover:border-gray-300';
    $estiloBorda = $recuperacao ? ' style="border-color:#fecaca"' : '';
    if ($tipo === 'periodo' && $estado === 'rascunho') {
        $borda = 'border-2';
        $estiloBorda = ' style="border-color:#eab308"';
    } elseif ($tipo === 'periodo' && $estado === 'publicado') {
        $borda = 'border-2';
        $estiloBorda = ' style="border-color:#16a34a"';
    }
    $seloClasse = $recuperacao ? 'text-red-600' : 'text-blue-600';
    $iconeFundo = $recuperacao ? 'bg-red-50 text-red-500' : 'bg-blue-50 text-blue-600';
    $badgeClasse = $recuperacao ? 'bg-red-50 text-red-600' : 'bg-blue-50 text-blue-700';
    $icone = $recuperacao ? 'fa-file-lines' : 'fa-calendar-days';
    $esc = static function (string $valor): string {
        return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
    };

    $html = '<a href="' . $esc($href) . '" class="block w-full overflow-hidden rounded-xl bg-white shadow-sm hover:shadow transition ' . $borda . '"' . $estiloBorda . '>';
    $html .= '<div class="flex items-start gap-3 p-4">';
    $html .= '<span class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full ' . $iconeFundo . '"><i class="fa-solid ' . $icone . '" aria-hidden="true"></i></span>';
    $html .= '<div class="min-w-0 flex-1">';
    $html .= '<p class="whitespace-nowrap text-xs font-semibold uppercase tracking-wide ' . $seloClasse . '">' . $esc($selo) . '</p>';
    $html .= '<p class="whitespace-nowrap text-base font-semibold leading-tight text-gray-900">' . $esc($titulo) . '</p>';
    if ($subtitulo !== '') {
        $html .= '<p class="mt-0.5 text-sm text-gray-500">' . $esc($subtitulo) . '</p>';
    }
    $html .= '</div>';
    if ($sigla !== '') {
        $html .= '<span class="inline-flex h-7 min-w-[2rem] shrink-0 items-center justify-center rounded-lg px-2 text-xs font-semibold ' . $badgeClasse . '">' . $esc($sigla) . '</span>';
    }
    $html .= '</div>';
    $html .= '<div class="grid grid-cols-2 border-t border-gray-100 px-4 py-3 text-sm">';
    $html .= '<div><p class="text-xs text-gray-400">Tipo</p><p class="mt-0.5 whitespace-nowrap text-gray-800">' . $esc($tipoValor) . '</p></div>';
    $html .= '<div class="border-l border-gray-100 pl-4"><p class="text-xs text-gray-400">Eventos</p><p class="mt-0.5 whitespace-nowrap text-gray-800">' . $esc($eventos) . '</p></div>';
    $html .= '</div></a>';

    return $html;
};
$estadoPeriodo = static function (int $numero) use ($contagens, $rascunhos): string {
    if ((int) ($rascunhos[$numero] ?? 0) > 0) {
        return 'rascunho';
    }
    if ((int) ($contagens[$numero] ?? 0) > 0) {
        return 'publicado';
    }
    return 'vazio';
};
$siglaPeriodo = static function (string $campo, int $numero): string {
    $letra = mb_strtoupper(mb_substr(trim($campo), 0, 1));
    return ($letra !== '' ? $letra : 'P') . $numero;
};
$haste = '<div class="h-5 shrink-0 bg-gray-400" style="width:2px"></div>';
$partesRotulo = static function (array $slot): array {
    $partes = explode(' · ', (string) ($slot['rotulo'] ?? 'Recuperação'), 2);
    return [$partes[0], $partes[1] ?? ''];
};
?>

<?php if (!$mostrarChave): ?>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
    <?php foreach ($periodosChave as $periodoCard): ?>
        <?php
            $total = (int) ($contagens[$periodoCard['numero']] ?? 0);
            $detalhePeriodo = $legendaRecNoPeriodo ? 'Recuperação neste ciclo' : 'Avaliação';
            $hrefPeriodo = URL . '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . $periodoCard['numero'] . $sufixoRegra;
        ?>
        <?= $caixa($hrefPeriodo, 'periodo', mb_strtoupper($campo), (string) $periodoCard['rotulo'], '', $detalhePeriodo, $textoEventos($total), $siglaPeriodo($campo, (int) $periodoCard['numero']), $estadoPeriodo((int) $periodoCard['numero'])) ?>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div class="w-full overflow-x-auto pb-2">
    <div class="flex w-full flex-col items-center" style="min-width:<?= max(1, $qtdColunas) * 20 ?>rem">
        <div class="flex w-full flex-col items-center">
            <div class="flex w-full items-stretch">
                <?php foreach ($colunasChave as $indiceColuna => $coluna): ?>
                    <?php
                        $itens = is_array($coluna['itens'] ?? null) ? $coluna['itens'] : [];
                        $qtdItens = count($itens);
                        $mostrarMeio = !empty($coluna['mostrar_meio']);
                        $meio = is_array($coluna['meio'] ?? null) ? $coluna['meio'] : null;
                    ?>
                    <div class="flex min-w-0 flex-1 flex-col items-center">
                        <div class="flex w-full items-stretch">
                            <?php foreach ($itens as $item): ?>
                                <?php
                                    $periodoCard = is_array($item['periodo'] ?? null) ? $item['periodo'] : ['numero' => 0, 'rotulo' => ''];
                                    $ciclo = is_array($item['ciclo'] ?? null) ? $item['ciclo'] : null;
                                    $total = (int) ($contagens[$periodoCard['numero']] ?? 0);
                                    $detalhePeriodo = ($ciclo === null && $legendaRecNoPeriodo) ? 'Recuperação neste ciclo' : 'Avaliação';
                                    $hrefPeriodo = URL . '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . (int) $periodoCard['numero'] . $sufixoRegra;
                                ?>
                                <div class="<?= $qtdItens > 1 ? 'w-1/2' : 'w-full' ?> px-3 flex flex-col items-center">
                                    <div class="w-full"><?= $caixa($hrefPeriodo, 'periodo', mb_strtoupper($campo), (string) $periodoCard['rotulo'], '', $detalhePeriodo, $textoEventos($total), $siglaPeriodo($campo, (int) $periodoCard['numero']), $estadoPeriodo((int) $periodoCard['numero'])) ?></div>
                                    <?php if ($ciclo !== null): ?>
                                        <?php
                                            [$tituloCiclo, $detalheCiclo] = $partesRotulo($ciclo);
                                            $codigoCiclo = (int) ($ciclo['codigo'] ?? 0);
                                            $totalCiclo = (int) ($contagens[$codigoCiclo] ?? 0);
                                            $hrefCiclo = URL . '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . $codigoCiclo . $sufixoRegra;
                                        ?>
                                        <?= $haste ?>
                                        <div class="w-full"><?= $caixa($hrefCiclo, 'rec', 'RECUPERAÇÃO', $tituloCiclo, $detalheCiclo, 'Recuperação', $textoEventos($totalCiclo), 'R' . ($codigoCiclo - \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_CICLO_BASE)) ?></div>
                                    <?php endif; ?>
                                    <?php if ($qtdItens > 1): ?>
                                        <div class="mt-auto flex flex-col items-center"><?= $haste ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php if ($qtdItens > 1): ?>
                            <div class="h-0.5 w-1/2 self-center bg-gray-400"></div>
                        <?php endif; ?>
                        <?php if ($mostrarMeio): ?>
                            <?= $haste ?>
                            <div class="w-full px-3" style="max-width:24rem">
                                <?php if ($meio !== null): ?>
                                    <?php
                                        [$tituloMeio, $detalheMeio] = $partesRotulo($meio);
                                        $codigoMeio = (int) ($meio['codigo'] ?? 0);
                                        $totalMeio = (int) ($contagens[$codigoMeio] ?? 0);
                                        $hrefMeio = URL . '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . $codigoMeio . $sufixoRegra;
                                    ?>
                                    <?= $caixa($hrefMeio, 'rec', 'RECUPERAÇÃO', $tituloMeio, $detalheMeio, 'Recuperação', $textoEventos($totalMeio), 'R' . ($codigoMeio - \App\Modulos\RegrasAcademicas\Models\RecuperacaoConfig::CODIGO_PAR_BASE)) ?>
                                <?php else: ?>
                                    <div class="rounded-xl border-2 border-dashed border-red-300 bg-gray-50 px-3 py-2 text-center text-sm text-gray-400">
                                        Sem recuperação neste par
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if ($temFinal && $qtdColunas > 0): ?>
                            <div class="mt-auto flex w-full flex-col items-center">
                                <?= $haste ?>
                                <?php if ($qtdColunas > 1): ?>
                                    <div class="flex h-0.5 w-full">
                                        <?php if ($indiceColuna === 0): ?>
                                            <div class="w-1/2"></div>
                                            <div class="w-1/2 bg-gray-400"></div>
                                        <?php elseif ($indiceColuna === $qtdColunas - 1): ?>
                                            <div class="w-1/2 bg-gray-400"></div>
                                            <div class="w-1/2"></div>
                                        <?php else: ?>
                                            <div class="w-full bg-gray-400"></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <?php if ($temFinal): ?>
                <?php
                    [$tituloFinal, $detalheFinal] = $partesRotulo($finalSlot);
                    $codigoFinal = (int) ($finalSlot['codigo'] ?? 90);
                    $totalFinal = (int) ($contagens[$codigoFinal] ?? 0);
                    $hrefFinal = URL . '/admin/eventos-avaliativos?ano=' . $ano . '&periodo=' . $codigoFinal . $sufixoRegra;
                ?>
                <?php if ($qtdColunas > 1): ?>
                    <div class="h-5 bg-gray-400" style="width:2px"></div>
                <?php endif; ?>
                <div class="w-full px-3" style="max-width:24rem"><?= $caixa($hrefFinal, 'final', 'RECUPERAÇÃO', $tituloFinal !== '' ? $tituloFinal : 'Recuperação Final', $detalheFinal !== '' ? $detalheFinal : 'Ano Letivo', 'Recuperação Final', $textoEventos($totalFinal), 'RF') ?></div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>
