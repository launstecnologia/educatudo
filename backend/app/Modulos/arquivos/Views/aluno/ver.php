<div class="mb-6">
    <?php
    $modo_recuperacao = !empty($modo_recuperacao) || !empty($pub['recuperacao']);
    $voltarUrl = $voltar_url ?? (URL . ($modo_recuperacao ? '/aluno/recuperacao' : '/aluno/arquivos'));
    $voltarLabel = $voltar_label ?? ($modo_recuperacao ? '← Voltar à recuperação' : '← Voltar aos arquivos');
    $url_visualizar_base = $url_visualizar_base ?? (URL . '/aluno/arquivos/visualizar');
    ?>
    <a href="<?= $voltarUrl ?>" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium mb-2 inline-block"><?= htmlspecialchars($voltarLabel) ?></a>
    <h1 class="text-2xl font-bold text-gray-900"><?= htmlspecialchars($pub['titulo']) ?></h1>
    <p class="text-gray-500 mt-1">
        <?php if (!empty($pub['materia_nome'])): ?><?= htmlspecialchars($pub['materia_nome']) ?><?php endif; ?>
        <?php if (!empty($pub['professor_nome'])): ?><?= !empty($pub['materia_nome']) ? ' · ' : '' ?>Professor: <?= htmlspecialchars($pub['professor_nome']) ?><?php endif; ?>
    </p>
    <?php if (!empty($pub['descricao'])): ?>
        <?php
        $descDisplay = class_exists(\App\Utils\HtmlSanitizer::class)
            ? \App\Utils\HtmlSanitizer::displaySafe((string)$pub['descricao'])
            : nl2br(htmlspecialchars((string)$pub['descricao'], ENT_QUOTES, 'UTF-8'));
        ?>
        <div class="text-gray-600 mt-2 prose prose-sm max-w-none"><?= $descDisplay ?></div>
    <?php endif; ?>
</div>

<?php
$temAnexos = !empty($anexos);
$temVideos = !empty($videos);
?>
<?php if ($temVideos): ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 p-6 mb-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Vídeos</h2>
        <div class="space-y-6">
            <?php foreach ($videos as $v): ?>
                <?php $embedUrl = $v['embed_url'] ?? $v['url']; ?>
                <div class="rounded-lg overflow-hidden bg-black/5">
                    <div class="aspect-video w-full max-w-3xl">
                        <iframe src="<?= htmlspecialchars($embedUrl) ?>" class="w-full h-full" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen title="<?= !empty($v['titulo']) ? htmlspecialchars($v['titulo']) : 'Vídeo' ?>"></iframe>
                    </div>
                    <?php if (!empty($v['titulo'])): ?>
                        <p class="px-3 py-2 text-sm text-gray-600"><?= htmlspecialchars($v['titulo']) ?></p>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if (!$temAnexos && !$temVideos): ?>
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">
        Nenhum anexo nem vídeo nesta publicação.
    </div>
<?php elseif ($temAnexos): ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 p-6">
        <h2 class="text-lg font-semibold text-gray-900 mb-4">Anexos</h2>
        <ul class="space-y-2">
            <?php foreach ($anexos as $a): ?>
                <li class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
                    <span class="text-gray-700 truncate"><?= htmlspecialchars($a['nome_original']) ?></span>
                    <a href="<?= htmlspecialchars($url_visualizar_base) ?>/<?= (int)$a['id'] ?>?download=1" class="shrink-0 ml-3 bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700 text-sm font-medium">
                        Baixar
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <p class="text-xs text-gray-500 mt-4">Todos os anexos estão com download liberado (PDF, Word, Excel, PowerPoint, imagens, etc.).</p>
    </div>
<?php endif; ?>
