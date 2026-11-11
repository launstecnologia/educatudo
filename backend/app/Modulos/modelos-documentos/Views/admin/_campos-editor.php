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
$renderCampos = static function (string $titulo, array $chaves, string $icone = 'fa-folder') use ($esc, $placeholders, $rotuloVar): void {
    $visiveis = array_values(array_filter($chaves, static fn ($ch) => isset($placeholders[$ch])));
    if ($visiveis === []) {
        return;
    }
    $qtd = count($visiveis);
    ?>
<details class="edoc-var-drop edoc-var-group-side">
<summary>
<span class="edoc-var-ico" aria-hidden="true"><i class="fa-solid <?= $esc($icone) ?>"></i></span>
<span class="edoc-var-titulo"><?= $esc($titulo) ?></span>
<span class="edoc-var-count"><?= (int) $qtd ?></span>
</summary>
<div class="edoc-var-chips">
<?php foreach ($visiveis as $ch): ?>
<button type="button" class="edoc-var-chip" draggable="true" data-drag-var="<?= $esc($ch) ?>" data-var-nome="<?= $esc($placeholders[$ch]) ?>" title="<?= $esc($placeholders[$ch]) ?>"><span class="edoc-var-nome"><?= $esc($placeholders[$ch]) ?></span><span class="edoc-var-token"><?= $esc($rotuloVar($ch)) ?></span></button>
<?php endforeach; ?>
</div>
</details>
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
<div class="edoc-var-stack">
<?php
$renderCampos('Aluno', $grupos['aluno']['chaves'] ?? [], 'fa-user-graduate');
$renderCampos('Pais e responsáveis', $grupos['responsavel']['chaves'] ?? [], 'fa-users');
$renderCampos('Escola', $grupos['escola']['chaves'] ?? [], 'fa-building-columns');
$renderCampos('Turma', ['turma_nome', 'turma_frase', 'turno', 'numero_chamada'], 'fa-chalkboard-user');
$renderCampos('Série', ['serie', 'organizacao', 'proxima_serie'], 'fa-layer-group');
$renderCampos('Curso', ['curso_nome', 'etapa'], 'fa-graduation-cap');
$renderCampos('Matrícula', ['matricula_numero', 'ano_letivo', 'situacao_matricula', 'data_entrada', 'data_saida', 'tipo_matricula'], 'fa-id-card');
$renderCampos('Contrato e valores', $grupos['contrato']['chaves'] ?? [], 'fa-file-invoice-dollar');
?>
</div>
</details>
<details class="edoc-tool-group" data-edoc-palette-group>
<summary><span>Acadêmico</span><small>Boletim, eventos, médias, faltas e histórico</small></summary>
<p class="edoc-hint">Na prévia, escolha a regra de aprovação. Clique em Demonstrativo e selecione o bimestre; Boletim é o layout criado em Acadêmico → Boletim.</p>
<div class="edoc-var-stack">
<?php $renderElementos(array_values(array_filter($catalogo['dados'] ?? [], static fn ($i) => ($i['tipo'] ?? '') === 'boletim'))); ?>
<div class="edoc-element-list edoc-demo-picker-wrap">
<div class="edoc-demo-picker" id="edoc-demo-picker">
<button type="button" class="edoc-item" id="edoc-demo-abrir" aria-haspopup="listbox" aria-expanded="false" aria-controls="edoc-demo-menu" title="Escolha o bimestre/semestre para inserir na folha">
<i class="fa-solid fa-table-cells" aria-hidden="true"></i>
<span class="edoc-item-text"><strong>Demonstrativo de Notas</strong><small>Clique para escolher 1º, 2º bimestre…</small></span>
<i class="fa-solid fa-chevron-down edoc-item-add" aria-hidden="true"></i>
</button>
<div class="edoc-demo-menu" id="edoc-demo-menu" role="listbox" hidden>
<p class="edoc-hint" id="edoc-periodos-hint">Escolha a regra de aprovação na prévia para listar os bimestres.</p>
<div class="edoc-demo-menu-list" id="edoc-periodos-chips"></div>
</div>
</div>
</div>
<div class="edoc-demo" id="edoc-demo">
<div class="edoc-demo-head">
<strong>Disciplinas, notas e médias</strong>
<p>Na prévia, escolha regra, curso/série e aluno. Depois abra a lista e arraste cada campo para a folha.</p>
</div>
<label class="edoc-demo-check" for="edoc-demo-sigla"><input type="checkbox" id="edoc-demo-sigla"> Sigla no lugar do nome</label>
<details class="edoc-var-drop" id="edoc-demo-comps-drop" hidden>
<summary>
<span class="edoc-var-ico" aria-hidden="true"><i class="fa-solid fa-book-open"></i></span>
<span class="edoc-var-titulo">Disciplinas, notas e médias</span>
<span class="edoc-var-count" id="edoc-demo-comps-count"></span>
</summary>
<div class="edoc-var-chips" id="edoc-demo-comps"></div>
</details>
</div>
<?php
$chavesAcademicoRestante = array_values(array_filter(
    $grupos['academico']['chaves'] ?? [],
    static fn ($ch) => $ch !== 'quadro_notas_html'
));
$renderCampos('Frequência, histórico e totais', $chavesAcademicoRestante, 'fa-chart-pie');
?>
<details class="edoc-var-drop">
<summary>
<span class="edoc-var-ico" aria-hidden="true"><i class="fa-solid fa-table"></i></span>
<span class="edoc-var-titulo">Outros quadros</span>
</summary>
<?php $renderElementos(array_merge(array_values(array_filter($catalogo['dados'] ?? [], static fn ($i) => ($i['tipo'] ?? '') === 'frequencia')), $catalogo['tabelas'] ?? [])); ?>
</details>
</div>
</details>
<details class="edoc-tool-group" data-edoc-palette-group>
<summary><span>Documento</span><small>Emissão, numeração, observações e assinaturas</small></summary>
<div class="edoc-var-stack">
<?php $renderCampos('Dados do documento', $grupos['documento']['chaves'] ?? [], 'fa-file-lines'); ?>
<?php $renderElementos(array_values(array_filter($catalogo['dados'] ?? [], static fn ($i) => in_array($i['tipo'], ['observacoes', 'assinaturas'], true)))); ?>
</div>
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
