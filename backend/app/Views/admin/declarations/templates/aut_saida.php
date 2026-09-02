<?php
require __DIR__ . '/_head.php';
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$mat = is_array($dados['matricula'] ?? null) ? $dados['matricula'] : [];
$responsaveis = is_array($dados['responsaveis'] ?? null) ? $dados['responsaveis'] : [];
$aut = is_array($dados['aut'] ?? null) ? $dados['aut'] : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtData = static function ($d): string {
    $d = trim((string) $d);
    if ($d === '' || $d === '0000-00-00') return '';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '';
};
$nome = \StudentFormHelper::nomeOficialLinha($aluno);
$turma = trim((string) ($mat['turma_nome'] ?? $aluno['turma_nome'] ?? ''));
$respNome = trim((string) ($responsaveis[0]['nome'] ?? ''));
$dataEvento = $fmtData($dados['data_evento'] ?? '');
$horario = trim((string) ($aut['horario'] ?? ''));
$motivo = trim((string) ($aut['motivo'] ?? ''));
$linha = static fn (string $v) => $v !== '' ? $esc($v) : '________________________';
?>
        <p>
            Eu, <span class="destaque"><?= $linha($respNome) ?></span>, responsável legal pelo(a) aluno(a)
            <span class="destaque"><?= $esc($nome) ?></span><?php if ($turma !== ''): ?>, da turma <?= $esc($turma) ?><?php endif; ?>,
            <span class="destaque">autorizo</span> a sua saída desta instituição de ensino
            <?php if ($dataEvento !== ''): ?>no dia <span class="destaque"><?= $esc($dataEvento) ?></span><?php else: ?>no dia ____/____/______<?php endif; ?>
            <?php if ($horario !== ''): ?>, às <span class="destaque"><?= $esc($horario) ?></span><?php else: ?>, às ____:____<?php endif; ?>.
        </p>

        <p>
            Motivo: <?= $motivo !== '' ? '<span class="destaque">' . $esc($motivo) . '</span>' : '____________________________________________________' ?>.
        </p>

        <p>
            Declaro estar ciente de que, a partir do horário autorizado, a responsabilidade sobre o(a) aluno(a)
            passa a ser do responsável legal.
        </p>
<?php
$assinaturaResponsavel = $respNome;
require __DIR__ . '/_foot_autorizacao.php';
