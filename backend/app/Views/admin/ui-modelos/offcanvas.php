<?php
$ui = __DIR__ . '/../_partials/ui';

ob_start();
$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'openFiltroDrawer()';
$ui_btn_filter_count = 0;
include $ui . '/btn.php';

$ui_btn_variant = 'primary';
$ui_btn_label = 'Novo cadastro';
$ui_btn_icon = 'fa-solid fa-plus';
$ui_btn_onclick = 'openCadastroDrawer()';
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();

$page_header_title = 'Offcanvas';
$page_header_subtitle = 'Drawer lateral — partial _partials/ui/offcanvas.php';
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
ob_start();
?>
<p class="text-sm text-gray-600 mb-4">
    Use os botões do cabeçalho para abrir o drawer de <strong>cadastro</strong> ou de <strong>filtros</strong>.
    O conteúdo abaixo simula uma listagem mínima.
</p>
<div class="text-sm text-gray-500 border border-dashed border-gray-200 rounded-lg p-8 text-center">
    Área da listagem (placeholder). Abra o offcanvas para ver o formulário.
</div>
<?php
$ui_card_body = ob_get_clean();
$ui_card_variant = 'form';
include $ui . '/card.php';
?>

<?php
// ---- Cadastro ----
ob_start();
?>
<form class="flex flex-col flex-1 overflow-hidden" onsubmit="event.preventDefault(); closeCadastroDrawer(); alert('Demo: formulário não envia dados.');">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-6">
        <?php $ui_form_secao_titulo = 'Dados do registro'; include $ui . '/form_secao.php'; ?>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2">
            <?php
            $ui_form_campo_label = 'Nome';
            $ui_form_campo_name = 'demo_nome';
            $ui_form_campo_tipo = 'text';
            $ui_form_campo_obrigatorio = true;
            $ui_form_campo_placeholder = 'Nome completo';
            $ui_form_campo_span = 'full';
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'E-mail';
            $ui_form_campo_name = 'demo_email';
            $ui_form_campo_tipo = 'email';
            $ui_form_campo_obrigatorio = true;
            $ui_form_campo_span = 'full';
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'Perfil';
            $ui_form_campo_name = 'demo_perfil';
            $ui_form_campo_tipo = 'select';
            $ui_form_campo_opcoes = ['' => 'Selecione…', 'aluno' => 'Aluno', 'professor' => 'Professor'];
            $ui_form_campo_obrigatorio = false;
            $ui_form_campo_span = 'full';
            include $ui . '/form_campo.php';
            ?>
        </div>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 bg-gray-50/50 flex justify-end gap-3">
        <?php
        $ui_btn_variant = 'secondary';
        $ui_btn_label = 'Cancelar';
        $ui_btn_onclick = 'closeCadastroDrawer()';
        include $ui . '/btn.php';

        $ui_btn_variant = 'primary';
        $ui_btn_label = 'Salvar';
        $ui_btn_type = 'submit';
        include $ui . '/btn.php';
        ?>
    </div>
</form>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'cadastro';
$ui_offcanvas_titulo = 'Novo cadastro';
$ui_offcanvas_max_w = 'max-w-xl';
include $ui . '/offcanvas.php';
?>

<?php
// ---- Filtro ----
ob_start();
?>
<form class="flex flex-col flex-1 overflow-hidden" onsubmit="event.preventDefault(); closeFiltroDrawer();">
    <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-4">
        <?php
        $ui_form_campo_label = 'Buscar';
        $ui_form_campo_name = 'q';
        $ui_form_campo_tipo = 'text';
        $ui_form_campo_placeholder = 'Nome ou e-mail';
        $ui_form_campo_span = 'full';
        $ui_form_campo_obrigatorio = false;
        include $ui . '/form_campo.php';

        $ui_form_campo_label = 'Status';
        $ui_form_campo_name = 'status';
        $ui_form_campo_tipo = 'select';
        $ui_form_campo_opcoes = ['' => 'Todos', 'ativo' => 'Ativo', 'inativo' => 'Inativo'];
        $ui_form_campo_span = 'full';
        include $ui . '/form_campo.php';
        ?>
    </div>
    <div class="px-6 sm:px-8 py-4 border-t border-gray-200 flex gap-3">
        <?php
        $ui_btn_variant = 'secondary';
        $ui_btn_label = 'Limpar';
        $ui_btn_class = 'flex-1 justify-center';
        $ui_btn_onclick = 'closeFiltroDrawer()';
        include $ui . '/btn.php';

        $ui_btn_variant = 'confirm';
        $ui_btn_label = 'Aplicar filtros';
        $ui_btn_type = 'submit';
        $ui_btn_class = 'flex-1 justify-center';
        include $ui . '/btn.php';
        ?>
    </div>
</form>
<?php
$ui_offcanvas_body = ob_get_clean();
$ui_offcanvas_id = 'filtro';
$ui_offcanvas_titulo = 'Filtros';
$ui_offcanvas_max_w = 'max-w-md';
include $ui . '/offcanvas.php';
?>
