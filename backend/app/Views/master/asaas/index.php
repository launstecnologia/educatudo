<?php
$cfg = $cfg ?? [];
$erro_tabela = $erro_tabela ?? null;
$webhook_url = $webhook_url ?? '';
$public_base_url = $public_base_url ?? '';
$escolas = $escolas ?? [];
$flash = $flash ?? null;
$csrf_token = $csrf_token ?? '';
?>
<div class="max-w-3xl mx-auto space-y-8">
    <div>
        <h1 class="text-2xl font-bold text-slate-900"><?= htmlspecialchars($page_title ?? 'Asaas') ?></h1>
        <p class="text-slate-600 mt-1 text-sm">API key, ambiente e token do webhook. A chave nunca é exibida após salvar.</p>
    </div>

    <?php if (!empty($flash['message'])): ?>
    <div class="rounded-lg px-4 py-3 text-sm <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 text-red-800 border border-red-200' : 'bg-green-50 text-green-800 border border-green-200' ?>">
        <?= htmlspecialchars((string) $flash['message']) ?>
    </div>
    <?php endif; ?>

    <?php if ($erro_tabela): ?>
    <div class="bg-amber-50 border border-amber-200 text-amber-900 text-sm rounded-lg p-4"><?= htmlspecialchars($erro_tabela) ?></div>
    <?php else: ?>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-2">Webhook (Asaas)</h2>
        <p class="text-sm text-slate-600 mb-2">Configure no painel Asaas a URL abaixo e o mesmo token que você salvar em &quot;Token do header&quot; (header <code class="text-xs bg-slate-100 px-1 rounded">asaas-access-token</code>).</p>
        <p class="font-mono text-sm bg-slate-50 border border-slate-200 rounded px-3 py-2 break-all"><?= htmlspecialchars($webhook_url) ?></p>
        <?php if ($public_base_url !== ''): ?>
        <p class="text-xs text-slate-500 mt-2">Domínio detectado automaticamente nesta tela: <code class="bg-slate-100 px-1 rounded"><?= htmlspecialchars($public_base_url) ?></code></p>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= URL ?>/master/asaas/salvar" class="bg-white rounded-xl shadow border border-slate-200 p-6 space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <h2 class="text-lg font-semibold text-slate-900">Credenciais</h2>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">API Key Asaas</label>
            <input type="password" name="api_key" autocomplete="off" placeholder="Deixe em branco para manter a atual" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm">
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Ambiente</label>
            <select name="environment" class="px-3 py-2 border border-slate-300 rounded-lg text-sm">
                <?php $cur = $cfg['environment'] ?? 'sandbox'; ?>
                <option value="sandbox" <?= $cur === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                <option value="production" <?= $cur === 'production' ? 'selected' : '' ?>>Produção</option>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Token do webhook (asaas-access-token)</label>
            <input type="text" name="webhook_token" value="<?= htmlspecialchars($cfg['webhook_token'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm font-mono">
        </div>
        <p class="text-xs text-slate-500">No tenant, defina <code class="bg-slate-100 px-1">MASTER_PUBLIC_URL</code> com a URL base deste Master e o mesmo <code class="bg-slate-100 px-1">MASTER_CREDITOS_CHECKOUT_SECRET</code> para o link de pagamento.</p>
        <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Salvar</button>
    </form>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h2 class="text-lg font-semibold text-slate-900 mb-2">Reconciliação</h2>
        <p class="text-sm text-slate-600 mb-4">Consulta a API Asaas para compras <strong>pending</strong> que já tenham <code class="text-xs bg-slate-100 px-1">asaas_payment_id</code> e aplica o crédito se o pagamento estiver confirmado (idempotente).</p>
        <form method="post" action="<?= URL ?>/master/asaas/reconciliar" class="flex flex-wrap items-end gap-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Escola (opcional)</label>
                <select name="escola_id" class="px-3 py-2 border border-slate-300 rounded-lg text-sm min-w-[220px]">
                    <option value="0">Todas com banco configurado</option>
                    <?php foreach ($escolas as $e): ?>
                    <option value="<?= (int) ($e['id'] ?? 0) ?>"><?= htmlspecialchars($e['nome'] ?? '') ?> (<?= (int) ($e['id'] ?? 0) ?>)</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <button type="submit" class="px-4 py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900 text-sm font-medium">Executar reconciliação</button>
        </form>
        <p class="text-xs text-slate-500 mt-4">Cron (opcional): <code class="bg-slate-100 px-1">GET <?= htmlspecialchars(rtrim((defined('URL') ? URL : ''), '/')) ?>/master/asaas/reconciliar-cron?key=SEU_ASAAS_RECONCILE_KEY</code> — defina <code class="bg-slate-100 px-1">ASAAS_RECONCILE_KEY</code> no .env do Master.</p>
        <p class="text-xs text-slate-500 mt-2">Cancelar pending &gt; 1h: <code class="bg-slate-100 px-1">GET <?= htmlspecialchars(rtrim((defined('URL') ? URL : ''), '/')) ?>/master/asaas/cancelar-pendentes-cron?key=SEU_ASAAS_RECONCILE_KEY</code> (opcional <code class="bg-slate-100 px-1">&amp;minutos=60</code>).</p>
    </div>
    <?php endif; ?>
</div>
