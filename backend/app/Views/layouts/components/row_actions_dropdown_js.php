<?php
/**
 * JS global do dropdown de ações de linha (listagens) — ver
 * _partials/row_actions_dropdown.php (design system: prompts/admin_ui_sistema/tabela-dropdown-paginacao.md).
 *
 * Posicionamento via position:fixed calculado em JS (não absolute dentro do
 * wrapper da linha) porque a tabela tem overflow-x-auto, que corta qualquer
 * menu absolute que estoure a altura visível do contêiner com scroll.
 *
 * Incluído nos layouts admin.php e professor.php (uma vez por página).
 */
if (!empty($GLOBALS['__row_actions_dropdown_js_done'])) {
    return;
}
$GLOBALS['__row_actions_dropdown_js_done'] = true;
?>
<script>
(function () {
    function closeAllMenus() {
        document.querySelectorAll('[data-dropdown-menu]').forEach(function (m) { m.classList.add('hidden'); });
    }

    function positionMenu(toggle, menu) {
        var rect = toggle.getBoundingClientRect();
        menu.style.left = '-9999px';
        menu.style.top = '-9999px';
        menu.classList.remove('hidden');
        var menuRect = menu.getBoundingClientRect();
        var left = rect.right - menuRect.width;
        if (left < 4) left = 4;
        var top = rect.bottom + 4;
        if (top + menuRect.height > window.innerHeight - 4) {
            top = rect.top - menuRect.height - 4;
        }
        menu.style.left = left + 'px';
        menu.style.top = top + 'px';
    }

    document.addEventListener('click', function (e) {
        var toggle = e.target.closest('[data-dropdown-toggle]');
        if (toggle) {
            var wrapper = toggle.closest('[data-dropdown]');
            var menu = wrapper ? wrapper.querySelector('[data-dropdown-menu]') : null;
            if (!menu) return;
            var wasHidden = menu.classList.contains('hidden');
            closeAllMenus();
            if (wasHidden) positionMenu(toggle, menu);
            e.stopPropagation();
            return;
        }
        if (!e.target.closest('[data-dropdown-menu]')) {
            closeAllMenus();
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeAllMenus();
        }
    });
    window.addEventListener('resize', function () {
        if (document.documentElement.getAttribute('data-native-file-picker') === '1') {
            return;
        }
        closeAllMenus();
    });
    window.addEventListener('scroll', function () {
        if (document.documentElement.getAttribute('data-native-file-picker') === '1') {
            return;
        }
        closeAllMenus();
    }, true);
})();
</script>
