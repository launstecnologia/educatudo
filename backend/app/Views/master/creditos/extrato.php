<?php
require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php';
require_once __DIR__ . '/../../../Core/CreditosModuleRegistry.php';

$movimentacoes = $movimentacoes ?? [];
$escolas = $escolas ?? [];
$totais = $totais ?? ['quantidade_filtrada' => 0, 'quantidade_exibida' => 0, 'consumido_filtrado' => 0, 'consumido_geral' => 0];
$modulos = $modulos ?? [];
$filtro_escola = (int) ($filtro_escola ?? 0);
$filtro_user_type = $filtro_user_type ?? '';
$filtro_modulo_key = $filtro_modulo_key ?? '';
$filtro_tipo = $filtro_tipo ?? '';
$filtro_data_ini = $filtro_data_ini ?? date('Y-m-01');
$filtro_data_fim = $filtro_data_fim ?? date('Y-m-t');
$filtro_q = $filtro_q ?? '';

$filtrosAtivos = 0;
if ($filtro_escola > 0) {
    $filtrosAtivos++;
}
if ($filtro_user_type !== '') {
    $filtrosAtivos++;
}
if ($filtro_modulo_key !== '') {
    $filtrosAtivos++;
}
if ($filtro_tipo !== '') {
    $filtrosAtivos++;
}
if ($filtro_q !== '') {
    $filtrosAtivos++;
}
$mesIniPadrao = date('Y-m-01');
$mesFimPadrao = date('Y-m-t');
if ($filtro_data_ini !== $mesIniPadrao) {
    $filtrosAtivos++;
}
if ($filtro_data_fim !== $mesFimPadrao) {
    $filtrosAtivos++;
}

$limparUrl = URL . '/master/creditos/extrato?data_ini=' . rawurlencode(date('Y-m-01')) . '&data_fim=' . rawurlencode(date('Y-m-t'));

$limparDetalhe = static function (string $texto): string {
    $t = trim($texto);
    if ($t === '') {
        return '';
    }
    $t = preg_replace('/\b[a-z0-9]+(?:_[a-z0-9]+){2,}\b/i', '', $t) ?? $t;
    $t = preg_replace('/\b[a-z]{1,3}_[a-z0-9_]{8,}\b/i', '', $t) ?? $t;
    $t = preg_replace('/\s{2,}/', ' ', $t) ?? $t;
    return trim($t, " \t\n\r\0\x0B-–—|");
};
?>

<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1">Extrato de TudiCoins</h2>
            <p class="text-slate-600 text-sm">Consumo de alunos, professores e usuários admin por escola. 1 TudiCoin = R$ 0,20.</p>
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

<div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Consumos no período</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= (int) ($totais['quantidade_filtrada'] ?? 0) ?></p>
        <p class="text-xs text-slate-400 mt-1">Exibindo <?= (int) ($totais['quantidade_exibida'] ?? 0) ?> linhas</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">TudiCoins no filtro</p>
        <p class="text-2xl font-bold text-amber-600 mt-1"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay((float) ($totais['consumido_filtrado'] ?? 0))) ?></p>
        <p class="text-xs text-slate-400 mt-1">≈ <?= htmlspecialchars(CreditosDecimalHelper::formatReaisFromTudicoins((float) ($totais['consumido_filtrado'] ?? 0))) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">TudiCoins no total (todas as datas)</p>
        <p class="text-2xl font-bold text-slate-800 mt-1"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay((float) ($totais['consumido_geral'] ?? 0))) ?></p>
        <p class="text-xs text-slate-400 mt-1">≈ <?= htmlspecialchars(CreditosDecimalHelper::formatReaisFromTudicoins((float) ($totais['consumido_geral'] ?? 0))) ?></p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($movimentacoes)): ?>
    <div class="px-6 py-10 text-center text-sm text-slate-500">
        <p>Nenhum consumo encontrado com os filtros atuais.</p>
        <button type="button" onclick="openFilterDrawer()" class="mt-3 text-sm text-blue-600 hover:underline">Ajustar filtros</button>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Usuário</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Módulo</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase">TudiCoins</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 bg-white">
                <?php foreach ($movimentacoes as $mov): ?>
                <?php
                    $moduloKey = (string) ($mov['modulo_key'] ?? '');
                    $moduloLabel = $modulos[$moduloKey] ?? ($moduloKey !== '' ? $moduloKey : '—');
                    if (str_starts_with($moduloKey, 'app_externo_') && isset($modulos[$moduloKey])) {
                        $moduloLabel = $modulos[$moduloKey];
                    }
                    $isExterno = (($mov['origem_tipo'] ?? '') === 'app_externo')
                        || CreditosModuleRegistry::isAppExterno($moduloKey);
                    $origemLabel = $isExterno ? 'App externo' : 'Plataforma';
                    $origemBadge = $isExterno
                        ? 'bg-violet-100 text-violet-800'
                        : 'bg-slate-100 text-slate-600';
                    $detalheBruto = (string) ($mov['observacao'] ?? '');
                    $detalhe = $limparDetalhe($detalheBruto);
                    $valor = (float) ($mov['valor_consumido'] ?? 0);
                    $tipoUser = (string) ($mov['user_type'] ?? '');
                    $tipoLabel = $tipoUser === 'aluno' ? 'Aluno' : ($tipoUser === 'professor' ? 'Professor' : ($tipoUser === 'admin' ? 'Admin' : $tipoUser));
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 text-sm text-slate-700 whitespace-nowrap">
                        <?= !empty($mov['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $mov['created_at']))) : '—' ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars((string) ($mov['escola_nome'] ?? '—')) ?></td>
                    <td class="px-6 py-4 text-sm text-slate-700">
                        <span class="block font-medium"><?= htmlspecialchars((string) ($mov['usuario_nome'] ?? '—')) ?></span>
                        <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars($tipoLabel) ?></span>
                    </td>
                    <td class="px-6 py-4 text-sm text-slate-700">
                        <span class="block font-medium"><?= htmlspecialchars((string) $moduloLabel) ?></span>
                        <span class="inline-flex mt-1 px-2 py-0.5 text-[11px] font-medium rounded-full <?= $origemBadge ?>"><?= htmlspecialchars($origemLabel) ?></span>
                        <?php if ($detalhe !== ''): ?>
                        <span class="block text-xs text-slate-500 mt-1"><?= htmlspecialchars($detalhe) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-right whitespace-nowrap">
                        <span class="block font-medium text-amber-600 tabular-nums"><?= htmlspecialchars(CreditosDecimalHelper::formatDisplay($valor)) ?></span>
                        <span class="block text-xs text-slate-400 mt-0.5 tabular-nums"><?= htmlspecialchars(CreditosDecimalHelper::formatReaisFromTudicoins($valor)) ?></span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    $pag = $pagination ?? [];
    $total = (int) ($pag['total'] ?? 0);
    $perPage = (int) ($pag['per_page'] ?? 10);
    $page = (int) ($pag['page'] ?? 1);
    $totalPages = (int) ($pag['total_pages'] ?? 1);
    $queryParams = array_merge($_GET ?? [], []);
    unset($queryParams['page']);
    $baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
    $sep = $baseQuery === '' ? '?' : '&';
    $paginationRoute = URL . '/master/creditos/extrato';
    ?>
    <?php if ($total > 0): ?>
    <div class="px-6 py-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-slate-600">
            Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> registro(s)
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
        <h3 class="text-lg font-semibold text-slate-900">Filtrar extrato</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/master/creditos/extrato" class="flex flex-col flex-1 overflow-hidden">
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
                <label for="filtro_user_type" class="block text-sm font-medium text-slate-700 mb-1.5">Usuário</label>
                <select id="filtro_user_type" name="user_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="aluno" <?= $filtro_user_type === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                    <option value="professor" <?= $filtro_user_type === 'professor' ? 'selected' : '' ?>>Professor</option>
                    <option value="admin" <?= $filtro_user_type === 'admin' ? 'selected' : '' ?>>Admin</option>
                </select>
            </div>
            <div>
                <label for="filtro_tipo" class="block text-sm font-medium text-slate-700 mb-1.5">Tipo</label>
                <select id="filtro_tipo" name="tipo" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="plataforma" <?= $filtro_tipo === 'plataforma' ? 'selected' : '' ?>>Plataforma</option>
                    <option value="app_externo" <?= $filtro_tipo === 'app_externo' ? 'selected' : '' ?>>App externo</option>
                </select>
            </div>
            <div>
                <label for="filtro_modulo" class="block text-sm font-medium text-slate-700 mb-1.5">Módulo</label>
                <select id="filtro_modulo" name="modulo_key" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <?php foreach ($modulos as $moduloKey => $moduloLabel): ?>
                    <option value="<?= htmlspecialchars((string) $moduloKey) ?>" <?= $filtro_modulo_key === (string) $moduloKey ? 'selected' : '' ?>><?= htmlspecialchars((string) $moduloLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label for="filtro_data_ini" class="block text-sm font-medium text-slate-700 mb-1.5">Data inicial</label>
                <input type="date" id="filtro_data_ini" name="data_ini" value="<?= htmlspecialchars((string) $filtro_data_ini) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_data_fim" class="block text-sm font-medium text-slate-700 mb-1.5">Data final</label>
                <input type="date" id="filtro_data_fim" name="data_fim" value="<?= htmlspecialchars((string) $filtro_data_fim) ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="filtro_q" class="block text-sm font-medium text-slate-700 mb-1.5">Buscar</label>
                <input type="text" id="filtro_q" name="q" value="<?= htmlspecialchars((string) $filtro_q) ?>"
                       placeholder="Nome, e-mail, detalhe..."
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
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeFilterDrawer();
});
</script>
