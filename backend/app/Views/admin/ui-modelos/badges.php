<?php
$ui = __DIR__ . '/../_partials/ui';

$page_header_title = 'Badges';
$page_header_subtitle = 'Pills de status — partial _partials/ui/badge.php';
$page_header_actions = '';
include __DIR__ . '/../_partials/page_header_list.php';
?>

<p class="mb-6">
    <?php
    $ui_btn_variant = 'link';
    $ui_btn_label = '← Voltar ao hub';
    $ui_btn_href = URL . '/admin/configuracao/ui-modelos';
    include $ui . '/btn.php';
    ?>
</p>

<?php
$badges = [
    ['variant' => 'ativo', 'label' => 'Ativo'],
    ['variant' => 'inativo', 'label' => 'Inativo'],
    ['variant' => 'pendente', 'label' => 'Pendente'],
    ['variant' => 'rascunho', 'label' => 'Rascunho'],
    ['variant' => 'erro', 'label' => 'Erro'],
    ['variant' => 'neutro', 'label' => 'Encerrado'],
    ['variant' => 'info', 'label' => 'Em análise'],
    ['variant' => 'transferido', 'label' => 'Transferido'],
];

ob_start();
?>
<?php $ui_form_secao_titulo = 'Variantes de status'; include $ui . '/form_secao.php'; ?>
<div class="flex flex-wrap items-center gap-3">
    <?php foreach ($badges as $b): ?>
        <?php
        $ui_badge_variant = $b['variant'];
        $ui_badge_label = $b['label'];
        include $ui . '/badge.php';
        ?>
    <?php endforeach; ?>
</div>

<div class="mt-10">
    <?php $ui_form_secao_titulo = 'Em contexto de tabela'; include $ui . '/form_secao.php'; ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Registro</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($badges as $i => $b): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">Exemplo #<?= $i + 1 ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php
                        $ui_badge_variant = $b['variant'];
                        $ui_badge_label = $b['label'];
                        include $ui . '/badge.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
$ui_card_body = ob_get_clean();
$ui_card_variant = 'form';
include $ui . '/card.php';
?>
