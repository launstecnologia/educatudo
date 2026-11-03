<?php
/** Página pública de validação da carteirinha. Variáveis: $info, $escola */
$info = is_array($info ?? null) ? $info : [];
$escola = (string) ($escola ?? 'EducaTudo');
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$uso = !empty($info['uso']);
$encontrado = !empty($info['encontrado']);
$valido = !empty($info['valido']);
$motivo = (string) ($info['motivo'] ?? '');
if (!$uso && $motivo === 'indisponivel') {
    $titulo = 'Validação indisponível';
    $cor = '#b91c1c';
} elseif (!$uso) {
    $titulo = 'Esta escola não usa carteirinha';
    $cor = '#b45309';
} elseif (!$encontrado) {
    $titulo = 'Carteirinha não encontrada';
    $cor = '#b91c1c';
} elseif ($valido) {
    $titulo = 'Carteirinha válida';
    $cor = '#047857';
} elseif ($motivo === 'vencida') {
    $titulo = 'Carteirinha vencida';
    $cor = '#b45309';
} elseif ($motivo === 'inativa') {
    $titulo = 'Aluno inativo';
    $cor = '#b45309';
} else {
    $titulo = 'Carteirinha cancelada';
    $cor = '#b91c1c';
}
$foto = (string) ($info['foto'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Validação de carteirinha · <?= $e($escola) ?></title>
<?php include dirname(__DIR__, 4) . '/Views/layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="px-6 py-8 text-center text-white" style="background:<?= $e($cor) ?>">
            <h1 class="text-xl font-bold"><?= $e($titulo) ?></h1>
            <p class="text-sm opacity-90 mt-1"><?= $e($info['escola'] ?? $escola) ?></p>
        </div>
        <div class="p-6">
            <?php if ($encontrado): ?>
                <div class="flex items-center gap-4 mb-4">
                    <div class="w-16 h-20 rounded-lg bg-gray-100 overflow-hidden flex items-center justify-center flex-shrink-0">
                        <?php if ($foto !== ''): ?>
                            <img src="<?= $e($foto) ?>" alt="" class="w-full h-full object-cover">
                        <?php else: ?>
                            <span class="text-xs text-gray-400">Sem foto</span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <p class="font-semibold text-gray-900"><?= $e($info['aluno_nome'] ?? '') ?></p>
                        <p class="text-sm text-gray-500"><?= $e($info['turma'] ?? '') ?></p>
                    </div>
                </div>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">RA</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $e($info['ra'] ?? '') ?></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Válida até</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $e($info['validade'] ?? '') ?></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Código</dt>
                        <dd class="font-mono text-xs text-gray-700 text-right"><?= $e($info['codigo'] ?? '') ?></dd>
                    </div>
                </dl>
            <?php elseif (!$uso): ?>
                <p class="text-gray-600 text-center">A validação online só responde enquanto a escola mantém a carteirinha ligada.</p>
            <?php else: ?>
                <p class="text-gray-600 text-center">Não localizamos uma carteirinha com este código.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
