<?php
require __DIR__ . '/_head.php';
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$mat = is_array($dados['matricula'] ?? null) ? $dados['matricula'] : [];
$freq = is_array($dados['frequencia'] ?? null) ? $dados['frequencia'] : [];
$periodo = is_array($dados['periodo'] ?? null) ? $dados['periodo'] : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtData = static function ($d): string {
    $d = trim((string) $d);
    if ($d === '') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '—';
};
$nome = trim((string) ($aluno['nome'] ?? ''));
$turma = trim((string) ($mat['turma_nome'] ?? $aluno['turma_nome'] ?? ''));
$ini = $fmtData($periodo['inicio'] ?? '');
$fim = $fmtData($periodo['fim'] ?? '');
$semRegistros = !empty($freq['sem_registros']);
$perc = $freq['percentual'] ?? null;
?>
        <p>
            Declaramos, para os devidos fins, que
            <span class="destaque"><?= $esc($nome) ?></span>,
            <?php if ($turma !== ''): ?>matriculado(a) na turma <span class="destaque"><?= $esc($turma) ?></span>, <?php endif; ?>
            apresentou a frequência abaixo no período de <span class="destaque"><?= $esc($ini) ?></span>
            a <span class="destaque"><?= $esc($fim) ?></span>, conforme os registros de diário de classe desta instituição.
        </p>

        <?php if ($semRegistros): ?>
            <p>Não há registros de aulas finalizadas no período informado para o cálculo de frequência.</p>
        <?php else: ?>
            <table class="dados">
                <tr><td class="label">Total de aulas registradas</td><td><?= (int) ($freq['total_aulas'] ?? 0) ?></td></tr>
                <tr><td class="label">Presenças</td><td><?= (int) ($freq['presencas'] ?? 0) ?></td></tr>
                <tr><td class="label">Faltas</td><td><?= (int) ($freq['faltas'] ?? 0) ?></td></tr>
                <tr><td class="label">Faltas justificadas</td><td><?= (int) ($freq['faltas_justificadas'] ?? 0) ?></td></tr>
                <tr><td class="label">Percentual de frequência</td><td><?= $perc !== null ? $esc(number_format((float) $perc, 1, ',', '.')) . '%' : '—' ?></td></tr>
            </table>
        <?php endif; ?>

        <p>Por ser expressão da verdade, firmamos a presente declaração.</p>
<?php
require __DIR__ . '/_foot.php';
