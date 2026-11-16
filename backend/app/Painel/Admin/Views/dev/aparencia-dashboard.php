<?php
$perfilDashboard = (string) ($perfil_dashboard ?? '');
$titulos = [
    'admin' => 'Dashboard do admin',
    'aluno' => 'Dashboard do aluno',
    'professor' => 'Dashboard do professor',
];
$tituloPagina = $titulos[$perfilDashboard] ?? 'Dashboard';
$blocos = is_array($blocos ?? null) ? $blocos : [];
?>
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2"><?= htmlspecialchars($tituloPagina) ?></h2>
            <p class="text-gray-600">Desmarcar um bloco só esconde aquele bloco. Arrastar muda a ordem e empilha os blocos. Sem salvar uma ordem diferente, o dashboard continua como está hoje.</p>
        </div>
        <a href="<?= URL ?>/admin/dev/aparencia" class="text-gray-600 hover:text-gray-900">← Voltar</a>
    </div>
</div>

<?php
$flash_message = $flash_message ?? '';
$flash_type = $flash_type ?? 'info';
if ($flash_message !== ''):
    $bg = $flash_type === 'success' ? 'bg-green-50 border-green-200 text-green-800' : ($flash_type === 'error' ? 'bg-red-50 border-red-200 text-red-800' : 'bg-blue-50 border-blue-200 text-blue-800');
?>
<div class="mb-6 p-4 rounded-lg border <?= $bg ?>"><?= htmlspecialchars((string) $flash_message) ?></div>
<?php endif; ?>

<div class="bg-white rounded-xl shadow-lg p-6">
    <form id="form-montagem" method="post" action="<?= URL ?>/admin/dev/aparencia/dashboard/salvar">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="perfil" value="<?= htmlspecialchars($perfilDashboard) ?>">
        <ul id="lista-montagem" class="space-y-2">
            <?php foreach ($blocos as $bloco): ?>
            <li draggable="true" class="montagem-item flex items-center gap-3 p-3 rounded-lg bg-white border border-gray-200 cursor-move hover:border-indigo-300">
                <span class="text-gray-400 select-none" aria-hidden="true">⠿</span>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900"><?= htmlspecialchars((string) ($bloco['nome'] ?? '')) ?></p>
                    <p class="text-xs text-gray-500 mt-0.5"><?= htmlspecialchars((string) ($bloco['descricao'] ?? '')) ?></p>
                </div>
                <label class="flex items-center gap-2 text-sm text-gray-700 shrink-0">
                    <input type="checkbox" name="visivel[<?= htmlspecialchars((string) $bloco['chave']) ?>]" value="1" class="h-4 w-4 text-indigo-600 rounded" <?= !empty($bloco['visivel']) ? 'checked' : '' ?>>
                    Exibir
                </label>
                <input type="hidden" name="ordem[]" value="<?= htmlspecialchars((string) $bloco['chave']) ?>">
            </li>
            <?php endforeach; ?>
        </ul>
        <div class="mt-6 flex justify-end gap-3">
            <button type="submit" class="btn-primary-custom px-5 py-2.5 rounded-lg font-medium hover:opacity-90 order-2">Salvar montagem</button>
            <button type="submit" name="restaurar" value="1" class="order-1 px-5 py-2.5 rounded-lg border border-gray-300 text-gray-700 hover:bg-gray-50" onclick="return confirm('Restaurar a ordem e a visibilidade do dashboard atual?');">Restaurar padrão</button>
        </div>
    </form>
</div>

<script>
(function () {
    var list = document.getElementById('lista-montagem');
    if (!list) return;
    var dragging = null;
    list.addEventListener('dragstart', function (e) {
        dragging = e.target.closest('.montagem-item');
        if (dragging) e.dataTransfer.effectAllowed = 'move';
    });
    list.addEventListener('dragover', function (e) {
        e.preventDefault();
        var target = e.target.closest('.montagem-item');
        if (!target || target === dragging || !dragging) return;
        var rect = target.getBoundingClientRect();
        var before = (e.clientY - rect.top) < rect.height / 2;
        list.insertBefore(dragging, before ? target : target.nextSibling);
    });
})();
</script>
