<?php
$painel = is_array($painel ?? null) ? $painel : [];
$turmas = is_array($painel['turmas'] ?? null) ? $painel['turmas'] : [];
$alunos = is_array($painel['alunos'] ?? null) ? $painel['alunos'] : [];
$demonstrativos = is_array($painel['demonstrativos'] ?? null) ? $painel['demonstrativos'] : [];
$ano = (int) ($painel['ano'] ?? date('Y'));
$turmaId = (int) ($painel['turma_id'] ?? 0);
$alunoId = (int) ($painel['aluno_id'] ?? 0);
$aluno = is_array($painel['aluno'] ?? null) ? $painel['aluno'] : null;
$turma = is_array($painel['turma'] ?? null) ? $painel['turma'] : null;

$fmt = static function ($valor): string {
    if ($valor === null || $valor === '') {
        return '—';
    }
    if (!is_numeric($valor)) {
        return '—';
    }
    return number_format((float) $valor, 1, ',', '');
};
$fmtInt = static function ($valor): string {
    if ($valor === null || $valor === '') {
        return '—';
    }
    return (string) (int) $valor;
};
$rotuloSerie = static function (int $serie): string {
    if ($serie === 1) {
        return '1ª Série EM';
    }
    if ($serie === 2) {
        return '2ª Série EM';
    }
    if ($serie === 3) {
        return '3ª Série EM';
    }
    return 'Ensino Médio';
};
?>
<div class="mb-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-1">Boletim - colag</h2>
    <p class="text-gray-600">Demonstrativo desta escola. A conta já está feita. A 1ª e a 2ª série separam Bloco A e Bloco B. A 3ª série fica numa lista só.</p>
</div>

<form method="get" action="<?= URL ?>/admin/boletim-26-colag" class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <label class="block text-sm">
            <span class="font-medium text-gray-700">Ano</span>
            <select name="ano" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2">
                <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 2; $y--): ?>
                <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
                <?php endfor; ?>
            </select>
        </label>
        <label class="block text-sm">
            <span class="font-medium text-gray-700">Turma</span>
            <select name="turma_id" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2">
                <option value="0">Escolha a turma</option>
                <?php foreach ($turmas as $item): ?>
                <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $turmaId ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    · <?= htmlspecialchars($rotuloSerie((int) ($item['ano_serie'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block text-sm md:col-span-2">
            <span class="font-medium text-gray-700">Aluno</span>
            <select name="aluno_id" class="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2">
                <option value="0">Escolha o aluno</option>
                <?php foreach ($alunos as $item): ?>
                <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $alunoId ? 'selected' : '' ?>>
                    <?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                    <?php if ((string) ($item['ra'] ?? '') !== ''): ?>
                    · RA <?= htmlspecialchars((string) $item['ra'], ENT_QUOTES, 'UTF-8') ?>
                    <?php endif; ?>
                </option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="mt-4">
        <button type="submit" class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-medium">Abrir demonstrativo</button>
    </div>
</form>

<?php
$nomeAluno = $aluno !== null ? htmlspecialchars((string) ($aluno['nome'] ?? ''), ENT_QUOTES, 'UTF-8') : '';
$serieTurma = $rotuloSerie((int) ($turma['ano_serie'] ?? 0));
$temAbas = false;
foreach ($demonstrativos as $demoAba) {
    if ((int) ($demoAba['bimestre'] ?? 0) > 0) {
        $temAbas = true;
        break;
    }
}
?>
<?php if ($aluno === null): ?>
<p class="text-sm text-gray-600 mb-6">Este é o modelo do demonstrativo. Escolha a turma e o aluno para preencher com as notas.</p>
<?php endif; ?>
<?php if ($temAbas): ?>
<div class="flex flex-wrap gap-2 mb-6" role="tablist" aria-label="Bimestres">
    <?php foreach ($demonstrativos as $indice => $demoAba): ?>
    <?php $bimestreAba = (int) ($demoAba['bimestre'] ?? 0); ?>
    <button type="button"
            class="boletim-aba px-4 py-1.5 rounded-full text-sm font-medium <?= $indice === 0 ? 'bg-blue-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>"
            role="tab"
            data-boletim-aba="<?= $bimestreAba ?>"
            aria-selected="<?= $indice === 0 ? 'true' : 'false' ?>">
        <?= $bimestreAba ?>º bimestre
    </button>
    <?php endforeach; ?>
</div>
<?php endif; ?>
<?php foreach ($demonstrativos as $indice => $demo): ?>
<?php $bimestre = (int) ($demo['bimestre'] ?? 0); ?>
<section class="mb-8 boletim-painel <?= ($temAbas && $indice > 0) ? 'hidden' : '' ?>" data-boletim-painel="<?= $bimestre ?>" id="<?= $bimestre > 0 ? 'bimestre-' . $bimestre : 'modelo-' . (int) ($demo['serie'] ?? 0) ?>">
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
    ?>
    <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white mb-4">
        <table class="min-w-full text-sm text-center">
            <thead>
                <tr class="bg-blue-900 text-white">
                    <th class="px-3 py-2 text-left" rowspan="2"><?= htmlspecialchars((string) ($bloco['titulo'] ?? 'Matérias'), ENT_QUOTES, 'UTF-8') ?></th>
                    <?php if ($semanas !== []): ?>
                    <th class="px-2 py-2" colspan="<?= count($semanas) * 2 ?>">Prova Semanal</th>
                    <th class="px-2 py-2" colspan="2">Total</th>
                    <th class="px-2 py-2" rowspan="2">Média Sem</th>
                    <?php endif; ?>
                    <th class="px-2 py-2 whitespace-nowrap" rowspan="2">Prova Bim</th>
                    <th class="px-2 py-2 whitespace-nowrap" rowspan="2" title="Jornada do aluno">Jor. Aluno</th>
                    <?php if (!$terceira): ?>
                    <th class="px-2 py-2" rowspan="2">MÉDIA</th>
                    <?php endif; ?>
                    <th class="px-2 py-2" rowspan="2">ENAC</th>
                    <th class="px-2 py-2 whitespace-nowrap" rowspan="2">Média Bim</th>
                    <th class="px-2 py-2 whitespace-nowrap" rowspan="2" title="Recuperação Semestral">Rec Sem</th>
                    <th class="px-2 py-2 whitespace-nowrap" rowspan="2" title="Resultado Final">Res. Final</th>
                    <th class="px-2 py-2" rowspan="2">FAL</th>
                </tr>
                <tr class="bg-blue-800 text-white text-xs">
                    <?php foreach ($semanas as $semana): ?>
                    <th class="px-2 py-1" colspan="2">S<?= (int) $semana ?></th>
                    <?php endforeach; ?>
                    <?php if ($semanas !== []): ?>
                    <th class="px-2 py-1">N</th><th class="px-2 py-1">Q</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($linhas as $linha): ?>
                <?php $filha = !empty($linha['filha']); ?>
                <tr class="border-t border-gray-200 <?= $filha ? 'bg-gray-50' : 'bg-white' ?>">
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
                    <td class="px-2 py-2"><?= $fmt($linha['media_sem'] ?? null) ?></td>
                    <?php endif; ?>
                    <td class="px-2 py-2"><?= $fmt($linha['prova_bim'] ?? null) ?></td>
                    <td class="px-2 py-2"><?= $fmt($linha['jornada'] ?? null) ?></td>
                    <?php if (!$terceira): ?>
                    <td class="px-2 py-2"><?= $fmt($linha['media'] ?? null) ?></td>
                    <?php endif; ?>
                    <td class="px-2 py-2"><?= $fmt($linha['enac'] ?? null) ?></td>
                    <td class="px-2 py-2"><?= $fmt($linha['media_bim'] ?? null) ?></td>
                    <td class="px-2 py-2"><?= $fmt($linha['rec'] ?? null) ?></td>
                    <td class="px-2 py-2 font-semibold text-emerald-700"><?= $fmt($linha['resultado'] ?? null) ?></td>
                    <td class="px-2 py-2"><?= $fmtInt($linha['faltas'] ?? null) ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if ($linhas === []): ?>
                <tr><td class="px-3 py-4 text-gray-500" colspan="16">Nenhuma matéria deste bloco.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php endforeach; ?>
</section>
<?php endforeach; ?>
<?php if ($temAbas): ?>
<script>
document.querySelectorAll('[data-boletim-aba]').forEach(function (botao) {
    botao.addEventListener('click', function () {
        var alvo = botao.getAttribute('data-boletim-aba');
        document.querySelectorAll('[data-boletim-aba]').forEach(function (item) {
            var ativo = item === botao;
            item.classList.toggle('bg-blue-900', ativo);
            item.classList.toggle('text-white', ativo);
            item.classList.toggle('bg-gray-100', !ativo);
            item.classList.toggle('text-gray-700', !ativo);
            item.setAttribute('aria-selected', ativo ? 'true' : 'false');
        });
        document.querySelectorAll('[data-boletim-painel]').forEach(function (painel) {
            painel.classList.toggle('hidden', painel.getAttribute('data-boletim-painel') !== alvo);
        });
    });
});
</script>
<?php endif; ?>
