<?php
require __DIR__ . '/_head.php';
$aluno = is_array($dados['aluno'] ?? null) ? $dados['aluno'] : [];
$mat = is_array($dados['matricula'] ?? null) ? $dados['matricula'] : [];
$responsaveis = is_array($dados['responsaveis'] ?? null) ? $dados['responsaveis'] : [];
$esc = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
$fmtData = static function ($d): string {
    $d = trim((string) $d);
    if ($d === '' || $d === '0000-00-00') return '—';
    $dt = DateTime::createFromFormat('Y-m-d', substr($d, 0, 10));
    return $dt ? $dt->format('d/m/Y') : '—';
};
$val = static function ($v): string {
    $v = trim((string) $v);
    return $v !== '' ? $v : '—';
};
$pick = static function (array $arr, array $keys): string {
    foreach ($keys as $k) {
        if (isset($arr[$k]) && trim((string) $arr[$k]) !== '') {
            return trim((string) $arr[$k]);
        }
    }
    return '';
};
$sexoLabels = ['M' => 'Masculino', 'F' => 'Feminino', 'N' => 'Neutro / outro'];

$nome = trim((string) ($aluno['nome'] ?? ''));
$cpf = $val($aluno['cpf'] ?? '');
$rg = $val($aluno['rg'] ?? '');
$nasc = $fmtData($aluno['data_nasc'] ?? '');
$sexo = $sexoLabels[(string) ($aluno['sexo'] ?? '')] ?? '—';
$codigo = $val($pick($aluno, ['codigo_aluno', 'ra']));
$turma = $val($pick(array_merge($aluno, $mat), ['turma_nome']));
$serie = $val($pick(array_merge($aluno, $mat), ['turma_serie', 'serie']));
$anoLetivo = $val($mat['ano_letivo'] ?? date('Y'));
$telefone = $val($pick($aluno, ['celular', 'telefone']));
$email = $val($aluno['email'] ?? '');
$nomeMae = $val($pick($aluno, ['nome_mae', 'mae']));
$nomePai = $val($pick($aluno, ['nome_pai', 'pai']));
$naturalidade = $val($aluno['naturalidade'] ?? '');
$nacionalidade = $val($aluno['nacionalidade'] ?? '');

$endereco = trim(implode(', ', array_filter([
    $pick($aluno, ['logradouro', 'endereco', 'endereco_logradouro'])
        . ($pick($aluno, ['numero', 'endereco_numero']) !== '' ? ', ' . $pick($aluno, ['numero', 'endereco_numero']) : ''),
    $pick($aluno, ['complemento', 'endereco_complemento']),
    $pick($aluno, ['bairro', 'endereco_bairro']),
    trim($pick($aluno, ['cidade', 'endereco_cidade']) . ($pick($aluno, ['uf', 'estado', 'endereco_uf']) !== '' ? ' / ' . $pick($aluno, ['uf', 'estado', 'endereco_uf']) : '')),
    $pick($aluno, ['cep', 'endereco_cep']) !== '' ? 'CEP ' . $pick($aluno, ['cep', 'endereco_cep']) : '',
])));
$endereco = $endereco !== '' ? $endereco : '—';
?>
        <p style="text-align:center; font-size:10pt; color:#4b5563; margin-top:-16px;">
            Ano letivo <span class="destaque"><?= $esc($anoLetivo) ?></span>
        </p>

        <h2 style="font-size:11pt; color:#064e3b; margin:18px 0 6px 0;">1. Dados do(a) Aluno(a)</h2>
        <table class="dados">
            <tr><td class="label">Nome completo</td><td><?= $esc($nome) ?></td></tr>
            <tr><td class="label">Matrícula / Código</td><td><?= $esc($codigo) ?></td></tr>
            <tr><td class="label">Data de nascimento</td><td><?= $esc($nasc) ?></td></tr>
            <tr><td class="label">Sexo</td><td><?= $esc($sexo) ?></td></tr>
            <tr><td class="label">CPF</td><td><?= $esc($cpf) ?></td></tr>
            <tr><td class="label">RG</td><td><?= $esc($rg) ?></td></tr>
            <tr><td class="label">Naturalidade</td><td><?= $esc($naturalidade) ?></td></tr>
            <tr><td class="label">Nacionalidade</td><td><?= $esc($nacionalidade) ?></td></tr>
            <tr><td class="label">Turma</td><td><?= $esc($turma) ?></td></tr>
            <tr><td class="label">Série</td><td><?= $esc($serie) ?></td></tr>
            <tr><td class="label">Endereço</td><td><?= $esc($endereco) ?></td></tr>
            <tr><td class="label">Telefone</td><td><?= $esc($telefone) ?></td></tr>
            <tr><td class="label">E-mail</td><td><?= $esc($email) ?></td></tr>
        </table>

        <h2 style="font-size:11pt; color:#064e3b; margin:18px 0 6px 0;">2. Filiação / Responsáveis</h2>
        <table class="dados">
            <tr><td class="label">Nome da mãe</td><td><?= $esc($nomeMae) ?></td></tr>
            <tr><td class="label">Nome do pai</td><td><?= $esc($nomePai) ?></td></tr>
        </table>
        <?php if (!empty($responsaveis)): ?>
        <table class="dados">
            <tr>
                <td class="label">Responsável</td>
                <td class="label" style="width:24%;">CPF</td>
                <td class="label" style="width:24%;">Contato</td>
            </tr>
            <?php foreach ($responsaveis as $r): ?>
            <tr>
                <td>
                    <?= $esc($val($r['nome'] ?? '')) ?>
                    <?php if ((int) ($r['is_financeiro'] ?? 0) === 1): ?>
                        <span style="font-size:8pt; color:#6d28d9;">(financeiro)</span>
                    <?php endif; ?>
                </td>
                <td><?= $esc($val($r['cpf'] ?? '')) ?></td>
                <td><?= $esc($val($r['telefone'] ?? ($r['email'] ?? ''))) ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        <?php endif; ?>

        <p style="margin-top:18px;">
            Declaro que as informações acima são verdadeiras e estou ciente das normas da instituição de ensino.
        </p>
<?php
require __DIR__ . '/_foot.php';
