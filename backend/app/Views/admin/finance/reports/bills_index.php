<?php
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');
$dt  = fn($d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$stCls = ['pendente'=>'bg-amber-100 text-amber-700','pago'=>'bg-green-100 text-green-700','vencido'=>'bg-red-100 text-red-700','cancelado'=>'bg-gray-100 text-gray-400'];
$stLabel = ['pendente'=>'Pendente','pago'=>'Pago','vencido'=>'Vencido','cancelado'=>'Cancelado'];

$totPendente = array_sum(array_column(array_filter($bills, fn($b) => in_array($b['status'],['pendente','vencido'])), 'valor'));
$totPago     = array_sum(array_column(array_filter($bills, fn($b) => $b['status']==='pago'), 'valor_pago'));
?>

<!-- Header -->
<div class="mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Contas a Pagar</h2>
            <p class="text-sm text-gray-500">Despesas operacionais e pagamentos a fornecedores</p>
        </div>
    </div>
    <button type="button" onclick="document.getElementById('modalNovaConta').showModal()"
            class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-xl text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
        <i class="fa-solid fa-plus mr-2"></i> Nova Conta
    </button>
</div>

<!-- KPIs -->
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-amber-50 rounded-xl border border-amber-200 p-4 shadow-sm">
        <p class="text-xs font-semibold text-amber-600 uppercase mb-1">A Pagar</p>
        <p class="text-xl font-bold text-amber-700"><?= $brl($totPendente) ?></p>
    </div>
    <div class="bg-green-50 rounded-xl border border-green-200 p-4 shadow-sm">
        <p class="text-xs font-semibold text-green-600 uppercase mb-1">Pago no Período</p>
        <p class="text-xl font-bold text-green-700"><?= $brl($totPago) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <p class="text-xs font-semibold text-gray-500 uppercase mb-1">Total Contas</p>
        <p class="text-xl font-bold text-gray-800"><?= count($bills) ?></p>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
                <option value="">Todos</option>
                <?php foreach ($stLabel as $v => $l): ?>
                <option value="<?= $v ?>" <?= ($_GET['status']??'')===$v?'selected':'' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Mês</label>
            <input type="month" name="mes" value="<?= htmlspecialchars($_GET['mes']??'') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <button type="submit" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-filter mr-2 text-gray-400"></i> Filtrar
        </button>
        <a href="<?= URL ?>/admin/finance/bills" class="text-sm text-gray-400 hover:text-gray-600 py-2">Limpar</a>
    </form>
</div>

<!-- Lista -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <?php if (empty($bills)): ?>
    <div class="px-6 py-16 text-center text-gray-400">
        <i class="fa-solid fa-file-invoice-dollar text-4xl mb-4 block text-gray-200"></i>
        <p class="font-medium">Nenhuma conta a pagar registrada.</p>
        <button type="button" onclick="document.getElementById('modalNovaConta').showModal()"
                class="mt-4 inline-flex items-center px-4 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90">
            <i class="fa-solid fa-plus mr-2"></i> Registrar primeira conta
        </button>
    </div>
    <?php else: ?>
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Descrição</th>
                <th class="px-5 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Categoria</th>
                <th class="px-5 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Valor</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Vencimento</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                <th class="px-5 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Pago em</th>
                <th class="px-5 py-3"></th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100">
            <?php foreach ($bills as $b): ?>
            <?php $hoje = date('Y-m-d'); $vencido = $b['data_vencimento'] < $hoje && $b['status']==='pendente'; ?>
            <tr class="hover:bg-gray-50 <?= $vencido ? 'bg-red-50/30' : '' ?>">
                <td class="px-5 py-3">
                    <p class="text-sm font-semibold text-gray-900"><?= htmlspecialchars($b['descricao']) ?></p>
                    <?php if ($b['fornecedor']): ?><p class="text-xs text-gray-400"><?= htmlspecialchars($b['fornecedor']) ?></p><?php endif; ?>
                    <?php if ($b['recorrente']): ?><span class="inline-flex items-center gap-1 text-xs text-indigo-600"><i class="fa-solid fa-repeat text-xs"></i> Recorrente</span><?php endif; ?>
                </td>
                <td class="px-5 py-3 text-sm text-gray-600">
                    <?php if ($b['conta_grupo']): ?><span class="text-xs text-gray-400 block"><?= htmlspecialchars($b['conta_grupo']) ?></span><?php endif; ?>
                    <?= htmlspecialchars($b['conta_nome'] ?? '—') ?>
                </td>
                <td class="px-5 py-3 text-sm text-right font-bold text-gray-900"><?= $brl($b['valor']) ?></td>
                <td class="px-5 py-3 text-sm text-center <?= $vencido ? 'text-red-600 font-semibold' : 'text-gray-600' ?>">
                    <?= $dt($b['data_vencimento']) ?>
                    <?php if ($vencido): ?><span class="block text-xs"><?= (new DateTime())->diff(new DateTime($b['data_vencimento']))->days ?>d vencido</span><?php endif; ?>
                </td>
                <td class="px-5 py-3 text-center">
                    <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $stCls[$vencido ? 'vencido' : $b['status']] ?>">
                        <?= $stLabel[$vencido ? 'vencido' : $b['status']] ?>
                    </span>
                </td>
                <td class="px-5 py-3 text-sm text-center text-gray-500"><?= $dt($b['data_pagamento']) ?></td>
                <td class="px-5 py-3 text-right">
                    <?php if (in_array($b['status'], ['pendente','vencido']) || $vencido): ?>
                    <button type="button" onclick="document.getElementById('pay-bill-<?= (int)$b['id'] ?>').showModal()"
                            class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-green-300 text-xs font-medium text-green-700 hover:bg-green-50 transition-colors">
                        <i class="fa-solid fa-check mr-1"></i> Pagar
                    </button>
                    <dialog id="pay-bill-<?= (int)$b['id'] ?>" class="rounded-xl shadow-xl border border-gray-200 p-6 w-full max-w-sm backdrop:bg-black/40">
                        <h3 class="text-base font-semibold text-gray-900 mb-4">Registrar Pagamento</h3>
                        <form method="POST" action="<?= URL ?>/admin/finance/bills/<?= (int)$b['id'] ?>/pay" class="space-y-4">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Valor pago</label>
                                <input type="text" name="valor_pago" value="<?= number_format((float)$b['valor'],2,',','.') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Data pagamento</label>
                                <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>"
                                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm">
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 px-4 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90">Confirmar</button>
                                <button type="button" onclick="this.closest('dialog').close()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50">Cancelar</button>
                            </div>
                        </form>
                    </dialog>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<!-- Modal Nova Conta -->
<dialog id="modalNovaConta" class="rounded-2xl shadow-2xl border border-gray-200 p-0 w-full max-w-lg backdrop:bg-black/50">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
        <h3 class="text-base font-bold text-gray-900">Nova Conta a Pagar</h3>
        <button onclick="document.getElementById('modalNovaConta').close()" class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-400 hover:bg-gray-100 transition-colors"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <form method="POST" action="<?= URL ?>/admin/finance/bills" class="px-6 py-4 space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Descrição <span class="text-red-500">*</span></label>
            <input type="text" name="descricao" required placeholder="Ex: Conta de energia — Julho/2026"
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Categoria</label>
                <select name="account_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                    <option value="">Sem categoria</option>
                    <?php $grupo = ''; foreach ($accounts as $a): ?>
                    <?php if ($a['grupo'] !== $grupo): $grupo = $a['grupo']; ?><optgroup label="<?= htmlspecialchars($grupo) ?>"><?php endif; ?>
                    <option value="<?= (int)$a['id'] ?>"><?= htmlspecialchars($a['nome']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Fornecedor</label>
                <input type="text" name="fornecedor" placeholder="Nome do fornecedor"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Valor <span class="text-red-500">*</span></label>
                <input type="text" name="valor" required placeholder="0,00"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Vencimento <span class="text-red-500">*</span></label>
                <input type="date" name="data_vencimento" required value="<?= date('Y-m-d') ?>"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
        </div>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Competência</label>
                <input type="month" name="data_competencia"
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
            </div>
            <div class="flex items-end pb-1">
                <label class="flex items-center gap-2 cursor-pointer">
                    <input type="checkbox" name="recorrente" value="1" class="rounded border-gray-300 text-green-600">
                    <span class="text-sm text-gray-700">Recorrente mensal</span>
                </label>
            </div>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Observações</label>
            <textarea name="observacoes" rows="2" placeholder="Informações adicionais..."
                      class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 resize-none"></textarea>
        </div>
        <div class="flex gap-3 pt-2 border-t border-gray-100">
            <button type="submit" class="flex-1 px-4 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">Salvar</button>
            <button type="button" onclick="document.getElementById('modalNovaConta').close()" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm text-gray-600 hover:bg-gray-50 transition-colors">Cancelar</button>
        </div>
    </form>
</dialog>
