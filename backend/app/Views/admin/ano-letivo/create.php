<?php
$csrf_token = $csrf_token ?? '';

$page_header_back_url = URL . '/admin/ano-letivo';
$page_header_title = 'Cadastrar Ano Letivo';
$page_header_subtitle = 'Informe o ano e o período letivo para turmas e matrículas.';
include __DIR__ . '/../_partials/page_header_form.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST" action="<?= URL ?>/admin/ano-letivo" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Dados do ano letivo</h3>
            <?php
            $ano_letivo_form_item = null;
            include __DIR__ . '/_ano_letivo_form_fields.php';
            ?>
        </div>

        <?php
        $form_cancel_url = URL . '/admin/ano-letivo';
        $form_submit_label = 'Cadastrar Ano Letivo';
        include __DIR__ . '/../_partials/form_actions.php';
        ?>
    </form>
</div>
