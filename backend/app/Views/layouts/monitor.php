<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Monitor - EducaTudo') ?></title>
    <?php include __DIR__ . '/components/estilos_plataforma.php'; ?>
    <?php include __DIR__ . '/components/form_control_safari.php'; ?>
    <?php if (!empty($additional_css)): ?><?= $additional_css ?><?php endif; ?>
</head>
<body class="bg-gray-50 min-h-screen">
    <nav class="bg-gradient-to-r from-teal-700 to-cyan-700 text-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4 py-4 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <a href="<?= URL ?>/monitor/dashboard" class="font-bold text-lg">Monitor de Sala</a>
                <span class="text-teal-100 text-sm hidden sm:inline">EducaTudo</span>
            </div>
            <div class="flex items-center gap-4">
                <span class="text-sm text-teal-100"><?= htmlspecialchars($user['nome'] ?? '') ?></span>
                <a href="<?= URL ?>/logout" class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1.5 rounded-lg">Sair</a>
            </div>
        </div>
    </nav>
    <main class="max-w-7xl mx-auto px-4 py-8">
        <?php
        $flash = $_SESSION['flash_message'] ?? null;
        $flashType = $_SESSION['flash_type'] ?? 'info';
        if ($flash) {
            unset($_SESSION['flash_message'], $_SESSION['flash_type']);
            $bg = $flashType === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-teal-100 text-teal-800 border-teal-200';
            echo '<div class="mb-6 border px-4 py-3 rounded-lg ' . $bg . '">' . htmlspecialchars($flash) . '</div>';
        }
        ?>
        <?= $content ?? '' ?>
    </main>
    <?php if (!empty($additional_js)): ?><?= $additional_js ?><?php endif; ?>
</body>
</html>
