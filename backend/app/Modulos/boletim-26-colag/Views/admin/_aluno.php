<?php
$alunoRel = is_array($relatorio['aluno'] ?? null) ? $relatorio['aluno'] : null;
$turmaRel = is_array($relatorio['turma'] ?? null) ? $relatorio['turma'] : null;
$demonstrativos = is_array($relatorio['demonstrativos'] ?? null) ? $relatorio['demonstrativos'] : [];
$nomeAluno = $alunoRel !== null ? htmlspecialchars((string) ($alunoRel['nome'] ?? ''), ENT_QUOTES, 'UTF-8') : '';
$serieTurma = $rotuloSerie((int) ($turmaRel['ano_serie'] ?? 0));
$alunoPainelId = (int) ($alunoRel['id'] ?? 0);
$exibir = (string) ($exibir ?? ($_GET['exibir'] ?? 'notas')) === 'boletim' ? 'boletim' : 'notas';
$frentePortugues = static function (array $linha): bool {
    if (empty($linha['filha'])) {
        return false;
    }
    $texto = mb_strtolower(trim((string) ($linha['nome'] ?? '') . ' ' . (string) ($linha['pai_nome'] ?? '')), 'UTF-8');
    $texto = strtr($texto, ['á' => 'a', 'à' => 'a', 'ã' => 'a', 'â' => 'a', 'é' => 'e', 'ê' => 'e', 'í' => 'i', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ú' => 'u', 'ç' => 'c']);

    return str_contains($texto, 'gramatica')
        || str_contains($texto, 'literatura')
        || str_contains($texto, 'leitura')
        || str_contains($texto, 'lingua portuguesa')
        || str_contains($texto, 'portugues');
};
?>
<?php foreach (($somentePortal ?? '') === 'boletim' ? [] : $demonstrativos as $indice => $demo): ?>
<?php
$demoTemAbaixo = $notaMinima === null;
if (!$demoTemAbaixo) {
    foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $blocoDemo) {
        foreach (is_array($blocoDemo['linhas'] ?? null) ? $blocoDemo['linhas'] : [] as $linhaDemo) {
            if ($linhaTemNotaAbaixo($linhaDemo)) {
                $demoTemAbaixo = true;
                break 2;
            }
        }
    }
}
if (!$demoTemAbaixo) {
    continue;
}
?>
<?php $bimestre = (int) ($demo['bimestre'] ?? 0); ?>
<?php $primeiraAba = (int) ($bimestresAbas[0] ?? 0); ?>
<section class="mb-8 boletim-painel vista-notas <?= $exibir === 'boletim' ? 'hidden' : '' ?> <?= ($bimestresAbas !== [] && $primeiraAba > 0 && $bimestre !== $primeiraAba) ? 'hidden' : '' ?>" data-boletim-painel="<?= $bimestre ?>" id="<?= $bimestre > 0 ? 'bimestre-' . $bimestre . '-aluno-' . $alunoPainelId : 'modelo-' . (int) ($demo['serie'] ?? 0) ?>">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">
        <?php if (!empty($demo['titulo'])): ?>
        <?= htmlspecialchars((string) $demo['titulo'], ENT_QUOTES, 'UTF-8') ?>
        <?php else: ?>
        Notas — <?= $bimestre ?>º Bimestre — <?= htmlspecialchars($serieTurma, ENT_QUOTES, 'UTF-8') ?>
        <?php endif; ?>
        <?php if ($nomeAluno !== ''): ?>
        <span class="block text-sm font-normal text-gray-600"><?= $nomeAluno ?></span>
        <?php endif; ?>
    </h3>
    <?php foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $bloco): ?>
    <?php
    $semanas = is_array($bloco['semanas'] ?? null) ? $bloco['semanas'] : [];
    $linhas = is_array($bloco['linhas'] ?? null) ? $bloco['linhas'] : [];
    $terceira = !empty($demo['terceira']);
    if ($notaMinima !== null) {
        $paisAbaixo = [];
        foreach ($linhas as $linhaFiltro) {
            if (!empty($linhaFiltro['filha']) && !$frentePortugues($linhaFiltro) && $linhaTemNotaAbaixo($linhaFiltro)) {
                $paisAbaixo[(string) ($linhaFiltro['pai_nome'] ?? '')] = true;
            }
        }
        $linhas = array_values(array_filter(
            $linhas,
            static function (array $linhaFiltro) use ($linhaTemNotaAbaixo, $paisAbaixo): bool {
                if ($linhaTemNotaAbaixo($linhaFiltro)) {
                    return true;
                }

                return empty($linhaFiltro['filha']) && isset($paisAbaixo[(string) ($linhaFiltro['nome'] ?? '')]);
            }
        ));
    }
    $linhas = array_values(array_filter(
        $linhas,
        static function (array $linhaVisivel) use ($frentePortugues): bool {
            return !$frentePortugues($linhaVisivel);
        }
    ));
    if ($linhas === []) {
        continue;
    }
    ?>
    <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white mb-4">
        <?php $cabeca = 'px-2 py-2 uppercase font-semibold whitespace-nowrap'; ?>
        <table class="boletim-colag min-w-full text-sm text-center">
            <thead>
                <tr>
                    <th class="<?= $cabeca ?> px-3 text-left"<?= $semanas !== [] ? ' rowspan="2"' : '' ?>><?= htmlspecialchars((string) ($bloco['titulo'] ?? 'Matérias'), ENT_QUOTES, 'UTF-8') ?></th>
                    <?php foreach ($semanas as $semana): ?>
                    <th class="<?= $cabeca ?>" colspan="2">S<?= (int) $semana ?></th>
                    <?php endforeach; ?>
                    <?php if ($semanas !== []): ?>
                    <th class="<?= $cabeca ?>" colspan="2">Total</th>
                    <th class="<?= $cabeca ?>" rowspan="2" title="Média semanal">Méd. Sem</th>
                    <?php endif; ?>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="<?= $terceira ? 'Avaliação bimestral' : 'Prova bimestral' ?>"><?= $terceira ? 'Aval. Bim' : 'P. Bim' ?></th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="Jornada do aluno"><?= $terceira ? 'Jornada' : 'Jor. Aluno' ?></th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="<?= $terceira ? 'Média da avaliação bimestral com a jornada' : 'Média' ?>">Média</th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?>>Enac</th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="<?= $terceira ? 'Média final com o ENAC' : 'Média bimestral' ?>"><?= $terceira ? 'Méd. Final' : 'Méd. Bim' ?></th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="Recuperação semestral"><?= $terceira ? 'Rec' : 'Rec. Sem' ?></th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="Resultado final">Res. Final</th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="Faltas">Fal</th>
                </tr>
                <?php if ($semanas !== []): ?>
                <tr>
                    <?php foreach ($semanas as $semana): ?>
                    <th class="<?= $cabeca ?> py-1">N</th>
                    <th class="<?= $cabeca ?> py-1">Q</th>
                    <?php endforeach; ?>
                    <th class="<?= $cabeca ?> py-1">N</th>
                    <th class="<?= $cabeca ?> py-1">Q</th>
                </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php foreach ($linhas as $linha): ?>
                <?php $filha = !empty($linha['filha']); ?>
                <tr>
                    <td class="px-3 py-2 text-left <?= $filha ? 'pl-8 text-gray-700' : 'font-semibold text-gray-900' ?>">
                        <?php if ($filha): ?><span class="text-gray-400 mr-1">↳</span><?php endif; ?>
                        <?= htmlspecialchars((string) ($linha['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    </td>
                    <?php foreach ($semanas as $semana): ?>
                    <?php $cel = is_array($linha['semanas'][$semana] ?? null) ? $linha['semanas'][$semana] : []; ?>
                    <td class="px-2 py-2"><?= $fmtInt($cel['n'] ?? null) ?></td>
                    <td class="px-2 py-2"><?= $fmtInt($cel['q'] ?? null) ?></td>
                    <?php endforeach; ?>
                    <?php if ($semanas !== []): ?>
                    <td class="px-2 py-2 font-semibold"><?= $fmtInt($linha['total_n'] ?? null) ?></td>
                    <td class="px-2 py-2 font-semibold"><?= $fmtInt($linha['total_q'] ?? null) ?></td>
                    <?= $celNota($linha['media_sem'] ?? null, 'media_sem') ?>
                    <?php endif; ?>
                    <?= $celNota($linha['prova_bim'] ?? null, 'prova_bim') ?>
                    <?= $celNota($linha['jornada'] ?? null, 'jornada') ?>
                    <?= $celNota($linha['media'] ?? null, 'media') ?>
                    <?= $celNota($linha['enac'] ?? null, 'enac') ?>
                    <?= $celNota($linha['media_bim'] ?? null, 'media_bim') ?>
                    <?= $celNota($linha['rec'] ?? null, 'rec') ?>
                    <?= $celNota($linha['resultado'] ?? null, 'resultado') ?>
                    <td class="px-2 py-2"><?= $fmtInt($linha['faltas'] ?? null) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($linhas === []): ?>
                <tr><td class="px-3 py-4 text-gray-500" colspan="16"><?= $notaMinima !== null ? 'Nenhuma ' . htmlspecialchars($notaCampoNome, ENT_QUOTES, 'UTF-8') . ' abaixo de ' . htmlspecialchars($notaMinimaTexto, ENT_QUOTES, 'UTF-8') . '.' : 'Nenhuma matéria deste bloco.' ?></td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php
if (($somentePortal ?? '') === 'notas') {
    return;
}
$gradeBoletim = [];
$ordemBoletim = [];
$bimestresBoletim = [];
foreach ($demonstrativos as $demoBoletim) {
    $bimestreGrade = (int) ($demoBoletim['bimestre'] ?? 0);
    if ($bimestreGrade <= 0) {
        continue;
    }
    $temLinha = false;
    foreach (is_array($demoBoletim['blocos'] ?? null) ? $demoBoletim['blocos'] : [] as $blocoBoletim) {
        foreach (is_array($blocoBoletim['linhas'] ?? null) ? $blocoBoletim['linhas'] : [] as $linhaBoletim) {
            if (!is_array($linhaBoletim) || $frentePortugues($linhaBoletim)) {
                continue;
            }
            if ($notaMinima !== null && !$linhaTemNotaAbaixo($linhaBoletim) && !empty($linhaBoletim['filha'])) {
                continue;
            }
            if ($notaMinima !== null && !$linhaTemNotaAbaixo($linhaBoletim) && empty($linhaBoletim['filha'])) {
                $filhaAbaixo = false;
                foreach (is_array($blocoBoletim['linhas'] ?? null) ? $blocoBoletim['linhas'] : [] as $irmaBoletim) {
                    if (!is_array($irmaBoletim) || empty($irmaBoletim['filha'])) {
                        continue;
                    }
                    if ($frentePortugues($irmaBoletim)) {
                        continue;
                    }
                    if ((string) ($irmaBoletim['pai_nome'] ?? '') === (string) ($linhaBoletim['nome'] ?? '') && $linhaTemNotaAbaixo($irmaBoletim)) {
                        $filhaAbaixo = true;
                        break;
                    }
                }
                if (!$filhaAbaixo) {
                    continue;
                }
            }
            $nomeGrade = (string) ($linhaBoletim['nome'] ?? '');
            if ($nomeGrade === '') {
                continue;
            }
            if (!isset($gradeBoletim[$nomeGrade])) {
                $gradeBoletim[$nomeGrade] = [];
                $ordemBoletim[] = $nomeGrade;
            }
            $gradeBoletim[$nomeGrade][$bimestreGrade] = [
                'resultado' => $linhaBoletim['resultado'] ?? null,
                'faltas' => $linhaBoletim['faltas'] ?? null,
            ];
            $temLinha = true;
        }
    }
    if ($temLinha) {
        $bimestresBoletim[$bimestreGrade] = true;
    }
}
$bimestresBoletim = array_keys($bimestresBoletim);
sort($bimestresBoletim);
?>
<?php if ($ordemBoletim !== [] && $bimestresBoletim !== []): ?>
<section class="mb-8 vista-boletim <?= $exibir === 'notas' ? 'hidden' : '' ?>">
    <h3 class="text-lg font-semibold text-gray-900 mb-3">
        Boletim — <?= htmlspecialchars($serieTurma, ENT_QUOTES, 'UTF-8') ?>
        <?php if ($nomeAluno !== ''): ?>
        <span class="block text-sm font-normal text-gray-600"><?= $nomeAluno ?></span>
        <?php endif; ?>
    </h3>
    <?php $cabecaBoletim = 'px-2 py-2 uppercase font-semibold whitespace-nowrap'; ?>
    <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
        <table class="boletim-colag min-w-full text-sm text-center">
            <thead>
                <tr>
                    <th class="<?= $cabecaBoletim ?> px-3 text-left" rowspan="2">Componente</th>
                    <?php foreach ($bimestresBoletim as $bimestreColuna): ?>
                    <th class="<?= $cabecaBoletim ?>" colspan="2"><?= (int) $bimestreColuna ?>º</th>
                    <?php endforeach; ?>
                    <th class="<?= $cabecaBoletim ?>" rowspan="2" title="Média dos 4 bimestres">Média</th>
                </tr>
                <tr>
                    <?php foreach ($bimestresBoletim as $bimestreColuna): ?>
                    <th class="<?= $cabecaBoletim ?>">Nota</th>
                    <th class="<?= $cabecaBoletim ?>">Falta</th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($ordemBoletim as $nomeGrade): ?>
                <tr>
                    <td class="px-3 py-2 text-left font-semibold text-gray-900 whitespace-nowrap"><?= htmlspecialchars($nomeGrade, ENT_QUOTES, 'UTF-8') ?></td>
                    <?php foreach ($bimestresBoletim as $bimestreColuna): ?>
                    <?php
                    $celulaGrade = $gradeBoletim[$nomeGrade][$bimestreColuna] ?? ['resultado' => null, 'faltas' => null];
                    $notaGrade = $celulaGrade['resultado'];
                    $notaBaixa = $notaMinima !== null && is_numeric($notaGrade) && (float) $notaGrade < $notaMinima;
                    ?>
                    <td class="px-2 py-2 <?= $notaBaixa ? 'vermelho' : (is_numeric($notaGrade) ? 'nota' : '') ?>"><?= $fmt($notaGrade) ?></td>
                    <td class="px-2 py-2"><?= $fmtInt($celulaGrade['faltas']) ?></td>
                    <?php endforeach; ?>
                    <?php
                    $somaMedia = 0.0;
                    $qtdMedia = 0;
                    foreach ($bimestresBoletim as $bimestreMedia) {
                        $notaMedia = $gradeBoletim[$nomeGrade][$bimestreMedia]['resultado'] ?? null;
                        if (!is_numeric($notaMedia)) {
                            continue;
                        }
                        $somaMedia += (float) $notaMedia;
                        $qtdMedia++;
                    }
                    $mediaAnual = $qtdMedia > 0
                        ? (round(($somaMedia / $qtdMedia) * 2, 0, PHP_ROUND_HALF_UP) / 2)
                        : null;
                    $mediaBaixa = $notaCampo === 'media_anual' && $notaMinima !== null && $mediaAnual !== null && $mediaAnual < $notaMinima;
                    ?>
                    <td class="px-2 py-2 font-semibold <?= $mediaBaixa ? 'vermelho' : ($mediaAnual !== null ? 'nota' : '') ?>"><?= $fmt($mediaAnual) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($alunoPainelId > 0 && empty($somentePortal)): ?>
    <?php
    $obsBoletim = '';
    try {
        $obsCfg = new \App\Modulos\Boletins\Models\BoletimConfig();
        $obsLinha = $obsCfg->getObservacaoCoordenacao($alunoPainelId);
        $obsBoletim = trim((string) (is_array($obsLinha) ? ($obsLinha['conteudo'] ?? '') : ''));
    } catch (\Throwable $e) {
        $obsBoletim = '';
    }
    ?>
    <div class="mt-4 rounded-xl border border-gray-200 bg-white p-4" data-obs-aluno="<?= $alunoPainelId ?>">
        <h4 class="text-sm font-semibold text-gray-900 mb-2">Observação</h4>
        <textarea rows="3" maxlength="5000" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm" placeholder="Observação que fica no boletim deste aluno"><?= htmlspecialchars($obsBoletim, ENT_QUOTES, 'UTF-8') ?></textarea>
        <div class="mt-2 flex items-center gap-3">
            <button type="button" data-salvar-obs class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-semibold hover:bg-blue-700">Salvar observação</button>
            <span data-obs-status class="text-xs text-gray-500"></span>
        </div>
    </div>
    <?php endif; ?>
</section>
<?php endif; ?>
