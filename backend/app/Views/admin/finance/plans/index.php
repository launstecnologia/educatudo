<?php /** @var array $plans @var array $anos_letivos @var int $ano_letivo_id @var string $csrf_token */ ?>

<!-- Cabeçalho -->
<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Planos e Preços</h2>
            <p class="text-gray-600 text-sm">Planos pré-configurados com itens de cobrança por ano letivo.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <a href="<?= URL ?>/admin/finance/plans/create"
               class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Plano
            </a>
        </div>
    </div>
</div>

<!-- Filtro ano letivo -->
<form method="GET" class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-6">
    <div class="flex items-end gap-3">
        <div class="w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1">Ano Letivo</label>
            <select name="ano_letivo_id"
                    class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                    onchange="this.form.submit()">
                <option value="">Todos os anos</option>
                <?php foreach ($anos_letivos as $al): ?>
                <option value="<?= (int)$al['id'] ?>" <?= $ano_letivo_id == $al['id'] ? 'selected' : '' ?>><?= htmlspecialchars($al['ano']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
</form>

<!-- Grid de planos -->
<?php if (empty($plans)): ?>
<div class="bg-white rounded-xl border border-gray-200 shadow-sm p-12 text-center">
    <i class="fa-solid fa-layer-group text-4xl text-gray-300 mb-4 block"></i>
    <p class="font-medium text-gray-500">Nenhum plano cadastrado</p>
    <a href="<?= URL ?>/admin/finance/plans/create"
       class="mt-3 inline-flex items-center px-4 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90">
        <i class="fa-solid fa-plus mr-2"></i> Criar primeiro plano
    </a>
</div>
<?php else: ?>
<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    <?php foreach ($plans as $plan): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow flex flex-col">
        <div class="p-5 flex-1">
            <div class="flex items-start justify-between mb-3">
                <div class="flex-1 min-w-0">
                    <h3 class="font-semibold text-gray-900 truncate"><?= htmlspecialchars($plan['nome']) ?></h3>
                    <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars($plan['ano_letivo_nome'] ?? '') ?></p>
                </div>
                <span class="ml-2 inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $plan['ativo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                    <?= $plan['ativo'] ? 'Ativo' : 'Inativo' ?>
                </span>
            </div>

            <?php if (!empty($plan['items'])): ?>
            <div class="space-y-1.5 mb-4">
                <?php foreach ($plan['items'] as $item): ?>
                <div class="flex justify-between text-xs text-gray-600">
                    <span class="truncate mr-2"><?= htmlspecialchars($item['descricao']) ?></span>
                    <span class="font-medium whitespace-nowrap">R$ <?= number_format($item['valor_base'], 2, ',', '.') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="flex justify-between text-sm font-semibold text-gray-900 border-t border-gray-100 pt-3">
                <span>Total</span>
                <span>R$ <?= number_format($plan['total_plan'], 2, ',', '.') ?></span>
            </div>
            <?php else: ?>
            <p class="text-xs text-gray-400 italic mb-4">Nenhum item adicionado</p>
            <?php endif; ?>
        </div>

        <div class="px-5 pb-4 flex gap-2 border-t border-gray-100 pt-4">
            <a href="<?= URL ?>/admin/finance/plans/<?= (int)$plan['id'] ?>"
               class="flex-1 inline-flex items-center justify-center gap-1.5 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100 hover:border-blue-300 transition-colors">
                <i class="fa-solid fa-circle-info text-blue-600"></i> Ver / Editar
            </a>
            <form method="POST" action="<?= URL ?>/admin/finance/plans/<?= (int)$plan['id'] ?>/toggle" class="flex-1">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit"
                        class="w-full text-xs py-1.5 rounded-lg border border-gray-300 text-gray-600 bg-white hover:bg-gray-50 transition-colors">
                    <?= $plan['ativo'] ? 'Desativar' : 'Ativar' ?>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>
