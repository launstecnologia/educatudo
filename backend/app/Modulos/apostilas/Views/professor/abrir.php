<div class="mb-4">
    <a href="<?= URL ?>/professor/apostilas" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">← Voltar às apostilas</a>
</div>

<div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
    <div class="px-4 py-2 border-b border-gray-200 bg-gray-50">
        <h1 class="text-lg font-semibold text-gray-900 truncate"><?= htmlspecialchars($item['titulo'] ?? $item['nome_original'] ?? '') ?></h1>
        <?php if (!empty($item['descricao'])): ?>
            <p class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($item['descricao']) ?></p>
        <?php endif; ?>
    </div>
    <div class="p-4 min-h-[60vh] flex items-center justify-center bg-gray-100">
        <?php if ($pode_embed): ?>
            <?php if (stripos((string)($item['mime_type'] ?? ''), 'image/') === 0): ?>
                <img src="<?= htmlspecialchars($url_visualizar) ?>" alt="<?= htmlspecialchars($item['titulo'] ?? $item['nome_original'] ?? '') ?>" class="max-w-full max-h-[75vh] object-contain rounded-lg">
            <?php else: ?>
                <iframe src="<?= htmlspecialchars($url_visualizar) ?>#toolbar=0" class="w-full border-0 rounded-lg" style="height: 75vh;" title="<?= htmlspecialchars($item['titulo'] ?? $item['nome_original'] ?? '') ?>"></iframe>
            <?php endif; ?>
        <?php else: ?>
            <div class="text-center max-w-md">
                <p class="text-gray-600 mb-2">Visualização incorporada não disponível para este tipo de arquivo.</p>
                <p class="text-sm text-gray-500">Para manter o acesso dentro do EducaTudo sem download, publique apostilas em PDF ou imagem.</p>
            </div>
        <?php endif; ?>
    </div>
</div>

