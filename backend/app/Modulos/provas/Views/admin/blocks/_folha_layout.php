<?php
/**
 * Folha da prova impressa. Usada no editor e na impressão.
 *
 * @var array<string, mixed> $layout
 * @var list<array<string, mixed>> $materias
 */
$layout = is_array($layout ?? null) ? $layout : [];
$materias = is_array($materias ?? null) ? $materias : [];
$logoUrl = trim((string) ($logo_url ?? ''));
$nomeEscola = trim((string) ($nome_escola ?? ''));
$dataTexto = trim((string) ($data_texto ?? ''));
$horarioTexto = trim((string) ($horario_texto ?? ''));
$margem = (int) ($layout['margem'] ?? 14);
$fonte = (int) ($layout['tamanho_fonte'] ?? 12);
$logoAltura = (int) ($layout['altura_logo'] ?? 56);
$imagemAltura = (int) ($layout['altura_imagem'] ?? 180);
$classes = ['folha-prova'];
foreach ([
    'mostrar_logo' => 'sem-logo',
    'mostrar_nome_escola' => 'sem-escola',
    'mostrar_data' => 'sem-data',
    'mostrar_horario' => 'sem-horario',
    'campo_nome' => 'sem-campo-nome',
    'campo_turma' => 'sem-campo-turma',
    'campo_numero' => 'sem-campo-numero',
    'mostrar_pontos' => 'sem-pontos',
    'mostrar_professor' => 'sem-professor',
    'numero_pagina' => 'sem-numero',
] as $chave => $classeOff) {
    if (empty($layout[$chave])) {
        $classes[] = $classeOff;
    }
}
if (trim((string) ($layout['instrucoes'] ?? '')) === '') {
    $classes[] = 'sem-instrucoes';
}
if (trim((string) ($layout['subtitulo'] ?? '')) === '') {
    $classes[] = 'sem-subtitulo';
}
if (trim((string) ($layout['rodape'] ?? '')) === '') {
    $classes[] = 'sem-rodape';
}
$meta = [];
if ($dataTexto !== '' && !empty($layout['mostrar_data'])) {
    $meta[] = 'Data: ' . $dataTexto;
}
if ($horarioTexto !== '' && !empty($layout['mostrar_horario'])) {
    $meta[] = $horarioTexto;
}
$primeiraMateria = true;
?>
<style>
.folha-prova { font-family: Georgia, "Times New Roman", serif; font-size: var(--fonte); color: #111; background: #fff; max-width: 210mm; margin: 0 auto; padding: var(--margem); box-shadow: 0 2px 10px rgba(0,0,0,.08); }
.folha-prova .logo { height: var(--logo); width: auto; max-width: 70mm; display: block; margin: 0 auto 8px; object-fit: contain; }
.folha-prova .escola { text-align: center; font-family: system-ui, sans-serif; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: #333; margin: 0 0 6px; }
.folha-prova h1 { font-size: 1.35em; text-align: center; margin: 0 0 4px; line-height: 1.25; }
.folha-prova .subtitulo, .folha-prova .meta { text-align: center; margin: 0 0 6px; color: #333; }
.folha-prova .campos { display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 10px 0 8px; font-family: system-ui, sans-serif; font-size: .92em; }
.folha-prova .campo { display: flex; align-items: flex-end; gap: 6px; min-width: 42%; flex: 1; }
.folha-prova .campo .risco { flex: 1; border-bottom: 1px solid #111; min-height: 1.1em; }
.folha-prova .instrucoes { margin: 8px 0 0; padding: 6px 8px; border: 1px solid #bbb; font-family: system-ui, sans-serif; font-size: .9em; white-space: pre-wrap; }
.folha-prova .cab { padding-bottom: 8px; border-bottom: 2px solid #111; margin-bottom: 12px; }
.folha-prova .materia { margin-top: 14px; }
.folha-prova .materia h2 { font-family: system-ui, sans-serif; font-size: 1.05em; margin: 0 0 8px; border-bottom: 1px solid #111; padding-bottom: 3px; }
.folha-prova .prof { font-weight: 500; font-size: .85em; color: #333; }
.folha-prova .questao { margin: 0 0 12px; }
.folha-prova .questao-num { font-family: system-ui, sans-serif; font-weight: 700; margin-bottom: 4px; }
.folha-prova .pts { font-weight: 500; color: #333; }
.folha-prova .enunciado, .folha-prova .alternativa { line-height: 1.45; }
.folha-prova .alternativa { margin: 2px 0 2px 12px; }
.folha-prova .questao img, .folha-prova .enunciado img { max-height: var(--qimg, var(--img)); max-width: 100%; width: auto; height: auto; display: block; margin: 6px 0; }
.folha-prova .linhas { height: 96px; margin-top: 8px; background-image: repeating-linear-gradient(#fff, #fff 23px, #cfcfcf 24px); }
.folha-prova .rodape-tela { margin-top: 16px; border-top: 1px solid #ccc; padding-top: 4px; text-align: center; font-family: system-ui, sans-serif; font-size: 10px; color: #555; }
.folha-prova.sem-logo .logo, .folha-prova.sem-escola .escola, .folha-prova.sem-subtitulo .subtitulo,
.folha-prova.sem-instrucoes .instrucoes, .folha-prova.sem-pontos .pts, .folha-prova.sem-professor .prof,
.folha-prova.sem-rodape .rodape-tela, .folha-prova.sem-campo-nome .campo-nome,
.folha-prova.sem-campo-turma .campo-turma, .folha-prova.sem-campo-numero .campo-numero,
.folha-prova .oculta { display: none !important; }
.folha-prova.sem-data.sem-horario .meta { display: none; }
@media print {
    .folha-prova { box-shadow: none; max-width: none; margin: 0; padding: 0; }
    .folha-prova .questao { break-inside: avoid; page-break-inside: avoid; }
    .folha-prova .materia.quebra-materia { break-before: page; page-break-before: always; }
    .folha-prova .questao.quebra-questao { break-before: page; page-break-before: always; }
    .folha-prova .rodape-tela { position: fixed; bottom: 4mm; left: 0; right: 0; border: 0; margin: 0; }
}
</style>
<article id="folha-prova" class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?>"
    style="--margem: <?= $margem ?>mm; --fonte: <?= $fonte ?>px; --logo: <?= $logoAltura ?>px; --img: <?= $imagemAltura ?>px;"
    data-data="<?= htmlspecialchars($dataTexto, ENT_QUOTES, 'UTF-8') ?>"
    data-hora="<?= htmlspecialchars($horarioTexto, ENT_QUOTES, 'UTF-8') ?>">
    <header class="cab">
        <?php if ($logoUrl !== ''): ?>
        <img class="logo" src="<?= htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') ?>" alt="Logo da escola">
        <?php endif; ?>
        <p class="escola" id="cab-escola"><?= htmlspecialchars($nomeEscola, ENT_QUOTES, 'UTF-8') ?></p>
        <h1 id="cab-titulo"><?= htmlspecialchars((string) ($layout['titulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="subtitulo" id="cab-subtitulo"><?= htmlspecialchars((string) ($layout['subtitulo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
        <p class="meta" id="cab-meta"><?= htmlspecialchars(implode(' — ', $meta), ENT_QUOTES, 'UTF-8') ?></p>
        <div class="campos">
            <div class="campo campo-nome"><span>Nome</span><span class="risco"></span></div>
            <div class="campo campo-turma"><span>Turma</span><span class="risco"></span></div>
            <div class="campo campo-numero"><span>Nº</span><span class="risco"></span></div>
        </div>
        <p class="instrucoes" id="cab-instrucoes"><?= htmlspecialchars((string) ($layout['instrucoes'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
    </header>

    <?php foreach ($materias as $materia): ?>
    <?php
        $visiveis = 0;
        foreach ($materia['questoes'] as $questao) {
            if (!empty($questao['incluida'])) {
                $visiveis++;
            }
        }
        $quebraMateria = !$primeiraMateria && !empty($layout['quebrar_disciplina']) && $visiveis > 0;
        if ($visiveis > 0) {
            $primeiraMateria = false;
        }
        $primeiraDaDisciplina = true;
    ?>
    <section class="materia<?= $visiveis === 0 ? ' oculta' : '' ?><?= $quebraMateria ? ' quebra-materia' : '' ?>" data-mid="<?= (int) ($materia['materia_id'] ?? 0) ?>">
        <h2>
            <?= htmlspecialchars((string) ($materia['materia_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            <?php if (trim((string) ($materia['professor_nome'] ?? '')) !== ''): ?>
            <span class="prof">— <?= htmlspecialchars((string) $materia['professor_nome'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </h2>
        <?php foreach ($materia['questoes'] as $questao): ?>
        <?php
            $qid = (int) ($questao['id'] ?? 0);
            $quebraQuestao = !empty($questao['incluida']) && !empty($questao['quebra']) && !$primeiraDaDisciplina;
            if (!empty($questao['incluida'])) {
                $primeiraDaDisciplina = false;
            }
        ?>
        <div class="questao<?= empty($questao['incluida']) ? ' oculta' : '' ?><?= $quebraQuestao ? ' quebra-questao' : '' ?>"
             id="q-folha-<?= $qid ?>" data-qid="<?= $qid ?>" style="--qimg: <?= (int) ($questao['altura_imagem'] ?? $imagemAltura) ?>px;">
            <div class="questao-num">
                <?php if (!empty($questao['numero'])): ?>
                    Questão <?= (int) $questao['numero'] ?>
                <?php else: ?>
                    Questão discursiva
                <?php endif; ?>
                <span class="pts"><?= number_format((float) ($questao['valor'] ?? 0), 1, ',', '') ?> pt</span>
            </div>
            <div class="enunciado"><?= $questao['enunciado_html'] ?? '' ?></div>
            <?php if (!empty($questao['imagem_url'])): ?>
            <img src="<?= htmlspecialchars((string) $questao['imagem_url'], ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php endif; ?>
            <?php if (!empty($questao['objetiva'])): ?>
                <?php foreach ($questao['alternativas'] as $alternativa): ?>
                <div class="alternativa"><strong><?= htmlspecialchars((string) ($alternativa['letra'] ?? ''), ENT_QUOTES, 'UTF-8') ?>)</strong> <?= $alternativa['html'] ?? '' ?></div>
                <?php endforeach; ?>
            <?php else: ?>
            <div class="linhas"></div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>

    <p class="rodape-tela" id="cab-rodape"><?= htmlspecialchars((string) ($layout['rodape'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
</article>
