<?php
$baseUrl = $baseUrl ?? (URL . '/professor/drive');
$item = $item ?? null;
$viewable = $viewable ?? false;
$breadcrumb = $breadcrumb ?? [];
$parentId = $parentId ?? null;
if (!$item) {
    echo '<p class="p-4 text-gray-500">Arquivo não encontrado.</p>';
    return;
}
$serveUrl = $baseUrl . '/serve/' . (int) $item['id'];
$downloadUrl = $baseUrl . '/download/' . (int) $item['id'];
$ext = strtolower(pathinfo($item['name'], PATHINFO_EXTENSION));
$isImage = in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg'], true);
?>
<div class="mobile-content flex-1 overflow-y-auto w-full min-h-0 p-4 md:p-6">
    <div class="w-full max-w-5xl mx-auto">
        <!-- Breadcrumb -->
        <nav class="flex items-center gap-2 text-sm text-gray-600 mb-4">
            <?php foreach ($breadcrumb as $i => $b): ?>
                <?php if ($i > 0): ?><span class="text-gray-400">/</span><?php endif; ?>
                <?php if (($b['id'] ?? null) === $item['id']): ?>
                    <span class="text-gray-900 font-medium truncate max-w-[180px]"><?= htmlspecialchars($item['name']) ?></span>
                <?php elseif (($b['id'] ?? null) === null): ?>
                    <a href="<?= $baseUrl ?>" class="hover:text-indigo-600"><?= htmlspecialchars($b['name']) ?></a>
                <?php else: ?>
                    <a href="<?= $baseUrl ?>/folder/<?= (int)$b['id'] ?>" class="hover:text-indigo-600"><?= htmlspecialchars($b['name']) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>
        </nav>

        <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden flex flex-col" style="min-height: 70vh;">
            <div class="flex items-center justify-between gap-4 p-4 border-b border-gray-200 flex-shrink-0">
                <h1 class="text-lg font-semibold text-gray-900 truncate"><?= htmlspecialchars($item['name']) ?></h1>
                <a href="<?= $downloadUrl ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors flex-shrink-0">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Baixar
                </a>
            </div>

            <div class="flex-1 min-h-0 p-4 flex items-center justify-center bg-gray-50">
                <?php if ($viewable && $isImage): ?>
                    <img src="<?= htmlspecialchars($serveUrl) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="max-w-full max-h-[75vh] object-contain rounded-lg shadow-inner">
                <?php elseif ($viewable): ?>
                    <iframe src="<?= htmlspecialchars($serveUrl) ?>" class="w-full rounded-lg border border-gray-200 bg-white" style="min-height: 70vh;" title="<?= htmlspecialchars($item['name']) ?>"></iframe>
                <?php else: ?>
                    <div class="text-center py-12">
                        <p class="text-gray-500 mb-4">Visualização não disponível para este tipo de arquivo.</p>
                        <a href="<?= $downloadUrl ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg font-medium transition-colors">
                            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            Baixar arquivo
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if ($parentId !== null): ?>
            <p class="mt-4">
                <a href="<?= $baseUrl ?>/folder/<?= (int)$parentId ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">← Voltar para a pasta</a>
            </p>
        <?php else: ?>
            <p class="mt-4">
                <a href="<?= $baseUrl ?>" class="text-indigo-600 hover:text-indigo-800 font-medium">← Voltar ao Drive</a>
            </p>
        <?php endif; ?>
    </div>
</div>
