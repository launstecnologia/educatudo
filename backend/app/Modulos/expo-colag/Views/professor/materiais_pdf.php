<?php
$projeto = is_array($projeto ?? null) ? $projeto : [];
$itens = is_array($itens ?? null) ? $itens : [];
$escola = (string) ($escola ?? 'Escola');
$logoUrl = (string) ($logo_url ?? '');
$user = is_array($user ?? null) ? $user : [];
$geradoEm = (string) ($geradoEm ?? date('d/m/Y H:i'));
$professor = (string) ($projeto['professor_nome'] ?? $user['nome'] ?? '—');
$logoData = '';
if ($logoUrl !== '') {
    $path = (string) (parse_url($logoUrl, PHP_URL_PATH) ?: $logoUrl);
    if (strpos($path, '/uploads/') === 0 || strpos($path, '/public/uploads/') === 0 || strpos($path, '/assets/') === 0) {
        $rel = ltrim(preg_replace('#^/public/#', '', $path) ?? $path, '/');
        $full = (defined('BASE_PATH') ? rtrim((string) BASE_PATH, '/') : dirname(__DIR__, 5)) . '/public/' . $rel;
        if (is_file($full) && is_readable($full)) {
            $mime = function_exists('mime_content_type') ? (string) mime_content_type($full) : 'image/png';
            $bin = @file_get_contents($full);
            if (is_string($bin) && $bin !== '') {
                $logoData = 'data:' . $mime . ';base64,' . base64_encode($bin);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Solicitação de materiais — <?= htmlspecialchars($projeto['titulo'] ?? '') ?></title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 11pt; color: #1e293b; }
        h1 { font-size: 16pt; margin: 0 0 4px; }
        h2 { font-size: 12pt; margin: 18px 0 8px; border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; }
        .muted { color: #64748b; font-size: 9pt; }
        .header { display: table; width: 100%; margin-bottom: 14px; }
        .header-left { display: table-cell; vertical-align: middle; }
        .header-right { display: table-cell; vertical-align: middle; text-align: right; }
        .logo { max-height: 44px; max-width: 160px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #94a3b8; padding: 6px 8px; text-align: left; vertical-align: top; }
        th { background: #e2e8f0; font-size: 9pt; }
        .assinaturas { margin-top: 36px; width: 100%; }
        .assinaturas td { border: 0; width: 50%; height: 90px; vertical-align: bottom; padding: 0 12px; }
        .linha { border-top: 1px solid #334155; padding-top: 6px; font-size: 9pt; text-align: center; }
        .box { border: 1px solid #cbd5e1; padding: 8px 10px; margin-top: 10px; font-size: 9pt; }
    </style>
</head>
<body>
    <div class="header">
        <div class="header-left">
            <p class="muted"><?= htmlspecialchars($escola) ?> · Expo Colag</p>
        </div>
        <?php if ($logoData !== ''): ?>
            <div class="header-right">
                <img src="<?= htmlspecialchars($logoData) ?>" class="logo" alt="">
            </div>
        <?php endif; ?>
    </div>
    <h1>Solicitação de materiais — almoxarifado</h1>
    <p class="muted">Gerado em <?= htmlspecialchars($geradoEm) ?></p>

    <p>
        <strong>Projeto:</strong> <?= htmlspecialchars($projeto['titulo'] ?? '—') ?><br>
        <strong>Professor responsável:</strong> <?= htmlspecialchars($professor) ?>
    </p>

    <h2>Materiais necessários</h2>
    <?php if ($itens === []): ?>
        <p>Nenhum item listado.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th style="width:6%">#</th>
                    <th style="width:46%">Material</th>
                    <th style="width:18%">Quantidade</th>
                    <th>Observação</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($itens as $i => $item): ?>
                    <tr>
                        <td><?= (int) $i + 1 ?></td>
                        <td><?= htmlspecialchars($item['nome'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['quantidade'] ?? '') ?></td>
                        <td><?= htmlspecialchars($item['observacao'] ?? '') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <div class="box">
        Após a autorização da coordenação, o professor retira os materiais no almoxarifado
        apresentando esta via.
    </div>

    <table class="assinaturas">
        <tr>
            <td>
                <div class="linha">
                    Coordenação — autorizo o fornecimento<br>
                    Data: ____/____/________
                </div>
            </td>
            <td>
                <div class="linha">
                    Almoxarifado — materiais retirados<br>
                    Data: ____/____/________
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
