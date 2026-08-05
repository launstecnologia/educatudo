<?php
$item = $item ?? null;
$csrf_token = $csrf_token ?? '';
$has_tipo_possui_serie = (bool) ($has_tipo_possui_serie ?? false);
if (!$item) {
    header('Location: ' . (defined('URL') ? URL : '') . '/admin/curso');
    exit;
}

$page_header_back_url = URL . '/admin/curso';
$page_header_title = 'Editar Curso';
$page_header_subtitle_html = 'Altere os dados do curso <strong>' . htmlspecialchars($item['nome']) . '</strong>.';
include __DIR__ . '/../_partials/page_header_form.php';
?>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="POST" action="<?= URL ?>/admin/curso/<?= (int) $item['id'] ?>/update" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Dados do curso</h3>
            <?php
            $curso_form_item = $item;
            include __DIR__ . '/_curso_form_fields.php';
            ?>
        </div>

        <?php
        $form_cancel_url = URL . '/admin/curso';
        $form_submit_label = 'Salvar alterações';
        include __DIR__ . '/../_partials/form_actions.php';
        ?>
    </form>
</div>
