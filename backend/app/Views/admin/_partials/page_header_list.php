<?php
/**
 * Cabeçalho padrão de listagem admin (padrão Turmas).
 *
 * Variáveis:
 * - $page_header_title (string)
 * - $page_header_subtitle (string, opcional)
 * - $page_header_actions (string HTML, opcional) — botões à direita
 * - $page_header_icon (string FA, opcional — ex.: fa-solid fa-school)
 * - $trilhaItens (list, opcional) — se ausente, monta pela URI quando no escopo
 */
$page_header_title = (string) ($page_header_title ?? '');
$page_header_subtitle = (string) ($page_header_subtitle ?? '');
$page_header_actions = (string) ($page_header_actions ?? '');
$page_header_icon = trim((string) ($page_header_icon ?? 'fa-solid fa-folder'));
if ($page_header_icon !== '' && !str_contains($page_header_icon, 'fa-')) {
    $page_header_icon = 'fa-solid ' . $page_header_icon;
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
                <h2 class="text-xl font-bold text-gray-900 truncate"><?= htmlspecialchars($page_header_title) ?></h2>
                <?php if ($page_header_subtitle !== ''): ?>
                <p class="text-sm text-gray-500 mt-0.5"><?= htmlspecialchars($page_header_subtitle) ?></p>
                <?php endif; ?>
            </div>
        </div>
        <?php if ($page_header_actions !== ''): ?>
        <div class="flex items-center justify-end gap-3 flex-shrink-0 ml-auto flex-wrap">
            <?= $page_header_actions ?>
        </div>
        <?php endif; ?>
    </div>
</div>
