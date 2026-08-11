<?php
$migrationFiles = $migration_files ?? [];
$totalMigrations = $total_migrations ?? 0;
$escolasData = $escolas ?? [];
$totalEscolas = $total_escolas ?? 0;
$totalPendentes = $total_pendentes ?? 0;
$masterMigrationFiles = $master_migration_files ?? [];
$masterExecutadas = $master_executadas ?? [];
$masterPendentes = $master_pendentes ?? [];
?>

<!-- Cards de resumo -->
<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl shadow-sm p-6 border border-blue-200">
        <div class="flex items-center">
            <div class="p-2 bg-blue-100 rounded-lg">
                <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4"></path></svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-slate-600">Total de migrations</p>
                <p class="text-2xl font-bold text-blue-600"><?= $totalMigrations ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6 border border-slate-200">
        <div class="flex items-center">
            <div class="p-2 bg-slate-100 rounded-lg">
                <svg class="w-6 h-6 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-slate-600">Escolas com banco</p>
                <p class="text-2xl font-bold text-slate-700"><?= $totalEscolas ?></p>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl shadow-sm p-6 border <?= $totalPendentes > 0 ? 'border-red-200' : 'border-green-200' ?>">
        <div class="flex items-center">
            <div class="p-2 <?= $totalPendentes > 0 ? 'bg-red-100' : 'bg-green-100' ?> rounded-lg">
                <?php if ($totalPendentes > 0): ?>
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                <?php else: ?>
                    <svg class="w-6 h-6 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                <?php endif; ?>
            </div>
            <div class="ml-4">
                <p class="text-sm font-medium text-slate-600">Total pendentes</p>
                <p class="text-2xl font-bold <?= $totalPendentes > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= $totalPendentes ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Acao principal -->
<?php if ($totalPendentes > 0): ?>
<div class="mb-6 flex flex-wrap items-center gap-3">
    <button type="button" id="btn-executar-todas" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-medium text-sm flex items-center gap-2">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
        Executar migrations em todas as escolas
    </button>
    <span id="status-geral" class="text-sm text-slate-500"></span>
</div>
<?php else: ?>
<div class="mb-6 bg-green-50 border border-green-200 rounded-lg p-4">
    <p class="text-green-800 font-medium text-sm">Todas as escolas estao em dia. Nenhuma migration pendente.</p>
</div>
<?php endif; ?>

<!-- Resultado da execucao em massa -->
<div id="resultado-execucao" class="mb-6 hidden"></div>

<!-- Migrations do banco Master (arquivos *_master.sql) -->
<div class="mb-8 bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Migrations do banco Master</h3>
            <p class="text-sm text-slate-600 mt-0.5">Arquivos <code class="bg-slate-100 px-1 rounded text-xs">*_master.sql</code> sao executados no proprio banco master (igual aos tenants, mas no banco master).</p>
        </div>
        <?php if (!empty($masterPendentes)): ?>
        <div class="flex items-center gap-2 flex-wrap">
            <button type="button" id="btn-executar-master" class="bg-amber-600 text-white px-5 py-2.5 rounded-lg hover:bg-amber-700 font-medium text-sm flex items-center gap-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                Executar todas
            </button>
            <button type="button" id="btn-escolher-master" class="bg-blue-600 text-white px-5 py-2.5 rounded-lg hover:bg-blue-700 font-medium text-sm" data-pendentes="<?= htmlspecialchars(json_encode($masterPendentes)) ?>">
                Escolher
            </button>
        </div>
        <?php else: ?>
        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Em dia</span>
        <?php endif; ?>
    </div>
    <div class="px-6 py-4">
        <?php if (empty($masterMigrationFiles)): ?>
        <p class="text-sm text-slate-500">Nenhum arquivo *_master.sql na pasta database/migrations.</p>
        <?php else: ?>
        <ul class="space-y-1.5 text-sm">
            <?php foreach ($masterMigrationFiles as $f): ?>
            <li class="flex items-center gap-2 font-mono text-xs">
                <?php if (in_array($f, $masterExecutadas, true)): ?>
                <span class="text-green-600" title="Executada">✓</span>
                <?php else: ?>
                <span class="text-amber-600" title="Pendente">○</span>
                <?php endif; ?>
                <?= htmlspecialchars($f) ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <p id="status-master" class="text-sm text-slate-500 mt-3"></p>
        <?php endif; ?>
    </div>
</div>

<!-- Modal: escolher migrations do master -->
<div id="modal-escolher-master" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" id="modal-escolher-master-backdrop"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[85vh] flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Executar migrations no banco Master</h3>
                <p class="text-sm text-slate-600 mt-0.5">Marque as migrations que deseja executar.</p>
            </div>
            <form id="form-escolher-master" class="flex flex-col flex-1 min-h-0">
                <div class="px-6 py-3 overflow-y-auto flex-1">
                    <ul id="modal-escolher-master-lista" class="space-y-2">
                        <!-- preenchido via JS -->
                    </ul>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 flex gap-2 justify-end">
                    <button type="button" id="modal-escolher-master-fechar" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-amber-600 text-white rounded-lg text-sm font-medium hover:bg-amber-700">Executar selecionadas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Tabela de escolas -->
<div class="bg-white rounded-xl shadow-sm border border-slate-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Status por escola</h3>
    </div>
    <?php if (empty($escolasData)): ?>
        <div class="p-6 text-slate-500 text-sm">Nenhuma escola com banco configurado.</div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Banco</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Executadas</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Pendentes</th>
                    <th class="px-4 py-3 text-center text-xs font-medium text-slate-500 uppercase">Acao</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($escolasData as $e): ?>
                <tr id="escola-row-<?= (int)$e['id'] ?>">
                    <td class="px-4 py-3">
                        <p class="text-sm font-medium text-slate-900"><?= htmlspecialchars($e['nome']) ?></p>
                        <p class="text-xs text-slate-500"><?= htmlspecialchars($e['slug']) ?></p>
                    </td>
                    <td class="px-4 py-3 text-sm text-slate-600"><?= htmlspecialchars($e['nome_banco']) ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if (!$e['ativo']): ?>
                            <span class="px-2 py-1 bg-slate-100 text-slate-600 rounded-full text-xs font-semibold">Inativa</span>
                        <?php elseif ($e['pendentes'] === 0): ?>
                            <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs font-semibold">Em dia</span>
                        <?php else: ?>
                            <span class="px-2 py-1 bg-red-100 text-red-800 rounded-full text-xs font-semibold"><?= $e['pendentes'] ?> pendente(s)</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-center text-sm font-medium text-slate-700"><?= $e['executadas'] ?>/<?= $totalMigrations ?></td>
                    <td class="px-4 py-3 text-center text-sm font-bold <?= $e['pendentes'] > 0 ? 'text-red-600' : 'text-green-600' ?>"><?= $e['pendentes'] ?></td>
                    <td class="px-4 py-3 text-center">
                        <?php if ($e['pendentes'] > 0): ?>
                            <div class="flex items-center justify-center gap-2 flex-wrap">
                                <button type="button" class="btn-executar-escola bg-blue-500 text-white px-3 py-1.5 rounded-lg hover:bg-blue-600 text-xs font-medium" data-escola-id="<?= (int)$e['id'] ?>">
                                    Executar todas
                                </button>
                                <button type="button" class="btn-escolher-migrations bg-blue-600 text-white px-3 py-1.5 rounded-lg hover:bg-blue-700 text-xs font-medium" data-escola-id="<?= (int)$e['id'] ?>" data-escola-nome="<?= htmlspecialchars($e['nome']) ?>" data-pendentes="<?= htmlspecialchars(json_encode($e['pendentes_lista'] ?? [])) ?>">
                                    Escolher
                                </button>
                                <button type="button" class="btn-marcar-executada bg-gray-200 text-slate-700 px-3 py-1.5 rounded-lg hover:bg-gray-300 text-xs font-medium" data-escola-id="<?= (int)$e['id'] ?>" title="Marca como executada sem rodar SQL (para escolas que já tinham banco)">
                                    Ignorar
                                </button>
                            </div>
                        <?php else: ?>
                            <span class="text-xs text-gray-400">--</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: escolher migrations para executar na escola -->
<div id="modal-escolher-migrations" class="fixed inset-0 z-50 hidden overflow-y-auto" aria-hidden="true">
    <div class="flex min-h-full items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/50 transition-opacity" id="modal-escolher-backdrop"></div>
        <div class="relative bg-white rounded-xl shadow-xl max-w-lg w-full max-h-[85vh] flex flex-col">
            <div class="px-6 py-4 border-b border-slate-200">
                <h3 class="text-lg font-semibold text-slate-900">Executar migrations na escola</h3>
                <p id="modal-escolher-escola-nome" class="text-sm text-slate-600 mt-0.5"></p>
            </div>
            <form id="form-escolher-migrations" class="flex flex-col flex-1 min-h-0">
                <input type="hidden" name="escola_id" id="modal-escolher-escola-id" value="">
                <div class="px-6 py-3 overflow-y-auto flex-1">
                    <p class="text-xs text-slate-500 mb-3">Marque apenas as migrations que deseja executar nesta escola. As já executadas não aparecem aqui.</p>
                    <ul id="modal-escolher-lista" class="space-y-2">
                        <!-- preenchido via JS -->
                    </ul>
                </div>
                <div class="px-6 py-4 border-t border-slate-200 flex gap-2 justify-end">
                    <button type="button" id="modal-escolher-fechar" class="px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">Cancelar</button>
                    <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm font-medium hover:bg-blue-700">Executar selecionadas</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Lista de arquivos de migration -->
<details class="mt-6 bg-white rounded-xl shadow-sm border border-slate-200">
    <summary class="px-6 py-4 cursor-pointer text-sm font-semibold text-slate-700 hover:text-slate-900">
        Ver arquivos de migration (<?= $totalMigrations ?>)
    </summary>
    <div class="px-6 pb-4">
        <ul class="divide-y divide-gray-100 text-sm text-slate-600">
            <?php foreach ($migrationFiles as $f): ?>
                <li class="py-1.5 font-mono text-xs"><?= htmlspecialchars($f) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</details>

<script>
(function() {
    var baseUrl = '<?= rtrim(URL, '/') ?>';
    var statusGeralEl = document.getElementById('status-geral');
    var resultadoExecucaoEl = document.getElementById('resultado-execucao');

    function escapeHtml(str) {
        return String(str || '').replace(/[&<>"']/g, function(ch) {
            return ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch];
        });
    }

    function renderErroDetalhado(r) {
        var message = escapeHtml(r.message || 'Erro desconhecido');
        var dbInfo = [r.db_name || '', r.db_host || '', r.db_port || ''].filter(Boolean).join(' | ');
        var errorType = escapeHtml(r.error_type || '');
        var extra = '';
        if (dbInfo || errorType) {
            extra = '<div class="text-[11px] text-red-700 mt-1">'
                + (errorType ? ('<strong>Tipo:</strong> ' + errorType + ' ') : '')
                + (dbInfo ? ('<strong>Banco:</strong> ' + escapeHtml(dbInfo)) : '')
                + '</div>';
        }
        return '<div class="mt-2 p-2 rounded bg-red-100 border border-red-200">'
            + '<div class="text-xs text-red-800 break-words"><strong>Detalhes:</strong> ' + message + '</div>'
            + extra
            + '</div>';
    }

    function mostrarResultado(resultados) {
        var container = resultadoExecucaoEl;
        container.classList.remove('hidden');
        var html = '<div class="space-y-2">';
        var hasErrors = false;
        resultados.forEach(function(r) {
            var isOk = r.status === 'ok';
            if (!isOk) hasErrors = true;
            html += '<div class="flex items-center gap-3 px-4 py-2 rounded-lg ' + (isOk ? 'bg-green-50 border border-green-200' : 'bg-red-50 border border-red-200') + '">';
            html += '<span class="font-medium text-sm ' + (isOk ? 'text-green-800' : 'text-red-800') + '">' + (r.nome || 'Escola #' + r.escola_id) + '</span>';
            html += '<span class="text-xs ' + (isOk ? 'text-green-600' : 'text-red-600') + '">' + r.message + '</span>';
            html += '</div>';
            if (!isOk) {
                html += renderErroDetalhado(r);
            }
        });
        html += '</div>';
        container.innerHTML = html;
        if (statusGeralEl) {
            statusGeralEl.textContent = hasErrors
                ? 'Uma ou mais migrations falharam. Veja os detalhes abaixo.'
                : 'Migrations executadas com sucesso.';
        }
        return hasErrors;
    }

    function mostrarErroUnico(r) {
        if (!resultadoExecucaoEl) return;
        resultadoExecucaoEl.classList.remove('hidden');
        var html = '<div class="space-y-2">';
        html += '<div class="flex items-center gap-3 px-4 py-2 rounded-lg bg-red-50 border border-red-200">';
        html += '<span class="font-medium text-sm text-red-800">' + escapeHtml(r.nome || ('Escola #' + (r.escola_id || ''))) + '</span>';
        html += '<span class="text-xs text-red-600">' + escapeHtml(r.message || 'Erro desconhecido') + '</span>';
        html += '</div>';
        html += renderErroDetalhado(r);
        html += '</div>';
        resultadoExecucaoEl.innerHTML = html;
        if (statusGeralEl) {
            statusGeralEl.textContent = 'Falha ao executar migration. Veja os detalhes abaixo.';
        }
    }

    var btnExecutarMaster = document.getElementById('btn-executar-master');
    var statusMasterEl = document.getElementById('status-master');
    if (btnExecutarMaster) {
        btnExecutarMaster.addEventListener('click', function() {
            if (!confirm('Executar as migrations pendentes no banco master?')) return;
            btnExecutarMaster.disabled = true;
            btnExecutarMaster.textContent = 'Executando...';
            if (statusMasterEl) statusMasterEl.textContent = 'Aguarde...';
            fetch(baseUrl + '/master/migrations/executar-master', { method: 'POST', body: (function() { var fd = new FormData(); fd.append('_token', getCsrfToken()); return fd; })() })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        if (statusMasterEl) statusMasterEl.textContent = data.message || 'OK.';
                        setTimeout(function() { location.reload(); }, 1200);
                    } else {
                        if (statusMasterEl) statusMasterEl.textContent = 'Erro: ' + (data.error || 'desconhecido');
                        btnExecutarMaster.disabled = false;
                        btnExecutarMaster.textContent = 'Executar pendentes no master';
                    }
                })
                .catch(function(err) {
                    if (statusMasterEl) statusMasterEl.textContent = 'Erro de rede: ' + err.message;
                    btnExecutarMaster.disabled = false;
                    btnExecutarMaster.textContent = 'Executar todas';
                });
        });
    }

    var btnEscolherMaster = document.getElementById('btn-escolher-master');
    var modalEscolherMaster = document.getElementById('modal-escolher-master');
    var modalEscolherMasterBackdrop = document.getElementById('modal-escolher-master-backdrop');
    var modalEscolherMasterLista = document.getElementById('modal-escolher-master-lista');
    var formEscolherMaster = document.getElementById('form-escolher-master');
    var modalEscolherMasterFechar = document.getElementById('modal-escolher-master-fechar');
    if (btnEscolherMaster) {
        btnEscolherMaster.addEventListener('click', function() {
            var pendentes = [];
            try {
                pendentes = JSON.parse(btnEscolherMaster.getAttribute('data-pendentes') || '[]');
            } catch (e) {}
            modalEscolherMasterLista.innerHTML = '';
            pendentes.forEach(function(file) {
                var li = document.createElement('li');
                li.className = 'flex items-center gap-2';
                var cb = document.createElement('input');
                cb.type = 'checkbox';
                cb.name = 'migrations[]';
                cb.value = file;
                cb.id = 'mig-master-' + file.replace(/[^a-z0-9_]/gi, '-');
                cb.checked = true;
                cb.className = 'rounded border-slate-300 text-amber-600 focus:ring-amber-500';
                var label = document.createElement('label');
                label.htmlFor = cb.id;
                label.className = 'text-sm text-slate-700 font-mono cursor-pointer';
                label.textContent = file;
                li.appendChild(cb);
                li.appendChild(label);
                modalEscolherMasterLista.appendChild(li);
            });
            if (pendentes.length === 0) {
                modalEscolherMasterLista.innerHTML = '<li class="text-sm text-slate-500">Nenhuma migration pendente.</li>';
            }
            modalEscolherMaster.classList.remove('hidden');
        });
    }
    if (modalEscolherMasterBackdrop) modalEscolherMasterBackdrop.addEventListener('click', function() { modalEscolherMaster.classList.add('hidden'); });
    if (modalEscolherMasterFechar) modalEscolherMasterFechar.addEventListener('click', function() { modalEscolherMaster.classList.add('hidden'); });
    if (formEscolherMaster) {
        formEscolherMaster.addEventListener('submit', function(e) {
            e.preventDefault();
            var checkboxes = formEscolherMaster.querySelectorAll('input[name="migrations[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Selecione ao menos uma migration.');
                return;
            }
            var formData = new FormData();
            formData.append('_token', getCsrfToken());
            checkboxes.forEach(function(cb) { formData.append('migrations[]', cb.value); });
            var btnSubmit = formEscolherMaster.querySelector('button[type="submit"]');
            var origText = btnSubmit.textContent;
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Executando...';
            fetch(baseUrl + '/master/migrations/executar-master-selecionadas', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        modalEscolherMaster.classList.add('hidden');
                        if (statusMasterEl) statusMasterEl.textContent = data.message || 'OK.';
                        setTimeout(function() { location.reload(); }, 800);
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                    }
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                });
        });
    }

    var btnTodas = document.getElementById('btn-executar-todas');
    if (btnTodas) {
        btnTodas.addEventListener('click', function() {
            if (!confirm('Executar todas as migrations pendentes em todas as escolas ativas?')) return;
            btnTodas.disabled = true;
            btnTodas.textContent = 'Executando...';
            if (statusGeralEl) statusGeralEl.textContent = 'Aguarde, pode levar alguns segundos...';
            fetch(baseUrl + '/master/migrations/executar-todas', { method: 'POST', body: (function() { var fd = new FormData(); fd.append('_token', getCsrfToken()); return fd; })() })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.resultados) {
                        var hasErrors = mostrarResultado(data.resultados);
                        if (!hasErrors) {
                            setTimeout(function() { location.reload(); }, 1500);
                        } else {
                            btnTodas.disabled = false;
                            btnTodas.textContent = 'Executar migrations em todas as escolas';
                        }
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                        btnTodas.disabled = false;
                        btnTodas.textContent = 'Executar migrations em todas as escolas';
                    }
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnTodas.disabled = false;
                    btnTodas.textContent = 'Executar migrations em todas as escolas';
                    if (statusGeralEl) statusGeralEl.textContent = '';
                });
        });
    }

    document.querySelectorAll('.btn-executar-escola').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var escolaId = this.getAttribute('data-escola-id');
            if (!confirm('Executar migrations pendentes nesta escola?')) return;
            var btnEl = this;
            btnEl.disabled = true;
            btnEl.textContent = '...';
            var formData = new FormData();
            formData.append('escola_id', escolaId);
            formData.append('_token', getCsrfToken());
            fetch(baseUrl + '/master/migrations/executar-escola', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.resultado) {
                        var r = data.resultado;
                        var isOk = r.status === 'ok';
                        btnEl.textContent = isOk ? 'OK' : 'Erro';
                        btnEl.className = isOk
                            ? 'bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium cursor-default'
                            : 'bg-red-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium cursor-default';
                        if (!isOk) {
                            mostrarErroUnico(r);
                            btnEl.disabled = false;
                            btnEl.textContent = 'Executar';
                            btnEl.className = 'btn-executar-escola bg-blue-500 text-white px-3 py-1.5 rounded-lg hover:bg-blue-600 text-xs font-medium';
                        } else {
                            setTimeout(function() { location.reload(); }, 1000);
                        }
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                        btnEl.disabled = false;
                        btnEl.textContent = 'Executar';
                    }
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnEl.disabled = false;
                    btnEl.textContent = 'Executar';
                });
        });
    });
    // Modal: escolher quais migrations executar
    var modalEscolher = document.getElementById('modal-escolher-migrations');
    var modalBackdrop = document.getElementById('modal-escolher-backdrop');
    var modalNome = document.getElementById('modal-escolher-escola-nome');
    var modalEscolaId = document.getElementById('modal-escolher-escola-id');
    var modalLista = document.getElementById('modal-escolher-lista');
    var formEscolher = document.getElementById('form-escolher-migrations');

    function openModalEscolher(escolaId, escolaNome, pendentes) {
        modalEscolaId.value = escolaId;
        modalNome.textContent = escolaNome;
        modalLista.innerHTML = '';
        var list = Array.isArray(pendentes) ? pendentes : [];
        list.forEach(function(file) {
            var li = document.createElement('li');
            li.className = 'flex items-center gap-2';
            var cb = document.createElement('input');
            cb.type = 'checkbox';
            cb.name = 'migrations[]';
            cb.value = file;
            cb.id = 'mig-' + file.replace(/[^a-z0-9_]/gi, '-');
            cb.checked = true;
            cb.className = 'rounded border-slate-300 text-blue-600 focus:ring-blue-500';
            var label = document.createElement('label');
            label.htmlFor = cb.id;
            label.className = 'text-sm text-slate-700 font-mono cursor-pointer';
            label.textContent = file;
            li.appendChild(cb);
            li.appendChild(label);
            modalLista.appendChild(li);
        });
        if (list.length === 0) {
            modalLista.innerHTML = '<li class="text-sm text-slate-500">Nenhuma migration pendente.</li>';
        }
        modalEscolher.classList.remove('hidden');
    }

    function closeModalEscolher() {
        modalEscolher.classList.add('hidden');
    }

    if (modalBackdrop) modalBackdrop.addEventListener('click', closeModalEscolher);
    if (document.getElementById('modal-escolher-fechar')) {
        document.getElementById('modal-escolher-fechar').addEventListener('click', closeModalEscolher);
    }

    document.querySelectorAll('.btn-escolher-migrations').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var escolaId = this.getAttribute('data-escola-id');
            var escolaNome = this.getAttribute('data-escola-nome') || ('Escola #' + escolaId);
            var pendentes = [];
            try {
                pendentes = JSON.parse(this.getAttribute('data-pendentes') || '[]');
            } catch (e) {}
            openModalEscolher(escolaId, escolaNome, pendentes);
        });
    });

    if (formEscolher) {
        formEscolher.addEventListener('submit', function(e) {
            e.preventDefault();
            var checkboxes = formEscolher.querySelectorAll('input[name="migrations[]"]:checked');
            if (checkboxes.length === 0) {
                alert('Selecione ao menos uma migration.');
                return;
            }
            var formData = new FormData();
            formData.append('escola_id', modalEscolaId.value);
            formData.append('_token', getCsrfToken());
            checkboxes.forEach(function(cb) {
                formData.append('migrations[]', cb.value);
            });
            var btnSubmit = formEscolher.querySelector('button[type="submit"]');
            var origText = btnSubmit.textContent;
            btnSubmit.disabled = true;
            btnSubmit.textContent = 'Executando...';
            fetch(baseUrl + '/master/migrations/executar-escola-selecionadas', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        closeModalEscolher();
                        if (data.resultado && data.resultado.status === 'ok') {
                            setTimeout(function() { location.reload(); }, 800);
                        }
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                    }
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnSubmit.disabled = false;
                    btnSubmit.textContent = origText;
                });
        });
    }

    document.querySelectorAll('.btn-marcar-executada').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var escolaId = this.getAttribute('data-escola-id');
            if (!confirm('Marcar todas as migrations pendentes como executadas nesta escola?\n(Use para escolas que já tinham banco antes do sistema de migrations)')) return;
            var btnEl = this;
            btnEl.disabled = true;
            btnEl.textContent = '...';
            var formData = new FormData();
            formData.append('escola_id', escolaId);
            formData.append('_token', getCsrfToken());
            fetch(baseUrl + '/master/migrations/marcar-executadas', { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        btnEl.textContent = 'OK';
                        btnEl.className = 'bg-green-500 text-white px-3 py-1.5 rounded-lg text-xs font-medium cursor-default';
                        setTimeout(function() { location.reload(); }, 1000);
                    } else {
                        alert('Erro: ' + (data.error || 'desconhecido'));
                        btnEl.disabled = false;
                        btnEl.textContent = 'Ignorar';
                    }
                })
                .catch(function(err) {
                    alert('Erro de rede: ' + err.message);
                    btnEl.disabled = false;
                    btnEl.textContent = 'Ignorar';
                });
        });
    });
})();
</script>
