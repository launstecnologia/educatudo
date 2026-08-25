<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Criar administrador master') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-4 font-sans antialiased">
    <div class="max-w-md w-full">
        <div class="bg-white py-8 px-6 shadow-sm rounded-xl border border-slate-200">
            <div class="text-center mb-8">
                <img src="<?= URL ?>/assets/logos/logo-educatudo-black.png" alt="EducaTudo" class="h-10 w-auto mx-auto mb-6 object-contain">
                <h1 class="text-xl font-bold text-slate-900">Painel Admin Master</h1>
                <p class="text-sm text-slate-500 mt-1">Crie o primeiro administrador</p>
            </div>
            <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
            <?php if (!empty($flash_msg)): ?>
            <div class="mb-4 px-4 py-3 rounded-lg border <?= ($flash['type'] ?? '') === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800' ?>">
                <?= htmlspecialchars($flash_msg) ?>
            </div>
            <?php endif; ?>
            <form action="<?= URL ?>/master/setup" method="post" class="space-y-4">
                <div>
                    <label for="nome" class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
                    <input id="nome" name="nome" type="text" value="Admin Master"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Seu nome">
                </div>
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
                    <input id="email" name="email" type="email" required
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="admin@exemplo.com">
                </div>
                <div>
                    <label for="senha" class="block text-sm font-medium text-slate-700 mb-1">Senha (mín. 6 caracteres)</label>
                    <input id="senha" name="senha" type="password" required minlength="6"
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="••••••••">
                </div>
                <button type="submit" class="w-full py-3 px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                    Criar e entrar
                </button>
            </form>
        </div>
        <p class="mt-4 text-center text-sm text-slate-500">EducaTudo Multi-tenant</p>
    </div>
</body>
</html>
