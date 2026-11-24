<?php

use App\Modulos\Diario\Services\ClassDiaryService;

$situacaoClasses = [
    'em_dia' => 'bg-green-100 text-green-800',
    'atencao' => 'bg-amber-100 text-amber-800',
    'atraso' => 'bg-red-100 text-red-800',
];
$inicioPadrao = date('Y-01-01');
$fimPadrao = date('Y-12-31');
$filtrosAtivos = 0;
foreach (['ano_letivo', 'turma_id', 'materia_id', 'situacao'] as $chave) {
    if (!empty($filtros[$chave])) {
        $filtrosAtivos++;
    }
}
if (($filtros['inicio'] ?? '') !== '' && ($filtros['inicio'] ?? '') !== $inicioPadrao) {
    $filtrosAtivos++;
}
if (($filtros['fim'] ?? '') !== '' && ($filtros['fim'] ?? '') !== $fimPadrao) {
    $filtrosAtivos++;
}
$limparUrl = URL . '/professor/diarios';
$campoFiltro = 'w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500';
?>
<div class="max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Diários de Classe</h1>
            <p class="text-gray-600 mt-1">Acompanhe cada turma + componente curricular: aulas, chamadas e pendências.</p>
        </div>
        <button type="button" data-csp-click="openFilterDrawer"
                class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
    </div>

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
                                        <?= htmlspecialchars(\App\Modulos\Diario\Services\ClassDiaryService::situacaoLabel((string) $d['situacao'])) ?>
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

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" data-csp-click="closeFilterDrawer"></div>
<aside id="filterDrawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col" aria-hidden="true" role="dialog" aria-labelledby="filterDrawerTitle">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <div>
            <h3 id="filterDrawerTitle" class="text-lg font-semibold text-gray-900">Filtrar diários</h3>
            <p class="mt-0.5 text-xs text-gray-500">Refine ano, turma, componente, situação ou período.</p>
        </div>
        <button type="button" data-csp-click="closeFilterDrawer" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar filtros"><i class="fa-solid fa-xmark text-xl"></i></button>
    </div>
    <form method="get" action="<?= URL ?>/professor/diarios" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_ano" class="block text-sm font-medium text-gray-700 mb-1.5">Ano letivo</label>
                <select id="filtro_ano" name="ano_letivo" class="<?= $campoFiltro ?>">
                    <option value="0">Todos</option>
                    <?php foreach ($anos_letivos as $ano): ?>
                        <option value="<?= (int) $ano ?>" <?= (int) $filtros['ano_letivo'] === (int) $ano ? 'selected' : '' ?>><?= (int) $ano ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_turma" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="filtro_turma" name="turma_id" class="<?= $campoFiltro ?>">
                    <option value="0">Todas</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= (int) $turma['id'] ?>" <?= (int) $filtros['turma_id'] === (int) $turma['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $turma['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_materia" class="block text-sm font-medium text-gray-700 mb-1.5">Componente curricular</label>
                <select id="filtro_materia" name="materia_id" class="<?= $campoFiltro ?>">
                    <option value="0">Todos</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= (int) $materia['id'] ?>" <?= (int) $filtros['materia_id'] === (int) $materia['id'] ? 'selected' : '' ?>><?= htmlspecialchars((string) $materia['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_situacao" class="block text-sm font-medium text-gray-700 mb-1.5">Situação</label>
                <select id="filtro_situacao" name="situacao" class="<?= $campoFiltro ?>">
                    <option value="">Todas</option>
                    <?php foreach (ClassDiaryService::SITUACAO_LABELS as $valor => $label): ?>
                        <option value="<?= htmlspecialchars((string) $valor) ?>" <?= $filtros['situacao'] === $valor ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_inicio" class="block text-sm font-medium text-gray-700 mb-1.5">Período de</label>
                <input type="date" id="filtro_inicio" name="inicio" value="<?= htmlspecialchars((string) $filtros['inicio']) ?>" class="<?= $campoFiltro ?>">
            </div>
            <div>
                <label for="filtro_fim" class="block text-sm font-medium text-gray-700 mb-1.5">até</label>
                <input type="date" id="filtro_fim" name="fim" value="<?= htmlspecialchars((string) $filtros['fim']) ?>" class="<?= $campoFiltro ?>">
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= htmlspecialchars($limparUrl) ?>" class="flex-1 inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">Limpar</a>
            <button type="submit" class="flex-1 px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors">Aplicar filtros</button>
        </div>
    </form>
</aside>

<script <?= $csp_nonce_attr ?? \App\Core\CspPolitica::attrNonce() ?>>
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') closeFilterDrawer();
});
</script>
