<?php
$periodos = is_array($notas_eventos_ao_vivo ?? null) ? $notas_eventos_ao_vivo : [];
if ($periodos === []) {
    return;
}
?>
<div class="space-y-4 mb-6">
    <?php foreach ($periodos as $periodo): ?>
        <?php
        if (!is_array($periodo)) {
            continue;
        }
        $colunas = is_array($periodo['colunas'] ?? null) ? $periodo['colunas'] : [];
        $linhas = is_array($periodo['linhas'] ?? null) ? $periodo['linhas'] : [];
        ?>
        <section class="bg-white rounded-2xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars((string) ($periodo['titulo'] ?? 'Notas'), ENT_QUOTES, 'UTF-8') ?></h2>
                <p class="text-sm text-gray-500 mt-1">Notas já lançadas. A média deste período aparece quando o boletim for gerado.</p>
            </div>
            <?php if ($linhas === []): ?>
                <p class="px-6 py-5 text-sm text-gray-500">Nenhuma nota lançada ainda.</p>
            <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                            <?php foreach ($colunas as $coluna): ?>
                                <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars((string) ($coluna['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-100">
                        <?php foreach ($linhas as $linha): ?>
                            <?php $notasLinha = is_array($linha['notas'] ?? null) ? $linha['notas'] : []; ?>
                            <tr>
                                <td class="px-4 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($linha['materia'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <?php foreach ($colunas as $coluna): ?>
                                    <?php $nota = (string) ($notasLinha[(int) ($coluna['id'] ?? 0)] ?? ''); ?>
                                    <td class="px-4 py-3 text-sm text-center text-gray-800"><?= $nota !== '' ? htmlspecialchars($nota, ENT_QUOTES, 'UTF-8') : '' ?></td>
                                <?php endforeach; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>
