<?php
$jornadas = is_array($jornadas ?? null) ? $jornadas : [];
$kpis = is_array($kpis_jornadas ?? null) ? $kpis_jornadas : [];
$filtroAnoLetivo = isset($filtro_ano_letivo) ? (int) $filtro_ano_letivo : 0;
$filtroBimestre = isset($filtro_bimestre) ? (int) $filtro_bimestre : 0;
$anosDisponiveis = is_array($anos_disponiveis ?? null) ? $anos_disponiveis : [];
$totalAlt = 0;
$totalAltFeitas = 0;
$totalAltAcertos = 0;
$totalAltErros = 0;
foreach ($jornadas as $jCalc) {
    $totalAlt += (int) ($jCalc['total_exercicios_alternativa'] ?? 0);
    $totalAltFeitas += (int) ($jCalc['exercicios_alternativa_feitos'] ?? 0);
    $totalAltAcertos += (int) ($jCalc['exercicios_alternativa_acertos'] ?? 0);
    $totalAltErros += (int) ($jCalc['exercicios_alternativa_erros'] ?? 0);
}
?>
<style>
    .safari-filter-field {
        min-height: 42px;
        font-size: 16px; /* evita zoom automático no iOS Safari */
        line-height: 1.2;
    }

    .safari-filter-select {
        -webkit-appearance: none;
        appearance: none;
        background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' viewBox='0 0 20 20' fill='none'%3E%3Cpath d='M5 7.5L10 12.5L15 7.5' stroke='%236b7280' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
        background-repeat: no-repeat;
        background-position: right 0.75rem center;
        background-size: 16px 16px;
        padding-right: 2.2rem;
    }
</style>
<div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
    <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($filho['nome'] ?? '') ?></h2>
    <p class="text-gray-600 mb-6">Detalhamento completo das jornadas: fez/não fez, conclusão e progresso.</p>

    <form method="get" class="grid grid-cols-1 md:grid-cols-4 gap-3 mb-6">
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Ano letivo</label>
            <select name="ano_letivo" class="safari-filter-field safari-filter-select w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">Todos</option>
                <?php foreach ($anosDisponiveis as $anoOpt): ?>
                    <option value="<?= (int) $anoOpt ?>" <?= $filtroAnoLetivo === (int) $anoOpt ? 'selected' : '' ?>><?= (int) $anoOpt ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Bimestre</label>
            <select name="bimestre" class="safari-filter-field safari-filter-select w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="">Todos</option>
                <option value="1" <?= $filtroBimestre === 1 ? 'selected' : '' ?>>1º bimestre</option>
                <option value="2" <?= $filtroBimestre === 2 ? 'selected' : '' ?>>2º bimestre</option>
                <option value="3" <?= $filtroBimestre === 3 ? 'selected' : '' ?>>3º bimestre</option>
                <option value="4" <?= $filtroBimestre === 4 ? 'selected' : '' ?>>4º bimestre</option>
            </select>
        </div>
        <div class="md:col-span-2 flex items-end gap-2">
            <button type="submit" class="px-4 py-2 rounded-lg bg-blue-600 text-white text-sm font-medium hover:bg-blue-700">Filtrar</button>
            <a href="<?= URL ?>/pais/filhos/<?= (int) ($filho['id'] ?? 0) ?>/jornadas" class="px-4 py-2 rounded-lg border border-gray-300 text-gray-700 text-sm font-medium hover:bg-gray-50">Limpar</a>
        </div>
    </form>

    <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
        <div class="rounded-lg border border-gray-200 p-3"><p class="text-xs text-gray-500">Total</p><p class="text-xl font-bold"><?= (int) ($kpis['total'] ?? 0) ?></p></div>
        <div class="rounded-lg border border-green-200 bg-green-50 p-3"><p class="text-xs text-green-700">Fez</p><p class="text-xl font-bold text-green-800"><?= (int) ($kpis['feitas'] ?? 0) ?></p></div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-3"><p class="text-xs text-red-700">Não fez</p><p class="text-xl font-bold text-red-800"><?= (int) ($kpis['nao_feitas'] ?? 0) ?></p></div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3"><p class="text-xs text-blue-700">Concluídas</p><p class="text-xl font-bold text-blue-800"><?= (int) ($kpis['concluidas'] ?? 0) ?></p></div>
        <div class="rounded-lg border border-purple-200 bg-purple-50 p-3"><p class="text-xs text-purple-700">Taxa conclusão</p><p class="text-xl font-bold text-purple-800"><?= (int) ($kpis['taxa_conclusao'] ?? 0) ?>%</p></div>
    </div>
    <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-6">
        <div class="rounded-lg border p-3"><p class="text-xs text-gray-500">Exercícios alternativas</p><p class="text-xl font-bold"><?= (int) $totalAlt ?></p></div>
        <div class="rounded-lg border border-blue-200 bg-blue-50 p-3"><p class="text-xs text-blue-700">Feitos</p><p class="text-xl font-bold text-blue-800"><?= (int) $totalAltFeitas ?></p></div>
        <div class="rounded-lg border border-green-200 bg-green-50 p-3"><p class="text-xs text-green-700">Acertos</p><p class="text-xl font-bold text-green-800"><?= (int) $totalAltAcertos ?></p></div>
        <div class="rounded-lg border border-red-200 bg-red-50 p-3"><p class="text-xs text-red-700">Erros</p><p class="text-xl font-bold text-red-800"><?= (int) $totalAltErros ?></p></div>
    </div>

    <?php if (empty($jornadas)): ?>
        <div class="text-center py-12"><p class="text-gray-500">Nenhuma jornada encontrada</p></div>
    <?php else: ?>
        <div class="overflow-x-auto border border-gray-200 rounded-lg">
            <table class="min-w-full text-sm">
                <thead class="bg-gray-100 text-gray-700">
                    <tr>
                        <th class="px-3 py-2 text-left">Jornada</th>
                        <th class="px-3 py-2 text-center min-w-[120px]">Status</th>
                        <th class="px-3 py-2 text-center">Exercício</th>
                        <th class="px-3 py-2 text-center">Acertos</th>
                        <th class="px-3 py-2 text-center">Erro</th>
                        <th class="px-3 py-2 text-center">Score</th>
                        <th class="px-3 py-2 text-center">Criada em</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($jornadas as $jornada): ?>
                        <?php
                        $status = !empty($jornada['concluiu']) ? 'Concluída' : (!empty($jornada['fez']) ? 'Em andamento' : (!empty($jornada['expirada']) ? 'Expirado' : 'Não iniciada'));
                        $statusClass = !empty($jornada['concluiu'])
                            ? 'bg-green-100 text-green-800'
                            : (!empty($jornada['fez'])
                                ? 'bg-amber-100 text-amber-800'
                                : (!empty($jornada['expirada']) ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-700'));
                        $professorMateria = trim((string) ($jornada['professor_nome'] ?? ''));
                        $materia = trim((string) ($jornada['materia_nome'] ?? ''));
                        if ($professorMateria !== '' && $materia !== '') {
                            $professorMateria .= ' - ' . $materia;
                        } elseif ($materia !== '') {
                            $professorMateria = $materia;
                        }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-3 py-2">
                                <div class="font-medium text-gray-900"><?= htmlspecialchars($jornada['titulo'] ?? 'Jornada') ?></div>
                                <div class="text-sm text-gray-600 mt-1"><?= htmlspecialchars($professorMateria !== '' ? $professorMateria : 'Professor/Matéria não informados') ?></div>
                            </td>
                            <td class="px-3 py-2 text-center min-w-[120px]">
                                <span class="inline-flex items-center justify-center px-2 py-1 text-xs rounded-full whitespace-nowrap leading-none <?= $statusClass ?>">
                                    <?= $status ?>
                                </span>
                            </td>
                            <td class="px-3 py-2 text-center text-gray-900"><?= (int) ($jornada['exercicios_alternativa_feitos'] ?? 0) ?>/<?= (int) ($jornada['total_exercicios_alternativa'] ?? 0) ?></td>
                            <td class="px-3 py-2 text-center text-green-700 font-semibold"><?= (int) ($jornada['exercicios_alternativa_acertos'] ?? 0) ?></td>
                            <td class="px-3 py-2 text-center text-red-700 font-semibold"><?= (int) ($jornada['exercicios_alternativa_erros'] ?? 0) ?></td>
                            <td class="px-3 py-2 text-center text-gray-900"><?= (int) ($jornada['percentual_exercicios_alternativa_acerto'] ?? 0) ?>%</td>
                            <td class="px-3 py-2 text-center text-gray-600"><?= !empty($jornada['created_at']) ? date('d/m/Y', strtotime($jornada['created_at'])) : '—' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>
