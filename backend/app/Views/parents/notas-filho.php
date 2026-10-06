<?php
$filho = $filho ?? [];
$filtroAnoLetivo = isset($filtro_ano_letivo) ? (int) $filtro_ano_letivo : 0;
$filtroBimestre = isset($filtro_bimestre) ? (int) $filtro_bimestre : 0;
$anosDisponiveis = is_array($anos_disponiveis ?? null) ? $anos_disponiveis : [];
$baseUrlNotas = URL . '/pais/filhos/' . (int) ($filho['id'] ?? 0) . '/notas';
?>

<div class="max-w-7xl mx-auto p-6">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Provas do aluno</h1>
        <p class="text-gray-600">
            Visualize as provas online de <?= htmlspecialchars((string) ($filho['nome'] ?? 'seu filho'), ENT_QUOTES, 'UTF-8') ?>.
        </p>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6">
        <form method="get" action="<?= htmlspecialchars($baseUrlNotas, ENT_QUOTES, 'UTF-8') ?>" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
            <input type="hidden" name="secao" value="provas">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Ano letivo</label>
                <select name="ano_letivo" data-periodo-ano class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <option value="">Todos</option>
                    <?php foreach ($anosDisponiveis as $anoOpt): ?>
                        <option value="<?= (int) $anoOpt ?>" <?= $filtroAnoLetivo === (int) $anoOpt ? 'selected' : '' ?>><?= (int) $anoOpt ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1" data-periodo-label>Bimestre</label>
                <select name="bimestre" data-periodo-letivo-select data-periodo-todos="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                    <?php
                    if (!class_exists('PeriodoLetivo')) {
                        require_once __DIR__ . '/../../../Core/PeriodoLetivo.php';
                    }
                    $anoFiltroPais = $filtroAnoLetivo > 0 ? $filtroAnoLetivo : (int) date('Y');
                    echo PeriodoLetivo::optionsHtml($anoFiltroPais, $filtroBimestre, ['todos' => true]);
                    ?>
                </select>
            </div>
            <div class="md:col-span-2 flex items-end gap-2">
                <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">Filtrar</button>
                <a href="<?= htmlspecialchars($baseUrlNotas, ENT_QUOTES, 'UTF-8') ?>" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">Limpar</a>
            </div>
        </form>

        <?php require __DIR__ . '/../partials/provas_matriz_blocos.php'; ?>
    </div>

    <?php if (!empty($boletins_gerados_notas) || !empty($boletins_gerados_boletim)): ?>
        <div class="bg-white rounded-2xl shadow-sm border border-gray-200 p-6 mt-6 space-y-8">
            <?php if (!empty($boletins_gerados_notas)): ?>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Demonstrativo de Notas</h2>
                    <?php
                    $boletins_gerados = $boletins_gerados_notas;
                    $boletim_pode_excluir = false;
                    require __DIR__ . '/../partials/boletins_gerados.php';
                    ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($boletins_gerados_boletim)): ?>
                <div>
                    <h2 class="text-lg font-semibold text-gray-900 mb-3">Boletim</h2>
                    <?php
                    $boletins_gerados = $boletins_gerados_boletim;
                    $boletim_pode_excluir = false;
                    require __DIR__ . '/../partials/boletins_gerados.php';
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
