<?php /** @var array $plan @var string $csrf_token */ ?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance/plans"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-900 mb-1"><?= htmlspecialchars((string)$plan['nome']) ?></h2>
            <p class="text-sm text-gray-600">
                <?= htmlspecialchars((string)($plan['ano_letivo_nome'] ?? '')) ?>
                <?php if (!empty($plan['serie_nome'])): ?> · <?= htmlspecialchars((string)$plan['serie_nome']) ?><?php endif; ?>
                <span class="ml-2 inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $plan['ativo'] ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                    <?= $plan['ativo'] ? 'Ativo' : 'Inativo' ?>
                </span>
            </p>
        </div>
        <form method="POST" action="<?= URL ?>/admin/finance/plans/<?= (int)$plan['id'] ?>/toggle">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                <?= $plan['ativo'] ? 'Desativar' : 'Ativar' ?>
            </button>
        </form>
    </div>
</div>

<!-- Itens do plano -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden mb-6">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Itens do Plano</h3>
        <span class="text-sm text-gray-500">Total: <strong class="text-gray-900">R$ <?= number_format($plan['total_plan'] ?? 0, 2, ',', '.') ?></strong></span>
    </div>

    <?php if (!empty($plan['items'])): ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Base</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Parcelas</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Início–Fim</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Dia Venc.</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Instituição</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($plan['items'] as $item): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars((string)$item['descricao']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex px-2 py-0.5 rounded text-xs bg-blue-50 text-blue-700"><?= htmlspecialchars((string)$item['categoria']) ?></span>
                        <?php if ($item['fornecedor_externo']): ?>
                            <span class="ml-1 inline-flex px-2 py-0.5 rounded text-xs bg-orange-50 text-orange-700">externo</span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-gray-900">R$ <?= number_format($item['valor_base'], 2, ',', '.') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-600"><?= (int)$item['num_parcelas'] ?>x</td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">
                        <?= (int)$item['mes_inicio'] ?>/<?= (int)($item['mes_fim'] ?? $item['mes_inicio']) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500"><?= $item['dia_vencimento'] ?? '—' ?></td>
                    <td class="px-6 py-4 text-xs text-gray-500">
                        <?php
                        if (!empty($item['unidade_nome'])) {
                            echo '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-indigo-50 text-indigo-700 font-medium">'
                               . '<i class="fa-solid fa-building text-indigo-400"></i> '
                               . htmlspecialchars((string)$item['unidade_nome']) . '</span>';
                        } elseif (!empty($item['nome_instituicao'])) {
                            echo htmlspecialchars((string)$item['nome_instituicao']);
                        } else {
                            echo '<span class="text-gray-300">Escola principal</span>';
                        }
                        ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <form method="POST" action="<?= URL ?>/admin/finance/plans/<?= (int)$plan['id'] ?>/items/<?= (int)$item['id'] ?>/delete"
                              onsubmit="return confirm('Remover este item?')">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" class="text-sm text-red-500 hover:text-red-700 font-medium">Remover</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="2" class="px-6 py-3 text-sm font-semibold text-gray-700">Total do Plano</td>
                    <td class="px-6 py-3 text-sm text-right font-semibold text-gray-900">R$ <?= number_format($plan['total_plan'] ?? 0, 2, ',', '.') ?></td>
                    <td colspan="5"></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <?php else: ?>
    <div class="px-6 py-12 text-center text-gray-500">
        <i class="fa-solid fa-list-ul text-4xl text-gray-300 mb-4 block"></i>
        <p>Nenhum item adicionado ainda.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Adicionar item -->
<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Adicionar Item</h3>
        <p class="mt-1 text-sm text-gray-500">Configure um novo item de cobrança para este plano.</p>
    </div>
    <form method="POST" action="<?= URL ?>/admin/finance/plans/<?= (int)$plan['id'] ?>/items" class="divide-y divide-gray-200">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

        <section class="p-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">
                        Descrição <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="descricao" name="descricao" required
                           placeholder="Ex: Mensalidade"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div>
                    <label for="categoria" class="block text-sm font-medium text-gray-700 mb-2">
                        Categoria <span class="text-red-500">*</span>
                    </label>
                    <select id="categoria" name="categoria" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="mensalidade">Mensalidade</option>
                        <option value="matricula">Matrícula</option>
                        <option value="material_didatico">Material Didático</option>
                        <option value="uniforme">Uniforme</option>
                        <option value="taxa">Taxa</option>
                        <option value="outros">Outros</option>
                    </select>
                </div>

                <div>
                    <label for="valor_base" class="block text-sm font-medium text-gray-700 mb-2">
                        Valor Base (R$) <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="valor_base" name="valor_base" required placeholder="0,00" inputmode="decimal"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div>
                    <label for="num_parcelas" class="block text-sm font-medium text-gray-700 mb-2">N de Parcelas</label>
                    <input type="number" id="num_parcelas" name="num_parcelas" value="1" min="1" max="12"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div>
                    <label for="mes_inicio" class="block text-sm font-medium text-gray-700 mb-2">Mês Início</label>
                    <input type="number" id="mes_inicio" name="mes_inicio" value="1" min="1" max="12"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div>
                    <label for="mes_fim" class="block text-sm font-medium text-gray-700 mb-2">Mês Fim</label>
                    <input type="number" id="mes_fim" name="mes_fim" min="1" max="12" placeholder="="
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div>
                    <label for="dia_vencimento" class="block text-sm font-medium text-gray-700 mb-2">Dia Vencimento</label>
                    <input type="number" id="dia_vencimento" name="dia_vencimento" min="1" max="31" placeholder="Padrão global"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>

                <div>
                    <label for="unidade_id" class="block text-sm font-medium text-gray-700 mb-2">Unidade Emissora (NF)</label>
                    <select id="unidade_id" name="unidade_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <option value="">Escola padrão</option>
                        <?php foreach ($unidades ?? [] as $u): ?>
                            <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars((string)$u['nome']) ?> — <?= htmlspecialchars((string)($u['razao_social'] ?? '')) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="md:col-span-2">
                    <label class="flex items-start gap-3">
                        <input type="checkbox" name="fornecedor_externo" id="fext" value="1"
                               class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">Fornecedor externo</span>
                            <span class="block text-xs text-gray-500 mt-0.5">Marque se o pagamento é feito a terceiros (ex: editora, fornecedor de uniforme).</span>
                        </span>
                    </label>
                </div>

                <div id="nome-instituicao-wrapper" class="md:col-span-2 hidden">
                    <label for="nome_instituicao" class="block text-sm font-medium text-gray-700 mb-2">Nome da Instituição</label>
                    <input type="text" id="nome_instituicao" name="nome_instituicao" placeholder="Ex: Livraria XYZ"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            </div>
        </section>

        <div class="px-6 py-4 bg-gray-50/70 border-t border-gray-200">
            <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-3">
                <button type="submit"
                        class="inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    <i class="fa-solid fa-plus mr-2"></i>
                    Adicionar Item
                </button>
            </div>
        </div>
    </form>
</div>

<script>
(function () {
    // Mostrar/ocultar campo de nome da instituição
    const fext = document.getElementById('fext');
    const wrapper = document.getElementById('nome-instituicao-wrapper');
    if (fext && wrapper) {
        fext.addEventListener('change', function () {
            wrapper.classList.toggle('hidden', !this.checked);
        });
    }

    // Máscara de valor BRL
    const input = document.getElementById('valor_base');
    if (!input) return;

    function maskBRL(val) {
        val = val.replace(/\D/g, '');
        if (!val) return '';
        val = (parseInt(val, 10) / 100).toFixed(2);
        return val.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    input.addEventListener('input', function () {
        this.value = maskBRL(this.value);
    });

    input.addEventListener('blur', function () {
        if (this.value && !this.value.includes(',')) {
            this.value = maskBRL(this.value);
        }
    });

    // Convert masked value to decimal before form submit
    input.closest('form').addEventListener('submit', function () {
        input.value = input.value.replace(/\./g, '').replace(',', '.');
    });
})();
</script>
