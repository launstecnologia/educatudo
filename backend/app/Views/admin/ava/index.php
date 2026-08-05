<?php
$cursos = $cursos ?? [];
$categorias = $categorias ?? [];
$modalidades = $modalidades ?? [];
$status_opcoes = $status_opcoes ?? [];
$busca = (string) ($busca ?? '');
$status = (string) ($status ?? '');
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');

$filtrosAtivos = 0;
if ($busca !== '') { $filtrosAtivos++; }
if ($status !== '') { $filtrosAtivos++; }

$filtrosBtn = '<button type="button" onclick="openFilterDrawer()" class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-filter mr-2 text-gray-500"></i> Filtros';
if ($filtrosAtivos > 0) {
    $filtrosBtn .= '<span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold">' . $filtrosAtivos . '</span>';
}
$filtrosBtn .= '</button>';

$page_header_title = 'AVA / EAD';
$page_header_subtitle = 'Ambiente Virtual de Aprendizagem: cursos, disciplinas, módulos e aulas.';
$page_header_actions = $filtrosBtn
    . '<a href="' . URL . '/admin/ava/categorias" class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors"><i class="fa-solid fa-tags mr-2 text-gray-500"></i> Categorias</a>'
    . '<a href="' . URL . '/admin/ava/cursos/novo" class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm"><i class="fa-solid fa-plus mr-2"></i> Novo Curso</a>';
include __DIR__ . '/../_partials/page_header_list.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <?php foreach (['Curso', 'Modalidade', 'Disciplinas', 'Status', ''] as $h): ?>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider"><?= htmlspecialchars($h) ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($cursos)): ?>
                            <tr><td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <i class="fa-solid fa-graduation-cap text-4xl text-gray-300 mb-3"></i>
                                <p><?= $filtrosAtivos > 0 ? 'Nenhum curso encontrado com os filtros aplicados.' : 'Nenhum curso cadastrado ainda.' ?></p>
                                <?php if ($filtrosAtivos > 0): ?>
                                    <button type="button" onclick="clearFilters()" class="mt-3 text-sm text-blue-600 hover:text-blue-800">Limpar filtros</button>
                                <?php endif; ?>
                            </td></tr>
                        <?php else: foreach ($cursos as $c): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4">
                                    <a href="<?= URL ?>/admin/ava/cursos/<?= (int) $c['id'] ?>" class="font-medium text-gray-900 hover:text-green-700"><?= htmlspecialchars((string) $c['nome']) ?></a>
                                    <?php if (!empty($c['categoria_nome'])): ?><div class="text-xs text-gray-500"><?= htmlspecialchars((string) $c['categoria_nome']) ?></div><?php endif; ?>
                                </td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars($modalidades[$c['modalidade']] ?? (string) $c['modalidade']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= (int) ($c['total_disciplinas'] ?? 0) ?></td>
                                <td class="px-6 py-4">
                                    <?php $st = (string) $c['status']; $cls = $st === 'ativo' ? 'bg-green-100 text-green-700' : ($st === 'arquivado' ? 'bg-gray-100 text-gray-600' : 'bg-amber-100 text-amber-700'); ?>
                                    <span class="inline-flex px-2 py-1 text-xs font-medium rounded-full <?= $cls ?>"><?= htmlspecialchars($status_opcoes[$st] ?? $st) ?></span>
                                </td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <?php ob_start(); ?>
                                    <a href="<?= URL ?>/admin/ava/cursos/<?= (int) $c['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-gear text-gray-400 w-4 text-center"></i> Gerenciar
                                    </a>
                                    <a href="<?= URL ?>/admin/ava/cursos/<?= (int) $c['id'] ?>/editar" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                    </a>
                                    <div class="border-t border-gray-100 my-1"></div>
                                    <form method="post" action="<?= URL ?>/admin/ava/cursos/<?= (int) $c['id'] ?>/excluir" onsubmit="return confirm('Excluir este curso e todo o seu conteúdo?');">
                                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                                        <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                        </button>
                                    </form>
                                    <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                    <?php $row_actions_dropdown_id = 'row-actions-curso-' . (int) $c['id']; ?>
                                    <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

<!-- Filtro lateral (drawer) -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar cursos</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/ava" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_busca" class="block text-sm font-medium text-gray-700 mb-1.5">Buscar curso</label>
                <input type="text" id="filtro_busca" name="busca" value="<?= htmlspecialchars($busca) ?>"
                       placeholder="Nome ou código do curso..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos os status</option>
                    <?php foreach ($status_opcoes as $k => $v): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= $status === $k ? 'selected' : '' ?>><?= htmlspecialchars($v) ?></option>
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
    window.location.href = <?= json_encode(URL . '/admin/ava') ?>;
}
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') { closeFilterDrawer(); }
});
</script>
