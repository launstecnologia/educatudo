<?php
$disciplina = $disciplina ?? [];
$professores = $professores ?? [];
$turmas = $turmas ?? [];
$semestres = $semestres ?? [];
$status_opcoes = $status_opcoes ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$dId = (int) ($disciplina['id'] ?? 0);
$cursoId = (int) ($disciplina['curso_id'] ?? 0);

$page_header_back_url = URL . '/admin/ava/disciplinas/' . $dId;
$page_header_title = 'Editar disciplina';
$page_header_subtitle = (string) ($disciplina['curso_nome'] ?? '');
include __DIR__ . '/../_partials/page_header_form.php';
include __DIR__ . '/../_partials/flash_message.php';

$form_cancel_url = URL . '/admin/ava/disciplinas/' . $dId;
$form_submit_label = 'Salvar alterações';
?>

<form method="post" action="<?= URL ?>/admin/ava/disciplinas/<?= $dId ?>">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="p-6 space-y-6">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <input type="hidden" name="curso_id" value="<?= $cursoId ?>">

            <div>
                <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome da disciplina <span class="text-red-500">*</span></label>
                <input type="text" id="nome" name="nome" required value="<?= htmlspecialchars((string) ($disciplina['nome'] ?? '')) ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="professor_id" class="block text-sm font-medium text-gray-700 mb-2">Professor</label>
                    <select id="professor_id" name="professor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">—</option>
                        <?php foreach ($professores as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) ($disciplina['professor_id'] ?? 0) === (int) $p['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $p['nome']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="semestre_id" class="block text-sm font-medium text-gray-700 mb-2">Período</label>
                    <select id="semestre_id" name="semestre_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">—</option>
                        <?php foreach ($semestres as $s): ?><option value="<?= (int) $s['id'] ?>" <?= (int) ($disciplina['semestre_id'] ?? 0) === (int) $s['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $s['nome']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">Turma vinculada (ERP)</label>
                    <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">—</option>
                        <?php foreach ($turmas as $t): ?><option value="<?= (int) $t['id'] ?>" <?= (int) ($disciplina['turma_id'] ?? 0) === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $t['nome']) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">Status</label>
                    <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <?php foreach ($status_opcoes as $k => $v): ?><option value="<?= htmlspecialchars($k) ?>" <?= ($disciplina['status'] ?? 'ativo') === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option><?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php include __DIR__ . '/../_partials/form_actions.php'; ?>
    </div>
</form>
