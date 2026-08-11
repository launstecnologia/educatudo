<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

$page_header_title    = 'Dashboard Financeiro';
$page_header_subtitle = 'Visão geral das finanças da escola.';
ob_start(); ?>
<form method="GET" class="inline-flex items-center">
    <select name="ano_letivo_id"
            class="border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
            onchange="this.form.submit()">
        <option value="">Todos os anos</option>
        <?php foreach ($anos_letivos as $al): ?>
        <option value="<?= (int)$al['id'] ?>" <?= $ano_letivo_id == $al['id'] ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
        <?php endforeach; ?>
    </select>
</form>
<form method="POST" action="<?= URL ?>/admin/finance/disparar-regua" class="inline">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
    <button type="submit"
            onclick="return confirm('Disparar régua de cobrança agora?')"
            class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
        <i class="fa-solid fa-bell mr-2 text-gray-500"></i> Disparar Régua
    </button>
</form>
<a href="<?= URL ?>/admin/finance/charges/batch"
   class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-bolt mr-2 text-amber-500"></i> Cobrança em Lote
</a>
<a href="<?= URL ?>/admin/finance/contracts/create"
   class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
    <i class="fa-solid fa-plus mr-2"></i> Novo Contrato
</a>
<?php $page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php'; ?>

<!-- KPIs -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <p class="text-xs font-medium text-gray-500 mb-2">Receita Prevista</p>
        <p class="text-2xl font-bold text-gray-900"><?= $brl($kpis['receita_prevista'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <p class="text-xs font-medium text-gray-500 mb-2">Receita Realizada</p>
        <p class="text-2xl font-bold text-green-600"><?= $brl($kpis['receita_realizada'] ?? 0) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <p class="text-xs font-medium text-gray-500 mb-2">Taxa de Inadimplência</p>
        <p class="text-2xl font-bold <?= $tx_inadimplencia > 10 ? 'text-red-600' : ($tx_inadimplencia > 5 ? 'text-amber-500' : 'text-green-600') ?>">
            <?= $tx_inadimplencia ?>%
        </p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6">
        <p class="text-xs font-medium text-gray-500 mb-2">Parcelas Vencidas</p>
        <p class="text-2xl font-bold text-red-600"><?= (int)($kpis['qtd_vencidas'] ?? 0) ?></p>
    </div>
</div>

<!-- Status de contratos -->
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <?php
    $statusMap = [
        'rascunho'  => ['Rascunho', 'gray'],
        'ativo'     => ['Ativo',    'green'],
        'cancelado' => ['Cancelado','red'],
        'encerrado' => ['Encerrado','purple'],
    ];
    foreach ($statusMap as $st => [$label, $color]): ?>
    <a href="<?= URL ?>/admin/finance/contracts?status=<?= $esc($st) ?>"
       class="bg-white border border-gray-200 rounded-xl p-4 text-center hover:bg-gray-50 transition shadow-sm">
        <p class="text-2xl font-bold text-<?= $color ?>-700"><?= (int)($contratos[$st] ?? 0) ?></p>
        <p class="text-xs text-<?= $color ?>-600 mt-1"><?= $esc($label) ?></p>
    </a>
    <?php endforeach; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <!-- Vencendo em 7 dias -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Vencendo nos próximos 7 dias</h3>
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800"><?= count($vencendo) ?></span>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (empty($vencendo)): ?>
            <div class="px-5 py-8 text-center">
                <i class="fa-solid fa-calendar-check text-3xl text-gray-300 mb-3 block"></i>
                <p class="text-sm text-gray-500">Nenhuma parcela vencendo em breve.</p>
            </div>
            <?php else: foreach ($vencendo as $p): ?>
            <div class="px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition">
                <div class="min-w-0 mr-3">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= $esc($p['aluno_nome'] ?? '') ?></p>
                    <p class="text-xs text-gray-500"><?= $esc($p['descricao']) ?> &mdash; <?= date('d/m/Y', strtotime($p['data_vencimento'])) ?></p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-semibold text-amber-700"><?= $brl($p['valor_cobrado']) ?></p>
                    <a href="<?= URL ?>/admin/finance/installments/<?= (int)$p['id'] ?>"
                       class="text-xs text-blue-600 hover:underline">Ver</a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

    <!-- Em atraso -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-gray-900">Em atraso</h3>
            <a href="<?= URL ?>/admin/finance/report/inadimplencia"
               class="text-xs text-red-600 hover:underline font-medium">Ver relatório</a>
        </div>
        <div class="divide-y divide-gray-100">
            <?php if (empty($vencidas)): ?>
            <div class="px-5 py-8 text-center">
                <i class="fa-solid fa-circle-check text-3xl text-gray-300 mb-3 block"></i>
                <p class="text-sm text-gray-500">Nenhuma parcela em atraso.</p>
            </div>
            <?php else: foreach ($vencidas as $p): ?>
            <div class="px-5 py-4 flex items-center justify-between hover:bg-gray-50 transition">
                <div class="min-w-0 mr-3">
                    <p class="text-sm font-medium text-gray-900 truncate"><?= $esc($p['aluno_nome'] ?? '') ?></p>
                    <p class="text-xs text-red-500"><?= $esc($p['descricao']) ?> &mdash; Venceu <?= date('d/m/Y', strtotime($p['data_vencimento'])) ?></p>
                </div>
                <div class="text-right flex-shrink-0">
                    <p class="text-sm font-semibold text-red-600"><?= $brl($p['valor_cobrado']) ?></p>
                    <a href="<?= URL ?>/admin/finance/installments/<?= (int)$p['id'] ?>"
                       class="text-xs text-blue-600 hover:underline">Ver</a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
