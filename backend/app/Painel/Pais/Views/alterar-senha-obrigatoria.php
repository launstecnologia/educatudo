<div class="max-w-2xl mx-auto">
    <div class="bg-white rounded-xl shadow-md border border-gray-200 p-6">
        <h2 class="text-2xl font-bold text-gray-900 mb-2">Alterar Senha</h2>
        <p class="text-sm text-gray-600 mb-6">Para continuar, defina uma nova senha para sua conta.</p>

        <form id="parent-force-password-form" class="space-y-4">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2" for="nova_senha">Nova senha</label>
                <input id="nova_senha" name="nova_senha" type="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2" for="confirmar_senha">Confirmar nova senha</label>
                <input id="confirmar_senha" name="confirmar_senha" type="password" required class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
            </div>

            <div id="parent-force-password-error" class="hidden px-4 py-3 rounded-lg bg-red-50 border border-red-200 text-red-700 text-sm"></div>

            <button type="submit" class="w-full px-4 py-2.5 rounded-lg bg-indigo-600 text-white font-medium hover:bg-indigo-700 transition-colors">
                Salvar nova senha
            </button>
        </form>
    </div>
</div>

<script>
document.getElementById('parent-force-password-form')?.addEventListener('submit', async function (e) {
    e.preventDefault();
    const form = e.currentTarget;
    const err = document.getElementById('parent-force-password-error');
    if (err) {
        err.classList.add('hidden');
        err.textContent = '';
    }

    const payload = new FormData(form);

    try {
        const response = await fetch('<?= URL ?>/pais/alterar-senha-obrigatoria', {
            method: 'POST',
            body: payload
        });
        const data = await response.json();
        if (!response.ok || !data.success) {
            throw new Error(data.error || 'Não foi possível alterar a senha.');
        }
        window.location.href = data.redirect || '<?= URL ?>/pais/dashboard';
    } catch (error) {
        if (err) {
            err.textContent = error.message || 'Erro ao alterar senha.';
            err.classList.remove('hidden');
        }
    }
});
</script>
