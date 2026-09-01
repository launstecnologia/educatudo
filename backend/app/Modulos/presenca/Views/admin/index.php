<?php
$schemaPronto = !empty($schema_pronto);
$dataFiltro = (string) ($data_filtro ?? date('Y-m-d'));
$eventos = is_array($eventos ?? null) ? $eventos : [];
$total = (int) ($total ?? 0);
$page = max(1, (int) ($page ?? 1));
$perPage = max(1, (int) ($per_page ?? 10));
$lastPage = max(1, (int) ($total_pages ?? (int) ceil($total / $perPage)));
$csrf = (string) ($csrf_token ?? '');
$hoje = date('Y-m-d');
$filtrosAtivosCount = $dataFiltro !== $hoje ? 1 : 0;
$origemLabels = [
    'integracao' => 'Catraca',
    'manual_secretaria' => 'Secretaria',
    'facial' => 'Facial',
    'importacao' => 'Importação',
];
$flash_status = (string) ($flash_status ?? '');
if ($flash_status === '' && !empty($flash_message)) {
    $flash_status = (($flash_type ?? '') === 'error') ? 'error' : 'success';
}

$page_header_title = 'Gestão de Presença';
$page_header_subtitle = 'Entrada e saída na portaria aplicam a chamada nas aulas da grade. O boletim continua no consolidado de faltas.';
ob_start();
?>
<?php if ($schemaPronto): ?>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <?php if ($filtrosAtivosCount > 0): ?>
    <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
    <?php endif; ?>
</button>
<?php endif; ?>
<a href="<?= URL ?>/admin/presenca/config"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-gear mr-2 text-gray-500"></i>
    Configurar
</a>
<?php if ($schemaPronto): ?>
<button type="button" onclick="openPresencaDrawer()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Registrar
</button>
<?php endif; ?>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (!$schemaPronto): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
        Rode a migration <code class="text-sm">2026_08_22_gestao_presenca.sql</code> no painel Master antes de usar este módulo.
    </div>
<?php else: ?>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar presença</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/presenca" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_data" class="block text-sm font-medium text-gray-700 mb-1.5">Dia</label>
                <input type="date" id="filtro_data" name="data" value="<?= htmlspecialchars($dataFiltro, ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= URL ?>/admin/presenca" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 text-center">Limpar</a>
            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">Aplicar filtros</button>
        </div>
    </form>
</aside>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Horário</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Origem</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($eventos === []): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <p>Nenhum registro neste dia.</p>
                        <button type="button" onclick="openPresencaDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i>Registrar
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($eventos as $ev): ?>
                        <?php
                        $tipo = (string) ($ev['tipo'] ?? '');
                        $origem = (string) ($ev['origem'] ?? '');
                        $erro = trim((string) ($ev['erro_processamento'] ?? ''));
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars(date('H:i', strtotime((string) $ev['ocorrido_em'])), ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 text-sm">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars((string) ($ev['aluno_nome'] ?? 'Não identificado'), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($ev['turma_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                $ui_badge_variant = $tipo === 'entrada' ? 'ativo' : 'pendente';
                                $ui_badge_label = $tipo === 'entrada' ? 'Entrada' : 'Saída';
                                include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php';
                                ?>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars($origemLabels[$origem] ?? $origem, ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php
                                if ($ev['aluno_id'] === null || $ev['aluno_id'] === '') {
                                    $ui_badge_variant = 'erro';
                                    $ui_badge_label = 'Sem aluno';
                                } elseif ($erro !== '') {
                                    $ui_badge_variant = 'pendente';
                                    $ui_badge_label = $erro;
                                } else {
                                    $ui_badge_variant = 'ativo';
                                    $ui_badge_label = 'Aplicado';
                                }
                                include __DIR__ . '/../../../../Views/admin/_partials/ui/badge.php';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php if ($total > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> registro(s)
        </p>
        <?php if ($lastPage > 1):
            $baseQuery = '?data=' . urlencode($dataFiltro);
        ?>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="<?= URL ?>/admin/presenca<?= $baseQuery ?>&page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($lastPage, $page + 2); $i++): ?>
                <a href="<?= URL ?>/admin/presenca<?= $baseQuery ?>&page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $lastPage): ?>
                <a href="<?= URL ?>/admin/presenca<?= $baseQuery ?>&page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div id="presencaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closePresencaDrawer()"></div>
<aside id="presencaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 class="text-xl font-bold text-gray-900">Registrar entrada ou saída</h2>
        <button type="button" onclick="closePresencaDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form id="form-presenca-registrar" method="POST" action="<?= URL ?>/admin/presenca/registrar" class="flex flex-col flex-1 overflow-hidden">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="aluno_id" id="aluno_id" value="">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <p class="text-sm text-gray-600 bg-slate-50 border border-slate-200 rounded-lg px-4 py-3">
                Use quando o aluno passar pela secretaria sem bater na catraca. As aulas do dia são atualizadas automaticamente.
            </p>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Registro</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="aluno_search" class="block text-sm font-medium text-gray-700 mb-1">Aluno <span class="text-red-500">*</span></label>
                        <div class="relative" id="aluno-search-wrapper">
                            <input type="text" id="aluno_search" autocomplete="off"
                                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                                   placeholder="Nome, RA ou código">
                            <div id="aluno_search_dropdown" class="hidden absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto"></div>
                        </div>
                    </div>
                    <div>
                        <label for="presenca_tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo <span class="text-red-500">*</span></label>
                        <select id="presenca_tipo" name="tipo" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="entrada">Entrada</option>
                            <option value="saida">Saída</option>
                        </select>
                    </div>
                    <div>
                        <label for="presenca_quando" class="block text-sm font-medium text-gray-700 mb-1">Data e horário <span class="text-red-500">*</span></label>
                        <input type="datetime-local" id="presenca_quando" name="ocorrido_em" required
                               value="<?= htmlspecialchars(date('Y-m-d\TH:i'), ENT_QUOTES, 'UTF-8') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            </section>
            <section id="linha-do-tempo" class="hidden">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Aulas do dia (prévia)</h3>
                <div id="linha-do-tempo-corpo" class="text-sm text-gray-600"></div>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closePresencaDrawer()" class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">Cancelar</button>
            <button type="submit" class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">Registrar</button>
        </div>
    </form>
</aside>

<script>
function showDrawer(backdropId, drawerId) {
    document.getElementById(backdropId).classList.remove('hidden');
    var drawer = document.getElementById(drawerId);
    drawer.setAttribute('aria-hidden', 'false');
    requestAnimationFrame(function () { drawer.classList.remove('translate-x-full'); });
    document.body.style.overflow = 'hidden';
}
function hideDrawer(backdropId, drawerId) {
    document.getElementById(backdropId).classList.add('hidden');
    var drawer = document.getElementById(drawerId);
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function openFilterDrawer() { closePresencaDrawer(); showDrawer('filterDrawerBackdrop', 'filterDrawer'); }
function closeFilterDrawer() { hideDrawer('filterDrawerBackdrop', 'filterDrawer'); }
function openPresencaDrawer() {
    closeFilterDrawer();
    var hidden = document.getElementById('aluno_id');
    var input = document.getElementById('aluno_search');
    var drop = document.getElementById('aluno_search_dropdown');
    var quando = document.getElementById('presenca_quando');
    var tipo = document.getElementById('presenca_tipo');
    var box = document.getElementById('linha-do-tempo');
    var corpo = document.getElementById('linha-do-tempo-corpo');
    if (hidden) hidden.value = '';
    if (input) input.value = '';
    if (drop) { drop.classList.add('hidden'); drop.innerHTML = ''; }
    if (tipo) tipo.value = 'entrada';
    if (quando) {
        var agora = new Date();
        var pad = function (n) { return String(n).padStart(2, '0'); };
        quando.value = agora.getFullYear() + '-' + pad(agora.getMonth() + 1) + '-' + pad(agora.getDate()) + 'T' + pad(agora.getHours()) + ':' + pad(agora.getMinutes());
    }
    if (box) box.classList.add('hidden');
    if (corpo) corpo.textContent = '';
    showDrawer('presencaDrawerBackdrop', 'presencaDrawer');
    if (input) setTimeout(function () { input.focus(); }, 280);
}
function closePresencaDrawer() { hideDrawer('presencaDrawerBackdrop', 'presencaDrawer'); }
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
        closePresencaDrawer();
    }
});

(function () {
    var input = document.getElementById('aluno_search');
    var hidden = document.getElementById('aluno_id');
    var drop = document.getElementById('aluno_search_dropdown');
    var form = document.getElementById('form-presenca-registrar');
    var quando = document.getElementById('presenca_quando');
    var timer = null;
    var urlBusca = <?= json_encode(URL . '/admin/presenca/alunos', JSON_UNESCAPED_SLASHES) ?>;
    var urlLinha = <?= json_encode(URL . '/admin/presenca/linha-do-tempo', JSON_UNESCAPED_SLASHES) ?>;
    var sitLabel = {presente:'Presente', falta:'Falta', atraso:'Atraso', saida_antecipada:'Saída antecipada', falta_justificada:'Justificada'};

    function dataDoRegistro() {
        var v = quando && quando.value ? quando.value : '';
        return v.length >= 10 ? v.slice(0, 10) : <?= json_encode($dataFiltro) ?>;
    }

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
    if (quando) {
        quando.addEventListener('change', function () {
            if (hidden.value) carregarLinha(hidden.value);
        });
    }
    function carregarLinha(alunoId) {
        fetch(urlLinha + '?aluno_id=' + encodeURIComponent(alunoId) + '&data=' + encodeURIComponent(dataDoRegistro()), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
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
if (new URLSearchParams(window.location.search).get('novo') === '1') {
    openPresencaDrawer();
    if (window.history && window.history.replaceState) {
        var limpa = new URL(window.location.href);
        limpa.searchParams.delete('novo');
        window.history.replaceState({}, '', limpa.pathname + limpa.search + limpa.hash);
    }
}
</script>
<?php endif; ?>
