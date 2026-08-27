<?php
$quadro = is_array($quadro_oficial ?? null) ? $quadro_oficial : [];
$ficha = is_array($quadro['ficha'] ?? null) ? $quadro['ficha'] : [];
$grid = is_array($quadro['grid'] ?? null) ? $quadro['grid'] : [];
$periodos = [1 => '1º Bim.', 2 => '2º Bim.', 3 => '3º Bim.', 4 => '4º Bim.', 0 => 'FINAL'];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmt = static function ($c): string {
    if (!is_array($c)) {
        return '—';
    }
    if (!empty($c['conceito'])) {
        return (string) $c['conceito'];
    }
    if ($c['nota'] === null || $c['nota'] === '') {
        return '—';
    }
    return number_format((float) $c['nota'], 1, ',', '');
};
if ($grid === []) {
    return;
}
?>
<div class="mb-8 overflow-x-auto border border-gray-200 rounded-xl bg-white">
    <div class="px-4 py-3 border-b border-gray-100">
        <h2 class="text-lg font-semibold text-gray-900">Boletim do ano</h2>
        <p class="text-sm text-gray-500"><?= (int) ($ficha['ano_letivo'] ?? 0) ?> · <?= $esc($ficha['serie_nome'] ?? $ficha['turma_nome'] ?? '') ?></p>
    </div>
    <table class="min-w-full text-sm">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-3 py-2 text-left text-xs font-medium text-gray-500">Componente</th>
                <?php foreach ([1, 2, 3, 4, 0] as $p): ?>
                    <th class="px-3 py-2 text-center text-xs font-medium text-gray-500" colspan="2"><?= $esc($periodos[$p]) ?></th>
                <?php endforeach; ?>
            </tr>
            <tr>
                <th></th>
                <?php foreach ([1, 2, 3, 4, 0] as $p): ?>
                    <th class="px-2 py-1 text-center text-[10px] text-gray-400">Nota</th>
                    <th class="px-2 py-1 text-center text-[10px] text-gray-400">Falta</th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($grid as $row): ?>
            <tr>
                <td class="px-3 py-2 font-medium"><?= $esc($row['linha']['componente_nome'] ?? '') ?></td>
                <?php foreach ([1, 2, 3, 4, 0] as $p):
                    $c = $row['celulas'][$p] ?? null;
                    $nota = $fmt($c);
                ?>
                    <td class="px-2 py-2 text-center">
                        <?= $esc($nota) ?><?php if (is_array($c) && ($c['origem'] ?? '') === 'externa' && $nota !== '—'): ?><sup class="text-violet-700">¹</sup><?php endif; ?>
                    </td>
                    <td class="px-2 py-2 text-center text-gray-600"><?= isset($c['faltas']) && $c['faltas'] !== null ? (int) $c['faltas'] : '—' ?></td>
                <?php endforeach; ?>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="px-4 py-2 text-xs text-gray-500">¹ Nota da escola de origem. Provas e trabalhos desta escola aparecem mais abaixo, quando houver.</p>
</div>
