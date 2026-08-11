<?php
require __DIR__ . '/_head.php';
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$mat = is_array($dados['matricula'] ?? null) ? $dados['matricula'] : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtData = static function ($d): string {
    $d = trim((string) $d);
    if ($d === '' || $d === '0000-00-00') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '—';
};
$nome = trim((string) ($aluno['nome'] ?? ''));
$cpf = trim((string) ($aluno['cpf'] ?? ''));
$nasc = $fmtData($aluno['data_nasc'] ?? '');
$codigo = trim((string) ($aluno['codigo_aluno'] ?? $aluno['ra'] ?? ''));
$turma = trim((string) ($mat['turma_nome'] ?? $aluno['turma_nome'] ?? ''));
$serie = trim((string) ($mat['turma_serie'] ?? $aluno['turma_serie'] ?? $aluno['serie'] ?? ''));
$anoLetivo = trim((string) ($mat['ano_letivo'] ?? date('Y')));
$situacao = ($mat['status'] ?? 'ativa') === 'ativa' ? 'Matrícula ativa' : ucfirst((string) ($mat['status'] ?? '—'));
?>
        <p>
            Declaramos, para os devidos fins, que
            <span class="destaque"><?= $esc($nome) ?></span><?php if ($cpf !== ''): ?>, inscrito(a) no CPF sob o nº <?= $esc($cpf) ?><?php endif; ?><?php if ($nasc !== '—'): ?>, nascido(a) em <?= $esc($nasc) ?><?php endif; ?>,
            encontra-se regularmente <span class="destaque">matriculado(a)</span> nesta instituição de ensino
            <?php if ($turma !== ''): ?>na turma <span class="destaque"><?= $esc($turma) ?></span><?php endif; ?>
            <?php if ($serie !== ''): ?> (<?= $esc($serie) ?>)<?php endif; ?>,
            referente ao ano letivo de <span class="destaque"><?= $esc($anoLetivo) ?></span>.
        </p>

        <table class="dados">
            <tr><td class="label">Aluno(a)</td><td><?= $esc($nome) ?></td></tr>
            <?php if ($codigo !== ''): ?><tr><td class="label">Matrícula / Código</td><td><?= $esc($codigo) ?></td></tr><?php endif; ?>
            <?php if ($cpf !== ''): ?><tr><td class="label">CPF</td><td><?= $esc($cpf) ?></td></tr><?php endif; ?>
            <?php if ($nasc !== '—'): ?><tr><td class="label">Data de nascimento</td><td><?= $esc($nasc) ?></td></tr><?php endif; ?>
            <?php if ($turma !== ''): ?><tr><td class="label">Turma</td><td><?= $esc($turma) ?></td></tr><?php endif; ?>
            <?php if ($serie !== ''): ?><tr><td class="label">Série</td><td><?= $esc($serie) ?></td></tr><?php endif; ?>
            <tr><td class="label">Ano letivo</td><td><?= $esc($anoLetivo) ?></td></tr>
            <tr><td class="label">Situação</td><td><?= $esc($situacao) ?></td></tr>
        </table>

        <p>Por ser expressão da verdade, firmamos a presente declaração.</p>
<?php
require __DIR__ . '/_foot.php';
