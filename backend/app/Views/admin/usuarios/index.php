<?php
$filters = $filters ?? ['tipo' => '', 'status' => '', 'busca' => ''];
$tipoLabels = ['aee' => 'AEE (Educação Especial)'];

$filtrosAtivosCount = 0;
foreach ($filters as $filterValue) {
    if ($filterValue !== '') {
        $filtrosAtivosCount++;
    }
}

$page_header_title = 'Gerenciar Usuários';
$page_header_subtitle = 'Gerencie usuários do sistema com controle de permissões';
ob_start();
?>
<button type="button" onclick="openFilterDrawer()"
        class="relative inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-filter mr-2 text-gray-500"></i>
    Filtros
    <?php if ($filtrosAtivosCount > 0): ?>
    <span class="ml-2 inline-flex items-center justify-center min-w-[1.25rem] h-5 px-1.5 rounded-full bg-blue-600 text-white text-xs font-semibold"><?= $filtrosAtivosCount ?></span>
    <?php endif; ?>
</button>
<a href="<?= URL ?>/admin/permissoes-perfis"
   class="inline-flex items-center px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
    <i class="fa-solid fa-user-shield mr-2 text-gray-500"></i>
    Perfis de Permissão
</a>
<button type="button" onclick="openUserDrawer()"
        class="btn-primary-custom inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
    <i class="fa-solid fa-plus mr-2"></i>
    Novo Usuário
</button>
<?php
$page_header_actions = ob_get_clean();
include __DIR__ . '/../_partials/page_header_list.php';
?>

<div class="bg-white rounded-xl shadow-sm border border-gray-200">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Usuário</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Tipo</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Criado por</th>
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Data</th>
                    <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Ações</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($usuarios)): ?>
                <tr>
                    <td colspan="6" class="px-6 py-12 text-center text-gray-500">
                        <i class="fa-solid fa-users text-4xl text-gray-300 mb-4"></i>
                        <p>Nenhum usuário encontrado</p>
                        <?php if ($filtrosAtivosCount > 0): ?>
                        <button type="button" onclick="clearFilters()" class="mt-3 text-sm text-blue-600 hover:text-blue-800">Limpar filtros</button>
                        <?php else: ?>
                        <button type="button" onclick="openUserDrawer()" class="btn-primary-custom mt-4 inline-flex items-center px-4 py-2.5 rounded-lg text-sm font-semibold transition-colors shadow-sm hover:opacity-90">
                            <i class="fa-solid fa-plus mr-2"></i> Novo Usuário
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php else: ?>
                <?php foreach ($usuarios as $usuario): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 whitespace-nowrap">
                        <div class="flex items-center">
                            <div class="flex-shrink-0 h-10 w-10">
                                <?php if (!empty($usuario['avatar_url'])): ?>
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden relative">
                                        <img class="h-10 w-10 rounded-full object-cover absolute inset-0" width="40" height="40" style="width:40px;height:40px;object-fit:cover;border-radius:50%" src="<?= htmlspecialchars($usuario['avatar_url']) ?>" alt="<?= htmlspecialchars($usuario['nome']) ?>" onerror="this.classList.add('hidden'); this.nextElementSibling?.classList.remove('hidden');">
                                        <span class="hidden text-sm font-medium text-gray-700"><?= strtoupper(substr($usuario['nome'], 0, 2)) ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="h-10 w-10 rounded-full bg-gray-300 flex items-center justify-center">
                                        <span class="text-sm font-medium text-gray-700">
                                            <?= strtoupper(substr($usuario['nome'], 0, 2)) ?>
                                        </span>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="ml-4">
                                <div class="text-sm font-medium text-gray-900"><?= htmlspecialchars($usuario['nome']) ?></div>
                                <div class="text-sm text-gray-500"><?= htmlspecialchars($usuario['email']) ?></div>
                            </div>
                        </div>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                            <?= $usuario['perfil_admin'] === 'dev' ? 'bg-purple-100 text-purple-800' :
                               ($usuario['perfil_admin'] === 'diretor' ? 'bg-blue-100 text-blue-800' :
                               ($usuario['perfil_admin'] === 'financeiro' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800')) ?>">
                            <?= htmlspecialchars($tipoLabels[$usuario['perfil_admin']] ?? ucfirst($usuario['perfil_admin'])) ?>
                        </span>
                        <?php if (!empty($usuario['perfil_permissao_nome'])): ?>
                            <div class="text-xs text-gray-500 mt-1">Perfil: <?= htmlspecialchars($usuario['perfil_permissao_nome']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full
                            <?= $usuario['ativo'] ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800' ?>">
                            <?= $usuario['ativo'] ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= $usuario['criado_por_nome'] ? htmlspecialchars($usuario['criado_por_nome']) : 'Sistema' ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                        <?= date('d/m/Y H:i', strtotime($usuario['created_at'])) ?>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-right">
                        <?php ob_start(); ?>
                        <button type="button" onclick="openUserDrawer(<?= (int) $usuario['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-pen text-gray-400 w-4 text-center"></i> Editar
                        </button>
                        <button type="button" onclick="changePassword(<?= (int) $usuario['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-key text-gray-400 w-4 text-center"></i> Alterar Senha
                        </button>
                        <button type="button" onclick="uploadAvatar(<?= (int) $usuario['id'] ?>)"
                                class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50">
                            <i class="fa-solid fa-camera text-gray-400 w-4 text-center"></i> Alterar Avatar
                        </button>
                        <?php
                        $row_actions_dropdown_items = ob_get_clean();
                        $row_actions_dropdown_id = 'row-actions-usuario-' . (int) $usuario['id'];
                        include __DIR__ . '/../_partials/row_actions_dropdown.php';
                        ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <?php
    $pag = $pagination ?? [];
    $pagTotal = (int) ($pag['total'] ?? 0);
    $pagPerPage = (int) ($pag['per_page'] ?? 10);
    $pagPage = (int) ($pag['page'] ?? 1);
    $pagTotalPages = (int) ($pag['total_pages'] ?? 1);
    $pagQueryParams = $_GET ?? [];
    unset($pagQueryParams['page']);
    $pagBaseQuery = empty($pagQueryParams) ? '' : ('?' . http_build_query($pagQueryParams));
    $pagSep = $pagBaseQuery === '' ? '?' : '&';
    ?>
    <?php if ($pagTotal > 0): ?>
    <div class="px-6 py-4 border-t border-gray-200 flex flex-wrap items-center justify-between gap-2">
        <p class="text-sm text-gray-600">
            Exibindo <?= min(($pagPage - 1) * $pagPerPage + 1, $pagTotal) ?>–<?= min($pagPage * $pagPerPage, $pagTotal) ?> de <?= $pagTotal ?> usuário(s)
        </p>
        <?php if ($pagTotalPages > 1): ?>
        <div class="flex items-center gap-1">
            <?php if ($pagPage > 1): ?>
                <a href="<?= URL ?>/admin/usuarios<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage - 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Anterior</a>
            <?php endif; ?>
            <?php for ($i = max(1, $pagPage - 2); $i <= min($pagTotalPages, $pagPage + 2); $i++): ?>
                <a href="<?= URL ?>/admin/usuarios<?= $pagBaseQuery . $pagSep ?>page=<?= $i ?>" class="px-3 py-1.5 text-sm font-medium rounded-lg <?= $i === $pagPage ? 'bg-primary text-white' : 'text-gray-700 bg-gray-100 hover:bg-gray-200' ?>"><?= $i ?></a>
            <?php endfor; ?>
            <?php if ($pagPage < $pagTotalPages): ?>
                <a href="<?= URL ?>/admin/usuarios<?= $pagBaseQuery . $pagSep ?>page=<?= $pagPage + 1 ?>" class="px-3 py-1.5 text-sm font-medium text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Próxima</a>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Filtro em drawer lateral -->
<div id="filterDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeFilterDrawer()"></div>
<aside id="filterDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-md bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Filtrar usuários</h3>
        <button type="button" onclick="closeFilterDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="GET" action="<?= URL ?>/admin/usuarios" class="flex flex-col flex-1 overflow-hidden">
        <div class="flex-1 overflow-y-auto px-6 py-5 space-y-4">
            <div>
                <label for="filtro_tipo" class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de Usuário</label>
                <div class="relative">
                    <select id="filtro_tipo" name="tipo"
                            class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos os tipos</option>
                        <?php foreach ($allowed_types as $tipo): ?>
                            <option value="<?= htmlspecialchars($tipo) ?>" <?= $filters['tipo'] === $tipo ? 'selected' : '' ?>><?= htmlspecialchars($tipoLabels[$tipo] ?? ucfirst($tipo)) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
            <div>
                <label for="filtro_status" class="block text-sm font-medium text-gray-700 mb-1.5">Status</label>
                <div class="relative">
                    <select id="filtro_status" name="status"
                            class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Todos</option>
                        <option value="1" <?= $filters['status'] === '1' ? 'selected' : '' ?>>Ativo</option>
                        <option value="0" <?= $filters['status'] === '0' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                    <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </div>
            </div>
            <div>
                <label for="filtro_busca" class="block text-sm font-medium text-gray-700 mb-1.5">Buscar</label>
                <input type="text" id="filtro_busca" name="busca" value="<?= htmlspecialchars($filters['busca']) ?>" placeholder="Nome ou email..."
                       class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
        </div>
        <div class="px-6 py-4 border-t border-gray-200 flex gap-3 bg-gray-50">
            <button type="button" onclick="clearFilters()"
                    class="flex-1 px-4 py-2.5 border border-gray-300 rounded-lg text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 transition-colors">
                Limpar
            </button>
            <button type="submit"
                    class="flex-1 px-4 py-2.5 bg-blue-600 text-white rounded-lg text-sm font-semibold hover:bg-blue-700 transition-colors">
                Aplicar filtros
            </button>
        </div>
    </form>
</aside>

<!-- Criar/Editar usuário em drawer lateral -->
<div id="userDrawerBackdrop" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="closeUserDrawer()"></div>
<aside id="userDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-3xl bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="userDrawerTitle" class="text-xl font-bold text-gray-900">Novo Usuário</h2>
        <button type="button" onclick="closeUserDrawer()" class="text-gray-400 hover:text-gray-600 p-1">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>

    <form id="user-form" class="flex flex-col flex-1 overflow-hidden" data-mode="create">
        <input type="hidden" id="csrf_token" name="_token" value="<?= htmlspecialchars((string) ($csrf_token ?? $_SESSION['csrf_token'] ?? '')) ?>">
        <input type="hidden" id="user_id" value="">

        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">

            <section id="user-avatar-section" class="hidden">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Avatar</h3>
                <div class="flex items-center gap-6">
                    <div id="user-avatar-preview" class="h-16 w-16 rounded-full bg-gray-300 flex items-center justify-center overflow-hidden flex-shrink-0"></div>
                    <div>
                        <button type="button" id="btn-user-avatar" class="btn-secondary-custom px-4 py-2 rounded-lg text-sm hover:opacity-90 transition-colors">
                            <i class="fa-solid fa-camera mr-2"></i> Alterar Avatar
                        </button>
                        <p class="text-xs text-gray-500 mt-2">Formatos: JPG, PNG, GIF (máx. 2MB)</p>
                    </div>
                </div>
            </section>

            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Dados do Usuário</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="nome" class="block text-sm font-medium text-gray-700 mb-1">Nome Completo *</label>
                        <input type="text" id="nome" name="nome" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Digite o nome completo">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email *</label>
                        <input type="email" id="email" name="email" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="usuario@escola.com">
                    </div>
                    <div>
                        <label for="tipo" class="block text-sm font-medium text-gray-700 mb-1">Tipo de Usuário *</label>
                        <div class="relative">
                            <select id="tipo" name="tipo" required
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="">Selecione o tipo</option>
                                <?php foreach ($allowed_types as $tipo): ?>
                                    <option value="<?= htmlspecialchars($tipo) ?>"><?= htmlspecialchars($tipoLabels[$tipo] ?? ucfirst($tipo)) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            <?php if (($user['perfil_admin'] ?? '') === 'dev'): ?>
                                Você pode criar usuários do tipo Dev, Diretor, Coordenador, Financeiro e Secretaria
                            <?php elseif (($user['perfil_admin'] ?? '') === 'diretor'): ?>
                                Você pode criar usuários do tipo Diretor, Coordenador, Financeiro e Secretaria
                            <?php endif; ?>
                        </p>
                    </div>
                    <div>
                        <label for="perfil_permissao_id" class="block text-sm font-medium text-gray-700 mb-1">Perfil de Permissão</label>
                        <div class="relative">
                            <select id="perfil_permissao_id" name="perfil_permissao_id"
                                    class="select-reset w-full px-3 py-2 pr-10 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="">Usar permissões padrão do tipo</option>
                                <?php foreach ($permission_profiles as $perfil): ?>
                                    <?php $perfilAtivo = (int) ($perfil['ativo'] ?? 0) === 1; ?>
                                    <option value="<?= (int) $perfil['id'] ?>" <?= $perfilAtivo ? '' : 'hidden data-inactive="1"' ?>>
                                        <?= htmlspecialchars($perfil['nome']) ?> (base: <?= htmlspecialchars(ucfirst((string) ($perfil['tipo_base'] ?? ''))) ?>)<?= $perfilAtivo ? '' : ' — inativo' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <svg class="pointer-events-none absolute right-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                            </svg>
                        </div>
                        <p class="text-xs text-gray-500 mt-1">
                            Não encontrou o perfil? <a href="<?= URL ?>/admin/permissoes-perfis" class="text-blue-600 hover:underline">Cadastrar perfil de permissão</a>.
                        </p>
                    </div>
                </div>
            </section>

            <section id="user-senha-section">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Senha</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="senha" class="block text-sm font-medium text-gray-700 mb-1">Senha *</label>
                        <input type="password" id="senha" name="senha"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Mínimo 6 caracteres">
                    </div>
                    <div>
                        <label for="confirmar_senha" class="block text-sm font-medium text-gray-700 mb-1">Confirmar Senha *</label>
                        <input type="password" id="confirmar_senha" name="confirmar_senha"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                               placeholder="Digite a senha novamente">
                    </div>
                </div>
            </section>

            <section>
                <div class="flex items-start justify-between gap-4 border-b border-gray-200 pb-2 mb-4">
                    <h3 class="text-lg font-semibold text-gray-900">Permissões de Acesso</h3>
                    <button type="button" id="btn-carregar-perfil" class="px-3 py-1.5 text-xs rounded border border-gray-300 hover:bg-gray-100 flex-shrink-0">
                        Carregar permissões do tipo
                    </button>
                </div>
                <p class="text-xs text-gray-600 -mt-2 mb-3">Selecione os módulos e as ações permitidas para este usuário.</p>
                <div class="overflow-auto border border-gray-200 rounded-lg bg-white">
                    <table class="min-w-full text-sm">
                        <thead class="bg-gray-100">
                        <tr>
                            <th class="text-left px-3 py-2">Módulo</th>
                            <?php foreach ($permissions_action_labels as $actionKey => $actionLabel): ?>
                                <th class="text-center px-3 py-2"><?= htmlspecialchars($actionLabel) ?></th>
                            <?php endforeach; ?>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($permissions_sections as $sectionKey => $section): ?>
                            <tr class="border-t bg-slate-50">
                                <td class="px-3 py-2 text-slate-900 font-semibold" colspan="<?= 1 + count($permissions_action_labels) ?>">
                                    <label class="inline-flex items-center gap-2">
                                        <input type="checkbox" class="section-toggle rounded border-gray-300 text-green-600" data-section-key="<?= htmlspecialchars($sectionKey) ?>">
                                        <span><?= htmlspecialchars($section['label']) ?></span>
                                    </label>
                                </td>
                            </tr>
                            <?php foreach ($section['items'] as $moduleKey): ?>
                                <?php if (!isset($permissions_catalog[$moduleKey])) { continue; } ?>
                                <?php $module = $permissions_catalog[$moduleKey]; ?>
                                <tr class="border-t" data-section-key="<?= htmlspecialchars($sectionKey) ?>">
                                    <td class="px-3 py-2 text-gray-800 pl-6">• <?= htmlspecialchars($module['label']) ?></td>
                                    <?php foreach ($permissions_action_labels as $actionKey => $actionLabel): ?>
                                        <td class="px-3 py-2 text-center">
                                            <input type="checkbox"
                                                   class="rounded border-gray-300 text-green-600"
                                                   name="permissions[<?= htmlspecialchars($moduleKey) ?>][<?= htmlspecialchars($actionKey) ?>]"
                                                   value="1">
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>

            <section id="user-status-section" class="hidden">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Status</h3>
                <label class="flex items-center">
                    <input type="checkbox" id="ativo" name="ativo" value="1"
                           class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50">
                    <span class="ml-2 text-sm text-gray-700">Usuário ativo</span>
                </label>
            </section>

            <section id="user-trocar-senha-section" class="hidden">
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Alterar Senha</h3>
                <p class="text-sm text-gray-600 mb-3">Define uma nova senha para este usuário.</p>
                <button type="button" id="btn-trocar-senha" class="btn-secondary-custom px-4 py-2 rounded-lg text-sm hover:opacity-90 transition-colors">
                    <i class="fa-solid fa-key mr-2"></i> Alterar Senha
                </button>
            </section>

            <section id="user-system-info-section" class="hidden border-t border-gray-200 pt-6">
                <h4 class="text-sm font-medium text-gray-900 mb-3">Informações do Sistema</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <span class="text-gray-500">Criado em:</span>
                        <span id="user-created-at" class="text-gray-900">-</span>
                    </div>
                    <div>
                        <span class="text-gray-500">Última atualização:</span>
                        <span id="user-updated-at" class="text-gray-900">-</span>
                    </div>
                </div>
            </section>
        </div>

        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="closeUserDrawer()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="btn-primary-custom px-6 py-2.5 rounded-lg font-semibold hover:opacity-90 transition-colors shadow-sm">
                <span id="user-form-submit-label">Criar Usuário</span>
            </button>
        </div>
    </form>
</aside>

<script>
const URL_BASE = <?= json_encode(URL) ?>;
const csrfToken = <?= json_encode((string) ($csrf_token ?? $_SESSION['csrf_token'] ?? '')) ?>;

function resolveCsrfToken() {
    if (typeof csrfToken === 'string' && csrfToken !== '') {
        return csrfToken;
    }
    const fromInput = document.getElementById('csrf_token')?.value
        || document.querySelector('#user-form input[name="_token"]')?.value
        || '';
    if (fromInput) {
        return fromInput;
    }
    if (typeof getCsrfToken === 'function') {
        return getCsrfToken() || '';
    }
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
}
const permissionsDefaultsByType = <?= json_encode($permissions_defaults_by_type, JSON_UNESCAPED_UNICODE) ?>;
const permissionProfiles = <?= json_encode($permission_profiles, JSON_UNESCAPED_UNICODE) ?>;
const permissionCheckboxes = Array.from(document.querySelectorAll('input[name^="permissions["]'));
const sectionToggles = Array.from(document.querySelectorAll('.section-toggle'));

function applyPermissionsMatrix(matrix) {
    matrix = matrix || {};
    permissionCheckboxes.forEach((checkbox) => {
        const moduleMatch = checkbox.name.match(/^permissions\[([^\]]+)\]\[([^\]]+)\]$/);
        if (!moduleMatch) return;
        const moduleKey = moduleMatch[1];
        const actionKey = moduleMatch[2];
        checkbox.checked = !!(matrix[moduleKey] && matrix[moduleKey][actionKey]);
    });
    refreshSectionToggles();
}

function fillPermissionsByType(tipo) {
    applyPermissionsMatrix(permissionsDefaultsByType[tipo] || {});
}

function fillPermissionsByProfileId(profileId) {
    const profile = permissionProfiles.find((item) => String(item.id) === String(profileId));
    if (!profile) return false;
    let matrix = {};
    try {
        matrix = profile.permissoes_json ? JSON.parse(profile.permissoes_json) : {};
    } catch (error) {
        matrix = {};
    }
    applyPermissionsMatrix(matrix);
    return true;
}

function refreshSectionToggles() {
    sectionToggles.forEach((toggle) => {
        const sectionKey = toggle.dataset.sectionKey;
        const sectionCheckboxes = Array.from(document.querySelectorAll(`tr[data-section-key="${sectionKey}"] input[type="checkbox"]`));
        if (sectionCheckboxes.length === 0) {
            toggle.checked = false;
            toggle.indeterminate = false;
            return;
        }
        const checkedCount = sectionCheckboxes.filter((c) => c.checked).length;
        toggle.checked = checkedCount === sectionCheckboxes.length;
        toggle.indeterminate = checkedCount > 0 && checkedCount < sectionCheckboxes.length;
    });
}

sectionToggles.forEach((toggle) => {
    toggle.addEventListener('change', function () {
        const sectionKey = this.dataset.sectionKey;
        const sectionCheckboxes = Array.from(document.querySelectorAll(`tr[data-section-key="${sectionKey}"] input[type="checkbox"]`));
        sectionCheckboxes.forEach((cb) => { cb.checked = this.checked; });
        refreshSectionToggles();
    });
});

permissionCheckboxes.forEach((checkbox) => checkbox.addEventListener('change', refreshSectionToggles));

document.getElementById('tipo').addEventListener('change', function () {
    const profileId = document.getElementById('perfil_permissao_id').value;
    if (profileId && fillPermissionsByProfileId(profileId)) return;
    if (this.value) fillPermissionsByType(this.value);
});
document.getElementById('perfil_permissao_id').addEventListener('change', function () {
    if (this.value) {
        fillPermissionsByProfileId(this.value);
        return;
    }
    const tipo = document.getElementById('tipo').value;
    if (tipo) fillPermissionsByType(tipo);
});
document.getElementById('btn-carregar-perfil').addEventListener('click', function () {
    const profileId = document.getElementById('perfil_permissao_id').value;
    if (profileId && fillPermissionsByProfileId(profileId)) return;
    const tipo = document.getElementById('tipo').value;
    if (!tipo) {
        alert('Selecione primeiro o tipo de usuário ou perfil de permissão.');
        return;
    }
    fillPermissionsByType(tipo);
});

document.getElementById('confirmar_senha').addEventListener('input', function () {
    const senha = document.getElementById('senha').value;
    if (this.value && senha !== this.value) {
        this.setCustomValidity('As senhas não coincidem');
        this.classList.add('border-red-500');
    } else {
        this.setCustomValidity('');
        this.classList.remove('border-red-500');
    }
});

// ---- Filtro (drawer) ----
function openFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('filterDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeFilterDrawer() {
    document.getElementById('filterDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('filterDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}
function clearFilters() {
    window.location.href = URL_BASE + '/admin/usuarios';
}

// ---- Criar/Editar usuário (drawer) ----
function renderUserAvatarPreview(usuario) {
    const el = document.getElementById('user-avatar-preview');
    el.textContent = '';
    if (usuario.avatar_url) {
        const img = document.createElement('img');
        img.src = usuario.avatar_url;
        img.alt = '';
        img.className = 'h-16 w-16 object-cover';
        img.onerror = function () { img.remove(); };
        el.appendChild(img);
    } else {
        const span = document.createElement('span');
        span.className = 'text-lg font-medium text-gray-700';
        span.textContent = (usuario.nome || '').slice(0, 2).toUpperCase();
        el.appendChild(span);
    }
}

function formatDateTimeBR(value) {
    if (!value) return '-';
    const d = new Date(String(value).replace(' ', 'T'));
    if (isNaN(d.getTime())) return value;
    const pad = (n) => String(n).padStart(2, '0');
    return `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function showUserDrawer() {
    document.getElementById('userDrawerBackdrop').classList.remove('hidden');
    const drawer = document.getElementById('userDrawer');
    drawer.classList.remove('translate-x-full');
    drawer.setAttribute('aria-hidden', 'false');
    document.body.style.overflow = 'hidden';
}
function closeUserDrawer() {
    document.getElementById('userDrawerBackdrop').classList.add('hidden');
    const drawer = document.getElementById('userDrawer');
    drawer.classList.add('translate-x-full');
    drawer.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
}

function openUserDrawer(id) {
    const form = document.getElementById('user-form');
    form.reset();
    document.getElementById('user_id').value = '';
    document.getElementById('confirmar_senha').classList.remove('border-red-500');

    const avatarSection = document.getElementById('user-avatar-section');
    const statusSection = document.getElementById('user-status-section');
    const senhaSection = document.getElementById('user-senha-section');
    const trocarSenhaSection = document.getElementById('user-trocar-senha-section');
    const systemInfoSection = document.getElementById('user-system-info-section');
    const senhaInput = document.getElementById('senha');
    const confirmarSenhaInput = document.getElementById('confirmar_senha');
    const selectPerfil = document.getElementById('perfil_permissao_id');

    if (!id) {
        form.dataset.mode = 'create';
        document.getElementById('userDrawerTitle').textContent = 'Novo Usuário';
        document.getElementById('user-form-submit-label').textContent = 'Criar Usuário';
        avatarSection.classList.add('hidden');
        statusSection.classList.add('hidden');
        trocarSenhaSection.classList.add('hidden');
        systemInfoSection.classList.add('hidden');
        senhaSection.classList.remove('hidden');
        senhaInput.required = true;
        confirmarSenhaInput.required = true;
        selectPerfil.querySelectorAll('option[data-inactive="1"]').forEach((opt) => { opt.hidden = true; });
        applyPermissionsMatrix({});
        showUserDrawer();
        return;
    }

    form.dataset.mode = 'edit';
    document.getElementById('userDrawerTitle').textContent = 'Editar Usuário';
    document.getElementById('user-form-submit-label').textContent = 'Salvar Alterações';
    senhaSection.classList.add('hidden');
    senhaInput.required = false;
    confirmarSenhaInput.required = false;
    avatarSection.classList.remove('hidden');
    statusSection.classList.remove('hidden');
    trocarSenhaSection.classList.remove('hidden');
    systemInfoSection.classList.remove('hidden');

    showUserDrawer();

    fetch(URL_BASE + '/admin/usuarios/' + id + '/dados')
        .then((response) => response.json())
        .then((data) => {
            if (!data.success) {
                alert('Erro: ' + (data.error || 'Não foi possível carregar o usuário'));
                closeUserDrawer();
                return;
            }
            const usuario = data.usuario;
            document.getElementById('user_id').value = usuario.id;
            document.getElementById('nome').value = usuario.nome;
            document.getElementById('email').value = usuario.email;
            document.getElementById('tipo').value = usuario.tipo;

            const perfilId = usuario.perfil_permissao_id ? String(usuario.perfil_permissao_id) : '';
            if (perfilId) {
                const opt = selectPerfil.querySelector('option[value="' + CSS.escape(perfilId) + '"]');
                if (opt && opt.hidden) opt.hidden = false;
            }
            selectPerfil.value = perfilId;

            document.getElementById('ativo').checked = !!usuario.ativo;
            renderUserAvatarPreview(usuario);
            document.getElementById('user-created-at').textContent = formatDateTimeBR(usuario.created_at);
            document.getElementById('user-updated-at').textContent = formatDateTimeBR(usuario.updated_at);
            document.getElementById('btn-user-avatar').onclick = function () { uploadAvatar(usuario.id); };
            document.getElementById('btn-trocar-senha').onclick = function () { changePassword(usuario.id); };

            applyPermissionsMatrix(data.selected_permissions || {});
        })
        .catch(() => {
            alert('Erro de conexão ao carregar usuário.');
            closeUserDrawer();
        });
}

document.getElementById('user-form').addEventListener('submit', async function (e) {
    e.preventDefault();

    const mode = this.dataset.mode;
    const id = document.getElementById('user_id').value;
    const formData = new FormData(this);
    const submitBtn = this.querySelector('button[type="submit"]');
    const submitLabel = document.getElementById('user-form-submit-label');
    const originalText = submitLabel.textContent;

    if (mode === 'create') {
        const senha = formData.get('senha');
        const confirmarSenha = formData.get('confirmar_senha');
        if (!senha || senha.length < 6) {
            alert('A senha deve ter pelo menos 6 caracteres!');
            return;
        }
        if (senha !== confirmarSenha) {
            alert('As senhas não coincidem!');
            return;
        }
    }

    submitBtn.disabled = true;
    submitLabel.textContent = mode === 'create' ? 'Criando...' : 'Salvando...';

    const url = mode === 'create' ? (URL_BASE + '/admin/usuarios') : (URL_BASE + '/admin/usuarios/' + id);

    try {
        const response = await fetch(url, { method: 'POST', body: formData });
        const result = await response.json();
        if (result.success) {
            window.location.reload();
        } else {
            alert('Erro: ' + result.error);
        }
    } catch (error) {
        alert('Erro de conexão. Tente novamente.');
    } finally {
        submitBtn.disabled = false;
        submitLabel.textContent = originalText;
    }
});

function changePassword(id) {
    const motivo = prompt('Motivo da alteração de senha (opcional):');
    if (motivo === null) return;

    const novaSenha = prompt('Nova senha:');
    if (!novaSenha) return;

    const confirmarSenha = prompt('Confirmar nova senha:');
    if (novaSenha !== confirmarSenha) {
        alert('Senhas não coincidem!');
        return;
    }

    const token = resolveCsrfToken();
    const formData = new FormData();
    formData.append('_token', token);
    formData.append('nova_senha', novaSenha);
    formData.append('confirmar_senha', confirmarSenha);
    formData.append('motivo', motivo);

    fetch(URL_BASE + '/admin/usuarios/' + id + '/senha', {
        method: 'POST',
        body: formData,
        headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' }
    })
        .then((response) => response.json())
        .then((data) => {
            if (data.success) {
                alert('Senha alterada com sucesso!');
            } else {
                alert('Erro: ' + data.error);
            }
        })
        .catch(() => alert('Erro de conexão'));
}

function uploadAvatar(id) {
    const input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/gif';

    input.onchange = function (e) {
        const file = e.target.files[0];
        if (!file) return;

        if (file.size > 2 * 1024 * 1024) {
            alert('Arquivo muito grande. Máximo 2MB.');
            return;
        }

        const token = resolveCsrfToken();
        if (!token) {
            alert('Sessão expirada. Recarregue a página e tente novamente.');
            return;
        }

        const formData = new FormData();
        formData.append('_token', token);
        formData.append('avatar', file);

        fetch(URL_BASE + '/admin/usuarios/' + id + '/avatar', {
            method: 'POST',
            body: formData,
            headers: { 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' }
        })
            .then((response) => response.json())
            .then((data) => {
                if (data.success) {
                    alert('Avatar enviado com sucesso!');
                    location.reload();
                } else {
                    alert('Erro: ' + (data.error || 'Falha no upload'));
                }
            })
            .catch(() => alert('Erro de conexão'));
    };

    input.click();
}

document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') {
        closeFilterDrawer();
        closeUserDrawer();
    }
});
</script>
