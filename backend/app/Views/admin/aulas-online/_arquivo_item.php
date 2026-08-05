<?php /** @var array $arq */ ?>
<div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0" id="arq-<?= (int)$arq['id'] ?>">
    <div class="flex items-center gap-3 min-w-0">
        <i class="fa-solid fa-file text-gray-400 flex-shrink-0"></i>
        <span class="text-sm text-gray-800 truncate"><?= htmlspecialchars((string)($arq['nome_original'] ?? '')) ?></span>
        <?php if (!empty($arq['tamanho'])): ?>
            <span class="text-xs text-gray-400 flex-shrink-0"><?= number_format(((int)$arq['tamanho']) / 1024, 1, ',', '.') ?> KB</span>
        <?php endif; ?>
    </div>
    <div class="flex items-center gap-3 flex-shrink-0">
        <a href="<?= URL ?>/admin/aulas-online/arquivo/download?id=<?= (int)$arq['id'] ?>"
           class="text-xs text-blue-600 hover:underline">Baixar</a>
        <button type="button" onclick="excluirArquivo(<?= (int)$arq['id'] ?>)"
                class="text-xs text-red-600 hover:underline">Excluir</button>
    </div>
</div>
