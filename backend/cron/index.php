<?php
/**
 * Índice da pasta cron – exibe links para os scripts.
 * Acesse /cron/rss_update.php para atualizar notícias RSS.
 */
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cron - EducaTudo</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 560px; margin: 2rem auto; padding: 1.5rem; }
        h1 { font-size: 1.5rem; margin-bottom: 1rem; }
        ul { list-style: none; padding: 0; }
        li { margin: 0.5rem 0; }
        a { color: #4f46e5; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .aviso { background: #fef3c7; border: 1px solid #f59e0b; padding: 0.75rem; border-radius: 0.5rem; margin-top: 1rem; font-size: 0.875rem; }
    </style>
</head>
<body>
    <h1>Scripts de Cron</h1>
    <p>Estes scripts devem ser executados pelo servidor (cron), não pelo navegador. Para testar:</p>
    <ul>
        <li><a href="rss_update.php">rss_update.php</a> – Atualiza notícias RSS (G1)</li>
    </ul>
    <p class="aviso">Ao acessar <strong>rss_update.php</strong> pelo navegador você verá o resultado da última execução. O ideal é agendar no cron: <code>*/15 * * * * php /caminho/cron/rss_update.php</code></p>
</body>
</html>
