<?php
/** Página pública de validação do Histórico Escolar. Variáveis: $hash, $info, $escola_sistema */
$hash = (string) ($hash ?? '');
$info = is_array($info ?? null) ? $info : [];
$encontrado = !empty($info['encontrado']);
$valido = !empty($info['valido']);
$escolaSistema = (string) ($escola_sistema ?? 'EducaTudo');
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$status = (string) ($info['status'] ?? '');
$emitido = '';
if (!empty($info['emitido_em'])) {
    $ts = strtotime((string) $info['emitido_em']);
    $emitido = $ts ? date('d/m/Y H:i', $ts) : '';
}
$tituloStatus = !$encontrado
    ? 'Histórico não encontrado'
    : ($valido ? 'Histórico válido' : ($status === 'Cancelado' ? 'Histórico cancelado / substituído' : 'Histórico sem validade'));
$cor = !$encontrado || (!$valido && $status !== 'Cancelado') ? 'bg-red-600' : ($valido ? 'bg-green-600' : 'bg-amber-600');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Validação de Histórico Escolar · <?= $e($escolaSistema) ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="<?= $cor ?> px-6 py-8 text-center text-white">
            <div class="text-4xl mb-2"><?= $valido ? '&#10004;' : ($status === 'Cancelado' ? '!' : '&#10006;') ?></div>
            <h1 class="text-xl font-bold"><?= $e($tituloStatus) ?></h1>
            <p class="text-sm opacity-90 mt-1"><?= $e($info['escola'] ?? $escolaSistema) ?></p>
        </div>
        <div class="p-6">
            <?php if ($encontrado): ?>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Aluno(a)</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $e($info['aluno_nome'] ?? '') ?></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Status</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $e($status) ?></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Versão</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= (int) ($info['versao'] ?? 1) ?></dd>
                    </div>
                    <?php if ($emitido !== ''): ?>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Emitido em</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $e($emitido) ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Código</dt>
                        <dd class="font-mono text-xs text-green-700 text-right break-all"><?= $e($info['hash'] ?? $hash) ?></dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-gray-400 text-center">
                    Esta página confirma apenas a autenticidade do documento. Notas e detalhes pedagógicos não são exibidos publicamente.
                </p>
            <?php else: ?>
                <p class="text-gray-600 text-center">Não localizamos histórico com o código informado<?php if ($hash !== ''): ?> (<span class="font-mono text-xs"><?= $e(substr($hash, 0, 24)) ?>…</span>)<?php endif; ?>.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
