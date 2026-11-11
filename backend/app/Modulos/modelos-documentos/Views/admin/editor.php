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
    <style><?= \App\Core\LayoutHelper::generateCustomCSS() ?></style>
    <?php include BASE_PATH . '/app/Views/layouts/components/form_control_safari.php'; ?>
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
        <button type="button" class="edoc-btn" id="edoc-preview-mode"><i class="fa-solid fa-eye"></i> Ver resultado</button>
        <a class="edoc-btn" id="edoc-pdf" href="<?= $esc($previewUrl ?: '#') ?>" target="_blank" rel="noopener">Abrir PDF</a>
        <button type="button" class="edoc-btn edoc-btn-primary" id="edoc-save"><i class="fa-solid fa-floppy-disk"></i> Salvar modelo</button>
    </header>

    <div class="edoc-body-row">
        <aside class="edoc-left">
            <div class="edoc-tabs">
                <button type="button" class="active" data-tab="pane-elementos">Inserir</button>
                <button type="button" data-tab="pane-estrutura">Organizar</button>
            </div>
            <div class="edoc-pane" id="pane-elementos">
                <div class="edoc-inserir-intro">
                    <strong>Monte seu documento</strong>
                    <p>Clique para adicionar. Para escolher o lugar, arraste até a folha.</p>
                </div>
                <details class="edoc-demo edoc-boletim-card" id="edoc-boletim-card" hidden>
                    <summary>Conferir com dados de aluno</summary>
                    <p class="edoc-hint">A regra de aprovação já vincula o Demonstrativo de Notas e o Boletim. Escolha regra, curso/série e o aluno.</p>
                    <label for="edoc-fonte-regra">Regra de aprovação</label>
                    <select id="edoc-fonte-regra"><option value="">Carregando regras…</option></select>
                    <label for="edoc-demo-turma">Curso / série</label>
                    <select id="edoc-demo-turma"><option value="">Carregando turmas…</option></select>
                    <label for="edoc-demo-aluno">Nome do aluno(a)</label>
                    <select id="edoc-demo-aluno"><option value="">Escolha o curso/série</option></select>
                    <label class="edoc-demo-check" for="edoc-demo-real">
                        <input type="checkbox" id="edoc-demo-real" checked>
                        Mostrar dados reais na folha
                    </label>
                    <p class="edoc-hint" id="edoc-demo-status">Escolha regra, curso/série e aluno para ver na folha.</p>
                    <?php /* Compat JS legado (ocultos). */ ?>
                    <select id="edoc-demo-ano" hidden aria-hidden="true"><option value="">Todos</option></select>
                    <select id="edoc-fonte-demonstrativo" hidden aria-hidden="true"><option value=""></option></select>
                    <select id="edoc-fonte-boletim" hidden aria-hidden="true"><option value=""></option></select>
                </details>
                <label class="edoc-busca-label" for="edoc-element-search">O que você quer adicionar?</label>
                <input type="search" id="edoc-element-search" class="edoc-var-search" placeholder="Buscar título, imagem, boletim…" autocomplete="off">
                <p id="edoc-element-empty" class="edoc-hint" role="status" hidden>Nenhum elemento encontrado. Tente outro nome.</p>
                <details class="edoc-tool-group" data-edoc-palette-group>
                    <summary><span>Dividir a folha</span><small>Coloque os elementos lado a lado</small></summary>
                    <div class="edoc-grid edoc-layout-basico">
                        <?php foreach (($catalogo['layout'] ?? []) as $lay):
                            $cols = $lay['cols'] ?? [100];
                            if (!in_array((string) ($lay['id'] ?? ''), ['100', '2col', '3col', '4col'], true)) continue;
                        ?>
                        <button type="button" class="edoc-layout" draggable="true" data-drag-layout="<?= $esc(json_encode($cols)) ?>" title="Adicionar uma linha com <?= count($cols) ?> coluna(s)">
                            <?= $layoutBars($cols) ?>
                            <span><?= $esc($lay['label'] ?? '') ?></span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <details class="edoc-layout-mais">
                        <summary>Mais proporções de colunas</summary>
                        <div class="edoc-grid edoc-grid-3">
                            <?php foreach (($catalogo['layout'] ?? []) as $lay):
                                $cols = $lay['cols'] ?? [100];
                                if (in_array((string) ($lay['id'] ?? ''), ['100', '50_50', '2col', '3col', '4col'], true)) continue;
                            ?>
                            <button type="button" class="edoc-layout" draggable="true" data-drag-layout="<?= $esc(json_encode($cols)) ?>" title="Largura das colunas: <?= $esc($lay['label'] ?? '') ?>%">
                                <?= $layoutBars($cols) ?>
                                <span><?= $esc($lay['label'] ?? '') ?></span>
                            </button>
                            <?php endforeach; ?>
                        </div>
                    </details>
                </details>
                <?php
                $ajudasElementos = [
                    'titulo' => ['Título', 'Nome do documento ou de uma seção'],
                    'texto' => ['Texto simples', 'Parágrafos e informações curtas'],
                    'texto_rico' => ['Texto formatado', 'Negrito, listas e links'],
                    'logo' => ['Logo da escola', 'Usa a imagem cadastrada da escola'],
                    'imagem' => ['Imagem', 'Envie uma foto, brasão ou assinatura'],
                    'tabela' => ['Tabela editável', 'Crie células, linhas e colunas'],
                    'dados_escola' => ['Escola', 'Nome e identificação da instituição'],
                    'dados_aluno' => ['Aluno', 'Nome, RA e dados do estudante'],
                    'dados_responsavel' => ['Responsável', 'Dados do responsável pelo aluno'],
                    'dados_turma' => ['Turma', 'Série, curso e turma do aluno'],
                    'boletim' => ['Boletim', 'Insere o layout de Boletim criado em Acadêmico → Boletim'],
                    'demonstrativo_notas' => ['Demonstrativo de Notas', 'Escolha o período configurado no Demonstrativo'],
                    'frequencia' => ['Frequência', 'Resumo de presença do aluno'],
                    'observacoes' => ['Observações', 'Informações adicionais do documento'],
                    'assinaturas' => ['Assinaturas', 'Espaço para os responsáveis assinarem'],
                    'tabela_aluno' => ['Identificação do aluno', 'Dados do estudante em tabela'],
                    'tabela_notas' => ['Tabela de notas simples', 'Notas e faltas por bimestre (sem recuperações)'],
                    'tabela_coletiva' => ['Tabela da turma', 'Resultados de vários alunos'],
                    'tabela_frequencia' => ['Tabela de frequência', 'Presenças e faltas por período'],
                    'historico' => ['Histórico escolar', 'Trajetória e resultados do aluno'],
                    'resultado_final' => ['Resultado final', 'Situação final do estudante'],
                    'linha' => ['Linha divisória', 'Separe partes do documento'],
                    'espacador' => ['Espaço em branco', 'Ajuste a distância entre elementos'],
                    'pagina' => ['Número da página', 'Numeração das páginas na emissão'],
                    'quebra_pagina' => ['Nova página', 'Continue o conteúdo na próxima folha'],
                    'qrcode' => ['QR Code', 'Código de verificação na emissão'],
                ];
                include __DIR__ . '/_campos-editor.php';
                ?>
            </div>
            <div class="edoc-pane" id="pane-estrutura" style="display:none">
                <p class="edoc-hint">Veja as partes do documento. Selecione um item para ajustar suas propriedades na lateral direita.</p>
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
                <span class="edoc-folha-info-wrap">
                    <button type="button" class="edoc-btn edoc-btn-icon" id="edoc-folha-info" aria-expanded="false" aria-controls="edoc-folha-info-pop" title="Medidas da folha para impressão">
                        <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                    </button>
                    <div class="edoc-folha-info-pop" id="edoc-folha-info-pop" role="dialog" aria-label="Medidas da folha" hidden></div>
                </span>
                <span class="edoc-canvas-hint">Edite na folha. Use Ver resultado para conferir as margens e as páginas da impressão.</span>
                <span class="edoc-canvas-hint" id="edoc-folha-aviso"></span>
                <?php if (!empty($layout_sugerido)): ?>
                <button type="button" class="edoc-btn" id="edoc-layout-sugerido" title="Monta cabeçalho, identificação, notas e assinaturas lado a lado">
                    <i class="fa-solid fa-wand-magic-sparkles"></i> Montar boletim
                </button>
                <?php endif; ?>
            </div>
            <section class="edoc-pdf-preview" id="edoc-pdf-preview" aria-label="Resultado da impressão" hidden>
                <div class="edoc-pdf-preview-bar">
                    <div><strong>Resultado da impressão</strong><p>Confira as páginas como serão emitidas no PDF.</p></div>
                    <button type="button" class="edoc-btn" id="edoc-pdf-refresh"><i class="fa-solid fa-rotate-right" aria-hidden="true"></i> Atualizar prévia</button>
                </div>
                <p class="edoc-pdf-message" id="edoc-pdf-message" role="status">Preparando a prévia…</p>
                <iframe id="edoc-pdf-frame" title="Prévia do PDF que será emitido" hidden></iframe>
                <p class="edoc-pdf-preview-help">Para alterar o documento, clique em <strong>Voltar à edição</strong>. Você também pode abrir o PDF em outra aba.</p>
            </section>
            <div class="edoc-stage">
                <div id="edoc-paper-wrap" class="edoc-paper-wrap">
                    <div class="edoc-ruler-h"></div>
                    <div class="edoc-ruler-v"></div>
                    <div id="edoc-paper" class="edoc-paper"></div>
                </div>
            </div>
            <section class="edoc-html-stage" id="edoc-html-stage" aria-label="Editor HTML" hidden>
                <form id="edoc-html-form" class="edoc-html-stage-form">
                    <header>
                        <div>
                            <h2 id="edoc-html-title">Editar HTML</h2>
                            <p id="edoc-html-desc">Os campos entre {{...}} são preenchidos para cada aluno. Edite a estrutura e os estilos à vontade.</p>
                        </div>
                        <div class="edoc-html-stage-actions">
                            <button type="button" class="edoc-btn" id="edoc-html-indent">Indentar código</button>
                            <button type="button" class="edoc-btn" id="edoc-html-cancel">Voltar à folha</button>
                            <button type="submit" class="edoc-btn edoc-btn-primary">Aplicar na folha</button>
                        </div>
                    </header>
                    <label class="edoc-sr-only" for="edoc-html-code">Código HTML</label>
                    <textarea id="edoc-html-code" spellcheck="false" autocapitalize="off" autocomplete="off" wrap="off"></textarea>
                    <footer><span>As alterações entram na folha ao clicar em Aplicar. Esc volta sem salvar.</span></footer>
                </form>
            </section>
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
                    <option value="boletim">Boletim</option>
                    <option value="ata_resultados">Ata de resultados finais</option>
                    <option value="historico">Histórico escolar</option>
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

<dialog class="edoc-table-dialog" id="edoc-table-dialog" aria-labelledby="edoc-table-title">
    <form id="edoc-table-form">
        <h2 id="edoc-table-title">Criar tabela</h2>
        <p>Escolha o tamanho. Depois, preencha as células diretamente na folha.</p>
        <div class="edoc-table-dimensions">
            <div><label for="edoc-table-rows">Linhas</label><input id="edoc-table-rows" type="number" value="4" min="1" max="40" step="1" required><small>De cima para baixo</small></div>
            <div><label for="edoc-table-cols">Colunas</label><input id="edoc-table-cols" type="number" value="3" min="1" max="20" step="1" required><small>Da esquerda para a direita</small></div>
        </div>
        <label class="edoc-chk"><input id="edoc-table-header" type="checkbox" checked> Primeira linha como cabeçalho</label>
        <div id="edoc-table-sample" class="edoc-table-sample" aria-hidden="true"></div>
        <p id="edoc-table-description" aria-live="polite"></p>
        <p class="edoc-hint">A tabela ocupa a largura da coluna escolhida. Você poderá adicionar linhas e colunas depois.</p>
        <div class="edoc-table-dialog-actions"><button type="button" class="edoc-btn" id="edoc-table-cancel">Cancelar</button><button type="submit" class="edoc-btn edoc-btn-primary">Inserir tabela</button></div>
    </form>
</dialog>

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
  modeloQuadroHtml: <?= json_encode(\App\Modulos\ModelosDocumentos\Services\QuadroBoletimEditavelService::expor((string) (($vars_preview ?? [])['quadro_notas_html'] ?? ''))['html'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>,
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
