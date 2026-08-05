<?php
/**
 * Rodapé de ações de formulário admin.
 *
 * Variáveis:
 * - $form_cancel_url (string)
 * - $form_submit_label (string)
 * - $form_cancel_label (string, opcional, default Cancelar)
 */
$form_cancel_url = (string) ($form_cancel_url ?? '');
$form_submit_label = (string) ($form_submit_label ?? 'Salvar');
$form_cancel_label = (string) ($form_cancel_label ?? 'Cancelar');
?>
<div class="p-6 bg-gray-50/50 border-t border-gray-200">
    <div class="flex justify-end gap-3 flex-wrap">
        <a href="<?= htmlspecialchars($form_cancel_url) ?>"
           class="px-6 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
            <?= htmlspecialchars($form_cancel_label) ?>
        </a>
        <button type="submit"
                class="px-6 py-2 bg-primary text-primary rounded-lg font-medium hover:opacity-90 transition-colors shadow-sm">
            <?= htmlspecialchars($form_submit_label) ?>
        </button>
    </div>
</div>
