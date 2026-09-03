<?php
/** Página pública do stand (QR). Variáveis: $stand, $erro, $cancelado, $escola_sistema */
$e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$stand = is_array($stand ?? null) ? $stand : null;
$erro = $erro ?? null;
$cancelado = !empty($cancelado);
$escola = (string) ($escola_sistema ?? 'Expo Colag');
$logoUrl = trim((string) ($logo_url ?? ''));
$avaliacoes = is_array($avaliacoes ?? null) ? $avaliacoes : ['total' => 0, 'media' => null];
$avaliacaoSucesso = !empty($avaliacao_sucesso);
$avaliacaoErro = (string) ($avaliacao_erro ?? '');
$tokenPublico = (string) ($token_publico ?? '');
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
        && (
            preg_match('#^https?://#i', (string) $stand['capa_url'])
            || str_starts_with((string) $stand['capa_url'], '/')
        );
    ?>
    <main class="max-w-3xl mx-auto px-4 py-6 sm:py-10 space-y-5">
        <header class="text-center space-y-3">
            <?php if ($logoUrl !== ''): ?>
                <img src="<?= $e($logoUrl) ?>" alt="<?= $e($escola) ?>" class="mx-auto max-h-16 max-w-48 object-contain">
            <?php endif; ?>
            <div>
                <p class="text-xs uppercase tracking-wide text-slate-500"><?= $e($escola) ?></p>
                <h1 class="text-2xl sm:text-3xl font-bold text-slate-900">Expo Colag</h1>
            </div>
        </header>

        <?php if ($capaOk): ?>
            <div class="max-w-2xl mx-auto rounded-2xl bg-slate-800 overflow-hidden shadow-sm">
                <img src="<?= $e($stand['capa_url']) ?>" alt="" class="w-full aspect-[16/7] object-cover">
            </div>
        <?php endif; ?>

        <article class="max-w-2xl mx-auto rounded-2xl bg-white shadow-lg overflow-hidden">
            <div class="p-6 space-y-4">
                <div class="text-center space-y-3">
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
                    <p class="text-sm text-slate-700 whitespace-pre-line leading-relaxed"><?= $e($stand['resumo']) ?></p>
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

        <section class="max-w-2xl mx-auto rounded-2xl bg-white shadow-sm border border-slate-200 p-6 space-y-4">
            <div class="text-center">
                <h2 class="text-xl font-bold text-slate-900">Avalie este projeto</h2>
                <p class="text-sm text-slate-600 mt-1">Pais e visitantes podem deixar uma nota de 5 a 10 e uma mensagem para a equipe.</p>
                <?php if (!empty($avaliacoes['total'])): ?>
                    <p class="text-xs text-slate-500 mt-2">
                        Média <?= $e(number_format((float) $avaliacoes['media'], 1, ',', '.')) ?> · <?= (int) $avaliacoes['total'] ?> avaliação(ões)
                    </p>
                <?php endif; ?>
            </div>
            <?php if ($avaliacaoSucesso): ?>
                <div class="rounded-lg bg-emerald-50 border border-emerald-100 px-3 py-2 text-sm text-emerald-800 text-center">
                    Avaliação enviada. Obrigado pela participação!
                </div>
            <?php elseif ($avaliacaoErro !== ''): ?>
                <div class="rounded-lg bg-red-50 border border-red-100 px-3 py-2 text-sm text-red-700 text-center">
                    <?= $e($avaliacaoErro) ?>
                </div>
            <?php endif; ?>
            <form method="post" action="<?= defined('URL') ? $e(URL) : '' ?>/expo-colag/s/<?= $e($tokenPublico) ?>/avaliar" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-700 mb-2 text-center">Nota</label>
                    <div class="flex flex-wrap justify-center gap-2">
                        <?php for ($nota = 5; $nota <= 10; $nota++): ?>
                            <label class="cursor-pointer">
                                <input type="radio" name="nota" value="<?= $nota ?>" class="peer sr-only" <?= $nota === 10 ? 'required' : '' ?>>
                                <span class="inline-flex w-10 h-10 items-center justify-center rounded-full border border-slate-300 bg-white text-sm font-semibold text-slate-700 peer-checked:bg-blue-600 peer-checked:text-white peer-checked:border-blue-600 hover:border-blue-400">
                                    <?= $nota ?>
                                </span>
                            </label>
                        <?php endfor; ?>
                    </div>
                </div>
                <div>
                    <label for="mensagem" class="block text-sm font-medium text-slate-700 mb-1">Mensagem</label>
                    <textarea id="mensagem" name="mensagem" rows="3" maxlength="500"
                              class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                              placeholder="Escreva um comentário para os alunos..."></textarea>
                </div>
                <button type="submit" class="w-full rounded-lg bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                    Enviar avaliação
                </button>
            </form>
        </section>
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
