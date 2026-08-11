<?php
$entrega = $entrega ?? [];
$arquivos = $arquivos ?? [];
$rubrica = $rubrica ?? null;
$resultadoAnterior = $resultado_anterior ?? [];
$csrf = htmlspecialchars((string) ($csrf_token ?? ''));
$flash_status = (string) ($flash_type ?? '');
$base = rtrim((string) ($base_url ?? '/professor/ava'), '/');
$entregaId = (int) ($entrega['id'] ?? 0);
$atividadeId = (int) ($entrega['atividade_id'] ?? 0);
$notaMax = (float) ($entrega['nota_maxima'] ?? 10);
require_once __DIR__ . '/../../components/wysiwyg.php';
require_once __DIR__ . '/../../../Helpers/RichTextHelper.php';
?>

<div class="mb-6">
    <div class="flex items-center gap-4">
        <a href="<?= URL . $base ?>/atividades/<?= $atividadeId ?>/entregas" class="inline-flex items-center justify-center w-10 h-10 rounded-lg border border-gray-200 bg-white text-gray-500 hover:bg-gray-50 hover:text-gray-700" aria-label="Voltar"><i class="fa-solid fa-arrow-left"></i></a>
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-1">Corrigir entrega</h2>
            <p class="text-sm text-gray-600"><?= htmlspecialchars((string) ($entrega['aluno_nome'] ?? 'Aluno')) ?> · <?= htmlspecialchars((string) ($entrega['atividade_titulo'] ?? '')) ?></p>
        </div>
    </div>
</div>

<?php if ($flash_status !== '' && !empty($flash_message)):
    $cls = $flash_status === 'success' ? 'bg-green-100 text-green-800 border-green-200' : ($flash_status === 'error' ? 'bg-red-100 text-red-800 border-red-200' : 'bg-blue-100 text-blue-800 border-blue-200'); ?>
    <div class="mb-6 p-4 rounded-lg border <?= $cls ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Conteúdo enviado -->
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
            <h3 class="text-lg font-semibold text-gray-900 mb-4">Conteúdo enviado</h3>
            <?php if (!empty($entrega['texto'])): ?>
                <div class="text-sm mb-4"><?php rich_text((string) $entrega['texto']); ?></div>
            <?php endif; ?>
            <?php if (!empty($entrega['link'])): ?>
                <p class="mb-4 text-sm"><i class="fa-solid fa-link text-gray-400 mr-2"></i><a href="<?= htmlspecialchars((string) $entrega['link']) ?>" target="_blank" rel="noopener" class="text-green-700 hover:underline break-all"><?= htmlspecialchars((string) $entrega['link']) ?></a></p>
            <?php endif; ?>
            <?php if (empty($entrega['texto']) && empty($entrega['link']) && empty($arquivos)): ?>
                <p class="text-sm text-gray-500">O aluno ainda não enviou conteúdo.</p>
            <?php endif; ?>
            <?php if (!empty($arquivos)): ?>
                <ul class="divide-y divide-gray-100">
                    <?php foreach ($arquivos as $f): ?>
                    <li class="py-2 flex items-center justify-between">
                        <span class="text-sm text-gray-700"><i class="fa-solid fa-paperclip text-gray-400 mr-2"></i><?= htmlspecialchars((string) ($f['nome'] ?? 'Arquivo')) ?></span>
                        <a href="<?= URL . $base ?>/entregas/arquivo/<?= (int) $f['id'] ?>" class="text-green-700 hover:underline text-sm"><i class="fa-solid fa-download mr-1"></i> Baixar</a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Avaliação -->
    <div class="lg:col-span-1">
        <form method="post" action="<?= URL . $base ?>/entregas/<?= $entregaId ?>/corrigir" class="bg-white rounded-xl shadow-sm border border-gray-200 p-6 space-y-4">
            <input type="hidden" name="_token" value="<?= $csrf ?>">
            <h3 class="text-lg font-semibold text-gray-900">Avaliação</h3>

            <?php if ($rubrica && !empty($rubrica['criterios'])): ?>
                <p class="text-xs text-gray-500">Rubrica: <?= htmlspecialchars((string) $rubrica['titulo']) ?>. A nota final é calculada automaticamente (peso × pontuação).</p>
                <?php foreach ($rubrica['criterios'] as $c): $cid = (int) $c['id']; $ant = $resultadoAnterior[$cid]['pontuacao'] ?? ''; ?>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1"><?= htmlspecialchars((string) $c['titulo']) ?> <span class="text-xs text-gray-400">(máx <?= number_format((float) $c['pontuacao_max'], 2, ',', '') ?> · peso <?= number_format((float) $c['peso'], 2, ',', '') ?>)</span></label>
                    <?php if (!empty($c['descricao'])): ?><p class="text-xs text-gray-400 mb-1"><?= htmlspecialchars((string) $c['descricao']) ?></p><?php endif; ?>
                    <input type="number" step="0.01" min="0" max="<?= htmlspecialchars((string) $c['pontuacao_max']) ?>" name="criterio[<?= $cid ?>]" value="<?= htmlspecialchars((string) $ant) ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div>
                    <label for="nota" class="block text-sm font-medium text-gray-700 mb-1">Nota (máx <?= number_format($notaMax, 2, ',', '') ?>)</label>
                    <input type="number" step="0.01" min="0" max="<?= htmlspecialchars((string) $notaMax) ?>" id="nota" name="nota" value="<?= $entrega['nota'] !== null ? htmlspecialchars((string) $entrega['nota']) : '' ?>" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                </div>
            <?php endif; ?>

            <div><?php wysiwyg_field(['name' => 'feedback', 'label' => 'Feedback', 'value' => $entrega['feedback'] ?? '', 'rows' => 5, 'placeholder' => 'Escreva o feedback para o aluno...']); ?></div>

            <label class="flex items-start gap-3">
                <input type="checkbox" name="reenviar" value="1" class="mt-0.5 rounded border-gray-300 text-amber-600 focus:ring focus:ring-amber-200">
                <span class="text-sm text-gray-700">Devolver para o aluno reenviar (em vez de finalizar)</span>
            </label>

            <button type="submit" class="w-full inline-flex items-center justify-center px-4 py-2.5 bg-primary text-primary rounded-lg text-sm font-semibold hover:opacity-90 transition-colors"><i class="fa-solid fa-check mr-2"></i> Salvar avaliação</button>
        </form>
    </div>
</div>
