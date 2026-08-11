<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$brl = fn($v) => 'R$ ' . number_format((float)$v, 2, ',', '.');

$statusBadge = function($s) {
    return match($s) {
        'ativo'     => '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>',
        'rascunho'  => '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-700">Rascunho</span>',
        'cancelado' => '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Cancelado</span>',
        'encerrado' => '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600">Encerrado</span>',
        default     => '<span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-500">' . htmlspecialchars($s) . '</span>',
    };
};

$filtrosAtivos = array_filter([$filters['q'], $filters['status'], $filters['ano_letivo_id']]);
?>

<!-- Cabeçalho -->
<div class="mb-6">
    <div class="flex justify-between items-center gap-4">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Contratos Financeiros</h2>
            <p class="text-gray-600 text-sm">Gerencie os contratos de mensalidade e cobrança dos alunos.</p>
        </div>
        <div class="flex items-center gap-3 flex-shrink-0">
            <a href="<?= URL ?>/admin/finance/contracts/create"
               class="inline-flex items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                <i class="fa-solid fa-plus mr-2"></i>
                Novo Contrato
            </a>
        </div>
    </div>
</div>

<!-- Filtros inline -->
<form method="GET" class="bg-white rounded-xl border border-gray-200 shadow-sm px-4 py-3 mb-6">
    <div class="flex flex-wrap gap-3 items-end">
        <div class="flex-1 min-w-48">
            <label class="block text-xs font-medium text-gray-500 mb-1">Buscar aluno</label>
            <input type="text" name="q" value="<?= $esc($filters['q']) ?>" placeholder="Nome ou RA..."
                   class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
        </div>
        <div class="w-44">
            <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
            <select name="status" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Todos os status</option>
                <?php foreach (['rascunho'=>'Rascunho','ativo'=>'Ativo','cancelado'=>'Cancelado','encerrado'=>'Encerrado'] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $filters['status'] === $v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="w-40">
            <label class="block text-xs font-medium text-gray-500 mb-1">Ano Letivo</label>
            <select name="ano_letivo_id" class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                <option value="">Todos os anos</option>
                <?php foreach ($anos_letivos as $al): ?>
                <option value="<?= (int)$al['id'] ?>" <?= $filters['ano_letivo_id'] == $al['id'] ? 'selected' : '' ?>><?= $esc($al['ano']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="flex gap-2">
            <button type="submit"
                    class="inline-flex items-center px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                <i class="fa-solid fa-magnifying-glass mr-2"></i>Filtrar
            </button>
            <?php if (!empty($filtrosAtivos)): ?>
            <a href="<?= URL ?>/admin/finance/contracts"
               class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-600 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </a>
            <?php endif; ?>
        </div>
    </div>
</form>

<!-- Tabela -->
<div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">#</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Aluno</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ano Letivo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Responsável</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Valor Líquido</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Criado</th>
                    <th class="px-6 py-3"></th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($contracts)): ?>
                <tr>
                    <td colspan="8" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-file-contract text-4xl text-gray-300 mb-4 block"></i>
                        <p class="font-medium">Nenhum contrato encontrado</p>
                        <?php if (!empty($filtrosAtivos)): ?>
                        <a href="<?= URL ?>/admin/finance/contracts" class="text-blue-600 text-sm mt-1 inline-block hover:underline">Limpar filtros</a>
                        <?php else: ?>
                        <a href="<?= URL ?>/admin/finance/contracts/create"
                           class="mt-3 inline-flex items-center px-4 py-2 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i> Criar primeiro contrato
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: foreach ($contracts as $c): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-400"><?= (int)$c['id'] ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-medium text-slate-600">
                                    <?= strtoupper(substr($c['aluno_nome'] ?? 'A', 0, 1)) ?>
                                </span>
                            </div>
                            <div class="ml-3">
                                <div class="text-sm font-medium text-gray-900"><?= $esc($c['aluno_nome'] ?? '') ?></div>
                                <div class="text-xs text-gray-400"><?= $esc($c['aluno_ra'] ?? '') ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= $esc($c['ano_letivo_nome'] ?? '') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600"><?= $esc($c['responsavel_nome'] ?? '') ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-right font-semibold text-gray-900"><?= $brl($c['valor_liquido']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap"><?= $statusBadge($c['status']) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-xs text-gray-400"><?= date('d/m/Y', strtotime($c['created_at'])) ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <a href="<?= URL ?>/admin/finance/contracts/<?= (int)$c['id'] ?>"
                           class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg border border-blue-200 bg-blue-50 text-blue-700 text-sm font-medium hover:bg-blue-100 hover:border-blue-300 transition-colors">
                            <i class="fa-solid fa-circle-info text-blue-600"></i>
                            Detalhes
                        </a>
                    </td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Paginação -->
    <?php if ($total > $limit): ?>
    <?php
        $totalPages = (int)ceil($total / $limit);
        $queryParams = array_merge($filters, []);
        unset($queryParams['page']);
        $baseQuery = empty(array_filter($queryParams)) ? '' : ('?' . http_build_query($queryParams));
        $sep = $baseQuery === '' ? '?' : '&';
    ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($page - 1) * $limit + 1, $total) ?>–<?= min($page * $limit, $total) ?> de <?= $total ?> contrato(s)
        </p>
        <?php if ($totalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
            <a href="<?= URL ?>/admin/finance/contracts<?= $baseQuery . $sep ?>page=<?= $page - 1 ?>"
               class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
            <a href="<?= URL ?>/admin/finance/contracts<?= $baseQuery . $sep ?>page=<?= $i ?>"
               class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>">
                <?= $i ?>
            </a>
            <?php endfor; ?>
            <?php if ($page < $totalPages): ?>
            <a href="<?= URL ?>/admin/finance/contracts<?= $baseQuery . $sep ?>page=<?= $page + 1 ?>"
               class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
