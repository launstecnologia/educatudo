<?php
/**
 * Shell de offcanvas / drawer lateral (direita).
 *
 * Variáveis:
 * - $ui_offcanvas_id (string) — prefixo dos ids (ex.: "demo" → demoDrawer, demoDrawerBackdrop)
 * - $ui_offcanvas_titulo (string)
 * - $ui_offcanvas_body (string HTML) — conteúdo do painel (form, filtros…)
 * - $ui_offcanvas_max_w (string, opcional) — classe max-width Tailwind (default max-w-3xl)
 * - $ui_offcanvas_include_js (bool, default true) — registra open/close helpers
 *
 * Helpers JS gerados (se include_js):
 *   open{Id}Drawer() / close{Id}Drawer()  — Id = ucfirst do id sanitizado
 */
$ui_offcanvas_id = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($ui_offcanvas_id ?? 'drawer'));
if ($ui_offcanvas_id === '') {
    $ui_offcanvas_id = 'drawer';
}
$ui_offcanvas_titulo = (string) ($ui_offcanvas_titulo ?? '');
$ui_offcanvas_body = (string) ($ui_offcanvas_body ?? '');
$ui_offcanvas_max_w = (string) ($ui_offcanvas_max_w ?? 'max-w-3xl');
$ui_offcanvas_include_js = (bool) ($ui_offcanvas_include_js ?? true);

$drawerId = $ui_offcanvas_id . 'Drawer';
$backdropId = $ui_offcanvas_id . 'DrawerBackdrop';
$titleId = $ui_offcanvas_id . 'DrawerTitle';
$fnSuffix = preg_replace('/[^a-zA-Z0-9]/', '', ucwords(str_replace(['-', '_'], ' ', $ui_offcanvas_id)));
$openFn = 'open' . $fnSuffix . 'Drawer';
$closeFn = 'close' . $fnSuffix . 'Drawer';
?>
<div id="<?= htmlspecialchars($backdropId) ?>" class="fixed inset-0 bg-black/40 z-40 hidden" onclick="<?= htmlspecialchars($closeFn) ?>()"></div>
<aside id="<?= htmlspecialchars($drawerId) ?>"
       class="fixed top-0 right-0 h-full w-full <?= htmlspecialchars($ui_offcanvas_max_w) ?> bg-white shadow-2xl z-50 transform translate-x-full transition-transform duration-300 ease-in-out flex flex-col"
       aria-hidden="true"
       role="dialog"
       aria-labelledby="<?= htmlspecialchars($titleId) ?>">
    <div class="flex items-center justify-between px-6 sm:px-8 py-5 border-b border-gray-200">
        <h2 id="<?= htmlspecialchars($titleId) ?>" class="text-xl font-bold text-gray-900"><?= htmlspecialchars($ui_offcanvas_titulo) ?></h2>
        <button type="button" onclick="<?= htmlspecialchars($closeFn) ?>()" class="text-gray-400 hover:text-gray-600 p-1" aria-label="Fechar">
            <i class="fa-solid fa-xmark text-xl"></i>
        </button>
    </div>
    <div class="flex flex-col flex-1 overflow-hidden">
        <?= $ui_offcanvas_body ?>
    </div>
</aside>
<?php if ($ui_offcanvas_include_js): ?>
<script>
(function () {
    window.<?= $openFn ?> = function () {
        var drawer = document.getElementById(<?= json_encode($drawerId) ?>);
        var backdrop = document.getElementById(<?= json_encode($backdropId) ?>);
        if (!drawer || !backdrop) return;
        backdrop.classList.remove('hidden');
        drawer.classList.remove('translate-x-full');
        drawer.setAttribute('aria-hidden', 'false');
        document.body.classList.add('overflow-hidden');
    };
    window.<?= $closeFn ?> = function () {
        var drawer = document.getElementById(<?= json_encode($drawerId) ?>);
        var backdrop = document.getElementById(<?= json_encode($backdropId) ?>);
        if (!drawer || !backdrop) return;
        drawer.classList.add('translate-x-full');
        drawer.setAttribute('aria-hidden', 'true');
        backdrop.classList.add('hidden');
        document.body.classList.remove('overflow-hidden');
    };
})();
</script>
<?php endif; ?>
<?php
unset(
    $ui_offcanvas_id, $ui_offcanvas_titulo, $ui_offcanvas_body, $ui_offcanvas_max_w,
    $ui_offcanvas_include_js, $drawerId, $backdropId, $titleId, $fnSuffix, $openFn, $closeFn
);
?>
