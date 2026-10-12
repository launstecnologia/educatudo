<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                <?= $item ? 'Editar Tipo Textual' : 'Novo Tipo Textual' ?> — <?= htmlspecialchars($board['name']) ?>
            </h2>
            <p class="text-gray-600">
                <a href="<?= URL ?>/admin/redacao-configuravel" class="text-purple-600 hover:underline">Redação Configurável</a>
                &rarr; <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos" class="text-purple-600 hover:underline">Tipos</a>
            </p>
        </div>
        <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos"
           class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Voltar
        </a>
    </div>
</div>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Dados do Tipo Textual</h3>
    </div>
    <form id="form" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div>
            <label for="name" class="block text-sm font-semibold text-gray-700 mb-2">Nome <span class="text-red-500">*</span></label>
            <input type="text" id="name" name="name" required value="<?= $item ? htmlspecialchars($item['name']) : '' ?>"
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                   placeholder="Ex: Dissertativo-argumentativo">
        </div>
        <div>
            <label for="slug" class="block text-sm font-semibold text-gray-700 mb-2">Identificador (slug)</label>
            <input type="text" id="slug" name="slug" value="<?= $item ? htmlspecialchars($item['slug']) : '' ?>"
                   class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"
                   placeholder="Ex: dissertativo-argumentativo">
            <p class="mt-1 text-sm text-gray-500">Deixe em branco para gerar a partir do nome.</p>
        </div>
        <div>
            <label for="description" class="block text-sm font-semibold text-gray-700 mb-2">Descrição</label>
            <textarea id="description" name="description" rows="3" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500"><?= $item ? htmlspecialchars($item['description'] ?? '') : '' ?></textarea>
        </div>
        <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
        <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"></div>
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700"><?= $item ? 'Atualizar' : 'Cadastrar' ?></button>
        </div>
    </form>
</div>

<script>
(function() {
    const form = document.getElementById('form');
    const boardId = <?= (int)$board['id'] ?>;
    const isEdit = <?= $item ? 'true' : 'false' ?>;
    const itemId = <?= $item ? (int)$item['id'] : 'null' ?>;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        document.getElementById('errorMessage').classList.add('hidden');
        document.getElementById('successMessage').classList.add('hidden');
        const fd = new FormData(this);
        if (isEdit) fd.append('_method', 'PUT');
        const url = isEdit ? '<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos/' + itemId : '<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos';
        fetch(url, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('successMessage').textContent = d.message;
                    document.getElementById('successMessage').classList.remove('hidden');
                    setTimeout(() => { window.location.href = '<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos'; }, 1500);
                } else {
                    document.getElementById('errorMessage').textContent = d.error || 'Erro';
                    document.getElementById('errorMessage').classList.remove('hidden');
                }
            })
            .catch(() => { document.getElementById('errorMessage').textContent = 'Erro de conexão'; document.getElementById('errorMessage').classList.remove('hidden'); });
    });
})();
</script>
