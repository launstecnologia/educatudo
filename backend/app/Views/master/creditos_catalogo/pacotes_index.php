<?php
$lista = $lista ?? [];
$erro_tabela = $erro_tabela ?? null;
$flash = $flash ?? [];
$csrf_token = $csrf_token ?? '';
require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php';
?>
<div class="mb-6 flex flex-wrap justify-between gap-4 items-start">
    <div>
        <h2 class="text-2xl font-bold text-slate-900 mb-2">Pacotes de TudiCoins</h2>
        <p class="text-slate-600 text-sm max-w-2xl">Pacotes reutilizáveis de compra avulsa. Vincule por escola na tela TudiCoins para aparecerem no EducaShop / compra da escola.</p>
    </div>
    <?php if (!$erro_tabela): ?>
    <button type="button" onclick="openPacoteDrawer()"
            class="inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold bg-blue-600 text-white hover:bg-blue-700 shadow-sm transition-colors">
        <i class="fa-solid fa-plus mr-2"></i> Novo pacote
    </button>
    <?php endif; ?>
</div>

<?php $catalogo_tab = 'pacotes'; include __DIR__ . '/_nav_tabs.php'; ?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<?php if ($erro_tabela): ?>
<div class="mb-6 px-4 py-3 rounded-lg bg-amber-100 border border-amber-200 text-amber-800"><?= htmlspecialchars($erro_tabela) ?></div>
<?php else: ?>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">TudiCoins</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Valor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($lista as $p): ?>
                <?php $pacoteId = (int) ($p['id'] ?? 0); ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900"><?= htmlspecialchars($p['nome'] ?? '') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay(\CreditosDecimalHelper::fromScalar($p['creditos'] ?? 0, 0.0))) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700">R$ <?= number_format(((int) ($p['valor_centavos'] ?? 0)) / 100, 2, ',', '.') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <?php if (!empty($p['ativo'])): ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>
                        <?php else: ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inativo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openPacoteDrawer(<?= $pacoteId ?>)" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <button type="button" onclick="togglePacote(<?= $pacoteId ?>)" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-power-off text-gray-400 w-4 text-center"></i> <?= !empty($p['ativo']) ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                        <?php $row_actions_dropdown_id = 'row-actions-pacote-' . $pacoteId; ?>
                        <?php include __DIR__ . '/../../admin/_partials/row_actions_dropdown.php'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lista)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-slate-500 text-sm">
                        Nenhum pacote no catálogo.
                        <button type="button" onclick="openPacoteDrawer()" class="ml-1 text-blue-600 hover:underline font-medium">Criar o primeiro</button>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="pacoteDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="closePacoteDrawer()"></div>
<aside id="pacoteDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="pacoteDrawerTitle" class="text-xl font-bold text-gray-900">Novo pacote</h2>
        <button type="button" onclick="closePacoteDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="pacote-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="ajax" value="1">
        <input type="hidden" name="id" id="pacote_id" value="">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do pacote</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="pacote_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="pacote_nome" name="nome" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="Ex.: Pacote 50">
                    </div>
                    <div>
                        <label for="pacote_creditos" class="block text-sm font-medium text-gray-700 mb-1">TudiCoins <span class="text-red-500">*</span></label>
                        <input type="number" id="pacote_creditos" name="creditos" value="50" min="0.0001" step="0.0001" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="pacote_valor_reais" class="block text-sm font-medium text-gray-700 mb-1">Valor (R$) <span class="text-red-500">*</span></label>
                        <input type="number" id="pacote_valor_reais" name="valor_reais" value="9.90" min="0.01" step="0.01" inputmode="decimal" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div id="pacote-ativo-wrap" class="hidden flex items-end">
                        <label class="flex items-center gap-2 text-sm text-gray-700 pb-2">
                            <input type="checkbox" id="pacote_ativo" name="ativo" value="1" checked class="rounded border-gray-300">
                            Ativo
                        </label>
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closePacoteDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="px-6 py-2.5 rounded-lg font-semibold bg-blue-600 text-white hover:bg-blue-700 transition-colors shadow-sm">
                <span id="pacote-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<script>
const URL_BASE = <?= json_encode(URL) ?>;
const CSRF_TOKEN = <?= json_encode($csrf_token) ?>;

function showPacoteDrawer() {
    document.getElementById('pacoteDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('pacoteDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closePacoteDrawer() {
    document.getElementById('pacoteDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('pacoteDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openPacoteDrawer(id) {
    const form = document.getElementById('pacote-form');
    form.reset();
    document.getElementById('pacote_id').value = '';
    document.getElementById('pacote_ativo').checked = true;
    document.getElementById('pacote-ativo-wrap').classList.add('hidden');

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('pacoteDrawerTitle').textContent = 'Novo pacote';
        document.getElementById('pacote-form-submit-label').textContent = 'Salvar';
        document.getElementById('pacote_creditos').value = '50';
        document.getElementById('pacote_valor_reais').value = '9.90';
        showPacoteDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('pacoteDrawerTitle').textContent = 'Editar pacote';
    document.getElementById('pacote-form-submit-label').textContent = 'Salvar alterações';
    document.getElementById('pacote-ativo-wrap').classList.remove('hidden');
    showPacoteDrawer();

    fetch(URL_BASE + '/master/creditos-catalogo/pacotes/' + id + '/dados', { credentials: 'same-origin' })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar.'));
                closePacoteDrawer();
                return;
            }
            var item = data.item;
            document.getElementById('pacote_id').value = item.id;
            document.getElementById('pacote_nome').value = item.nome || '';
            document.getElementById('pacote_creditos').value = item.creditos;
            document.getElementById('pacote_valor_reais').value = item.valor_reais;
            document.getElementById('pacote_ativo').checked = !!item.ativo;
        })
        .catch(function () {
            alert('Erro de conexão ao carregar o pacote.');
            closePacoteDrawer();
        });
}

document.getElementById('pacote-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var submitBtn = this.querySelector('button[type="submit"]');
    var submitLabel = document.getElementById('pacote-form-submit-label');
    var originalText = submitLabel.textContent;
    submitBtn.disabled = true;
    submitLabel.textContent = 'Salvando...';

    fetch(URL_BASE + '/master/creditos-catalogo/pacotes/salvar', {
        method: 'POST',
        body: new FormData(this),
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Erro ao salvar'));
            }
        })
        .catch(function () { alert('Erro de conexão. Tente novamente.'); })
        .finally(function () {
            submitBtn.disabled = false;
            submitLabel.textContent = originalText;
        });
});

function togglePacote(id) {
    if (!confirm('Alternar status ativo deste pacote?')) return;
    var fd = new FormData();
    fd.append('_token', CSRF_TOKEN);
    fd.append('id', id);
    fd.append('ajax', '1');
    fetch(URL_BASE + '/master/creditos-catalogo/pacotes/toggle', {
        method: 'POST',
        body: fd,
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' }
    })
        .then(function (r) { return r.json(); })
        .then(function (d) {
            if (d.success) { window.location.reload(); }
            else { alert(d.error || 'Erro'); }
        })
        .catch(function () { alert('Erro de conexão.'); });
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closePacoteDrawer();
});
</script>
<?php endif; ?>
