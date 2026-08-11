<?php
$execucao = $execucao ?? [];
$escolas_cron = $escolas_cron ?? [];
$fmtDt = static function ($dt) {
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y H:i:s', $ts) : $dt;
};
$status = (string) ($execucao['status'] ?? '');
$statusCls = [
    'rodando' => 'bg-blue-100 text-blue-800',
    'ok' => 'bg-emerald-100 text-emerald-800',
    'erro' => 'bg-red-100 text-red-800',
    'parcial' => 'bg-amber-100 text-amber-800',
][$status] ?? 'bg-slate-100 text-slate-700';
$ms = isset($execucao['duracao_ms']) ? (int) $execucao['duracao_ms'] : null;
$duracao = $ms === null ? '—' : ($ms < 1000 ? $ms . ' ms' : number_format($ms / 1000, 1, ',', '.') . ' s');
?>

<div class="mb-6">
    <a href="<?= URL ?>/master/fila-ia?aba=cron" class="text-sm text-blue-600 hover:underline">← Voltar às execuções</a>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 mt-2">
        <div>
            <h2 class="text-2xl font-bold text-slate-900">Execução #<?= (int) ($execucao['id'] ?? 0) ?></h2>
            <p class="text-slate-600 text-sm mt-1">
                Script <code class="text-xs bg-slate-100 px-1 rounded"><?= htmlspecialchars((string) ($execucao['script'] ?? '')) ?></code>
            </p>
        </div>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusCls ?>"><?= htmlspecialchars($status) ?></span>
    </div>
</div>

<div class="grid grid-cols-2 lg:grid-cols-4 gap-3 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase">Início</p>
        <p class="text-sm font-semibold text-slate-900 mt-1"><?= $fmtDt($execucao['iniciado_em'] ?? '') ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase">Fim</p>
        <p class="text-sm font-semibold text-slate-900 mt-1"><?= $fmtDt($execucao['finalizado_em'] ?? '') ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase">Duração</p>
        <p class="text-sm font-semibold text-slate-900 mt-1"><?= htmlspecialchars($duracao) ?></p>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
        <p class="text-xs font-medium text-slate-500 uppercase">Jobs processados</p>
        <p class="text-2xl font-bold text-slate-900 mt-1"><?= (int) ($execucao['jobs_processados'] ?? 0) ?></p>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 mb-6">
    <h3 class="text-sm font-semibold text-slate-900 mb-3">Totais</h3>
    <dl class="grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
        <div>
            <dt class="text-slate-500">Escolas total</dt>
            <dd class="font-medium text-slate-900"><?= (int) ($execucao['escolas_total'] ?? 0) ?></dd>
        </div>
        <div>
            <dt class="text-slate-500">OK</dt>
            <dd class="font-medium text-emerald-700"><?= (int) ($execucao['escolas_ok'] ?? 0) ?></dd>
        </div>
        <div>
            <dt class="text-slate-500">Erro</dt>
            <dd class="font-medium text-red-700"><?= (int) ($execucao['escolas_erro'] ?? 0) ?></dd>
        </div>
        <div>
            <dt class="text-slate-500">Puladas</dt>
            <dd class="font-medium text-slate-700"><?= (int) ($execucao['escolas_puladas'] ?? 0) ?></dd>
        </div>
    </dl>
    <?php if (!empty($execucao['hostname'])): ?>
    <p class="text-xs text-slate-500 mt-4">Host: <?= htmlspecialchars((string) $execucao['hostname']) ?></p>
    <?php endif; ?>
    <?php if (!empty($execucao['mensagem_erro'])): ?>
    <div class="mt-4 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm whitespace-pre-wrap"><?= htmlspecialchars((string) $execucao['mensagem_erro']) ?></div>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="px-6 py-4 border-b border-slate-200">
        <h3 class="text-lg font-semibold text-slate-900">Por escola</h3>
    </div>
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-slate-50">
                <tr>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Escola</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Status</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Jobs</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Período</th>
                    <th class="px-4 py-3 text-left text-xs font-medium text-slate-500 uppercase">Erro</th>
                    <th class="px-4 py-3 text-right text-xs font-medium text-slate-500 uppercase">Fila</th>
                </tr>
            </thead>
            <tbody class="bg-white divide-y divide-gray-200">
                <?php if (empty($escolas_cron)): ?>
                <tr>
                    <td colspan="6" class="px-4 py-8 text-center text-sm text-slate-500">Sem detalhe por escola nesta execução.</td>
                </tr>
                <?php else: ?>
                <?php foreach ($escolas_cron as $esc): ?>
                <?php
                $st = (string) ($esc['status'] ?? '');
                $stCls = [
                    'ok' => 'bg-emerald-100 text-emerald-800',
                    'erro' => 'bg-red-100 text-red-800',
                    'pulada' => 'bg-slate-100 text-slate-700',
                ][$st] ?? 'bg-slate-100 text-slate-700';
                $eid = (int) ($esc['escola_id'] ?? 0);
                ?>
                <tr class="hover:bg-slate-50">
                    <td class="px-4 py-3 text-sm">
                        <div class="font-medium text-slate-900"><?= htmlspecialchars((string) ($esc['escola_nome'] ?? ($eid > 0 ? 'Escola #' . $eid : '—'))) ?></div>
                        <?php if ($eid > 0): ?>
                        <div class="text-xs text-slate-400">#<?= $eid ?></div>
                        <?php endif; ?>
                    </td>
                    <td class="px-4 py-3 text-sm">
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $stCls ?>"><?= htmlspecialchars($st) ?></span>
                    </td>
                    <td class="px-4 py-3 text-sm font-medium text-slate-900"><?= (int) ($esc['jobs_processados'] ?? 0) ?></td>
                    <td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">
                        <?= $fmtDt($esc['iniciado_em'] ?? '') ?><br>
                        <?= $fmtDt($esc['finalizado_em'] ?? '') ?>
                    </td>
                    <td class="px-4 py-3 text-xs text-red-600 max-w-xs truncate" title="<?= htmlspecialchars((string) ($esc['mensagem_erro'] ?? '')) ?>">
                        <?= htmlspecialchars((string) ($esc['mensagem_erro'] ?? '—')) ?>
                    </td>
                    <td class="px-4 py-3 text-sm text-right">
                        <?php if ($eid > 0): ?>
                        <a href="<?= URL ?>/master/fila-ia?aba=fila&escola_id=<?= $eid ?>"
                           class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-50">
                            Ver fila
                        </a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
