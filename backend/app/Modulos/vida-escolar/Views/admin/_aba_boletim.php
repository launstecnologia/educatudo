<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = $base ?? (URL . '/admin/students/' . (int) ($aluno_id ?? 0) . '/vida-escolar');
$token = (string) ($csrf_token ?? $token ?? '');
$quadro = is_array($quadro ?? null) ? $quadro : null;
$ficha = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : null;
$grid = is_array($quadro['grid'] ?? null) ? $quadro['grid'] : [];
if (!class_exists('PeriodoLetivo')) {
}
$anoFicha = (int) ($ficha['ano_letivo'] ?? date('Y'));
$infoPeriodo = \App\Core\PeriodoLetivo::doAno($anoFicha);
$periodos = $infoPeriodo['rotulos'];
$periodosQuadro = [];
foreach ($periodos as $numPeriodo => $rotuloPeriodo) {
    $nPeriodo = (int) $numPeriodo;
    if ($nPeriodo >= 1) {
        $periodosQuadro[] = $nPeriodo;
    }
}
if ($periodosQuadro === []) {
    $periodosQuadro = [1, 2, 3, 4];
}
$rotuloPeriodoCampo = mb_strtolower((string) ($infoPeriodo['rotulo_campo'] ?? 'bimestre'), 'UTF-8');
$fichas = is_array($fichas ?? null) ? $fichas : [];
$somenteLeitura = !empty($boletim_somente_leitura);
$notasSomenteLeitura = $somenteLeitura || !empty($boletim_notas_somente_leitura);
$editavelFicha = !$notasSomenteLeitura && $ficha && ($ficha['status'] ?? '') !== 'homologada';
$fichaHomologada = (bool) $ficha && ($ficha['status'] ?? '') === 'homologada';
$podeAlterarBoletim = !$notasSomenteLeitura && !empty($admin_permissions['vida_escolar']['alterar']);
$podeObsBoletim = !$somenteLeitura;
$rotuloStatusFicha = [
    'homologada' => 'Homologada',
    'em_curso' => 'Em curso',
    'fechada' => 'Fechada',
];
$rotuloAcao = [
    'homologar' => 'Homologou',
    'reabrir' => 'Reabriu',
    'salvar_celula' => 'Salvou nota',
    'importar_externa' => 'Importou de outra escola',
    'importar_meio_ano' => 'Lançou notas de outra escola',
    'fechar_bimestre' => 'Fechou bimestre',
    'alimentar_calculo' => 'Sincronizou eventos',
    'alimentar' => 'Sincronizou eventos',
    'garantir_ficha' => 'Criou a ficha',
];
$layoutExibicao = is_array($quadro['layout_exibicao'] ?? null) ? $quadro['layout_exibicao'] : null;
$layoutFaixas = is_array($layoutExibicao['faixas'] ?? null) ? $layoutExibicao['faixas'] : [];
$layoutMinima = (float) ($layoutExibicao['media_minima'] ?? 6);
$layoutFormula = (string) ($layoutExibicao['formula_final_media'] ?? '');
$layoutNome = trim((string) ($layoutExibicao['nome'] ?? ''));
$escalaNota = (string) ($quadro['escala_nota'] ?? ($layoutExibicao['escala'] ?? '10'));
$escalaMax = \App\Modulos\RegrasAcademicas\Services\EscalaNota::maximo($escalaNota);
$quadroImpressaoHtml = trim((string) ($quadro_impressao_html ?? ''));
$usarQuadroImpressaoPronto = $quadroImpressaoHtml !== '' && stripos($quadroImpressaoHtml, '<table') !== false;
$moduloEventosAvaliativos = class_exists(\App\Core\LayoutHelper::class)
    && \App\Core\LayoutHelper::isModuleEnabled('eventos_avaliativos');
// Quadro do assistente sobe via AJAX (montagem pesada); placeholder evita cair no Quadro SM.
$quadroImpressaoLazy = !$usarQuadroImpressaoPronto
    && $moduloEventosAvaliativos
    && is_array($ficha)
    && (int) ($ficha['id'] ?? $ficha_id ?? 0) > 0;
$usarQuadroImpressao = $usarQuadroImpressaoPronto || $quadroImpressaoLazy;
$alunoIdQuadro = (int) ($aluno_id ?? $student['id'] ?? 0);
$fichaIdQuadro = (int) ($ficha['id'] ?? $ficha_id ?? 0);
$fmtNota = static function ($c): string {
    if (!is_array($c)) {
        return '—';
    }
    if (!empty($c['conceito'])) {
        return htmlspecialchars((string) $c['conceito'], ENT_QUOTES, 'UTF-8');
    }
    if ($c['nota'] === null || $c['nota'] === '') {
        return '—';
    }
    return number_format((float) $c['nota'], 1, ',', '');
};
?>
<?php if (count($fichas) > 1): ?>
<div class="mb-4 border-b border-gray-200">
    <nav class="flex flex-wrap gap-1 -mb-px" aria-label="Boletins do aluno">
    <?php foreach ($fichas as $f): ?>
        <?php
            $fichaAtiva = (int) $ficha_id === (int) ($f['id'] ?? 0);
            $abaRotulo = trim((string) ($f['aba_rotulo'] ?? ''));
            if ($abaRotulo === '') {
                $abaRotulo = trim((string) (($f['ano_letivo'] ?? '') . ' · ' . ($f['turma_nome'] ?? 'turma')));
            }
            $ehExtra = (($f['boletim_finalidade'] ?? '') === 'complementar')
                || (($f['curso_tipo'] ?? '') === 'extra');
            $pillComLink = !$somenteLeitura || (isset($hrefAba) && is_callable($hrefAba));
            $hrefFicha = isset($hrefAba) && is_callable($hrefAba)
                ? $hrefAba('boletim', ['ficha_id' => (int) $f['id']])
                : ($base . '?aba=boletim&ficha_id=' . (int) $f['id']);
            $classeAba = $fichaAtiva
                ? 'border-primary text-primary'
                : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300';
        ?>
        <?php if (!$pillComLink): ?>
        <span class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 <?= $classeAba ?>">
        <?php else: ?>
        <a href="<?= $esc($hrefFicha) ?>"
           class="inline-flex items-center gap-2 px-4 py-2.5 text-sm font-medium border-b-2 <?= $classeAba ?>"
           <?= $fichaAtiva ? 'aria-current="page"' : '' ?>>
        <?php endif; ?>
            <span><?= $esc($abaRotulo) ?></span>
            <?php if ($ehExtra): ?>
            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold uppercase tracking-wide <?= $fichaAtiva ? 'bg-amber-100 text-amber-800' : 'bg-gray-100 text-gray-600' ?>">Extra</span>
            <?php endif; ?>
            <span class="text-xs <?= $fichaAtiva ? 'text-primary/70' : 'text-gray-400' ?>"><?= (int) ($f['ano_letivo'] ?? 0) ?></span>
        <?php if (!$pillComLink): ?></span><?php else: ?></a><?php endif; ?>
    <?php endforeach; ?>
    </nav>
</div>
<?php elseif (count($fichas) === 1): ?>
<?php
    $fUnica = $fichas[0];
    $rotuloUnico = trim((string) ($fUnica['aba_rotulo'] ?? ''));
    if ($rotuloUnico === '') {
        $rotuloUnico = trim((string) (($fUnica['turma_nome'] ?? 'turma')));
    }
?>
<p class="mb-3 text-xs text-gray-500"><?= $esc($rotuloUnico) ?> · <?= (int) ($fUnica['ano_letivo'] ?? 0) ?></p>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <div class="flex justify-between items-center mb-4 gap-3 flex-wrap">
        <div>
            <h3 class="text-lg font-semibold text-gray-900"><?= $layoutNome !== '' ? $esc($layoutNome) : 'Boletim oficial' ?></h3>
            <p class="text-sm text-gray-500"><?php
                $modeloNome = $layoutNome !== '' ? $layoutNome : trim((string) ($quadro['modelo_nome'] ?? ''));
                if ($modeloNome !== '') {
                    echo 'Modelo: <span class="font-medium text-gray-700">' . $esc($modeloNome) . '</span>. ';
                } else {
                    echo 'Conforme o modelo de boletim oficial da turma. ';
                }
                if ($usarQuadroImpressao) {
                    echo 'Mesmo layout do boletim criado (médias e faltas por período). ';
                } elseif ($layoutFaixas !== []) {
                    echo 'As colunas seguem o layout deste boletim. ';
                } else {
                    echo 'Cada ' . $esc($rotuloPeriodoCampo) . ': nota à esquerda, falta à direita. ';
                }
            ?><?php if (!$usarQuadroImpressao): ?><span class="text-violet-700">¹</span> = escola anterior.<?php endif; ?></p>
            <?php if ($notasSomenteLeitura && !$somenteLeitura): ?>
            <p class="mt-2 text-sm text-slate-600">Notas somente leitura. Para registrar comentário use a observação abaixo.</p>
            <?php elseif ($fichaHomologada && !$somenteLeitura): ?>
            <p class="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Boletim homologado — notas travadas. Para corrigir, reabra o período abaixo (motivo obrigatório; fica na auditoria).</p>
            <?php elseif ($fichaHomologada): ?>
            <p class="mt-2 text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">Boletim homologado.</p>
            <?php endif; ?>
        </div>
        <?php if (!$somenteLeitura): ?>
        <div class="flex flex-wrap gap-2">
            <?php if (!$notasSomenteLeitura): ?>
            <form method="post" action="<?= $base ?>/garantir">
                <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                <button class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Criar ficha do ano</button>
            </form>
            <?php endif; ?>
            <?php if ($ficha): ?>
            <a href="<?= $base ?>/pdf?ficha_id=<?= (int) $ficha['id'] ?>" download class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Baixar boletim (PDF)</a>
            <?php endif; ?>
            <?php if ($ficha && $editavelFicha): ?>
            <form method="post" action="<?= $base ?>/alimentar">
                <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                <input type="hidden" name="periodo_ref" value="">
                <input type="hidden" name="ficha_id" value="<?= (int) $ficha['id'] ?>">
                <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Sincronizar eventos</button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$quadro && !$usarQuadroImpressao): ?>
        <p class="text-sm text-gray-500"><?= $somenteLeitura ? 'Nenhum boletim disponível.' : 'Nenhuma ficha neste ano. Vincule o aluno a uma turma ou clique em “Criar ficha do ano”.' ?></p>
    <?php else: ?>
        <?php if ($usarQuadroImpressao): ?>
        <div class="ve-boletim-zoom mb-2 flex flex-wrap items-center gap-2 <?= $quadroImpressaoLazy ? 'hidden' : '' ?>" data-ve-boletim-zoom>
            <span class="text-xs text-gray-500 mr-1">Zoom do boletim</span>
            <button type="button" data-ve-boletim-zoom-out
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    title="Diminuir" aria-label="Diminuir zoom">
                <i class="fa-solid fa-magnifying-glass-minus text-sm" aria-hidden="true"></i>
            </button>
            <span class="min-w-[3.25rem] text-center text-xs font-semibold text-gray-700 tabular-nums" data-ve-boletim-zoom-label>100%</span>
            <button type="button" data-ve-boletim-zoom-in
                    class="inline-flex items-center justify-center w-8 h-8 rounded-lg border border-gray-300 bg-white text-gray-700 hover:bg-gray-50"
                    title="Ampliar" aria-label="Ampliar zoom">
                <i class="fa-solid fa-magnifying-glass-plus text-sm" aria-hidden="true"></i>
            </button>
            <button type="button" data-ve-boletim-zoom-fit
                    class="inline-flex items-center gap-1.5 px-2.5 h-8 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50"
                    title="Ajustar à largura da tela">
                <i class="fa-solid fa-expand text-xs" aria-hidden="true"></i>
                Caber na tela
            </button>
        </div>
        <div class="ve-boletim-fit w-full overflow-auto border border-gray-200 rounded-lg bg-white max-h-[70vh]"
             data-ve-boletim-fit
             data-ve-quadro-lazy="<?= $quadroImpressaoLazy ? '1' : '0' ?>"
             data-ve-quadro-loaded="<?= $usarQuadroImpressaoPronto ? '1' : '0' ?>"
             data-aluno-id="<?= $alunoIdQuadro ?>"
             data-ficha-id="<?= $fichaIdQuadro ?>">
            <div data-ve-boletim-fit-scroll>
                <div class="ve-boletim-fit-scale origin-top-left inline-block" data-ve-boletim-fit-scale>
                    <?php if ($quadroImpressaoLazy): ?>
                    <div class="p-8 text-center text-slate-500 text-sm" data-ve-quadro-loading>
                        <i class="fa-solid fa-spinner fa-spin mr-2" aria-hidden="true"></i>
                        Carregando boletim…
                    </div>
                    <?php else: ?>
                    <?= $quadroImpressaoHtml ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <style>
            [data-ve-boletim-fit] .boletim-quadro-tabela,
            [data-ve-boletim-fit] table {
                font-size: 11px !important;
            }
            [data-ve-boletim-fit] th,
            [data-ve-boletim-fit] td {
                padding: 3px 4px !important;
                white-space: nowrap;
            }
            [data-ve-boletim-fit] .overflow-x-auto {
                overflow: visible !important;
            }
        </style>
        <script>
        function initVeBoletimZoom() {
            var root = document.querySelector('[data-ve-boletim-fit]');
            var bar = document.querySelector('[data-ve-boletim-zoom]');
            if (!root || !bar || root.getAttribute('data-ve-zoom-ready') === '1') return;
            var scrollBox = root.querySelector('[data-ve-boletim-fit-scroll]');
            var inner = root.querySelector('[data-ve-boletim-fit-scale]');
            var label = bar.querySelector('[data-ve-boletim-zoom-label]');
            var btnIn = bar.querySelector('[data-ve-boletim-zoom-in]');
            var btnOut = bar.querySelector('[data-ve-boletim-zoom-out]');
            var btnFit = bar.querySelector('[data-ve-boletim-zoom-fit]');
            if (!inner || !scrollBox) return;
            if (!inner.querySelector('table')) return;

            root.setAttribute('data-ve-zoom-ready', '1');
            bar.classList.remove('hidden');

            var fitScale = 1;
            var naturalW = 0;
            var naturalH = 0;
            var userScale = null;
            var passo = 0.1;
            var minZoom = 0.35;
            var maxZoom = 2.5;

            var medirNatural = function () {
                inner.style.transform = 'none';
                scrollBox.style.width = 'auto';
                scrollBox.style.height = 'auto';
                naturalW = Math.max(inner.scrollWidth || 0, inner.offsetWidth || 0);
                naturalH = Math.max(inner.scrollHeight || 0, inner.offsetHeight || 0);
                var disponivel = root.clientWidth || root.offsetWidth || 0;
                if (disponivel <= 0 || naturalW <= 0) {
                    fitScale = 1;
                    return;
                }
                fitScale = Math.min(1, disponivel / naturalW);
                if (fitScale < minZoom) fitScale = minZoom;
            };

            var aplicar = function () {
                medirNatural();
                var escala = userScale === null ? fitScale : userScale;
                escala = Math.max(minZoom, Math.min(maxZoom, escala));
                inner.style.transformOrigin = 'top left';
                inner.style.transform = (escala >= 0.999 && escala <= 1.001) ? '' : ('scale(' + escala + ')');
                var visW = Math.ceil(naturalW * escala);
                var visH = Math.ceil(naturalH * escala);
                scrollBox.style.width = visW > 0 ? visW + 'px' : 'auto';
                scrollBox.style.height = visH > 0 ? visH + 'px' : 'auto';
                if (label) label.textContent = Math.round(escala * 100) + '%';
            };

            var zoomPor = function (delta) {
                medirNatural();
                var base = userScale === null ? fitScale : userScale;
                userScale = Math.max(minZoom, Math.min(maxZoom, Math.round((base + delta) * 100) / 100));
                aplicar();
            };

            if (btnIn) btnIn.addEventListener('click', function () { zoomPor(passo); });
            if (btnOut) btnOut.addEventListener('click', function () { zoomPor(-passo); });
            if (btnFit) btnFit.addEventListener('click', function () {
                userScale = null;
                aplicar();
            });

            aplicar();
            if (typeof ResizeObserver !== 'undefined') {
                new ResizeObserver(function () {
                    if (userScale === null) aplicar();
                }).observe(root);
            } else {
                window.addEventListener('resize', function () {
                    if (userScale === null) aplicar();
                });
            }
            window.addEventListener('load', aplicar);
            setTimeout(aplicar, 50);
            setTimeout(aplicar, 250);
        }

        function carregarVeQuadroImpressaoSobDemanda() {
            var root = document.querySelector('[data-ve-boletim-fit]');
            if (!root || root.getAttribute('data-ve-quadro-lazy') !== '1') {
                initVeBoletimZoom();
                return;
            }
            if (root.getAttribute('data-ve-quadro-loaded') === '1') {
                initVeBoletimZoom();
                return;
            }
            var alunoId = parseInt(root.getAttribute('data-aluno-id') || '0', 10) || 0;
            var fichaId = parseInt(root.getAttribute('data-ficha-id') || '0', 10) || 0;
            var inner = root.querySelector('[data-ve-boletim-fit-scale]');
            if (alunoId <= 0 || !inner) return;
            root.setAttribute('data-ve-quadro-loaded', '1');
            var url = <?= json_encode(URL . '/admin/students/', JSON_UNESCAPED_SLASHES) ?> + alunoId + '/tab/quadro-impressao';
            if (fichaId > 0) url += '?ficha_id=' + fichaId;
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) {
                    if (!r.ok) { throw new Error('HTTP ' + r.status); }
                    return r.text();
                })
                .then(function (html) {
                    if (!html || html.indexOf('<table') === -1) {
                        inner.innerHTML = '<p class="p-6 text-sm text-slate-500">Boletim de impressão indisponível para esta ficha.</p>';
                        return;
                    }
                    inner.innerHTML = html;
                    initVeBoletimZoom();
                })
                .catch(function () {
                    root.setAttribute('data-ve-quadro-loaded', '0');
                    inner.innerHTML = '<div class="p-6 text-center text-red-600 text-sm">Erro ao carregar boletim. <button type="button" class="underline" onclick="document.querySelector(\'[data-ve-boletim-fit]\').setAttribute(\'data-ve-quadro-loaded\',\'0\');carregarVeQuadroImpressaoSobDemanda();">Tentar novamente</button></div>';
                });
        }

        if (document.querySelector('[data-ve-boletim-fit][data-ve-quadro-lazy="1"]')) {
            // Só busca quando a subaba Boletim estiver visível (selecionarVeAba / init).
            var painelBol = document.getElementById('ve-painel-boletim');
            if (painelBol && !painelBol.classList.contains('hidden')) {
                carregarVeQuadroImpressaoSobDemanda();
            }
        } else {
            initVeBoletimZoom();
        }
        </script>
        <?php elseif ($layoutFaixas !== []): ?>
        <?php
            $layoutCalc = new \App\Modulos\EventosAvaliativos\Models\BoletimLayout();
            $layoutColunas = 0;
            $pecasPorPeriodo = [];
            foreach ($layoutFaixas as $faixaLayout) {
                $layoutColunas += count($faixaLayout['colunas'] ?? []);
                foreach ($faixaLayout['colunas'] ?? [] as $colPeriodo) {
                    $periodoPeca = (int) ($colPeriodo['periodo'] ?? 0);
                    $nomePeca = (string) ($colPeriodo['peca'] ?? '');
                    if ($periodoPeca > 0 && $nomePeca !== '') {
                        $pecasPorPeriodo[$periodoPeca][$nomePeca] = true;
                    }
                }
            }
            $txtSituacao = static function ($nota, float $minima) use ($esc): string {
                $aprovado = \App\Modulos\RegrasAcademicas\Services\EscalaNota::aprovado(
                    ($nota === null || $nota === '') ? null : (float) $nota,
                    $minima
                );
                if ($aprovado === null) {
                    return '—';
                }
                $classe = $aprovado ? 'text-emerald-700' : 'text-red-700';
                return '<span class="font-medium ' . $classe . '">' . $esc($aprovado ? 'Aprovado' : 'Reprovado') . '</span>';
            };
        ?>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase" rowspan="2">Componente</th>
                        <?php foreach ($layoutFaixas as $faixaLayout): ?>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="<?= count($faixaLayout['colunas']) ?>"><?= $esc($faixaLayout['nome'] ?? '') ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <?php foreach ($layoutFaixas as $faixaLayout): ?>
                            <?php foreach ($faixaLayout['colunas'] as $colLayout): ?>
                                <th class="px-2 py-1 text-center text-[10px] text-gray-400"><?= $esc($colLayout['nome'] ?? '') ?></th>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($grid as $row):
                        $linha = $row['linha'];
                        $cels = $row['celulas'];
                        $mediasPeriodo = [];
                        $faltasPeriodo = [];
                        foreach ($cels as $numCel => $celLayout) {
                            $numCel = (int) $numCel;
                            if ($numCel < 1 || !is_array($celLayout)) {
                                continue;
                            }
                            $mediasPeriodo[$numCel] = ($celLayout['nota'] === null || $celLayout['nota'] === '') ? null : (float) $celLayout['nota'];
                            $faltasPeriodo[$numCel] = ($celLayout['faltas'] === null || $celLayout['faltas'] === '') ? null : (int) $celLayout['faltas'];
                        }
                        $finalMedia = $layoutCalc->calcularFormula($layoutFormula, $mediasPeriodo);
                        $faltasPreenchidas = array_filter($faltasPeriodo, static fn ($v) => $v !== null);
                        $finalFaltas = $faltasPreenchidas === [] ? null : array_sum($faltasPreenchidas);
                        $formsAbertos = [];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap"><?= $esc($linha['componente_nome'] ?? '') ?></td>
                        <?php foreach ($layoutFaixas as $faixaLayout): ?>
                            <?php foreach ($faixaLayout['colunas'] as $colLayout): ?>
                            <?php
                                $peca = (string) ($colLayout['peca'] ?? '');
                                $periodoCol = (int) ($colLayout['periodo'] ?? 0);
                                $c = $periodoCol > 0 ? ($cels[$periodoCol] ?? null) : null;
                                $aberta = is_array($c) && in_array($c['status'] ?? '', ['aberta', 'reaberta'], true);
                                $editavel = $aberta && $editavelFicha && in_array($peca, ['media', 'faltas'], true);
                                $formId = is_array($c) ? ('ve-cel-' . (int) $c['id']) : '';
                                $temMediaNoPeriodo = !empty($pecasPorPeriodo[$periodoCol]['media']);
                                $temFaltasNoPeriodo = !empty($pecasPorPeriodo[$periodoCol]['faltas']);
                                $abriuForm = false;
                            ?>
                            <td class="px-1.5 py-1.5 text-center whitespace-nowrap tabular-nums">
                                <?php if ($editavel && $formId !== '' && !isset($formsAbertos[$periodoCol])): ?>
                                    <?php $formsAbertos[$periodoCol] = true; $abriuForm = true; ?>
                                    <form id="<?= $esc($formId) ?>" method="post" action="<?= $base ?>/celula/<?= (int) $c['id'] ?>"></form>
                                    <input type="hidden" form="<?= $esc($formId) ?>" name="_token" value="<?= $esc($token) ?>">
                                <?php endif; ?>
                                <?php if ($peca === 'media' && $periodoCol > 0): ?>
                                    <?php if ($editavel): ?>
                                    <span class="inline-flex items-center justify-center gap-1">
                                        <?php $notaCampo = $c['nota'] !== null && $c['nota'] !== '' ? number_format((float) $c['nota'], 1, ',', '') : ''; ?>
                                        <input form="<?= $esc($formId) ?>" name="nota" value="<?= $esc($notaCampo) ?>"<?= $escalaMax === null ? '' : ' data-escala-max="' . (int) $escalaMax . '" data-nota-salva="' . $esc($notaCampo) . '"' ?> class="w-14 h-8 border border-gray-300 rounded-md px-1 text-center text-sm tabular-nums" placeholder="—" inputmode="decimal">
                                        <?php if (($c['origem'] ?? '') === 'externa'): ?><sup class="text-violet-700">¹</sup><?php endif; ?>
                                        <?php if ($abriuForm): ?><button type="submit" form="<?= $esc($formId) ?>" class="text-indigo-600 hover:text-indigo-800 p-0.5" title="Salvar" aria-label="Salvar"><i class="fa-solid fa-check text-xs"></i></button><?php endif; ?>
                                    </span>
                                    <?php if (!$temFaltasNoPeriodo): ?>
                                    <input type="hidden" form="<?= $esc($formId) ?>" name="faltas" value="<?= $c['faltas'] !== null && $c['faltas'] !== '' ? (int) $c['faltas'] : '' ?>">
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <span class="font-medium text-gray-900"><?= $fmtNota($c) ?></span><?php if (is_array($c) && ($c['origem'] ?? '') === 'externa' && $fmtNota($c) !== '—'): ?><sup class="text-violet-700">¹</sup><?php endif; ?>
                                    <?php endif; ?>
                                <?php elseif ($peca === 'faltas' && $periodoCol > 0): ?>
                                    <?php if ($editavel): ?>
                                    <span class="inline-flex items-center justify-center gap-1">
                                    <input form="<?= $esc($formId) ?>" name="faltas" value="<?= $c['faltas'] !== null && $c['faltas'] !== '' ? (int) $c['faltas'] : '' ?>" class="w-12 h-8 border border-gray-300 rounded-md px-1 text-center text-sm tabular-nums" placeholder="—">
                                    <?php if ($abriuForm): ?><button type="submit" form="<?= $esc($formId) ?>" class="text-indigo-600 hover:text-indigo-800 p-0.5" title="Salvar" aria-label="Salvar"><i class="fa-solid fa-check text-xs"></i></button><?php endif; ?>
                                    </span>
                                    <?php if (!$temMediaNoPeriodo): ?>
                                    <input type="hidden" form="<?= $esc($formId) ?>" name="nota" value="<?= $c['nota'] !== null && $c['nota'] !== '' ? $esc(number_format((float) $c['nota'], 1, ',', '')) : '' ?>">
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <?= ($faltasPeriodo[$periodoCol] ?? null) === null ? '—' : (int) $faltasPeriodo[$periodoCol] ?>
                                    <?php endif; ?>
                                <?php elseif ($peca === 'situacao' && $periodoCol > 0): ?>
                                    <?= $txtSituacao($mediasPeriodo[$periodoCol] ?? null, $layoutMinima) ?>
                                <?php elseif ($peca === 'media' && $periodoCol === 0): ?>
                                    <span class="font-medium text-gray-900"><?= $finalMedia === null ? '—' : $esc(number_format($finalMedia, 1, ',', '')) ?></span>
                                <?php elseif ($peca === 'faltas' && $periodoCol === 0): ?>
                                    <?= $finalFaltas === null ? '—' : (int) $finalFaltas ?>
                                <?php elseif ($peca === 'situacao' && $periodoCol === 0): ?>
                                    <?= $txtSituacao($finalMedia, $layoutMinima) ?>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($grid === []): ?>
                    <tr><td colspan="<?= 1 + $layoutColunas ?>" class="px-3 py-6 text-center text-gray-500">Ficha sem componentes. Vincule uma matriz curricular à turma.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php else: ?>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase">Componente</th>
                        <?php foreach ($periodosQuadro as $p): ?>
                            <th class="px-3 py-2 text-center text-xs font-medium text-gray-500 uppercase" colspan="2"><?= $esc($periodos[$p] ?? $p) ?></th>
                        <?php endforeach; ?>
                    </tr>
                    <tr>
                        <th></th>
                        <?php foreach ($periodosQuadro as $p): ?>
                            <th class="px-2 py-1 text-center text-[10px] text-gray-400">Nota</th>
                            <th class="px-2 py-1 text-center text-[10px] text-gray-400">Falta</th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($grid as $row):
                        $linha = $row['linha'];
                        $cels = $row['celulas'];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-3 py-2 font-medium text-gray-900 whitespace-nowrap"><?= $esc($linha['componente_nome'] ?? '') ?></td>
                        <?php foreach ($periodosQuadro as $p):
                            $c = $cels[$p] ?? null;
                            $origem = (string) ($c['origem'] ?? 'vazia');
                            $aberta = $c && in_array($c['status'] ?? '', ['aberta', 'reaberta'], true) && $p !== 0;
                            $formId = $c ? ('ve-cel-' . (int) $c['id']) : '';
                            $faltasTxt = (is_array($c) && isset($c['faltas']) && $c['faltas'] !== null && $c['faltas'] !== '')
                                ? (string) (int) $c['faltas']
                                : '—';
                        ?>
                            <?php if ($aberta && $editavelFicha): ?>
                            <td class="px-1.5 py-1.5 text-center whitespace-nowrap">
                                <form id="<?= $esc($formId) ?>" method="post" action="<?= $base ?>/celula/<?= (int) $c['id'] ?>"></form>
                                <input type="hidden" form="<?= $esc($formId) ?>" name="_token" value="<?= $esc($token) ?>">
                                <span class="inline-flex items-center justify-center gap-1">
                                    <?php $notaCampo = $c['nota'] !== null && $c['nota'] !== '' ? number_format((float) $c['nota'], 1, ',', '') : ''; ?>
                                    <input form="<?= $esc($formId) ?>" name="nota" value="<?= $esc($notaCampo) ?>"<?= $escalaMax === null ? '' : ' data-escala-max="' . (int) $escalaMax . '" data-nota-salva="' . $esc($notaCampo) . '"' ?> class="w-14 h-8 border border-gray-300 rounded-md px-1 text-center text-sm tabular-nums" placeholder="—" inputmode="decimal">
                                    <?php if ($origem === 'externa'): ?><sup class="text-violet-700">¹</sup><?php endif; ?>
                                    <button type="submit" form="<?= $esc($formId) ?>" class="text-indigo-600 hover:text-indigo-800 p-0.5" title="Salvar" aria-label="Salvar">
                                        <i class="fa-solid fa-check text-xs"></i>
                                    </button>
                                </span>
                            </td>
                            <td class="px-1.5 py-1.5 text-center">
                                <input form="<?= $esc($formId) ?>" name="faltas" value="<?= $c['faltas'] !== null && $c['faltas'] !== '' ? (int) $c['faltas'] : '' ?>" class="w-12 h-8 border border-gray-300 rounded-md px-1 text-center text-sm tabular-nums" placeholder="—">
                            </td>
                            <?php else:
                                $notaTxt = $fmtNota($c);
                            ?>
                            <td class="px-2 py-2 text-center whitespace-nowrap tabular-nums">
                                <span class="font-medium text-gray-900"><?= $notaTxt ?></span><?php if ($origem === 'externa' && $notaTxt !== '—'): ?><sup class="text-violet-700">¹</sup><?php endif; ?>
                            </td>
                            <td class="px-2 py-2 text-center text-gray-600 tabular-nums"><?= $esc($faltasTxt) ?></td>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                    <?php if ($grid === []): ?>
                    <tr><td colspan="<?= 1 + (count($periodosQuadro) * 2) ?>" class="px-3 py-6 text-center text-gray-500">Ficha sem componentes. Vincule uma matriz curricular à turma.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>

        <?php if ($podeObsBoletim): ?>
            <?php
            $obsPartial = \App\Core\ViewPaths::dominio('Pessoas', 'Views/admin/students/_bloco_observacao_boletim.php');
            if (is_file($obsPartial)) {
                include $obsPartial;
            }
            ?>
        <?php else: ?>
            <?php $obsLeitura = trim((string) (($boletim_observacao['conteudo'] ?? '') ?: '')); ?>
            <?php if ($obsLeitura !== ''): ?>
            <div class="mt-6 rounded-xl border border-gray-200 bg-white p-5">
                <h3 class="text-base font-semibold text-gray-900 mb-3">Observação</h3>
                <p class="text-sm text-gray-800 whitespace-pre-wrap break-words"><?= $esc($obsLeitura) ?></p>
            </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($podeAlterarBoletim): ?>
        <div class="mt-6 grid grid-cols-1 <?= $editavelFicha ? 'lg:grid-cols-2' : '' ?> gap-4">
            <?php if ($editavelFicha): ?>
            <div class="rounded-xl border border-gray-200 bg-slate-50 p-4">
                <h4 class="text-sm font-semibold text-gray-900">Encerrar período</h4>
                <p class="text-xs text-gray-500 mt-0.5 mb-3">Fecha um <?= $esc($rotuloPeriodoCampo) ?> ou homologa o ano e trava as notas.</p>
                <div class="flex flex-col sm:flex-row sm:items-end gap-3">
                    <form method="post" action="<?= $base ?>/fechar-bimestre" class="flex-1 min-w-0 flex items-end gap-2">
                        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                        <input type="hidden" name="ficha_id" value="<?= (int) $ficha['id'] ?>">
                        <div class="flex-1 min-w-0">
                            <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($infoPeriodo['rotulo_campo']) ?></label>
                            <select name="bimestre" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <?= \App\Core\PeriodoLetivo::optionsHtml($anoFicha, 0) ?>
                            </select>
                        </div>
                        <button class="shrink-0 h-10 px-4 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Fechar</button>
                    </form>
                    <form method="post" action="<?= $base ?>/homologar" class="shrink-0" onsubmit="return confirm('Homologar o boletim deste ano? As células preenchidas serão travadas.');">
                        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                        <input type="hidden" name="ficha_id" value="<?= (int) $ficha['id'] ?>">
                        <label class="hidden sm:block text-sm font-medium text-gray-700 mb-1">&nbsp;</label>
                        <button class="h-10 px-4 rounded-lg btn-primary-custom text-sm font-semibold">Homologar ano</button>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            <div class="rounded-xl border <?= $fichaHomologada ? 'border-amber-200 bg-amber-50/60' : 'border-gray-200 bg-slate-50' ?> p-4">
                <h4 class="text-sm font-semibold text-gray-900">Corrigir notas</h4>
                <p class="text-xs text-gray-500 mt-0.5 mb-3">Reabre o período travado. O motivo é obrigatório e fica na auditoria.</p>
                <form method="post" action="<?= $base ?>/reabrir" class="grid grid-cols-1 sm:grid-cols-12 gap-3 items-end" onsubmit="return confirm('Reabrir para correção? A versão atual permanece na auditoria.');">
                    <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                    <input type="hidden" name="ficha_id" value="<?= (int) $ficha['id'] ?>">
                    <div class="sm:col-span-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Período</label>
                            <select name="bimestre" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                                <option value="-1">Ano inteiro</option>
                                <?= \App\Core\PeriodoLetivo::optionsHtml($anoFicha, 0) ?>
                            </select>
                    </div>
                    <div class="sm:col-span-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Motivo</label>
                        <input name="motivo" required placeholder="Ex.: correção de digitação" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
                    </div>
                    <div class="sm:col-span-3">
                        <button class="w-full h-10 px-4 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Reabrir</button>
                    </div>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$somenteLeitura && !empty($quadro['auditoria'])): ?>
        <div class="mt-6 rounded-xl border border-gray-200 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                <h4 class="text-sm font-semibold text-gray-900">Auditoria</h4>
            </div>
            <ul class="divide-y divide-gray-100 max-h-56 overflow-y-auto">
                <?php foreach ($quadro['auditoria'] as $log): ?>
                    <?php $acao = (string) ($log['acao'] ?? ''); ?>
                    <li class="px-4 py-2.5 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span class="text-gray-500 tabular-nums w-36 shrink-0"><?= !empty($log['created_at']) ? $esc(date('d/m/Y H:i', strtotime((string) $log['created_at']))) : '—' ?></span>
                        <span class="font-medium text-gray-900 w-44 shrink-0"><?= $esc($rotuloAcao[$acao] ?? $acao) ?></span>
                        <span class="text-gray-600 min-w-0">
                            <?= $esc($log['usuario_nome'] ?? '') ?>
                            <?php if (!empty($log['motivo'])): ?>
                                <span class="text-gray-400"> — <?= $esc($log['motivo']) ?></span>
                            <?php endif; ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php if (!$somenteLeitura && $escalaMax !== null): ?>
<script>
(function () {
    function lerNota(bruto) {
        var texto = String(bruto == null ? '' : bruto).trim().replace(',', '.');
        if (texto === '') return { vazia: true };
        if (!/^-?\d+(?:\.\d+)?$/.test(texto)) return { invalida: true };
        return { nota: Math.round(parseFloat(texto) * 100) / 100 };
    }
    function mostrarAviso(input, texto) {
        var id = input.getAttribute('data-aviso-nota');
        var el = id ? document.getElementById(id) : null;
        if (!el) {
            el = document.createElement('p');
            el.id = 'aviso-nota-' + Math.random().toString(36).slice(2, 10);
            el.className = 'mt-1 text-xs text-red-600 text-left';
            input.setAttribute('data-aviso-nota', el.id);
            input.insertAdjacentElement('afterend', el);
        }
        el.textContent = texto || '';
        el.classList.toggle('hidden', !texto);
    }
    document.querySelectorAll('form[id^="ve-cel-"]').forEach(function (form) {
        form.addEventListener('submit', function (evento) {
            var nota = document.querySelector('input[name="nota"][form="' + form.id + '"]');
            if (!nota || !nota.hasAttribute('data-escala-max')) return;
            var maximo = parseFloat(nota.getAttribute('data-escala-max'));
            var lida = lerNota(nota.value);
            if (lida.vazia || !(lida.invalida || lida.nota < 0 || lida.nota > maximo)) {
                mostrarAviso(nota, '');
                return;
            }
            evento.preventDefault();
            nota.value = nota.getAttribute('data-nota-salva') || '';
            mostrarAviso(nota, lida.invalida
                ? 'Informe a nota com número. A vírgula vale como decimal.'
                : 'A nota precisa estar entre 0 e ' + String(maximo) + '.');
        });
    });
})();
</script>
<?php endif; ?>
