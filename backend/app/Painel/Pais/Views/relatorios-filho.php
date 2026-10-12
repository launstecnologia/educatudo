<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-4"><?= htmlspecialchars($filho['nome'] ?? '') ?></h2>
    
    <?php if (empty($relatorios)): ?>
        <div class="text-center py-12">
            <p class="text-gray-500">Nenhum relatório disponível</p>
        </div>
    <?php else: ?>
        <div class="space-y-4">
            <?php foreach ($relatorios as $relatorio): ?>
                <div class="border border-gray-200 rounded-lg p-4">
                    <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($relatorio['titulo'] ?? 'Relatório') ?></h3>
                    <p class="text-sm text-gray-500 mt-1"><?= date('d/m/Y H:i', strtotime($relatorio['created_at'])) ?></p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
