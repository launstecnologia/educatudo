<?php
/**
 * Célula de estado vazio para tabela de listagem.
 *
 * Variáveis:
 * - $ui_tabela_vazia_colspan (int)
 * - $ui_tabela_vazia_icone (string FA classes, default fa-solid fa-inbox)
 * - $ui_tabela_vazia_mensagem (string)
 * - $ui_tabela_vazia_cta_html (string HTML opcional)
 */
$ui_tabela_vazia_colspan = (int) ($ui_tabela_vazia_colspan ?? 1);
$ui_tabela_vazia_icone = (string) ($ui_tabela_vazia_icone ?? 'fa-solid fa-inbox');
$ui_tabela_vazia_mensagem = (string) ($ui_tabela_vazia_mensagem ?? 'Nenhum registro encontrado');
$ui_tabela_vazia_cta_html = (string) ($ui_tabela_vazia_cta_html ?? '');
?>
<tr>
    <td colspan="<?= max(1, $ui_tabela_vazia_colspan) ?>" class="px-6 py-12 text-center text-gray-500">
        <i class="<?= htmlspecialchars($ui_tabela_vazia_icone) ?> text-4xl text-gray-300 mb-4"></i>
        <p><?= htmlspecialchars($ui_tabela_vazia_mensagem) ?></p>
        <?php if ($ui_tabela_vazia_cta_html !== ''): ?>
        <div class="mt-4"><?= $ui_tabela_vazia_cta_html ?></div>
        <?php endif; ?>
    </td>
</tr>
<?php
unset(
    $ui_tabela_vazia_colspan, $ui_tabela_vazia_icone,
    $ui_tabela_vazia_mensagem, $ui_tabela_vazia_cta_html
);
?>
