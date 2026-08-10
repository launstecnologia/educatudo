<?php
$job = $job ?? [];
$fmtDt = static function ($dt) {
    $dt = trim((string) $dt);
    if ($dt === '') {
        return '—';
    }
    $ts = strtotime($dt);
    return $ts ? date('d/m/Y H:i:s', $ts) : $dt;
};
$status = (string) ($job['status'] ?? '');
$statusCls = [
    'pending' => 'bg-amber-100 text-amber-800',
    'processing' => 'bg-blue-100 text-blue-800',
    'done' => 'bg-emerald-100 text-emerald-800',
    'failed' => 'bg-red-100 text-red-800',
][$status] ?? 'bg-slate-100 text-slate-700';

$payloadJson = json_encode($job['payload_decoded'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$resultJson = json_encode($job['result_decoded'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
?>

<div class="mb-6 flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
    <div>
        <a href="<?= URL ?>/master/fila-ia" class="text-sm text-blue-600 hover:underline">← Voltar à fila</a>
        <h2 class="text-2xl font-bold text-slate-900 mt-2">Job #<?= (int) ($job['id'] ?? 0) ?></h2>
        <p class="text-slate-600 text-sm mt-1">
            <?= htmlspecialchars((string) ($job['escola_nome'] ?? '')) ?>
            <?php if (!empty($job['escola_slug'])): ?>
            <span class="text-slate-400">(<?= htmlspecialchars((string) $job['escola_slug']) ?>)</span>
            <?php endif; ?>
        </p>
    </div>
    <div class="flex items-center gap-2">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusCls ?>"><?= htmlspecialchars($status) ?></span>
        <?php if (!empty($job['travado'])): ?>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">travado</span>
        <?php endif; ?>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 lg:col-span-2">
        <h3 class="text-sm font-semibold text-slate-900 mb-4">Resumo</h3>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-3 text-sm">
            <div>
                <dt class="text-slate-500">Tipo</dt>
                <dd class="font-medium text-slate-900"><code class="text-xs bg-slate-100 px-1.5 py-0.5 rounded"><?= htmlspecialchars((string) ($job['job_type'] ?? '')) ?></code></dd>
            </div>
            <div>
                <dt class="text-slate-500">Tentativas</dt>
                <dd class="font-medium text-slate-900"><?= (int) ($job['attempts'] ?? 0) ?></dd>
            </div>
            <div>
                <dt class="text-slate-500">Criado em</dt>
                <dd class="font-medium text-slate-900"><?= $fmtDt($job['created_at'] ?? '') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500">Iniciado em</dt>
                <dd class="font-medium text-slate-900"><?= $fmtDt($job['started_at'] ?? '') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500">Concluído em</dt>
                <dd class="font-medium text-slate-900"><?= $fmtDt($job['completed_at'] ?? '') ?></dd>
            </div>
            <div>
                <dt class="text-slate-500">Escola ID</dt>
                <dd class="font-medium text-slate-900"><?= (int) ($job['escola_id'] ?? 0) ?></dd>
            </div>
        </dl>
        <?php if (!empty($job['error_message'])): ?>
        <div class="mt-4 px-3 py-2 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm whitespace-pre-wrap"><?= htmlspecialchars((string) $job['error_message']) ?></div>
        <?php endif; ?>
    </div>

    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
        <h3 class="text-sm font-semibold text-slate-900 mb-4">Usuário / aluno / professor</h3>
        <dl class="space-y-3 text-sm">
            <div>
                <dt class="text-slate-500">Quem disparou</dt>
                <dd class="font-medium text-slate-900">
                    <?= htmlspecialchars((string) ($job['usuario_nome'] ?? '—')) ?>
                    <?php if (!empty($job['user_role']) || !empty($job['user_id'])): ?>
                    <div class="text-xs text-slate-500 font-normal mt-0.5">
                        <?= htmlspecialchars((string) ($job['user_role'] ?? '')) ?>
                        <?= !empty($job['user_id']) ? '#' . (int) $job['user_id'] : '' ?>
                    </div>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Aluno</dt>
                <dd class="font-medium text-slate-900">
                    <?php if (!empty($job['aluno_nome']) || !empty($job['aluno_id'])): ?>
                    <?= htmlspecialchars((string) ($job['aluno_nome'] ?? '—')) ?>
                    <?php if (!empty($job['aluno_id'])): ?>
                    <span class="text-xs text-slate-500 font-normal">#<?= (int) $job['aluno_id'] ?></span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-slate-400">—</span>
                    <?php endif; ?>
                </dd>
            </div>
            <div>
                <dt class="text-slate-500">Professor</dt>
                <dd class="font-medium text-slate-900">
                    <?php if (!empty($job['professor_nome']) || !empty($job['professor_id'])): ?>
                    <?= htmlspecialchars((string) ($job['professor_nome'] ?? '—')) ?>
                    <?php if (!empty($job['professor_id'])): ?>
                    <span class="text-xs text-slate-500 font-normal">#<?= (int) $job['professor_id'] ?></span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="text-slate-400">—</span>
                    <?php endif; ?>
                </dd>
            </div>
        </dl>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-900">Payload</h3>
        </div>
        <pre class="p-5 text-xs text-slate-700 overflow-x-auto max-h-[480px] overflow-y-auto bg-slate-50"><?= htmlspecialchars((string) $payloadJson) ?></pre>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200">
            <h3 class="text-sm font-semibold text-slate-900">Result</h3>
        </div>
        <pre class="p-5 text-xs text-slate-700 overflow-x-auto max-h-[480px] overflow-y-auto bg-slate-50"><?= htmlspecialchars((string) $resultJson) ?></pre>
    </div>
</div>
