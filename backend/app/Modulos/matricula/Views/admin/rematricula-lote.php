<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$turma_origem_id = (int)($turma_origem_id ?? 0);
$alunos = $alunos ?? [];
$planos_financeiros = $planos_financeiros ?? [];
$turmas = $turmas ?? [];
$anos_letivos = $anos_letivos ?? [];

$page_header_title    = 'Rematrícula em lote';
$page_header_subtitle = 'Gere processos de rematrícula para vários alunos de uma turma.';
ob_start(); ?>
<a href="<?= URL ?>/admin/enrollment" class="btn-secondary text-sm">← Matrículas</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php'; ?>

<?php if (!empty($status_message)): ?>
<div class="mb-4 p-3 rounded-xl text-sm <?= ($status_type ?? '') === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<!-- Filtro turma de origem (GET) -->
<form method="GET" action="<?= URL ?>/admin/enrollment/rematricula-lote"
      class="bg-white rounded-xl border border-gray-200 p-4 mb-5 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-64">
        <label for="turma_origem_id" class="block text-xs text-gray-500 mb-1">Turma de origem</label>
        <select id="turma_origem_id" name="turma_origem_id"
                class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
            <option value="">Selecionar turma...</option>
            <?php foreach ($turmas as $t): ?>
            <option value="<?= (int)$t['id'] ?>" <?= $turma_origem_id === (int)$t['id'] ? 'selected' : '' ?>>
                <?= $esc($t['nome']) ?> — <?= $esc($t['serie'] ?? '') ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>
    <button type="submit" class="btn-primary text-sm px-4 py-2">Listar alunos</button>
</form>

<?php if ($turma_origem_id <= 0): ?>
<div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-gray-400">
    <i class="fa-solid fa-chalkboard-user text-3xl mb-3 block"></i>
    Selecione a turma de origem para listar os alunos.
</div>
<?php elseif (empty($alunos)): ?>
<div class="bg-white rounded-2xl border border-gray-200 p-10 text-center text-gray-400">
    <i class="fa-regular fa-folder-open text-3xl mb-3 block"></i>
    Nenhum aluno ativo nesta turma.
</div>
<?php else: ?>

<form method="POST" action="<?= URL ?>/admin/enrollment/rematricula-lote" class="space-y-5">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
    <input type="hidden" name="turma_origem_id" value="<?= $turma_origem_id ?>">

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm p-5">
        <h3 class="font-semibold text-gray-800 mb-4">Destino</h3>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div>
                <label for="ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-1">
                    Ano letivo <span class="text-red-500">*</span>
                </label>
                <select id="ano_letivo_id" name="ano_letivo_id" required
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">Selecionar...</option>
                    <?php foreach ($anos_letivos as $al): ?>
                    <option value="<?= (int)$al['id'] ?>" <?= !empty($al['ativo']) ? 'selected' : '' ?>>
                        <?= $esc($al['ano']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="turma_destino_id" class="block text-sm font-medium text-gray-700 mb-1">Turma destino</label>
                <select id="turma_destino_id" name="turma_destino_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">Selecionar...</option>
                    <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int)$t['id'] ?>">
                        <?= $esc($t['nome']) ?> — <?= $esc($t['serie'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="finance_plan_id" class="block text-sm font-medium text-gray-700 mb-1">Plano financeiro</label>
                <select id="finance_plan_id" name="finance_plan_id"
                        class="w-full border border-gray-300 rounded-lg px-3 py-2.5 text-sm">
                    <option value="">Selecionar...</option>
                    <?php foreach ($planos_financeiros as $plano): ?>
                    <option value="<?= (int)$plano['id'] ?>">
                        <?= $esc($plano['nome'] ?? ('Plano #' . $plano['id'])) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
            <h3 class="font-semibold text-gray-800">Alunos (<?= count($alunos) ?>)</h3>
            <label class="text-sm text-gray-600 flex items-center gap-2 cursor-pointer">
                <input type="checkbox" id="selecionar_todos"
                       class="rounded border-gray-300 text-green-600 focus:ring-green-500">
                Selecionar todos
            </label>
        </div>
        <ul class="divide-y divide-gray-100 max-h-96 overflow-y-auto">
            <?php foreach ($alunos as $aluno): ?>
            <li>
                <label class="flex items-center gap-3 px-5 py-3 hover:bg-gray-50 cursor-pointer">
                    <input type="checkbox" name="aluno_ids[]" value="<?= (int)$aluno['id'] ?>"
                           class="aluno-check rounded border-gray-300 text-green-600 focus:ring-green-500">
                    <div>
                        <p class="text-sm font-medium text-gray-800"><?= $esc($aluno['nome'] ?? '') ?></p>
                        <p class="text-xs text-gray-500">
                            <?= !empty($aluno['cpf']) ? 'CPF: ' . $esc($aluno['cpf']) : '' ?>
                            <?= !empty($aluno['email']) ? ' · ' . $esc($aluno['email']) : '' ?>
                        </p>
                    </div>
                </label>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <div class="flex gap-3">
        <button type="submit" class="btn-primary"
                onclick="return document.querySelectorAll('.aluno-check:checked').length > 0 || (alert('Selecione ao menos um aluno.'), false)">
            <i class="fa-solid fa-check mr-1.5"></i> Gerar rematrículas
        </button>
        <a href="<?= URL ?>/admin/enrollment" class="btn-secondary">Cancelar</a>
    </div>
</form>

<script>
(function () {
    var master = document.getElementById('selecionar_todos');
    if (!master) return;
    master.addEventListener('change', function () {
        document.querySelectorAll('.aluno-check').forEach(function (cb) {
            cb.checked = master.checked;
        });
    });
})();
</script>
<?php endif; ?>
