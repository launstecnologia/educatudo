<?php
$painel = is_array($painel ?? null) ? $painel : [];
$turmas = is_array($painel['turmas'] ?? null) ? $painel['turmas'] : [];
$alunos = is_array($painel['alunos'] ?? null) ? $painel['alunos'] : [];
$relatorios = is_array($painel['relatorios'] ?? null) ? $painel['relatorios'] : [];
$ano = (int) ($painel['ano'] ?? date('Y'));
$serieFiltro = (int) ($painel['serie'] ?? 0);
$bimestreFiltro = (int) ($painel['bimestre'] ?? 0);
$notaMinima = is_numeric($painel['nota_minima'] ?? null) ? (float) $painel['nota_minima'] : null;
$notaMinimaTexto = $notaMinima === null ? '' : number_format($notaMinima, 1, ',', '');
$notaCampo = (string) ($painel['nota_campo'] ?? 'resultado') === 'media_bim' ? 'media_bim' : 'resultado';
$notaCampoNome = $notaCampo === 'media_bim' ? 'média bimestral' : 'resultado final';
$abrir = !empty($painel['abrir']);
$homologacao = is_array($painel['homologacao'] ?? null) ? $painel['homologacao'] : [];
$homologacaoEm = '';
if ((string) ($homologacao['em'] ?? '') !== '') {
    $quando = strtotime((string) $homologacao['em']);
    $homologacaoEm = $quando ? date('d/m/Y H:i', $quando) : (string) $homologacao['em'];
}
$turmaId = (int) ($painel['turma_id'] ?? 0);
$alunoId = (int) ($painel['aluno_id'] ?? 0);
$filtrosAtivos = 0;
if ($serieFiltro > 0) {
    $filtrosAtivos++;
}
if ($turmaId > 0) {
    $filtrosAtivos++;
}
if ($alunoId > 0) {
    $filtrosAtivos++;
}
if ($bimestreFiltro > 0) {
    $filtrosAtivos++;
}
if ($notaMinima !== null) {
    $filtrosAtivos++;
}
$reabrirFiltro = (string) ($_GET['filtro_aberto'] ?? '') === '1' && !$abrir;

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
$celNota = static function ($valor, string $campo = '') use ($fmt, $notaMinima, $notaCampo): string {
    $texto = $fmt($valor);
    if ($texto === '—') {
        return '<td class="px-2 py-2">—</td>';
    }
    $abaixo = $notaMinima !== null
        && $campo === $notaCampo
        && is_numeric($valor)
        && (float) $valor < $notaMinima;

    return '<td class="px-2 py-2 ' . ($abaixo ? 'abaixo' : 'nota') . '">' . $texto . '</td>';
};
$linhaTemNotaAbaixo = static function (array $linha) use ($notaMinima, $notaCampo): bool {
    if ($notaMinima === null) {
        return true;
    }

    return is_numeric($linha[$notaCampo] ?? null) && (float) $linha[$notaCampo] < $notaMinima;
};
$relatorioTemAbaixo = static function (array $relatorio) use ($linhaTemNotaAbaixo): bool {
    foreach (is_array($relatorio['demonstrativos'] ?? null) ? $relatorio['demonstrativos'] : [] as $demo) {
        foreach (is_array($demo['blocos'] ?? null) ? $demo['blocos'] : [] as $bloco) {
            foreach (is_array($bloco['linhas'] ?? null) ? $bloco['linhas'] : [] as $linha) {
                if ($linhaTemNotaAbaixo($linha)) {
                    return true;
                }
            }
        }
    }

    return false;
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
<style>
.boletim-colag { border-collapse: collapse; }
.boletim-colag th,
.boletim-colag td { border: 1px solid #dbe4ee; }
.boletim-colag thead th {
    background: #1e3a8a;
    color: #fff;
    border-color: rgba(255, 255, 255, .45);
    font-size: 11px;
    letter-spacing: .04em;
    line-height: 1.2;
}
.boletim-colag thead tr:nth-child(2) th { font-size: 10px; padding-top: 4px; padding-bottom: 4px; }
.boletim-colag tbody tr:nth-child(even) td { background: #f4f8fc; }
.boletim-colag tbody tr:nth-child(odd) td { background: #fff; }
.boletim-colag td.nota { color: #047857; font-weight: 600; }
.boletim-colag tbody tr td.abaixo { color: #b45309; font-weight: 600; background: #fff7ed; }
</style>
<div id="boletim-carregando" hidden style="position:fixed;inset:0;z-index:80;display:none;align-items:center;justify-content:center;background:rgba(255,255,255,.78);">
    <div class="bg-white rounded-xl shadow-lg px-6 py-4 text-sm font-medium text-gray-800">Carregando o demonstrativo...</div>
</div>
<div class="mb-6 flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        <h2 class="text-2xl font-bold text-gray-900 mb-1">Notas do Aluno</h2>
    </div>
    <div class="flex items-center gap-3 flex-wrap">
        <button type="button" data-csp-click="openBoletimFiltro"
                class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
            <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
        <?php if ($abrir): ?>
        <button type="submit" form="boletim-filtros" formmethod="get" formaction="<?= URL ?>/admin/boletim-26-colag/exportar" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-file-excel mr-2 text-gray-500"></i>
            Exportar Excel
        </button>
        <button type="submit" form="boletim-filtros" formmethod="post" formaction="<?= URL ?>/admin/boletim-26-colag/homologar" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-stamp mr-2 text-gray-500"></i>
            Homologar
        </button>
        <?php endif; ?>
    </div>
</div>

<div id="boletimFiltroBackdrop" class="fixed inset-0 bg-black/40 z-40 <?= $reabrirFiltro ? '' : 'hidden' ?>" data-csp-click="closeBoletimFiltro"></div>
<aside id="boletimFiltroDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform <?= $reabrirFiltro ? '' : 'translate-x-full' ?> transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="<?= $reabrirFiltro ? 'false' : 'true' ?>">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar demonstrativo</h3>
        <button type="button" data-csp-click="closeBoletimFiltro" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/admin/boletim-26-colag" id="boletim-filtros" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="boletim_ano" class="block text-sm font-medium text-gray-700 mb-1.5">Ano</label>
                <select id="boletim_ano" name="ano" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php for ($y = (int) date('Y') + 1; $y >= (int) date('Y') - 2; $y--): ?>
                    <option value="<?= $y ?>" <?= $y === $ano ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            <div>
                <label for="boletim_serie" class="block text-sm font-medium text-gray-700 mb-1.5">Série</label>
                <select id="boletim_serie" name="serie" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Escolha a série</option>
                    <?php foreach ([1, 2, 3] as $serieOpcao): ?>
                    <option value="<?= $serieOpcao ?>" <?= $serieOpcao === $serieFiltro ? 'selected' : '' ?>><?= htmlspecialchars($rotuloSerie($serieOpcao), ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="boletim_turma" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="boletim_turma" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0"><?= $serieFiltro > 0 ? 'Todas as turmas' : 'Escolha a turma' ?></option>
                    <?php foreach ($turmas as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $turmaId ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        · <?= htmlspecialchars($rotuloSerie((int) ($item['ano_serie'] ?? 0)), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="boletim_aluno" class="block text-sm font-medium text-gray-700 mb-1.5">Aluno</label>
                <select id="boletim_aluno" name="aluno_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0"><?= ($serieFiltro > 0 || $turmaId > 0) ? 'Todos os alunos' : 'Escolha o aluno' ?></option>
                    <?php foreach ($alunos as $item): ?>
                    <option value="<?= (int) $item['id'] ?>" <?= (int) $item['id'] === $alunoId ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($turmaId === 0 && (string) ($item['turma_nome'] ?? '') !== ''): ?>
                        · <?= htmlspecialchars((string) $item['turma_nome'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                        <?php if ((string) ($item['ra'] ?? '') !== ''): ?>
                        · RA <?= htmlspecialchars((string) $item['ra'], ENT_QUOTES, 'UTF-8') ?>
                        <?php endif; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="boletim_bimestre" class="block text-sm font-medium text-gray-700 mb-1.5">Bimestre</label>
                <select id="boletim_bimestre" name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0" <?= $bimestreFiltro === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ([1, 2, 3, 4] as $bimestreOpcao): ?>
                    <option value="<?= $bimestreOpcao ?>" <?= $bimestreOpcao === $bimestreFiltro ? 'selected' : '' ?>><?= $bimestreOpcao ?>º bimestre</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="boletim_nota_campo" class="block text-sm font-medium text-gray-700 mb-1.5">Nota mínima em</label>
                <select id="boletim_nota_campo" name="nota_campo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="media_bim" <?= $notaCampo === 'media_bim' ? 'selected' : '' ?>>Média bimestral</option>
                    <option value="resultado" <?= $notaCampo === 'resultado' ? 'selected' : '' ?>>Resultado final</option>
                </select>
            </div>
            <div>
                <label for="boletim_nota_minima" class="block text-sm font-medium text-gray-700 mb-1.5">Nota mínima</label>
                <input type="text" id="boletim_nota_minima" name="nota_minima" inputmode="decimal" value="<?= htmlspecialchars($notaMinimaTexto, ENT_QUOTES, 'UTF-8') ?>" placeholder="Ex.: 6,5" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                <span class="mt-1 block text-xs text-gray-500">Deixe vazio para ver todas. O cálculo da linha continua visível.</span>
            </div>
        </div>
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ($_SESSION['csrf_token'] ?? '')), ENT_QUOTES, 'UTF-8') ?>" disabled>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= URL ?>/admin/boletim-26-colag" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors text-center">Limpar</a>
            <button type="submit" name="abrir" value="1" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">Aplicar filtro</button>
        </div>
    </form>
</aside>

<?php if ($abrir && ($serieFiltro > 0 || $turmaId > 0)): ?>
<?php $estadoHomologacao = (string) ($homologacao['estado'] ?? 'nenhuma'); ?>
<?php if ($estadoHomologacao === 'alterada'): ?>
<p class="mb-6 rounded-lg px-4 py-3 text-sm" style="background:#fff7ed;color:#9a3412;border:1px solid #fdba74;">Houve alteração de nota desde a homologação de <?= htmlspecialchars($homologacaoEm, ENT_QUOTES, 'UTF-8') ?>. Homologue de novo.</p>
<?php elseif ($estadoHomologacao === 'igual'): ?>
<p class="mb-6 rounded-lg px-4 py-3 text-sm" style="background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;">Homologado em <?= htmlspecialchars($homologacaoEm, ENT_QUOTES, 'UTF-8') ?><?= (string) ($homologacao['usuario'] ?? '') !== '' ? ' por ' . htmlspecialchars((string) $homologacao['usuario'], ENT_QUOTES, 'UTF-8') : '' ?>. Nenhuma nota mudou desde então.</p>
<?php elseif ($estadoHomologacao === 'sem_tabela'): ?>
<p class="mb-6 rounded-lg px-4 py-3 text-sm" style="background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;">A homologação ainda não está disponível neste ambiente. Rode a migration do boletim Colag no Master.</p>
<?php else: ?>
<p class="mb-6 text-sm text-gray-600">Este resultado ainda não foi homologado.</p>
<?php endif; ?>
<?php endif; ?>

<?php
$primeiroRelatorio = $relatorios[0] ?? null;
$modeloVazio = !is_array($primeiroRelatorio) || !is_array($primeiroRelatorio['aluno'] ?? null);
$bimestresAbas = (!$modeloVazio && $bimestreFiltro === 0) ? [1, 2, 3, 4] : [];
?>
<?php if (!$abrir): ?>
<div class="bg-white rounded-xl border border-gray-200 px-6 py-16 text-center mb-6">
    <p class="text-base font-medium text-gray-800">Aplique o filtro para ver o demonstrativo.</p>
    <?php if ($serieFiltro > 0 || $turmaId > 0): ?>
    <p class="mt-2 text-sm text-gray-500"><?= count($alunos) ?> aluno(s) nesta lista.</p>
    <?php endif; ?>
    <button type="button" data-csp-click="openBoletimFiltro" class="mt-5 inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
        Filtros
    </button>
</div>
<?php elseif ($abrir && ($serieFiltro > 0 || $turmaId > 0)): ?>
<p class="text-sm text-gray-600 mb-6">
    <?php if ($alunoId > 0): ?>
    Um aluno.
    <?php else: ?>
    Todos os alunos<?= $serieFiltro > 0 && $turmaId === 0 ? ' da série' : ' da turma' ?>.
    <?php endif; ?>
    <?php if ($notaMinima !== null): ?>
    Só as matérias com <?= htmlspecialchars($notaCampoNome, ENT_QUOTES, 'UTF-8') ?> abaixo de <?= htmlspecialchars($notaMinimaTexto, ENT_QUOTES, 'UTF-8') ?>.
    <?php endif; ?>
</p>
<?php endif; ?>
<?php
if ($abrir && $notaMinima !== null) {
    $relatorios = array_values(array_filter($relatorios, $relatorioTemAbaixo));
}
?>
<?php if ($abrir && $notaMinima !== null && $relatorios === []): ?>
<p class="text-sm text-gray-600 mb-6">Nenhum aluno com <?= htmlspecialchars($notaCampoNome, ENT_QUOTES, 'UTF-8') ?> abaixo de <?= htmlspecialchars($notaMinimaTexto, ENT_QUOTES, 'UTF-8') ?>.</p>
<?php endif; ?>
<?php if ($bimestresAbas !== []): ?>
<div class="flex flex-wrap gap-2 mb-6" role="tablist" aria-label="Bimestres">
    <?php foreach ($bimestresAbas as $indice => $bimestreAba): ?>
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
<?php if ($abrir): ?>
<?php foreach ($relatorios as $relatorio): ?>
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
<?php endforeach; ?>
<?php endif; ?>
<script>
function openBoletimFiltro() {
    var fundo = document.getElementById('boletimFiltroBackdrop');
    var painel = document.getElementById('boletimFiltroDrawer');
    if (!fundo || !painel) return;
    fundo.classList.remove('hidden');
    painel.classList.remove('translate-x-full');
    painel.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeBoletimFiltro() {
    var fundo = document.getElementById('boletimFiltroBackdrop');
    var painel = document.getElementById('boletimFiltroDrawer');
    if (!fundo || !painel) return;
    fundo.classList.add('hidden');
    painel.classList.add('translate-x-full');
    painel.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function (evento) {
    if (evento.key === 'Escape') {
        closeBoletimFiltro();
    }
});
(function () {
    var form = document.getElementById('boletim-filtros');
    if (!form) return;
    function carregando() {
        var tela = document.getElementById('boletim-carregando');
        if (!tela) return;
        tela.hidden = false;
        tela.style.display = 'flex';
    }
    form.addEventListener('submit', function (evento) {
        var botao = evento.submitter;
        var destino = botao ? (botao.getAttribute('formaction') || '') : '';
        if (destino.indexOf('/exportar') !== -1) {
            return;
        }
        if (destino.indexOf('/homologar') !== -1) {
            var token = form.querySelector('[name="_token"]');
            if (token) token.disabled = false;
        }
        carregando();
    });
    function aoMudar(nome, limpar) {
        var campo = form.querySelector('[name="' + nome + '"]');
        if (!campo) return;
        campo.addEventListener('change', function () {
            limpar.forEach(function (alvo) {
                var item = form.querySelector('[name="' + alvo + '"]');
                if (item) item.value = '0';
            });
            var aberto = document.createElement('input');
            aberto.type = 'hidden';
            aberto.name = 'filtro_aberto';
            aberto.value = '1';
            form.appendChild(aberto);
            carregando();
            form.submit();
        });
    }
    aoMudar('ano', []);
    aoMudar('serie', ['turma_id', 'aluno_id']);
    aoMudar('turma_id', ['aluno_id']);
    if (document.getElementById('boletimFiltroDrawer') && !document.getElementById('boletimFiltroDrawer').classList.contains('translate-x-full')) {
        document.body.style.overflow = 'hidden';
    }
})();
</script>
<?php if ($bimestresAbas !== []): ?>
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
