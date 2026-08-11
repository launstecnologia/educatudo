<?php
$limites = $limites ?? [];
$counts = $counts ?? [];
$escola_id = $escola_id ?? 0;

$fields = [
    ['key' => 'max_alunos', 'label' => 'Máx. alunos', 'uso_key' => 'alunos'],
    ['key' => 'max_professores', 'label' => 'Máx. professores', 'uso_key' => 'professores'],
    ['key' => 'max_admins', 'label' => 'Máx. administradores', 'uso_key' => 'admins'],
    ['key' => 'max_storage_mb', 'label' => 'Máx. storage (MB)', 'uso_key' => null],
    ['key' => 'max_tokens_ia_mes', 'label' => 'Máx. tokens IA/mês', 'uso_key' => null],
    ['key' => 'max_custo_ia_mes_usd', 'label' => 'Máx. custo IA/mês (USD)', 'uso_key' => null],
];
$csrf_token = $csrf_token ?? '';
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-6">
    <h3 class="text-lg font-semibold text-slate-800 mb-2">Limites de Uso</h3>
    <p class="text-sm text-slate-600 mb-6">Defina 0 ou deixe em branco para ilimitado.</p>

    <form method="post" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/limites">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="space-y-6">
            <?php foreach ($fields as $f):
                $max = $limites[$f['key']] ?? null;
                $current = ($f['uso_key'] && isset($counts[$f['uso_key']])) ? (float) $counts[$f['uso_key']] : null;
                $unlimited = empty($max);
                $pct = (!$unlimited && $max > 0 && $current !== null) ? min(($current / $max) * 100, 100) : 0;
                if ($pct >= 90) $barColor = 'bg-red-500';
                elseif ($pct >= 70) $barColor = 'bg-amber-500';
                else $barColor = 'bg-green-500';
            ?>
            <div>
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 mb-2">
                    <label class="text-sm font-medium text-slate-700"><?= htmlspecialchars($f['label']) ?></label>
                    <?php if ($current !== null): ?>
                    <span class="text-xs text-slate-500">
                        Uso atual: <strong><?= number_format($current, $f['key'] === 'max_custo_ia_mes_usd' ? 2 : 0, ',', '.') ?></strong>
                        <?php if (!$unlimited): ?>
                            / <?= number_format((float) $max, $f['key'] === 'max_custo_ia_mes_usd' ? 2 : 0, ',', '.') ?>
                        <?php else: ?>
                            (Ilimitado)
                        <?php endif; ?>
                    </span>
                    <?php endif; ?>
                </div>
                <?php if ($current !== null && !$unlimited): ?>
                <div class="w-full bg-gray-200 rounded-full h-2 mb-2">
                    <div class="<?= $barColor ?> h-2 rounded-full transition-all" style="width: <?= round($pct, 1) ?>%"></div>
                </div>
                <?php endif; ?>
                <input type="number" name="<?= htmlspecialchars($f['key']) ?>" value="<?= $unlimited ? '' : htmlspecialchars($max) ?>"
                       placeholder="Ilimitado" step="<?= $f['key'] === 'max_custo_ia_mes_usd' ? '0.01' : '1' ?>" min="0"
                       class="w-full sm:w-48 px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <?php endforeach; ?>
        </div>

        <div class="mt-6">
            <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Salvar</button>
        </div>
    </form>
</div>
