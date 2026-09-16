<?php
$itens = is_array($itens ?? null) ? $itens : [];
$schemaPronto = !empty($schema_pronto);
$csrf_token = $csrf_token ?? '';

$page_header_title = 'Agrupamento de Disciplinas';
$page_header_subtitle = 'Defina média ou soma da área oficial. Os desdobramentos vêm do componente pai.';
ob_start();
?>
<a href="<?= URL ?>/admin/agrupamentos-componentes/novo"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Nova regra de nota
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_list.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (!$schemaPronto): ?>
<div class="mb-6 p-4 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-sm">
    Rode a migration <code class="text-sm">2026_09_02_agrupamentos_componentes.sql</code> no painel Master antes de cadastrar.
</div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Onde agrupa</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Desdobramentos</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Situação</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if ($itens === []): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <p>Nenhuma regra de nota cadastrada.</p>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($itens as $item): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 text-sm font-medium text-gray-900">
                        <?= htmlspecialchars((string) ($item['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($item['materia_rotulo_nome'])): ?>
                            <span class="block text-xs text-gray-500 font-normal">Área: <?= htmlspecialchars((string) $item['materia_rotulo_nome'], ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= (($item['modo'] ?? 'media') === 'soma') ? 'Soma' : 'Média' ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= (($item['aplicar_em'] ?? 'boletim') === 'ambos') ? 'Boletim e notas' : 'Só no boletim' ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= (int) ($item['itens_qtd'] ?? 0) ?></td>
                    <td class="px-6 py-4">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full <?= !empty($item['ativo']) ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= !empty($item['ativo']) ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <a href="<?= URL ?>/admin/agrupamentos-componentes/<?= (int) $item['id'] ?>/editar"
                           class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </a>
                        <div class="border-t border-gray-100 my-1"></div>
                        <form method="POST" action="<?= URL ?>/admin/agrupamentos-componentes/<?= (int) $item['id'] ?>/delete"
                              onsubmit="return confirm('Excluir este agrupamento? Eventos já gerados mantêm o snapshot.');">
                            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                            </button>
                        </form>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'agrup-actions-' . (int) $item['id'];
                        include __DIR__ . '/../../../../Views/admin/_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
