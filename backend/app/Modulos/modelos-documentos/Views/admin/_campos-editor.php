<?php
/** Catálogo de inserção organizado pela origem dos dados. */
$renderElementos = static function (array $itens) use ($esc, $ajudasElementos): void { ?>
<div class="edoc-element-list">
<?php foreach ($itens as $it):
    $tipo = (string) ($it['tipo'] ?? '');
    [$rotulo, $ajuda] = $ajudasElementos[$tipo] ?? [(string) ($it['label'] ?? ''), (string) ($it['ajuda'] ?? '')]; ?>
<button type="button" class="edoc-item" draggable="true" data-drag-type="<?= $esc($tipo) ?>" aria-label="<?= $esc($rotulo) ?>" title="<?= $esc($ajuda) ?>"><i class="fa-solid <?= $esc($it['icone'] ?? 'fa-cube') ?>" aria-hidden="true"></i><span class="edoc-item-text"><strong><?= $esc($rotulo) ?></strong><small><?= $esc($ajuda) ?></small></span><i class="fa-solid fa-plus edoc-item-add" aria-hidden="true"></i></button>
<?php endforeach; ?></div>
<?php };
$renderCampos = static function (string $titulo, array $chaves) use ($esc, $placeholders, $rotuloVar): void { ?>
<details class="edoc-var-drop edoc-var-group-side"><summary><?= $esc($titulo) ?></summary><div class="edoc-var-chips">
<?php foreach ($chaves as $ch): if (!isset($placeholders[$ch])) continue; ?>
<button type="button" class="edoc-var-chip" draggable="true" data-drag-var="<?= $esc($ch) ?>" data-var-nome="<?= $esc($placeholders[$ch]) ?>" title="<?= $esc($placeholders[$ch]) ?>"><span class="edoc-var-nome"><?= $esc($placeholders[$ch]) ?></span><span class="edoc-var-token"><?= $esc($rotuloVar($ch)) ?></span></button>
<?php endforeach; ?></div></details>
<?php };
?>
<details class="edoc-tool-group" data-edoc-palette-group open>
<summary><span>Adicionar conteúdo</span><small>Textos, imagens e tabelas</small></summary>
<?php $renderElementos($catalogo['conteudo'] ?? []); ?>
<p class="edoc-palette-tip">Dica: cole imagens ou tabelas do Excel com Ctrl+V (⌘V no Mac).</p>
</details>
<details class="edoc-tool-group" data-edoc-palette-group>
<summary><span>Cadastro</span><small>Aluno, pais e responsáveis, escola e matrícula</small></summary>
<p class="edoc-hint">Escolha cada informação e clique ou arraste para montar o documento.</p>
<?php
$renderCampos('Aluno', $grupos['aluno']['chaves'] ?? []);
$renderCampos('Pais e responsáveis', $grupos['responsavel']['chaves'] ?? []);
$renderCampos('Escola', $grupos['escola']['chaves'] ?? []);
$renderCampos('Turma', ['turma_nome', 'turma_frase', 'turno', 'numero_chamada']);
$renderCampos('Série', ['serie', 'organizacao', 'proxima_serie']);
$renderCampos('Curso', ['curso_nome', 'etapa']);
$renderCampos('Matrícula', ['matricula_numero', 'ano_letivo', 'situacao_matricula', 'data_entrada', 'data_saida', 'tipo_matricula']);
$renderCampos('Contrato e valores', $grupos['contrato']['chaves'] ?? []);
?>
</details>
<details class="edoc-tool-group" data-edoc-palette-group>
<summary><span>Acadêmico</span><small>Boletim, eventos, médias, faltas e histórico</small></summary>
<?php $renderCampos('Resultados e frequência', $grupos['academico']['chaves'] ?? []); ?>
<div class="edoc-demo" id="edoc-demo">
<h4>Disciplinas e eventos</h4>
<p class="edoc-hint">Escolha turma, aluno e boletim na demonstração. Depois insira cada nota, falta ou média final.</p>
<label class="edoc-demo-check" for="edoc-demo-sigla"><input type="checkbox" id="edoc-demo-sigla"> Sigla no lugar do nome</label>
<div class="edoc-atalhos"><button type="button" class="edoc-btn" data-atalho="glossario">Glossário</button><button type="button" class="edoc-btn" data-atalho="componentes">Disciplinas</button><button type="button" class="edoc-btn" data-atalho="serie">Série e turma</button></div>
<details class="edoc-var-drop" id="edoc-demo-comps-drop" hidden><summary>Disciplinas, notas e médias <span class="edoc-var-count" id="edoc-demo-comps-count"></span></summary><div class="edoc-var-chips" id="edoc-demo-comps"></div></details>
</div>
<details class="edoc-var-drop"><summary>Quadros prontos</summary>
<?php $renderElementos(array_merge(array_values(array_filter($catalogo['dados'] ?? [], static fn ($i) => in_array($i['tipo'], ['boletim', 'frequencia'], true))), $catalogo['tabelas'] ?? [])); ?>
</details>
</details>
<details class="edoc-tool-group" data-edoc-palette-group>
<summary><span>Documento</span><small>Emissão, numeração, observações e assinaturas</small></summary>
<?php $renderCampos('Dados do documento', $grupos['documento']['chaves'] ?? []); ?>
<?php $renderElementos(array_values(array_filter($catalogo['dados'] ?? [], static fn ($i) => in_array($i['tipo'], ['observacoes', 'assinaturas'], true)))); ?>
</details>
<details class="edoc-tool-group" data-edoc-palette-group>
<summary><span>Autorização e campos da emissão</span><small>Informações digitadas ao gerar o documento</small></summary>
<p class="edoc-hint">Clique para adicionar o campo. A secretaria informa o valor na emissão de cada documento.</p>
<div class="edoc-var-chips">
<?php foreach (\App\Modulos\ModelosDocumentos\Services\ModeloDocumentoService::camposAutorizacao() as $campo): ?>
<button type="button" class="edoc-var-chip" data-emissao-preset="<?= $esc(json_encode($campo)) ?>" title="Preencher ao emitir"><span class="edoc-var-nome"><?= $esc($campo['rotulo']) ?></span><span class="edoc-var-token">Preencher na emissão</span></button>
<?php endforeach; ?>
</div>
<div class="edoc-sec-label">CAMPOS ADICIONADOS AO MODELO</div>
<div id="edoc-campos-lista" class="edoc-campos-lista"></div>
<button type="button" class="edoc-btn" id="edoc-campo-novo" style="width:100%;margin-bottom:12px"><i class="fa-solid fa-plus"></i> Novo campo</button>
</details>
<details class="edoc-tool-group" data-edoc-palette-group>
<summary><span>Acabamento da página</span><small>Espaços, divisórias e numeração</small></summary>
<?php $renderElementos($catalogo['extras'] ?? []); ?>
</details>
