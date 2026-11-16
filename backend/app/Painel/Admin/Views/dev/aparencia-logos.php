<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">Logos</h2>
            <p class="text-gray-600">Padrão do sistema, navbar, tela inicial, capa do login e o tamanho de cada logo.</p>
        </div>
        <a href="<?= URL ?>/admin/dev/aparencia" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Imagens</p>
                    <p class="text-sm text-gray-500 mt-0.5">O envio vale na hora. Formatos: JPG, PNG, GIF ou WebP.</p>
                </div>
            </div>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php
                $logoNavbarPreview = trim((string) ($config_layout['logo_navbar_url'] ?? ''));
                if ($logoNavbarPreview === '') {
                    $logoNavbarPreview = (string) \App\Core\LayoutHelper::getNavbarLogoUrl();
                }
                $logoLoginPreview = trim((string) ($config_layout['logo_login_url'] ?? ''));
                if ($logoLoginPreview === '') {
                    $logoLoginPreview = (string) \App\Core\LayoutHelper::getLoginLogoUrl();
                }
                $logoSlots = [
                    [
                        'type' => 'logo',
                        'label' => 'Padrão do sistema',
                        'hint' => 'Vai em documentos, declarações, boletins e PDFs.',
                        'url' => (string) ($config_layout['logo_url'] ?? ''),
                        'box' => 'w-20 h-20 bg-white',
                        'button' => 'Escolher logo padrão',
                        'img' => 'object-contain',
                    ],
                    [
                        'type' => 'logo-navbar',
                        'label' => 'Logo do navbar',
                        'hint' => 'Menu lateral (admin, professor, aluno e pais). Prefira versão clara para fundo escuro.',
                        'url' => $logoNavbarPreview,
                        'box' => 'w-24 h-16 bg-gray-800',
                        'button' => 'Escolher logo do navbar',
                        'img' => 'object-contain',
                    ],
                    [
                        'type' => 'logo-login',
                        'label' => 'Logo da tela inicial',
                        'hint' => 'Páginas de login e recuperação de senha.',
                        'url' => $logoLoginPreview,
                        'box' => 'w-24 h-16 bg-white',
                        'button' => 'Escolher logo da tela inicial',
                        'img' => 'object-contain',
                    ],
                    [
                        'type' => 'cover',
                        'label' => 'Capa do login',
                        'hint' => 'Imagem ao lado do formulário de login.',
                        'url' => (string) ($config_layout['login_cover_url'] ?? ''),
                        'box' => 'w-32 h-20 bg-gray-100',
                        'button' => 'Escolher capa',
                        'img' => 'object-cover rounded-lg',
                    ],
                ];
                foreach ($logoSlots as $slot):
                    $previewId = $slot['type'] . '-preview';
                    $inputId = $slot['type'] . '-input';
                ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars($slot['label']) ?></label>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div id="<?= htmlspecialchars($previewId) ?>"
                                 class="<?= htmlspecialchars($slot['box']) ?> rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center overflow-hidden">
                                <?php if ($slot['url'] !== ''): ?>
                                    <img src="<?= htmlspecialchars($slot['url']) ?>" alt="<?= htmlspecialchars($slot['label']) ?>" class="max-w-full max-h-full <?= htmlspecialchars($slot['img']) ?>">
                                <?php else: ?>
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="<?= htmlspecialchars($inputId) ?>" accept="image/jpeg,image/png,image/gif,image/webp" class="hidden" data-logo-upload="<?= htmlspecialchars($slot['type']) ?>">
                            <button type="button" onclick="document.getElementById('<?= htmlspecialchars($inputId) ?>').click()" class="btn-primary-custom px-4 py-2 rounded-lg hover:opacity-90 transition-colors">
                                <?= htmlspecialchars($slot['button']) ?>
                            </button>
                            <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($slot['hint']) ?></p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">2</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Tamanho e carteirinha</p>
                    <p class="text-sm text-gray-500 mt-0.5">A escala vale no menu e na tela de login. A carteirinha usa uma das logos acima.</p>
                </div>
            </div>
        </div>
        <div class="p-5">
            <form id="layout-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <?php
                $tamanhosLogo = ['1' => 'Normal (100%)', '1.25' => 'Grande (125%)', '1.5' => 'Muito grande (150%)', '2' => 'Extra grande (200%)'];
                $tamanhoNavbarAtual = (string) ($config_layout['logo_size_navbar'] ?? '1');
                $tamanhoLoginAtual = (string) ($config_layout['logo_size_login'] ?? '1');
                $logoCarteirinhaAtual = (string) ($config_layout['carteirinha_logo'] ?? 'padrao');
                if (!in_array($logoCarteirinhaAtual, ['padrao', 'navbar', 'login'], true)) {
                    $logoCarteirinhaAtual = 'padrao';
                }
                ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="logo-size-navbar">Tamanho da logo no menu</label>
                        <select name="config[logo_size_navbar]" id="logo-size-navbar" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                            <?php foreach ($tamanhosLogo as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= $tamanhoNavbarAtual === (string) $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Menu lateral de admin, professor e aluno.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="logo-size-login">Tamanho da logo na página de login</label>
                        <select name="config[logo_size_login]" id="logo-size-login" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                            <?php foreach ($tamanhosLogo as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= $tamanhoLoginAtual === (string) $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Logo exibida na tela de login.</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2" for="carteirinha-logo">Logo da carteirinha</label>
                        <select name="config[carteirinha_logo]" id="carteirinha-logo" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent bg-white">
                            <option value="padrao" <?= $logoCarteirinhaAtual === 'padrao' ? 'selected' : '' ?>>Padrão do sistema</option>
                            <option value="navbar" <?= $logoCarteirinhaAtual === 'navbar' ? 'selected' : '' ?>>Logo do navbar</option>
                            <option value="login" <?= $logoCarteirinhaAtual === 'login' ? 'selected' : '' ?>>Logo da tela inicial</option>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Qual logo aparece na faixa da carteirinha.</p>
                    </div>
                </div>
                <div class="flex justify-end">
                    <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">Salvar tamanhos</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-logo-upload]').forEach(function (input) {
    input.addEventListener('change', function () {
        var file = input.files && input.files[0];
        var type = input.getAttribute('data-logo-upload');
        if (!file || !type) return;
        var formData = new FormData();
        formData.append('image', file);
        formData.append('type', type);
        formData.append('_token', <?= json_encode($csrf_token) ?>);
        fetch('<?= URL ?>/admin/dev/layout/upload', { method: 'POST', body: formData })
            .then(function (response) { return response.json(); })
            .then(function (data) {
                if (data && data.success) {
                    showNotification('Imagem enviada com sucesso!', 'success');
                    setTimeout(function () { window.location.reload(); }, 600);
                } else {
                    showNotification('Erro ao enviar imagem: ' + ((data && data.error) ? data.error : ''), 'error');
                }
            })
            .catch(function () { showNotification('Erro de conexão', 'error'); });
    });
});

function aplicarTamanhoLogoNavbar() {
    var selectNavbar = document.getElementById('logo-size-navbar');
    if (!selectNavbar) return;
    var escala = parseFloat(selectNavbar.value) || 1;
    document.documentElement.style.setProperty('--logo-navbar-size', (escala * 2.5) + 'rem');
}
var selectLogoNavbar = document.getElementById('logo-size-navbar');
if (selectLogoNavbar) {
    selectLogoNavbar.addEventListener('change', aplicarTamanhoLogoNavbar);
    aplicarTamanhoLogoNavbar();
}
</script>
<?php include __DIR__ . '/_salvar_layout_js.php'; ?>
