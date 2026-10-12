<?php
/** Página pública de validação de certificado. Variáveis: $codigo, $valido, $cert, $disciplina_nome, $escola */
$codigo = (string) ($codigo ?? '');
$valido = !empty($valido);
$cert = $cert ?? [];
$escola = (string) ($escola ?? 'EducaTudo');
$e = static fn($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$emitido = $valido && !empty($cert['emitido_em']) ? date('d/m/Y', strtotime((string) $cert['emitido_em'])) : '';
$carga = (int) ($cert['carga_horaria'] ?? 0);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title>Validação de certificado · <?= $e($escola) ?></title>
<?php include __DIR__ . '/../layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-lg bg-white rounded-2xl shadow-lg overflow-hidden">
        <div class="<?= $valido ? 'bg-green-600' : 'bg-red-600' ?> px-6 py-8 text-center text-white">
            <div class="text-4xl mb-2"><?= $valido ? '&#10004;' : '&#10006;' ?></div>
            <h1 class="text-xl font-bold"><?= $valido ? 'Certificado válido' : 'Certificado não encontrado' ?></h1>
            <p class="text-sm opacity-90 mt-1"><?= $e($escola) ?></p>
        </div>
        <div class="p-6">
            <?php if ($valido): ?>
                <dl class="divide-y divide-gray-100 text-sm">
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Aluno(a)</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $e($cert['aluno_nome'] ?? '') ?></dd>
                    </div>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Disciplina</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $e($disciplina_nome ?? ($cert['titulo'] ?? '')) ?></dd>
                    </div>
                    <?php if ($carga > 0): ?>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Carga horária</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $carga ?> hora(s)</dd>
                    </div>
                    <?php endif; ?>
                    <?php if ($emitido !== ''): ?>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Emitido em</dt>
                        <dd class="font-semibold text-gray-900 text-right"><?= $e($emitido) ?></dd>
                    </div>
                    <?php endif; ?>
                    <div class="py-3 flex justify-between gap-4">
                        <dt class="text-gray-500">Código</dt>
                        <dd class="font-mono text-green-700 text-right"><?= $e($codigo) ?></dd>
                    </div>
                </dl>
                <p class="mt-4 text-xs text-gray-400 text-center">Este certificado foi emitido eletronicamente e é autêntico.</p>
            <?php else: ?>
                <p class="text-gray-600 text-center">Não localizamos nenhum certificado com o código informado<?php if ($codigo !== ''): ?> (<span class="font-mono"><?= $e($codigo) ?></span>)<?php endif; ?>. Verifique o código e tente novamente.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
