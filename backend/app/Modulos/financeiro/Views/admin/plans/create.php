<?php /** @var array $anos_letivos @var array $series @var string $csrf_token */ ?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance/plans"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Novo Plano Financeiro</h2>
            <p class="text-sm text-gray-600">Defina nome, ano letivo e adicione os itens de cobrança.</p>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden w-full">
    <form method="POST" action="<?= URL ?>/admin/finance/plans" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <section class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Identificação</h3>
                <p class="mt-1 text-sm text-gray-500">Dados básicos do plano financeiro.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="md:col-span-2">
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">
                        Nome do Plano <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nome" name="nome" required
                           placeholder="Ex: Plano Ensino Médio 2026"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div class="md:col-span-2">
                    <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <textarea id="descricao" name="descricao" rows="3"
                              placeholder="Detalhes opcionais..."
                              class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"></textarea>
                </div>

                <div>
                    <label for="ano_letivo_id" class="block text-sm font-medium text-gray-700 mb-2">
                        Ano Letivo <span class="text-red-500">*</span>
                    </label>
                    <select id="ano_letivo_id" name="ano_letivo_id" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Selecione...</option>
                        <?php foreach ($anos_letivos as $al): ?>
                            <option value="<?= (int)$al['id'] ?>"><?= htmlspecialchars((string)$al['ano']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </section>

        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200">
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <a href="<?= URL ?>/admin/finance/plans"
                   class="inline-flex items-center justify-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancelar
                </a>
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    <i class="fa-solid fa-check mr-2"></i>
                    Criar Plano
                </button>
            </div>
        </div>
    </form>
</div>
