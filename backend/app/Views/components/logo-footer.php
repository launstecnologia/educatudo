<?php
/**
 * Logo EducaTudo para o footer.
 * Usa logo horizontal (ou branca com fundo escuro) do LayoutHelper.
 */
if (!class_exists('LayoutHelper')) {
    require_once __DIR__ . '/../../Core/LayoutHelper.php';
}
$logoUrl = '';
if (class_exists('LayoutHelper')) {
    $logoUrl = LayoutHelper::getLogoHorizontalUrl() ?: LayoutHelper::getLogoUrl();
    if (empty($logoUrl)) {
        $logoUrl = LayoutHelper::getLogoHorizontalWhiteUrl() ?: LayoutHelper::getLogoWhiteUrl();
    }
}
if (empty($logoUrl)) {
    $logoUrl = (defined('URL') ? URL : '') . '/static/images/logo-educatudo-white.png';
}
$isWhiteLogo = (strpos($logoUrl, 'logo-educatudo-white') !== false);
?>
<div class="educatudo-footer-logo <?= $isWhiteLogo ? 'educatudo-footer-logo-dark' : '' ?>">>
    <img src="<?= htmlspecialchars($logoUrl) ?>" alt="EducaTudo" class="educatudo-footer-logo-img">
</div>
<style>
.educatudo-footer-logo {
    margin-bottom: 0.5rem;
}
.educatudo-footer-logo-img {
    height: 1.75rem;
    width: auto;
    max-width: 100px;
    object-fit: contain;
    display: inline-block;
    vertical-align: middle;
}
.educatudo-footer-logo-dark {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    border-radius: 6px;
    background: rgba(0, 0, 0, 0.2);
}
</style>
