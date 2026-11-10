<?php
/**
 * Cabeçalho padrão de formulário admin (padrão Turmas).
 *
 * Variáveis:
 * - $page_header_back_url (string)
 * - $page_header_title (string)
 * - $page_header_subtitle (string, opcional — ou HTML via $page_header_subtitle_html)
 * - $page_header_back_label (string, opcional)
 * - $page_header_icon (string FA, opcional)
 * - $page_header_actions (string HTML, opcional — além do Voltar)
 * - $trilhaItens (list, opcional)
 */
$page_header_back_url = (string) ($page_header_back_url ?? '');
$page_header_title = (string) ($page_header_title ?? '');
$page_header_subtitle = (string) ($page_header_subtitle ?? '');
$page_header_subtitle_html = (string) ($page_header_subtitle_html ?? '');
$page_header_title_id = (string) ($page_header_title_id ?? '');
$page_header_subtitle_id = (string) ($page_header_subtitle_id ?? '');
$page_header_back_label = trim((string) ($page_header_back_label ?? ''));
$page_header_actions = (string) ($page_header_actions ?? '');
$page_header_icon = trim((string) ($page_header_icon ?? 'fa-solid fa-pen-to-square'));
if ($page_header_icon !== '' && !str_contains($page_header_icon, 'fa-')) {
    $page_header_icon = 'fa-solid ' . $page_header_icon;
}
if ($page_header_back_label === '') {
    $page_header_back_label = 'Voltar';
}

if (!isset($trilhaItens) || !is_array($trilhaItens) || $trilhaItens === []) {
    $trilhaUri = (string) ($_SERVER['REQUEST_URI'] ?? '');
    $trilhaItens = \App\Helpers\TrilhaAdmin::montar($trilhaUri, $page_header_title);
}
?>
<div class="mb-6">
    <?php if (!\App\Helpers\TrilhaAdmin::jaRenderizada() && count($trilhaItens) >= 2): ?>
        <?php include \App\Core\ViewPaths::layoutComponent('trilha.php'); ?>
    <?php endif; ?>
    <div class="<?= \App\Helpers\TrilhaAdmin::jaRenderizada() || count($trilhaItens) >= 2 ? 'mt-4 ' : '' ?>flex justify-between items-center gap-4">
        <div class="flex items-center gap-3 min-w-0">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                <i class="<?= htmlspecialchars($page_header_icon, ENT_QUOTES, 'UTF-8') ?> text-lg" aria-hidden="true"></i>
            </span>
            <div class="min-w-0">
                <h2 class="text-xl font-bold text-gray-900 truncate"<?= $page_header_title_id !== '' ? ' id="' . htmlspecialchars($page_header_title_id, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($page_header_title) ?></h2>
                <?php if ($page_header_subtitle_html !== ''): ?>
                <p class="text-sm text-gray-500 mt-0.5"<?= $page_header_subtitle_id !== '' ? ' id="' . htmlspecialchars($page_header_subtitle_id, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= $page_header_subtitle_html ?></p>
                <?php elseif ($page_header_subtitle !== ''): ?>
                <p class="text-sm text-gray-500 mt-0.5"<?= $page_header_subtitle_id !== '' ? ' id="' . htmlspecialchars($page_header_subtitle_id, ENT_QUOTES, 'UTF-8') . '"' : '' ?>><?= htmlspecialchars($page_header_subtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <div class="flex items-center justify-end gap-3 flex-shrink-0 ml-auto flex-wrap">
            <?php if ($page_header_back_url !== ''): ?>
            <a href="<?= htmlspecialchars($page_header_back_url, ENT_QUOTES, 'UTF-8') ?>"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-arrow-left mr-2 text-gray-500" aria-hidden="true"></i>
                <?= htmlspecialchars($page_header_back_label, ENT_QUOTES, 'UTF-8') ?>
            </a>
            <?php endif; ?>
            <?php if ($page_header_actions !== ''): ?>
                <?= $page_header_actions ?>
            <?php endif; ?>
        </div>
    </div>
</div>
