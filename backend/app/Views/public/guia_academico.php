<?php
$escola = (string) ($escola ?? 'EducaTudo');
$cssTema = (string) ($css_tema ?? '');
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$gruposGuia = is_array($diag['grupos'] ?? null) ? $diag['grupos'] : [];
$etapasGuia = [];
foreach ($gruposGuia as $grupoGuia) {
    foreach (($grupoGuia['passos'] ?? []) as $etapaGuia) {
        $etapaGuia['grupo_rotulo'] = (string) ($grupoGuia['label'] ?? '');
        $etapasGuia[] = $etapaGuia;
    }
}
$indiceReferenciaGuia = require __DIR__ . '/conteudo_guia/indice.php';
$inicioReferenciaGuia = [];
$fimReferenciaGuia = [];
foreach ($indiceReferenciaGuia as $chaveReferenciaGuia => $referenciaGuia) {
    $posicaoReferenciaGuia = (string) ($referenciaGuia['posicao'] ?? '');
    if (!in_array($posicaoReferenciaGuia, ['inicio', 'fim'], true)) {
        continue;
    }
    $novaEtapaReferenciaGuia = [
        'chave' => $chaveReferenciaGuia,
        'label' => (string) $referenciaGuia['label'],
        'grupo_rotulo' => (string) $referenciaGuia['grupo_rotulo'],
    ];
    if ($posicaoReferenciaGuia === 'inicio') {
        $inicioReferenciaGuia[] = $novaEtapaReferenciaGuia;
    } else {
        $fimReferenciaGuia[] = $novaEtapaReferenciaGuia;
    }
}
$etapasGuia = array_merge($inicioReferenciaGuia, $etapasGuia, $fimReferenciaGuia);
$totalGuia = count($etapasGuia);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<link rel="stylesheet" href="<?= $e(URL) ?>/assets/guia-onboarding/referencia/guia-referencia.css?v=2">
<title>Guia acadêmico · <?= $e($escola) ?></title>
<?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
<style><?= str_replace(['<', '>'], '', $cssTema) ?></style>
<style>
.guia{--guia-cor:#0084a0;--guia-texto:#20323e;--guia-suave:#61717e;--guia-borda:#dfe7ec;color:var(--guia-texto);background:#f5f8fa;margin:0;font:15px/1.6 system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}
.guia *{box-sizing:border-box}.guia [hidden]{display:none!important}.guia a{color:inherit}.guia button,.guia select{font:inherit}.guia button,.guia summary,.guia a{touch-action:manipulation}.guia :focus-visible{outline:3px solid #0084a0;outline-offset:4px}.guia-pular{position:absolute;left:16px;top:-100px;background:white;padding:12px;z-index:10}.guia-pular:focus{top:12px}.guia-cabecalho{background:#fff;border-bottom:1px solid var(--guia-borda)}.guia-cabecalho>div{max-width:1180px;margin:auto;padding:18px 28px;display:flex;align-items:center;justify-content:space-between;gap:16px}.guia-marca{display:block;width:220px;max-width:100%;height:auto}.guia-selo{color:var(--guia-suave);font-size:12px}.guia-layout{max-width:1180px;margin:auto;padding:34px 28px 48px;display:grid;grid-template-columns:250px minmax(0,1fr);gap:36px;align-items:start}.guia-menu{position:sticky;top:24px}.guia-menu h2{font-size:13px;font-weight:700;margin:0 0 20px}.guia-grupo-menu{margin:22px 0 8px;font-size:11px;text-transform:uppercase;letter-spacing:1px;font-weight:700;color:var(--guia-suave)}.guia-menu a{display:flex;gap:10px;align-items:center;min-height:44px;padding:8px 10px;text-decoration:none;border-radius:8px;font-size:13px;color:#4c606d}.guia-menu a:hover{background:#eaf1f5}.guia-menu a[aria-current=step]{background:#e1f3f7;color:#006b82;font-weight:650}.guia-numero{display:inline-flex;width:24px;height:24px;align-items:center;justify-content:center;flex-shrink:0;border-radius:50%;background:#eaf0f3;font-size:11px}.guia-menu a[aria-current=step] .guia-numero{background:var(--guia-cor);color:white}.guia-menu-nota{font-size:12px;color:var(--guia-suave);margin:22px 10px 0}.guia-intro{margin-bottom:24px}.guia-sobretitulo{font-size:11px;letter-spacing:1.3px;font-weight:700;color:var(--guia-cor);margin:0 0 6px}.guia h1{font-size:28px;letter-spacing:-.6px;line-height:1.25;font-weight:750;margin:0 0 8px}.guia-intro>p:last-child{color:var(--guia-suave);margin:0;font-size:14px}.guia-seletor{display:none}.guia-posicao{display:flex;align-items:center;justify-content:space-between;gap:12px;color:var(--guia-suave);font-size:12px;margin:0 0 10px}.guia-indicador{height:4px;background:#e0e9ee;border-radius:4px;overflow:hidden;margin-bottom:22px}.guia-indicador span{height:100%;display:block;background:var(--guia-cor);width:0}.guia-cartao{background:white;border:1px solid var(--guia-borda);border-radius:16px;box-shadow:0 5px 20px #213a4a05;overflow:hidden;margin-bottom:20px}.guia-conteudo{padding:28px 30px}.guia-etapa-grupo{color:var(--guia-cor);font-size:12px;font-weight:650;margin:0 0 6px}.guia h2.guia-titulo-etapa{font-size:26px;font-weight:750;line-height:1.25;letter-spacing:-.4px;margin:0 0 22px;scroll-margin-top:22px}.guia-o-que-e{margin:-10px 0 22px;color:#3d5160;font-size:15px;line-height:1.7}.guia-titulo-etapa:focus{outline:none}.guia-instrucoes{list-style:none;padding:0;margin:0;counter-reset:instrucao}.guia-instrucoes>li{counter-increment:instrucao;position:relative;padding:0 0 20px 38px;font-size:14px;line-height:1.75}.guia-instrucoes>li:before{content:counter(instrucao);position:absolute;left:0;top:1px;display:grid;place-items:center;width:25px;height:25px;background:#edf6f9;color:#00758f;border-radius:50%;font-size:12px;font-weight:700}.guia-instrucoes>li:last-child{padding-bottom:0}.guia-conferencia{display:flex;align-items:flex-start;gap:12px;background:#edf8f2;border-radius:10px;padding:16px 18px;margin-top:24px;color:#255c43}.guia-conferencia>span{font-size:18px;line-height:1.4}.guia-conferencia h3{font-size:13px;font-weight:700;margin:0 0 4px}.guia-conferencia p{margin:0;font-size:13px}.guia-ajuda{border-top:1px solid #e8eef2;margin-top:24px;padding-top:4px}.guia-ajuda details{border-bottom:1px solid #e8eef2}.guia-ajuda summary{cursor:pointer;padding:15px 0;font-size:13px;font-weight:600;min-height:48px}.guia-ajuda details>div{padding:0 0 18px;font-size:13px;color:#536773}.guia-ajuda ul{padding-left:20px;margin:0}.guia-ajuda li+li{margin-top:8px}.guia-ajuda p{margin:0 0 10px}.guia-ajuda p:last-child{margin-bottom:0}.guia-prints{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:12px}.guia-print{cursor:pointer;text-align:left;background:#fff;border:1px solid var(--guia-borda);padding:0;border-radius:8px;overflow:hidden;min-width:0}.guia-print img{width:100%;height:110px;object-fit:cover;object-position:top;display:block;background:#f5f8fa}.guia-print span{display:block;padding:10px;font-size:12px;overflow-wrap:anywhere}.guia-print:hover{border-color:var(--guia-cor)}.guia-foto{display:block;width:100%;margin:10px 0 4px;padding:0;border:1px solid var(--guia-borda);border-radius:10px;overflow:hidden;background:#fff;cursor:pointer;text-align:left}.guia-foto img{width:100%;height:auto;display:block;background:#f5f8fa}.guia-foto:hover{border-color:var(--guia-cor)}.guia-navegacao{display:flex;align-items:center;justify-content:space-between;gap:16px;border-top:1px solid var(--guia-borda);padding:18px 30px;background:#fcfdfe}.guia-botao{border:1px solid var(--guia-borda);border-radius:8px;min-height:44px;padding:10px 16px;background:white;color:#405666;cursor:pointer;font-size:13px!important;font-weight:650!important}.guia-botao:disabled{opacity:.4;cursor:default}.guia-botao-principal{background:var(--guia-cor);color:white;border-color:var(--guia-cor)}.guia-botao-principal:hover{background:#006f88}.guia-proxima{font-size:12px;color:var(--guia-suave);text-align:center;margin:14px 0 0}.guia-aviso{font-size:12px;text-align:center;color:var(--guia-suave);margin:18px 0}.guia-final{padding:16px;border-radius:10px;background:#edf8f2;font-size:14px;color:#255c43;margin-top:20px}.guia-modal{padding:0;border:0;border-radius:12px;max-width:min(1100px,calc(100vw - 24px));width:100%;max-height:92dvh;background:#fff;color:var(--guia-texto)}.guia-modal::backdrop{background:#12232edd}.guia-modal-topo{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:12px 16px;border-bottom:1px solid var(--guia-borda)}.guia-modal-topo p{margin:0;font-size:14px;font-weight:600}.guia-modal-imagem{overflow:auto;max-height:calc(92dvh - 74px)}.guia-modal img{width:100%;height:auto;display:block}.guia-sem-etapas{padding:24px;background:white;border-radius:12px}
@media(max-width:800px){.guia-layout{grid-template-columns:minmax(0,1fr);padding:24px 16px;gap:0}.guia-menu{display:none}.guia-cabecalho>div{padding:16px 20px}.guia h1{font-size:24px}.guia-seletor{display:block;margin:0 0 20px}.guia-seletor label{display:block;font-size:12px;font-weight:600;color:var(--guia-suave);margin-bottom:6px}.guia-seletor select{width:100%;min-height:46px;padding:10px 12px;border:1px solid var(--guia-borda);border-radius:8px;background:white;color:var(--guia-texto);font-size:16px}.guia-conteudo{padding:24px 20px}.guia h2.guia-titulo-etapa{font-size:24px}.guia-navegacao{padding:16px 20px;gap:10px}.guia-navegacao .guia-botao{padding:10px 12px}.guia-selo{max-width:135px;text-align:right}.guia-prints{gap:8px}}
@media(max-width:600px){.guia-cabecalho>div{flex-direction:column;align-items:flex-start;gap:10px}.guia-marca{width:200px}.guia-selo{max-width:none;text-align:left}}
@media print{.guia-menu,.guia-seletor,.guia-controles,.guia-navegacao,.guia-modal,.guia-proxima,.guia-pular{display:none!important}.guia-layout{display:block;padding:0}.guia [data-etapa][hidden]{display:block!important}.guia-cartao{break-inside:avoid;box-shadow:none}.guia{background:white}}
</style>
<noscript><style>.guia-menu{display:block;position:static}</style></noscript>
</head>
<body class="guia">
<a class="guia-pular" href="#guia-conteudo">Pular para as instruções</a>
<header class="guia-cabecalho"><div><img class="guia-marca" src="<?= $e(URL) ?>/assets/logos/logo-educatudo-black.png" alt="EducaTudo — Educação para o futuro agora!" width="4544" height="728"><span class="guia-selo">Guia acadêmico</span></div></header>
<div class="guia-layout">
<aside class="guia-menu" aria-label="Índice do guia">
    <h2>Etapas da implantação</h2>
    <nav aria-label="Etapas do guia">
    <?php $ultimoGrupoGuia = null; ?>
    <?php foreach ($etapasGuia as $indiceGuia => $etapaGuia): ?>
        <?php if ($ultimoGrupoGuia !== $etapaGuia['grupo_rotulo']): ?>
        <p class="guia-grupo-menu"><?= $e($etapaGuia['grupo_rotulo']) ?></p>
        <?php $ultimoGrupoGuia = $etapaGuia['grupo_rotulo']; endif; ?>
        <a href="#passo-<?= $e($etapaGuia['chave']) ?>" data-ir-etapa="<?= $indiceGuia ?>"><span class="guia-numero"><?= $indiceGuia + 1 ?></span><span><?= $e($etapaGuia['label']) ?></span></a>
    <?php endforeach; ?>
    </nav>
    <p class="guia-menu-nota">Siga a ordem: cada etapa prepara a próxima.</p>
</aside>
<main id="guia-conteudo">
    <div class="guia-intro"><p class="guia-sobretitulo">IMPLANTAÇÃO ACADÊMICA</p><h1>Monte o ano letivo, passo a passo.</h1><p>Veja o que fazer e o que conferir antes de seguir.</p></div>
    <?php if ($totalGuia > 0): ?>
    <div class="guia-seletor guia-controles" hidden><label for="guia-selecionar">Ir para uma etapa</label><select id="guia-selecionar">
        <?php foreach ($etapasGuia as $indiceGuia => $etapaGuia): ?>
        <option value="<?= $indiceGuia ?>"><?= $indiceGuia + 1 ?>. <?= $e($etapaGuia['label']) ?></option>
        <?php endforeach; ?>
    </select></div>
    <div class="guia-controles" hidden><div class="guia-posicao"><span id="guia-posicao" role="status" aria-live="polite"></span><span>Posição no guia</span></div><div class="guia-indicador" aria-hidden="true"><span id="guia-indicador"></span></div></div>
    <?php foreach ($etapasGuia as $indiceGuia => $etapaGuia):
        $instrucoesGuia = is_array($etapaGuia['como_fazer'] ?? null) ? $etapaGuia['como_fazer'] : [];
        $errosGuia = is_array($etapaGuia['erros_comuns'] ?? null) ? $etapaGuia['erros_comuns'] : [];
        $printsGuia = [];
        foreach (($etapaGuia['prints'] ?? []) as $printGuia) {
            if (!empty($printGuia['existe'])) {
                $printsGuia[(string) $printGuia['url']] = $printGuia;
            }
        }
        $notaGuia = trim((string) ($etapaGuia['nota'] ?? ''));
        $usoGuia = trim((string) ($etapaGuia['usado_depois_em'] ?? ''));
    ?>
    <article class="guia-cartao" id="passo-<?= $e($etapaGuia['chave']) ?>" data-etapa aria-labelledby="guia-titulo-<?= $indiceGuia ?>">
        <div class="guia-conteudo">
            <p class="guia-etapa-grupo"><?= $e($etapaGuia['grupo_rotulo']) ?></p>
            <h2 class="guia-titulo-etapa" id="guia-titulo-<?= $indiceGuia ?>" tabindex="-1"><?= $e($etapaGuia['label']) ?></h2>
            <?php if ((string) ($etapaGuia['chave'] ?? '') === 'regra'): ?>
            <?php require __DIR__ . '/_guia_regra_aprovacao.php'; ?>
            <?php elseif ((string) ($etapaGuia['chave'] ?? '') === 'eventos'): ?>
            <?php require __DIR__ . '/_guia_eventos_avaliativos.php'; ?>
            <?php elseif (in_array((string) ($etapaGuia['chave'] ?? ''), ['demonstrativo', 'boletim', 'relatorio'], true)): ?>
            <?php require __DIR__ . '/_guia_documentos_academicos.php'; ?>
            <?php elseif (isset($indiceReferenciaGuia[(string) ($etapaGuia['chave'] ?? '')])): ?>
            <?php require __DIR__ . '/_guia_referencia.php'; ?>
            <?php else: ?>
            <?php $introGuia = trim((string) ($etapaGuia['intro'] ?? '')); ?>
            <?php if ($introGuia !== ''): ?>
            <p class="guia-o-que-e"><?= $e($introGuia) ?></p>
            <?php endif; ?>
            <ol class="guia-instrucoes">
                <?php foreach ($instrucoesGuia as $instrucaoGuia):
                    $textoGuia = is_array($instrucaoGuia) ? (string) ($instrucaoGuia['texto'] ?? '') : (string) $instrucaoGuia;
                    $fotoGuia = (is_array($instrucaoGuia) && is_array($instrucaoGuia['print'] ?? null) && !empty($instrucaoGuia['print']['existe']))
                        ? $instrucaoGuia['print']
                        : null;
                    $fotoSrc = $fotoGuia ? URL . (string) ($fotoGuia['url'] ?? '') : '';
                    $fotoLegenda = $fotoGuia ? (string) ($fotoGuia['rotulo'] ?? 'Tela de exemplo') : '';
                ?>
                <li>
                    <?= $e($textoGuia) ?>
                    <?php if ($fotoSrc !== ''): ?>
                    <button type="button" class="guia-foto" data-print-src="<?= $e($fotoSrc) ?>" data-print-legenda="<?= $e($fotoLegenda) ?>" aria-label="Ampliar: <?= $e($fotoLegenda) ?>" aria-haspopup="dialog">
                        <img src="<?= $e($fotoSrc) ?>" alt="<?= $e($fotoLegenda) ?>">
                    </button>
                    <?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($etapaGuia['aceite'])): ?>
            <div class="guia-conferencia"><span aria-hidden="true">✓</span><div><h3>Confira antes de seguir</h3><p><?= $e($etapaGuia['aceite']) ?></p></div></div>
            <?php endif; ?>
            <div class="guia-ajuda">
                <?php if ($printsGuia !== []): ?>
                <details><summary>Ver telas de exemplo (<?= count($printsGuia) ?>)</summary><div><p>Selecione uma imagem para ampliar.</p><div class="guia-prints">
                    <?php foreach ($printsGuia as $printGuia): $srcGuia = URL . (string) ($printGuia['url'] ?? ''); $legendaGuia = (string) ($printGuia['rotulo'] ?? 'Tela de exemplo'); ?>
                    <button type="button" class="guia-print" data-print-src="<?= $e($srcGuia) ?>" data-print-legenda="<?= $e($legendaGuia) ?>" aria-label="Ampliar: <?= $e($legendaGuia) ?>" aria-haspopup="dialog"><img src="<?= $e($srcGuia) ?>" alt="<?= $e($legendaGuia) ?>" loading="lazy"><span><?= $e($legendaGuia) ?> ↗</span></button>
                    <?php endforeach; ?>
                </div></div></details>
                <?php endif; ?>
                <?php if ($errosGuia !== []): ?>
                <details><summary>Precisa de ajuda? Veja os erros comuns</summary><div><ul><?php foreach ($errosGuia as $erroGuia): ?><li><?= $e($erroGuia) ?></li><?php endforeach; ?></ul></div></details>
                <?php endif; ?>
                <?php if ($notaGuia !== '' || $usoGuia !== ''): ?>
                <details><summary>Como esta etapa se conecta às próximas</summary><div><?php if ($usoGuia !== ''): ?><p><strong>Usado depois em:</strong> <?= $e($usoGuia) ?></p><?php endif; ?><?php if ($notaGuia !== ''): ?><p><?= $e($notaGuia) ?></p><?php endif; ?></div></details>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <?php if ($indiceGuia === $totalGuia - 1): ?><p class="guia-final">Você chegou ao fim do guia. Confira um boletim e um demonstrativo antes de entregar os documentos à família.</p><?php endif; ?>
        </div>
        <div class="guia-navegacao guia-controles" hidden><button type="button" class="guia-botao" data-anterior <?= $indiceGuia === 0 ? 'disabled' : '' ?>>← Voltar</button><?php if ($indiceGuia < $totalGuia - 1): ?><button type="button" class="guia-botao guia-botao-principal" data-proximo>Próximo passo →</button><?php else: ?><button type="button" class="guia-botao guia-botao-principal" data-reiniciar>Voltar ao início</button><?php endif; ?></div>
    </article>
    <?php endforeach; ?>
    <p id="guia-proxima" class="guia-proxima guia-controles" hidden></p>
    <?php else: ?><p class="guia-sem-etapas">As etapas do guia ainda não estão disponíveis.</p><?php endif; ?>
    <p class="guia-aviso">Este guia não altera cadastros. O progresso real da escola aparece no painel, após o login.</p>
    <noscript><p class="guia-aviso">Todas as etapas estão abertas. Use o índice para navegar.</p></noscript>
</main>
</div>
<dialog id="guia-modal" class="guia-modal" aria-labelledby="guia-modal-legenda"><div class="guia-modal-topo"><p id="guia-modal-legenda"></p><button type="button" class="guia-botao" id="guia-fechar">Fechar ×</button></div><div class="guia-modal-imagem"><img id="guia-imagem" alt=""></div></dialog>
<script>
(function () {
    'use strict';
    const etapas = Array.from(document.querySelectorAll('[data-etapa]'));
    const links = Array.from(document.querySelectorAll('[data-ir-etapa]'));
    const seletor = document.getElementById('guia-selecionar');
    const posicao = document.getElementById('guia-posicao');
    const indicador = document.getElementById('guia-indicador');
    const proxima = document.getElementById('guia-proxima');
    let atual = 0;
    function etapaDoEndereco() {
        const indice = etapas.findIndex(function (etapa) { return '#' + etapa.id === window.location.hash; });
        return indice;
    }
    function mostrarEtapa(indice, focar) {
        if (indice < 0 || indice >= etapas.length) return;
        atual = indice;
        etapas.forEach(function (etapa, i) { etapa.hidden = i !== atual; });
        links.forEach(function (link, i) {
            if (i === atual) link.setAttribute('aria-current', 'step');
            else link.removeAttribute('aria-current');
        });
        seletor.value = String(atual);
        posicao.textContent = 'Passo ' + (atual + 1) + ' de ' + etapas.length;
        indicador.style.width = ((atual + 1) / etapas.length * 100) + '%';
        proxima.textContent = etapas[atual + 1] ? 'A seguir: ' + etapas[atual + 1].querySelector('h2').textContent : 'Todas as etapas do guia estão disponíveis no índice.';
        if (focar) etapas[atual].querySelector('h2').focus();
    }
    function navegar(indice) {
        if (!etapas[indice]) return;
        if (window.location.hash !== '#' + etapas[indice].id) window.location.hash = etapas[indice].id;
        mostrarEtapa(indice, true);
    }
    if (etapas.length > 0) {
        document.querySelectorAll('.guia-controles').forEach(function (controle) { controle.hidden = false; });
        links.forEach(function (link) { link.addEventListener('click', function (evento) { evento.preventDefault(); navegar(Number(link.dataset.irEtapa)); }); });
        document.querySelector('.guia-pular').addEventListener('click', function (evento) { evento.preventDefault(); etapas[atual].querySelector('h2').focus(); });
        seletor.addEventListener('change', function () { navegar(Number(seletor.value)); });
        document.querySelectorAll('[data-anterior]').forEach(function (botao) { botao.addEventListener('click', function () { navegar(atual - 1); }); });
        document.querySelectorAll('[data-proximo]').forEach(function (botao) { botao.addEventListener('click', function () { navegar(atual + 1); }); });
        document.querySelectorAll('[data-reiniciar]').forEach(function (botao) { botao.addEventListener('click', function () { navegar(0); }); });
        window.addEventListener('hashchange', function () { const indice = etapaDoEndereco(); if (indice >= 0 && indice !== atual) mostrarEtapa(indice, true); });
        mostrarEtapa(Math.max(0, etapaDoEndereco()), false);
    }
    const modal = document.getElementById('guia-modal');
    const imagem = document.getElementById('guia-imagem');
    let origemPrint = null;
    document.querySelectorAll('[data-print-src]').forEach(function (botao) {
        botao.addEventListener('click', function () {
            origemPrint = botao;
            imagem.src = botao.dataset.printSrc;
            imagem.alt = botao.dataset.printLegenda || 'Tela de exemplo';
            document.getElementById('guia-modal-legenda').textContent = imagem.alt;
            modal.showModal();
        });
    });
    document.getElementById('guia-fechar').addEventListener('click', function () { modal.close(); });
    modal.addEventListener('click', function (evento) { if (evento.target === modal) { const caixa = modal.getBoundingClientRect(); if (evento.clientX < caixa.left || evento.clientX > caixa.right || evento.clientY < caixa.top || evento.clientY > caixa.bottom) modal.close(); } });
    modal.addEventListener('close', function () { imagem.removeAttribute('src'); if (origemPrint) origemPrint.focus(); });
}());
</script>
</body>
</html>
