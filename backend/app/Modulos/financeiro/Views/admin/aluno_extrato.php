<?php /** @var array $aluno @var array $extrato @var float $saldo @var ?string $data_inicio @var ?string $data_fim @var string $csrf_token */ ?>

<!-- Cabeçalho -->
<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL ?>/admin/finance"
           class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700 transition-colors flex-shrink-0"
           aria-label="Voltar">
            <i class="fa-solid fa-arrow-left"></i>
        </a>
        <div class="flex-1 min-w-0">
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Extrato &mdash; <?= htmlspecialchars($aluno['nome'], ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-sm text-gray-600"><?= htmlspecialchars($aluno['ra'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <div class="text-right flex-shrink-0">
            <p class="text-xs font-medium text-gray-500 mb-1">Saldo atual</p>
            <p class="text-2xl font-bold <?= $saldo >= 0 ? 'text-green-600' : 'text-red-600' ?>">
                R$ <?= number_format(abs($saldo), 2, ',', '.') ?>
                <span class="text-sm font-normal"><?= $saldo >= 0 ? 'credor' : 'devedor' ?></span>
            </p>
        </div>
    </div>
</div>

<!-- Ações rápidas -->
<div class="flex gap-3 mb-6">
    <a href="<?= URL ?>/admin/finance/aluno/<?= (int)$aluno['id'] ?>/charge"
       class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
        <i class="fa-solid fa-plus mr-2"></i> Cobrança Avulsa
    </a>
</div>

<!-- Filtro de período -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-6">
    <form method="GET" class="flex flex-wrap gap-4 items-end">
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">De</label>
            <input type="date" name="data_inicio" value="<?= htmlspecialchars($data_inicio ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-500 mb-1">Até</label>
            <input type="date" name="data_fim" value="<?= htmlspecialchars($data_fim ?? '', ENT_QUOTES, 'UTF-8') ?>"
                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <button type="submit"
                class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
            <i class="fa-solid fa-filter mr-2 text-gray-500"></i> Filtrar
        </button>
    </form>
</div>

<!-- Extrato -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <?php if (empty($extrato)): ?>
    <div class="px-6 py-12 text-center">
        <i class="fa-solid fa-file-invoice text-4xl text-gray-300 mb-4 block"></i>
        <p class="text-gray-500">Nenhum lançamento encontrado.</p>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Débito</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Crédito</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Saldo</th>
                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Auto</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($extrato as $entry): ?>
                <tr class="hover:bg-gray-50 <?= ($entry['referencia_tipo'] ?? '') === 'estorno' ? 'bg-amber-50 opacity-70' : '' ?>">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?= date('d/m/Y', strtotime($entry['data_lancamento'])) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-900">
                        <?= htmlspecialchars($entry['descricao'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if ($entry['observacoes']): ?>
                        <span class="text-xs text-gray-400 block"><?= htmlspecialchars($entry['observacoes'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-700">
                            <?= htmlspecialchars($entry['categoria'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-red-600">
                        <?= $entry['tipo'] === 'debito' ? 'R$ ' . number_format($entry['valor'], 2, ',', '.') : '' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-medium text-green-600">
                        <?= $entry['tipo'] === 'credito' ? 'R$ ' . number_format($entry['valor'], 2, ',', '.') : '' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold <?= $entry['saldo_acumulado'] >= 0 ? 'text-green-700' : 'text-red-700' ?>">
                        R$ <?= number_format($entry['saldo_acumulado'], 2, ',', '.') ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-400">
                        <?= $entry['gerado_auto'] ? '<i class="fa-solid fa-gear" title="Gerado automaticamente"></i>' : '' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right">
                        <?php if (($entry['referencia_tipo'] ?? '') !== 'estorno'): ?>
                        <button type="button"
                                onclick="document.getElementById('estorno-modal-<?= (int)$entry['id'] ?>').showModal()"
                                class="text-xs text-gray-400 hover:text-red-500 transition-colors">Estornar</button>
                        <!-- Modal estorno -->
                        <dialog id="estorno-modal-<?= (int)$entry['id'] ?>"
                                class="rounded-xl shadow-xl border border-gray-200 p-6 w-full max-w-sm backdrop:bg-black/40">
                            <h3 class="text-base font-semibold text-gray-900 mb-4">Estornar lançamento</h3>
                            <form method="POST" action="<?= URL ?>/admin/finance/ledger/<?= (int)$entry['id'] ?>/estorno" class="space-y-4">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-2">Motivo <span class="text-red-500">*</span></label>
                                    <input type="text" name="motivo" required
                                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 text-sm">
                                </div>
                                <div class="flex gap-3">
                                    <button type="submit"
                                            class="flex-1 px-4 py-2 bg-red-600 text-white rounded-lg text-sm font-semibold hover:bg-red-700 transition-colors">
                                        Estornar
                                    </button>
                                    <button type="button"
                                            onclick="this.closest('dialog').close()"
                                            class="flex-1 px-4 py-2 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                                        Cancelar
                                    </button>
                                </div>
                            </form>
                        </dialog>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
