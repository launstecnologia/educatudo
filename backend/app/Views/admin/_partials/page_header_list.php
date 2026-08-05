<?php
/**
 * Cabeçalho padrão de listagem admin (design system EducaTudo).
 *
 * Variáveis:
 * - $page_header_title (string)
 * - $page_header_subtitle (string, opcional)
 * - $page_header_actions (string HTML, opcional) — botões à direita
 */
$page_header_title = (string) ($page_header_title ?? '');
$page_header_subtitle = (string) ($page_header_subtitle ?? '');
$page_header_actions = (string) ($page_header_actions ?? '');
?>
<div class="mb-6">
    <div class="flex justify-between items-center gap-4 flex-wrap">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars($page_header_title) ?></h2>
            <?php if ($page_header_subtitle !== ''): ?>
            <p class="text-gray-600 text-sm"><?= htmlspecialchars($page_header_subtitle) ?></p>
            <?php endif; ?>
        </div>
        <?php if ($page_header_actions !== ''): ?>
        <div class="flex items-center gap-3 flex-shrink-0">
            <?= $page_header_actions ?>
        </div>
        <?php endif; ?>
    </div>
</div>
