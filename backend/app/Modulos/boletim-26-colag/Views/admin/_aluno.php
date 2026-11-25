<?php
$alunoRel = is_array($relatorio['aluno'] ?? null) ? $relatorio['aluno'] : null;
$turmaRel = is_array($relatorio['turma'] ?? null) ? $relatorio['turma'] : null;
$demonstrativos = is_array($relatorio['demonstrativos'] ?? null) ? $relatorio['demonstrativos'] : [];
$nomeAluno = $alunoRel !== null ? htmlspecialchars((string) ($alunoRel['nome'] ?? ''), ENT_QUOTES, 'UTF-8') : '';
$serieTurma = $rotuloSerie((int) ($turmaRel['ano_serie'] ?? 0));
$alunoPainelId = (int) ($alunoRel['id'] ?? 0);
?>
<?php foreach ($demonstrativos as $indice => $demo): ?>
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
<section class="mb-8 boletim-painel <?= ($bimestresAbas !== [] && $bimestre !== 1 && $bimestre > 0) ? 'hidden' : '' ?>" data-boletim-painel="<?= $bimestre ?>" id="<?= $bimestre > 0 ? 'bimestre-' . $bimestre . '-aluno-' . $alunoPainelId : 'modelo-' . (int) ($demo['serie'] ?? 0) ?>">
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
            if (!empty($linhaFiltro['filha']) && $linhaTemNotaAbaixo($linhaFiltro)) {
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
    if ($notaMinima !== null && $linhas === []) {
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
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="Prova bimestral">P. Bim</th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="Jornada do aluno">Jor. Aluno</th>
                    <?php if (!$terceira): ?>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?>>Média</th>
                    <?php endif; ?>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?>>Enac</th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="Média bimestral">Méd. Bim</th>
                    <th class="<?= $cabeca ?>"<?= $semanas !== [] ? ' rowspan="2"' : '' ?> title="Recuperação semestral">Rec. Sem</th>
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
                    <?php if (!$terceira): ?>
                    <?= $celNota($linha['media'] ?? null, 'media') ?>
                    <?php endif; ?>
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
