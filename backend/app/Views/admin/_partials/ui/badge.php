<?php
/**
 * Badge de status (prompts/admin_ui_sistema/tabela-dropdown-paginacao.md §5).
 *
 * Variáveis:
 * - $ui_badge_variant (string): ativo|inativo|pendente|neutro|info|rascunho|erro|transferido
 * - $ui_badge_label (string)
 * - $ui_badge_class (string, opcional)
 */
$ui_badge_variant = (string) ($ui_badge_variant ?? 'neutro');
$ui_badge_label = (string) ($ui_badge_label ?? '');
$ui_badge_class = (string) ($ui_badge_class ?? '');

$ui_badge_variants_map = [
    'ativo' => 'bg-green-100 text-green-800',
    'inativo' => 'bg-red-100 text-red-800',
    'erro' => 'bg-red-100 text-red-800',
    'pendente' => 'bg-amber-100 text-amber-800',
    'rascunho' => 'bg-amber-100 text-amber-800',
    'neutro' => 'bg-slate-100 text-slate-700',
    'info' => 'bg-blue-100 text-blue-800',
    'transferido' => 'bg-gray-100 text-gray-600',
];

$ui_badge_color = $ui_badge_variants_map[$ui_badge_variant] ?? $ui_badge_variants_map['neutro'];
?>
<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= htmlspecialchars($ui_badge_color) ?> <?= htmlspecialchars($ui_badge_class) ?>">
    <?= htmlspecialchars($ui_badge_label) ?>
</span>
<?php
unset($ui_badge_variant, $ui_badge_label, $ui_badge_class, $ui_badge_variants_map, $ui_badge_color);
?>
