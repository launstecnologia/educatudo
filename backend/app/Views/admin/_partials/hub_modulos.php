<?php
/**
 * Hub de módulos (cards compactos).
 *
 * Variáveis:
 * - $hub_title (string)
 * - $hub_subtitle (string)
 * - $hub_cards (list<array{href:string,title:string,description:string,icon:string,target?:string}>)
 */
$hub_title = (string) ($hub_title ?? '');
$hub_subtitle = (string) ($hub_subtitle ?? '');
$hub_cards = is_array($hub_cards ?? null) ? $hub_cards : [];

if (!class_exists('AdminSecretariaAccess')) {
    require_once dirname(__DIR__, 3) . '/Core/AdminSecretariaAccess.php';
}
if (class_exists('AdminSecretariaAccess') && AdminSecretariaAccess::isSecretaria($user ?? [])) {
    $hub_cards = array_values(array_filter($hub_cards, static function (array $card): bool {
        $href = (string) ($card['href'] ?? '');
        $path = parse_url($href, PHP_URL_PATH);
        if (!is_string($path) || $path === '') {
            $path = $href;
        }
        return AdminSecretariaAccess::requestPathIsAllowed($path);
    }));
}
?>
<style>
.hub-page-header {
    margin-bottom: 24px;
}

.hub-page-header h2 {
    margin: 0;
    font-size: 28px;
    font-weight: 650;
    color: #0f172a;
}

.hub-page-header p {
    margin: 6px 0 0;
    font-size: 15px;
    color: #64748b;
}

.hub-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 20px;
}

.hub-card {
    display: flex;
    flex-direction: column;
    padding: 20px;
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    box-shadow: 0 2px 6px rgba(15, 23, 42, 0.03);
    color: inherit;
    text-decoration: none;
    transition:
        border-color 0.2s ease,
        box-shadow 0.2s ease,
        transform 0.2s ease;
}

.hub-card:hover {
    transform: translateY(-2px);
    border-color: #cbd5e1;
    box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
}

.hub-card:focus-visible {
    outline: 2px solid #0b5ed7;
    outline-offset: 2px;
}

.hub-card-header {
    display: flex;
    align-items: center;
    gap: 12px;
}

.hub-card-icon {
    width: 22px;
    font-size: 20px;
    line-height: 1;
    color: #172554;
    text-align: center;
    flex-shrink: 0;
}

.hub-card-title {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
    color: #0f172a;
}

.hub-card-description {
    margin: 12px 0 0;
    max-width: 340px;
    font-size: 14px;
    line-height: 1.55;
    color: #64748b;
}

.hub-card-link {
    margin-top: auto;
    padding-top: 14px;
    display: inline-flex;
    align-items: center;
    width: fit-content;
    gap: 8px;
    font-size: 14px;
    font-weight: 500;
    color: #0b5ed7;
    text-decoration: none;
    transition: color 0.2s ease, gap 0.2s ease;
}

.hub-card:hover .hub-card-link {
    color: #084298;
    gap: 11px;
}

.hub-card-link i {
    font-size: 12px;
}

@media (max-width: 1199px) {
    .hub-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 767px) {
    .hub-grid {
        grid-template-columns: 1fr;
        gap: 14px;
    }

    .hub-card {
        padding: 18px;
    }
}
</style>

<div class="hub-page-header">
    <h2><?= htmlspecialchars($hub_title) ?></h2>
    <?php if ($hub_subtitle !== ''): ?>
    <p><?= htmlspecialchars($hub_subtitle) ?></p>
    <?php endif; ?>
</div>

<div class="hub-grid">
    <?php foreach ($hub_cards as $card): ?>
    <?php
        $href = (string) ($card['href'] ?? '#');
        $target = (string) ($card['target'] ?? '');
        $rel = $target === '_blank' ? 'noopener noreferrer' : '';
        $onclick = (string) ($card['onclick'] ?? '');
    ?>
    <a href="<?= htmlspecialchars($href) ?>"
       class="hub-card"
       <?php if ($target !== ''): ?>target="<?= htmlspecialchars($target) ?>"<?php endif; ?>
       <?php if ($rel !== ''): ?>rel="<?= htmlspecialchars($rel) ?>"<?php endif; ?>
       <?php if ($onclick !== ''): ?>onclick="<?= htmlspecialchars($onclick) ?>"<?php endif; ?>>
        <div class="hub-card-header">
            <i class="<?= htmlspecialchars((string) ($card['icon'] ?? '')) ?> hub-card-icon" aria-hidden="true"></i>
            <h3 class="hub-card-title"><?= htmlspecialchars((string) ($card['title'] ?? '')) ?></h3>
        </div>
        <p class="hub-card-description"><?= htmlspecialchars((string) ($card['description'] ?? '')) ?></p>
        <span class="hub-card-link">
            Acessar
            <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        </span>
    </a>
    <?php endforeach; ?>
</div>
