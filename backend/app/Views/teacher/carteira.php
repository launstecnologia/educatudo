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
$primaryHex = $primary_color ?? '#4f46e5';
$tabela_precos_modulos = $tabela_precos_modulos ?? [];
$filtro_tipo = $filtro_tipo ?? '';
$filtro_modulo = $filtro_modulo ?? '';
$data_ini = $data_ini ?? '';
$data_fim = $data_fim ?? '';
$modulos_opcao_filtro = $modulos_opcao_filtro ?? [];
$tiposFiltro = [
    '' => 'Todos os tipos',
    'consumo' => 'Consumo',
    'compra' => 'Compra',
    'cortesia' => 'Cortesia',
    'estorno' => 'Estorno',
    'recarga_mensal' => 'Recarga mensal',
    'recarga_inicial' => 'Créditos iniciais',
    'recarga_plano' => 'Recarga plano',
];
$totalModulosCobrados = count(array_filter($tabela_precos_modulos, static fn($row) => !empty($row['cobra'])));
$totalModulosGratuitos = max(0, count($tabela_precos_modulos) - $totalModulosCobrados);
$modulosPorGrupo = [];
foreach ($tabela_precos_modulos as $row) {
    $grupo = trim((string) ($row['grupo'] ?? ''));
    $grupo = $grupo !== '' ? $grupo : 'Outros';
    $cobra = !empty($row['cobra']);
    $custo = \CreditosDecimalHelper::fromScalar($row['custo'] ?? 0, 0.0);

    if (!isset($modulosPorGrupo[$grupo])) {
        $modulosPorGrupo[$grupo] = [
            'total' => 0,
            'cobrados' => 0,
            'gratuitos' => 0,
            'menor_custo' => null,
            'maior_custo' => null,
            'itens' => [],
        ];
    }

    $row['_cobra_bool'] = $cobra;
    $row['_custo_float'] = $custo;
    $modulosPorGrupo[$grupo]['total']++;
    $modulosPorGrupo[$grupo][$cobra ? 'cobrados' : 'gratuitos']++;
    if ($cobra) {
        $modulosPorGrupo[$grupo]['menor_custo'] = $modulosPorGrupo[$grupo]['menor_custo'] === null ? $custo : min($modulosPorGrupo[$grupo]['menor_custo'], $custo);
        $modulosPorGrupo[$grupo]['maior_custo'] = $modulosPorGrupo[$grupo]['maior_custo'] === null ? $custo : max($modulosPorGrupo[$grupo]['maior_custo'], $custo);
    }
    $modulosPorGrupo[$grupo]['itens'][] = $row;
}
$formatarOrigem = static function ($origem): string {
    return [
        'escola' => 'Escola',
        'comprado' => 'Comprado',
        'misto' => 'Misto',
    ][(string) $origem] ?? 'Não informado';
};
?>
<div class="mx-auto max-w-5xl">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Minha Carteira</h1>
            <p class="mt-1 text-gray-600">Acompanhe saldo, custos por módulo e movimentações de TudiCoins.</p>
        </div>
        <?php if ($creditosHabilitado): ?>
        <div class="flex flex-wrap gap-2">
            <a href="<?= URL ?>/professor/carteira/comprar" class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary px-4 py-2.5 text-sm font-semibold text-primary shadow-sm transition-colors hover:opacity-90">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 5v14"></path>
                    <path d="M5 12h14"></path>
                </svg>
                Comprar créditos
            </a>
            <?php if ($creditosLiberarB2C): ?>
            <a href="<?= URL ?>/professor/carteira/planos" class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-4 py-2.5 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50">Planos</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if (!$creditosHabilitado): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-amber-800 text-sm">
        O sistema de créditos não está habilitado para sua escola no momento. Seu saldo é exibido apenas para consulta.
    </div>
    <?php endif; ?>

    <div class="mb-6 grid grid-cols-1 gap-3 lg:grid-cols-[1.15fr_1fr_1fr]">
        <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Saldo atual</p>
                    <p class="mt-1 text-3xl font-bold" style="color: <?= htmlspecialchars($primaryHex) ?>"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldo)) ?></p>
                    <p class="mt-1 text-sm text-gray-500">Disponível para usar em recursos de IA.</p>
                </div>
                <span class="inline-flex h-11 w-11 items-center justify-center rounded-lg bg-sky-50 text-sky-700">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M20 7H5a2 2 0 0 0 0 4h15"></path>
                        <path d="M5 7V5a2 2 0 0 1 2-2h11v4"></path>
                        <path d="M5 11v8a2 2 0 0 0 2 2h13V11"></path>
                        <path d="M17 16h.01"></path>
                    </svg>
                </span>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Créditos da escola</p>
                    <p class="mt-1 text-2xl font-bold text-blue-700"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldoEscola)) ?></p>
                    <p class="mt-1 text-xs text-gray-500">Saldo liberado pela instituição.</p>
                </div>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-700">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 21h18"></path>
                        <path d="M5 21V9"></path>
                        <path d="M19 21V9"></path>
                        <path d="M4 9h16"></path>
                        <path d="M12 3 4 9"></path>
                        <path d="m12 3 8 6"></path>
                        <path d="M9 21v-6h6v6"></path>
                    </svg>
                </span>
            </div>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white px-5 py-4 shadow-sm">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Créditos comprados</p>
                    <p class="mt-1 text-2xl font-bold text-emerald-700"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldoComprado)) ?></p>
                    <p class="mt-1 text-xs text-gray-500">Permanecem após recargas.</p>
                </div>
                <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-emerald-700">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <ellipse cx="12" cy="6" rx="7" ry="3"></ellipse>
                        <path d="M5 6v6c0 1.66 3.13 3 7 3s7-1.34 7-3V6"></path>
                        <path d="M5 12v6c0 1.66 3.13 3 7 3s7-1.34 7-3v-6"></path>
                    </svg>
                </span>
            </div>
        </div>
    </div>

    <div class="mb-4 flex rounded-xl border border-gray-200 bg-white p-1 shadow-sm">
        <button type="button"
                data-carteira-tab="precos"
                class="carteira-tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors">
            Preços por item
        </button>
        <button type="button"
                data-carteira-tab="extrato"
                class="carteira-tab-btn flex-1 rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors">
            Extrato
        </button>
    </div>

    <section data-carteira-tab-panel="precos">
    <?php if ($creditosHabilitado && !empty($tabela_precos_modulos)): ?>
    <div class="mb-6 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="flex flex-col gap-3 border-b border-gray-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-lg font-semibold text-gray-900">Preços por categoria</h2>
                <p class="mt-0.5 text-sm text-gray-500">Clique em uma categoria para ver os itens e custos.</p>
            </div>
            <div class="flex gap-2 text-xs">
                <span class="rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-700"><?= (int) $totalModulosCobrados ?> itens ativos</span>
                <?php if ($totalModulosGratuitos > 0): ?>
                <span class="rounded-full bg-gray-100 px-3 py-1 font-semibold text-gray-600"><?= (int) $totalModulosGratuitos ?> gratuitos</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-3">
            <?php foreach ($modulosPorGrupo as $grupo => $info):
                $modalId = 'precoGrupoModal-' . md5($grupo);
                $menor = $info['menor_custo'];
                $maior = $info['maior_custo'];
                $precoResumo = 'Sem custo';
                if ($info['cobrados'] > 0 && $menor !== null && $maior !== null) {
                    $precoResumo = abs($menor - $maior) < 0.00001
                        ? \CreditosDecimalHelper::formatDisplay($menor)
                        : \CreditosDecimalHelper::formatDisplay($menor) . ' a ' . \CreditosDecimalHelper::formatDisplay($maior);
                }
            ?>
            <button type="button"
                    data-open-preco-modal="<?= htmlspecialchars($modalId) ?>"
                    class="group rounded-xl border border-gray-200 bg-white p-4 text-left shadow-sm transition-all hover:-translate-y-0.5 hover:border-primary/40 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-primary/30">
                <div class="flex items-start justify-between gap-3">
                    <span class="inline-flex h-10 w-10 items-center justify-center rounded-lg bg-sky-50 text-sm font-bold text-sky-700">
                        <?= htmlspecialchars(mb_strtoupper(mb_substr($grupo, 0, 1, 'UTF-8'), 'UTF-8')) ?>
                    </span>
                    <span class="rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-600"><?= (int) $info['total'] ?> itens</span>
                </div>
                <h3 class="mt-4 text-base font-semibold text-gray-900"><?= htmlspecialchars($grupo) ?></h3>
                <p class="mt-1 text-sm text-gray-500">
                    <?= (int) $info['cobrados'] ?> cobrando<?= $info['gratuitos'] > 0 ? ' · ' . (int) $info['gratuitos'] . ' gratuitos' : '' ?>
                </p>
                <div class="mt-4 flex items-end justify-between gap-3">
                    <div>
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Custo</p>
                        <p class="mt-0.5 text-lg font-bold text-gray-900"><?= htmlspecialchars($precoResumo) ?></p>
                    </div>
                    <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-gray-50 text-gray-500 transition-colors group-hover:bg-primary/10 group-hover:text-primary">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M8 6h13"></path>
                            <path d="M8 12h13"></path>
                            <path d="M8 18h13"></path>
                            <path d="M3 6h.01"></path>
                            <path d="M3 12h.01"></path>
                            <path d="M3 18h.01"></path>
                        </svg>
                    </span>
                </div>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <?php foreach ($modulosPorGrupo as $grupo => $info):
        $modalId = 'precoGrupoModal-' . md5($grupo);
    ?>
    <div id="<?= htmlspecialchars($modalId) ?>" class="preco-grupo-modal fixed inset-0 z-50 hidden items-center justify-center bg-gray-900/50 p-4">
        <div class="max-h-[88vh] w-full max-w-3xl overflow-hidden rounded-xl bg-white shadow-xl">
            <div class="flex items-start justify-between gap-4 border-b border-gray-200 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($grupo) ?></h2>
                    <p class="mt-0.5 text-sm text-gray-500"><?= (int) $info['total'] ?> itens configurados nesta categoria.</p>
                </div>
                <button type="button" data-close-preco-modal class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-gray-200 text-gray-500 transition-colors hover:bg-gray-50 hover:text-gray-900">
                    <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M18 6 6 18"></path>
                        <path d="m6 6 12 12"></path>
                    </svg>
                </button>
            </div>
            <div class="max-h-[62vh] overflow-y-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="sticky top-0 bg-gray-50">
                        <tr>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Item</th>
                            <th class="px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">Código</th>
                            <th class="px-5 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">Custo</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <?php foreach ($info['itens'] as $item): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-5 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars($item['label'] ?? '') ?></td>
                            <td class="px-5 py-4 font-mono text-xs text-gray-500"><?= htmlspecialchars($item['modulo_key'] ?? '') ?></td>
                            <td class="px-5 py-4 text-right">
                                <?php if (!empty($item['_cobra_bool'])): ?>
                                <span class="text-sm font-bold text-gray-900"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($item['_custo_float'])) ?></span>
                                <span class="ml-1 text-xs text-gray-500">por uso</span>
                                <?php else: ?>
                                <span class="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-semibold text-emerald-700">Sem custo</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="flex justify-end border-t border-gray-200 bg-gray-50 px-5 py-3">
                <button type="button" data-close-preco-modal class="rounded-lg bg-gray-900 px-4 py-2 text-sm font-semibold text-white transition-colors hover:bg-gray-800">Fechar</button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php else: ?>
    <div class="mb-6 rounded-xl border border-gray-200 bg-white p-6 text-sm text-gray-500 shadow-sm">
        Nenhuma tabela de preço disponível para esta escola.
    </div>
    <?php endif; ?>
    </section>

    <section data-carteira-tab-panel="extrato" class="hidden">
    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-200 px-5 py-4">
            <h2 class="text-lg font-semibold text-gray-900">Histórico de movimentações</h2>
            <p class="mt-0.5 text-sm text-gray-500">Consumos, recargas, compras e estornos da carteira.</p>
        </div>
        <form method="get" action="<?= URL ?>/professor/carteira" class="border-b border-gray-100 bg-gray-50/70 px-5 py-4">
            <div class="flex flex-col gap-3 xl:flex-row xl:items-end">
                <div class="grid flex-1 grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-[150px_1fr_140px_140px]">
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Tipo</label>
                        <select name="filtro_tipo" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/15">
                            <?php foreach ($tiposFiltro as $k => $lab): ?>
                            <option value="<?= htmlspecialchars($k) ?>" <?= $filtro_tipo === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Módulo</label>
                        <select name="filtro_modulo" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/15">
                            <option value="">Todos</option>
                            <?php foreach ($modulos_opcao_filtro as $mk => $mlab): ?>
                            <option value="<?= htmlspecialchars($mk) ?>" <?= $filtro_modulo === $mk ? 'selected' : '' ?>><?= htmlspecialchars($mlab) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">De</label>
                        <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/15">
                    </div>
                    <div>
                        <label class="mb-1.5 block text-xs font-semibold uppercase tracking-wide text-gray-500">Até</label>
                        <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" class="h-10 w-full rounded-lg border border-gray-300 bg-white px-3 text-sm text-gray-800 shadow-sm outline-none transition-colors focus:border-primary focus:ring-2 focus:ring-primary/15">
                    </div>
                </div>
                <div class="flex gap-2 xl:pb-0">
                    <button type="submit" class="inline-flex h-10 flex-1 items-center justify-center gap-2 rounded-lg bg-gray-900 px-4 text-sm font-semibold text-white shadow-sm transition-colors hover:bg-gray-800 sm:flex-none">
                        <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.25" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M22 3H2l8 9.46V19l4 2v-8.54L22 3z"></path>
                        </svg>
                        Filtrar
                    </button>
                    <a href="<?= URL ?>/professor/carteira#extrato" class="inline-flex h-10 flex-1 items-center justify-center rounded-lg border border-gray-300 bg-white px-4 text-sm font-semibold text-gray-700 shadow-sm transition-colors hover:bg-gray-50 sm:flex-none">Limpar</a>
                </div>
            </div>
        </form>
        <?php if (empty($movimentacoes)): ?>
        <p class="px-6 py-8 text-gray-500 text-sm">Nenhuma movimentação ainda.</p>
        <?php else: ?>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Data</th>
                        <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Tipo</th>
                        <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Valor</th>
                        <th class="px-6 py-2 text-left text-xs font-medium text-gray-500 uppercase">Módulo / Referência</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php foreach ($movimentacoes as $m): 
                        $valor = \CreditosDecimalHelper::fromSignedScalar($m['valor'] ?? 0, 0.0);
                        $exibicao = \CreditosModuleRegistry::formatMovimentacaoExibicao($m);
                        $tipoLabel = [
                            'recarga_mensal' => 'Recarga mensal',
                            'recarga_inicial' => 'Créditos iniciais (escola)',
                            'cortesia' => 'Cortesia',
                            'compra' => 'Compra',
                            'consumo' => 'Consumo',
                            'estorno' => 'Estorno',
                            'recarga_plano' => 'Recarga plano',
                        ][$m['tipo'] ?? ''] ?? $m['tipo'];
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-600"><?= htmlspecialchars(date('d/m/Y', strtotime($m['created_at'] ?? 'now'))) ?><span class="block text-xs text-gray-500"><?= htmlspecialchars(date('H:i', strtotime($m['created_at'] ?? 'now'))) ?></span></td>
                        <td class="px-6 py-3 text-sm">
                            <span class="inline-flex rounded-full bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700"><?= htmlspecialchars($tipoLabel) ?></span>
                        </td>
                        <td class="px-6 py-3 text-sm font-semibold <?= $valor >= 0 ? 'text-green-600' : 'text-red-600' ?>"><?= $valor >= 0 ? '+' : '' ?><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($valor)) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-600">
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($exibicao['label'] ?? ($m['modulo_key'] ?? '-')) ?></span>
                            <?php if (!empty($exibicao['codigo'])): ?>
                            <span class="block text-xs font-mono text-gray-500"><?= htmlspecialchars($exibicao['codigo']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($m['saldo_origem'])): ?>
                            <span class="mt-1 inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Origem: <?= htmlspecialchars($formatarOrigem($m['saldo_origem'])) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($m['observacao'])): ?>
                            <span class="block mt-1 text-xs text-gray-500"><?= htmlspecialchars($m['observacao']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const buttons = Array.from(document.querySelectorAll('[data-carteira-tab]'));
    const panels = Array.from(document.querySelectorAll('[data-carteira-tab-panel]'));
    const temFiltrosExtrato = <?= json_encode($filtro_tipo !== '' || $filtro_modulo !== '' || $data_ini !== '' || $data_fim !== '') ?>;

    function ativarAba(tab) {
        buttons.forEach(button => {
            const active = button.dataset.carteiraTab === tab;
            button.classList.toggle('bg-primary', active);
            button.classList.toggle('text-primary', active);
            button.classList.toggle('shadow-sm', active);
            button.classList.toggle('text-gray-600', !active);
            button.classList.toggle('hover:bg-gray-50', !active);
        });

        panels.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.carteiraTabPanel !== tab);
        });

        if (window.location.hash !== '#' + tab) {
            history.replaceState(null, '', window.location.pathname + window.location.search + '#' + tab);
        }
    }

    buttons.forEach(button => {
        button.addEventListener('click', function() {
            ativarAba(button.dataset.carteiraTab);
        });
    });

    const modaisPreco = Array.from(document.querySelectorAll('.preco-grupo-modal'));

    function fecharModaisPreco() {
        modaisPreco.forEach(modal => {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        });
        document.body.classList.remove('overflow-hidden');
    }

    document.querySelectorAll('[data-open-preco-modal]').forEach(button => {
        button.addEventListener('click', function() {
            const modal = document.getElementById(button.dataset.openPrecoModal);
            if (!modal) {
                return;
            }
            modal.classList.remove('hidden');
            modal.classList.add('flex');
            document.body.classList.add('overflow-hidden');
        });
    });

    document.querySelectorAll('[data-close-preco-modal]').forEach(button => {
        button.addEventListener('click', fecharModaisPreco);
    });

    modaisPreco.forEach(modal => {
        modal.addEventListener('click', function(event) {
            if (event.target === modal) {
                fecharModaisPreco();
            }
        });
    });

    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            fecharModaisPreco();
        }
    });

    const hashTab = window.location.hash === '#extrato' ? 'extrato' : (window.location.hash === '#precos' ? 'precos' : '');
    ativarAba(hashTab || (temFiltrosExtrato ? 'extrato' : 'precos'));
});
</script>
