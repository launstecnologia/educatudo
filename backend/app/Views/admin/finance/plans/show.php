<?php /** @var array $plan @var string $csrf_token */ ?>

<?php include __DIR__ . '/../../_partials/flash_message.php'; ?>

<div class="mb-6">
    <div class="flex items-center gap-4 flex-wrap">
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
        <div class="flex items-center gap-3 flex-shrink-0">
            <form method="POST" action="<?= URL ?>/admin/finance/plans/<?= (int)$plan['id'] ?>/toggle">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    <?= $plan['ativo'] ? 'Desativar' : 'Ativar' ?>
                </button>
            </form>
            <button type="button" onclick="openPlanItemDrawer()"
                    class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo item
            </button>
        </div>
    </div>
</div>

<div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
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
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
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
                        <?php ob_start(); ?>
                        <button type="button" onclick="openPlanItemDrawer(<?= (int) $item['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="<?= URL ?>/admin/finance/plans/<?= (int)$plan['id'] ?>/items/<?= (int)$item['id'] ?>/delete"
                              onsubmit="return confirm('Remover este item?');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Remover
                            </button>
                        </form>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-plan-item-' . (int) $item['id'];
                        include __DIR__ . '/../../_partials/row_actions_dropdown.php';
                        ?>
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
        <button type="button" onclick="openPlanItemDrawer()"
                class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
            <i class="fa-solid fa-plus mr-2"></i> Novo item
        </button>
    </div>
    <?php endif; ?>
</div>

<div id="planItemDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closePlanItemDrawer()"></div>
<aside id="planItemDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="planItemDrawerTitle" class="text-xl font-bold text-gray-900">Novo item</h2>
        <button type="button" onclick="closePlanItemDrawer()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="plan-item-form" method="POST" action="<?= URL ?>/admin/finance/plans/<?= (int)$plan['id'] ?>/items"
          class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" id="pi_id" value="">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do item</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="pi_descricao" class="block text-sm font-medium text-gray-700 mb-1">
                            Descrição <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="pi_descricao" name="descricao" required placeholder="Ex: Mensalidade"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="pi_categoria" class="block text-sm font-medium text-gray-700 mb-1">
                            Categoria <span class="text-red-500">*</span>
                        </label>
                        <select id="pi_categoria" name="categoria" required
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="mensalidade">Mensalidade</option>
                            <option value="matricula">Matrícula</option>
                            <option value="material_didatico">Material Didático</option>
                            <option value="uniforme">Uniforme</option>
                            <option value="taxa">Taxa</option>
                            <option value="outros">Outros</option>
                        </select>
                    </div>
                    <div>
                        <label for="pi_valor_base" class="block text-sm font-medium text-gray-700 mb-1">
                            Valor Base (R$) <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="pi_valor_base" name="valor_base" required placeholder="0,00" inputmode="decimal"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        <p class="text-xs text-gray-500 mt-1">Total do item. Com 12 parcelas, cada cobrança será este valor dividido por 12.</p>
                    </div>
                    <div>
                        <label for="pi_num_parcelas" class="block text-sm font-medium text-gray-700 mb-1">N de Parcelas</label>
                        <input type="number" id="pi_num_parcelas" name="num_parcelas" value="1" min="1" max="12"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="pi_mes_inicio" class="block text-sm font-medium text-gray-700 mb-1">Mês Início</label>
                        <input type="number" id="pi_mes_inicio" name="mes_inicio" value="1" min="1" max="12"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="pi_mes_fim" class="block text-sm font-medium text-gray-700 mb-1">Mês Fim</label>
                        <input type="number" id="pi_mes_fim" name="mes_fim" min="1" max="12" placeholder="="
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="pi_dia_vencimento" class="block text-sm font-medium text-gray-700 mb-1">Dia Vencimento</label>
                        <input type="number" id="pi_dia_vencimento" name="dia_vencimento" min="1" max="31" placeholder="Padrão global"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                    <div>
                        <label for="pi_unidade_id" class="block text-sm font-medium text-gray-700 mb-1">Unidade Emissora (NF)</label>
                        <select id="pi_unidade_id" name="unidade_id"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                            <option value="">Escola padrão</option>
                            <?php foreach ($unidades ?? [] as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= htmlspecialchars((string)$u['nome']) ?> — <?= htmlspecialchars((string)($u['razao_social'] ?? '')) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="flex items-start gap-3">
                            <input type="checkbox" name="fornecedor_externo" id="pi_fext" value="1"
                                   class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                            <span>
                                <span class="block text-sm font-medium text-gray-700">Fornecedor externo</span>
                                <span class="block text-xs text-gray-500 mt-0.5">Marque se o pagamento é feito a terceiros (ex: editora, fornecedor de uniforme).</span>
                            </span>
                        </label>
                    </div>
                    <div id="pi-nome-instituicao-wrapper" class="sm:col-span-2 hidden">
                        <label for="pi_nome_instituicao" class="block text-sm font-medium text-gray-700 mb-1">Nome da Instituição</label>
                        <input type="text" id="pi_nome_instituicao" name="nome_instituicao" placeholder="Ex: Livraria XYZ"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closePlanItemDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="plan-item-form-submit-label">Adicionar item</span>
            </button>
        </div>
    </form>
</aside>

<script>
(function () {
    var PLAN_ID = <?= (int) $plan['id'] ?>;
    var URL_BASE = <?= json_encode(URL, JSON_UNESCAPED_SLASHES) ?>;
    var form = document.getElementById('plan-item-form');
    var inputValor = document.getElementById('pi_valor_base');
    var fext = document.getElementById('pi_fext');
    var wrapperInst = document.getElementById('pi-nome-instituicao-wrapper');
    var submitBtn = form.querySelector('button[type="submit"]');
    var submitLabel = document.getElementById('plan-item-form-submit-label');

    function setSubmitEnabled(enabled) {
        if (!submitBtn) return;
        submitBtn.disabled = !enabled;
        submitBtn.classList.toggle('opacity-60', !enabled);
        submitBtn.classList.toggle('cursor-not-allowed', !enabled);
    }

    function maskBRL(val) {
        val = String(val || '').replace(/\D/g, '');
        if (!val) return '';
        val = (parseInt(val, 10) / 100).toFixed(2);
        return val.replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }
    function formatValorFromNumber(n) {
        var cents = Math.round(Number(n) * 100);
        if (!isFinite(cents) || cents < 0) cents = 0;
        return maskBRL(String(cents));
    }
    function syncFext() {
        if (wrapperInst) wrapperInst.classList.toggle('hidden', !(fext && fext.checked));
    }
    if (fext) fext.addEventListener('change', syncFext);
    if (inputValor) {
        inputValor.addEventListener('input', function () {
            this.value = maskBRL(this.value);
        });
        inputValor.addEventListener('blur', function () {
            if (this.value && this.value.indexOf(',') === -1) {
                this.value = maskBRL(this.value);
            }
        });
    }
    form.addEventListener('submit', function (e) {
        if (form.dataset.mode === 'edit' && !document.getElementById('pi_id').value) {
            e.preventDefault();
            return;
        }
        if (inputValor && inputValor.value) {
            inputValor.value = inputValor.value.replace(/\./g, '').replace(',', '.');
        }
    });

    function showPlanItemDrawer() {
        document.getElementById('planItemDrawerBackdrop').classList.remove('hidden');
        var drawer = document.getElementById('planItemDrawer');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }
    window.closePlanItemDrawer = function () {
        document.getElementById('planItemDrawerBackdrop').classList.add('hidden');
        var drawer = document.getElementById('planItemDrawer');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };
    window.openPlanItemDrawer = function (id) {
        form.reset();
        document.getElementById('pi_id').value = '';
        document.getElementById('pi_num_parcelas').value = '1';
        document.getElementById('pi_mes_inicio').value = '1';
        syncFext();
        form.action = URL_BASE + '/admin/finance/plans/' + PLAN_ID + '/items';
        form.dataset.mode = 'create';

        if (!id) {
            document.getElementById('planItemDrawerTitle').textContent = 'Novo item';
            submitLabel.textContent = 'Adicionar item';
            setSubmitEnabled(true);
            showPlanItemDrawer();
            return;
        }

        form.dataset.mode = 'edit';
        document.getElementById('planItemDrawerTitle').textContent = 'Editar item';
        submitLabel.textContent = 'Carregando...';
        setSubmitEnabled(false);
        form.action = URL_BASE + '/admin/finance/plans/' + PLAN_ID + '/items/' + id + '/update';
        showPlanItemDrawer();

        fetch(URL_BASE + '/admin/finance/plans/' + PLAN_ID + '/items/' + id + '/dados', {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (!data || !data.success || !data.item) {
                alert((data && data.error) ? data.error : 'Não foi possível carregar o item.');
                closePlanItemDrawer();
                return;
            }
            var item = data.item;
            document.getElementById('pi_id').value = item.id;
            document.getElementById('pi_descricao').value = item.descricao || '';
            document.getElementById('pi_categoria').value = item.categoria || 'mensalidade';
            document.getElementById('pi_valor_base').value = formatValorFromNumber(item.valor_base);
            document.getElementById('pi_num_parcelas').value = item.num_parcelas || 1;
            document.getElementById('pi_mes_inicio').value = item.mes_inicio || 1;
            document.getElementById('pi_mes_fim').value = item.mes_fim || '';
            document.getElementById('pi_dia_vencimento').value = item.dia_vencimento || '';
            document.getElementById('pi_unidade_id').value = item.unidade_id || '';
            document.getElementById('pi_fext').checked = !!parseInt(item.fornecedor_externo, 10);
            document.getElementById('pi_nome_instituicao').value = item.nome_instituicao || '';
            syncFext();
            submitLabel.textContent = 'Salvar alterações';
            setSubmitEnabled(true);
        }).catch(function () {
            alert('Erro de conexão ao carregar o item.');
            closePlanItemDrawer();
        });
    };

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') closePlanItemDrawer();
    });
})();
</script>
