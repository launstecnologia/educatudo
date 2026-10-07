<?php
/** Ficha individual do aluno. Variáveis: $payload, $vars, $esc. */
$payload = is_array($payload ?? null) ? $payload : [];
$vars = is_array($vars ?? null) ? $vars : [];
$quadro = $vars['quadro_notas_html'] ?? '';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Ficha individual</title>
    <?php $timbre_parte = 'css'; include __DIR__ . '/_timbre.php'; ?>
</head>
<body>
    <?php $timbre_parte = 'cabecalho'; include __DIR__ . '/_timbre.php'; ?>
    <h2>Ficha individual do aluno</h2>
    <p class="lead">Ano letivo <?= $vars['ano_letivo'] ?? '' ?> · <?= $vars['turma_nome'] ?? '' ?></p>

    <table class="dados">
        <tr><td class="label">Aluno(a)</td><td><?= $vars['aluno_nome'] ?? '' ?></td><td class="label">RA</td><td><?= $vars['aluno_ra'] ?? '—' ?></td></tr>
        <tr><td class="label">Nascimento</td><td><?= $vars['aluno_data_nasc'] ?? '—' ?></td><td class="label">Naturalidade</td><td><?= $vars['aluno_naturalidade'] ?? '—' ?></td></tr>
        <tr><td class="label">Filiação</td><td colspan="3"><?= $vars['aluno_filiacao'] ?? '—' ?></td></tr>
        <tr><td class="label">Curso / etapa</td><td><?= $vars['etapa'] ?? '—' ?></td><td class="label">Série</td><td><?= $vars['serie'] ?? '—' ?></td></tr>
        <tr><td class="label">Turma</td><td><?= $vars['turma_nome'] ?? '' ?></td><td class="label">Turno</td><td><?= $vars['turno'] ?? '—' ?></td></tr>
        <tr><td class="label">Frequência</td><td><?= $vars['frequencia_percentual'] ?? '—' ?></td><td class="label">Resultado</td><td><?= $vars['situacao_final'] ?? '—' ?></td></tr>
    </table>

    <h2>Componentes curriculares</h2>
    <?= $quadro ?>

    <h2>Observações</h2>
    <p><?= !empty($vars['observacoes']) ? $vars['observacoes'] : '—' ?></p>

    <table class="assin">
        <tr>
            <td><span><?= $vars['secretario_nome'] ?? 'Secretaria' ?><br>Secretaria</span></td>
            <td><span><?= $vars['diretor_nome'] ?? 'Direção' ?><br>Direção</span></td>
        </tr>
    </table>
    <?php $timbre_parte = 'rodape'; include __DIR__ . '/_timbre.php'; ?>
</body>
</html>
