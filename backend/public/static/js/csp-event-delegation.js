/**
 * SEC-02 — delegação de eventos (substitui onclick/onchange/onsubmit inline).
 *
 * data-csp-action / data-csp-click / data-csp-arg / data-csp-arg2 / data-csp-pass
 * data-csp-change / data-csp-confirm / data-csp-submit-check / data-csp-href / data-csp-click-id
 */
(function () {
  window.EducaTudoCspActions = window.EducaTudoCspActions || {};

  function callNamed(name, el, ev, arg, arg2, pass) {
    if (typeof window.EducaTudoCspActions[name] === 'function') {
      window.EducaTudoCspActions[name](el, ev, arg, arg2);
      return true;
    }
    if (typeof window[name] !== 'function') {
      return false;
    }
    if (pass === 'el') {
      window[name](el);
      return true;
    }
    if (pass === 'value') {
      window[name](el && el.value);
      return true;
    }
    if (pass === 'event') {
      window[name](ev);
      return true;
    }
    if (arg !== null && arg !== undefined && arg !== '' && arg2 !== null && arg2 !== undefined && arg2 !== '') {
      var a1 = arg;
      var a2 = arg2;
      if (arg === 'true' || arg === 'false') a1 = arg === 'true';
      if (arg2 === 'true' || arg2 === 'false') a2 = arg2 === 'true';
      window[name](a1, a2);
      return true;
    }
    if (arg !== null && arg !== undefined && arg !== '') {
      var a = arg;
      if (arg === 'true' || arg === 'false') a = arg === 'true';
      window[name](a);
      return true;
    }
    window[name]();
    return true;
  }

  window.EducaTudoCspActions['toggle-menu-group'] = function (el) {
    var group = el.getAttribute('data-csp-group');
    if (group && typeof window.toggleMenuGroup === 'function') {
      window.toggleMenuGroup(group);
    }
  };

  window.EducaTudoCspActions['__cspSubmitClosestForm'] = function (el) {
    var form = el && el.closest ? el.closest('form') : null;
    if (form) form.submit();
  };

  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest
      ? e.target.closest('[data-csp-action],[data-csp-click],[data-csp-confirm],[data-csp-href],[data-csp-click-id]')
      : null;
    if (!el) return;

    if (el.hasAttribute('data-csp-confirm') && !el.hasAttribute('data-csp-submit-check')) {
      var msg = el.getAttribute('data-csp-confirm') || '';
      if (msg && !window.confirm(msg)) {
        e.preventDefault();
        e.stopPropagation();
        return;
      }
      // se for só confirm em botão submit, deixa o form seguir
      if (!el.hasAttribute('data-csp-click') && !el.hasAttribute('data-csp-action') && !el.hasAttribute('data-csp-href')) {
        return;
      }
    }

    if (el.hasAttribute('data-csp-href')) {
      e.preventDefault();
      window.location.href = el.getAttribute('data-csp-href');
      return;
    }

    if (el.hasAttribute('data-csp-click-id')) {
      e.preventDefault();
      var target = document.getElementById(el.getAttribute('data-csp-click-id'));
      if (target) target.click();
      return;
    }

    if (el.hasAttribute('data-csp-action')) {
      var action = el.getAttribute('data-csp-action');
      if (action && typeof window.EducaTudoCspActions[action] === 'function') {
        e.preventDefault();
        window.EducaTudoCspActions[action](el, e);
        return;
      }
    }

    if (el.hasAttribute('data-csp-click')) {
      var fn = el.getAttribute('data-csp-click');
      var arg = el.getAttribute('data-csp-arg');
      var arg2 = el.getAttribute('data-csp-arg2');
      var pass = el.getAttribute('data-csp-pass');
      if (fn && callNamed(fn, el, e, arg, arg2, pass)) {
        e.preventDefault();
        e.stopPropagation();
      } else if (fn && typeof console !== 'undefined' && console.warn) {
        console.warn('[CSP] handler ausente:', fn);
      }
    }
  });

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.getAttribute) return;
    var fn = el.getAttribute('data-csp-change');
    if (!fn) return;
    var arg = el.getAttribute('data-csp-arg');
    var pass = el.getAttribute('data-csp-pass') || 'value';
    callNamed(fn, el, e, arg, null, pass);
  });

  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (!form || !form.getAttribute) return;

    if (form.hasAttribute('data-csp-confirm')) {
      var cmsg = form.getAttribute('data-csp-confirm') || '';
      if (cmsg && !window.confirm(cmsg)) {
        e.preventDefault();
        return;
      }
    }

    if (form.hasAttribute('data-csp-submit-check')) {
      var check = form.getAttribute('data-csp-submit-check');
      if (check && typeof window[check] === 'function') {
        var ok = window[check]();
        if (ok === false) {
          e.preventDefault();
        }
      }
    }

    if (form.hasAttribute('data-csp-submit-pass-el')) {
      var fnEl = form.getAttribute('data-csp-submit-pass-el');
      if (fnEl && typeof window[fnEl] === 'function') {
        var okEl = window[fnEl](form);
        if (okEl === false) {
          e.preventDefault();
        }
      }
    }
  }, true);
})();
