<?php
require __DIR__ . '/_head.php';
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$mat = is_array($dados['matricula'] ?? null) ? $dados['matricula'] : [];
$responsaveis = is_array($dados['responsaveis'] ?? null) ? $dados['responsaveis'] : [];
$aut = is_array($dados['aut'] ?? null) ? $dados['aut'] : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nome = \StudentFormHelper::nomeOficialLinha($aluno);
$turma = trim((string) ($mat['turma_nome'] ?? $aluno['turma_nome'] ?? ''));
$respNome = trim((string) ($responsaveis[0]['nome'] ?? ''));
$autorizado = trim((string) ($aut['nome_autorizado'] ?? ''));
$documento = trim((string) ($aut['documento'] ?? ''));
$parentesco = trim((string) ($aut['parentesco'] ?? ''));
$linha = static fn (string $v) => $v !== '' ? $esc($v) : '________________________';
?>
        <p>
            Eu, <span class="destaque"><?= $linha($respNome) ?></span>, responsável legal pelo(a) aluno(a)
            <span class="destaque"><?= $esc($nome) ?></span><?php if ($turma !== ''): ?>, da turma <?= $esc($turma) ?><?php endif; ?>,
            <span class="destaque">autorizo</span> a pessoa abaixo identificada a retirar o(a) referido(a) aluno(a) desta instituição de ensino.
        </p>

        <table class="dados">
            <tr><td class="label">Pessoa autorizada</td><td><?= $autorizado !== '' ? $esc($autorizado) : '&nbsp;' ?></td></tr>
            <tr><td class="label">Documento (RG/CPF)</td><td><?= $documento !== '' ? $esc($documento) : '&nbsp;' ?></td></tr>
            <tr><td class="label">Grau de parentesco / vínculo</td><td><?= $parentesco !== '' ? $esc($parentesco) : '&nbsp;' ?></td></tr>
        </table>

        <p>
            Declaro estar ciente de que a instituição somente liberará o(a) aluno(a) mediante a apresentação de
            documento de identificação da pessoa autorizada, e que assumo total responsabilidade por esta autorização.
        </p>
<?php
$assinaturaResponsavel = $respNome;
require __DIR__ . '/_foot_autorizacao.php';
