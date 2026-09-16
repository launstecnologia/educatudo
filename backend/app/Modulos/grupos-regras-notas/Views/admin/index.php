<?php
$itens = is_array($itens ?? null) ? $itens : [];
$schemaPronto = !empty($schema_pronto);
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Quadro de Notas';
$page_header_subtitle = 'Molde das notas: colunas (S1, AV1…) e, se a escola separar matérias, blocos de disciplinas. As provas são criadas em Lançamento de Notas.';
ob_start();
?>
<a href="<?= URL ?>/admin/grupos-regras-notas/novo"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo quadro
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode as migrations <code class="text-sm">2026_09_01_grupos_regras_notas.sql</code> e <code class="text-sm">2026_09_10_quadros_notas.sql</code> no painel Master antes de cadastrar.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Organização</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Blocos de disciplinas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Colunas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($itens === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-layer-group text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum quadro cadastrado</p>
                        <p class="text-sm mt-1">Comece pelas colunas (S1, AV1…). Blocos de disciplinas são opcionais.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($itens as $row): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($row['nome'] ?? '')) ?></div>
                            <?php if (!empty($row['descricao'])): ?>
                                <div class="text-xs text-gray-500"><?= htmlspecialchars((string) $row['descricao']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= (($row['modo'] ?? '') === 'blocos' || (int) ($row['total_tipos'] ?? 0) > 0) ? 'Colunas + blocos de disciplinas' : 'Só colunas' ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= (int) ($row['total_tipos'] ?? 0) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= (int) ($row['total_marcas'] ?? 0) ?></td>
                        <td class="px-6 py-4">
                            <?php if (!empty($row['ativo'])): ?>
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                            <?php else: ?>
                                <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <?php ob_start(); ?>
                            <a href="<?= URL ?>/admin/grupos-regras-notas/<?= (int) $row['id'] ?>/editar"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </a>
                            <form method="POST" action="<?= URL ?>/admin/grupos-regras-notas/<?= (int) $row['id'] ?>/delete"
                                  onsubmit="return confirm('Excluir este quadro de notas?');">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                </button>
                            </form>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'row-actions-grn-' . (int) $row['id']; ?>
                            <?php include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
