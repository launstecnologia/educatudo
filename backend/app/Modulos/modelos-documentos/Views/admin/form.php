<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modelo = $modelo ?? null;
$isEdit = !empty($modelo['id']);
$placeholders = $placeholders ?? [];

require_once __DIR__ . '/../../../../Views/components/wysiwyg.php';
require_once __DIR__ . '/../../Services/ModeloDocumentoService.php';

use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;

$codigoSistema = $isEdit && ModeloDocumentoService::isCodigoSistema((string) ($modelo['codigo'] ?? ''));
$ori = ($modelo['orientacao'] ?? 'retrato') === 'paisagem' ? 'paisagem' : 'retrato';

$page_header_back_url = URL . '/admin/modelos-documentos';
$page_header_title = $isEdit ? 'Editar modelo' : 'Novo modelo de documento';
$page_header_subtitle = $isEdit
    ? (string) ($modelo['nome'] ?? '')
    : 'Monte o texto com formatação (negrito, listas, títulos) e placeholders.';
ob_start();
if ($isEdit): ?>
<a href="<?= URL ?>/admin/modelos-documentos/<?= (int) $modelo['id'] ?>/preview"
   target="_blank" rel="noopener"
   class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50 mr-2">
    <i class="fa-solid fa-eye mr-1.5"></i> Ver modelo (PDF)
</a>
<?php endif;
$page_header_actions = ob_get_clean();
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (empty($schema_pronto)): ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
    Migration <code>2026_08_06_secretaria_modelos_documentos.sql</code> pendente neste tenant.
</div>
<?php endif; ?>

<style>
.ck-wysiwyg-box .ck-editor__editable { min-height: 12rem !important; }
#wy_corpo_html_box .ck-editor__editable { min-height: 22rem !important; }
</style>

<div class="bg-white rounded-lg shadow-sm border border-gray-200 w-full">
    <form method="post"
          action="<?= $isEdit ? URL . '/admin/modelos-documentos/' . (int) $modelo['id'] . '/edit' : URL . '/admin/modelos-documentos' ?>"
          enctype="multipart/form-data"
          class="divide-y divide-gray-200">
        <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
        <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Identificação</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">
                        Nome <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nome" name="nome" required
                           value="<?= $esc($modelo['nome'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="Ex.: Contrato de Matrícula 2026">
                </div>
                <div>
                    <label for="codigo" class="block text-sm font-medium text-gray-700 mb-2">
                        Código <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="codigo" name="codigo" required
                           value="<?= $esc($modelo['codigo'] ?? '') ?>"
                           <?= $codigoSistema ? 'readonly' : '' ?>
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 <?= $codigoSistema ? 'bg-gray-50' : '' ?>"
                           placeholder="Ex.: contrato_customizado">
                    <p class="mt-1 text-xs text-gray-500">Identificador interno. Modelos do sistema (<code>contrato_*</code>) têm código fixo.</p>
                </div>
                <div class="md:col-span-2">
                    <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <input type="text" id="descricao" name="descricao"
                           value="<?= $esc($modelo['descricao'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div>
                    <span class="block text-sm font-medium text-gray-700 mb-2">Orientação da página</span>
                    <div class="flex flex-wrap gap-4 mt-1">
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="orientacao" value="retrato"
                                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50"
                                   <?= $ori === 'retrato' ? 'checked' : '' ?>>
                            Vertical (retrato)
                        </label>
                        <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="orientacao" value="paisagem"
                                   class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50"
                                   <?= $ori === 'paisagem' ? 'checked' : '' ?>>
                            Horizontal (paisagem)
                        </label>
                    </div>
                </div>
                <div class="flex items-end">
                    <label class="flex items-center">
                        <input type="checkbox" name="ativo" value="1"
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50"
                               <?= !isset($modelo['ativo']) || !empty($modelo['ativo']) ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Modelo ativo (disponível para emissão)</span>
                    </label>
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Imagens (opcional)</h3>
                <p class="text-sm text-gray-600 mt-1">
                    PNG ou JPG · máx. 5MB. No PDF a imagem é redimensionada automaticamente para a largura do A4.
                </p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="imagem_cabecalho" class="block text-sm font-medium text-gray-700 mb-2">Imagem de cabeçalho</label>
                    <?php if (!empty($preview_cabecalho)): ?>
                    <div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                        <img src="<?= $esc($preview_cabecalho) ?>" alt="Cabeçalho" class="max-h-24 mx-auto object-contain">
                    </div>
                    <label class="flex items-center mb-2">
                        <input type="checkbox" name="remover_imagem_cabecalho" value="1"
                               class="rounded border-gray-300 text-red-600 shadow-sm focus:ring focus:ring-red-200 focus:ring-opacity-50">
                        <span class="ml-2 text-xs text-red-600">Remover imagem atual</span>
                    </label>
                    <?php endif; ?>
                    <input type="file" id="imagem_cabecalho" name="imagem_cabecalho" accept="image/png,image/jpeg"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 file:font-medium hover:file:bg-gray-200">
                </div>
                <div>
                    <label for="imagem_rodape" class="block text-sm font-medium text-gray-700 mb-2">Imagem de rodapé</label>
                    <?php if (!empty($preview_rodape)): ?>
                    <div class="mb-2 rounded-lg border border-gray-200 bg-gray-50 p-2">
                        <img src="<?= $esc($preview_rodape) ?>" alt="Rodapé" class="max-h-20 mx-auto object-contain">
                    </div>
                    <label class="flex items-center mb-2">
                        <input type="checkbox" name="remover_imagem_rodape" value="1"
                               class="rounded border-gray-300 text-red-600 shadow-sm focus:ring focus:ring-red-200 focus:ring-opacity-50">
                        <span class="ml-2 text-xs text-red-600">Remover imagem atual</span>
                    </label>
                    <?php endif; ?>
                    <input type="file" id="imagem_rodape" name="imagem_rodape" accept="image/png,image/jpeg"
                           class="w-full text-sm text-gray-600 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:bg-gray-100 file:text-gray-700 file:font-medium hover:file:bg-gray-200">
                </div>
            </div>
        </div>

        <div class="p-6 space-y-6">
            <h3 class="text-lg font-semibold text-gray-900">Conteúdo</h3>
            <div id="wy_cabecalho_html_box" class="md:col-span-2">
                <?php wysiwyg_field([
                    'name' => 'cabecalho_html',
                    'label' => 'Cabeçalho',
                    'value' => $modelo['cabecalho_html'] ?? '',
                    'rows' => 6,
                    'help' => 'Logo, nome da escola, título do documento.',
                ]); ?>
            </div>
            <div id="wy_corpo_html_box">
                <?php wysiwyg_field([
                    'name' => 'corpo_html',
                    'label' => 'Corpo',
                    'value' => $modelo['corpo_html'] ?? '',
                    'required' => true,
                    'rows' => 14,
                    'help' => 'Texto principal — edite como no Word (negrito, listas, títulos).',
                ]); ?>
            </div>
            <div id="wy_rodape_html_box">
                <?php wysiwyg_field([
                    'name' => 'rodape_html',
                    'label' => 'Rodapé',
                    'value' => $modelo['rodape_html'] ?? '',
                    'rows' => 5,
                    'help' => 'Assinaturas, endereço, numeração.',
                ]); ?>
            </div>
        </div>

        <div class="p-6 space-y-4">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Placeholders disponíveis</h3>
                <p class="text-sm text-gray-600 mt-1">Clique para copiar e cole no texto do editor.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <?php foreach ($placeholders as $key => $label): ?>
                <button type="button"
                        class="js-copy-ph inline-flex items-center px-2.5 py-1 rounded-lg border border-gray-300 bg-white text-xs font-medium text-gray-700 hover:bg-gray-50 transition-colors"
                        data-ph="{{<?= $esc($key) ?>}}"
                        title="<?= $esc($label) ?>">
                    {{<?= $esc($key) ?>}}
                </button>
                <?php endforeach; ?>
            </div>
        </div>

        <?php
        $form_cancel_url = URL . '/admin/modelos-documentos';
        $form_submit_label = 'Salvar modelo';
        include __DIR__ . '/../../../../Views/admin/_partials/form_actions.php';
        ?>
    </form>
</div>

<script>
document.querySelectorAll('.js-copy-ph').forEach(function (btn) {
    btn.addEventListener('click', function () {
        var t = btn.getAttribute('data-ph') || '';
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(t).then(function () {
                btn.classList.add('ring-2', 'ring-green-400');
                setTimeout(function () { btn.classList.remove('ring-2', 'ring-green-400'); }, 800);
            });
        } else {
            prompt('Copie o placeholder:', t);
        }
    });
});
</script>
