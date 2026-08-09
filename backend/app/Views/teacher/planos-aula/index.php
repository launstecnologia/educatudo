<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

<?php
$filtroDescricao = trim((string) ($filtros['descricao'] ?? ''));
$filtroTurmaId = (string) ($filtros['turma_id'] ?? '');
$filtroMateriaId = (string) ($filtros['materia_id'] ?? '');
$filtroDataInicio = trim((string) ($filtros['data_inicio'] ?? ''));
$filtroDataFim = trim((string) ($filtros['data_fim'] ?? ''));
$pagination = $pagination ?? [
    'current_page' => 1,
    'total_pages' => 1,
    'total_items' => (int) ($stats['total'] ?? 0),
    'per_page' => 10,
    'has_prev' => false,
    'has_next' => false,
    'prev_page' => 1,
    'next_page' => 1,
];
$filtrosAtivosCount = 0;
foreach ([$filtroDescricao, $filtroTurmaId, $filtroMateriaId, $filtroDataInicio, $filtroDataFim] as $filtroValor) {
    if ((string) $filtroValor !== '') {
        $filtrosAtivosCount++;
    }
}

$pagTotal = (int) ($pagination['total_items'] ?? 0);
$pagPerPage = (int) ($pagination['per_page'] ?? 10);
$pagPage = (int) ($pagination['current_page'] ?? 1);
$pagTotalPages = (int) ($pagination['total_pages'] ?? 1);
$itensLabel = $pagTotal === 1 ? 'item' : 'itens';
$queryParams = array_filter([
    'descricao' => $filtroDescricao,
    'turma_id' => $filtroTurmaId,
    'materia_id' => $filtroMateriaId,
    'data_inicio' => $filtroDataInicio,
    'data_fim' => $filtroDataFim,
], static fn($v) => $v !== '' && $v !== null);
$baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
$sep = $baseQuery === '' ? '?' : '&';

$formatarDatasPlano = static function ($dataAula): string {
    $datas = [];
    if (!empty($dataAula)) {
        $datasJson = json_decode((string) $dataAula, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($datasJson)) {
            $datas = $datasJson;
        } else {
            $datas = [(string) $dataAula];
        }
    }

    $datasFormatadas = [];
    foreach ($datas as $dataItem) {
        $tsData = strtotime((string) $dataItem);
        if ($tsData !== false) {
            $datasFormatadas[] = date('d/m/Y', $tsData);
        }
    }

    return !empty($datasFormatadas) ? implode(', ', $datasFormatadas) : 'N/A';
};

$statusPlano = static function ($status): array {
    $status = (string) ($status ?? 'rascunho');
    $map = [
        'rascunho' => ['Rascunho', 'bg-slate-100 text-slate-700'],
        'pendente' => ['Pendente', 'bg-amber-100 text-amber-800'],
        'enviado' => ['Enviado', 'bg-blue-100 text-blue-800'],
        'aprovado' => ['Aprovado', 'bg-green-100 text-green-800'],
        'rejeitado' => ['Rejeitado', 'bg-red-100 text-red-800'],
    ];

    return $map[$status] ?? [ucfirst(str_replace('_', ' ', $status)), 'bg-slate-100 text-slate-700'];
};
?>

<div class="mb-6">
    <div class="flex flex-wrap justify-between items-start gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Meus Planos de Aula</h2>
            <p class="text-gray-600 text-sm">Gerencie seus planos de aula.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <button type="button"
                    onclick="abrirFiltroPlanos()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter text-gray-500 mr-2"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                    <span class="ml-2 min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold inline-flex items-center justify-center"><?= (int) $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>

            <a href="<?= URL ?>/professor/planos-aula/criar"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors hover:opacity-90 shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Plano
            </a>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
        <h3 class="text-lg font-semibold text-gray-900">Planos de Aula</h3>
        <span class="text-sm text-gray-500"><?= (int) $pagTotal ?> <?= $itensLabel ?></span>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plano</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($planos)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fa-regular fa-calendar-days text-4xl text-gray-300 mb-4"></i>
                            <p>Nenhum plano encontrado para os filtros aplicados.</p>
                            <?php if ($filtrosAtivosCount > 0): ?>
                                <a href="<?= URL ?>/professor/planos-aula" class="inline-flex mt-3 text-blue-600 hover:text-blue-700 text-sm font-medium">Limpar filtros</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($planos as $plano): ?>
                        <?php
                        $planoId = (int) ($plano['id'] ?? 0);
                        $tituloPlano = (string) ($plano['titulo'] ?? 'Sem título');
                        $datasPlano = $formatarDatasPlano($plano['data_aula'] ?? null);
                        [$statusLabel, $statusClasses] = $statusPlano($plano['status'] ?? 'rascunho');
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4">
                                <div class="text-sm font-medium text-gray-900 max-w-md truncate" title="<?= htmlspecialchars($tituloPlano) ?>">
                                    <?= htmlspecialchars($tituloPlano) ?>
                                </div>
                                <?php if (!empty($plano['descricao'])): ?>
                                    <div class="text-xs text-gray-500 mt-1 max-w-md truncate">
                                        <?= htmlspecialchars((string) $plano['descricao']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= htmlspecialchars((string) ($plano['materia_nome'] ?? '-')) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= htmlspecialchars((string) ($plano['turma_nome'] ?? '-')) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600">
                                <?= htmlspecialchars($datasPlano) ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= htmlspecialchars($statusClasses) ?>">
                                    <?= htmlspecialchars($statusLabel) ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <?php ob_start(); ?>
                                <a href="<?= URL ?>/professor/planos-aula/visualizar/<?= $planoId ?>"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Ver
                                </a>
                                <a href="<?= URL ?>/professor/planos-aula/editar/<?= $planoId ?>"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                </a>
                                <a href="<?= URL ?>/professor/planos-aula/pdf/<?= $planoId ?>" target="_blank"
                                   class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-file-pdf text-gray-400 w-4 text-center"></i> Exportar PDF
                                </a>
                                <button type="button" onclick="duplicarPlano(<?= $planoId ?>)"
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                    <i class="fa-solid fa-copy text-gray-400 w-4 text-center"></i> Duplicar
                                </button>
                                <div class="border-t border-gray-100 my-1"></div>
                                <button type="button" onclick="excluirPlano(<?= $planoId ?>)"
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                </button>
                                <?php
                                $row_actions_dropdown_items = ob_get_clean();
                                $row_actions_dropdown_id = 'row-actions-plano-' . $planoId;
                                include __DIR__ . '/../../admin/_partials/row_actions_dropdown.php';
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pagTotal > 0): ?>
        <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>-<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> registro(s)
            </p>
            <?php if ($pagTotalPages > 1): ?>
                <div class="flex items-center gap-1">
                    <?php if (!empty($pagination['has_prev'])): ?>
                        <a href="<?= URL ?>/professor/planos-aula<?= $baseQuery . $sep ?>page=<?= (int) $pagination['prev_page'] ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
                    <?php endif; ?>
                    <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                        <a href="<?= URL ?>/professor/planos-aula<?= $baseQuery . $sep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'btn-primary-custom' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
                    <?php endfor; ?>
                    <?php if (!empty($pagination['has_next'])): ?>
                        <a href="<?= URL ?>/professor/planos-aula<?= $baseQuery . $sep ?>page=<?= (int) $pagination['next_page'] ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<div id="planos-filter-backdrop" class="hidden fixed inset-0 bg-black/40 z-40" onclick="fecharFiltroPlanos()"></div>
<aside id="planos-filter-drawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 translate-x-full transition-transform duration-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900">Filtrar planos de aula</h3>
        <button type="button" class="text-gray-500 hover:text-gray-700" onclick="fecharFiltroPlanos()" aria-label="Fechar filtros">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/professor/planos-aula" class="h-[calc(100%-65px)] flex flex-col">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro-descricao" class="block text-sm font-medium text-gray-700 mb-1.5">Descrição</label>
                <input id="filtro-descricao" type="text" name="descricao" value="<?= htmlspecialchars($filtroDescricao) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500" placeholder="Título, conteúdo ou objetivo">
            </div>
            <div>
                <label for="filtro-turma" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="filtro-turma" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach (($turmas ?? []) as $turma): ?>
                        <option value="<?= (int) ($turma['id'] ?? 0) ?>" <?= (string) ($turma['id'] ?? '') === $filtroTurmaId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($turma['nome'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-materia" class="block text-sm font-medium text-gray-700 mb-1.5">Matéria</label>
                <select id="filtro-materia" name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach (($materias ?? []) as $materia): ?>
                        <option value="<?= (int) ($materia['id'] ?? 0) ?>" <?= (string) ($materia['id'] ?? '') === $filtroMateriaId ? 'selected' : '' ?>>
                            <?= htmlspecialchars((string) ($materia['nome'] ?? '')) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label for="filtro-data-inicio" class="block text-sm font-medium text-gray-700 mb-1.5">Data início</label>
                    <input id="filtro-data-inicio" type="date" name="data_inicio" value="<?= htmlspecialchars($filtroDataInicio) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label for="filtro-data-fim" class="block text-sm font-medium text-gray-700 mb-1.5">Data fim</label>
                    <input id="filtro-data-fim" type="date" name="data_fim" value="<?= htmlspecialchars($filtroDataFim) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 bg-gray-50 flex items-center gap-3">
            <a href="<?= URL ?>/professor/planos-aula" class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 text-center hover:bg-gray-100 transition-colors">Limpar</a>
            <button type="submit" class="btn-primary-custom flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold hover:opacity-90 transition-colors">Aplicar</button>
        </div>
    </form>
</aside>

<?php include __DIR__ . '/../../layouts/components/row_actions_dropdown_js.php'; ?>

<script>
function abrirFiltroPlanos() {
    document.getElementById('planos-filter-backdrop').classList.remove('hidden');
    document.getElementById('planos-filter-drawer').classList.remove('translate-x-full');
}

function fecharFiltroPlanos() {
    document.getElementById('planos-filter-backdrop').classList.add('hidden');
    document.getElementById('planos-filter-drawer').classList.add('translate-x-full');
}

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        fecharFiltroPlanos();
    }
});

function excluirPlano(id) {
    if (!confirm('Tem certeza que deseja excluir este plano de aula?')) {
        return;
    }

    fetch('<?= URL ?>/professor/planos-aula/excluir/' + id, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Erro: ' + (data.error || 'Erro ao excluir plano'));
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao excluir plano de aula');
    });
}

function duplicarPlano(id) {
    if (!confirm('Deseja duplicar este plano de aula? A cópia será criada como rascunho.')) {
        return;
    }

    fetch('<?= URL ?>/professor/planos-aula/duplicar/' + id, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            if (data.redirect) {
                window.location.href = data.redirect;
                return;
            }
            location.reload();
            return;
        }
        alert('Erro: ' + (data.error || 'Erro ao duplicar plano'));
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('Erro ao duplicar plano de aula');
    });
}
</script>
