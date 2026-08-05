<section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <?php
    require_once __DIR__ . '/../../../Services/DominioEscolaService.php';
    ?>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-6">
        <div>
            <h2 class="text-xl font-semibold text-slate-900">Escolas (tenants)</h2>
            <p class="text-sm text-slate-500 mt-1">Gerencie as escolas cadastradas na plataforma.</p>
        </div>
        <a href="<?= URL ?>/master/escolas/criar" class="inline-flex items-center justify-center px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-all duration-200 shrink-0">Nova escola</a>
    </div>
    <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
    <?php if (!empty($flash_msg)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
        <?= htmlspecialchars($flash_msg) ?>
    </div>
    <?php endif; ?>
    <?php if (empty($escolas)): ?>
    <p class="text-slate-600">Nenhuma escola cadastrada. <a href="<?= URL ?>/master/escolas/criar" class="text-blue-600 underline font-medium">Criar primeira escola</a>.</p>
    <?php else: ?>
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Slug</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Domínio</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">HTTPS</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Banco</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Ativo</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($escolas as $e): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700"><?= (int) $e['id'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900"><?= htmlspecialchars($e['nome']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600"><?= htmlspecialchars($e['slug'] ?? '') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><?= htmlspecialchars($e['dominio'] ?? '') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?php
                            $sslSt = (string) ($e['ssl_status'] ?? 'nao_verificado');
                            $sslClass = match ($sslSt) {
                                'ok' => 'bg-green-100 text-green-800',
                                'pendente' => 'bg-amber-100 text-amber-800',
                                'erro' => 'bg-red-100 text-red-800',
                                default => 'bg-slate-100 text-slate-600',
                            };
                            ?>
                            <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= $sslClass ?>" title="<?= htmlspecialchars(DominioEscolaService::rotuloSslStatus($sslSt)) ?>">
                                <?= htmlspecialchars(DominioEscolaService::rotuloSslStatus($sslSt)) ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?php if (!empty($e['tem_banco'])): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-slate-100 text-slate-700">Sim</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Não</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?php if (!empty($e['ativo'])): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <?php ob_start(); ?>
                            <a href="<?= URL ?>/master/escolas/<?= (int) $e['id'] ?>/detalhes" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <i class="fa-solid fa-circle-info text-gray-400 w-4 text-center"></i> Detalhes
                            </a>
                            <a href="<?= URL ?>/master/escolas/editar?id=<?= (int) $e['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </a>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'row-actions-escola-' . (int) $e['id']; ?>
                            <?php include __DIR__ . '/../../admin/_partials/row_actions_dropdown.php'; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php
        $pag = $pagination ?? [];
        $total = (int) ($pag['total'] ?? 0);
        $perPage = (int) ($pag['per_page'] ?? 10);
        $page = (int) ($pag['page'] ?? 1);
        $totalPages = (int) ($pag['total_pages'] ?? 1);
        $queryParams = array_merge($_GET ?? [], []);
        unset($queryParams['page']);
        $baseQuery = empty($queryParams) ? '' : ('?' . http_build_query($queryParams));
        $sep = $baseQuery === '' ? '?' : '&';
        $paginationRoute = URL . '/master/escolas';
        ?>
        <?php if ($total > 0): ?>
        <div class="px-6 py-4 border-t border-slate-200 flex flex-wrap items-center justify-between gap-2">
            <p class="text-sm text-slate-600">
                Exibindo <?= min(($page - 1) * $perPage + 1, $total) ?>–<?= min($page * $perPage, $total) ?> de <?= $total ?> registro(s)
            </p>
            <?php if ($totalPages > 1): ?>
            <div class="flex items-center gap-1">
                <?php if ($page > 1): ?>
                    <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $page - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200">Anterior</a>
                <?php endif; ?>
                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $page ? 'bg-blue-600 text-white' : 'text-slate-700 bg-slate-100 hover:bg-slate-200' ?>"><?= $i ?></a>
                <?php endfor; ?>
                <?php if ($page < $totalPages): ?>
                    <a href="<?= $paginationRoute . $baseQuery . $sep ?>page=<?= $page + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-slate-100 rounded-lg hover:bg-slate-200">Próxima</a>
                <?php endif; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</section>
