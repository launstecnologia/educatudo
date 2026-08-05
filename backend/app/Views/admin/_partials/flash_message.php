<?php
/**
 * Mensagem flash inline (sucesso/erro).
 *
 * Variáveis: $flash_status ('success'|'error'), $flash_message (string)
 */
$flash_status = (string) ($flash_status ?? '');
$flash_message = (string) ($flash_message ?? '');
if ($flash_status === '' || $flash_message === '') {
    return;
}
$flash_class = $flash_status === 'success'
    ? 'bg-green-100 text-green-800 border border-green-200'
    : 'bg-red-100 text-red-800 border border-red-200';
?>
<div class="mb-6 p-4 rounded-lg <?= $flash_class ?>">
    <?= htmlspecialchars($flash_message) ?>
</div>
