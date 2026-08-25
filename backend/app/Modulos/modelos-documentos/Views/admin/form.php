<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$modelo = is_array($modelo ?? null) ? $modelo : [];
$isEdit = !empty($modelo['id']);
$placeholders = $placeholders ?? [];
$grupos = is_array($grupos_placeholders ?? null) ? $grupos_placeholders : [];
$blocos = is_array($blocos ?? null) ? $blocos : [];
$categoria = (string) ($categoria ?? 'outro');
$categorias = is_array($categorias ?? null) ? $categorias : [];

require_once __DIR__ . '/../../Services/ModeloDocumentoService.php';

use App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService;

$estruturas = is_array($estruturas ?? null) && $estruturas !== []
    ? $estruturas
    : ModeloDocumentoService::estruturasEditor();

$codigoSistema = $isEdit && ModeloDocumentoService::isCodigoSistema((string) ($modelo['codigo'] ?? ''));
$ori = ($modelo['orientacao'] ?? 'retrato') === 'paisagem' ? 'paisagem' : 'retrato';
$formatoPapel = strtolower((string) ($modelo['formato_papel'] ?? 'a4')) === 'a5' ? 'a5' : 'a4';
$margemMm = (int) ($modelo['margem_mm'] ?? 20);
if ($margemMm < 8) { $margemMm = 8; }
if ($margemMm > 40) { $margemMm = 40; }
$espacamentoLinha = (string) ($modelo['espacamento_linha'] ?? '1.50');
if (!in_array($espacamentoLinha, ['1.00', '1.15', '1.50', '2.00', '1', '1.5', '2'], true)) {
    $espacamentoLinha = '1.50';
}
$espacamentoLinha = number_format((float) str_replace(',', '.', $espacamentoLinha), 2, '.', '');
$usaLayout = $isEdit
    ? ((int) ($modelo['usar_layout_padrao'] ?? 0) === 1)
    : in_array($categoria, ['declaracao', 'autorizacao', 'oficial'], true);

$sugestaoCodigo = match ($categoria) {
    'declaracao' => 'declaracao_',
    'autorizacao' => 'declaracao_aut_',
    'contrato' => 'contrato_',
    'oficial' => 'resultado_',
    default => '',
};

$listaUrl = URL . '/admin/modelos-documentos?categoria=' . rawurlencode($categoria);
$page_header_back_url = $listaUrl;
$page_header_title = $isEdit ? 'Montar modelo' : 'Novo modelo de documento';
$page_header_subtitle = 'Monte como no Elementor: colunas na folha (logo de um lado, nome da escola no outro, alinhado no meio).';
include __DIR__ . '/../../../../Views/admin/_partials/page_header_form.php';
include __DIR__ . '/../../../../Views/admin/_partials/flash_message.php';
?>

<?php if (empty($schema_pronto)): ?>
<div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800 mb-6">
    Migration <code>2026_08_06_secretaria_modelos_documentos.sql</code> pendente neste tenant.
</div>
<?php endif; ?>

<style>
.ck-mini-host {
    border: 1px solid #d1d5db; border-radius: 0.5rem; min-height: 8rem; background: #fff;
}
.ck-mini-host .ck-editor__editable { min-height: 8rem !important; border: 0 !important; box-shadow: none !important; }
.ck-mini-toolbar { margin-bottom: 0.35rem; }
.doc-bloco {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 0.35rem; padding: 0.7rem 0.4rem; border: 1px solid #e5e7eb; border-radius: 0.75rem;
    background: #fff; font-size: 0.7rem; color: #374151; text-align: center; cursor: pointer;
}
.doc-bloco:hover { border-color: var(--color-primary, #059669); background: #f8fafc; }
.doc-bloco i { font-size: 1rem; color: #6b7280; }
.folha-stage {
    background: #d1d5db;
    border-radius: 0.75rem;
    padding: 1rem;
    overflow: auto;
}
#toolbar-corpo {
    position: sticky; top: 0; z-index: 20;
    background: #fff; border-radius: 0.5rem; margin-bottom: 1rem;
    box-shadow: 0 1px 3px rgba(15,23,42,.12);
}
#toolbar-corpo .ck.ck-toolbar { border: 0 !important; }
.folha-pagina {
    --folha-w: <?= $formatoPapel === 'a5' ? ($ori === 'paisagem' ? '210' : '148') : ($ori === 'paisagem' ? '297' : '210') ?>mm;
    --folha-h: <?= $formatoPapel === 'a5' ? ($ori === 'paisagem' ? '148' : '210') : ($ori === 'paisagem' ? '210' : '297') ?>mm;
    --folha-margem: <?= (int) $margemMm ?>mm;
    --folha-lh: <?= $esc($espacamentoLinha) ?>;
    width: var(--folha-w);
    min-height: var(--folha-h);
    margin: 0 auto;
    background: #fff;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.22);
    padding: var(--folha-margem);
    box-sizing: border-box;
}
.folha-pagina .ck-editor__editable {
    width: 100% !important;
    max-width: 100% !important;
    min-width: 0 !important;
    min-height: calc(var(--folha-h) - (var(--folha-margem) * 2)) !important;
    border: 0 !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 !important;
    line-height: var(--folha-lh);
    font-size: 11pt;
    font-family: Arial, Helvetica, sans-serif;
    color: #111;
    box-sizing: border-box;
}
.folha-pagina .ck-editor__editable.ck-focused { box-shadow: none !important; }
/* CKEditor envolve tabela em figure.table com display:table + margin:auto → vira quadradinho no centro */
.folha-pagina .ck-content .table,
.folha-pagina .ck-content figure.table,
.folha-pagina .ck-content figure.table.ck-widget {
    width: 100% !important;
    max-width: 100% !important;
    margin: 0 0 12px 0 !important;
    display: block !important;
    float: none !important;
}
.folha-pagina .ck-content figure.table > table,
.folha-pagina .ck-content table {
    width: 100% !important;
    max-width: 100% !important;
    table-layout: fixed !important;
    border-collapse: collapse;
    border: none !important;
}
.folha-pagina .ck-content td,
.folha-pagina .ck-content th {
    min-height: 2.5rem;
    padding: 8px 10px;
    vertical-align: middle;
    word-wrap: break-word;
    border-color: transparent;
}
/* Guia só na edição: não imprime e não impede borda transparente */
.folha-pagina .ck-content figure.table:hover > table > tbody > tr > td,
.folha-pagina .ck-content figure.table.ck-widget_selected > table > tbody > tr > td {
    outline: 1px dashed #cbd5e1;
    outline-offset: -1px;
}
.folha-pagina .ck-content table.dados td,
.folha-pagina .ck-content table.dados th {
    border: 1px solid #ccc !important;
    outline: none;
}
.folha-pagina .ck-table-column-resizer {
    width: 8px !important;
    cursor: col-resize;
}
.folha-pagina .ck-table-column-resizer:hover {
    background: var(--color-primary, #059669) !important;
    opacity: 0.45;
}
.estrutura-item {
    display: flex; align-items: stretch; gap: 3px; height: 32px; padding: 5px;
    border: 1px solid #e5e7eb; border-radius: 0.5rem; background: #fff; cursor: pointer; width: 100%;
}
.estrutura-item:hover { border-color: var(--color-primary, #059669); background: #f8fafc; }
.estrutura-item span { display: block; background: #cbd5e1; border-radius: 2px; min-width: 4px; }
</style>

<form method="post"
      action="<?= $isEdit ? URL . '/admin/modelos-documentos/' . (int) $modelo['id'] . '/edit' : URL . '/admin/modelos-documentos' ?>"
      enctype="multipart/form-data"
      id="form-modelo-documento">
    <input type="hidden" name="csrf_token" value="<?= $esc($csrf_token ?? '') ?>">
    <input type="hidden" name="_token" value="<?= $esc($csrf_token ?? '') ?>">
    <input type="hidden" name="categoria" value="<?= $esc($categoria) ?>">

    <div class="bg-white rounded-xl shadow-lg w-full mb-6">
        <div class="p-6 space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <h3 class="text-lg font-semibold text-gray-900">Identificação</h3>
                <?php if ($isEdit): ?>
                <a href="<?= URL ?>/admin/modelos-documentos/<?= (int) $modelo['id'] ?>/preview"
                   target="_blank" rel="noopener"
                   class="inline-flex items-center px-3 py-2 rounded-lg border border-gray-200 text-sm font-medium text-gray-700 hover:bg-gray-50">
                    <i class="fa-solid fa-eye mr-1.5"></i> Ver PDF
                </a>
                <?php endif; ?>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <label for="nome" class="block text-sm font-medium text-gray-700 mb-2">
                        Nome <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="nome" name="nome" required
                           value="<?= $esc($modelo['nome'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500"
                           placeholder="Ex.: Declaração de Matrícula 2026">
                </div>
                <div>
                    <label for="codigo" class="block text-sm font-medium text-gray-700 mb-2">
                        Código <span class="text-red-500">*</span>
                    </label>
                    <input type="text" id="codigo" name="codigo" required
                           value="<?= $esc($modelo['codigo'] ?? $sugestaoCodigo) ?>"
                           <?= $codigoSistema ? 'readonly' : '' ?>
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500 <?= $codigoSistema ? 'bg-gray-50' : '' ?>"
                           placeholder="Ex.: declaracao_matricula">
                    <p class="mt-1 text-xs text-gray-500">
                        Identificador interno.
                        <?php if ($codigoSistema): ?>Modelo do sistema — o código não muda.<?php else: ?>
                        Use <code>declaracao_*</code>, <code>contrato_*</code> ou um código próprio.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="md:col-span-2">
                    <label for="descricao" class="block text-sm font-medium text-gray-700 mb-2">Descrição</label>
                    <input type="text" id="descricao" name="descricao"
                           value="<?= $esc($modelo['descricao'] ?? '') ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <div class="md:col-span-2">
                    <span class="block text-sm font-medium text-gray-700 mb-2">Folha para impressão</span>
                    <p class="text-xs text-gray-500 mb-3">O retângulo cinza abaixo simula a página. O PDF sai nesse tamanho.</p>
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div>
                            <span class="block text-xs font-medium text-gray-600 mb-1.5">Tamanho</span>
                            <div class="flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="formato_papel" value="a4" class="js-folha-cfg rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200"
                                           <?= $formatoPapel === 'a4' ? 'checked' : '' ?>>
                                    A4 (210×297 mm)
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="formato_papel" value="a5" class="js-folha-cfg rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200"
                                           <?= $formatoPapel === 'a5' ? 'checked' : '' ?>>
                                    A5 (148×210 mm)
                                </label>
                            </div>
                        </div>
                        <div>
                            <span class="block text-xs font-medium text-gray-600 mb-1.5">Orientação</span>
                            <div class="flex flex-wrap gap-3">
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="orientacao" value="retrato" class="js-folha-cfg rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200"
                                           <?= $ori === 'retrato' ? 'checked' : '' ?>>
                                    Vertical
                                </label>
                                <label class="inline-flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                    <input type="radio" name="orientacao" value="paisagem" class="js-folha-cfg rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200"
                                           <?= $ori === 'paisagem' ? 'checked' : '' ?>>
                                    Horizontal
                                </label>
                            </div>
                        </div>
                        <div>
                            <label for="margem_mm" class="block text-xs font-medium text-gray-600 mb-1.5">Margem (mm)</label>
                            <input type="number" id="margem_mm" name="margem_mm" min="8" max="40" step="1"
                                   value="<?= (int) $margemMm ?>"
                                   class="js-folha-cfg w-full px-3 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label for="espacamento_linha" class="block text-xs font-medium text-gray-600 mb-1.5">Espaçamento entre linhas</label>
                            <select id="espacamento_linha" name="espacamento_linha"
                                    class="js-folha-cfg w-full px-3 py-2 border border-gray-300 rounded-lg bg-white focus:outline-none focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <?php
                                $opcoesLh = ['1.00' => 'Simples (1,0)', '1.15' => '1,15', '1.50' => '1,5', '2.00' => 'Duplo (2,0)'];
                                foreach ($opcoesLh as $val => $lab):
                                ?>
                                <option value="<?= $esc($val) ?>" <?= $espacamentoLinha === $val ? 'selected' : '' ?>><?= $esc($lab) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="space-y-3">
                    <label class="flex items-center">
                        <input type="checkbox" name="ativo" value="1"
                               class="rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50"
                               <?= !isset($modelo['ativo']) || !empty($modelo['ativo']) ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">Modelo ativo (disponível para emissão)</span>
                    </label>
                    <label class="flex items-start">
                        <input type="checkbox" name="usar_layout_padrao" value="1" id="usar_layout_padrao"
                               class="mt-0.5 rounded border-gray-300 text-green-600 shadow-sm focus:border-green-300 focus:ring focus:ring-green-200 focus:ring-opacity-50"
                               <?= $usaLayout ? 'checked' : '' ?>>
                        <span class="ml-2 text-sm text-gray-700">
                            Usar papel timbrado da escola
                            <span class="block text-xs text-gray-500">Aplica logo/faixa e quem assina. O texto que você montar no cabeçalho e no rodapé <strong>não é substituído</strong>.
                                <a href="<?= URL ?>/admin/modelos-documentos/layout" class="underline">Papel timbrado</a>.
                            </span>
                        </span>
                    </label>
                </div>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-6 mb-6">
        <aside class="xl:col-span-3 space-y-4">
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Estrutura</h3>
                <p class="text-xs text-gray-500 mb-3">Clique na divisão. Depois escreva ou solte a logo em cada coluna.</p>
                <fieldset class="mb-3">
                    <legend class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1.5">Alinhamento vertical</legend>
                    <div class="flex flex-wrap gap-3">
                        <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                            <input type="radio" name="estrutura_valign" value="top" class="rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200">
                            Topo
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                            <input type="radio" name="estrutura_valign" value="middle" class="rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200" checked>
                            Meio
                        </label>
                        <label class="inline-flex items-center gap-1.5 text-xs text-gray-700 cursor-pointer">
                            <input type="radio" name="estrutura_valign" value="bottom" class="rounded border-gray-300 text-green-600 shadow-sm focus:ring focus:ring-green-200">
                            Base
                        </label>
                    </div>
                </fieldset>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($estruturas as $est):
                        $colsEst = is_array($est['cols'] ?? null) ? $est['cols'] : [];
                        $colsAttr = implode(',', array_map('intval', $colsEst));
                    ?>
                    <button type="button" class="js-insert-estrutura text-left"
                            data-cols="<?= $esc($colsAttr) ?>"
                            title="<?= $esc($est['label'] ?? '') ?>">
                        <div class="estrutura-item" aria-hidden="true">
                            <?php foreach ($colsEst as $w): ?>
                            <span style="flex: <?= max(1, (int) $w) ?> 1 0;"></span>
                            <?php endforeach; ?>
                        </div>
                        <span class="mt-1 block text-[10px] text-gray-500 text-center"><?= $esc($est['label'] ?? '') ?></span>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Blocos</h3>
                <p class="text-xs text-gray-500 mb-3">Clique para inserir no editor. Você pode depois editar como no Word.</p>
                <div class="grid grid-cols-2 gap-2">
                    <?php foreach ($blocos as $bloco): ?>
                    <button type="button" class="doc-bloco js-insert-bloco"
                            data-alvo="<?= $esc($bloco['alvo'] ?? 'corpo_html') ?>"
                            data-html="<?= $esc($bloco['html'] ?? '') ?>"
                            title="<?= $esc($bloco['ajuda'] ?? '') ?>">
                        <i class="fa-solid <?= $esc($bloco['icone'] ?? 'fa-square') ?>"></i>
                        <?= $esc($bloco['label'] ?? '') ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="bg-white rounded-xl shadow-lg p-4">
                <h3 class="text-sm font-semibold text-gray-900 mb-1">Campos dinâmicos</h3>
                <p class="text-xs text-gray-500 mb-3">Clique para inserir no ponto do cursor.</p>
                <div class="space-y-3 max-h-[28rem] overflow-y-auto pr-1">
                    <?php foreach ($grupos as $grupo):
                        $chaves = is_array($grupo['chaves'] ?? null) ? $grupo['chaves'] : [];
                    ?>
                    <div>
                        <p class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-1.5"><?= $esc($grupo['label'] ?? '') ?></p>
                        <div class="flex flex-wrap gap-1.5">
                            <?php foreach ($chaves as $key):
                                if (!isset($placeholders[$key])) {
                                    continue;
                                }
                            ?>
                            <button type="button"
                                    class="js-insert-ph inline-flex items-center px-2 py-0.5 rounded border border-gray-200 bg-white text-[11px] font-medium text-gray-700 hover:bg-gray-50"
                                    data-ph="{{<?= $esc($key) ?>}}"
                                    title="<?= $esc($placeholders[$key]) ?>">
                                {{<?= $esc($key) ?>}}
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </aside>

        <div class="xl:col-span-9 space-y-6">
            <div class="bg-white rounded-xl shadow-lg divide-y divide-gray-200" id="js-imagens-modelo">
                <div class="p-6 space-y-4">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-900">Imagens deste modelo</h3>
                        <p class="text-sm text-gray-600 mt-1">
                            Use quando o documento <strong>não</strong> herda o papel timbrado. PNG ou JPG · máx. 5MB.
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
            </div>

            <div class="bg-white rounded-xl shadow-lg p-6 space-y-6">
                <h3 class="text-lg font-semibold text-gray-900">Conteúdo do documento</h3>
                <div id="wy_cabecalho_html_box">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Cabeçalho</label>
                    <div id="toolbar-cabecalho" class="ck-mini-toolbar"></div>
                    <div id="editor-cabecalho" class="ck-mini-host"></div>
                    <textarea id="cabecalho_html" name="cabecalho_html" class="sr-only" aria-hidden="true"><?= $esc($modelo['cabecalho_html'] ?? '') ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">Este texto sai no PDF. O papel timbrado só entra aqui se o cabeçalho estiver vazio.</p>
                </div>
                <div id="wy_corpo_html_box">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Corpo <span class="text-red-500">*</span></label>
                    <p class="text-xs text-gray-500 mb-3">Arraste a linha entre as colunas para mudar a largura. Clique na célula e no ícone do quadrado para altura e borda (deixe “nenhuma” / transparente).</p>
                    <div class="folha-stage">
                        <div id="toolbar-corpo"></div>
                        <div class="folha-pagina" id="folha-pagina">
                            <div id="editor-corpo"></div>
                        </div>
                    </div>
                    <textarea id="corpo_html" name="corpo_html" class="sr-only" aria-hidden="true"><?= $esc($modelo['corpo_html'] ?? '') ?></textarea>
                </div>
                <div id="wy_rodape_html_box">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Rodapé</label>
                    <div id="toolbar-rodape" class="ck-mini-toolbar"></div>
                    <div id="editor-rodape" class="ck-mini-host"></div>
                    <textarea id="rodape_html" name="rodape_html" class="sr-only" aria-hidden="true"><?= $esc($modelo['rodape_html'] ?? '') ?></textarea>
                    <p class="mt-1 text-xs text-gray-500">Assinaturas e data. O papel timbrado só preenche o rodapé se este campo estiver vazio.</p>
                </div>
            </div>
        </div>
    </div>

    <?php
    $form_cancel_url = $listaUrl;
    $form_submit_label = 'Salvar modelo';
    ?>
    <div class="bg-white rounded-xl shadow-lg overflow-hidden">
    <?php include __DIR__ . '/../../../../Views/admin/_partials/form_actions.php'; ?>
    </div>
</form>

<script src="https://cdn.ckeditor.com/ckeditor5/41.2.1/decoupled-document/ckeditor.js"></script>
<script>
(function () {
    var editors = {};
    var lastAlvo = 'corpo_html';
    var fontSize = {
        options: [
            { title: '8 pt', model: '8pt' },
            { title: '9 pt', model: '9pt' },
            { title: '10 pt', model: '10pt' },
            { title: '11 pt', model: '11pt' },
            { title: '12 pt', model: '12pt' },
            { title: '14 pt', model: '14pt' },
            { title: '16 pt', model: '16pt' },
            { title: '18 pt', model: '18pt' },
            { title: '24 pt', model: '24pt' },
            { title: '36 pt', model: '36pt' }
        ],
        supportAllValues: true
    };
    var fontFamily = {
        options: [
            'default',
            'Arial, Helvetica, sans-serif',
            'Times New Roman, Times, serif',
            'Georgia, serif',
            'Courier New, Courier, monospace',
            'Verdana, Geneva, sans-serif'
        ]
    };
    var toolbarMini = ['heading', '|', 'fontFamily', 'fontSize', '|', 'bold', 'italic', 'underline', '|', 'alignment', '|', 'undo', 'redo'];
    var toolbarDoc = [
        'heading', '|',
        'fontFamily', 'fontSize', 'fontColor', 'fontBackgroundColor', '|',
        'bold', 'italic', 'underline', 'strikethrough', '|',
        'alignment', '|',
        'numberedList', 'bulletedList', '|',
        'outdent', 'indent', '|',
        'link', 'uploadImage', 'insertTable', 'horizontalLine', 'pageBreak', '|',
        'undo', 'redo'
    ];
    var iniciais = {
        cabecalho_html: <?= json_encode((string) ($modelo['cabecalho_html'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
        corpo_html: <?= json_encode((string) ($modelo['corpo_html'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
        rodape_html: <?= json_encode((string) ($modelo['rodape_html'] ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>
    };

    function insertHtml(alvo, html) {
        var ed = editors[alvo];
        if (!ed || !html) return;
        var viewFragment = ed.data.processor.toView(html);
        var modelFragment = ed.data.toModel(viewFragment);
        ed.model.insertContent(modelFragment);
    }

    function insertText(alvo, text) {
        var ed = editors[alvo];
        if (!ed || !text) return;
        ed.model.change(function (writer) {
            ed.model.insertContent(writer.createText(text));
        });
    }

    function valignAtual() {
        var el = document.querySelector('input[name="estrutura_valign"]:checked');
        return el ? el.value : 'middle';
    }

    function htmlEstrutura(colsCsv) {
        var cols = String(colsCsv || '50,50').split(',').map(function (n) { return parseInt(n, 10) || 50; });
        var va = valignAtual();
        if (va !== 'top' && va !== 'bottom') va = 'middle';
        var colgroup = cols.map(function (w) {
            return '<col style="width:' + w + '%">';
        }).join('');
        var tds = cols.map(function (w) {
            return '<td class="doc-col" style="width:' + w + '%;vertical-align:' + va + ';padding:8px 10px;border:none;border-color:transparent;"><p>&nbsp;</p></td>';
        }).join('');
        return '<table class="doc-linha" width="100%" border="0" style="width:100%;border-collapse:collapse;table-layout:fixed;border:none;">'
            + '<colgroup>' + colgroup + '</colgroup><tbody><tr>' + tds + '</tr></tbody></table><p>&nbsp;</p>';
    }

    function forcarTabelaLarguraTotal(ed) {
        if (!ed || !ed.model) return;
        try {
            var pos = ed.model.document.selection.getFirstPosition();
            var table = pos && pos.findAncestor ? pos.findAncestor('table') : null;
            if (!table) return;
            ed.model.change(function (writer) {
                writer.setAttribute('tableWidth', '100%', table);
            });
        } catch (e) { /* plugin sem tableWidth */ }
    }

    function DataUriAdapter(loader) {
        this.loader = loader;
    }
    DataUriAdapter.prototype.upload = function () {
        return this.loader.file.then(function (file) {
            return new Promise(function (resolve, reject) {
                var ok = file && (file.type === 'image/png' || file.type === 'image/jpeg');
                if (!ok) { reject('Use PNG ou JPG.'); return; }
                if (file.size > 1500000) { reject('Imagem maior que 1,5 MB.'); return; }
                var reader = new FileReader();
                reader.onload = function () { resolve({ default: reader.result }); };
                reader.onerror = function () { reject('Falha ao ler a imagem.'); };
                reader.readAsDataURL(file);
            });
        });
    };
    DataUriAdapter.prototype.abort = function () {};

    function atualizarFolha() {
        var fmtEl = document.querySelector('input[name="formato_papel"]:checked');
        var oriEl = document.querySelector('input[name="orientacao"]:checked');
        var margemEl = document.getElementById('margem_mm');
        var lhEl = document.getElementById('espacamento_linha');
        var fmt = fmtEl ? fmtEl.value : 'a4';
        var ori = oriEl ? oriEl.value : 'retrato';
        var margem = parseInt(margemEl && margemEl.value, 10);
        if (isNaN(margem) || margem < 8) margem = 8;
        if (margem > 40) margem = 40;
        var lh = (lhEl && lhEl.value) ? lhEl.value : '1.50';
        var sizes = { a4: { w: 210, h: 297 }, a5: { w: 148, h: 210 } };
        var s = sizes[fmt] || sizes.a4;
        var w = ori === 'paisagem' ? s.h : s.w;
        var h = ori === 'paisagem' ? s.w : s.h;
        var el = document.getElementById('folha-pagina');
        if (!el) return;
        el.style.setProperty('--folha-w', w + 'mm');
        el.style.setProperty('--folha-h', h + 'mm');
        el.style.setProperty('--folha-margem', margem + 'mm');
        el.style.setProperty('--folha-lh', lh);
    }

    function criarEditor(hostId, toolbarId, campo, toolbar) {
        var host = document.getElementById(hostId);
        if (!host || typeof DecoupledEditor === 'undefined') return;
        DecoupledEditor.create(host, {
            language: 'pt-br',
            toolbar: toolbar,
            initialData: iniciais[campo] || '',
            fontSize: fontSize,
            fontFamily: fontFamily,
            image: {
                toolbar: [
                    'imageStyle:inline',
                    'imageStyle:wrapText',
                    'imageStyle:breakText',
                    '|',
                    'toggleImageCaption',
                    'imageTextAlternative'
                ]
            },
            table: {
                contentToolbar: [
                    'tableColumn',
                    'tableRow',
                    'mergeTableCells',
                    'tableCellProperties',
                    'tableProperties'
                ],
                tableProperties: {
                    defaultProperties: {
                        borderStyle: 'none',
                        borderColor: 'transparent',
                        borderWidth: '0',
                        width: '100%',
                        alignment: 'left'
                    }
                },
                tableCellProperties: {
                    defaultProperties: {
                        borderStyle: 'none',
                        borderColor: 'transparent',
                        borderWidth: '0',
                        padding: '8px'
                    }
                }
            }
        }).then(function (editor) {
            var toolbarHost = document.getElementById(toolbarId);
            if (toolbarHost) {
                toolbarHost.appendChild(editor.ui.view.toolbar.element);
            }
            try {
                editor.plugins.get('FileRepository').createUploadAdapter = function (loader) {
                    return new DataUriAdapter(loader);
                };
            } catch (e) { /* build sem upload */ }
            editors[campo] = editor;
            editor.editing.view.document.on('focus', function () { lastAlvo = campo; });
        }).catch(function (err) { console.error(err); });
    }

    criarEditor('editor-cabecalho', 'toolbar-cabecalho', 'cabecalho_html', toolbarMini);
    criarEditor('editor-corpo', 'toolbar-corpo', 'corpo_html', toolbarDoc);
    criarEditor('editor-rodape', 'toolbar-rodape', 'rodape_html', toolbarMini);

    document.querySelectorAll('.js-folha-cfg').forEach(function (el) {
        el.addEventListener('change', atualizarFolha);
        el.addEventListener('input', atualizarFolha);
    });

    var form = document.getElementById('form-modelo-documento');
    if (form) {
        form.addEventListener('submit', function () {
            ['cabecalho_html', 'corpo_html', 'rodape_html'].forEach(function (campo) {
                if (editors[campo] && editors[campo].getData) {
                    var ta = document.getElementById(campo);
                    if (ta) ta.value = editors[campo].getData();
                }
            });
        });
    }

    document.querySelectorAll('.js-insert-estrutura').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var alvo = 'corpo_html';
            insertHtml(alvo, htmlEstrutura(btn.getAttribute('data-cols')));
            forcarTabelaLarguraTotal(editors[alvo]);
            lastAlvo = alvo;
        });
    });

    document.querySelectorAll('.js-insert-bloco').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var alvo = btn.getAttribute('data-alvo') || 'corpo_html';
            var html = btn.getAttribute('data-html') || '';
            if (!editors[alvo]) alvo = lastAlvo;
            insertHtml(alvo, html);
            if (html && html.indexOf('doc-linha') !== -1) {
                forcarTabelaLarguraTotal(editors[alvo]);
            }
            lastAlvo = alvo;
        });
    });

    document.querySelectorAll('.js-insert-ph').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var t = btn.getAttribute('data-ph') || '';
            insertText(lastAlvo, t);
        });
    });
})();
</script>
