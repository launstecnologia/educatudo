<?php
$linhas = $linhas ?? [];
$escolas = $escolas ?? [];
$totais = $totais ?? [
    'escolas' => 0,
    'alunos_pagantes' => 0,
    'professores_pagantes' => 0,
    'valor_total' => 0,
    'sem_valor' => 0,
];
$filtro_escola = (int) ($filtro_escola ?? 0);
$filtro_q = (string) ($filtro_q ?? '');
$csrf_token = $csrf_token ?? '';
$flash = $flash ?? null;

$filtrosAtivos = 0;
if ($filtro_escola > 0) {
    $filtrosAtivos++;
}
if ($filtro_q !== '') {
    $filtrosAtivos++;
}
$limparUrl = URL . '/master/mensalidade';

$fmtMoeda = static function ($valor): string {
    return 'R$ ' . number_format((float) $valor, 2, ',', '.');
};
?>

<?php if (!empty($flash['message'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
    <?= htmlspecialchars((string) $flash['message']) ?>
</div>
<?php endif; ?>

<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1">Mensalidade</h2>
            <p class="text-slate-600 text-sm">Cobrança da plataforma por escola: valor por usuário × alunos (e professores) pagantes.</p>
        </div>
        <button type="button" onclick="openFilterDrawer()"
                class="relative inline-flex items-center px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors flex-shrink-0">
            <i class="fa-solid fa-filter mr-2 text-slate-500"></i>
            Filtros
            <?php if ($filtrosAtivos > 0): ?>
            <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivos ?></span>
            <?php endif; ?>
        </button>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Escolas</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= (int) ($totais['escolas'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Alunos pagantes</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= number_format((int) ($totais['alunos_pagantes'] ?? 0), 0, ',', '.') ?></p>
        <p class="text-xs text-slate-400 mt-1"><?= number_format((int) ($totais['professores_pagantes'] ?? 0), 0, ',', '.') ?> professores pagantes</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Receita mensal estimada</p>
        <p class="text-2xl font-bold text-green-600 mt-1"><?= htmlspecialchars($fmtMoeda($totais['valor_total'] ?? 0)) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Sem valor definido</p>
        <p class="text-2xl font-bold text-amber-600 mt-1"><?= (int) ($totais['sem_valor'] ?? 0) ?></p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($linhas)): ?>
    <div class="px-6 py-10 text-center text-sm text-slate-500">
        <p>Nenhuma escola encontrada com os filtros atuais.</p>
        <?php if ($filtrosAtivos > 0): ?>
        <button type="button" onclick="openFilterDrawer()" class="mt-3 text-sm text-blue-600 hover:underline">Ajustar filtros</button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-slate-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Alunos pagantes</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Valor / usuário</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Total estimado</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">Ações</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($linhas as $linha): ?>
            <?php
                $escolaId = (int) ($linha['escola_id'] ?? 0);
                $valor = (float) ($linha['valor_por_usuario'] ?? 0);
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-4 text-sm">
                    <a href="<?= URL ?>/master/mensalidade/escola/<?= $escolaId ?>" class="font-medium text-slate-900 hover:text-blue-600">
                        <?= htmlspecialchars((string) ($linha['escola_nome'] ?? '')) ?>
                    </a>
                    <?php if (!empty($linha['erro'])): ?>
                    <span class="block text-xs text-red-600 mt-0.5"><?= htmlspecialchars((string) $linha['erro']) ?></span>
                    <?php else: ?>
                    <span class="block text-xs text-slate-400 mt-0.5"><?= (int) ($linha['alunos_ativos'] ?? 0) ?> alunos ativos</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm text-slate-700">
                    <span class="block font-medium"><?= number_format((int) ($linha['alunos_pagantes'] ?? 0), 0, ',', '.') ?></span>
                    <span class="block text-xs text-slate-400 mt-0.5"><?= number_format((int) ($linha['professores_pagantes'] ?? 0), 0, ',', '.') ?> professores</span>
                </td>
                <td class="px-6 py-4 text-sm font-medium text-slate-900"><?= htmlspecialchars($fmtMoeda($valor)) ?></td>
                <td class="px-6 py-4 text-sm font-semibold text-green-700"><?= htmlspecialchars($fmtMoeda($linha['valor_total'] ?? 0)) ?></td>
                <td class="px-6 py-4 text-sm text-right">
                    <?php if (empty($linha['erro'])): ?>
                    <button type="button"
                            class="js-abrir-valor inline-flex items-center px-3 py-1.5 text-sm font-medium text-blue-700 bg-blue-50 hover:bg-blue-100 rounded-lg"
                            data-escola-id="<?= $escolaId ?>"
                            data-escola-nome="<?= htmlspecialchars((string) ($linha['escola_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                            data-valor="<?= htmlspecialchars(number_format($valor, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>">
                        Definir valor
                    </button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php
    $pag = $pagination ?? [];
    $total = (int) ($pag['total'] ?? 0);
    $perPage = (int) ($pag['per_page'] ?? 20);
    $page = (int) ($pag['page'] ?? 1);
    $totalPages = (int) ($pag['total_pages'] ?? 1);
    $queryParams = $_GET ?? [];
    unset($queryParams['page']);
    $baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
    $sep = $baseQuery === '' ? '?' : '&';
    $paginationRoute = URL . '/master/mensalidade';
    ?>
    <?php if ($total > 0): ?>
    <div class="px-6 py-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-600">
            Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> escola(s)
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
                <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-blue-600 text-white' : 'text-slate-700 bg-slate-100 hover:bg-slate-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
                <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Filtrar mensalidade</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/master/mensalidade" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_escola" class="block text-sm font-medium text-slate-700 mb-1.5">Escola</label>
                <select id="filtro_escola" name="escola_id" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="0">Todas</option>
                    <?php foreach ($escolas as $escola): ?>
                    <option value="<?= (int) ($escola['id'] ?? 0) ?>" <?= $filtro_escola === (int) ($escola['id'] ?? 0) ? 'selected' : '' ?>><?= htmlspecialchars((string) ($escola['nome'] ?? '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_q" class="block text-sm font-medium text-slate-700 mb-1.5">Buscar</label>
                <input type="text" id="filtro_q" name="q" value="<?= htmlspecialchars($filtro_q) ?>"
                       placeholder="Nome da escola..."
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex gap-3 bg-slate-50">
            <a href="<?= htmlspecialchars($limparUrl) ?>"
               class="flex-1 px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50 transition-colors text-center">
                Limpar
            </a>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<div id="valorDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="fecharValorDrawer()"></div>
<aside id="valorDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Valor por usuário</h3>
        <button type="button" onclick="fecharValorDrawer()" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="post" action="<?= URL ?>/master/mensalidade/salvar" class="flex flex-col flex-1 overflow-hidden">
        <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
        <input type="hidden" name="escola_id" id="valor_escola_id" value="">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <p class="text-sm text-slate-600">Escola: <strong id="valor_escola_nome" class="text-slate-900"></strong></p>
            <div>
                <label for="valor_por_usuario" class="block text-sm font-medium text-slate-700 mb-1.5">Valor por usuário (R$)</label>
                <input type="number" id="valor_por_usuario" name="valor_por_usuario" step="0.01" min="0"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="0.00" required>
                <p class="mt-2 text-xs text-slate-500">Este valor é multiplicado pelos alunos e professores pagantes da escola.</p>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-200 flex gap-3 bg-slate-50">
            <button type="button" onclick="fecharValorDrawer()"
                    class="flex-1 px-4 py-2.5 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 bg-white hover:bg-slate-50">
                Cancelar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700">
                Salvar valor
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
function abrirValorDrawer(escolaId, escolaNome, valor) {
    document.getElementById('valor_escola_id').value = String(escolaId);
    document.getElementById('valor_escola_nome').textContent = escolaNome;
    document.getElementById('valor_por_usuario').value = Number(valor).toFixed(2);
    document.getElementById('valorDrawerBackdrop').classList.remove('hidden');
    var drawer = document.getElementById('valorDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
    document.getElementById('valor_por_usuario').focus();
}
function fecharValorDrawer() {
    document.getElementById('valorDrawerBackdrop').classList.add('hidden');
    var drawer = document.getElementById('valorDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
document.querySelectorAll('.js-abrir-valor').forEach(function(btn) {
    btn.addEventListener('click', function() {
        abrirValorDrawer(this.getAttribute('data-escola-id'), this.getAttribute('data-escola-nome'), this.getAttribute('data-valor'));
    });
});
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
        fecharValorDrawer();
    }
});
</script>
