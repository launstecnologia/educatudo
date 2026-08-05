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
?>
<div class="w-full space-y-8 educashop-vitrine" id="educashopApp">
    <!-- Hero -->
    <section class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="absolute inset-0 bg-gradient-to-br from-indigo-50 via-white to-violet-50 pointer-events-none"></div>
        <div class="absolute -right-16 -top-16 h-56 w-56 rounded-full bg-primary/10 blur-3xl pointer-events-none"></div>
        <div class="absolute -left-10 bottom-0 h-40 w-40 rounded-full bg-violet-200/40 blur-2xl pointer-events-none"></div>
        <div class="relative px-6 py-8 md:px-10 md:py-10">
            <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
                <div class="max-w-2xl">
                    <a href="<?= URL ?>/dashboard" class="inline-flex items-center gap-1.5 text-sm text-gray-600 hover:text-gray-900 mb-3">
                        <i class="fa-solid fa-arrow-left text-xs"></i> Voltar ao painel
                    </a>
                    <div class="inline-flex items-center gap-2 rounded-full bg-white/80 border border-indigo-100 px-3 py-1 text-xs font-semibold text-indigo-700 mb-3">
                        <i class="fa-solid fa-store"></i> Loja oficial de TudiCoins
                    </div>
                    <h1 class="text-3xl md:text-4xl font-bold text-gray-900 tracking-tight">EducaShop</h1>
                    <p class="text-gray-600 mt-2 text-sm md:text-base leading-relaxed">
                        Compre pacotes de TudiCoins e use nos módulos com IA da sua escola.
                        Os TudiCoins comprados ficam na sua carteira e <strong>não expiram</strong> na recarga mensal.
                    </p>
                    <div class="flex flex-wrap items-center gap-3 mt-5">
                        <a href="<?= URL ?>/carteira" class="inline-flex items-center gap-2 text-sm font-medium text-accent hover:underline">
                            <i class="fa-solid fa-wallet"></i> Ver minha carteira
                        </a>
                        <span class="hidden sm:inline text-gray-300">|</span>
                        <button type="button" data-tab="pedidos" class="educashop-tab-btn inline-flex items-center gap-2 text-sm font-medium text-gray-600 hover:text-gray-900">
                            <i class="fa-solid fa-receipt"></i> Meus pedidos
                        </button>
                    </div>
                </div>
                <div class="flex flex-col sm:flex-row gap-3 lg:flex-col xl:flex-row shrink-0">
                    <div class="rounded-xl border border-gray-200 bg-white/90 backdrop-blur px-5 py-4 min-w-[200px] shadow-sm">
                        <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Seu saldo</p>
                        <p class="text-2xl font-bold text-accent tabular-nums mt-1"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldo)) ?></p>
                        <p class="text-xs text-gray-500 mt-1">Comprados: <?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($saldoComprado)) ?></p>
                    </div>
                    <div class="rounded-xl border border-emerald-100 bg-emerald-50/80 px-5 py-4 min-w-[200px]">
                        <p class="text-xs font-semibold uppercase tracking-wide text-emerald-800">Pagamento seguro</p>
                        <p class="text-sm text-emerald-900 mt-1 font-medium">PIX e cartão via Asaas</p>
                        <div class="flex items-center gap-2 mt-2 text-emerald-700 text-xs">
                            <i class="fa-brands fa-pix"></i>
                            <i class="fa-regular fa-credit-card"></i>
                            <span>Confirmação automática</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Abas Loja / Pedidos -->
    <div class="flex gap-2 border-b border-gray-200">
        <button type="button" data-tab="loja" class="educashop-tab-btn educashop-tab-active px-4 py-2.5 text-sm font-semibold border-b-2 border-accent text-accent -mb-px">
            <i class="fa-solid fa-bag-shopping mr-1.5"></i> Loja
        </button>
        <button type="button" data-tab="pedidos" class="educashop-tab-btn px-4 py-2.5 text-sm font-medium text-gray-600 border-b-2 border-transparent -mb-px hover:text-gray-900">
            <i class="fa-solid fa-clock-rotate-left mr-1.5"></i> Meus pedidos
            <?php if (!empty($compras)): ?>
            <span class="ml-1 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1 rounded-full bg-gray-100 text-gray-700 text-xs"><?= count($compras) ?></span>
            <?php endif; ?>
        </button>
    </div>

    <!-- Painel Loja -->
    <div id="educashopPanelLoja" class="educashop-panel space-y-6">
        <?php if (empty($pacotes)): ?>
        <div class="bg-amber-50 border border-amber-200 rounded-xl p-8 text-center text-amber-900">
            <i class="fa-solid fa-box-open text-4xl text-amber-400 mb-3"></i>
            <p class="font-medium">Nenhum pacote disponível no momento.</p>
            <p class="text-sm mt-1">Entre em contato com sua escola.</p>
        </div>
        <?php else: ?>
        <!-- Filtro categorias -->
        <div class="flex flex-wrap gap-2">
            <button type="button" data-categoria="todos" class="educashop-cat-btn educashop-cat-active px-4 py-2 rounded-full text-sm font-medium bg-primary text-white">
                Todos <span class="opacity-80">(<?= $totalPacotes ?>)</span>
            </button>
            <?php foreach ($categoriasMeta as $slug => $meta):
                $qtd = count($categorias[$slug]['pacotes'] ?? []);
                if ($qtd === 0) {
                    continue;
                }
            ?>
            <button type="button" data-categoria="<?= htmlspecialchars($slug) ?>" class="educashop-cat-btn px-4 py-2 rounded-full text-sm font-medium bg-gray-100 text-gray-700 hover:bg-gray-200">
                <?= htmlspecialchars($meta['label']) ?> <span class="opacity-70">(<?= $qtd ?>)</span>
            </button>
            <?php endforeach; ?>
        </div>

        <!-- Grid produtos -->
        <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-5" id="educashopGrid">
            <?php foreach ($pacotes as $p):
                $catSlug = (string) ($p['categoria_slug'] ?? 'inicio');
                $imagemUrl = (string) ($p['imagem_url'] ?? '');
            ?>
            <article
                class="educashop-produto group flex flex-col overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm hover:shadow-lg hover:-translate-y-0.5 transition-all duration-200"
                data-categoria="<?= htmlspecialchars($catSlug) ?>"
            >
                <div class="relative aspect-[16/10] overflow-hidden bg-gray-100">
                    <?php if ($imagemUrl !== ''): ?>
                    <img src="<?= htmlspecialchars($imagemUrl) ?>" alt="<?= htmlspecialchars((string) $p['nome']) ?>" class="h-full w-full object-cover group-hover:scale-105 transition-transform duration-300">
                    <?php else: ?>
                    <div class="h-full w-full bg-gradient-to-br <?= htmlspecialchars((string) $p['gradiente']) ?> flex items-center justify-center relative">
                        <div class="absolute inset-0 bg-[radial-gradient(circle_at_30%_20%,rgba(255,255,255,0.35),transparent_50%)]"></div>
                        <div class="relative text-center text-white px-4">
                            <i class="fa-solid <?= htmlspecialchars((string) $p['icone']) ?> text-4xl md:text-5xl drop-shadow-lg opacity-95"></i>
                            <p class="mt-3 text-2xl font-bold tabular-nums drop-shadow"><?= htmlspecialchars((string) $p['creditos_display']) ?></p>
                            <p class="text-xs uppercase tracking-widest opacity-90 mt-0.5">TudiCoins</p>
                        </div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($p['badge'])): ?>
                    <span class="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full bg-white/95 px-2.5 py-1 text-xs font-bold text-amber-700 shadow-sm">
                        <i class="fa-solid fa-star text-amber-500"></i> <?= htmlspecialchars((string) $p['badge']) ?>
                    </span>
                    <?php endif; ?>
                    <span class="absolute top-3 right-3 rounded-full bg-black/40 backdrop-blur px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">
                        <?= htmlspecialchars((string) $p['categoria_label']) ?>
                    </span>
                </div>

                <div class="flex flex-col flex-1 p-5">
                    <h3 class="text-lg font-bold text-gray-900 leading-snug"><?= htmlspecialchars((string) $p['nome']) ?></h3>
                    <p class="text-sm text-gray-600 mt-2 line-clamp-2 flex-1"><?= htmlspecialchars((string) $p['descricao']) ?></p>

                    <div class="mt-4 pt-4 border-t border-gray-100">
                        <div class="flex items-end justify-between gap-3">
                            <div>
                                <p class="text-2xl font-bold text-gray-900 tabular-nums"><?= htmlspecialchars((string) $p['valor_reais_display']) ?></p>
                                <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) $p['preco_por_coin_display']) ?></p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold text-accent tabular-nums"><?= htmlspecialchars((string) $p['creditos_display']) ?></p>
                                <p class="text-[11px] text-gray-500">na carteira</p>
                            </div>
                        </div>

                        <form method="post" action="<?= URL ?>/educashop" class="mt-4">
                            <input type="hidden" name="pacote_id" value="<?= (int) $p['id'] ?>">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            <button type="submit" class="w-full inline-flex items-center justify-center gap-2 px-4 py-3 rounded-xl bg-primary text-white text-sm font-semibold hover:opacity-90 transition-opacity shadow-sm">
                                <i class="fa-solid fa-cart-shopping"></i> Comprar agora
                            </button>
                        </form>
                        <p class="text-[11px] text-center text-gray-400 mt-2 flex items-center justify-center gap-1.5">
                            <i class="fa-solid fa-lock text-[10px]"></i> Checkout Asaas — PIX ou cartão
                        </p>
                    </div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>

        <p id="educashopEmptyFilter" class="hidden text-center text-gray-500 py-12">
            Nenhum pacote nesta categoria.
            <button type="button" data-categoria="todos" class="educashop-cat-btn block mx-auto mt-2 text-sm text-accent hover:underline">Ver todos</button>
        </p>

        <!-- Faixa confiança -->
        <div class="rounded-xl border border-gray-200 bg-gray-50 px-6 py-5">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-center md:text-left">
                <div class="flex flex-col md:flex-row items-center md:items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-accent shrink-0">
                        <i class="fa-solid fa-shield-halved"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Pagamento Asaas</p>
                        <p class="text-xs text-gray-600 mt-0.5">Gateway certificado com PIX e cartão de crédito.</p>
                    </div>
                </div>
                <div class="flex flex-col md:flex-row items-center md:items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-accent shrink-0">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Crédito na hora</p>
                        <p class="text-xs text-gray-600 mt-0.5">TudiCoins liberados automaticamente após confirmação.</p>
                    </div>
                </div>
                <div class="flex flex-col md:flex-row items-center md:items-start gap-3">
                    <div class="w-10 h-10 rounded-lg bg-white border border-gray-200 flex items-center justify-center text-accent shrink-0">
                        <i class="fa-solid fa-infinity"></i>
                    </div>
                    <div>
                        <p class="text-sm font-semibold text-gray-900">Sem expiração</p>
                        <p class="text-xs text-gray-600 mt-0.5">TudiCoins comprados não zeram na recarga da escola.</p>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Painel Pedidos -->
    <div id="educashopPanelPedidos" class="educashop-panel hidden">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-900">Histórico de compras</h2>
                <p class="text-sm text-gray-600 mt-0.5">Acompanhe pagamentos e status dos seus pedidos.</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pacote</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Meio</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ação</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($compras)): ?>
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                                <i class="fa-solid fa-bag-shopping text-4xl text-gray-300 mb-4"></i>
                                <p>Nenhuma compra registrada ainda.</p>
                                <button type="button" data-tab="loja" class="educashop-tab-btn mt-3 text-sm text-accent hover:underline">Ir para a loja</button>
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($compras as $c): ?>
                        <?php
                            $valorReais = 'R$ ' . number_format(((int) ($c['valor_centavos'] ?? 0)) / 100, 2, ',', '.');
                            $creditosCompra = \CreditosDecimalHelper::fromScalar($c['creditos'] ?? 0, 0.0);
                            $billingType = strtoupper((string) ($c['billing_type'] ?? ''));
                            $billingLabel = [
                                'PIX' => 'PIX',
                                'CREDIT_CARD' => 'Cartão',
                                'BOLETO' => 'Boleto',
                            ][$billingType] ?? ($billingType !== '' ? $billingType : '—');
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
                                'refunded' => 'bg-slate-100 text-slate-700',
                                'cancelled' => 'bg-slate-100 text-slate-700',
                            ][$statusValue] ?? 'bg-gray-100 text-gray-700';
                            $createdAt = !empty($c['created_at']) ? date('d/m/Y H:i', strtotime($c['created_at'])) : '-';
                            $compraId = (int) ($c['id'] ?? 0);
                        ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($createdAt) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-900">
                                <span class="font-medium"><?= htmlspecialchars((string) ($c['pacote_nome'] ?? 'Pacote')) ?></span>
                                <span class="block text-xs text-gray-500 mt-0.5 tabular-nums"><?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay($creditosCompra)) ?> TudiCoins</span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900 font-medium"><?= htmlspecialchars($valorReais) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= htmlspecialchars($billingLabel) ?></td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $statusClass ?>"><?= htmlspecialchars($statusLabel) ?></span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <?php if ($statusValue === 'pending' && $compraId > 0): ?>
                                <a href="<?= URL ?>/carteira/comprar/aguardando/<?= $compraId ?>" class="inline-flex items-center gap-1 text-accent font-medium hover:underline">
                                    <i class="fa-solid fa-credit-card text-xs"></i> Pagar
                                </a>
                                <?php elseif ($statusValue === 'paid'): ?>
                                <a href="<?= URL ?>/carteira" class="text-gray-600 hover:text-gray-900 text-xs">Ver carteira</a>
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

    const tabBtns = app.querySelectorAll('.educashop-tab-btn');
    const panelLoja = document.getElementById('educashopPanelLoja');
    const panelPedidos = document.getElementById('educashopPanelPedidos');

    function setTab(tab) {
        tabBtns.forEach(function (btn) {
            const active = btn.getAttribute('data-tab') === tab;
            btn.classList.toggle('educashop-tab-active', active);
            btn.classList.toggle('border-accent', active);
            btn.classList.toggle('text-accent', active);
            btn.classList.toggle('font-semibold', active);
            btn.classList.toggle('border-transparent', !active);
            btn.classList.toggle('text-gray-600', !active);
            btn.classList.toggle('font-medium', !active);
        });
        if (panelLoja) panelLoja.classList.toggle('hidden', tab !== 'loja');
        if (panelPedidos) panelPedidos.classList.toggle('hidden', tab !== 'pedidos');
    }

    tabBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            setTab(btn.getAttribute('data-tab') || 'loja');
        });
    });

    const catBtns = app.querySelectorAll('.educashop-cat-btn');
    const produtos = app.querySelectorAll('.educashop-produto');
    const emptyMsg = document.getElementById('educashopEmptyFilter');

    function filtrarCategoria(slug) {
        let visiveis = 0;
        produtos.forEach(function (el) {
            const cat = el.getAttribute('data-categoria') || '';
            const show = slug === 'todos' || cat === slug;
            el.classList.toggle('hidden', !show);
            if (show) visiveis++;
        });
        if (emptyMsg) emptyMsg.classList.toggle('hidden', visiveis > 0);

        catBtns.forEach(function (btn) {
            const active = btn.getAttribute('data-categoria') === slug;
            btn.classList.toggle('educashop-cat-active', active);
            btn.classList.toggle('bg-primary', active);
            btn.classList.toggle('text-white', active);
            btn.classList.toggle('bg-gray-100', !active);
            btn.classList.toggle('text-gray-700', !active);
        });
    }

    catBtns.forEach(function (btn) {
        btn.addEventListener('click', function () {
            filtrarCategoria(btn.getAttribute('data-categoria') || 'todos');
        });
    });
})();
</script>
