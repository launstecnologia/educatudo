<?php
$isEdit = isset($usuario) && !empty($usuario['id']);
$actionUrl = $isEdit ? (URL . '/master/usuarios/atualizar') : (URL . '/master/usuarios/salvar');
?>
<section class="bg-white rounded-xl shadow border border-slate-200 p-6">
    <h2 class="text-xl font-semibold text-slate-800 mb-4"><?= $isEdit ? 'Editar usuário master' : 'Novo usuário master' ?></h2>
    <?php $flash_msg = isset($flash) ? ($flash['message'] ?? null) : null; ?>
    <?php if (!empty($flash_msg)): ?>
    <div class="mb-4 px-4 py-3 rounded-lg <?= (isset($flash['type']) && $flash['type'] === 'error') ? 'bg-red-100 border border-red-200 text-red-800' : 'bg-green-100 border border-green-200 text-green-800' ?>">
        <?= htmlspecialchars($flash_msg) ?>
    </div>
    <?php endif; ?>
    <form method="post" action="<?= htmlspecialchars($actionUrl) ?>" enctype="multipart/form-data" class="space-y-4 max-w-xl">
        <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token ?? '') ?>">
        <?php if ($isEdit): ?>
        <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
        <?php endif; ?>
        <div>
            <label class="block text-sm font-medium text-slate-700 mb-2">Foto de perfil</label>
            <div class="flex items-center gap-4">
                <?php
                $avatarAtual = $usuario['avatar_url'] ?? '';
                if (!class_exists('MasterAvatarService')) {
                    require_once __DIR__ . '/../../../Services/MasterAvatarService.php';
                }
                $iniciaisPreview = MasterAvatarService::iniciais($usuario['nome'] ?? 'Master');
                ?>
                <div id="avatar-preview" class="w-16 h-16 rounded-full shrink-0 overflow-hidden border border-slate-200 bg-slate-800 flex items-center justify-center text-white text-lg font-semibold">
                    <?php if (!empty($avatarAtual)): ?>
                    <img src="<?= htmlspecialchars($avatarAtual) ?>" alt="Foto atual" class="w-full h-full object-cover" id="avatar-preview-img">
                    <?php else: ?>
                    <span id="avatar-preview-initials"><?= htmlspecialchars($iniciaisPreview) ?></span>
                    <?php endif; ?>
                </div>
                <div class="flex-1 min-w-0">
                    <input type="file" id="avatar_upload" name="avatar_upload" accept=".jpg,.jpeg,.png,.webp,.gif"
                           class="block w-full text-sm text-slate-500 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-medium file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100">
                    <p class="text-xs text-slate-500 mt-1">JPG, PNG, WebP ou GIF. Máximo 2 MB.</p>
                </div>
            </div>
        </div>
        <div>
            <label for="email" class="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
            <input type="email" id="email" name="email" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   <?= $isEdit ? 'readonly' : '' ?> required>
            <?php if ($isEdit): ?>
            <p class="text-xs text-slate-500 mt-1">E-mail não pode ser alterado na edição.</p>
            <?php endif; ?>
        </div>
        <div>
            <label for="nome" class="block text-sm font-medium text-slate-700 mb-1">Nome</label>
            <input type="text" id="nome" name="nome" value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>"
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="Nome de exibição">
        </div>
        <?php if ($isEdit): ?>
        <div>
            <label for="ativo" class="block text-sm font-medium text-slate-700 mb-1">Ativo</label>
            <label class="inline-flex items-center">
                <input type="checkbox" name="ativo" value="1" <?= !empty($usuario['ativo']) ? 'checked' : '' ?> class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                <span class="ml-2 text-sm text-slate-700">Usuário ativo (pode fazer login)</span>
            </label>
        </div>
        <div class="border-t border-slate-200 pt-4">
            <h3 class="text-sm font-medium text-slate-800 mb-2">Trocar senha</h3>
            <p class="text-xs text-slate-500 mb-2">Deixe em branco para manter a senha atual.</p>
            <div class="space-y-2">
                <label for="nova_senha" class="block text-sm font-medium text-slate-700">Nova senha</label>
                <input type="password" id="nova_senha" name="nova_senha" autocomplete="new-password" minlength="6"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                       placeholder="Mínimo 6 caracteres">
            </div>
        </div>
        <?php else: ?>
        <div>
            <label for="senha" class="block text-sm font-medium text-slate-700 mb-1">Senha</label>
            <input type="password" id="senha" name="senha" autocomplete="new-password" minlength="6" required
                   class="w-full px-3 py-2 border border-slate-300 rounded-lg focus:ring-2 focus:ring-blue-500 focus:border-blue-500"
                   placeholder="Mínimo 6 caracteres">
        </div>
        <?php endif; ?>
        <div class="flex gap-3 pt-2">
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 font-medium">
                <?= $isEdit ? 'Atualizar' : 'Criar usuário' ?>
            </button>
            <a href="<?= URL ?>/master/usuarios" class="px-4 py-2 bg-gray-200 text-slate-800 rounded-lg hover:bg-gray-300 font-medium">Voltar</a>
        </div>
    </form>
</section>
<script>
document.getElementById('avatar_upload')?.addEventListener('change', function(e) {
    var file = e.target.files && e.target.files[0];
    if (!file) return;
    var preview = document.getElementById('avatar-preview');
    if (!preview) return;
    var reader = new FileReader();
    reader.onload = function(ev) {
        preview.innerHTML = '<img src="' + ev.target.result + '" alt="Prévia" class="w-full h-full object-cover">';
    };
    reader.readAsDataURL(file);
});
document.getElementById('nome')?.addEventListener('input', function() {
    var initialsEl = document.getElementById('avatar-preview-initials');
    if (!initialsEl) return;
    var parts = this.value.trim().split(/\s+/).filter(Boolean);
    var initials = parts.length >= 2
        ? (parts[0][0] + parts[parts.length - 1][0]).toUpperCase()
        : this.value.trim().substring(0, 2).toUpperCase();
    initialsEl.textContent = initials || 'AD';
});
</script>
