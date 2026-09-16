<?php
$page_header_title = 'Tipos de Nota';
$page_header_subtitle = 'Cadastre os tipos e como cada um fecha a nota final. O boletim só lê essa nota.';
ob_start();
?>
<a href="<?= URL ?>/admin/provas/tipos-avaliacao/criar"
   class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo tipo
</a>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../_partials/page_header_list.php';
include __DIR__ . '/../../_partials/flash_message.php';
$temRegras = !empty($tem_regras);
$colspan = 4 + ($temRegras ? 1 : 0);
?>

<div class="bg-white rounded-xl shadow-lg">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Lista de Tipos</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Nome</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Descrição</th>
                    <?php if ($temRegras): ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Cálculo</th>
                    <?php endif; ?>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($tipos)): ?>
                    <tr>
                        <td colspan="<?= (int) $colspan ?>" class="px-6 py-12 text-center text-gray-500">Nenhum tipo de avaliação cadastrado.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tipos as $tipo): ?>
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $tipo['nome']) ?></td>
                            <td class="px-6 py-4 text-sm text-gray-600"><?= !empty($tipo['descricao']) ? htmlspecialchars((string) $tipo['descricao']) : '<span class="text-gray-400">Sem descrição</span>' ?></td>
                            <?php if ($temRegras): ?>
                            <td class="px-6 py-4 text-sm text-gray-700"><?= htmlspecialchars((string) ($tipo['rotulo_calculo'] ?? '—'), ENT_QUOTES, 'UTF-8') ?></td>
                            <?php endif; ?>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <?php if (!empty($tipo['ativo'])): ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                                <?php else: ?>
                                    <span class="inline-flex px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-700">Inativo</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                <?php ob_start(); ?>
                                    <a href="<?= URL ?>/admin/provas/tipos-avaliacao/<?= (int) $tipo['id'] ?>/editar" class="flex items-center gap-2 px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                                        <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                                    </a>
                                    <button type="button" onclick="excluirTipo(<?= (int) $tipo['id'] ?>, '<?= htmlspecialchars((string) $tipo['nome'], ENT_QUOTES) ?>')" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                    </button>
                                <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                <?php $row_actions_dropdown_id = 'row-actions-tipo-' . (int) $tipo['id']; ?>
                                <?php include __DIR__ . '/../../_partials/row_actions_dropdown.php'; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function excluirTipo(id, nome) {
    if (!confirm(`Deseja excluir o tipo "${nome}"?`)) return;
    fetch(`<?= URL ?>/admin/provas/tipos-avaliacao/${id}`, { method: 'DELETE' })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                location.reload();
                return;
            }
            alert(data.error || 'Erro ao excluir tipo de avaliação.');
        })
        .catch(() => alert('Erro ao excluir tipo de avaliação.'));
}
</script>
