<div class="mb-4">
    <a href="<?= URL ?>/aluno/arquivos" class="text-indigo-600 hover:text-indigo-800 text-sm font-medium">← Voltar aos arquivos</a>
</div>

<div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden">
    <div class="px-4 py-2 border-b border-gray-200 bg-gray-50">
        <h1 class="text-lg font-semibold text-gray-900 truncate"><?= htmlspecialchars($anexo['nome_original']) ?></h1>
    </div>
    <div class="p-4 min-h-[60vh] flex items-center justify-center bg-gray-100">
        <?php if ($pode_embed): ?>
            <?php if (!empty($eh_video)): ?>
                <video controls class="w-full max-h-[75vh] rounded-lg" src="<?= htmlspecialchars($url_visualizar) ?>" title="<?= htmlspecialchars($anexo['nome_original']) ?>">
                    Seu navegador não suporta reprodução de vídeo. <a href="<?= htmlspecialchars($url_visualizar) ?>">Abrir em nova aba</a>.
                </video>
            <?php elseif (strtolower((string)$anexo['extensao']) === 'pdf'): ?>
                <iframe src="<?= htmlspecialchars($url_visualizar) ?>#toolbar=0" class="w-full border-0 rounded-lg" style="height: 75vh;" title="<?= htmlspecialchars($anexo['nome_original']) ?>"></iframe>
            <?php else: ?>
                <img src="<?= htmlspecialchars($url_visualizar) ?>" alt="<?= htmlspecialchars($anexo['nome_original']) ?>" class="max-w-full max-h-[75vh] object-contain rounded-lg">
            <?php endif; ?>
        <?php elseif (!empty($eh_office) && !empty($url_embed_public)): ?>
            <div class="w-full flex flex-col items-center" style="height: 75vh;">
                <iframe src="https://docs.google.com/viewer?url=<?= htmlspecialchars(urlencode($url_embed_public)) ?>&embedded=true" class="w-full border-0 rounded-lg flex-1" style="min-height: 70vh;" title="<?= htmlspecialchars($anexo['nome_original']) ?>"></iframe>
                <p class="text-sm text-gray-500 mt-3">Se a apresentação não carregar acima, <a href="<?= htmlspecialchars($url_download) ?>" class="text-indigo-600 hover:underline" download>baixe o arquivo</a> para abrir no PowerPoint.</p>
            </div>
        <?php else: ?>
            <div class="text-center max-w-md">
                <p class="text-gray-600 mb-4">Visualização incorporada não disponível para este tipo de arquivo.</p>
                <p class="text-sm text-gray-500 mb-4">Para manter o acesso dentro do EducaTudo sem download, apenas PDF e imagens são exibidos nesta tela.</p>
                <a href="<?= htmlspecialchars($url_download ?? $url_visualizar . '?download=1') ?>" class="inline-flex items-center px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700" download>Baixar arquivo</a>
            </div>
        <?php endif; ?>
    </div>
</div>
