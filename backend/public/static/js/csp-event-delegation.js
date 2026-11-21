/**
 * SEC-02 — delegação de eventos (substitui onclick/onchange inline).
 *
 * data-csp-action="toggle-menu-group" data-csp-group="academico"
 * data-csp-click="closeFilterDrawer"
 * data-csp-click="togglePassword" data-csp-arg="senha"
 * data-csp-change="nomeFuncao"  (dispara no change do próprio elemento)
 */
(function () {
  window.EducaTudoCspActions = window.EducaTudoCspActions || {};

  function callNamed(name, el, ev, arg) {
    if (typeof window.EducaTudoCspActions[name] === 'function') {
      window.EducaTudoCspActions[name](el, ev, arg);
      return true;
    }
    if (typeof window[name] === 'function') {
      if (arg !== null && arg !== undefined && arg !== '') {
        window[name](arg);
      } else {
        window[name]();
      }
      return true;
    }
    return false;
  }

  // data-csp-arg chega como string; funções que esperam número continuam ok (JS coerção).

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
    var el = e.target && e.target.closest ? e.target.closest('[data-csp-action],[data-csp-click]') : null;
    if (!el) return;

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
      if (fn && callNamed(fn, el, e, arg)) {
        e.preventDefault();
      }
    }
  });

  document.addEventListener('change', function (e) {
    var el = e.target;
    if (!el || !el.getAttribute) return;
    var fn = el.getAttribute('data-csp-change');
    if (!fn) return;
    var arg = el.getAttribute('data-csp-arg');
    callNamed(fn, el, e, arg);
  });
})();
