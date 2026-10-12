<?php
$stats = $stats ?? [];
$tenant_ok = $tenant_ok ?? false;
$migrations = $migrations ?? [];

$cards = [
    ['label' => 'Alunos ativos', 'value' => $stats['alunos'] ?? 0, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197m13.5-9a2.5 2.5 0 11-5 0 2.5 2.5 0 015 0z"/>'],
    ['label' => 'Professores', 'value' => $stats['professores'] ?? 0, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>'],
    ['label' => 'Administradores', 'value' => $stats['admins'] ?? 0, 'icon' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>'],
];
?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-6 mb-6">
    <?php foreach ($cards as $card): ?>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500"><?= htmlspecialchars($card['label']) ?></p>
                <p class="text-3xl font-bold text-slate-900 mt-1"><?= number_format($card['value'], 0, ',', '.') ?></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24"><?= $card['icon'] ?></svg>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <h3 class="text-lg font-semibold text-slate-900 mb-4">Status do banco de dados</h3>
    <div class="flex items-center gap-3">
        <?php if ($tenant_ok): ?>
        <span class="flex items-center gap-2 text-green-700">
            <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
            Conectado
        </span>
        <?php else: ?>
        <span class="flex items-center gap-2 text-red-700">
            <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
            Desconectado
        </span>
        <?php endif; ?>
    </div>
    <?php if (!$tenant_ok): ?>
    <p class="text-sm text-slate-500 mt-3">Verifique as configurações de banco de dados na seção <strong class="text-slate-700">Banco de Dados</strong>.</p>
    <a href="<?= URL ?>/master/escolas/<?= (int)($escola_id ?? $escola['id'] ?? 0) ?>/banco" class="inline-flex items-center gap-2 mt-4 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 text-sm font-medium transition-all duration-200">
        Configurar / Reconectar
    </a>
    <?php endif; ?>
</div>
