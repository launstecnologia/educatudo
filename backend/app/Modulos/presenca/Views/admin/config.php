<?php
$schemaPronto = !empty($schema_pronto);
$config = is_array($config ?? null) ? $config : [];
$integracoes = is_array($integracoes ?? null) ? $integracoes : [];
$identificadores = is_array($identificadores ?? null) ? $identificadores : [];
$csrf = (string) ($csrf_token ?? '');
$tokenGerado = (string) ($token_gerado ?? '');
$webhookUrl = (string) ($webhook_url ?? '');
$page_header_title = 'Configurar presença';
$page_header_subtitle = 'Tolerância, corte sem entrada, catraca e crachás. O consolidado do boletim só muda se você ligar a soma a partir do diário.';
ob_start(); ?>
<a href="<?= URL ?>/admin/presenca" class="text-gray-600 hover:text-gray-900">← Voltar</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($flash_message)): ?>
    <div class="mb-6 p-4 rounded-lg border <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
        <?= htmlspecialchars((string) $flash_message, ENT_QUOTES, 'UTF-8') ?>
    </div>
<?php endif; ?>

<?php if ($tokenGerado !== ''): ?>
    <div class="mb-6 p-4 rounded-lg border border-amber-200 bg-amber-50 text-amber-950">
        <p class="font-semibold mb-2">Token da catraca (copie agora)</p>
        <code class="block break-all text-sm bg-white border border-amber-200 rounded-lg px-3 py-2"><?= htmlspecialchars($tokenGerado, ENT_QUOTES, 'UTF-8') ?></code>
        <p class="text-sm mt-2">Envie no header <code>Authorization: Bearer …</code> para <code><?= htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8') ?></code></p>
    </div>
<?php endif; ?>

<?php if (!$schemaPronto): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
        Rode a migration <code class="text-sm">2026_08_22_gestao_presenca.sql</code> no painel Master.
    </div>
<?php else: ?>

<div class="bg-white rounded-xl shadow-lg p-6 mb-8 w-full">
    <h3 class="text-lg font-semibold text-gray-900 mb-4">Regras da escola</h3>
    <form method="POST" action="<?= URL ?>/admin/presenca/config">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Tolerância de atraso (minutos)</label>
                <input type="number" min="0" max="180" name="tolerancia_atraso_min"
                       value="<?= (int) ($config['tolerancia_atraso_min'] ?? 10) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Corte sem entrada (minutos após a 1ª aula)</label>
                <input type="number" min="0" max="240" name="minutos_corte_sem_entrada"
                       value="<?= (int) ($config['minutos_corte_sem_entrada'] ?? 30) ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Data de corte do boletim</label>
                <input type="date" name="data_corte" value="<?= htmlspecialchars((string) ($config['data_corte'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg">
                <p class="text-xs text-gray-500 mt-1">Faltas anteriores a esta data não são recalculadas.</p>
            </div>
        </div>
        <div class="space-y-3 mb-6">
            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                <input type="checkbox" name="criar_aula_rascunho" value="1" class="rounded border-gray-300"
                       <?= !empty($config['criar_aula_rascunho']) ? 'checked' : '' ?>>
                Criar aula em rascunho na grade se o professor ainda não abriu a chamada
            </label>
            <label class="inline-flex items-center gap-2 text-sm text-gray-800">
                <input type="checkbox" name="consolidar_boletim" value="1" class="rounded border-gray-300"
                       <?= !empty($config['consolidar_boletim']) ? 'checked' : '' ?>>
                Recalcular o total em “Só Faltas” (eventos com origem Diário)
            </label>
        </div>
        <div class="flex justify-end">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg font-semibold">Salvar regras</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-8 w-full">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Integração da catraca</h3>
    <p class="text-sm text-gray-500 mb-4">Webhook genérico. Cada fornecedor mapeia o payload para <code>id_externo</code>, <code>tipo</code> (entrada/saida) e identificador (RA, código ou crachá).</p>
    <p class="text-sm text-gray-600 mb-6">URL: <code class="break-all"><?= htmlspecialchars($webhookUrl, ENT_QUOTES, 'UTF-8') ?></code></p>
    <form method="POST" action="<?= URL ?>/admin/presenca/integracoes" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Nome</label>
            <input type="text" name="nome" required class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Catraca portaria">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Provedor</label>
            <select name="provedor" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="generico">Genérico</option>
                <option value="intelbras">Intelbras</option>
                <option value="control_id">Control iD</option>
                <option value="henry">Henry</option>
                <option value="facial_educatudo">Facial EducaTudo</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Modo</label>
            <select name="modo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="webhook">Webhook (fornecedor chama o EducaTudo)</option>
                <option value="polling">Polling (reservado — use webhook no v1)</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Identificador padrão</label>
            <select name="mapeamento_identificador" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="ra">RA</option>
                <option value="codigo_aluno">Código do aluno</option>
                <option value="cartao">Crachá (tabela de identificadores)</option>
                <option value="aluno_id">ID interno do aluno</option>
            </select>
        </div>
        <div class="md:col-span-2 flex justify-end">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg font-semibold">Criar integração</button>
        </div>
    </form>
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Nome</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Provedor</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($integracoes === []): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhuma integração ainda.</td></tr>
                <?php else: ?>
                    <?php foreach ($integracoes as $int): ?>
                        <tr>
                            <td class="px-4 py-3 font-medium text-gray-900"><?= htmlspecialchars((string) $int['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars((string) $int['provedor'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3">
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-semibold <?= !empty($int['ativo']) ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-700' ?>">
                                    <?= !empty($int['ativo']) ? 'Ativa' : 'Inativa' ?>
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <?php ob_start(); ?>
                                <form method="POST" action="<?= URL ?>/admin/presenca/integracoes/ativo">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int) $int['id'] ?>">
                                    <input type="hidden" name="ativo" value="<?= !empty($int['ativo']) ? '0' : '1' ?>">
                                    <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-power-off text-gray-400 w-4 text-center"></i>
                                        <?= !empty($int['ativo']) ? 'Desativar' : 'Ativar' ?>
                                    </button>
                                </form>
                                <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                <?php $row_actions_dropdown_id = 'int-actions-' . (int) $int['id']; ?>
                                <?php include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 w-full">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Crachás / identificadores</h3>
    <p class="text-sm text-gray-500 mb-6">Quando a catraca não envia RA, vincule o número do cartão ao aluno.</p>
    <form method="POST" action="<?= URL ?>/admin/presenca/identificadores" class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6" id="form-identificador">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="aluno_id" id="ident_aluno_id" value="">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Aluno</label>
            <div class="relative">
                <input type="text" id="ident_aluno_search" autocomplete="off"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg" placeholder="Nome ou RA">
                <div id="ident_aluno_drop" class="hidden absolute z-20 mt-1 w-full bg-white border border-gray-200 rounded-lg shadow-lg max-h-56 overflow-y-auto"></div>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
            <select name="tipo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="cartao">Crachá / cartão</option>
                <option value="externo">ID externo</option>
                <option value="ra">RA (cópia)</option>
                <option value="codigo_aluno">Código do aluno</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Valor</label>
            <input type="text" name="valor" required maxlength="80" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div class="flex items-end justify-end">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg font-semibold">Vincular</button>
        </div>
    </form>
    <div class="overflow-x-auto border border-gray-200 rounded-lg">
        <table class="min-w-full text-sm divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Tipo</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Valor</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Ação</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($identificadores === []): ?>
                    <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhum crachá cadastrado.</td></tr>
                <?php else: ?>
                    <?php foreach ($identificadores as $idnt): ?>
                        <tr>
                            <td class="px-4 py-3">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars((string) $idnt['aluno_nome'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars((string) ($idnt['turma_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="px-4 py-3 text-gray-600"><?= htmlspecialchars((string) $idnt['tipo'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 font-mono text-gray-800"><?= htmlspecialchars((string) $idnt['valor'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="px-4 py-3 text-right">
                                <?php ob_start(); ?>
                                <form method="POST" action="<?= URL ?>/admin/presenca/identificadores/excluir" onsubmit="return confirm('Remover este identificador?');">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="id" value="<?= (int) $idnt['id'] ?>">
                                    <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                    </button>
                                </form>
                                <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                <?php $row_actions_dropdown_id = 'ident-actions-' . (int) $idnt['id']; ?>
                                <?php include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
(function () {
    var input = document.getElementById('ident_aluno_search');
    var hidden = document.getElementById('ident_aluno_id');
    var drop = document.getElementById('ident_aluno_drop');
    var form = document.getElementById('form-identificador');
    var urlBusca = <?= json_encode(URL . '/admin/presenca/alunos', JSON_UNESCAPED_SLASHES) ?>;
    var timer = null;
    input.addEventListener('input', function () {
        hidden.value = '';
        var q = input.value.trim();
        clearTimeout(timer);
        if (q.length < 2) { drop.classList.add('hidden'); return; }
        timer = setTimeout(function () {
            fetch(urlBusca + '?q=' + encodeURIComponent(q), { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    drop.innerHTML = '';
                    (data.alunos || []).forEach(function (a) {
                        var btn = document.createElement('button');
                        btn.type = 'button';
                        btn.className = 'block w-full text-left px-4 py-2 hover:bg-gray-50';
                        btn.textContent = a.nome + (a.ra ? ' · RA ' + a.ra : '');
                        btn.addEventListener('click', function () {
                            hidden.value = String(a.id);
                            input.value = a.nome;
                            drop.classList.add('hidden');
                        });
                        drop.appendChild(btn);
                    });
                    drop.classList.toggle('hidden', !(data.alunos || []).length);
                });
        }, 250);
    });
    document.addEventListener('click', function (e) {
        if (!drop.contains(e.target) && e.target !== input) drop.classList.add('hidden');
    });
    form.addEventListener('submit', function (e) {
        if (!hidden.value) { e.preventDefault(); alert('Selecione um aluno da lista.'); }
    });
})();
</script>
<?php endif; ?>
