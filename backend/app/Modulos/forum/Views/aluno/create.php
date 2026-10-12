<?php
$error = $_SESSION['forum_error'] ?? '';
if (isset($_SESSION['forum_error'])) unset($_SESSION['forum_error']);
$baseUrl = URL . '/forum';
?>
<div class="mobile-content flex-1 overflow-y-auto w-full min-h-0 p-4 md:p-6">
    <div class="w-full max-w-5xl xl:max-w-6xl mx-auto">
        <div class="mb-6">
            <a href="<?= $baseUrl ?>" class="text-indigo-600 hover:text-indigo-700 hover:underline text-sm font-medium">← Voltar ao fórum</a>
            <h1 class="text-2xl md:text-3xl font-bold text-gray-900 mt-2">Nova pergunta</h1>
            <p class="text-gray-600 mt-1">Descreva sua dúvida com clareza.</p>
        </div>

        <?php if ($error): ?>
            <div class="mb-4 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form action="<?= $baseUrl ?>/store" method="post" class="bg-white rounded-xl shadow border border-gray-200 p-6">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <div class="mb-4">
                <label for="title" class="block text-sm font-medium text-gray-700 mb-1">Título *</label>
                <input type="text" id="title" name="title" required maxlength="255" value="<?= htmlspecialchars($_POST['title'] ?? '') ?>" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Ex: Como resolver equação do 2º grau?">
            </div>
            <div class="mb-4">
                <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Conteúdo *</label>
                <textarea id="content" name="content" required rows="6" class="w-full rounded-lg border border-gray-300 px-3 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Descreva sua pergunta em detalhes..."><?= htmlspecialchars($_POST['content'] ?? '') ?></textarea>
            </div>
            <?php
            $selectedIds = isset($_POST['turma_ids']) && is_array($_POST['turma_ids']) ? array_map('intval', $_POST['turma_ids']) : [];
            $turmasById = [];
            foreach ($turmas as $t) { $turmasById[(int)$t['id']] = $t['nome']; }
            $turmasLabel = empty($selectedIds) ? 'Todas as turmas' : (count($selectedIds) === 1 ? htmlspecialchars($turmasById[$selectedIds[0]] ?? '1 turma') : count($selectedIds) . ' turmas');
            ?>
            <div class="mb-6 relative max-w-md" id="create-turmas-wrap">
                <label class="block text-sm font-medium text-gray-700 mb-1">Turma(s) (opcional)</label>
                <button type="button" id="create-turmas-btn" class="w-full rounded-lg border border-gray-300 px-3 py-2.5 bg-white text-left text-sm flex items-center justify-between gap-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 hover:bg-gray-50 transition-colors" aria-haspopup="listbox" aria-expanded="false">
                    <span id="create-turmas-label" class="truncate"><?= $turmasLabel ?></span>
                    <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                </button>
                <div id="create-turmas-panel" class="hidden absolute top-full left-0 mt-1 w-full min-w-[200px] bg-white border border-gray-200 rounded-lg shadow-lg z-20 py-2 max-h-64 overflow-y-auto">
                    <button type="button" class="create-turmas-todas w-full px-3 py-2 text-left text-sm text-indigo-600 hover:bg-indigo-50 font-medium flex items-center gap-2">
                        <span class="w-4 h-4 rounded border border-gray-300 flex items-center justify-center flex-shrink-0 create-turmas-todas-check"><?= empty($selectedIds) ? '✓' : '' ?></span>
                        Todas as turmas
                    </button>
                    <div class="border-t border-gray-100 mt-1 pt-1">
                        <?php foreach ($turmas as $t): $id = (int)$t['id']; $sel = in_array($id, $selectedIds, true); ?>
                            <label class="flex items-center gap-2 px-3 py-2 hover:bg-gray-50 cursor-pointer text-sm">
                                <input type="checkbox" name="turma_ids[]" value="<?= $id ?>" class="create-turmas-cb rounded border-gray-300 text-indigo-600 focus:ring-indigo-500" <?= $sel ? 'checked' : '' ?>>
                                <span><?= htmlspecialchars($t['nome']) ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <script>
            (function() {
                var btn = document.getElementById('create-turmas-btn');
                var panel = document.getElementById('create-turmas-panel');
                var label = document.getElementById('create-turmas-label');
                var wrap = document.getElementById('create-turmas-wrap');
                var todasBtn = panel && panel.querySelector('.create-turmas-todas');
                var todasCheck = panel && panel.querySelector('.create-turmas-todas-check');
                var checkboxes = panel && panel.querySelectorAll('.create-turmas-cb');
                function updateLabel() {
                    if (!checkboxes || !label) return;
                    var names = [];
                    for (var i = 0; i < checkboxes.length; i++) {
                        if (checkboxes[i].checked) names.push(checkboxes[i].nextElementSibling ? checkboxes[i].nextElementSibling.textContent : '');
                    }
                    label.textContent = names.length === 0 ? 'Todas as turmas' : (names.length === 1 ? names[0] : names.length + ' turmas');
                    if (todasCheck) todasCheck.textContent = names.length === 0 ? '✓' : '';
                }
                function setTodas() {
                    if (!checkboxes) return;
                    for (var i = 0; i < checkboxes.length; i++) checkboxes[i].checked = false;
                    updateLabel();
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
                if (todasBtn) todasBtn.addEventListener('click', function() { setTodas(); });
                if (checkboxes) {
                    for (var i = 0; i < checkboxes.length; i++) {
                        checkboxes[i].addEventListener('change', updateLabel);
                    }
                }
            })();
            </script>
            <div class="flex gap-3">
                <button type="submit" class="px-6 py-2.5 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors shadow-sm">Publicar</button>
                <a href="<?= $baseUrl ?>" class="px-6 py-2.5 border border-indigo-300 bg-white text-indigo-700 hover:bg-indigo-50 rounded-lg font-medium transition-colors">Cancelar</a>
            </div>
        </form>
    </div>
</div>
