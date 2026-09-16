<?php
$boletim = is_array($boletim ?? null) ? $boletim : [];
$eventos = is_array($eventos ?? null) ? $eventos : [];
$regra = is_array($regra ?? null) ? $regra : null;
$alunos = is_array($alunos ?? null) ? $alunos : [];
$turmas = is_array($turmas ?? null) ? $turmas : [];
$simulacao = is_array($simulacao ?? null) ? $simulacao : null;
$csrf_token = (string) ($csrf_token ?? '');
$boletimId = (int) ($boletim['id'] ?? 0);
$selectedRegraId = (int) ($selected_regra_id ?? 0);
$selectedAlunoId = (int) ($selected_aluno_id ?? 0);
$periodoRef = (string) ($periodo_ref ?? '');
$dataInicio = (string) ($data_inicio ?? '');
$dataFim = (string) ($data_fim ?? '');
$geracaoEmAndamento = !empty($geracao_em_andamento);
$abrirSimular = !empty($abrir_simular);
$retornoPath = '/admin/boletins/' . $boletimId . '/gerar-boletins?regra_id=' . $selectedRegraId;

$page_header_title = $abrirSimular ? 'Simular boletim' : 'Gerar boletins';
$page_header_subtitle = (string) ($boletim['nome'] ?? '');
$page_header_back_url = URL . '/admin/boletins';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if ($eventos === []): ?>
<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <div class="p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-900 text-sm">
        Este modelo ainda não tem evento de notas. Monte as fórmulas e, se quiser, gere os bimestres do ano.
        <div class="mt-3 flex flex-wrap gap-2">
            <a href="<?= URL ?>/admin/boletim-configuracao/assistente?boletim_id=<?= $boletimId ?>&amp;voltar=boletins"
               class="inline-flex items-center px-4 py-2 border border-amber-300 rounded-lg text-sm font-medium bg-white hover:bg-amber-50">
                Fórmulas
            </a>
            <a href="<?= URL ?>/admin/boletins/<?= $boletimId ?>/gerar-avaliacoes"
               class="inline-flex items-center px-4 py-2 border border-amber-300 rounded-lg text-sm font-medium bg-white hover:bg-amber-50">
                Gerar avaliações do ano
            </a>
        </div>
    </div>
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-lg p-6 w-full mb-6">
    <form method="GET" action="<?= URL ?>/admin/boletins/<?= $boletimId ?>/gerar-boletins" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label for="regra_id" class="block text-sm font-medium text-gray-700 mb-2">Evento de notas</label>
            <select name="regra_id" id="regra_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()">
                <?php foreach ($eventos as $ev): ?>
                    <?php $evId = (int) ($ev['id'] ?? 0); ?>
                    <option value="<?= $evId ?>" <?= $evId === $selectedRegraId ? 'selected' : '' ?>>
                        <?= htmlspecialchars((string) ($ev['nome'] ?? ('#' . $evId)), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($ev['bimestre'])): ?> — <?= (int) $ev['bimestre'] ?>º bim.<?php endif; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <p class="text-sm text-gray-500">
                Período <?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>
                a <?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>
            </p>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 w-full">
    <div class="px-5 py-4 border-b border-gray-200 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold text-gray-900">Geração em lote</h2>
            <p class="text-sm text-gray-500 mt-1">Simule um aluno e depois gere o período. Alunos travados não entram.</p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
            <button type="button" id="btn-abrir-conferir-boletim"
                    class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                <i class="fa-solid fa-flask mr-2"></i>Simular boletim
            </button>
            <?php if ($geracaoEmAndamento): ?>
            <a href="<?= URL ?>/admin/boletim"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                Ver status
            </a>
            <?php elseif ($selectedRegraId > 0): ?>
            <button type="submit" form="form-gerar-lote-boletim"
                    class="btn-primary-custom inline-flex items-center px-4 py-2 rounded-lg text-sm font-semibold hover:opacity-90">
                Gerar boletins
            </button>
            <?php endif; ?>
        </div>
    </div>
    <div class="p-5 space-y-4">
        <form method="POST" action="<?= URL ?>/admin/boletim-configuracao/gerar-boletins" id="form-gerar-lote-boletim" class="space-y-3">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="regra_id" value="<?= $selectedRegraId ?>">
            <input type="hidden" name="periodo_ref" value="<?= htmlspecialchars($periodoRef, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="data_inicio" value="<?= htmlspecialchars($dataInicio, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="data_fim" value="<?= htmlspecialchars($dataFim, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="retorno" value="<?= htmlspecialchars($retornoPath, ENT_QUOTES, 'UTF-8') ?>">
            <label class="flex items-start gap-2.5 text-sm text-gray-800 cursor-pointer">
                <input type="checkbox" id="incluir-novos-boletim" name="incluir_novos" value="1" class="mt-1 w-4 h-4 rounded border-gray-300" checked>
                <span>Incluir alunos que ainda não têm boletim neste período</span>
            </label>
            <p class="text-xs text-gray-500">Nova versão vigente; o histórico fica guardado. Roda em segundo plano.</p>
            <?php if ($geracaoEmAndamento): ?>
            <p class="text-sm text-amber-800 font-medium">Já existe uma geração em andamento para este evento.</p>
            <?php endif; ?>
        </form>
        <p class="text-xs text-gray-500">
            <a href="<?= URL ?>/admin/boletim-configuracao/gerados" class="hover:underline">Ver boletins gerados</a>
        </p>
    </div>
</div>

<?php if (is_array($simulacao)): ?>
<?php
$matrizSim = $simulacao['matriz_materias'] ?? null;
$matrizLinhas = is_array($matrizSim) && !empty($matrizSim['linhas']) ? $matrizSim['linhas'] : [];
$matrizColunas = is_array($matrizSim) && !empty($matrizSim['colunas']) ? $matrizSim['colunas'] : [];
?>
<div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200">
        <h2 class="text-lg font-semibold text-gray-900">Resultado da simulação</h2>
        <p class="text-sm text-gray-500 mt-1">
            Aluno: <strong><?= htmlspecialchars((string) (($simulacao['aluno']['nome'] ?? '-')), ENT_QUOTES, 'UTF-8') ?></strong>
        </p>
    </div>
    <div class="p-5 overflow-x-auto">
        <?php if ($matrizLinhas === [] || $matrizColunas === []): ?>
            <p class="text-sm text-gray-500">Sem dados de simulação para o aluno/evento selecionado.</p>
        <?php else: ?>
            <table class="min-w-full text-sm">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-500">Matéria</th>
                        <?php foreach ($matrizColunas as $col): ?>
                        <th class="px-3 py-2 text-left font-medium text-gray-500"><?= htmlspecialchars((string) ($col['nome'] ?? $col['label'] ?? $col['codigo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    <?php foreach ($matrizLinhas as $linha): ?>
                    <tr>
                        <td class="px-3 py-2 text-gray-900"><?= htmlspecialchars((string) ($linha['materia_nome'] ?? $linha['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                        <?php
                        $notasLin = is_array($linha['notas'] ?? null) ? $linha['notas'] : [];
                        foreach ($matrizColunas as $col):
                            $codM = (string) ($col['codigo'] ?? '');
                            $nv = $notasLin[$codM] ?? null;
                            if (is_array($nv)) {
                                $nv = $nv['texto'] ?? $nv['valor'] ?? '—';
                            }
                            if ($nv === null || $nv === '') {
                                $nv = '—';
                            }
                        ?>
                            <td class="px-3 py-2 text-gray-700"><?= htmlspecialchars((string) $nv, ENT_QUOTES, 'UTF-8') ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div id="boletimConferirBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="fecharConferirBoletim()"></div>
<aside id="boletimConferirDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">Simular boletim</h2>
        <button type="button" onclick="fecharConferirBoletim()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
        <section>
            <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Ver um aluno</h3>
            <form method="GET" action="<?= URL ?>/admin/boletins/<?= $boletimId ?>/gerar-boletins" class="space-y-4">
                <input type="hidden" name="regra_id" value="<?= $selectedRegraId ?>">
                <input type="hidden" name="simular" value="1">
                <div>
                    <label for="conferir-aluno-id" class="block text-sm font-medium text-gray-700 mb-1">Aluno <span class="text-red-500">*</span></label>
                    <select name="aluno_id" id="conferir-aluno-id" required class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                        <option value="">Selecione</option>
                        <?php foreach ($alunos as $aluno): ?>
                            <?php $label = trim((string) ($aluno['nome'] ?? '')) . ' (' . ((string) ($aluno['turma_nome'] ?? 'Sem turma')) . ')'; ?>
                            <option value="<?= (int) ($aluno['id'] ?? 0) ?>" <?= ((int) ($aluno['id'] ?? 0) === $selectedAlunoId) ? 'selected' : '' ?>><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 shadow-sm">Ver simulação</button>
            </form>
        </section>
        <section>
            <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Simular lote</h3>
            <p class="text-sm text-gray-500 mb-4">Valida até 60 alunos sem gravar. A turma é opcional.</p>
            <div class="space-y-4">
                <div>
                    <label for="lote-turma-id" class="block text-sm font-medium text-gray-700 mb-1">Turma</label>
                    <select id="lote-turma-id" class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white">
                        <option value="">Todo o escopo do evento</option>
                        <?php foreach ($turmas as $turmaOpt): ?>
                            <option value="<?= (int) ($turmaOpt['id'] ?? 0) ?>"><?= htmlspecialchars((string) ($turmaOpt['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <button type="button" id="btn-simular-lote" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 shadow-sm text-sm">Simular lote</button>
                <div id="lote-resultado" class="hidden max-h-72 overflow-y-auto border border-gray-200 rounded-lg bg-white"></div>
            </div>
        </section>
    </div>
    <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex justify-end">
        <button type="button" onclick="fecharConferirBoletim()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Fechar</button>
    </div>
</aside>

<script>
(function () {
    function escHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c];
        });
    }
    var URL_GERAR_LOTE = <?= json_encode(URL . '/admin/boletim-configuracao/gerar-boletins', JSON_UNESCAPED_SLASHES) ?>;
    var URL_ATUALIZAR_LOTE = <?= json_encode(URL . '/admin/boletim-configuracao/atualizar-boletins-gravados', JSON_UNESCAPED_SLASHES) ?>;
    var SIMULAR_LOTE_URL = <?= json_encode(URL . '/admin/boletim-configuracao/simular-lote', JSON_UNESCAPED_SLASHES) ?>;
    var REGRA_ATUAL_ID = <?= $selectedRegraId ?>;
    var formGerarLote = document.getElementById('form-gerar-lote-boletim');
    var chkIncluirNovos = document.getElementById('incluir-novos-boletim');
    function aplicarAcaoGerarLote() {
        if (!formGerarLote) return;
        formGerarLote.action = (chkIncluirNovos && chkIncluirNovos.checked) ? URL_GERAR_LOTE : URL_ATUALIZAR_LOTE;
    }
    if (formGerarLote) {
        aplicarAcaoGerarLote();
        if (chkIncluirNovos) chkIncluirNovos.addEventListener('change', aplicarAcaoGerarLote);
        formGerarLote.addEventListener('submit', aplicarAcaoGerarLote);
    }

    window.abrirConferirBoletim = function () {
        var backdrop = document.getElementById('boletimConferirBackdrop');
        var drawer = document.getElementById('boletimConferirDrawer');
        if (!backdrop || !drawer) return;
        backdrop.classList.remove('hidden');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
    };
    window.fecharConferirBoletim = function () {
        var backdrop = document.getElementById('boletimConferirBackdrop');
        var drawer = document.getElementById('boletimConferirDrawer');
        if (!backdrop || !drawer) return;
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
        setTimeout(function () { backdrop.classList.add('hidden'); }, 300);
    };
    var btnAbrir = document.getElementById('btn-abrir-conferir-boletim');
    if (btnAbrir) btnAbrir.addEventListener('click', function () { window.abrirConferirBoletim(); });
    <?php if ($abrirSimular): ?>
    window.abrirConferirBoletim();
    <?php endif; ?>
    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        var drawer = document.getElementById('boletimConferirDrawer');
        if (!drawer || drawer.getAttribute('aria-hidden') === 'true') return;
        window.fecharConferirBoletim();
    });

    var btnSimularLote = document.getElementById('btn-simular-lote');
    if (btnSimularLote) {
        btnSimularLote.addEventListener('click', function () {
            var loteBox = document.getElementById('lote-resultado');
            var turmaSelect = document.getElementById('lote-turma-id');
            btnSimularLote.disabled = true;
            btnSimularLote.textContent = 'Simulando...';
            var body = new URLSearchParams();
            body.set('_token', <?= json_encode($csrf_token) ?>);
            body.set('regra_id', String(REGRA_ATUAL_ID));
            body.set('turma_id', turmaSelect ? turmaSelect.value : '');
            body.set('periodo_ref', <?= json_encode($periodoRef) ?>);
            body.set('data_inicio', <?= json_encode($dataInicio) ?>);
            body.set('data_fim', <?= json_encode($dataFim) ?>);
            fetch(SIMULAR_LOTE_URL, { method: 'POST', credentials: 'same-origin', body: body })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    loteBox.classList.remove('hidden');
                    var alunosLote = data.alunos || [];
                    if (alunosLote.length === 0) {
                        loteBox.innerHTML = '<p class="p-3 text-gray-500">Nenhum aluno encontrado para simular.</p>';
                        return;
                    }
                    var html = '<table class="min-w-full text-sm"><thead class="bg-gray-50 sticky top-0"><tr>'
                        + '<th class="px-3 py-2 text-left font-medium text-gray-500">Aluno</th>'
                        + '<th class="px-3 py-2 text-left font-medium text-gray-500">Nota final</th>'
                        + '<th class="px-3 py-2 text-left font-medium text-gray-500">Status</th></tr></thead><tbody class="divide-y divide-gray-100">';
                    alunosLote.forEach(function (a) {
                        var status = a.erro
                            ? '<span class="text-red-700">Erro</span>'
                            : (a.tem_lacuna ? '<span class="text-amber-700">Tem componente vazio</span>' : '<span class="text-emerald-700">Completo</span>');
                        html += '<tr><td class="px-3 py-2">' + escHtml(a.nome || '') + '</td><td class="px-3 py-2">'
                            + escHtml(a.media_final !== null && a.media_final !== undefined ? String(a.media_final) : '—')
                            + '</td><td class="px-3 py-2">' + status + '</td></tr>';
                    });
                    html += '</tbody></table>';
                    loteBox.innerHTML = html;
                })
                .catch(function () {
                    loteBox.classList.remove('hidden');
                    loteBox.innerHTML = '<p class="p-3 text-red-700">Erro ao simular. Tente novamente.</p>';
                })
                .finally(function () {
                    btnSimularLote.disabled = false;
                    btnSimularLote.textContent = 'Simular lote';
                });
        });
    }
})();
</script>
<?php endif; ?>
