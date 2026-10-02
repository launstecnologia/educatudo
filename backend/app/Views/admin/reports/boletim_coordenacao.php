<?php
$fonte = (($fonte ?? 'vida_escolar') === 'evento') ? 'evento' : 'vida_escolar';
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
    <p class="text-gray-600 mt-1">Escolha o boletim da Vida Escolar ou as notas do evento (provas, trabalhos e médias).</p>
</div>

<form method="GET" action="<?= URL ?>/admin/reports/boletim-coordenacao" id="form-boletim-coordenacao" class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5 md:p-6 mb-6">
    <?php $alunoQFiltro = trim((string) ($aluno_q ?? '')); ?>
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4">
        <label class="block md:col-span-2">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Aluno</span>
            <input type="text" name="aluno_q" value="<?= htmlspecialchars($alunoQFiltro, ENT_QUOTES, 'UTF-8') ?>"
                   placeholder="Nome, RA ou código do aluno"
                   class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100"
                   autocomplete="off">
            <span class="block text-xs text-gray-500 mt-1">Traz todos os boletins e notas desse aluno no filtro atual.</span>
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Exibir</span>
            <select name="fonte" id="fonte-boletim-coord" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
                <option value="vida_escolar" <?= $fonte === 'vida_escolar' ? 'selected' : '' ?>>Boletim da Vida Escolar</option>
                <option value="evento" <?= $fonte === 'evento' ? 'selected' : '' ?>>Notas do evento</option>
            </select>
        </label>
        <label class="block campo-fonte campo-fonte-vida_escolar <?= $fonte === 'vida_escolar' ? '' : 'hidden' ?>">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Ano letivo</span>
            <select name="ano_letivo" id="ano-letivo-boletim-coord" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100" <?= $fonte === 'vida_escolar' ? 'required' : '' ?>>
                <?php foreach ($anosLetivos as $ano): ?>
                    <option value="<?= (int) $ano ?>" <?= $anoSelecionado === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Turma</span>
            <select name="turma_id" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
                <option value="0">Todas as turmas</option>
                <?php foreach ((array) ($turmas ?? []) as $turma): ?>
                    <option value="<?= (int) $turma['id'] ?>" <?= (int) ($turma_id ?? 0) === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Média final abaixo de</span>
            <input type="text" name="nota_abaixo_de" inputmode="decimal" placeholder="Ex.: 7 ou 6,5" value="<?= htmlspecialchars($nota_abaixo_de !== null ? str_replace('.', ',', (string) $nota_abaixo_de) : '') ?>" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
        </label>
        <label class="block">
            <span class="block text-sm font-semibold text-gray-700 mb-1.5">Exibir matérias</span>
            <select name="materias_exibicao" class="w-full h-11 rounded-xl border border-gray-300 bg-white px-3 text-gray-900 focus:border-primary focus:ring-2 focus:ring-purple-100">
                <option value="todas" <?= ($materias_exibicao ?? 'todas') === 'todas' ? 'selected' : '' ?>>Todas as matérias do aluno</option>
                <option value="abaixo" <?= ($materias_exibicao ?? 'todas') === 'abaixo' ? 'selected' : '' ?>>Somente matérias abaixo do corte</option>
            </select>
        </label>
    </div>

    <div class="campo-fonte campo-fonte-evento mt-5 <?= $fonte === 'evento' ? '' : 'hidden' ?>">
        <div class="flex flex-wrap items-center justify-between gap-3 mb-2">
            <span class="text-sm font-semibold text-gray-700">Boletins</span>
            <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                <input type="checkbox" id="eventos-selecionar-todos" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= $selecionarTodos ? 'checked' : '' ?>>
                Selecionar todos
            </label>
            <span id="eventos-qtd" class="text-xs text-gray-500"></span>
        </div>
        <div id="lista-eventos-coord" class="max-h-72 overflow-y-auto rounded-xl border border-gray-200 bg-gray-50/40">
            <?php if (empty($eventos)): ?>
                <p class="px-4 py-6 text-sm text-gray-500">Nenhuma avaliação gerada. Em Avaliações, gere o lote para ver provas, trabalhos e médias.</p>
            <?php endif; ?>
            <?php foreach ((array) ($eventos ?? []) as $evento):
                $value = (int) $evento['regra_id'] . ':' . base64_encode((string) $evento['periodo_ref']);
                $marcado = $selecionarTodos || isset($eventosSelecionados[$value]);
                $vigente = !empty($evento['eh_vigente']);
                ?>
                <label class="evento-item flex items-center gap-3 px-4 py-2.5 bg-white border-b border-gray-100 last:border-b-0 cursor-pointer hover:bg-purple-50/40">
                    <input type="checkbox" name="eventos[]" value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>" class="evento-check w-4 h-4 shrink-0 rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= $marcado ? 'checked' : '' ?>>
                    <span class="min-w-0 flex-1">
                        <span class="block text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) ($evento['nome_exibicao'] ?? $evento['nome'])) ?></span>
                        <span class="block text-xs text-gray-500 truncate"><?= htmlspecialchars((string) ($evento['nome_detalhe'] ?? '')) ?></span>
                    </span>
                    <span class="shrink-0 text-[11px] font-medium px-2 py-0.5 rounded-full <?= $vigente ? 'bg-emerald-50 text-emerald-700' : 'bg-gray-100 text-gray-600' ?>"><?= $vigente ? 'Vigente' : 'Anterior' ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="evento" id="evento-boletim-coord" value="<?= $selecionarTodos ? 'todos' : '' ?>">
        <label class="mt-3 inline-flex items-center gap-2 text-sm text-gray-600 cursor-pointer">
            <input type="checkbox" name="incluir_antigas" value="1" id="incluir-antigas-boletim-coord"
                   class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500"
                   <?= $incluirAntigas ? 'checked' : '' ?>
                   onchange="if (window.sincronizarEventosCoordenacao) { window.sincronizarEventosCoordenacao(); } this.form.submit();">
            Incluir versões anteriores
        </label>
        <?php if ($incluirAntigas): ?>
            <span class="block text-xs text-gray-500 mt-1">Vigente = oficial. Anterior = histórico da mesma regra.</span>
        <?php endif; ?>
    </div>

    <label class="inline-flex items-center gap-2.5 mt-5 text-sm text-gray-700 cursor-pointer">
        <input type="checkbox" name="assinatura" value="1" class="w-4 h-4 rounded border-gray-300 text-purple-600 focus:ring-purple-500" <?= !empty($incluir_assinatura) ? 'checked' : '' ?>>
        Incluir campo de assinatura ao lado do nome do aluno
    </label>
    <div class="mt-5 flex flex-wrap gap-3">
        <button type="submit" name="executar" value="1" class="btn-primary-custom px-5 py-2.5 rounded-xl font-semibold shadow-sm hover:opacity-90 transition-opacity"><i class="fa-solid fa-chart-column mr-2"></i>Gerar relatório</button>
        <a href="<?= URL ?>/admin/reports/boletim-coordenacao" class="px-5 py-2.5 rounded-xl border border-gray-300 text-gray-700 hover:bg-gray-50 transition-colors">Limpar</a>
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
            <?php elseif ($eventosTotal === 1 && !empty($relatorio['total_alunos'])): ?>
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
        <div class="bg-amber-50 border border-amber-200 text-amber-800 rounded-lg p-4"><?= $fonteRelatorio === 'vida_escolar' ? 'Nenhuma ficha da Vida Escolar encontrada para os filtros selecionados.' : 'Nenhum boletim encontrado para os filtros selecionados.' ?></div>
    <?php endif; ?>

    <?php foreach ($grupos as $grupo): ?>
        <?php
        $colunasGrupo = is_array($grupo['columns'] ?? null) ? $grupo['columns'] : [];
        $casasGrupo = (int) ($grupo['decimal_places'] ?? $relatorio['decimal_places'] ?? 1);
        $refEvento = (int) ($grupo['regra_id'] ?? 0);
        $rotuloGrupo = (string) ($grupo['evento_rotulo'] ?? $grupo['evento_nome'] ?? '');
        $detalheGrupo = (string) ($grupo['evento_detalhe'] ?? '');
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
                    <?php if ($refEvento > 0): ?><span class="text-sm text-gray-500">Ref: <?= $refEvento ?></span><?php endif; ?>
                    <?php if ((string) ($aluno['ra'] ?? '') !== ''): ?><span class="text-sm text-gray-500">RA: <?= htmlspecialchars((string) $aluno['ra'], ENT_QUOTES, 'UTF-8') ?></span><?php endif; ?>
                </div>
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
    var form = document.getElementById('form-boletim-coordenacao');
    var checks = Array.prototype.slice.call(document.querySelectorAll('.evento-check'));
    var selecionarTodos = document.getElementById('eventos-selecionar-todos');
    var qtdEl = document.getElementById('eventos-qtd');

    function aplicarFonte() {
        var fonte = fonteSelect ? fonteSelect.value : 'vida_escolar';
        document.querySelectorAll('.campo-fonte').forEach(function (el) {
            el.classList.toggle('hidden', !el.classList.contains('campo-fonte-' + fonte));
        });
        if (anoSelect) {
            anoSelect.required = fonte === 'vida_escolar';
        }
    }
    function atualizarContagem() {
        var marcados = checks.filter(function (check) { return check.checked; }).length;
        if (selecionarTodos) {
            selecionarTodos.checked = checks.length > 0 && marcados === checks.length;
            selecionarTodos.indeterminate = marcados > 0 && marcados < checks.length;
        }
        if (qtdEl) {
            qtdEl.textContent = checks.length === 0
                ? ''
                : (marcados + ' de ' + checks.length + ' selecionado(s)');
        }
    }
    window.sincronizarEventosCoordenacao = function () {
        var todos = checks.length > 0 && checks.every(function (check) { return check.checked; });
        if (eventoHidden) {
            eventoHidden.disabled = false;
            eventoHidden.value = todos ? 'todos' : '';
        }
        checks.forEach(function (check) {
            check.disabled = todos;
        });
    };
    if (selecionarTodos) {
        selecionarTodos.addEventListener('change', function () {
            checks.forEach(function (check) { check.checked = selecionarTodos.checked; });
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
            var alunoInput = form.querySelector('input[name="aluno_q"]');
            var alunoBusca = alunoInput ? String(alunoInput.value || '').trim() : '';
            if (gerar && fonte === 'evento' && checks.length > 0 && !checks.some(function (check) { return check.checked; })) {
                if (alunoBusca !== '') {
                    checks.forEach(function (check) { check.checked = true; check.disabled = false; });
                    if (eventoHidden) {
                        eventoHidden.disabled = false;
                        eventoHidden.value = 'todos';
                    }
                    atualizarContagem();
                    window.sincronizarEventosCoordenacao();
                } else {
                    event.preventDefault();
                    checks.forEach(function (check) { check.disabled = false; });
                    window.alert('Selecione ao menos um boletim ou informe o aluno.');
                }
            }
        });
    }
    if (fonteSelect) {
        fonteSelect.addEventListener('change', aplicarFonte);
        aplicarFonte();
    }
    atualizarContagem();
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
