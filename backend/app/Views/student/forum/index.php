<?php
$current_page = 'forum';
$error = $_SESSION['forum_error'] ?? '';
$success = $_SESSION['forum_success'] ?? '';
if (isset($_SESSION['forum_error'])) unset($_SESSION['forum_error']);
if (isset($_SESSION['forum_success'])) unset($_SESSION['forum_success']);
$baseUrl = URL . '/forum';
?>
<div class="mobile-content flex-1 overflow-y-auto w-full min-h-0 p-4 md:p-6">
    <div class="w-full max-w-5xl xl:max-w-6xl mx-auto">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-gray-900">Fórum EducaTudo</h1>
                <p class="text-gray-600 mt-1">Pergunte e compartilhe conhecimento com a comunidade.</p>
            </div>
            <a href="<?= $baseUrl ?>/create" class="inline-flex items-center px-4 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm">
                <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8v8H4V4h8z"></path></svg>
                Nova pergunta
            </a>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="mb-4 p-4 bg-green-50 border border-green-200 text-green-700 rounded-lg"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <form method="get" action="<?= $baseUrl ?>" class="mb-6 flex flex-wrap gap-3 items-end">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 mb-1">Buscar</label>
                <input type="text" name="q" value="<?= htmlspecialchars($filters['q'] ?? '') ?>" placeholder="Título ou conteúdo..." class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <?php
            $selectedIds = isset($filters['turma_ids']) && is_array($filters['turma_ids']) ? array_map('intval', $filters['turma_ids']) : [];
            $turmasById = [];
            foreach ($turmas as $t) { $turmasById[(int)$t['id']] = $t['nome']; }
            $turmasLabel = empty($selectedIds) ? 'Todas as turmas' : (count($selectedIds) === 1 ? htmlspecialchars($turmasById[$selectedIds[0]] ?? '1 turma') : count($selectedIds) . ' turmas');
            ?>
            <div class="w-56 relative" id="turmas-filter-wrap">
                <label class="block text-sm font-medium text-gray-700 mb-1">Turma(s)</label>
                <button type="button" id="turmas-filter-btn" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 bg-white text-left text-sm flex items-center justify-between gap-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:bg-gray-50 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                    <span id="turmas-filter-label" class="truncate"><?= $turmasLabel ?></span>
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div id="turmas-filter-panel" class="hidden absolute top-full left-0 mt-1 w-full min-w-[200px] bg-white border border-gray-200 rounded-lg shadow-lg z-20 py-2 max-h-64 overflow-y-auto">
                    <button type="button" class="turmas-filter-todas w-full px-3 py-2 text-left text-sm text-indigo-600 hover:bg-indigo-50 font-medium flex items-center gap-2">
                        <span class="w-4 h-4 rounded border border-gray-300 flex items-center justify-center flex-shrink-0"><?= empty($selectedIds) ? '✓' : '' ?></span>
                        Todas as turmas
                    </button>
                    <div class="border-t border-gray-100 mt-1 pt-1">
                        <?php foreach ($turmas as $t): $id = (int)$t['id']; $sel = in_array($id, $selectedIds, true); ?>
                            <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm">
                                <input type="checkbox" name="turma_ids[]" value="<?= $id ?>" class="rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= $sel ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($t['nome']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <script>
            (function() {
                var btn = document.getElementById('turmas-filter-btn');
                var panel = document.getElementById('turmas-filter-panel');
                var label = document.getElementById('turmas-filter-label');
                var wrap = document.getElementById('turmas-filter-wrap');
                var todasBtn = panel && panel.querySelector('.turmas-filter-todas');
                var checkboxes = panel && panel.querySelectorAll('input[name="turma_ids[]"]');
                function updateLabel() {
                    if (!checkboxes || !label) return;
                    var names = [];
                    for (var i = 0; i < checkboxes.length; i++) {
                        if (checkboxes[i].checked) names.push(checkboxes[i].nextElementSibling ? checkboxes[i].nextElementSibling.textContent : '');
                    }
                    label.textContent = names.length === 0 ? 'Todas as turmas' : (names.length === 1 ? names[0] : names.length + ' turmas');
                }
                function setTodas() {
                    if (!checkboxes) return;
                    for (var i = 0; i < checkboxes.length; i++) checkboxes[i].checked = false;
                    updateLabel();
                    if (todasBtn) {
                        var box = todasBtn.querySelector('span');
                        if (box) box.textContent = '✓';
                    }
                }
                if (btn && panel) {
                    btn.addEventListener('click', function(e) {
                        e.preventDefault();
                        var open = !panel.classList.contains('hidden');
                        panel.classList.toggle('hidden', open);
                        btn.setAttribute('aria-expanded', !open);
                    });
                    document.addEventListener('click', function(e) {
                        if (wrap && !wrap.contains(e.target)) panel.classList.add('hidden');
                    });
                }
                if (todasBtn) {
                    todasBtn.addEventListener('click', function() {
                        setTodas();
                    });
                }
                if (checkboxes) {
                    for (var i = 0; i < checkboxes.length; i++) {
                        checkboxes[i].addEventListener('change', function() {
                            updateLabel();
                            if (todasBtn) {
                                var any = false;
                                for (var j = 0; j < checkboxes.length; j++) { if (checkboxes[j].checked) { any = true; break; } }
                                todasBtn.querySelector('span').textContent = any ? '' : '✓';
                            }
                        });
                    }
                }
            })();
            </script>
            <div class="w-36">
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="resolved" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="">Todos</option>
                    <option value="0" <?= ($filters['is_resolved'] ?? '') === '0' ? 'selected' : '' ?>>Abertos</option>
                    <option value="1" <?= ($filters['is_resolved'] ?? '') === '1' ? 'selected' : '' ?>>Resolvidos</option>
                </select>
            </div>
            <div class="w-40">
                <label class="block text-sm font-medium text-gray-700 mb-1">Ordenar</label>
                <select name="order" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500">
                    <option value="recent" <?= ($filters['order'] ?? '') === 'recent' ? 'selected' : '' ?>>Mais recentes</option>
                    <option value="votes" <?= ($filters['order'] ?? '') === 'votes' ? 'selected' : '' ?>>Mais votados</option>
                </select>
            </div>
            <button type="submit" class="px-4 py-2.5 border border-indigo-300 bg-white text-indigo-700 hover:bg-indigo-50 rounded-lg font-medium transition-colors">Filtrar</button>
        </form>

        <div class="space-y-3">
            <?php if (empty($topics)): ?>
                <div class="bg-white rounded-xl shadow border border-gray-200 p-8 text-center text-gray-500">
                    Nenhum tópico encontrado. Seja o primeiro a <a href="<?= $baseUrl ?>/create" class="text-indigo-600 hover:underline">criar uma pergunta</a>.
                </div>
            <?php else: ?>
                <?php foreach ($topics as $t): ?>
                    <a href="<?= $baseUrl ?>/<?= (int)$t['id'] ?>" class="block bg-white rounded-xl shadow border border-gray-200 hover:border-indigo-300 hover:shadow-md transition-all p-4">
                        <div class="flex flex-wrap items-start justify-between gap-2">
                            <div class="flex-1 min-w-0">
                                <h2 class="text-lg font-semibold text-gray-900 truncate"><?= htmlspecialchars($t['title']) ?></h2>
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

        <?php if ($total > $per_page): ?>
            <div class="mt-6 flex justify-center gap-2">
                <?php $totalPages = (int) ceil($total / $per_page); ?>
                <?php
                $baseQuery = 'q=' . urlencode($filters['q'] ?? '') . '&resolved=' . urlencode($filters['is_resolved'] ?? '') . '&order=' . urlencode($filters['order'] ?? '');
                $turmaPart = '';
                if (!empty($filters['turma_ids']) && is_array($filters['turma_ids'])) {
                    foreach ($filters['turma_ids'] as $tid) {
                        $turmaPart .= '&turma_ids[]=' . (int)$tid;
                    }
                }
                $prevUrl = $baseUrl . '?page=' . ($page - 1) . '&' . $baseQuery . $turmaPart;
                $nextUrl = $baseUrl . '?page=' . ($page + 1) . '&' . $baseQuery . $turmaPart;
                ?>
                <?php if ($page > 1): ?>
                <a href="<?= $prevUrl ?>" class="px-4 py-2.5 border border-indigo-300 bg-white text-indigo-700 hover:bg-indigo-50 rounded-lg font-medium transition-colors">Anterior</a>
                <?php endif; ?>
                <span class="px-4 py-2 text-gray-600">Página <?= $page ?> de <?= $totalPages ?></span>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $nextUrl ?>" class="px-4 py-2.5 border border-indigo-300 bg-white text-indigo-700 hover:bg-indigo-50 rounded-lg font-medium transition-colors">Próxima</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</div>
