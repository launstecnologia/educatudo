<?php
/**
 * Card branco padrão do admin.
 *
 * Variáveis:
 * - $ui_card_variant (string): listagem|form (default listagem)
 * - $ui_card_body (string HTML) — conteúdo interno
 * - $ui_card_class (string, opcional)
 * - $ui_card_id (string, opcional)
 */
$ui_card_variant = (string) ($ui_card_variant ?? 'listagem');
$ui_card_body = (string) ($ui_card_body ?? '');
$ui_card_class = (string) ($ui_card_class ?? '');
$ui_card_id = (string) ($ui_card_id ?? '');

$ui_card_base = $ui_card_variant === 'form'
    ? 'bg-white rounded-xl shadow-lg p-6 w-full'
    : 'bg-white rounded-xl shadow-sm border border-gray-200';

$ui_card_id_attr = $ui_card_id !== '' ? ' id="' . htmlspecialchars($ui_card_id) . '"' : '';
?>
<div<?= $ui_card_id_attr ?> class="<?= htmlspecialchars(trim($ui_card_base . ' ' . $ui_card_class)) ?>">
    <?= $ui_card_body ?>
</div>
<?php
unset($ui_card_variant, $ui_card_body, $ui_card_class, $ui_card_id, $ui_card_base, $ui_card_id_attr);
?>
