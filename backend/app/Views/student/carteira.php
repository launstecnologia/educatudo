<?php
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../Core/LayoutHelper.php';
}
if (!class_exists('CreditosDecimalHelper')) {
    require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';
}
if (!class_exists('CreditosModuleRegistry')) {
    require_once __DIR__ . '/../../Core/CreditosModuleRegistry.php';
}
$saldo = isset($saldo) ? \CreditosDecimalHelper::fromScalar($saldo, 0.0) : 0.0;
$wallet_saldos = $wallet_saldos ?? ['saldo_escola' => 0, 'saldo_comprado' => 0, 'saldo_total' => $saldo];
$saldoEscola = \CreditosDecimalHelper::fromScalar($wallet_saldos['saldo_escola'] ?? 0, 0.0);
$saldoComprado = \CreditosDecimalHelper::fromScalar($wallet_saldos['saldo_comprado'] ?? 0, 0.0);
$movimentacoes = $movimentacoes ?? [];
$creditosHabilitado = (bool) ($creditos_habilitado ?? false);
$creditosLiberarB2C = LayoutHelper::get('creditos_liberar_b2c', '0') === '1';
$filtro_tipo = $filtro_tipo ?? '';
$filtro_modulo = $filtro_modulo ?? '';
$data_ini = $data_ini ?? '';
$data_fim = $data_fim ?? '';
$modulos_opcao_filtro = $modulos_opcao_filtro ?? [];
$pag = $pagination ?? [];
$total = (int) ($pag['total'] ?? 0);
$perPage = (int) ($pag['per_page'] ?? 10);
$page = (int) ($pag['page'] ?? 1);
$totalPages = (int) ($pag['total_pages'] ?? 1);
$queryParams = array_merge($_GET ?? [], []);
unset($queryParams['page']);
$baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
$sep = $baseQuery === '' ? '?' : '&';
$paginationRoute = URL . '/carteira';
$tiposFiltro = [
    '' => 'Todos os tipos',
    'consumo' => 'Consumo',
    'compra' => 'Compra',
    'cortesia' => 'Cortesia',
    'estorno' => 'Estorno',
    'recarga_mensal' => 'Recarga mensal',
    'recarga_inicial' => 'TudiCoins iniciais',
    'recarga_plano' => 'Recarga plano',
];
$tipoLabels = [
    'recarga_mensal' => 'Recarga mensal',
    'recarga_inicial' => 'TudiCoins iniciais',
    'cortesia' => 'Cortesia',
    'compra' => 'Compra',
    'consumo' => 'Consumo',
    'estorno' => 'Estorno',
    'recarga_plano' => 'Recarga plano',
];
?>
<div class="w-full space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900 mb-1">Minha Carteira</h1>
        <p class="text-gray-600 text-sm">Seu saldo de TudiCoins e histórico de movimentações.</p>
    </div>

    <?php if (!$creditosHabilitado): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 text-amber-800 text-sm">
        O sistema de TudiCoins não está habilitado para sua escola no momento. Seu saldo é exibido apenas para consulta.
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
        <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Saldo atual</p>
        <p class="text-4xl font-bold mt-1 text-accent tabular-nums"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldo)) ?> <span class="text-lg font-normal text-gray-600">TudiCoins</span></p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">TudiCoins da escola</p>
                <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldoEscola)) ?></p>
            </div>
            <div class="rounded-lg border border-gray-200 bg-gray-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-600">TudiCoins comprados</p>
                <p class="text-2xl font-bold text-gray-900 mt-1 tabular-nums"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldoComprado)) ?></p>
                <p class="text-xs text-gray-500 mt-1">Não expiram na recarga mensal da escola.</p>
            </div>
        </div>
        <?php if ($creditosHabilitado && $creditosLiberarB2C): ?>
        <div class="flex gap-3 mt-4">
            <a href="<?= URL ?>/carteira/planos" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">Planos (assinatura)</a>
        </div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Histórico de movimentações</h2>
            <p class="text-sm text-gray-600 mt-0.5">Consumos, recargas e ajustes da sua carteira.</p>
        </div>

        <form method="get" action="<?= URL ?>/carteira" class="px-6 py-4 border-b border-gray-200 bg-gray-50">
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6 gap-4 items-end">
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                    <select name="filtro_tipo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <?php foreach ($tiposFiltro as $k => $lab): ?>
                        <option value="<?= htmlspecialchars($k) ?>" <?= $filtro_tipo === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Módulo</label>
                    <select name="filtro_modulo" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <?php foreach ($modulos_opcao_filtro as $mk => $mlab): ?>
                        <option value="<?= htmlspecialchars($mk) ?>" <?= $filtro_modulo === $mk ? 'selected' : '' ?>><?= htmlspecialchars($mlab) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">De</label>
                    <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="sm:col-span-1">
                    <label class="block text-sm font-medium text-gray-700 mb-1.5">Até</label>
                    <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="sm:col-span-2 xl:col-span-2 flex flex-wrap gap-2">
                    <button type="submit" class="px-4 py-2 bg-primary text-white rounded-lg text-sm font-medium hover:opacity-90">Filtrar</button>
                    <a href="<?= URL ?>/carteira" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">Limpar</a>
                </div>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($movimentacoes)): ?>
                    <tr>
                        <td colspan="4" class="px-6 py-12 text-center text-gray-500">
                            <i class="fa-solid fa-wallet text-4xl text-gray-300 mb-4"></i>
                            <p>Nenhuma movimentação encontrada.</p>
                            <?php if ($filtro_tipo !== '' || $filtro_modulo !== '' || $data_ini !== '' || $data_fim !== ''): ?>
                            <a href="<?= URL ?>/carteira" class="inline-block mt-2 text-sm text-blue-600 hover:underline">Limpar filtros</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($movimentacoes as $m):
                        $valor = \CreditosDecimalHelper::fromSignedScalar($m['valor'] ?? 0, 0.0);
                        $tipoLabel = $tipoLabels[$m['tipo'] ?? ''] ?? ($m['tipo'] ?? '—');
                        $dataFormatada = date('d/m/Y H:i', strtotime($m['created_at'] ?? 'now'));
                        $exibicao = \CreditosModuleRegistry::formatMovimentacaoExibicao($m);
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($dataFormatada) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($tipoLabel) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm tabular-nums <?= $valor >= 0 ? 'text-green-600 font-medium' : 'text-red-600 font-medium' ?>">
                            <?= $valor >= 0 ? '+' : '' ?><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($valor)) ?>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-900">
                            <span class="font-medium"><?= htmlspecialchars($exibicao['label']) ?></span>
                            <?php if (!empty($exibicao['codigo'])): ?>
                            <span class="block text-xs text-gray-500 font-mono mt-0.5"><?= htmlspecialchars((string) $exibicao['codigo']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total > 0): ?>
        <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-gray-600">
                Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> registro(s)
            </p>
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
