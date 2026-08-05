<?php
require_once __DIR__ . '/../../../Core/LayoutHelper.php';

$profiles = [
    'aluno' => [
        'label' => 'Aluno',
        'manifest' => URL . '/manifest-aluno.json',
        'prefix' => 'pwa_aluno'
    ],
    'professor' => [
        'label' => 'Professor',
        'manifest' => URL . '/manifest-professor.json',
        'prefix' => 'pwa_professor'
    ],
    'admin' => [
        'label' => 'Admin',
        'manifest' => URL . '/manifest-admin.json',
        'prefix' => 'pwa_admin'
    ],
    'pais' => [
        'label' => 'Pais',
        'manifest' => URL . '/manifest-pais.json',
        'prefix' => 'pwa_pais'
    ]
];
?>

<div class="space-y-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h2 class="text-lg font-semibold text-gray-900">PWA por Perfil</h2>
            <p class="text-sm text-gray-600 mt-1">Configure nome, ícones e cores de cada app instalável.</p>
        </div>
        <div class="p-6">
            <form id="pwa-settings-form" method="post" action="<?= URL ?>/admin/dev/layout/save" class="space-y-6">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">

                <?php foreach ($profiles as $key => $profile): ?>
                    <?php
                        $prefix = $profile['prefix'];
                        $name = LayoutHelper::get("{$prefix}_name", "EducaTudo {$profile['label']}");
                        $shortName = LayoutHelper::get("{$prefix}_short_name", $profile['label']);
                        $icon192 = LayoutHelper::get("{$prefix}_icon_192", LayoutHelper::getLogo1x1Url());
                        $icon512 = LayoutHelper::get("{$prefix}_icon_512", LayoutHelper::getLogo1x1Url());
                        $themeColor = LayoutHelper::get("{$prefix}_theme_color", LayoutHelper::get('primary_color', '#a855f7'));
                        $backgroundColor = LayoutHelper::get("{$prefix}_background_color", '#ffffff');
                    ?>
                    <div class="border border-gray-200 rounded-lg p-4">
                        <div class="flex items-center justify-between mb-4">
                            <div>
                                <h3 class="text-md font-semibold text-gray-900">App <?= htmlspecialchars($profile['label']) ?></h3>
                                <p class="text-xs text-gray-500">Manifest: <a class="text-indigo-600 hover:underline" href="<?= htmlspecialchars($profile['manifest']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($profile['manifest']) ?></a></p>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nome do App</label>
                                <input type="text" name="config[<?= $prefix ?>_name]" value="<?= htmlspecialchars($name) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Short Name</label>
                                <input type="text" name="config[<?= $prefix ?>_short_name]" value="<?= htmlspecialchars($shortName) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ícone 192x192 (URL)</label>
                                <div class="flex gap-2 items-center">
                                    <input type="text" id="<?= $prefix ?>_icon_192" name="config[<?= $prefix ?>_icon_192]" value="<?= htmlspecialchars($icon192) ?>" class="flex-1 border border-gray-300 rounded-lg px-3 py-2">
                                    <input type="file" id="<?= $prefix ?>_icon_file" accept="image/png,image/jpeg,image/jpg,image/gif" class="hidden">
                                    <button type="button" onclick="document.getElementById('<?= $prefix ?>_icon_file').click()" class="px-3 py-2 bg-gray-200 hover:bg-gray-300 rounded-lg text-sm font-medium text-gray-700 whitespace-nowrap">Selecionar imagem</button>
                                </div>
                                <p class="text-xs text-gray-500 mt-1">PNG ou JPG recomendado (quadrado). Será usado em 192 e 512.</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Ícone 512x512 (URL)</label>
                                <input type="text" id="<?= $prefix ?>_icon_512" name="config[<?= $prefix ?>_icon_512]" value="<?= htmlspecialchars($icon512) ?>" class="w-full border border-gray-300 rounded-lg px-3 py-2">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Theme Color</label>
                                <input type="color" name="config[<?= $prefix ?>_theme_color]" value="<?= htmlspecialchars($themeColor) ?>" class="w-full h-10 border border-gray-300 rounded-lg px-2 py-1">
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Background Color</label>
                                <input type="color" name="config[<?= $prefix ?>_background_color]" value="<?= htmlspecialchars($backgroundColor) ?>" class="w-full h-10 border border-gray-300 rounded-lg px-2 py-1">
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <div class="flex justify-end">
                    <button type="submit" class="btn-primary-custom px-4 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar PWA
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    const csrfToken = document.querySelector('input[name="_token"]')?.value;
    const uploadUrl = '<?= URL ?>/admin/dev/layout/upload';
    
    ['pwa_aluno', 'pwa_professor', 'pwa_admin'].forEach(function(prefix) {
        const fileInput = document.getElementById(prefix + '_icon_file');
        const icon192 = document.getElementById(prefix + '_icon_192');
        const icon512 = document.getElementById(prefix + '_icon_512');
        if (!fileInput || !icon192 || !icon512) return;
        
        fileInput.addEventListener('change', function() {
            const file = this.files[0];
            if (!file) return;
            if (!file.type.match(/^image\/(png|jpeg|jpg|gif)$/)) {
                alert('Selecione uma imagem (PNG, JPG ou GIF).');
                return;
            }
            const formData = new FormData();
            formData.append('image', file);
            formData.append('type', 'pwa_icon');
            formData.append('_token', csrfToken);
            
            fetch(uploadUrl, { method: 'POST', body: formData })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && data.url) {
                        icon192.value = data.url;
                        icon512.value = data.url;
                        if (typeof showNotification === 'function') {
                            showNotification('Imagem selecionada. Clique em "Salvar PWA" para aplicar.', 'success');
                        } else {
                            alert('Imagem enviada. Clique em "Salvar PWA" para aplicar.');
                        }
                    } else {
                        alert(data.error || 'Erro ao enviar imagem.');
                    }
                })
                .catch(function() {
                    alert('Erro de conexão.');
                });
            fileInput.value = '';
        });
    });
})();
</script>

