<?php
$boletim_eventos_notas = is_array($boletim_eventos_notas ?? null) ? $boletim_eventos_notas : [];
$boletins_gerados_notas_por_regra = is_array($boletins_gerados_notas_por_regra ?? null) ? $boletins_gerados_notas_por_regra : [];
$paineis_notas_originais = is_array($paineis_notas ?? null) ? $paineis_notas : [];
$paineis_notas = $paineis_notas_originais;
$alunoIdNotas = (int) ($student['id'] ?? $aluno_id ?? 0);

$appDirNotas = dirname(__DIR__, 3);
if (!class_exists('BoletimQuadroLayoutHelper', false)) {
    require_once $appDirNotas . '/Helpers/BoletimQuadroLayoutHelper.php';
}
if (!class_exists('PeriodoLetivo', false)) {
    require_once $appDirNotas . '/Core/PeriodoLetivo.php';
}

$resumo_notas_somente_montar = true;
require dirname(__DIR__, 2) . '/partials/resumo_notas_tabelas.php';
unset($resumo_notas_somente_montar);
$resumosNotas = is_array($resumosNotas ?? null) ? $resumosNotas : [];

$resumoPorChave = [];
foreach ($resumosNotas as $resumo) {
    if (!is_array($resumo)) {
        continue;
    }
    $rid = (int) ($resumo['regra_id'] ?? 0);
    $anoR = (int) ($resumo['ano_letivo'] ?? 0);
    $bimR = (int) ($resumo['bimestre'] ?? 0);
    $resumoPorChave[$rid . ':' . $anoR . ':' . $bimR] = $resumo;
    if (!isset($resumoPorChave['0:' . $anoR . ':' . $bimR])) {
        $resumoPorChave['0:' . $anoR . ':' . $bimR] = $resumo;
    }
}

$linhasPeriodo = [];
foreach ($boletim_eventos_notas as $ev) {
    if (!is_array($ev)) {
        continue;
    }
    $ano = (int) ($ev['ano_letivo'] ?? 0);
    $bim = (int) ($ev['bimestre'] ?? 0);
    $rid = (int) ($ev['id'] ?? 0);
    $chave = $ano . '-' . $bim . '-' . $rid;
    $resumo = $resumoPorChave[$rid . ':' . $ano . ':' . $bim]
        ?? $resumoPorChave['0:' . $ano . ':' . $bim]
        ?? null;
    $linhasPeriodo[$chave] = [
        'ano' => $ano,
        'numero' => $bim,
        'descricao' => trim((string) ($ev['nome'] ?? '')),
        'resumo' => is_array($resumo) ? $resumo : null,
    ];
}
if ($linhasPeriodo === []) {
    foreach ($resumosNotas as $idx => $resumo) {
        if (!is_array($resumo)) {
            continue;
        }
        $ano = (int) ($resumo['ano_letivo'] ?? 0);
        $bim = (int) ($resumo['bimestre'] ?? 0);
        $linhasPeriodo['r-' . $idx] = [
            'ano' => $ano,
            'numero' => $bim,
            'descricao' => trim((string) ($resumo['titulo'] ?? '')),
            'resumo' => $resumo,
        ];
    }
}

uasort($linhasPeriodo, static function (array $a, array $b): int {
    if ((int) $a['ano'] !== (int) $b['ano']) {
        return (int) $b['ano'] <=> (int) $a['ano'];
    }
    return (int) $a['numero'] <=> (int) $b['numero'];
});

$cabPeriodo = 'Período';
$rotulosCab = [];
foreach ($linhasPeriodo as $linhaP) {
    $anoL = (int) $linhaP['ano'];
    if ($anoL > 0) {
        $rotulosCab[(string) PeriodoLetivo::doAno($anoL)['rotulo_campo']] = true;
    }
}
if (count($rotulosCab) === 1) {
    $cabPeriodo = (string) array_key_first($rotulosCab);
}

$svcPainelPath = $appDirNotas . '/Modulos/grupos-regras-notas/Services/PainelNotasService.php';
if (!class_exists('PainelNotasService', false) && is_file($svcPainelPath)) {
    require_once $svcPainelPath;
}
$temPainelNotasService = class_exists('PainelNotasService', false);

$fontesNotas = [];
$htmlQuadroPorPeriodo = [];
$idxLinha = 0;
foreach ($linhasPeriodo as &$linhaP) {
    $idxLinha++;
    $anoP = (int) $linhaP['ano'];
    $bimP = (int) $linhaP['numero'];
    $idResumo = 'fonte-notas-resumo-' . $idxLinha;
    $idQuadro = 'fonte-notas-quadro-' . $anoP . '-' . $bimP;
    $htmlResumo = '';
    if (is_array($linhaP['resumo'] ?? null)) {
        $resumos_notas = [$linhaP['resumo']];
        ob_start();
        require dirname(__DIR__, 2) . '/partials/resumo_notas_tabelas.php';
        $htmlResumo = (string) ob_get_clean();
        unset($resumos_notas);
    }
    $chaveQuadro = $anoP . ':' . $bimP;
    if (!array_key_exists($chaveQuadro, $htmlQuadroPorPeriodo)) {
        $htmlQuadroPorPeriodo[$chaveQuadro] = '';
        if ($temPainelNotasService && $alunoIdNotas > 0 && $bimP > 0) {
            $paineis_notas = PainelNotasService::paraAluno($alunoIdNotas, [
                'portal' => false,
                'ano_letivo' => $anoP > 0 ? $anoP : null,
                'bimestre' => $bimP,
            ]);
            if ($paineis_notas === []) {
                $paineis_notas = $paineis_notas_originais;
            }
            ob_start();
            require dirname(__DIR__, 2) . '/partials/painel_notas_tabelas.php';
            $htmlQuadroPorPeriodo[$chaveQuadro] = (string) ob_get_clean();
        } elseif ($paineis_notas_originais !== []) {
            $paineis_notas = $paineis_notas_originais;
            ob_start();
            require dirname(__DIR__, 2) . '/partials/painel_notas_tabelas.php';
            $htmlQuadroPorPeriodo[$chaveQuadro] = (string) ob_get_clean();
        }
    }
    $htmlQuadro = $htmlQuadroPorPeriodo[$chaveQuadro];
    $linhaP['id_resumo'] = $idResumo;
    $linhaP['id_quadro'] = $idQuadro;
    $linhaP['tem_resumo'] = trim($htmlResumo) !== '';
    $linhaP['tem_quadro'] = trim($htmlQuadro) !== '';
    if ($linhaP['descricao'] === '') {
        $linhaP['descricao'] = $anoP > 0 && $bimP > 0
            ? PeriodoLetivo::rotulo($anoP, $bimP)
            : 'Notas';
    }
    if ($linhaP['tem_resumo']) {
        $fontesNotas[$idResumo] = $htmlResumo;
    }
    if ($linhaP['tem_quadro'] && !isset($fontesNotas[$idQuadro])) {
        $fontesNotas[$idQuadro] = $htmlQuadro;
    }
}
unset($linhaP);
$paineis_notas = $paineis_notas_originais;

$btnNotasPartial = dirname(__DIR__) . '/_partials/ui/btn.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$jsAbrir = static function (string $fonteId, string $titulo): string {
    return 'abrirModalNotasAluno('
        . json_encode($fonteId, JSON_UNESCAPED_UNICODE)
        . ','
        . json_encode($titulo, JSON_UNESCAPED_UNICODE)
        . ')';
};
$celulaPeriodo = static function (int $ano, int $numero, string $cabPeriodo): string {
    if ($numero <= 0) {
        return '—';
    }
    if ($cabPeriodo === 'Período' || $cabPeriodo === 'Etapa única') {
        return $ano > 0 ? PeriodoLetivo::rotulo($ano, $numero) : ($numero . 'º');
    }
    return $numero . 'º';
};
?>

<?php if ($linhasPeriodo === []): ?>
    <div class="text-center py-12 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhuma nota visível para coordenação.</p>
    </div>
<?php else: ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano letivo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= $esc($cabPeriodo) ?></th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($linhasPeriodo as $linhaP): ?>
                        <?php
                        $anoP = (int) $linhaP['ano'];
                        $bimP = (int) $linhaP['numero'];
                        $descricao = trim((string) $linhaP['descricao']);
                        $tituloModal = $descricao !== ''
                            ? $descricao
                            : ($anoP > 0 && $bimP > 0 ? PeriodoLetivo::rotulo($anoP, $bimP) : 'Notas');
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= $anoP > 0 ? $anoP : '—' ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-800"><?= $esc($celulaPeriodo($anoP, $bimP, $cabPeriodo)) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-800"><?= $esc($descricao !== '' ? $descricao : '—') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <?php if (!empty($linhaP['tem_resumo'])): ?>
                                        <?php
                                        $ui_btn_variant = 'complementar';
                                        $ui_btn_label = 'Notas';
                                        $ui_btn_icon = 'fa-solid fa-list-ol';
                                        $ui_btn_class = 'px-3 py-1.5';
                                        $ui_btn_onclick = $jsAbrir((string) $linhaP['id_resumo'], 'Notas — ' . $tituloModal);
                                        require $btnNotasPartial;
                                        ?>
                                    <?php else: ?>
                                        <button type="button" disabled class="inline-flex items-center px-3 py-1.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed">
                                            <i class="fa-solid fa-list-ol mr-2"></i> Notas
                                        </button>
                                    <?php endif; ?>
                                    <?php if (!empty($linhaP['tem_quadro'])): ?>
                                        <?php
                                        $ui_btn_variant = 'primary';
                                        $ui_btn_label = 'Quadro de notas';
                                        $ui_btn_icon = 'fa-solid fa-table-cells';
                                        $ui_btn_class = 'px-3 py-1.5';
                                        $ui_btn_onclick = $jsAbrir((string) $linhaP['id_quadro'], 'Quadro de notas — ' . $tituloModal);
                                        require $btnNotasPartial;
                                        ?>
                                    <?php else: ?>
                                        <button type="button" disabled class="inline-flex items-center px-3 py-1.5 border border-gray-200 rounded-lg text-sm font-medium text-gray-400 bg-gray-50 cursor-not-allowed">
                                            <i class="fa-solid fa-table-cells mr-2"></i> Quadro de notas
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
<?php endif; ?>

<?php foreach ($fontesNotas as $fonteId => $fonteHtml): ?>
    <div id="<?= $esc($fonteId) ?>" hidden><?= $fonteHtml ?></div>
<?php endforeach; ?>

<?php if ($linhasPeriodo !== []): ?>
<div id="modalNotasAluno" class="hidden fixed inset-0 z-[80] items-center justify-center bg-black/50 p-3 sm:p-6" role="dialog" aria-modal="true" aria-labelledby="modalNotasAlunoTitulo" onclick="if (event.target === this) fecharModalNotasAluno()">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-6xl max-h-[92vh] flex flex-col">
        <div class="flex items-center justify-between gap-3 px-5 py-4 border-b border-gray-200 shrink-0">
            <h3 id="modalNotasAlunoTitulo" class="text-lg font-semibold text-gray-900">Notas</h3>
            <button type="button" onclick="fecharModalNotasAluno()" class="text-gray-400 hover:text-gray-700 p-1" aria-label="Fechar">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <div id="modalNotasAlunoCorpo" class="overflow-auto p-5"></div>
    </div>
</div>
<script>
function abrirModalNotasAluno(fonteId, titulo) {
    var fonte = document.getElementById(fonteId);
    var modal = document.getElementById('modalNotasAluno');
    var corpo = document.getElementById('modalNotasAlunoCorpo');
    var tit = document.getElementById('modalNotasAlunoTitulo');
    if (!fonte || !modal || !corpo) return;
    if (tit) tit.textContent = titulo || 'Notas';
    corpo.innerHTML = fonte.innerHTML;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    document.body.classList.add('overflow-hidden');
}
function fecharModalNotasAluno() {
    var modal = document.getElementById('modalNotasAluno');
    var corpo = document.getElementById('modalNotasAlunoCorpo');
    if (!modal) return;
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    if (corpo) corpo.innerHTML = '';
    document.body.classList.remove('overflow-hidden');
}
document.addEventListener('keydown', function (e) {
    if (e.key !== 'Escape') return;
    var modal = document.getElementById('modalNotasAluno');
    if (!modal || modal.classList.contains('hidden')) return;
    fecharModalNotasAluno();
});
</script>
<?php endif; ?>
