<?php
/**
 * Dropdown de ações de linha (listagens admin) — design system em
 * prompts/admin_ui_sistema/tabela-dropdown-paginacao.md, seção 4b.
 *
 * Uso:
 *   <?php ob_start(); ?>
 *       <a href="..." class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
 *           <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
 *       </a>
 *       <button type="button" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
 *           <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
 *       </button>
 *   <?php $row_actions_dropdown_items = ob_get_clean(); ?>
 *   <?php $row_actions_dropdown_id = 'row-actions-' . $row['id']; ?>
 *   <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
 *
 * O JS de toggle/fechar é global, carregado uma vez em layouts/admin.php
 * (delegação por [data-dropdown-toggle] / [data-dropdown-menu]).
 */
$row_actions_dropdown_id = $row_actions_dropdown_id ?? ('row-actions-' . uniqid());
$row_actions_dropdown_items = $row_actions_dropdown_items ?? '';
?>
<div class="relative inline-block text-left" data-dropdown>
    <button type="button" id="<?= htmlspecialchars($row_actions_dropdown_id) ?>" data-dropdown-toggle
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-gray-300 bg-white text-gray-700 text-sm font-medium hover:bg-gray-50 transition-colors">
        Ações
        <i class="fa-solid fa-chevron-down text-xs text-gray-400"></i>
    </button>
    <div data-dropdown-menu class="hidden fixed z-50 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black ring-opacity-5 focus:outline-none overflow-hidden">
        <div class="py-1">
            <?= $row_actions_dropdown_items ?>
        </div>
    </div>
</div>
<?php
unset($row_actions_dropdown_id, $row_actions_dropdown_items);
?>
