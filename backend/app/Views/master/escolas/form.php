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
    require_once __DIR__ . '/../../../Services/DominioEscolaService.php';

    $externalAppsLinksRaw = $layout['external_apps_links'] ?? '[]';
    $externalAppsLinks = json_decode((string) $externalAppsLinksRaw, true);
    if (!is_array($externalAppsLinks)) {
        $externalAppsLinks = [];
    }
    if (empty($externalAppsLinks)) {
        $legacyDefaults = [
            ['nome' => 'EducaLabs', 'url' => (string) ($layout['educalabs_external_url'] ?? 'https://educalabs.educatudo.com/login'), 'aluno' => true, 'professor' => false, 'nova_guia' => true],
            ['nome' => 'Games', 'url' => (string) ($layout['games_external_url'] ?? 'https://games.educatudo.com'), 'aluno' => true, 'professor' => false, 'nova_guia' => true],
            ['nome' => 'Notes', 'url' => (string) ($layout['notes_external_url'] ?? 'https://notes.educatudo.com'), 'aluno' => true, 'professor' => false, 'nova_guia' => true],
        ];
        foreach ($legacyDefaults as $item) {
            if (trim((string) ($item['url'] ?? '')) !== '') {
                $externalAppsLinks[] = $item;
            }
        }
    }
    ?>
    <form method="post" action="<?= $escola ? (URL . '/master/escolas/atualizar') : (URL . '/master/escolas/salvar') ?>" class="space-y-8" enctype="multipart/form-data">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <?php if ($escola): ?>
        <input type="hidden" name="id" value="<?= (int) $escola['id'] ?>">
        <?php endif; ?>

        <!-- Dados da escola -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Dados da escola</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
                    <input type="text" name="nome" required value="<?= htmlspecialchars($escola['nome'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Slug</label>
                        <input type="text" name="slug" id="campo_slug" required value="<?= htmlspecialchars($escola['slug'] ?? '') ?>"
                           placeholder="minha-escola" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Apenas letras minúsculas, números e hífen. Usado no header X-Tenant.</p>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Domínio (subdomínio)</label>
                    <input type="text" name="dominio" id="campo_dominio" value="<?= htmlspecialchars($escola['dominio'] ?? '') ?>"
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
                            <dd class="font-medium text-slate-900"><?= htmlspecialchars(DominioEscolaService::rotuloDnsStatus($escola['dns_status'] ?? null)) ?></dd>
                        </div>
                        <div>
                            <dt class="text-slate-500">HTTPS</dt>
                            <dd class="font-medium text-slate-900"><?= htmlspecialchars(DominioEscolaService::rotuloSslStatus($escola['ssl_status'] ?? null)) ?></dd>
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
                    <p class="text-xs text-slate-500 mb-3">DNS wildcard (<code>* .<?= htmlspecialchars($tenantBaseDomain === 'localhost' ? 'localhost' : $tenantBaseDomain) ?></code>) e certificado Let's Encrypt na origem são configurados no servidor. Ver <code>docs/DEPLOY-DOMINIOS.md</code>.</p>
                    <button type="submit" form="form-verificar-dominio-escola" class="inline-flex items-center px-3 py-1.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Verificar HTTPS agora</button>
                </div>
                <?php endif; ?>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="ativo" value="1" <?= empty($escola) || !empty($escola['ativo']) ? 'checked' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-slate-700">Escola ativa</span>
                    </label>
                </div>
            </div>
        </section>

        <!-- Banco de dados da escola -->
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
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nome do banco</label>
                    <input type="text" name="db_nome_banco" value="<?= htmlspecialchars($banco['nome_banco'] ?? '') ?>"
                           placeholder="educatudo_escola1" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Usuário</label>
                    <input type="text" name="db_usuario" value="<?= htmlspecialchars($banco['usuario'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Senha</label>
                    <input type="password" name="db_senha" value=""
                           placeholder="<?= $banco ? 'Deixe em branco para não alterar' : 'Senha do banco' ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Ao salvar com host, banco, usuário e senha, o sistema aplica o schema atual e as migrations no banco informado — mesmo se ele já existir e estiver vazio.</p>
                </div>
                <?php if (!empty($criar_banco_disponivel)): ?>
                <div class="md:col-span-2">
                    <label class="inline-flex items-center gap-2">
                        <input type="checkbox" name="criar_banco_automaticamente" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-slate-700">Criar banco e usuário MySQL automaticamente</span>
                    </label>
                    <p class="text-xs text-slate-500 mt-1">Requer <code>DB_ADMIN_USER</code> e <code>DB_ADMIN_PASS</code> no .env. Esse usuário precisa ter permissão <strong>a partir da VPS</strong> (não vale só <code>root@localhost</code>). O sistema cria o banco, o usuário, aplica o schema e roda as migrations.</p>
                </div>
                <?php endif; ?>
            </div>
        </section>

        <!-- Layout (cor e imagem) -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-4">Layout da escola</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cor primária</label>
                    <div class="flex gap-2 items-center">
                        <input type="color" id="layout_primary_color_picker" value="<?= htmlspecialchars($layout['primary_color'] ?? '#6366f1') ?>"
                               class="h-10 w-14 rounded border border-slate-300 cursor-pointer">
                        <input type="text" name="layout_primary_color" id="layout_primary_color" value="<?= htmlspecialchars($layout['primary_color'] ?? '#6366f1') ?>"
                               class="flex-1 px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    </div>
                    <script>document.getElementById('layout_primary_color_picker').oninput=function(){document.getElementById('layout_primary_color').value=this.value};document.getElementById('layout_primary_color').oninput=function(){var v=this.value;if(/^#[0-9A-Fa-f]{6}$/.test(v))document.getElementById('layout_primary_color_picker').value=v;};</script>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Cor do texto (sobre primária)</label>
                    <input type="text" name="layout_primary_text_color" value="<?= htmlspecialchars($layout['primary_text_color'] ?? '#ffffff') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">URL do logo</label>
                    <input type="text" name="layout_logo_url" value="<?= htmlspecialchars($layout['logo_url'] ?? '') ?>"
                           placeholder="https://... ou /public/uploads/logo.png" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Ou envie um arquivo (será salvo no storage local ou S3):</p>
                    <input type="file" name="layout_logo_upload" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">URL do logo quadrado (1x1)</label>
                    <input type="text" name="layout_logo_1x1_url" value="<?= htmlspecialchars($layout['logo_1x1_url'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Ou envie um arquivo:</p>
                    <input type="file" name="layout_logo_1x1_upload" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">URL da capa da página de login</label>
                    <input type="text" name="layout_login_cover_url" value="<?= htmlspecialchars($layout['login_cover_url'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Ou envie um arquivo (imagem de capa):</p>
                    <input type="file" name="layout_login_cover_upload" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Título do sistema</label>
                    <input type="text" name="layout_system_title" value="<?= htmlspecialchars($layout['system_title'] ?? 'EducaTudo') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Subtítulo</label>
                    <input type="text" name="layout_system_subtitle" value="<?= htmlspecialchars($layout['system_subtitle'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nome da IA (chat)</label>
                    <input type="text" name="layout_ia_name" value="<?= htmlspecialchars($layout['ia_name'] ?? 'Tudinha') ?>"
                           placeholder="Ex: Tudinha, Edu, Mia..." class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Nome que aparecerá em todo o sistema para a IA educacional.</p>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Ícone/avatar da IA (chat)</label>
                    <input type="text" name="layout_ia_avatar_url" value="<?= htmlspecialchars($layout['ia_avatar_url'] ?? '') ?>"
                           placeholder="https://... ou /public/uploads/ia-avatar.png" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                    <p class="text-xs text-slate-500 mt-1">Ou envie um arquivo. Se vazio, usa o ícone padrão (🤖).</p>
                    <input type="file" name="layout_ia_avatar_upload" accept=".jpg,.jpeg,.png,.gif,.webp,.svg" class="mt-1 block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Primeiro acesso: turma obrigatória</label>
                    <select name="layout_primeiro_acesso_turma_obrigatoria" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="1" <?= ($layout['primeiro_acesso_turma_obrigatoria'] ?? '1') === '1' ? 'selected' : '' ?>>Sim (aluno seleciona turma)</option>
                        <option value="0" <?= ($layout['primeiro_acesso_turma_obrigatoria'] ?? '1') === '0' ? 'selected' : '' ?>>Não (busca pelo nome em todas as turmas)</option>
                    </select>
                    <p class="text-xs text-slate-500 mt-1">Se "Não", o primeiro acesso busca o aluno pelo nome em todo o banco de dados.</p>
                </div>
            </div>
        </section>

        <!-- Módulos do sistema -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-2">Módulos do sistema</h3>
            <p class="text-sm text-slate-600 mb-4">1 = Habilitado, 0 = Desabilitado, 2 = Inativo (oculto). Desligar remove o acesso de aluno, professor e pais.</p>
            <div class="flex flex-wrap gap-2 mb-4">
                <button type="button" class="js-master-modules-batch px-3 py-1.5 rounded-lg text-sm bg-green-100 text-green-800 hover:bg-green-200" data-value="1">Habilitar tudo</button>
                <button type="button" class="js-master-modules-batch px-3 py-1.5 rounded-lg text-sm bg-amber-100 text-amber-800 hover:bg-amber-200" data-value="0">Desabilitar tudo</button>
                <button type="button" class="js-master-modules-batch px-3 py-1.5 rounded-lg text-sm bg-gray-200 text-slate-700 hover:bg-gray-300" data-value="2">Inativar tudo</button>
            </div>
            <?php
            $layout = $layout ?? [];
            $modulos_catalogo = $modulos_catalogo ?? [];
            if (!class_exists('ModuloCatalogo', false)) {
                require_once dirname(__DIR__, 3) . '/Core/ModuloCatalogo.php';
            }
            ?>
            <div class="border border-slate-200 rounded-lg overflow-hidden">
                <div class="p-4 grid grid-cols-1 md:grid-cols-2 gap-3">
                    <?php foreach ($modulos_catalogo as $mod): ?>
                    <?php
                        $chave = (string) ($mod['chave'] ?? '');
                        $nome = (string) ($mod['nome'] ?? $chave);
                        $featureKeys = is_array($mod['feature_keys'] ?? null) ? $mod['feature_keys'] : [];
                        $firstKey = (string) ($featureKeys[0] ?? '');
                        $defaultMod = $firstKey !== '' ? ModuloCatalogo::valorPadrao($mod) : '1';
                        $cur = $firstKey !== '' ? ($layout['module_' . $firstKey] ?? $defaultMod) : $defaultMod;
                    ?>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-sm text-slate-700"><?= htmlspecialchars($nome) ?></span>
                        <select name="modules[<?= htmlspecialchars($chave) ?>]" class="master-module-select rounded border-slate-300 text-sm w-36 focus:ring-blue-500 focus:border-blue-500">
                            <option value="1" <?= $cur === '1' ? 'selected' : '' ?>>Habilitado</option>
                            <option value="0" <?= $cur === '0' ? 'selected' : '' ?>>Desabilitado</option>
                            <option value="2" <?= $cur === '2' ? 'selected' : '' ?>>Inativo</option>
                        </select>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <!-- Links no submenu (Aluno/Professor) -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-2">Links no submenu (Aluno/Professor)</h3>
            <p class="text-sm text-slate-600 mb-4">Adicione links personalizados no submenu do aluno e/ou professor.</p>
            <input type="hidden" name="layout_menu_links_submenu" id="master-menu-links-json" value="<?= htmlspecialchars($layout['menu_links_submenu'] ?? '[]') ?>">
            <div id="master-menu-links-list" class="space-y-3 mb-3"></div>
            <button type="button" id="master-add-menu-link" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-200 text-sm">+ Adicionar link</button>
        </section>

        <!-- Apps externos -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-2">Apps externos (menu Materiais)</h3>
            <p class="text-sm text-slate-600 mb-4">Cadastre links externos dinâmicos. O acesso envia <code>token</code> + <code>slug</code> por um único fluxo.</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">ID da instituição (inst)</label>
                    <input type="text" name="external_institution_id" value="<?= htmlspecialchars($layout['external_institution_id'] ?? '') ?>"
                           placeholder="Ex: 02" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Endpoint único de validação</label>
                    <input type="text" name="external_apps_validate_url" value="<?= htmlspecialchars($layout['external_apps_validate_url'] ?? (rtrim(URL, '/') . '/external-apps/validate-token')) ?>"
                           class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
            <input type="hidden" name="layout_external_apps_links" id="master-external-apps-json" value="<?= htmlspecialchars(json_encode($externalAppsLinks, JSON_UNESCAPED_UNICODE)) ?>">
            <div id="master-external-apps-list" class="space-y-3 mb-3"></div>
            <button type="button" id="master-add-external-app" class="bg-slate-100 text-slate-700 px-4 py-2 rounded-lg hover:bg-slate-200 text-sm">+ Adicionar app externo</button>
        </section>

        <!-- E-mail (SMTP) -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-2">E-mail (SMTP)</h3>
            <p class="text-sm text-slate-600 mb-4">Servidor de envio de e-mails (recuperação de senha, notificações).</p>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Servidor SMTP</label>
                    <input type="text" name="email_smtp_host" value="<?= htmlspecialchars($layout['email_smtp_host'] ?? '') ?>" placeholder="smtp.gmail.com" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Porta</label>
                    <input type="number" name="email_smtp_port" value="<?= htmlspecialchars($layout['email_smtp_port'] ?? '587') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Segurança</label>
                    <select name="email_smtp_secure" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                        <option value="tls" <?= ($layout['email_smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                        <option value="ssl" <?= ($layout['email_smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Usuário SMTP</label>
                    <input type="text" name="email_smtp_username" value="<?= htmlspecialchars($layout['email_smtp_username'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium text-slate-700 mb-1">Senha SMTP</label>
                    <input type="password" name="email_smtp_password" value="" placeholder="Deixe em branco para não alterar" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">E-mail remetente</label>
                    <input type="email" name="email_from_email" value="<?= htmlspecialchars($layout['email_from_email'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nome do remetente</label>
                    <input type="text" name="email_from_name" value="<?= htmlspecialchars($layout['email_from_name'] ?? 'EducaTudo') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Reply-to</label>
                    <input type="email" name="email_reply_to" value="<?= htmlspecialchars($layout['email_reply_to'] ?? '') ?>" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </section>

        <!-- Prompts de IA -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-2">Prompts de IA</h3>
            <p class="text-sm text-slate-600 mb-4">Textos usados pelos modelos de IA. Selecione a aba para editar.</p>
            <?php
            $promptTabs = [
                'prompt_tudinha_chat'              => ['label' => 'Chat Aluno',              'icon' => '💬', 'rows' => 6],
                'prompt_tema'                      => ['label' => 'Tema Redação',            'icon' => '📝', 'rows' => 6],
                'prompt_correcao'                  => ['label' => 'Correção',                'icon' => '✅', 'rows' => 6],
                'prompt_ocr'                       => ['label' => 'OCR',                     'icon' => '📷', 'rows' => 4],
                'prompt_prova'                     => ['label' => 'Prova IA',                'icon' => '📋', 'rows' => 8],
                'prompt_prova_imagens'             => ['label' => 'Imagens Prova',           'icon' => '🖼️', 'rows' => 6],
                'prompt_exercicios_jornada'        => ['label' => 'Exercícios Jornada',      'icon' => '🎯', 'rows' => 8],
                'prompt_exercicios_personalizados' => ['label' => 'Exercícios Personalizados','icon' => '📚', 'rows' => 8],
            ];
            $promptValues = $layout ?? [];
            $tabsId = 'master-form-prompts';
            include __DIR__ . '/../../components/prompt-tabs.php';
            ?>
        </section>

        <!-- Chaves de API -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <h3 class="text-lg font-semibold text-slate-800 mb-2">Chaves de API</h3>
            <p class="text-sm text-slate-600 mb-4">OpenAI, Gamma, Nanobanana. Deixe em branco para não alterar.</p>
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">OpenAI API Key</label>
                    <input type="password" name="openai_api_key" value="" placeholder="Deixe em branco para não alterar" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Gamma API Key</label>
                    <input type="password" name="gamma_api_key" value="" placeholder="Deixe em branco para não alterar" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-1">Nanobanana API Key</label>
                    <input type="password" name="nanobanana_api_key" value="" placeholder="Deixe em branco para não alterar" class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
                </div>
            </div>
        </section>

        <!-- Sincronização com o banco da escola -->
        <section class="bg-white rounded-xl shadow border border-slate-200 p-6">
            <p class="text-sm text-slate-700 mb-2">Quando você preenche os dados do banco da escola acima, ao salvar as alterações de layout e módulos são enviadas <strong>direto para o banco da escola</strong> (config_layout do tenant).</p>
            <label class="inline-flex items-center gap-2">
                <input type="checkbox" name="nao_sincronizar" value="1" class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="text-sm text-slate-700">Não sincronizar agora (marque só se o banco da escola ainda não existir ou estiver indisponível)</span>
            </label>
        </section>

        <div class="flex gap-3">
            <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                <?= $escola ? 'Atualizar escola' : 'Criar escola' ?>
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
    var menuLinksJson = document.getElementById('master-menu-links-json');
    var menuLinksList = document.getElementById('master-menu-links-list');
    var addBtn = document.getElementById('master-add-menu-link');
    var externalAppsJson = document.getElementById('master-external-apps-json');
    var externalAppsList = document.getElementById('master-external-apps-list');
    var addExternalAppBtn = document.getElementById('master-add-external-app');
    function getLinks() {
        try { return JSON.parse(menuLinksJson.value || '[]'); } catch (e) { return []; }
    }
    function setLinks(arr) {
        menuLinksJson.value = JSON.stringify(arr);
    }
    function renderLinks() {
        var links = getLinks();
        menuLinksList.innerHTML = '';
        links.forEach(function(link, i) {
            var row = document.createElement('div');
            row.className = 'grid grid-cols-1 md:grid-cols-12 gap-2 items-center border border-slate-200 rounded p-2';
            row.innerHTML = '<input type="text" class="ml-nome md:col-span-3 px-2 py-1.5 border rounded text-sm" placeholder="Nome" value="' + (link.nome || '').replace(/"/g, '&quot;') + '">' +
                '<input type="text" class="ml-url md:col-span-4 px-2 py-1.5 border rounded text-sm" placeholder="URL" value="' + (link.url || '').replace(/"/g, '&quot;') + '">' +
                '<label class="md:col-span-1 text-sm"><input type="checkbox" class="ml-aluno rounded" ' + (link.aluno ? 'checked' : '') + '> Aluno</label>' +
                '<label class="md:col-span-1 text-sm"><input type="checkbox" class="ml-professor rounded" ' + (link.professor ? 'checked' : '') + '> Prof.</label>' +
                '<label class="md:col-span-1 text-sm"><input type="checkbox" class="ml-nova-guia rounded" ' + (link.nova_guia ? 'checked' : '') + '> Nova guia</label>' +
                '<button type="button" class="ml-remove text-red-600 text-sm md:col-span-1">Remover</button>';
            row.querySelector('.ml-remove').addEventListener('click', function() {
                links.splice(i, 1);
                setLinks(links);
                renderLinks();
            });
            menuLinksList.appendChild(row);
        });
    }
    function collectLinks() {
        var links = getLinks();
        var rows = menuLinksList.querySelectorAll('.grid');
        var out = [];
        rows.forEach(function(row) {
            var nome = (row.querySelector('.ml-nome') || {}).value || '';
            var url = (row.querySelector('.ml-url') || {}).value || '';
            out.push({ nome: nome, url: url, aluno: !!(row.querySelector('.ml-aluno') && row.querySelector('.ml-aluno').checked), professor: !!(row.querySelector('.ml-professor') && row.querySelector('.ml-professor').checked), nova_guia: !!(row.querySelector('.ml-nova-guia') && row.querySelector('.ml-nova-guia').checked) });
        });
        setLinks(out);
    }

    function getExternalApps() {
        try { return JSON.parse((externalAppsJson || {}).value || '[]'); } catch (e) { return []; }
    }
    function setExternalApps(arr) {
        if (externalAppsJson) externalAppsJson.value = JSON.stringify(arr);
    }
    function renderExternalApps() {
        if (!externalAppsList) return;
        var apps = getExternalApps();
        externalAppsList.innerHTML = '';
        apps.forEach(function(app, i) {
            var row = document.createElement('div');
            row.className = 'grid grid-cols-1 md:grid-cols-12 gap-2 items-center border border-slate-200 rounded p-2';
            row.innerHTML =
                '<input type="text" class="ea-nome md:col-span-2 px-2 py-1.5 border rounded text-sm" placeholder="Nome" value="' + (app.nome || '').replace(/"/g, '&quot;') + '">' +
                '<input type="text" class="ea-url md:col-span-5 px-2 py-1.5 border rounded text-sm" placeholder="URL" value="' + (app.url || '').replace(/"/g, '&quot;') + '">' +
                '<label class="md:col-span-1 text-sm"><input type="checkbox" class="ea-aluno rounded" ' + (app.aluno ? 'checked' : '') + '> Aluno</label>' +
                '<label class="md:col-span-1 text-sm"><input type="checkbox" class="ea-professor rounded" ' + (app.professor ? 'checked' : '') + '> Prof.</label>' +
                '<label class="md:col-span-2 text-sm"><input type="checkbox" class="ea-nova-guia rounded" ' + (app.nova_guia ? 'checked' : '') + '> Nova guia</label>' +
                '<button type="button" class="ea-remove text-red-600 text-sm md:col-span-1">Remover</button>';
            row.querySelector('.ea-remove').addEventListener('click', function() {
                apps.splice(i, 1);
                setExternalApps(apps);
                renderExternalApps();
            });
            externalAppsList.appendChild(row);
        });
    }
    function collectExternalApps() {
        if (!externalAppsList) return;
        var rows = externalAppsList.querySelectorAll('.grid');
        var out = [];
        rows.forEach(function(row) {
            var nome = (row.querySelector('.ea-nome') || {}).value || '';
            var url = (row.querySelector('.ea-url') || {}).value || '';
            out.push({
                nome: nome,
                url: url,
                aluno: !!(row.querySelector('.ea-aluno') && row.querySelector('.ea-aluno').checked),
                professor: !!(row.querySelector('.ea-professor') && row.querySelector('.ea-professor').checked),
                nova_guia: !!(row.querySelector('.ea-nova-guia') && row.querySelector('.ea-nova-guia').checked)
            });
        });
        setExternalApps(out);
    }
    addBtn.addEventListener('click', function() {
        var links = getLinks();
        links.push({ nome: '', url: '', aluno: false, professor: false, nova_guia: true });
        setLinks(links);
        renderLinks();
    });
    if (addExternalAppBtn) {
        addExternalAppBtn.addEventListener('click', function() {
            var apps = getExternalApps();
            apps.push({ nome: '', url: '', aluno: true, professor: false, nova_guia: true });
            setExternalApps(apps);
            renderExternalApps();
        });
    }
    document.querySelector('form').addEventListener('submit', function() {
        collectLinks();
        collectExternalApps();
    });
    renderLinks();
    renderExternalApps();

        document.querySelectorAll('.js-master-modules-batch').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var val = this.getAttribute('data-value');
                document.querySelectorAll('.master-module-select').forEach(function(sel) { sel.value = val; });
            });
        });

        var btnDominio = document.getElementById('sugerir_dominio');
        var tenantBaseDomain = <?= json_encode($tenantBaseDomain, JSON_UNESCAPED_UNICODE) ?>;
        var campoSlug = document.getElementById('campo_slug');
        var campoDominio = document.getElementById('campo_dominio');
        var previewUrl = document.getElementById('preview_url_escola');

        function sugerirDominioDoSlug() {
            var slug = (campoSlug || {}).value.trim().toLowerCase().replace(/[^a-z0-9\-]/g, '');
            if (slug && campoDominio) {
                campoDominio.value = slug + '.' + (tenantBaseDomain || 'localhost');
            }
            atualizarPreviewUrl();
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

        if (btnDominio) {
            btnDominio.addEventListener('click', sugerirDominioDoSlug);
        }
        if (campoSlug) campoSlug.addEventListener('input', atualizarPreviewUrl);
        if (campoDominio) campoDominio.addEventListener('input', atualizarPreviewUrl);
        atualizarPreviewUrl();
    })();
</script>
