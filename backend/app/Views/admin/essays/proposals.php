<?php
$filtros = $filtros ?? ['titulo' => '', 'professor_id' => '', 'status' => ''];
$professores = $professores ?? [];
$filtrosAtivosCount = 0;
foreach (['titulo', 'professor_id', 'status'] as $fk) {
    if (!empty($filtros[$fk])) {
        $filtrosAtivosCount++;
    }
}
?>
<div class="mb-8">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Redação do Professor</h2>
            <p class="text-gray-600">Visualizar propostas e envios dos alunos</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
            <a href="<?= URL ?>/admin/redacao-professor/novo"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Nova proposta
            </a>
        </div>
    </div>
</div>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar propostas</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/redacao-professor" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_titulo" class="block text-sm font-medium text-gray-700 mb-1.5">Título</label>
                <input type="text" id="filtro_titulo" name="titulo" value="<?= htmlspecialchars($filtros['titulo']) ?>"
                       placeholder="Buscar por título..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_professor" class="block text-sm font-medium text-gray-700 mb-1.5">Professor</label>
                <select id="filtro_professor" name="professor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($professores as $prof): ?>
                        <option value="<?= (int) $prof['id'] ?>" <?= (string) $filtros['professor_id'] === (string) $prof['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($prof['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="published" <?= $filtros['status'] === 'published' ? 'selected' : '' ?>>Publicada</option>
                    <option value="draft" <?= $filtros['status'] === 'draft' ? 'selected' : '' ?>>Rascunho</option>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <button type="button" onclick="clearFilters()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<?php
$flash = $message ?? null;
if ($flash && is_array($flash) && !empty($flash['message'])):
    $type = $flash['type'] ?? 'info';
    $cls = $type === 'error' ? 'bg-red-100 border-red-400 text-red-700' : 'bg-green-100 border-green-400 text-green-700';
?>
<div class="mb-4 p-4 rounded-lg border <?= $cls ?>">
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Propostas de Redação</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Banca / Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data de criação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($proposals)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhuma proposta encontrada.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($proposals as $p): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($p['title'] ?? '')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($p['professor_nome'] ?? '—')) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-500">
                            <?= htmlspecialchars((string) ($p['board_name'] ?? '')) ?> — <?= htmlspecialchars((string) ($p['text_type_name'] ?? '')) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex flex-col gap-1">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= (($p['status'] ?? '') === 'published') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800' ?>">
                                    <?= (($p['status'] ?? '') === 'published') ? 'Publicada' : 'Rascunho' ?>
                                </span>
                                <?php if (!(bool) ($p['ativo'] ?? 1)): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inativa</span>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= !empty($p['created_at']) ? htmlspecialchars(date('d/m/Y', strtotime((string) $p['created_at']))) : '—' ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <a href="<?= URL ?>/admin/redacao-configuravel/propostas/<?= (int) ($p['id'] ?? 0) ?>" class="text-indigo-600 hover:text-indigo-900">Abrir</a>
                            <span class="mx-1 text-gray-300">|</span>
                            <?php if ((bool) ($p['ativo'] ?? 1)): ?>
                            <button type="button" class="text-red-600 hover:text-red-800"
                                    onclick="openToggleAtivoModal(<?= (int) ($p['id'] ?? 0) ?>, <?= htmlspecialchars(json_encode((string) ($p['title'] ?? '')), ENT_QUOTES) ?>, true)">Desativar</button>
                            <?php else: ?>
                            <button type="button" class="text-green-600 hover:text-green-800"
                                    onclick="openToggleAtivoModal(<?= (int) ($p['id'] ?? 0) ?>, <?= htmlspecialchars(json_encode((string) ($p['title'] ?? '')), ENT_QUOTES) ?>, false)">Reativar</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pag = $pagination ?? [];
    $pagTotal = (int)($pag['total'] ?? 0);
    $pagPerPage = (int)($pag['per_page'] ?? 10);
    $pagPage = (int)($pag['page'] ?? 1);
    $pagTotalPages = (int)($pag['total_pages'] ?? 1);
    $pagQueryParams = $_GET ?? [];
    unset($pagQueryParams['page']);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> proposta(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/redacao-professor<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/redacao-professor<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/redacao-professor<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: confirmar senha para desativar/reativar proposta -->
<div id="toggle-ativo-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-24 mx-auto p-5 border w-full max-w-md shadow-lg rounded-md bg-white">
        <h3 class="text-lg font-medium text-gray-900 mb-2" id="toggle-ativo-modal-title">Confirmar</h3>
        <p class="text-sm text-gray-600 mb-4" id="toggle-ativo-modal-desc"></p>
        <div class="mb-4">
            <label for="toggle-ativo-senha" class="block text-sm font-medium text-gray-700 mb-2">Sua senha *</label>
            <input type="password" id="toggle-ativo-senha"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-red-500 focus:border-transparent"
                   placeholder="Digite sua senha para confirmar" autocomplete="current-password">
            <p id="toggle-ativo-error" class="mt-2 text-sm text-red-600 hidden"></p>
        </div>
        <div class="flex justify-end gap-3">
            <button type="button" id="toggle-ativo-cancel" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50">Cancelar</button>
            <button type="button" id="toggle-ativo-confirm" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 font-semibold">Confirmar</button>
        </div>
    </div>
</div>

<script>
    (function () {
        var csrfToken = <?= json_encode($csrf_token ?? '') ?>;
        var modal = document.getElementById('toggle-ativo-modal');
        var senhaInput = document.getElementById('toggle-ativo-senha');
        var errorEl = document.getElementById('toggle-ativo-error');
        var titleEl = document.getElementById('toggle-ativo-modal-title');
        var descEl = document.getElementById('toggle-ativo-modal-desc');
        var confirmBtn = document.getElementById('toggle-ativo-confirm');
        var pendingId = null;
        var pendingDesativar = true;

        window.openToggleAtivoModal = function (id, titulo, desativar) {
            pendingId = id;
            pendingDesativar = desativar;
            senhaInput.value = '';
            errorEl.classList.add('hidden');
            errorEl.textContent = '';
            if (desativar) {
                titleEl.textContent = 'Desativar proposta';
                descEl.textContent = '"' + titulo + '" deixará de aparecer para professores e alunos (relatórios inclusos). Nada é apagado — você pode reativar quando quiser. Digite sua senha para confirmar.';
            } else {
                titleEl.textContent = 'Reativar proposta';
                descEl.textContent = '"' + titulo + '" voltará a aparecer para professores e alunos. Digite sua senha para confirmar.';
            }
            modal.classList.remove('hidden');
            senhaInput.focus();
        };

        function closeModal() {
            modal.classList.add('hidden');
            pendingId = null;
        }

        document.getElementById('toggle-ativo-cancel').addEventListener('click', closeModal);
        modal.addEventListener('click', function (e) {
            if (e.target === modal) closeModal();
        });

        confirmBtn.addEventListener('click', function () {
            var senha = senhaInput.value.trim();
            if (!senha) {
                errorEl.textContent = 'Digite sua senha para confirmar.';
                errorEl.classList.remove('hidden');
                return;
            }
            confirmBtn.disabled = true;

            var body = new URLSearchParams();
            body.append('_token', csrfToken);
            body.append('senha', senha);

            fetch(<?= json_encode(URL . '/admin/redacao-professor/') ?> + pendingId + '/toggle-status', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: body.toString()
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        location.reload();
                        return;
                    }
                    errorEl.textContent = data.error || 'Falha ao atualizar a proposta.';
                    errorEl.classList.remove('hidden');
                })
                .catch(function () {
                    errorEl.textContent = 'Erro de conexão. Tente novamente.';
                    errorEl.classList.remove('hidden');
                })
                .finally(function () { confirmBtn.disabled = false; });
        });

        senhaInput.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                confirmBtn.click();
            }
        });
    })();

    function openFilterDrawer() {
        document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
        const drawer = document.getElementById('filterDrawer');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeFilterDrawer() {
        document.getElementById('filterDrawerBackdrop').classList.add('hidden');
        const drawer = document.getElementById('filterDrawer');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    function clearFilters() {
        window.location.href = <?= json_encode(URL . '/admin/redacao-professor') ?>;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilterDrawer();
        }
    });
</script>
