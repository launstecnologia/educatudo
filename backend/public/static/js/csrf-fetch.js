/**
 * SEC-10 / CSRF_MODE=enforce — injeta CSRF em fetch e XMLHttpRequest same-origin.
 *
 * O middleware NÃO lê _token do body JSON (php://input só pode ser consumido uma vez).
 * application/json e XHR precisam de X-CSRF-TOKEN; FormData sem _token é completado.
 *
 * sendBeacon não permite header customizado — rotas beacon ficam em csrf_excecoes.php.
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

  function mesmaOrigem(url) {
    try {
      var absolute = new URL(url || '', window.location.href);
      return absolute.origin === window.location.origin;
    } catch (e) {
      return true;
    }
  }

  function headersTemCsrf(headers) {
    if (!headers) return false;
    if (typeof headers.has === 'function') {
      return headers.has('X-CSRF-TOKEN') || headers.has('X-CSRFToken') || headers.has('X-XSRF-TOKEN');
    }
    var keys = Object.keys(headers);
    for (var i = 0; i < keys.length; i++) {
      var k = String(keys[i]).toLowerCase();
      if (k === 'x-csrf-token' || k === 'x-csrftoken' || k === 'x-xsrf-token') {
        return true;
      }
    }
    return false;
  }

  if (typeof window.getCsrfToken !== 'function') {
    window.getCsrfToken = lerTokenCsrf;
  }

  // --- fetch ---
  var fetchOriginal = window.fetch;
  if (typeof fetchOriginal === 'function') {
    window.fetch = function (input, init) {
      init = init ? Object.assign({}, init) : {};
      var method = String(
        init.method ||
          (typeof Request !== 'undefined' && input instanceof Request ? input.method : 'GET')
      ).toUpperCase();

      if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) === -1) {
        return fetchOriginal.call(this, input, init);
      }

      var url = '';
      if (typeof input === 'string') {
        url = input;
      } else if (input && typeof input.url === 'string') {
        url = input.url;
      }

      if (!mesmaOrigem(url)) {
        return fetchOriginal.call(this, input, init);
      }

      var headers;
      if (init.headers instanceof Headers) {
        headers = init.headers;
      } else {
        headers = new Headers(init.headers || {});
        init.headers = headers;
      }

      var token = lerTokenCsrf();
      if (token && !headersTemCsrf(headers)) {
        headers.set('X-CSRF-TOKEN', token);
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
  }

  // --- XMLHttpRequest ---
  if (typeof XMLHttpRequest === 'undefined') {
    return;
  }

  var openOriginal = XMLHttpRequest.prototype.open;
  var sendOriginal = XMLHttpRequest.prototype.send;

  XMLHttpRequest.prototype.open = function (method, url) {
    this.__educatudoCsrfMethod = method;
    this.__educatudoCsrfUrl = url;
    this.__educatudoCsrfHeaderSet = false;
    return openOriginal.apply(this, arguments);
  };

  XMLHttpRequest.prototype.send = function (body) {
    var method = String(this.__educatudoCsrfMethod || 'GET').toUpperCase();
    var url = this.__educatudoCsrfUrl || '';

    if (['POST', 'PUT', 'PATCH', 'DELETE'].indexOf(method) !== -1 && mesmaOrigem(url)) {
      var token = lerTokenCsrf();
      if (token && !this.__educatudoCsrfHeaderSet) {
        try {
          this.setRequestHeader('X-CSRF-TOKEN', token);
          this.__educatudoCsrfHeaderSet = true;
        } catch (e) {
          // header já definido pelo caller
        }
      }
      try {
        this.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
      } catch (e2) {
        // ignore
      }
      if (typeof FormData !== 'undefined' && body instanceof FormData && token && !body.has('_token')) {
        body.append('_token', token);
      }
    }

    return sendOriginal.call(this, body);
  };
})();
