<?php
$ui = __DIR__ . '/../_partials/ui';

$page_header_back_url = URL . '/admin/configuracao/ui-modelos';
$page_header_title = 'Formulário';
$page_header_subtitle = 'Página própria em largura total — partials form_secao + form_campo + card form';
include __DIR__ . '/../_partials/page_header_form.php';

$flash_status = ($flash_message ?? '') !== ''
    ? (($flash_type ?? '') === 'error' ? 'error' : 'success')
    : '';
include __DIR__ . '/../_partials/flash_message.php';
?>

<?php
ob_start();
?>
<form method="POST" action="<?= URL ?>/admin/configuracao/ui-modelos/formulario" class="space-y-8">
    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

    <section>
        <?php $ui_form_secao_titulo = 'Dados básicos'; include $ui . '/form_secao.php'; ?>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <?php
            $ui_form_campo_label = 'Nome completo';
            $ui_form_campo_name = 'nome';
            $ui_form_campo_tipo = 'text';
            $ui_form_campo_obrigatorio = true;
            $ui_form_campo_placeholder = 'Digite o nome';
            $ui_form_campo_span = 'full';
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'E-mail';
            $ui_form_campo_name = 'email';
            $ui_form_campo_tipo = 'email';
            $ui_form_campo_obrigatorio = true;
            $ui_form_campo_placeholder = 'usuario@escola.com';
            $ui_form_campo_span = 'half';
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'Data de início';
            $ui_form_campo_name = 'data_inicio';
            $ui_form_campo_tipo = 'date';
            $ui_form_campo_obrigatorio = false;
            $ui_form_campo_span = 'half';
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'Tipo';
            $ui_form_campo_name = 'tipo';
            $ui_form_campo_tipo = 'select';
            $ui_form_campo_obrigatorio = true;
            $ui_form_campo_opcoes = [
                '' => 'Selecione…',
                'aluno' => 'Aluno',
                'professor' => 'Professor',
                'admin' => 'Administrativo',
            ];
            $ui_form_campo_span = 'half';
            include $ui . '/form_campo.php';

            $ui_form_campo_label = 'Status';
            $ui_form_campo_name = 'status';
            $ui_form_campo_tipo = 'select';
            $ui_form_campo_value = 'ativo';
            $ui_form_campo_opcoes = [
                'ativo' => 'Ativo',
                'inativo' => 'Inativo',
                'pendente' => 'Pendente',
            ];
            $ui_form_campo_obrigatorio = false;
            $ui_form_campo_span = 'half';
            include $ui . '/form_campo.php';
            ?>
        </div>
    </section>

    <section>
        <?php $ui_form_secao_titulo = 'Observações'; include $ui . '/form_secao.php'; ?>
        <?php
        $ui_form_campo_label = 'Notas internas';
        $ui_form_campo_name = 'observacoes';
        $ui_form_campo_tipo = 'textarea';
        $ui_form_campo_rows = 4;
        $ui_form_campo_ajuda = 'Este formulário é só demo — nada é persistido.';
        $ui_form_campo_span = 'full';
        $ui_form_campo_obrigatorio = false;
        $ui_form_campo_value = '';
        include $ui . '/form_campo.php';
        ?>
    </section>

    <?php
    $form_cancel_url = URL . '/admin/configuracao/ui-modelos';
    $form_submit_label = 'Salvar exemplo';
    include __DIR__ . '/../_partials/form_actions.php';
    ?>
</form>
<?php
$ui_card_body = ob_get_clean();
$ui_card_variant = 'form';
include $ui . '/card.php';
?>
