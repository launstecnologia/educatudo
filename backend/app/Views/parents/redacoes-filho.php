<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($filho['nome'] ?? '') ?></h2>
    
    <?php if (empty($redacoes)): ?>
        <div class="text-center py-12">
            <p class="text-gray-500">Nenhuma redação encontrada</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($redacoes as $redacao): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($redacao['tema'] ?? $redacao['titulo'] ?? 'Redação') ?></h3>
                    <p class="text-sm text-gray-500 mt-1"><?= date('d/m/Y H:i', strtotime($redacao['created_at'])) ?></p>
                    <?php if (!empty($redacao['nota']) || !empty($redacao['nota_final'])): ?>
                        <p class="text-sm text-blue-600 mt-1">Nota: <?= number_format($redacao['nota'] ?? $redacao['nota_final'], 1) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
