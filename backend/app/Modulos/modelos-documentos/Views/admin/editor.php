<?php
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$rotuloVar = static function (string $ch): string {
    if ($ch === 'se_resp2') {
        return '{{#se_resp2}}…{{/se_resp2}}';
    }
    if ($ch === 'se_resp_fin') {
        return '{{#se_resp_fin}}…{{/se_resp_fin}}';
    }
    return '{{' . $ch . '}}';
};
require_once BASE_PATH . '/app/Core/LayoutHelper.php';

$modelo = is_array($modelo ?? null) ? $modelo : [];
$estrutura = is_array($estrutura ?? null) ? $estrutura : [];
$catalogo = is_array($catalogo ?? null) ? $catalogo : [];
$grupos = is_array($grupos_placeholders ?? null) ? $grupos_placeholders : [];
$placeholders = is_array($placeholders ?? null) ? $placeholders : [];
$id = (int) ($modelo['id'] ?? 0);
$nome = (string) ($modelo['nome'] ?? 'Novo modelo');
$codigo = (string) ($modelo['codigo'] ?? '');
$listaUrl = URL . '/admin/modelos-documentos';
$saveUrl = $id > 0
    ? URL . '/admin/modelos-documentos/' . $id . '/estrutura'
    : URL . '/admin/modelos-documentos/estrutura';
$previewUrl = $id > 0 ? URL . '/admin/modelos-documentos/' . $id . '/preview' : '';
$usaLayout = (int) ($modelo['usar_layout_padrao'] ?? 1) === 1;
$logoPreview = (string) ($logo_preview ?? '');

$layoutBars = static function (array $cols): string {
    $html = '<div class="edoc-layout-bars">';
    foreach ($cols as $w) {
        $html .= '<span style="flex:' . (int) $w . '"></span>';
    }
    return $html . '</div>';
};
$vCss = is_file(BASE_PATH . '/public/static/css/editor-documento.css')
    ? (string) filemtime(BASE_PATH . '/public/static/css/editor-documento.css')
    : '1';
$vJs = is_file(BASE_PATH . '/public/static/js/editor-documento.js')
    ? (string) filemtime(BASE_PATH . '/public/static/js/editor-documento.js')
    : '1';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $esc($nome) ?> — Editor de documentos</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <link rel="stylesheet" href="<?= URL ?>/static/css/editor-documento.css?v=<?= $esc($vCss) ?>">
    <style><?= LayoutHelper::generateCustomCSS() ?></style>
</head>
<body class="edoc-body">
<div class="edoc-app">
    <header class="edoc-top">
        <a class="edoc-btn" href="<?= $esc($listaUrl) ?>"><i class="fa-solid fa-arrow-left"></i> Voltar</a>
        <div class="edoc-top-title">
            <input id="edoc-nome" value="<?= $esc($nome) ?>" placeholder="Nome do modelo">
        </div>
        <input type="hidden" id="edoc-codigo" value="<?= $esc($codigo) ?>">
        <span class="edoc-top-status" id="edoc-status">Pronto</span>
        <div style="flex:1"></div>
        <label class="edoc-chk" style="margin:0">
            <input type="checkbox" id="edoc-layout-padrao" <?= $usaLayout ? 'checked' : '' ?>> Papel timbrado
        </label>
        <button type="button" class="edoc-btn edoc-btn-icon" id="edoc-undo" title="Desfazer"><i class="fa-solid fa-rotate-left"></i></button>
        <button type="button" class="edoc-btn edoc-btn-icon" id="edoc-redo" title="Refazer"><i class="fa-solid fa-rotate-right"></i></button>
        <button type="button" class="edoc-btn" id="edoc-preview-mode"><i class="fa-solid fa-eye"></i> Preview</button>
        <a class="edoc-btn" id="edoc-pdf" href="<?= $esc($previewUrl ?: '#') ?>" target="_blank" rel="noopener">Pré-visualizar PDF</a>
        <button type="button" class="edoc-btn edoc-btn-primary" id="edoc-save"><i class="fa-solid fa-floppy-disk"></i> Salvar modelo</button>
    </header>

    <div class="edoc-body-row">
        <aside class="edoc-left">
            <div class="edoc-tabs">
                <button type="button" class="active" data-tab="pane-elementos">ELEMENTOS</button>
                <button type="button" data-tab="pane-estrutura">ESTRUTURA</button>
            </div>
            <div class="edoc-pane" id="pane-elementos">
                <div class="edoc-sec-label">LAYOUT</div>
                <div class="edoc-grid edoc-grid-3">
                    <?php foreach (($catalogo['layout'] ?? []) as $lay):
                        $cols = $lay['cols'] ?? [100];
                    ?>
                    <div class="edoc-layout" draggable="true" data-drag-layout="<?= $esc(json_encode($cols)) ?>" title="<?= $esc($lay['label'] ?? '') ?>">
                        <?= $layoutBars($cols) ?>
                        <span><?= $esc($lay['label'] ?? '') ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php foreach (['conteudo' => 'CONTEÚDO', 'dados' => 'DADOS', 'tabelas' => 'TABELAS', 'extras' => 'EXTRAS'] as $grp => $lab): ?>
                <div class="edoc-sec-label"><?= $esc($lab) ?></div>
                <div class="edoc-grid">
                    <?php foreach (($catalogo[$grp] ?? []) as $it): ?>
                    <div class="edoc-item" draggable="true" data-drag-type="<?= $esc($it['tipo'] ?? '') ?>">
                        <i class="fa-solid <?= $esc($it['icone'] ?? 'fa-cube') ?>"></i>
                        <?= $esc($it['label'] ?? '') ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($grp === 'dados'): ?>
                <p class="edoc-hint" style="margin-top:10px">Todas as variáveis. Arraste para a folha ou clique para inserir no texto selecionado.</p>
                <input type="search" id="edoc-var-search" class="edoc-var-search" placeholder="Buscar variável…" autocomplete="off">
                <?php foreach ($grupos as $g):
                    $chavesGrupo = is_array($g['chaves'] ?? null) ? $g['chaves'] : [];
                    if ($chavesGrupo === []) {
                        continue;
                    }
                ?>
                <div class="edoc-var-group-side">
                    <h4><?= $esc($g['label'] ?? '') ?></h4>
                    <div class="edoc-var-chips">
                        <?php foreach ($chavesGrupo as $ch):
                            if (!isset($placeholders[$ch])) {
                                continue;
                            }
                            $labCh = (string) $placeholders[$ch];
                        ?>
                        <button type="button" class="edoc-var-chip" draggable="true"
                                data-drag-var="<?= $esc($ch) ?>"
                                title="<?= $esc($labCh) ?>"><?= $esc($rotuloVar($ch)) ?></button>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="edoc-pane" id="pane-estrutura" style="display:none">
                <div id="edoc-tree" class="edoc-tree"></div>
            </div>
        </aside>

        <main class="edoc-center">
            <div class="edoc-canvas-tools">
                <button type="button" class="edoc-btn edoc-btn-icon" id="edoc-zoom-out"><i class="fa-solid fa-minus"></i></button>
                <span id="edoc-zoom-label" style="font-size:12px;min-width:48px;text-align:center">90%</span>
                <button type="button" class="edoc-btn edoc-btn-icon" id="edoc-zoom-in"><i class="fa-solid fa-plus"></i></button>
                <button type="button" class="edoc-btn" id="edoc-zoom-fit">Ajustar à tela</button>
                <span class="edoc-canvas-hint">Clique no texto da folha para digitar</span>
                <?php if (!empty($layout_sugerido)): ?>
                <button type="button" class="edoc-btn" id="edoc-layout-sugerido" title="Monta cabeçalho, identificação, notas e assinaturas lado a lado">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Montar boletim
                </button>
                <?php endif; ?>
            </div>
            <div class="edoc-stage">
                <div id="edoc-paper-wrap" class="edoc-paper-wrap">
                    <div class="edoc-ruler-h"></div>
                    <div class="edoc-ruler-v"></div>
                    <div id="edoc-paper" class="edoc-paper"></div>
                </div>
            </div>
        </main>

        <aside class="edoc-right">
            <div class="edoc-tabs">
                <button type="button" class="active" data-tab="pane-props">PROPRIEDADES</button>
                <button type="button" data-tab="pane-estilo">ESTILO</button>
                <button type="button" data-tab="pane-avancado">AVANÇADO</button>
            </div>
            <div class="edoc-pane edoc-prop" id="pane-props">
                <div id="edoc-props"></div>
            </div>
            <div class="edoc-pane edoc-prop" id="pane-estilo" style="display:none">
                <p class="edoc-hint">Margem, preenchimento, borda e posição da imagem ficam em <strong>Propriedades</strong> ao selecionar o elemento na folha.</p>
            </div>
            <div class="edoc-pane edoc-prop" id="pane-avancado" style="display:none">
                <label>Código interno</label>
                <input id="edoc-codigo-visivel" value="<?= $esc($codigo) ?>" <?= $id && !empty($codigo_sistema) ? 'readonly' : '' ?>>
                <p class="edoc-empty" style="padding-top:8px">Quebra de página e “ocultar se vazio” ficam no elemento/seção selecionados.</p>
            </div>
        </aside>
    </div>
</div>

<div class="edoc-modal" id="edoc-vars">
    <div class="edoc-modal-box">
        <h3 style="margin:0 0 12px;font-size:16px">Inserir variável</h3>
        <?php foreach ($grupos as $g): ?>
        <div class="edoc-var-group">
            <h4><?= $esc($g['label'] ?? '') ?></h4>
            <div class="edoc-var-list">
                <?php foreach (($g['chaves'] ?? []) as $ch):
                    if (!isset($placeholders[$ch])) {
                        continue;
                    }
                ?>
                <button type="button" class="edoc-btn" data-var="<?= $esc($ch) ?>"
                        title="<?= $esc((string) $placeholders[$ch]) ?>"><?= $esc($rotuloVar($ch)) ?></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
window.EDOC = {
  csrf: <?= json_encode((string) ($csrf_token ?? ''), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  urlBase: <?= json_encode((string) URL, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  saveUrl: <?= json_encode($saveUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  previewUrl: <?= json_encode($previewUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  listaUrl: <?= json_encode($listaUrl, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  logoPreview: <?= json_encode($logoPreview, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  modelo: <?= json_encode(['id' => $id, 'nome' => $nome, 'codigo' => $codigo, 'descricao' => (string) ($modelo['descricao'] ?? '')], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  estrutura: <?= json_encode($estrutura, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  varsPreview: <?= json_encode($vars_preview ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  catalogo: <?= json_encode($catalogo, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  layoutSugerido: <?= json_encode($layout_sugerido ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>
};
</script>
<script src="<?= URL ?>/static/js/editor-documento.js?v=<?= $esc($vJs) ?>"></script>
<script>
(function () {
  document.querySelectorAll('.edoc-tabs').forEach(function (tabs) {
    tabs.querySelectorAll('[data-tab]').forEach(function (btn) {
      btn.addEventListener('click', function () {
        var paneId = btn.getAttribute('data-tab');
        tabs.querySelectorAll('[data-tab]').forEach(function (b) { b.classList.remove('active'); });
        btn.classList.add('active');
        var parent = tabs.parentElement;
        parent.querySelectorAll(':scope > .edoc-pane').forEach(function (p) {
          p.style.display = p.id === paneId ? 'block' : 'none';
        });
        if (paneId === 'pane-estrutura' && window.EDOC) {
          document.dispatchEvent(new Event('edoc-tree'));
        }
      });
    });
  });
  var cod = document.getElementById('edoc-codigo-visivel');
  var hid = document.getElementById('edoc-codigo');
  if (cod && hid) {
    cod.addEventListener('input', function () { hid.value = cod.value; });
  }
})();
</script>
</body>
</html>
