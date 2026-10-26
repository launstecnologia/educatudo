<div class="max-w-4xl mx-auto">
    <div class="mb-4">
        <a href="<?= URL ?>/master/escolas" class="text-slate-600 hover:text-blue-600 hover:underline">← Voltar às escolas</a>
    </div>
    <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
    <?php if (!empty($flash_msg)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
        <?= htmlspecialchars($flash_msg) ?>
    </div>
    <?php endif; ?>

    <?php
    $domCfg = is_array($dominio_config ?? null) ? $dominio_config : [];
    $tenantBaseDomain = (string) ($domCfg['tenant_base_domain'] ?? 'localhost');
    $escola = is_array($escola ?? null) ? $escola : [];
    $banco = is_array($banco ?? null) ? $banco : null;
    $ufs = ['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'];
    $ufAtual = strtoupper((string) ($escola['uf'] ?? ''));
    $campo = static function (string $chave) use ($escola): string {
        return htmlspecialchars((string) ($escola[$chave] ?? ''));
    };
    ?>
    <form method="post" action="<?= !empty($escola['id']) ? (URL . '/master/escolas/atualizar') : (URL . '/master/escolas/salvar') ?>" class="space-y-8" autocomplete="off">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <?php if (!empty($escola['id'])): ?>
        <input type="hidden" name="id" value="<?= (int) $escola['id'] ?>">
        <?php endif; ?>

        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Dados da escola</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
                    <input type="text" name="nome" required value="<?= $campo('nome') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                    <input type="text" name="slug" id="campo_slug" required value="<?= $campo('slug') ?>"
                           placeholder="minha-escola" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Apenas letras minúsculas, números e hífen. Usado no header X-Tenant.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Domínio (subdomínio)</label>
                    <input type="text" name="dominio" id="campo_dominio" value="<?= $campo('dominio') ?>"
                           placeholder="<?= htmlspecialchars($tenantBaseDomain === 'localhost' ? 'escola.localhost' : 'escola.' . $tenantBaseDomain) ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">
                        Endereço da escola. Em branco, usa <strong>{slug}.<?= htmlspecialchars($tenantBaseDomain) ?></strong>.
                        <button type="button" id="sugerir_dominio" class="text-blue-600 hover:underline">Sugerir a partir do slug</button>
                    </p>
                    <p id="preview_url_escola" class="text-xs text-slate-600 mt-2 font-medium"></p>
                </div>
                <?php if (!empty($escola['id'])): ?>
                <div class="md:col-span-2 rounded-lg border border-slate-200 bg-slate-50 p-4">
                    <h4 class="text-sm font-semibold text-slate-800 mb-3">Status do domínio</h4>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-sm mb-4">
                        <div>
                            <dt class="text-slate-500">DNS</dt>
                            <dd class="font-medium text-slate-900"><?= htmlspecialchars(\App\Services\DominioEscolaService::rotuloDnsStatus($escola['dns_status'] ?? null)) ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">HTTPS</dt>
                            <dd class="font-medium text-slate-900"><?= htmlspecialchars(\App\Services\DominioEscolaService::rotuloSslStatus($escola['ssl_status'] ?? null)) ?></dd>
                        </div>
                        <?php if (!empty($escola['ssl_verificado_em'])): ?>
                        <div>
                            <dt class="text-slate-500">Verificado em</dt>
                            <dd><?= htmlspecialchars((string) $escola['ssl_verificado_em']) ?></dd>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($escola['ssl_expira_em'])): ?>
                        <div>
                            <dt class="text-slate-500">Certificado expira</dt>
                            <dd><?= htmlspecialchars((string) $escola['ssl_expira_em']) ?></dd>
                        </div>
                        <?php endif; ?>
                    </dl>
                    <?php if (!empty($escola['dominio_ultimo_erro']) && ($escola['ssl_status'] ?? '') !== 'ok'): ?>
                    <p class="text-xs text-amber-800 bg-amber-50 border border-amber-200 rounded px-3 py-2 mb-3"><?= htmlspecialchars((string) $escola['dominio_ultimo_erro']) ?></p>
                    <?php endif; ?>
                    <button type="submit" form="form-verificar-dominio-escola" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Verificar HTTPS agora</button>
                </div>
                <?php endif; ?>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Rua</label>
                    <input type="text" name="rua" value="<?= $campo('rua') ?>" maxlength="255"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Número</label>
                    <input type="text" name="numero" value="<?= $campo('numero') ?>" maxlength="20"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Complemento</label>
                    <input type="text" name="complemento" value="<?= $campo('complemento') ?>" maxlength="120"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Bairro</label>
                    <input type="text" name="bairro" value="<?= $campo('bairro') ?>" maxlength="120"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cidade</label>
                    <input type="text" name="cidade" value="<?= $campo('cidade') ?>" maxlength="120"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">UF</label>
                    <select name="uf" class="w-full px-3 py-2 border border-slate-300 rounded-lg bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="">Selecione</option>
                        <?php foreach ($ufs as $sigla): ?>
                        <option value="<?= $sigla ?>" <?= $ufAtual === $sigla ? 'selected' : '' ?>><?= $sigla ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Telefone</label>
                    <input type="tel" name="telefone" value="<?= $campo('telefone') ?>" maxlength="30"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Pessoa de contato</label>
                    <input type="text" name="contato_nome" value="<?= $campo('contato_nome') ?>" maxlength="255"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">E-mail da instituição</label>
                    <input type="email" name="email" value="<?= $campo('email') ?>" maxlength="255"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="ativo" value="1" <?= empty($escola['id']) || !empty($escola['ativo']) ? 'checked' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-slate-700">Escola ativa</span>
                    </label>
                </div>
            </div>
        </section>

        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Banco de dados da escola</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Host</label>
                    <input type="text" name="db_host" value="<?= htmlspecialchars($banco['host'] ?? '72.61.28.136') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Porta</label>
                    <input type="number" name="db_porta" value="<?= (int) ($banco['porta'] ?? 3306) ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="db_nome_banco">Nome do banco</label>
                    <input type="text" name="db_nome_banco" id="db_nome_banco" value="<?= htmlspecialchars($banco['nome_banco'] ?? '') ?>"
                           autocomplete="off" spellcheck="false" autocapitalize="off"
                           placeholder="preenchido pelo slug" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="db_usuario">Usuário</label>
                    <input type="text" name="db_usuario" id="db_usuario" value="<?= htmlspecialchars($banco['usuario'] ?? '') ?>"
                           autocomplete="off" spellcheck="false" autocapitalize="off"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1" for="db_senha">Senha</label>
                    <input type="password" name="db_senha" id="db_senha" value="" autocomplete="new-password"
                           placeholder="<?= $banco ? 'Deixe em branco para não alterar' : 'Senha do usuário novo' ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Ao salvar, o MySQL cria esse banco e o usuário, aplica o schema atual e roda as migrations.</p>
                </div>
                <?php if (!empty($criar_banco_disponivel)): ?>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="criar_banco_automaticamente" id="criar_banco_automaticamente" value="1"
                               <?= empty($escola['id']) ? 'checked' : '' ?>
                               class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-slate-700">Criar banco e usuário MySQL automaticamente</span>
                    </label>
                    <p class="text-xs text-slate-500 mt-1">Já vem marcado na escola nova. O nome do banco acompanha o slug. Requer <code>DB_ADMIN_USER</code> e <code>DB_ADMIN_PASS</code> no .env, com permissão a partir da VPS.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                <?= !empty($escola['id']) ? 'Atualizar escola' : 'Criar escola' ?>
            </button>
            <a href="<?= URL ?>/master/escolas" class="px-6 py-2 border border-slate-300 rounded-lg text-slate-700 hover:bg-slate-50">Cancelar</a>
        </div>
    </form>
    <?php if (!empty($escola['id'])): ?>
    <form id="form-verificar-dominio-escola" method="post" action="<?= URL ?>/master/escolas/verificar-dominio" class="hidden">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <input type="hidden" name="id" value="<?= (int) $escola['id'] ?>">
    </form>
    <?php endif; ?>
</div>
<script>
(function() {
    var btnDominio = document.getElementById('sugerir_dominio');
    var tenantBaseDomain = <?= json_encode($tenantBaseDomain, JSON_UNESCAPED_UNICODE) ?>;
    var campoSlug = document.getElementById('campo_slug');
    var campoDominio = document.getElementById('campo_dominio');
    var previewUrl = document.getElementById('preview_url_escola');
    var criarNova = <?= empty($escola['id']) ? 'true' : 'false' ?>;
    var campoBanco = document.getElementById('db_nome_banco');
    var campoUsuario = document.getElementById('db_usuario');
    var bancoAuto = campoBanco ? campoBanco.value : '';
    var usuarioAuto = campoUsuario ? campoUsuario.value : '';

    function identificadorDoSlug() {
        var slug = (campoSlug && campoSlug.value || '').trim().toLowerCase().replace(/[^a-z0-9\-]/g, '');
        return slug.replace(/-/g, '_').slice(0, 48);
    }

    function preencherBancoPeloSlug() {
        if (!criarNova) return;
        var base = identificadorDoSlug();
        if (!base) return;
        var nome = 'educatudo_' + base;
        if (campoBanco && (campoBanco.value.trim() === '' || campoBanco.value === bancoAuto)) {
            campoBanco.value = nome;
            bancoAuto = nome;
        }
        if (campoUsuario && (campoUsuario.value.trim() === '' || campoUsuario.value === usuarioAuto)) {
            campoUsuario.value = nome;
            usuarioAuto = nome;
        }
    }

    function atualizarPreviewUrl() {
        if (!previewUrl) return;
        var dom = (campoDominio && campoDominio.value.trim()) || '';
        if (!dom && campoSlug) {
            var slug = campoSlug.value.trim().toLowerCase().replace(/[^a-z0-9\-]/g, '');
            if (slug) dom = slug + '.' + (tenantBaseDomain || 'localhost');
        }
        previewUrl.textContent = dom ? ('URL da escola: https://' + dom) : '';
    }

    if (btnDominio && campoSlug && campoDominio) {
        btnDominio.addEventListener('click', function() {
            var slug = campoSlug.value.trim().toLowerCase().replace(/[^a-z0-9\-]/g, '');
            if (slug) {
                campoDominio.value = slug + '.' + (tenantBaseDomain || 'localhost');
            }
            atualizarPreviewUrl();
        });
    }
    if (campoSlug) campoSlug.addEventListener('input', function() {
        atualizarPreviewUrl();
        preencherBancoPeloSlug();
    });
    preencherBancoPeloSlug();
    if (campoDominio) campoDominio.addEventListener('input', atualizarPreviewUrl);
    atualizarPreviewUrl();
})();
</script>
