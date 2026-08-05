<?php
require __DIR__ . '/_head.php';
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$mat = is_array($dados['matricula'] ?? null) ? $dados['matricula'] : [];
$responsaveis = is_array($dados['responsaveis'] ?? null) ? $dados['responsaveis'] : [];
$aut = is_array($dados['aut'] ?? null) ? $dados['aut'] : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$nome = trim((string) ($aluno['nome'] ?? ''));
$turma = trim((string) ($mat['turma_nome'] ?? $aluno['turma_nome'] ?? ''));
$respNome = trim((string) ($responsaveis[0]['nome'] ?? ''));
$finalidade = trim((string) ($aut['finalidade'] ?? ''));
$linha = static fn (string $v) => $v !== '' ? $esc($v) : '________________________';
?>
        <p>
            Eu, <span class="destaque"><?= $linha($respNome) ?></span>, responsável legal pelo(a) aluno(a)
            <span class="destaque"><?= $esc($nome) ?></span><?php if ($turma !== ''): ?>, da turma <?= $esc($turma) ?><?php endif; ?>,
            <span class="destaque">autorizo</span> o uso da imagem, voz e produções escolares do(a) referido(a) aluno(a)
            pela instituição de ensino.
        </p>

        <p>
            A presente autorização abrange o uso da imagem em
            <?= $finalidade !== '' ? '<span class="destaque">' . $esc($finalidade) . '</span>' : 'materiais de divulgação, redes sociais, site, murais e registros pedagógicos' ?>,
            sem qualquer ônus para a instituição, sendo vedada a utilização para fins comerciais ou que exponham
            negativamente o(a) aluno(a).
        </p>

        <p>
            Esta autorização é concedida de forma gratuita e por prazo indeterminado, podendo ser revogada por
            escrito a qualquer momento pelo responsável legal.
        </p>
<?php
$assinaturaResponsavel = $respNome;
require __DIR__ . '/_foot_autorizacao.php';
