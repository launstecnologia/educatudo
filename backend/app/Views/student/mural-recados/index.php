<!-- Header -->
<div class="mb-8">
    <div>
        <h1 class="text-3xl font-bold text-gray-900">Mural de Recados</h1>
        <p class="text-gray-600 mt-2">Recados publicados para sua turma ou para todos.</p>
    </div>
</div>

<!-- Filtros -->
<div class="bg-white rounded-xl shadow-lg p-4 mb-6">
    <form method="get" action="<?= URL ?>/mural-recados" class="flex flex-wrap items-end gap-4">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Professor</label>
            <select name="professor_id" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todos</option>
                <?php foreach ($professores_opcoes ?? [] as $prof): ?>
                <option value="<?= (int)$prof['id'] ?>" <?= (int)($filtro_professor ?? 0) === (int)$prof['id'] ? 'selected' : '' ?>><?= htmlspecialchars($prof['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Matéria</label>
            <select name="materia_id" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
                <option value="">Todas</option>
                <?php foreach ($materias_opcoes ?? [] as $mat): ?>
                <option value="<?= (int)$mat['id'] ?>" <?= (int)($filtro_materia ?? 0) === (int)$mat['id'] ? 'selected' : '' ?>><?= htmlspecialchars($mat['nome']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Data de</label>
            <input type="date" name="data_de" value="<?= htmlspecialchars($filtro_data_de ?? '') ?>" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Data até</label>
            <input type="date" name="data_ate" value="<?= htmlspecialchars($filtro_data_ate ?? '') ?>" class="rounded-lg border-gray-300 focus:ring-blue-500 focus:border-blue-500">
        </div>
        <div class="flex gap-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">Filtrar</button>
            <a href="<?= URL ?>/mural-recados" class="px-4 py-2 text-gray-700 bg-gray-100 rounded-lg hover:bg-gray-200">Limpar</a>
        </div>
    </form>
</div>

<?php if (empty($recados)): ?>
<div class="bg-white rounded-xl shadow-lg p-8 text-center">
    <p class="text-gray-500">Nenhum recado no mural no momento.</p>
</div>
<?php else: ?>
<div class="space-y-4">
    <?php foreach ($recados as $recado): ?>
    <div class="bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden" data-recado-id="<?= (int)$recado['id'] ?>">
        <div class="p-6">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1">
                    <h2 class="text-xl font-semibold text-gray-900 mb-2"><?= htmlspecialchars($recado['titulo']) ?></h2>
                    <p class="text-sm text-gray-500 mb-2">
                        Por <?= htmlspecialchars($recado['autor_nome'] ?? 'Admin') ?> · Publicado em <?= date('d/m/Y H:i', strtotime($recado['data_publicacao'])) ?>
                    </p>
                    <?php
                    $c = $recado['conteudo'] ?? '';
                    if ($c !== '') {
                        if (strpos($c, '<') !== false) {
                            echo '<div class="text-gray-700 prose prose-sm max-w-none">' . rich_text_render($c) . '</div>';
                        } else {
                            echo '<div class="text-gray-700 whitespace-pre-wrap">' . nl2br(htmlspecialchars($c)) . '</div>';
                        }
                    }
                    ?>
                </div>
                <?php if (empty($recado['ja_visto'])): ?>
                <button type="button" class="marcar-visto-btn flex-shrink-0 px-4 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700" data-id="<?= (int)$recado['id'] ?>">Marcar como lido</button>
                <?php else: ?>
                <span class="flex-shrink-0 text-sm text-gray-500">✓ Lido</span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.marcar-visto-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var id = this.getAttribute('data-id');
            if (!id) return;
            var form = new FormData();
            form.append('ids[]', id);
            form.append('_token', '<?= htmlspecialchars($csrf_token ?? '') ?>');
            fetch('<?= URL ?>/mural-recados/marcar-visto', { method: 'POST', body: form })
                .then(function(r) { return r.json(); })
                .then(function(data) {
                    if (data.success && btn.parentNode) {
                        var span = document.createElement('span');
                        span.className = 'flex-shrink-0 text-sm text-gray-500';
                        span.textContent = '✓ Lido';
                        btn.parentNode.replaceChild(span, btn);
                    }
                });
        });
    });
});
</script>
