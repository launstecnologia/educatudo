<?php
/**
 * Banner para instalar o app no iOS (Safari).
 * Só aparece em iPhone/iPad, quando não está já em modo standalone (app instalado).
 * Pode ser fechado; o fechamento é lembrado na sessão (sessionStorage).
 */
$storage_key = $ios_install_storage_key ?? 'ios_install_dismissed';
?>
<div id="ios-install-banner" class="hidden bg-gradient-to-r from-purple-600 to-indigo-600 text-white rounded-xl shadow-lg p-4 mb-4" role="alert">
    <div class="flex items-start gap-3">
        <div class="flex-shrink-0 w-10 h-10 bg-white/20 rounded-lg flex items-center justify-center">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path>
            </svg>
        </div>
        <div class="flex-1 min-w-0">
            <p class="font-semibold">Instale o app no seu iPhone ou iPad</p>
            <p class="text-sm text-white/90 mt-1">Para usar como app e receber notificações:</p>
            <ol class="text-sm text-white/90 mt-2 space-y-1 list-decimal list-inside">
                <li>Toque no ícone <strong>Compartilhar</strong> <span class="inline-flex align-middle">(□ com seta para cima)</span> na barra do Safari.</li>
                <li>Role e toque em <strong>« Adicionar à Tela de Início »</strong>.</li>
                <li>Toque em <strong>Adicionar</strong>.</li>
            </ol>
        </div>
        <button type="button" id="ios-install-banner-close" class="flex-shrink-0 p-1 rounded-lg hover:bg-white/20 transition-colors" aria-label="Fechar">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
            </svg>
        </button>
    </div>
</div>
<script>
(function() {
    var banner = document.getElementById('ios-install-banner');
    var closeBtn = document.getElementById('ios-install-banner-close');
    var storageKey = <?= json_encode($storage_key) ?>;

    function isIOS() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }
    function isStandalone() {
        return window.navigator.standalone === true || window.matchMedia('(display-mode: standalone)').matches;
    }
    function wasDismissed() {
        try { return sessionStorage.getItem(storageKey) === '1'; } catch (e) { return false; }
    }
    function setDismissed() {
        try { sessionStorage.setItem(storageKey, '1'); } catch (e) {}
    }

    if (!banner) return;
    if (isIOS() && !isStandalone() && !wasDismissed()) {
        banner.classList.remove('hidden');
    }
    if (closeBtn) {
        closeBtn.addEventListener('click', function() {
            setDismissed();
            banner.classList.add('hidden');
        });
    }
})();
</script>
