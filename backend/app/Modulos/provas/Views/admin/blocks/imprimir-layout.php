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
            margin: <?= $margem ?>mm <?= $margem ?>mm <?= $margem + 8 ?>mm;
            <?php if (!empty($layout['numero_pagina'])): ?>
            @bottom-center { content: counter(page); font-size: 9px; font-family: sans-serif; color: #444; }
            <?php endif; ?>
        }
        body { margin: 0; background: #f3f4f6; }
        .toolbar { display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; padding: 12px 16px; background: #fff; border-bottom: 1px solid #e5e7eb; font-family: system-ui, sans-serif; }
        .toolbar a, .toolbar button { font: inherit; font-size: 14px; border-radius: 8px; padding: 8px 14px; text-decoration: none; cursor: pointer; }
        .btn-print { background: #0f766e; color: #fff; border: 0; font-weight: 600; }
        .btn-sec { background: #fff; color: #111; border: 1px solid #d1d5db; }
        .miolo { padding: 16px; }
        @media print {
            body { background: #fff; }
            .toolbar, .miolo { padding: 0; }
            .toolbar { display: none !important; }
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
            <a class="btn-sec" href="<?= htmlspecialchars($baseUrl, ENT_QUOTES, 'UTF-8') ?>/admin/provas/blocos/<?= $blocoId ?>/imprimir?gabaritos=1">PDF dos gabaritos</a>
        </div>
    </div>
    <div class="miolo">
        <?php include __DIR__ . '/_folha_layout.php'; ?>
    </div>
    <script>
    document.getElementById('btn-imprimir').addEventListener('click', function () {
        var seguir = function () { window.print(); };
        if (window.MathJax && window.MathJax.typesetPromise) {
            window.MathJax.typesetPromise().then(seguir).catch(seguir);
            return;
        }
        seguir();
    });
    </script>
</body>
</html>
