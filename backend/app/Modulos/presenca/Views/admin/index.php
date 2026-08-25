<?php
$schemaPronto = !empty($schema_pronto);
$dataFiltro = (string) ($data_filtro ?? date('Y-m-d'));
$eventos = is_array($eventos ?? null) ? $eventos : [];
$total = (int) ($total ?? 0);
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($per_page ?? 10));
$lastPage = max(1, (int) ceil($total / $perPage));
$csrf = (string) ($csrf_token ?? '');
$origemLabels = [
    'integracao' => 'Catraca',
    'manual_secretaria' => 'Secretaria',
    'facial' => 'Facial',
    'importacao' => 'Importação',
];
$page_header_title = 'Gestão de Presença';
$page_header_subtitle = 'Entrada e saída na portaria aplicam a chamada nas aulas da grade. O boletim continua no consolidado de faltas.';
ob_start(); ?>
<a href="<?= URL ?>/admin/presenca/config" class="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50">
    <i class="fa-solid fa-gear"></i> Configurar
</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($flash_message)): ?>
    <div class="mb-6 p-4 rounded-lg border <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
        <?= htmlspecialchars((string) $flash_message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if (!$schemaPronto): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
        Rode a migration <code class="text-sm">2026_08_22_gestao_presenca.sql</code> no painel Master antes de usar este módulo.
    </div>
<?php else: ?>

<div class="bg-white rounded-xl shadow-lg p-6 mb-8 w-full">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Registrar entrada ou saída</h3>
    <p class="text-sm text-gray-500 mb-6">Use quando o aluno passar pela secretaria sem bater na catraca. As aulas do dia são atualizadas automaticamente.</p>
    <form method="POST" action="<?= URL ?>/admin/presenca/registrar" class="space-y-6" id="form-presenca-registrar">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="aluno_id" id="aluno_id" value="">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Aluno</label>
                <div class="relative" id="aluno-search-wrapper">
                    <input type="text" id="aluno_search" autocomplete="off"
                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent"
                           placeholder="Nome, RA ou código">
                    <div id="aluno_search_dropdown" class="hidden absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto"></div>
                </div>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                <select name="tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" required>
                    <option value="entrada">Entrada</option>
                    <option value="saida">Saída</option>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data e horário</label>
                <input type="datetime-local" name="ocorrido_em"
                       value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg font-semibold">Registrar</button>
        </div>
    </form>
    <div id="linha-do-tempo" class="hidden mt-6 border-t border-gray-100 pt-6">
        <h4 class="text-sm font-semibold text-gray-900 mb-3">Aulas do dia (prévia)</h4>
        <div id="linha-do-tempo-corpo" class="text-sm text-gray-600"></div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <form method="GET" action="<?= URL ?>/admin/presenca" class="flex flex-col sm:flex-row sm:items-end gap-4 mb-6">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Dia</label>
            <input type="date" name="data" value="<?= htmlspecialchars($dataFiltro, ENT_QUOTES, 'UTF-8') ?>"
                   class="px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <button type="submit" class="px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50">Filtrar</button>
    </form>
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Horário</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Origem</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <?php if ($eventos === []): ?>
                    <tr><td colspan="5" class="px-4 py-10 text-center text-gray-500">Nenhum registro neste dia.</td></tr>
                <?php else: ?>
                    <?php foreach ($eventos as $ev): ?>
                        <?php
                        $tipo = (string) ($ev['tipo'] ?? '');
                        $origem = (string) ($ev['origem'] ?? '');
                        $erro = trim((string) ($ev['erro_processamento'] ?? ''));
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 whitespace-nowrap text-gray-700"><?= htmlspecialchars(date('H:i', strtotime((string) $ev['ocorrido_em'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($ev['aluno_nome'] ?? 'Não identificado'), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($ev['turma_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= $tipo === 'entrada' ? 'bg-green-100 text-green-800' : 'bg-amber-100 text-amber-800' ?>">
                                    <?= $tipo === 'entrada' ? 'Entrada' : 'Saída' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars($origemLabels[$origem] ?? $origem, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-gray-600">
                                <?php if ($ev['aluno_id'] === null || $ev['aluno_id'] === ''): ?>
                                    <span class="text-red-700">Sem aluno</span>
                                <?php elseif ($erro !== ''): ?>
                                    <span class="text-amber-800"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></span>
                                <?php else: ?>
                                    Aplicado
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($lastPage > 1): ?>
        <div class="flex items-center justify-between mt-4 text-sm text-gray-600">
            <span><?= $total ?> registro(s)</span>
            <div class="flex gap-2">
                <?php if ($page > 1): ?>
                    <a class="px-3 py-1.5 border rounded-lg hover:bg-gray-50" href="<?= URL ?>/admin/presenca?data=<?= urlencode($dataFiltro) ?>&page=<?= $page - 1 ?>">Anterior</a>
                <?php endif; ?>
                <?php if ($page < $lastPage): ?>
                    <a class="px-3 py-1.5 border rounded-lg hover:bg-gray-50" href="<?= URL ?>/admin/presenca?data=<?= urlencode($dataFiltro) ?>&page=<?= $page + 1 ?>">Próxima</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
(function () {
    var input = document.getElementById('aluno_search');
    var hidden = document.getElementById('aluno_id');
    var drop = document.getElementById('aluno_search_dropdown');
    var form = document.getElementById('form-presenca-registrar');
    var timer = null;
    var urlBusca = <?= json_encode(URL . '/admin/presenca/alunos', JSON_UNESCAPED_SLASHES) ?>;
    var urlLinha = <?= json_encode(URL . '/admin/presenca/linha-do-tempo', JSON_UNESCAPED_SLASHES) ?>;
    var dataFiltro = <?= json_encode($dataFiltro) ?>;
    var sitLabel = {presente:'Presente', falta:'Falta', atraso:'Atraso', saida_antecipada:'Saída antecipada', falta_justificada:'Justificada'};

    input.addEventListener('input', function () {
        hidden.value = '';
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { drop.classList.add('hidden'); drop.innerHTML = ''; return; }
        timer = setTimeout(function () {
            fetch(urlBusca + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    drop.innerHTML = '';
                    (data.alunos || []).forEach(function (a) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'block w-full text-left px-4 py-2 hover:bg-gray-50';
                        btn.textContent = a.nome + (a.turma_nome ? ' · ' + a.turma_nome : '');
                        btn.addEventListener('click', function () {
                            hidden.value = String(a.id);
                            input.value = a.nome;
                            drop.classList.add('hidden');
                            carregarLinha(a.id);
                        });
                        drop.appendChild(btn);
                    });
                    drop.classList.toggle('hidden', !(data.alunos || []).length);
                });
        }, 250);
    });
    document.addEventListener('click', function (e) {
        if (!e.target.closest('#aluno-search-wrapper')) drop.classList.add('hidden');
    });
    form.addEventListener('submit', function (e) {
        if (!hidden.value) {
            e.preventDefault();
            alert('Selecione um aluno da lista.');
        }
    });
    function carregarLinha(alunoId) {
        fetch(urlLinha + '?aluno_id=' + encodeURIComponent(alunoId) + '&data=' + encodeURIComponent(dataFiltro), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                var box = document.getElementById('linha-do-tempo');
                var corpo = document.getElementById('linha-do-tempo-corpo');
                var aulas = data.aulas || [];
                if (!aulas.length) { box.classList.add('hidden'); corpo.textContent = ''; return; }
                corpo.textContent = '';
                aulas.forEach(function (a) {
                    var de = String(a.horario_de || '').slice(0, 5);
                    var ate = String(a.horario_ate || '').slice(0, 5);
                    var sit = a.situacao ? (sitLabel[a.situacao] || a.situacao) : 'Sem marca';
                    var row = document.createElement('div');
                    row.className = 'flex justify-between py-1 border-b border-gray-50';
                    var esq = document.createElement('span');
                    esq.textContent = de + '–' + ate + ' · ' + (a.materia_nome || '');
                    var dir = document.createElement('span');
                    dir.textContent = sit;
                    row.appendChild(esq);
                    row.appendChild(dir);
                    corpo.appendChild(row);
                });
                box.classList.remove('hidden');
            });
    }
})();
</script>
<?php endif; ?>
