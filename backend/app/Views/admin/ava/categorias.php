<?php
$categorias = $categorias ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');

$page_header_back_url = URL . '/admin/ava';
$page_header_title = 'Categorias';
$page_header_subtitle = 'Organize os cursos do AVA por categoria.';
include __DIR__ . '/../_partials/page_header_form.php';
include __DIR__ . '/../_partials/flash_message.php';
?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Cadastro (único nesta tela) -->
    <div class="lg:col-span-1">
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Nova categoria</h3>
            <form method="post" action="<?= URL ?>/admin/ava/categorias" class="space-y-4">
                <input type="hidden" name="_token" value="<?= $csrf ?>">
                <div>
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
                    <input type="text" id="nome" name="nome" required placeholder="Ex.: Tecnologia"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <input type="text" id="descricao" name="descricao" placeholder="Opcional"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <button type="submit" class="w-full inline-flex justify-center items-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors shadow-sm">
                    <i class="fa-solid fa-plus mr-2"></i> Adicionar categoria
                </button>
            </form>
        </div>
    </div>

    <!-- Lista -->
    <div class="lg:col-span-2">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Categoria</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Descrição</th>
                            <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (empty($categorias)): ?>
                            <tr><td colspan="3" class="px-6 py-12 text-center text-gray-500"><i class="fa-solid fa-tags text-3xl text-gray-300 mb-3"></i><p>Nenhuma categoria cadastrada.</p></td></tr>
                        <?php else: foreach ($categorias as $cat): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 text-sm font-medium text-gray-900"><?= htmlspecialchars((string) $cat['nome']) ?></td>
                                <td class="px-6 py-4 text-sm text-gray-600"><?= htmlspecialchars((string) ($cat['descricao'] ?? '')) ?: '—' ?></td>
                                <td class="px-6 py-4 text-right whitespace-nowrap">
                                    <?php ob_start(); ?>
                                    <form method="post" action="<?= URL ?>/admin/ava/categorias/<?= (int) $cat['id'] ?>/excluir" onsubmit="return confirm('Remover categoria?');">
                                        <input type="hidden" name="_token" value="<?= $csrf ?>">
                                        <button type="submit" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fa-solid fa-trash-can text-red-400 w-4 text-center"></i> Excluir
                                        </button>
                                    </form>
                                    <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                                    <?php $row_actions_dropdown_id = 'row-actions-cat-' . (int) $cat['id']; ?>
                                    <?php include __DIR__ . '/../_partials/row_actions_dropdown.php'; ?>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
