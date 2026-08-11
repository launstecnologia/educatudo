<?php
$links = $links ?? [];
$baseRoute = $base_route ?? '/aluno/materiais';
?>

<div class="max-w-5xl mx-auto px-4 py-6">
    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <h2 class="text-xl font-semibold text-gray-900 mb-1">Materiais e Apps externos</h2>
        <p class="text-sm text-gray-600 mb-6">Abra os apps externos da escola em um único fluxo com validação por token.</p>

        <?php if (empty($links)): ?>
            <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-amber-800 text-sm">
                Nenhum app externo configurado para este perfil.
            </div>
        <?php else: ?>
            <div class="space-y-3">
                <?php foreach ($links as $item): ?>
                    <div class="flex items-center justify-between rounded-lg border border-gray-200 px-4 py-3">
                        <div>
                            <p class="font-medium text-gray-900"><?= htmlspecialchars($item['nome'] ?? '') ?></p>
                            <p class="text-xs text-gray-500 truncate"><?= htmlspecialchars($item['url'] ?? '') ?></p>
                        </div>
                        <a href="<?= URL . $baseRoute . '/abrir/' . rawurlencode((string) ($item['id'] ?? '')) ?>"
                           class="inline-flex items-center px-3 py-2 text-sm rounded-lg bg-indigo-600 text-white hover:bg-indigo-700">
                            Abrir
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

