<?php
/**
 * Logo EducaTudo fixa no canto inferior direito da tela.
 * Usa logo branca/horizontal branca do LayoutHelper ou fallback para /static/images/logo-educatudo-white.png
 */
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../Core/LayoutHelper.php';
}
$logoUrl = class_exists('LayoutHelper') ? (LayoutHelper::getLogoHorizontalWhiteUrl() ?: LayoutHelper::getLogoWhiteUrl()) : '';
if (empty($logoUrl)) {
    $logoUrl = (defined('URL') ? URL : '') . '/static/images/logo-educatudo-white.png';
}
?>
<div class="educatudo-logo-corner" aria-hidden="true">
    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="EducaTudo" class="educatudo-logo-corner-img">
</div>
<style>
.educatudo-logo-corner {
    position: fixed;
    bottom: 1rem;
    right: 1rem;
    z-index: 30;
    pointer-events: none;
    opacity: 0.9;
    padding: 0.35rem 0.5rem;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.25);
}
.educatudo-logo-corner-img {
    height: 2rem;
    width: auto;
    max-width: 120px;
    object-fit: contain;
    display: block;
}
@media (max-width: 640px) {
    .educatudo-logo-corner {
        bottom: 0.75rem;
        right: 0.75rem;
    }
    .educatudo-logo-corner-img {
        height: 1.5rem;
        max-width: 90px;
    }
}
</style>
