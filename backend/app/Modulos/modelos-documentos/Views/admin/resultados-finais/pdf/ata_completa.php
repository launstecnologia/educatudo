<?php
/** Reserva, se o layout visual não estiver disponível. A grade vem em $vars['tabela_html']. */
$vars = is_array($vars ?? null) ? $vars : [];
$tabela = (string) ($vars['tabela_html'] ?? '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ata completa da turma</title>
    <?php $timbre_parte = 'css'; include __DIR__ . '/_timbre.php'; ?>
</head>
<body>
    <?php $timbre_parte = 'cabecalho'; include __DIR__ . '/_timbre.php'; ?>
    <h2>Ata de resultados finais — ano letivo <?= $vars['ano_letivo'] ?? '' ?></h2>
    <p class="lead"><?= $vars['etapa'] ?? '' ?> · <?= $vars['serie'] ?? '' ?> · <?= $vars['turma_nome'] ?? '' ?> · <?= $vars['turno'] ?? '' ?></p>
    <?= $tabela ?>
    <table class="assin">
        <tr>
            <td><span><?= $vars['secretario_nome'] ?? 'Secretaria' ?><br>Secretaria escolar</span></td>
            <td><span><?= $vars['diretor_nome'] ?? 'Direção' ?><br>Direção</span></td>
        </tr>
    </table>
    <?php $timbre_parte = 'rodape'; include __DIR__ . '/_timbre.php'; ?>
</body>
</html>
