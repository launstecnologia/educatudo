<?php
require __DIR__ . '/_head.php';
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$mat = is_array($dados['matricula'] ?? null) ? $dados['matricula'] : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtData = static function ($d): string {
    $d = trim((string) $d);
    if ($d === '') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '—';
};
$nome = \StudentFormHelper::nomeOficialLinha($aluno);
$turma = trim((string) ($mat['turma_nome'] ?? $aluno['turma_nome'] ?? ''));
$dataComp = $fmtData($dados['data_comparecimento'] ?? '');
$periodoTexto = trim((string) ($dados['periodo_texto'] ?? ''));
?>
        <p>
            Declaramos, para os devidos fins, que
            <span class="destaque"><?= $esc($nome) ?></span>,
            <?php if ($turma !== ''): ?>matriculado(a) na turma <span class="destaque"><?= $esc($turma) ?></span>, <?php endif; ?>
            compareceu a esta instituição de ensino no dia <span class="destaque"><?= $esc($dataComp) ?></span><?php if ($periodoTexto !== ''): ?>, no período <span class="destaque"><?= $esc($periodoTexto) ?></span><?php endif; ?>.
        </p>

        <table class="dados">
            <tr><td class="label">Aluno(a)</td><td><?= $esc($nome) ?></td></tr>
            <?php if ($turma !== ''): ?><tr><td class="label">Turma</td><td><?= $esc($turma) ?></td></tr><?php endif; ?>
            <tr><td class="label">Data do comparecimento</td><td><?= $esc($dataComp) ?></td></tr>
            <?php if ($periodoTexto !== ''): ?><tr><td class="label">Período / Horário</td><td><?= $esc($periodoTexto) ?></td></tr><?php endif; ?>
        </table>

        <p>Por ser expressão da verdade, firmamos a presente declaração.</p>
<?php
require __DIR__ . '/_foot.php';
