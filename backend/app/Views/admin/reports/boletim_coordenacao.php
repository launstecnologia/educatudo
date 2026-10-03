<?php
$fontePedido = (string) ($fonte ?? 'vida_escolar');
$fonte = in_array($fontePedido, ['evento', 'vida_escolar', 'demonstrativo'], true) ? $fontePedido : 'vida_escolar';
$relatorio = is_array($relatorio ?? null) ? $relatorio : null;
$anosLetivos = (array) ($anos_letivos ?? []);
if ($anosLetivos === []) {
    $anosLetivos[] = (int) date('Y');
}
$anoSelecionado = (int) ($ano_letivo ?? 0);
if ($anoSelecionado <= 0) {
    $anoSelecionado = (int) $anosLetivos[0];
}
$eventosSelecionados = [];
foreach ((array) ($eventos_selecionados ?? []) as $valorSelecionado) {
    $valorSelecionado = (string) $valorSelecionado;
    if ($valorSelecionado !== '') {
        $eventosSelecionados[$valorSelecionado] = true;
    }
}
$selecionarTodos = !empty($selecionar_todos);
$queryExport = [
    'fonte' => $fonte,
    'ano_letivo' => $anoSelecionado,
    'periodo' => max(0, (int) ($periodo ?? 0)),
    'turma_id' => (int) ($turma_id ?? 0),
    'aluno_q' => trim((string) ($aluno_q ?? '')),
    'nota_abaixo_de' => $nota_abaixo_de !== null ? str_replace('.', ',', (string) $nota_abaixo_de) : '',
    'materias_exibicao' => $materias_exibicao ?? 'todas',
    'assinatura' => !empty($incluir_assinatura) ? 1 : 0,
    'incluir_antigas' => !empty($incluir_antigas) ? 1 : 0,
];
if ($queryExport['aluno_q'] === '') {
    unset($queryExport['aluno_q']);
}
if ($selecionarTodos) {
    $queryExport['evento'] = 'todos';
} elseif (count($eventosSelecionados) === 1) {
    $queryExport['evento'] = (string) array_key_first($eventosSelecionados);
} elseif ($eventosSelecionados !== []) {
    $queryExport['eventos'] = array_keys($eventosSelecionados);
}
$incluirAntigas = !empty($incluir_antigas);
$formatNota = static function ($value, int $places): string {
    return is_numeric($value) ? number_format((float) $value, $places, ',', '.') : ((string) $value !== '' ? (string) $value : '—');
};
$fonteRelatorio = (string) ($relatorio['fonte'] ?? $fonte);
$zipJob = is_array($zip_job ?? null) ? $zip_job : null;
$zipJobId = (int) ($zipJob['id'] ?? 0);
$zipJobStatus = (string) ($zipJob['status'] ?? '');
$zipGerando = $zipJobId > 0 && in_array($zipJobStatus, ['pending', 'processing'], true);
$zipPronto = $zipJobId > 0 && $zipJobStatus === 'done';
$zipFalhou = $zipJobId > 0 && in_array($zipJobStatus, ['failed', 'error'], true);
$zipInicio = (string) ($zipJob['iniciado_em'] ?? '');
$zipTermino = (string) ($zipJob['finalizado_em'] ?? '');
$zipPedido = (string) ($zipJob['pedido_em'] ?? '');
$zipDuracao = (string) ($zipJob['duracao'] ?? '');
include __DIR__ . '/../_partials/flash_message.php';
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Notas da Coordenação</h1>
    <p class="text-gray-600 mt-1">Escolha o boletim, o demonstrativo de notas ou as notas do evento (provas, trabalhos e médias).</p>
</div>

<?php
$periodoSelecionado = max(0, (int) ($periodo ?? 0));
$periodosPorAno = is_array($periodos_por_ano ?? null) ? $periodos_por_ano : [];
$infoPeriodoAno = is_array($periodosPorAno[(string) $anoSelecionado] ?? null) ? $periodosPorAno[(string) $anoSelecionado] : [];
$rotuloPeriodo = trim((string) ($infoPeriodoAno['rotulo'] ?? 'Bimestre'));
if ($rotuloPeriodo === '') {
    $rotuloPeriodo = 'Bimestre';
}
$opcoesPeriodo = is_array($infoPeriodoAno['opcoes'] ?? null) ? $infoPeriodoAno['opcoes'] : [];
$campoClasse = 'w-full h-11 rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100';
$alunoQFiltro = trim((string) ($aluno_q ?? ''));
?>
<form method="GET" action="<?= URL ?>/admin/reports/boletim-coordenacao" id="form-boletim-coordenacao" class="bg-white rounded-xl border border-gray-200 shadow-sm mb-6">
    <div class="p-5 md:p-6 space-y-5">
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <label class="block">
            <span class="block text-sm font-medium text-gray-700 mb-1.5">Exibir</span>
            <select name="fonte" id="fonte-boletim-coord" class="<?= $campoClasse ?>">
                <option value="vida_escolar" <?= $fonte === 'vida_escolar' ? 'selected' : '' ?>>Boletim</option>
                <option value="demonstrativo" <?= $fonte === 'demonstrativo' ? 'selected' : '' ?>>Demonstrativo de notas</option>
                <option value="evento" <?= $fonte === 'evento' ? 'selected' : '' ?>>Notas do evento</option>
            </select>
        </label>
        <label class="block">
            <span class="block text-sm font-medium text-gray-700 mb-1.5">Ano letivo</span>
            <select name="ano_letivo" id="ano-letivo-boletim-coord" class="<?= $campoClasse ?>">
                <?php foreach ($anosLetivos as $ano): ?>
                    <option value="<?= (int) $ano ?>" <?= $anoSelecionado === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block">
            <span class="block text-sm font-medium text-gray-700 mb-1.5" id="rotulo-periodo-boletim-coord"><?= htmlspecialchars($rotuloPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
            <select name="periodo" id="periodo-boletim-coord" class="<?= $campoClasse ?>">
                <option value="0" <?= $periodoSelecionado === 0 ? 'selected' : '' ?>>Todos</option>
                <?php foreach ($opcoesPeriodo as $opcaoPeriodo): ?>
                    <?php if (!is_array($opcaoPeriodo)) { continue; } ?>
                    <?php $valorPeriodo = (int) ($opcaoPeriodo['valor'] ?? 0); ?>
                    <option value="<?= $valorPeriodo ?>" <?= $periodoSelecionado === $valorPeriodo ? 'selected' : '' ?>><?= htmlspecialchars((string) ($opcaoPeriodo['rotulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block">
            <span class="block text-sm font-medium text-gray-700 mb-1.5">Turma</span>
            <select name="turma_id" class="<?= $campoClasse ?>">
                <option value="0">Todas as turmas</option>
                <?php foreach ((array) ($turmas ?? []) as $turma): ?>
                    <option value="<?= (int) $turma['id'] ?>" <?= (int) ($turma_id ?? 0) === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <label class="block md:col-span-2 relative">
            <span class="block text-sm font-medium text-gray-700 mb-1.5">Aluno</span>
            <input type="text" name="aluno_q" id="aluno-q-boletim-coord" value="<?= htmlspecialchars($alunoQFiltro, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Nome, RA ou código"
                   class="<?= $campoClasse ?>"
                   autocomplete="off"
                   role="combobox"
                   aria-autocomplete="list"
                   aria-expanded="false"
                   aria-controls="aluno-sugestoes-boletim-coord">
            <div id="aluno-sugestoes-boletim-coord" class="hidden absolute z-30 left-0 right-0 mt-1 max-h-64 overflow-y-auto rounded-lg border border-gray-200 bg-white shadow-lg" role="listbox"></div>
        </label>
        <label class="block">
            <span class="block text-sm font-medium text-gray-700 mb-1.5">Nota abaixo de</span>
            <input type="text" name="nota_abaixo_de" inputmode="decimal" placeholder="Ex.: 7 ou 6,5" value="<?= htmlspecialchars($nota_abaixo_de !== null ? str_replace('.', ',', (string) $nota_abaixo_de) : '') ?>" class="<?= $campoClasse ?>">
        </label>
        <label class="block">
            <span class="block text-sm font-medium text-gray-700 mb-1.5">Matérias</span>
            <select name="materias_exibicao" class="<?= $campoClasse ?>">
                <option value="todas" <?= ($materias_exibicao ?? 'todas') === 'todas' ? 'selected' : '' ?>>Todas as matérias</option>
                <option value="abaixo" <?= ($materias_exibicao ?? 'todas') === 'abaixo' ? 'selected' : '' ?>>Só as que estão abaixo do corte</option>
            </select>
        </label>
    </div>

    <div class="campo-fonte campo-fonte-evento campo-fonte-demonstrativo mt-5 <?= in_array($fonte, ['evento', 'demonstrativo'], true) ? '' : 'hidden' ?>">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
            <span class="text-sm font-medium text-gray-700">Eventos</span>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" id="eventos-selecionar-todos" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= $selecionarTodos ? 'checked' : '' ?>>
                Selecionar todos
            </label>
            <span id="eventos-qtd" class="text-xs text-gray-500"></span>
        </div>
        <div id="lista-eventos-coord" class="max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50/40">
            <?php
            $valorEventoLista = static function (array $ev): string {
                return (int) ($ev['regra_id'] ?? 0)
                    . ':' . base64_encode((string) ($ev['periodo_ref'] ?? ''))
                    . ':' . (int) ($ev['geracao_id'] ?? 0);
            };
            $dataVersaoLista = static function (array $ev): string {
                $raw = trim((string) ($ev['criado_em'] ?? ''));
                if ($raw === '') {
                    $raw = trim((string) ($ev['updated_at'] ?? ''));
                }
                $ts = strtotime($raw);

                return $ts !== false ? date('d/m/Y', $ts) : '';
            };
            $gruposVersao = [];
            foreach ((array) ($eventos ?? []) as $eventoGrupo) {
                if (!is_array($eventoGrupo)) {
                    continue;
                }
                $ridGrupo = (int) ($eventoGrupo['regra_id'] ?? 0);
                if ($ridGrupo <= 0) {
                    continue;
                }
                $gruposVersao[$ridGrupo][] = $eventoGrupo;
            }
            $linhasEvento = [];
            foreach ($gruposVersao as $versoesGrupo) {
                usort($versoesGrupo, static function (array $a, array $b): int {
                    $vigA = !empty($a['eh_vigente']) ? 0 : 1;
                    $vigB = !empty($b['eh_vigente']) ? 0 : 1;
                    if ($vigA !== $vigB) {
                        return $vigA <=> $vigB;
                    }
                    $dataA = (string) ($b['criado_em'] ?? $b['updated_at'] ?? '');
                    $dataB = (string) ($a['criado_em'] ?? $a['updated_at'] ?? '');

                    return strcmp($dataA, $dataB);
                });
                $linhasEvento[] = $versoesGrupo;
            }
            usort($linhasEvento, static function (array $a, array $b): int {
                $baseA = $a[0];
                $baseB = $b[0];
                $serieA = mb_strtolower(trim((string) ($baseA['series_nomes'] ?? '')));
                $serieB = mb_strtolower(trim((string) ($baseB['series_nomes'] ?? '')));
                $cmp = $serieA <=> $serieB;
                if ($cmp !== 0) {
                    return $cmp;
                }
                $tipoA = (($baseA['exibir_em'] ?? '') === 'notas') ? 0 : 1;
                $tipoB = (($baseB['exibir_em'] ?? '') === 'notas') ? 0 : 1;
                if ($tipoA !== $tipoB) {
                    return $tipoA <=> $tipoB;
                }
                $cmp = ((int) ($baseA['bimestre'] ?? 0)) <=> ((int) ($baseB['bimestre'] ?? 0));
                if ($cmp !== 0) {
                    return $cmp;
                }
                return ((int) ($baseA['regra_id'] ?? 0)) <=> ((int) ($baseB['regra_id'] ?? 0));
            });
            ?>
            <?php if ($linhasEvento === []): ?>
                <p class="px-4 py-6 text-sm text-gray-500">Nenhuma avaliação gerada. Em Avaliações, gere o lote para ver provas, trabalhos e médias.</p>
            <?php else: ?>
                <div class="grid gap-x-3 items-center px-4 py-2 text-[11px] font-semibold uppercase tracking-wide text-gray-500 bg-gray-50 border-b border-gray-200" style="grid-template-columns: 1.25rem 4.25rem 4.5rem 7rem minmax(0,1fr) 12.5rem;">
                    <span></span>
                    <span>Tipo</span>
                    <span>Ref</span>
                    <span id="cab-periodo-eventos"><?= htmlspecialchars($rotuloPeriodo, ENT_QUOTES, 'UTF-8') ?></span>
                    <span>Série</span>
                    <span>Versões</span>
                </div>
            <?php endif; ?>
            <?php foreach ($linhasEvento as $versoesLinha):
                $escolhida = $versoesLinha[0];
                $marcado = $selecionarTodos;
                foreach ($versoesLinha as $versaoEv) {
                    if (isset($eventosSelecionados[$valorEventoLista($versaoEv)])) {
                        $escolhida = $versaoEv;
                        $marcado = true;
                        break;
                    }
                }
                $value = $valorEventoLista($escolhida);
                $refLista = (int) ($escolhida['regra_id'] ?? 0);
                $bimLista = trim((string) ($escolhida['rotulo_bimestre'] ?? ''));
                $serieLista = trim((string) ($escolhida['series_nomes'] ?? ''));
                $tipoLista = (($escolhida['exibir_em'] ?? '') === 'notas') ? 'Notas' : 'Boletim';
                $totalVersoes = count($versoesLinha);
                ?>
                <div class="evento-item grid gap-x-3 items-center px-4 py-2.5 bg-white border-b border-gray-100 last:border-b-0 hover:bg-purple-50/40 text-sm text-gray-900" style="grid-template-columns: 1.25rem 4.25rem 4.5rem 7rem minmax(0,1fr) 12.5rem;" data-ano="<?= (int) ($escolhida['ano_letivo'] ?? 0) ?>" data-bimestre="<?= (int) ($escolhida['bimestre'] ?? 0) ?>">
                    <input type="checkbox" name="eventos[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" class="evento-check w-4 h-4 shrink-0 rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= $marcado ? 'checked' : '' ?>>
                    <span class="font-medium"><?= htmlspecialchars($tipoLista, ENT_QUOTES, 'UTF-8') ?></span>
                    <span>Ref: <?= $refLista > 0 ? $refLista : '—' ?></span>
                    <span><?= $bimLista !== '' ? htmlspecialchars($bimLista, ENT_QUOTES, 'UTF-8') : '' ?></span>
                    <span class="min-w-0 truncate"><?= htmlspecialchars($serieLista !== '' ? $serieLista : 'Todas', ENT_QUOTES, 'UTF-8') ?></span>
                    <div class="min-w-0">
                        <span class="block text-[10px] text-gray-500 mb-1"><?= $totalVersoes ?> <?= $totalVersoes === 1 ? 'versão' : 'versões' ?></span>
                        <div class="flex flex-col gap-1">
                            <?php foreach ($versoesLinha as $versaoEv):
                                $valorVersao = $valorEventoLista($versaoEv);
                                $ativa = $valorVersao === $value;
                                $ehVigenteVersao = !empty($versaoEv['eh_vigente']);
                                $dataVersao = $dataVersaoLista($versaoEv);
                                $classeVersao = $ativa
                                    ? ($ehVigenteVersao
                                        ? 'border-green-400 bg-green-50 text-green-900'
                                        : 'border-gray-400 bg-gray-50 text-gray-900')
                                    : 'border-gray-200 bg-white text-gray-600';
                                ?>
                                <?php if ($totalVersoes > 1): ?>
                                    <button type="button"
                                            class="evento-versao w-full text-left rounded-md border px-2 py-1 leading-tight <?= $classeVersao ?>"
                                            data-value="<?= htmlspecialchars($valorVersao, ENT_QUOTES, 'UTF-8') ?>"
                                            data-vigente="<?= $ehVigenteVersao ? '1' : '0' ?>"
                                            aria-pressed="<?= $ativa ? 'true' : 'false' ?>">
                                        <span class="block text-[11px] font-semibold"><?= $ehVigenteVersao ? 'Vigente' : 'Anterior' ?></span>
                                        <?php if ($dataVersao !== ''): ?>
                                            <span class="block text-[10px] opacity-80"><?= htmlspecialchars($dataVersao, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </button>
                                <?php else: ?>
                                    <span class="inline-flex flex-col rounded-md border border-green-200 bg-green-50 px-2 py-1 leading-tight text-green-900">
                                        <span class="text-[11px] font-semibold">Vigente</span>
                                        <?php if ($dataVersao !== ''): ?>
                                            <span class="text-[10px] opacity-80"><?= htmlspecialchars($dataVersao, ENT_QUOTES, 'UTF-8') ?></span>
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <p id="eventos-filtro-vazio" class="hidden px-4 py-6 text-sm text-gray-500">Nenhum evento neste ano e período.</p>
        </div>
        <input type="hidden" name="evento" id="evento-boletim-coord" value="">
    </div>

    <div class="pt-4 border-t border-gray-100 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
        <label class="inline-flex items-center gap-2.5 text-sm text-gray-700 cursor-pointer">
            <input type="checkbox" name="assinatura" value="1" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= !empty($incluir_assinatura) ? 'checked' : '' ?>>
            Incluir campo de assinatura ao lado do nome
        </label>
        <div class="flex flex-wrap gap-3">
            <a href="<?= URL ?>/admin/reports/boletim-coordenacao" class="px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">Limpar</a>
            <button type="submit" name="executar" value="1" class="btn-primary-custom px-5 py-2.5 rounded-lg font-semibold shadow-sm hover:opacity-90 transition-opacity"><i class="fa-solid fa-chart-column mr-2"></i>Gerar relatório</button>
        </div>
    </div>
    </div>
</form>

<?php if ($zipGerando || $zipPronto || $zipFalhou): ?>
    <div id="zip-boletins-banner" class="rounded-lg p-4 mb-6 <?= $zipFalhou ? 'bg-red-50 border border-red-200 text-red-800' : ($zipPronto ? 'bg-emerald-50 border border-emerald-200 text-emerald-800' : 'bg-indigo-50 border border-indigo-200 text-indigo-900') ?>">
        <?php if ($zipGerando): ?>
            <p class="font-semibold"><i class="fa-solid fa-spinner fa-spin mr-2"></i><span id="zip-boletins-texto">Gerando os boletins em segundo plano. Um PDF por aluno, depois o ZIP. Com a escola inteira pode levar vários minutos — deixe esta página aberta.</span></p>
        <?php elseif ($zipPronto): ?>
            <p class="font-semibold"><i class="fa-solid fa-circle-check mr-2"></i>ZIP pronto<?= (int) ($zipJob['emitidos'] ?? 0) > 0 ? ': ' . (int) $zipJob['emitidos'] . ' boletim(ns)' : '' ?><?= (int) ($zipJob['falhas'] ?? 0) > 0 ? ' · ' . (int) $zipJob['falhas'] . ' falha(s)' : '' ?>.</p>
            <a href="<?= URL ?>/admin/reports/boletim-coordenacao/zip/<?= $zipJobId ?>" class="inline-flex items-center mt-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 font-semibold"><i class="fa-solid fa-download mr-2"></i>Baixar ZIP</a>
        <?php else: ?>
            <p class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-2"></i>Não foi possível gerar o ZIP<?= ($zipJob['erro'] ?? '') !== '' ? ': ' . htmlspecialchars((string) $zipJob['erro']) : '.' ?></p>
        <?php endif; ?>
        <p id="zip-boletins-horarios" class="text-sm mt-2 <?= $zipFalhou ? 'text-red-700' : ($zipPronto ? 'text-emerald-800' : 'text-indigo-800') ?>">
            <?php if ($zipInicio !== ''): ?>
                Início: <?= htmlspecialchars($zipInicio) ?>
                <?php if ($zipTermino !== ''): ?> · Término: <?= htmlspecialchars($zipTermino) ?><?php else: ?> · Término: em andamento<?php endif; ?>
                <?php if ($zipDuracao !== ''): ?> · Duração: <?= htmlspecialchars($zipDuracao) ?><?php endif; ?>
            <?php elseif ($zipPedido !== ''): ?>
                Pedido às <?= htmlspecialchars($zipPedido) ?> · Aguardando início da geração
            <?php else: ?>
                Horários da geração aparecem assim que o processamento começar.
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<?php if (!empty($executar) && $relatorio): ?>
    <?php
    $paginaAtual = max(1, (int) ($relatorio['pagina'] ?? 1));
    $totalPaginas = max(1, (int) ($relatorio['total_paginas'] ?? 1));
    $eventosTotal = max(1, (int) ($relatorio['eventos_total'] ?? 1));
    $grupos = is_array($relatorio['grupos'] ?? null) ? $relatorio['grupos'] : [];
    $indice = is_array($relatorio['indice'] ?? null) ? $relatorio['indice'] : [];
    $linkRelatorio = static function (int $paginaLink) use ($queryExport): string {
        $params = $queryExport;
        unset($params['pagina'], $params['evento_idx']);
        if ($paginaLink > 1) {
            $params['pagina'] = $paginaLink;
        }
        $params['executar'] = 1;
        return URL . '/admin/reports/boletim-coordenacao?' . http_build_query($params);
    };
    $queryExportacao = $queryExport;
    unset($queryExportacao['pagina'], $queryExportacao['evento_idx']);
    ?>
    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <div>
            <strong><?= (int) $relatorio['total_alunos'] ?> alunos</strong>
            <span class="text-gray-500">
                · <?= (int) $relatorio['total_linhas'] ?> registros de matérias
                <?php if ($eventosTotal > 1): ?> · <?= (int) $eventosTotal ?> boletins<?php endif; ?>
                <?php if ($relatorio['nota_abaixo_de'] !== null): ?> · média final abaixo de <?= htmlspecialchars(number_format((float) $relatorio['nota_abaixo_de'], 1, ',', '.')) ?><?php endif; ?>
                <?php if (($relatorio['materias_exibicao'] ?? 'todas') === 'abaixo'): ?> · somente matérias abaixo do corte<?php endif; ?>
                <?php if ($fonteRelatorio === 'vida_escolar' && !empty($relatorio['alunos_com_ficha'])): ?> · <?= (int) $relatorio['alunos_com_ficha'] ?> com ficha na Vida Escolar<?php endif; ?>
            </span>
        </div>
        <div class="flex flex-wrap gap-2">
            <?php if ($fonteRelatorio === 'vida_escolar'): ?>
                <?php if ((int) ($relatorio['alunos_com_ficha'] ?? 0) > 0 && !$zipGerando): ?>
                <a href="<?= URL ?>/admin/reports/boletim-coordenacao/exportar?<?= htmlspecialchars(http_build_query($queryExportacao + ['formato' => 'pdf'])) ?>" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700"><i class="fa-solid fa-file-zipper mr-2"></i>Baixar boletins (ZIP)</a>
                <?php elseif ($zipGerando): ?>
                <span class="px-4 py-2 rounded-lg bg-gray-200 text-gray-500 cursor-not-allowed" title="Aguarde o ZIP atual terminar"><i class="fa-solid fa-file-zipper mr-2"></i>Gerando ZIP...</span>
                <?php else: ?>
                <span class="px-4 py-2 rounded-lg bg-gray-200 text-gray-500 cursor-not-allowed" title="Nenhum aluno com ficha na Vida Escolar"><i class="fa-solid fa-file-zipper mr-2"></i>Baixar boletins (ZIP)</span>
                <?php endif; ?>
            <?php elseif ($fonteRelatorio !== 'demonstrativo' && $eventosTotal === 1 && !empty($relatorio['total_alunos'])): ?>
                <a href="<?= URL ?>/admin/reports/boletim-coordenacao/exportar?<?= htmlspecialchars(http_build_query($queryExportacao + ['formato' => 'pdf'])) ?>" class="px-4 py-2 rounded-lg bg-red-600 text-white hover:bg-red-700"><i class="fa-solid fa-file-pdf mr-2"></i>Exportar PDF</a>
            <?php endif; ?>
            <a href="<?= URL ?>/admin/reports/boletim-coordenacao/exportar?<?= htmlspecialchars(http_build_query($queryExportacao + ['formato' => 'excel'])) ?>" class="px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700"><i class="fa-solid fa-file-excel mr-2"></i>Exportar Excel</a>
            <a href="<?= URL ?>/admin/reports/boletim-coordenacao/exportar?<?= htmlspecialchars(http_build_query($queryExportacao + ['formato' => 'json'])) ?>" class="px-4 py-2 rounded-lg bg-slate-800 text-white hover:bg-slate-900"><i class="fa-solid fa-file-code mr-2"></i>Exportar JSON</a>
        </div>
    </div>
    <p class="text-sm text-gray-500 mb-4">Excel e JSON saem com todos os boletins selecionados. A tela pagina 20 alunos por vez para não ficar pesada.</p>
    <?php if ($fonteRelatorio === 'vida_escolar' && (int) ($relatorio['alunos_com_ficha'] ?? 0) > 0): ?>
        <p class="text-sm text-gray-500 mb-4">Um PDF por aluno, gerado em segundo plano e empacotado em ZIP. Com a escola inteira pode levar vários minutos.</p>
    <?php endif; ?>
    <?php if ($fonteRelatorio === 'vida_escolar' && (int) ($relatorio['alunos_sem_ficha'] ?? 0) > 0 && (int) ($relatorio['total_alunos'] ?? 0) > 0): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4 mb-4"><?= (int) $relatorio['alunos_sem_ficha'] ?> aluno(s) desta lista ainda não têm ficha na Vida Escolar no ano <?= (int) ($relatorio['ano_letivo'] ?? 0) ?> e ficam de fora do ZIP.</div>
    <?php endif; ?>

    <?php if (count($indice) > 1): ?>
        <div class="bg-white rounded-xl border border-gray-200 shadow-sm mb-5 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-200">
                <h2 class="text-sm font-semibold text-gray-900">Boletins neste relatório</h2>
            </div>
            <div class="divide-y divide-gray-100">
                <?php foreach ($indice as $itemIndice): ?>
                    <?php
                    $paginaIndice = max(1, (int) ($itemIndice['pagina'] ?? 1));
                    $qtdIndice = (int) ($itemIndice['alunos'] ?? 0);
                    ?>
                    <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5">
                        <div class="min-w-0">
                            <div class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) ($itemIndice['rotulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            <?php if (trim((string) ($itemIndice['detalhe'] ?? '')) !== ''): ?>
                                <div class="text-xs text-gray-500 truncate"><?= htmlspecialchars((string) $itemIndice['detalhe'], ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="flex items-center gap-3 shrink-0 text-xs text-gray-600">
                            <span><?= $qtdIndice ?> aluno(s)</span>
                            <?php if ($qtdIndice > 0): ?>
                                <a href="<?= htmlspecialchars($linkRelatorio($paginaIndice), ENT_QUOTES, 'UTF-8') ?>" class="font-medium text-purple-700 hover:text-purple-900">Ir à pág. <?= $paginaIndice ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if ((int) ($relatorio['total_alunos'] ?? 0) <= 0): ?>
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4"><?php
            if ($fonteRelatorio === 'vida_escolar') {
                echo 'Nenhuma ficha encontrada para os filtros selecionados.';
            } elseif ($fonteRelatorio === 'demonstrativo') {
                echo 'Nenhum demonstrativo de notas encontrado para os filtros selecionados.';
            } else {
                echo 'Nenhum boletim encontrado para os filtros selecionados.';
            }
        ?></div>
    <?php endif; ?>

    <?php foreach ($grupos as $grupo): ?>
        <?php
        $colunasGrupo = is_array($grupo['columns'] ?? null) ? $grupo['columns'] : [];
        $casasGrupo = (int) ($grupo['decimal_places'] ?? $relatorio['decimal_places'] ?? 1);
        $refEvento = (int) ($grupo['regra_id'] ?? 0);
        $rotuloGrupo = (string) ($grupo['evento_rotulo'] ?? $grupo['evento_nome'] ?? '');
        $detalheGrupo = (string) ($grupo['evento_detalhe'] ?? '');
        $bimestreGrupo = trim((string) ($grupo['bimestre_rotulo'] ?? ''));
        ?>
        <?php if ($rotuloGrupo !== '' && $eventosTotal > 1): ?>
            <div class="mb-3 mt-6 first:mt-0">
                <h2 class="text-base font-semibold text-gray-900"><?= htmlspecialchars($rotuloGrupo, ENT_QUOTES, 'UTF-8') ?></h2>
                <?php if ($detalheGrupo !== ''): ?>
                    <p class="text-sm text-gray-500"><?= htmlspecialchars($detalheGrupo, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        <?php foreach ((array) ($grupo['alunos'] ?? []) as $aluno): ?>
            <?php if (!is_array($aluno)) { continue; } ?>
            <?php $observacaoAluno = trim((string) ($aluno['observacao'] ?? '')); ?>
            <section class="bg-white rounded-xl border border-gray-200 shadow-sm mb-4 overflow-hidden">
                <div class="px-5 py-3 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center gap-x-6 gap-y-2">
                    <strong class="text-gray-900"><?= htmlspecialchars((string) ($aluno['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></strong>
                    <?php if (!empty($incluir_assinatura)): ?><span class="text-sm text-gray-600">Assinatura: <span class="inline-block w-52 border-b border-gray-500"></span></span><?php endif; ?>
                    <span class="text-sm text-gray-500">Turma: <?= htmlspecialchars((string) ($aluno['turma'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
                    <?php if ($fonteRelatorio === 'evento' || $fonteRelatorio === 'demonstrativo'): ?>
                        <?php if ($refEvento > 0): ?><span class="text-sm text-gray-500">Ref: <?= $refEvento ?></span><?php endif; ?>
                        <span class="text-sm text-gray-500">Bimestre: <?= htmlspecialchars($bimestreGrupo !== '' ? $bimestreGrupo : '—', ENT_QUOTES, 'UTF-8') ?></span>
                    <?php else: ?>
                        <?php if ((string) ($aluno['ra'] ?? '') !== ''): ?><span class="text-sm text-gray-500">RA: <?= htmlspecialchars((string) $aluno['ra'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                    <?php endif; ?>
                </div>
                <?php if ($fonteRelatorio === 'demonstrativo'): ?>
                <div class="p-4 overflow-x-auto">
                    <?php if (trim((string) ($aluno['demonstrativo_html'] ?? '')) !== ''): ?>
                        <?= $aluno['demonstrativo_html'] ?>
                    <?php else: ?>
                        <p class="text-sm text-gray-500">Não há demonstrativo de notas para este aluno neste evento.</p>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-2 text-left">Matéria</th>
                                <?php foreach ($colunasGrupo as $column): ?>
                                    <th class="px-4 py-2 text-center whitespace-nowrap"><?= htmlspecialchars((string) ($column['label'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ((array) ($aluno['materias'] ?? []) as $materia): ?>
                                <tr>
                                    <td class="px-4 py-2"><?= htmlspecialchars((string) ($materia['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php foreach ($colunasGrupo as $column): ?>
                                        <td class="px-4 py-2 text-center font-medium"><?= htmlspecialchars($formatNota($materia['notas'][$column['codigo']] ?? null, $casasGrupo), ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <div class="coord-observation border-t border-gray-200 bg-slate-50/70 px-5 py-4"
                     data-endpoint="<?= URL ?>/admin/students/<?= (int) ($aluno['id'] ?? 0) ?>/boletim/observacao"
                     data-csrf="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="flex items-center justify-between gap-3 mb-2">
                        <div class="flex items-center gap-2">
                            <i class="fa-regular fa-note-sticky text-gray-400"></i>
                            <h4 class="text-sm font-semibold text-gray-800">Observação da coordenação</h4>
                        </div>
                        <?php if (!empty($pode_editar_observacao)): ?>
                        <button type="button" class="coord-observation-edit text-sm font-semibold hover:opacity-75" style="color: var(--button-primary-color)">
                            <?= $observacaoAluno !== '' ? 'Editar' : 'Adicionar observação' ?>
                        </button>
                        <?php endif; ?>
                    </div>
                    <div class="coord-observation-view">
                        <p class="coord-observation-text text-sm text-gray-700 whitespace-pre-wrap break-words <?= $observacaoAluno === '' ? 'italic text-gray-400' : '' ?>"><?= htmlspecialchars($observacaoAluno !== '' ? $observacaoAluno : 'Nenhuma observação registrada.', ENT_QUOTES, 'UTF-8') ?></p>
                    </div>
                    <?php if (!empty($pode_editar_observacao)): ?>
                    <div class="coord-observation-form hidden mt-3">
                        <textarea rows="4" maxlength="5000" class="coord-observation-textarea w-full rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm focus:border-primary focus:ring-2 focus:ring-purple-100" placeholder="Escreva uma observação que ficará no boletim e no PDF…"><?= htmlspecialchars($observacaoAluno, ENT_QUOTES, 'UTF-8') ?></textarea>
                        <div class="flex flex-wrap items-center gap-2 mt-2">
                            <button type="button" class="coord-observation-save btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">Salvar observação</button>
                            <button type="button" class="coord-observation-cancel px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium text-gray-700 hover:bg-gray-50">Cancelar</button>
                        </div>
                    </div>
                    <span class="coord-observation-status block mt-2 text-xs text-gray-500"></span>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <?php if ($totalPaginas > 1): ?>
        <div class="flex flex-wrap items-center justify-between gap-3 mb-6 px-4 py-3 rounded-xl border border-gray-200 bg-white text-sm">
            <div class="text-gray-600">Página <?= $paginaAtual ?> de <?= $totalPaginas ?> · 20 alunos por página</div>
            <div class="flex gap-2">
                <?php if ($paginaAtual > 1): ?>
                    <a href="<?= htmlspecialchars($linkRelatorio($paginaAtual - 1), ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">‹ Anterior</a>
                <?php endif; ?>
                <?php if ($paginaAtual < $totalPaginas): ?>
                    <a href="<?= htmlspecialchars($linkRelatorio($paginaAtual + 1), ENT_QUOTES, 'UTF-8') ?>" class="px-3 py-1.5 rounded-lg border border-gray-300 text-gray-700 bg-white hover:bg-gray-50">Próxima ›</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
<?php endif; ?>

<script>
(function () {
    var fonteSelect = document.getElementById('fonte-boletim-coord');
    var eventoHidden = document.getElementById('evento-boletim-coord');
    var anoSelect = document.getElementById('ano-letivo-boletim-coord');
    var periodoSelect = document.getElementById('periodo-boletim-coord');
    var rotuloPeriodo = document.getElementById('rotulo-periodo-boletim-coord');
    var cabPeriodo = document.getElementById('cab-periodo-eventos');
    var avisoEventos = document.getElementById('eventos-filtro-vazio');
    var periodosPorAno = <?= json_encode($periodosPorAno, JSON_UNESCAPED_UNICODE) ?>;
    var form = document.getElementById('form-boletim-coordenacao');
    var checks = Array.prototype.slice.call(document.querySelectorAll('.evento-check'));
    var selecionarTodos = document.getElementById('eventos-selecionar-todos');
    var qtdEl = document.getElementById('eventos-qtd');
    var alunoInput = document.getElementById('aluno-q-boletim-coord');
    var alunoLista = document.getElementById('aluno-sugestoes-boletim-coord');
    var turmaSelect = form ? form.querySelector('select[name="turma_id"]') : null;
    var buscaAlunoUrl = <?= json_encode(rtrim((string) URL, '/') . '/admin/reports/boletim-coordenacao/buscar-alunos', JSON_UNESCAPED_SLASHES) ?>;
    var buscaTimer = null;
    var buscaSeq = 0;

    function aplicarFonte() {
        var fonte = fonteSelect ? fonteSelect.value : 'vida_escolar';
        document.querySelectorAll('.campo-fonte').forEach(function (el) {
            el.classList.toggle('hidden', !el.classList.contains('campo-fonte-' + fonte));
        });
    }
    function checksVisiveis() {
        return checks.filter(function (check) {
            var item = check.closest('.evento-item');
            return !item || !item.classList.contains('hidden');
        });
    }
    function atualizarContagem() {
        var visiveis = checksVisiveis();
        var marcados = visiveis.filter(function (check) { return check.checked; }).length;
        if (selecionarTodos) {
            selecionarTodos.checked = visiveis.length > 0 && marcados === visiveis.length;
            selecionarTodos.indeterminate = marcados > 0 && marcados < visiveis.length;
        }
        if (qtdEl) {
            qtdEl.textContent = visiveis.length === 0
                ? ''
                : (marcados + ' de ' + visiveis.length + ' selecionado(s)');
        }
    }
    function preencherPeriodos() {
        if (!periodoSelect || !anoSelect) return;
        var info = periodosPorAno[String(anoSelect.value)] || { rotulo: 'Bimestre', opcoes: [] };
        var rotulo = info.rotulo || 'Bimestre';
        if (rotuloPeriodo) rotuloPeriodo.textContent = rotulo;
        if (cabPeriodo) cabPeriodo.textContent = rotulo;
        var atual = periodoSelect.value;
        periodoSelect.innerHTML = '';
        var todos = document.createElement('option');
        todos.value = '0';
        todos.textContent = 'Todos';
        periodoSelect.appendChild(todos);
        (info.opcoes || []).forEach(function (opcao) {
            var opt = document.createElement('option');
            opt.value = String(opcao.valor);
            opt.textContent = opcao.rotulo || '';
            periodoSelect.appendChild(opt);
        });
        var existe = Array.prototype.some.call(periodoSelect.options, function (opt) { return opt.value === atual; });
        periodoSelect.value = existe ? atual : '0';
    }
    function filtrarEventos() {
        var ano = anoSelect ? (parseInt(anoSelect.value, 10) || 0) : 0;
        var periodo = periodoSelect ? (parseInt(periodoSelect.value, 10) || 0) : 0;
        var visiveis = 0;
        document.querySelectorAll('.evento-item').forEach(function (item) {
            var anoItem = parseInt(item.getAttribute('data-ano') || '0', 10);
            var bimItem = parseInt(item.getAttribute('data-bimestre') || '0', 10);
            var ok = (ano <= 0 || anoItem <= 0 || anoItem === ano) && (periodo <= 0 || bimItem === periodo);
            item.classList.toggle('hidden', !ok);
            if (!ok) {
                var check = item.querySelector('.evento-check');
                if (check) check.checked = false;
            } else {
                visiveis += 1;
            }
        });
        if (avisoEventos) avisoEventos.classList.toggle('hidden', visiveis > 0);
        atualizarContagem();
    }
    function aplicarVersaoNosChecks() {
        document.querySelectorAll('.evento-item').forEach(function (item) {
            var ativo = item.querySelector('.evento-versao[aria-pressed="true"]');
            var check = item.querySelector('.evento-check');
            if (ativo && check) {
                check.value = ativo.getAttribute('data-value') || check.value;
            }
        });
    }
    function marcarVersao(btn) {
        var grupo = btn.parentElement;
        if (!grupo) return;
        grupo.querySelectorAll('.evento-versao').forEach(function (el) {
            var ativo = el === btn;
            var vigente = el.getAttribute('data-vigente') === '1';
            el.setAttribute('aria-pressed', ativo ? 'true' : 'false');
            el.className = 'evento-versao w-full text-left rounded-md border px-2 py-1 leading-tight '
                + (ativo
                    ? (vigente ? 'border-green-400 bg-green-50 text-green-900' : 'border-gray-400 bg-gray-50 text-gray-900')
                    : 'border-gray-200 bg-white text-gray-600');
        });
        var item = btn.closest('.evento-item');
        var check = item ? item.querySelector('.evento-check') : null;
        if (!check) return;
        check.value = btn.getAttribute('data-value') || check.value;
        check.checked = true;
        atualizarContagem();
    }
    window.sincronizarEventosCoordenacao = function () {
        aplicarVersaoNosChecks();
    };
    document.querySelectorAll('.evento-versao').forEach(function (btn) {
        btn.addEventListener('click', function () {
            marcarVersao(btn);
        });
    });
    function fecharSugestoes() {
        if (!alunoLista) return;
        alunoLista.classList.add('hidden');
        alunoLista.innerHTML = '';
        if (alunoInput) alunoInput.setAttribute('aria-expanded', 'false');
    }
    function abrirSugestoes(alunos) {
        if (!alunoLista || !alunoInput) return;
        alunoLista.innerHTML = '';
        if (!alunos.length) {
            var vazio = document.createElement('div');
            vazio.className = 'px-3 py-2 text-sm text-gray-500';
            vazio.textContent = 'Nenhum aluno encontrado.';
            alunoLista.appendChild(vazio);
        } else {
            alunos.forEach(function (aluno) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'block w-full text-left px-3 py-2.5 text-sm text-gray-800 hover:bg-purple-50 border-b border-gray-100 last:border-b-0';
                btn.setAttribute('role', 'option');
                btn.textContent = aluno.rotulo || ((aluno.turma_nome ? aluno.turma_nome + ' · ' : '') + (aluno.nome || ''));
                btn.addEventListener('mousedown', function (ev) {
                    ev.preventDefault();
                    alunoInput.value = aluno.nome || '';
                    fecharSugestoes();
                    alunoInput.focus();
                });
                alunoLista.appendChild(btn);
            });
        }
        alunoLista.classList.remove('hidden');
        alunoInput.setAttribute('aria-expanded', 'true');
    }
    function buscarAlunos(termo) {
        if (!termo || termo.length < 2) {
            fecharSugestoes();
            return;
        }
        var seq = ++buscaSeq;
        var url = buscaAlunoUrl + '?aluno_q=' + encodeURIComponent(termo);
        if (turmaSelect && turmaSelect.value && turmaSelect.value !== '0') {
            url += '&turma_id=' + encodeURIComponent(turmaSelect.value);
        }
        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } })
            .then(function (resp) { return resp.json(); })
            .then(function (data) {
                if (seq !== buscaSeq) return;
                abrirSugestoes((data && data.alunos) ? data.alunos : []);
            })
            .catch(function () {
                if (seq !== buscaSeq) return;
                fecharSugestoes();
            });
    }
    if (alunoInput) {
        alunoInput.addEventListener('input', function () {
            var termo = String(alunoInput.value || '').trim();
            if (buscaTimer) clearTimeout(buscaTimer);
            buscaTimer = setTimeout(function () { buscarAlunos(termo); }, 220);
        });
        alunoInput.addEventListener('keydown', function (ev) {
            if (ev.key === 'Escape') fecharSugestoes();
        });
        alunoInput.addEventListener('blur', function () {
            setTimeout(fecharSugestoes, 150);
        });
    }
    if (selecionarTodos) {
        selecionarTodos.addEventListener('change', function () {
            checksVisiveis().forEach(function (check) { check.checked = selecionarTodos.checked; });
            atualizarContagem();
        });
    }
    checks.forEach(function (check) {
        check.addEventListener('change', atualizarContagem);
    });
    if (form) {
        form.addEventListener('submit', function (event) {
            window.sincronizarEventosCoordenacao();
            var submitter = event.submitter;
            var gerar = submitter && submitter.name === 'executar';
            var fonte = fonteSelect ? fonteSelect.value : 'vida_escolar';
            var alunoBusca = alunoInput ? String(alunoInput.value || '').trim() : '';
            var visiveis = checksVisiveis();
            var nenhum = visiveis.length > 0 && !visiveis.some(function (check) { return check.checked; });
            if (gerar && (fonte === 'evento' || fonte === 'demonstrativo') && nenhum) {
                if (alunoBusca !== '') {
                    visiveis.forEach(function (check) { check.checked = true; check.disabled = false; });
                    if (eventoHidden) {
                        eventoHidden.disabled = false;
                        eventoHidden.value = 'todos';
                    }
                    atualizarContagem();
                } else {
                    event.preventDefault();
                    window.alert('Selecione ao menos um boletim ou informe o aluno.');
                }
            } else if (eventoHidden) {
                eventoHidden.value = '';
            }
        });
    }
    if (fonteSelect) {
        fonteSelect.addEventListener('change', aplicarFonte);
        aplicarFonte();
    }
    if (anoSelect) {
        anoSelect.addEventListener('change', function () {
            preencherPeriodos();
            filtrarEventos();
        });
    }
    if (periodoSelect) {
        periodoSelect.addEventListener('change', filtrarEventos);
    }
    filtrarEventos();
})();
</script>
<?php if (!empty($pode_editar_observacao)): ?>
<script>
(function () {
    document.querySelectorAll('.coord-observation').forEach(function (block) {
        var editButton = block.querySelector('.coord-observation-edit');
        var view = block.querySelector('.coord-observation-view');
        var formWrap = block.querySelector('.coord-observation-form');
        var text = block.querySelector('.coord-observation-text');
        var textarea = block.querySelector('.coord-observation-textarea');
        var saveButton = block.querySelector('.coord-observation-save');
        var cancelButton = block.querySelector('.coord-observation-cancel');
        var status = block.querySelector('.coord-observation-status');
        if (!editButton || !view || !formWrap || !textarea) return;

        var saved = textarea.value || '';
        function renderView() {
            var hasContent = saved.trim() !== '';
            text.textContent = hasContent ? saved : 'Nenhuma observação registrada.';
            text.classList.toggle('italic', !hasContent);
            text.classList.toggle('text-gray-400', !hasContent);
            editButton.textContent = hasContent ? 'Editar' : 'Adicionar observação';
            view.classList.remove('hidden');
            formWrap.classList.add('hidden');
        }
        editButton.addEventListener('click', function () {
            textarea.value = saved;
            view.classList.add('hidden');
            formWrap.classList.remove('hidden');
            status.textContent = '';
            textarea.focus();
        });
        cancelButton.addEventListener('click', renderView);
        saveButton.addEventListener('click', function () {
            saveButton.disabled = true;
            status.textContent = 'Salvando...';
            status.className = 'coord-observation-status text-xs text-gray-500';
            var payload = new FormData();
            payload.append('_token', block.dataset.csrf || '');
            payload.append('conteudo', textarea.value || '');
            fetch(block.dataset.endpoint || '', {
                method: 'POST',
                body: payload,
                headers: {'X-Requested-With': 'XMLHttpRequest'}
            }).then(function (response) {
                return response.json().then(function (data) {
                    if (!response.ok || !data.success) throw new Error(data.error || 'Erro ao salvar observação');
                    return data;
                });
            }).then(function (data) {
                saved = data.conteudo || '';
                textarea.value = saved;
                renderView();
                status.textContent = 'Observação salva.';
                status.className = 'coord-observation-status text-xs text-emerald-600';
            }).catch(function (error) {
                status.textContent = error.message || 'Erro ao salvar observação.';
                status.className = 'coord-observation-status text-xs text-red-600';
            }).finally(function () {
                saveButton.disabled = false;
            });
        });
    });
})();
</script>
<?php endif; ?>
<?php if ($zipGerando): ?>
<?php include dirname(__DIR__, 2) . '/components/ai-job-poller.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var jobId = <?= (int) $zipJobId ?>;
    var downloadUrl = <?= json_encode(rtrim((string) URL, '/') . '/admin/reports/boletim-coordenacao/zip/' . (int) $zipJobId) ?>;
    var texto = document.getElementById('zip-boletins-texto');
    var banner = document.getElementById('zip-boletins-banner');
    var horariosEl = document.getElementById('zip-boletins-horarios');

    function formatarHorarioZip(valor) {
        if (!valor) return '';
        var s = String(valor).replace('T', ' ').replace(/\.\d+.*/, '');
        var m = s.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2}):(\d{2})/);
        if (!m) return s;
        return m[3] + '/' + m[2] + '/' + m[1] + ' ' + m[4] + ':' + m[5] + ':' + m[6];
    }

    function formatarDuracaoZip(inicio, fim) {
        var a = Date.parse(String(inicio || '').replace(' ', 'T'));
        var b = Date.parse(String(fim || '').replace(' ', 'T'));
        if (!a || !b || b < a) return '';
        var seg = Math.round((b - a) / 1000);
        if (seg < 60) return seg + 's';
        var min = Math.floor(seg / 60);
        var resto = seg % 60;
        if (min < 60) return resto ? (min + ' min ' + resto + 's') : (min + ' min');
        var h = Math.floor(min / 60);
        min = min % 60;
        return min ? (h + 'h ' + min + ' min') : (h + 'h');
    }

    function textoHorarios(inicio, termino, pedido, emAndamento) {
        var ini = formatarHorarioZip(inicio);
        var fim = formatarHorarioZip(termino);
        var ped = formatarHorarioZip(pedido);
        if (ini) {
            var t = 'Início: ' + ini + (fim ? ' · Término: ' + fim : (emAndamento ? ' · Término: em andamento' : ''));
            var dur = formatarDuracaoZip(inicio, termino);
            if (dur) t += ' · Duração: ' + dur;
            return t;
        }
        if (ped) return 'Pedido às ' + ped + ' · Aguardando início da geração';
        return 'Horários da geração aparecem assim que o processamento começar.';
    }

    function atualizarHorarios(data, emAndamento) {
        if (!horariosEl) return;
        data = data || {};
        var result = data.result || {};
        horariosEl.textContent = textoHorarios(
            data.iniciado_em || result.iniciado_em || '',
            data.finalizado_em || result.finalizado_em || data.completed_at || '',
            data.created_at || '',
            emAndamento
        );
    }

    new AIJobPoller(jobId, {
        interval: 4000,
        statusUrl: <?= json_encode(rtrim((string) URL, '/') . '/admin/ai-job/{id}/status') ?>,
        onDone: function (result) {
            var emitidos = result && result.emitidos ? result.emitidos : 0;
            var falhas = result && result.falhas ? result.falhas : 0;
            var linhaHorarios = textoHorarios(
                result && result.iniciado_em,
                result && result.finalizado_em,
                '',
                false
            );
            if (banner) {
                banner.className = 'rounded-lg p-4 mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800';
                banner.innerHTML = '<p class="font-semibold"><i class="fa-solid fa-circle-check mr-2"></i>ZIP pronto'
                    + (emitidos ? ': ' + emitidos + ' boletim(ns)' : '')
                    + (falhas ? ' · ' + falhas + ' falha(s)' : '')
                    + '.</p>'
                    + '<p class="text-sm mt-2 text-emerald-800">' + linhaHorarios + '</p>'
                    + '<a href="' + downloadUrl + '" class="inline-flex items-center mt-2 px-4 py-2 rounded-lg bg-emerald-600 text-white hover:bg-emerald-700 font-semibold"><i class="fa-solid fa-download mr-2"></i>Baixar ZIP</a>';
            }
            var iframe = document.createElement('iframe');
            iframe.style.display = 'none';
            iframe.src = downloadUrl;
            document.body.appendChild(iframe);
        },
        onFailed: function (msg) {
            if (banner) {
                banner.className = 'rounded-lg p-4 mb-6 bg-red-50 border border-red-200 text-red-800';
            }
            if (texto) {
                texto.textContent = 'Não foi possível gerar o ZIP: ' + (msg || 'erro desconhecido');
            }
            if (horariosEl) {
                horariosEl.className = 'text-sm mt-2 text-red-700';
            }
        },
        onProgress: function (status, data) {
            atualizarHorarios(data, true);
            if (!texto) return;
            if (status === 'pending') {
                texto.textContent = 'Na fila: a geração começa em instantes.';
            } else if (status === 'processing') {
                texto.textContent = 'Gerando os boletins em segundo plano. Um PDF por aluno, depois o ZIP. Com a escola inteira pode levar vários minutos — deixe esta página aberta.';
            }
        }
    });
});
</script>
<?php endif; ?>
