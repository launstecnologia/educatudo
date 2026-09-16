<?php
/**
 * Sub-cabeçalho visual do menu (não é link).
 * @var string $menu_subcab_titulo
 * @var bool $menu_subcab_ordenar ícone para alternar ordem alfabética / de execução
 */
$menu_subcab_titulo = (string) ($menu_subcab_titulo ?? '');
$menu_subcab_ordenar = !empty($menu_subcab_ordenar);
if ($menu_subcab_titulo === '') {
    return;
}
?>
<div class="sidebar-subcab sidebar-text<?= $menu_subcab_ordenar ? ' sidebar-subcab-com-ordem' : '' ?>">
    <p class="text-[13px] font-semibold tracking-wide text-white/90 leading-snug"><?= htmlspecialchars($menu_subcab_titulo, ENT_QUOTES, 'UTF-8') ?></p>
    <?php if ($menu_subcab_ordenar): ?>
    <button type="button"
            class="sidebar-ordem-btn"
            data-sidebar-ordem
            title="Usar ordem de execução"
            aria-label="Usar ordem de execução"
            aria-pressed="true">
        <i class="fa-solid fa-arrow-down-a-z" aria-hidden="true"></i>
    </button>
    <?php endif; ?>
</div>
<?php
unset($menu_subcab_titulo, $menu_subcab_periodo, $menu_subcab_ordenar);
?>
