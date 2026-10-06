<?php
$resumo = is_array($impressao_lote ?? null) ? $impressao_lote : [];
$anoLetivo = (int) ($ano_letivo ?? date('Y'));
$periodoTipo = (string) ($periodo_tipo ?? 'ano');
$periodoNumero = (int) ($periodo_numero ?? 0);
$turmaId = (int) ($turma_id ?? 0);
$serie = trim((string) ($serie ?? ''));
$series = is_array($series ?? null) ? $series : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$escopoOk = !empty($escopo_ok);
$jobs = is_array($jobs ?? null) ? $jobs : [];
$linhas = is_array($linhas ?? null) ? $linhas : [];
$documentos = is_array($documentos ?? null) ? $documentos : [];
$pacote = is_array($pacote ?? null) ? $pacote : [];
$painelQs = http_build_query([
    'ano_letivo' => $anoLetivo,
    'periodo_tipo' => $periodoTipo,
    'periodo_numero' => $periodoNumero,
]);
$homologadas = (int) ($resumo['homologadas'] ?? 0);
$page_header_title = 'Impressão em lote';
$page_header_subtitle = 'Escolha a série, emita os quatro documentos e baixe cada um no menu da turma.';
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
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 items-end">
        <div>
            <label for="serie" class="block text-sm font-medium text-gray-700 mb-1.5">Série</label>
            <select id="serie" name="serie" onchange="var turma=document.getElementById('turma_id'); if(turma){ turma.disabled=false; turma.value='0'; } this.form.submit();" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
                <option value="">Escolha a série</option>
                <?php foreach ($series as $opcao): ?>
                <option value="<?= htmlspecialchars((string) $opcao, ENT_QUOTES, 'UTF-8') ?>" <?= $serie === (string) $opcao ? 'selected' : '' ?>><?= htmlspecialchars((string) $opcao) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
            <select id="turma_id" name="turma_id" onchange="this.form.submit()" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white" <?= $serie === '' ? 'disabled' : '' ?>>
                <option value="0">Todas as turmas desta série</option>
                <?php foreach ($turmas as $turma):
                    $serieTurma = trim((string) ($turma['serie'] ?? ''));
                    if ($serie === '' || $serieTurma !== $serie) {
                        continue;
                    }
                ?>
                <option value="<?= (int) ($turma['id'] ?? 0) ?>" <?= $turmaId === (int) ($turma['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($turma['nome'] ?? '')) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <p class="text-sm text-gray-500 mt-4">Cada turma ganha o próprio PDF. Trocar a turma mostra o download dela. Boletim, ficha e histórico aceitam até <?= (int) ImpressaoLoteFechamentoService::MAX_ALUNOS ?> alunos por turma.</p>
</form>

<?php if ($jobs !== []): ?>
<div id="lote-jobs" class="mb-6 p-4 rounded-lg bg-sky-50 border border-sky-200 text-sky-900 text-sm"
     data-jobs="<?= htmlspecialchars(implode(',', $jobs), ENT_QUOTES, 'UTF-8') ?>"
     data-status="<?= URL ?>/admin/ai-job/"
     data-pdf="<?= URL ?>/admin/fechamento/impressao-lote/pdf?chave=">
    Geração em andamento. Pode deixar esta página aberta. Quando terminar, o download fica em Ações na turma.
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
    var recarregou = false;
    function desenhar(id, dados) {
        var item = document.getElementById('job-' + id);
        if (!item) {
            item = document.createElement('li');
            item.id = 'job-' + id;
            lista.appendChild(item);
        }
        var doc = (dados && dados.documento && nomes[dados.documento]) ? nomes[dados.documento] : ('Documento ' + id);
        if (dados && dados.rotulo) doc = dados.rotulo + ' — ' + doc;
        var status = dados && dados.status ? dados.status : 'pending';
        if (status === 'done') {
            var chave = dados && (dados.arquivo_key || (dados.result && dados.result.arquivo_key)) ? (dados.arquivo_key || dados.result.arquivo_key) : '';
            if (chave) {
                item.innerHTML = doc + ' — <a class="underline font-medium" target="_blank" rel="noopener" href="' + basePdf + encodeURIComponent(chave) + '">Abrir PDF</a>';
            } else {
                item.textContent = doc + ' — pronto, mas o PDF não foi localizado.';
            }
            return 'done';
        }
        if (status === 'failed') {
            item.textContent = doc + ' — ' + ((dados && dados.error) ? dados.error : 'a geração falhou. Tente de novo.');
            return 'failed';
        }
        item.textContent = doc + ' — ' + ((dados && dados.andamento) ? dados.andamento : 'na fila');
        return 'pending';
    }
    function consultar() {
        var estados = {};
        var faltam = ids.length;
        ids.forEach(function (id) {
            fetch(baseStatus + id + '/status', { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (dados) { estados[id] = desenhar(id, dados); })
                .catch(function () { estados[id] = desenhar(id, { status: 'pending', andamento: 'aguardando o servidor' }); })
                .then(function () {
                    faltam--;
                    if (faltam > 0 || recarregou) return;
                    var todosProntos = ids.every(function (jobId) { return estados[jobId] === 'done'; });
                    if (!todosProntos) return;
                    recarregou = true;
                    var url = new URL(window.location.href);
                    url.searchParams.delete('jobs');
                    window.setTimeout(function () { window.location.replace(url.toString()); }, 600);
                });
        });
    }
    ids.forEach(function (id) { desenhar(id, { status: 'pending' }); });
    consultar();
    setInterval(consultar, 4000);
})();
</script>
<?php endif; ?>

<?php if (!$escopoOk): ?>
<div class="mb-6 p-4 rounded-lg bg-sky-50 border border-sky-200 text-sky-900 text-sm">
    Escolha a série. A lista mostra cada turma, e em Ações você baixa o boletim, a ficha, o histórico e o resultado final.
</div>
<?php elseif ($linhas === []): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Nenhuma turma nesta série.
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 mb-6">
    <div class="p-6 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 border-b border-gray-100">
        <div>
            <h3 class="text-lg font-semibold text-gray-900"><?= $turmaId > 0 ? 'Esta turma' : htmlspecialchars($serie) ?></h3>
            <p class="text-sm text-gray-500 mt-1">
                <?= $homologadas ?> de <?= count($linhas) ?> turma(s) homologada(s).
                Emite boletim, ficha, histórico e resultado final só do que ainda não tem PDF. O histórico sai emitido, com as escolas dos anos anteriores.
            </p>
        </div>
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 shrink-0">
            <?php if (!empty($pode_preparar_historico) && $homologadas > 0): ?>
            <form method="post" action="<?= URL ?>/admin/fechamento/impressao-lote/preparar-historicos">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
                <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                <input type="hidden" name="serie" value="<?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-medium border border-gray-300 bg-white text-gray-700 hover:bg-gray-50 w-full sm:w-auto">
                    Preparar históricos
                </button>
            </form>
            <?php endif; ?>
            <?php if ($homologadas > 0): ?>
            <form method="post" action="<?= URL ?>/admin/fechamento/impressao-lote/enfileirar" class="flex flex-col sm:flex-row sm:items-center gap-3">
                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
                <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                <input type="hidden" name="serie" value="<?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="documento" value="pacote">
                <button type="submit" class="btn-primary-custom inline-flex items-center justify-center px-4 py-2.5 rounded-lg text-sm font-semibold">
                    <i class="fa-solid fa-print mr-2"></i> Emitir o que falta
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Alunos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">PDFs</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($linhas as $linha):
                    $pdfs = is_array($linha['pdfs'] ?? null) ? $linha['pdfs'] : [];
                    $prontos = 0;
                    foreach ($pacote as $chavePacote) {
                        if (!empty($pdfs[$chavePacote])) {
                            $prontos++;
                        }
                    }
                    $tid = (int) ($linha['id'] ?? 0);
                    $homologada = !empty($linha['homologada']);
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-medium text-gray-900"><?= htmlspecialchars((string) ($linha['nome'] ?? '')) ?></td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $homologada ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-800' ?>">
                            <?= htmlspecialchars((string) ($linha['status_rotulo'] ?? '')) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= (int) ($linha['alunos'] ?? 0) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700">
                        <?php if (!$homologada): ?>
                            Homologue para emitir
                        <?php else: ?>
                            <?= $prontos ?> de <?= count($pacote) ?> pronto<?= $prontos === 1 ? '' : 's' ?>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-right">
                        <?php if ($homologada): ?>
                        <?php ob_start(); ?>
                        <?php foreach ($documentos as $chave => $rotulo): ?>
                            <?php if (!empty($pdfs[$chave])): ?>
                            <a href="<?= URL ?>/admin/fechamento/impressao-lote/pdf?chave=<?= rawurlencode((string) $pdfs[$chave]) ?>"
                               target="_blank" rel="noopener"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-file-pdf text-gray-400 w-4 text-center"></i> Baixar <?= htmlspecialchars((string) $rotulo) ?>
                            </a>
                            <?php else: ?>
                            <form method="post" action="<?= URL ?>/admin/fechamento/impressao-lote/enfileirar" class="block">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
                                <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
                                <input type="hidden" name="turma_id" value="<?= $tid ?>">
                                <input type="hidden" name="serie" value="<?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="documento" value="<?= htmlspecialchars((string) $chave, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-print text-gray-400 w-4 text-center"></i> Gerar <?= htmlspecialchars((string) $rotulo) ?>
                                </button>
                            </form>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="post" action="<?= URL ?>/admin/fechamento/impressao-lote/enfileirar" class="block">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="ano_letivo" value="<?= $anoLetivo ?>">
                            <input type="hidden" name="periodo_tipo" value="<?= htmlspecialchars($periodoTipo, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="periodo_numero" value="<?= $periodoNumero ?>">
                            <input type="hidden" name="turma_id" value="<?= $tid ?>">
                            <input type="hidden" name="serie" value="<?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="documento" value="pacote">
                            <input type="hidden" name="regenerar" value="1">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-rotate text-gray-400 w-4 text-center"></i> Gerar os quatro de novo
                            </button>
                        </form>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-lote-' . $tid;
                        $row_actions_dropdown_menu_class = 'w-72';
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                        <?php else: ?>
                        <span class="text-sm text-gray-400">Aguardando homologação</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>
