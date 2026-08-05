<?php
$filters = $filters ?? [];
$q = $filters['q'] ?? '';
$status = $filters['status'] ?? '';
$pagante = $filters['pagante'] ?? '';
$perPage = (int) ($filters['per_page'] ?? 10);
$page = (int) ($filters['page'] ?? 1);
$total = (int) ($filters['total'] ?? 0);
$totalPages = max(1, (int) ceil($total / max(1, $perPage)));

$filtrosAtivosCount = 0;
foreach (['q' => $q, 'status' => $status, 'pagante' => $pagante] as $fv) {
    if ($fv !== '') {
        $filtrosAtivosCount++;
    }
}

$page_header_title = 'Gestão de Professores';
$page_header_subtitle = 'Cadastre e gerencie os professores da escola';
ob_start();
?>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <?php if ($filtrosAtivosCount > 0): ?>
    <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
    <?php endif; ?>
</button>
<button type="button" onclick="abrirModalCSV()"
        class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-file-import mr-2 text-gray-500"></i>
    Importar CSV
</button>
<a href="<?= URL ?>/admin/teachers/export-csv"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-file-export mr-2 text-gray-500"></i>
    Exportar CSV
</a>
<button type="button" onclick="openTeacherDrawer()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo Professor
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Professor</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matérias</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($teachers)): ?>
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-chalkboard-user text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum professor cadastrado</p>
                        <button type="button" onclick="openTeacherDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i> Novo Professor
                        </button>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($teachers as $teacher): ?>
                <?php
                    $materias = json_decode($teacher['materias'] ?? '[]', true) ?: [];
                    $turmasProfessor = json_decode($teacher['turmas'] ?? '[]', true) ?: [];
                    $turmasNomes = [];
                    foreach ($turmasProfessor as $turmaId) {
                        $key = (string) $turmaId;
                        if (isset($turmas_map[$key])) {
                            $turmasNomes[] = $turmas_map[$key];
                        }
                    }
                    $turmasJson = htmlspecialchars(json_encode($turmasNomes), ENT_QUOTES, 'UTF-8');
                ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-10 h-10 bg-gray-100 rounded-full flex items-center justify-center overflow-hidden flex-shrink-0">
                                <?php if (!empty($teacher['avatar_url'])): ?>
                                    <img src="<?= htmlspecialchars($teacher['avatar_url']) ?>" alt="" class="w-full h-full object-cover rounded-full">
                                <?php else: ?>
                                    <span class="text-sm font-medium text-gray-600"><?= strtoupper(substr($teacher['nome'] ?? '', 0, 2)) ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($teacher['nome'] ?? '') ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($teacher['email'] ?? 'Sem email') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?php if (!empty($materias)): ?>
                            <div class="flex flex-wrap gap-1">
                                <?php foreach (array_slice($materias, 0, 3) as $materia): ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">
                                        <?= htmlspecialchars($materia) ?>
                                    </span>
                                <?php endforeach; ?>
                                <?php if (count($materias) > 3): ?>
                                    <span class="text-xs text-gray-400">+<?= count($materias) - 3 ?> mais</span>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <span class="text-gray-400">Nenhuma matéria</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $teacher['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $teacher['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <button type="button"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                data-turmas="<?= $turmasJson ?>"
                                data-professor="<?= htmlspecialchars($teacher['nome'] ?? '') ?>"
                                onclick="openTurmasModal(this)">
                            <i class="fa-solid fa-people-group text-gray-400 w-4 text-center"></i> Ver turmas
                        </button>
                        <button type="button" onclick="openTeacherDrawer(<?= (int) $teacher['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <button type="button" onclick="toggleStatusTeacher(<?= (int) $teacher['id'] ?>, <?= $teacher['ativo'] ? 'false' : 'true' ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-power-off text-gray-400 w-4 text-center"></i> <?= $teacher['ativo'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                        <button type="button" onclick="togglePaganteTeacher(<?= (int) $teacher['id'] ?>, <?= ($teacher['pagante'] ?? 1) ? 'false' : 'true' ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-sack-dollar text-gray-400 w-4 text-center"></i> Marcar como <?= ($teacher['pagante'] ?? 1) ? 'não pagante' : 'pagante' ?>
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <button type="button" onclick="deleteTeacher(<?= (int) $teacher['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-teacher-' . (int) $teacher['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (!empty($teachers)): ?>
    <?php
        $queryBase = ['q' => $q, 'status' => $status, 'pagante' => $pagante, 'per_page' => $perPage];
    ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> professor(es)
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="?<?= http_build_query(array_merge($queryBase, ['page' => $page - 1])) ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="?<?= http_build_query(array_merge($queryBase, ['page' => $i])) ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="?<?= http_build_query(array_merge($queryBase, ['page' => $page + 1])) ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar professores</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/teachers" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_q" class="block text-sm font-medium text-gray-700 mb-1.5">Buscar</label>
                <input type="text" id="filtro_q" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Nome, email ou código..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <div class="relative">
                    <select id="filtro_status" name="status" class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
            <div>
                <label for="filtro_pagante" class="block text-sm font-medium text-gray-700 mb-1.5">Pagante</label>
                <div class="relative">
                    <select id="filtro_pagante" name="pagante" class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="sim" <?= $pagante === 'sim' ? 'selected' : '' ?>>Sim</option>
                        <option value="nao" <?= $pagante === 'nao' ? 'selected' : '' ?>>Não</option>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
            <div>
                <label for="filtro_per_page" class="block text-sm font-medium text-gray-700 mb-1.5">Itens por página</label>
                <div class="relative">
                    <select id="filtro_per_page" name="per_page" class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ([10, 20, 50, 100] as $size): ?>
                            <option value="<?= $size ?>" <?= $perPage === $size ? 'selected' : '' ?>><?= $size ?> / página</option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <button type="button" onclick="clearFilters()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<!-- Modal Turmas -->
<div id="turmasModal" class="fixed inset-0 bg-black/50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-2xl max-w-lg w-full">
            <div class="p-6 border-b border-gray-200 flex items-center justify-between">
                <h3 class="text-lg font-semibold text-gray-900" id="turmasModalTitle">Turmas do Professor</h3>
                <button type="button" onclick="closeTurmasModal()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
            <div class="p-6">
                <div id="turmasModalContent" class="text-sm text-gray-700 space-y-2"></div>
            </div>
            <div class="p-4 border-t border-gray-200 flex justify-end">
                <button type="button" onclick="closeTurmasModal()" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200 transition-colors text-sm">
                    Fechar
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Importar CSV -->
<div id="modalCSV" class="fixed inset-0 bg-black/50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-xl shadow-2xl max-w-md w-full mx-4">
        <div class="p-6 border-b border-gray-200">
            <div class="flex justify-between items-center">
                <h3 class="text-xl font-bold text-gray-900">Importar Professores via CSV</h3>
                <button onclick="fecharModalCSV()" class="text-gray-400 hover:text-gray-600">
                    <i class="fa-solid fa-xmark text-xl"></i>
                </button>
            </div>
        </div>
        <div class="p-6">
            <div class="mb-4 bg-blue-50 border border-blue-200 rounded-lg p-4">
                <p class="text-sm text-blue-800 mb-2"><strong>Formato do CSV:</strong></p>
                <p class="text-xs text-blue-700 font-mono">nome;1;2;3;email</p>
                <p class="text-xs text-blue-700 mt-2"><strong>Delimitador:</strong> Ponto e vírgula (;) ou vírgula (,)</p>
                <p class="text-xs text-blue-700 mt-1"><strong>Matérias:</strong> As colunas são os <strong>IDs das matérias</strong> cadastradas no banco. Use "X" nas colunas de matérias que o professor leciona (pode ter uma ou mais)</p>
                <p class="text-xs text-blue-700 mt-1"><strong>Exemplo:</strong></p>
                <p class="text-xs text-blue-700 font-mono bg-white p-2 rounded mt-1">
                    nome;1;2;3;email<br>
                    João Silva;X;X;;joao@escola.com<br>
                    Maria Santos;;X;X;maria@escola.com
                </p>
                <p class="text-xs text-blue-700 mt-2">
                    <strong>Dica:</strong> Exporte o CSV primeiro para ver os IDs das matérias cadastradas.
                </p>
            </div>
            <form id="formImportarCSV" enctype="multipart/form-data">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Selecione o arquivo CSV
                    </label>
                    <input type="file" name="csv_file" accept=".csv" required
                           class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <div class="flex justify-end space-x-3">
                    <button type="button" onclick="fecharModalCSV()"
                            class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors">
                        Cancelar
                    </button>
                    <button type="submit"
                            class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                        Importar
                    </button>
                </div>
            </form>
            <div id="resultadoImportacao" class="mt-4 hidden"></div>
        </div>
    </div>
</div>

<!-- Criar/Editar professor em drawer lateral -->
<div id="teacherDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeTeacherDrawer()"></div>
<aside id="teacherDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="teacherDrawerTitle" class="text-xl font-bold text-gray-900">Novo Professor</h2>
        <button type="button" onclick="closeTeacherDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="teacher-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="teacher_id" value="">
        <input type="hidden" name="_method" id="teacher_method" value="" disabled>

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do Professor</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="teacher_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                        <input type="text" id="teacher_nome" name="nome" required placeholder="Ex: João Silva Santos"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="teacher_email" class="block text-sm font-medium text-gray-700 mb-1">Email Institucional *</label>
                        <input type="email" id="teacher_email" name="email" required placeholder="professor@escola.com"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            </section>

            <p id="teacher-senha-padrao" class="text-sm text-blue-800 bg-blue-50 border border-blue-100 rounded-lg px-3 py-2">
                A senha padrão será <strong>123456</strong>. O professor poderá alterá-la após o primeiro login.
            </p>

            <section id="teacher-senha-section" class="hidden">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Trocar Senha</h3>
                <p class="text-sm text-gray-500 mb-3">Preencha os dois campos para alterar. Deixe em branco para manter a senha atual.</p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="teacher_nova_senha" class="block text-sm font-medium text-gray-700 mb-1">Nova senha</label>
                        <input type="password" id="teacher_nova_senha" name="nova_senha" minlength="6" autocomplete="new-password" placeholder="Mínimo 6 caracteres"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="teacher_confirmar_senha" class="block text-sm font-medium text-gray-700 mb-1">Confirmar senha</label>
                        <input type="password" id="teacher_confirmar_senha" name="confirmar_senha" minlength="6" autocomplete="new-password" placeholder="Repita a nova senha"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Matérias que Leciona</h3>
                <?php if (empty($materias_disponiveis)): ?>
                    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        Nenhuma matéria cadastrada no sistema.
                        <a href="<?= URL ?>/admin/subjects/create" class="text-blue-600 hover:underline">Cadastre matérias primeiro</a>.
                    </p>
                <?php else: ?>
                    <div id="teacher-materias-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3">
                        <?php foreach ($materias_disponiveis as $materia): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="materias[]" value="<?= htmlspecialchars($materia) ?>" class="rounded border-gray-300 text-green-600">
                                <?= htmlspecialchars($materia) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Turmas que Leciona</h3>
                <?php if (empty($turmas_disponiveis)): ?>
                    <p class="text-sm text-amber-800 bg-amber-50 border border-amber-200 rounded-lg px-3 py-2">
                        Nenhuma turma cadastrada no sistema.
                        <a href="<?= URL ?>/admin/classes/create" class="text-blue-600 hover:underline">Cadastre turmas primeiro</a>.
                    </p>
                <?php else: ?>
                    <div id="teacher-turmas-grid" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto border border-gray-200 rounded-lg p-3">
                        <?php foreach ($turmas_disponiveis as $turma): ?>
                            <label class="flex items-center gap-2 text-sm text-gray-700">
                                <input type="checkbox" name="turmas[]" value="<?= (int) $turma['id'] ?>" class="rounded border-gray-300 text-green-600">
                                <?= htmlspecialchars($turma['nome']) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Status</h3>
                <div class="space-y-3">
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" id="teacher_ativo" name="ativo" value="1" checked class="rounded border-gray-300 text-green-600">
                        Professor ativo
                    </label>
                    <label class="flex items-center gap-2 text-sm text-gray-700">
                        <input type="checkbox" id="teacher_pagante" name="pagante" value="1" checked class="rounded border-gray-300 text-green-600">
                        Professor pagante
                    </label>
                </div>
            </section>

            <section id="teacher-system-info-section" class="hidden border-t border-gray-200 pt-6">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Informações do Professor</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Criado em:</span>
                        <span id="teacher-created-at" class="text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Última atualização:</span>
                        <span id="teacher-updated-at" class="text-gray-900">-</span>
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeTeacherDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="teacher-form-submit-label">Cadastrar Professor</span>
            </button>
        </div>
    </form>
</aside>

<script>
const URL_BASE = <?= json_encode(URL) ?>;

// ---- Filtro (drawer) ----
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
function clearFilters() {
    window.location.href = URL_BASE + '/admin/teachers';
}

// ---- Modal Turmas ----
function openTurmasModal(button) {
    const modal = document.getElementById('turmasModal');
    const title = document.getElementById('turmasModalTitle');
    const content = document.getElementById('turmasModalContent');
    const professor = button.getAttribute('data-professor') || 'Professor';
    let turmas = [];
    try {
        turmas = JSON.parse(button.getAttribute('data-turmas') || '[]') || [];
    } catch (e) {
        turmas = [];
    }
    title.textContent = `Turmas de ${professor}`;
    if (!turmas.length) {
        content.innerHTML = '<p class="text-gray-500">Sem turmas vinculadas.</p>';
    } else {
        content.innerHTML = `<div class="flex flex-wrap gap-2">${turmas
            .map(t => `<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-blue-100 text-blue-800">${t}</span>`)
            .join('')}</div>`;
    }
    modal.classList.remove('hidden');
}
function closeTurmasModal() {
    document.getElementById('turmasModal').classList.add('hidden');
}
document.getElementById('turmasModal').addEventListener('click', function (event) {
    if (event.target === this) closeTurmasModal();
});

// ---- Ações de linha ----
function toggleStatusTeacher(id, status) {
    if (!confirm('Tem certeza que deseja alterar o status deste professor?')) return;
    fetch(URL_BASE + '/admin/teachers/' + id + '/toggle-status', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ ativo: status })
    })
        .then((response) => response.json())
        .then((data) => { if (data.success) { location.reload(); } else { alert('Erro ao alterar status: ' + data.error); } })
        .catch(() => alert('Erro de conexão'));
}

function togglePaganteTeacher(id, status) {
    fetch(URL_BASE + '/admin/teachers/' + id + '/toggle-pagante', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        body: JSON.stringify({ pagante: status })
    })
        .then((response) => response.json())
        .then((data) => { if (data.success) { location.reload(); } else { alert('Erro ao alterar status de pagante: ' + data.error); } })
        .catch(() => alert('Erro de conexão'));
}

function deleteTeacher(id) {
    if (!confirm('Tem certeza que deseja excluir este professor? Esta ação não pode ser desfeita.')) return;
    const formData = new FormData();
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    formData.append('_method', 'DELETE');
    fetch(URL_BASE + '/admin/teachers/' + id, { method: 'POST', body: formData, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then((response) => response.json())
        .then((data) => { if (data.success) { location.reload(); } else { alert('Erro ao excluir: ' + data.error); } })
        .catch(() => alert('Erro de conexão'));
}

// ---- Importar CSV ----
function abrirModalCSV() {
    document.getElementById('modalCSV').classList.remove('hidden');
}
function fecharModalCSV() {
    document.getElementById('modalCSV').classList.add('hidden');
    document.getElementById('formImportarCSV').reset();
    document.getElementById('resultadoImportacao').classList.add('hidden');
}
document.getElementById('formImportarCSV').addEventListener('submit', function (e) {
    e.preventDefault();
    const formData = new FormData(this);
    const resultadoDiv = document.getElementById('resultadoImportacao');
    resultadoDiv.classList.remove('hidden');
    resultadoDiv.innerHTML = '<div class="text-center py-4"><div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600 mx-auto"></div><p class="mt-2 text-sm text-gray-600">Importando...</p></div>';
    fetch(URL_BASE + '/admin/teachers/import-csv', { method: 'POST', body: formData })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                const erros = (data.erros > 0 && data.mensagens_erro)
                    ? `<div class="mt-2 text-sm text-green-700"><p class="font-medium">Erros encontrados:</p><ul class="list-disc list-inside mt-1">${data.mensagens_erro.map((msg) => `<li>${msg}</li>`).join('')}</ul></div>`
                    : '';
                resultadoDiv.innerHTML = `<div class="bg-green-50 border border-green-200 rounded-lg p-4"><p class="text-green-800 font-medium">${data.message}</p>${erros}</div>`;
                setTimeout(() => location.reload(), 2000);
            } else {
                resultadoDiv.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800">${data.error}</p></div>`;
            }
        })
        .catch((error) => {
            resultadoDiv.innerHTML = `<div class="bg-red-50 border border-red-200 rounded-lg p-4"><p class="text-red-800">Erro ao importar: ${error.message}</p></div>`;
        });
});

// ---- Criar/Editar professor (drawer) ----
function showTeacherDrawer() {
    document.getElementById('teacherDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('teacherDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeTeacherDrawer() {
    document.getElementById('teacherDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('teacherDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function formatDateTimeBR(value) {
    if (!value) return '-';
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return value;
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function openTeacherDrawer(id) {
    const form = document.getElementById('teacher-form');
    form.reset();
    document.getElementById('teacher_id').value = '';
    document.getElementById('teacher_method').value = '';
    document.getElementById('teacher_method').disabled = true;

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('teacherDrawerTitle').textContent = 'Novo Professor';
        document.getElementById('teacher-form-submit-label').textContent = 'Cadastrar Professor';
        document.getElementById('teacher-senha-padrao').classList.remove('hidden');
        document.getElementById('teacher-senha-section').classList.add('hidden');
        document.getElementById('teacher-system-info-section').classList.add('hidden');
        document.getElementById('teacher_ativo').checked = true;
        document.getElementById('teacher_pagante').checked = true;
        showTeacherDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('teacherDrawerTitle').textContent = 'Editar Professor';
    document.getElementById('teacher-form-submit-label').textContent = 'Atualizar Professor';
    document.getElementById('teacher-senha-padrao').classList.add('hidden');
    document.getElementById('teacher-senha-section').classList.remove('hidden');
    document.getElementById('teacher-system-info-section').classList.remove('hidden');
    document.getElementById('teacher_method').value = 'PUT';
    document.getElementById('teacher_method').disabled = false;

    showTeacherDrawer();

    fetch(URL_BASE + '/admin/teachers/' + id + '/dados')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar o professor'));
                closeTeacherDrawer();
                return;
            }
            const teacher = data.teacher;
            document.getElementById('teacher_id').value = teacher.id;
            document.getElementById('teacher_nome').value = teacher.nome;
            document.getElementById('teacher_email').value = teacher.email;
            document.getElementById('teacher_ativo').checked = !!teacher.ativo;
            document.getElementById('teacher_pagante').checked = !!teacher.pagante;
            document.getElementById('teacher-created-at').textContent = formatDateTimeBR(teacher.created_at);
            document.getElementById('teacher-updated-at').textContent = formatDateTimeBR(teacher.updated_at);
            document.querySelectorAll('#teacher-materias-grid input[type="checkbox"]').forEach((cb) => {
                cb.checked = teacher.materias_array.includes(cb.value);
            });
            document.querySelectorAll('#teacher-turmas-grid input[type="checkbox"]').forEach((cb) => {
                cb.checked = teacher.turmas_array.includes(parseInt(cb.value, 10));
            });
        })
        .catch(() => {
            alert('Erro de conexão ao carregar professor.');
            closeTeacherDrawer();
        });
}

document.getElementById('teacher-form').addEventListener('submit', function (e) {
    e.preventDefault();

    const mode = this.dataset.mode;
    const id = document.getElementById('teacher_id').value;
    const submitBtn = this.querySelector('button[type="submit"]');
    const submitLabel = document.getElementById('teacher-form-submit-label');
    const originalText = submitLabel.textContent;

    if (mode === 'edit') {
        const novaSenha = document.getElementById('teacher_nova_senha').value.trim();
        const confirmarSenha = document.getElementById('teacher_confirmar_senha').value.trim();
        if (novaSenha !== '' || confirmarSenha !== '') {
            if (novaSenha.length < 6) {
                alert('A nova senha deve ter no mínimo 6 caracteres.');
                return;
            }
            if (novaSenha !== confirmarSenha) {
                alert('Nova senha e Confirmar senha não coincidem.');
                return;
            }
        }
    }

    submitBtn.disabled = true;
    submitLabel.textContent = mode === 'create' ? 'Cadastrando...' : 'Atualizando...';

    const url = mode === 'create' ? (URL_BASE + '/admin/teachers') : (URL_BASE + '/admin/teachers/' + id);

    fetch(url, { method: 'POST', body: new FormData(this) })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Erro: ' + (data.error || 'Erro ao salvar'));
            }
        })
        .catch(() => alert('Erro de conexão. Tente novamente.'))
        .finally(() => {
            submitBtn.disabled = false;
            submitLabel.textContent = originalText;
        });
});

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
        closeTeacherDrawer();
        closeTurmasModal();
    }
});
</script>
