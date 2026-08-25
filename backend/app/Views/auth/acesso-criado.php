<?php
require_once __DIR__ . '/../../Core/LayoutHelper.php';
$logo_horizontal_url = LayoutHelper::getLogoHorizontalUrl();
$system_title = LayoutHelper::getSystemTitle();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso criado - <?= htmlspecialchars($system_title) ?></title>
    <?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="min-h-screen bg-gray-50 flex items-center justify-center p-6">
    <div class="w-full max-w-xl bg-white rounded-xl shadow-lg p-8 text-center">
        <?php if (!empty($logo_horizontal_url)): ?>
            <img src="<?= htmlspecialchars($logo_horizontal_url) ?>" alt="Logo" class="h-12 mx-auto mb-3">
        <?php endif; ?>
        <h1 class="text-2xl font-bold text-gray-900 mb-2">Acesso criado com sucesso!</h1>
        <p class="text-gray-600 mb-6">
            A partir de agora, seu login será feito usando <strong>nickname</strong> e <strong>senha</strong>.
        </p>
        <a href="<?= URL ?>/" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 px-6 rounded-lg">
            Ir para o login
        </a>
    </div>
</body>
</html>

