<?php
$aula = $aula ?? [];
$url_exibir = $url_exibir ?? '';
$tipo = $aula['tipo'] ?? 'link';
$link_nome = trim($aula['link_nome'] ?? '') ?: 'Abrir link';
// Conteúdo HTML da aula: exibe com formatação (pode vir junto com arquivo/link)
$conteudo_html = $aula['conteudo_html'] ?? '';
$allowed_tags = '<p><br><strong><b><em><i><u><a><ul><ol><li><h1><h2><h3><h4><blockquote><span><div>';
if ($conteudo_html !== '') {
    $conteudo_html = strip_tags($conteudo_html, $allowed_tags);
}
$tem_conteudo = $conteudo_html !== '';
$tem_material = $url_exibir !== '';
?>
<div class="mb-4">
    <a href="<?= URL ?>/minicursos/<?= (int)$aula['minicurso_id'] ?>" class="text-indigo-600 hover:underline text-sm">← Voltar ao minicurso</a>
</div>
<div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
    <div class="px-4 py-3 border-b border-gray-200 bg-gray-50">
        <h1 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($aula['titulo']) ?></h1>
        <p class="text-sm text-gray-500"><?= htmlspecialchars($aula['modulo_titulo'] ?? '') ?> • <?= htmlspecialchars($aula['minicurso_titulo'] ?? '') ?></p>
    </div>
    <div class="p-4 min-h-[50vh] space-y-6">
        <?php if ($tem_conteudo): ?>
            <section class="minicurso-conteudo-aula">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Conteúdo da aula</h2>
                <p class="text-xs text-gray-500 mb-2">Leia o conteúdo abaixo. Você também pode ter um arquivo ou link desta aula logo em seguida.</p>
                <div class="prose prose-indigo max-w-none minicurso-conteudo-html rounded-lg bg-gray-50/50 p-4 border border-gray-100">
                    <?= $conteudo_html ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($tem_material): ?>
            <section class="minicurso-material-aula">
                <h2 class="text-sm font-semibold text-gray-700 mb-2">Material da aula</h2>
                <?php if ($tipo === 'video'): ?>
                    <div class="aspect-video max-w-4xl mx-auto rounded-lg overflow-hidden border border-gray-200">
                        <iframe src="<?= htmlspecialchars($url_exibir) ?>" class="w-full h-full" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" title="<?= htmlspecialchars($aula['titulo']) ?>"></iframe>
                    </div>
                <?php elseif ($tipo === 'slides'): ?>
                    <div class="aspect-video max-w-4xl mx-auto rounded-lg overflow-hidden border border-gray-200">
                        <iframe src="<?= htmlspecialchars($url_exibir) ?>" class="w-full h-full" allowfullscreen title="<?= htmlspecialchars($aula['titulo']) ?>"></iframe>
                    </div>
                <?php elseif ($tipo === 'pdf'): ?>
                    <p class="text-xs text-gray-500 mb-2">Documento exibido na plataforma (visualização na página).</p>
                    <div class="rounded-lg overflow-hidden border border-gray-200 bg-gray-100" style="height: 70vh;">
                        <iframe src="<?= htmlspecialchars($url_exibir) ?>#toolbar=0" class="w-full h-full border-0" title="<?= htmlspecialchars($aula['titulo']) ?>"></iframe>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600 mb-3">Acesse o link abaixo:</p>
                    <a href="<?= htmlspecialchars($url_exibir) ?>" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-2 bg-indigo-600 text-white px-6 py-3 rounded-lg hover:bg-indigo-700 font-medium shadow-sm">
                        <?= htmlspecialchars($link_nome) ?>
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/></svg>
                    </a>
                <?php endif; ?>
            </section>
        <?php endif; ?>

        <?php if (!$tem_conteudo && !$tem_material): ?>
            <p class="text-gray-500">Nenhum conteúdo nesta aula.</p>
        <?php endif; ?>
    </div>
</div>
