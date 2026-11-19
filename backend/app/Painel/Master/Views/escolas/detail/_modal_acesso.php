<?php
$acesso_id = (string) ($acesso_id ?? 'pessoa');
$acesso_titulo = (string) ($acesso_titulo ?? 'Editar acesso');
$acesso_dica = (string) ($acesso_dica ?? 'Deixe a senha em branco para manter a atual.');
$acesso_email_obrigatorio = !empty($acesso_email_obrigatorio);
$acesso_mostrar_apelido = !empty($acesso_mostrar_apelido);
$acesso_ocultos = is_array($acesso_ocultos ?? null) ? $acesso_ocultos : [];
$csrf_token = $csrf_token ?? '';
$modal_id = 'modal-acesso-' . $acesso_id;
?>
<div id="<?= htmlspecialchars($modal_id) ?>" class="fixed inset-0 z-[80] hidden items-center justify-center bg-black/50 p-4" aria-hidden="true">
    <div class="bg-white rounded-xl shadow-xl w-full max-w-md" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($modal_id) ?>-titulo">
        <form method="post" id="<?= htmlspecialchars($modal_id) ?>-form" class="px-5 py-4 space-y-4" autocomplete="off">
            <input type="hidden" name="_token" value="<?= htmlspecialchars($csrf_token) ?>">
            <?php foreach ($acesso_ocultos as $nomeCampo => $valorCampo): ?>
            <input type="hidden" name="<?= htmlspecialchars((string) $nomeCampo) ?>" value="<?= htmlspecialchars((string) $valorCampo) ?>">
            <?php endforeach; ?>
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h4 id="<?= htmlspecialchars($modal_id) ?>-titulo" class="text-lg font-semibold text-slate-900"><?= htmlspecialchars($acesso_titulo) ?></h4>
                    <p class="text-sm text-slate-500 mt-0.5" id="<?= htmlspecialchars($modal_id) ?>-nome"></p>
                </div>
                <button type="button" data-fechar-acesso class="p-1.5 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Fechar">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>
            <p class="text-xs text-slate-500"><?= htmlspecialchars($acesso_dica) ?></p>
            <p id="<?= htmlspecialchars($modal_id) ?>-login" class="text-xs text-slate-600 hidden"></p>
            <?php if ($acesso_mostrar_apelido): ?>
            <div>
                <label for="<?= htmlspecialchars($modal_id) ?>-nickname" class="block text-sm font-medium text-slate-700 mb-1">Apelido de login</label>
                <input type="text" id="<?= htmlspecialchars($modal_id) ?>-nickname" name="nickname" maxlength="60" autocomplete="off"
                       placeholder="Deixe em branco para não alterar"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <?php endif; ?>
            <div>
                <label for="<?= htmlspecialchars($modal_id) ?>-email" class="block text-sm font-medium text-slate-700 mb-1">E-mail</label>
                <input type="email" id="<?= htmlspecialchars($modal_id) ?>-email" name="email" <?= $acesso_email_obrigatorio ? 'required' : '' ?>
                       maxlength="180" autocomplete="off"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div>
                <label for="<?= htmlspecialchars($modal_id) ?>-senha" class="block text-sm font-medium text-slate-700 mb-1">Nova senha</label>
                <input type="password" id="<?= htmlspecialchars($modal_id) ?>-senha" name="senha" maxlength="128"
                       autocomplete="new-password" placeholder="Deixe em branco para não alterar"
                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white focus:ring-2 focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="flex justify-end gap-2 pt-1">
                <button type="button" data-fechar-acesso class="px-4 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancelar</button>
                <button type="submit" class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700">Salvar</button>
            </div>
        </form>
    </div>
</div>
<script>
(function () {
    var modal = document.getElementById(<?= json_encode($modal_id) ?>);
    if (!modal) return;
    document.querySelectorAll('body > #' + modal.id).forEach(function (el) {
        if (el !== modal) el.remove();
    });
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    var form = document.getElementById(modal.id + '-form');
    var nomeEl = document.getElementById(modal.id + '-nome');
    var emailEl = document.getElementById(modal.id + '-email');
    var senhaEl = document.getElementById(modal.id + '-senha');
    var loginEl = document.getElementById(modal.id + '-login');

    function abrir(btn) {
        form.action = btn.getAttribute('data-action') || '';
        nomeEl.textContent = btn.getAttribute('data-nome') || '';
        emailEl.value = btn.getAttribute('data-email') || '';
        senhaEl.value = '';
        var nickEl = document.getElementById(modal.id + '-nickname');
        if (nickEl) nickEl.value = btn.getAttribute('data-login') || '';
        var login = btn.getAttribute('data-login') || '';
        var loginVazio = btn.getAttribute('data-login-vazio') || '';
        if (loginEl) {
            var textoLogin = '';
            if (nickEl && login === '') textoLogin = loginVazio;
            else if (!nickEl && login !== '') textoLogin = 'Apelido de login: ' + login;
            else if (!nickEl) textoLogin = loginVazio;
            loginEl.textContent = textoLogin;
            loginEl.hidden = textoLogin === '';
        }
        modal.classList.remove('hidden');
        modal.classList.add('flex');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
        setTimeout(function () { (nickEl && login === '' ? nickEl : emailEl).focus(); }, 50);
    }
    function fechar() {
        modal.classList.add('hidden');
        modal.classList.remove('flex');
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('overflow-hidden');
    }
    document.querySelectorAll('[data-abrir-acesso="<?= htmlspecialchars($acesso_id, ENT_QUOTES) ?>"]').forEach(function (btn) {
        btn.addEventListener('click', function () { abrir(btn); });
    });
    modal.querySelectorAll('[data-fechar-acesso]').forEach(function (btn) {
        btn.addEventListener('click', fechar);
    });
    modal.addEventListener('click', function (e) {
        if (e.target === modal) fechar();
    });
})();
</script>
