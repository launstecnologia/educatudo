<?php
/** Página de impressão do layout da prova impressa. */
$pacote = is_array($pacote ?? null) ? $pacote : [];
$layout = is_array($pacote['layout'] ?? null) ? $pacote['layout'] : [];
$materias = is_array($pacote['materias'] ?? null) ? $pacote['materias'] : [];
$logo_url = (string) ($pacote['logo_url'] ?? '');
$nome_escola = (string) ($pacote['nome_escola'] ?? '');
$data_texto = (string) ($pacote['data_texto'] ?? '');
$horario_texto = (string) ($pacote['horario_texto'] ?? '');
$bloco = is_array($pacote['bloco'] ?? null) ? $pacote['bloco'] : [];
$blocoId = (int) ($bloco['id'] ?? 0);
$margem = (int) ($layout['margem'] ?? 14);
$baseUrl = defined('URL') ? rtrim((string) URL, '/') : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars((string) ($layout['titulo'] ?? 'Prova impressa'), ENT_QUOTES, 'UTF-8') ?></title>
    <style>
        @page {
            size: A4 portrait;
            margin: <?= $margem ?>mm;
        }
        body { margin: 0; background: #f3f4f6; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; padding: 12px 16px; background: #fff; border-bottom: 1px solid #e5e7eb; font-family: system-ui, sans-serif; }
        .toolbar a, .toolbar button { font: inherit; font-size: 14px; border-radius: 8px; padding: 8px 14px; text-decoration: none; cursor: pointer; }
        .btn-print { background: #0f766e; color: #fff; border: 0; font-weight: 600; }
        .btn-sec { background: #fff; color: #111; border: 1px solid #d1d5db; }
        #gerando-gabaritos { position: fixed; inset: 0; z-index: 50; background: rgba(15, 23, 42, .45); display: flex; align-items: center; justify-content: center; font-family: system-ui, sans-serif; }
        #gerando-gabaritos[hidden] { display: none !important; }
        .gerando-card { background: #fff; border-radius: 12px; padding: 24px 28px; text-align: center; box-shadow: 0 12px 40px rgba(0,0,0,.18); }
        .gerando-card p { margin: 12px 0 0; font-size: 15px; font-weight: 600; color: #111; }
        .gerando-spinner { width: 28px; height: 28px; margin: 0 auto; border: 3px solid #e5e7eb; border-top-color: #0f766e; border-radius: 50%; animation: girar-gabarito .8s linear infinite; }
        @keyframes girar-gabarito { to { transform: rotate(360deg); } }
        .miolo { padding: 16px; }
        @media print {
            body { background: #fff; }
            .toolbar, .miolo { padding: 0; }
            .toolbar, #gerando-gabaritos { display: none !important; }
        }
    </style>
    <script>
        window.MathJax = { tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['$$', '$$'], ['\\[', '\\]']], processEscapes: true }, svg: { fontCache: 'global' }, options: { enableMenu: false } };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>
</head>
<body>
    <div class="toolbar">
        <strong>Prova para imprimir</strong>
        <div style="display:flex; gap:8px; flex-wrap:wrap;">
            <button type="button" class="btn-print" id="btn-imprimir">Imprimir / Salvar em PDF</button>
            <a class="btn-sec" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin/provas/blocos/<?= $blocoId ?>/layout">Voltar ao layout</a>
            <a class="btn-sec" id="btn-pdf-gabaritos" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin/provas/blocos/<?= $blocoId ?>/imprimir?gabaritos=1">PDF dos gabaritos</a>
        </div>
    </div>
    <div class="miolo">
        <?php include __DIR__ . '/_folha_layout.php'; ?>
    </div>
    <div id="gerando-gabaritos" hidden>
        <div class="gerando-card">
            <div class="gerando-spinner" aria-hidden="true"></div>
            <p>Gerando o PDF dos gabaritos...</p>
        </div>
    </div>
    <script>
    (function () {
        var linkGabaritos = document.getElementById('btn-pdf-gabaritos');
        var aviso = document.getElementById('gerando-gabaritos');
        if (!linkGabaritos || !aviso) return;
        linkGabaritos.addEventListener('click', function (e) {
            if (linkGabaritos.getAttribute('aria-busy') === 'true') {
                e.preventDefault();
                return;
            }
            e.preventDefault();
            linkGabaritos.setAttribute('aria-busy', 'true');
            aviso.hidden = false;
            window.location.href = linkGabaritos.href;
        });
        window.addEventListener('pageshow', function () {
            aviso.hidden = true;
            linkGabaritos.removeAttribute('aria-busy');
        });
    })();
    </script>
    <script>
    function paginarEImprimir(imprimir) {
        var seguir = function () {
            if (window.paginarFolha) window.paginarFolha();
            if (imprimir) window.print();
        };
        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise().then(seguir).catch(seguir);
            return;
        }
        seguir();
    }
    document.getElementById('btn-imprimir').addEventListener('click', function () {
        paginarEImprimir(true);
    });
    window.addEventListener('load', function () { paginarEImprimir(false); });
    </script>
</body>
</html>
