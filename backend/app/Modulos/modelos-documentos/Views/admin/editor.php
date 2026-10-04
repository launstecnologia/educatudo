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
$vFolha = is_file(BASE_PATH . '/public/static/css/folha-oficial.css')
    ? (string) filemtime(BASE_PATH . '/public/static/css/folha-oficial.css')
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
    <link rel="stylesheet" href="<?= URL ?>/static/css/folha-oficial.css?v=<?= $esc($vFolha) ?>">
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
        <?php if (!empty($ia_disponivel)): ?>
        <button type="button" class="edoc-btn" id="edoc-ia" title="A IA monta o layout a partir de uma foto ou scan. Você pode colar a imagem."><i class="fa-solid fa-wand-magic-sparkles"></i> Reproduzir de imagem</button>
        <input type="file" id="edoc-ia-file" accept="image/png,image/jpeg,image/webp" hidden>
        <?php endif; ?>
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
                    <div class="edoc-item" draggable="true" data-drag-type="<?= $esc($it['tipo'] ?? '') ?>" title="<?= $esc(($it['ajuda'] ?? '') !== '' ? $it['ajuda'] : 'Clique para inserir ou arraste para a folha') ?>">
                        <i class="fa-solid <?= $esc($it['icone'] ?? 'fa-cube') ?>"></i>
                        <?= $esc($it['label'] ?? '') ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($grp === 'conteudo'): ?>
                <p class="edoc-hint" style="margin-top:10px">Clique em Tabela ou Imagem para inserir. Cole uma imagem ou uma tabela do Excel com Ctrl+V.</p>
                <?php endif; ?>
                <?php if ($grp === 'dados'): ?>
                <div class="edoc-demo" id="edoc-demo">
                    <h4>Demonstração</h4>
                    <label for="edoc-demo-turma">Turma / série</label>
                    <select id="edoc-demo-turma"><option value="">Carregando turmas…</option></select>
                    <label for="edoc-demo-aluno">Aluno</label>
                    <select id="edoc-demo-aluno"><option value="">Escolha a turma</option></select>
                    <label class="edoc-demo-check" for="edoc-demo-real">
                        <input type="checkbox" id="edoc-demo-real" checked>
                        Mostrar dados reais na folha
                    </label>
                    <p class="edoc-hint" id="edoc-demo-status">A folha usa um exemplo até escolher o aluno.</p>
                    <div class="edoc-atalhos">
                        <button type="button" class="edoc-btn" data-atalho="serie">Série e turma</button>
                        <button type="button" class="edoc-btn" data-atalho="componentes">Componentes</button>
                        <button type="button" class="edoc-btn" data-atalho="notas">Notas</button>
                    </div>
                    <details class="edoc-var-drop" id="edoc-demo-comps-drop" hidden>
                        <summary>Componentes da série <span class="edoc-var-count" id="edoc-demo-comps-count"></span></summary>
                        <div class="edoc-var-chips" id="edoc-demo-comps"></div>
                    </details>
                </div>
                <div class="edoc-sec-label">CAMPOS DA EMISSÃO</div>
                <p class="edoc-hint">Crie data, horário, local ou valor. Na emissão, a secretaria preenche e o texto entra no documento. Arraste o campo para a folha.</p>
                <div id="edoc-campos-lista" class="edoc-campos-lista"></div>
                <button type="button" class="edoc-btn" id="edoc-campo-novo" style="width:100%;margin-bottom:12px">
                    <i class="fa-solid fa-plus"></i> Novo campo
                </button>
                <input type="search" id="edoc-var-search" class="edoc-var-search" placeholder="Buscar variável…" autocomplete="off">
                <?php foreach ($grupos as $g):
                    $chavesGrupo = is_array($g['chaves'] ?? null) ? $g['chaves'] : [];
                    $itensGrupo = [];
                    foreach ($chavesGrupo as $ch) {
                        if (!isset($placeholders[$ch])) {
                            continue;
                        }
                        $itensGrupo[] = $ch;
                    }
                    if ($itensGrupo === []) {
                        continue;
                    }
                ?>
                <details class="edoc-var-drop edoc-var-group-side">
                    <summary><?= $esc($g['label'] ?? '') ?> <span class="edoc-var-count"><?= count($itensGrupo) ?></span></summary>
                    <div class="edoc-var-chips">
                        <?php foreach ($itensGrupo as $ch):
                            $labCh = (string) $placeholders[$ch];
                        ?>
                        <button type="button" class="edoc-var-chip" draggable="true"
                                data-drag-var="<?= $esc($ch) ?>"
                                data-var-nome="<?= $esc($labCh) ?>"
                                title="<?= $esc($labCh) ?>">
                            <span class="edoc-var-nome"><?= $esc($labCh) ?></span>
                            <span class="edoc-var-token"><?= $esc($rotuloVar($ch)) ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                </details>
                <?php endforeach; ?>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
            <div class="edoc-pane" id="pane-estrutura" style="display:none">
                <div id="edoc-tree" class="edoc-tree"></div>
            </div>
        </aside>

        <main class="edoc-center">
            <div class="edoc-ia-overlay" id="edoc-ia-overlay" aria-live="polite">
                <div class="edoc-ia-overlay-card">
                    <i class="fa-solid fa-wand-magic-sparkles" aria-hidden="true"></i>
                    <strong>A IA está reproduzindo o documento</strong>
                    <p id="edoc-ia-overlay-msg">Lendo a imagem…</p>
                </div>
            </div>
            <div class="edoc-canvas-tools">
                <button type="button" class="edoc-btn edoc-btn-icon" id="edoc-zoom-out"><i class="fa-solid fa-minus"></i></button>
                <span id="edoc-zoom-label" style="font-size:12px;min-width:48px;text-align:center">90%</span>
                <button type="button" class="edoc-btn edoc-btn-icon" id="edoc-zoom-in"><i class="fa-solid fa-plus"></i></button>
                <button type="button" class="edoc-btn" id="edoc-zoom-fit">Ajustar à tela</button>
                <span class="edoc-canvas-hint">Medidas em mm. O zoom não altera a impressão. No histórico: selecione células, mescle, use texto na vertical e faixa cinza. O PDF repete a folha.</span>
                <span class="edoc-canvas-hint" id="edoc-folha-aviso"></span>
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
                <div class="edoc-sec-label">USO NA EMISSÃO</div>
                <label>Documento oficial</label>
                <select id="edoc-emissao-tipo">
                    <option value="">Não vincular</option>
                    <option value="ficha_individual">Ficha individual</option>
                    <option value="relatorio">Relatório final</option>
                </select>
                <label>Curso</label>
                <select id="edoc-emissao-curso">
                    <option value="0">Todos os cursos</option>
                    <?php foreach (($cursos_emissao ?? []) as $curso): ?>
                    <option value="<?= (int) ($curso['id'] ?? 0) ?>"><?= $esc($curso['nome'] ?? '') ?></option>
                    <?php endforeach; ?>
                </select>
                <label>Série</label>
                <select id="edoc-emissao-serie">
                    <option value="0">Todas as séries</option>
                </select>
                <p class="edoc-hint">Ao gerar o documento da turma, este modelo entra quando o curso e a série coincidirem. Sem série, vale para o curso inteiro.</p>
                <label style="margin-top:14px">Código interno</label>
                <input id="edoc-codigo-visivel" value="<?= $esc($codigo) ?>" <?= $id && !empty($codigo_sistema) ? 'readonly' : '' ?>>
            </div>
        </aside>
    </div>
</div>

<div class="edoc-modal" id="edoc-ia-modal">
    <div class="edoc-modal-box">
        <h3 style="margin:0 0 8px;font-size:16px">Reproduzir de imagem</h3>
        <p class="edoc-hint">Cole a foto ou o scan com Ctrl+V, ou escolha um arquivo. A IA substitui o conteúdo atual da folha.</p>
        <div class="edoc-dropzone" id="edoc-ia-cola" tabindex="0">Clique aqui e cole a imagem (Ctrl+V)</div>
        <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="edoc-btn" id="edoc-ia-fechar">Cancelar</button>
            <button type="button" class="edoc-btn edoc-btn-primary" id="edoc-ia-escolher">Escolher arquivo</button>
        </div>
    </div>
</div>

<div class="edoc-modal" id="edoc-campo-modal">
    <div class="edoc-modal-box">
        <h3 style="margin:0 0 12px;font-size:16px" id="edoc-campo-titulo">Novo campo</h3>
        <label for="edoc-campo-rotulo">Rótulo</label>
        <input id="edoc-campo-rotulo" maxlength="80" placeholder="Ex.: Data do passeio">
        <label for="edoc-campo-mascara">Máscara</label>
        <select id="edoc-campo-mascara">
            <?php foreach ((array) ($mascaras_campo ?? []) as $chaveMascara => $rotuloMascara): ?>
            <option value="<?= $esc($chaveMascara) ?>"><?= $esc($rotuloMascara) ?></option>
            <?php endforeach; ?>
        </select>
        <label class="edoc-demo-check" for="edoc-campo-obrigatorio" style="margin:10px 0 14px">
            <input type="checkbox" id="edoc-campo-obrigatorio" checked>
            Obrigatório na emissão
        </label>
        <div style="display:flex;gap:8px;justify-content:flex-end">
            <button type="button" class="edoc-btn" id="edoc-campo-cancelar">Cancelar</button>
            <button type="button" class="edoc-btn edoc-btn-primary" id="edoc-campo-confirmar">Salvar campo</button>
        </div>
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
  layoutSugerido: <?= json_encode($layout_sugerido ?? null, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  iaUrl: <?= json_encode(!empty($ia_disponivel) ? (URL . '/admin/modelos-documentos/reproduzir-imagem') : '', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  series: <?= json_encode($series_emissao ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  demoUrl: <?= json_encode(URL . '/admin/modelos-documentos/demonstracao', JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
  mascaras: <?= json_encode($mascaras_campo ?? [], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>
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
