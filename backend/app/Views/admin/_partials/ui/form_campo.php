<?php
/**
 * Campo de formulário admin (label + input/select/textarea).
 *
 * Variáveis:
 * - $ui_form_campo_label (string)
 * - $ui_form_campo_name (string)
 * - $ui_form_campo_tipo (string): text|email|password|number|date|tel|select|textarea (default text)
 * - $ui_form_campo_value (string, opcional)
 * - $ui_form_campo_placeholder (string, opcional)
 * - $ui_form_campo_obrigatorio (bool)
 * - $ui_form_campo_ajuda (string, opcional)
 * - $ui_form_campo_opcoes (array, para select) — [['value'=>'', 'label'=>''], ...] ou value=>label
 * - $ui_form_campo_id (string, opcional — default = name)
 * - $ui_form_campo_disabled (bool)
 * - $ui_form_campo_rows (int, textarea)
 * - $ui_form_campo_class (string, wrapper)
 * - $ui_form_campo_span (string): full|half (default half — útil em grid)
 * - $ui_form_campo_mb (string, opcional): classe de margem inferior (default mb-6; use '' em grids com gap)
 */
$ui_form_campo_label = (string) ($ui_form_campo_label ?? '');
$ui_form_campo_name = (string) ($ui_form_campo_name ?? '');
$ui_form_campo_tipo = (string) ($ui_form_campo_tipo ?? 'text');
$ui_form_campo_value = (string) ($ui_form_campo_value ?? '');
$ui_form_campo_placeholder = (string) ($ui_form_campo_placeholder ?? '');
$ui_form_campo_obrigatorio = (bool) ($ui_form_campo_obrigatorio ?? false);
$ui_form_campo_ajuda = (string) ($ui_form_campo_ajuda ?? '');
$ui_form_campo_opcoes = $ui_form_campo_opcoes ?? [];
$ui_form_campo_id = (string) ($ui_form_campo_id ?? $ui_form_campo_name);
$ui_form_campo_disabled = (bool) ($ui_form_campo_disabled ?? false);
$ui_form_campo_rows = (int) ($ui_form_campo_rows ?? 4);
$ui_form_campo_class = (string) ($ui_form_campo_class ?? '');
$ui_form_campo_span = (string) ($ui_form_campo_span ?? 'half');
$ui_form_campo_mb = (string) ($ui_form_campo_mb ?? 'mb-6');

$ui_form_campo_input_class = 'w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 focus:border-transparent';
$ui_form_campo_span_class = $ui_form_campo_span === 'full' ? 'md:col-span-2' : '';
$ui_form_campo_req = $ui_form_campo_obrigatorio ? ' required' : '';
$ui_form_campo_dis = $ui_form_campo_disabled ? ' disabled' : '';
?>
<div class="<?= htmlspecialchars(trim($ui_form_campo_mb . ' ' . $ui_form_campo_span_class . ' ' . $ui_form_campo_class)) ?>">
    <label for="<?= htmlspecialchars($ui_form_campo_id) ?>" class="block text-sm font-medium text-gray-700 mb-1">
        <?= htmlspecialchars($ui_form_campo_label) ?><?= $ui_form_campo_obrigatorio ? ' *' : '' ?>
    </label>
    <?php if ($ui_form_campo_tipo === 'textarea'): ?>
        <textarea
            id="<?= htmlspecialchars($ui_form_campo_id) ?>"
            name="<?= htmlspecialchars($ui_form_campo_name) ?>"
            rows="<?= max(2, $ui_form_campo_rows) ?>"
            placeholder="<?= htmlspecialchars($ui_form_campo_placeholder) ?>"
            class="<?= htmlspecialchars($ui_form_campo_input_class) ?>"<?= $ui_form_campo_req ?><?= $ui_form_campo_dis ?>
        ><?= htmlspecialchars($ui_form_campo_value) ?></textarea>
    <?php elseif ($ui_form_campo_tipo === 'select'): ?>
        <select
            id="<?= htmlspecialchars($ui_form_campo_id) ?>"
            name="<?= htmlspecialchars($ui_form_campo_name) ?>"
            class="<?= htmlspecialchars($ui_form_campo_input_class) ?> bg-white"<?= $ui_form_campo_req ?><?= $ui_form_campo_dis ?>
        >
            <?php foreach ((array) $ui_form_campo_opcoes as $ui_form_campo_opt_key => $ui_form_campo_opt): ?>
                <?php
                if (is_array($ui_form_campo_opt)) {
                    $ui_form_campo_opt_value = (string) ($ui_form_campo_opt['value'] ?? '');
                    $ui_form_campo_opt_label = (string) ($ui_form_campo_opt['label'] ?? $ui_form_campo_opt_value);
                } else {
                    $ui_form_campo_opt_value = (string) $ui_form_campo_opt_key;
                    $ui_form_campo_opt_label = (string) $ui_form_campo_opt;
                }
                $ui_form_campo_selected = ((string) $ui_form_campo_value === $ui_form_campo_opt_value) ? ' selected' : '';
                ?>
                <option value="<?= htmlspecialchars($ui_form_campo_opt_value) ?>"<?= $ui_form_campo_selected ?>><?= htmlspecialchars($ui_form_campo_opt_label) ?></option>
            <?php endforeach; ?>
        </select>
    <?php else: ?>
        <input
            type="<?= htmlspecialchars($ui_form_campo_tipo) ?>"
            id="<?= htmlspecialchars($ui_form_campo_id) ?>"
            name="<?= htmlspecialchars($ui_form_campo_name) ?>"
            value="<?= htmlspecialchars($ui_form_campo_value) ?>"
            placeholder="<?= htmlspecialchars($ui_form_campo_placeholder) ?>"
            class="<?= htmlspecialchars($ui_form_campo_input_class) ?>"<?= $ui_form_campo_req ?><?= $ui_form_campo_dis ?>
        >
    <?php endif; ?>
    <?php if ($ui_form_campo_ajuda !== ''): ?>
    <p class="text-xs text-gray-500 mt-1"><?= htmlspecialchars($ui_form_campo_ajuda) ?></p>
    <?php endif; ?>
</div>
<?php
unset(
    $ui_form_campo_label, $ui_form_campo_name, $ui_form_campo_tipo, $ui_form_campo_value,
    $ui_form_campo_placeholder, $ui_form_campo_obrigatorio, $ui_form_campo_ajuda,
    $ui_form_campo_opcoes, $ui_form_campo_id, $ui_form_campo_disabled, $ui_form_campo_rows,
    $ui_form_campo_class, $ui_form_campo_span, $ui_form_campo_mb,
    $ui_form_campo_input_class, $ui_form_campo_span_class, $ui_form_campo_req, $ui_form_campo_dis,
    $ui_form_campo_opt_key, $ui_form_campo_opt, $ui_form_campo_opt_value,
    $ui_form_campo_opt_label, $ui_form_campo_selected
);
?>
