<?php
/** @var array $charges @var array $turmas @var array $anos_letivos @var string $csrf_token */
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

$statusLabel = [
    'pendente'  => ['text' => 'Pendente',  'cls' => 'bg-amber-100 text-amber-700'],
    'pago'      => ['text' => 'Pago',      'cls' => 'bg-green-100 text-green-700'],
    'vencido'   => ['text' => 'Vencido',   'cls' => 'bg-red-100 text-red-700'],
    'cancelado' => ['text' => 'Cancelado', 'cls' => 'bg-gray-100 text-gray-500'],
];

$catLabel = [
    'mensalidade' => 'Mensalidade', 'matricula' => 'Matrícula', 'material' => 'Material',
    'uniforme' => 'Uniforme', 'passeio' => 'Passeio', 'ingresso' => 'Ingresso',
    'evento' => 'Evento', 'outros' => 'Outros',
];
?>

<!-- Header -->
<div class="mb-6 flex items-center justify-between gap-4">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900">Cobranças Avulsas</h2>
            <p class="text-sm text-gray-500">Passeios, uniformes, eventos e outras cobranças extras</p>
        </div>
    </div>
    <a href="<?= URL ?>/admin/finance/charges/batch"
       class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-xl text-sm font-semibold shadow-sm hover:opacity-90 transition-opacity">
        <i class="fa-solid fa-bolt mr-2"></i> Nova Cobrança em Lote
    </a>
</div>

<!-- Resumo cards -->
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
    <?php
    $totPendente  = array_sum(array_column(array_filter($charges, fn($c) => $c['status'] === 'pendente'), 'valor'));
    $totVencido   = array_sum(array_column(array_filter($charges, fn($c) => $c['status'] === 'vencido'),  'valor'));
    $totPago      = array_sum(array_column(array_filter($charges, fn($c) => $c['status'] === 'pago'),     'valor_pago'));
    $totCancelado = count(array_filter($charges, fn($c) => $c['status'] === 'cancelado'));
    ?>
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <p class="text-xs font-medium text-gray-500 uppercase mb-1">A Receber</p>
        <p class="text-xl font-bold text-amber-600"><?= $brl($totPendente) ?></p>
        <p class="text-xs text-gray-400 mt-0.5"><?= count(array_filter($charges, fn($c) => $c['status'] === 'pendente')) ?> cobranças</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <p class="text-xs font-medium text-gray-500 uppercase mb-1">Vencidas</p>
        <p class="text-xl font-bold text-red-600"><?= $brl($totVencido) ?></p>
        <p class="text-xs text-gray-400 mt-0.5"><?= count(array_filter($charges, fn($c) => $c['status'] === 'vencido')) ?> cobranças</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <p class="text-xs font-medium text-gray-500 uppercase mb-1">Recebido</p>
        <p class="text-xl font-bold text-green-600"><?= $brl($totPago) ?></p>
        <p class="text-xs text-gray-400 mt-0.5"><?= count(array_filter($charges, fn($c) => $c['status'] === 'pago')) ?> cobranças</p>
    </div>
    <div class="bg-white rounded-xl border border-gray-200 p-4 shadow-sm">
        <p class="text-xs font-medium text-gray-500 uppercase mb-1">Total Cobranças</p>
        <p class="text-xl font-bold text-gray-800"><?= count($charges) ?></p>
        <p class="text-xs text-gray-400 mt-0.5"><?= $totCancelado ?> canceladas</p>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Todos</option>
                <?php foreach ($statusLabel as $val => $info): ?>
                <option value="<?= $val ?>" <?= ($_GET['status'] ?? '') === $val ? 'selected' : '' ?>><?= $info['text'] ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Categoria</label>
            <select name="categoria" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Todas</option>
                <?php foreach ($catLabel as $val => $label): ?>
                <option value="<?= $val ?>" <?= ($_GET['categoria'] ?? '') === $val ? 'selected' : '' ?>><?= $label ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Turma</label>
            <select name="turma_id" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                <option value="">Todas</option>
                <?php foreach ($turmas as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= ($_GET['turma_id'] ?? '') == $t['id'] ? 'selected' : '' ?>><?= $esc($t['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Vencimento de</label>
            <input type="date" name="data_inicio" value="<?= $esc($_GET['data_inicio'] ?? '') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">até</label>
            <input type="date" name="data_fim" value="<?= $esc($_GET['data_fim'] ?? '') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
        </div>
        <button type="submit"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-filter mr-2 text-gray-500"></i> Filtrar
        </button>
        <a href="<?= URL ?>/admin/finance/charges"
           class="inline-flex items-center px-3 py-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
            Limpar
        </a>
    </form>
</div>

<!-- Lista -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <?php if (empty($charges)): ?>
    <div class="px-6 py-16 text-center">
        <i class="fa-solid fa-bolt text-4xl text-gray-200 mb-4 block"></i>
        <p class="text-gray-500 font-medium">Nenhuma cobrança encontrada.</p>
        <a href="<?= URL ?>/admin/finance/charges/batch"
           class="inline-flex items-center mt-4 px-4 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
            <i class="fa-solid fa-bolt mr-2"></i> Criar Cobrança em Lote
        </a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Aluno</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Descrição</th>
                    <th class="px-4 py-3 text-left text-xs font-semibold text-gray-500 uppercase">Categoria</th>
                    <th class="px-4 py-3 text-right text-xs font-semibold text-gray-500 uppercase">Valor</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Vencimento</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-center text-xs font-semibold text-gray-500 uppercase">Pago em</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                <?php foreach ($charges as $c): ?>
                <?php $st = $statusLabel[$c['status']] ?? $statusLabel['pendente']; ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-4 py-3">
                        <a href="<?= URL ?>/admin/finance/aluno/<?= (int)$c['aluno_id'] ?>/extrato"
                           class="text-sm font-medium text-gray-900 hover:text-green-700 transition-colors">
                            <?= $esc($c['aluno_nome'] ?? 'Aluno #' . $c['aluno_id']) ?>
                        </a>
                        <?php if (!empty($c['turma_nome'])): ?>
                        <span class="text-xs text-gray-400 block"><?= $esc($c['turma_nome']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-gray-700">
                        <?= $esc($c['descricao']) ?>
                        <?php if (!empty($c['observacoes'])): ?>
                        <span class="text-xs text-gray-400 block truncate max-w-xs"><?= $esc($c['observacoes']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-slate-100 text-slate-700">
                            <?= $esc($catLabel[$c['categoria']] ?? ucfirst($c['categoria'])) ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm font-semibold text-right text-gray-900"><?= $brl($c['valor']) ?></td>
                    <td class="px-4 py-3 text-sm text-center text-gray-600">
                        <?= !empty($c['data_vencimento']) ? date('d/m/Y', strtotime($c['data_vencimento'])) : '-' ?>
                    </td>
                    <td class="px-4 py-3 text-center">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-semibold <?= $st['cls'] ?>">
                            <?= $st['text'] ?>
                        </span>
                    </td>
                    <td class="px-4 py-3 text-sm text-center text-gray-500">
                        <?= !empty($c['data_pagamento']) ? date('d/m/Y', strtotime($c['data_pagamento'])) : '—' ?>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <div class="flex items-center justify-end gap-2">
                            <?php if (in_array($c['status'], ['pendente', 'vencido'])): ?>
                            <!-- Registrar pagamento -->
                            <button type="button"
                                    onclick="document.getElementById('pay-modal-<?= (int)$c['id'] ?>').showModal()"
                                    class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-green-300 text-xs font-medium text-green-700 hover:bg-green-50 transition-colors">
                                <i class="fa-solid fa-check mr-1"></i> Pago
                            </button>
                            <dialog id="pay-modal-<?= (int)$c['id'] ?>"
                                    class="rounded-xl shadow-xl border border-gray-200 p-6 w-full max-w-sm backdrop:bg-black/40">
                                <h3 class="text-base font-semibold text-gray-900 mb-1">Registrar Pagamento</h3>
                                <p class="text-sm text-gray-500 mb-4"><?= $esc($c['aluno_nome'] ?? '') ?> — <?= $esc($c['descricao']) ?></p>
                                <form method="POST" action="<?= URL ?>/admin/finance/charges/<?= (int)$c['id'] ?>/pay" class="space-y-4">
                                    <input type="hidden" name="_token" value="<?= $esc($csrf_token) ?>">
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Valor pago</label>
                                        <input type="text" name="valor_pago" value="<?= number_format((float)$c['valor'], 2, ',', '.') ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Data de pagamento</label>
                                        <input type="date" name="data_pagamento" value="<?= date('Y-m-d') ?>"
                                               class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                                    </div>
                                    <div>
                                        <label class="block text-sm font-medium text-gray-700 mb-1">Forma de pagamento</label>
                                        <select name="forma_pagamento" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500">
                                            <option value="dinheiro">Dinheiro</option>
                                            <option value="pix">PIX</option>
                                            <option value="cartao_debito">Cartão Débito</option>
                                            <option value="cartao_credito">Cartão Crédito</option>
                                            <option value="boleto">Boleto</option>
                                            <option value="transferencia">Transferência</option>
                                        </select>
                                    </div>
                                    <div class="flex gap-3 pt-2">
                                        <button type="submit"
                                                class="flex-1 px-4 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-opacity">
                                            Confirmar
                                        </button>
                                        <button type="button" onclick="this.closest('dialog').close()"
                                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 hover:bg-gray-50 transition-colors">
                                            Cancelar
                                        </button>
                                    </div>
                                </form>
                            </dialog>
                            <?php endif; ?>
                            <!-- Link extrato aluno -->
                            <a href="<?= URL ?>/admin/finance/aluno/<?= (int)$c['aluno_id'] ?>/extrato"
                               class="inline-flex items-center px-2.5 py-1.5 rounded-lg border border-gray-200 text-xs font-medium text-gray-600 hover:bg-gray-50 transition-colors"
                               title="Ver extrato do aluno">
                                <i class="fa-solid fa-receipt"></i>
                            </a>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
