<?php
$filtrosAtivosCount = 0;
foreach ([$filtro_status ?? '', $filtro_professor ?? '', $filtro_turma ?? '', $filtro_materia ?? '', $filtro_tipo_ensino ?? ''] as $fv) {
    if (!empty($fv)) {
        $filtrosAtivosCount++;
    }
}
$totalRascunhos = (int) ($stats['rascunho'] ?? 0);
$pag = $pagination ?? [];
$pagTotal = (int)($pag['total_items'] ?? 0);
$pagPerPage = (int)($pag['per_page'] ?? 10);
$pagPage = (int)($pag['current_page'] ?? 1);
$pagTotalPages = (int)($pag['total_pages'] ?? 1);
$itensLabel = $pagTotal === 1 ? 'item' : 'itens';
$pagQueryParams = array_filter([
    'status' => $filtro_status ?? '',
    'professor_id' => $filtro_professor ?? '',
    'turma_id' => $filtro_turma ?? '',
    'materia_id' => $filtro_materia ?? '',
    'tipo_ensino' => $filtro_tipo_ensino ?? '',
], static function ($v) { return $v !== '' && $v !== null; });
$pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
$pagSep = $pagBaseQuery === '' ? '?' : '&';
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
$resumoPlanos = [
    [
        'label' => 'Resultados',
        'value' => $pagTotal,
        'icon' => 'fa-list-check',
        'valueClass' => 'text-gray-900',
        'iconClass' => 'bg-slate-100 text-slate-600',
    ],
    [
        'label' => 'Exibindo',
        'value' => count($planos ?? []),
        'icon' => 'fa-eye',
        'valueClass' => 'text-blue-700',
        'iconClass' => 'bg-blue-50 text-blue-600',
    ],
    [
        'label' => 'Rascunhos',
        'value' => $totalRascunhos,
        'icon' => 'fa-pen-to-square',
        'valueClass' => 'text-slate-800',
        'iconClass' => 'bg-slate-100 text-slate-600',
    ],
    [
        'label' => 'Aprovados',
        'value' => (int) ($stats['aprovado'] ?? 0),
        'icon' => 'fa-circle-check',
        'valueClass' => 'text-green-700',
        'iconClass' => 'bg-green-50 text-green-600',
    ],
];
?>
<!-- Header Section -->
<div class="mb-6">
    <div class="flex items-center justify-between flex-wrap gap-4">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Planos de Aula</h1>
            <p class="text-gray-600 mt-1">Gerencie os planos de aula dos professores.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
            <?php if ($totalRascunhos > 0): ?>
            <button type="button" onclick="aprovarTodosRascunhosAdmin(<?= (int) $totalRascunhos ?>)"
                    class="inline-flex items-center px-4 py-2.5 border border-green-200 rounded-lg text-sm font-semibold text-green-700 bg-green-50 hover:bg-green-100 transition-colors">
                <svg class="mr-2 h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 6 9 17l-5-5"></path>
                </svg>
                Aprovar todos
                <span class="ml-2 inline-flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-green-700 px-1.5 text-xs font-semibold text-white"><?= (int) $totalRascunhos ?></span>
            </button>
            <?php endif; ?>
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>
</div>

<div class="grid grid-cols-2 xl:grid-cols-4 gap-3 mb-6">
    <?php foreach ($resumoPlanos as $card): ?>
        <div class="rounded-xl border border-gray-200 bg-white px-4 py-3 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-500"><?= htmlspecialchars($card['label']) ?></p>
                    <p class="mt-1 text-2xl font-bold leading-none <?= htmlspecialchars($card['valueClass']) ?>"><?= (int) $card['value'] ?></p>
                </div>
                <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl <?= htmlspecialchars($card['iconClass']) ?>">
                    <i class="fa-solid <?= htmlspecialchars($card['icon']) ?> text-sm"></i>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar planos de aula</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/planos-aula" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="status" name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach (['rascunho' => 'Rascunho', 'aprovado' => 'Aprovado', 'rejeitado' => 'Rejeitado', 'pendente' => 'Pendente', 'enviado' => 'Enviado'] as $statusKey => $statusNome): ?>
                        <option value="<?= htmlspecialchars($statusKey) ?>" <?= ($filtro_status ?? '') === $statusKey ? 'selected' : '' ?>>
                            <?= htmlspecialchars($statusNome) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="professor_id" class="block text-sm font-medium text-gray-700 mb-1.5">Professor</label>
                <select id="professor_id" name="professor_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($professores as $professor): ?>
                        <option value="<?= $professor['id'] ?>" <?= ($filtro_professor ?? '') == $professor['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($professor['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="turma_id" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="turma_id" name="turma_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($turmas as $turma): ?>
                        <option value="<?= $turma['id'] ?>" <?= ($filtro_turma ?? '') == $turma['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($turma['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="materia_id" class="block text-sm font-medium text-gray-700 mb-1.5">Matéria</label>
                <select id="materia_id" name="materia_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($materias as $materia): ?>
                        <option value="<?= $materia['id'] ?>" <?= ($filtro_materia ?? '') == $materia['id'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($materia['nome']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="tipo_ensino" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Ensino</label>
                <select id="tipo_ensino" name="tipo_ensino" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($tipos_ensino as $tipo): ?>
                        <?php $tipoValue = $tipo['tipo_ensino'] ?? ''; ?>
                        <?php if ($tipoValue === '') continue; ?>
                        <option value="<?= htmlspecialchars($tipoValue) ?>" <?= ($filtro_tipo_ensino ?? '') == $tipoValue ? 'selected' : '' ?>>
                            <?= htmlspecialchars($tipoValue) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <a href="<?= URL ?>/admin/planos-aula"
               class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors text-center">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<!-- Planos de Aula List -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between gap-3">
        <h2 class="text-lg font-semibold text-gray-900">Planos de Aula</h2>
        <span class="text-sm text-gray-500"><?= (int) $pagTotal ?> <?= $itensLabel ?></span>
    </div>
    <div>
        <?php if (empty($planos)): ?>
            <div class="text-center py-12">
                <svg class="w-16 h-16 text-gray-400 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                </svg>
                <p class="text-gray-500 text-lg mb-2">Nenhum plano de aula encontrado</p>
                <p class="text-sm text-gray-400">Ajuste os filtros para ver mais resultados</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Plano</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($planos as $plano): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <div class="max-w-3xl truncate text-[13px] font-semibold leading-5 text-gray-900" title="<?= htmlspecialchars((string) ($plano['titulo'] ?? '')) ?>">
                                        <?= htmlspecialchars((string) ($plano['titulo'] ?? 'Sem título')) ?>
                                    </div>
                                    <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-gray-500">
                                        <span class="font-medium text-gray-700"><?= htmlspecialchars((string) ($plano['materia_nome'] ?? '-')) ?></span>
                                        <span class="text-gray-300">•</span>
                                        <span><?= htmlspecialchars($plano['professor_nome'] ?? 'N/A') ?></span>
                                        <span class="text-gray-300">•</span>
                                        <span>Turma <?= htmlspecialchars((string) ($plano['turma_nome'] ?? '-')) ?></span>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap align-middle">
                                    <?php
                                    [$statusLabel, $statusClasses] = $statusPlano($plano['status'] ?? 'rascunho');
                                    // Tenta decodificar as datas se estiverem em JSON
                                    $datas = [];
                                    if (!empty($plano['data_aula'])) {
                                        $datasJson = json_decode($plano['data_aula'], true);
                                        if (json_last_error() === JSON_ERROR_NONE && is_array($datasJson)) {
                                            $datas = $datasJson;
                                        } else {
                                            // Se não for JSON, assume que é uma data única (compatibilidade)
                                            $datas = [$plano['data_aula']];
                                        }
                                    }
                                    ?>
                                    <div class="text-sm text-gray-500">
                                        <?php if (!empty($datas)): ?>
                                            <?php foreach ($datas as $index => $dataItem): ?>
                                                <?= date('d/m/Y', strtotime($dataItem)) ?><?= $index < count($datas) - 1 ? ', ' : '' ?>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap align-middle">
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= htmlspecialchars($statusClasses) ?>">
                                        <?= htmlspecialchars($statusLabel) ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium align-middle">
                                    <?php ob_start(); ?>
                                    <a href="<?= URL ?>/admin/planos-aula/visualizar/<?= $plano['id'] ?>"
                                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-eye text-gray-400 w-4 text-center"></i> Ver
                                    </a>
                                    <a href="<?= URL ?>/admin/planos-aula/editar/<?= $plano['id'] ?>"
                                       class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                    </a>
                                    <?php
                                    $row_actions_dropdown_items = ob_get_clean();
                                    $row_actions_dropdown_id = 'row-actions-plano-' . (int)$plano['id'];
                                    include __DIR__ . '/../_partials/row_actions_dropdown.php';
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> plano(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/planos-aula<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/planos-aula<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/planos-aula<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<script>
    function openFilterDrawer() {
        document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
        const drawer = document.getElementById('filterDrawer');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeFilterDrawer() {
        document.getElementById('filterDrawerBackdrop').classList.add('hidden');
        const drawer = document.getElementById('filterDrawer');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilterDrawer();
        }
    });

    function aprovarTodosRascunhosAdmin(total) {
        const totalTexto = total === 1 ? '1 rascunho' : total + ' rascunhos';
        if (!confirm('Aprovar todos os ' + totalTexto + ' de todos os professores desta escola?')) {
            return;
        }

        fetch('<?= URL ?>/admin/planos-aula/aprovar-rascunhos', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message || 'Planos aprovados com sucesso.');
                location.reload();
                return;
            }
            alert('Erro: ' + (data.error || 'Erro ao aprovar rascunhos'));
        })
        .catch(error => {
            console.error('Erro:', error);
            alert('Erro ao aprovar rascunhos');
        });
    }
</script>
