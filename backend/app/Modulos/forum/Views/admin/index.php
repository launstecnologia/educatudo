<?php
$current_page = 'forum';
$error = $_SESSION['forum_error'] ?? '';
$success = $_SESSION['forum_success'] ?? '';
if (isset($_SESSION['forum_error'])) unset($_SESSION['forum_error']);
if (isset($_SESSION['forum_success'])) unset($_SESSION['forum_success']);
$baseUrl = URL . '/forum';

$selectedIds = isset($filters['turma_ids']) && is_array($filters['turma_ids']) ? array_map('intval', $filters['turma_ids']) : [];
$filtrosAtivosCount = 0;
if (!empty($filters['q'])) { $filtrosAtivosCount++; }
if (!empty($selectedIds)) { $filtrosAtivosCount++; }
if (($filters['is_resolved'] ?? '') !== '') { $filtrosAtivosCount++; }
?>
<div class="mb-8">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Fórum EducaTudo</h2>
            <p class="text-gray-600">Pergunte e compartilhe conhecimento com a comunidade.</p>
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
            <a href="<?= $baseUrl ?>/create"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Nova pergunta
            </a>
        </div>
    </div>
</div>

<?php if ($error): ?>
    <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar tópicos</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= $baseUrl ?>" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="f_q" class="block text-sm font-medium text-gray-700 mb-1.5">Buscar</label>
                <input type="text" id="f_q" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>"
                       placeholder="Título ou conteúdo..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1.5">Turma(s)</label>
                <div class="border border-gray-300 rounded-lg max-h-48 overflow-y-auto">
                    <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm border-b border-gray-100">
                        <input type="checkbox" id="f_turma_todas" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500" <?= empty($selectedIds) ? 'checked' : '' ?>>
                        <span class="font-medium">Todas as turmas</span>
                    </label>
                    <?php foreach ($turmas as $t): $id = (int)$t['id']; $sel = in_array($id, $selectedIds, true); ?>
                        <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm">
                            <input type="checkbox" name="turma_ids[]" value="<?= $id ?>" class="f_turma_item rounded border-gray-300 text-blue-600 focus:ring-blue-500" <?= $sel ? 'checked' : '' ?>>
                            <span><?= htmlspecialchars($t['nome']) ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div>
                <label for="f_resolved" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="f_resolved" name="resolved" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="0" <?= ($filters['is_resolved'] ?? '') === '0' ? 'selected' : '' ?>>Abertos</option>
                    <option value="1" <?= ($filters['is_resolved'] ?? '') === '1' ? 'selected' : '' ?>>Resolvidos</option>
                </select>
            </div>
            <div>
                <label for="f_order" class="block text-sm font-medium text-gray-700 mb-1.5">Ordenar</label>
                <select id="f_order" name="order" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="recent" <?= ($filters['order'] ?? '') === 'recent' ? 'selected' : '' ?>>Mais recentes</option>
                    <option value="votes" <?= ($filters['order'] ?? '') === 'votes' ? 'selected' : '' ?>>Mais votados</option>
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

<div class="space-y-3">
    <?php if (empty($topics)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-500">
            Nenhum tópico encontrado. Seja o primeiro a <a href="<?= $baseUrl ?>/create" class="text-blue-600 hover:underline">criar uma pergunta</a>.
        </div>
    <?php else: ?>
        <?php foreach ($topics as $t): ?>
            <a href="<?= $baseUrl ?>/<?= (int)$t['id'] ?>" class="block bg-white rounded-xl shadow-sm border border-gray-200 hover:border-blue-300 hover:shadow-md transition-all p-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <h3 class="text-lg font-semibold text-gray-900 truncate"><?= htmlspecialchars($t['title']) ?></h3>
                        <p class="text-sm text-gray-500 mt-1">
                            <?= htmlspecialchars($t['author_name']) ?>
                            · <?= (int)($t['replies_count'] ?? 0) ?> resposta(s)
                            <?php if (!empty($t['is_resolved'])): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800">Resolvido</span>
                            <?php endif; ?>
                        </p>
                    </div>
                    <time class="text-sm text-gray-400 whitespace-nowrap" datetime="<?= htmlspecialchars($t['created_at'] ?? '') ?>">
                        <?= !empty($t['created_at']) ? date('d/m/Y H:i', strtotime($t['created_at'])) : '' ?>
                    </time>
                </div>
            </a>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$pagTotal = (int) ($total ?? 0);
$pagPerPage = (int) ($per_page ?? 20);
$pagPage = (int) ($page ?? 1);
$pagTotalPages = $pagPerPage > 0 ? (int) ceil($pagTotal / $pagPerPage) : 1;
$pagQueryParams = array_filter([
    'q' => $filters['q'] ?? '',
    'resolved' => $filters['is_resolved'] ?? '',
    'order' => $filters['order'] ?? '',
], static function ($v) { return $v !== '' && $v !== null; });
$pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
if (!empty($filters['turma_ids']) && is_array($filters['turma_ids'])) {
    foreach ($filters['turma_ids'] as $tid) {
        $pagBaseQuery .= ($pagBaseQuery === '' ? '?' : '&') . 'turma_ids[]=' . (int) $tid;
    }
}
$pagSep = $pagBaseQuery === '' ? '?' : '&';
?>
<?php if ($pagTotal > 0): ?>
<div class="mt-6 bg-white rounded-xl shadow-sm border border-gray-200 px-6 py-4 flex flex-wrap items-center justify-between gap-2">
    <p class="text-sm text-gray-600">
        Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> tópico(s)
    </p>
    <?php if ($pagTotalPages > 1): ?>
    <div class="flex items-center gap-1">
        <?php if ($pagPage > 1): ?>
            <a href="<?= $baseUrl . $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
        <?php endif; ?>
        <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
            <a href="<?= $baseUrl . $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
        <?php endfor; ?>
        <?php if ($pagPage < $pagTotalPages): ?>
            <a href="<?= $baseUrl . $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
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
        window.location.href = <?= json_encode($baseUrl) ?>;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilterDrawer();
        }
    });

    (function() {
        var todasCb = document.getElementById('f_turma_todas');
        var itemCbs = document.querySelectorAll('.f_turma_item');
        if (todasCb) {
            todasCb.addEventListener('change', function() {
                if (todasCb.checked) {
                    itemCbs.forEach(function(cb) { cb.checked = false; });
                }
            });
        }
        itemCbs.forEach(function(cb) {
            cb.addEventListener('change', function() {
                var anyChecked = false;
                itemCbs.forEach(function(c) { if (c.checked) anyChecked = true; });
                if (anyChecked && todasCb) todasCb.checked = false;
            });
        });
    })();
</script>
