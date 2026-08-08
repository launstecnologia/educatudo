<?php
$ui = __DIR__ . '/../_partials/ui';

// Cabeçalho canônico: complementares (outline) → CTA primary por último
ob_start();
$ui_btn_variant = 'filtro';
$ui_btn_label = 'Filtros';
$ui_btn_icon = 'fa-solid fa-filter';
$ui_btn_onclick = 'return false;';
include $ui . '/btn.php';

$ui_btn_variant = 'complementar';
$ui_btn_label = 'Tipo de Avaliação';
$ui_btn_icon = 'fa-solid fa-layer-group';
$ui_btn_href = '#';
include $ui . '/btn.php';

$ui_btn_variant = 'complementar';
$ui_btn_label = 'Bloco Professor';
$ui_btn_icon = 'fa-solid fa-table-cells';
$ui_btn_href = '#';
include $ui . '/btn.php';

$ui_btn_variant = 'primary';
$ui_btn_label = 'Novo Evento';
$ui_btn_icon = 'fa-solid fa-plus';
$ui_btn_href = '#';
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();

$page_header_title = 'Botões';
$page_header_subtitle = 'Complementares = outline cinza (mesmo visual). CTA = primary, sempre por último.';
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
<div class="space-y-8">
    <section>
        <?php $ui_form_secao_titulo = 'Padrão de cabeçalho (canônico)'; include $ui . '/form_secao.php'; ?>
        <p class="text-sm text-gray-600 mb-4">
            Filtros e ações secundárias usam o mesmo estilo outline. Só o CTA (criar/novo) usa <code class="text-xs bg-gray-100 px-1 rounded">primary</code>.
        </p>
        <div class="flex items-center gap-3 flex-wrap">
            <?php
            $ui_btn_variant = 'filtro';
            $ui_btn_label = 'Filtros';
            $ui_btn_icon = 'fa-solid fa-filter';
            include $ui . '/btn.php';

            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Tipo de Avaliação';
            $ui_btn_icon = 'fa-solid fa-layer-group';
            $ui_btn_href = '#';
            include $ui . '/btn.php';

            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Bloco Professor';
            $ui_btn_icon = 'fa-solid fa-table-cells';
            $ui_btn_href = '#';
            include $ui . '/btn.php';

            $ui_btn_variant = 'primary';
            $ui_btn_label = 'Novo Evento';
            $ui_btn_icon = 'fa-solid fa-plus';
            $ui_btn_href = '#';
            include $ui . '/btn.php';
            ?>
        </div>
    </section>

    <section>
        <?php $ui_form_secao_titulo = 'Complementares (outline)'; include $ui . '/form_secao.php'; ?>
        <p class="text-sm text-gray-600 mb-4">
            Variants <code class="text-xs bg-gray-100 px-1 rounded">complementar</code>,
            <code class="text-xs bg-gray-100 px-1 rounded">secondary</code> (alias) e
            <code class="text-xs bg-gray-100 px-1 rounded">filtro</code> — branco, borda cinza, ícone <code class="text-xs bg-gray-100 px-1 rounded">text-gray-500</code>.
        </p>
        <div class="flex flex-wrap items-center gap-3">
            <?php
            $ui_btn_variant = 'filtro';
            $ui_btn_label = 'Filtros';
            $ui_btn_icon = 'fa-solid fa-filter';
            $ui_btn_filter_count = 2;
            include $ui . '/btn.php';

            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Tipo de Avaliação';
            $ui_btn_icon = 'fa-solid fa-layer-group';
            $ui_btn_href = '#';
            include $ui . '/btn.php';

            $ui_btn_variant = 'complementar';
            $ui_btn_label = 'Bloco Professor';
            $ui_btn_icon = 'fa-solid fa-table-cells';
            $ui_btn_href = '#';
            include $ui . '/btn.php';
            ?>
        </div>
    </section>

    <section>
        <?php $ui_form_secao_titulo = 'Outras variantes'; include $ui . '/form_secao.php'; ?>
        <div class="flex flex-wrap items-center gap-3">
            <?php
            $ui_btn_variant = 'primary';
            $ui_btn_label = 'Primário';
            $ui_btn_icon = 'fa-solid fa-plus';
            $ui_btn_href = '#';
            include $ui . '/btn.php';

            $ui_btn_variant = 'detalhes';
            $ui_btn_label = 'Detalhes';
            $ui_btn_icon = 'fa-solid fa-circle-info';
            $ui_btn_href = '#';
            include $ui . '/btn.php';

            $ui_btn_variant = 'confirm';
            $ui_btn_label = 'Aplicar filtros';
            $ui_btn_type = 'button';
            include $ui . '/btn.php';

            $ui_btn_variant = 'destrutivo';
            $ui_btn_label = 'Excluir';
            $ui_btn_icon = 'fa-solid fa-trash-can';
            $ui_btn_type = 'button';
            include $ui . '/btn.php';

            $ui_btn_variant = 'link';
            $ui_btn_label = '← Voltar';
            $ui_btn_href = '#';
            include $ui . '/btn.php';
            ?>
        </div>
    </section>
</div>
<?php
$ui_card_body = ob_get_clean();
$ui_card_variant = 'form';
include $ui . '/card.php';
?>
