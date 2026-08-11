<?php
/**
 * Inicialização do OneSignal (push) após login.
 * Define $onesignal_app_id e $base_url antes de incluir.
 * Apenas registra o usuário e tags; sem banners nem pedido de permissão visível.
 */
$onesignal_app_id = $onesignal_app_id ?? (function_exists('env') ? env('ONESIGNAL_APP_ID', '') : '') ?: getenv('ONESIGNAL_APP_ID') ?: (defined('ONESIGNAL_APP_ID') ? ONESIGNAL_APP_ID : '');
$base_url = $base_url ?? (defined('URL') ? URL : '');
if ($onesignal_app_id === '' || $base_url === '') return;
if (strpos($base_url, 'localhost') !== false || strpos($base_url, '127.0.0.1') !== false) return;
?>
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
(function() {
  var appId = <?= json_encode($onesignal_app_id) ?>;
  var baseUrl = <?= json_encode(rtrim($base_url, '/')) ?>;
  if (!appId) return;
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  window.OneSignalDeferred.push(async function(OneSignal) {
    try {
      await OneSignal.init({ appId: appId });
      var r = await fetch(baseUrl + '/api/notificacoes-push/meu-tags', { credentials: 'include' });
      if (!r.ok) return;
      var data = await r.json();
      if (!data || !data.user_id) return;
      await OneSignal.login(data.user_id);
      var tags = {
        role: data.role || '',
        turmas: (data.turmas || '').toString(),
        alunos_ids: (data.alunos_ids || '').toString(),
        escola_id: (data.escola_id || '1').toString()
      };
      await OneSignal.User.addTags(tags);
    } catch (e) { console.warn('OneSignal init:', e); }
  });
})();
</script>
