<?php
$schemaPronto = !empty($schema_pronto);
$habilitada = !empty($habilitada);
$podeAlterar = !empty($pode_alterar);
$turmas = is_array($turmas ?? null) ? $turmas : [];
$alunos = is_array($alunos ?? null) ? $alunos : [];
$csrf = (string) ($csrf_token ?? '');
$turmaId = (int) ($turma_id ?? 0);
$busca = (string) ($busca ?? '');
$filtrar = !empty($filtrar);
$pagina = (int) ($pagina ?? 1);
$totalPaginas = (int) ($total_paginas ?? 1);
$total = (int) ($total ?? 0);
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rotulos = [
    'valida' => 'Válida',
    'vencida' => 'Vencida',
    'inativa' => 'Aluno inativo',
    'sem_emissao' => 'Sem emissão',
];
$cores = [
    'valida' => 'bg-emerald-50 text-emerald-800',
    'vencida' => 'bg-amber-50 text-amber-800',
    'inativa' => 'bg-amber-50 text-amber-800',
    'sem_emissao' => 'bg-gray-100 text-gray-600',
];
$queryBase = [];
if ($turmaId > 0) {
    $queryBase['turma_id'] = $turmaId;
}
if ($busca !== '') {
    $queryBase['busca'] = $busca;
}
$page_header_title = 'Carteirinha';
$page_header_subtitle = 'A escola decide se usa. Ligada, a secretaria emite e o QR confirma na hora.';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
?>

<?php if (!empty($flash_message)): ?>
    <div class="mb-6 p-4 rounded-lg border <?= ($flash_type ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
        <?= $e($flash_message) ?>
    </div>
<?php endif; ?>

<?php if (!$schemaPronto): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 rounded-xl p-6">
        Rode a migration <code class="text-sm">2026_11_03_carteirinha.sql</code> no painel Master.
    </div>
<?php else: ?>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6 w-full">
    <form method="POST" action="<?= URL ?>/admin/carteirinha/uso" class="flex flex-wrap items-center justify-between gap-4">
        <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
        <input type="hidden" name="habilitada" value="0">
        <div>
            <p class="text-sm font-semibold text-gray-900">Usar carteirinha nesta escola</p>
            <p class="text-sm text-gray-500 mt-1">
                <?= $habilitada
                    ? 'Ligada. O aluno vê o cartão e o QR pode ser conferido sem login.'
                    : 'Desligada. O menu do aluno some e a página pública avisa que a escola não usa.' ?>
            </p>
        </div>
        <?php if ($podeAlterar): ?>
        <label class="inline-flex items-center gap-3 text-sm font-medium text-gray-800">
            <input type="checkbox" name="habilitada" value="1" class="rounded border-gray-300" <?= $habilitada ? 'checked' : '' ?>
                <?= $habilitada ? 'onchange="if(!this.checked){this.form.dataset.confirmar=\'1\'}"' : '' ?>>
            <?= $habilitada ? 'Habilitada' : 'Desabilitada' ?>
        </label>
        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold"
            onclick="if (this.form.dataset.confirmar==='1' && !this.form.querySelector('[name=habilitada][type=checkbox]').checked) { return confirm('Desabilitar a carteirinha? Os QR deixam de valer até você ligar de novo.'); } return true;">
            Salvar
        </button>
        <?php else: ?>
        <span class="text-sm font-semibold <?= $habilitada ? 'text-emerald-700' : 'text-gray-500' ?>"><?= $habilitada ? 'Habilitada' : 'Desabilitada' ?></span>
        <?php endif; ?>
    </form>
</div>

<?php if ($habilitada): ?>
<form method="GET" action="<?= URL ?>/admin/carteirinha" class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6 w-full">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
        <div>
            <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-2">Turma</label>
            <select id="turma_id" name="turma_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-white">
                <option value="0">Todas</option>
                <?php foreach ($turmas as $turma): ?>
                    <option value="<?= (int) $turma['id'] ?>" <?= (int) $turma['id'] === $turmaId ? 'selected' : '' ?>>
                        <?= $e(trim((string) $turma['nome'] . ' · ' . (string) ($turma['ano_letivo'] ?? ''))) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="busca" class="block text-sm font-medium text-gray-700 mb-2">Aluno</label>
            <input id="busca" name="busca" value="<?= $e($busca) ?>" placeholder="Nome ou RA" class="w-full px-4 py-2 border border-gray-300 rounded-lg">
        </div>
        <div class="flex gap-3">
            <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Filtrar</button>
            <a href="<?= URL ?>/admin/carteirinha" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700">Limpar</a>
        </div>
    </div>
</form>

<?php if ($podeAlterar && $turmaId > 0): ?>
<form method="POST" action="<?= URL ?>/admin/carteirinha/emitir-turma" class="mb-6 flex flex-wrap items-center gap-3">
    <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
    <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
    <input type="hidden" name="busca" value="<?= $e($busca) ?>">
    <button type="submit" class="px-4 py-2 rounded-lg border border-teal-600 text-teal-800 text-sm font-semibold hover:bg-teal-50">Emitir quem ainda não tem</button>
    <a href="<?= URL ?>/admin/carteirinha/imprimir?turma_id=<?= $turmaId ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-sm text-gray-700">Imprimir a turma</a>
</form>
<?php endif; ?>

<?php if (!$filtrar): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-8 text-center text-gray-500">
        Escolha uma turma ou busque um aluno para emitir.
    </div>
<?php else: ?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Validade</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <?php if ($alunos === []): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-gray-500">Nenhum aluno encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($alunos as $aluno): ?>
                <?php $situacao = (string) ($aluno['situacao'] ?? 'sem_emissao'); ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-slate-100 rounded-full overflow-hidden flex items-center justify-center flex-shrink-0">
                                <?php if (($aluno['foto'] ?? '') !== ''): ?>
                                    <img src="<?= $e($aluno['foto']) ?>" alt="" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span class="text-sm font-medium text-slate-600"><?= $e(mb_strtoupper(mb_substr((string) $aluno['nome'], 0, 1))) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4 min-w-0">
                                <div class="text-sm font-medium text-gray-900 truncate"><?= $e($aluno['nome']) ?></div>
                                <div class="text-xs text-gray-500">RA <?= $e($aluno['ra'] !== '' ? $aluno['ra'] : '—') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= $e($aluno['turma'] !== '' ? $aluno['turma'] : '—') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 rounded-full text-xs font-semibold <?= $e($cores[$situacao] ?? $cores['sem_emissao']) ?>">
                            <?= $e($rotulos[$situacao] ?? 'Sem emissão') ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= $e($aluno['validade'] !== '' ? $aluno['validade'] : '—') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                            <?php if ($podeAlterar && $situacao !== 'valida'): ?>
                            <form method="POST" action="<?= URL ?>/admin/carteirinha/emitir">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="aluno_id" value="<?= (int) $aluno['id'] ?>">
                                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                                <input type="hidden" name="busca" value="<?= $e($busca) ?>">
                                <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-id-card text-gray-400 w-4 text-center"></i> Emitir
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($podeAlterar && $situacao === 'valida'): ?>
                            <form method="POST" action="<?= URL ?>/admin/carteirinha/emitir">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="aluno_id" value="<?= (int) $aluno['id'] ?>">
                                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                                <input type="hidden" name="busca" value="<?= $e($busca) ?>">
                                <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-rotate text-gray-400 w-4 text-center"></i> Reemitir
                                </button>
                            </form>
                            <form method="POST" action="<?= URL ?>/admin/carteirinha/cancelar" onsubmit="return confirm('Cancelar esta carteirinha? O QR deixa de valer.');">
                                <input type="hidden" name="csrf_token" value="<?= $e($csrf) ?>">
                                <input type="hidden" name="id" value="<?= (int) $aluno['carteirinha_id'] ?>">
                                <input type="hidden" name="turma_id" value="<?= $turmaId ?>">
                                <input type="hidden" name="busca" value="<?= $e($busca) ?>">
                                <input type="hidden" name="pagina" value="<?= $pagina ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-ban text-red-400 w-4 text-center"></i> Cancelar
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ((int) $aluno['carteirinha_id'] > 0): ?>
                            <a href="<?= URL ?>/admin/carteirinha/imprimir?aluno_id=<?= (int) $aluno['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-print text-gray-400 w-4 text-center"></i> Imprimir
                            </a>
                            <?php endif; ?>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-carteirinha-' . (int) $aluno['id'];
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php if ($totalPaginas > 1): ?>
    <div class="mt-4 flex items-center justify-between text-sm text-gray-600">
        <span><?= $total ?> aluno(s)</span>
        <div class="flex gap-2">
            <?php if ($pagina > 1): ?>
                <a class="px-3 py-1 rounded border border-gray-300" href="<?= URL ?>/admin/carteirinha?<?= $e(http_build_query($queryBase + ['pagina' => $pagina - 1])) ?>">Anterior</a>
            <?php endif; ?>
            <span>Página <?= $pagina ?> de <?= $totalPaginas ?></span>
            <?php if ($pagina < $totalPaginas): ?>
                <a class="px-3 py-1 rounded border border-gray-300" href="<?= URL ?>/admin/carteirinha?<?= $e(http_build_query($queryBase + ['pagina' => $pagina + 1])) ?>">Próxima</a>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
<?php endif; ?>
