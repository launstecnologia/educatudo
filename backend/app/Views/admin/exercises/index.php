<!-- Messages -->
<?php if (isset($_GET['success'])): ?>
<div class="mb-6 bg-green-50 border-l-4 border-green-500 text-green-700 p-4 rounded-lg shadow-lg" role="alert">
    <div class="flex items-center">
        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
            <p class="font-bold">Sucesso!</p>
            <p><?= htmlspecialchars(urldecode($_GET['success'])) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if (isset($_GET['error'])): ?>
<div class="mb-6 bg-red-50 border-l-4 border-red-500 text-red-700 p-4 rounded-lg shadow-lg" role="alert">
    <div class="flex items-center">
        <svg class="w-6 h-6 mr-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
        </svg>
        <div>
            <p class="font-bold">Erro!</p>
            <p><?= htmlspecialchars(urldecode($_GET['error'])) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php
$filtros = $filtros ?? ['materia' => '', 'nivel' => '', 'titulo' => ''];
$filtrosAtivosCount = 0;
foreach (['materia', 'nivel', 'titulo'] as $fk) {
    if (!empty($filtros[$fk])) {
        $filtrosAtivosCount++;
    }
}
?>

<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center flex-wrap gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Gestão de Exercícios</h2>
            <p class="text-gray-600">Crie e gerencie os exercícios das jornadas</p>
        </div>
        <div class="flex items-center gap-3 flex-wrap">
            <button type="button" onclick="openFilterDrawer()"
                    class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
                Filtros
                <?php if ($filtrosAtivosCount > 0): ?>
                <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
                <?php endif; ?>
            </button>
            <button type="button" onclick="showImportModal()"
                    class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-file-import mr-2 text-gray-500"></i>
                Importar JSON
            </button>
            <a href="<?= URL ?>/admin/exercises/export"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <i class="fa-solid fa-file-export mr-2 text-gray-500"></i>
                Exportar Todos
            </a>
            <a href="<?= URL ?>/admin/exercises/create"
               class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Exercício
            </a>
        </div>
    </div>
</div>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar exercícios</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/exercises" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_titulo" class="block text-sm font-medium text-gray-700 mb-1.5">Título</label>
                <input type="text" id="filtro_titulo" name="titulo" value="<?= htmlspecialchars($filtros['titulo']) ?>"
                       placeholder="Buscar por título..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_materia" class="block text-sm font-medium text-gray-700 mb-1.5">Matéria</label>
                <select id="filtro_materia" name="materia" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todas</option>
                    <?php foreach ($materias as $m): ?>
                        <option value="<?= htmlspecialchars($m['materia']) ?>" <?= $filtros['materia'] === $m['materia'] ? 'selected' : '' ?>>
                            <?= htmlspecialchars($m['materia']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_nivel" class="block text-sm font-medium text-gray-700 mb-1.5">Nível</label>
                <select id="filtro_nivel" name="nivel" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="Fácil" <?= $filtros['nivel'] === 'Fácil' ? 'selected' : '' ?>>Fácil</option>
                    <option value="Médio" <?= $filtros['nivel'] === 'Médio' ? 'selected' : '' ?>>Médio</option>
                    <option value="Difícil" <?= $filtros['nivel'] === 'Difícil' ? 'selected' : '' ?>>Difícil</option>
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

<!-- Exercises Table -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <div class="flex justify-between items-center">
            <h3 class="text-lg font-semibold text-gray-900">Lista de Exercícios</h3>
            <div class="text-sm text-gray-600">
                <span class="font-medium">Total:</span> <?= $pagination['total'] ?> exercícios
            </div>
        </div>
    </div>

    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Título</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Matéria</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nível</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data de Criação</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Estatísticas</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($exercises)): ?>
                    <tr>
                        <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                            <i class="fa-solid fa-circle-check text-4xl text-gray-300 mb-4"></i>
                            <p>Nenhum exercício cadastrado</p>
                            <a href="<?= URL ?>/admin/exercises/create" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                                <i class="fa-solid fa-plus mr-2"></i>
                                Criar Primeiro Exercício
                            </a>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($exercises as $exercise): ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">
                            <?= htmlspecialchars($exercise['titulo'] ?? 'Sem título') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($exercise['materia'] ?? 'N/A') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                            <?= htmlspecialchars($exercise['nivel_dificuldade'] ?? 'N/A') ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                            <?= date('d/m/Y', strtotime($exercise['created_at'])) ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <div class="flex flex-col space-y-2">
                                <div class="flex items-center space-x-2">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-blue-100 text-blue-800">
                                        <?= $exercise['total_questoes'] ?? 0 ?> questões
                                    </span>
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">
                                        <?= $exercise['total_execucoes'] ?? 0 ?> execuções
                                    </span>
                                </div>
                                <?php if (!empty($exercise['media_acerto']) && $exercise['media_acerto'] > 0): ?>
                                <div class="flex items-center">
                                    <span class="text-xs text-gray-500">
                                        Média: <?= number_format($exercise['media_acerto'], 1) ?>%
                                    </span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                            <?php ob_start(); ?>
                            <a href="<?= URL ?>/admin/exercises/<?= $exercise['id'] ?>/questions"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-list-check text-gray-400 w-4 text-center"></i> Questões
                            </a>
                            <a href="<?= URL ?>/admin/exercises/<?= $exercise['id'] ?>/edit"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </a>
                            <a href="<?= URL ?>/admin/exercises/<?= $exercise['id'] ?>/export"
                               class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                <i class="fa-solid fa-file-export text-gray-400 w-4 text-center"></i> Exportar
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <button type="button" onclick="deleteExercise(<?= $exercise['id'] ?>)"
                                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                            </button>
                            <?php
                            $row_actions_dropdown_items = ob_get_clean();
                            $row_actions_dropdown_id = 'row-actions-exercise-' . (int)$exercise['id'];
                            include __DIR__ . '/../_partials/row_actions_dropdown.php';
                            ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pag = $pagination ?? [];
    $pagTotal = (int)($pag['total'] ?? 0);
    $pagPerPage = (int)($pag['per_page'] ?? 10);
    $pagPage = (int)($pag['page'] ?? 1);
    $pagTotalPages = (int)($pag['total_pages'] ?? 1);
    $pagQueryParams = $_GET ?? [];
    unset($pagQueryParams['page']);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> exercício(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/exercises<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/exercises<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/exercises<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Modal de Importação -->
<div id="importModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50">
    <div class="flex items-center justify-center min-h-screen p-4">
        <div class="bg-white rounded-xl shadow-xl max-w-md w-full">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Importar Exercícios</h3>
                    <button onclick="hideImportModal()" class="text-gray-400 hover:text-gray-600">
                        <i class="fa-solid fa-xmark text-xl"></i>
                    </button>
                </div>

                <form action="<?= URL ?>/admin/exercises/import" method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">
                            Arquivo JSON
                        </label>
                        <input type="file" name="json_file" accept=".json" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="text-xs text-gray-500 mt-1">
                            Selecione um arquivo JSON com o formato correto dos exercícios
                        </p>
                    </div>

                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <h4 class="text-sm font-medium text-blue-800 mb-2">Formato esperado:</h4>
                        <pre class="text-xs text-blue-700 overflow-x-auto">{
  "exercicios": [
    {
      "titulo_lista": "Nome do Exercício",
      "materia": "Matemática",
      "serie": "1º ano do Ensino Médio",
      "nivel_dificuldade": "Fácil",
      "questoes": [...]
    }
  ]
}</pre>
                    </div>

                    <div class="flex gap-3 pt-4">
                        <button type="button" onclick="hideImportModal()"
                                class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                            Cancelar
                        </button>
                        <button type="submit"
                                class="btn-primary-custom flex-1 px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors hover:opacity-90">
                            Importar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        const successMessage = document.querySelector('div[role="alert"]');
        if (successMessage) {
            setTimeout(() => {
                successMessage.style.transition = 'opacity 0.5s ease-out';
                successMessage.style.opacity = '0';
                setTimeout(() => {
                    successMessage.remove();
                }, 500);
            }, 5000);
        }
    });

    function showImportModal() {
        document.getElementById('importModal').classList.remove('hidden');
    }

    function hideImportModal() {
        document.getElementById('importModal').classList.add('hidden');
    }

    function deleteExercise(id) {
        if (confirm('Tem certeza que deseja excluir este exercício? Esta ação não pode ser desfeita.')) {
            fetch(`<?= URL ?>/admin/exercises/${id}`, {
                method: 'DELETE',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Erro ao excluir: ' + data.error);
                }
            })
            .catch(error => {
                alert('Erro de conexão');
            });
        }
    }

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
        window.location.href = <?= json_encode(URL . '/admin/exercises') ?>;
    }

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeFilterDrawer();
        }
    });
</script>
