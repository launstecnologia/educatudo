<?php if (!empty($admin_perfis_table_missing)): ?>
<div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-900">
    A tabela <code class="bg-amber-100 px-1 rounded">admin_perfis_permissao</code> ainda não existe neste banco.
    Execute a migration <code class="bg-amber-100 px-1 rounded">2026_05_13_admin_perfis_permissao.sql</code> no schema atual para habilitar perfis reutilizáveis.
</div>
<?php endif; ?>

<?php
$page_header_title = 'Perfis de Permissão';
$page_header_subtitle = 'Cadastre perfis reutilizáveis e atribua no cadastro de usuários';
ob_start();
?>
<button type="button" onclick="openProfileDrawer()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo Perfil
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo Base</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Criado por</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($perfis)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-user-shield text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum perfil cadastrado</p>
                        <button type="button" onclick="openProfileDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i> Novo Perfil
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($perfis as $perfil): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($perfil['nome']) ?></div>
                        <div class="text-sm text-gray-500"><?= htmlspecialchars((string) ($perfil['descricao'] ?? '')) ?></div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars(ucfirst((string) ($perfil['tipo_base'] ?? ''))) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= ((int) ($perfil['ativo'] ?? 0) === 1) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= ((int) ($perfil['ativo'] ?? 0) === 1) ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars((string) ($perfil['criado_por_nome'] ?? 'Sistema')) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openProfileDrawer(<?= (int) $perfil['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-perfil-' . (int) $perfil['id'];
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

<!-- Criar/Editar perfil em drawer lateral -->
<div id="profileDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeProfileDrawer()"></div>
<aside id="profileDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="profileDrawerTitle" class="text-xl font-bold text-gray-900">Novo Perfil</h2>
        <button type="button" onclick="closeProfileDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="profile-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="perfil_id" value="">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do Perfil</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="perfil_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome do Perfil *</label>
                        <input type="text" id="perfil_nome" name="nome" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="perfil_tipo_base" class="block text-sm font-medium text-gray-700 mb-1">Tipo Base *</label>
                        <div class="relative">
                            <select id="perfil_tipo_base" name="tipo_base" required
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="dev">Dev</option>
                                <option value="diretor">Diretor</option>
                                <option value="coordenador">Coordenador</option>
                                <option value="financeiro">Financeiro</option>
                                <option value="secretaria">Secretaria</option>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                    </div>
                    <div class="sm:col-span-2">
                        <label for="perfil_descricao" class="block text-sm font-medium text-gray-700 mb-1">Descrição</label>
                        <textarea id="perfil_descricao" name="descricao" rows="2"
                                  class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                </div>
                <label class="inline-flex items-center gap-2 text-sm text-gray-700 mt-4">
                    <input type="checkbox" id="perfil_ativo" name="ativo" value="1" checked class="rounded border-gray-300 text-green-600">
                    Perfil ativo
                </label>
            </section>

            <section>
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-2 mb-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Permissões do Perfil</h3>
                        <p class="text-xs text-gray-600 mt-1">Defina exatamente o que este perfil poderá acessar.</p>
                    </div>
                    <button type="button" id="btn-apply-base" class="px-3 py-1.5 text-xs rounded border border-gray-300 hover:bg-gray-100 flex-shrink-0">
                        Aplicar padrão do tipo base
                    </button>
                </div>
                <div class="overflow-auto border border-gray-200 rounded-lg bg-white">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="text-left px-3 py-2">Módulo</th>
                            <?php foreach ($permissions_action_labels as $actionKey => $actionLabel): ?>
                                <th class="text-center px-3 py-2"><?= htmlspecialchars($actionLabel) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($permissions_sections as $sectionKey => $section): ?>
                            <tr class="border-t bg-slate-50">
                                <td class="px-3 py-2 text-slate-900 font-semibold" colspan="<?= 1 + count($permissions_action_labels) ?>">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" class="section-toggle rounded border-gray-300 text-green-600" data-section-key="<?= htmlspecialchars($sectionKey) ?>">
                                        <span><?= htmlspecialchars($section['label']) ?></span>
                                    </label>
                                </td>
                            </tr>
                            <?php foreach ($section['items'] as $moduleKey): ?>
                                <?php if (!isset($permissions_catalog[$moduleKey])) { continue; } ?>
                                <?php $module = $permissions_catalog[$moduleKey]; ?>
                                <tr class="border-t" data-section-key="<?= htmlspecialchars($sectionKey) ?>">
                                    <td class="px-3 py-2 text-gray-800 pl-6">• <?= htmlspecialchars($module['label']) ?></td>
                                    <?php foreach ($permissions_action_labels as $actionKey => $actionLabel): ?>
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox" class="rounded border-gray-300 text-green-600"
                                                   name="permissions[<?= htmlspecialchars($moduleKey) ?>][<?= htmlspecialchars($actionKey) ?>]" value="1">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeProfileDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="profile-form-submit-label">Salvar Perfil</span>
            </button>
        </div>
    </form>
</aside>

<script>
const URL_BASE = <?= json_encode(URL) ?>;
const defaultsByType = <?= json_encode($permissions_defaults_by_type, JSON_UNESCAPED_UNICODE) ?>;
const permissionCheckboxes = Array.from(document.querySelectorAll('input[name^="permissions["]'));
const sectionToggles = Array.from(document.querySelectorAll('.section-toggle'));

function applyPermissionsMatrix(matrix) {
    matrix = matrix || {};
    permissionCheckboxes.forEach((checkbox) => {
        const match = checkbox.name.match(/^permissions\[([^\]]+)\]\[([^\]]+)\]$/);
        if (!match) return;
        checkbox.checked = !!(matrix[match[1]] && matrix[match[1]][match[2]]);
    });
    refreshSectionToggles();
}

function fillPermissionsByType(type) {
    applyPermissionsMatrix(defaultsByType[type] || {});
}

function refreshSectionToggles() {
    sectionToggles.forEach((toggle) => {
        const sectionKey = toggle.dataset.sectionKey;
        const sectionCheckboxes = Array.from(document.querySelectorAll(`tr[data-section-key="${sectionKey}"] input[type="checkbox"]`));
        if (sectionCheckboxes.length === 0) {
            toggle.checked = false;
            toggle.indeterminate = false;
            return;
        }
        const checkedCount = sectionCheckboxes.filter((c) => c.checked).length;
        toggle.checked = checkedCount === sectionCheckboxes.length;
        toggle.indeterminate = checkedCount > 0 && checkedCount < sectionCheckboxes.length;
    });
}

sectionToggles.forEach((toggle) => {
    toggle.addEventListener('change', function () {
        const sectionKey = this.dataset.sectionKey;
        const sectionCheckboxes = Array.from(document.querySelectorAll(`tr[data-section-key="${sectionKey}"] input[type="checkbox"]`));
        sectionCheckboxes.forEach((cb) => { cb.checked = this.checked; });
        refreshSectionToggles();
    });
});

permissionCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshSectionToggles));

document.getElementById('perfil_tipo_base').addEventListener('change', function () {
    fillPermissionsByType(this.value);
});

document.getElementById('btn-apply-base').addEventListener('click', function () {
    fillPermissionsByType(document.getElementById('perfil_tipo_base').value);
});

function showProfileDrawer() {
    document.getElementById('profileDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('profileDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeProfileDrawer() {
    document.getElementById('profileDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('profileDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openProfileDrawer(id) {
    const form = document.getElementById('profile-form');
    form.reset();
    document.getElementById('perfil_id').value = '';

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('profileDrawerTitle').textContent = 'Novo Perfil';
        document.getElementById('profile-form-submit-label').textContent = 'Salvar Perfil';
        document.getElementById('perfil_ativo').checked = true;
        fillPermissionsByType(document.getElementById('perfil_tipo_base').value);
        showProfileDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('profileDrawerTitle').textContent = 'Editar Perfil';
    document.getElementById('profile-form-submit-label').textContent = 'Salvar Perfil';

    showProfileDrawer();

    fetch(URL_BASE + '/admin/permissoes-perfis/' + id + '/dados')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar o perfil'));
                closeProfileDrawer();
                return;
            }
            const perfil = data.perfil;
            document.getElementById('perfil_id').value = perfil.id;
            document.getElementById('perfil_nome').value = perfil.nome;
            document.getElementById('perfil_tipo_base').value = perfil.tipo_base;
            document.getElementById('perfil_descricao').value = perfil.descricao || '';
            document.getElementById('perfil_ativo').checked = !!perfil.ativo;
            applyPermissionsMatrix(data.permissoes || {});
        })
        .catch(() => {
            alert('Erro de conexão ao carregar perfil.');
            closeProfileDrawer();
        });
}

document.getElementById('profile-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const mode = this.dataset.mode;
    const id = document.getElementById('perfil_id').value;
    const url = mode === 'create' ? (URL_BASE + '/admin/permissoes-perfis') : (URL_BASE + '/admin/permissoes-perfis/' + id);

    const response = await fetch(url, { method: 'POST', body: new FormData(this) });
    const result = await response.json();
    if (result.success) {
        window.location.reload();
        return;
    }
    alert('Erro: ' + (result.error || 'Não foi possível salvar.'));
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeProfileDrawer();
    }
});
</script>
