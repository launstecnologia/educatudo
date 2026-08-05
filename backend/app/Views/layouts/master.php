<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Painel Admin Master') ?></title>
    <meta name="csrf-token" content="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
    tailwind.config = {
        theme: {
            extend: {
                fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
            }
        }
    };
    </script>
    <?php include __DIR__ . '/components/form_control_safari.php'; ?>
</head>
<body class="h-screen overflow-hidden bg-slate-50 font-sans antialiased text-slate-900 flex flex-col">
<?php
$master_nome = $master_nome ?? ($_SESSION['master_user_nome'] ?? 'Admin');
$master_avatar_url = $master_avatar_url ?? ($_SESSION['master_user_avatar'] ?? '');
if (!class_exists('MasterAvatarService')) {
    require_once __DIR__ . '/../../Services/MasterAvatarService.php';
}
$master_iniciais = MasterAvatarService::iniciais($master_nome);
?>
    <div id="sidebar-overlay" class="fixed inset-0 bg-black/40 z-40 hidden md:hidden" aria-hidden="true"></div>
    <div class="flex flex-1 min-h-0 h-full overflow-hidden">
        <?php include __DIR__ . '/components/master_sidebar.php'; ?>
        <div class="flex flex-col flex-1 min-w-0 min-h-0 overflow-hidden">
            <header class="bg-white border-b border-slate-200 px-4 md:px-8 py-4 shrink-0 z-30">
                <div class="flex justify-between items-center gap-4">
                    <button id="mobileMenuToggle" type="button" class="md:hidden p-2 text-slate-600 hover:bg-slate-100 rounded-lg transition-colors duration-200" aria-label="Abrir menu">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                        </svg>
                    </button>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-xl md:text-2xl font-bold text-slate-900 truncate"><?= htmlspecialchars($page_title ?? 'Dashboard') ?></h1>
                        <p class="text-sm text-slate-500 truncate mt-0.5">Bem-vindo, <?= htmlspecialchars($master_nome ?? 'Admin') ?></p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <a href="<?= URL ?>/master/documentacao"
                           class="hidden sm:inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium rounded-lg border border-slate-200 text-slate-700 bg-white hover:bg-slate-50 transition-colors <?= (($current_page ?? '') === 'documentacao') ? 'ring-2 ring-blue-100 border-blue-200 text-blue-700' : '' ?>">
                            <i class="fa-solid fa-book text-xs opacity-70"></i>
                            Documentação
                        </a>
                    <div class="relative flex-shrink-0" data-dropdown>
                        <button type="button" id="master-user-menu-toggle" data-dropdown-toggle
                                class="flex items-center gap-2 px-2 py-1.5 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 transition-all duration-200">
                            <?php if (!empty($master_avatar_url)): ?>
                            <img src="<?= htmlspecialchars($master_avatar_url) ?>" alt="<?= htmlspecialchars($master_nome) ?>"
                                 class="w-8 h-8 rounded-full object-cover border border-slate-200">
                            <?php else: ?>
                            <div class="w-8 h-8 bg-slate-800 rounded-full flex items-center justify-center text-white text-xs font-semibold">
                                <?= htmlspecialchars($master_iniciais) ?>
                            </div>
                            <?php endif; ?>
                            <span class="hidden md:inline text-sm font-medium text-slate-700 max-w-[10rem] truncate"><?= htmlspecialchars($master_nome ?? 'Admin') ?></span>
                            <svg class="w-4 h-4 text-slate-400 hidden md:block" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                        <div data-dropdown-menu class="hidden fixed z-50 w-48 origin-top-right rounded-lg bg-white shadow-lg ring-1 ring-black/5 overflow-hidden">
                            <div class="px-4 py-3 border-b border-slate-100">
                                <p class="text-sm font-medium text-slate-900 truncate"><?= htmlspecialchars($master_nome ?? 'Admin') ?></p>
                                <p class="text-xs text-slate-500">Admin Master</p>
                            </div>
                            <div class="py-1">
                                <a href="<?= URL ?>/master/logout" class="flex items-center gap-2 px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path>
                                    </svg>
                                    Sair
                                </a>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </header>
            <div class="flex-1 min-h-0 overflow-y-auto overflow-x-hidden">
                <main class="px-4 md:px-8 py-6">
                    <?= $content ?? '' ?>
                </main>
                <footer class="border-t border-slate-200 bg-white px-4 md:px-8 py-4">
                    <div class="space-y-2">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                            <p class="text-xs text-slate-500 flex items-center justify-center sm:justify-start gap-2">
                                <span>Todos os direitos reservados</span>
                                <img src="<?= URL ?>/public/assets/logos/logo-educatudo-black.png" alt="EducaTudo" class="h-4 w-auto inline-block" loading="lazy">
                            </p>
                            <p class="text-xs text-slate-500 flex items-center justify-center sm:justify-end gap-2">
                                <span>Desenvolvido por</span>
                                <a href="https://www.launs.com.br" target="_blank" rel="noopener noreferrer" class="inline-flex items-center">
                                    <img src="<?= URL ?>/public/assets/logos/logo-launs-black.png" alt="Launs" class="h-3 w-auto inline-block" loading="lazy">
                                </a>
                            </p>
                        </div>
                        <p class="text-xs text-slate-500 text-center">
                            Documentação: <a href="<?= URL ?>/master/documentacao" class="text-blue-600 hover:underline">/master/documentacao</a>
                            · arquivos em <code class="text-xs">doc_sistema/</code>
                        </p>
                    </div>
                </footer>
            </div>
        </div>
    </div>
    <script>
    function getCsrfToken() {
        const tokenElement = document.querySelector('meta[name="csrf-token"]');
        return tokenElement ? tokenElement.getAttribute('content') : '';
    }
    </script>
    <script>
    document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
        var s = document.getElementById('sidebar');
        var o = document.getElementById('sidebar-overlay');
        if (s) s.classList.toggle('mobile-open');
        if (o) o.classList.toggle('hidden', !s || !s.classList.contains('mobile-open'));
    });
    </script>
    <script>
    (function() {
        window.EDUCATUDO_MASTER_ONLINE = { data: null };
        var ws = null;
        function updateResumo(data) {
            data = data || {};
            var totalAlunos = 0, totalProf = 0;
            for (var s in data) {
                totalAlunos += (data[s].alunos || 0);
                totalProf += (data[s].professores || 0);
            }
            var totalUsuarios = totalAlunos + totalProf;
            var elAlunos = document.getElementById('master-total-alunos');
            var elProf = document.getElementById('master-total-professores');
            var elUsuarios = document.getElementById('master-total-usuarios');
            if (elAlunos) elAlunos.textContent = totalAlunos;
            if (elProf) elProf.textContent = totalProf;
            if (elUsuarios) elUsuarios.textContent = totalUsuarios;
            var cards = document.querySelectorAll('#master-escolas-cards [data-escola-slug]');
            cards.forEach(function(card) {
                var slug = (card.getAttribute('data-escola-slug') || '').toLowerCase();
                var d = data[slug] || { alunos: 0, professores: 0 };
                var alunos = d.alunos || 0;
                var prof = d.professores || 0;
                var a = card.querySelector('.master-card-alunos');
                var p = card.querySelector('.master-card-professores');
                if (a) a.textContent = alunos;
                if (p) p.textContent = prof;
            });
        }
        function connect() {
            try { ws = new WebSocket("wss://ws.educatudo.com"); } catch (e) { return; }
            ws.onopen = function() {
                ws.send(JSON.stringify({ type: 'get_dashboard' }));
                if (window.EDUCATUDO_MASTER_ONLINE.data != null) {
                    updateResumo(window.EDUCATUDO_MASTER_ONLINE.data);
                }
            };
            ws.onmessage = function(event) {
                try {
                    var msg = JSON.parse(event.data);
                    if (msg.type === 'dashboard' && msg.data != null) {
                        window.EDUCATUDO_MASTER_ONLINE.data = msg.data;
                        updateResumo(msg.data);
                    }
                } catch (e) {}
            };
            ws.onclose = function() { ws = null; setTimeout(connect, 3000); };
            ws.onerror = function() { if (ws) ws.close(); };
        }
        connect();
    })();
    </script>
    <?php include __DIR__ . '/components/row_actions_dropdown_js.php'; ?>
</body>
</html>
