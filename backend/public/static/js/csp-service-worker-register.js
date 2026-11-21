/**
 * SEC-02 — registro do Service Worker sem script inline.
 * Usar: <script src=".../csp-service-worker-register.js" data-sw-url="/service-worker.js" defer></script>
 */
(function () {
  if (!('serviceWorker' in navigator)) return;
  var script = document.currentScript;
  var url = (script && script.getAttribute('data-sw-url')) || '/service-worker.js';
  navigator.serviceWorker.register(url).catch(function () {});
})();
