<?php
$page_header_title = 'Monitores de Sala';
$page_header_subtitle = 'Usuários que acompanham alunos online durante provas e jornadas';
ob_start();
?>
<button type="button" onclick="openMonitorDrawer()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo Monitor
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
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turmas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($monitors)): ?>
                <tr>
                    <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-user-shield text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum monitor cadastrado</p>
                        <button type="button" onclick="openMonitorDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i> Novo Monitor
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($monitors as $m): ?>
                <?php
                    $turmasIds = json_decode($m['turmas'] ?? '[]', true) ?: [];
                    $nomes = [];
                    foreach ($turmasIds as $tid) {
                        $nomes[] = $turmas_map[(string) $tid] ?? ('#' . $tid);
                    }
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars($m['nome']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($m['email']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars(implode(', ', $nomes) ?: '-') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($m['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= !empty($m['ativo']) ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openMonitorDrawer(<?= (int) $m['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="excluirMonitor(<?= (int) $m['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-monitor-' . (int) $m['id'];
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

<p class="mt-4 text-sm text-gray-500">Login em: <a class="text-blue-600 hover:underline" href="<?= URL ?>/monitor"><?= URL ?>/monitor</a></p>

<!-- Criar/Editar monitor em drawer lateral -->
<div id="monitorDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeMonitorDrawer()"></div>
<aside id="monitorDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="monitorDrawerTitle" class="text-xl font-bold text-gray-900">Novo Monitor</h2>
        <button type="button" onclick="closeMonitorDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="monitor-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="monitor_id" value="">
        <input type="hidden" name="_method" id="monitor_method" value="" disabled>

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-6">
            <p id="monitor-senha-padrao" class="text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded-lg px-3 py-2">
                Senha padrão: <strong>123456</strong> (alteração obrigatória no primeiro login)
            </p>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                <div>
                    <label for="monitor_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome *</label>
                    <input type="text" id="monitor_nome" name="nome" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="monitor_email" class="block text-sm font-medium text-gray-700 mb-1">Email (login) *</label>
                    <input type="email" id="monitor_email" name="email" required
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            </div>

            <div id="monitor-senha-section" class="hidden">
                <label for="monitor_senha" class="block text-sm font-medium text-gray-700 mb-1">Nova senha (opcional)</label>
                <input type="password" id="monitor_senha" name="senha" placeholder="Deixe em branco para manter"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>

            <div>
                <div class="flex items-center justify-between mb-2">
                    <label class="block text-sm font-medium text-gray-700">Turmas que pode monitorar *</label>
                    <button type="button" id="btn-turmas-todas" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Selecionar todas</button>
                </div>
                <div id="turmas-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-56 overflow-y-auto border border-gray-200 rounded-lg p-3">
                    <?php foreach ($turmas_disponiveis as $t): ?>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" name="turmas[]" value="<?= (int) $t['id'] ?>" class="rounded border-gray-300 text-green-600">
                        <?= htmlspecialchars($t['nome']) ?>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <label class="flex items-center gap-2 text-sm text-gray-700">
                <input type="checkbox" id="monitor_ativo" name="ativo" value="1" checked class="rounded border-gray-300 text-green-600">
                Ativo
            </label>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeMonitorDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="monitor-form-submit-label">Salvar</span>
            </button>
        </div>
    </form>
</aside>

<script>
const URL_BASE = <?= json_encode(URL) ?>;

document.getElementById('btn-turmas-todas')?.addEventListener('click', function () {
    document.querySelectorAll('#turmas-grid input[type="checkbox"]').forEach(function (cb) { cb.checked = true; });
});

function showMonitorDrawer() {
    document.getElementById('monitorDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('monitorDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeMonitorDrawer() {
    document.getElementById('monitorDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('monitorDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openMonitorDrawer(id) {
    const form = document.getElementById('monitor-form');
    form.reset();
    document.getElementById('monitor_id').value = '';
    document.getElementById('monitor_method').value = '';
    document.getElementById('monitor_method').disabled = true;

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('monitorDrawerTitle').textContent = 'Novo Monitor';
        document.getElementById('monitor-form-submit-label').textContent = 'Salvar';
        document.getElementById('monitor-senha-padrao').classList.remove('hidden');
        document.getElementById('monitor-senha-section').classList.add('hidden');
        document.getElementById('monitor_ativo').checked = true;
        showMonitorDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('monitorDrawerTitle').textContent = 'Editar Monitor';
    document.getElementById('monitor-form-submit-label').textContent = 'Atualizar';
    document.getElementById('monitor-senha-padrao').classList.add('hidden');
    document.getElementById('monitor-senha-section').classList.remove('hidden');
    document.getElementById('monitor_method').value = 'PUT';
    document.getElementById('monitor_method').disabled = false;

    showMonitorDrawer();

    fetch(URL_BASE + '/admin/monitors/' + id + '/dados')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar o monitor'));
                closeMonitorDrawer();
                return;
            }
            const monitor = data.monitor;
            document.getElementById('monitor_id').value = monitor.id;
            document.getElementById('monitor_nome').value = monitor.nome;
            document.getElementById('monitor_email').value = monitor.email;
            document.getElementById('monitor_ativo').checked = !!monitor.ativo;
            document.querySelectorAll('#turmas-grid input[type="checkbox"]').forEach((cb) => {
                cb.checked = monitor.turmas_array.includes(parseInt(cb.value, 10));
            });
        })
        .catch(() => {
            alert('Erro de conexão ao carregar monitor.');
            closeMonitorDrawer();
        });
}

document.getElementById('monitor-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const mode = this.dataset.mode;
    const id = document.getElementById('monitor_id').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const submitLabel = document.getElementById('monitor-form-submit-label');
    const originalText = submitLabel.textContent;

    const turmasChecked = document.querySelectorAll('#turmas-grid input[type="checkbox"]:checked');
    if (turmasChecked.length === 0) {
        alert('Selecione ao menos uma turma.');
        return;
    }

    submitBtn.disabled = true;
    submitLabel.textContent = 'Salvando...';

    const url = mode === 'create' ? (URL_BASE + '/admin/monitors') : (URL_BASE + '/admin/monitors/' + id);

    fetch(url, { method: 'POST', body: new FormData(this), credentials: 'same-origin' })
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

function excluirMonitor(id) {
    if (!confirm('Excluir este monitor?')) return;
    const fd = new FormData();
    fd.append('_token', <?= json_encode($csrf_token) ?>);
    fd.append('_method', 'DELETE');
    fetch(URL_BASE + '/admin/monitors/' + id, { method: 'POST', body: fd, credentials: 'same-origin' })
        .then((r) => r.json())
        .then((d) => { if (d.success) { location.reload(); } else { alert(d.error || 'Erro'); } });
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeMonitorDrawer();
    }
});
</script>
