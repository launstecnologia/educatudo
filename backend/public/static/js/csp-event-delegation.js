/**
 * SEC-02 — delegação de cliques via data-csp-action (substitui onclick=).
 * data-csp-action="nome" → window.EducaTudoCspActions[nome](el, event)
 * Atalho: data-csp-action="toggle-menu-group" data-csp-group="academico"
 */
(function () {
  window.EducaTudoCspActions = window.EducaTudoCspActions || {};

  window.EducaTudoCspActions['toggle-menu-group'] = function (el) {
    var group = el.getAttribute('data-csp-group');
    if (group && typeof window.toggleMenuGroup === 'function') {
      window.toggleMenuGroup(group);
    }
  };

  document.addEventListener('click', function (e) {
    var el = e.target && e.target.closest ? e.target.closest('[data-csp-action]') : null;
    if (!el) return;
    var name = el.getAttribute('data-csp-action');
    if (!name || typeof window.EducaTudoCspActions[name] !== 'function') return;
    e.preventDefault();
    window.EducaTudoCspActions[name](el, e);
  });
})();
