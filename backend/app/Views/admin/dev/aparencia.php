<?php require __DIR__ . '/_styles.php'; ?>

<header class="mb-8 pb-6 border-b border-gray-200">
    <a href="<?= URL ?>/admin/dev" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Configurações Avançadas</a>
    <div class="flex items-center gap-3 mt-2">
        <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 text-2xl">🎨</span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Aparência e Menu</h1>
            <p class="text-sm text-gray-500 mt-0.5">Cores, logos, PWA e organização do menu lateral</p>
        </div>
    </div>
</header>

<?php
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? 'info';
if ($flash_message !== ''):
    $bg = $flash_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800');
?>
<div class="mb-6 p-4 rounded-lg border <?= $bg ?>">
    <?= htmlspecialchars($flash_message) ?>
</div>
<?php endif; ?>

<div class="space-y-6 max-w-5xl">

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="dev-card">
            <div class="dev-card-header">Layout do Sistema</div>
            <div class="dev-card-body">
                <p class="text-sm text-gray-600 mb-4">Cores, logotipos e nome do grupo de menu.</p>
                <a href="<?= URL ?>/admin/dev/layout" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm">
                    Abrir Layout do Sistema
                </a>
            </div>
        </div>
        <div class="dev-card">
            <div class="dev-card-header">PWA por Perfil</div>
            <div class="dev-card-body">
                <p class="text-sm text-gray-600 mb-4">Nome, ícones e cores do app instalável por perfil.</p>
                <a href="<?= URL ?>/admin/dev-settings/pwa" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm">
                    Abrir PWA Settings
                </a>
            </div>
        </div>
        <div class="dev-card">
            <div class="dev-card-header">Layout Mobile</div>
            <div class="dev-card-body">
                <p class="text-sm text-gray-600 mb-4">Layout mobile do Aluno e Admin.</p>
                <a href="<?= URL ?>/admin/dev-settings/layout-mobile" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors text-sm">
                    Abrir Layout Mobile
                </a>
            </div>
        </div>
    </div>

    <div class="dev-card">
        <div class="dev-card-header">Ordem e nomes do menu do Aluno — grupo "Estudo"</div>
        <p class="text-gray-500 text-sm mt-1 px-4 pt-2 pb-0">Arraste para reordenar. Edite o nome se quiser renomear o item no menu (deixe em branco para usar o nome padrão).</p>
        <div class="dev-card-body">
            <form id="menu-order-estudo-form" method="post" action="<?= URL ?>/admin/dev/menu-order">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php
                    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
                    $menuOrderEstudoDefault = [
                        'aulas_online',
                        'drive',
                        'educa_livros',
                        'aluno_minicursos',
                        'educalabs',
                        'exercicios',
                        'aluno_flashcards',
                        'forum',
                        'ingles',
                        'ead',
                        'redacoes',
                        'simulados',
                        '_apps_externos',
                    ];
                    $menuLabelsDefault = [
                        'educa_livros' => 'Educa Livros',
                        'educalabs' => 'EducaLabs',
                        'aluno_flashcards' => 'Flash Card',
                        'exercicios' => 'Exercícios',
                        'ingles' => 'Inglês',
                        'redacoes' => 'Redação',
                        'simulados' => 'Simulados',
                        'aulas_online' => 'Aulas Online',
                        'aluno_minicursos' => 'EducaCursos',
                        'ead' => 'Meus Cursos',
                        'forum' => 'Fórum',
                        'drive' => 'Drive',
                    ];
                    $menuOrderEstudoSalvo = json_decode((string) LayoutHelper::get('menu_order_estudo', '[]'), true);
                    if (!is_array($menuOrderEstudoSalvo)) {
                        $menuOrderEstudoSalvo = [];
                    }
                    $menuOrderEstudoFinal = [];
                    foreach ($menuOrderEstudoSalvo as $k) {
                        if (in_array($k, $menuOrderEstudoDefault, true) && !in_array($k, $menuOrderEstudoFinal, true)) {
                            $menuOrderEstudoFinal[] = $k;
                        }
                    }
                    foreach ($menuOrderEstudoDefault as $k) {
                        if (!in_array($k, $menuOrderEstudoFinal, true)) {
                            $menuOrderEstudoFinal[] = $k;
                        }
                    }
                    $menuLabelsSalvos = json_decode((string) LayoutHelper::get('menu_labels', '[]'), true);
                    if (!is_array($menuLabelsSalvos)) {
                        $menuLabelsSalvos = [];
                    }
                ?>
                <ul id="menu-order-estudo-list" class="space-y-2">
                    <?php foreach ($menuOrderEstudoFinal as $itemKey): ?>
                        <?php $isApps = $itemKey === '_apps_externos'; ?>
                        <li draggable="true" data-key="<?= htmlspecialchars($itemKey) ?>"
                            class="menu-order-item flex items-center gap-3 p-3 rounded-lg bg-white border border-gray-200 cursor-move hover:border-indigo-300">
                            <span class="text-gray-400 select-none" aria-hidden="true">⠿</span>
                            <?php if ($isApps): ?>
                                <span class="text-sm text-gray-600 italic flex-1">Apps Externos deste grupo (Notes, Tudinha 2.0 etc. — ordem entre eles é configurada no Master)</span>
                            <?php else: ?>
                                <input type="text" name="labels[<?= htmlspecialchars($itemKey) ?>]"
                                       value="<?= htmlspecialchars($menuLabelsSalvos[$itemKey] ?? '') ?>"
                                       placeholder="<?= htmlspecialchars($menuLabelsDefault[$itemKey] ?? $itemKey) ?>"
                                       class="flex-1 text-sm border border-gray-300 rounded-lg px-3 py-1.5 focus:border-indigo-500 focus:ring-1 focus:ring-indigo-500">
                            <?php endif; ?>
                            <input type="hidden" name="order[estudo][]" value="<?= htmlspecialchars($itemKey) ?>">
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="pt-4 flex justify-end">
                    <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg transition-colors font-medium hover:opacity-90">
                        Salvar ordem e nomes
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="dev-card">
        <div class="dev-card-header">Links no Submenu (Aluno/Professor)</div>
        <p class="text-gray-500 text-sm mt-1 px-4 pt-2 pb-0">Adicione links personalizados no submenu do aluno e/ou professor.</p>
        <div class="dev-card-body">
            <form id="menu-links-form" method="post" action="<?= URL ?>/admin/dev/layout/save" class="space-y-4">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php
                    $customLinksJson = LayoutHelper::get('menu_links_submenu', '[]');
                    $customLinks = json_decode($customLinksJson, true);
                    if (!is_array($customLinks)) {
                        $customLinks = [];
                    }
                ?>
                <input type="hidden" name="config[menu_links_submenu]" id="menu-links-json" value="<?= htmlspecialchars($customLinksJson) ?>">

                <div id="menu-links-list" class="space-y-3">
                    <?php if (empty($customLinks)): ?>
                        <p class="text-sm text-gray-500">Nenhum link cadastrado.</p>
                    <?php else: ?>
                        <?php foreach ($customLinks as $link): ?>
                            <div class="grid grid-cols-1 md:grid-cols-12 gap-3 items-center menu-link-row">
                                <input type="text" class="menu-link-nome md:col-span-4 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Nome do menu" value="<?= htmlspecialchars($link['nome'] ?? '') ?>">
                                <input type="text" class="menu-link-url md:col-span-5 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                                       placeholder="Link (ex: /aluno/planos-aula ou https://...)" value="<?= htmlspecialchars($link['url'] ?? '') ?>">
                                <label class="md:col-span-1 flex items-center space-x-2 text-sm text-gray-700">
                                    <input type="checkbox" class="menu-link-aluno h-4 w-4 text-indigo-600 rounded" <?= !empty($link['aluno']) ? 'checked' : '' ?>>
                                    <span>Aluno</span>
                                </label>
                                <label class="md:col-span-1 flex items-center space-x-2 text-sm text-gray-700">
                                    <input type="checkbox" class="menu-link-professor h-4 w-4 text-indigo-600 rounded" <?= !empty($link['professor']) ? 'checked' : '' ?>>
                                    <span>Professor</span>
                                </label>
                                <label class="md:col-span-1 flex items-center space-x-2 text-sm text-gray-700">
                                    <input type="checkbox" class="menu-link-nova-guia h-4 w-4 text-indigo-600 rounded" <?= !empty($link['nova_guia']) ? 'checked' : '' ?>>
                                    <span>Nova guia</span>
                                </label>
                                <div class="md:col-span-1">
                                    <button type="button" class="menu-link-remove text-red-600 hover:text-red-800 text-sm">Remover</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <div class="flex items-center justify-between pt-2">
                    <button type="button" id="add-menu-link" class="bg-gray-100 text-gray-700 px-4 py-2 rounded-lg hover:bg-gray-200 transition-colors">
                        + Adicionar link
                    </button>
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Links
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
(function () {
    var list = document.getElementById('menu-order-estudo-list');
    if (!list) { return; }
    var dragging = null;
    list.addEventListener('dragstart', function (e) {
        dragging = e.target.closest('.menu-order-item');
        e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        var target = e.target.closest('.menu-order-item');
        if (!target || target === dragging) { return; }
        var rect = target.getBoundingClientRect();
        var before = (e.clientY - rect.top) < rect.height / 2;
        list.insertBefore(dragging, before ? target : target.nextSibling);
    });
    var form = document.getElementById('menu-order-estudo-form');
    if (form) {
        form.addEventListener('submit', function () {
            list.querySelectorAll('.menu-order-item').forEach(function (li) {
                form.appendChild(li.querySelector('input[type="hidden"]'));
            });
        });
    }
})();

function escapeHtml(str) {
    var div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

(function() {
    const form = document.getElementById('menu-links-form');
    const list = document.getElementById('menu-links-list');
    const addButton = document.getElementById('add-menu-link');
    const jsonInput = document.getElementById('menu-links-json');
    if (!form || !list || !addButton || !jsonInput) return;

    function createRow(data = {}) {
        const row = document.createElement('div');
        row.className = 'grid grid-cols-1 md:grid-cols-12 gap-3 items-center menu-link-row';
        row.innerHTML = `
            <input type="text" class="menu-link-nome md:col-span-4 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                   placeholder="Nome do menu" value="${data.nome ? escapeHtml(data.nome) : ''}">
            <input type="text" class="menu-link-url md:col-span-5 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500"
                   placeholder="Link (ex: /aluno/planos-aula ou https://...)" value="${data.url ? escapeHtml(data.url) : ''}">
            <label class="md:col-span-1 flex items-center space-x-2 text-sm text-gray-700">
                <input type="checkbox" class="menu-link-aluno h-4 w-4 text-indigo-600 rounded" ${data.aluno ? 'checked' : ''}>
                <span>Aluno</span>
            </label>
            <label class="md:col-span-1 flex items-center space-x-2 text-sm text-gray-700">
                <input type="checkbox" class="menu-link-professor h-4 w-4 text-indigo-600 rounded" ${data.professor ? 'checked' : ''}>
                <span>Professor</span>
            </label>
            <label class="md:col-span-1 flex items-center space-x-2 text-sm text-gray-700">
                <input type="checkbox" class="menu-link-nova-guia h-4 w-4 text-indigo-600 rounded" ${data.nova_guia ? 'checked' : ''}>
                <span>Nova guia</span>
            </label>
            <div class="md:col-span-1">
                <button type="button" class="menu-link-remove text-red-600 hover:text-red-800 text-sm">Remover</button>
            </div>
        `;
        row.querySelector('.menu-link-remove').addEventListener('click', () => row.remove());
        return row;
    }

    addButton.addEventListener('click', function() {
        list.querySelectorAll('p').forEach(p => p.remove());
        list.appendChild(createRow());
    });

    list.querySelectorAll('.menu-link-remove').forEach(btn => {
        btn.addEventListener('click', function() {
            btn.closest('.menu-link-row')?.remove();
        });
    });

    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const rows = list.querySelectorAll('.menu-link-row');
        const links = [];
        rows.forEach(row => {
            const nome = row.querySelector('.menu-link-nome')?.value.trim() || '';
            const url = row.querySelector('.menu-link-url')?.value.trim() || '';
            const aluno = row.querySelector('.menu-link-aluno')?.checked || false;
            const professor = row.querySelector('.menu-link-professor')?.checked || false;
            const novaGuia = row.querySelector('.menu-link-nova-guia')?.checked || false;
            if (!nome || !url) {
                return;
            }
            links.push({ nome, url, aluno, professor, nova_guia: novaGuia });
        });

        jsonInput.value = JSON.stringify(links);
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ Links salvos com sucesso');
                window.location.reload();
            } else if (data && data.error) {
                alert('❌ Erro: ' + data.error);
            } else {
                alert('❌ Erro inesperado ao salvar links');
            }
        })
        .catch(() => alert('❌ Falha na conexão ao salvar links'));
    });
})();
</script>
