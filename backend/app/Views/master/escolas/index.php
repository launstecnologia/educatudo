<section class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
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
    <?php
    $jobsClonagem = is_array($jobs_clonagem ?? null) ? $jobs_clonagem : [];
    $temJobsPendentes = !empty($tem_jobs_clonagem_pendentes);
    $rotuloStatusJob = static function (string $status): array {
        return match ($status) {
            'pending' => ['Aguardando', 'bg-slate-100 text-slate-700'],
            'processing' => ['Clonando', 'bg-amber-100 text-amber-800'],
            'done' => ['Concluído', 'bg-green-100 text-green-800'],
            'failed' => ['Falhou', 'bg-red-100 text-red-800'],
            default => [$status, 'bg-slate-100 text-slate-600'],
        };
    };
    ?>
    <?php if (!empty($jobsClonagem)): ?>
    <div id="painel-jobs-clonagem" class="mb-6 rounded-xl border border-slate-200 bg-slate-50 p-4">
        <div class="flex items-center justify-between gap-3 mb-3">
            <h3 class="text-sm font-semibold text-slate-800">Clonagens recentes</h3>
            <?php if ($temJobsPendentes): ?>
            <span class="text-xs text-amber-700">Atualiza automaticamente enquanto houver job na fila.</span>
            <?php endif; ?>
        </div>
        <ul id="lista-jobs-clonagem" class="space-y-2">
            <?php foreach ($jobsClonagem as $job): ?>
            <?php
                $st = (string) ($job['status'] ?? '');
                [$label, $cls] = $rotuloStatusJob($st);
                $res = is_array($job['resultado_decoded'] ?? null) ? $job['resultado_decoded'] : [];
                $erro = trim((string) ($job['mensagem_erro'] ?? ''));
                $msg = $st === 'failed'
                    ? ($erro !== '' ? $erro : (string) ($res['mensagem'] ?? ''))
                    : (string) ($res['mensagem'] ?? $erro);
            ?>
            <li class="flex flex-col gap-1 text-sm">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <span class="text-slate-700">
                        <?= htmlspecialchars((string) ($job['origem_nome'] ?? 'Escola')) ?>
                        →
                        <?= htmlspecialchars((string) ($job['destino_nome'] ?? 'nova escola')) ?>
                    </span>
                    <span class="inline-flex px-2 py-0.5 text-xs font-semibold rounded-full <?= $cls ?>"><?= htmlspecialchars($label) ?></span>
                </div>
                <?php if ($msg !== ''): ?>
                <p class="text-xs <?= $st === 'failed' ? 'text-red-700' : 'text-slate-500' ?> break-words"><?= htmlspecialchars($msg) ?></p>
                <?php endif; ?>
                <?php if ($st === 'failed'): ?>
                <form method="POST" action="<?= URL ?>/master/escolas/clonar-retry" class="mt-1">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="job_id" value="<?= (int) ($job['id'] ?? 0) ?>">
                    <button type="submit" class="text-xs font-medium text-blue-600 hover:text-blue-800 hover:underline">
                        Tentar de novo
                    </button>
                </form>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
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
                        <th class="px-6 py-3 text-left text-xs font-medium text-slate-500 uppercase tracking-wider">Ativo</th>
                        <th class="px-6 py-3 text-right text-xs font-medium text-slate-500 uppercase tracking-wider">Ações</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php foreach ($escolas as $e): ?>
                    <?php $emManutencao = (string) ($e['maintenance_mode'] ?? '0') === '1'; ?>
                    <tr class="hover:bg-slate-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-700"><?= (int) $e['id'] ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-slate-900"><?= htmlspecialchars($e['nome']) ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-600"><?= htmlspecialchars($e['slug'] ?? '') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-500"><?= htmlspecialchars($e['dominio'] ?? '') ?></td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <?php if ($emManutencao): ?>
                                <span class="inline-flex px-2 py-1 text-xs font-semibold rounded-full bg-amber-100 text-amber-800">Manutenção</span>
                            <?php elseif (!empty($e['ativo'])): ?>
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
                            <?php if (!empty($e['tem_banco'])): ?>
                            <button type="button"
                                    class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm text-slate-700 hover:bg-slate-50 js-clonar-escola"
                                    data-id="<?= (int) $e['id'] ?>"
                                    data-nome="<?= htmlspecialchars($e['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                                    data-host="<?= htmlspecialchars((string) ($e['db_host'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                    data-porta="<?= (int) ($e['db_porta'] ?? 3306) ?>"
                                    data-usuario="<?= htmlspecialchars((string) ($e['db_usuario'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                <i class="fa-solid fa-clone text-blue-500 w-4 text-center"></i> Clonar escola
                            </button>
                            <?php else: ?>
                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 cursor-not-allowed" title="Configure o banco da escola antes de clonar.">
                                <i class="fa-solid fa-clone text-slate-300 w-4 text-center"></i> Clonar indisponível
                            </span>
                            <?php endif; ?>
                            <?php if (empty($e['tem_banco'])): ?>
                            <span class="flex items-center gap-2 px-4 py-2 text-sm text-slate-400 cursor-not-allowed" title="Configure o banco da escola antes de alterar a manutenção.">
                                <i class="fa-solid fa-screwdriver-wrench text-slate-300 w-4 text-center"></i> Manutenção indisponível
                            </span>
                            <?php else: ?>
                            <form method="POST" action="<?= URL ?>/master/escolas/manutencao">
                                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                                <input type="hidden" name="id" value="<?= (int) $e['id'] ?>">
                                <input type="hidden" name="enabled" value="<?= $emManutencao ? '0' : '1' ?>">
                                <button type="submit"
                                        class="flex items-center gap-2 w-full text-left px-4 py-2 text-sm <?= $emManutencao ? 'text-emerald-700 hover:bg-emerald-50' : 'text-amber-700 hover:bg-amber-50' ?>"
                                        onclick="return confirm('<?= $emManutencao ? 'Retirar esta escola da manutenção?' : 'Colocar esta escola em manutenção?' ?>')">
                                    <i class="fa-solid <?= $emManutencao ? 'fa-circle-play text-emerald-500' : 'fa-screwdriver-wrench text-amber-500' ?> w-4 text-center"></i>
                                    <?= $emManutencao ? 'Sair da manutenção' : 'Entrar em manutenção' ?>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php $row_actions_dropdown_items = ob_get_clean(); ?>
                            <?php $row_actions_dropdown_id = 'row-actions-escola-' . (int) $e['id']; ?>
                            <?php $row_actions_dropdown_menu_class = 'w-56'; ?>
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

<?php
$domCfg = is_array($dominio_config ?? null) ? $dominio_config : [];
$tenantBaseDomain = (string) ($domCfg['tenant_base_domain'] ?? 'localhost');
$criarBancoDisponivel = !empty($criar_banco_disponivel);
?>
<div id="clonarDrawerBackdrop" class="fixed inset-0 bg-black/40 z-[60] hidden" onclick="fecharClonarEscola()"></div>
<aside id="clonarDrawer"
       class="fixed top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl z-[70] transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Clonar escola</h2>
            <p id="clonarOrigemLabel" class="text-sm text-slate-500 mt-1"></p>
        </div>
        <button type="button" onclick="fecharClonarEscola()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <form method="POST" action="<?= URL ?>/master/escolas/clonar" class="flex flex-col flex-1 overflow-hidden" id="form-clonar-escola">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" name="escola_origem_id" id="clonar_escola_origem_id" value="">
        <div class="flex-1 overflow-y-auto px-6 sm:px-8 py-6 space-y-8">
            <p class="text-sm text-slate-600">A cópia roda em fila (não trava esta tela). O banco destino nasce vazio; em seguida o job importa o dump completo da escola origem (todas as tabelas e dados) e aplica as migrations do projeto que a origem ainda não tinha. Arquivos locais da escola também são copiados; objetos no S3 ficam de fora.</p>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Nova escola</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div class="sm:col-span-2">
                        <label for="clonar_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                        <input type="text" id="clonar_nome" name="nome" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="clonar_slug" class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                        <input type="text" id="clonar_slug" name="slug"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="gerado a partir do nome">
                    </div>
                    <div>
                        <label for="clonar_dominio" class="block text-sm font-medium text-gray-700 mb-1">Domínio</label>
                        <input type="text" id="clonar_dominio" name="dominio"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="slug.<?= htmlspecialchars($tenantBaseDomain) ?>">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="ativo" value="1" checked class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">Ativar a cópia ao terminar</span>
                        </label>
                    </div>
                </div>
            </section>
            <section>
                <h3 class="text-lg font-semibold text-gray-900 border-b border-gray-200 pb-2 mb-4">Banco de dados da cópia</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-5">
                    <div>
                        <label for="clonar_db_host" class="block text-sm font-medium text-gray-700 mb-1">Host <span class="text-red-500">*</span></label>
                        <input type="text" id="clonar_db_host" name="db_host" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="clonar_db_porta" class="block text-sm font-medium text-gray-700 mb-1">Porta</label>
                        <input type="number" id="clonar_db_porta" name="db_porta" value="3306" min="1" max="65535"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="clonar_db_nome" class="block text-sm font-medium text-gray-700 mb-1">Nome do banco <span class="text-red-500">*</span></label>
                        <input type="text" id="clonar_db_nome" name="db_nome_banco" required
                               placeholder="educatudo_nova_escola"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div>
                        <label for="clonar_db_usuario" class="block text-sm font-medium text-gray-700 mb-1">Usuário <span class="text-red-500">*</span></label>
                        <input type="text" id="clonar_db_usuario" name="db_usuario" required
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <div class="sm:col-span-2">
                        <label for="clonar_db_senha" class="block text-sm font-medium text-gray-700 mb-1">Senha <span class="text-red-500">*</span></label>
                        <input type="password" id="clonar_db_senha" name="db_senha" required autocomplete="new-password"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <p class="text-xs text-slate-500 mt-1">O banco destino precisa estar vazio (ou ainda não existir, se a criação automática estiver ligada).</p>
                    </div>
                    <?php if ($criarBancoDisponivel): ?>
                    <div class="sm:col-span-2">
                        <label class="inline-flex items-center gap-2">
                            <input type="checkbox" name="criar_banco_automaticamente" value="1" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm text-gray-700">Criar banco e usuário MySQL automaticamente</span>
                        </label>
                    </div>
                    <?php endif; ?>
                </div>
            </section>
        </div>
        <div class="px-6 sm:px-8 py-5 border-t border-gray-200 flex flex-col-reverse sm:flex-row justify-end gap-3">
            <button type="button" onclick="fecharClonarEscola()"
                    class="px-6 py-2.5 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition-colors">
                Cancelar
            </button>
            <button type="submit"
                    class="px-6 py-2.5 rounded-lg font-semibold bg-blue-600 text-white hover:bg-blue-700 transition-colors shadow-sm">
                Enfileirar clonagem
            </button>
        </div>
    </form>
</aside>
<script>
(function () {
    var tenantBaseDomain = <?= json_encode($tenantBaseDomain, JSON_UNESCAPED_UNICODE) ?>;
    var slugManual = false;
    var dominioManual = false;
    var dbNomeManual = false;

    function slugify(text) {
        return String(text || '')
            .toLowerCase()
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9]+/g, '-')
            .replace(/^-+|-+$/g, '');
    }

    function bancoFromSlug(slug) {
        var s = String(slug || '').replace(/-/g, '_');
        s = s.replace(/[^a-z0-9_]/g, '');
        if (!s) return '';
        return 'educatudo_' + s;
    }

    window.fecharClonarEscola = function () {
        document.getElementById('clonarDrawerBackdrop').classList.add('hidden');
        var drawer = document.getElementById('clonarDrawer');
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    };

    window.abrirClonarEscola = function (btn) {
        slugManual = false;
        dominioManual = false;
        dbNomeManual = false;
        document.getElementById('form-clonar-escola').reset();
        document.getElementById('clonar_escola_origem_id').value = btn.getAttribute('data-id') || '';
        document.getElementById('clonarOrigemLabel').textContent = 'Cópia de: ' + (btn.getAttribute('data-nome') || '');
        document.getElementById('clonar_db_host').value = btn.getAttribute('data-host') || '';
        document.getElementById('clonar_db_porta').value = btn.getAttribute('data-porta') || '3306';
        document.getElementById('clonar_db_usuario').value = btn.getAttribute('data-usuario') || '';
        document.getElementById('clonarDrawerBackdrop').classList.remove('hidden');
        var drawer = document.getElementById('clonarDrawer');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.getElementById('clonar_nome').focus();
    };

    document.querySelectorAll('.js-clonar-escola').forEach(function (btn) {
        btn.addEventListener('click', function () { window.abrirClonarEscola(btn); });
    });

    var campoNome = document.getElementById('clonar_nome');
    var campoSlug = document.getElementById('clonar_slug');
    var campoDominio = document.getElementById('clonar_dominio');
    var campoDbNome = document.getElementById('clonar_db_nome');

    campoNome.addEventListener('input', function () {
        var slug = slugify(campoNome.value);
        if (!slugManual) campoSlug.value = slug;
        if (!dominioManual && (campoSlug.value || slug)) {
            campoDominio.value = (campoSlug.value || slug) + '.' + (tenantBaseDomain || 'localhost');
        }
        if (!dbNomeManual) campoDbNome.value = bancoFromSlug(campoSlug.value || slug);
    });
    campoSlug.addEventListener('input', function () { slugManual = true; });
    campoDominio.addEventListener('input', function () { dominioManual = true; });
    campoDbNome.addEventListener('input', function () { dbNomeManual = true; });

    <?php if ($temJobsPendentes): ?>
    setTimeout(function () { window.location.reload(); }, 8000);
    <?php endif; ?>
})();
</script>

