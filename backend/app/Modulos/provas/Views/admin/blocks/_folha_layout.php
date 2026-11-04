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
$posicaoLogo = (string) ($layout['posicao_logo'] ?? 'centro');
if (!in_array($posicaoLogo, ['esquerda', 'centro', 'direita'], true)) {
    $posicaoLogo = 'centro';
}
$classes[] = 'logo-' . $posicaoLogo;
$espacoQuestao = max(4, min(48, (int) ($layout['espaco_questao'] ?? 12)));
$espacoAlternativa = max(0, min(24, (int) ($layout['espaco_alternativa'] ?? 4)));
$textoExtra = trim((string) ($layout['texto_extra'] ?? ''));
$textoExtraNoFim = (string) ($layout['texto_extra_lugar'] ?? 'cabecalho') === 'final';
$meta = [];
if ($dataTexto !== '' && !empty($layout['mostrar_data'])) {
    $meta[] = 'Data: ' . $dataTexto;
}
if ($horarioTexto !== '' && !empty($layout['mostrar_horario'])) {
    $meta[] = $horarioTexto;
}
$primeiraMateria = true;
$jaTeveQuestao = false;
$indiceFolha = 0;
?>
<style>
.folha-prova { font-family: Georgia, "Times New Roman", serif; font-size: var(--fonte); color: #111; background: #fff; max-width: 210mm; margin: 0 auto; padding: var(--margem); box-shadow: 0 2px 10px rgba(0,0,0,.08); }
.folha-prova .logo { height: var(--logo); width: auto; max-width: 70mm; display: block; margin: 0 auto 8px; object-fit: contain; }
.folha-prova.logo-esquerda .logo { margin-left: 0; margin-right: auto; }
.folha-prova.logo-direita .logo { margin-left: auto; margin-right: 0; }
.folha-prova .escola { text-align: center; font-family: system-ui, sans-serif; font-size: 11px; letter-spacing: .04em; text-transform: uppercase; color: #333; margin: 0 0 6px; }
.folha-prova h1 { font-size: 1.35em; text-align: center; margin: 0 0 4px; line-height: 1.25; }
.folha-prova .subtitulo, .folha-prova .meta { text-align: center; margin: 0 0 6px; color: #333; }
.folha-prova .campos { display: flex; flex-wrap: wrap; gap: 8px 18px; margin: 10px 0 8px; font-family: system-ui, sans-serif; font-size: .92em; }
.folha-prova .campo { display: flex; align-items: flex-end; gap: 6px; min-width: 42%; flex: 1; }
.folha-prova .campo .risco { flex: 1; border-bottom: 1px solid #111; min-height: 1.1em; }
.folha-prova .instrucoes, .folha-prova .texto-extra { margin: 8px 0 0; padding: 6px 8px; border: 1px solid #bbb; font-family: system-ui, sans-serif; font-size: .9em; white-space: pre-wrap; }
.folha-prova .texto-extra.final { margin-top: 16px; }
.folha-prova .cab { padding-bottom: 8px; border-bottom: 2px solid #111; margin-bottom: 12px; }
.folha-prova .materia { margin-top: 14px; }
.folha-prova h2.titulo-materia { font-family: system-ui, sans-serif; font-size: 1.05em; margin: 14px 0 8px; border-bottom: 1px solid #111; padding-bottom: 3px; }
.folha-miolo > h2.titulo-materia:first-child { margin-top: 0; }
.folha-prova .prof { font-weight: 500; font-size: .85em; color: #333; }
.folha-prova .questao { margin: 0 0 var(--espaco-questao, 12px); }
.folha-prova .questao-num { font-family: system-ui, sans-serif; font-weight: 700; margin-bottom: 4px; }
.folha-prova .pts { font-weight: 500; color: #333; }
.folha-prova .enunciado, .folha-prova .alternativa { line-height: 1.45; }
.folha-prova .alternativa { margin: var(--espaco-alt, 4px) 0 var(--espaco-alt, 4px) 12px; }
.folha-prova .questao img, .folha-prova .enunciado img { max-height: var(--qimg, var(--img)); max-width: 100%; width: auto; height: auto; display: block; margin: 6px 0; }
.folha-prova .linhas { height: 96px; margin-top: 8px; background-image: repeating-linear-gradient(#fff, #fff 23px, #cfcfcf 24px); }
.folha-prova .rodape-tela { margin-top: 16px; border-top: 1px solid #ccc; padding-top: 4px; text-align: center; font-family: system-ui, sans-serif; font-size: 10px; color: #555; }
.folha-prova.sem-logo .logo, .folha-prova.sem-escola .escola, .folha-prova.sem-subtitulo .subtitulo,
.folha-prova.sem-instrucoes .instrucoes, .folha-prova.sem-pontos .pts, .folha-prova.sem-professor .prof,
.folha-prova.sem-rodape .rodape-tela, .folha-prova.sem-campo-nome .campo-nome,
.folha-prova.sem-campo-turma .campo-turma, .folha-prova.sem-campo-numero .campo-numero,
.folha-prova .oculta { display: none !important; }
.folha-prova.sem-data.sem-horario .meta { display: none; }
@media screen {
    .folha-prova .materia.quebra-materia,
    .folha-prova .questao.quebra-questao {
        margin-top: 18px;
        padding-top: 14px;
        border-top: 2px dashed #cbd5e1;
    }
    .folha-prova .materia.quebra-materia::before,
    .folha-prova .questao.quebra-questao::before {
        content: "Próxima página";
        display: block;
        margin: 0 0 10px;
        font-family: system-ui, sans-serif;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: .02em;
        color: #64748b;
    }
}
@media print {
    .folha-prova { box-shadow: none; max-width: none; margin: 0; padding: 0; }
    .folha-prova .questao { break-inside: avoid; page-break-inside: avoid; }
    .folha-prova .materia.quebra-materia { break-before: page; page-break-before: always; }
    .folha-prova .questao.quebra-questao { break-before: page; page-break-before: always; margin-top: 0; padding-top: 0; border-top: 0; }
    .folha-prova .materia.quebra-materia::before,
    .folha-prova .questao.quebra-questao::before { content: none; display: none; }
    .folha-prova .rodape-tela { position: fixed; bottom: 4mm; left: 0; right: 0; border: 0; margin: 0; }
    .barra-questao { display: none !important; }
    .folha-fora, .folha-fonte { display: none !important; }
    .folha-prova.folha-paginada .questao { break-inside: auto; page-break-inside: auto; }
    .folha-prova.folha-paginada .materia.quebra-materia,
    .folha-prova.folha-paginada .questao.quebra-questao { break-before: auto; page-break-before: auto; }
    .folha-prova.folha-paginada .rodape-tela { position: static; left: auto; right: auto; bottom: auto; }
}
.folha-prova.folha-paginada { max-width: none; margin: 0; padding: 0; background: transparent; box-shadow: none; }
.folha-prova.folha-paginada .materia.quebra-materia,
.folha-prova.folha-paginada .questao.quebra-questao { margin-top: 0; padding-top: 0; border-top: 0; }
.folha-prova.folha-paginada .materia.quebra-materia::before,
.folha-prova.folha-paginada .questao.quebra-questao::before { content: none; display: none; }
.folha-paginas { display: flex; flex-direction: column; align-items: center; gap: 28px; }
.folha-pagina {
    width: 210mm; height: 297mm; box-sizing: border-box; flex-shrink: 0;
    padding: var(--margem); padding-bottom: calc(var(--margem) + 8mm);
    display: flex; flex-direction: column;
    background: #fff; box-shadow: 0 2px 10px rgba(0,0,0,.12);
}
.folha-pagina-alta { height: auto; min-height: 297mm; }
.folha-miolo { position: relative; flex: 1 1 auto; min-height: 0; overflow: hidden; }
.folha-pagina-alta .folha-miolo { flex: none; height: auto !important; max-height: none !important; overflow: visible; }
.folha-pe .rodape-tela { border: 0; margin: 0; padding: 0; }
.folha-pe {
    position: relative; flex: 0 0 auto; margin-top: 6px;
    display: flex; justify-content: center; align-items: center; gap: 16px;
    min-height: 14px; text-align: center; font-family: system-ui, sans-serif; font-size: 10px; color: #555;
}
.folha-prova.sem-rodape .folha-pe-texto { display: none !important; }
.folha-prova.sem-numero .folha-num { display: none !important; }
.folha-fonte { display: none !important; }
.folha-fora { max-width: 210mm; margin: 16px auto 0; background: #fff; border: 1px dashed #cbd5e1; padding: 12px 16px; }
.folha-fora:empty { display: none !important; }
.folha-fora::before {
    content: "Fora da prova"; display: block; font-family: system-ui, sans-serif;
    font-size: 12px; font-weight: 600; color: #64748b; margin-bottom: 8px;
}
@media print {
    .folha-paginas { display: block; gap: 0; }
    .folha-prova.folha-paginada .folha-pagina,
    .folha-prova.folha-paginada .folha-pagina-alta {
        display: block; width: auto; height: auto !important; min-height: 0 !important; max-height: none !important;
        margin: 0; padding: 0; box-shadow: none; overflow: visible !important;
        break-after: page; page-break-after: always; background: #fff;
    }
    .folha-prova.folha-paginada .folha-pagina:last-child { break-after: auto; page-break-after: auto; }
    .folha-prova.folha-paginada .folha-miolo {
        position: static !important; height: auto !important; max-height: none !important;
        overflow: visible !important; flex: none; margin: 0;
    }
    .folha-prova.folha-paginada .folha-pe { position: static; margin-top: 8px; }
}
</style>
<article id="folha-prova" class="<?= htmlspecialchars(implode(' ', $classes), ENT_QUOTES, 'UTF-8') ?>"
    style="--margem: <?= $margem ?>mm; --fonte: <?= $fonte ?>px; --logo: <?= $logoAltura ?>px; --img: <?= $imagemAltura ?>px; --espaco-questao: <?= $espacoQuestao ?>px; --espaco-alt: <?= $espacoAlternativa ?>px;"
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
        <p class="texto-extra<?= ($textoExtra === '' || $textoExtraNoFim) ? ' oculta' : '' ?>" id="cab-texto-extra"><?= htmlspecialchars($textoExtra, ENT_QUOTES, 'UTF-8') ?></p>
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
        $indiceFolha++;
        $secId = $indiceFolha;
    ?>
    <section class="materia<?= $visiveis === 0 ? ' oculta' : '' ?><?= $quebraMateria ? ' quebra-materia' : '' ?>" data-mid="<?= (int) ($materia['materia_id'] ?? 0) ?>" data-prova="<?= (int) ($materia['prova_id'] ?? 0) ?>" data-sec="<?= $secId ?>">
        <h2 class="titulo-materia" data-sec="<?= $secId ?>">
            <?= htmlspecialchars((string) ($materia['materia_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
            <?php if (trim((string) ($materia['professor_nome'] ?? '')) !== ''): ?>
            <span class="prof">— <?= htmlspecialchars((string) $materia['professor_nome'], ENT_QUOTES, 'UTF-8') ?></span>
            <?php endif; ?>
        </h2>
        <?php foreach ($materia['questoes'] as $questao): ?>
        <?php
            $qid = (int) ($questao['id'] ?? 0);
            $quebraQuestao = !empty($questao['incluida']) && !empty($questao['quebra']) && $jaTeveQuestao;
            if (!empty($questao['incluida'])) {
                $jaTeveQuestao = true;
            }
        ?>
        <div class="questao<?= empty($questao['incluida']) ? ' oculta' : '' ?><?= $quebraQuestao ? ' quebra-questao' : '' ?>"
             id="q-folha-<?= $qid ?>" data-qid="<?= $qid ?>" data-sec="<?= $secId ?>" style="--qimg: <?= (int) ($questao['altura_imagem'] ?? $imagemAltura) ?>px;">
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

    <p class="texto-extra final<?= ($textoExtra === '' || !$textoExtraNoFim) ? ' oculta' : '' ?>" id="fim-texto-extra"><?= htmlspecialchars($textoExtra, ENT_QUOTES, 'UTF-8') ?></p>
    <p class="rodape-tela" id="cab-rodape"><?= htmlspecialchars((string) ($layout['rodape'] ?? ''), ENT_QUOTES, 'UTF-8') ?></p>
</article>
<script>
window.paginarFolha = function () {
    var folha = document.getElementById('folha-prova');
    if (!folha || folha.getAttribute('data-paginando') === '1') return;
    folha.setAttribute('data-paginando', '1');
    try {
        var fonte = folha.querySelector(':scope > .folha-fonte');
        var paginas = folha.querySelector(':scope > .folha-paginas');
        var fora = folha.querySelector(':scope > .folha-fora');
        if (!fonte) {
            fonte = document.createElement('div');
            fonte.className = 'folha-fonte';
            paginas = document.createElement('div');
            paginas.className = 'folha-paginas';
            fora = document.createElement('div');
            fora.className = 'folha-fora';
            var existentes = Array.prototype.slice.call(folha.childNodes);
            folha.appendChild(paginas);
            folha.appendChild(fora);
            folha.appendChild(fonte);
            existentes.forEach(function (no) { fonte.appendChild(no); });
        }

        var cab = folha.querySelector('.cab');
        if (cab && cab.parentElement !== fonte) fonte.insertBefore(cab, fonte.firstChild);
        Array.prototype.forEach.call(folha.querySelectorAll('.materia'), function (sec) {
            if (sec.parentElement !== fonte) fonte.appendChild(sec);
            var id = sec.getAttribute('data-sec');
            var titulo = folha.querySelector('h2.titulo-materia[data-sec="' + id + '"]');
            if (titulo && titulo.parentElement !== sec) sec.insertBefore(titulo, sec.firstChild);
            var lista = document.querySelector('ul[data-lista="' + (sec.getAttribute('data-prova') || '') + '"]');
            if (lista) {
                Array.prototype.forEach.call(lista.querySelectorAll('li[data-qid]'), function (li) {
                    var questao = document.getElementById('q-folha-' + li.getAttribute('data-qid'));
                    if (questao) sec.appendChild(questao);
                });
            }
            Array.prototype.forEach.call(folha.querySelectorAll('.questao[data-sec="' + id + '"]'), function (questao) {
                if (questao.parentElement !== sec) sec.appendChild(questao);
            });
        });
        var extra = document.getElementById('fim-texto-extra');
        var rodape = document.getElementById('cab-rodape');
        if (extra) fonte.appendChild(extra);
        if (rodape) fonte.appendChild(rodape);
        paginas.innerHTML = '';
        fora.innerHTML = '';

        var atual = null;
        var limite = 0;
        function pxDe(altura) {
            var sonda = document.createElement('div');
            sonda.style.cssText = 'position:absolute;left:-9999px;top:0;width:1px;height:' + altura + ';';
            document.body.appendChild(sonda);
            var valor = sonda.getBoundingClientRect().height;
            sonda.remove();
            return valor;
        }
        var margemCss = (window.getComputedStyle(folha).getPropertyValue('--margem') || '14mm').trim();
        limite = Math.floor(pxDe('297mm') - (pxDe(margemCss) * 2) - pxDe('8mm') - 8);
        if (limite < 200) limite = Math.floor(pxDe('240mm'));

        function novaPagina() {
            var pag = document.createElement('section');
            pag.className = 'folha-pagina';
            var miolo = document.createElement('div');
            miolo.className = 'folha-miolo';
            var pe = document.createElement('div');
            pe.className = 'folha-pe';
            var texto = document.createElement('div');
            texto.className = 'folha-pe-texto';
            var num = document.createElement('div');
            num.className = 'folha-num';
            pe.appendChild(texto);
            pe.appendChild(num);
            pag.appendChild(miolo);
            pag.appendChild(pe);
            paginas.appendChild(pag);
            atual = { miolo: miolo, estourou: false };
        }
        function estoura(miolo) {
            var caixa = miolo.clientHeight;
            if (caixa < 120) caixa = limite;
            return miolo.scrollHeight > caixa + 2;
        }
        function marcarSeNaoCabe() {
            if (!atual || !estoura(atual.miolo)) return;
            atual.miolo.style.height = 'auto';
            atual.miolo.parentElement.classList.add('folha-pagina-alta');
            atual.estourou = true;
        }
        function colocar(el, forcar) {
            if (!el) return;
            if (!atual || atual.estourou) novaPagina();
            if (forcar && atual.miolo.childElementCount > 0) novaPagina();
            atual.miolo.appendChild(el);
            if (estoura(atual.miolo) && atual.miolo.childElementCount > 1) {
                novaPagina();
                atual.miolo.appendChild(el);
            }
            marcarSeNaoCabe();
        }
        function colocarJunto(a, b, forcar) {
            if (!atual || atual.estourou) novaPagina();
            if (forcar && atual.miolo.childElementCount > 0) novaPagina();
            if (a) atual.miolo.appendChild(a);
            if (b) atual.miolo.appendChild(b);
            var extras = (a ? 1 : 0) + (b ? 1 : 0);
            if (estoura(atual.miolo) && atual.miolo.childElementCount > extras) {
                novaPagina();
                if (a) atual.miolo.appendChild(a);
                if (b) atual.miolo.appendChild(b);
            }
            marcarSeNaoCabe();
        }

        if (cab) colocar(cab, false);
        Array.prototype.forEach.call(fonte.querySelectorAll(':scope > .materia'), function (sec) {
            if (sec.classList.contains('oculta')) return;
            var titulo = sec.querySelector('h2.titulo-materia');
            var questoes = Array.prototype.filter.call(sec.querySelectorAll(':scope > .questao'), function (q) {
                return !q.classList.contains('oculta') && !q.classList.contains('fora-da-prova');
            });
            Array.prototype.forEach.call(sec.querySelectorAll(':scope > .questao.fora-da-prova'), function (q) {
                fora.appendChild(q);
            });
            if (!questoes.length) return;
            var quebraSecao = sec.classList.contains('quebra-materia');
            questoes.forEach(function (questao, indice) {
                var quebraQuestao = questao.classList.contains('quebra-questao');
                if (indice === 0) {
                    colocarJunto(titulo, questao, quebraSecao || quebraQuestao);
                    return;
                }
                colocar(questao, quebraQuestao);
            });
        });
        if (extra && !extra.classList.contains('oculta')) colocar(extra, false);

        var textoRodape = rodape ? rodape.textContent : '';
        var folhas = paginas.querySelectorAll('.folha-pagina');
        Array.prototype.forEach.call(folhas, function (pag, indice) {
            var numero = pag.querySelector('.folha-num');
            if (numero) numero.textContent = String(indice + 1);
            if (indice === folhas.length - 1 && rodape) {
                var destino = pag.querySelector('.folha-pe-texto');
                if (destino) destino.replaceWith(rodape);
            } else {
                var espelho = pag.querySelector('.folha-pe-texto');
                if (espelho) espelho.textContent = textoRodape;
            }
        });
        folha.classList.add('folha-paginada');
    } finally {
        folha.removeAttribute('data-paginando');
    }
};
document.addEventListener('load', function (ev) {
    if (ev.target && ev.target.tagName === 'IMG' && ev.target.closest('#folha-prova') && window.paginarFolha) {
        window.paginarFolha();
    }
}, true);
</script>
