<?php
$situacaoLabels = ['em_dia' => 'Em dia', 'atencao' => 'Atenção', 'atraso' => 'Em atraso'];
$situacaoClasses = [
    'em_dia' => 'bg-green-100 text-green-800',
    'atencao' => 'bg-amber-100 text-amber-800',
    'atraso' => 'bg-red-100 text-red-800',
];
$situacaoDot = ['em_dia' => 'bg-green-500', 'atencao' => 'bg-amber-500', 'atraso' => 'bg-red-500'];
$resumo = $resumo ?? [];
$queryExport = http_build_query(['inicio' => $inicio, 'fim' => $fim, 'professor_id' => $professor_id]);
?>
<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/diario"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Indicadores do Diário</h2>
            <p class="text-sm text-gray-600">Acompanhe aulas previstas, ministradas e a situação de cada professor por turma e matéria.</p>
        </div>
    </div>
</div>

<form method="get" action="<?= URL ?>/admin/diario/indicadores"
      class="bg-white rounded-xl shadow-sm border border-gray-200 p-4 mb-6">
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <div>
            <label for="inicio" class="block text-sm font-medium text-gray-700 mb-2">Data inicial</label>
            <input type="date" id="inicio" name="inicio" value="<?= htmlspecialchars($inicio) ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div>
            <label for="fim" class="block text-sm font-medium text-gray-700 mb-2">Data final</label>
            <input type="date" id="fim" name="fim" value="<?= htmlspecialchars($fim) ?>"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div>
            <label for="professor_id" class="block text-sm font-medium text-gray-700 mb-2">Professor</label>
            <select id="professor_id" name="professor_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="0">Todos os professores</option>
                <?php foreach ($professores as $professor): ?>
                    <option value="<?= (int) $professor['id'] ?>" <?= $professor_id === (int) $professor['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $professor['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex flex-wrap gap-2">
            <button type="submit" class="inline-flex items-center justify-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm">
                <i class="fa-solid fa-filter mr-2"></i> Aplicar
            </button>
            <a href="<?= URL ?>/admin/diario/relatorio/pdf?<?= htmlspecialchars($queryExport) ?>"
               class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-file-pdf mr-2 text-red-500"></i> PDF
            </a>
            <a href="<?= URL ?>/admin/diario/relatorio/excel?<?= htmlspecialchars($queryExport) ?>"
               class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-file-excel mr-2 text-green-600"></i> Excel
            </a>
        </div>
    </div>
</form>

<div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4 mb-6">
    <?php
    $cards = [
        ['label' => 'Linhas', 'value' => (int) ($resumo['total'] ?? 0), 'color' => 'text-gray-900'],
        ['label' => 'Em dia', 'value' => (int) ($resumo['em_dia'] ?? 0), 'color' => 'text-green-600'],
        ['label' => 'Atenção', 'value' => (int) ($resumo['atencao'] ?? 0), 'color' => 'text-amber-600'],
        ['label' => 'Em atraso', 'value' => (int) ($resumo['atraso'] ?? 0), 'color' => 'text-red-600'],
        ['label' => 'Cobertura média', 'value' => $resumo['cobertura_media'] !== null ? number_format((float) $resumo['cobertura_media'], 1, ',', '') . '%' : '-', 'color' => 'text-gray-900'],
        ['label' => 'Horas (min./prev.)', 'value' => number_format((float) ($resumo['horas_ministradas'] ?? 0), 1, ',', '') . ' / ' . number_format((float) ($resumo['horas_previstas'] ?? 0), 1, ',', ''), 'color' => 'text-gray-900'],
    ];
    foreach ($cards as $card): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-4">
            <p class="text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($card['label']) ?></p>
            <p class="mt-1 text-2xl font-bold <?= $card['color'] ?>"><?= htmlspecialchars((string) $card['value']) ?></p>
        </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <?php foreach (['Professor', 'Turma', 'Matéria', 'Previstas', 'Ministradas', 'Pendentes', 'Cobertura', 'Última atualização', 'Situação'] as $header): ?>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($header) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($indicadores)): ?>
                    <tr>
                        <td colspan="9" class="px-6 py-12 text-center text-gray-500">
                            <i class="fa-regular fa-chart-bar text-4xl text-gray-300 mb-4"></i>
                            <p>Nenhuma aula prevista na grade horária para o período.</p>
                        </td>
                    </tr>
                <?php else: foreach ($indicadores as $i):
                    $sit = (string) ($i['situacao'] ?? 'em_dia'); ?>
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $i['professor_nome']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700"><?= htmlspecialchars((string) $i['turma_nome']) ?></td>
                        <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) $i['materia_nome']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= (int) $i['aulas_previstas'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= (int) $i['aulas_ministradas'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm <?= (int) $i['pendentes_vencidas'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-500' ?>"><?= (int) $i['pendentes_vencidas'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $i['percentual'] !== null ? number_format((float) $i['percentual'], 1, ',', '') . '%' : '<span class="text-gray-400">—</span>' ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= $i['ultima_data'] ? date('d/m/Y H:i', strtotime((string) $i['ultima_data'])) : '<span class="text-gray-400">—</span>' ?></td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex items-center gap-1.5 px-2 py-1 text-xs font-semibold rounded-full <?= $situacaoClasses[$sit] ?? 'bg-slate-100 text-slate-700' ?>">
                                <span class="w-1.5 h-1.5 rounded-full <?= $situacaoDot[$sit] ?? 'bg-slate-400' ?>"></span>
                                <?= htmlspecialchars($situacaoLabels[$sit] ?? $sit) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>
</div>
