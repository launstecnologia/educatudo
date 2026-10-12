<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

$statusBadge = match($contract['status']) {
    'ativo'     => 'bg-green-100 text-green-800',
    'rascunho'  => 'bg-slate-100 text-slate-700',
    'cancelado' => 'bg-red-100 text-red-800',
    'encerrado' => 'bg-indigo-100 text-indigo-800',
    default     => 'bg-slate-100 text-slate-700',
};
?>

<!-- Cabeçalho -->
<div class="mb-6">
    <div class="flex items-start gap-4 flex-wrap">
        <a href="<?= URL ?>/admin/finance/contracts"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors flex-shrink-0"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Contrato #<?= (int)$contract['id'] ?></h2>
            <p class="text-sm text-gray-600"><?= $esc($contract['aluno_nome'] ?? '') ?> &middot; <?= $esc($contract['ano_letivo_nome'] ?? '') ?></p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <a href="<?= URL ?>/admin/finance/contracts"
               class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Contratos
            </a>
            <?php if ($contract['status'] === 'rascunho'): ?>
            <form method="POST" action="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>/activate" class="inline">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit"
                        class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Ativar Contrato
                </button>
            </form>
            <?php elseif ($contract['status'] === 'ativo'): ?>
            <form method="POST" action="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>/cancel" class="inline">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit"
                        onclick="return confirm('Cancelar contrato?')"
                        class="inline-flex items-center px-4 py-2 border border-red-200 rounded-lg text-sm font-medium text-red-600 bg-white hover:bg-red-50 transition-colors">
                    Cancelar Contrato
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
<!-- Coluna principal -->
<div class="lg:col-span-2 space-y-6">

<!-- Resumo financeiro -->
<div class="grid grid-cols-3 gap-4">
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 text-center">
        <p class="text-xs font-medium text-gray-500 mb-2">Valor Bruto</p>
        <p class="text-xl font-bold text-gray-700"><?= $brl($contract['valor_bruto']) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-6 text-center">
        <p class="text-xs font-medium text-gray-500 mb-2">Desconto</p>
        <p class="text-xl font-bold text-amber-600">- <?= $brl($contract['valor_desconto']) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-green-200 shadow-sm p-6 text-center bg-green-50">
        <p class="text-xs font-medium text-green-600 mb-2">Valor Líquido</p>
        <p class="text-xl font-bold text-green-700"><?= $brl($contract['valor_liquido']) ?></p>
    </div>
</div>

<!-- Parcelas -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Parcelas</h3>
        <div class="flex gap-3 text-xs">
            <span class="text-green-600 font-medium"><?= (int)($summary['pagas'] ?? 0) ?> pagas</span>
            <span class="text-amber-600 font-medium"><?= (int)($summary['pendentes'] ?? 0) ?> pendentes</span>
            <span class="text-red-600 font-medium"><?= (int)($summary['vencidas'] ?? 0) ?> vencidas</span>
        </div>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Vencimento</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach ($installments as $inst):
                $sc = match($inst['status']) {
                    'pago'        => 'bg-green-100 text-green-800',
                    'vencido'     => 'bg-red-100 text-red-800',
                    'cancelado'   => 'bg-slate-100 text-slate-700',
                    'renegociado' => 'bg-indigo-100 text-indigo-800',
                    default       => 'bg-amber-100 text-amber-800',
                };
                $enc = $inst['encargos'];
            ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= (int)$inst['num_parcela'] ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?= $esc($inst['descricao']) ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= date('d/m/Y', strtotime($inst['data_vencimento'])) ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-mono">
                    <?= $brl($inst['valor_cobrado']) ?>
                    <?php if ($enc['juros'] > 0 || $enc['multa'] > 0): ?>
                    <br><span class="text-xs text-red-500">+ <?= $brl($enc['juros'] + $enc['multa']) ?> encargos</span>
                    <?php endif; ?>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $sc ?>">
                        <?= $esc(ucfirst($inst['status'])) ?>
                    </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                    <div class="flex gap-2 justify-end">
                        <?php if ($inst['status'] !== 'pago' && $inst['status'] !== 'cancelado'): ?>
                        <a href="<?= URL ?>/admin/finance/installments/<?= (int)$inst['id'] ?>"
                           class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-xs font-medium hover:bg-blue-100 transition-colors">
                            Pagar
                        </a>
                        <?php endif; ?>
                        <a href="<?= URL ?>/admin/finance/installments/<?= (int)$inst['id'] ?>/boleto"
                           class="text-xs text-gray-500 hover:underline">Boleto</a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot class="bg-gray-50">
                <tr>
                    <td colspan="3" class="px-6 py-3 text-sm font-medium text-gray-600">Total cobrado</td>
                    <td class="px-6 py-3 text-right font-bold text-gray-900"><?= $brl($summary['total_cobrado'] ?? 0) ?></td>
                    <td colspan="2" class="px-6 py-3 text-right text-sm text-green-600 font-medium">Pago: <?= $brl($summary['total_pago'] ?? 0) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Descontos aplicados -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">
        <h3 class="text-sm font-semibold text-gray-900">Descontos aplicados</h3>
        <?php if ($contract['status'] !== 'cancelado'): ?>
        <button type="button"
                onclick="document.getElementById('modal_add_discount').classList.remove('hidden')"
                class="inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 font-medium transition-colors">
            <i class="fa-solid fa-plus"></i> Adicionar desconto
        </button>
        <?php endif; ?>
    </div>
    <?php if (empty($discounts)): ?>
    <div class="px-5 py-8 text-center">
        <i class="fa-solid fa-tag text-3xl text-gray-300 mb-3 block"></i>
        <p class="text-sm text-gray-500">Nenhum desconto aplicado.</p>
    </div>
    <?php else: ?>
    <div class="divide-y divide-gray-100">
    <?php foreach ($discounts as $d):
        $dBadge = match($d['status']) {
            'aprovado' => 'bg-green-100 text-green-800',
            'pendente' => 'bg-amber-100 text-amber-800',
            'rejeitado'=> 'bg-red-100 text-red-800',
            default    => 'bg-slate-100 text-slate-700',
        };
        $dVal = match($d['status']) {
            'aprovado' => 'text-green-700',
            'rejeitado'=> 'text-red-600',
            default    => 'text-amber-700',
        };
    ?>
    <div class="px-5 py-4 flex items-center justify-between gap-4">
        <div class="min-w-0">
            <p class="text-sm font-medium text-gray-900"><?= $esc($d['descricao']) ?></p>
            <p class="text-xs text-gray-500"><?= $esc($d['tipo']) ?> &middot; <?= $d['calculo'] === 'percentual' ? $esc($d['valor']) . '%' : 'R$ ' . number_format($d['valor'], 2, ',', '.') ?></p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <span class="text-sm font-semibold <?= $dVal ?>">- <?= $brl($d['valor_aplicado']) ?></span>
            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $dBadge ?>"><?= $esc($d['status']) ?></span>
            <?php if ($d['status'] === 'pendente'): ?>
            <form method="POST" action="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>/discounts/<?= (int)$d['id'] ?>/approve" class="inline">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit" class="text-xs text-green-600 hover:underline font-medium">Aprovar</button>
            </form>
            <form method="POST" action="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>/discounts/<?= (int)$d['id'] ?>/reject" class="inline">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit" class="text-xs text-red-500 hover:underline font-medium">Rejeitar</button>
            </form>
            <?php endif; ?>
            <form method="POST" action="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>/discounts/<?= (int)$d['id'] ?>/remove" class="inline">
                <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                <button type="submit"
                        onclick="return confirm('Remover desconto?')"
                        class="text-xs text-gray-400 hover:text-red-500 transition-colors">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </form>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

</div><!-- /col principal -->

<!-- Coluna lateral -->
<div class="space-y-4">
    <!-- Dados do contrato -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Dados do Contrato</h3>
        <dl class="space-y-3 text-sm">
            <div class="flex justify-between items-center">
                <dt class="text-gray-500">Status</dt>
                <dd>
                    <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $statusBadge ?>">
                        <?= $esc($contract['status']) ?>
                    </span>
                </dd>
            </div>
            <div class="flex justify-between"><dt class="text-gray-500">Plano</dt><dd class="font-medium text-gray-900"><?= $esc($contract['plano_pagamento']) ?></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Parcelas</dt><dd class="text-gray-900"><?= (int)$contract['num_parcelas'] ?>x</dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Vencimento</dt><dd class="text-gray-900">dia <?= (int)$contract['dia_vencimento'] ?></dd></div>
            <div class="flex justify-between"><dt class="text-gray-500">Período</dt><dd class="text-gray-900"><?= (int)$contract['mes_inicio'] ?>/<?= (int)$contract['mes_fim'] ?></dd></div>
            <div class="flex justify-between border-t border-gray-100 pt-3">
                <dt class="text-gray-500">Criado</dt>
                <dd class="text-gray-400 text-xs"><?= date('d/m/Y', strtotime($contract['created_at'])) ?></dd>
            </div>
        </dl>
    </div>

    <!-- Responsável financeiro -->
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Responsável Financeiro</h3>
        <div class="space-y-1 text-sm">
            <p class="font-medium text-gray-900"><?= $esc($contract['responsavel_nome']) ?></p>
            <?php if ($contract['responsavel_cpf']): ?>
            <p class="text-gray-500">CPF: <?= $esc($contract['responsavel_cpf']) ?></p>
            <?php endif; ?>
            <?php if ($contract['responsavel_email']): ?>
            <p class="text-gray-500"><?= $esc($contract['responsavel_email']) ?></p>
            <?php endif; ?>
            <?php if ($contract['responsavel_telefone']): ?>
            <p class="text-gray-500"><?= $esc($contract['responsavel_telefone']) ?></p>
            <a href="https://wa.me/<?= preg_replace('/\D/', '', $contract['responsavel_telefone']) ?>"
               target="_blank"
               class="inline-flex items-center gap-1 text-xs text-green-600 hover:underline mt-2">
                <i class="fa-brands fa-whatsapp"></i> WhatsApp
            </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Histórico de auditoria -->
    <?php if (!empty($audit)): ?>
    <div class="bg-white rounded-xl border border-gray-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-gray-900 mb-4">Histórico</h3>
        <div class="space-y-3">
        <?php foreach (array_slice($audit, 0, 8) as $a): ?>
        <div class="text-xs text-gray-500">
            <span class="font-medium text-gray-700"><?= $esc($a['acao']) ?></span>
            <?= $a['usuario_nome'] ? ' &middot; ' . $esc($a['usuario_nome']) : '' ?>
            <br><span class="text-gray-400"><?= date('d/m/Y H:i', strtotime($a['created_at'])) ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>

<!-- Modal adicionar desconto -->
<div id="modal_add_discount" class="hidden fixed inset-0 bg-black/40 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-base font-semibold text-gray-900">Adicionar Desconto Manual</h3>
            <button type="button"
                    onclick="document.getElementById('modal_add_discount').classList.add('hidden')"
                    class="text-gray-400 hover:text-gray-600 transition-colors">
                <i class="fa-solid fa-xmark text-lg"></i>
            </button>
        </div>
        <form method="POST" action="<?= URL ?>/admin/finance/contracts/<?= (int)$contract['id'] ?>/discounts/add" class="divide-y divide-gray-200">
            <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
            <div class="p-6 space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Regra existente</label>
                    <select name="discount_rule_id"
                            class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                        <option value="">— Desconto avulso (preencher abaixo) —</option>
                        <?php foreach ($discount_rules as $r): ?>
                        <option value="<?= (int)$r['id'] ?>"><?= $esc($r['nome']) ?> (<?= $esc($r['valor']) ?><?= $r['calculo'] === 'percentual' ? '%' : ' R$' ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <input type="text" name="descricao" placeholder="Ex: Bolsa parcial"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo</label>
                        <select name="tipo"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                            <option value="manual">Manual</option>
                            <option value="bolsa">Bolsa</option>
                            <option value="irmaos">Irmãos</option>
                            <option value="convenio">Convênio</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cálculo</label>
                        <select name="calculo"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                            <option value="percentual">Percentual (%)</option>
                            <option value="fixo">Valor fixo (R$)</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Valor</label>
                    <input type="text" name="valor" placeholder="0,00"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm font-mono">
                </div>
            </div>
            <div class="px-6 py-4 bg-gray-50 flex gap-3">
                <button type="submit"
                        class="flex-1 inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    Adicionar
                </button>
                <button type="button"
                        onclick="document.getElementById('modal_add_discount').classList.add('hidden')"
                        class="flex-1 inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                    Cancelar
                </button>
            </div>
        </form>
    </div>
</div>
