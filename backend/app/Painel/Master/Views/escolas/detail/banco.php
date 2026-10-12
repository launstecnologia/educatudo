<?php
$escola = $escola ?? [];
$connection_ok = $connection_ok ?? null;
$connection_error = $connection_error ?? null;
$migration_files = $migration_files ?? [];
$migrations_executadas = $migrations_executadas ?? [];
$escola_id = $escola_id ?? 0;

$total = count($migration_files);
$executadas = count($migrations_executadas);
$pendentes_list = array_diff($migration_files, $migrations_executadas);
$pendentes = count($pendentes_list);
?>

<div class="space-y-6">
    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Informações do Banco de Dados</h3>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
                <span class="block text-xs font-medium text-slate-500 uppercase mb-1">Host</span>
                <span class="text-sm text-slate-800 font-mono"><?= htmlspecialchars($escola['db_host'] ?? 'localhost') ?></span>
            </div>
            <div>
                <span class="block text-xs font-medium text-slate-500 uppercase mb-1">Porta</span>
                <span class="text-sm text-slate-800 font-mono"><?= htmlspecialchars($escola['db_porta'] ?? '3306') ?></span>
            </div>
            <div>
                <span class="block text-xs font-medium text-slate-500 uppercase mb-1">Nome do banco</span>
                <span class="text-sm text-slate-800 font-mono"><?= htmlspecialchars($escola['nome_banco'] ?? $escola['db_nome_banco'] ?? '') ?></span>
            </div>
            <div>
                <span class="block text-xs font-medium text-slate-500 uppercase mb-1">Usuário</span>
                <span class="text-sm text-slate-800 font-mono"><?= htmlspecialchars($escola['db_usuario'] ?? '') ?></span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Teste de Conexão</h3>
        <div class="flex items-center gap-3">
            <?php if ($connection_ok === true): ?>
            <div class="flex items-center gap-2 px-4 py-2.5 bg-green-50 border border-green-200 rounded-lg">
                <span class="w-3 h-3 rounded-full bg-green-500"></span>
                <span class="text-sm font-medium text-green-800">Conexão estabelecida com sucesso</span>
            </div>
            <?php elseif ($connection_ok === false): ?>
            <div class="flex items-center gap-2 px-4 py-2.5 bg-red-50 border border-red-200 rounded-lg">
                <span class="w-3 h-3 rounded-full bg-red-500"></span>
                <span class="text-sm font-medium text-red-800">Falha na conexão<?= $connection_error ? ': ' . htmlspecialchars($connection_error) : '' ?></span>
            </div>
            <?php else: ?>
            <div class="flex items-center gap-2 px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-lg">
                <span class="w-3 h-3 rounded-full bg-gray-400"></span>
                <span class="text-sm font-medium text-slate-600">Banco não configurado</span>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow border border-slate-200 p-6">
        <h3 class="text-lg font-semibold text-slate-800 mb-4">Status das Migrations</h3>
        <div class="flex flex-wrap gap-4 mb-4">
            <div class="px-4 py-3 bg-slate-50 rounded-lg">
                <span class="block text-xs font-medium text-slate-500 uppercase">Total</span>
                <span class="text-2xl font-bold text-slate-900"><?= (int) $total ?></span>
            </div>
            <div class="px-4 py-3 bg-green-50 rounded-lg">
                <span class="block text-xs font-medium text-green-600 uppercase">Executadas</span>
                <span class="text-2xl font-bold text-green-800"><?= (int) $executadas ?></span>
            </div>
            <div class="px-4 py-3 <?= $pendentes > 0 ? 'bg-red-50' : 'bg-green-50' ?> rounded-lg">
                <span class="block text-xs font-medium <?= $pendentes > 0 ? 'text-red-600' : 'text-green-600' ?> uppercase">Pendentes</span>
                <span class="text-2xl font-bold <?= $pendentes > 0 ? 'text-red-800' : 'text-green-800' ?>"><?= (int) $pendentes ?></span>
            </div>
        </div>

        <?php if ($pendentes > 0): ?>
        <div class="mb-4">
            <p class="text-sm font-medium text-slate-700 mb-2">Migrations pendentes:</p>
            <ul class="space-y-1">
                <?php foreach ($pendentes_list as $mig): ?>
                <li class="flex items-center gap-2 text-sm text-slate-600">
                    <svg class="w-4 h-4 text-amber-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <code class="bg-slate-100 px-1.5 py-0.5 rounded text-xs"><?= htmlspecialchars($mig) ?></code>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <form method="post" action="<?= URL ?>/master/migrations/executar-escola">
            <input type="hidden" name="escola_id" value="<?= (int) $escola_id ?>">
            <button type="submit" class="px-5 py-2.5 bg-red-600 text-white rounded-lg hover:bg-red-700 text-sm font-medium"
                    onclick="return confirm('Deseja executar as migrations pendentes?')">
                Executar migrations pendentes
            </button>
        </form>
        <?php else: ?>
        <p class="text-sm text-green-700">Todas as migrations foram executadas.</p>
        <?php endif; ?>
    </div>

    <div class="text-right">
        <a href="<?= URL ?>/master/escolas/editar?id=<?= (int) $escola_id ?>" class="inline-flex items-center gap-2 px-4 py-2 border border-slate-300 rounded-lg text-sm font-medium text-slate-700 hover:bg-slate-50">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
            Editar configurações do banco
        </a>
    </div>
</div>
