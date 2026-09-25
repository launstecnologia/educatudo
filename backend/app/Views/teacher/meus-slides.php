<?php
$ui = __DIR__ . '/../admin/_partials/ui';
ob_start();
$ui_btn_variant = 'primary';
$ui_btn_label = 'Nova apresentação';
$ui_btn_href = URL . '/professor/gerar-slides';
$ui_btn_icon = 'fa-solid fa-plus';
include $ui . '/btn.php';
$page_header_actions = ob_get_clean();
$page_header_title = 'Meus Slides';
$page_header_subtitle = 'Para editar os slides depois de prontos, é necessário ter uma conta gratuita no Gamma.';
include __DIR__ . '/../admin/_partials/page_header_list.php';

$erro = trim((string) ($_GET['erro'] ?? ''));
?>

<?php if ($erro !== ''): ?>
    <div class="mb-6 bg-red-50 border border-red-200 text-red-700 rounded-xl px-4 py-3 text-sm">
        <?= htmlspecialchars($erro) ?>
    </div>
<?php endif; ?>

<?php if (empty($slides)): ?>
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
        <i class="fa-solid fa-display text-4xl text-gray-300 mb-4"></i>
        <h3 class="text-lg font-semibold text-gray-900 mb-2">Nenhuma apresentação salva ainda</h3>
        <p class="text-sm text-gray-500 mb-6">Crie a primeira no Educa Slides.</p>
        <?php
        $ui_btn_variant = 'primary';
        $ui_btn_label = 'Criar apresentação';
        $ui_btn_href = URL . '/professor/gerar-slides';
        $ui_btn_icon = 'fa-solid fa-plus';
        include $ui . '/btn.php';
        ?>
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($slides as $slide): ?>
            <article class="bg-white rounded-xl shadow-sm border border-gray-200 flex flex-col">
                <div class="p-5 flex-1">
                    <h3 class="text-base font-semibold text-gray-900 truncate">
                        <?= htmlspecialchars((string) ($slide['titulo'] ?? 'Sem título')) ?>
                    </h3>
                    <div class="mt-3 space-y-1 text-sm text-gray-600">
                        <p><?= (int) ($slide['numero_slides'] ?? 0) ?> slides</p>
                        <p><?= !empty($slide['created_at']) ? date('d/m/Y H:i', strtotime($slide['created_at'])) : '—' ?></p>
                    </div>
                    <?php if (!empty($slide['conteudo'])): ?>
                        <?php
                        $resumo = trim((string) preg_replace('/\s+/u', ' ', (string) $slide['conteudo']));
                        if (mb_strlen($resumo) > 140) {
                            $resumo = mb_substr($resumo, 0, 140) . '…';
                        }
                        ?>
                        <p class="mt-3 text-sm text-gray-500"><?= htmlspecialchars($resumo) ?></p>
                    <?php endif; ?>
                </div>
                <div class="px-5 pb-5 flex flex-wrap gap-2">
                    <?php
                    $ui_btn_variant = 'detalhes';
                    $ui_btn_label = 'Editar no Gamma';
                    $ui_btn_href = (string) ($slide['url_gamma'] ?? '#');
                    $ui_btn_icon = '';
                    $ui_btn_attrs = 'target="_blank" rel="noopener noreferrer"';
                    include $ui . '/btn.php';

                    $ui_btn_variant = 'complementar';
                    $ui_btn_label = 'Baixar PPTX';
                    $ui_btn_href = URL . '/professor/meus-slides/' . (int) $slide['id'] . '/pptx';
                    $ui_btn_icon = 'fa-solid fa-download';
                    $ui_btn_attrs = '';
                    include $ui . '/btn.php';
                    ?>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
