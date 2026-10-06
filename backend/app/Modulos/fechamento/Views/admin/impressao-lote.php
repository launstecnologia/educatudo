<?php
$resumo = is_array($impressao_lote ?? null) ? $impressao_lote : [];
$pode = !empty($resumo['pode']);
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$turmaId = (int) ($turma_id ?? 0);
$serie = trim((string) ($serie ?? ''));
$series = is_array($series ?? null) ? $series : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$escopoOk = !empty($escopo_ok);
$jobs = is_array($jobs ?? null) ? $jobs : [];
$pdfsSalvos = is_array($pdfs_salvos ?? null) ? $pdfs_salvos : [];
$documentos = is_array($documentos ?? null) ? $documentos : [];
$qs = http_build_query(array_filter([
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
    'turma_id' => $turmaId > 0 ? $turmaId : null,
    'serie' => $serie !== '' ? $serie : null,
], static fn ($v) => $v !== null && $v !== ''));
$painelQs = http_build_query([
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
    'turma_id' => $turmaId,
]);
$pacote = is_array($pacote ?? null) ? $pacote : [];
$descricoes = [
    'boletim' => 'Um boletim por aluno, na ordem das turmas homologadas. Paisagem.',
    'ficha' => 'Uma ficha individual por aluno. Retrato.',
    'historico' => 'Um histórico por aluno. Entra a versão emitida, assinada, entregue ou o rascunho. Quem ainda não tem histórico fica de fora até você preparar os rascunhos.',
    'resultado' => 'A ata de resultados finais, uma por turma. É o resultado final da turma. Paisagem.',
    'relatorio' => 'O relatório de fechamento, um por turma. Paisagem.',
];
$page_header_title = 'Impressão em lote';
$page_header_subtitle = 'Boletim, ficha individual, histórico e resultado final da turma, depois da homologação.';
ob_start();
?>
<a href="<?= URL ?>/admin/fechamento?<?= htmlspecialchars($painelQs) ?>" class="text-gray-600 hover:text-gray-900 text-sm">← Fechamento</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<form method="get" action="<?= URL ?>/admin/fechamento/impressao-lote" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6">
    <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
    <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label for="serie" class="block text-sm font-medium text-gray-700 mb-1.5">Série</label>
            <select id="serie" name="serie" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Todas as séries</option>
                <?php foreach ($series as $opcao): ?>
                <option value="<?= htmlspecialchars((string) $opcao, ENT_QUOTES, 'UTF-8') ?>" <?= $serie === (string) $opcao ? 'selected' : '' ?>><?= htmlspecialchars((string) $opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
            <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="0">Todas as turmas da série</option>
                <?php foreach ($turmas as $turma):
                    $serieTurma = trim((string) ($turma['serie'] ?? ''));
                    if ($serie !== '' && $serieTurma !== $serie) {
                        continue;
                    }
                ?>
                <option value="<?= (int) ($turma['id'] ?? 0) ?>" <?= $turmaId === (int) ($turma['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($turma['nome'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50">Aplicar recorte</button>
        </div>
    </div>
    <p class="text-sm text-gray-500 mt-4">A geração roda em segundo plano e só começa com uma turma ou uma série. Boletim, ficha e histórico aceitam até <?= (int) ImpressaoLoteFechamentoService::MAX_ALUNOS ?> alunos por vez.</p>
</form>

<?php if ($jobs !== []): ?>
<div id="lote-jobs" class="mb-6 p-4 rounded-lg bg-sky-50 border border-sky-200 text-sky-900 text-sm"
     data-jobs="<?= htmlspecialchars(implode(',', $jobs), ENT_QUOTES, 'UTF-8') ?>"
     data-status="<?= URL ?>/admin/ai-job/"
     data-pdf="<?= URL ?>/admin/fechamento/impressao-lote/pdf?chave=">
    Geração em andamento. Pode deixar esta página aberta.
    <ul class="mt-2 space-y-1" id="lote-jobs-lista"></ul>
</div>
<script>
(function () {
    var caixa = document.getElementById('lote-jobs');
    var lista = document.getElementById('lote-jobs-lista');
    if (!caixa || !lista) return;
    var ids = (caixa.getAttribute('data-jobs') || '').split(',').filter(Boolean);
    var baseStatus = caixa.getAttribute('data-status') || '';
    var basePdf = caixa.getAttribute('data-pdf') || '';
    var nomes = <?= json_encode(ImpressaoLoteFechamentoService::DOCUMENTOS, JSON_UNESCAPED_UNICODE) ?>;
    function desenhar(id, dados) {
        var item = document.getElementById('job-' + id);
        if (!item) {
            item = document.createElement('li');
            item.id = 'job-' + id;
            lista.appendChild(item);
        }
        var doc = (dados && dados.documento && nomes[dados.documento]) ? nomes[dados.documento] : ('Documento ' + id);
        var status = dados && dados.status ? dados.status : 'pending';
        if (status === 'done') {
            var chave = dados && (dados.arquivo_key || (dados.result && dados.result.arquivo_key)) ? (dados.arquivo_key || dados.result.arquivo_key) : '';
            if (chave) {
                item.innerHTML = doc + ' — <a class="underline font-medium" target="_blank" rel="noopener" href="' + basePdf + encodeURIComponent(chave) + '">Abrir PDF</a>';
            } else {
                item.textContent = doc + ' — pronto, mas o PDF não foi localizado.';
            }
            return true;
        }
        if (status === 'failed') {
            item.textContent = doc + ' — ' + ((dados && dados.error) ? dados.error : 'a geração falhou. Tente de novo.');
            return true;
        }
        item.textContent = doc + ' — ' + ((dados && dados.andamento) ? dados.andamento : 'na fila');
        return false;
    }
    function consultar() {
        var pendentes = 0;
        ids.forEach(function (id) {
            fetch(baseStatus + id + '/status', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (dados) {
                    if (!desenhar(id, dados)) pendentes++;
                })
                .catch(function () { desenhar(id, { status: 'pending', andamento: 'aguardando o servidor' }); });
        });
    }
    ids.forEach(function (id) { desenhar(id, { status: 'pending' }); });
    consultar();
    setInterval(consultar, 4000);
})();
</script>
<?php endif; ?>


<?php if (!$pode): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    <?php if ((int) ($resumo['total_turmas'] ?? 0) === 0): ?>
        Nenhuma turma neste filtro.
    <?php else: ?>
        Homologue todas as turmas deste filtro para imprimir.
        <?= (int) ($resumo['homologadas'] ?? 0) ?> de <?= (int) ($resumo['total_turmas'] ?? 0) ?> já homologadas.
        <?php if (!empty($resumo['pendentes'])): ?>
            Faltam: <?= htmlspecialchars(implode(', ', $resumo['pendentes']), ENT_QUOTES, 'UTF-8') ?>.
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php elseif (!$escopoOk): ?>
<div class="mb-6 p-4 rounded-lg bg-sky-50 border border-sky-200 text-sky-900 text-sm">
    Escolha uma turma ou uma série acima. Sem esse recorte a geração tenta a escola inteira e a página trava.
</div>
<?php else: ?>
<p class="text-sm text-gray-600 mb-6">
    <?= (int) ($resumo['total_turmas'] ?? 0) ?> turma(s) homologada(s),
    <?= (int) ($resumo['total_alunos'] ?? 0) ?> aluno(s).
    O servidor monta o arquivo e o link de impressão aparece aqui. Boletim, histórico, ata e relatório saem em paisagem; a ficha, em retrato.
</p>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 mb-6 flex flex-col gap-4">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Gerar os quatro de uma vez</h3>
            <p class="text-sm text-gray-500 mt-1">Enfileira boletim, ficha individual, histórico e resultado final deste recorte. Um documento por vez, em segundo plano.</p>
        </div>
        <form method="post" action="<?= URL ?>/admin/fechamento/impressao-lote/enfileirar">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
            <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
            <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
            <input type="hidden" name="serie" value="<?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="documento" value="pacote">
            <label class="flex items-center gap-2 text-sm text-gray-600 mb-3 md:mb-0 md:mr-4">
                <input type="checkbox" name="regenerar" value="1"> Gerar de novo
            </label>
            <button type="submit" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold shrink-0">
                <i class="fa-solid fa-print mr-2"></i> Gerar tudo
            </button>
        </form>
    </div>
    <?php if (!empty($pode_preparar_historico)): ?>
    <form method="post" action="<?= URL ?>/admin/fechamento/impressao-lote/preparar-historicos" class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 pt-4 border-t border-gray-100">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
        <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
        <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
        <input type="hidden" name="serie" value="<?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?>">
        <p class="text-sm text-gray-500">O histórico só entra se o aluno já tiver rascunho ou versão emitida. Este passo cria o rascunho de conclusão de quem ainda não tem.</p>
        <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 shrink-0">
            Preparar históricos
        </button>
    </form>
    <?php endif; ?>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4">
    <?php foreach ($documentos as $chave => $rotulo): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 flex flex-col">
        <h3 class="text-lg font-semibold text-gray-900 mb-2"><?= htmlspecialchars((string) $rotulo) ?></h3>
        <p class="text-sm text-gray-500 mb-6 flex-1"><?= htmlspecialchars($descricoes[$chave] ?? '') ?></p>
        <?php if (!empty($pdfs_salvos[$chave])): ?>
        <a href="<?= URL ?>/admin/fechamento/impressao-lote/pdf?chave=<?= rawurlencode((string) $pdfs_salvos[$chave]) ?>"
           target="_blank" rel="noopener"
           class="inline-flex items-center text-sm font-medium text-indigo-700 hover:underline mb-4">
            <i class="fa-solid fa-file-pdf mr-2"></i> Abrir PDF salvo
        </a>
        <?php endif; ?>
        <form method="post" action="<?= URL ?>/admin/fechamento/impressao-lote/enfileirar">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
            <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
            <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
            <input type="hidden" name="serie" value="<?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="documento" value="<?= htmlspecialchars((string) $chave, ENT_QUOTES, 'UTF-8') ?>">
            <button type="submit" class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold">
                <i class="fa-solid fa-print mr-2"></i> Gerar em segundo plano
            </button>
        </form>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
