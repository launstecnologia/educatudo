<?php
$baseUrl = $url ?? (defined('URL') ? URL : '');
$initialData = $initialData ?? null;
$monCssPath = dirname(__DIR__, 4) . '/public/static/css/monitoramento.css';
$monJsPath  = dirname(__DIR__, 4) . '/public/static/js/monitoramento.js';
$monCss = file_exists($monCssPath) ? file_get_contents($monCssPath) : '';
$monJs  = file_exists($monJsPath)  ? file_get_contents($monJsPath)  : '';
$logoUrl = class_exists('LayoutHelper') ? LayoutHelper::getLogoHorizontalWhiteUrl() : '';
if (empty($logoUrl) && class_exists('LayoutHelper')) {
    $logoUrl = LayoutHelper::getLogoWhiteUrl() ?: LayoutHelper::getLogoHorizontalUrl() ?: LayoutHelper::getLogoUrl();
}
$escolaNome = class_exists('LayoutHelper') ? LayoutHelper::getSystemTitle() : (getenv('ESCOLA_NOME') ?: 'EducaTudo');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Monitoramento - EducaTudo</title>
    <?php if ($monCss !== ''): ?><style><?= $monCss ?></style><?php else: ?><link href="<?= htmlspecialchars($baseUrl) ?>/static/css/monitoramento.css" rel="stylesheet"><?php endif; ?>
</head>
<body class="monitoramento-dark mon-no-scroll">
    <div class="mon-header">
        <div class="mon-header-brand">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" class="mon-header-logo">
            <?php endif; ?>
            <span class="mon-header-school"><?= htmlspecialchars($escolaNome) ?></span>
        </div>
        <h1 class="mon-header-title">Dashboard de Monitoramento</h1>
        <div class="mon-live" id="mon-live">
            <span class="mon-live-dot"></span>
            <span>Live</span>
        </div>
        <div class="mon-live mon-live-off hidden" id="mon-offline">
            <span class="mon-live-dot off"></span>
            <span>Conexão perdida</span>
        </div>
        <a href="<?= htmlspecialchars($baseUrl) ?>/admin/dashboard" class="mon-back">← Voltar ao Admin</a>
    </div>

    <p id="mon-error-msg" class="mon-error hidden"></p>

    <div class="mon-main">
        <div class="mon-dashboard-grid">
            <section class="mon-card mon-card-infra">
                <h2>Infraestrutura</h2>
                <p class="mon-subtitle">CPU, RAM e disco do servidor.</p>
                <div class="mon-gauges">
                    <div class="mon-gauge">
                        <span class="mon-gauge-label">CPU</span>
                        <div class="mon-gauge-dial">
                            <div class="mon-gauge-needle" id="infra-cpu-needle"></div>
                        </div>
                        <span class="mon-gauge-value" id="infra-cpu">--</span>
                    </div>
                    <div class="mon-gauge">
                        <span class="mon-gauge-label">RAM</span>
                        <div class="mon-gauge-dial">
                            <div class="mon-gauge-needle" id="infra-ram-needle"></div>
                        </div>
                        <span class="mon-gauge-value" id="infra-ram">--</span>
                    </div>
                    <div class="mon-gauge">
                        <span class="mon-gauge-label">Disco</span>
                        <div class="mon-gauge-dial">
                            <div class="mon-gauge-needle" id="infra-disk-needle"></div>
                        </div>
                        <span class="mon-gauge-value" id="infra-disk">--</span>
                    </div>
                </div>
                <div class="mon-access-total">
                    <span class="mon-access-label">Acessos hoje (logins)</span>
                    <span class="mon-access-value" id="acessos-hoje-total">--</span>
                </div>
            </section>
            <section class="mon-card mon-card-db">
                <h2>Banco de dados</h2>
                <p class="mon-subtitle">Requisições hoje (registradas pelo MetricsService).</p>
                <div class="mon-db-stats">
                    <div class="mon-db-item">
                        <span class="mon-db-label">Requisições</span>
                        <span class="mon-db-value" id="db-queries">--</span>
                    </div>
                    <div class="mon-db-item">
                        <span class="mon-db-label">Lentas (&gt;500ms)</span>
                        <span class="mon-db-value" id="db-slow">--</span>
                    </div>
                    <div class="mon-db-item">
                        <span class="mon-db-label">Erros</span>
                        <span class="mon-db-value" id="db-errors">--</span>
                    </div>
                    <div class="mon-db-item">
                        <span class="mon-db-label">Tempo médio</span>
                        <span class="mon-db-value" id="db-avg-ms">--</span>
                    </div>
                </div>
            </section>
        </div>
    </div>

    <footer class="mon-footer">
        <?php if (!empty($logoUrl)): ?>
            <img src="<?= htmlspecialchars($logoUrl) ?>" alt="EducaTudo" class="mon-footer-logo">
        <?php endif; ?>
    </footer>

    <script>
        window.MONITORAMENTO_BASE_URL = <?= json_encode($baseUrl) ?>;
        window.MONITORAMENTO_INITIAL_DATA = <?= $initialData ? json_encode($initialData) : 'null' ?>;
    </script>
    <?php if ($monJs !== ''): ?><script><?= str_replace('</script>', '<\/script>', $monJs) ?></script><?php else: ?><script src="<?= htmlspecialchars($baseUrl) ?>/static/js/monitoramento.js"></script><?php endif; ?>
</body>
</html>
