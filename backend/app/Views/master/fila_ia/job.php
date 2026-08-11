<?php
$job = $job ?? [];
$csrf_token = $csrf_token ?? ($_SESSION['csrf_token'] ?? '');
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

$result = is_array($job['result_decoded'] ?? null) ? $job['result_decoded'] : [];
$payload = is_array($job['payload_decoded'] ?? null) ? $job['payload_decoded'] : [];
$payloadJson = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$resultJson = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$temResultado = $result !== [];

$questoes = [];
if (!empty($result['questoes']) && is_array($result['questoes'])) {
    $questoes = $result['questoes'];
} elseif (!empty($result['questions']) && is_array($result['questions'])) {
    $questoes = $result['questions'];
}
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
    <div class="flex items-center gap-2 flex-wrap">
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium <?= $statusCls ?>"><?= htmlspecialchars($status) ?></span>
        <?php if (!empty($job['travado'])): ?>
        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-orange-100 text-orange-800">travado</span>
        <?php endif; ?>
        <?php if (in_array($status, ['processing', 'failed', 'pending'], true)): ?>
        <form method="post" action="<?= URL ?>/master/fila-ia/reenfileirar"
              onsubmit="return confirm('Reenfileirar este job para o cron processar de novo?');">
            <input type="hidden" name="_token" value="<?= htmlspecialchars((string) $csrf_token) ?>">
            <input type="hidden" name="escola_id" value="<?= (int) ($job['escola_id'] ?? 0) ?>">
            <input type="hidden" name="job_id" value="<?= (int) ($job['id'] ?? 0) ?>">
            <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-xs font-medium text-white bg-orange-600 hover:bg-orange-700">
                Reenfileirar
            </button>
        </form>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($_SESSION['flash_success'])): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-emerald-50 border border-emerald-200 text-emerald-900 text-sm">
    <?= htmlspecialchars((string) $_SESSION['flash_success']) ?>
</div>
<?php unset($_SESSION['flash_success']); endif; ?>
<?php if (!empty($_SESSION['flash_error'])): ?>
<div class="mb-4 px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm">
    <?= htmlspecialchars((string) $_SESSION['flash_error']) ?>
</div>
<?php unset($_SESSION['flash_error']); endif; ?>

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

<!-- Retorno da IA (destaque) -->
<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden mb-6">
    <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between gap-3">
        <div>
            <h3 class="text-sm font-semibold text-slate-900">Retorno da IA</h3>
            <p class="text-xs text-slate-500 mt-0.5">Conteúdo salvo em <code class="bg-slate-100 px-1 rounded">ai_jobs.result</code></p>
        </div>
        <?php if ($temResultado): ?>
        <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('result-json-raw').textContent)"
                class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-50">
            Copiar JSON
        </button>
        <?php endif; ?>
    </div>

    <?php if (!$temResultado): ?>
    <div class="px-5 py-8 text-sm text-slate-500 text-center">
        <?php if ($status === 'done'): ?>
        Job marcado como done, mas sem <code>result</code> gravado.
        <?php elseif ($status === 'failed'): ?>
        Sem retorno — o job falhou. Veja a mensagem de erro acima.
        <?php else: ?>
        Ainda sem retorno (job não concluído).
        <?php endif; ?>
    </div>
    <?php else: ?>

    <?php if (!empty($questoes)): ?>
    <div class="px-5 py-4 border-b border-slate-100 space-y-4">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide">
            Questões geradas (<?= count($questoes) ?>)
            <?php if (isset($result['questoes_descartadas'])): ?>
            · descartadas: <?= (int) $result['questoes_descartadas'] ?>
            <?php endif; ?>
        </p>
        <?php foreach ($questoes as $i => $q): ?>
        <?php
            $q = is_array($q) ? $q : ['enunciado' => (string) $q];
            $enunciado = (string) ($q['enunciado'] ?? $q['pergunta'] ?? $q['question'] ?? $q['texto'] ?? '');
            $alternativas = $q['alternativas'] ?? $q['options'] ?? $q['opcoes'] ?? null;
            $resposta = $q['resposta_correta'] ?? $q['gabarito'] ?? $q['correct'] ?? $q['resposta'] ?? null;
            $nivel = $q['dificuldade'] ?? $q['nivel'] ?? null;
        ?>
        <div class="rounded-lg border border-slate-200 bg-slate-50 p-4">
            <div class="flex items-start justify-between gap-2 mb-2">
                <p class="text-xs font-semibold text-slate-500">Questão <?= $i + 1 ?></p>
                <?php if ($nivel): ?>
                <span class="text-xs px-2 py-0.5 rounded-full bg-white border border-slate-200 text-slate-600"><?= htmlspecialchars((string) $nivel) ?></span>
                <?php endif; ?>
            </div>
            <?php if ($enunciado !== ''): ?>
            <p class="text-sm text-slate-900 whitespace-pre-wrap"><?= htmlspecialchars($enunciado) ?></p>
            <?php else: ?>
            <pre class="text-xs text-slate-700 overflow-x-auto whitespace-pre-wrap"><?= htmlspecialchars(json_encode($q, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?></pre>
            <?php endif; ?>
            <?php if (is_array($alternativas) && $alternativas !== []): ?>
            <ul class="mt-3 space-y-1 text-sm text-slate-700">
                <?php foreach ($alternativas as $altKey => $altVal): ?>
                <li class="flex gap-2">
                    <span class="font-medium text-slate-500 shrink-0"><?= htmlspecialchars(is_string($altKey) ? $altKey : chr(65 + (int) $altKey)) ?>)</span>
                    <span><?= htmlspecialchars(is_scalar($altVal) ? (string) $altVal : json_encode($altVal, JSON_UNESCAPED_UNICODE)) ?></span>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
            <?php if ($resposta !== null && $resposta !== ''): ?>
            <p class="mt-2 text-xs text-emerald-700 font-medium">
                Gabarito: <?= htmlspecialchars(is_scalar($resposta) ? (string) $resposta : json_encode($resposta, JSON_UNESCAPED_UNICODE)) ?>
            </p>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <div class="px-5 py-3 border-b border-slate-100 bg-slate-50/80">
        <p class="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">JSON completo do retorno</p>
        <pre id="result-json-raw" class="text-xs text-slate-700 overflow-x-auto max-h-[520px] overflow-y-auto whitespace-pre-wrap"><?= htmlspecialchars((string) $resultJson) ?></pre>
    </div>
    <?php endif; ?>
</div>

<div class="grid grid-cols-1 gap-4">
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        <div class="px-5 py-3 border-b border-slate-200 flex items-center justify-between">
            <h3 class="text-sm font-semibold text-slate-900">Payload (entrada)</h3>
            <button type="button" onclick="navigator.clipboard.writeText(document.getElementById('payload-json-raw').textContent)"
                    class="inline-flex items-center px-3 py-1.5 rounded-lg border border-slate-300 text-xs font-medium text-slate-700 hover:bg-slate-50">
                Copiar JSON
            </button>
        </div>
        <pre id="payload-json-raw" class="p-5 text-xs text-slate-700 overflow-x-auto max-h-[400px] overflow-y-auto bg-slate-50 whitespace-pre-wrap"><?= htmlspecialchars((string) $payloadJson) ?></pre>
    </div>
</div>
