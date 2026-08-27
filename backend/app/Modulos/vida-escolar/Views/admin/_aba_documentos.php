<?php
$esc = $esc ?? static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$base = $base ?? (URL . '/admin/students/' . (int) ($aluno_id ?? 0) . '/vida-escolar');
$token = (string) ($csrf_token ?? $token ?? '');
$docsChecklist = is_array($docs_checklist ?? null) ? $docs_checklist : ['itens' => []];
$docsRecebidos = is_array($docs_recebidos ?? $documentos ?? null) ? ($docs_recebidos ?? $documentos) : [];
$emissoes = is_array($emissoes ?? null) ? $emissoes : [];
$historicos = is_array($historicos ?? null) ? $historicos : [];
$links = is_array($links ?? null) ? $links : [];
$alunoId = (int) ($aluno_id ?? 0);
$podeLerIa = !empty($pode_ler_ia);
$aiJobId = (int) ($ai_job_id ?? 0);
$tipoLabels = [
    'matricula' => 'Declaração de matrícula',
    'frequencia' => 'Declaração de frequência',
    'comparecimento' => 'Declaração de comparecimento',
    'transferencia' => 'Declaração de transferência',
    'historico' => 'Histórico (PDF rápido)',
    'ficha_matricula' => 'Ficha de matrícula',
];
$badgeDoc = static function (string $st): string {
    return match ($st) {
        'entregue', 'validado', 'Emitido', 'Assinado' => 'bg-green-100 text-green-800',
        'dispensado' => 'bg-slate-100 text-slate-600',
        'rejeitado' => 'bg-red-100 text-red-800',
        default => 'bg-amber-100 text-amber-800',
    };
};
?>
<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex justify-between items-start gap-3 flex-wrap mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Checklist de matrícula</h3>
            <p class="text-sm text-gray-500"><?= (int) ($docsChecklist['entregues'] ?? 0) ?>/<?= (int) ($docsChecklist['total'] ?? 0) ?> entregues. Histórico anexado abaixo também entra nesta lista. Outros itens: gerenciar na ficha do aluno.</p>
        </div>
        <a href="<?= URL ?>/admin/students/<?= $alunoId ?>?tab=documentos" class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium hover:bg-gray-50">Gerenciar na ficha</a>
    </div>
    <ul class="grid grid-cols-1 md:grid-cols-2 gap-2 text-sm">
        <?php foreach ($docsChecklist['itens'] ?? [] as $item): ?>
        <li class="flex items-center justify-between gap-2 border border-gray-100 rounded-lg px-3 py-2">
            <span><?= $esc($item['label'] ?? '') ?></span>
            <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeDoc((string) ($item['status'] ?? '')) ?>"><?= $esc($item['status'] ?? 'pendente') ?></span>
        </li>
        <?php endforeach; ?>
        <?php if (($docsChecklist['itens'] ?? []) === []): ?>
        <li class="text-gray-500">Nenhum item de checklist neste tenant.</li>
        <?php endif; ?>
    </ul>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Recebidos de outra escola</h3>
    <p class="text-sm text-gray-500 mb-4">Anexe o PDF aqui. Depois use <strong>Ler com IA</strong>. Os anos e as notas aparecem na aba Trajetória para conferir e validar — não preenchem sozinhos o histórico oficial.</p>
    <?php if ($aiJobId > 0): ?>
    <div id="historicoIaLoading" class="flex items-center gap-3 text-sm text-indigo-800 bg-indigo-50 border border-indigo-100 rounded-lg px-3 py-2 mb-4">
        <i class="fa-solid fa-spinner fa-spin"></i>
        <span>Lendo o histórico… o rascunho vai para a aba Trajetória.</span>
    </div>
    <div id="historicoIaErro" class="hidden rounded-lg bg-red-50 border border-red-200 text-red-800 text-sm px-4 py-3 mb-4"></div>
    <?php endif; ?>
    <?php if ($docsRecebidos !== []): ?>
        <ul class="text-sm mb-4 space-y-2">
            <?php foreach ($docsRecebidos as $d): ?>
                <li class="flex items-center justify-between gap-3 border border-gray-100 rounded-lg px-3 py-2">
                    <span><?= $esc($d['tipo'] ?? '') ?> · <?= $esc($d['escola_emissora'] ?? '') ?> · <?= $esc($d['arquivo_nome'] ?? 'sem arquivo') ?>
                        <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeDoc((string) ($d['status'] ?? '')) ?>"><?= $esc($d['status'] ?? '') ?></span>
                    </span>
                    <span class="flex items-center gap-3 shrink-0">
                    <?php if (!empty($d['arquivo_key'])): ?>
                    <a class="text-indigo-600 text-sm font-medium" href="<?= $base ?>/documento/<?= (int) $d['id'] ?>/arquivo" target="_blank">Abrir</a>
                    <?php endif; ?>
                    <?php if ($podeLerIa && !empty($d['arquivo_key']) && in_array((string) ($d['tipo'] ?? ''), ['historico', 'ficha_individual', 'declaracao_transferencia'], true)): ?>
                    <form method="post" action="<?= $base ?>/documento/<?= (int) $d['id'] ?>/ler" class="inline">
                        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
                        <button class="text-sm font-medium text-violet-700 hover:underline">Ler com IA</button>
                    </form>
                    <?php endif; ?>
                    </span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <form method="post" action="<?= $base ?>/documento" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-4 gap-3">
        <input type="hidden" name="_token" value="<?= $esc($token) ?>">
        <div>
            <label class="block text-xs text-gray-600 mb-1">Tipo</label>
            <select name="tipo" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-white">
                <option value="historico">Histórico escolar</option>
                <option value="ficha_individual">Ficha individual</option>
                <option value="declaracao_transferencia">Declaração de transferência</option>
                <option value="guia">Guia</option>
                <option value="outro">Outro</option>
            </select>
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">Escola emissora</label>
            <input name="escola_emissora" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm">
        </div>
        <div>
            <label class="block text-xs text-gray-600 mb-1">Arquivo (PDF/imagem)</label>
            <input type="file" name="arquivo" accept=".pdf,.jpg,.jpeg,.png,.webp" class="w-full text-sm">
        </div>
        <div class="flex items-end">
            <button class="px-4 py-2 rounded-lg border border-gray-300 bg-white text-sm font-medium">Anexar</button>
        </div>
    </form>
</div>

<div class="bg-white rounded-xl shadow-lg p-6 mb-6">
    <div class="flex justify-between items-start gap-3 flex-wrap mb-4">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">Histórico escolar oficial</h3>
            <p class="text-sm text-gray-500">Workflow jurídico (rascunho → emissão → assinatura → QR).</p>
        </div>
        <a href="<?= URL . ($links['historico'] ?? '#') ?>" class="btn-primary-custom px-4 py-2 rounded-lg text-sm font-semibold">Abrir histórico</a>
    </div>
    <?php if ($historicos === []): ?>
        <p class="text-sm text-gray-500">Nenhuma versão gerada ainda.</p>
    <?php else: ?>
        <ul class="text-sm space-y-2">
            <?php foreach ($historicos as $h): ?>
            <li class="flex items-center justify-between gap-3 border border-gray-100 rounded-lg px-3 py-2">
                <span>v<?= (int) ($h['versao'] ?? 1) ?> · <?= $esc($h['finalidade'] ?? '') ?> · <span class="text-xs px-2 py-0.5 rounded-full <?= $badgeDoc((string) ($h['status'] ?? '')) ?>"><?= $esc($h['status'] ?? '') ?></span></span>
                <a class="text-indigo-600 font-medium" href="<?= URL . ($links['historico'] ?? '#') ?>/<?= (int) $h['id'] ?>">Ver</a>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<div class="bg-white rounded-xl shadow-lg p-6">
    <h3 class="text-lg font-semibold text-gray-900 mb-1">Declarações já emitidas</h3>
    <p class="text-sm text-gray-500 mb-4">Numeração registrada na secretaria.</p>
    <?php if ($emissoes === []): ?>
        <p class="text-sm text-gray-500">Nenhuma declaração emitida neste prontuário.</p>
    <?php else: ?>
        <ul class="text-sm divide-y divide-gray-100">
            <?php foreach ($emissoes as $em): ?>
            <li class="py-2 flex justify-between gap-3">
                <span><?= $esc($tipoLabels[$em['tipo'] ?? ''] ?? ($em['tipo'] ?? '')) ?> nº <?= (int) ($em['numero'] ?? 0) ?>/<?= (int) ($em['ano'] ?? 0) ?></span>
                <span class="text-gray-500"><?= !empty($em['created_at']) ? $esc(date('d/m/Y H:i', strtotime((string) $em['created_at']))) : '' ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>
<?php if ($aiJobId > 0): ?>
<?php include dirname(__DIR__, 4) . '/Views/components/ai-job-poller.php'; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    new AIJobPoller(<?= $aiJobId ?>, {
        onDone: function () {
            window.location.href = <?= json_encode(isset($hrefAba) && is_callable($hrefAba) ? $hrefAba('trajetoria') : ($base . '?aba=trajetoria')) ?>;
        },
        onFailed: function (msg) {
            var loading = document.getElementById('historicoIaLoading');
            var erro = document.getElementById('historicoIaErro');
            if (loading) loading.classList.add('hidden');
            if (erro) {
                erro.textContent = 'Não foi possível ler o histórico: ' + msg;
                erro.classList.remove('hidden');
            }
        }
    });
});
</script>
<?php endif; ?>
