<?php require __DIR__ . '/_styles.php'; ?>

<header class="mb-8 pb-6 border-b border-gray-200">
    <a href="<?= URL ?>/admin/dev" class="text-sm text-indigo-600 hover:text-indigo-800">&larr; Configurações Avançadas</a>
    <div class="flex items-center gap-3 mt-2">
        <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-orange-100 text-orange-600 text-2xl">🔌</span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Integrações Externas</h1>
            <p class="text-sm text-gray-500 mt-0.5">Chaves de API, e-mail, webhooks e WhatsApp</p>
        </div>
    </div>
</header>

<?php
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? 'info';
if ($flash_message !== ''):
    $bg = $flash_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800');
?>
<div class="mb-6 p-4 rounded-lg border <?= $bg ?>">
    <?= htmlspecialchars($flash_message) ?>
</div>
<?php endif; ?>

<div class="space-y-6 max-w-5xl">

    <div class="dev-card">
        <div class="dev-card-header">Chaves de API</div>
        <p class="text-gray-500 text-sm mt-1 px-4 pt-2 pb-0">Configure as chaves de API utilizadas pelo sistema. Estas configurações são sensíveis e apenas acessíveis para desenvolvedores.</p>
        <div class="dev-card-body">
            <form id="api-keys-form" method="post" action="<?= URL ?>/admin/dev/api-keys/save" class="space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <?php
                require_once __DIR__ . '/../../../Core/LayoutHelper.php';

                $openai_key = LayoutHelper::get('openai_api_key', '');
                $openai_key_display = $openai_key ? (str_repeat('*', max(0, strlen($openai_key) - 4)) . substr($openai_key, -4)) : '';

                $gamma_key = LayoutHelper::get('gamma_api_key', '');
                $gamma_key_display = $gamma_key ? (str_repeat('*', max(0, strlen($gamma_key) - 4)) . substr($gamma_key, -4)) : '';

                $nanobanana_key = LayoutHelper::get('nanobanana_api_key', '');
                $nanobanana_key_display = $nanobanana_key ? (str_repeat('*', max(0, strlen($nanobanana_key) - 4)) . substr($nanobanana_key, -4)) : '';

                $replicate_key = LayoutHelper::get('replicate_api_key', '');
                $replicate_key_display = $replicate_key ? (str_repeat('*', max(0, strlen($replicate_key) - 4)) . substr($replicate_key, -4)) : '';

                $replicate_model = LayoutHelper::get('replicate_model_version', '');

                $elevenlabs_key = LayoutHelper::get('elevenlabs_api_key', '');
                $elevenlabs_key_display = $elevenlabs_key ? (str_repeat('*', max(0, strlen($elevenlabs_key) - 4)) . substr($elevenlabs_key, -4)) : '';

                $onesignal_app_id = LayoutHelper::get('onesignal_app_id', '');
                $onesignal_app_id_display = $onesignal_app_id ? (str_repeat('*', max(0, strlen($onesignal_app_id) - 4)) . substr($onesignal_app_id, -4)) : '';
                $onesignal_rest_key = LayoutHelper::get('onesignal_rest_api_key', '');
                $onesignal_rest_key_display = $onesignal_rest_key ? (str_repeat('*', max(0, strlen($onesignal_rest_key) - 4)) . substr($onesignal_rest_key, -4)) : '';
                $evolution_api_url = LayoutHelper::get('evolution_api_url', '');
                $evolution_instance = LayoutHelper::get('evolution_instance', '');
                $evolution_group_id = LayoutHelper::get('evolution_group_id', '');
                $evolution_api_key = LayoutHelper::get('evolution_api_key', '');
                $evolution_api_key_display = $evolution_api_key ? (str_repeat('*', max(0, strlen($evolution_api_key) - 4)) . substr($evolution_api_key, -4)) : '';
                ?>

                <!-- OpenAI API Key -->
                <div>
                    <label for="openai_api_key" class="block text-sm font-medium text-gray-700 mb-2">
                        OpenAI API Key
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="openai_api_key"
                            name="openai_api_key"
                            value="<?= htmlspecialchars($openai_key) ?>"
                            placeholder="sk-..."
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('openai_api_key')"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 px-3 py-1 text-sm"
                        >
                            <span id="toggle-openai">👁️ Mostrar</span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-start">
                        <?php if ($openai_key): ?>
                            <span class="text-xs text-green-600 mr-2">✓ Chave configurada</span>
                            <span class="text-xs text-gray-500">(Últimos 4 dígitos: <?= htmlspecialchars($openai_key_display) ?>)</span>
                        <?php else: ?>
                            <span class="text-xs text-yellow-600">⚠️ Chave não configurada - O sistema buscará no arquivo .env</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        A chave da OpenAI é usada para correção de redações, geração de temas e transcrição de imagens.
                        Se não configurada aqui, o sistema tentará buscar no arquivo .env.
                    </p>
                    <div class="mt-2 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs text-blue-800">
                            <strong>ℹ️ Importante:</strong> A chave salva aqui terá prioridade sobre o arquivo .env.
                            Mantenha a chave segura e não compartilhe com pessoas não autorizadas.
                        </p>
                    </div>
                </div>

                <!-- Gamma API Key -->
                <div class="mt-6">
                    <label for="gamma_api_key" class="block text-sm font-medium text-gray-700 mb-2">
                        Gamma API Key
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="gamma_api_key"
                            name="gamma_api_key"
                            value="<?= htmlspecialchars($gamma_key) ?>"
                            placeholder="Sua chave da Gamma API..."
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('gamma_api_key')"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 px-3 py-1 text-sm"
                        >
                            <span id="toggle-gamma">👁️ Mostrar</span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-start">
                        <?php if ($gamma_key): ?>
                            <span class="text-xs text-green-600 mr-2">✓ Chave configurada</span>
                            <span class="text-xs text-gray-500">(Últimos 4 dígitos: <?= htmlspecialchars($gamma_key_display) ?>)</span>
                        <?php else: ?>
                            <span class="text-xs text-yellow-600">⚠️ Chave não configurada - O sistema buscará no arquivo .env</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        A chave da Gamma é usada para gerar apresentações de slides automaticamente.
                        Se não configurada aqui, o sistema tentará buscar no arquivo .env.
                    </p>
                    <div class="mt-2 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-xs text-yellow-800">
                            <strong>⚠️ Atenção:</strong> Use APENAS a chave da <strong>Gamma API</strong> aqui.
                            Não use chaves de outros serviços (Gemini, OpenAI, etc).
                            A chave da Gamma pode ser obtida em: <a href="https://gamma.app" target="_blank" class="underline">gamma.app</a>
                        </p>
                    </div>
                    <div class="mt-2 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs text-blue-800">
                            <strong>ℹ️ Importante:</strong> A chave salva aqui terá prioridade sobre o arquivo .env.
                            Mantenha a chave segura e não compartilhe com pessoas não autorizadas.
                        </p>
                    </div>
                </div>


                <!-- Nano Banana API Key -->
                <div class="mt-6">
                    <label for="nanobanana_api_key" class="block text-sm font-medium text-gray-700 mb-2">
                        Nano Banana API Key
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="nanobanana_api_key"
                            name="nanobanana_api_key"
                            value="<?= htmlspecialchars($nanobanana_key) ?>"
                            placeholder="Sua chave da Nano Banana API..."
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('nanobanana_api_key')"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 px-3 py-1 text-sm"
                        >
                            <span id="toggle-nanobanana">👁️ Mostrar</span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-start">
                        <?php if ($nanobanana_key): ?>
                            <span class="text-xs text-green-600 mr-2">✓ Chave configurada</span>
                            <span class="text-xs text-gray-500">(Últimos 4 dígitos: <?= htmlspecialchars($nanobanana_key_display) ?>)</span>
                        <?php else: ?>
                            <span class="text-xs text-yellow-600">⚠️ Chave não configurada - O sistema buscará no arquivo .env</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        A chave da Nano Banana é usada para gerar imagens com IA no chat.
                        Se não configurada aqui, o sistema tentará buscar no arquivo .env.
                    </p>
                    <div class="mt-2 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs text-blue-800">
                            <strong>ℹ️ Importante:</strong> A chave salva aqui terá prioridade sobre o arquivo .env.
                            Obtenha sua chave em: <a href="https://nanobanana.ai" target="_blank" class="underline">nanobanana.ai</a>
                        </p>
                    </div>
                </div>

                <!-- Replicate API Key -->
                <div class="mt-6">
                    <label for="replicate_api_key" class="block text-sm font-medium text-gray-700 mb-2">
                        Replicate API Key (Token)
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="replicate_api_key"
                            name="replicate_api_key"
                            value="<?= htmlspecialchars($replicate_key) ?>"
                            placeholder="r8_..."
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('replicate_api_key')"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 px-3 py-1 text-sm"
                        >
                            <span id="toggle-replicate_api_key">👁️ Mostrar</span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-start">
                        <?php if ($replicate_key): ?>
                            <span class="text-xs text-green-600 mr-2">✓ Chave configurada</span>
                            <span class="text-xs text-gray-500">(Últimos 4 dígitos: <?= htmlspecialchars($replicate_key_display) ?>)</span>
                        <?php else: ?>
                            <span class="text-xs text-yellow-600">⚠️ Chave não configurada - O sistema buscará no arquivo .env</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        A chave do Replicate é usada para gerar imagens com IA no chat. A chave deve começar com "r8_".
                        Se não configurada aqui, o sistema tentará buscar no arquivo .env.
                    </p>
                    <div class="mt-2 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs text-blue-800">
                            <strong>ℹ️ Importante:</strong> A chave salva aqui terá prioridade sobre o arquivo .env.
                            Obtenha sua chave em: <a href="https://replicate.com" target="_blank" class="underline">replicate.com</a> → Settings → API Tokens
                        </p>
                    </div>
                </div>

                <!-- Replicate Model Version -->
                <div class="mt-6">
                    <label for="replicate_model_version" class="block text-sm font-medium text-gray-700 mb-2">
                        Replicate Model Version (Hash do Modelo)
                    </label>
                    <input
                        type="text"
                        id="replicate_model_version"
                        name="replicate_model_version"
                        value="<?= htmlspecialchars($replicate_model) ?>"
                        placeholder="stability-ai/stable-diffusion:db21e45d3f7023abc2a46ee38a23973f6dce16bb082a930b0c49861f96d1e5bf"
                        class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                        autocomplete="off"
                    />
                    <div class="mt-2 flex items-start">
                        <?php if ($replicate_model): ?>
                            <span class="text-xs text-green-600 mr-2">✓ Modelo configurado</span>
                        <?php else: ?>
                            <span class="text-xs text-yellow-600">⚠️ Modelo não configurado - Será usado o modelo padrão (Stable Diffusion)</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        Hash da versão do modelo do Replicate a ser usado para geração de imagens.
                        Se não configurado, será usado o modelo padrão Stable Diffusion.
                    </p>
                    <div class="mt-2 bg-yellow-50 border border-yellow-200 rounded-lg p-3">
                        <p class="text-xs text-yellow-800">
                            <strong>ℹ️ Dica:</strong> Você pode encontrar modelos disponíveis em:
                            <a href="https://replicate.com/explore" target="_blank" class="underline">replicate.com/explore</a>
                        </p>
                    </div>
                </div>

                <!-- ElevenLabs API Key -->
                <div class="mt-6">
                    <label for="elevenlabs_api_key" class="block text-sm font-medium text-gray-700 mb-2">
                        ElevenLabs API Key
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="elevenlabs_api_key"
                            name="elevenlabs_api_key"
                            value="<?= htmlspecialchars($elevenlabs_key) ?>"
                            placeholder="Sua chave da ElevenLabs API..."
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('elevenlabs_api_key')"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 px-3 py-1 text-sm"
                        >
                            <span id="toggle-elevenlabs">👁️ Mostrar</span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-start">
                        <?php if ($elevenlabs_key): ?>
                            <span class="text-xs text-green-600 mr-2">✓ Chave configurada</span>
                            <span class="text-xs text-gray-500">(Últimos 4 dígitos: <?= htmlspecialchars($elevenlabs_key_display) ?>)</span>
                        <?php else: ?>
                            <span class="text-xs text-yellow-600">⚠️ Chave não configurada - O sistema buscará no arquivo .env</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        A chave da ElevenLabs é usada para conversão de texto em voz e transcrição de áudio no chat.
                        Se não configurada aqui, o sistema tentará buscar no arquivo .env.
                    </p>
                    <div class="mt-2 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs text-blue-800">
                            <strong>ℹ️ Importante:</strong> A chave salva aqui terá prioridade sobre o arquivo .env.
                            Obtenha sua chave em: <a href="https://elevenlabs.io" target="_blank" class="underline">elevenlabs.io</a> → Dashboard → API Keys
                        </p>
                    </div>
                </div>

                <!-- OneSignal - App ID -->
                <div class="mt-6">
                    <label for="onesignal_app_id" class="block text-sm font-medium text-gray-700 mb-2">
                        OneSignal App ID (Notificações Push)
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="onesignal_app_id"
                            name="onesignal_app_id"
                            value="<?= htmlspecialchars($onesignal_app_id) ?>"
                            placeholder="Ex: da5b8089-bfdc-4f16-93cb-20989e46d0a6"
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('onesignal_app_id')"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 px-3 py-1 text-sm"
                        >
                            <span id="toggle-onesignal_app_id">👁️ Mostrar</span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-start">
                        <?php if ($onesignal_app_id): ?>
                            <span class="text-xs text-green-600 mr-2">✓ App ID configurado</span>
                            <span class="text-xs text-gray-500">(Últimos 4 caracteres: <?= htmlspecialchars($onesignal_app_id_display) ?>)</span>
                        <?php else: ?>
                            <span class="text-xs text-yellow-600">⚠️ Não configurado - O sistema buscará no arquivo .env (ONESIGNAL_APP_ID)</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        App ID do OneSignal (Web Push). Usado para notificações push no PWA. Obtenha em: OneSignal → Settings → Keys & IDs.
                    </p>
                </div>

                <!-- OneSignal - REST API Key -->
                <div class="mt-6">
                    <label for="onesignal_rest_api_key" class="block text-sm font-medium text-gray-700 mb-2">
                        OneSignal REST API Key
                    </label>
                    <div class="relative">
                        <input
                            type="password"
                            id="onesignal_rest_api_key"
                            name="onesignal_rest_api_key"
                            value="<?= htmlspecialchars($onesignal_rest_key) ?>"
                            placeholder="Ex: N2Q2Zj..."
                            class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                            autocomplete="off"
                        />
                        <button
                            type="button"
                            onclick="togglePasswordVisibility('onesignal_rest_api_key')"
                            class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 px-3 py-1 text-sm"
                        >
                            <span id="toggle-onesignal_rest_api_key">👁️ Mostrar</span>
                        </button>
                    </div>
                    <div class="mt-2 flex items-start">
                        <?php if ($onesignal_rest_key): ?>
                            <span class="text-xs text-green-600 mr-2">✓ Chave configurada</span>
                            <span class="text-xs text-gray-500">(Últimos 4 caracteres: <?= htmlspecialchars($onesignal_rest_key_display) ?>)</span>
                        <?php else: ?>
                            <span class="text-xs text-yellow-600">⚠️ Não configurada - O sistema buscará no arquivo .env (ONESIGNAL_REST_API_KEY)</span>
                        <?php endif; ?>
                    </div>
                    <p class="mt-1 text-xs text-gray-500">
                        REST API Key do OneSignal (User Auth Key). Necessária para o admin enviar notificações push pelo painel.
                    </p>
                    <div class="mt-2 bg-blue-50 border border-blue-200 rounded-lg p-3">
                        <p class="text-xs text-blue-800">
                            <strong>ℹ️ Importante:</strong> As chaves salvas aqui terão prioridade sobre o arquivo .env.
                        </p>
                    </div>
                </div>

                <!-- WhatsApp - Evolution API (notificação de logs no grupo) -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h4 class="text-sm font-semibold text-gray-900 mb-3">WhatsApp – Evolution API</h4>
                    <p class="text-xs text-gray-500 mb-4">Usado para enviar notificações de log (erros/avisos) para um grupo no WhatsApp. Deixe a API Key vazia para desativar.</p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="evolution_api_url" class="block text-sm font-medium text-gray-700 mb-1">URL da API</label>
                            <input type="url" id="evolution_api_url" name="evolution_api_url" value="<?= htmlspecialchars($evolution_api_url) ?>"
                                placeholder="https://evolutionapi.launs.com.br"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div>
                            <label for="evolution_instance" class="block text-sm font-medium text-gray-700 mb-1">Nome da instância</label>
                            <input type="text" id="evolution_instance" name="evolution_instance" value="<?= htmlspecialchars($evolution_instance) ?>"
                                placeholder="Lucas_Moraes_Vivo"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="md:col-span-2">
                            <label for="evolution_group_id" class="block text-sm font-medium text-gray-700 mb-1">ID do grupo (ex.: 120363406502358415@g.us)</label>
                            <input type="text" id="evolution_group_id" name="evolution_group_id" value="<?= htmlspecialchars($evolution_group_id) ?>"
                                placeholder="120363406502358415@g.us"
                                class="w-full border border-gray-300 rounded-lg px-4 py-2 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" />
                        </div>
                        <div class="md:col-span-2">
                            <label for="evolution_api_key" class="block text-sm font-medium text-gray-700 mb-1">API Key (token global da Evolution API)</label>
                            <div class="relative">
                                <input type="password" id="evolution_api_key" name="evolution_api_key" value="<?= htmlspecialchars($evolution_api_key) ?>"
                                    placeholder="Sua API Key da Evolution"
                                    class="w-full border border-gray-300 rounded-lg px-4 py-2 pr-24 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
                                    autocomplete="off" />
                                <button type="button" onclick="togglePasswordVisibility('evolution_api_key')"
                                    class="absolute right-2 top-1/2 transform -translate-y-1/2 text-gray-500 hover:text-gray-700 px-3 py-1 text-sm">
                                    <span id="toggle-evolution_api_key">👁️ Mostrar</span>
                                </button>
                            </div>
                            <?php if ($evolution_api_key): ?>
                                <p class="mt-1 text-xs text-green-600">✓ API Key configurada (últimos 4 caracteres: <?= htmlspecialchars($evolution_api_key_display) ?>)</p>
                                <p class="mt-0.5 text-xs text-gray-500">Deixe o campo em branco ao salvar para manter a chave atual.</p>
                            <?php else: ?>
                                <p class="mt-1 text-xs text-yellow-600">⚠️ Sem API Key – notificações no WhatsApp desativadas</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4">
                    <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Chaves
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Configuração de E-mail (SMTP)</h3>
            <p class="text-sm text-gray-600 mt-1">Configure o servidor SMTP para envio de e-mails (recuperação de senha, notificações, etc.)</p>
        </div>
        <div class="p-6">
            <form id="email-config-form" action="<?= URL ?>/admin/dev/email/save" method="POST">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <div class="space-y-6">
                    <div>
                        <label for="email_smtp_host" class="block text-sm font-medium text-gray-700 mb-2">
                            Servidor SMTP (Host)
                        </label>
                        <input type="text" id="email_smtp_host" name="email_smtp_host"
                               value="<?= htmlspecialchars($email_config['smtp_host'] ?? '') ?>"
                               placeholder="smtp.gmail.com"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-sm text-gray-500">Exemplo: smtp.gmail.com, smtp.outlook.com</p>
                    </div>

                    <div>
                        <label for="email_smtp_port" class="block text-sm font-medium text-gray-700 mb-2">
                            Porta SMTP
                        </label>
                        <input type="number" id="email_smtp_port" name="email_smtp_port"
                               value="<?= htmlspecialchars($email_config['smtp_port'] ?? '587') ?>"
                               placeholder="587"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-sm text-gray-500">Geralmente 587 (TLS) ou 465 (SSL)</p>
                    </div>

                    <div>
                        <label for="email_smtp_secure" class="block text-sm font-medium text-gray-700 mb-2">
                            Segurança
                        </label>
                        <select id="email_smtp_secure" name="email_smtp_secure"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <option value="tls" <?= ($email_config['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>TLS</option>
                            <option value="ssl" <?= ($email_config['smtp_secure'] ?? 'tls') === 'ssl' ? 'selected' : '' ?>>SSL</option>
                        </select>
                    </div>

                    <div>
                        <label for="email_smtp_username" class="block text-sm font-medium text-gray-700 mb-2">
                            Usuário/E-mail SMTP <span class="text-gray-400 font-normal">(Opcional)</span>
                        </label>
                        <input type="email" id="email_smtp_username" name="email_smtp_username"
                               value="<?= htmlspecialchars($email_config['smtp_username'] ?? '') ?>"
                               placeholder="seu-email@gmail.com (deixe vazio se não necessário)"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-sm text-gray-500">Alguns servidores SMTP (como smtp-relay.gmail.com) não requerem autenticação</p>
                    </div>

                    <div>
                        <label for="email_smtp_password" class="block text-sm font-medium text-gray-700 mb-2">
                            Senha SMTP <span class="text-gray-400 font-normal">(Opcional)</span>
                        </label>
                        <div class="relative">
                            <input type="password" id="email_smtp_password" name="email_smtp_password"
                                   value="<?= htmlspecialchars($email_config['smtp_password'] ?? '') ?>"
                                   placeholder="Sua senha ou senha de app (deixe vazio se não necessário)"
                                   class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                            <button type="button" onclick="togglePasswordVisibility('email_smtp_password')"
                                    id="toggle-email_smtp_password"
                                    class="absolute right-3 top-2 text-sm text-indigo-600 hover:text-indigo-800">
                                👁️ Mostrar
                            </button>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Para Gmail com autenticação, use uma "Senha de App". Para smtp-relay.gmail.com, deixe vazio.</p>
                    </div>

                    <div>
                        <label for="email_from_email" class="block text-sm font-medium text-gray-700 mb-2">
                            E-mail Remetente
                        </label>
                        <input type="email" id="email_from_email" name="email_from_email"
                               value="<?= htmlspecialchars($email_config['from_email'] ?? '') ?>"
                               placeholder="noreply@educatudo.com"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        <p class="mt-1 text-sm text-gray-500">E-mail que aparecerá como remetente</p>
                    </div>

                    <div>
                        <label for="email_from_name" class="block text-sm font-medium text-gray-700 mb-2">
                            Nome do Remetente
                        </label>
                        <input type="text" id="email_from_name" name="email_from_name"
                               value="<?= htmlspecialchars($email_config['from_name'] ?? 'EducaTudo') ?>"
                               placeholder="EducaTudo"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div>
                        <label for="email_reply_to" class="block text-sm font-medium text-gray-700 mb-2">
                            E-mail para Resposta (Opcional)
                        </label>
                        <input type="email" id="email_reply_to" name="email_reply_to"
                               value="<?= htmlspecialchars($email_config['reply_to'] ?? '') ?>"
                               placeholder="suporte@educatudo.com"
                               class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                    </div>

                    <div class="flex items-center justify-between pt-4 border-t border-gray-200">
                        <button type="button" onclick="testEmail()"
                                class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                            🧪 Testar Configuração
                        </button>
                        <button type="submit"
                                class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                            💾 Salvar Configurações
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="dev-card">
        <div class="dev-card-header">WhatsApp e Webhooks</div>
        <div class="dev-card-body grid grid-cols-1 md:grid-cols-2 gap-4">
            <div id="whatsapp-test-wrapper" class="contents" data-action="<?= htmlspecialchars(URL . '/admin/dev-settings/whatsapp-evolution-test') ?>" data-token="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <div id="whatsapp-test-message" class="hidden mb-3 p-3 rounded-lg text-sm"></div>
                <button type="button" id="whatsapp-test-btn" class="flex items-center w-full p-4 bg-emerald-50 rounded-lg hover:bg-emerald-100 transition-colors border border-emerald-200 text-left">
                    <div class="w-10 h-10 bg-emerald-500 rounded-lg flex items-center justify-center mr-4 flex-shrink-0">
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"></path>
                        </svg>
                    </div>
                    <div>
                        <h4 class="font-medium text-gray-900">Testar WhatsApp (Evolution API)</h4>
                        <p class="text-sm text-gray-600">Envia mensagem de teste para o grupo de logs</p>
                    </div>
                </button>
            </div>

            <a href="<?= URL ?>/admin/dev/webhooks" class="flex items-center p-4 bg-orange-50 rounded-lg hover:bg-orange-100 transition-colors border border-orange-200">
                <div class="w-10 h-10 bg-orange-500 rounded-lg flex items-center justify-center mr-4">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"></path>
                    </svg>
                </div>
                <div>
                    <h4 class="font-medium text-gray-900">Configurar Webhooks</h4>
                    <p class="text-sm text-gray-600">Configure integrações externas</p>
                </div>
            </a>
        </div>
    </div>

</div>

<script>
function togglePasswordVisibility(inputId) {
    const input = document.getElementById(inputId);
    const toggle = document.getElementById('toggle-' + inputId);

    if (input.type === 'password') {
        input.type = 'text';
        toggle.textContent = '🙈 Ocultar';
    } else {
        input.type = 'password';
        toggle.textContent = '👁️ Mostrar';
    }
}

function testEmail() {
    alert('Funcionalidade de teste em desenvolvimento. Configure e salve primeiro.');
}

(function() {
    const form = document.getElementById('api-keys-form');
    if (!form) return;
    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ ' + data.message);
                window.location.reload();
            } else if (data && data.error) {
                alert('❌ Erro: ' + data.error);
            } else {
                alert('❌ Erro inesperado ao salvar chaves');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('❌ Falha na conexão ao salvar chaves');
        });
    });
})();

(function() {
    const form = document.getElementById('email-config-form');
    if (!form) return;
    form.addEventListener('submit', function(ev) {
        ev.preventDefault();
        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                alert('✅ ' + data.message);
            } else if (data && data.error) {
                alert('❌ Erro: ' + data.error);
            } else {
                alert('❌ Erro inesperado ao salvar configurações de e-mail');
            }
        })
        .catch(err => {
            console.error('Erro:', err);
            alert('❌ Falha na conexão ao salvar configurações');
        });
    });
})();

(function() {
    const wrapper = document.getElementById('whatsapp-test-wrapper');
    const btn = document.getElementById('whatsapp-test-btn');
    const msgEl = document.getElementById('whatsapp-test-message');
    if (!wrapper || !btn || !msgEl) return;
    const action = wrapper.getAttribute('data-action');
    const token = wrapper.getAttribute('data-token');
    if (!action || !token) return;
    btn.addEventListener('click', function() {
        btn.disabled = true;
        var label = btn.querySelector('h4');
        var origText = label ? label.textContent : 'Testar WhatsApp (Evolution API)';
        if (label) label.textContent = 'Enviando...';
        msgEl.classList.add('hidden');
        var formData = new FormData();
        formData.append('_token', token);
        fetch(action, {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            msgEl.classList.remove('hidden');
            msgEl.textContent = data.message || (data.success ? 'Enviado.' : 'Falha.');
            msgEl.className = 'mb-3 p-3 rounded-lg text-sm ' + (data.success ? 'bg-green-50 text-green-800 border border-green-200' : 'bg-red-50 text-red-800 border border-red-200');
            if (label) label.textContent = origText;
            btn.disabled = false;
        })
        .catch(function() {
            msgEl.classList.remove('hidden');
            msgEl.textContent = 'Erro de conexão. Tente novamente.';
            msgEl.className = 'mb-3 p-3 rounded-lg text-sm bg-red-50 text-red-800 border border-red-200';
            if (label) label.textContent = origText;
            btn.disabled = false;
        });
    });
})();
</script>
