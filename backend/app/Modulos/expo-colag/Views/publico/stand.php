<?php
/** Página pública do stand (QR). Variáveis: $stand, $erro, $cancelado, $escola_sistema */
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$stand = is_array($stand ?? null) ? $stand : null;
$erro = $erro ?? null;
$cancelado = !empty($cancelado);
$escola = (string) ($escola_sistema ?? 'Expo Colag');
$titulo = $stand['titulo'] ?? ($cancelado ? 'Stand indisponível' : 'Stand não encontrado');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex">
<title><?= $e($titulo) ?> · <?= $e($escola) ?></title>
<?php include __DIR__ . '/../../../../Views/layouts/components/estilos_plataforma.php'; ?>
</head>
<body class="bg-slate-100 min-h-screen">
<?php if ($stand): ?>
    <?php
    $capaOk = !empty($stand['capa_url'])
        && preg_match('#^https?://#i', (string) $stand['capa_url']);
    ?>
    <?php if ($capaOk): ?>
        <div class="w-full h-48 sm:h-64 bg-slate-800 overflow-hidden">
            <img src="<?= $e($stand['capa_url']) ?>" alt="" class="w-full h-full object-cover">
        </div>
    <?php else: ?>
        <div class="w-full h-32 bg-gradient-to-br from-slate-700 to-slate-900"></div>
    <?php endif; ?>
    <main class="max-w-lg mx-auto px-4 -mt-8 pb-12">
        <article class="rounded-2xl bg-white shadow-lg overflow-hidden">
            <div class="p-6 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <?php if (!empty($stand['numero']) || !empty($stand['setor'])): ?>
                            <p class="text-xs uppercase tracking-wide text-slate-500">
                                <?= $e(trim(($stand['setor'] ?? '') . ' · Stand ' . ($stand['numero'] ?? ''), ' ·')) ?>
                            </p>
                        <?php endif; ?>
                        <h1 class="text-2xl font-bold text-slate-900 mt-1"><?= $e($stand['titulo'] ?? '') ?></h1>
                        <?php if (!empty($stand['subtitulo'])): ?>
                            <p class="text-slate-600"><?= $e($stand['subtitulo']) ?></p>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($stand['area'])): ?>
                        <span class="inline-flex px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700"><?= $e($stand['area']) ?></span>
                    <?php endif; ?>
                </div>

                <?php if (!empty($stand['resumo'])): ?>
                    <p class="text-sm text-slate-700 whitespace-pre-line"><?= $e($stand['resumo']) ?></p>
                <?php endif; ?>

                <?php if (!empty($stand['produto']) && ($stand['produto'] ?? '') !== ($stand['resumo'] ?? '')): ?>
                    <div>
                        <h2 class="text-xs uppercase tracking-wide text-slate-500">Produto</h2>
                        <p class="text-sm text-slate-800 mt-1"><?= $e($stand['produto']) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($stand['participantes'])): ?>
                    <div>
                        <h2 class="text-xs uppercase tracking-wide text-slate-500">Equipe</h2>
                        <p class="text-sm text-slate-800 mt-1"><?= $e(implode(', ', $stand['participantes'])) ?></p>
                    </div>
                <?php endif; ?>

                <?php if (!empty($stand['professor_nome'])): ?>
                    <p class="text-xs text-slate-500">Orientação: <?= $e($stand['professor_nome']) ?></p>
                <?php endif; ?>

                <?php if (!empty($stand['horario_apresentacao'])): ?>
                    <p class="text-sm text-slate-700">
                        Apresentação: <?= $e(date('d/m/Y H:i', strtotime($stand['horario_apresentacao']))) ?>
                    </p>
                <?php endif; ?>
            </div>
            <div class="px-6 py-3 bg-slate-50 text-xs text-slate-500 border-t border-slate-100">
                <?= $e($escola) ?> · Expo Colag
            </div>
        </article>
    </main>
<?php else: ?>
    <main class="min-h-screen flex items-center justify-center p-4">
        <div class="w-full max-w-md bg-white rounded-2xl shadow-lg overflow-hidden text-center">
            <div class="<?= $cancelado ? 'bg-amber-600' : 'bg-slate-700' ?> px-6 py-8 text-white">
                <h1 class="text-xl font-bold"><?= $e($erro ?: 'Stand não encontrado') ?></h1>
            </div>
            <p class="p-6 text-sm text-slate-600"><?= $e($escola) ?></p>
        </div>
    </main>
<?php endif; ?>
</body>
</html>
