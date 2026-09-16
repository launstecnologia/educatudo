<?php
/**
 * Sub-cabeçalho visual do menu (não é link).
 * Com $menu_subcab_id vira dropdown (toggleNestedMenu).
 * @var string $menu_subcab_titulo
 * @var bool $menu_subcab_ordenar ícone para alternar ordem alfabética / de execução
 * @var string $menu_subcab_id id do nested (sem sufixo -nested/-arrow)
 * @var bool $menu_subcab_aberto
 */
$menu_subcab_titulo = (string) ($menu_subcab_titulo ?? '');
$menu_subcab_ordenar = !empty($menu_subcab_ordenar);
$menu_subcab_id = (string) ($menu_subcab_id ?? '');
$menu_subcab_aberto = !empty($menu_subcab_aberto);
if ($menu_subcab_titulo === '') {
    return;
}
$temAcoes = $menu_subcab_ordenar || $menu_subcab_id !== '';
?>
<div class="sidebar-subcab sidebar-text<?= $temAcoes ? ' sidebar-subcab-com-ordem' : '' ?>">
    <?php if ($menu_subcab_id !== ''): ?>
    <button type="button"
            class="sidebar-subcab-toggle"
            onclick="toggleNestedMenu('<?= htmlspecialchars($menu_subcab_id, ENT_QUOTES, 'UTF-8') ?>')"
            title="Expandir <?= htmlspecialchars($menu_subcab_titulo, ENT_QUOTES, 'UTF-8') ?>"
            aria-expanded="<?= $menu_subcab_aberto ? 'true' : 'false' ?>">
        <span class="text-[11px] font-medium tracking-wide text-white/80 leading-snug"><?= htmlspecialchars($menu_subcab_titulo, ENT_QUOTES, 'UTF-8') ?></span>
        <svg id="<?= htmlspecialchars($menu_subcab_id, ENT_QUOTES, 'UTF-8') ?>-arrow" class="w-3 h-3 flex-shrink-0 transition-transform duration-200" <?= $menu_subcab_aberto ? 'style="transform: rotate(180deg)"' : '' ?> fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>
    <?php else: ?>
    <p class="text-[11px] font-medium tracking-wide text-white/80 leading-snug"><?= htmlspecialchars($menu_subcab_titulo, ENT_QUOTES, 'UTF-8') ?></p>
    <?php endif; ?>
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
unset($menu_subcab_titulo, $menu_subcab_periodo, $menu_subcab_ordenar, $menu_subcab_id, $menu_subcab_aberto, $temAcoes);
?>
