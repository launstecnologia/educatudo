<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                Layout do Sistema 🎨
            </h2>
            <p class="text-gray-600">
                Personalize a aparência do sistema com cores e imagens personalizadas
            </p>
        </div>
        <a href="<?= URL ?>/admin/dev" class="bg-gray-600 text-white px-4 py-2 rounded-lg hover:bg-gray-700 transition-colors">
            ← Voltar
        </a>
    </div>
</div>

<!-- Layout Configuration Form -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
    
    <!-- Upload de Imagens -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Imagens do Sistema</h3>
        </div>
        <div class="p-6">
            <div class="space-y-6">
                
                <!-- Logo Padrão Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Logo Padrão</label>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div id="logo-preview" class="w-20 h-20 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                                <?php if (!empty($config_layout['logo_url'])): ?>
                                    <img src="<?= htmlspecialchars($config_layout['logo_url']) ?>" alt="Logo" class="max-w-full max-h-full object-contain">
                                <?php else: ?>
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="logo-input" accept="image/*" class="hidden">
                            <button onclick="document.getElementById('logo-input').click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Escolher Logo
                            </button>
                            <p class="text-sm text-gray-500 mt-1">Formatos: JPG, PNG, GIF, SVG</p>
                        </div>
                    </div>
                </div>
                
                <!-- Logo Quadrada (1x1) Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Logo Quadrada (1x1)</label>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div id="logo-1x1-preview" class="w-16 h-16 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                                <?php if (!empty($config_layout['logo_1x1_url'])): ?>
                                    <img src="<?= htmlspecialchars($config_layout['logo_1x1_url']) ?>" alt="Logo 1x1" class="max-w-full max-h-full object-contain">
                                <?php else: ?>
                                    <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="logo-1x1-input" accept="image/*" class="hidden">
                            <button onclick="document.getElementById('logo-1x1-input').click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Escolher Logo 1x1
                            </button>
                            <p class="text-sm text-gray-500 mt-1">Para favicon e ícones quadrados</p>
                        </div>
                    </div>
                </div>
                
                <!-- Logo Horizontal Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Logo Horizontal</label>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div id="logo-horizontal-preview" class="w-24 h-16 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                                <?php if (!empty($config_layout['logo_horizontal_url'])): ?>
                                    <img src="<?= htmlspecialchars($config_layout['logo_horizontal_url']) ?>" alt="Logo Horizontal" class="max-w-full max-h-full object-contain">
                                <?php else: ?>
                                    <svg class="w-8 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="logo-horizontal-input" accept="image/*" class="hidden">
                            <button onclick="document.getElementById('logo-horizontal-input').click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Escolher Logo Horizontal
                            </button>
                            <p class="text-sm text-gray-500 mt-1">Para páginas de login e headers</p>
                        </div>
                    </div>
                </div>
                
                <!-- Logo Branca Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Logo Branca</label>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div id="logo-white-preview" class="w-20 h-20 bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                                <?php if (!empty($config_layout['logo_white_url'])): ?>
                                    <img src="<?= htmlspecialchars($config_layout['logo_white_url']) ?>" alt="Logo Branca" class="max-w-full max-h-full object-contain">
                                <?php else: ?>
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="logo-white-input" accept="image/*" class="hidden">
                            <button onclick="document.getElementById('logo-white-input').click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Escolher Logo Branca
                            </button>
                            <p class="text-sm text-gray-500 mt-1">Para fundos escuros (navbar)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Logo Horizontal Branca Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Logo Horizontal Branca</label>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div id="logo-horizontal-white-preview" class="w-24 h-16 bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                                <?php if (!empty($config_layout['logo_horizontal_white_url'])): ?>
                                    <img src="<?= htmlspecialchars($config_layout['logo_horizontal_white_url']) ?>" alt="Logo Horizontal Branca" class="max-w-full max-h-full object-contain">
                                <?php else: ?>
                                    <svg class="w-8 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="logo-horizontal-white-input" accept="image/*" class="hidden">
                            <button onclick="document.getElementById('logo-horizontal-white-input').click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Escolher Logo Horizontal Branca
                            </button>
                            <p class="text-sm text-gray-500 mt-1">Para fundos escuros (navbar)</p>
                        </div>
                    </div>
                </div>
                
                <!-- Capa de Login Upload -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Capa da Página de Login</label>
                    <div class="flex items-center space-x-4">
                        <div class="flex-shrink-0">
                            <div id="cover-preview" class="w-32 h-20 bg-gray-100 rounded-lg border-2 border-dashed border-gray-300 flex items-center justify-center">
                                <?php if (!empty($config_layout['login_cover_url'])): ?>
                                    <img src="<?= htmlspecialchars($config_layout['login_cover_url']) ?>" alt="Capa Login" class="max-w-full max-h-full object-cover rounded-lg">
                                <?php else: ?>
                                    <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                    </svg>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="flex-1">
                            <input type="file" id="cover-input" accept="image/*" class="hidden">
                            <button onclick="document.getElementById('cover-input').click()" class="bg-blue-600 text-white px-4 py-2 rounded-lg hover:bg-blue-700 transition-colors">
                                Escolher Capa
                            </button>
                            <p class="text-sm text-gray-500 mt-1">Formatos: JPG, PNG, GIF, SVG</p>
                        </div>
                    </div>
                </div>
                
            </div>
        </div>
    </div>
    
    <!-- Configuração de Cores -->
    <div class="bg-white rounded-lg shadow-sm border border-gray-200">
        <div class="p-6 border-b border-gray-200">
            <h3 class="text-lg font-semibold text-gray-900">Cores do Sistema</h3>
        </div>
        <div class="p-6">
            <form id="layout-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                
                <div class="space-y-4">
                    
                    <!-- Título do Sistema -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Título do Sistema</label>
                        <input type="text" 
                               name="config[system_title]" 
                               value="<?= htmlspecialchars($config_layout['system_title'] ?? 'EducaTudo') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="EducaTudo">
                        <p class="text-sm text-gray-500 mt-1">Nome que aparece no sistema</p>
                    </div>
                    
                    <!-- Subtítulo do Sistema -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Subtítulo do Sistema</label>
                        <input type="text" 
                               name="config[system_subtitle]" 
                               value="<?= htmlspecialchars($config_layout['system_subtitle'] ?? 'Sistema Educacional') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Sistema Educacional">
                        <p class="text-sm text-gray-500 mt-1">Subtítulo que aparece nas páginas de login</p>
                    </div>
                    
                    <!-- Tamanho da Logo -->
                    <?php
                    $tamanhosLogo = ['1' => 'Normal (100%)', '1.25' => 'Grande (125%)', '1.5' => 'Muito grande (150%)', '2' => 'Extra grande (200%)'];
                    $tamanhoNavbarAtual = (string) ($config_layout['logo_size_navbar'] ?? '1');
                    $tamanhoLoginAtual = (string) ($config_layout['logo_size_login'] ?? '1');
                    ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tamanho da logo no menu (sidebar)</label>
                        <select name="config[logo_size_navbar]"
                                id="logo-size-navbar"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                            <?php foreach ($tamanhosLogo as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= $tamanhoNavbarAtual === (string) $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Ajusta a logo no menu lateral (admin, professor e aluno)</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tamanho da logo na página de login</label>
                        <select name="config[logo_size_login]"
                                id="logo-size-login"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent bg-white">
                            <?php foreach ($tamanhosLogo as $val => $label): ?>
                                <option value="<?= htmlspecialchars($val) ?>" <?= $tamanhoLoginAtual === (string) $val ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="text-sm text-gray-500 mt-1">Ajusta a logo exibida na tela de login</p>
                    </div>
                    
                    <!-- Nome do Menu Colag (Navbar do Aluno) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Nome do Menu Colag (Navbar do Aluno)</label>
                        <input type="text" 
                               name="config[menu_colag_nome]" 
                               value="<?= htmlspecialchars($config_layout['menu_colag_nome'] ?? 'Colag') ?>"
                               class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                               placeholder="Colag">
                        <p class="text-sm text-gray-500 mt-1">Nome do menu que aparece na navbar do aluno (ex: nome da escola). Padrão: "Colag"</p>
                    </div>
                    
                    <!-- Cor Primária -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cor Primária</label>
                        <div class="flex items-center space-x-3">
                            <input type="color" 
                                   name="config[primary_color]" 
                                   value="<?= htmlspecialchars($config_layout['primary_color'] ?? '#a855f7') ?>"
                                   class="w-12 h-10 rounded border border-gray-300">
                            <input type="text" 
                                   name="config[primary_color]" 
                                   value="<?= htmlspecialchars($config_layout['primary_color'] ?? '#a855f7') ?>"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="#a855f7">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Usada em botões principais e elementos destacados</p>
                    </div>
                    
                    <!-- Cor do Texto Primário -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cor do Texto Primário</label>
                        <div class="flex items-center space-x-3">
                            <input type="color" 
                                   name="config[primary_text_color]" 
                                   value="<?= htmlspecialchars($config_layout['primary_text_color'] ?? '#ffffff') ?>"
                                   class="w-12 h-10 rounded border border-gray-300">
                            <input type="text" 
                                   name="config[primary_text_color]" 
                                   value="<?= htmlspecialchars($config_layout['primary_text_color'] ?? '#ffffff') ?>"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="#ffffff">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Cor do texto sobre fundo primário</p>
                    </div>
                    
                    <!-- Cor Secundária -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cor Secundária</label>
                        <div class="flex items-center space-x-3">
                            <input type="color" 
                                   name="config[secondary_color]" 
                                   value="<?= htmlspecialchars($config_layout['secondary_color'] ?? '#0ea5e9') ?>"
                                   class="w-12 h-10 rounded border border-gray-300">
                            <input type="text" 
                                   name="config[secondary_color]" 
                                   value="<?= htmlspecialchars($config_layout['secondary_color'] ?? '#0ea5e9') ?>"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="#0ea5e9">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Usada em botões secundários e elementos complementares</p>
                    </div>
                    
                    <!-- Cor do Texto Secundário -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Cor do Texto Secundário</label>
                        <div class="flex items-center space-x-3">
                            <input type="color" 
                                   name="config[secondary_text_color]" 
                                   value="<?= htmlspecialchars($config_layout['secondary_text_color'] ?? '#ffffff') ?>"
                                   class="w-12 h-10 rounded border border-gray-300">
                            <input type="text" 
                                   name="config[secondary_text_color]" 
                                   value="<?= htmlspecialchars($config_layout['secondary_text_color'] ?? '#ffffff') ?>"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="#ffffff">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Cor do texto sobre fundo secundário</p>
                    </div>
                    
                    <!-- Background da Página -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Background da Página</label>
                        <div class="flex items-center space-x-3">
                            <input type="color" 
                                   name="config[page_background_color]" 
                                   value="<?= htmlspecialchars($config_layout['page_background_color'] ?? '#f8fafc') ?>"
                                   class="w-12 h-10 rounded border border-gray-300">
                            <input type="text" 
                                   name="config[page_background_color]" 
                                   value="<?= htmlspecialchars($config_layout['page_background_color'] ?? '#f8fafc') ?>"
                                   class="flex-1 px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-transparent"
                                   placeholder="#f8fafc">
                        </div>
                        <p class="text-sm text-gray-500 mt-1">Cor de fundo principal das páginas</p>
                    </div>
                    
                </div>
                
                <div class="mt-6 flex space-x-3">
                    <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">
                        Salvar Configurações
                    </button>
                    <button type="button" onclick="resetColors()" class="bg-gray-600 text-white px-6 py-2 rounded-lg hover:bg-gray-700 transition-colors">
                        Restaurar Padrão
                    </button>
                </div>
                
            </form>
        </div>
    </div>
    
</div>

<!-- Preview Section -->
<div class="mt-8 bg-white rounded-lg shadow-sm border border-gray-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Preview das Cores</h3>
    </div>
    <div class="p-6">
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
            
            <!-- Botão Primário -->
            <div class="text-center">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Botão Primário</h4>
                <button id="preview-primary-btn" class="px-4 py-2 rounded-lg text-white font-medium">
                    Botão Primário
                </button>
            </div>
            
            <!-- Botão Secundário -->
            <div class="text-center">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Botão Secundário</h4>
                <button id="preview-secondary-btn" class="px-4 py-2 rounded-lg text-white font-medium">
                    Botão Secundário
                </button>
            </div>
            
            <!-- Card -->
            <div class="text-center">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Card</h4>
                <div id="preview-card" class="p-4 rounded-lg border">
                    <h5 class="font-medium mb-1">Título do Card</h5>
                    <p class="text-sm text-gray-600">Conteúdo do card</p>
                </div>
            </div>
            
            <!-- Navbar -->
            <div class="text-center">
                <h4 class="text-sm font-medium text-gray-700 mb-2">Navbar</h4>
                <div id="preview-navbar" class="px-4 py-2 rounded-lg text-white font-medium">
                    Navbar
                </div>
            </div>
            
        </div>
    </div>
</div>

<script>
// Sincronizar inputs de cor
document.querySelectorAll('input[type="color"]').forEach(colorInput => {
    const textInput = colorInput.parentElement.querySelector('input[type="text"]');
    
    colorInput.addEventListener('input', function() {
        textInput.value = this.value;
        updatePreview();
    });
    
    textInput.addEventListener('input', function() {
        if (/^#[0-9A-F]{6}$/i.test(this.value)) {
            colorInput.value = this.value;
            updatePreview();
        }
    });
});

// Upload de imagens
document.getElementById('logo-input').addEventListener('change', function(e) {
    uploadImage(e.target.files[0], 'logo');
});

document.getElementById('logo-1x1-input').addEventListener('change', function(e) {
    uploadImage(e.target.files[0], 'logo-1x1');
});

document.getElementById('logo-horizontal-input').addEventListener('change', function(e) {
    uploadImage(e.target.files[0], 'logo-horizontal');
});

document.getElementById('logo-white-input').addEventListener('change', function(e) {
    uploadImage(e.target.files[0], 'logo-white');
});

document.getElementById('logo-horizontal-white-input').addEventListener('change', function(e) {
    uploadImage(e.target.files[0], 'logo-horizontal-white');
});

document.getElementById('cover-input').addEventListener('change', function(e) {
    uploadImage(e.target.files[0], 'cover');
});

function uploadImage(file, type) {
    if (!file) return;
    
    const formData = new FormData();
    formData.append('image', file);
    formData.append('type', type);
    formData.append('_token', <?= json_encode($csrf_token) ?>);
    
    fetch('<?= URL ?>/admin/dev/layout/upload', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Atualizar preview
            const previewId = type + '-preview';
            const preview = document.getElementById(previewId);
            if (preview) {
                preview.innerHTML = `<img src="${data.url}" alt="${type}" class="max-w-full max-h-full object-contain">`;
            }
            
            showNotification('Imagem enviada com sucesso!', 'success');
            setTimeout(function () { window.location.reload(); }, 600);
        } else {
            showNotification('Erro ao enviar imagem: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
    });
}

// Salvar configurações
document.getElementById('layout-form').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('<?= URL ?>/admin/dev/layout/save', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showNotification(data.message, 'success');
        } else {
            showNotification('Erro: ' + data.error, 'error');
        }
    })
    .catch(error => {
        showNotification('Erro de conexão', 'error');
    });
});

// Atualizar preview
function updatePreview() {
    const primaryColor = document.querySelector('input[name="config[primary_color]"]').value;
    const primaryTextColor = document.querySelector('input[name="config[primary_text_color]"]').value;
    const secondaryColor = document.querySelector('input[name="config[secondary_color]"]').value;
    const secondaryTextColor = document.querySelector('input[name="config[secondary_text_color]"]').value;
    
    // Botão primário
    const primaryBtn = document.getElementById('preview-primary-btn');
    primaryBtn.style.backgroundColor = primaryColor;
    primaryBtn.style.color = primaryTextColor;
    
    // Botão secundário
    const secondaryBtn = document.getElementById('preview-secondary-btn');
    secondaryBtn.style.backgroundColor = secondaryColor;
    secondaryBtn.style.color = secondaryTextColor;
    
    // Navbar
    const navbar = document.getElementById('preview-navbar');
    navbar.style.backgroundColor = primaryColor;
    navbar.style.color = primaryTextColor;
}

// Restaurar cores padrão
function resetColors() {
    if (confirm('Restaurar cores padrão?')) {
        document.querySelector('input[name="config[primary_color]"]').value = '#a855f7';
        document.querySelector('input[name="config[primary_text_color]"]').value = '#ffffff';
        document.querySelector('input[name="config[secondary_color]"]').value = '#0ea5e9';
        document.querySelector('input[name="config[secondary_text_color]"]').value = '#ffffff';
        
        // Atualizar inputs de cor
        document.querySelectorAll('input[type="color"]').forEach(input => {
            const textInput = input.parentElement.querySelector('input[type="text"]');
            input.value = textInput.value;
        });
        
        updatePreview();
    }
}

function aplicarTamanhoLogoNavbar() {
    const selectNavbar = document.getElementById('logo-size-navbar');
    if (!selectNavbar) {
        return;
    }
    const escala = parseFloat(selectNavbar.value) || 1;
    document.documentElement.style.setProperty('--logo-navbar-size', (escala * 2.5) + 'rem');
}

const selectLogoNavbar = document.getElementById('logo-size-navbar');
if (selectLogoNavbar) {
    selectLogoNavbar.addEventListener('change', aplicarTamanhoLogoNavbar);
}

// Atualizar preview inicial
updatePreview();
aplicarTamanhoLogoNavbar();

</script>
