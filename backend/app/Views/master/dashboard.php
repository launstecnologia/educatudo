<?php require_once __DIR__ . '/../../Core/CreditosDecimalHelper.php'; ?>
<?php
$kpis = $kpis ?? [
    'total_logins_sucesso' => 0,
    'total_jornadas' => 0,
    'total_provas' => 0,
    'modulos' => [],
    'gerado_em' => null,
    'disponivel' => false,
];
$modulos = is_array($kpis['modulos'] ?? null) ? $kpis['modulos'] : [];
$somaModulos = 0;
foreach ($modulos as $m) {
    $somaModulos += (int) ($m['acessos'] ?? 0);
}
$geradoEmFmt = !empty($kpis['gerado_em']) ? date('d/m/Y H:i', strtotime($kpis['gerado_em'])) : null;
?>

<div class="mb-8">
    <h2 class="text-2xl font-bold text-slate-900">Painel Master</h2>
    <p class="text-slate-500 mt-1">Visão geral das escolas e atividade em tempo real.</p>
</div>

<?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
<?php if (!empty($flash_msg)): ?>
<div class="mb-6 px-4 py-3 rounded-lg border <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-50 border-red-200 text-red-800' : 'bg-green-50 border-green-200 text-green-800' ?>">
    <?= htmlspecialchars($flash_msg) ?>
</div>
<?php endif; ?>

<?php if (empty($kpis['disponivel'])): ?>
<div class="mb-6 px-4 py-3 rounded-lg border border-amber-200 bg-amber-50 text-amber-800 text-sm">
    KPIs agregados ainda não disponíveis. Aguarde a primeira execução do CRON <code class="text-xs bg-amber-100 px-1 rounded">master_dashboard_kpis</code> (meia-noite) ou rode manualmente via CLI.
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Total de escolas</p>
                <p class="text-3xl font-bold text-slate-900 mt-1"><?= (int) ($stats['escolas_ativas'] ?? 0) ?></p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Alunos online</p>
                <p class="text-3xl font-bold text-slate-900 mt-1" id="master-total-alunos">0</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Professores online</p>
                <p class="text-3xl font-bold text-slate-900 mt-1" id="master-total-professores">0</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
        </div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
        <div class="flex items-start justify-between gap-4">
            <div>
                <p class="text-sm text-slate-500">Usuários online</p>
                <p class="text-3xl font-bold text-slate-900 mt-1" id="master-total-usuarios">0</p>
            </div>
            <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center shrink-0">
                <svg class="w-5 h-5 text-slate-700" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                </svg>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
        <p class="text-sm text-slate-500">Total de acessos</p>
        <p class="text-3xl font-bold text-slate-900 mt-1"><?= number_format((int) ($kpis['total_logins_sucesso'] ?? 0), 0, ',', '.') ?></p>
        <p class="text-xs text-slate-400 mt-2">Sessões de alunos (ignora reentradas em menos de 10 min)</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
        <p class="text-sm text-slate-500">Total de jornadas criadas</p>
        <p class="text-3xl font-bold text-slate-900 mt-1"><?= number_format((int) ($kpis['total_jornadas'] ?? 0), 0, ',', '.') ?></p>
        <p class="text-xs text-slate-400 mt-2">Soma em todas as escolas</p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 hover:shadow-md transition-all duration-200">
        <p class="text-sm text-slate-500">Total de provas</p>
        <p class="text-3xl font-bold text-slate-900 mt-1"><?= number_format((int) ($kpis['total_provas'] ?? 0), 0, ',', '.') ?></p>
        <p class="text-xs text-slate-400 mt-2">Provas cadastradas nas escolas</p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
        <div>
            <h3 class="text-lg font-semibold text-slate-900">Módulos mais usados</h3>
            <p class="text-sm text-slate-500 mt-1">Alunos distintos que usaram cada módulo (não linhas de progresso).</p>
        </div>
        <?php if ($geradoEmFmt): ?>
        <p class="text-xs text-slate-400">Atualizado em <?= htmlspecialchars($geradoEmFmt) ?></p>
        <?php endif; ?>
    </div>
    <div class="p-6">
        <?php if (empty($modulos) || $somaModulos <= 0): ?>
        <p class="text-sm text-slate-500">Sem dados de uso de módulos ainda.</p>
        <?php else:
            $coresHex = ['#2563eb', '#10b981', '#8b5cf6', '#f59e0b', '#f43f5e', '#06b6d4'];
            $chartData = [];
            $i = 0;
            foreach ($modulos as $m) {
                $acessos = (int) ($m['acessos'] ?? 0);
                $pct = $somaModulos > 0 ? round(($acessos / $somaModulos) * 100, 1) : 0;
                $chartData[] = [
                    'label' => (string) ($m['label'] ?? $m['slug'] ?? 'Módulo'),
                    'acessos' => $acessos,
                    'pct' => $pct,
                    'cor' => $coresHex[$i % count($coresHex)],
                ];
                $i++;
            }
            // Donut SVG via stroke-dasharray
            $r = 70;
            $c = 2 * M_PI * $r;
            $offset = 0;
        ?>
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-center">
            <div class="lg:col-span-5 flex flex-col items-center justify-center">
                <div class="relative w-56 h-56">
                    <svg viewBox="0 0 180 180" class="w-full h-full -rotate-90" aria-hidden="true">
                        <circle cx="90" cy="90" r="<?= $r ?>" fill="none" stroke="#f1f5f9" stroke-width="22"></circle>
                        <?php foreach ($chartData as $slice):
                            $len = ($slice['pct'] / 100) * $c;
                            $gap = max(0, $c - $len);
                        ?>
                        <circle cx="90" cy="90" r="<?= $r ?>" fill="none"
                                stroke="<?= htmlspecialchars($slice['cor']) ?>"
                                stroke-width="22"
                                stroke-linecap="butt"
                                stroke-dasharray="<?= number_format($len, 2, '.', '') ?> <?= number_format($gap, 2, '.', '') ?>"
                                stroke-dashoffset="<?= number_format(-$offset, 2, '.', '') ?>"
                                class="transition-all duration-700"></circle>
                        <?php
                            $offset += $len;
                        endforeach; ?>
                    </svg>
                    <div class="absolute inset-0 flex flex-col items-center justify-center text-center pointer-events-none">
                        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">Alunos</p>
                        <p class="text-2xl font-bold text-slate-900 tabular-nums leading-tight"><?= number_format($somaModulos, 0, ',', '.') ?></p>
                        <p class="text-xs text-slate-400 mt-0.5">soma por módulo</p>
                    </div>
                </div>
            </div>
            <div class="lg:col-span-7 space-y-3">
                <?php foreach ($chartData as $idx => $slice): ?>
                <div class="flex items-center gap-3 p-3 rounded-xl border border-slate-100 hover:border-slate-200 hover:bg-slate-50/80 transition-colors">
                    <div class="w-8 h-8 rounded-lg flex items-center justify-center text-xs font-bold text-white shrink-0"
                         style="background-color: <?= htmlspecialchars($slice['cor']) ?>">
                        <?= $idx + 1 ?>
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2 mb-1.5">
                            <p class="text-sm font-semibold text-slate-900 truncate"><?= htmlspecialchars($slice['label']) ?></p>
                            <p class="text-sm font-bold text-slate-800 tabular-nums shrink-0"><?= number_format($slice['pct'], 1, ',', '.') ?>%</p>
                        </div>
                        <div class="h-1.5 w-full rounded-full bg-slate-100 overflow-hidden">
                            <div class="h-full rounded-full transition-all duration-700"
                                 style="width: <?= max(1.5, min(100, $slice['pct'])) ?>%; background-color: <?= htmlspecialchars($slice['cor']) ?>"></div>
                        </div>
                        <p class="text-xs text-slate-500 mt-1 tabular-nums"><?= number_format($slice['acessos'], 0, ',', '.') ?> alunos</p>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <p class="text-xs text-slate-400 mt-6">Contagem de alunos distintos por módulo; o mesmo aluno pode aparecer em mais de um. Atualizado diariamente.</p>
        <?php endif; ?>
    </div>
</div>
