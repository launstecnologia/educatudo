<?php
$edicao = is_array($edicao ?? null) ? $edicao : null;
$eid = (int) ($edicao['id'] ?? 0);
$entidade = (string) ($entidade ?? 'alunos');
$linhas = is_array($linhas ?? null) ? $linhas : [];
$filtros = is_array($filtros ?? null) ? $filtros : [];
$editavel = !empty($editavel);
$labels = [
    'escola' => 'Escola',
    'gestores' => 'Gestores',
    'turmas' => 'Turmas',
    'alunos' => 'Alunos',
    'profissionais' => 'Profissionais',
    'matriculas' => 'Matrículas',
    'vinculos' => 'Vínculos profissionais',
];
$formMap = [
    'escola' => 'escola',
    'gestores' => 'gestor',
    'turmas' => 'turma',
    'alunos' => 'aluno',
    'profissionais' => 'profissional',
    'matriculas' => 'matricula',
];
$page_header_title = $labels[$entidade] ?? 'Censo Escolar';
$page_header_subtitle = 'Completude e pendências da categoria na edição atual.';
include dirname(__DIR__, 4) . '/Views/admin/_partials/page_header_list.php';
include dirname(__DIR__, 4) . '/Views/admin/_partials/flash_message.php';
include __DIR__ . '/_contexto.php';
$nav_atual = $entidade;
include __DIR__ . '/_nav.php';
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$statusBadge = static function (string $s): string {
    $map = [
        'pronto' => 'bg-green-100 text-green-800',
        'com_erro' => 'bg-red-100 text-red-800',
        'com_alerta' => 'bg-amber-100 text-amber-800',
        'incompleto' => 'bg-amber-100 text-amber-800',
        'pendente' => 'bg-gray-100 text-gray-700',
        'conferido' => 'bg-blue-100 text-blue-800',
    ];
    return $map[$s] ?? 'bg-gray-100 text-gray-700';
};
?>
<form method="GET" action="<?= URL ?>/admin/censo/<?= $eid ?>/<?= $esc($entidade) ?>" class="bg-white rounded-xl border border-gray-200 p-4 mb-4 flex flex-wrap gap-3 items-end">
    <div class="flex-1 min-w-[200px]">
        <label class="block text-xs font-medium text-gray-500 mb-1">Busca</label>
        <input type="text" name="q" value="<?= $esc($filtros['q'] ?? '') ?>" placeholder="Nome, CPF, código ou turma"
               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
    </div>
    <div>
        <label class="block text-xs font-medium text-gray-500 mb-1">Situação</label>
        <select name="status" class="px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white">
            <?php foreach (['todos' => 'Todos', 'pendentes' => 'Somente pendentes', 'pronto' => 'Prontos', 'com_erro' => 'Com erro', 'com_alerta' => 'Com alerta', 'incompleto' => 'Incompletos'] as $k => $lb): ?>
                <option value="<?= $esc($k) ?>" <?= ($filtros['status'] ?? '') === $k ? 'selected' : '' ?>><?= $esc($lb) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <button class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Filtrar</button>
</form>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome / registro</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Código INEP</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Vínculo</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Situação</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                <?php if ($linhas === []): ?>
                <tr><td colspan="5" class="px-4 py-10 text-center text-gray-400">Nenhum registro. Sincronize os cadastros na visão geral.</td></tr>
                <?php endif; ?>
                <?php foreach ($linhas as $row):
                    $formEnt = $formMap[$entidade] ?? '';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3 font-medium text-gray-800"><?= $esc($row['nome'] ?? '—') ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= $esc($row['codigo_inep'] ?? '') ?: '<span class="text-gray-300">—</span>' ?></td>
                    <td class="px-4 py-3 text-gray-600"><?= $esc($row['turma_nome'] ?? ($row['cargo_codigo'] ?? '')) ?: '—' ?></td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $statusBadge((string) ($row['status_validacao'] ?? '')) ?>">
                            <?= $esc($row['status_validacao'] ?? 'pendente') ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <?php ob_start(); ?>
                        <?php if ($formEnt !== ''): ?>
                        <a href="<?= URL ?>/admin/censo/<?= $eid ?>/<?= $esc($formEnt) ?>/<?= (int) $row['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Complemento do Censo
                        </a>
                        <?php endif; ?>
                        <?php if ($entidade === 'profissionais' && !empty($row['professor_id'])): ?>
                        <a href="<?= URL ?>/admin/teachers" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-chalkboard-user text-gray-400 w-4 text-center"></i> Cadastro de professores
                        </a>
                        <?php endif; ?>
                        <?php if ($entidade === 'alunos' && !empty($row['aluno_id'])): ?>
                        <a href="<?= URL ?>/admin/students/<?= (int) $row['aluno_id'] ?>/edit" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-user text-gray-400 w-4 text-center"></i> Cadastro do aluno
                        </a>
                        <?php endif; ?>
                        <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                        <?php $row_actions_dropdown_id = 'censo-row-' . $entidade . '-' . (int) $row['id']; ?>
                        <?php include dirname(__DIR__, 4) . '/Views/admin/_partials/row_actions_dropdown.php'; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
