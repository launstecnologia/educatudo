<div class="<?= $card ?> mb-4">
    <div class="flex items-center justify-between mb-3">
        <h3 class="text-sm font-semibold text-gray-900">Aulas online</h3>
        <a href="<?= URL ?>/admin/aulas-online" class="text-xs font-medium text-accent hover:underline">Ver todas</a>
    </div>
    <div class="space-y-2">
        <?php foreach ($aulasOnline as $aula): ?>
            <?php
                $inicioTs = !empty($aula['inicio_em']) ? strtotime((string) $aula['inicio_em']) : false;
                $fimTs = !empty($aula['fim_em']) ? strtotime((string) $aula['fim_em']) : false;
                $aoVivo = $inicioTs !== false && time() >= $inicioTs && ($fimTs === false || time() <= $fimTs);
            ?>
            <div class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 <?= $aoVivo ? 'bg-rose-50' : 'bg-slate-50' ?>">
                <div class="min-w-0">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars((string) ($aula['titulo'] ?? 'Aula')) ?></p>
                    <p class="text-xs text-gray-500"><?= $aoVivo ? 'Ao vivo' : ($inicioTs ? date('d/m H:i', $inicioTs) : '') ?></p>
                </div>
                <a href="<?= URL ?>/admin/aulas-online/chat?id=<?= (int) ($aula['id'] ?? 0) ?>" class="text-xs font-semibold text-accent hover:underline shrink-0">Abrir</a>
            </div>
        <?php endforeach; ?>
    </div>
</div>
