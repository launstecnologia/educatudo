<?php
$esc = fn($v) => htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
$zapsign = $zapsign ?? [];
$ativoOk = !empty($zapsign['ativo']) && !empty($zapsign['tem_api_token']);
?>

<!-- Header Section — padrão página própria (ex.: exams/blocks/create.php) -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Assinatura Digital</h2>
            <p class="text-gray-600">
                Tokens e integrações com parceiros de assinatura eletrônica (ZapSign, DocuSign…).
            </p>
        </div>
        <a href="<?= URL ?>/admin/enrollment" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php if (!empty($status_message)): ?>
<div class="mb-6 p-4 rounded-lg <?= ($status_type ?? '') === 'error' ? 'bg-red-100 text-red-800 border border-red-200' : 'bg-green-100 text-green-800 border border-green-200' ?>">
    <?= $esc($status_message) ?>
</div>
<?php endif; ?>

<!-- Form — card largura total, sem max-w -->
<div class="bg-white rounded-xl shadow-lg p-6">
    <form method="POST" action="<?= URL ?>/admin/configuracao/assinatura-digital">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

        <div class="mb-6 flex items-start justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-gray-900 flex items-center gap-2">
                    <span class="inline-flex items-center justify-center w-8 h-8 rounded-lg"
                          style="background-color: color-mix(in srgb, var(--primary-color) 12%, white); color: var(--primary-color);">
                        <i class="fa-solid fa-pen-nib text-sm"></i>
                    </span>
                    ZapSign
                </h3>
                <p class="mt-1 text-sm text-gray-500">Assinatura eletrônica via ZapSign (sandbox ou produção).</p>
            </div>
            <span class="shrink-0 inline-flex px-2.5 py-1 rounded-full text-[11px] font-semibold <?= $ativoOk ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600' ?>">
                <?= $ativoOk ? 'Ativo' : 'Inativo' ?>
            </span>
        </div>

        <div class="mb-6">
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="zapsign_ativo" value="1"
                       class="rounded border-gray-300 text-primary focus:ring-primary"
                       <?= !empty($zapsign['ativo']) ? 'checked' : '' ?>>
                Ativar ZapSign
            </label>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <label for="zapsign_ambiente" class="block text-sm font-medium text-gray-700 mb-2">Ambiente</label>
                <select id="zapsign_ambiente" name="zapsign_ambiente"
                        class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                    <option value="sandbox" <?= ($zapsign['ambiente'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox</option>
                    <option value="production" <?= ($zapsign['ambiente'] ?? '') === 'production' ? 'selected' : '' ?>>Production</option>
                </select>
            </div>
            <div>
                <label for="zapsign_api_token" class="block text-sm font-medium text-gray-700 mb-2">API Token</label>
                <input type="password" id="zapsign_api_token" name="zapsign_api_token" value=""
                       autocomplete="new-password"
                       placeholder="<?= !empty($zapsign['tem_api_token']) ? 'Deixe em branco para manter' : 'Cole o token da API' ?>"
                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
                <?php if (!empty($zapsign['api_token_mascarado'])): ?>
                <p class="mt-1 text-xs text-gray-500">Atual: <?= $esc($zapsign['api_token_mascarado']) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <div class="mb-6">
            <label for="zapsign_webhook_base_url" class="block text-sm font-medium text-gray-700 mb-2">Webhook base URL</label>
            <input type="url" id="zapsign_webhook_base_url" name="zapsign_webhook_base_url"
                   value="<?= $esc($zapsign['webhook_base_url'] ?? '') ?>"
                   placeholder="https://sua-escola.dominio.com"
                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent">
            <p class="mt-1 text-xs text-gray-500">Domínio público da escola (sem path). Usado para montar a URL do webhook.</p>
        </div>

        <?php if (!empty($zapsign['webhook_url'])): ?>
        <div class="mb-6 bg-gray-50 border border-gray-200 rounded-lg p-3">
            <p class="text-xs font-medium text-gray-500 mb-1">URL do webhook (cadastre na ZapSign)</p>
            <code class="text-xs text-gray-800 break-all"><?= $esc($zapsign['webhook_url']) ?></code>
        </div>
        <?php endif; ?>

        <div class="mb-6 space-y-3">
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="zapsign_enviar_email" value="1"
                       class="rounded border-gray-300 text-primary focus:ring-primary"
                       <?= !empty($zapsign['enviar_email']) ? 'checked' : '' ?>>
                Enviar e-mail pela ZapSign
            </label>
            <label class="flex items-center gap-3 text-sm text-gray-700">
                <input type="checkbox" name="zapsign_regenerar_webhook" value="1"
                       class="rounded border-gray-300 text-amber-600 focus:ring-amber-500">
                Regenerar token do webhook
            </label>
        </div>

        <div class="mb-8 pt-4 border-t border-gray-100">
            <h3 class="text-sm font-semibold text-gray-700 flex items-center gap-2 mb-1">
                <i class="fa-solid fa-file-signature text-gray-400"></i>
                DocuSign
            </h3>
            <p class="text-sm text-gray-500">Em breve — outro provedor poderá ser configurado nesta mesma tela.</p>
        </div>

        <div class="flex gap-3">
            <button type="submit" class="btn-primary">
                <i class="fa-solid fa-check mr-1.5"></i> Salvar
            </button>
            <a href="<?= URL ?>/admin/enrollment/config" class="btn-secondary">Configuração de Matrícula</a>
        </div>
    </form>
</div>
