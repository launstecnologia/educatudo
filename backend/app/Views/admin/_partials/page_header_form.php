<?php
/**
 * Cabeçalho padrão de formulário admin (create/edit).
 *
 * Variáveis:
 * - $page_header_back_url (string)
 * - $page_header_title (string)
 * - $page_header_subtitle (string, opcional — pode conter HTML seguro via $page_header_subtitle_html)
 * - $page_header_subtitle_html (string, opcional — use quando precisar de <strong> etc.)
 */
$page_header_back_url = (string) ($page_header_back_url ?? '');
$page_header_title = (string) ($page_header_title ?? '');
$page_header_subtitle = (string) ($page_header_subtitle ?? '');
$page_header_subtitle_html = (string) ($page_header_subtitle_html ?? '');
?>
<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= htmlspecialchars($page_header_back_url) ?>" class="text-gray-500 hover:text-gray-700 flex-shrink-0" aria-label="Voltar">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path>
            </svg>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($page_header_title) ?></h2>
            <?php if ($page_header_subtitle_html !== ''): ?>
            <p class="text-gray-600 text-sm"><?= $page_header_subtitle_html ?></p>
            <?php elseif ($page_header_subtitle !== ''): ?>
            <p class="text-gray-600 text-sm"><?= htmlspecialchars($page_header_subtitle) ?></p>
            <?php endif; ?>
        </div>
    </div>
</div>
