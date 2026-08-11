<!-- Header Section -->
<div class="mb-8">
    <div class="flex items-center mb-4">
        <a href="<?= URL ?>/admin/students" class="text-gray-500 hover:text-gray-700 mr-4">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Movimentação de alunos</h2>
            <p class="text-gray-600">Troca de turma, rematrícula em lote ou saída da escola (TR).</p>
        </div>
    </div>
    <div class="flex flex-wrap gap-2 text-sm mb-3">
        <a href="<?= URL ?>/admin/students/remanejamento" class="px-3 py-1 rounded-full <?= str_contains($form_action, 'remanejamento') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Remanejamento</a>
        <a href="<?= URL ?>/admin/students/transferencia-escolar" class="px-3 py-1 rounded-full <?= str_contains($form_action, 'transferencia-escolar') ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">Saída da escola (TR)</a>
    </div>
    <?php if (str_contains($form_action, 'remanejamento')): ?>
    <p class="text-sm text-blue-800 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2 inline-block">
        Aluno continua na escola; troca turma principal, matrícula e nº de chamada. Use também para rematrícula em lote no início do ano.
    </p>
    <?php else: ?>
    <p class="text-sm text-red-800 bg-red-50 border border-red-100 rounded-lg px-3 py-2 inline-block">
        Aluno deixa a escola; inativa cadastro, encerra matrículas e marca TR na lista de chamada.
    </p>
    <?php endif; ?>
    <p class="mt-3 text-sm text-gray-500">
        Após movimentar, confira vínculos em
        <a href="<?= URL ?>/admin/saude-academica" class="text-blue-600 hover:text-blue-800 font-medium underline">Saúde Acadêmica</a>.
    </p>
</div>

<?php if (!empty($flash_message)): ?>
    <div class="mb-6 p-4 rounded-lg <?= ($flash_type ?? '') === 'success' ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
        <?= htmlspecialchars($flash_message) ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 mb-6">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($page_title ?? 'Turma de origem') ?></h3>
        <?php if (!empty($page_subtitle)): ?>
        <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($page_subtitle) ?></p>
        <?php endif; ?>
    </div>
    <form method="GET" action="<?= htmlspecialchars(parse_url($form_action, PHP_URL_PATH) ?: $form_action) ?>" class="p-6 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="md:col-span-2">
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">Selecione a turma</label>
            <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Selecione uma turma</option>
                <?php foreach ($turmas as $turma): ?>
                    <option value="<?= $turma['id'] ?>" <?= (string)$turma_id === (string)$turma['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($turma['nome']) ?><?= !empty($turma['serie']) ? ' - ' . htmlspecialchars($turma['serie']) : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex items-end">
            <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Carregar alunos</button>
        </div>
    </form>
</div>

<?php if (!empty($turma_id)): ?>
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200 flex justify-between items-center">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Alunos ativos da turma</h3>
                <p class="text-sm text-gray-500">Selecionados: <span id="selected-count" class="font-semibold">0</span></p>
            </div>
            <label class="inline-flex items-center text-sm">
                <input id="select-all" type="checkbox" class="rounded border-gray-300 text-green-600">
                <span class="ml-2">Selecionar todos</span>
            </label>
        </div>

        <form method="POST" action="<?= htmlspecialchars($form_action) ?>" class="p-6 space-y-6" id="movimentacao-form">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
            <input type="hidden" name="turma_origem" value="<?= htmlspecialchars($turma_id) ?>">

            <?php if (!empty($show_destino)): ?>
            <div>
                <label for="turma_destino" class="block text-sm font-medium text-gray-700 mb-2">Turma de destino</label>
                <select id="turma_destino" name="turma_destino" required class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">Selecione a turma de destino</option>
                    <?php foreach ($turmas as $turma): ?>
                        <?php if ((string)$turma['id'] === (string)$turma_id) continue; ?>
                        <option value="<?= $turma['id'] ?>"><?= htmlspecialchars($turma['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>

            <?php if (!empty($show_tr_fields)): ?>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 p-4 bg-red-50 border border-red-100 rounded-lg">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Observação (obrigatória)</label>
                    <textarea name="observation" rows="2" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="Ex.: Transferência para outra escola — documentação recebida"></textarea>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Sua senha (reautenticação)</label>
                    <input type="password" name="password" required class="w-full border border-gray-300 rounded-lg px-3 py-2" autocomplete="current-password">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Digite CONFIRMAR</label>
                    <input type="text" name="confirm_text" required class="w-full border border-gray-300 rounded-lg px-3 py-2" placeholder="CONFIRMAR">
                    <input type="hidden" name="confirm" value="1">
                </div>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center text-sm">
                        <input type="checkbox" name="remover_turma" value="1" checked class="rounded border-gray-300 text-red-600">
                        <span class="ml-2">Remover turma do cadastro após TR</span>
                    </label>
                </div>
            </div>
            <?php endif; ?>

            <div class="overflow-x-auto border border-gray-200 rounded-lg">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Sel.</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">RA</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php if (empty($alunos)): ?>
                            <tr><td colspan="3" class="px-6 py-10 text-center text-gray-500">Nenhum aluno ativo nesta turma.</td></tr>
                        <?php else: ?>
                            <?php foreach ($alunos as $aluno): ?>
                                <tr>
                                    <td class="px-4 py-3"><input type="checkbox" name="student_ids[]" value="<?= $aluno['id'] ?>" class="student-checkbox rounded border-gray-300 text-green-600"></td>
                                    <td class="px-4 py-3 text-sm"><?= htmlspecialchars($aluno['nome'] ?? '') ?></td>
                                    <td class="px-4 py-3 text-sm text-gray-600"><?= htmlspecialchars($aluno['ra'] ?? '') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="flex justify-end">
                <button id="submit-button" type="submit" disabled class="btn-primary-custom px-6 py-2 rounded-lg disabled:opacity-50 hover:opacity-90">
                    <?= htmlspecialchars($submit_label ?? 'Confirmar') ?>
                </button>
            </div>
        </form>
    </div>

    <script>
    (function() {
        const selectAll = document.getElementById('select-all');
        const boxes = Array.from(document.querySelectorAll('.student-checkbox'));
        const countEl = document.getElementById('selected-count');
        const submitBtn = document.getElementById('submit-button');
        const destino = document.getElementById('turma_destino');

        function update() {
            const n = boxes.filter(b => b.checked).length;
            countEl.textContent = n;
            let ok = n > 0;
            if (destino) ok = ok && !!destino.value;
            submitBtn.disabled = !ok;
            if (selectAll) selectAll.checked = n > 0 && n === boxes.length;
        }

        if (selectAll) selectAll.addEventListener('change', () => { boxes.forEach(b => b.checked = selectAll.checked); update(); });
        boxes.forEach(b => b.addEventListener('change', update));
        if (destino) destino.addEventListener('change', update);
        update();
    })();
    </script>
<?php endif; ?>
