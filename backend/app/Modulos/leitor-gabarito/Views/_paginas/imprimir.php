<?php
$prova = $prova ?? [];
$folhas = $folhas ?? [];
$baseUrl = URL . ($base ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <title>Folhas — <?= htmlspecialchars((string) ($prova['titulo'] ?? 'Prova')) ?></title>
    <style>
        body { margin: 0; font-family: sans-serif; background: #f3f4f6; }
        .barra { padding: 12px 16px; background: #fff; border-bottom: 1px solid #e5e7eb; }
        .barra button { padding: 8px 14px; border: 0; border-radius: 8px; background: #0f766e; color: #fff; font-weight: 600; cursor: pointer; }
        .folha { page-break-after: always; background: #fff; margin: 12px auto; width: 190mm; }
        .folha img { width: 190mm; height: auto; display: block; }
        .vazio { padding: 24px; }
        @media print {
            .barra { display: none; }
            body { background: #fff; }
            .folha { margin: 0 auto; }
            @page { size: A4; margin: 8mm; }
        }
    </style>
</head>
<body>
    <div class="barra">
        <button type="button" onclick="window.print()">Imprimir</button>
        <span>Tamanho real, sem “ajustar à página”. Margem pequena para as marcas dos cantos não serem cortadas.</span>
    </div>
    <?php if ($folhas === []): ?>
    <p class="vazio">Nenhuma folha gerada para esta turma. Volte e clique em Gerar folhas.</p>
    <?php endif; ?>
    <?php foreach ($folhas as $folha): ?>
    <div class="folha">
        <img src="<?= htmlspecialchars($baseUrl) ?>/folhas/<?= (int) $folha['id'] ?>/png" alt="Folha de <?= htmlspecialchars((string) $folha['aluno_nome']) ?>">
    </div>
    <?php endforeach; ?>
</body>
</html>
