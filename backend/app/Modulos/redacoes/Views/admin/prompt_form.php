<!-- Header Section -->
<div class="mb-8">
    <div class="flex justify-between items-center">
        <div>
            <h2 class="text-2xl font-bold text-gray-900 mb-2">
                <?= $item ? 'Editar Prompt' : 'Novo Prompt' ?> — <?= htmlspecialchars($textType['name']) ?>
            </h2>
            <p class="text-gray-600">
                Versão <?= $item ? (int)$item['version'] : (int)$nextVersion ?>.
                <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/prompts" class="text-purple-600 hover:underline">Voltar aos prompts</a>
            </p>
        </div>
        <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/prompts"
           class="bg-gray-600 text-white px-6 py-3 rounded-xl hover:bg-gray-700 transition-all duration-300 flex items-center">
            <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Voltar
        </a>
    </div>
</div>

<div class="bg-white/80 backdrop-blur-sm rounded-xl shadow-lg border border-purple-200">
    <div class="p-6 border-b border-gray-200">
        <h3 class="text-lg font-semibold text-gray-900">Texto do prompt (instruções para a IA na correção)</h3>
        <p class="text-sm text-gray-500 mt-1">Use placeholders como {critérios} ou descreva como a IA deve avaliar e retornar feedback e notas por critério.</p>
    </div>
    <form id="form" class="p-6 space-y-6">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div>
            <label for="prompt_text" class="block text-sm font-semibold text-gray-700 mb-2">Prompt <span class="text-red-500">*</span></label>
            <textarea id="prompt_text" name="prompt_text" required rows="12" class="w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-purple-500 font-mono text-sm"><?= $item ? htmlspecialchars($item['prompt_text']) : '' ?></textarea>
        </div>
        <div>
            <label class="flex items-center">
                <input type="checkbox" name="is_active" value="1" <?= ($item && $item['is_active']) || (!$item) ? 'checked' : '' ?> class="rounded border-gray-300 text-purple-600 focus:ring-purple-500">
                <span class="ml-2 text-sm text-gray-700">Definir como prompt ativo (único ativo por banca + tipo)</span>
            </label>
        </div>
        <div id="errorMessage" class="hidden bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded-lg"></div>
        <div id="successMessage" class="hidden bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded-lg"></div>
        <div class="flex justify-end space-x-4">
            <a href="<?= URL ?>/admin/redacao-configuravel/bancas/<?= (int)$board['id'] ?>/tipos/<?= (int)$textType['id'] ?>/prompts" class="px-6 py-3 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50">Cancelar</a>
            <button type="submit" class="px-6 py-3 bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-lg hover:from-purple-700 hover:to-indigo-700"><?= $item ? 'Atualizar' : 'Cadastrar' ?></button>
        </div>
    </form>
</div>

<script>
(function() {
    const form = document.getElementById('form');
    const boardId = <?= (int)$board['id'] ?>;
    const textTypeId = <?= (int)$textType['id'] ?>;
    const isEdit = <?= $item ? 'true' : 'false' ?>;
    const itemId = <?= $item ? (int)$item['id'] : 'null' ?>;
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        document.getElementById('errorMessage').classList.add('hidden');
        document.getElementById('successMessage').classList.add('hidden');
        const fd = new FormData(this);
        if (isEdit) fd.append('_method', 'PUT');
        const url = isEdit ? '<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos/' + textTypeId + '/prompts/' + itemId : '<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos/' + textTypeId + '/prompts';
        fetch(url, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success) {
                    document.getElementById('successMessage').textContent = d.message;
                    document.getElementById('successMessage').classList.remove('hidden');
                    setTimeout(() => { window.location.href = '<?= URL ?>/admin/redacao-configuravel/bancas/' + boardId + '/tipos/' + textTypeId + '/prompts'; }, 1500);
                } else {
                    document.getElementById('errorMessage').textContent = d.error || 'Erro';
                    document.getElementById('errorMessage').classList.remove('hidden');
                }
            })
            .catch(() => { document.getElementById('errorMessage').textContent = 'Erro de conexão'; document.getElementById('errorMessage').classList.remove('hidden'); });
    });
})();
</script>
