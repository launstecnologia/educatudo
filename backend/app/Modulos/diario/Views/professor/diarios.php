<?php
require_once __DIR__ . '/../../Services/ClassDiaryService.php';

use App\Modulos\Diario\Services\ClassDiaryService;

$situacaoClasses = [
    'em_dia' => 'bg-green-100 text-green-800',
    'atencao' => 'bg-amber-100 text-amber-800',
    'atraso' => 'bg-red-100 text-red-800',
];
$filtrosAtivos = 0;
foreach (['ano_letivo', 'turma_id', 'materia_id', 'situacao'] as $chave) {
    if (!empty($filtros[$chave])) {
        $filtrosAtivos++;
    }
}
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Diários de Classe</h1>
        <p class="text-gray-600 mt-1">Acompanhe cada turma + componente curricular: aulas, chamadas e pendências.</p>
    </div>

    <form method="get" action="<?= URL ?>/professor/diarios" class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Ano Letivo</label>
                <select name="ano_letivo" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="0">Todos</option>
                    <?php foreach ($anos_letivos as $ano): ?>
                        <option value="<?= (int) $ano ?>" <?= (int) $filtros['ano_letivo'] === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Turma</label>
                <select name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="0">Todas</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>" <?= (int) $filtros['turma_id'] === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Componente Curricular</label>
                <select name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="0">Todos</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= (int) $materia['id'] ?>" <?= (int) $filtros['materia_id'] === (int) $materia['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $materia['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Situação</label>
                <select name="situacao" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
                    <option value="">Todas</option>
                    <?php foreach (ClassDiaryService::SITUACAO_LABELS as $valor => $label): ?>
                        <option value="<?= $valor ?>" <?= $filtros['situacao'] === $valor ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-purple-600 text-white rounded-lg font-semibold hover:bg-purple-700">
                    Filtrar<?php if ($filtrosAtivos > 0): ?> (<?= $filtrosAtivos ?>)<?php endif; ?>
                </button>
            </div>
        </div>
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-3">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Período de</label>
                <input type="date" name="inicio" value="<?= htmlspecialchars($filtros['inicio']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">até</label>
                <input type="date" name="fim" value="<?= htmlspecialchars($filtros['fim']) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg">
            </div>
        </div>
    </form>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <?php foreach (['Turma', 'Componente Curricular', 'Aulas', 'Chamadas', 'Pendências', 'Situação', ''] as $header): ?>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($header) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($diarios)): ?>
                        <tr>
                            <td colspan="7" class="px-6 py-12 text-center text-gray-500">
                                <i class="fa-regular fa-clipboard text-4xl text-gray-300 mb-4"></i>
                                <p>Nenhum diário encontrado para os filtros selecionados.</p>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($diarios as $d): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $d['turma_nome']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) $d['materia_nome']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $d['aulas_finalizadas'] ?>/<?= (int) $d['aulas_previstas'] ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $d['aulas_registradas'] ?>/<?= (int) $d['aulas_previstas_ate_hoje'] ?></td>
                                <td class="px-6 py-4 text-sm text-gray-700"><?= (int) $d['chamadas_pendentes'] ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $situacaoClasses[$d['situacao']] ?? 'bg-slate-100 text-slate-700' ?>">
                                        <?= htmlspecialchars(ClassDiaryService::situacaoLabel((string) $d['situacao'])) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-right">
                                    <a href="<?= URL ?>/professor/diarios/abrir?turma_id=<?= (int) $d['turma_id'] ?>&materia_id=<?= (int) $d['materia_id'] ?>"
                                       class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-semibold bg-purple-50 text-purple-700 hover:bg-purple-100">
                                        Abrir Diário
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
