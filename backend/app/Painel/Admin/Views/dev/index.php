<?php require __DIR__ . '/_styles.php'; ?>

<!-- Header Section -->
<header class="mb-8 pb-6 border-b border-gray-200">
    <div class="flex items-center gap-3 mb-1">
        <span class="flex items-center justify-center w-12 h-12 rounded-xl bg-indigo-100 text-indigo-600 text-2xl">🔧</span>
        <div>
            <h1 class="text-2xl font-bold text-gray-900 tracking-tight">Configurações Avançadas</h1>
            <p class="text-sm text-gray-500 mt-0.5">Configurações para desenvolvedores · altere com cuidado</p>
        </div>
    </div>
</header>

<?php
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? 'info';
if ($flash_message !== ''):
    $bg = $flash_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800');
?>
<div class="mb-6 p-4 rounded-lg border <?= $bg ?>">
    <?= htmlspecialchars($flash_message) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5 max-w-5xl">

    <a href="<?= URL ?>/admin/dev/modulos" class="dev-card p-5 hover:border-green-300 transition-colors">
        <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-green-100 text-green-600 text-xl">🧩</span>
            <h3 class="font-semibold text-gray-900">Módulos e Limites</h3>
        </div>
        <p class="text-sm text-gray-600">Habilitar/desabilitar funcionalidades, limites diários e configurações financeiras.</p>
    </a>

    <a href="<?= URL ?>/admin/dev/aparencia" class="dev-card p-5 hover:border-indigo-300 transition-colors">
        <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-indigo-100 text-indigo-600 text-xl">🎨</span>
            <h3 class="font-semibold text-gray-900">Aparência e Menu</h3>
        </div>
        <p class="text-sm text-gray-600">Cores, logos, PWA, layout mobile e organização do menu lateral do aluno.</p>
    </a>

    <a href="<?= URL ?>/admin/dev/ia" class="dev-card p-5 hover:border-fuchsia-300 transition-colors">
        <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-fuchsia-100 text-fuchsia-600 text-xl">🤖</span>
            <h3 class="font-semibold text-gray-900">Inteligência Artificial</h3>
        </div>
        <p class="text-sm text-gray-600">Prompts usados pela IA e custos de uso por modelo.</p>
    </a>

    <a href="<?= URL ?>/admin/dev/integracoes" class="dev-card p-5 hover:border-orange-300 transition-colors">
        <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-orange-100 text-orange-600 text-xl">🔌</span>
            <h3 class="font-semibold text-gray-900">Integrações Externas</h3>
        </div>
        <p class="text-sm text-gray-600">Chaves de API, e-mail (SMTP), WhatsApp e webhooks.</p>
    </a>

    <a href="<?= URL ?>/admin/dev/operacao" class="dev-card p-5 hover:border-cyan-300 transition-colors">
        <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-cyan-100 text-cyan-600 text-xl">📊</span>
            <h3 class="font-semibold text-gray-900">Operação e Infraestrutura</h3>
        </div>
        <p class="text-sm text-gray-600">Monitoramento, logs, histórico de logins, migrations e SSH.</p>
    </a>

    <a href="<?= URL ?>/admin/dev/conteudo" class="dev-card p-5 hover:border-gray-400 transition-colors">
        <div class="flex items-center gap-3 mb-2">
            <span class="flex items-center justify-center w-10 h-10 rounded-lg bg-gray-100 text-gray-600 text-xl">🎥</span>
            <h3 class="font-semibold text-gray-900">Conteúdo</h3>
        </div>
        <p class="text-sm text-gray-600">Tutoriais em vídeo exibidos no menu do professor.</p>
    </a>

</div>
