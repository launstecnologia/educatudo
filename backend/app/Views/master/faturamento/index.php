<?php require_once __DIR__ . '/../../../Core/CreditosDecimalHelper.php'; ?>
<?php
$compras = $compras ?? [];
$escolas = $escolas ?? [];
$totais = $totais ?? ['quantidade' => 0, 'bruto' => 0, 'pago' => 0, 'pendentes' => 0];
$filtro_escola = (int) ($filtro_escola ?? 0);
$filtro_status = $filtro_status ?? '';
$filtro_billing_type = $filtro_billing_type ?? '';
$filtro_user_type = $filtro_user_type ?? '';
$filtro_q = $filtro_q ?? '';

$filtrosAtivos = 0;
if ($filtro_escola > 0) {
    $filtrosAtivos++;
}
if ($filtro_status !== '') {
    $filtrosAtivos++;
}
if ($filtro_billing_type !== '') {
    $filtrosAtivos++;
}
if ($filtro_user_type !== '') {
    $filtrosAtivos++;
}
if ($filtro_q !== '') {
    $filtrosAtivos++;
}
$limparUrl = URL . '/master/faturamento';
?>

<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-slate-900 mb-1">Faturamento</h2>
            <p class="text-slate-600 text-sm">Acompanhe as compras de TudiCoins feitas por alunos e professores nas escolas.</p>
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

<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Compras encontradas</p>
        <p class="text-2xl font-bold text-blue-600 mt-1"><?= (int) ($totais['quantidade'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Valor bruto</p>
        <p class="text-2xl font-bold text-slate-800 mt-1">R$ <?= number_format((float) ($totais['bruto'] ?? 0), 2, ',', '.') ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Confirmado</p>
        <p class="text-2xl font-bold text-green-600 mt-1">R$ <?= number_format((float) ($totais['pago'] ?? 0), 2, ',', '.') ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <p class="text-sm text-slate-500">Pendentes</p>
        <p class="text-2xl font-bold text-amber-600 mt-1"><?= (int) ($totais['pendentes'] ?? 0) ?></p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <?php if (empty($compras)): ?>
    <div class="px-6 py-10 text-center text-sm text-slate-500">
        <p>Nenhuma compra encontrada com os filtros atuais.</p>
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
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Comprador</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Pacote</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Valor</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Meio</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase">Datas</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php foreach ($compras as $compra): ?>
            <?php
                $status = (string) ($compra['status'] ?? '');
                $statusLabel = [
                    'pending' => 'Aguardando',
                    'paid' => 'Pago',
                    'failed' => 'Falhou',
                    'refunded' => 'Estornado',
                    'cancelled' => 'Cancelado',
                ][$status] ?? $status;
                $statusClass = [
                    'pending' => 'bg-amber-100 text-amber-800',
                    'paid' => 'bg-green-100 text-green-800',
                    'failed' => 'bg-red-100 text-red-800',
                    'refunded' => 'bg-slate-100 text-slate-700',
                    'cancelled' => 'bg-slate-100 text-slate-700',
                ][$status] ?? 'bg-slate-100 text-slate-700';
                $billing = strtoupper((string) ($compra['billing_type'] ?? ''));
                $billingLabel = [
                    'PIX' => 'PIX',
                    'CREDIT_CARD' => 'Cartão',
                    'BOLETO' => 'Boleto',
                ][$billing] ?? ($billing !== '' ? $billing : '—');
                $valor = 'R$ ' . number_format(((int) ($compra['valor_centavos'] ?? 0)) / 100, 2, ',', '.');
                $creditos = \CreditosDecimalHelper::fromScalar($compra['creditos'] ?? 0, 0.0);
                $tipoUser = (string) ($compra['user_type'] ?? '');
                $tipoLabel = $tipoUser === 'aluno' ? 'Aluno' : ($tipoUser === 'professor' ? 'Professor' : $tipoUser);
            ?>
            <tr class="hover:bg-slate-50">
                <td class="px-6 py-4 text-sm text-slate-700"><?= htmlspecialchars((string) ($compra['escola_nome'] ?? '')) ?></td>
                <td class="px-6 py-4 text-sm text-slate-700">
                    <span class="block font-medium"><?= htmlspecialchars((string) ($compra['usuario_nome'] ?? '—')) ?></span>
                    <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars((string) ($compra['usuario_email'] ?? '—')) ?></span>
                    <span class="block text-xs text-slate-400 mt-0.5"><?= htmlspecialchars($tipoLabel) ?></span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-700">
                    <span class="block font-medium"><?= htmlspecialchars((string) ($compra['pacote_nome'] ?? 'Pacote')) ?></span>
                    <span class="block text-xs text-slate-500 mt-0.5"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($creditos)) ?></span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-900 font-medium"><?= htmlspecialchars($valor) ?></td>
                <td class="px-6 py-4 text-sm text-slate-700">
                    <span class="block"><?= htmlspecialchars($billingLabel) ?></span>
                    <?php if (!empty($compra['asaas_payment_id'])): ?>
                    <span class="block text-xs text-slate-400 mt-0.5">Asaas: <?= htmlspecialchars((string) $compra['asaas_payment_id']) ?></span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 text-sm">
                    <span class="inline-flex rounded-full px-2 py-1 text-xs font-semibold <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                </td>
                <td class="px-6 py-4 text-sm text-slate-500">
                    <span class="block">Compra: <?= !empty($compra['created_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $compra['created_at']))) : '—' ?></span>
                    <span class="block text-xs mt-0.5">Pago: <?= !empty($compra['paid_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string) $compra['paid_at']))) : '—' ?></span>
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
    $paginationRoute = URL . '/master/faturamento';
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
        <h3 class="text-lg font-semibold text-slate-900">Filtrar faturamento</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-slate-400 hover:text-slate-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="get" action="<?= URL ?>/master/faturamento" class="flex flex-col flex-1 overflow-hidden">
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
                <label for="filtro_status" class="block text-sm font-medium text-slate-700 mb-1.5">Status</label>
                <select id="filtro_status" name="status" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="pending" <?= $filtro_status === 'pending' ? 'selected' : '' ?>>Aguardando</option>
                    <option value="paid" <?= $filtro_status === 'paid' ? 'selected' : '' ?>>Pago</option>
                    <option value="failed" <?= $filtro_status === 'failed' ? 'selected' : '' ?>>Falhou</option>
                    <option value="refunded" <?= $filtro_status === 'refunded' ? 'selected' : '' ?>>Estornado</option>
                    <option value="cancelled" <?= $filtro_status === 'cancelled' ? 'selected' : '' ?>>Cancelado</option>
                </select>
            </div>
            <div>
                <label for="filtro_billing" class="block text-sm font-medium text-slate-700 mb-1.5">Meio</label>
                <select id="filtro_billing" name="billing_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="PIX" <?= $filtro_billing_type === 'PIX' ? 'selected' : '' ?>>PIX</option>
                    <option value="CREDIT_CARD" <?= $filtro_billing_type === 'CREDIT_CARD' ? 'selected' : '' ?>>Cartão</option>
                    <option value="BOLETO" <?= $filtro_billing_type === 'BOLETO' ? 'selected' : '' ?>>Boleto</option>
                </select>
            </div>
            <div>
                <label for="filtro_user_type" class="block text-sm font-medium text-slate-700 mb-1.5">Comprador</label>
                <select id="filtro_user_type" name="user_type" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Todos</option>
                    <option value="aluno" <?= $filtro_user_type === 'aluno' ? 'selected' : '' ?>>Aluno</option>
                    <option value="professor" <?= $filtro_user_type === 'professor' ? 'selected' : '' ?>>Professor</option>
                </select>
            </div>
            <div>
                <label for="filtro_q" class="block text-sm font-medium text-slate-700 mb-1.5">Buscar</label>
                <input type="text" id="filtro_q" name="q" value="<?= htmlspecialchars((string) $filtro_q) ?>"
                       placeholder="Nome, e-mail, pacote..."
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
