<?php
require_once __DIR__ . '/../../Core/LayoutHelper.php';
$logo_horizontal_url = LayoutHelper::getLoginLogoUrl();
$login_cover_url = LayoutHelper::getLoginCoverUrl();
$system_title = LayoutHelper::getSystemTitle();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal do Monitor - <?= htmlspecialchars($system_title) ?></title>
    <?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="min-h-screen flex flex-col bg-gray-50">
    <div class="flex flex-1">
        <div class="hidden md:flex flex-1 relative bg-cover bg-center"
             style="background-image: url('<?= !empty($login_cover_url) ? htmlspecialchars($login_cover_url) : '' ?>');">
            <div class="absolute inset-0 bg-teal-900 bg-opacity-60"></div>
        </div>
        <div class="flex-1 flex flex-col items-center justify-center p-6">
            <div class="w-full max-w-md">
                <?php if (!empty($logo_horizontal_url)): ?>
                    <img src="<?= htmlspecialchars($logo_horizontal_url) ?>" alt="Logo" class="h-14 mx-auto mb-6">
                <?php endif; ?>
                <h1 class="text-2xl font-bold text-gray-900 text-center mb-2">Portal do Monitor</h1>
                <p class="text-gray-600 text-center mb-8">Acompanhe alunos online e apoie a sala em tempo real</p>

                <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
                <?php if (!empty($flash_msg)): ?>
                <div class="mb-4 bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg">
                    <?= htmlspecialchars($flash_msg) ?>
                </div>
                <?php endif; ?>

                <form action="<?= URL ?>/login" method="post" class="space-y-4 bg-white p-6 rounded-xl shadow-lg border border-teal-100">
                    <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                    <input type="hidden" name="tipo" value="monitor">
                    <input type="text" name="site_url" value="" tabindex="-1" autocomplete="off" class="hidden">
                    <div>
                        <label for="login" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                        <input id="login" name="login" type="email" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                               placeholder="monitor@escola.com">
                    </div>
                    <div>
                        <label for="senha" class="block text-sm font-medium text-gray-700 mb-1">Senha</label>
                        <input id="senha" name="senha" type="password" required
                               class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:ring-2 focus:ring-teal-500"
                               placeholder="Sua senha">
                    </div>
                    <button type="submit"
                            class="w-full bg-gradient-to-r from-teal-600 to-cyan-600 hover:from-teal-700 hover:to-cyan-700 text-white font-semibold py-3 rounded-lg">
                        Entrar no painel
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
