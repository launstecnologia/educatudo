/**
 * SEC-10 / CSRF_MODE=enforce — injeta X-CSRF-TOKEN em fetch same-origin mutável.
 *
 * O middleware NÃO lê _token do body JSON (php://input só pode ser consumido uma vez).
 * Por isso application/json precisa do header; FormData sem _token também é completado.
 */
(function () {
  if (window.__educatudoCsrfFetchPatched) {
    return;
  }
  window.__educatudoCsrfFetchPatched = true;

  function lerTokenCsrf() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? (meta.getAttribute('content') || '') : '';
  }

  if (typeof window.getCsrfToken !== 'function') {
    window.getCsrfToken = lerTokenCsrf;
  }

  var fetchOriginal = window.fetch;
  if (typeof fetchOriginal !== 'function') {
    return;
  }

  window.fetch = function (input, init) {
    init = init ? Object.assign({}, init) : {};
    var method = String(init.method || (typeof Request !== 'undefined' && input instanceof Request ? input.method : 'GET')).toUpperCase();
    if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) === -1) {
      return fetchOriginal.call(this, input, init);
    }

    var url = '';
    if (typeof input === 'string') {
      url = input;
    } else if (input && typeof input.url === 'string') {
      url = input.url;
    }

    try {
      var absolute = new URL(url, window.location.href);
      if (absolute.origin !== window.location.origin) {
        return fetchOriginal.call(this, input, init);
      }
    } catch (e) {
      // URL relativa inválida — segue e tenta injetar mesmo assim
    }

    var headers;
    if (init.headers instanceof Headers) {
      headers = init.headers;
    } else {
      headers = new Headers(init.headers || {});
      init.headers = headers;
    }

    var token = lerTokenCsrf();
    if (token) {
      if (!headers.has('X-CSRF-TOKEN') && !headers.has('X-CSRFToken') && !headers.has('X-XSRF-TOKEN')) {
        headers.set('X-CSRF-TOKEN', token);
      }
    }
    if (!headers.has('X-Requested-With')) {
      headers.set('X-Requested-With', 'XMLHttpRequest');
    }

    if (typeof FormData !== 'undefined' && init.body instanceof FormData && token) {
      if (!init.body.has('_token')) {
        init.body.append('_token', token);
      }
    }

    return fetchOriginal.call(this, input, init);
  };
})();
