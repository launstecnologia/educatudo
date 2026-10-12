<?php
$planos = $planos ?? [];
$assinaturas = $assinaturas ?? [];
?>
<div class="max-w-4xl mx-auto">
    <div class="mb-6">
        <a href="<?= URL ?>/carteira" class="text-sm text-gray-600 hover:underline">&larr; Voltar à carteira</a>
        <h1 class="text-2xl font-bold text-gray-900 mt-2">Planos de créditos</h1>
        <p class="text-gray-600 mt-1">Assine um plano e receba créditos todo mês.</p>
    </div>

    <?php if (!empty($assinaturas)): ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-3">Minhas assinaturas</h2>
        <ul class="space-y-2">
            <?php foreach ($assinaturas as $a): ?>
            <li class="flex justify-between items-center text-sm">
                <span><?= htmlspecialchars($a['plano_nome'] ?? '') ?> – <?= (int)($a['creditos_mensais'] ?? 0) ?> créditos/mês</span>
                <span class="<?= !empty($a['ativa']) ? 'text-green-600' : 'text-gray-500' ?>"><?= !empty($a['ativa']) ? 'Ativa' : 'Inativa' ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <h2 class="text-lg font-semibold text-gray-900 mb-3">Planos disponíveis</h2>
    <?php if (empty($planos)): ?>
    <p class="text-gray-500">Nenhum plano disponível no momento.</p>
    <?php else: ?>
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($planos as $p): ?>
        <div class="bg-white rounded-xl shadow border border-gray-200 p-6 flex flex-col">
            <h3 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($p['nome'] ?? '') ?></h3>
            <p class="text-2xl font-bold text-indigo-600 mt-2"><?= (int)($p['creditos_mensais'] ?? 0) ?> <span class="text-sm font-normal text-gray-600">créditos/mês</span></p>
            <p class="text-sm text-gray-500 mt-1">R$ <?= number_format((float)($p['valor_mensal'] ?? 0), 2, ',', '.') ?>/mês</p>
            <form method="post" action="<?= URL ?>/carteira/planos/assinar" class="mt-4">
                <input type="hidden" name="plano_id" value="<?= (int)($p['id'] ?? 0) ?>">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 text-sm font-medium">Assinar</button>
            </form>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
