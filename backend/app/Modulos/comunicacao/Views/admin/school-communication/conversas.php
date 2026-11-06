<?php
$items = is_array($items ?? null) ? $items : [];
?>
<div class="mb-6">
    <a href="<?= URL ?>/admin/comunicacao-escolar" class="text-sm text-gray-600 hover:text-gray-900">← Comunicação escolar</a>
</div>
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-100">
        <h2 class="text-lg font-bold text-gray-900">Fale com a Escola</h2>
        <p class="text-sm text-gray-500">Mensagens que a família enviou. O aviso também entra no sininho.</p>
    </div>
    <?php if ($items === []): ?>
        <p class="px-6 py-8 text-gray-500">Nenhuma família falou com a escola ainda.</p>
    <?php else: ?>
        <div class="divide-y divide-gray-100">
            <?php foreach ($items as $item): ?>
                <a href="<?= URL ?>/admin/comunicacao-escolar/fale-com-a-escola/<?= (int) $item['id'] ?>" class="flex items-center justify-between gap-4 px-6 py-4 hover:bg-gray-50">
                    <div class="min-w-0">
                        <p class="font-semibold text-gray-900"><?= htmlspecialchars((string) ($item['aluno_nome'] ?? 'Aluno')) ?></p>
                        <p class="text-sm text-gray-500"><?= htmlspecialchars((string) ($item['responsavel_nome'] ?? 'Responsável')) ?></p>
                        <p class="text-sm text-gray-600 truncate"><?= htmlspecialchars((string) (($item['ultima'] ?? '') !== '' ? $item['ultima'] : 'Imagem')) ?></p>
                    </div>
                    <?php if ((int) ($item['nao_lidas'] ?? 0) > 0): ?>
                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-700"><?= (int) $item['nao_lidas'] ?> nova(s)</span>
                    <?php endif; ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
