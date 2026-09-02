<?php
$filtrosAtivos = 0;
foreach (['nome', 'ra', 'cpf', 'nickname', 'responsavel', 'turma_id'] as $fk) {
    if (!empty($filtros[$fk])) {
        $filtrosAtivos++;
    }
}
?>
<!-- Header Section -->
<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Lista de Alunos</h2>
            <p class="text-gray-600 text-sm">Gerencie todos os alunos da escola</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivos > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
                <?php endif; ?>
            </button>
            <a href="<?= URL ?>/admin/students/create"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Registrar novo Aluno
            </a>
        </div>
    </div>
</div>

<!-- Students Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">RA</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Turma</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($students)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                            <i class="fa-solid fa-user-graduate text-4xl text-gray-300 mb-4"></i>
                            <p>Nenhum aluno encontrado</p>
                            <?php if ($filtrosAtivos > 0): ?>
                            <button type="button" onclick="clearFilters()" class="mt-3 text-sm text-blue-600 hover:text-blue-800">Limpar filtros</button>
                            <?php else: ?>
                            <a href="<?= URL ?>/admin/students/create" class="btn-primary-custom mt-4 inline-block px-4 py-2 rounded-lg text-sm transition-colors hover:opacity-90">
                                Registrar novo Aluno
                            </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($students as $student): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap">
                            <div class="flex items-center">
                                <div class="w-10 h-10 bg-slate-100 rounded-full flex items-center justify-center overflow-hidden relative flex-shrink-0">
                                    <?php if (!empty($student['foto_display_url'])): ?>
                                        <img src="<?= htmlspecialchars($student['foto_display_url']) ?>"
                                             alt=""
                                             class="w-10 h-10 rounded-full object-cover absolute inset-0"
                                             onerror="this.classList.add('hidden'); this.nextElementSibling?.classList.remove('hidden');">
                                        <span class="hidden text-sm font-medium text-slate-600"><?= htmlspecialchars($student['foto_initials'] ?? '?') ?></span>
                                    <?php else: ?>
                                        <span class="text-sm font-medium text-slate-600">
                                            <?= htmlspecialchars($student['foto_initials'] ?? strtoupper(substr($student['nome'] ?? '', 0, 2))) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="ml-4 min-w-0">
                                    <div class="text-sm font-medium text-gray-900 truncate"><?= htmlspecialchars($student['nome_exibicao'] ?? $student['nome'] ?? '') ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($student['ra'] ?? '') ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900 leading-snug max-w-xs">
                            <span class="block"><?= htmlspecialchars($student['turma_display'] ?? $student['turma_nome'] ?? 'Sem turma') ?></span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $student['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                                <?= $student['ativo'] ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap">
                            <a href="<?= URL ?>/admin/students/<?= $student['id'] ?>"
                               class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100 hover:border-blue-300 transition-colors">
                                <i class="fa-solid fa-circle-info text-blue-600"></i>
                                Detalhes
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php if (($pagination['total_items'] ?? 0) > 0): ?>
    <?php
    $query_params = array_filter([
        'nome' => $filtros['nome'] ?? '',
        'ra' => $filtros['ra'] ?? '',
        'cpf' => $filtros['cpf'] ?? '',
        'nickname' => $filtros['nickname'] ?? '',
        'responsavel' => $filtros['responsavel'] ?? '',
        'turma_id' => $filtros['turma_id'] ?? '',
    ], fn($v) => $v !== '' && $v !== null);
    $query_string = !empty($query_params) ? '&' . http_build_query($query_params) : '';
    ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= count($students) ?> de <?= $pagination['total_items'] ?> aluno(s)
        </p>
        <?php if ($pagination['total_pages'] > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagination['has_prev']): ?>
                <a href="<?= URL ?>/admin/students?page=<?= $pagination['prev_page'] ?><?= $query_string ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php
            $start_page = max(1, $pagination['current_page'] - 2);
            $end_page = min($pagination['total_pages'], $pagination['current_page'] + 2);
            ?>
            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                <a href="<?= URL ?>/admin/students?page=<?= $i ?><?= $query_string ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagination['current_page'] ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagination['has_next']): ?>
                <a href="<?= URL ?>/admin/students?page=<?= $pagination['next_page'] ?><?= $query_string ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Filtro lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar alunos</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/students" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_nome" class="block text-sm font-medium text-gray-700 mb-1.5">Nome do Aluno</label>
                <input type="text" id="filtro_nome" name="nome"
                       value="<?= htmlspecialchars($filtros['nome'] ?? '') ?>"
                       placeholder="Digite o nome..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_ra" class="block text-sm font-medium text-gray-700 mb-1.5">RA</label>
                <input type="text" id="filtro_ra" name="ra"
                       value="<?= htmlspecialchars($filtros['ra'] ?? '') ?>"
                       placeholder="Código / RA..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_cpf" class="block text-sm font-medium text-gray-700 mb-1.5">CPF / CIN</label>
                <input type="text" id="filtro_cpf" name="cpf"
                       value="<?= htmlspecialchars($filtros['cpf'] ?? '') ?>"
                       placeholder="Somente números"
                       inputmode="numeric"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_nickname" class="block text-sm font-medium text-gray-700 mb-1.5">Nickname</label>
                <input type="text" id="filtro_nickname" name="nickname"
                       value="<?= htmlspecialchars($filtros['nickname'] ?? '') ?>"
                       placeholder="Login do aluno..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_responsavel" class="block text-sm font-medium text-gray-700 mb-1.5">Nome do Responsável</label>
                <input type="text" id="filtro_responsavel" name="responsavel"
                       value="<?= htmlspecialchars($filtros['responsavel'] ?? '') ?>"
                       placeholder="Nome do responsável..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_turma" class="block text-sm font-medium text-gray-700 mb-1.5">Turma</label>
                <select id="filtro_turma" name="turma_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas as turmas</option>
                    <?php foreach ($turmas as $turma): ?>
                    <option value="<?= $turma['id'] ?>" <?= ($filtros['turma_id'] ?? '') == $turma['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($turma['label'] ?? $turma['turma_nome'] ?? $turma['nome'] ?? '') ?>
                    </option>
                    <?php endforeach; ?>
                </select>
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

function clearFilters() {
    window.location.href = <?= json_encode(URL . '/admin/students') ?>;
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
    }
});
</script>
