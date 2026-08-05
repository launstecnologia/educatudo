<?php
/**
 * Página para impressão / Salvar como PDF (prova completa do bloco).
 * Imagens carregam pela URL no navegador; ao usar Imprimir → Salvar como PDF as imagens aparecem.
 */
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../../Core/LayoutHelper.php';
}
$baseUrl = defined('URL') ? rtrim(URL, '/') : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir: <?= htmlspecialchars($bloco['titulo'] ?? 'Prova completa') ?></title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif; font-size: 12px; color: #111; margin: 0; padding: 20px; background: #f5f5f5; }
        .toolbar { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 24px; padding: 16px 20px; background: #fff; border-radius: 8px; box-shadow: 0 1px 3px rgba(0,0,0,.08); }
        .toolbar h2 { margin: 0; font-size: 1.1rem; color: #333; }
        .btn-print { display: inline-flex; align-items: center; gap: 8px; padding: 10px 20px; background: #16a34a; color: #fff; border: none; border-radius: 8px; font-weight: 600; font-size: 14px; cursor: pointer; }
        .btn-print:hover { background: #15803d; }
        .btn-back { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; background: #6b7280; color: #fff; text-decoration: none; border-radius: 8px; font-size: 13px; }
        .btn-back:hover { background: #4b5563; color: #fff; }
        .paper { max-width: 210mm; margin: 0 auto; padding: 24px; background: #fff; box-shadow: 0 2px 8px rgba(0,0,0,.1); border-radius: 4px; }
        .bloco-header { margin-bottom: 28px; padding-bottom: 16px; border-bottom: 2px solid #e5e7eb; }
        .bloco-header h1 { font-size: 20px; margin: 0 0 8px 0; color: #1a1a1a; }
        .bloco-meta { color: #555; font-size: 12px; }
        .prova-section { margin-top: 32px; page-break-before: always; }
        .prova-section:first-of-type { page-break-before: auto; margin-top: 0; }
        .prova-section h2 { font-size: 16px; margin: 0 0 6px 0; color: #1a1a1a; }
        .prova-meta { color: #555; font-size: 11px; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
        .questao { margin-bottom: 24px; padding-top: 16px; border-top: 1px solid #e5e7eb; }
        .questao:first-of-type { border-top: none; padding-top: 0; }
        .questao-num { font-weight: 700; font-size: 13px; margin-bottom: 8px; color: #1a1a1a; }
        .badge { display: inline-block; font-size: 10px; padding: 2px 8px; border-radius: 4px; background: #e5e7eb; margin-left: 6px; font-weight: 500; }
        .enunciado { margin-bottom: 12px; line-height: 1.6; }
        .questao img { max-width: 100%; max-height: 280px; margin: 8px 0; border-radius: 4px; border: 1px solid #e5e7eb; }
        .alternativas { padding-left: 16px; margin-top: 10px; }
        .alternativa { margin-bottom: 8px; }
        .alternativa.correta { font-weight: 600; color: #16a34a; }
        @media print {
            body { background: #fff; padding: 0; }
            .toolbar { display: none !important; }
            .paper { max-width: none; box-shadow: none; padding: 16px; }
            .prova-section { page-break-before: always; }
            .prova-section:first-of-type { page-break-before: auto; }
            .questao { page-break-inside: avoid; }
        }
        .enunciado mjx-container { font-size: 1em; }
    </style>
    <script>
        window.MathJax = {
            tex: { inlineMath: [['$', '$'], ['\\(', '\\)']], displayMath: [['$$', '$$'], ['\\[', '\\]']], processEscapes: true },
            svg: { fontCache: 'global' },
            options: { enableMenu: false }
        };
    </script>
    <script src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-svg.js" async></script>
</head>
<body>
    <div class="toolbar">
        <h2>Prova completa: <?= htmlspecialchars($bloco['titulo'] ?? '') ?></h2>
        <div style="display: flex; align-items: center; gap: 12px;">
            <button type="button" class="btn-print" id="btn-imprimir-prova">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                <span id="btn-imprimir-texto">Imprimir / Salvar em PDF</span>
            </button>
            <a href="<?= $baseUrl ?>/admin/provas/blocos/<?= (int)($bloco['id'] ?? 0) ?>/gerenciar" class="btn-back">← Voltar</a>
        </div>
    </div>

    <div class="paper">
        <div class="bloco-header">
            <h1>Bloco: <?= htmlspecialchars($bloco['titulo']) ?></h1>
            <div class="bloco-meta">
                Data: <?= !empty($bloco['data_prova']) ? date('d/m/Y', strtotime($bloco['data_prova'])) : '—' ?>
                — Horário: <?= !empty($bloco['hora_inicio']) ? date('H:i', strtotime($bloco['hora_inicio'])) : '—' ?> às <?= !empty($bloco['hora_fim']) ? date('H:i', strtotime($bloco['hora_fim'])) : '—' ?>
            </div>
        </div>

        <?php foreach ($provas as $prova): ?>
        <div class="prova-section">
            <h2><?= htmlspecialchars($prova['titulo']) ?></h2>
            <div class="prova-meta">
                <strong>Professor:</strong> <?= htmlspecialchars($prova['professor_nome'] ?? '—') ?> &nbsp;|&nbsp;
                <strong>Disciplina:</strong> <?= htmlspecialchars($prova['materia_nome'] ?? '—') ?>
            </div>

            <?php if (empty($prova['questoes'])): ?>
                <p class="text-gray-500">Nenhuma questão nesta prova.</p>
            <?php else: ?>
                <?php foreach ($prova['questoes'] as $i => $q): ?>
                <div class="questao">
                    <div class="questao-num">
                        Questão <?= $i + 1 ?>
                        <span class="badge"><?= htmlspecialchars(ucfirst($q['dificuldade'] ?? '')) ?></span>
                        &nbsp;<?= number_format((float)($q['valor'] ?? 0), 1, ',', '') ?> pt(s)
                    </div>
                    <div class="enunciado"><?= isset($q['enunciado']) ? LayoutHelper::renderEnunciadoProva($q['enunciado']) : '' ?></div>
                    <?php if (!empty($q['imagem_url'])): ?>
                        <img src="<?= htmlspecialchars(strpos($q['imagem_url'], 'http') === 0 ? $q['imagem_url'] : $baseUrl . '/' . ltrim($q['imagem_url'], '/')) ?>" alt="Imagem da questão">
                    <?php endif; ?>
                    <?php if (!empty($q['tipo']) && $q['tipo'] === 'multipla_escolha' && !empty($q['alternativas'])): ?>
                    <div class="alternativas">
                        <?php foreach ($q['alternativas'] as $alt): ?>
                        <div class="alternativa <?= !empty($alt['correta']) ? 'correta' : '' ?>">
                            <?= !empty($alt['correta']) ? '✓' : '◦' ?> <?= isset($alt['texto']) ? LayoutHelper::renderEnunciadoProva($alt['texto']) : '' ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <script>
    (function() {
        function runTypeset() {
            if (window.MathJax && window.MathJax.typesetPromise) {
                var el = document.querySelector('.paper');
                return MathJax.typesetPromise(el ? [el] : null).catch(function(e) { console.warn('MathJax:', e); });
            }
            return Promise.resolve();
        }
        function prepararEImprimir() {
            var btn = document.getElementById('btn-imprimir-prova');
            var texto = document.getElementById('btn-imprimir-texto');
            if (btn) btn.disabled = true;
            if (texto) texto.textContent = 'Preparando fórmulas...';
            runTypeset().then(function() {
                setTimeout(function() {
                    if (texto) texto.textContent = 'Imprimir / Salvar em PDF';
                    if (btn) btn.disabled = false;
                    window.print();
                }, 400);
            });
        }
        document.getElementById('btn-imprimir-prova').addEventListener('click', prepararEImprimir);
        // MathJax carrega com async: aguardar estar disponível e então fazer typeset (como no layout do aluno)
        var runs = 0;
        var t = setInterval(function() {
            runs++;
            if (window.MathJax && window.MathJax.typesetPromise) {
                clearInterval(t);
                runTypeset();
                setTimeout(runTypeset, 400);
                setTimeout(runTypeset, 1200);
            } else if (runs > 100) { clearInterval(t); }
        }, 100);
    })();
    </script>
</body>
</html>
