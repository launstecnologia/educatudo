<?php
$settings = $settings ?? [];
$flashcard_prompt = $flashcard_prompt ?? '';
$panda_video_api_url = $panda_video_api_url ?? '';
$panda_video_api_key = $panda_video_api_key ?? '';
$panda_video_live_create_endpoint = $panda_video_live_create_endpoint ?? '';
$panda_video_auth_header = $panda_video_auth_header ?? '';
$panda_video_auth_prefix = $panda_video_auth_prefix ?? '';
$panda_video_stream_key_id = $panda_video_stream_key_id ?? '';
$jaas_app_id = $jaas_app_id ?? '';
$jaas_api_key_id = $jaas_api_key_id ?? '';
$jaas_private_key = $jaas_private_key ?? '';
$jaas_base_url = $jaas_base_url ?? '';
$jaas_webhook_secret = $jaas_webhook_secret ?? '';
$jaas_webhook_signing_secret = $jaas_webhook_signing_secret ?? '';
$jitsi_public_base_url = $jitsi_public_base_url ?? '';
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? 'success';
$csrf_token = $csrf_token ?? '';
?>
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <a href="<?= URL ?>/admin/dev" class="text-indigo-600 hover:text-indigo-700 font-medium flex items-center gap-1 mb-2">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                Voltar às Configurações Avançadas
            </a>
            <h1 class="text-2xl font-bold text-gray-900">Dev Settings</h1>
            <p class="text-sm text-gray-500 mt-0.5">Configurações key-value (prompts, integrações, etc.).</p>
        </div>
    </div>

    <?php if ($flash_message !== ''): ?>
        <?php $bg = $flash_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800'); ?>
        <div class="p-4 rounded-lg border <?= $bg ?>"><?= htmlspecialchars($flash_message) ?></div>
    <?php endif; ?>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Configurações cadastradas</h2>
            <p class="text-sm text-gray-500 mt-0.5">Chave e última atualização.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                    <tr>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Chave</th>
                        <th class="px-5 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Atualizado em</th>
                    </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                    <?php if (empty($settings)): ?>
                        <tr><td colspan="2" class="px-5 py-4 text-sm text-gray-500">Nenhuma configuração.</td></tr>
                    <?php else: ?>
                        <?php foreach ($settings as $row): ?>
                            <tr>
                                <td class="px-5 py-3 text-sm font-medium text-gray-900"><?= htmlspecialchars($row['key_name'] ?? '') ?></td>
                                <td class="px-5 py-3 text-sm text-gray-500"><?= htmlspecialchars($row['updated_at'] ?? '-') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Panda Video Live</h2>
            <p class="text-sm text-gray-500 mt-0.5">Configure a integração para gerar lives automaticamente no módulo Aulas Online.</p>
        </div>
        <div class="p-5 space-y-4">
            <?php
            $fields = [
                ['key' => 'panda_video_api_url', 'label' => 'PANDA_VIDEO_API_URL', 'value' => $panda_video_api_url, 'placeholder' => 'https://api-v2.pandavideo.com.br'],
                ['key' => 'panda_video_api_key', 'label' => 'PANDA_VIDEO_API_KEY', 'value' => $panda_video_api_key, 'placeholder' => 'sua-chave-api'],
                ['key' => 'panda_video_live_create_endpoint', 'label' => 'PANDA_VIDEO_LIVE_CREATE_ENDPOINT', 'value' => $panda_video_live_create_endpoint, 'placeholder' => '/lives/'],
                ['key' => 'panda_video_auth_header', 'label' => 'PANDA_VIDEO_AUTH_HEADER', 'value' => $panda_video_auth_header, 'placeholder' => 'Authorization'],
                ['key' => 'panda_video_auth_prefix', 'label' => 'PANDA_VIDEO_AUTH_PREFIX', 'value' => $panda_video_auth_prefix, 'placeholder' => 'Bearer (opcional)'],
                ['key' => 'panda_video_stream_key_id', 'label' => 'PANDA_VIDEO_STREAM_KEY_ID', 'value' => $panda_video_stream_key_id, 'placeholder' => '64ef6a89-ed21-4acc-9815-6f192cb71427'],
            ];
            ?>
            <?php foreach ($fields as $f): ?>
                <form method="POST" action="<?= URL ?>/admin/dev-settings/save" class="grid grid-cols-1 md:grid-cols-[220px_1fr_auto] gap-3 items-end">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="key_name" value="<?= htmlspecialchars($f['key']) ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($f['label']) ?></label>
                        <div class="text-xs text-gray-500">Salvo em <code class="bg-gray-100 px-1 rounded">config_dev</code></div>
                    </div>
                    <div>
                        <input type="text" name="value" value="<?= htmlspecialchars((string) $f['value']) ?>" placeholder="<?= htmlspecialchars($f['placeholder']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm">
                    </div>
                    <div>
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors font-medium hover:opacity-90">Salvar</button>
                    </div>
                </form>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Jitsi / Aulas Online</h2>
            <p class="text-sm text-gray-500 mt-0.5">Por padrão as salas usam o servidor self-hosted <code class="bg-gray-100 px-1 rounded">meet.launs.com.br</code> em modo aberto (sem JWT). Para usar o JaaS (8x8) com JWT por usuário e gravação automática, preencha App ID, API Key ID e a Private Key abaixo.</p>
        </div>
        <div class="p-5 space-y-4">
            <?php
            $jaasFields = [
                ['key' => 'jaas_app_id', 'label' => 'JAAS_APP_ID', 'value' => $jaas_app_id, 'placeholder' => 'vpaas-magic-cookie-xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx'],
                ['key' => 'jaas_api_key_id', 'label' => 'JAAS_API_KEY_ID (kid)', 'value' => $jaas_api_key_id, 'placeholder' => 'vpaas-magic-cookie-.../xxxxxxxx'],
                ['key' => 'jaas_base_url', 'label' => 'JAAS_BASE_URL', 'value' => $jaas_base_url, 'placeholder' => 'https://8x8.vc'],
                ['key' => 'jitsi_public_base_url', 'label' => 'JITSI_PUBLIC_BASE_URL', 'value' => $jitsi_public_base_url, 'placeholder' => 'https://meet.launs.com.br'],
                ['key' => 'jitsi_webhook_token', 'label' => 'JITSI_WEBHOOK_TOKEN (gravações Jibri)', 'value' => $jitsi_webhook_token ?? '', 'placeholder' => 'ex: jitsi_webhook_2026_xdq'],
                ['key' => 'jaas_webhook_secret', 'label' => 'JAAS_WEBHOOK_SECRET', 'value' => $jaas_webhook_secret, 'placeholder' => 'educatudo-jaas-recording-2026'],
                ['key' => 'jaas_webhook_signing_secret', 'label' => 'JAAS_WEBHOOK_SIGNING_SECRET', 'value' => $jaas_webhook_signing_secret, 'placeholder' => 'opcional, Reveal secret no painel JaaS'],
            ];
            ?>
            <?php foreach ($jaasFields as $f): ?>
                <form method="POST" action="<?= URL ?>/admin/dev-settings/save" class="grid grid-cols-1 md:grid-cols-[220px_1fr_auto] gap-3 items-end">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                    <input type="hidden" name="key_name" value="<?= htmlspecialchars($f['key']) ?>">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars($f['label']) ?></label>
                        <div class="text-xs text-gray-500">Salvo em <code class="bg-gray-100 px-1 rounded">config_dev</code></div>
                    </div>
                    <div>
                        <input type="text" name="value" value="<?= htmlspecialchars((string) $f['value']) ?>" placeholder="<?= htmlspecialchars($f['placeholder']) ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm">
                    </div>
                    <div>
                        <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors font-medium hover:opacity-90">Salvar</button>
                    </div>
                </form>
            <?php endforeach; ?>

            <form method="POST" action="<?= URL ?>/admin/dev-settings/save" class="grid grid-cols-1 md:grid-cols-[220px_1fr_auto] gap-3 items-start">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <input type="hidden" name="key_name" value="jaas_private_key">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">JAAS_PRIVATE_KEY</label>
                    <div class="text-xs text-gray-500">Cole a chave RSA privada. Pode ser com quebras de linha ou <code class="bg-gray-100 px-1 rounded">\n</code>.</div>
                </div>
                <div>
                    <textarea name="value" rows="7" placeholder="-----BEGIN PRIVATE KEY-----" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-xs"><?= htmlspecialchars((string) $jaas_private_key) ?></textarea>
                </div>
                <div>
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors font-medium hover:opacity-90">Salvar</button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">
        <div class="px-5 py-4 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">Prompt de Flashcards</h2>
            <p class="text-sm text-gray-500 mt-0.5">Usado pela Tudinha para gerar flashcards. Placeholders: <code class="bg-gray-100 px-1 rounded">{TOPIC}</code>, <code class="bg-gray-100 px-1 rounded">{GRADE}</code>, <code class="bg-gray-100 px-1 rounded">{QUANTITY}</code>.</p>
        </div>
        <form method="POST" action="<?= URL ?>/admin/dev-settings/save" class="p-5">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <input type="hidden" name="key_name" value="flashcard_prompt">
            <div>
                <label for="value" class="block text-sm font-medium text-gray-700 mb-2">Conteúdo do prompt</label>
                <textarea id="value" name="value" rows="14" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 font-mono text-sm" placeholder="You are Tudinha..."><?= htmlspecialchars($flashcard_prompt) ?></textarea>
            </div>
            <div class="mt-4">
                <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors font-medium hover:opacity-90">Salvar</button>
            </div>
        </form>
    </div>
</div>
