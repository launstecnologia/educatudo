<div class="max-w-md mx-auto bg-white rounded-xl shadow-lg p-8">
    <h1 class="text-xl font-bold text-gray-900 mb-2">Alterar senha</h1>
    <p class="text-gray-600 text-sm mb-6">Por segurança, defina uma nova senha antes de continuar.</p>
    <form id="formSenha" class="space-y-4">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Nova senha</label>
            <input type="password" name="nova_senha" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar senha</label>
            <input type="password" name="confirmar_senha" required class="w-full border rounded-lg px-3 py-2">
        </div>
        <p id="erro" class="text-red-600 text-sm hidden"></p>
        <button type="submit" class="w-full bg-teal-600 text-white py-2 rounded-lg hover:bg-teal-700">Salvar</button>
    </form>
</div>
<script>
document.getElementById('formSenha').addEventListener('submit', function(e) {
    e.preventDefault();
    const fd = new FormData(this);
    fetch('<?= URL ?>/monitor/alterar-senha-obrigatoria', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(r => r.json())
        .then(function(d) {
            if (d.success && d.redirect) window.location = d.redirect;
            else {
                document.getElementById('erro').textContent = d.error || 'Erro';
                document.getElementById('erro').classList.remove('hidden');
            }
        });
});
</script>
