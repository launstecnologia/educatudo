<?php
/**
 * Botão do design system admin (prompts/admin_ui_sistema/cores-e-botoes.md).
 *
 * Variantes:
 * - primary      — CTA (criar/salvar), bg-primary, sempre o último no cabeçalho
 * - complementar — outline cinza + ícone cinza (Filtros, Tipo de Avaliação, Bloco…)
 * - secondary    — alias de complementar
 * - filtro       — complementar + badge de contagem opcional
 * - detalhes|confirm|destrutivo|link
 *
 * Variáveis:
 * - $ui_btn_variant, $ui_btn_label, $ui_btn_icon
 * - $ui_btn_href | $ui_btn_type | $ui_btn_onclick | $ui_btn_id | $ui_btn_attrs
 * - $ui_btn_filter_count (int) — badge (variant filtro)
 * - $ui_btn_class (string, opcional)
 */
$ui_btn_variant = (string) ($ui_btn_variant ?? 'primary');
$ui_btn_label = (string) ($ui_btn_label ?? '');
$ui_btn_icon = (string) ($ui_btn_icon ?? '');
$ui_btn_href = (string) ($ui_btn_href ?? '');
$ui_btn_type = (string) ($ui_btn_type ?? 'button');
$ui_btn_onclick = (string) ($ui_btn_onclick ?? '');
$ui_btn_id = (string) ($ui_btn_id ?? '');
$ui_btn_attrs = (string) ($ui_btn_attrs ?? '');
$ui_btn_filter_count = (int) ($ui_btn_filter_count ?? 0);
$ui_btn_class = (string) ($ui_btn_class ?? '');

// secondary = alias de complementar (mesmo visual outline)
if ($ui_btn_variant === 'secondary') {
    $ui_btn_variant = 'complementar';
}

$ui_btn_outline = 'inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors';
$ui_btn_variants_map = [
    'primary' => 'inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm',
    'complementar' => $ui_btn_outline,
    'filtro' => 'relative ' . $ui_btn_outline,
    'detalhes' => 'inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100 hover:border-blue-300 transition-colors',
    'confirm' => 'inline-flex items-center px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors',
    'destrutivo' => 'inline-flex items-center px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors',
    'link' => 'inline-flex items-center text-gray-600 hover:text-gray-900 text-sm',
];

$ui_btn_is_outline = in_array($ui_btn_variant, ['complementar', 'filtro'], true);
$ui_btn_classes = trim(($ui_btn_variants_map[$ui_btn_variant] ?? $ui_btn_variants_map['primary']) . ' ' . $ui_btn_class);
$ui_btn_tag = $ui_btn_href !== '' ? 'a' : 'button';

$ui_btn_attr_parts = [];
if ($ui_btn_id !== '') {
    $ui_btn_attr_parts[] = 'id="' . htmlspecialchars($ui_btn_id) . '"';
}
if ($ui_btn_tag === 'a') {
    $ui_btn_attr_parts[] = 'href="' . htmlspecialchars($ui_btn_href) . '"';
} else {
    $ui_btn_attr_parts[] = 'type="' . htmlspecialchars($ui_btn_type) . '"';
}
if ($ui_btn_onclick !== '') {
    $ui_btn_attr_parts[] = 'onclick="' . htmlspecialchars($ui_btn_onclick, ENT_QUOTES) . '"';
}
if ($ui_btn_attrs !== '') {
    $ui_btn_attr_parts[] = $ui_btn_attrs;
}
$ui_btn_attr_parts[] = 'class="' . htmlspecialchars($ui_btn_classes) . '"';

$ui_btn_icon_extra = '';
if ($ui_btn_variant === 'detalhes') {
    $ui_btn_icon_extra = '';
} elseif ($ui_btn_is_outline) {
    $ui_btn_icon_extra = 'mr-2 text-gray-500';
} else {
    $ui_btn_icon_extra = 'mr-2';
}
?>
<<?= $ui_btn_tag ?> <?= implode(' ', $ui_btn_attr_parts) ?>>
    <?php if ($ui_btn_icon !== ''): ?>
    <i class="<?= htmlspecialchars(trim($ui_btn_icon . ' ' . $ui_btn_icon_extra)) ?>"></i>
    <?php endif; ?>
    <?= htmlspecialchars($ui_btn_label) ?>
    <?php if ($ui_btn_variant === 'filtro' && $ui_btn_filter_count > 0): ?>
    <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $ui_btn_filter_count ?></span>
    <?php endif; ?>
</<?= $ui_btn_tag ?>>
<?php
unset(
    $ui_btn_variant, $ui_btn_label, $ui_btn_icon, $ui_btn_href, $ui_btn_type,
    $ui_btn_onclick, $ui_btn_id, $ui_btn_attrs, $ui_btn_filter_count, $ui_btn_class,
    $ui_btn_outline, $ui_btn_variants_map, $ui_btn_is_outline, $ui_btn_classes,
    $ui_btn_tag, $ui_btn_attr_parts, $ui_btn_icon_extra
);
?>
