<?php
/**
 * Página para impressão / Salvar como PDF (prova única).
 * Imagens carregam pela URL no navegador; ao usar Imprimir → Salvar como PDF as imagens aparecem.
 */
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../../Core/LayoutHelper.php';
}
$baseUrl = defined('URL') ? rtrim(URL, '/') : '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Imprimir: <?= htmlspecialchars($prova['titulo'] ?? 'Prova') ?></title>
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
        .paper h1 { font-size: 18px; margin: 0 0 8px 0; color: #1a1a1a; }
        .meta { color: #555; font-size: 11px; margin-bottom: 24px; padding-bottom: 12px; border-bottom: 1px solid #e5e7eb; }
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
            .questao { page-break-inside: avoid; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h2><?= htmlspecialchars($prova['titulo'] ?? 'Prova') ?></h2>
        <div style="display: flex; align-items: center; gap: 12px;">
            <button type="button" class="btn-print" onclick="window.print();">
                <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V2h12v7"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/></svg>
                Imprimir / Salvar em PDF
            </button>
            <a href="<?= $baseUrl ?>/admin/provas" class="btn-back">← Voltar</a>
        </div>
    </div>

    <div class="paper">
        <h1><?= htmlspecialchars($prova['titulo']) ?></h1>
        <div class="meta">
            <strong>Professor:</strong> <?= htmlspecialchars($prova['professor_nome'] ?? '—') ?> &nbsp;|&nbsp;
            <strong>Disciplina:</strong> <?= htmlspecialchars($prova['materia_nome'] ?? '—') ?> &nbsp;|&nbsp;
            <strong>Data:</strong> <?= !empty($prova['data_inicio']) ? date('d/m/Y', strtotime($prova['data_inicio'])) : '—' ?>
            <?php if (!empty($prova['hora_inicio'])): ?> às <?= htmlspecialchars(substr($prova['hora_inicio'], 0, 5)) ?><?php endif; ?>
        </div>

        <?php foreach ($questoes as $i => $q): ?>
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
    </div>
</body>
</html>
