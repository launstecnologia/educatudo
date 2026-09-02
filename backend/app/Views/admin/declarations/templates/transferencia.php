<?php
require __DIR__ . '/_head.php';
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$mat = is_array($dados['matricula_encerrada'] ?? null) ? $dados['matricula_encerrada'] : (is_array($dados['matricula'] ?? null) ? $dados['matricula'] : []);
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtData = static function ($d): string {
    $d = trim((string) $d);
    if ($d === '' || $d === '0000-00-00') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '—';
};
$nome = \StudentFormHelper::nomeOficialLinha($aluno);
$cpf = trim((string) ($aluno['cpf'] ?? ''));
$codigo = trim((string) ($aluno['codigo_aluno'] ?? $aluno['ra'] ?? ''));
$turma = trim((string) ($mat['turma_nome'] ?? $aluno['turma_nome'] ?? ''));
$serie = trim((string) ($mat['turma_serie'] ?? $aluno['turma_serie'] ?? $aluno['serie'] ?? ''));
$anoLetivo = trim((string) ($mat['ano_letivo'] ?? date('Y')));
$dataEntrada = $fmtData($mat['data_entrada'] ?? '');
$dataSaida = $fmtData($mat['data_saida'] ?? '');
$status = (string) ($mat['status'] ?? '');
$situacao = $status === 'concluido' ? 'Concluído' : ($status === 'transferido' ? 'Transferido' : 'Em andamento');
?>
        <p>
            Declaramos, para os devidos fins, que
            <span class="destaque"><?= $esc($nome) ?></span><?php if ($cpf !== ''): ?>, inscrito(a) no CPF sob o nº <?= $esc($cpf) ?><?php endif; ?>,
            esteve matriculado(a) nesta instituição de ensino
            <?php if ($turma !== ''): ?>na turma <span class="destaque"><?= $esc($turma) ?></span><?php endif; ?>
            <?php if ($serie !== ''): ?> (<?= $esc($serie) ?>)<?php endif; ?>,
            referente ao ano letivo de <span class="destaque"><?= $esc($anoLetivo) ?></span>,
            encontrando-se a situação de seu vínculo registrada como <span class="destaque"><?= $esc($situacao) ?></span>.
        </p>

        <table class="dados">
            <tr><td class="label">Aluno(a)</td><td><?= $esc($nome) ?></td></tr>
            <?php if ($codigo !== ''): ?><tr><td class="label">Matrícula / Código</td><td><?= $esc($codigo) ?></td></tr><?php endif; ?>
            <?php if ($cpf !== ''): ?><tr><td class="label">CPF</td><td><?= $esc($cpf) ?></td></tr><?php endif; ?>
            <?php if ($turma !== ''): ?><tr><td class="label">Turma</td><td><?= $esc($turma) ?></td></tr><?php endif; ?>
            <?php if ($serie !== ''): ?><tr><td class="label">Série</td><td><?= $esc($serie) ?></td></tr><?php endif; ?>
            <tr><td class="label">Ano letivo</td><td><?= $esc($anoLetivo) ?></td></tr>
            <tr><td class="label">Data de entrada</td><td><?= $esc($dataEntrada) ?></td></tr>
            <tr><td class="label">Data de saída</td><td><?= $esc($dataSaida) ?></td></tr>
            <tr><td class="label">Situação</td><td><?= $esc($situacao) ?></td></tr>
        </table>

        <p>Declaramos ainda que o(a) referido(a) aluno(a) está apto(a) a prosseguir seus estudos em outra instituição. Por ser expressão da verdade, firmamos a presente declaração.</p>
<?php
require __DIR__ . '/_foot.php';
