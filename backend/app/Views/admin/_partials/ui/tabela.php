<?php
/**
 * Shell de tabela de listagem admin.
 *
 * Variáveis:
 * - $ui_tabela_colunas (array) — cada item: string label OU ['label'=>..., 'class'=>...]
 * - $ui_tabela_body (string HTML) — linhas <tr>…</tr>
 * - $ui_tabela_class (string, opcional) — classes extras no container
 */
$ui_tabela_colunas = $ui_tabela_colunas ?? [];
$ui_tabela_body = (string) ($ui_tabela_body ?? '');
$ui_tabela_class = (string) ($ui_tabela_class ?? '');

if (!is_array($ui_tabela_colunas)) {
    $ui_tabela_colunas = [];
}
?>
<div class="bg-white rounded-xl shadow-sm border border-gray-200 <?= htmlspecialchars($ui_tabela_class) ?>">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach ($ui_tabela_colunas as $ui_tabela_coluna): ?>
                        <?php
                        if (is_array($ui_tabela_coluna)) {
                            $ui_tabela_th_label = (string) ($ui_tabela_coluna['label'] ?? '');
                            $ui_tabela_th_class = (string) ($ui_tabela_coluna['class'] ?? 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider');
                        } else {
                            $ui_tabela_th_label = (string) $ui_tabela_coluna;
                            $ui_tabela_th_class = 'px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider';
                        }
                        ?>
                        <th class="<?= htmlspecialchars($ui_tabela_th_class) ?>"><?= htmlspecialchars($ui_tabela_th_label) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?= $ui_tabela_body ?>
            </tbody>
        </table>
    </div>
</div>
<?php
unset(
    $ui_tabela_colunas, $ui_tabela_body, $ui_tabela_class,
    $ui_tabela_coluna, $ui_tabela_th_label, $ui_tabela_th_class
);
?>
