<?php
/**
 * Título de seção de formulário admin.
 *
 * Variáveis:
 * - $ui_form_secao_titulo (string)
 * - $ui_form_secao_id (string, opcional)
 * - $ui_form_secao_class (string, opcional)
 */
$ui_form_secao_titulo = (string) ($ui_form_secao_titulo ?? '');
$ui_form_secao_id = (string) ($ui_form_secao_id ?? '');
$ui_form_secao_class = (string) ($ui_form_secao_class ?? '');
$ui_form_secao_id_attr = $ui_form_secao_id !== '' ? ' id="' . htmlspecialchars($ui_form_secao_id) . '"' : '';
?>
<h3<?= $ui_form_secao_id_attr ?> class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4 <?= htmlspecialchars($ui_form_secao_class) ?>">
    <?= htmlspecialchars($ui_form_secao_titulo) ?>
</h3>
<?php
unset($ui_form_secao_titulo, $ui_form_secao_id, $ui_form_secao_class, $ui_form_secao_id_attr);
?>
