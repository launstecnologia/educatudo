<?php
$items = $items ?? [];
$tipos = $tipos ?? [];
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Salas / Ambientes';
$page_header_subtitle = 'Cadastre as salas de aula e outros ambientes da escola (laboratórios, quadra, biblioteca…).';
ob_start();
?>
<button type="button" onclick="openSalaDrawer()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova Sala / Ambiente
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Código</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bloco / Andar</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Capacidade</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($items)): ?>
                <tr>
                    <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-door-open text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhuma sala/ambiente cadastrada</p>
                        <button type="button" onclick="openSalaDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i> Nova Sala / Ambiente
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($items as $item): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($item['nome'] ?? '') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= htmlspecialchars($item['codigo'] ?? '') ?: '—' ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= htmlspecialchars($tipos[$item['tipo'] ?? ''] ?? ($item['tipo'] ?? '—')) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?php
                        $blocoAndar = array_filter([$item['bloco'] ?? '', $item['andar'] ?? '']);
                        echo $blocoAndar ? htmlspecialchars(implode(' — ', $blocoAndar)) : '—';
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= $item['capacidade'] !== null ? (int) $item['capacidade'] : '—' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($item['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= !empty($item['ativo']) ? 'Ativa' : 'Inativa' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openSalaDrawer(<?= (int) $item['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="deleteSala(<?= (int) $item['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-sala-' . (int) $item['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Criar/Editar sala/ambiente em drawer lateral -->
<div id="salaDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeSalaDrawer()"></div>
<aside id="salaDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="salaDrawerTitle" class="text-xl font-bold text-gray-900">Nova Sala / Ambiente</h2>
        <button type="button" onclick="closeSalaDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="sala-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="sl_id" value="">
        <input type="hidden" name="_method" id="sl_method" value="" disabled>

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                <div class="sm:col-span-2">
                    <label for="sl_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                    <input type="text" id="sl_nome" name="nome" required placeholder="Ex.: Sala 06, Laboratório de Informática"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="sl_codigo" class="block text-sm font-medium text-gray-700 mb-1">Código</label>
                    <input type="text" id="sl_codigo" name="codigo" placeholder="Ex.: S06"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 uppercase">
                </div>
                <div>
                    <label for="sl_tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo <span class="text-red-500">*</span></label>
                    <select id="sl_tipo" name="tipo" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($tipos as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>"><?= htmlspecialchars($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="sl_bloco" class="block text-sm font-medium text-gray-700 mb-1">Bloco</label>
                    <input type="text" id="sl_bloco" name="bloco" placeholder="Ex.: Bloco A"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="sl_andar" class="block text-sm font-medium text-gray-700 mb-1">Andar</label>
                    <input type="text" id="sl_andar" name="andar" placeholder="Ex.: Térreo, 1º andar"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="sl_capacidade" class="block text-sm font-medium text-gray-700 mb-1">Capacidade</label>
                    <input type="number" id="sl_capacidade" name="capacidade" min="0" step="1" placeholder="Ex.: 35"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="sl_responsavel_nome" class="block text-sm font-medium text-gray-700 mb-1">Responsável</label>
                    <input type="text" id="sl_responsavel_nome" name="responsavel_nome" placeholder="Ex.: Coordenação Pedagógica"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="sm:col-span-2 flex items-center pt-2 border-t border-gray-100">
                    <label class="flex items-center">
                        <input type="checkbox" id="sl_ativo" name="ativo" value="1" checked
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span class="ml-2 text-sm text-gray-700">Ativa</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeSalaDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="sala-form-submit-label">Cadastrar</span>
            </button>
        </div>
    </form>
</aside>

<script>
const SALA_URL_BASE = <?= json_encode(URL) ?>;
const salaCheckboxFields = ['ativo'];
const salaTextFields = ['nome', 'codigo', 'tipo', 'bloco', 'andar', 'capacidade', 'responsavel_nome'];

function showSalaDrawer() {
    document.getElementById('salaDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('salaDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeSalaDrawer() {
    document.getElementById('salaDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('salaDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openSalaDrawer(id) {
    const form = document.getElementById('sala-form');
    form.reset();
    document.getElementById('sl_id').value = '';
    document.getElementById('sl_method').value = '';
    document.getElementById('sl_method').disabled = true;

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('salaDrawerTitle').textContent = 'Nova Sala / Ambiente';
        document.getElementById('sala-form-submit-label').textContent = 'Cadastrar';
        document.getElementById('sl_ativo').checked = true;
        showSalaDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('salaDrawerTitle').textContent = 'Editar Sala / Ambiente';
    document.getElementById('sala-form-submit-label').textContent = 'Salvar Alterações';
    document.getElementById('sl_method').value = 'PUT';
    document.getElementById('sl_method').disabled = false;

    showSalaDrawer();

    fetch(SALA_URL_BASE + '/admin/salas/' + id + '/dados')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar a sala/ambiente'));
                closeSalaDrawer();
                return;
            }
            const item = data.item;
            document.getElementById('sl_id').value = item.id;
            salaTextFields.forEach((field) => {
                const el = document.getElementById('sl_' + field);
                if (el && item[field] !== null && item[field] !== undefined && item[field] !== '') {
                    el.value = item[field];
                }
            });
            salaCheckboxFields.forEach((field) => {
                document.getElementById('sl_' + field).checked = !!parseInt(item[field], 10);
            });
        })
        .catch(() => {
            alert('Erro de conexão ao carregar sala/ambiente.');
            closeSalaDrawer();
        });
}

document.getElementById('sala-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const mode = this.dataset.mode;
    const id = document.getElementById('sl_id').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const submitLabel = document.getElementById('sala-form-submit-label');
    const originalText = submitLabel.textContent;

    submitBtn.disabled = true;
    submitLabel.textContent = 'Salvando...';

    const url = mode === 'create' ? (SALA_URL_BASE + '/admin/salas') : (SALA_URL_BASE + '/admin/salas/' + id);

    fetch(url, { method: 'POST', body: new FormData(this), headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Erro ao salvar'));
            }
        })
        .catch(() => alert('Erro de conexão. Tente novamente.'))
        .finally(() => {
            submitBtn.disabled = false;
            submitLabel.textContent = originalText;
        });
});

function deleteSala(id) {
    if (!confirm('Tem certeza que deseja excluir esta sala/ambiente? Esta ação não pode ser desfeita.')) return;
    const formData = new FormData();
    formData.append('_method', 'DELETE');
    formData.append('_token', <?= json_encode($csrf_token ?? '') ?>);
    fetch(SALA_URL_BASE + '/admin/salas/' + id, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((r) => r.json())
        .then((data) => {
            if (data.success) {
                location.reload();
            } else {
                alert('Erro ao excluir: ' + (data.error || 'tente novamente'));
            }
        })
        .catch(() => alert('Erro de conexão'));
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeSalaDrawer();
    }
});
</script>
