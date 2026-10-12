<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Apostilas da Escola</h1>
    <p class="text-gray-600 mt-1">Acesse as apostilas disponibilizadas pela coordenação.</p>
</div>

<?php if (!empty($flash['message'])): ?>
    <div class="mb-4 rounded-lg px-4 py-3 <?= ($flash['type'] ?? 'info') === 'success' ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200' ?>">
        <?= htmlspecialchars($flash['message']) ?>
    </div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow border border-gray-200 p-6">
    <?php if (empty($items)): ?>
        <p class="text-gray-500 text-center py-8">Nenhuma apostila disponível no momento.</p>
    <?php else: ?>
        <ul class="space-y-3">
            <?php foreach ($items as $item): ?>
                <li class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <div class="min-w-0">
                        <p class="text-gray-900 font-medium truncate"><?= htmlspecialchars($item['titulo'] ?? $item['nome_original']) ?></p>
                        <?php if (!empty($item['descricao'])): ?>
                            <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars($item['descricao']) ?></p>
                        <?php endif; ?>
                        <p class="text-xs text-gray-500"><?= number_format(((int)($item['tamanho'] ?? 0)) / 1024, 1, ',', '.') ?> KB</p>
                    </div>
                    <a href="<?= URL ?>/professor/apostilas/abrir/<?= rawurlencode((string)$item['id']) ?>" class="shrink-0 ml-3 bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 text-sm font-medium">
                        Abrir
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="text-xs text-gray-500 mt-4">Visualização dentro da plataforma; download não é disponibilizado.</p>
    <?php endif; ?>
</div>

