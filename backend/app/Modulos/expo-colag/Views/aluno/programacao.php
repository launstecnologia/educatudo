<?php
$itens = $itens ?? [];
$edicao = $edicao ?? null;
$ainda_nao_publica = !empty($ainda_nao_publica);
?>
<div class="max-w-3xl mx-auto px-4 py-6 space-y-6">
    <div>
        <a href="<?= URL ?>/expo-colag" class="text-sm text-accent hover:underline">← Expo Colag</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-1">Programação</h1>
        <?php if ($edicao): ?>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($edicao['nome'] ?? 'Expo Colag') ?>
                <?php if (!empty($edicao['data_evento'])): ?>
                    · <?= htmlspecialchars(date('d/m/Y', strtotime($edicao['data_evento']))) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
    </div>

    <?php if ($ainda_nao_publica): ?>
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
            A programação ainda não foi liberada pela coordenação.
        </div>
    <?php elseif (empty($itens)): ?>
        <p class="text-sm text-gray-500">Nenhum item na programação por enquanto.</p>
    <?php else: ?>
        <ol class="space-y-3">
            <?php foreach ($itens as $item): ?>
                <li class="rounded-xl border border-gray-200 bg-white p-4 text-sm">
                    <div class="flex flex-wrap justify-between gap-2">
                        <h2 class="font-semibold text-gray-900"><?= htmlspecialchars($item['titulo'] ?? '') ?></h2>
                        <span class="text-xs text-gray-500"><?= htmlspecialchars($item['tipo'] ?? '') ?></span>
                    </div>
                    <p class="text-gray-600 mt-1">
                        <?= !empty($item['hora_inicio']) ? htmlspecialchars(date('d/m H:i', strtotime($item['hora_inicio']))) : '' ?>
                        <?php if (!empty($item['hora_fim'])): ?>
                            – <?= htmlspecialchars(date('H:i', strtotime($item['hora_fim']))) ?>
                        <?php endif; ?>
                        <?php if (!empty($item['local'])): ?>
                            · <?= htmlspecialchars($item['local']) ?>
                        <?php endif; ?>
                        <?php if (!empty($item['setor_nome'])): ?>
                            · <?= htmlspecialchars($item['setor_nome']) ?>
                        <?php endif; ?>
                    </p>
                    <?php if (!empty($item['descricao'])): ?>
                        <p class="mt-2 text-gray-700 whitespace-pre-line"><?= htmlspecialchars($item['descricao']) ?></p>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ol>
    <?php endif; ?>
</div>
