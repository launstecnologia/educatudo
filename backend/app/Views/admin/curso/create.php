<?php
$csrf_token = $csrf_token ?? '';
$has_tipo_possui_serie = (bool) ($has_tipo_possui_serie ?? false);

$page_header_back_url = URL . '/admin/curso';
$page_header_title = 'Cadastrar Curso';
$page_header_subtitle = 'Informe nome, tipo e ordem para organizar a estrutura acadêmica.';
include __DIR__ . '/../_partials/page_header_form.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST" action="<?= URL ?>/admin/curso" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Dados do curso</h3>
            <?php
            $curso_form_item = null;
            include __DIR__ . '/_curso_form_fields.php';
            ?>
        </div>

        <?php
        $form_cancel_url = URL . '/admin/curso';
        $form_submit_label = 'Cadastrar Curso';
        include __DIR__ . '/../_partials/form_actions.php';
        ?>
    </form>
</div>
