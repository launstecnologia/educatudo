<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <title><?= htmlspecialchars($title ?? 'Prova - EducaTudo') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        html, body { height: 100%; margin: 0; overflow: auto; }
        #exam-secure-root { min-height: 100%; }
    </style>
    <?php if (!empty($additional_css)): ?><?= $additional_css ?><?php endif; ?>
</head>
<body class="bg-gray-100 antialiased" id="exam-secure-body">
    <div id="exam-secure-root">
        <?= $content ?? '' ?>
    </div>
    <?php if (!empty($additional_js)): ?><?= $additional_js ?><?php endif; ?>
    <script>window.EDUCATUDO_URL = <?= json_encode(defined('URL') ? URL : '') ?>;</script>
    <script src="<?= (defined('URL') ? URL : '') ?>/public/static/js/student-presence.js?v=20250608b"></script>
</body>
</html>
