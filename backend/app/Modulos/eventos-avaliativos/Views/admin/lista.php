<?php
$itens = is_array($itens ?? null) ? $itens : [];
$schemaPronto = !empty($schema_pronto);
$anoPadrao = (int) ($ano_padrao ?? 0);
$anoFiltro = (int) ($ano_filtro ?? $anoPadrao);
$anos = is_array($anos ?? null) ? $anos : [$anoFiltro];
$cursos = is_array($cursos ?? null) ? $cursos : [];
$cursoFiltro = (int) ($curso_filtro ?? 0);
$cursoNome = trim((string) ($curso_nome ?? ''));
$filtrosAtivos = (int) ($filtros_ativos ?? 0);

$page_header_title = 'Eventos Avaliativos';
$page_header_subtitle = 'Escolha a regra de nota para abrir os períodos e as recuperações.';
ob_start();
?>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500" aria-hidden="true"></i>
    Filtros
    <?php if ($filtrosAtivos > 0): ?>
        <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
    <?php endif; ?>
</button>
<?php
$page_header_actions = ob_get_clean();
include \App\Core\ViewPaths::adminPartial('page_header_list.php');
include \App\Core\ViewPaths::adminPartial('flash_message.php');
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration de regras de aprovação no painel Master antes de abrir os eventos.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Regras de Nota</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Etapa/Ciclo/Curso</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($itens === []): ?>
                <tr>
                    <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                        <p>Nenhuma regra de nota em <?= $anoFiltro ?><?= $cursoNome !== '' ? ' para ' . htmlspecialchars($cursoNome, ENT_QUOTES, 'UTF-8') : '' ?>.</p>
                        <p class="text-sm mt-1">Crie a regra em Regras de Aprovação para montar os eventos avaliativos.</p>
                    </td>
                </tr>
                <?php else: ?>
                    <?php foreach ($itens as $row): ?>
                        <?php
                            $id = (int) ($row['id'] ?? 0);
                            $anoRegra = (int) ($row['ano_letivo'] ?? 0);
                            $anoAbrir = $anoRegra > 0 ? $anoRegra : $anoPadrao;
                            $urlAbrir = URL . '/admin/eventos-avaliativos?ano=' . $anoAbrir . ($id > 0 ? '&regra=' . $id : '');
                            $curso = trim((string) ($row['curso_nome'] ?? ''));
                            $serie = trim((string) ($row['serie_nome'] ?? ''));
                        ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4">
                            <a href="<?= $urlAbrir ?>" class="text-sm font-medium text-gray-900 hover:text-indigo-700">
                                <?= htmlspecialchars((string) ($row['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-700">
                            <?= $curso !== '' ? htmlspecialchars($curso, ENT_QUOTES, 'UTF-8') : 'Todos os cursos' ?>
                            <?php if ($serie !== ''): ?>
                                <div class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($serie, ENT_QUOTES, 'UTF-8') ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600"><?= $anoRegra > 0 ? $anoRegra : '—' ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-right">
                            <a href="<?= $urlAbrir ?>" class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100 hover:border-blue-300 transition-colors">
                                <i class="fa-solid fa-arrow-right text-blue-600" aria-hidden="true"></i>
                                Abrir
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar regras de nota</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar filtros">
            <i class="fa-solid fa-xmark text-xl" aria-hidden="true"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/eventos-avaliativos" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro-ano-lista" class="block text-sm font-medium text-gray-700 mb-1.5">Ano</label>
                <select id="filtro-ano-lista" name="ano_lista"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($anos as $opcao): ?>
                        <option value="<?= (int) $opcao ?>" <?= (int) $opcao === $anoFiltro ? 'selected' : '' ?>><?= (int) $opcao ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro-curso" class="block text-sm font-medium text-gray-700 mb-1.5">Curso</label>
                <select id="filtro-curso" name="curso_id"
                        class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todos</option>
                    <?php foreach ($cursos as $curso): ?>
                        <?php $cursoId = (int) ($curso['id'] ?? 0); ?>
                        <option value="<?= $cursoId ?>" <?= $cursoId === $cursoFiltro ? 'selected' : '' ?>><?= htmlspecialchars((string) ($curso['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></option>
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
function clearFilters() {
    window.location.href = <?= json_encode(URL . '/admin/eventos-avaliativos') ?>;
}
document.addEventListener('keydown', function (evento) {
    if (evento.key === 'Escape') {
        closeFilterDrawer();
    }
});
</script>
