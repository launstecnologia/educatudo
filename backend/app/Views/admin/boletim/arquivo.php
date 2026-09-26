<?php
$eventos = $eventos ?? [];
if (!class_exists('PeriodoLetivo')) {
    require_once __DIR__ . '/../../../Core/PeriodoLetivo.php';
}
$bimestreLabel = static function ($bimestre, $ano = 0) {
    $bimestre = (int) $bimestre;
    $ano = (int) $ano;
    if ($bimestre <= 0) {
        return '—';
    }
    $lab = PeriodoLetivo::rotulo($ano > 0 ? $ano : (int) date('Y'), $bimestre);

    return $lab !== '' ? $lab : '—';
};
?>

<div class="mb-8">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Arquivo de fórmulas</h2>
            <p class="text-gray-600">Eventos antigos de Boletim e de Notas, um por linha. Abra para ver a fórmula no configurador antigo. Esta página vai sair.</p>
        </div>
        <a href="<?= URL ?>/admin/boletim" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<div class="mb-6 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950">
    A lista de Avaliações mostra só os eventos de Notas. Aqui entram também os de Boletim, com o mesmo nome do relatório da coordenação.
</div>

<div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Evento</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Séries</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano letivo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Bimestre</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($eventos === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhum evento antigo encontrado.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($eventos as $evento): ?>
                <?php
                $eventoId = (int) ($evento['id'] ?? 0);
                $tipoNotas = (string) ($evento['tipo_label'] ?? '') === 'Notas';
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4">
                        <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($evento['nome_exibicao'] ?? $evento['nome'] ?? '')) ?></div>
                        <?php if (!empty($evento['codigo'])): ?>
                        <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) $evento['codigo']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $tipoNotas ? 'bg-indigo-100 text-indigo-800' : 'bg-emerald-100 text-emerald-800' ?>">
                            <?= htmlspecialchars((string) ($evento['tipo_label'] ?? '')) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4">
                        <?php $seriesNomes = $evento['series_nomes'] ?? []; ?>
                        <?php if ($seriesNomes === []): ?>
                            <span class="text-sm text-gray-500">Todas as séries</span>
                        <?php else: ?>
                            <div class="flex flex-wrap gap-1 max-w-xs">
                                <?php foreach ($seriesNomes as $serieNome): ?>
                                    <span class="inline-flex px-2 py-0.5 text-xs font-medium rounded-full bg-gray-100 text-gray-700"><?= htmlspecialchars((string) $serieNome) ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?= (int) ($evento['ano_letivo'] ?? 0) > 0 ? (int) $evento['ano_letivo'] : '—' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700">
                        <?= htmlspecialchars($bimestreLabel($evento['bimestre'] ?? 0, $evento['ano_letivo'] ?? 0)) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <a href="<?= URL ?>/admin/boletim-configuracao?regra_id=<?= $eventoId ?>&amp;arquivo=1"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg border border-gray-300 text-sm font-medium text-gray-700 bg-white hover:bg-gray-50">
                            Ver fórmula
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
