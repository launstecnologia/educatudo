<?php
$alunos = $alunos ?? [];
$turmas = $turmas ?? [];
$escola_id = $escola_id ?? 0;
$total = $total ?? 0;
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
$filtro_nome = $filtro_nome ?? '';
$filtro_turma = $filtro_turma ?? 0;
$filtro_creditos_ordem = $filtro_creditos_ordem ?? '';
$creditos_disponiveis = $creditos_disponiveis ?? false;
$entrar_como_base = URL . '/master/entrar-como?escola_id=' . (int) $escola_id . '&tipo=aluno';
$base_url = URL . '/master/escolas/' . (int) $escola_id . '/alunos';
$has_filtros = $filtro_nome !== '' || $filtro_turma > 0 || $filtro_creditos_ordem !== '';
if (!class_exists('CreditosDecimalHelper')) {
    require_once __DIR__ . '/../../../../Core/CreditosDecimalHelper.php';
}
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-4 sm:p-6">
    <div class="mb-5">
        <h3 class="text-lg font-semibold text-slate-800">
            Alunos
            <span class="text-sm font-normal text-slate-500">(<?= (int) $total ?>)</span>
        </h3>
    </div>

    <form method="GET" action="<?= $base_url ?>" class="mb-5 space-y-3">
        <div>
            <label for="filtro-busca-alunos" class="block text-xs font-medium text-slate-600 mb-1">Busca</label>
            <input type="text" id="filtro-busca-alunos" name="nome" value="<?= htmlspecialchars($filtro_nome) ?>"
                   placeholder="Buscar por nome..."
                   class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <div class="min-w-0">
                <label for="filtro-turma-alunos" class="block text-xs font-medium text-slate-600 mb-1">Turma</label>
                <div class="relative">
                    <select id="filtro-turma-alunos" name="turma_id"
                            class="w-full appearance-none px-3 py-2.5 pr-10 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="0">Todas as turmas</option>
                        <?php foreach ($turmas as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $filtro_turma === (int) $t['id'] ? 'selected' : '' ?>><?= htmlspecialchars($t['nome']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="min-w-0">
                <label for="filtro-ordem-alunos" class="block text-xs font-medium text-slate-600 mb-1">Ordenação</label>
                <div class="relative">
                    <select id="filtro-ordem-alunos" name="creditos_ordem"
                            class="w-full appearance-none px-3 py-2.5 pr-10 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Ordenar por nome</option>
                        <?php if ($creditos_disponiveis): ?>
                        <option value="maior" <?= $filtro_creditos_ordem === 'maior' ? 'selected' : '' ?>>Maior saldo (créditos)</option>
                        <option value="menor" <?= $filtro_creditos_ordem === 'menor' ? 'selected' : '' ?>>Menor saldo (créditos)</option>
                        <?php else: ?>
                        <option value="" disabled>Créditos indisponíveis nesta escola</option>
                        <?php endif; ?>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="flex flex-wrap gap-2 sm:justify-end">
                <button type="submit" class="inline-flex flex-1 sm:flex-none items-center justify-center gap-2 px-4 py-2.5 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition-colors">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i> Filtrar
                </button>
                <?php if ($has_filtros): ?>
                <a href="<?= $base_url ?>" class="inline-flex flex-1 sm:flex-none items-center justify-center px-4 py-2.5 border border-slate-300 text-sm font-medium text-slate-600 rounded-lg hover:bg-slate-50 transition-colors">Limpar</a>
                <?php endif; ?>
            </div>
        </div>
    </form>

    <?php if (empty($alunos)): ?>
    <p class="text-slate-500 text-sm">Nenhum aluno encontrado.</p>
    <?php else: ?>

    <div class="hidden md:block">
        <table class="w-full divide-y divide-gray-200 table-fixed">
            <thead>
                <tr class="bg-slate-50">
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase w-[calc(100%-7rem)]">Nome</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-600 uppercase w-28">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($alunos as $a):
                    $id = (int) ($a['id'] ?? 0);
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-3 py-3 align-top">
                        <div class="font-medium text-sm text-slate-900 break-words"><?= htmlspecialchars($a['nome'] ?? '') ?></div>
                        <div class="text-xs text-slate-500 mt-0.5 break-all"><?= htmlspecialchars($a['email'] ?? '') ?></div>
                        <div class="flex flex-wrap items-center gap-1.5 mt-2 text-xs text-slate-500">
                            <?php if (!empty($a['ra'])): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">RA: <?= htmlspecialchars($a['ra']) ?></span>
                            <?php endif; ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full bg-blue-50 text-blue-700"><?= htmlspecialchars($a['turma_nome'] ?? 'Sem turma') ?></span>
                            <?php if ($creditos_disponiveis): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full bg-amber-50 text-amber-800">
                                <?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay(\CreditosDecimalHelper::fromSignedScalar($a['saldo_creditos'] ?? 0, 0.0))) ?> créd.
                            </span>
                            <?php endif; ?>
                        </div>
                    </td>
                    <td class="px-3 py-3 align-top text-right whitespace-nowrap">
                        <?php
                        ob_start();
                        ?>
                        <a href="<?= $entrar_como_base ?>&id=<?= $id ?>" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            <i class="fa-solid fa-right-to-bracket text-gray-400 w-4 text-center"></i> Entrar como
                        </a>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-aluno-' . $id;
                        include __DIR__ . '/../../../admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="md:hidden space-y-3">
        <?php foreach ($alunos as $a):
            $id = (int) ($a['id'] ?? 0);
        ?>
        <div class="border border-slate-200 rounded-lg p-3 bg-slate-50/50">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-sm text-slate-900 break-words"><?= htmlspecialchars($a['nome'] ?? '') ?></div>
                    <div class="text-xs text-slate-500 mt-0.5 break-all"><?= htmlspecialchars($a['email'] ?? '') ?></div>
                    <div class="flex flex-wrap items-center gap-1.5 mt-2 text-xs">
                        <?php if (!empty($a['ra'])): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full bg-slate-100 text-slate-700">RA: <?= htmlspecialchars($a['ra']) ?></span>
                        <?php endif; ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full bg-blue-50 text-blue-700"><?= htmlspecialchars($a['turma_nome'] ?? 'Sem turma') ?></span>
                        <?php if ($creditos_disponiveis): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full bg-amber-50 text-amber-800">
                            <?= htmlspecialchars(\CreditosDecimalHelper::formatDisplay(\CreditosDecimalHelper::fromSignedScalar($a['saldo_creditos'] ?? 0, 0.0))) ?> créd.
                        </span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="shrink-0">
                    <?php
                    ob_start();
                    ?>
                    <a href="<?= $entrar_como_base ?>&id=<?= $id ?>" target="_blank" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        <i class="fa-solid fa-right-to-bracket text-gray-400 w-4 text-center"></i> Entrar como
                    </a>
                    <?php
                    $row_actions_dropdown_items = ob_get_clean();
                    $row_actions_dropdown_id = 'row-actions-aluno-m-' . $id;
                    include __DIR__ . '/../../../admin/_partials/row_actions_dropdown.php';
                    ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <?php
    $query_params = [];
    if ($filtro_nome !== '') {
        $query_params['nome'] = $filtro_nome;
    }
    if ($filtro_turma > 0) {
        $query_params['turma_id'] = $filtro_turma;
    }
    if ($filtro_creditos_ordem !== '') {
        $query_params['creditos_ordem'] = $filtro_creditos_ordem;
    }
    ?>
    <div class="flex items-center justify-between mt-6 pt-4 border-t border-slate-200">
        <p class="text-sm text-slate-600">
            Página <?= (int) $page ?> de <?= (int) $total_pages ?> &middot; <?= (int) $total ?> alunos
        </p>
        <div class="flex items-center gap-1">
            <?php if ($page > 1): ?>
            <a href="<?= $base_url ?>?<?= http_build_query(array_merge($query_params, ['page' => $page - 1])) ?>"
               class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">&laquo; Anterior</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            if ($start > 1): ?>
            <a href="<?= $base_url ?>?<?= http_build_query(array_merge($query_params, ['page' => 1])) ?>"
               class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">1</a>
            <?php if ($start > 2): ?><span class="px-1 text-gray-400">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="<?= $base_url ?>?<?= http_build_query(array_merge($query_params, ['page' => $i])) ?>"
               class="px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'text-slate-700 bg-white border-slate-300 hover:bg-slate-50' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?><span class="px-1 text-gray-400">...</span><?php endif; ?>
            <a href="<?= $base_url ?>?<?= http_build_query(array_merge($query_params, ['page' => $total_pages])) ?>"
               class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"><?= $total_pages ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
            <a href="<?= $base_url ?>?<?= http_build_query(array_merge($query_params, ['page' => $page + 1])) ?>"
               class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">Próxima &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>
