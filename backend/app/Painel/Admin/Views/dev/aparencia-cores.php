<?php
$corPrimariaAtual = (string) ($config_layout['primary_color'] ?? '#a855f7');
$navbarBgAtual = (string) ($config_layout['navbar_bg_color'] ?? $corPrimariaAtual);
$sidebarTextAtual = (string) ($config_layout['sidebar_text_color'] ?? '');
if ($sidebarTextAtual === '') {
    $sidebarTextAtual = \App\Core\LayoutHelper::getSidebarColors()['text'];
}
$botaoPrimarioAtual = (string) ($config_layout['button_primary_color'] ?? $corPrimariaAtual);
$corBarraCarteirinha = (string) ($config_layout['carteirinha_barra_cor'] ?? '');
if ($corBarraCarteirinha === '') {
    $corBarraCarteirinha = '#0f766e';
}
$camposCor = [
    ['chave' => 'primary_color', 'label' => 'Cor primária', 'valor' => $corPrimariaAtual, 'placeholder' => '#a855f7', 'ajuda' => 'Destaque geral (links, ícones e acentos). Navbar e botões têm cores próprias abaixo.'],
    ['chave' => 'primary_text_color', 'label' => 'Cor do texto primário', 'valor' => (string) ($config_layout['primary_text_color'] ?? '#ffffff'), 'placeholder' => '#ffffff', 'ajuda' => 'Texto sobre botões primários e fundos de destaque.'],
    ['chave' => 'button_primary_color', 'label' => 'Cor dos botões primários', 'valor' => $botaoPrimarioAtual, 'placeholder' => '#a855f7', 'ajuda' => 'Fundo dos botões de ação principal. Padrão: cor primária.'],
    ['chave' => 'navbar_bg_color', 'label' => 'Background do navbar', 'valor' => $navbarBgAtual, 'placeholder' => '#a855f7', 'ajuda' => 'Fundo do menu lateral (admin, professor, aluno e pais). Padrão: cor primária.'],
    ['chave' => 'sidebar_text_color', 'label' => 'Cor do texto do menu e submenu', 'valor' => $sidebarTextAtual, 'placeholder' => '#ffffff', 'ajuda' => 'Texto dos itens do menu e dos submenus. Padrão: contraste automático no fundo do navbar.'],
    ['chave' => 'secondary_color', 'label' => 'Cor secundária', 'valor' => (string) ($config_layout['secondary_color'] ?? '#0ea5e9'), 'placeholder' => '#0ea5e9', 'ajuda' => 'Botões secundários e elementos complementares.'],
    ['chave' => 'secondary_text_color', 'label' => 'Cor do texto secundário', 'valor' => (string) ($config_layout['secondary_text_color'] ?? '#ffffff'), 'placeholder' => '#ffffff', 'ajuda' => 'Texto sobre fundo secundário.'],
    ['chave' => 'page_background_color', 'label' => 'Background da página', 'valor' => (string) ($config_layout['page_background_color'] ?? '#f8fafc'), 'placeholder' => '#f8fafc', 'ajuda' => 'Cor de fundo principal das páginas.'],
    ['chave' => 'carteirinha_barra_cor', 'label' => 'Cor da barra da carteirinha', 'valor' => $corBarraCarteirinha, 'placeholder' => '#0f766e', 'ajuda' => 'Faixa do cartão. Prefira uma cor escura: o texto dela é branco.'],
];
?>
<?php $aba_aparencia = 'cores'; include __DIR__ . '/_aparencia_abas.php'; ?>

<div class="space-y-6">
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <div class="flex items-center gap-3 min-w-0">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">1</span>
                <div>
                    <p class="text-sm font-semibold text-gray-900">Paleta</p>
                    <p class="text-sm text-gray-500 mt-0.5">A prévia abaixo acompanha o que você escolhe antes de salvar.</p>
                </div>
            </div>
        </div>
        <div class="p-5">
            <form id="layout-form">
                <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <?php foreach ($camposCor as $campo): ?>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2"><?= htmlspecialchars($campo['label']) ?></label>
                        <div class="flex items-center space-x-3">
                            <input type="color" name="config[<?= htmlspecialchars($campo['chave']) ?>]" value="<?= htmlspecialchars($campo['valor']) ?>" class="w-12 h-10 rounded border border-gray-300">
                            <input type="text" name="config[<?= htmlspecialchars($campo['chave']) ?>]" value="<?= htmlspecialchars($campo['valor']) ?>" class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-purple-500 focus:border-transparent" placeholder="<?= htmlspecialchars($campo['placeholder']) ?>">
                        </div>
                        <p class="text-sm text-gray-500 mt-1"><?= htmlspecialchars($campo['ajuda']) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="flex justify-end space-x-4">
                    <button type="button" data-csp-click="resetColors" class="px-6 py-2 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50">Restaurar padrão</button>
                    <button type="submit" class="btn-primary-custom px-6 py-2 rounded-lg transition-colors hover:opacity-90">Salvar cores</button>
                </div>
            </form>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white shadow-sm w-full overflow-hidden">
        <div class="flex flex-wrap items-center gap-3 border-b border-gray-200 bg-gray-50 px-5 py-4">
            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-teal-600 text-sm font-semibold text-white">2</span>
            <div>
                <p class="text-sm font-semibold text-gray-900">Prévia</p>
                <p class="text-sm text-gray-500 mt-0.5">Botões, card e menu com as cores desta tela.</p>
            </div>
        </div>
        <div class="p-5">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="text-center">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Botão primário</h4>
                    <button id="preview-primary-btn" type="button" class="px-4 py-2 rounded-lg text-white font-medium">Botão primário</button>
                </div>
                <div class="text-center">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Botão secundário</h4>
                    <button id="preview-secondary-btn" type="button" class="px-4 py-2 rounded-lg text-white font-medium">Botão secundário</button>
                </div>
                <div class="text-center">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Card</h4>
                    <div id="preview-card" class="p-4 rounded-lg border">
                        <h5 class="font-medium mb-1">Título do card</h5>
                        <p class="text-sm text-gray-600">Conteúdo do card</p>
                    </div>
                </div>
                <div class="text-center">
                    <h4 class="text-sm font-medium text-gray-700 mb-2">Navbar / submenu</h4>
                    <div id="preview-navbar" class="px-4 py-3 rounded-lg text-left">
                        <div class="font-medium">Menu</div>
                        <div class="text-sm mt-1 pl-3 border-l border-current/30">Submenu</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('input[type="color"]').forEach(function (colorInput) {
    var textInput = colorInput.parentElement.querySelector('input[type="text"]');
    colorInput.addEventListener('input', function () {
        textInput.value = colorInput.value;
        updatePreview();
    });
    textInput.addEventListener('input', function () {
        if (/^#[0-9A-F]{6}$/i.test(textInput.value)) {
            colorInput.value = textInput.value;
            updatePreview();
        }
    });
});

function valorCorCampo(nome) {
    var inputs = document.querySelectorAll('input[name="' + nome + '"]');
    if (!inputs.length) return '';
    return inputs[inputs.length - 1].value;
}

function updatePreview() {
    var primaryTextColor = valorCorCampo('config[primary_text_color]');
    var secondaryColor = valorCorCampo('config[secondary_color]');
    var secondaryTextColor = valorCorCampo('config[secondary_text_color]');
    var buttonPrimaryColor = valorCorCampo('config[button_primary_color]');
    var navbarBgColor = valorCorCampo('config[navbar_bg_color]');
    var sidebarTextColor = valorCorCampo('config[sidebar_text_color]');
    var pageBg = valorCorCampo('config[page_background_color]');
    var primaryBtn = document.getElementById('preview-primary-btn');
    primaryBtn.style.backgroundColor = buttonPrimaryColor;
    primaryBtn.style.color = primaryTextColor;
    var secondaryBtn = document.getElementById('preview-secondary-btn');
    secondaryBtn.style.backgroundColor = secondaryColor;
    secondaryBtn.style.color = secondaryTextColor;
    var navbar = document.getElementById('preview-navbar');
    navbar.style.backgroundColor = navbarBgColor;
    navbar.style.color = sidebarTextColor;
    var card = document.getElementById('preview-card');
    card.style.backgroundColor = pageBg;
}

function resetColors() {
    if (!confirm('Restaurar cores padrão?')) return;
    var padroes = {
        'config[primary_color]': '#a855f7',
        'config[primary_text_color]': '#ffffff',
        'config[button_primary_color]': '#a855f7',
        'config[navbar_bg_color]': '#a855f7',
        'config[sidebar_text_color]': '#ffffff',
        'config[secondary_color]': '#0ea5e9',
        'config[secondary_text_color]': '#ffffff',
        'config[page_background_color]': '#f8fafc',
        'config[carteirinha_barra_cor]': '#0f766e'
    };
    Object.keys(padroes).forEach(function (nome) {
        document.querySelectorAll('input[name="' + nome + '"]').forEach(function (input) {
            input.value = padroes[nome];
        });
    });
    updatePreview();
}

updatePreview();
</script>
<?php include __DIR__ . '/_salvar_layout_js.php'; ?>
