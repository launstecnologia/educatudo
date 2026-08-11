<section class="bg-white rounded-xl shadow border border-slate-200 p-6">
    <div class="flex justify-between items-center mb-4">
        <h2 class="text-xl font-semibold text-slate-800">Usuários Master</h2>
        <a href="<?= URL ?>/master/usuarios/criar" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Novo usuário</a>
    </div>
    <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
    <?php if (!empty($flash_msg)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
        <?= htmlspecialchars($flash_msg) ?>
    </div>
    <?php endif; ?>
    <?php if (empty($usuarios)): ?>
    <p class="text-slate-600">Nenhum usuário master cadastrado. <a href="<?= URL ?>/master/usuarios/criar" class="text-blue-600 underline font-medium">Criar primeiro usuário</a>.</p>
    <?php else: ?>
    <div class="bg-white rounded-xl border border-slate-200">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-slate-50">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">ID</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Nome</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">E-mail</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Ativo</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($usuarios as $u): ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700"><?= (int) $u['id'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900"><?= htmlspecialchars($u['nome'] ?? '') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600"><?= htmlspecialchars($u['email'] ?? '') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?php if (!empty($u['ativo'])): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800">Ativo</span>
                            <?php else: ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-red-100 text-red-800">Inativo</span>
                            <?php endif; ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                            <?php
                            $currentId = (int) ($_SESSION['master_user_id'] ?? 0);
                            $podeDesativar = $currentId !== (int) $u['id'] && !empty($u['ativo']);
                            ob_start();
                            ?>
                            <a href="<?= URL ?>/master/usuarios/editar?id=<?= (int) $u['id'] ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                            </a>
                            <?php if ($podeDesativar): ?>
                            <div class="border-t border-slate-100 my-1"></div>
                            <button type="button" onclick="if(confirm('Desativar este usuário?')){document.getElementById('form-desativar-<?= (int) $u['id'] ?>').submit();}" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                <i class="fa-solid fa-power-off text-red-400 w-4 text-center"></i> Desativar
                            </button>
                            <?php endif; ?>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'row-actions-usuario-' . (int) $u['id']; ?>
                            <?php include __DIR__ . '/../../admin/_partials/row_actions_dropdown.php'; ?>
                            <?php if ($podeDesativar): ?>
                            <form id="form-desativar-<?= (int) $u['id'] ?>" method="post" action="<?= URL ?>/master/usuarios/desativar" class="hidden">
                                <input type="hidden" name="id" value="<?= (int) $u['id'] ?>">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                            </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
</section>
