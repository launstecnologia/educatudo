<?php
$lista = $lista ?? [];
?>
<div class="mb-6">
    <h1 class="text-2xl font-bold text-gray-900">Mini Cursos</h1>
    <p class="text-gray-600 mt-1">Assista às aulas (Vídeo, Slides, PDF, Link ou texto).</p>
</div>

<?php if (empty($lista)): ?>
    <div class="bg-white rounded-xl shadow border border-gray-200 p-8 text-center text-gray-500">
        Nenhum minicurso disponível no momento.
    </div>
<?php else: ?>
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        <?php foreach ($lista as $row): ?>
            <?php
            $capa_url = !empty($row['imagem_caminho']) ? (rtrim(URL, '/') . '/' . ltrim($row['imagem_caminho'], '/')) : ($row['imagem_url'] ?? '');
            ?>
            <div class="bg-white rounded-xl shadow border border-gray-200 overflow-hidden hover:border-indigo-200 transition-colors">
                <?php if ($capa_url): ?>
                    <div class="h-32 bg-gray-100 flex items-center justify-center">
                        <img src="<?= htmlspecialchars($capa_url) ?>" alt="" class="max-h-full w-full object-cover">
                    </div>
                <?php endif; ?>
                <div class="p-4">
                    <h2 class="text-lg font-semibold text-gray-900"><?= htmlspecialchars($row['titulo']) ?></h2>
                    <?php if (!empty($row['descricao'])): ?>
                        <p class="text-sm text-gray-600 mt-1 line-clamp-2"><?= htmlspecialchars($row['descricao']) ?></p>
                    <?php endif; ?>
                    <p class="text-xs text-gray-500 mt-2"><?= (int)($row['total_aulas'] ?? 0) ?> aulas • <?= (int)($row['total_modulos'] ?? 0) ?> módulos</p>
                    <div class="mt-3 flex items-center justify-between">
                        <div class="w-full bg-gray-200 rounded-full h-2 mr-2">
                            <div class="bg-indigo-600 h-2 rounded-full" style="width: <?= (int)($row['total_aulas']) > 0 ? round(100 * ($row['aulas_vistas_count'] ?? 0) / (int)$row['total_aulas']) : 0 ?>%"></div>
                        </div>
                        <span class="text-xs text-gray-500"><?= (int)($row['aulas_vistas_count'] ?? 0) ?>/<?= (int)($row['total_aulas'] ?? 0) ?></span>
                    </div>
                    <a href="<?= URL ?>/minicursos/<?= (int)$row['id'] ?>" class="mt-4 block w-full text-center bg-indigo-600 text-white py-2 rounded-lg hover:bg-indigo-700 text-sm font-medium">
                        <?= !empty($row['concluido']) ? 'Ver minicurso' : 'Continuar' ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
