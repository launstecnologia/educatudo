<?php
$quadro = is_array($quadro ?? ($quadro_notas_semanais ?? null)) ? ($quadro ?? $quadro_notas_semanais) : [];
$tabelas = is_array($quadro['tabelas'] ?? null) ? $quadro['tabelas'] : [];
$bimestreAtual = (int) ($quadro['bimestre'] ?? 0);
$bimestres = is_array($quadro['bimestres_disponiveis'] ?? null) ? $quadro['bimestres_disponiveis'] : [];
$baseQs = $_GET;
unset($baseQs['bimestre']);

$fmtNota = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return number_format((float) $v, 1, ',', '');
};

$fmtQtd = static function ($v): string {
    if ($v === null || $v === '') {
        return '—';
    }
    return (string) (int) $v;
};

$colunasFinais = [
    ['key' => 'media_sem', 'label' => 'Média Sem', 'valor10' => true],
    ['key' => 'prova_bim', 'label' => 'Prova Bim', 'valor10' => true],
    ['key' => 'enac', 'label' => 'ENAC', 'valor10' => true],
    ['key' => 'participacao', 'label' => 'Part', 'valor10' => true],
    ['key' => 'trabalho', 'label' => 'Trab', 'valor10' => true],
    ['key' => 'media_bim', 'label' => 'Média Bim', 'valor10' => true],
    ['key' => 'recuperacao', 'label' => 'Rec', 'valor10' => true],
    ['key' => 'media_bim_final', 'label' => 'Média Bim Final', 'valor10' => true],
];
?>

<?php if (empty($quadro['tem_dados'])): ?>
    <div class="text-center py-10 bg-gray-50 rounded-lg border border-gray-200">
        <p class="text-gray-500">Nenhuma nota no quadro semanal ainda.</p>
        <p class="text-sm text-gray-400 mt-1">Quando a escola marcar a semana (S1–S8) no evento de prova, as notas aparecem aqui.</p>
    </div>
<?php else: ?>
    <?php if ($bimestres !== []): ?>
        <div class="flex flex-wrap gap-2 mb-4">
            <?php foreach ($bimestres as $bim):
                $qs = $baseQs;
                $qs['secao'] = $secaoNotas ?? 'notas';
                $qs['bimestre'] = (int) $bim;
                $href = ($baseUrlNotas ?? (URL . '/notas-boletins')) . '?' . http_build_query($qs);
            ?>
                <a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"
                   class="px-3 py-1.5 rounded-lg text-sm font-medium <?= $bimestreAtual === (int) $bim ? 'bg-teal-700 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' ?>">
                    <?= (int) $bim ?>º bimestre
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <div class="space-y-8">
        <?php foreach ($tabelas as $tab):
            $semanas = is_array($tab['semanas'] ?? null) ? $tab['semanas'] : [];
            $materias = is_array($tab['materias'] ?? null) ? $tab['materias'] : [];
            if ($materias === []) {
                continue;
            }
        ?>
            <div class="overflow-x-auto border border-gray-300 rounded-lg bg-white">
                <?php if (!empty($tab['subtitulo'])): ?>
                    <div class="px-3 py-2 text-sm font-semibold text-gray-800 bg-gray-50 border-b border-gray-200">
                        <?= htmlspecialchars((string) $tab['subtitulo'], ENT_QUOTES, 'UTF-8') ?>
                    </div>
                <?php endif; ?>
                <table class="min-w-full border-collapse text-xs sm:text-sm text-center">
                    <thead>
                        <tr class="bg-teal-700 text-white">
                            <th rowspan="2" class="border border-teal-800 px-3 py-2 text-left font-semibold align-middle whitespace-nowrap">
                                <?= htmlspecialchars((string) ($tab['titulo'] ?? 'Matérias'), ENT_QUOTES, 'UTF-8') ?>
                            </th>
                            <?php foreach ($semanas as $s): ?>
                                <th colspan="2" class="border border-teal-800 px-2 py-1 font-semibold">S<?= (int) $s ?></th>
                            <?php endforeach; ?>
                            <th colspan="2" class="border border-teal-800 px-2 py-1 font-semibold">Total</th>
                            <?php foreach ($colunasFinais as $col): ?>
                                <th rowspan="2" class="border border-teal-800 px-2 py-1 font-semibold align-middle leading-tight">
                                    <?= htmlspecialchars($col['label'], ENT_QUOTES, 'UTF-8') ?>
                                    <?php if (!empty($col['valor10'])): ?>
                                        <div class="text-[10px] font-normal opacity-80">Valor 10</div>
                                    <?php endif; ?>
                                </th>
                            <?php endforeach; ?>
                        </tr>
                        <tr class="bg-teal-600 text-white">
                            <?php foreach ($semanas as $_s): ?>
                                <th class="border border-teal-800 px-1 py-1 font-semibold" title="Questões certas">N</th>
                                <th class="border border-teal-800 px-1 py-1 font-semibold" title="Total de questões">Q</th>
                            <?php endforeach; ?>
                            <th class="border border-teal-800 px-1 py-1 font-semibold" title="Total de acertos">N</th>
                            <th class="border border-teal-800 px-1 py-1 font-semibold" title="Total de questões">Q</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 0; foreach ($materias as $linha): $i++; ?>
                            <tr class="<?= $i % 2 === 0 ? 'bg-gray-50' : 'bg-white' ?>">
                                <td class="border border-gray-300 px-3 py-1.5 text-left font-semibold text-gray-900 uppercase whitespace-nowrap">
                                    <?= htmlspecialchars((string) ($linha['materia'] ?? '—'), ENT_QUOTES, 'UTF-8') ?>
                                </td>
                                <?php foreach ($semanas as $s):
                                    $cel = $linha['semanas'][$s] ?? $linha['semanas'][(int) $s] ?? [];
                                ?>
                                    <td class="border border-gray-300 px-1 py-1 tabular-nums text-gray-800"><?= htmlspecialchars($fmtQtd($cel['n'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="border border-gray-300 px-1 py-1 tabular-nums text-gray-600"><?= htmlspecialchars($fmtQtd($cel['q'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php endforeach; ?>
                                <?php $tot = $linha['total'] ?? []; ?>
                                <td class="border border-gray-300 px-1 py-1 tabular-nums font-semibold"><?= htmlspecialchars($fmtQtd($tot['n'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="border border-gray-300 px-1 py-1 tabular-nums font-semibold"><?= htmlspecialchars($fmtQtd($tot['q'] ?? null), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php foreach ($colunasFinais as $col): ?>
                                    <td class="border border-gray-300 px-1 py-1 tabular-nums <?= in_array($col['key'], ['media_bim', 'media_bim_final'], true) ? 'font-semibold text-gray-900' : 'text-gray-800' ?>">
                                        <?= htmlspecialchars($fmtNota($linha[$col['key']] ?? null), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
