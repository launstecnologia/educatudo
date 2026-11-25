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
$notaCampo = (string) ($painel['nota_campo'] ?? 'resultado');
$notaCampo = ($notaCampo === 'media_bim' || $notaCampo === 'media_anual') ? $notaCampo : 'resultado';
$notaCampoNome = $notaCampo === 'media_bim' ? 'média bimestral' : ($notaCampo === 'media_anual' ? 'média dos 4 bimestres' : 'resultado final');
$abrir = !empty($painel['abrir']);
$exibir = (string) ($_GET['exibir'] ?? 'notas') === 'boletim' ? 'boletim' : 'notas';
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
if ($exibir === 'boletim') {
    $filtrosAtivos++;
}
if ($notaMinima !== null) {
    $filtrosAtivos++;
}
$reabrirFiltro = (string) ($_GET['filtro_aberto'] ?? '') === '1' && !$abrir;
$carregarFundo = !empty($painel['carregar_fundo']);
$filaFundo = [];
if ($carregarFundo) {
    foreach ($alunos as $itemFila) {
        $idFila = (int) ($itemFila['id'] ?? 0);
        if ($idFila <= 0 || ($alunoId > 0 && $idFila !== $alunoId)) {
            continue;
        }
        $filaFundo[] = $idFila;
    }
}

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
.boletim-colag tbody tr td.vermelho { color: #b91c1c; font-weight: 700; background: #fef2f2; }
@keyframes boletim-status {
    0% { transform: translateX(-120%); }
    100% { transform: translateX(280%); }
}
</style>
<div id="boletim-status" <?= $carregarFundo ? '' : 'hidden' ?> style="position:sticky;top:0;z-index:30;margin-bottom:1rem;">
    <div style="background:#fff;border:1px solid #dbe4ee;border-radius:12px;padding:12px 16px;">
        <div style="display:flex;justify-content:space-between;gap:12px;font-size:14px;color:#1e293b;margin-bottom:8px;">
            <span id="boletim-status-texto">Carregando as notas em segundo plano...</span>
            <span id="boletim-status-conta"></span>
        </div>
        <div style="height:8px;background:#e2e8f0;border-radius:999px;overflow:hidden;">
            <div id="boletim-status-barra" style="height:100%;width:<?= $carregarFundo ? '0' : '40%' ?>;background:#1d4ed8;<?= $carregarFundo ? '' : 'animation:boletim-status 1.1s ease-in-out infinite;' ?>"></div>
        </div>
    </div>
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
                <label for="boletim_exibir" class="block text-sm font-medium text-gray-700 mb-1.5">Exibir</label>
                <select id="boletim_exibir" name="exibir" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="notas" <?= $exibir === 'notas' ? 'selected' : '' ?>>Notas</option>
                    <option value="boletim" <?= $exibir === 'boletim' ? 'selected' : '' ?>>Boletim</option>
                </select>
            </div>
            <?php
            $bimestresMarcados = [];
            if ($exibir === 'boletim') {
                foreach ((array) ($_GET['bimestres'] ?? []) as $bimestreMarcado) {
                    $bimestreMarcado = (int) $bimestreMarcado;
                    if ($bimestreMarcado >= 1 && $bimestreMarcado <= 4) {
                        $bimestresMarcados[$bimestreMarcado] = true;
                    }
                }
            }
            ?>
            <div id="filtro-bimestre-um" class="<?= $exibir === 'boletim' ? 'hidden' : '' ?>">
                <label for="boletim_bimestre" class="block text-sm font-medium text-gray-700 mb-1.5">Bimestre</label>
                <select id="boletim_bimestre" name="bimestre" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0" <?= $bimestreFiltro === 0 ? 'selected' : '' ?>>Todos</option>
                    <?php foreach ([1, 2, 3, 4] as $bimestreOpcao): ?>
                    <option value="<?= $bimestreOpcao ?>" <?= $bimestreOpcao === $bimestreFiltro ? 'selected' : '' ?>><?= $bimestreOpcao ?>º bimestre</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div id="filtro-bimestre-varios" class="<?= $exibir === 'notas' ? 'hidden' : '' ?>">
                <span class="block text-sm font-medium text-gray-700 mb-1.5">Bimestres do boletim</span>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ([1, 2, 3, 4] as $bimestreOpcao): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="bimestres[]" value="<?= $bimestreOpcao ?>" <?= isset($bimestresMarcados[$bimestreOpcao]) ? 'checked' : '' ?>>
                        <?= $bimestreOpcao ?>º bimestre
                    </label>
                    <?php endforeach; ?>
                </div>
                <span class="mt-1 block text-xs text-gray-500">Marque os que quer ver. Nenhum marcado traz os quatro.</span>
            </div>
            <div>
                <label for="boletim_nota_campo" class="block text-sm font-medium text-gray-700 mb-1.5">Nota mínima em</label>
                <select id="boletim_nota_campo" name="nota_campo" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="media_bim" <?= $notaCampo === 'media_bim' ? 'selected' : '' ?>>Média bimestral</option>
                    <option value="resultado" <?= $notaCampo === 'resultado' ? 'selected' : '' ?>>Resultado final</option>
                    <option value="media_anual" <?= $notaCampo === 'media_anual' ? 'selected' : '' ?>>Média dos 4 bimestres</option>
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
<?php elseif ($estadoHomologacao === 'carregando'): ?>
<div id="boletim-homologacao" class="mb-6"></div>
<?php else: ?>
<p class="mb-6 text-sm text-gray-600">Este resultado ainda não foi homologado.</p>
<?php endif; ?>
<?php endif; ?>

<?php
$primeiroRelatorio = $relatorios[0] ?? null;
$modeloVazio = !is_array($primeiroRelatorio) || !is_array($primeiroRelatorio['aluno'] ?? null);
$bimestresPainel = [];
foreach ((array) ($painel['bimestres'] ?? []) as $bimestrePainel) {
    $bimestrePainel = (int) $bimestrePainel;
    if ($bimestrePainel >= 1 && $bimestrePainel <= 4) {
        $bimestresPainel[$bimestrePainel] = $bimestrePainel;
    }
}
$bimestresPainel = array_values($bimestresPainel);
sort($bimestresPainel);
$bimestresAbas = (($carregarFundo || !$modeloVazio) && count($bimestresPainel) > 1) ? $bimestresPainel : [];
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
<?php if ($abrir && !$carregarFundo && $notaMinima !== null && $relatorios === []): ?>
<p class="text-sm text-gray-600 mb-6">Nenhum aluno com <?= htmlspecialchars($notaCampoNome, ENT_QUOTES, 'UTF-8') ?> abaixo de <?= htmlspecialchars($notaMinimaTexto, ENT_QUOTES, 'UTF-8') ?>.</p>
<?php endif; ?>
<?php if ($abrir || $carregarFundo): ?>
<div class="flex flex-wrap gap-2 mb-4" role="tablist" aria-label="Visão">
    <button type="button" class="boletim-vista px-4 py-1.5 rounded-full text-sm font-medium <?= $exibir === 'notas' ? 'bg-blue-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>" data-vista="notas" aria-selected="<?= $exibir === 'notas' ? 'true' : 'false' ?>">Notas</button>
    <button type="button" class="boletim-vista px-4 py-1.5 rounded-full text-sm font-medium <?= $exibir === 'boletim' ? 'bg-blue-900 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>" data-vista="boletim" aria-selected="<?= $exibir === 'boletim' ? 'true' : 'false' ?>">Boletim</button>
</div>
<?php endif; ?>
<?php if ($bimestresAbas !== []): ?>
<div id="abas-bimestre" class="flex flex-wrap gap-2 mb-6 <?= $exibir === 'boletim' ? 'hidden' : '' ?>" role="tablist" aria-label="Bimestres">
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
<?php if ($carregarFundo): ?>
<div id="boletim-resultado"></div>
<p id="boletim-vazio" hidden class="text-sm text-gray-600 mb-6"></p>
<?php elseif ($abrir): ?>
<?php foreach ($relatorios as $relatorio): ?>
<?php include __DIR__ . '/_aluno.php'; ?>
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
window.boletimVista = <?= json_encode($exibir) ?>;
window.boletimAbaAtiva = <?= json_encode((string) ($bimestresAbas[0] ?? '1')) ?>;
function aplicarVistaBoletim(vista) {
    window.boletimVista = vista === 'boletim' ? 'boletim' : 'notas';
    document.querySelectorAll('.vista-notas').forEach(function (painel) {
        var oculto = window.boletimVista !== 'notas';
        if (!oculto && document.querySelector('[data-boletim-aba]')) {
            oculto = painel.getAttribute('data-boletim-painel') !== (window.boletimAbaAtiva || '1');
        }
        painel.classList.toggle('hidden', oculto);
    });
    document.querySelectorAll('.vista-boletim').forEach(function (painel) {
        painel.classList.toggle('hidden', window.boletimVista !== 'boletim');
    });
    var abas = document.getElementById('abas-bimestre');
    if (abas) abas.classList.toggle('hidden', window.boletimVista !== 'notas');
    document.querySelectorAll('[data-vista]').forEach(function (botao) {
        var ativo = botao.getAttribute('data-vista') === window.boletimVista;
        botao.classList.toggle('bg-blue-900', ativo);
        botao.classList.toggle('text-white', ativo);
        botao.classList.toggle('bg-gray-100', !ativo);
        botao.classList.toggle('text-gray-700', !ativo);
        botao.setAttribute('aria-selected', ativo ? 'true' : 'false');
    });
}
document.querySelectorAll('[data-vista]').forEach(function (botao) {
    botao.addEventListener('click', function () {
        aplicarVistaBoletim(botao.getAttribute('data-vista'));
    });
});
var campoExibir = document.getElementById('boletim_exibir');
if (campoExibir) {
    campoExibir.addEventListener('change', function () {
        var boletim = campoExibir.value === 'boletim';
        var um = document.getElementById('filtro-bimestre-um');
        var varios = document.getElementById('filtro-bimestre-varios');
        if (um) um.classList.toggle('hidden', boletim);
        if (varios) varios.classList.toggle('hidden', !boletim);
    });
}
document.addEventListener('click', function (evento) {
    var botao = evento.target.closest('[data-salvar-obs]');
    if (!botao) return;
    var caixa = botao.closest('[data-obs-aluno]');
    var campo = caixa ? caixa.querySelector('textarea') : null;
    var status = caixa ? caixa.querySelector('[data-obs-status]') : null;
    var token = document.querySelector('#boletim-filtros [name="_token"]');
    if (!caixa || !campo) return;
    if (status) status.textContent = 'Salvando...';
    fetch(<?= json_encode(URL) ?> + '/admin/students/' + caixa.getAttribute('data-obs-aluno') + '/boletim/observacao', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({_token: token ? token.value : '', conteudo: campo.value})
    }).then(function (resposta) {
        if (status) status.textContent = resposta.ok ? 'Observação salva.' : 'Não foi possível salvar.';
    }).catch(function () {
        if (status) status.textContent = 'Não foi possível salvar.';
    });
});
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
        var caixa = document.getElementById('boletim-status');
        var texto = document.getElementById('boletim-status-texto');
        var barra = document.getElementById('boletim-status-barra');
        if (!caixa) return;
        caixa.hidden = false;
        if (texto) texto.textContent = 'Carregando as notas em segundo plano...';
        if (barra) {
            barra.style.width = '40%';
            barra.style.animation = 'boletim-status 1.1s ease-in-out infinite';
        }
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
<?php if ($carregarFundo): ?>
<script>
(function () {
    var fila = <?= json_encode($filaFundo, JSON_UNESCAPED_UNICODE) ?>;
    var form = document.getElementById('boletim-filtros');
    var destino = document.getElementById('boletim-resultado');
    var vazio = document.getElementById('boletim-vazio');
    var texto = document.getElementById('boletim-status-texto');
    var conta = document.getElementById('boletim-status-conta');
    var barra = document.getElementById('boletim-status-barra');
    var feitos = 0;
    var linhas = [];
    function atualizar(mensagem) {
        if (texto) texto.textContent = mensagem;
        if (conta) conta.textContent = fila.length ? (feitos + ' de ' + fila.length) : '';
        if (barra) {
            barra.style.animation = '';
            barra.style.width = (fila.length ? Math.round((feitos / fila.length) * 100) : 0) + '%';
        }
    }
    function pintarHomologacao(dados) {
        var caixa = document.getElementById('boletim-homologacao');
        if (!caixa || !dados) return;
        var aviso = document.createElement('p');
        aviso.className = 'rounded-lg px-4 py-3 text-sm';
        if (dados.estado === 'alterada') {
            aviso.style.cssText = 'background:#fff7ed;color:#9a3412;border:1px solid #fdba74;';
            aviso.textContent = 'Houve alteração de nota desde a homologação de ' + (dados.em || '') + '. Homologue de novo.';
        } else if (dados.estado === 'igual') {
            aviso.style.cssText = 'background:#ecfdf5;color:#065f46;border:1px solid #6ee7b7;';
            aviso.textContent = 'Homologado em ' + (dados.em || '') + (dados.usuario ? ' por ' + dados.usuario : '') + '. Nenhuma nota mudou desde então.';
        } else if (dados.estado === 'sem_tabela') {
            aviso.style.cssText = 'background:#fef2f2;color:#991b1b;border:1px solid #fca5a5;';
            aviso.textContent = 'A homologação ainda não está disponível neste ambiente. Rode a migration do boletim Colag no Master.';
        } else {
            aviso.className = 'text-sm text-gray-600';
            aviso.textContent = 'Este resultado ainda não foi homologado.';
        }
        caixa.textContent = '';
        caixa.appendChild(aviso);
    }
    function finalizar() {
        var temNota = destino && destino.children.length > 0;
        atualizar(temNota ? 'Notas carregadas.' : 'Nenhuma nota encontrada nesse filtro.');
        if (!temNota && vazio) {
            vazio.hidden = false;
            vazio.textContent = 'Nenhum aluno com nota abaixo do filtro.';
        }
        if (!form) return;
        var token = form.querySelector('[name="_token"]');
        var payload = {};
        new FormData(form).forEach(function (valor, chave) {
            if (Object.prototype.hasOwnProperty.call(payload, chave)) {
                if (!Array.isArray(payload[chave])) {
                    payload[chave] = [payload[chave]];
                }
                payload[chave].push(valor);
                return;
            }
            payload[chave] = valor;
        });
        if (Array.isArray(payload['bimestres[]'])) {
            payload.bimestres = payload['bimestres[]'];
        } else if (payload['bimestres[]']) {
            payload.bimestres = [payload['bimestres[]']];
        }
        payload._token = token ? token.value : '';
        payload.linhas = linhas;
        fetch(<?= json_encode(URL . '/admin/boletim-26-colag/situacao') ?>, {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        }).then(function (resposta) {
            return resposta.json();
        }).then(pintarHomologacao).catch(function () {});
    }
    function proximo() {
        if (!form || feitos >= fila.length) {
            finalizar();
            return;
        }
        var dados = new FormData(form);
        dados.set('aluno_id', String(fila[feitos]));
        dados.delete('_token');
        dados.delete('abrir');
        dados.delete('filtro_aberto');
        atualizar('Carregando as notas em segundo plano...');
        fetch(<?= json_encode(URL . '/admin/boletim-26-colag/parcial') ?> + '?' + new URLSearchParams(dados).toString(), {
            headers: {Accept: 'application/json'}
        }).then(function (resposta) {
            return resposta.json();
        }).then(function (corpo) {
            if (corpo && corpo.html && destino) {
                destino.insertAdjacentHTML('beforeend', corpo.html);
                if (typeof aplicarVistaBoletim === 'function') {
                    aplicarVistaBoletim(window.boletimVista || 'notas');
                }
            }
            if (corpo && Array.isArray(corpo.linhas)) {
                linhas = linhas.concat(corpo.linhas);
            }
        }).catch(function () {}).then(function () {
            feitos += 1;
            proximo();
        });
    }
    if (fila.length === 0) {
        finalizar();
        return;
    }
    proximo();
})();
</script>
<?php endif; ?>
<?php if ($bimestresAbas !== []): ?>
<script>
document.querySelectorAll('[data-boletim-aba]').forEach(function (botao) {
    botao.addEventListener('click', function () {
        var alvo = botao.getAttribute('data-boletim-aba');
        window.boletimAbaAtiva = alvo;
        document.querySelectorAll('[data-boletim-aba]').forEach(function (item) {
            var ativo = item === botao;
            item.classList.toggle('bg-blue-900', ativo);
            item.classList.toggle('text-white', ativo);
            item.classList.toggle('bg-gray-100', !ativo);
            item.classList.toggle('text-gray-700', !ativo);
            item.setAttribute('aria-selected', ativo ? 'true' : 'false');
        });
        if (typeof aplicarVistaBoletim === 'function') {
            aplicarVistaBoletim('notas');
        }
    });
});
</script>
<?php endif; ?>
