<?php
$pacotes = $pacotes ?? [];
$categorias = $categorias ?? [];
$compras = $compras ?? [];
$saldo = isset($saldo) ? (float) $saldo : 0.0;
$wallet_saldos = $wallet_saldos ?? ['saldo_comprado' => 0, 'saldo_escola' => 0];
require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php';

$saldoComprado = \CreditosDecimalHelper::fromScalar($wallet_saldos['saldo_comprado'] ?? 0, 0.0);
$totalPacotes = count($pacotes);
$categoriasMeta = $categorias_meta ?? [];
$ui = __DIR__ . '/../admin/_partials/ui';
?>
<div class="w-full space-y-6" id="educashopApp">
    <?php
    ob_start();
    $ui_btn_variant = 'complementar';
    $ui_btn_label = 'Minha carteira';
    $ui_btn_href = URL . '/professor/carteira';
    $ui_btn_icon = 'fa-solid fa-wallet';
    include $ui . '/btn.php';
    $page_header_actions = ob_get_clean();
    $page_header_title = 'EducaShop';
    $page_header_subtitle = 'Compre pacotes de TudiCoins para usar nos módulos com IA. O saldo comprado fica na sua carteira.';
    include __DIR__ . '/../admin/_partials/page_header_list.php';
    ?>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Seu saldo</p>
            <p class="text-2xl font-bold text-gray-900 tabular-nums mt-1"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldo)) ?></p>
            <p class="text-xs text-gray-500 mt-1">Comprados: <?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldoComprado)) ?></p>
        </div>
        <div class="rounded-xl border border-gray-200 bg-white shadow-sm px-5 py-4">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Pagamento</p>
            <p class="text-sm font-medium text-gray-900 mt-1">PIX e cartão, com confirmação automática</p>
        </div>
    </div>

    <div class="flex gap-2 border-b border-gray-200">
        <button type="button" data-tab="loja" class="educashop-tab-btn educashop-tab-active px-4 py-2.5 text-sm font-semibold border-b-2 border-blue-600 text-blue-700 -mb-px">
            Loja
        </button>
        <button type="button" data-tab="pedidos" class="educashop-tab-btn px-4 py-2.5 text-sm font-medium text-gray-600 border-b-2 border-transparent -mb-px hover:text-gray-900">
            Meus pedidos
            <?php if (!empty($compras)): ?>
            <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-gray-100 text-gray-700 text-xs"><?= count($compras) ?></span>
            <?php endif; ?>
        </button>
    </div>

    <div id="educashopPanelLoja" class="space-y-6">
        <?php if (empty($pacotes)): ?>
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-12 text-center">
            <i class="fa-solid fa-box-open text-4xl text-gray-300 mb-4"></i>
            <p class="font-medium text-gray-900">Nenhum pacote disponível no momento.</p>
            <p class="text-sm text-gray-500 mt-1">Entre em contato com a escola.</p>
        </div>
        <?php else: ?>
        <div class="flex flex-wrap gap-2">
            <button type="button" data-categoria="todos" class="educashop-cat-btn educashop-cat-active px-4 py-2 rounded-full text-sm font-medium bg-primary text-primary">
                Todos (<?= $totalPacotes ?>)
            </button>
            <?php foreach ($categoriasMeta as $slug => $meta):
                $qtd = count($categorias[$slug]['pacotes'] ?? []);
                if ($qtd === 0) {
                    continue;
                }
            ?>
            <button type="button" data-categoria="<?= htmlspecialchars($slug) ?>" class="educashop-cat-btn px-4 py-2 rounded-full text-sm font-medium bg-white border border-gray-300 text-gray-700 hover:bg-gray-50">
                <?= htmlspecialchars($meta['label']) ?> (<?= $qtd ?>)
            </button>
            <?php endforeach; ?>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5" id="educashopGrid">
            <?php foreach ($pacotes as $p):
                $catSlug = (string) ($p['categoria_slug'] ?? 'inicio');
            ?>
            <article class="educashop-produto flex flex-col rounded-xl border border-gray-200 bg-white shadow-sm" data-categoria="<?= htmlspecialchars($catSlug) ?>">
                <div class="p-5 flex flex-col flex-1">
                    <?php if (!empty($p['badge'])): ?>
                    <span class="inline-flex self-start mb-3 px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800"><?= htmlspecialchars((string) $p['badge']) ?></span>
                    <?php endif; ?>
                    <p class="text-xs font-medium text-gray-500"><?= htmlspecialchars((string) ($p['categoria_label'] ?? '')) ?></p>
                    <h3 class="text-lg font-semibold text-gray-900 mt-1"><?= htmlspecialchars((string) $p['nome']) ?></h3>
                    <p class="text-sm text-gray-600 mt-2 flex-1"><?= htmlspecialchars((string) ($p['descricao'] ?? '')) ?></p>
                    <div class="mt-4 pt-4 border-t border-gray-100 flex items-end justify-between gap-3">
                        <div>
                            <p class="text-2xl font-bold text-gray-900 tabular-nums"><?= htmlspecialchars((string) ($p['valor_reais_display'] ?? '')) ?></p>
                            <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) ($p['preco_por_coin_display'] ?? '')) ?></p>
                        </div>
                        <p class="text-sm font-semibold text-gray-900 tabular-nums"><?= htmlspecialchars((string) ($p['creditos_display'] ?? '')) ?> TudiCoins</p>
                    </div>
                    <form method="post" action="<?= URL ?>/professor/carteira/comprar" class="mt-4">
                        <input type="hidden" name="pacote_id" value="<?= (int) $p['id'] ?>">
                        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                        <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                            Comprar
                        </button>
                    </form>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
        <p id="educashopEmptyFilter" class="hidden text-center text-sm text-gray-500 py-8">Nenhum pacote nesta categoria.</p>
        <?php endif; ?>
    </div>

    <div id="educashopPanelPedidos" class="hidden">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pacote</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($compras)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">Nenhuma compra registrada ainda.</td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($compras as $c): ?>
                        <?php
                            $valorReais = 'R$ ' . number_format(((int) ($c['valor_centavos'] ?? 0)) / 100, 2, ',', '.');
                            $creditosCompra = \CreditosDecimalHelper::fromScalar($c['creditos'] ?? 0, 0.0);
                            $billingType = strtoupper((string) ($c['billing_type'] ?? ''));
                            $billingLabel = ['PIX' => 'PIX', 'CREDIT_CARD' => 'Cartão', 'BOLETO' => 'Boleto'][$billingType] ?? ($billingType !== '' ? $billingType : '—');
                            $statusValue = (string) ($c['status'] ?? '');
                            $statusLabel = [
                                'pending' => 'Aguardando',
                                'paid' => 'Pago',
                                'failed' => 'Falhou',
                                'refunded' => 'Estornado',
                                'cancelled' => 'Cancelado',
                            ][$statusValue] ?? $statusValue;
                            $statusClass = [
                                'pending' => 'bg-amber-100 text-amber-800',
                                'paid' => 'bg-green-100 text-green-800',
                                'failed' => 'bg-red-100 text-red-800',
                            ][$statusValue] ?? 'bg-gray-100 text-gray-700';
                            $createdAt = !empty($c['created_at']) ? date('d/m/Y H:i', strtotime($c['created_at'])) : '—';
                            $compraId = (int) ($c['id'] ?? 0);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($createdAt) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <span class="font-medium"><?= htmlspecialchars((string) ($c['pacote_nome'] ?? 'Pacote')) ?></span>
                                <span class="block text-xs text-gray-500 mt-0.5"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($creditosCompra)) ?> TudiCoins</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= htmlspecialchars($valorReais) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($billingLabel) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <?php if ($statusValue === 'pending' && $compraId > 0): ?>
                                <a href="<?= URL ?>/professor/carteira/comprar/aguardando/<?= $compraId ?>" class="font-medium text-blue-700 hover:underline">Pagar</a>
                                <?php else: ?>
                                <span class="text-gray-400">—</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const app = document.getElementById('educashopApp');
    if (!app) return;
    const panelLoja = document.getElementById('educashopPanelLoja');
    const panelPedidos = document.getElementById('educashopPanelPedidos');
    app.querySelectorAll('.educashop-tab-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const tab = btn.getAttribute('data-tab') || 'loja';
            app.querySelectorAll('.educashop-tab-btn').forEach(function (item) {
                const active = item === btn;
                item.classList.toggle('border-blue-600', active);
                item.classList.toggle('text-blue-700', active);
                item.classList.toggle('font-semibold', active);
                item.classList.toggle('border-transparent', !active);
                item.classList.toggle('text-gray-600', !active);
                item.classList.toggle('font-medium', !active);
            });
            if (panelLoja) panelLoja.classList.toggle('hidden', tab !== 'loja');
            if (panelPedidos) panelPedidos.classList.toggle('hidden', tab !== 'pedidos');
        });
    });
    const produtos = app.querySelectorAll('.educashop-produto');
    const emptyMsg = document.getElementById('educashopEmptyFilter');
    app.querySelectorAll('.educashop-cat-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const slug = btn.getAttribute('data-categoria') || 'todos';
            let visiveis = 0;
            produtos.forEach(function (el) {
                const show = slug === 'todos' || el.getAttribute('data-categoria') === slug;
                el.classList.toggle('hidden', !show);
                if (show) visiveis++;
            });
            if (emptyMsg) emptyMsg.classList.toggle('hidden', visiveis > 0);
            app.querySelectorAll('.educashop-cat-btn').forEach(function (item) {
                const active = item === btn;
                item.classList.toggle('bg-primary', active);
                item.classList.toggle('text-primary', active);
                item.classList.toggle('bg-white', !active);
                item.classList.toggle('border', !active);
                item.classList.toggle('border-gray-300', !active);
                item.classList.toggle('text-gray-700', !active);
            });
        });
    });
})();
</script>
