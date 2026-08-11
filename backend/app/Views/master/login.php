<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Login Admin Master') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] } } } };</script>
</head>
<body class="bg-slate-50 min-h-screen flex flex-col items-center justify-center p-4 font-sans antialiased">
    <div class="max-w-md w-full">
        <div class="bg-white py-8 px-6 shadow-sm rounded-xl border border-slate-200">
            <div class="text-center mb-8">
                <img src="<?= URL ?>/assets/logos/logo-educatudo-black.png" alt="EducaTudo" class="h-10 w-auto mx-auto mb-6 object-contain">
                <h1 class="text-xl font-bold text-slate-900">Painel Admin Master</h1>
                <p class="text-sm text-slate-500 mt-1">Entre com seu e-mail e senha</p>
            </div>
            <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
            <?php if (!empty($flash_msg)): ?>
            <div class="mb-4 px-4 py-3 rounded-lg border <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-50 border-red-200 text-red-800' : 'bg-amber-50 border-amber-200 text-amber-800' ?>">
                <?= htmlspecialchars($flash_msg) ?>
            </div>
            <?php endif; ?>
            <form action="<?= URL ?>/master/login" method="post" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
                <div>
                    <label for="email" class="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
                    <input id="email" name="email" type="email" required
                           class="w-full px-4 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                           placeholder="seu@email.com">
                </div>
                <div>
                    <label for="senha" class="block text-sm font-medium text-slate-700 mb-1">Senha</label>
                    <div class="relative">
                        <input id="senha" name="senha" type="password" required
                               class="w-full px-4 py-2 pr-11 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                               placeholder="••••••••">
                        <button type="button" id="toggleSenha" class="absolute right-2 top-1/2 -translate-y-1/2 p-1.5 text-slate-500 hover:text-slate-700 rounded focus:outline-none focus:ring-2 focus:ring-blue-400" aria-label="Mostrar ou ocultar senha">
                            <svg id="iconSenhaOcultar" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/></svg>
                            <svg id="iconSenhaMostrar" class="w-5 h-5 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/></svg>
                        </button>
                    </div>
                </div>
                <button type="submit" class="w-full py-3 px-4 bg-blue-600 text-white font-semibold rounded-lg hover:bg-blue-700 focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 transition-all duration-200">
                    Entrar
                </button>
            </form>
            <p class="mt-4 text-center text-sm text-slate-500">
                <a href="<?= URL ?>/master/logout" class="text-slate-600 hover:text-blue-600">Sair</a> (se já estiver em outra sessão)
            </p>
        </div>
        <p class="mt-4 text-center text-sm text-slate-500">EducaTudo Multi-tenant</p>
    </div>
    <script>
    (function() {
        var input = document.getElementById('senha');
        var btn = document.getElementById('toggleSenha');
        var iconOcultar = document.getElementById('iconSenhaOcultar');
        var iconMostrar = document.getElementById('iconSenhaMostrar');
        if (!btn || !input) return;
        btn.addEventListener('click', function() {
            var show = input.type === 'password';
            input.type = show ? 'text' : 'password';
            iconOcultar.classList.toggle('hidden', show);
            iconMostrar.classList.toggle('hidden', !show);
        });
    })();
    </script>
</body>
</html>
