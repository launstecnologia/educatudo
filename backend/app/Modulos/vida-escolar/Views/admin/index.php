<?php
$page_header_title = 'Vida Escolar';
$page_header_subtitle = 'Prontuário por aluno: boletim oficial, trajetória, documentos e conferência SED/INEP. Escolha a turma ou abra o aluno.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
?>

<?php if (!empty($flash_message)): ?>
<div class="mb-4 rounded-lg px-4 py-3 text-sm <?= ($flash_status ?? '') === 'success' ? 'bg-emerald-50 text-emerald-800' : 'bg-red-50 text-red-800' ?>">
    <?= $esc($flash_message) ?>
</div>
<?php endif; ?>

<?php if (empty($schema_pronto)): ?>
<div class="bg-amber-50 border border-amber-200 rounded-xl p-6 text-amber-900 text-sm">
    Aplique a migration <code>2026_08_25_vida_escolar</code> no painel Master para ativar as fichas oficiais.
</div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <form method="get" action="<?= URL ?>/admin/vida-escolar" class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Ano letivo</label>
            <select name="ano_letivo" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()">
                <?php foreach ($anos as $a): ?>
                    <option value="<?= (int) $a ?>" <?= (int) $ano_letivo === (int) $a ? 'selected' : '' ?>><?= (int) $a ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
            <select name="turma_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white" onchange="this.form.submit()">
                <option value="0">Selecione a turma</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (int) $turma_id === (int) $t['id'] ? 'selected' : '' ?>><?= $esc($t['nome'] ?? '') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200 text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Aluno</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Versão</th>
                <th class="px-4 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php if ($turma_id <= 0): ?>
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Escolha uma turma para ver as fichas.</td></tr>
            <?php elseif ($fichas === []): ?>
            <tr><td colspan="4" class="px-4 py-8 text-center text-gray-500">Nenhuma ficha ainda. Ao vincular o aluno à turma a ficha é criada automaticamente (após a migration). Você também pode abrir o aluno → Vida escolar (prontuário).</td></tr>
            <?php else: ?>
                <?php foreach ($fichas as $f): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-900"><?= $esc($f['aluno_nome'] ?? '') ?></td>
                    <td class="px-4 py-3">
                        <?php
                        $st = (string) ($f['status'] ?? '');
                        $cls = $st === 'homologada' ? 'bg-emerald-100 text-emerald-800' : ($st === 'fechada' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-700');
                        $stLabel = $st === 'homologada' ? 'Homologada' : ($st === 'fechada' ? 'Fechada' : 'Em curso');
                        ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $cls ?>"><?= $esc($stLabel) ?></span>
                    </td>
                    <td class="px-4 py-3 text-gray-600">v<?= (int) ($f['versao'] ?? 1) ?></td>
                    <td class="px-4 py-3 text-right">
                        <a class="text-sm text-indigo-600 hover:underline" href="<?= URL ?>/admin/students/<?= (int) $f['aluno_id'] ?>?tab=vida-escolar&amp;ve_aba=boletim&amp;ficha_id=<?= (int) $f['id'] ?>">Abrir na ficha</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
