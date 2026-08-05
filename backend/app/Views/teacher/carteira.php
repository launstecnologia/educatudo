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
?>
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-gray-900">Minha Carteira</h1>
        <p class="text-gray-600 mt-1">Seu saldo de créditos e histórico de movimentações.</p>
    </div>

    <?php if (!$creditosHabilitado): ?>
    <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-6 text-amber-800 text-sm">
        O sistema de créditos não está habilitado para sua escola no momento. Seu saldo é exibido apenas para consulta.
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-6">
        <p class="text-sm font-medium text-gray-500 uppercase tracking-wide">Saldo atual</p>
        <p class="text-4xl font-bold mt-1" style="color: <?= htmlspecialchars($primaryHex) ?>"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldo)) ?> <span class="text-lg font-normal text-gray-600">créditos</span></p>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mt-4">
            <div class="rounded-xl border border-blue-100 bg-blue-50/70 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-blue-700">Créditos da escola</p>
                <p class="text-2xl font-bold text-blue-900 mt-1"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldoEscola)) ?></p>
            </div>
            <div class="rounded-xl border border-emerald-100 bg-emerald-50/70 p-4">
                <p class="text-xs font-semibold uppercase tracking-wide text-emerald-700">Créditos comprados</p>
                <p class="text-2xl font-bold text-emerald-900 mt-1"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldoComprado)) ?></p>
                <p class="text-xs text-emerald-700 mt-1">Esse saldo permanece disponível mesmo após novas recargas da escola.</p>
            </div>
        </div>
        <?php if ($creditosHabilitado): ?>
        <div class="flex gap-3 mt-4">
            <a href="<?= URL ?>/professor/carteira/comprar" class="px-4 py-2 rounded-lg text-white text-sm font-medium hover:opacity-90" style="background-color: <?= htmlspecialchars($primaryHex) ?>">Comprar créditos</a>
            <?php if ($creditosLiberarB2C): ?>
            <a href="<?= URL ?>/professor/carteira/planos" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 text-sm font-medium hover:bg-gray-50">Planos (assinatura)</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($creditosHabilitado && !empty($tabela_precos_modulos)): ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden mb-6">
        <h2 class="text-lg font-semibold text-gray-900 px-6 py-4 border-b border-gray-200">Tabela de uso por módulo (esta escola)</h2>
        <p class="px-6 py-2 text-xs text-gray-500">Somente módulos válidos no produto. &quot;Cobra&quot; indica se o uso desconta créditos aqui.</p>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Módulo</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Grupo</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Cobra crédito</th>
                        <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Custo (créditos)</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200">
                    <?php
                    $grupoAnt = null;
                    foreach ($tabela_precos_modulos as $row):
                        $g = $row['grupo'] ?? '';
                        if ($g !== $grupoAnt):
                            $grupoAnt = $g;
                    ?>
                    <tr class="bg-indigo-50/60">
                        <td colspan="4" class="px-4 py-1.5 text-xs font-semibold text-indigo-900 uppercase tracking-wide"><?= htmlspecialchars($g) ?></td>
                    </tr>
                    <?php endif; ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-2 text-sm">
                            <span class="font-medium text-gray-800"><?= htmlspecialchars($row['label'] ?? '') ?></span>
                            <span class="block text-xs font-mono text-gray-500"><?= htmlspecialchars($row['modulo_key'] ?? '') ?></span>
                        </td>
                        <td class="px-4 py-2 text-sm text-gray-600"><?= htmlspecialchars($g) ?></td>
                        <td class="px-4 py-2 text-sm"><?= !empty($row['cobra']) ? '<span class="text-green-700 font-medium">Sim</span>' : '<span class="text-gray-400">Não</span>' ?></td>
                        <td class="px-4 py-2 text-sm text-gray-800"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay((float)($row['custo'] ?? 0))) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
        <h2 class="text-lg font-semibold text-gray-900 px-6 py-4 border-b border-gray-200">Histórico de movimentações</h2>
        <form method="get" action="<?= URL ?>/professor/carteira" class="px-6 py-4 border-b border-gray-100 flex flex-wrap gap-3 items-end bg-gray-50/80">
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Tipo</label>
                <select name="filtro_tipo" class="px-2 py-1.5 border border-gray-300 rounded text-sm">
                    <?php foreach ($tiposFiltro as $k => $lab): ?>
                    <option value="<?= htmlspecialchars($k) ?>" <?= $filtro_tipo === $k ? 'selected' : '' ?>><?= htmlspecialchars($lab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Módulo</label>
                <select name="filtro_modulo" class="px-2 py-1.5 border border-gray-300 rounded text-sm min-w-[160px]">
                    <option value="">Todos</option>
                    <?php foreach ($modulos_opcao_filtro as $mk => $mlab): ?>
                    <option value="<?= htmlspecialchars($mk) ?>" <?= $filtro_modulo === $mk ? 'selected' : '' ?>><?= htmlspecialchars($mlab) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">De</label>
                <input type="date" name="data_ini" value="<?= htmlspecialchars($data_ini) ?>" class="px-2 py-1.5 border border-gray-300 rounded text-sm">
            </div>
            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Até</label>
                <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim) ?>" class="px-2 py-1.5 border border-gray-300 rounded text-sm">
            </div>
            <button type="submit" class="px-3 py-1.5 bg-gray-800 text-white rounded text-sm hover:bg-gray-900">Filtrar</button>
            <a href="<?= URL ?>/professor/carteira" class="text-sm text-indigo-600 hover:underline py-1.5">Limpar</a>
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
                        $tipoLabel = [
                            'recarga_mensal' => 'Recarga mensal',
                            'recarga_inicial' => 'Créditos iniciais (escola)',
                            'cortesia' => 'Cortesia',
                            'compra' => 'Compra',
                            'consumo' => 'Consumo',
                            'estorno' => 'Estorno',
                            'recarga_plano' => 'Recarga plano',
                        ][$m['tipo'] ?? ''] ?? $m['tipo'];
                        $dataFormatada = date('d/m/Y H:i', strtotime($m['created_at'] ?? 'now'));
                    ?>
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-3 text-sm text-gray-600"><?= htmlspecialchars($dataFormatada) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-800"><?= htmlspecialchars($tipoLabel) ?></td>
                        <td class="px-6 py-3 text-sm <?= $valor >= 0 ? 'text-green-600 font-medium' : 'text-red-600' ?>"><?= $valor >= 0 ? '+' : '' ?><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($valor)) ?></td>
                        <td class="px-6 py-3 text-sm text-gray-500">
                            <?= htmlspecialchars($m['modulo_key'] ?? '-') ?><?= !empty($m['referencia_id']) ? ' (' . htmlspecialchars($m['referencia_id']) . ')' : '' ?>
                            <?php if (!empty($m['saldo_origem'])): ?>
                            <span class="block text-xs text-gray-600 mt-0.5">Origem: <?= htmlspecialchars((string) $m['saldo_origem']) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($m['observacao'])): ?>
                            <span class="block text-xs text-gray-600 mt-0.5"><?= htmlspecialchars($m['observacao']) ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
