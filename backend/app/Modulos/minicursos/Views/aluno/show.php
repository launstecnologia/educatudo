<?php
$minicurso = $minicurso ?? [];
$modulos = $modulos ?? [];
$arquivos = $arquivos ?? [];
$aulas_vistas = $aulas_vistas ?? [];
$concluido = $concluido ?? false;
$id = (int)($minicurso['id'] ?? 0);
$tiposLabel = ['video' => 'Vídeo', 'slides' => 'Slides', 'pdf' => 'PDF', 'link' => 'Link', 'texto' => 'Texto'];
$capa_url = !empty($minicurso['imagem_caminho']) ? (rtrim(URL, '/') . '/' . ltrim($minicurso['imagem_caminho'], '/')) : ($minicurso['imagem_url'] ?? '');
?>
<div class="mb-6">
    <a href="<?= URL ?>/minicursos" class="text-indigo-600 hover:underline text-sm">← Mini Cursos</a>
    <?php if ($capa_url): ?>
        <div class="mt-2 mb-3 rounded-lg overflow-hidden max-h-48 bg-gray-100">
            <img src="<?= htmlspecialchars($capa_url) ?>" alt="" class="w-full object-cover max-h-48">
        </div>
    <?php endif; ?>
    <h1 class="text-2xl font-bold text-gray-900 mt-2"><?= htmlspecialchars($minicurso['titulo']) ?></h1>
    <?php if (!empty($minicurso['descricao'])): ?>
        <p class="text-gray-600 mt-1"><?= nl2br(htmlspecialchars($minicurso['descricao'])) ?></p>
    <?php endif; ?>
    <div class="mt-3 flex items-center gap-4 flex-wrap">
        <span class="text-sm text-gray-500"><?= count($aulas_vistas) ?> / <?= count($todas_aulas ?? []) ?> aulas</span>
        <?php if ($concluido): ?>
            <span class="text-sm font-medium text-green-600">Concluído</span>
        <?php endif; ?>
    </div>
    <?php if (!empty($arquivos)): ?>
        <div class="mt-4 p-3 bg-gray-50 rounded-lg border border-gray-200">
            <p class="text-sm font-medium text-gray-700 mb-2">Arquivos do minicurso</p>
            <ul class="space-y-1">
                <?php foreach ($arquivos as $arq): ?>
                    <li>
                        <?php if ($arq['tipo'] === 'link' && !empty($arq['url'])): ?>
                            <a href="<?= htmlspecialchars($arq['url']) ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline text-sm"><?= htmlspecialchars($arq['label']) ?></a>
                        <?php elseif ($arq['tipo'] === 'upload' && !empty($arq['caminho'])): ?>
                            <a href="<?= htmlspecialchars(rtrim(URL, '/') . '/' . ltrim($arq['caminho'], '/')) ?>" target="_blank" rel="noopener noreferrer" class="text-indigo-600 hover:underline text-sm"><?= htmlspecialchars($arq['label']) ?></a>
                        <?php else: ?>
                            <span class="text-gray-600 text-sm"><?= htmlspecialchars($arq['label']) ?></span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
</div>

<?php if (empty($modulos)): ?>
    <div class="bg-white rounded-xl shadow p-6 text-center text-gray-500">Nenhum módulo neste minicurso.</div>
<?php else: ?>
    <?php foreach ($modulos as $mod): ?>
        <div class="bg-white rounded-xl shadow border border-gray-200 mb-4 overflow-hidden">
            <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
                <h2 class="font-semibold text-gray-900"><?= htmlspecialchars($mod['titulo']) ?></h2>
            </div>
            <ul class="divide-y divide-gray-200">
                <?php foreach ($mod['aulas'] as $aula): ?>
                    <?php $vista = in_array($aula['id'], $aulas_vistas); ?>
                    <li class="flex items-center justify-between px-4 py-3">
                        <div class="flex items-center gap-3">
                            <?php if ($vista): ?>
                                <span class="text-green-600" title="Assistida">✓</span>
                            <?php endif; ?>
                            <span class="text-sm text-gray-600"><?= $tiposLabel[$aula['tipo']] ?? $aula['tipo'] ?></span>
                            <span class="font-medium text-gray-900"><?= htmlspecialchars($aula['titulo']) ?></span>
                        </div>
                        <a href="<?= URL ?>/minicursos/aula/<?= (int)$aula['id'] ?>" class="text-sm bg-indigo-600 text-white px-3 py-1.5 rounded-lg hover:bg-indigo-700">
                            <?= $vista ? 'Assistir novamente' : 'Assistir' ?>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endforeach; ?>
<?php endif; ?>
