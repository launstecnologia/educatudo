<?php
$ui = __DIR__ . '/../_partials/ui';

$linhasFake = [
    ['id' => 1, 'nome' => 'Ana Souza', 'iniciais' => 'AS', 'email' => 'ana@escola.com', 'status' => 'ativo', 'status_label' => 'Ativo'],
    ['id' => 2, 'nome' => 'Bruno Lima', 'iniciais' => 'BL', 'email' => 'bruno@escola.com', 'status' => 'pendente', 'status_label' => 'Pendente'],
    ['id' => 3, 'nome' => 'Carla Dias', 'iniciais' => 'CD', 'email' => 'carla@escola.com', 'status' => 'inativo', 'status_label' => 'Inativo'],
    ['id' => 4, 'nome' => 'Diego Alves', 'iniciais' => 'DA', 'email' => 'diego@escola.com', 'status' => 'info', 'status_label' => 'Em análise'],
];

ob_start();
$ui_btn_variant = 'primary';
$ui_btn_label = 'Novo exemplo';
$ui_btn_icon = 'fa-solid fa-plus';
$ui_btn_href = '#';
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();

$page_header_title = 'Tabela';
$page_header_subtitle = 'Shell de listagem + dropdown de ações + estado vazio';
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

<?php $ui_form_secao_titulo = 'Listagem com dados'; include $ui . '/form_secao.php'; ?>

<?php
ob_start();
foreach ($linhasFake as $row):
?>
<tr class="hover:bg-gray-50">
    <td class="px-6 py-4 whitespace-nowrap">
        <div class="flex items-center">
            <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center flex-shrink-0">
                <span class="text-sm font-medium text-slate-600"><?= htmlspecialchars($row['iniciais']) ?></span>
            </div>
            <div class="ml-4 min-w-0">
                <div class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($row['nome']) ?></div>
            </div>
        </div>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($row['email']) ?></td>
    <td class="px-6 py-4 whitespace-nowrap">
        <?php
        $ui_badge_variant = $row['status'];
        $ui_badge_label = $row['status_label'];
        include $ui . '/badge.php';
        ?>
    </td>
    <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
        <?php
        ob_start();
        ?>
        <a href="#" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
        </a>
        <div class="border-t border-gray-100 my-1"></div>
        <button type="button" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
        </button>
        <?php
        $row_actions_dropdown_items = ob_get_clean();
        $row_actions_dropdown_id = 'row-actions-demo-' . (int) $row['id'];
        include __DIR__ . '/../_partials/row_actions_dropdown.php';
        ?>
    </td>
</tr>
<?php
endforeach;
$ui_tabela_body = ob_get_clean();
$ui_tabela_colunas = ['Nome', 'E-mail', 'Status', ['label' => 'Ações', 'class' => 'px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider']];
include $ui . '/tabela.php';
?>

<div class="mt-10">
    <?php $ui_form_secao_titulo = 'Estado vazio'; include $ui . '/form_secao.php'; ?>
    <?php
    ob_start();
    $ui_tabela_vazia_colspan = 3;
    $ui_tabela_vazia_icone = 'fa-solid fa-user-graduate';
    $ui_tabela_vazia_mensagem = 'Nenhum registro encontrado';
    ob_start();
    $ui_btn_variant = 'primary';
    $ui_btn_label = 'Registrar novo';
    $ui_btn_icon = 'fa-solid fa-plus';
    $ui_btn_href = '#';
    include $ui . '/btn.php';
    $ui_tabela_vazia_cta_html = ob_get_clean();
    include $ui . '/tabela_vazia.php';
    $ui_tabela_body = ob_get_clean();
    $ui_tabela_colunas = ['Nome', 'E-mail', 'Status'];
    include $ui . '/tabela.php';
    ?>
</div>
