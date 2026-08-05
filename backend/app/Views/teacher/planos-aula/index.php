<?php
$primaryColor = $primary_color ?? '#3b82f6';
$primaryTextColor = $primary_text_color ?? '#ffffff';
$filtroDescricao = trim((string) ($filtros['descricao'] ?? ''));
$filtroTurmaId = (string) ($filtros['turma_id'] ?? '');
$filtroMateriaId = (string) ($filtros['materia_id'] ?? '');
$filtroDataInicio = trim((string) ($filtros['data_inicio'] ?? ''));
$filtroDataFim = trim((string) ($filtros['data_fim'] ?? ''));
$pagination = $pagination ?? [
    'current_page' => 1,
    'total_pages' => 1,
    'total_items' => (int) ($stats['total'] ?? 0),
    'per_page' => 20,
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
?>

<div class="mb-6">
    <div class="flex flex-wrap justify-between items-start gap-3">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Meus Planos de Aula</h2>
            <p class="text-gray-600 text-sm">Gerencie seus planos de aula.</p>
        </div>
        <div class="flex items-center gap-2">
            <button type="button"
                    onclick="abrirFiltroPlanos()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <svg class="w-4 h-4 mr-2 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2l-6 7v6l-4-2v-4L3 6V4z"></path></svg>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                    <span class="ml-2 min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold inline-flex items-center justify-center"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>

            <a href="<?= URL ?>/professor/planos-aula/criar"
               class="inline-flex items-center px-4 py-2.5 bg-green-600 text-white rounded-lg text-sm font-semibold hover:bg-green-700 transition-colors shadow-sm">
                <span class="mr-2">+</span>
                Novo Plano
            </a>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between gap-2">
        <h3 class="text-lg font-semibold text-gray-900">Planos de Aula</h3>
        <span class="text-sm text-gray-500"><?= (int) ($stats['total'] ?? 0) ?> itens</span>
    </div>

    <div class="p-0">
        <?php if (empty($planos)): ?>
            <div class="text-center py-12 px-5">
                <div class="text-gray-400 mb-3">Nenhum plano encontrado para os filtros aplicados.</div>
                <a href="<?= URL ?>/professor/planos-aula" class="text-blue-600 hover:text-blue-700 text-sm font-medium">Limpar filtros</a>
            </div>
        <?php else: ?>
            <table class="w-full table-fixed divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="w-[38%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                        <th class="w-[18%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                        <th class="w-[12%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                        <th class="w-[16%] px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="w-[16%] px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($planos as $plano): ?>
                        <?php
                        $datas = [];
                        if (!empty($plano['data_aula'])) {
                            $datasJson = json_decode((string) $plano['data_aula'], true);
                            if (json_last_error() === JSON_ERROR_NONE && is_array($datasJson)) {
                                $datas = $datasJson;
                            } else {
                                $datas = [(string) $plano['data_aula']];
                            }
                        }
                        $datasFormatadas = [];
                        foreach ($datas as $dataItem) {
                            $tsData = strtotime((string) $dataItem);
                            if ($tsData !== false) {
                                $datasFormatadas[] = date('d/m/Y', $tsData);
                            }
                        }
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-4 py-3 text-sm font-medium text-gray-900">
                                <div class="truncate" title="<?= htmlspecialchars((string) ($plano['titulo'] ?? 'Sem título')) ?>">
                                    <?= htmlspecialchars((string) ($plano['titulo'] ?? 'Sem título')) ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="truncate" title="<?= htmlspecialchars((string) ($plano['materia_nome'] ?? '-')) ?>">
                                    <?= htmlspecialchars((string) ($plano['materia_nome'] ?? '-')) ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
                                <?= htmlspecialchars((string) ($plano['turma_nome'] ?? '-')) ?>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-600">
                                <div class="truncate" title="<?= !empty($datasFormatadas) ? htmlspecialchars(implode(', ', $datasFormatadas)) : 'N/A' ?>">
                                    <?= !empty($datasFormatadas) ? htmlspecialchars(implode(', ', $datasFormatadas)) : 'N/A' ?>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-right whitespace-nowrap">
                                <button type="button"
                                        class="btn-acoes-dropdown inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors hover:opacity-90"
                                        style="background-color: <?= htmlspecialchars($primaryColor) ?>; color: <?= htmlspecialchars($primaryTextColor) ?>; border-color: <?= htmlspecialchars($primaryColor) ?>;"
                                        data-id="<?= (int) ($plano['id'] ?? 0) ?>"
                                        data-visualizar="<?= htmlspecialchars(URL . '/professor/planos-aula/visualizar/' . (int) ($plano['id'] ?? 0)) ?>"
                                        data-editar="<?= htmlspecialchars(URL . '/professor/planos-aula/editar/' . (int) ($plano['id'] ?? 0)) ?>"
                                        data-pdf="<?= htmlspecialchars(URL . '/professor/planos-aula/pdf/' . (int) ($plano['id'] ?? 0)) ?>"
                                        onclick="abrirDropdownAcoes(this)">
                                    <span>Ações</span>
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="px-5 py-4 border-t border-gray-200">
        <div class="text-center text-sm text-gray-500 mb-3">
            Total de planos de aula: <span class="font-semibold text-gray-700"><?= (int) ($pagination['total_items'] ?? $stats['total'] ?? 0) ?></span>
        </div>

        <?php if (($pagination['total_pages'] ?? 1) > 1): ?>
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div class="text-sm text-gray-700 text-center sm:text-left">
                Mostrando <?= count($planos) ?> de <?= (int) ($pagination['total_items'] ?? 0) ?> planos
            </div>
            <div class="flex items-center justify-center flex-wrap gap-2">
                <?php
                $queryParams = array_filter([
                    'descricao' => $filtroDescricao,
                    'turma_id' => $filtroTurmaId,
                    'materia_id' => $filtroMateriaId,
                    'data_inicio' => $filtroDataInicio,
                    'data_fim' => $filtroDataFim,
                    'per_page' => ($pagination['per_page'] ?? 20) != 20 ? ($pagination['per_page'] ?? 20) : '',
                ], static fn($v) => $v !== '' && $v !== null);
                $queryString = !empty($queryParams) ? '&' . http_build_query($queryParams) : '';
                ?>

                <?php if (!empty($pagination['has_prev'])): ?>
                <a href="<?= URL ?>/professor/planos-aula?page=<?= (int) $pagination['prev_page'] ?><?= $queryString ?>"
                   class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors text-sm">
                    Anterior
                </a>
                <?php endif; ?>

                <?php
                $startPage = max(1, (int) $pagination['current_page'] - 2);
                $endPage = min((int) $pagination['total_pages'], (int) $pagination['current_page'] + 2);
                ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                <a href="<?= URL ?>/professor/planos-aula?page=<?= $i ?><?= $queryString ?>"
                   class="px-3 py-2 border rounded-lg text-sm transition-colors <?= $i === (int) $pagination['current_page'] ? 'bg-blue-600 text-white border-blue-600' : 'bg-white border-gray-300 text-gray-700 hover:bg-gray-50' ?>">
                    <?= $i ?>
                </a>
                <?php endfor; ?>

                <?php if (!empty($pagination['has_next'])): ?>
                <a href="<?= URL ?>/professor/planos-aula?page=<?= (int) $pagination['next_page'] ?><?= $queryString ?>"
                   class="px-4 py-2 bg-white border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors text-sm">
                    Próxima
                </a>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<div id="planos-filter-backdrop" class="hidden fixed inset-0 bg-black/40 z-40" onclick="fecharFiltroPlanos()"></div>
<aside id="planos-filter-drawer" class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 translate-x-full transition-transform duration-200">
    <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-base font-semibold text-gray-900">Filtrar planos de aula</h3>
        <button type="button" class="text-gray-500 hover:text-gray-700" onclick="fecharFiltroPlanos()">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
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
            <button type="submit" class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">Aplicar</button>
        </div>
    </form>
</aside>

<div id="dropdown-actions-portal" class="hidden fixed z-[100] w-44 py-1 bg-white rounded-lg shadow-xl border border-gray-200"></div>

<script>
function abrirFiltroPlanos() {
    document.getElementById('planos-filter-backdrop').classList.remove('hidden');
    document.getElementById('planos-filter-drawer').classList.remove('translate-x-full');
}

function fecharFiltroPlanos() {
    document.getElementById('planos-filter-backdrop').classList.add('hidden');
    document.getElementById('planos-filter-drawer').classList.add('translate-x-full');
}

function abrirDropdownAcoes(btn) {
    const portal = document.getElementById('dropdown-actions-portal');
    const id = Number(btn.dataset.id || 0);
    const visualizar = btn.dataset.visualizar || '#';
    const editar = btn.dataset.editar || '#';
    const pdf = btn.dataset.pdf || '#';
    const rect = btn.getBoundingClientRect();

    portal.style.right = (window.innerWidth - rect.right) + 'px';
    portal.style.top = (rect.bottom + 4) + 'px';
    portal.innerHTML = `
        <a href="${visualizar}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Ver</a>
        <a href="${editar}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Editar</a>
        <a href="${pdf}" target="_blank" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Exportar PDF</a>
        <button type="button" onclick="duplicarPlano(${id}); fecharDropdownActions();" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">Duplicar</button>
        <button type="button" onclick="excluirPlano(${id}); fecharDropdownActions();" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">Excluir</button>
    `;
    portal.classList.remove('hidden');
}

function fecharDropdownActions() {
    document.getElementById('dropdown-actions-portal').classList.add('hidden');
}

document.addEventListener('click', function (event) {
    const portal = document.getElementById('dropdown-actions-portal');
    const isBtn = event.target.closest('.btn-acoes-dropdown');
    if (!portal.classList.contains('hidden') && !portal.contains(event.target) && !isBtn) {
        fecharDropdownActions();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        fecharDropdownActions();
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
