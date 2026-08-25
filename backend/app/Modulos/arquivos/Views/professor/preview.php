<?php
$pub = $pub ?? [];
$anexos = $anexos ?? [];
$videos = $videos ?? [];
$url_base = $url_base ?? rtrim(URL ?? '', '/');
$iframe = $iframe ?? false;
$temAnexos = !empty($anexos);
$temVideos = !empty($videos);
$descricao = trim((string)($pub['descricao'] ?? ''));
$descricaoHtml = '';
if ($descricao !== '') {
    $descricaoHtml = class_exists(\App\Utils\HtmlSanitizer::class)
        ? \App\Utils\HtmlSanitizer::displaySafe($descricao)
        : nl2br(htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'));
}
if ($iframe) {
    if (!class_exists('EstilosPlataforma', false)) {
        require_once dirname(__DIR__, 4) . '/Helpers/EstilosPlataforma.php';
    }
    echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo EstilosPlataforma::tagLink();
    echo '<style>.prose img{max-width:100%;height:auto;}</style></head><body class="bg-gray-100">';
}
?>
<div class="p-4 bg-white rounded-lg">
    <p class="text-xs text-indigo-600 font-medium mb-3">👁 Visualização do aluno</p>
    <h1 class="text-xl font-bold text-gray-900"><?= htmlspecialchars($pub['titulo'] ?? '') ?></h1>
    <p class="text-gray-500 text-sm mt-1">
        <?php if (!empty($pub['materia_nome'])): ?><?= htmlspecialchars($pub['materia_nome']) ?><?php endif; ?>
        <?php if (!empty($pub['professor_nome'])): ?><?= !empty($pub['materia_nome']) ? ' · ' : '' ?>Professor: <?= htmlspecialchars($pub['professor_nome']) ?><?php endif; ?>
    </p>
    <?php if ($descricaoHtml !== ''): ?>
        <div class="text-gray-600 mt-2 prose prose-sm max-w-none"><?= $descricaoHtml ?></div>
    <?php endif; ?>

    <?php if ($temVideos): ?>
        <div class="mt-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">Vídeos</h2>
            <div class="space-y-3">
                <?php foreach ($videos as $v): ?>
                    <?php $embedUrl = $v['embed_url'] ?? $v['url'] ?? ''; ?>
                    <div class="rounded-lg overflow-hidden bg-black/5">
                        <div class="aspect-video w-full max-w-xl">
                            <iframe src="<?= htmlspecialchars($embedUrl) ?>" class="w-full h-full" allowfullscreen title="<?= !empty($v['titulo']) ? htmlspecialchars($v['titulo']) : 'Vídeo' ?>"></iframe>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!$temAnexos && !$temVideos): ?>
        <p class="mt-4 text-gray-500 text-sm">Nenhum anexo nem vídeo nesta publicação.</p>
    <?php elseif ($temAnexos): ?>
        <div class="mt-4">
            <h2 class="text-sm font-semibold text-gray-900 mb-2">Anexos</h2>
            <ul class="space-y-1.5">
                <?php foreach ($anexos as $a): ?>
                    <li class="flex items-center justify-between py-1.5 border-b border-gray-100 last:border-0 text-sm">
                        <span class="text-gray-700 truncate"><?= htmlspecialchars($a['nome_original']) ?></span>
                        <a href="<?= $url_base ?>/professor/arquivos/ver-anexo/<?= (int)$a['id'] ?>" target="_blank" rel="noopener" class="shrink-0 ml-2 bg-indigo-600 text-white px-2 py-1 rounded text-xs hover:bg-indigo-700">Abrir</a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>
<?php if ($iframe) { echo '</body></html>'; } ?>
