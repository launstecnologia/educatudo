<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Código de acesso') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php include \App\Core\ViewPaths::layoutComponent('estilos_plataforma.php'); ?>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-4 font-sans antialiased">
    <div class="max-w-md w-full">
        <div class="bg-white py-8 px-6 shadow-sm rounded-xl border border-slate-200">
            <div class="text-center mb-8">
                <img src="<?= URL ?>/assets/logos/logo-educatudo-black.png" alt="EducaTudo" class="h-10 w-auto mx-auto mb-6 object-contain">
                <h1 class="text-xl font-bold text-slate-900">Código de acesso</h1>
                <p class="text-sm text-slate-500 mt-1">Enviamos um código de 6 dígitos para <?= htmlspecialchars($email_mascarado ?? 'seu e-mail') ?>. Ele vale por 10 minutos.</p>
            </div>
            <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
            <?php
            $flashTipo = $flash['type'] ?? 'info';
            $flashClasse = 'bg-amber-50 border-amber-200 text-amber-800';
            if ($flashTipo === 'error') {
                $flashClasse = 'bg-red-50 border-red-200 text-red-800';
            } elseif ($flashTipo === 'success') {
                $flashClasse = 'bg-green-50 border-green-200 text-green-800';
            }
            ?>
            <?php if (!empty($flash_msg)): ?>
            <div class="mb-4 px-4 py-3 rounded-lg border <?= $flashClasse ?>">
                <?= htmlspecialchars($flash_msg) ?>
            </div>
            <?php endif; ?>
            <form action="<?= URL ?>/master/verificar" method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <div>
                    <label for="codigo" class="block text-sm font-medium text-slate-700 mb-1">Código</label>
                    <input id="codigo" name="codigo" type="text" inputmode="numeric" autocomplete="one-time-code"
                           pattern="[0-9]{6}" maxlength="6" required autofocus
                           class="w-full px-4 py-3 border border-slate-300 rounded-lg text-center text-2xl tracking-[0.4em] font-semibold focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="000000">
                </div>
                <button type="submit" class="w-full py-3 px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                    Confirmar
                </button>
            </form>
            <form action="<?= URL ?>/master/verificar/reenviar" method="post" class="mt-3">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <button type="submit" class="w-full py-2 px-4 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                    Reenviar código
                </button>
            </form>
            <p class="mt-4 text-center text-sm text-slate-500">
                <a href="<?= URL ?>/master/verificar/cancelar" class="text-slate-600 hover:text-blue-600">Entrar com outra conta</a>
            </p>
        </div>
        <p class="mt-4 text-center text-sm text-slate-500">EducaTudo Multi-tenant</p>
    </div>
</body>
</html>
