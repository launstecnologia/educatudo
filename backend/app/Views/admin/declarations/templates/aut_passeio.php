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
$local = trim((string) ($aut['local'] ?? ''));
$dataEvento = $fmtData($dados['data_evento'] ?? '');
$horaSaida = trim((string) ($aut['hora_saida'] ?? ''));
$horaRetorno = trim((string) ($aut['hora_retorno'] ?? ''));
$linha = static fn (string $v) => $v !== '' ? $esc($v) : '________________________';
?>
        <p>
            Eu, <span class="destaque"><?= $linha($respNome) ?></span>, responsável legal pelo(a) aluno(a)
            <span class="destaque"><?= $esc($nome) ?></span><?php if ($turma !== ''): ?>, da turma <?= $esc($turma) ?><?php endif; ?>,
            <span class="destaque">autorizo</span> a sua participação no passeio/excursão organizado(a) por esta instituição de ensino.
        </p>

        <table class="dados">
            <tr><td class="label">Destino / Local</td><td><?= $local !== '' ? $esc($local) : '&nbsp;' ?></td></tr>
            <tr><td class="label">Data</td><td><?= $dataEvento !== '' ? $esc($dataEvento) : '&nbsp;' ?></td></tr>
            <tr><td class="label">Horário de saída</td><td><?= $horaSaida !== '' ? $esc($horaSaida) : '&nbsp;' ?></td></tr>
            <tr><td class="label">Horário previsto de retorno</td><td><?= $horaRetorno !== '' ? $esc($horaRetorno) : '&nbsp;' ?></td></tr>
        </table>

        <p>
            Declaro estar ciente da programação, do meio de transporte utilizado e das orientações da instituição,
            autorizando a participação do(a) aluno(a) na atividade acima descrita.
        </p>
<?php
$assinaturaResponsavel = $respNome;
require __DIR__ . '/_foot_autorizacao.php';
