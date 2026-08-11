<?php
$usuarios = $usuarios ?? [];
$escola_id = $escola_id ?? 0;
$csrf_token = $csrf_token ?? '';
$total = $total ?? count($usuarios);
$page = $page ?? 1;
$total_pages = $total_pages ?? 1;
$filtro_busca = $filtro_busca ?? '';
$filtro_perfil = $filtro_perfil ?? '';
$filtro_status = $filtro_status ?? '';
$base_url = URL . '/master/escolas/' . (int) $escola_id . '/usuarios';
$entrar_como_base = URL . '/master/entrar-como?escola_id=' . (int) $escola_id . '&tipo=admin';

$labels_perfil = [
    'coordenador' => 'Coordenador',
    'diretor'    => 'Diretor',
    'dev'        => 'Desenvolvedor',
    'secretaria' => 'Secretaria',
];

$badge_perfil_class = static function (string $perfil): string {
    return match ($perfil) {
        'dev'      => 'bg-slate-100 text-slate-800',
        'diretor'  => 'bg-blue-100 text-blue-800',
        'secretaria' => 'bg-amber-100 text-amber-800',
        default    => 'bg-slate-100 text-slate-800',
    };
};

$formatar_data = static function (?string $data): string {
    if ($data === null || $data === '') {
        return '-';
    }
    $ts = strtotime($data);
    return $ts !== false ? date('d/m/Y', $ts) : '-';
};

$query_params = [];
if ($filtro_busca !== '') {
    $query_params['busca'] = $filtro_busca;
}
if ($filtro_perfil !== '') {
    $query_params['perfil'] = $filtro_perfil;
}
if ($filtro_status !== '') {
    $query_params['status'] = $filtro_status;
}

$has_filtros = $filtro_busca !== '' || $filtro_perfil !== '' || $filtro_status !== '';

$render_form_editar = static function (array $u) use ($escola_id, $csrf_token, $labels_perfil): void {
    $id = (int) ($u['id'] ?? 0);
    $perfil = (string) ($u['perfil_admin'] ?? 'coordenador');
    ?>
    <form method="post" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/usuarios/atualizar">
        <input type="hidden" name="usuario_id" value="<?= $id ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
                <input type="text" name="nome" required value="<?= htmlspecialchars($u['nome'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
                <input type="email" name="email" required value="<?= htmlspecialchars($u['email'] ?? '') ?>"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Senha</label>
                <input type="password" name="senha" placeholder="Deixe em branco para não alterar"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1">Perfil</label>
                <select name="perfil_admin" class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <?php foreach ($labels_perfil as $valor => $rotulo): ?>
                    <option value="<?= htmlspecialchars($valor) ?>" <?= $perfil === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="flex items-end">
                <label class="inline-flex items-center gap-2">
                    <input type="checkbox" name="ativo" value="1" <?= !empty($u['ativo']) ? 'checked' : '' ?>
                           class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                    <span class="text-sm text-slate-700">Ativo</span>
                </label>
            </div>
        </div>
        <div class="flex flex-col-reverse sm:flex-row gap-2">
            <button type="button" onclick="toggleEditUser(<?= $id ?>)" class="px-4 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50 text-sm">Cancelar</button>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium">Salvar</button>
        </div>
    </form>
    <?php
};

$render_dropdown_acoes = static function (array $u, string $suffix = '') use ($escola_id, $csrf_token, $entrar_como_base): void {
    $id = (int) ($u['id'] ?? 0);
    $ativo = !empty($u['ativo']);
    $form_id = 'form-toggle' . $suffix . '-' . $id;
    ob_start();
    ?>
    <a href="<?= $entrar_como_base ?>&id=<?= $id ?>" class="flex items-center gap-2 px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
        <i class="fa-solid fa-right-to-bracket text-gray-400 w-4 text-center"></i> Entrar como
    </a>
    <button type="button" onclick="toggleEditUser(<?= $id ?>)" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
        <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
    </button>
    <div class="border-t border-slate-100 my-1"></div>
    <?php if ($ativo): ?>
    <button type="button" onclick="if(confirm('Inativar este usuário?')){document.getElementById('<?= $form_id ?>').submit();}" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
        <i class="fa-solid fa-power-off text-red-400 w-4 text-center"></i> Inativar
    </button>
    <?php else: ?>
    <button type="button" onclick="if(confirm('Ativar este usuário?')){document.getElementById('<?= $form_id ?>').submit();}" class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">
        <i class="fa-solid fa-check text-green-500 w-4 text-center"></i> Ativar
    </button>
    <?php endif; ?>
    <?php
    $row_actions_dropdown_items = ob_get_clean();
    $row_actions_dropdown_id = 'row-actions-usuario' . $suffix . '-' . $id;
    include __DIR__ . '/../../../admin/_partials/row_actions_dropdown.php';
    ?>
    <form id="<?= $form_id ?>" method="post" action="<?= URL ?>/master/escolas/<?= (int) $escola_id ?>/usuarios/atualizar" class="hidden">
        <input type="hidden" name="usuario_id" value="<?= $id ?>">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="nome" value="<?= htmlspecialchars($u['nome'] ?? '') ?>">
        <input type="hidden" name="email" value="<?= htmlspecialchars($u['email'] ?? '') ?>">
        <input type="hidden" name="perfil_admin" value="<?= htmlspecialchars($u['perfil_admin'] ?? 'coordenador') ?>">
        <?php if (!$ativo): ?>
        <input type="hidden" name="ativo" value="1">
        <?php endif; ?>
    </form>
    <?php
};
?>

<div class="bg-white rounded-xl shadow border border-slate-200 p-4 sm:p-6">
    <div class="mb-5">
        <h3 class="text-lg font-semibold text-slate-800">
            Usuários Administradores
            <span class="text-sm font-normal text-slate-500">(<?= (int) $total ?>)</span>
        </h3>
    </div>

    <form method="GET" action="<?= $base_url ?>" class="mb-5 space-y-3">
        <div>
            <label for="filtro-busca-usuarios" class="block text-xs font-medium text-slate-600 mb-1">Busca</label>
            <input type="text" id="filtro-busca-usuarios" name="busca" value="<?= htmlspecialchars($filtro_busca) ?>"
                   placeholder="Buscar por nome ou e-mail..."
                   class="w-full px-3 py-2.5 border border-slate-300 rounded-lg text-sm focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 items-end">
            <div class="min-w-0">
                <label for="filtro-perfil-usuarios" class="block text-xs font-medium text-slate-600 mb-1">Nível</label>
                <div class="relative">
                    <select id="filtro-perfil-usuarios" name="perfil"
                            class="w-full appearance-none px-3 py-2.5 pr-10 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos os níveis</option>
                        <?php foreach ($labels_perfil as $valor => $rotulo): ?>
                        <option value="<?= htmlspecialchars($valor) ?>" <?= $filtro_perfil === $valor ? 'selected' : '' ?>><?= htmlspecialchars($rotulo) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-3 text-slate-400">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
                    </span>
                </div>
            </div>
            <div class="min-w-0">
                <label for="filtro-status-usuarios" class="block text-xs font-medium text-slate-600 mb-1">Status</label>
                <div class="relative">
                    <select id="filtro-status-usuarios" name="status"
                            class="w-full appearance-none px-3 py-2.5 pr-10 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="ativo" <?= $filtro_status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
                        <option value="inativo" <?= $filtro_status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
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

    <?php if (empty($usuarios)): ?>
    <p class="text-slate-500 text-sm">Nenhum administrador encontrado.</p>
    <?php else: ?>

    <!-- Desktop: tabela compacta -->
    <div class="hidden md:block">
        <table class="w-full divide-y divide-gray-200 table-fixed">
            <thead>
                <tr class="bg-slate-50">
                    <th class="px-3 py-2 text-left text-xs font-medium text-slate-600 uppercase w-[calc(100%-7rem)]">Nome</th>
                    <th class="px-3 py-2 text-right text-xs font-medium text-slate-600 uppercase w-28">Ações</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                <?php foreach ($usuarios as $u):
                    $id = (int) ($u['id'] ?? 0);
                    $perfil = (string) ($u['perfil_admin'] ?? 'coordenador');
                    $ativo = !empty($u['ativo']);
                ?>
                <tr class="hover:bg-slate-50" id="row-user-<?= $id ?>">
                    <td class="px-3 py-3 align-top">
                        <div class="font-medium text-sm text-slate-900 break-words"><?= htmlspecialchars($u['nome'] ?? '') ?></div>
                        <div class="text-xs text-slate-500 mt-0.5 break-all"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                        <div class="flex flex-wrap items-center gap-1.5 mt-2">
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $badge_perfil_class($perfil) ?>">
                                <?= htmlspecialchars($labels_perfil[$perfil] ?? $perfil) ?>
                            </span>
                            <?php if ($ativo): ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                            <?php else: ?>
                            <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inativo</span>
                            <?php endif; ?>
                            <span class="text-xs text-gray-400"><?= htmlspecialchars($formatar_data($u['created_at'] ?? null)) ?></span>
                        </div>
                    </td>
                    <td class="px-3 py-3 align-top text-right whitespace-nowrap">
                        <?php $render_dropdown_acoes($u); ?>
                    </td>
                </tr>
                <tr id="edit-user-<?= $id ?>" class="hidden">
                    <td colspan="2" class="px-3 py-4 bg-slate-50">
                        <?php $render_form_editar($u); ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <!-- Mobile: cards -->
    <div class="md:hidden space-y-3">
        <?php foreach ($usuarios as $u):
            $id = (int) ($u['id'] ?? 0);
            $perfil = (string) ($u['perfil_admin'] ?? 'coordenador');
            $ativo = !empty($u['ativo']);
        ?>
        <div class="border border-slate-200 rounded-lg p-3 bg-slate-50/50" id="card-user-<?= $id ?>">
            <div class="flex items-start justify-between gap-2">
                <div class="min-w-0 flex-1">
                    <div class="font-medium text-sm text-slate-900 break-words"><?= htmlspecialchars($u['nome'] ?? '') ?></div>
                    <div class="text-xs text-slate-500 mt-0.5 break-all"><?= htmlspecialchars($u['email'] ?? '') ?></div>
                    <div class="flex flex-wrap items-center gap-1.5 mt-2">
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium <?= $badge_perfil_class($perfil) ?>">
                            <?= htmlspecialchars($labels_perfil[$perfil] ?? $perfil) ?>
                        </span>
                        <?php if ($ativo): ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-800">Ativo</span>
                        <?php else: ?>
                        <span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium bg-red-100 text-red-800">Inativo</span>
                        <?php endif; ?>
                    </div>
                    <div class="text-xs text-gray-400 mt-1.5"><?= htmlspecialchars($formatar_data($u['created_at'] ?? null)) ?></div>
                </div>
                <div class="shrink-0">
                    <?php $render_dropdown_acoes($u, '-mobile'); ?>
                </div>
            </div>
            <div id="edit-user-mobile-<?= $id ?>" class="hidden mt-3 pt-3 border-t border-slate-200">
                <?php $render_form_editar($u); ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <?php if ($total_pages > 1): ?>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-5 pt-4 border-t border-slate-200">
        <p class="text-sm text-slate-600">
            Página <?= (int) $page ?> de <?= (int) $total_pages ?> &middot; <?= (int) $total ?> usuários
        </p>
        <div class="flex flex-wrap items-center gap-1">
            <?php if ($page > 1): ?>
            <a href="<?= $base_url ?>?<?= htmlspecialchars(http_build_query(array_merge($query_params, ['page' => $page - 1]))) ?>"
               class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">&laquo; Anterior</a>
            <?php endif; ?>

            <?php
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);
            if ($start > 1): ?>
            <a href="<?= $base_url ?>?<?= htmlspecialchars(http_build_query(array_merge($query_params, ['page' => 1]))) ?>"
               class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">1</a>
            <?php if ($start > 2): ?><span class="px-1 text-gray-400">...</span><?php endif; ?>
            <?php endif; ?>

            <?php for ($i = $start; $i <= $end; $i++): ?>
            <a href="<?= $base_url ?>?<?= htmlspecialchars(http_build_query(array_merge($query_params, ['page' => $i]))) ?>"
               class="px-3 py-1.5 text-sm font-medium rounded-lg border transition-colors <?= $i === $page ? 'bg-blue-600 text-white border-blue-600' : 'text-slate-700 bg-white border-slate-300 hover:bg-slate-50' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($end < $total_pages): ?>
            <?php if ($end < $total_pages - 1): ?><span class="px-1 text-gray-400">...</span><?php endif; ?>
            <a href="<?= $base_url ?>?<?= htmlspecialchars(http_build_query(array_merge($query_params, ['page' => $total_pages]))) ?>"
               class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50"><?= (int) $total_pages ?></a>
            <?php endif; ?>

            <?php if ($page < $total_pages): ?>
            <a href="<?= $base_url ?>?<?= htmlspecialchars(http_build_query(array_merge($query_params, ['page' => $page + 1]))) ?>"
               class="px-3 py-1.5 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 transition-colors">Próxima &raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php endif; ?>
</div>

<script>
function toggleEditUser(id) {
    var row = document.getElementById('edit-user-' + id);
    if (row) {
        row.classList.toggle('hidden');
    }
    var mobile = document.getElementById('edit-user-mobile-' + id);
    if (mobile) {
        mobile.classList.toggle('hidden');
    }
}
</script>
